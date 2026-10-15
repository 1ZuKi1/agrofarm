<?php
/**
 * tests/shop_test.php — checks the shop data layer in inc/shop-lib.php:
 * the single-option line name, the card price labels and the category
 * sections. Run from the project root, after sql/orders-schema-v5.sql:
 *
 *   php tests/shop_test.php
 *
 * Uses the local database (config.php one folder above the project) and a
 * temporary copy of it (naf_nov5) for the "v5 not run" check. Not deployed.
 */

require dirname(__DIR__) . '/inc/shop-lib.php';

$failed = 0;
function check(bool $ok, string $name): void {
  global $failed;
  echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
  if (!$ok) $failed++;
}

// ---- single-option line name
check(shop_line_name('Үнээний баримал', 'Үнээний баримал', null) === 'Үнээний баримал', 'one option: product name only');
check(shop_line_name('Эрдэст Долооц', 'Содтой', '5кг') === 'Эрдэст Долооц — Содтой (5кг)', 'several options: product — option (weight)');
check(shop_line_name('Ном', 'Ном', '') === 'Ном', 'empty weight adds nothing');

// ---- card price labels
$one = shop_price_label([['id' => 7, 'price' => 120000, 'available' => 3]]);
check($one['label'] === '120,000₮' && $one['add'] === 7 && !$one['choose'] && !$one['sold_out'], 'one available option → price + add');
$many = shop_price_label([
  ['id' => 1, 'price' => 15000, 'available' => 5],
  ['id' => 2, 'price' => 12000, 'available' => 0],
  ['id' => 3, 'price' => 18000, 'available' => 2],
]);
check($many['label'] === '15,000₮-өөс' && $many['add'] === null && $many['choose'] && !$many['sold_out'], 'several options → lowest AVAILABLE price "-өөс" + choose');
$none = shop_price_label([['id' => 1, 'price' => 15000, 'available' => 0]]);
check($none['label'] === 'Дууссан' && $none['add'] === null && !$none['choose'] && $none['sold_out'], 'nothing in stock → Дууссан');
$lastOne = shop_price_label([['id' => 1, 'price' => 15000, 'available' => 0], ['id' => 2, 'price' => 16000, 'available' => 4]]);
check($lastOne['label'] === '16,000₮-өөс' && $lastOne['choose'], 'several options, one available → still choose on the product page');

// ---- sections
$pdo = naf_db();
$sections = shop_sections($pdo);
$slugs = array_column($sections, 'slug');
check(array_slice($slugs, 0, 3) === ['mal-tejeel-erdes', 'beleg-dursgal', 'milking-parts'], 'order: feed, gifts, then the equipment groups (' . implode(',', array_slice($slugs, 0, 3)) . ')');
$by = array_column($sections, null, 'slug');
check(isset($by['beleg-dursgal']) && array_column($by['beleg-dursgal']['items'], 'name') === ['Үнээний баримал', 'Ном'], 'Бэлэг дурсгал holds the statue and the book');
check(($by['beleg-dursgal']['items'][0]['kind'] ?? '') === 'priced' && $by['beleg-dursgal']['more'] === null, 'gift items are priced, no "see all"');
check(($by['mal-tejeel-erdes']['items'][0]['name'] ?? '') === 'Эрдэст Долооц', 'Долооц is in Малын тэжээл, эрдэс');
check(count($by['milking-parts']['items']) === 21 && $by['milking-parts']['count'] === 21 && ($by['milking-parts']['more']['total'] ?? 0) === 21
  && $by['milking-parts']['more']['url'] === '/shop/equipment/milking-parts', 'equipment group: all 21 cards (the row scrolls) + see all');
check($by['milking-parts']['items'][0]['kind'] === 'quote' && $by['milking-parts']['items'][0]['price'] === null, 'equipment cards are quote cards');
check(strpos($by['beleg-dursgal']['items'][0]['url'], '/shop/uneenii-barimal') === 0, 'priced card links to /shop/<slug>');

$pdo->exec("INSERT INTO categories (slug, name, sort_order) VALUES ('test-empty', 'Хоосон', 3)");
$pdo->exec("INSERT INTO products (slug, name_mn, description_mn) VALUES ('test-nocat', 'Ангилалгүй', 'x')");
$noCatId = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO product_variants (product_id, name_mn, price, stock) VALUES ($noCatId, 'Ангилалгүй', 1000, 5)");
try {
  $s2 = shop_sections($pdo);
  $slugs2 = array_column($s2, 'slug');
  check(!in_array('test-empty', $slugs2, true), 'empty category hidden');
  check(end($slugs2) === 'busad' && end($s2)['name'] === 'Бусад' && end($s2)['items'][0]['name'] === 'Ангилалгүй', 'uncategorised item → last section Бусад');
} finally {
  $pdo->exec("DELETE FROM categories WHERE slug = 'test-empty'");
  $pdo->exec("DELETE FROM products WHERE id = $noCatId");
}
check(!in_array('busad', array_column(shop_sections($pdo), 'slug'), true), 'no Бусад section when every item has a category');

// ---- v5 not run: a clear error, not a bare PDO one
shell_exec('mysql -uroot -e "DROP DATABASE IF EXISTS naf_nov5; CREATE DATABASE naf_nov5 CHARACTER SET utf8mb4; GRANT ALL ON naf_nov5.* TO \'u\'@\'127.0.0.1\'; GRANT ALL ON naf_nov5.* TO \'u\'@\'localhost\';"');
shell_exec('mysqldump -uroot naf | mysql -uroot naf_nov5');
shell_exec('mysql -uroot naf_nov5 -e "ALTER TABLE products DROP FOREIGN KEY fk_product_category, DROP COLUMN category_id"');
try {
  $old = new PDO('mysql:host=' . NAF_DB_HOST . ';dbname=naf_nov5;charset=utf8mb4', NAF_DB_USER, NAF_DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $msg = '';
  try { shop_sections($old); } catch (RuntimeException $e) { $msg = $e->getMessage(); }
  check(strpos($msg, 'orders-schema-v5') !== false, "v5 missing → RuntimeException naming the SQL file ($msg)");
  check(count(shop_products($old)) >= 1, 'v5 missing → shop_products (product pages, checkout) still works');
} finally {
  shell_exec('mysql -uroot -e "DROP DATABASE IF EXISTS naf_nov5"');
}

echo $failed ? "$failed check(s) failed\n" : "all checks passed\n";
exit($failed ? 1 : 0);
