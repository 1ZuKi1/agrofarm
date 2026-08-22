<?php
/**
 * order-status.php — public polling endpoint for a single order's status.
 */

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid token']);
  exit;
}

$pdo = naf_db();
$pdo->exec("UPDATE orders SET status='expired' WHERE status='pending' AND expires_at < NOW()");

$stmt = $pdo->prepare('SELECT status, total, delivery_date, delivery_slot FROM orders WHERE public_token = ?');
$stmt->execute([$token]);
$order = $stmt->fetch();

if (!$order) {
  http_response_code(404);
  echo json_encode(['error' => 'Order not found']);
  exit;
}

echo json_encode($order);
