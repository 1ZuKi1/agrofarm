<?php
/**
 * order-status.php — public polling endpoint for a single order's status.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/qpay.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid token']);
  exit;
}

$pdo = naf_db();
$pdo->exec("UPDATE orders SET status='expired' WHERE status='pending' AND expires_at < NOW()");

$stmt = $pdo->prepare('SELECT id, status, qpay_invoice_id, total, delivery_date, delivery_slot FROM orders WHERE public_token = ?');
$stmt->execute([$token]);
$order = $stmt->fetch();

if (!$order) {
  http_response_code(404);
  echo json_encode(['error' => 'Order not found']);
  exit;
}

// Polling is the fallback path for a missed webhook, so — same as
// qpay-callback.php — re-verify via QPay's own check endpoint rather than
// trusting the cached status column. Unlike the webhook, a QPay failure here
// must NOT error out: just fall back to the cached (still-pending) status,
// since the browser will poll again in a few seconds regardless.
if ($order['status'] === 'pending') {
  try {
    $qpayToken = qpay_token();
    $check = qpay_check_payment($qpayToken, $order['qpay_invoice_id']);
    if (($check['count'] ?? 0) > 0) {
      $pdo->prepare("UPDATE orders SET status='paid', paid_at=NOW() WHERE id=? AND status='pending'")->execute([$order['id']]);
      $order['status'] = 'paid';
    }
  } catch (Throwable $e) {
    // QPay unreachable/erroring — leave $order['status'] as the cached value.
  }
}

unset($order['id'], $order['qpay_invoice_id']);
echo json_encode($order);
