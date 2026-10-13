<?php
/**
 * order-status.php — public polling endpoint for a single order's status.
 *
 * Plain polls (every 3s from shop.js) read the database ONLY. QPay forbids
 * frequent payment checks ("Cron Job ашиглан гүйлгээ байнга шалгахыг
 * хориглоно. Зөвхөн Callback URL авсаны дараа шалгана уу."), so the normal
 * path to 'paid' is qpay-callback.php. QPay is asked from here only when the
 * request carries ?check=1 — sent when the buyer taps "Төлбөр шалгах", returns
 * to the tab from their bank app, opens the ?order= link, or the countdown
 * reaches zero — and at most once per 10 seconds per order (qpay_checked_at,
 * claimed atomically below).
 *
 * Expiry: orders_expire_stale() cancels an expired order's QPay invoice before
 * marking it 'expired', and marks it 'paid' instead if QPay says it was paid
 * — so this order can safely be part of that sweep too.
 */

require dirname(__DIR__) . '/inc/db.php';
require dirname(__DIR__) . '/inc/qpay.php';
require dirname(__DIR__) . '/inc/orders.php';
require_once dirname(__DIR__) . '/inc/fulfillment.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid token']);
  exit;
}

try {
  $pdo = naf_db();
  orders_expire_stale($pdo);

  $stmt = $pdo->prepare(
    'SELECT id, status, qpay_invoice_id, subtotal, delivery_fee, total, fulfillment, delivery_date, delivery_slot, paid_at, qr_image, qr_text, qpay_urls,
            GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS expires_in
     FROM orders WHERE public_token = ?'
  );
  $stmt->execute([$token]);
  $order = $stmt->fetch();

  if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
  }

  // An order that is 'expired' with no paid_at can still turn out paid (the
  // buyer paid in the last seconds, before its invoice was cancelled) — a
  // requested check treats it like 'pending'.
  $wasExpiredUnpaid = ($order['status'] === 'expired' && $order['paid_at'] === null);

  // A requested check is the fallback for a missed or slow webhook, so — same
  // as qpay-callback.php — re-verify via QPay's own check endpoint rather than
  // trusting the cached status column. A QPay failure here must NOT error
  // out: just fall back to the cached status.
  $order['verified'] = false;
  $wantsCheck = (($_GET['check'] ?? '') === '1') && ($order['status'] === 'pending' || $wasExpiredUnpaid);
  if ($wantsCheck) {
    $claim = $pdo->prepare('UPDATE orders SET qpay_checked_at=NOW() WHERE id=? AND (qpay_checked_at IS NULL OR qpay_checked_at < DATE_SUB(NOW(), INTERVAL 10 SECOND))');
    $claim->execute([$order['id']]);
    $wantsCheck = ($claim->rowCount() === 1);
  }
  if ($wantsCheck) {
    try {
      $check = qpay_check_payment(qpay_token(), $order['qpay_invoice_id']);
      $order['verified'] = true;
      // count:0 is definitely not paid. count > 0 alone does not prove a
      // settled full payment — qpay_paid_amount() (inc/qpay.php) sums only
      // PAID rows.
      if (($check['count'] ?? 0) > 0 && qpay_paid_amount($check) >= (float)$order['total']) {
        order_mark_paid($pdo, (int)$order['id']); // emails the shop if this call flipped it
        $order['status'] = 'paid';
      }
    } catch (Throwable $e) {
      // QPay unreachable/erroring — leave $order['status'] as the cached value.
    }
  }

  $order['order_no'] = order_number((int)$order['id']);
  // How the confirmation says the day: "Маргааш" + "10-р сарын 15, Пүрэв".
  // Old orders (before the 2026-10 checkout) also carry delivery_slot.
  $order['day_word'] = fulfillment_day_word($order['delivery_date'], fulfillment_now());
  $order['date_label'] = fulfillment_date_label($order['delivery_date']);
  foreach (['subtotal', 'delivery_fee', 'total'] as $k) $order[$k] = (int)$order[$k];
  $order['expires_in'] = (int)$order['expires_in'];
  if ($order['status'] === 'pending') {
    $order['banks'] = json_decode((string)$order['qpay_urls'], true) ?: [];
  } else {
    unset($order['qr_image'], $order['qr_text'], $order['expires_in']);
  }
  unset($order['id'], $order['qpay_invoice_id'], $order['paid_at'], $order['qpay_urls']);
  echo json_encode($order);
} catch (\Throwable $e) {
  // Never leak exception details (DB host/name, stack trace) to this
  // endpoint's anonymous, unauthenticated visitors.
  http_response_code(500);
  echo json_encode(['error' => 'Internal server error']);
}
