<?php
/**
 * inc/admin-data.php — what the /admin/ pages read: the menu counters, Нүүр's
 * jobs, low stock and sales, and the orders list / one order.
 *
 * "Today" and "tomorrow" are calendar days in Ulaanbaatar (fulfillment_now()),
 * and an order's own delivery_date decides which day it belongs to.
 *
 * Loaded by inc/admin-layout.php, so every admin page has the counters.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/qpay.php';
require_once __DIR__ . '/orders.php'; // + inc/fulfillment.php

const ADMIN_ORDERS_PER_PAGE = 50;
const ADMIN_LOW_STOCK = 3;

/** Order tabs: key → [label, statuses]. */
const ADMIN_ORDER_TABS = [
  'todo'      => ['Хийх', ['paid']],
  'pending'   => ['Хүлээгдэж буй', ['pending']],
  'done'      => ['Хүргэсэн', ['fulfilled']],
  'cancelled' => ['Цуцлагдсан', ['cancelled', 'expired']],
  'all'       => ['Бүгд', ['pending', 'paid', 'fulfilled', 'cancelled', 'expired']],
];

/** Day filter: key → label. */
const ADMIN_ORDER_DAYS = ['' => 'Бүх өдөр', 'today' => 'Өнөөдөр', 'tomorrow' => 'Маргааш', 'week' => 'Ойрын 7 хоног'];

/** Status → [label, tag modifier]. Colour is never the only signal. */
function admin_order_status(string $status): array {
  $map = [
    'pending'   => ['Төлбөр хүлээгдэж буй', 'info'],
    'paid'      => ['Төлсөн — хүргэх', 'warn'],
    'fulfilled' => ['Хүргэсэн', 'ok'],
    'cancelled' => ['Цуцлагдсан', 'mute'],
    'expired'   => ['Хугацаа дууссан', 'mute'],
  ];
  return $map[$status] ?? [$status, 'mute'];
}

/** The menu counters: paid orders not yet delivered, and new quote requests. */
function admin_counts(PDO $pdo): array {
  static $memo = null;
  if ($memo !== null) return $memo;
  $todo = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'paid'")->fetchColumn();
  $quotes = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'new'")->fetchColumn();
  return $memo = ['todo' => $todo, 'quotes' => $quotes];
}

/** Attaches 'items' (name, quantity, line_total) to each order row. */
function admin_attach_items(PDO $pdo, array $orders): array {
  if (!$orders) return $orders;
  $ids = array_map(function ($o) { return (int)$o['id']; }, $orders);
  $in = implode(',', $ids); // ints only
  $by = [];
  foreach ($pdo->query("SELECT order_id, variant_name_snapshot, unit_price_snapshot, quantity, line_total FROM order_items WHERE order_id IN ($in) ORDER BY id") as $it) {
    $by[(int)$it['order_id']][] = $it;
  }
  foreach ($orders as &$o) $o['items'] = $by[(int)$o['id']] ?? [];
  unset($o);
  return $orders;
}

/** "Долооц × 2, Ном × 1" */
function admin_items_short(array $items): string {
  return implode(', ', array_map(function ($it) {
    return $it['variant_name_snapshot'] . ' × ' . (int)$it['quantity'];
  }, $items));
}

const ADMIN_ORDER_COLS = 'id, status, buyer_name, buyer_phone, buyer_address, buyer_note, fulfillment, delivery_date, delivery_slot,
  subtotal, delivery_fee, total, created_at, paid_at, fulfilled_at';

/** Paid orders for one day (Y-m-d), with items. Pick-ups and deliveries together. */
function admin_jobs(PDO $pdo, string $date): array {
  $st = $pdo->prepare('SELECT ' . ADMIN_ORDER_COLS . " FROM orders WHERE status = 'paid' AND delivery_date = ? ORDER BY fulfillment DESC, delivery_slot, id");
  $st->execute([$date]);
  return admin_attach_items($pdo, $st->fetchAll());
}

