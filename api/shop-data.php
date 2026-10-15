<?php
/**
 * shop-data.php — public, read-only product catalog for shop.js.
 *
 * No auth, no input, no user-controlled query surface. Only returns products
 * and variants marked active — inactive rows are silently omitted, not
 * flagged. The pages themselves are rendered by PHP now (shop.php,
 * shop-product.php); shop.js still needs this for live prices and stock in
 * the basket. The query lives in inc/shop-lib.php, shared with those pages.
 */

require dirname(__DIR__) . '/inc/shop-lib.php';

header('Content-Type: application/json; charset=utf-8');

try {
  echo json_encode(['products' => shop_products(naf_db())]);
} catch (\Throwable $e) {
  // Never leak exception details (DB host/name, stack trace) to this
  // endpoint's anonymous, unauthenticated visitors.
  http_response_code(500);
  echo json_encode(['error' => 'Internal server error']);
}
