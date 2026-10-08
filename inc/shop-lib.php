<?php
/**
 * inc/shop-lib.php — the priced products (products → product_variants →
 * variant_ingredients), read the same way for every consumer:
 *
 *   api/shop-data.php JSON for shop.js (the basket needs live prices/stock)
 *   shop.php          the /shop grid, rendered on the server
 *   shop-product.php  /shop/<slug>, rendered on the server
 *
 * Server rendering is what lets search engines see these products; before,
 * shop.js drew them in the browser after the page loaded.
 */

require_once __DIR__ . '/db.php';

/**
 * Active products with their active variants. Each variant carries
 * 'ingredients' and 'available' — stock minus everything reserved by an order
 * that hasn't been cancelled/expired. The reservation-set literal
 * ('pending','paid','fulfilled') must stay in sync with the same literal in
 * order-create.php, slots.php and products-admin.php.
 *
 * $slug limits the result to that one product.
 */
function shop_products(PDO $pdo, ?string $slug = null): array {
  if ($slug === null) {
    $products = $pdo->query('SELECT id, slug, name_mn, name_en, description_mn, description_en FROM products WHERE active = 1 ORDER BY id')->fetchAll();
  } else {
    $st = $pdo->prepare('SELECT id, slug, name_mn, name_en, description_mn, description_en FROM products WHERE active = 1 AND slug = ?');
    $st->execute([$slug]);
    $products = $st->fetchAll();
  }

  $variantStmt = $pdo->prepare(
    'SELECT id, product_id, name_mn, name_en, price, stock, weight_label, standard_code,
     storage_text_mn, storage_text_en, benefits_text_mn, benefits_text_en,
     usage_text_mn, usage_text_en, image_path, sort_order
     FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY sort_order, id'
  );
  $ingredientStmt = $pdo->prepare(
    'SELECT name, percentage FROM variant_ingredients WHERE variant_id = ? ORDER BY sort_order, id'
  );
  $reservedStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.variant_id = ? AND o.status IN ('pending','paid','fulfilled')"
  );

  foreach ($products as &$product) {
    $variantStmt->execute([$product['id']]);
    $variants = $variantStmt->fetchAll();
    foreach ($variants as &$variant) {
      $ingredientStmt->execute([$variant['id']]);
      $variant['ingredients'] = $ingredientStmt->fetchAll();
      $reservedStmt->execute([$variant['id']]);
      $reserved = (int)$reservedStmt->fetchColumn();
      $variant['available'] = max(0, (int)$variant['stock'] - $reserved);
    }
    unset($variant);
    $product['variants'] = $variants;
  }
  unset($product);

  return $products;
}

/**
 * Stored image paths are site-relative ("img/products/x.jpg"). Pages now live
 * at deeper addresses like /shop/<slug>, so they must be made root-absolute or
 * the browser would look for /shop/img/….
 */
function shop_img(?string $path): string {
  $path = (string)$path;
  if ($path === '' || preg_match('#^(https?:)?//#i', $path) || $path[0] === '/') return $path;
  return '/' . $path;
}

/** 15000 → "15,000₮" — the same grouping the browser's toLocaleString() gave. */
function shop_price(int $amount): string {
  return number_format($amount) . '₮';
}
