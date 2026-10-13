<?php
/**
 * inc/qpay.php — QPay Merchant V2 API client.
 *
 * Auth is HTTP Basic (username:password) against /v2/auth/token, returning a
 * bearer token used for subsequent calls. QPay asks merchants to reuse the
 * token until it expires rather than re-authenticating on every request, so
 * qpay_token() caches it in a small JSON file OUTSIDE the web root (next to
 * config.php; falls back to the system temp dir if that isn't writable).
 * NOTE: QPay's `expires_in` is an absolute Unix timestamp, not seconds — a
 * deviation from OAuth. If QPay ever answers 401, the cache is dropped so the
 * next request re-authenticates.
 *
 * The request functions throw RuntimeException on any failure (except
 * qpay_cancel_invoice, which returns a status) — callers must catch and
 * translate to a user-facing error, never let this surface raw.
 *
 * config.php should live ONE DIRECTORY ABOVE public_html (outside the web
 * root) so it can never be served over HTTP, no matter how the server is
 * configured. This checks that location first and falls back to the old
 * in-webroot spot only if it hasn't been moved yet — safe during migration.
 */

require_once (file_exists(dirname(__DIR__, 2) . '/config.php'))
  ? dirname(__DIR__, 2) . '/config.php'   // above public_html — where it belongs
  : dirname(__DIR__) . '/config.php';     // public_html — not moved yet

function qpay_token_cache_path(): string {
  // Two levels up = the folder above public_html (this file is in public_html/inc/).
  $dir = is_writable(dirname(__DIR__, 2)) ? dirname(__DIR__, 2) : sys_get_temp_dir();
  // Keyed by endpoint + username so a sandbox token is never reused in production.
  return $dir . '/naf-qpay-token-' . md5(NAF_QPAY_BASE_URL . '|' . NAF_QPAY_USERNAME) . '.json';
}

function qpay_forget_token(): void {
  @unlink(qpay_token_cache_path());
}

function qpay_token(): string {
  $path = qpay_token_cache_path();
  $fh = @fopen($path, 'c+');
  if ($fh) flock($fh, LOCK_EX); // one request refreshes; concurrent ones wait and reuse
  try {
    if ($fh) {
      $cached = json_decode((string)stream_get_contents($fh), true);
      if (is_array($cached) && isset($cached['access_token'], $cached['expires_at'])
          && time() < (int)$cached['expires_at'] - 60) {
        return $cached['access_token'];
      }
    }
    $data = qpay_fetch_token();
    $expiresAt = (int)($data['expires_in'] ?? 0);
    if ($expiresAt > 0 && $expiresAt < 1000000000) $expiresAt += time(); // tolerate relative seconds
    if ($fh && $expiresAt > time() + 60) {
      ftruncate($fh, 0);
      rewind($fh);
      fwrite($fh, json_encode(['access_token' => $data['access_token'], 'expires_at' => $expiresAt]));
      fflush($fh);
      @chmod($path, 0600);
    }
    return $data['access_token'];
  } finally {
    if ($fh) {
      flock($fh, LOCK_UN);
      fclose($fh);
    }
  }
}

function qpay_fetch_token(): array {
  $ch = curl_init(NAF_QPAY_BASE_URL . '/v2/auth/token');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '',
    CURLOPT_USERPWD => NAF_QPAY_USERNAME . ':' . NAF_QPAY_PASSWORD,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 15,
  ]);
  $res = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($res === false) throw new RuntimeException('QPay auth request failed: ' . $err);
  if ($status !== 200) throw new RuntimeException('QPay auth failed: HTTP ' . $status);
  $data = json_decode($res, true);
  if (!isset($data['access_token'])) throw new RuntimeException('QPay auth response missing access_token');
  return $data;
}

/**
 * Creates an itemised invoice: one line per basket line, so the buyer's bank
 * app lists what they are paying for, and so eBarimt can later carry per-line
 * product codes (classification_code / tax_product_code go on these lines).
 * QPay totals the lines itself; the caller checks it matches our total.
 *
 * $lines: [['description' => string, 'quantity' => int, 'unit_price' => int], ...]
 *
 * No QPay-side expiry is set: its expiry_date is unreliable in testing (the
 * sandbox stores the creation time whatever is sent). Expired orders cancel
 * their invoice instead — see orders_expire_stale() in inc/orders.php.
 */
function qpay_create_invoice(string $token, string $senderInvoiceNo, array $lines, string $callbackUrl, string $description): array {
  $qpLines = [];
  foreach ($lines as $l) {
    $qpLines[] = [
      'line_description' => mb_substr((string)$l['description'], 0, 255, 'UTF-8'),
      'line_quantity' => number_format((float)$l['quantity'], 2, '.', ''),
      'line_unit_price' => number_format((float)$l['unit_price'], 2, '.', ''),
    ];
  }
  $ch = curl_init(NAF_QPAY_BASE_URL . '/v2/invoice');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
    CURLOPT_POSTFIELDS => json_encode([
      'invoice_code' => NAF_QPAY_INVOICE_CODE,
      'sender_invoice_no' => $senderInvoiceNo,
      'invoice_receiver_code' => 'terminal',
      'invoice_description' => mb_substr($description, 0, 255, 'UTF-8'),
      'callback_url' => $callbackUrl,
      'lines' => $qpLines,
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 15,
  ]);
  $res = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($res === false) throw new RuntimeException('QPay invoice request failed: ' . $err);
  if ($status === 401) qpay_forget_token();
  if ($status !== 200) throw new RuntimeException('QPay invoice creation failed: HTTP ' . $status . ' ' . substr((string)$res, 0, 200));
  $data = json_decode($res, true);
  if (!isset($data['invoice_id'])) throw new RuntimeException('QPay invoice response missing invoice_id');
  return $data;
}

