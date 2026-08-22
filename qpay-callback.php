<?php
/**
 * qpay-callback.php — QPay's webhook, called when a payment completes.
 *
 * Never trusts the callback body — re-verifies via QPay's own check-invoice
 * endpoint before marking anything paid. This is the fast confirmation path;
 * order-status.php's polling is the fallback if this is ever missed.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/qpay.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['order'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
  http_response_code(400);
  exit;
}

$pdo = naf_db();
$stmt = $pdo->prepare('SELECT id, status, qpay_invoice_id, total FROM orders WHERE public_token = ?');
$stmt->execute([$token]);
$order = $stmt->fetch();

if (!$order) {
  http_response_code(404);
  exit;
}

if ($order['status'] !== 'pending') {
  // Already resolved (paid/expired/cancelled) — nothing to do, respond OK
  // so QPay doesn't retry indefinitely.
  echo json_encode(['ok' => true]);
  exit;
}

try {
  $qpayToken = qpay_token();
  $check = qpay_check_payment($qpayToken, $order['qpay_invoice_id']);
} catch (Throwable $e) {
  http_response_code(502);
  exit;
}

// count:0 (no payment row on file) is definitely not-paid and is left as-is
// below. A count > 0 alone does NOT prove a settled full payment — it only
// proves QPay has some row for this invoice, which could be non-final,
// reversed, or partial. See qpay.php's qpay_paid_amount() docblock: this
// amount check was implemented from researched (not live-verified against a
// real completed payment) QPay response field names, and MUST be
// re-confirmed against a real completed sandbox payment before switching
// config.php to production QPay credentials.
if (($check['count'] ?? 0) > 0 && qpay_paid_amount($check) >= (float)$order['total']) {
  $pdo->prepare("UPDATE orders SET status='paid', paid_at=NOW() WHERE id=? AND status='pending'")->execute([$order['id']]);
}

echo json_encode(['ok' => true]);
