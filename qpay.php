<?php
/**
 * qpay.php — QPay Merchant V2 API client.
 *
 * Auth is HTTP Basic (username:password) against /v2/auth/token, returning a
 * bearer token used for subsequent calls. The token is not cached across
 * requests — each PHP request re-authenticates. That's simple and avoids
 * stale-token edge cases; fine at this traffic volume (QPay's own docs warn
 * against polling via cron, not against re-authenticating per request).
 *
 * All three functions throw RuntimeException on any failure — callers must
 * catch and translate to a user-facing error, never let this surface raw.
 */

require_once __DIR__ . '/config.php';

function qpay_token(): string {
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
  return $data['access_token'];
}

function qpay_create_invoice(string $token, string $senderInvoiceNo, int $amount, string $callbackUrl, string $description): array {
  $ch = curl_init(NAF_QPAY_BASE_URL . '/v2/invoice');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
    CURLOPT_POSTFIELDS => json_encode([
      'invoice_code' => NAF_QPAY_INVOICE_CODE,
      'sender_invoice_no' => $senderInvoiceNo,
      'invoice_receiver_code' => 'terminal',
      'invoice_description' => $description,
      'amount' => $amount,
      'callback_url' => $callbackUrl,
    ]),
    CURLOPT_TIMEOUT => 15,
  ]);
  $res = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($res === false) throw new RuntimeException('QPay invoice request failed: ' . $err);
  if ($status !== 200) throw new RuntimeException('QPay invoice creation failed: HTTP ' . $status);
  $data = json_decode($res, true);
  if (!isset($data['invoice_id'])) throw new RuntimeException('QPay invoice response missing invoice_id');
  return $data;
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
  if ($status !== 200) throw new RuntimeException('QPay payment check failed: HTTP ' . $status);
  $data = json_decode($res, true);
  if (!is_array($data) || !isset($data['count'], $data['rows'])) {
    throw new RuntimeException('QPay payment check response missing count/rows');
  }
  return $data;
}
