<?php
/**
 * inc/orders.php — order state changes that more than one endpoint performs.
 *
 *   order_mark_paid()       pending/expired → paid, then emails the shop
 *                           (qpay-callback.php, api/order-status.php,
 *                           orders_expire_stale() below)
 *   orders_expire_stale()   pending orders past expires_at → expired, after
 *                           cancelling their QPay invoice so the QR stops
 *                           accepting payment (api/order-create.php,
 *                           api/order-status.php, api/slots.php,
 *                           admin/orders-admin.php)
 *
 * An order holds its stock and delivery slot for 20 minutes. Before this, an
 * expired order's QR kept working: a payment on minute 25 still went through,
 * after the stock or slot could already have gone to someone else. Now the
 * invoice is cancelled at QPay first, and the order only becomes 'expired'
 * once QPay confirms the cancel. If QPay answers that the invoice was already
 * paid (the buyer paid in the last seconds), the order is marked paid instead
 * — nobody loses money.
 *
 * Requires inc/db.php and inc/qpay.php to be loaded first.
 */

const NAF_ORDERS_TO = 'info@agrofarm.mn';
const NAF_ORDERS_FROM = 'no-reply@agrofarm.mn'; // must be on our own domain for SPF

/** "#000012" — the order number customers and staff both see (admin uses the same format). */
function order_number(int $id): string {
  return '#' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

/**
 * Marks an order paid. Only the request that actually flips the row sends
 * the email, so a callback and a buyer's "check payment" arriving together
 * never notify twice. Returns true if this call changed the order.
 */
function order_mark_paid(PDO $pdo, int $orderId): bool {
  $prev = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
  $prev->execute([$orderId]);
  $wasExpired = ($prev->fetchColumn() === 'expired');

  $st = $pdo->prepare("UPDATE orders SET status='paid', paid_at=NOW() WHERE id=? AND status IN ('pending','expired')");
  $st->execute([$orderId]);
  if ($st->rowCount() !== 1) return false;

  if ($wasExpired) error_log("orders: order id=$orderId paid after it had expired");
  order_notify_paid($pdo, $orderId, $wasExpired);
  return true;
}

/**
 * Emails the shop about a newly paid order. A mail failure is logged, never
 * thrown: the order is already paid and visible in /admin/ either way.
 */
function order_notify_paid(PDO $pdo, int $orderId, bool $late = false): void {
  try {
    $st = $pdo->prepare('SELECT id, buyer_name, buyer_phone, buyer_address, buyer_note, delivery_date, delivery_slot, total FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o) return;
    $it = $pdo->prepare('SELECT variant_name_snapshot, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id');
    $it->execute([$orderId]);

    $no = order_number((int)$o['id']);
    $lines = ["Шинэ захиалга $no — төлбөр төлөгдсөн", str_repeat('-', 40), ''];
    if ($late) {
      $lines[] = 'АНХААР: энэ захиалгын 20 минутын хугацаа дууссаны дараа төлбөр орсон.';
      $lines[] = 'Нөөц болон хүргэлтийн цаг хэвээр байгаа эсэхийг шалгана уу.';
      $lines[] = '';
    }
    $lines[] = 'Нэр:      ' . $o['buyer_name'];
    $lines[] = 'Утас:     ' . $o['buyer_phone'];
    $lines[] = 'Хаяг:     ' . $o['buyer_address'];
    if ($o['buyer_note']) $lines[] = 'Тэмдэглэл: ' . $o['buyer_note'];
    $lines[] = 'Хүргэлт:  ' . $o['delivery_date'] . ', ' . $o['delivery_slot'];
    $lines[] = '';
    $lines[] = 'Бараа:';
    foreach ($it->fetchAll() as $row) {
      $lines[] = sprintf('  %s × %d — %s₮', $row['variant_name_snapshot'], $row['quantity'], number_format((int)$row['line_total']));
    }
    $lines[] = '';
    $lines[] = 'Нийт:     ' . number_format((int)$o['total']) . '₮ (QPay-ээр төлсөн)';
    $lines[] = '';
    $lines[] = 'Хүргэсний дараа админд "Хүргэсэн" гэж тэмдэглэнэ үү: https://agrofarm.mn/admin/';

    $subject = "Шинэ захиалга $no — " . number_format((int)$o['total']) . '₮' . ($late ? ' (хугацаа хэтэрсэн)' : '');
    $sent = @mail(
      NAF_ORDERS_TO,
      '=?UTF-8?B?' . base64_encode($subject) . '?=',
      implode("\n", $lines),
      implode("\r\n", [
        'From: Agrofarm <' . NAF_ORDERS_FROM . '>',
        'Content-Type: text/plain; charset=utf-8',
        'X-Mailer: agrofarm.mn',
      ])
    );
    if (!$sent) error_log("orders: paid-order email for id=$orderId was not accepted by mail()");
  } catch (\Throwable $e) {
    error_log('orders: paid-order email failed: ' . $e->getMessage());
  }
}

/**
 * Expires pending orders whose 20 minutes are up — cancelling each one's QPay
 * invoice first. Each order is attempted at most once per 15 seconds
 * (claimed on qpay_checked_at, shared with the buyer's "check payment"), so a
 * QPay outage can't slow every page down: such an order simply stays
 * 'pending' (still holding its stock) until a later try succeeds. At most
 * $limit orders per call keeps any one request quick.
 */
function orders_expire_stale(PDO $pdo, int $limit = 5): void {
  $rows = $pdo->query(
    "SELECT id, qpay_invoice_id FROM orders
     WHERE status = 'pending' AND expires_at < NOW()
       AND (qpay_checked_at IS NULL OR qpay_checked_at < DATE_SUB(NOW(), INTERVAL 15 SECOND))
     ORDER BY expires_at LIMIT " . (int)$limit
  )->fetchAll();
  if (!$rows) return;

  $claim = $pdo->prepare(
    "UPDATE orders SET qpay_checked_at = NOW()
     WHERE id = ? AND status = 'pending'
       AND (qpay_checked_at IS NULL OR qpay_checked_at < DATE_SUB(NOW(), INTERVAL 15 SECOND))"
  );
  $expire = $pdo->prepare("UPDATE orders SET status = 'expired' WHERE id = ? AND status = 'pending'");

  try {
    $token = qpay_token();
  } catch (\Throwable $e) {
    return; // QPay unreachable — try again on a later request
  }

  foreach ($rows as $r) {
    $claim->execute([$r['id']]);
    if ($claim->rowCount() !== 1) continue; // another request is handling it

    if (!$r['qpay_invoice_id']) { $expire->execute([$r['id']]); continue; }

    $result = qpay_cancel_invoice($token, $r['qpay_invoice_id']);
    if ($result === 'cancelled') {
      $expire->execute([$r['id']]);
      continue;
    }
    // Not cancelled: either already paid, or QPay had a problem. Ask QPay
    // which — a paid order must become 'paid', never 'expired'.
    try {
      $check = qpay_check_payment($token, $r['qpay_invoice_id']);
      $orderTotal = $pdo->prepare('SELECT total FROM orders WHERE id = ?');
      $orderTotal->execute([$r['id']]);
      if (($check['count'] ?? 0) > 0 && qpay_paid_amount($check) >= (float)$orderTotal->fetchColumn()) {
        order_mark_paid($pdo, (int)$r['id']);
      }
      // otherwise leave it pending; the next attempt (15 s on) retries the cancel
    } catch (\Throwable $e) {
      // QPay unreachable — leave pending, retry later
    }
  }
}
