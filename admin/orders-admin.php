<?php
/**
 * orders-admin.php — admin listing of shop orders, plus two status changes:
 * "Хүргэсэн" (paid → fulfilled) and "Цуцлах" (pending/paid → cancelled).
 *
 * Auth matches products-admin.php exactly: PHP session + CSRF token issued
 * at login.
 *
 * Stock consequence, handled deliberately: availability is derived from the
 * reservation set ('pending','paid','fulfilled') — see order-create.php,
 * shop-data.php and products-admin.php. Fulfilling keeps the
 * order in that set (the goods are gone); cancelling takes it out, so its
 * stock becomes available again.
 */

require dirname(__DIR__) . '/inc/db.php';
require dirname(__DIR__) . '/inc/qpay.php';
require dirname(__DIR__) . '/inc/orders.php';

require_once dirname(__DIR__) . '/inc/admin-auth.php';

// Auth: a logged-in account (inc/admin-auth.php) and this session's CSRF token.
admin_session_start();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Метод зөвшөөрөгдөөгүй']);
  exit;
}
$me = admin_require(naf_db(), 'staff', true);
admin_check_csrf(true);

$action = $_POST['action'] ?? '';

try {
  $pdo = naf_db();

  if ($action === 'list') {
    // Same expiry sweep the public endpoints run, so this list never shows an
    // abandoned pending order as though it were still live.
    orders_expire_stale($pdo);

    // public_token is deliberately not selected — it's the bearer secret for
    // the buyer's order-status link, and nothing on this screen needs it.
    $orders = $pdo->query(
      'SELECT id, status, buyer_name, buyer_phone, buyer_address, buyer_note,
              fulfillment, delivery_date, delivery_slot, subtotal, delivery_fee, total,
              created_at, paid_at, fulfilled_at
       FROM orders
       ORDER BY created_at DESC, id DESC
       LIMIT 500'
    )->fetchAll();

    $itemStmt = $pdo->prepare(
      'SELECT variant_name_snapshot, unit_price_snapshot, quantity, line_total
       FROM order_items WHERE order_id = ? ORDER BY id'
    );
    $now = fulfillment_now(); // inc/fulfillment.php, loaded by inc/orders.php
    foreach ($orders as &$order) {
      // "Маргааш" + "10-р сарын 15, Пүрэв" — same words the buyer saw.
      $order['day_word'] = fulfillment_day_word($order['delivery_date'], $now);
      $order['date_label'] = fulfillment_date_label($order['delivery_date']);
      $itemStmt->execute([$order['id']]);
      $order['items'] = $itemStmt->fetchAll();
    }
    unset($order);

    echo json_encode(['orders' => $orders]);
    exit;
  }

  // ---- Deliver / cancel ----------------------------------------------------
  // fulfill: a paid order has been delivered.
  // cancel:  a pending or paid order is called off. Its stock and delivery
  //          slot are released (cancelled is outside the reservation set). A
  //          pending order's QPay invoice is cancelled too, so its QR stops
  //          working. A PAID order's money is NOT returned automatically —
  //          admin.js tells the admin to refund it in the QPay merchant portal.
  if ($action === 'fulfill' || $action === 'cancel') {
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT id, status, qpay_invoice_id FROM orders WHERE id = ?');
    $st->execute([$id]);
    $order = $st->fetch();
    if (!$order) {
      http_response_code(404);
      echo json_encode(['error' => 'Захиалга олдсонгүй']);
      exit;
    }

    if ($action === 'fulfill') {
      $up = $pdo->prepare("UPDATE orders SET status='fulfilled', fulfilled_at=NOW() WHERE id=? AND status='paid'");
      $up->execute([$id]);
      if ($up->rowCount() !== 1) {
        http_response_code(409);
        echo json_encode(['error' => 'Зөвхөн төлбөр төлөгдсөн захиалгыг "Хүргэсэн" болгоно.']);
        exit;
      }
      echo json_encode(['ok' => true]);
      exit;
    }

    if ($order['status'] === 'pending' && $order['qpay_invoice_id']) {
      $result = 'error';
      try { $result = qpay_cancel_invoice(qpay_token(), $order['qpay_invoice_id']); } catch (\Throwable $e) {}
      if ($result === 'paid') {
        // Paid a moment ago — record it rather than cancel a paid order unseen.
        order_mark_paid($pdo, $id);
        http_response_code(409);
        echo json_encode(['error' => 'Энэ захиалгын төлбөр яг одоо төлөгдсөн байна — жагсаалтыг шинэчилнэ үү.']);
        exit;
      }
      if ($result !== 'cancelled') {
        http_response_code(502);
        echo json_encode(['error' => 'QPay-тэй холбогдож нэхэмжлэлийг цуцалж чадсангүй — дахин оролдоно уу.']);
        exit;
      }
    }
    $up = $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND status IN ('pending','paid')");
    $up->execute([$id]);
    if ($up->rowCount() !== 1) {
      http_response_code(409);
      echo json_encode(['error' => 'Энэ захиалгыг цуцлах боломжгүй (аль хэдийн хүргэсэн, цуцалсан эсвэл хугацаа нь дууссан).']);
      exit;
    }
    echo json_encode(['ok' => true, 'was_paid' => $order['status'] === 'paid']);
    exit;
  }

  http_response_code(400);
  echo json_encode(['error' => 'Тодорхойгүй үйлдэл']);
} catch (\Throwable $e) {
  // Never leak DB host/name or a stack trace, even to an authenticated admin.
  http_response_code(500);
  echo json_encode(['error' => 'Серверийн дотоод алдаа']);
}