/**
 * Cancels an invoice so its QR stops accepting payment.
 * Returns 'cancelled' (also when it was already cancelled or never existed —
 * nothing payable either way), 'paid' (too late: the buyer already paid), or
 * 'error' (QPay unreachable or another problem — try again later).
 * Short timeout: this runs inside other requests.
 */
function qpay_cancel_invoice(string $token, string $invoiceId): string {
  $ch = curl_init(NAF_QPAY_BASE_URL . '/v2/invoice/' . rawurlencode($invoiceId));
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    CURLOPT_TIMEOUT => 6,
  ]);
  $res = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($res === false) return 'error';
  if ($status === 200) return 'cancelled';
  if ($status === 401) { qpay_forget_token(); return 'error'; }
  $key = (string)((json_decode((string)$res, true) ?: [])['error'] ?? '');
  if ($key === 'INVOICE_ALREADY_CANCELED' || $key === 'INVOICE_NOTFOUND') return 'cancelled';
  if ($key === 'INVOICE_PAID') return 'paid';
  return 'error';
}

/**
 * Trims an invoice's `urls` (bank-app deep links, ~9 KB raw) to
 * [{name, logo, link}] for the phone checkout. `name` is QPay's Mongolian
 * `description` (e.g. "Хаан банк"). Links must be an app scheme (khanbank://…)
 * and logos https — anything else is dropped, since these end up in an href/src.
 */
function qpay_bank_links(array $invoice): array {
  $out = [];
  foreach (($invoice['urls'] ?? []) as $u) {
    if (!is_array($u)) continue;
    $link = (string)($u['link'] ?? '');
    $logo = (string)($u['logo'] ?? '');
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $link) || preg_match('~^(javascript|data|vbscript):~i', $link)) continue;
    if (strpos($logo, 'https://') !== 0) $logo = '';
    $name = trim((string)($u['description'] ?? '')) ?: trim((string)($u['name'] ?? ''));
    if ($name === '') continue;
    $out[] = ['name' => $name, 'logo' => $logo, 'link' => $link];
  }
  return $out;
}

function qpay_check_payment(string $token, string $invoiceId): array {
  $ch = curl_init(NAF_QPAY_BASE_URL . '/v2/payment/check');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
    CURLOPT_POSTFIELDS => json_encode([
      'object_type' => 'INVOICE',
      'object_id' => $invoiceId,
      'offset' => ['page_number' => 1, 'page_limit' => 100],
    ]),
    CURLOPT_TIMEOUT => 15,
  ]);
  $res = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($res === false) throw new RuntimeException('QPay payment check request failed: ' . $err);
  if ($status === 401) qpay_forget_token();
  if ($status !== 200) throw new RuntimeException('QPay payment check failed: HTTP ' . $status);
  $data = json_decode($res, true);
  if (!is_array($data) || !isset($data['count'], $data['rows'])) {
    throw new RuntimeException('QPay payment check response missing count/rows');
  }
  return $data;
}

/**
 * Sums the settled payment amount from a qpay_check_payment() response.
 *
 * NOTE: the PAID response shape (paid_amount, rows[].payment_amount,
 * rows[].payment_status) was implemented from researched but NOT
 * live-verified QPay documentation — only the count:0/rows:[] "unpaid" shape
 * has been live-verified against the sandbox. This MUST be re-confirmed
 * against a real completed sandbox payment before config.php is switched to
 * production QPay credentials.
 * Update 2026-10-08: field names cross-checked against the qpay-client
 * library's v2 schema — top-level count / paid_amount / rows[], and
 * rows[].payment_status ∈ NEW, FAILED, PAID, PARTIAL, REFUND. Still confirm
 * with one small real payment after going live.
 *
 * Favors false negatives: prefers the top-level paid_amount when present and
 * numeric, otherwise sums rows[].payment_amount. A row with an explicit
 * payment_status is only counted if that status case-insensitively contains
 * "PAID" or "SUCCESS"; a row with no payment_status key at all is counted
 * toward the sum regardless (the field's presence/name is the uncertain
 * part, so its absence is not treated as a failure signal).
 */
function qpay_paid_amount(array $check): float {
  if (isset($check['paid_amount']) && is_numeric($check['paid_amount'])) {
    return (float)$check['paid_amount'];
  }
  $sum = 0.0;
  foreach (($check['rows'] ?? []) as $row) {
    if (!is_array($row)) continue;
    if (isset($row['payment_status'])) {
      $status = strtoupper((string)$row['payment_status']);
      if (strpos($status, 'PAID') === false && strpos($status, 'SUCCESS') === false) {
        continue;
      }
    }
    $sum += (float)($row['payment_amount'] ?? 0);
  }
  return $sum;
}
