<?php
/**
 * inquiry-create.php — receives an inquiry from a product page or from the
 * multi-item inquiry drawer.
 *
 * Order of operations matters: the row is INSERTed first, the email is sent
 * second. mail() on shared cPanel hosting fails quietly often enough that a
 * mail-first design loses customers. If the mail never arrives the lead is
 * still in the database and visible in /admin.
 *
 * Nothing here trusts the posted item list for anything but intent — the
 * product name and SKU that get stored are looked up from the database by
 * SKU, so a tampered form cannot put invented products into our inbox.
 */

require __DIR__ . '/db.php';

const NAF_INQUIRY_TO = 'info@agrofarm.mn';
const NAF_INQUIRY_FROM = 'no-reply@agrofarm.mn';   // must be on our own domain for SPF

function back_with(string $status, ?string $token = null): void {
  $ref = $_SERVER['HTTP_REFERER'] ?? '/equipment';
  // Only ever redirect within this site.
  if (!preg_match('#^https?://(www\.)?agrofarm\.mn/#i', $ref)) $ref = '/equipment';
  $ref = strtok($ref, '#');
  // strpos, not str_contains — this host runs PHP 7.4.
  $sep = (strpos($ref, '?') !== false) ? '&' : '?';
  $url = $ref . $sep . 'inquiry=' . rawurlencode($status);
  if ($token) $url .= '&ref=' . rawurlencode($token);
  header('Location: ' . $url, true, 303);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  back_with('error');
}

// Honeypot — silently accept so a bot learns nothing, but store nothing.
if (trim((string)($_POST['website'] ?? '')) !== '') {
  back_with('ok');
}

$name    = trim((string)($_POST['name'] ?? ''));
$phone   = trim((string)($_POST['phone'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$company = trim((string)($_POST['company'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || $phone === '') {
  back_with('missing');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  back_with('bademail');
}

$posted = json_decode((string)($_POST['items'] ?? '[]'), true);
if (!is_array($posted) || !$posted) {
  back_with('empty');
}
$posted = array_slice($posted, 0, 50);

try {
  $pdo = naf_db();

  // Resolve every posted SKU against the catalogue. Unknown SKUs are dropped.
  $wanted = [];
  foreach ($posted as $row) {
    $sku = trim((string)($row['sku'] ?? ''));
    $qty = (int)($row['quantity'] ?? 1);
    if ($sku === '') continue;
    $wanted[$sku] = max(1, min(9999, $qty));
  }
  if (!$wanted) back_with('empty');

  $in = implode(',', array_fill(0, count($wanted), '?'));
  $st = $pdo->prepare("SELECT id, sku, name FROM equipment_products WHERE sku IN ($in) AND active = 1");
  $st->execute(array_keys($wanted));
  $found = $st->fetchAll();
  if (!$found) back_with('empty');

  $token = bin2hex(random_bytes(16));

  $pdo->beginTransaction();
  $ins = $pdo->prepare(
    'INSERT INTO inquiries (public_token, buyer_name, buyer_phone, buyer_email,
     buyer_company, message) VALUES (?, ?, ?, ?, ?, ?)'
  );
  $ins->execute([$token, $name, $phone, ($email ?: null), ($company ?: null), ($message ?: null)]);
  $inquiryId = (int)$pdo->lastInsertId();

  $insItem = $pdo->prepare(
    'INSERT INTO inquiry_items (inquiry_id, product_id, product_name_snapshot,
     sku_snapshot, quantity) VALUES (?, ?, ?, ?, ?)'
  );
  foreach ($found as $f) {
    $insItem->execute([$inquiryId, (int)$f['id'], $f['name'], $f['sku'], $wanted[$f['sku']]]);
  }
  $pdo->commit();
} catch (\Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  back_with('error');
}

// ---- Notification. A failure here must not fail the request. --------------
$lines = ["Шинэ хүсэлт — agrofarm.mn", str_repeat('-', 40), ''];
$lines[] = 'Нэр:      ' . $name;
$lines[] = 'Утас:     ' . $phone;
if ($email !== '')   $lines[] = 'И-мэйл:   ' . $email;
if ($company !== '') $lines[] = 'Байгууллага: ' . $company;
$lines[] = '';
$lines[] = 'Бүтээгдэхүүн:';
foreach ($found as $f) {
  $lines[] = sprintf('  %-14s %s  ×%d', $f['sku'], $f['name'], $wanted[$f['sku']]);
}
if ($message !== '') {
  $lines[] = '';
  $lines[] = 'Нэмэлт мэдээлэл:';
  $lines[] = $message;
}
$lines[] = '';
$lines[] = 'Админ: https://agrofarm.mn/admin';

$headers = [
  'From: Agrofarm <' . NAF_INQUIRY_FROM . '>',
  'Content-Type: text/plain; charset=utf-8',
  'X-Mailer: agrofarm.mn',
];
// Reply-To only when the buyer gave a real address, so hitting reply works.
if ($email !== '') {
  $headers[] = 'Reply-To: ' . $email;
}

$sent = @mail(
  NAF_INQUIRY_TO,
  '=?UTF-8?B?' . base64_encode('Шинэ хүсэлт — ' . $name) . '?=',
  implode("\n", $lines),
  implode("\r\n", $headers)
);

if ($sent) {
  try {
    $pdo->prepare('UPDATE inquiries SET emailed = 1 WHERE id = ?')->execute([$inquiryId]);
  } catch (\Throwable $e) {
    // The inquiry is saved; the flag is only bookkeeping.
  }
}

back_with('ok', $token);