/** Paid orders whose day has passed, oldest first. */
function admin_jobs_late(PDO $pdo, string $today): array {
  $st = $pdo->prepare('SELECT ' . ADMIN_ORDER_COLS . " FROM orders WHERE status = 'paid' AND delivery_date < ? ORDER BY delivery_date, id");
  $st->execute([$today]);
  return admin_attach_items($pdo, $st->fetchAll());
}

/** The strip on Нүүр. */
function admin_home_numbers(PDO $pdo, string $today, string $tomorrow): array {
  $st = $pdo->prepare("SELECT
      SUM(status = 'paid' AND delivery_date = ?) AS today,
      SUM(status = 'paid' AND delivery_date = ?) AS tomorrow,
      SUM(status = 'pending') AS pending
    FROM orders");
  $st->execute([$today, $tomorrow]);
  $r = $st->fetch();
  return [
    'today' => (int)$r['today'],
    'tomorrow' => (int)$r['tomorrow'],
    'pending' => (int)$r['pending'],
    'quotes' => admin_counts($pdo)['quotes'],
  ];
}

/**
 * Sales this week (Monday–Sunday) and this month, UB time: paid and
 * delivered orders by paid_at. Each: ['orders', 'revenue', 'fees'].
 */
function admin_sales(PDO $pdo, DateTimeImmutable $now): array {
  $day = $now->setTime(0, 0);
  $weekStart = $day->modify('-' . ((int)$day->format('N') - 1) . ' days');
  $monthStart = $day->modify('first day of this month');
  $st = $pdo->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(total), 0) AS revenue, COALESCE(SUM(delivery_fee), 0) AS fees
    FROM orders WHERE status IN ('paid', 'fulfilled') AND paid_at >= ? AND paid_at < ?");
  $out = [];
  foreach (['week' => [$weekStart, $weekStart->modify('+7 days')], 'month' => [$monthStart, $monthStart->modify('+1 month')]] as $k => $range) {
    $st->execute([$range[0]->format('Y-m-d H:i:s'), $range[1]->format('Y-m-d H:i:s')]);
    $r = $st->fetch();
    $out[$k] = ['orders' => (int)$r['orders'], 'revenue' => (int)$r['revenue'], 'fees' => (int)$r['fees']];
  }
  return $out;
}

/**
 * Active options (of active products) with at most $max left to sell, sold out
 * first. 'available' = stock minus everything reserved — the same reservation
 * set as inc/shop-lib.php ('pending','paid','fulfilled').
 */
