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
 *                           api/order-status.php, the /admin/ order pages)
 *   order_fulfill()         paid → fulfilled ("Хүргэсэн"), /admin/ only
 *   order_cancel()          pending/paid → cancelled ("Цуцлах"), /admin/ only
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

require_once __DIR__ . '/fulfillment.php'; // day words for the staff email

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
    $st = $pdo->prepare('SELECT id, buyer_name, buyer_phone, buyer_address, buyer_note, fulfillment, delivery_date, delivery_slot, delivery_fee, total FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o) return;
    $it = $pdo->prepare('SELECT variant_name_snapshot, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id');
    $it->execute([$orderId]);

    $no = order_number((int)$o['id']);
    $pickup = ($o['fulfillment'] === 'pickup');
    $word = fulfillment_day_word($o['delivery_date'], fulfillment_now());
    $shortDate = date('m/d', strtotime($o['delivery_date']));
    // What staff must do, first: "Хүргэлт Маргааш 10/15" or "Очиж авна Өнөөдөр".
    // Orders from before the 2026-10 checkout still have a time slot.
    if ($pickup) $job = 'Очиж авна ' . $word;
    elseif ($o['delivery_slot']) $job = 'Хүргэлт ' . $shortDate . ' ' . $o['delivery_slot'];
    else $job = 'Хүргэлт ' . $word . ' ' . $shortDate;
    $lines = ["Шинэ захиалга $no — төлбөр төлөгдсөн", str_repeat('-', 40), ''];
    if ($late) {
      $lines[] = 'АНХААР: энэ захиалгын 20 минутын хугацаа дууссаны дараа төлбөр орсон.';
      $lines[] = 'Нөөц болон хүргэлтийн цаг хэвээр байгаа эсэхийг шалгана уу.';
      $lines[] = '';
    }
    $lines[] = 'Нэр:      ' . $o['buyer_name'];
    $lines[] = 'Утас:     ' . $o['buyer_phone'];
    $lines[] = 'Авах хэлбэр: ' . fulfillment_method_label($o['fulfillment']);
    $lines[] = 'Өдөр:     ' . $word . ' — ' . fulfillment_date_label($o['delivery_date']) . ($o['delivery_slot'] ? ', ' . $o['delivery_slot'] : '');
    if (!$pickup) $lines[] = 'Хаяг:     ' . $o['buyer_address'];
    if ($o['buyer_note']) $lines[] = 'Тэмдэглэл: ' . $o['buyer_note'];
    if ($pickup) $lines[] = 'Худалдан авагч оффис дээр ирж авна (' . NAF_PICKUP_ADDRESS . ').';
    $lines[] = '';
    $lines[] = 'Бараа:';
    foreach ($it->fetchAll() as $row) {
      $lines[] = sprintf('  %s × %d — %s₮', $row['variant_name_snapshot'], $row['quantity'], number_format((int)$row['line_total']));
    }
    if ((int)$o['delivery_fee'] > 0) $lines[] = 'Хүргэлт:  ' . number_format((int)$o['delivery_fee']) . '₮';
    $lines[] = '';
    $lines[] = 'Нийт:     ' . number_format((int)$o['total']) . '₮ (QPay-ээр төлсөн)';
    $lines[] = '';
    $lines[] = ($pickup ? 'Барааг өгсний' : 'Хүргэсний') . ' дараа админд "Хүргэсэн" гэж тэмдэглэнэ үү: https://agrofarm.mn/admin/';

    $subject = "Шинэ захиалга $no — $job — " . number_format((int)$o['total']) . '₮' . ($late ? ' (хугацаа хэтэрсэн)' : '');
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


// ---- /admin/: Хүргэсэн and Цуцлах ------------------------------------------
// Both need inc/admin-auth.php (admin_log) as well, which every /admin/ page
// loads. Each one changes the row with a guarded UPDATE, so two staff pressing
// the same button at once can't both succeed: the second gets a calm message
// naming who got there first (from the activity log).
//
// Stock: availability is derived from the reservation set
// ('pending','paid','fulfilled') — see inc/shop-lib.php, api/order-create.php
// and admin/products-admin.php. Fulfilling keeps the order in that set (the
// goods are gone); cancelling takes it out, so its stock is available again.

/** "Тест Админ, 10/15 14:20" for the newest log row of one of $actions, or null. */
function order_last_actor(PDO $pdo, int $id, array $actions): ?string {
  $in = implode(',', array_fill(0, count($actions), '?'));
  $st = $pdo->prepare("SELECT user_name, created_at FROM admin_log WHERE target_type = 'order' AND target_id = ? AND action IN ($in) ORDER BY id DESC LIMIT 1");
  $st->execute(array_merge([$id], $actions));
  $row = $st->fetch();
  return $row ? $row['user_name'] . ', ' . date('m/d H:i', strtotime($row['created_at'])) : null;
}

/** Why an order is no longer in the state the button expected. */
function order_state_message(PDO $pdo, array $order, string $tried): string {
  $id = (int)$order['id'];
  switch ($order['status']) {
    case 'fulfilled':
      $who = order_last_actor($pdo, $id, ['order_fulfill'])
        ?: ($order['fulfilled_at'] ? date('m/d H:i', strtotime($order['fulfilled_at'])) : null);
      return ($tried === 'cancel' ? 'Цуцлах боломжгүй: аль хэдийн хүргэсэн' : 'Аль хэдийн хүргэсэн') . ($who ? ' — ' . $who : '');
    case 'cancelled':
      $who = order_last_actor($pdo, $id, ['order_cancel']);
      return ($tried === 'cancel' ? 'Аль хэдийн цуцалсан' : 'Энэ захиалга цуцлагдсан') . ($who ? ' — ' . $who : '');
    case 'expired':
      return 'Энэ захиалгын 20 минутын хугацаа дууссан — төлбөр төлөгдөөгүй.';
    case 'pending':
      return 'Төлбөр хараахан төлөгдөөгүй — зөвхөн төлсөн захиалгыг "Хүргэсэн" болгоно.';
  }
  return 'Энэ үйлдлийг хийх боломжгүй.';
}

/** paid → fulfilled. Returns ['ok' => bool, 'message' => string]. */
function order_fulfill(PDO $pdo, int $id, array $me): array {
  $st = $pdo->prepare('SELECT id, status, fulfilled_at FROM orders WHERE id = ?');
  $st->execute([$id]);
  $order = $st->fetch();
  if (!$order) return ['ok' => false, 'message' => 'Захиалга олдсонгүй'];

  $up = $pdo->prepare("UPDATE orders SET status = 'fulfilled', fulfilled_at = NOW() WHERE id = ? AND status = 'paid'");
  $up->execute([$id]);
  if ($up->rowCount() !== 1) {
    $st->execute([$id]); // re-read: someone may have changed it a moment ago
    return ['ok' => false, 'message' => order_state_message($pdo, $st->fetch() ?: $order, 'fulfill')];
  }
  admin_log($pdo, $me, 'order_fulfill', 'order', $id);
  return ['ok' => true, 'message' => order_number($id) . ' — хүргэсэн гэж тэмдэглэлээ.'];
}

/**
 * pending/paid → cancelled. A pending order's QPay invoice is cancelled first,
 * so its QR stops working; if QPay says it was paid a moment ago, the order is
 * marked paid instead and the cancel is refused. A PAID order's money is NOT
 * returned automatically — the message tells staff to refund it in the QPay
 * merchant portal. Returns ['ok' => bool, 'message' => string, 'was_paid' => bool].
 */
function order_cancel(PDO $pdo, int $id, array $me): array {
  $st = $pdo->prepare('SELECT id, status, qpay_invoice_id, fulfilled_at FROM orders WHERE id = ?');
  $st->execute([$id]);
  $order = $st->fetch();
  if (!$order) return ['ok' => false, 'message' => 'Захиалга олдсонгүй', 'was_paid' => false];
  if (!in_array($order['status'], ['pending', 'paid'], true)) {
    return ['ok' => false, 'message' => order_state_message($pdo, $order, 'cancel'), 'was_paid' => false];
  }

  if ($order['status'] === 'pending' && $order['qpay_invoice_id']) {
    $result = 'error';
    try { $result = qpay_cancel_invoice(qpay_token(), $order['qpay_invoice_id']); } catch (\Throwable $e) {}
    if ($result === 'paid') {
      order_mark_paid($pdo, $id); // paid a moment ago — record it rather than cancel it unseen
      return ['ok' => false, 'message' => 'Энэ захиалгын төлбөр яг одоо төлөгдсөн байна — хуудсыг шинэчилнэ үү.', 'was_paid' => false];
    }
    if ($result !== 'cancelled') {
      return ['ok' => false, 'message' => 'QPay-тэй холбогдож нэхэмжлэлийг цуцалж чадсангүй — дахин оролдоно уу.', 'was_paid' => false];
    }
  }

  $up = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = ?");
  $up->execute([$id, $order['status']]);
  if ($up->rowCount() !== 1) {
    $st->execute([$id]);
    return ['ok' => false, 'message' => order_state_message($pdo, $st->fetch() ?: $order, 'cancel'), 'was_paid' => false];
  }
  $wasPaid = $order['status'] === 'paid';
  admin_log($pdo, $me, 'order_cancel', 'order', $id, $wasPaid ? 'was_paid' : null);
  return [
    'ok' => true,
    'message' => order_number($id) . ' цуцлагдлаа.' . ($wasPaid ? ' Төлбөрийг QPay мерчант порталаас худалдан авагчид буцааж олгоно уу.' : ''),
    'was_paid' => $wasPaid,
  ];
}
