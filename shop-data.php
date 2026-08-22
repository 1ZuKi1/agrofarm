<?php
/**
 * shop-data.php — public, read-only product catalog for the shop page.
 *
 * No auth, no input, no user-controlled query surface. Only returns products
 * and variants marked active — inactive rows are silently omitted, not
 * flagged. This is intentionally the ONLY thing this file does.
 */

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = naf_db();

  $products = $pdo->query('SELECT id, slug, name_mn, name_en, description_mn, description_en FROM products WHERE active = 1 ORDER BY id')->fetchAll();

  $variantStmt = $pdo->prepare(
    'SELECT id, product_id, name_mn, name_en, price, stock, weight_label, standard_code,
     storage_text_mn, storage_text_en, benefits_text_mn, benefits_text_en,
     usage_text_mn, usage_text_en, image_path, sort_order
     FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY sort_order, id'
  );
  $ingredientStmt = $pdo->prepare(
    'SELECT name, percentage FROM variant_ingredients WHERE variant_id = ? ORDER BY sort_order, id'
  );

  foreach ($products as &$product) {
    $variantStmt->execute([$product['id']]);
    $variants = $variantStmt->fetchAll();
    foreach ($variants as &$variant) {
      $ingredientStmt->execute([$variant['id']]);
      $variant['ingredients'] = $ingredientStmt->fetchAll();
    }
    unset($variant);
    $product['variants'] = $variants;
  }
  unset($product);

  echo json_encode(['products' => $products]);
} catch (\Throwable $e) {
  // Never leak exception details (DB host/name, stack trace) to this
  // endpoint's anonymous, unauthenticated visitors.
  http_response_code(500);
  echo json_encode(['error' => 'Internal server error']);
}
