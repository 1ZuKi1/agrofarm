<?php
/**
 * order-create.php — public checkout endpoint.
 *
 * Sequence (per spec, do not reorder): re-validate the day and stock ->
 * generate public_token -> call QPay invoice-create -> ONLY on success, write
 * the order + items (this single write reserves the stock).
 * If the QPay call fails, nothing is written and nothing is reserved.
 *
 * The buyer chooses Хүргүүлэх (delivery, with a fee) or Очиж авах (pick-up)
 * and a day. The day must be one inc/fulfillment.php offers RIGHT NOW: a page
 * left open past 13:00 or past midnight gets a 409 with the fresh choices,
 * before anything is created at QPay. Delivery adds the fee to the total and
 * as its own line on the QPay invoice.
 */

require dirname(__DIR__) . '/inc/db.php';
require dirname(__DIR__) . '/inc/qpay.php';
require dirname(__DIR__) . '/inc/orders.php';
require_once dirname(__DIR__) . '/inc/fulfillment.php';

// Error messages are shown to the buyer as-is (js/shop.js), so they are in
// Mongolian like the rest of the shop.

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Зөвшөөрөгдөөгүй хүсэлт']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  http_response_code(400);
  echo json_encode(['error' => 'Хүсэлт буруу байна']);
  exit;
}

$items = $input['items'] ?? [];
$buyerName = trim($input['buyer_name'] ?? '');
$buyerPhone = trim($input['buyer_phone'] ?? '');
$buyerAddress = trim($input['buyer_address'] ?? '');
$buyerNote = trim($input['buyer_note'] ?? '') ?: null;
$fulfillment = $input['fulfillment'] ?? '';
$deliveryDate = trim((string)($input['delivery_date'] ?? ''));

if (!is_array($items) || !count($items)) {
  http_response_code(400);
  echo json_encode(['error' => 'Сагс хоосон байна']);
  exit;
}
if ($fulfillment !== 'delivery' && $fulfillment !== 'pickup') {
  http_response_code(400);
  echo json_encode(['error' => 'Хүргүүлэх эсвэл очиж авахаа сонгоно уу']);
  exit;
}
if ($buyerName === '' || $buyerPhone === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Нэр, утсаа бөглөнө үү']);
  exit;
}
if ($fulfillment === 'delivery' && $buyerAddress === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Хүргэлтийн хаягаа бичнэ үү']);
  exit;
}
if ($fulfillment === 'pickup') $buyerAddress = null; // nothing to deliver to
// Mongolian numbers are 8 digits. Spaces, dashes and a +976 prefix are
// accepted and stripped, so "+976 9911-2233" is stored as "99112233".
$buyerPhone = preg_replace('/[\s\-().]/', '', $buyerPhone);
$buyerPhone = preg_replace('/^(\+?976)(?=\d{8}$)/', '', $buyerPhone);
if (!preg_match('/^\d{8}$/', $buyerPhone)) {
  http_response_code(400);
  echo json_encode(['error' => 'Утасны дугаар 8 оронтой байх ёстой']);
  exit;
}

if ($deliveryDate === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Өдрөө сонгоно уу']);
  exit;
}

