<?php
/**
 * category.php — one category: the drop-down tree on the left, the products
 * on the right. Reached as /equipment/<cat-slug> via .htaccess.
 */

require __DIR__ . '/catalog-lib.php';

$catSlug = (string)($_GET['cat'] ?? '');

try {
  $pdo = naf_db();
  $st = $pdo->prepare('SELECT id, slug, name FROM categories WHERE slug = ? AND active = 1');
  $st->execute([$catSlug]);
  $cat = $st->fetch();
} catch (\Throwable $e) {
  $cat = null;
}

if (!$cat) {
  http_response_code(404);
  catalog_head('Ангилал олдсонгүй — Нүүдэлчин Агро Ферм',
    'Хүссэн ангилал олдсонгүй.', '/equipment');
  echo '<section class="eq-wrap"><h1 class="eq-404">Ангилал олдсонгүй</h1>'
     . '<p><a class="btn" href="/equipment">Каталог руу буцах</a></p></section>';
  catalog_foot();
  exit;
}

$items = catalog_products_by_category($pdo, (int)$cat['id']);

catalog_head(
  ce($cat['name']) . ' — Нүүдэлчин Агро Ферм',
  mb_substr($cat['name'] . '. Фермийн тоног төхөөрөмж, сэлбэг хэрэгсэл. '
    . 'Үнийн санал авахыг хүсвэл хүсэлтээ илгээнэ үү.', 0, 300, 'UTF-8'),
  '/equipment/' . $cat['slug']
);
?>
<section class="eq-wrap">
  <?php catalog_crumbs([
    ['Нүүр', '/'],
    ['Тоног төхөөрөмж', '/equipment'],
    [$cat['name'], null],
  ]); ?>

  <div class="eq-layout">
    <?php catalog_sidebar($pdo, $cat['slug'], null); ?>

    <div class="eq-main">
      <h1 class="eq-main__title"><?= ce($cat['name']) ?></h1>
      <p class="eq-main__count"><?= count($items) ?> бүтээгдэхүүн</p>

      <?php if (!$items): ?>
        <p class="shop__empty">Энэ ангилалд бүтээгдэхүүн удахгүй нэмэгдэнэ.</p>
      <?php else: ?>
        <div class="eq-grid">
          <?php foreach ($items as $p) catalog_card($p, $cat['slug']); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php catalog_foot(); ?>
