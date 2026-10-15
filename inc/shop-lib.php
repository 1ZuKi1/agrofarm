<?php
/**
 * inc/shop-lib.php — the priced products (products → product_variants →
 * variant_ingredients), read the same way for every consumer:
 *
 *   api/shop-data.php JSON for shop.js (the basket needs live prices/stock)
 *   shop.php          /shop — every category, via shop_sections() below
 *   shop-product.php  /shop/<slug>, rendered on the server
 *   order-create.php  shop_line_name() for the QPay invoice line
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
 * order-create.php and products-admin.php.
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

/**
 * The name a basket line, the QPay invoice and the staff email show for one
 * option of a product. An item sold in one form (the cow statue, the book)
 * has a single option named like the product, so it reads "Үнээний баримал",
 * not "Үнээний баримал — Үнээний баримал". js/checkout.js mirrors this rule.
 */
function shop_line_name(string $product, string $option, ?string $weight): string {
  $name = ($option === $product) ? $product : $product . ' — ' . $option;
  return ($weight !== null && $weight !== '') ? $name . ' (' . $weight . ')' : $name;
}

/**
 * The bottom line of a priced card, from the product's variants (each with
 * 'id', 'price', 'available'):
 *   one option in stock      "120,000₮"     + add that option straight away
 *   several options          "15,000₮-өөс"  (lowest price in stock) + "Сонгох"
 *   nothing in stock         "Дууссан"
 * Returns ['label' => string, 'add' => ?int, 'choose' => bool, 'sold_out' => bool].
 */
function shop_price_label(array $variants): array {
  $inStock = array_values(array_filter($variants, function ($v) { return (int)$v['available'] > 0; }));
  if (!$inStock) return ['label' => 'Дууссан', 'add' => null, 'choose' => false, 'sold_out' => true];
  if (count($variants) === 1) {
    return ['label' => shop_price((int)$inStock[0]['price']), 'add' => (int)$inStock[0]['id'], 'choose' => false, 'sold_out' => false];
  }
  $min = min(array_map(function ($v) { return (int)$v['price']; }, $inStock));
  return ['label' => shop_price($min) . '-өөс', 'add' => null, 'choose' => true, 'sold_out' => false];
}

/**
 * Everything /shop shows, one section per category that has something in it,
 * in the categories' sort order:
 *   [['slug', 'name', 'items' => [card…], 'count' => n, 'more' => null|['url', 'total']], …]
 * A card is ['kind' => 'priced'|'quote', 'name', 'text', 'url',
 *            'image' => ?string (root-absolute), 'price' => shop_price_label()|null].
 * In a category, priced items come first, then all of its equipment (/shop
 * shows a big category as one row that scrolls sideways); 'more' points at
 * the group page when there are more than 4.
 * Priced items with no category end up in a last section, "Бусад", so nothing
 * for sale is ever unreachable.
 *
 * Needs products.category_id (sql/orders-schema-v5.sql); without it, throws a
 * RuntimeException naming that file. shop_products() — and with it the
 * product pages and checkout — does not need the column.
 */
function shop_sections(PDO $pdo): array {
  require_once __DIR__ . '/catalog-lib.php'; // catalog_categories(), catalog_products_by_category()

  try {
    $catOf = $pdo->query('SELECT id, category_id FROM products')->fetchAll(PDO::FETCH_KEY_PAIR);
  } catch (PDOException $e) {
    if ($e->getCode() === '42S22') { // unknown column
      throw new RuntimeException('products.category_id missing — run sql/orders-schema-v5.sql', 0, $e);
    }
    throw $e;
  }

  $priced = []; // category id ('' = none) => cards
  foreach (shop_products($pdo) as $p) {
    if (!$p['variants']) continue;
    $img = $p['variants'][0]['image_path'];
    $priced[(string)($catOf[$p['id']] ?? '')][] = [
      'kind' => 'priced',
      'name' => $p['name_mn'],
      'text' => (string)($p['description_mn'] ?: $p['variants'][0]['benefits_text_mn']),
      'url' => '/shop/' . $p['slug'],
      'image' => $img ? shop_img($img) : null,
      'price' => shop_price_label($p['variants']),
    ];
  }

  $sections = [];
  foreach (catalog_categories($pdo) as $c) {
    $items = $priced[(string)$c['id']] ?? [];
    $equipment = catalog_products_by_category($pdo, (int)$c['id']);
    foreach ($equipment as $e) { // every item: the section scrolls sideways
      $items[] = [
        'kind' => 'quote',
        'name' => $e['name'],
        'text' => (string)$e['intro'],
        'url' => '/shop/equipment/' . $c['slug'] . '/' . $e['slug'],
        'image' => $e['thumb'] ? '/' . $e['thumb'] : null,
        'price' => null,
      ];
    }
    if (!$items) continue;
    $sections[] = [
      'slug' => $c['slug'],
      'name' => $c['name'],
      'items' => $items,
      'more' => count($equipment) > 4 ? ['url' => '/shop/equipment/' . $c['slug'], 'total' => count($equipment)] : null,
      'count' => count($items),
    ];
  }
  if (!empty($priced[''])) {
    $sections[] = ['slug' => 'busad', 'name' => 'Бусад', 'items' => $priced[''], 'more' => null, 'count' => count($priced[''])];
  }
  return $sections;
}