// Everything below touches the DB — wrapped in an outer safety net so a
// PDOException (e.g. a DB outage) never leaks a raw stack trace / DB
// host-name to this endpoint's anonymous visitors. The two inner try/catch
// blocks below (QPay call, and the order+items transaction) already have
// their own specific error handling and don't rethrow, so this outer catch
// only ever fires for DB-only failures that had no handler before.
try {
  $pdo = naf_db();

  // The chosen day must still be on offer (inc/fulfillment.php). If not —
  // typically a page left open past 13:00 or midnight — send the fresh
  // choices back so the page can redraw its day buttons.
  $options = fulfillment_options($pdo, fulfillment_now());
  if (!in_array($deliveryDate, array_column($options[$fulfillment], 'date'), true)) {
    http_response_code(409);
    echo json_encode(['error' => 'Сонгосон өдөр боломжгүй болсон — өөр өдөр сонгоно уу', 'reason' => 'day', 'options' => $options], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $deliveryFee = $fulfillment === 'delivery' ? $options['delivery_fee'] : 0;

  // Free the stock of abandoned orders (cancelling their QR first). At most
  // one per request: each may wait on QPay, and the buyer is waiting here.
  orders_expire_stale($pdo, 1);

  $variantStmt = $pdo->prepare(
    'SELECT v.id, v.name_mn, v.price, v.stock, v.weight_label, p.name_mn AS product_name
     FROM product_variants v JOIN products p ON p.id = v.product_id
     WHERE v.id = ? AND v.active = 1 AND p.active = 1'
  );
  // Reservation-set literal ('pending','paid','fulfilled') — must stay in sync
  // with the same literal in shop-data.php and products-admin.php.
  $reservedStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.variant_id = ? AND o.status IN ('pending','paid','fulfilled')"
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
      echo json_encode(['error' => 'Сагсанд буруу мөр байна']);
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
      echo json_encode(['error' => 'Сагсан дахь нэг бараа зарагдаж дууссан байна']);
      exit;
    }

    $reservedStmt->execute([$variantId]);
    $reserved = (int)$reservedStmt->fetchColumn();
    $available = $variant['stock'] - $reserved;

    if ($qty > $available) {
      http_response_code(400);
      echo json_encode(['error' => $variant['product_name'] . ' — ' . $variant['name_mn'] . ': нөөцөд ' . max(0, $available) . ' ширхэг үлдсэн']);
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
      // What the buyer's bank app shows for this line.
      'invoice_line' => trim($variant['product_name'] . ' — ' . $variant['name_mn'] . ($variant['weight_label'] ? ' (' . $variant['weight_label'] . ')' : '')),
    ];
  }

  if ($total <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Захиалгын дүн буруу байна']);
    exit;
  }
  $subtotal = $total;           // the goods
  $total = $subtotal + $deliveryFee;

  // The page sends the total it showed the buyer. If the basket changed after
  // the page drew it (edited in the basket drawer or another tab), or a price
  // or the delivery fee changed meanwhile, refuse rather than charge an
  // amount the buyer never saw; the page redraws and they press again.
  if (isset($input['expected_total']) && (int)$input['expected_total'] !== $total) {
    http_response_code(409);
    echo json_encode(['error' => 'Захиалгын дүн өөрчлөгдсөн — шалгаад дахин илгээнэ үү', 'reason' => 'total', 'options' => $options], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $publicToken = bin2hex(random_bytes(16));

  try {
    $qpayToken = qpay_token();
    $callbackUrl = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/qpay-callback.php?order=' . $publicToken;
    $invoiceLines = array_map(function ($li) {
      return ['description' => $li['invoice_line'], 'quantity' => $li['qty'], 'unit_price' => $li['price']];
    }, $lineItems);
    if ($deliveryFee > 0) {
      $invoiceLines[] = ['description' => NAF_FEE_LINE, 'quantity' => 1, 'unit_price' => $deliveryFee];
    }
    $invoice = qpay_create_invoice($qpayToken, $publicToken, $invoiceLines, $callbackUrl, 'Нүүдэлчин Агро Ферм — онлайн захиалга');
    $banks = qpay_bank_links($invoice);
  } catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'Төлбөр эхлүүлж чадсангүй — дахин оролдоно уу']);
    exit;
  }

  $pdo->beginTransaction();
  try {
    // expires_at is computed via MySQL's own NOW() rather than a PHP-computed
    // value, so it's always consistent with the NOW() used by every lazy-
    // expiry sweep, regardless of any PHP/MySQL timezone mismatch.
    // qr_image/qr_text are persisted so a buyer who navigates away mid-payment
    // (or revisits the ?order=<token> link) can be shown the same QR again
    // without re-creating the order — see order-status.php and shop.js.
    // qpay_urls (bank-app deep links) likewise, for phone buyers.
    $orderStmt = $pdo->prepare(
      "INSERT INTO orders (public_token, status, buyer_name, buyer_phone, buyer_address, buyer_note, fulfillment, delivery_date, delivery_slot, subtotal, delivery_fee, total, qpay_invoice_id, qr_image, qr_text, qpay_urls, expires_at)
       VALUES (?, 'pending', ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 20 MINUTE))"
    );
    $orderStmt->execute([$publicToken, $buyerName, $buyerPhone, $buyerAddress, $buyerNote, $fulfillment, $deliveryDate, $subtotal, $deliveryFee, $total, $invoice['invoice_id'], $invoice['qr_image'] ?? null, $invoice['qr_text'] ?? null, json_encode($banks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $orderId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
      'INSERT INTO order_items (order_id, variant_id, variant_name_snapshot, unit_price_snapshot, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($lineItems as $li) {
      $itemStmt->execute([$orderId, $li['variant_id'], $li['invoice_line'], $li['price'], $li['qty'], $li['line_total']]);
    }
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Захиалга хадгалж чадсангүй — дахин оролдоно уу']);
    exit;
  }

  echo json_encode([
    'token' => $publicToken,
    'order_no' => order_number($orderId),
    // Seconds the order (and its QR) stays valid — the QR screen counts down.
    'expires_in' => 20 * 60,
    'total' => $total,
    'subtotal' => $subtotal,
    'delivery_fee' => $deliveryFee,
    'fulfillment' => $fulfillment,
    'qr_image' => $invoice['qr_image'] ?? null,
    'qr_text' => $invoice['qr_text'] ?? null,
    'banks' => $banks,
  ]);
} catch (\Throwable $e) {
  error_log('order-create: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Серверийн алдаа гарлаа — дахин оролдоно уу']);
}
