<?php
/**
 * products.php — /admin/products: Бүтээгдэхүүн. The shop's products, their
 * options (төрөл), prices, stock and ingredients.
 *
 * The page gives the frame; admin/js/products.js (moved from the old one-page
 * admin.js) fills it from admin/products-admin.php — one card per product,
 * its options as rows — and opens the editors and the delete confirmation in
 * a dialog. Saves and deletes are logged (product_save / variant_save /
 * product_delete / variant_delete) with who did them.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo);

admin_page_start($pdo, $me, [
  'title' => 'Бүтээгдэхүүн',
  'active' => 'products',
  'sub' => 'Дэлгүүрийн бараа, төрөл, үнэ. «Зарах боломжтой» = нийт нөөцөөс захиалсныг хассан тоо.',
  'actions' => '<button type="button" class="ad-btn ad-btn--primary" id="addProductBtn">+ Бүтээгдэхүүн нэмэх</button>',
]);
?>
<div class="ad-stats" id="productStats" aria-live="polite"></div>

<div class="ad-filters">
  <nav class="ad-tabs" id="productTabs" aria-label="Нөөцөөр шүүх" style="margin:0"></nav>
  <div class="ad-field" style="flex:1 1 220px;max-width:340px;margin-left:auto">
    <label for="productSearch" class="ad-sr">Бүтээгдэхүүн хайх</label>
    <input type="search" id="productSearch" placeholder="Бүтээгдэхүүн хайх…">
  </div>
</div>

<div id="productsTab"><p class="ad-empty">Ачааллаж байна…</p></div>

<div id="productEditor" class="ad-modal" role="dialog" aria-modal="true" aria-labelledby="editorHeading" hidden></div>
<?php admin_page_end(['scripts' => ['/admin/js/products.js']]); ?>