function admin_low_stock(PDO $pdo, int $max = ADMIN_LOW_STOCK): array {
  $st = $pdo->prepare("SELECT v.id, v.name_mn AS variant, p.name_mn AS product,
      v.stock - COALESCE((SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON o.id = oi.order_id
                          WHERE oi.variant_id = v.id AND o.status IN ('pending','paid','fulfilled')), 0) AS available
    FROM product_variants v JOIN products p ON p.id = v.product_id
    WHERE v.active = 1 AND p.active = 1
    HAVING available <= ?
    ORDER BY available, p.name_mn, v.sort_order, v.id");
  $st->execute([$max]);
  return $st->fetchAll();
}

/** WHERE parts shared by the list and its tab counts. */
function admin_orders_where(string $day, string $q, DateTimeImmutable $now, array &$args): string {
  $where = [];
  $today = $now->format('Y-m-d');
  if ($day === 'today') { $where[] = 'delivery_date = ?'; $args[] = $today; }
  elseif ($day === 'tomorrow') { $where[] = 'delivery_date = ?'; $args[] = $now->modify('+1 day')->format('Y-m-d'); }
  elseif ($day === 'week') { $where[] = 'delivery_date BETWEEN ? AND ?'; $args[] = $today; $args[] = $now->modify('+6 days')->format('Y-m-d'); }

  $q = trim($q);
  if ($q !== '') {
    if (preg_match('/^#\s*0*(\d+)$/', $q, $m)) {
      // "#000012" — the order number
      $where[] = 'id = ?'; $args[] = (int)$m[1];
    } else {
      $like = '%' . addcslashes($q, '%_\\') . '%';
      $digits = preg_replace('/\D/', '', $q);
      $parts = ['buyer_name LIKE ?'];
      $args[] = $like;
      if ($digits !== '') { $parts[] = 'buyer_phone LIKE ?'; $args[] = '%' . $digits . '%'; }
      if (ctype_digit($q)) { $parts[] = 'id = ?'; $args[] = (int)$q; }
      $where[] = '(' . implode(' OR ', $parts) . ')';
    }
  }
  return $where ? ' AND ' . implode(' AND ', $where) : '';
}

/**
 * The orders list. $tab is a key of ADMIN_ORDER_TABS, $day of ADMIN_ORDER_DAYS.
 * Returns ['rows' => …, 'total' => n, 'counts' => [tab => n]]; the counts use
 * the same day filter and search.
 */
function admin_orders_list(PDO $pdo, string $tab, string $day, string $q, int $page): array {
  if (!isset(ADMIN_ORDER_TABS[$tab])) $tab = 'todo';
  if (!isset(ADMIN_ORDER_DAYS[$day])) $day = '';
  $now = fulfillment_now();

  $args = [];
  $filter = admin_orders_where($day, $q, $now, $args);
  $counts = [];
  $byStatus = $pdo->prepare("SELECT status, COUNT(*) AS n FROM orders WHERE 1 = 1 $filter GROUP BY status");
  $byStatus->execute($args);
  $n = [];
  foreach ($byStatus as $r) $n[$r['status']] = (int)$r['n'];
  foreach (ADMIN_ORDER_TABS as $key => $t) {
    $counts[$key] = array_sum(array_map(function ($s) use ($n) { return $n[$s] ?? 0; }, $t[1]));
  }

  $statuses = ADMIN_ORDER_TABS[$tab][1];
  $in = "'" . implode("','", $statuses) . "'"; // fixed literals
  $order = $tab === 'todo' ? 'delivery_date ASC, id ASC' : 'created_at DESC, id DESC';
  $page = min(max(1, $page), max(1, (int)ceil($counts[$tab] / ADMIN_ORDERS_PER_PAGE))); // never past the last page
  $st = $pdo->prepare('SELECT ' . ADMIN_ORDER_COLS . " FROM orders WHERE status IN ($in) $filter ORDER BY $order LIMIT "
    . ADMIN_ORDERS_PER_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_ORDERS_PER_PAGE));
  $st->execute($args);
  return ['rows' => $st->fetchAll(), 'total' => $counts[$tab], 'counts' => $counts, 'tab' => $tab, 'day' => $day, 'page' => $page];
}

/** One order with its items and history (oldest first), or null. */
function admin_order(PDO $pdo, int $id): ?array {
  $st = $pdo->prepare('SELECT ' . ADMIN_ORDER_COLS . ', expires_at FROM orders WHERE id = ?');
  $st->execute([$id]);
  $o = $st->fetch();
  if (!$o) return null;
  $o = admin_attach_items($pdo, [$o])[0];

  $events = [['at' => $o['created_at'], 'text' => 'Захиалга үүссэн']];
  if ($o['paid_at']) $events[] = ['at' => $o['paid_at'], 'text' => 'Төлсөн (QPay)'];
  $loggedFulfil = false;
  foreach (array_reverse(admin_history($pdo, 'order', $id)) as $h) {
    if ($h['action'] === 'order_fulfill') {
      $loggedFulfil = true;
      $events[] = ['at' => $h['created_at'], 'text' => 'Хүргэсэн — ' . $h['user_name']];
    } elseif ($h['action'] === 'order_cancel') {
      $events[] = ['at' => $h['created_at'], 'text' => 'Цуцалсан — ' . $h['user_name'] . ($h['detail'] === 'was_paid' ? ' (төлбөрийг гараар буцаана)' : '')];
    }
  }
  if ($o['status'] === 'fulfilled' && !$loggedFulfil && $o['fulfilled_at']) {
    $events[] = ['at' => $o['fulfilled_at'], 'text' => 'Хүргэсэн'];
  }
  if ($o['status'] === 'expired') {
    $events[] = ['at' => $o['expires_at'], 'text' => 'Хугацаа дууссан — төлбөр төлөгдөөгүй'];
  }
  usort($events, function ($a, $b) { return strcmp($a['at'], $b['at']); });
  $o['history'] = $events;
  return $o;
}

