<?php
/**
 * orders-admin.php — read-only admin listing of shop orders.
 *
 * Auth matches products-admin.php exactly: PHP session + CSRF token issued
 * at login.
 *
 * Read-only by design. The shop's stock maths derives true availability from
 * the reservation set ('pending','paid','fulfilled') — see order-create.php,
 * slots.php, shop-data.php and products-admin.php — so letting this screen
 * mutate order status would silently move stock numbers on the public site.
 * Status changes belong in their own endpoint with that consequence handled
 * deliberately.
 */

require __DIR__ . '/db.php';

session_set_cookie_params([
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Метод зөвшөөрөгдөөгүй']);
  exit;
}

if (empty($_SESSION['naf_admin'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Нэвтрэх хугацаа дууссан — дахин нэвтэрнэ үү.']);
  exit;
}

$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['naf_csrf_token']) || !hash_equals($_SESSION['naf_csrf_token'], $csrf)) {
  http_response_code(403);
  echo json_encode(['error' => 'Аюулгүй байдлын токен буруу эсвэл дутуу байна — дахин нэвтэрнэ үү.']);
  exit;
}

$action = $_POST['action'] ?? '';

try {
  $pdo = naf_db();

  if ($action === 'list') {
    // Same lazy-expiry sweep the public endpoints run, so this list never
    // shows an abandoned pending order as though it were still live.
    $pdo->exec("UPDATE orders SET status='expired' WHERE status='pending' AND expires_at < NOW()");

    // public_token is deliberately not selected — it's the bearer secret for
    // the buyer's order-status link, and nothing on this screen needs it.
    $orders = $pdo->query(
      'SELECT id, status, buyer_name, buyer_phone, buyer_address, buyer_note,
              delivery_date, delivery_slot, subtotal, total,
              created_at, paid_at, fulfilled_at
       FROM orders
       ORDER BY created_at DESC, id DESC
       LIMIT 500'
    )->fetchAll();

    $itemStmt = $pdo->prepare(
      'SELECT variant_name_snapshot, unit_price_snapshot, quantity, line_total
       FROM order_items WHERE order_id = ? ORDER BY id'
    );
    foreach ($orders as &$order) {
      $itemStmt->execute([$order['id']]);
      $order['items'] = $itemStmt->fetchAll();
    }
    unset($order);

    echo json_encode(['orders' => $orders]);
    exit;
  }

  http_response_code(400);
  echo json_encode(['error' => 'Тодорхойгүй үйлдэл']);
} catch (\Throwable $e) {
  // Never leak DB host/name or a stack trace, even to an authenticated admin.
  http_response_code(500);
  echo json_encode(['error' => 'Серверийн дотоод алдаа']);
}
