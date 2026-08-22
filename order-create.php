<?php
/**
 * order-create.php — public checkout endpoint.
 *
 * Sequence (per spec, do not reorder): re-validate stock/slot -> generate
 * public_token -> call QPay invoice-create -> ONLY on success, write the
 * order + items (this single write reserves stock and the delivery slot).
 * If the QPay call fails, nothing is written and nothing is reserved.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/qpay.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid request']);
  exit;
}

$items = $input['items'] ?? [];
$buyerName = trim($input['buyer_name'] ?? '');
$buyerPhone = trim($input['buyer_phone'] ?? '');
$buyerAddress = trim($input['buyer_address'] ?? '');
$buyerNote = trim($input['buyer_note'] ?? '') ?: null;
$deliveryDate = trim($input['delivery_date'] ?? '');
$deliverySlot = trim($input['delivery_slot'] ?? '');

if (!is_array($items) || !count($items)) {
  http_response_code(400);
  echo json_encode(['error' => 'Cart is empty']);
  exit;
}
if ($buyerName === '' || $buyerPhone === '' || $buyerAddress === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Name, phone, and address are required']);
  exit;
}

$validSlots = ['10:00-12:00', '14:00-16:00', '16:00-18:00'];
if (!in_array($deliverySlot, $validSlots, true)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid delivery slot']);
  exit;
}
$today = new DateTime('today');
$dateObj = DateTime::createFromFormat('Y-m-d', $deliveryDate);
if (!$dateObj || $dateObj < $today) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid delivery date']);
  exit;
}
$deliveryDate = $dateObj->format('Y-m-d');

$pdo = naf_db();
$pdo->exec("UPDATE orders SET status='expired' WHERE status='pending' AND expires_at < NOW()");

$slotCountStmt = $pdo->prepare(
  "SELECT COUNT(*) FROM orders WHERE delivery_date=? AND delivery_slot=? AND status IN ('pending','paid')"
);
$slotCountStmt->execute([$deliveryDate, $deliverySlot]);
if ((int)$slotCountStmt->fetchColumn() >= 5) {
  http_response_code(400);
  echo json_encode(['error' => 'That delivery slot is now full — please pick another']);
  exit;
}

$variantStmt = $pdo->prepare('SELECT id, name_mn, price, stock FROM product_variants WHERE id = ? AND active = 1');
$reservedStmt = $pdo->prepare(
  "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
   JOIN orders o ON o.id = oi.order_id
   WHERE oi.variant_id = ? AND o.status IN ('pending','paid')"
);

// Merge quantities by variant_id first, so a request that lists the same
// variant on more than one cart line is validated against its combined
// quantity — never checked line-by-line against the same stock in isolation.
$requestedByVariant = [];
foreach ($items as $item) {
  $variantId = (int)($item['variant_id'] ?? 0);
  $qty = (int)($item['quantity'] ?? 0);
  if ($variantId <= 0 || $qty <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid cart line']);
    exit;
  }
  $requestedByVariant[$variantId] = ($requestedByVariant[$variantId] ?? 0) + $qty;
}

$lineItems = [];
$total = 0;
foreach ($requestedByVariant as $variantId => $qty) {
  $variantStmt->execute([$variantId]);
  $variant = $variantStmt->fetch();
  if (!$variant) {
    http_response_code(400);
    echo json_encode(['error' => 'A product in your cart is no longer available']);
    exit;
  }

  $reservedStmt->execute([$variantId]);
  $reserved = (int)$reservedStmt->fetchColumn();
  $available = $variant['stock'] - $reserved;

  if ($qty > $available) {
    http_response_code(400);
    echo json_encode(['error' => $variant['name_mn'] . ' — only ' . max(0, $available) . ' left in stock']);
    exit;
  }

  $lineTotal = $variant['price'] * $qty;
  $total += $lineTotal;
  $lineItems[] = [
    'variant_id' => $variantId,
    'name' => $variant['name_mn'],
    'price' => $variant['price'],
    'qty' => $qty,
    'line_total' => $lineTotal,
  ];
}

if ($total <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid order total']);
  exit;
}

$publicToken = bin2hex(random_bytes(16));

try {
  $qpayToken = qpay_token();
  $callbackUrl = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/qpay-callback.php?order=' . $publicToken;
  $invoice = qpay_create_invoice($qpayToken, $publicToken, $total, $callbackUrl, 'Nuudelchin Agro Farm order');
} catch (Throwable $e) {
  http_response_code(502);
  echo json_encode(['error' => "Could not start payment — please try again"]);
  exit;
}

$expiresAt = (new DateTime('+20 minutes'))->format('Y-m-d H:i:s');

$pdo->beginTransaction();
try {
  $orderStmt = $pdo->prepare(
    "INSERT INTO orders (public_token, status, buyer_name, buyer_phone, buyer_address, buyer_note, delivery_date, delivery_slot, subtotal, total, qpay_invoice_id, expires_at)
     VALUES (?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
  );
  $orderStmt->execute([$publicToken, $buyerName, $buyerPhone, $buyerAddress, $buyerNote, $deliveryDate, $deliverySlot, $total, $total, $invoice['invoice_id'], $expiresAt]);
  $orderId = (int)$pdo->lastInsertId();

  $itemStmt = $pdo->prepare(
    'INSERT INTO order_items (order_id, variant_id, variant_name_snapshot, unit_price_snapshot, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)'
  );
  foreach ($lineItems as $li) {
    $itemStmt->execute([$orderId, $li['variant_id'], $li['name'], $li['price'], $li['qty'], $li['line_total']]);
  }
  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'Could not save your order — please try again']);
  exit;
}

echo json_encode([
  'token' => $publicToken,
  'total' => $total,
  'qr_image' => $invoice['qr_image'] ?? null,
  'qr_text' => $invoice['qr_text'] ?? null,
]);