/** '2026-10-14 11:02:00' → '10/14 11:02' */
function admin_when(?string $dt): string {
  return $dt ? date('m/d H:i', strtotime($dt)) : '';
}

/** 28000 → '28,000₮' */
function admin_money(int $n): string {
  return number_format($n) . '₮';
}

/** "Өнөөдөр · 10-р сарын 15, Пүрэв" (and the old time slot when there is one). */
function admin_order_day(array $o, DateTimeImmutable $now): string {
  return fulfillment_day_word($o['delivery_date'], $now) . ' · ' . fulfillment_date_label($o['delivery_date'])
    . ($o['delivery_slot'] ? ', ' . $o['delivery_slot'] : '');
}

// ---- Үнийн санал (quote requests from the equipment pages) ----------------

/** Quote tabs: key → [label, statuses]. */
const ADMIN_QUOTE_TABS = [
  'new'     => ['Шинэ', ['new']],
  'replied' => ['Хариулсан', ['replied']],
  'closed'  => ['Хаагдсан', ['closed']],
  'all'     => ['Бүгд', ['new', 'replied', 'closed']],
];

/** Status → [label, tag modifier]. */
function admin_quote_status(string $status): array {
  $map = ['new' => ['Шинэ', 'warn'], 'replied' => ['Хариулсан', 'ok'], 'closed' => ['Хаагдсан', 'mute']];
  return $map[$status] ?? [$status, 'mute'];
}

/** Log action → the history line's verb. */
const ADMIN_QUOTE_ACTIONS = [
  'quote_replied'  => 'Хариулсан',
  'quote_closed'   => 'Хаасан',
  'quote_reopened' => 'Буцааж нээсэн',
  'quote_note'     => 'Тэмдэглэл',
];

/**
 * Requests for one tab, newest first, each with 'items' and 'history' (oldest
 * first). Returns ['rows' => …, 'counts' => [tab => n], 'tab' => key].
 */
