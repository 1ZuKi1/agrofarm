<?php
/**
 * settings-admin.php — /admin/ → Тохиргоо: the delivery fee and the closed
 * days (Цагаан сар, Наадам… — skipped by the checkout like weekends).
 * Values live in the `settings` table; inc/fulfillment.php reads them.
 *
 * Auth matches orders-admin.php exactly: PHP session + CSRF token issued at
 * login. Every action answers with the current values:
 *   {"delivery_fee": 8000, "closed_days": ["2027-07-11", …]}
 *
 *   get            current values
 *   save_fee       fee        whole tögrög, 0–100,000
 *   add_closed     from, to   'Y-m-d'; to defaults to from; at most 31 days.
 *                             Merged with the list, de-duplicated, sorted;
 *                             days already past are dropped.
 *   remove_closed  date       'Y-m-d'
 */

require dirname(__DIR__) . '/inc/db.php';
require_once dirname(__DIR__) . '/inc/fulfillment.php';

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

/** 'Y-m-d' → DateTimeImmutable in UB time, or null if not a real date. */
function settings_admin_date(string $s): ?DateTimeImmutable {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
  $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s, new DateTimeZone(NAF_TZ));
  return ($d && $d->format('Y-m-d') === $s) ? $d : null;
}

function settings_admin_fail(string $msg): void {
  http_response_code(400);
  echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

function settings_admin_state(PDO $pdo): array {
  $days = fulfillment_closed_days($pdo);
  return [
    'delivery_fee' => fulfillment_delivery_fee($pdo),
    'closed_days' => array_map(function ($d) {
      return ['date' => $d, 'label' => fulfillment_date_label($d)];
    }, $days),
  ];
}

try {
  $pdo = naf_db();
  $action = $_POST['action'] ?? '';

  if ($action === 'save_fee') {
    $fee = trim((string)($_POST['fee'] ?? ''));
    if (!preg_match('/^\d{1,6}$/', $fee) || (int)$fee > 100000) {
      settings_admin_fail('Хүргэлтийн төлбөр 0–100,000₮ байна');
    }
    settings_set($pdo, 'delivery_fee', (string)(int)$fee);
  } elseif ($action === 'add_closed') {
    $from = settings_admin_date(trim((string)($_POST['from'] ?? '')));
    $toRaw = trim((string)($_POST['to'] ?? ''));
    $to = $toRaw === '' ? $from : settings_admin_date($toRaw);
    if (!$from || !$to) settings_admin_fail('Огноогоо сонгоно уу');
    if ($to < $from) settings_admin_fail('Дуусах өдөр эхлэх өдрөөс өмнө байж болохгүй');
    if ((int)$from->diff($to)->format('%a') > 30) settings_admin_fail('Нэг удаад 31 хоногоос ихгүй хугацаа нэмнэ');
    $today = fulfillment_now()->format('Y-m-d');
    $days = fulfillment_closed_days($pdo);
    for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) $days[] = $d->format('Y-m-d');
    $days = array_values(array_unique(array_filter($days, function ($d) use ($today) { return $d >= $today; })));
    sort($days);
    settings_set($pdo, 'closed_days', json_encode($days));
  } elseif ($action === 'remove_closed') {
    $date = (string)($_POST['date'] ?? '');
    $days = array_values(array_filter(fulfillment_closed_days($pdo), function ($d) use ($date) { return $d !== $date; }));
    settings_set($pdo, 'closed_days', json_encode($days));
  } elseif ($action !== 'get') {
    settings_admin_fail('Үйлдэл буруу байна');
  }

  echo json_encode(settings_admin_state($pdo), JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
  error_log('settings-admin: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Серверийн алдаа гарлаа — дахин оролдоно уу'], JSON_UNESCAPED_UNICODE);
}
