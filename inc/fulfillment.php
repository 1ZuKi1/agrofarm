<?php
/**
 * inc/fulfillment.php — how and when a buyer gets their order.
 *
 * The buyer chooses Хүргүүлэх (delivery) or Очиж авах (pick-up), then a DAY —
 * never a time of day: NAF promises a day and staff phone to agree the time.
 *
 *   delivery   the next two working days (never today), Ulaanbaatar only,
 *              for a fee set in /admin/ → Тохиргоо (8,000₮ to start)
 *   pick-up    today — only on a working day before 13:00 — plus the next
 *              two working days, at the office
 *
 * Working day = Monday–Friday and not one of the closed days staff list in
 * /admin/. Every decision is made in Ulaanbaatar time: the hosting server's
 * own clock may be set to another zone, and a 12:30 order in UB must still
 * be offered "Өнөөдөр".
 *
 * Used by: shop-checkout.php (the day buttons), api/checkout-options.php,
 * api/order-create.php (refuses a day not offered right now),
 * api/order-status.php, inc/orders.php (staff email), admin/orders-admin.php.
 * This file is the only place these rules live.
 */

require_once __DIR__ . '/settings.php';

const NAF_TZ = 'Asia/Ulaanbaatar';
const NAF_PICKUP_CUTOFF_HOUR = 13;          // "Өнөөдөр" pick-up only before 13:00
const NAF_OFFICE_HOURS_TEXT = 'Ажлын өдрүүдэд 09:00–18:00';
const NAF_PICKUP_ADDRESS = 'Цома Оффис байр, 402 тоот, Сүхбаатар дүүрэг';
const NAF_PICKUP_MAP_URL = 'https://maps.google.com/?q=Tsoma+Office+Ulaanbaatar';
const NAF_FEE_LINE = 'Хүргэлт (Улаанбаатар)';  // the fee's line on the QPay invoice
const NAF_DEFAULT_DELIVERY_FEE = 8000;

const NAF_WEEKDAYS = [1 => 'Даваа', 2 => 'Мягмар', 3 => 'Лхагва', 4 => 'Пүрэв', 5 => 'Баасан', 6 => 'Бямба', 7 => 'Ням'];

/** Now, in Ulaanbaatar. */
function fulfillment_now(): DateTimeImmutable {
  return new DateTimeImmutable('now', new DateTimeZone(NAF_TZ));
}

function fulfillment_is_working_day(DateTimeImmutable $day, array $closedDays): bool {
  return (int)$day->format('N') <= 5 && !in_array($day->format('Y-m-d'), $closedDays, true);
}

/**
 * The days a buyer may choose right now, in order:
 *   [['date' => '2026-10-15', 'word' => 'Маргааш', 'date_label' => '10-р сарын 15, Лхагва'], …]
 * $method is 'delivery' or 'pickup'; $closedDays a list of 'Y-m-d'.
 */
function fulfillment_days(string $method, DateTimeImmutable $now, array $closedDays): array {
  $now = $now->setTimezone(new DateTimeZone(NAF_TZ));
  $today = $now->setTime(0, 0);
  $picked = [];
  if ($method === 'pickup' && fulfillment_is_working_day($today, $closedDays) && (int)$now->format('G') < NAF_PICKUP_CUTOFF_HOUR) {
    $picked[] = $today;
  }
  // Two working days after today. 60 days is far more than any run of
  // closed days; it only stops a misconfigured list from looping forever.
  $want = count($picked) + 2;
  for ($i = 1; $i <= 60 && count($picked) < $want; $i++) {
    $day = $today->modify("+$i day");
    if (fulfillment_is_working_day($day, $closedDays)) $picked[] = $day;
  }
  $out = [];
  foreach ($picked as $day) {
    $date = $day->format('Y-m-d');
    $out[] = ['date' => $date, 'word' => fulfillment_day_word($date, $now), 'date_label' => fulfillment_date_label($date)];
  }
  return $out;
}

/** Өнөөдөр / Маргааш / Нөгөөдөр when literally true, otherwise the weekday name. */
function fulfillment_day_word(string $date, DateTimeImmutable $now): string {
  $tz = new DateTimeZone(NAF_TZ);
  $today = $now->setTimezone($tz)->setTime(0, 0);
  $day = new DateTimeImmutable($date, $tz);
  $diff = (int)$today->diff($day)->format('%r%a');
  if ($diff === 0) return 'Өнөөдөр';
  if ($diff === 1) return 'Маргааш';
  if ($diff === 2) return 'Нөгөөдөр';
  return NAF_WEEKDAYS[(int)$day->format('N')];
}

/** '2026-10-15' → '10-р сарын 15, Лхагва' */
function fulfillment_date_label(string $date): string {
  $day = new DateTimeImmutable($date, new DateTimeZone(NAF_TZ));
  return (int)$day->format('n') . '-р сарын ' . (int)$day->format('j') . ', ' . NAF_WEEKDAYS[(int)$day->format('N')];
}

function fulfillment_method_label(string $method): string {
  return $method === 'pickup' ? 'Очиж авах' : 'Хүргүүлэх';
}

/** The closed days from /admin/. A broken stored value counts as none (and is logged). */
function fulfillment_closed_days(PDO $pdo): array {
  $raw = settings_get($pdo, 'closed_days', '[]');
  $list = json_decode($raw, true);
  if (!is_array($list)) {
    error_log('fulfillment: closed_days setting is not a JSON list — ignored');
    return [];
  }
  return array_values(array_filter($list, function ($d) {
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
  }));
}

/** The delivery fee in tögrög. A broken stored value falls back to the default (and is logged). */
function fulfillment_delivery_fee(PDO $pdo): int {
  $raw = settings_get($pdo, 'delivery_fee', (string)NAF_DEFAULT_DELIVERY_FEE);
  if (!preg_match('/^\d{1,6}$/', trim($raw))) {
    error_log('fulfillment: delivery_fee setting is not a whole number — using ' . NAF_DEFAULT_DELIVERY_FEE);
    return NAF_DEFAULT_DELIVERY_FEE;
  }
  return (int)trim($raw);
}

/** Everything the checkout page needs to draw its choices (also api/checkout-options.php). */
function fulfillment_options(PDO $pdo, DateTimeImmutable $now): array {
  $closed = fulfillment_closed_days($pdo);
  return [
    'delivery' => fulfillment_days('delivery', $now, $closed),
    'pickup' => fulfillment_days('pickup', $now, $closed),
    'delivery_fee' => fulfillment_delivery_fee($pdo),
    'pickup_address' => NAF_PICKUP_ADDRESS,
    'map_url' => NAF_PICKUP_MAP_URL,
    'office_hours' => NAF_OFFICE_HOURS_TEXT,
  ];
}