function admin_quotes_list(PDO $pdo, string $tab): array {
  if (!isset(ADMIN_QUOTE_TABS[$tab])) $tab = 'new';
  $n = [];
  foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM inquiries GROUP BY status') as $r) $n[$r['status']] = (int)$r['n'];
  $counts = [];
  foreach (ADMIN_QUOTE_TABS as $key => $t) {
    $counts[$key] = array_sum(array_map(function ($s) use ($n) { return $n[$s] ?? 0; }, $t[1]));
  }

  $in = "'" . implode("','", ADMIN_QUOTE_TABS[$tab][1]) . "'"; // fixed literals
  $rows = $pdo->query("SELECT id, status, buyer_name, buyer_phone, buyer_email, buyer_company, message, staff_note, created_at, replied_at
                       FROM inquiries WHERE status IN ($in) ORDER BY created_at DESC, id DESC LIMIT 200")->fetchAll();
  if ($rows) {
    $ids = implode(',', array_map(function ($r) { return (int)$r['id']; }, $rows));
    $items = [];
    foreach ($pdo->query("SELECT inquiry_id, product_name_snapshot, sku_snapshot, quantity FROM inquiry_items WHERE inquiry_id IN ($ids) ORDER BY id") as $it) {
      $items[(int)$it['inquiry_id']][] = $it;
    }
    $logs = [];
    foreach ($pdo->query("SELECT target_id, user_name, action, created_at FROM admin_log WHERE target_type = 'quote' AND target_id IN ($ids) ORDER BY id") as $l) {
      $logs[(int)$l['target_id']][] = $l;
    }
    foreach ($rows as &$r) {
      $id = (int)$r['id'];
      $r['items'] = $items[$id] ?? [];
      $events = [['at' => $r['created_at'], 'text' => 'Хүсэлт ирсэн']];
      $loggedReply = false;
      foreach ($logs[$id] ?? [] as $l) {
        if (!isset(ADMIN_QUOTE_ACTIONS[$l['action']])) continue;
        if ($l['action'] === 'quote_replied') $loggedReply = true;
        $events[] = ['at' => $l['created_at'], 'text' => ADMIN_QUOTE_ACTIONS[$l['action']] . ' — ' . $l['user_name']];
      }
      if (!$loggedReply && $r['replied_at']) $events[] = ['at' => $r['replied_at'], 'text' => 'Хариулсан'];
      usort($events, function ($a, $b) { return strcmp($a['at'], $b['at']); });
      $r['history'] = $events;
    }
    unset($r);
  }
  return ['rows' => $rows, 'counts' => $counts, 'tab' => $tab];
}

/**
 * Хариулсан (new → replied), Хаах (new/replied → closed), Буцааж нээх
 * (replied/closed → new). A guarded UPDATE, so a second person pressing the
 * same button gets "Аль хэдийн … — who, when". Returns ['ok', 'message'].
 */
function admin_quote_set_status(PDO $pdo, int $id, string $status, array $me): array {
  $rules = [
    'replied' => [['new'], 'quote_replied', 'хариулсан', ", replied_at = NOW()"],
    'closed'  => [['new', 'replied'], 'quote_closed', 'хаасан', ''],
    'new'     => [['replied', 'closed'], 'quote_reopened', 'буцааж нээсэн', ''],
  ];
  if (!isset($rules[$status])) return ['ok' => false, 'message' => 'Тодорхойгүй үйлдэл'];
  [$from, $action, $word, $extra] = $rules[$status];
  $in = "'" . implode("','", $from) . "'";
  $up = $pdo->prepare("UPDATE inquiries SET status = ?$extra WHERE id = ? AND status IN ($in)");
  $up->execute([$status, $id]);
  if ($up->rowCount() !== 1) {
    $st = $pdo->prepare('SELECT status FROM inquiries WHERE id = ?');
    $st->execute([$id]);
    $now = $st->fetchColumn();
    if ($now === false) return ['ok' => false, 'message' => 'Хүсэлт олдсонгүй'];
    if ($now !== $status) return ['ok' => false, 'message' => 'Энэ хүсэлтийн төлөв өөрчлөгдсөн байна — хуудсыг шинэчилнэ үү.'];
    $last = $pdo->prepare("SELECT user_name, created_at FROM admin_log WHERE target_type = 'quote' AND target_id = ? AND action = ? ORDER BY id DESC LIMIT 1");
    $last->execute([$id, $action]);
    $who = $last->fetch();
    return ['ok' => false, 'message' => 'Аль хэдийн ' . $word . ($who ? ' — ' . $who['user_name'] . ', ' . admin_when($who['created_at']) : '')];
  }
  admin_log($pdo, $me, $action, 'quote', $id);
  return ['ok' => true, 'message' => 'Хүсэлтийг ' . $word . ' гэж тэмдэглэлээ.'];
}

/** Saves the staff note (at most 2,000 characters) and logs it. */
function admin_quote_note(PDO $pdo, int $id, string $note, array $me): void {
  $note = trim($note);
  if (function_exists('mb_substr')) $note = mb_substr($note, 0, 2000);
  $up = $pdo->prepare('UPDATE inquiries SET staff_note = ? WHERE id = ?');
  $up->execute([$note === '' ? null : $note, $id]);
  if ($up->rowCount() === 1) admin_log($pdo, $me, 'quote_note', 'quote', $id);
}
