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
$stmt = $pdo->prepare('SELECT id, status, qpay_invoice_id FROM orders WHERE public_token = ?');
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

if (($check['count'] ?? 0) > 0) {
  $pdo->prepare("UPDATE orders SET status='paid', paid_at=NOW() WHERE id=? AND status='pending'")->execute([$order['id']]);
}

echo json_encode(['ok' => true]);
