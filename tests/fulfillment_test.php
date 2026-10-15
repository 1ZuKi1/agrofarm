<?php
/**
 * tests/fulfillment_test.php — checks the delivery / pick-up day rules in
 * inc/fulfillment.php and the settings they read. Run from the project root:
 *
 *   php tests/fulfillment_test.php
 *
 * Prints PASS/FAIL per check and exits 1 if anything failed. The settings
 * checks need the local database (config.php one folder above the project).
 * Not deployed: .cpanel.yml does not copy tests/.
 */

require dirname(__DIR__) . '/inc/db.php';
require dirname(__DIR__) . '/inc/fulfillment.php';

$failed = 0;
function check(bool $ok, string $name): void {
  global $failed;
  echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
  if (!$ok) $failed++;
}

/** A moment in Ulaanbaatar time. 2026-10-13 is a Tuesday. */
function ub(string $when): DateTimeImmutable {
  return new DateTimeImmutable($when, new DateTimeZone('Asia/Ulaanbaatar'));
}
function dates(array $days): array { return array_column($days, 'date'); }
function words(array $days): array { return array_column($days, 'word'); }

// ---- delivery: the next two working days, never today
$d = fulfillment_days('delivery', ub('2026-10-13 10:00'), []);
check(dates($d) === ['2026-10-14', '2026-10-15'], 'delivery Tue 10:00 → Wed, Thu');
check(words($d) === ['Маргааш', 'Нөгөөдөр'], 'delivery Tue 10:00 words Маргааш, Нөгөөдөр');

// ---- pick-up: today before 13:00, plus the next two working days
check(words(fulfillment_days('pickup', ub('2026-10-13 10:00'), [])) === ['Өнөөдөр', 'Маргааш', 'Нөгөөдөр'], 'pickup Tue 10:00 → today + 2');
check(words(fulfillment_days('pickup', ub('2026-10-13 12:59:59'), []))[0] === 'Өнөөдөр', 'pickup Tue 12:59:59 still offers today');
check(words(fulfillment_days('pickup', ub('2026-10-13 13:00:00'), [])) === ['Маргааш', 'Нөгөөдөр'], 'pickup Tue 13:00 → no today');

// ---- weekends are skipped
$d = fulfillment_days('delivery', ub('2026-10-16 10:00'), []);
check(dates($d) === ['2026-10-19', '2026-10-20'], 'delivery Fri → Mon, Tue');
check(words($d) === ['Даваа', 'Мягмар'], 'delivery Fri words are weekday names');
check(words(fulfillment_days('pickup', ub('2026-10-16 10:00'), [])) === ['Өнөөдөр', 'Даваа', 'Мягмар'], 'pickup Fri 10:00 → today, Mon, Tue');
foreach (['2026-10-16 19:00' => 'Fri 19:00', '2026-10-17 11:00' => 'Sat', '2026-10-18 11:00' => 'Sun'] as $when => $label) {
  check(dates(fulfillment_days('pickup', ub($when), [])) === ['2026-10-19', '2026-10-20'], "pickup $label → Mon, Tue");
}

// ---- closed days are skipped like weekends
$d = fulfillment_days('delivery', ub('2026-10-13 10:00'), ['2026-10-14']);
check(dates($d) === ['2026-10-15', '2026-10-16'], 'closed Wed → delivery Thu, Fri');
check($d[0]['word'] === 'Нөгөөдөр', 'closed Wed → first word Нөгөөдөр');
check(!in_array('Өнөөдөр', words(fulfillment_days('pickup', ub('2026-10-13 10:00'), ['2026-10-13'])), true), 'closed today → no Өнөөдөр');

// ---- the host clock may be in another zone: UB time decides
$utc = function (string $when) { return new DateTimeImmutable($when, new DateTimeZone('UTC')); };
check(words(fulfillment_days('pickup', $utc('2026-10-13 04:30'), []))[0] === 'Өнөөдөр', 'UTC 04:30 = 12:30 UB → today offered');
check(!in_array('Өнөөдөр', words(fulfillment_days('pickup', $utc('2026-10-13 05:30'), [])), true), 'UTC 05:30 = 13:30 UB → no today');

// ---- labels
check(fulfillment_date_label('2026-10-14') === '10-р сарын 14, Лхагва', 'date label 10-р сарын 14, Лхагва');
check($d[0]['date_label'] === '10-р сарын 15, Пүрэв', 'days carry their date label');
check(fulfillment_day_word('2026-10-20', ub('2026-10-16 10:00')) === 'Мягмар', 'day word falls back to weekday name');
check(fulfillment_method_label('pickup') === 'Очиж авах' && fulfillment_method_label('delivery') === 'Хүргүүлэх', 'method labels');

// ---- settings
$pdo = naf_db();
$oldClosed = settings_get($pdo, 'closed_days', '[]');
$oldFee = settings_get($pdo, 'delivery_fee', '8000');
try {
  settings_set($pdo, 'closed_days', 'not json');
  check(fulfillment_closed_days($pdo) === [], 'broken closed_days → empty list');
  settings_set($pdo, 'closed_days', '["2026-10-14"]');
  check(fulfillment_closed_days($pdo) === ['2026-10-14'], 'closed_days read back');
  settings_set($pdo, 'delivery_fee', '8000');
  check(fulfillment_delivery_fee($pdo) === 8000, 'delivery fee 8000');
  settings_set($pdo, 'delivery_fee', 'abc');
  check(fulfillment_delivery_fee($pdo) === 8000, 'broken fee → 8000');
  check(settings_get($pdo, 'no_such_key', 'dflt') === 'dflt', 'missing key → default');
} finally {
  settings_set($pdo, 'closed_days', $oldClosed);
  settings_set($pdo, 'delivery_fee', $oldFee);
}

echo $failed ? "$failed check(s) failed\n" : "all checks passed\n";
exit($failed ? 1 : 0);
