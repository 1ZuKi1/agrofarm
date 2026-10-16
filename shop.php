<?php
/**
 * shop.php — /shop, the single storefront. One section per category, every
 * item on the same card (shop_card(), inc/catalog-lib.php):
 *
 *   QPay items     fixed price, "Сагсанд нэмэх" (one option) or "Сонгох"
 *                  (several) → basket → /shop/checkout → QPay;
 *                  own page /shop/<slug> (shop-product.php)
 *   equipment      no price, "Үнийн санал авах" → added to the basket as a
 *                  quote request on its page /shop/equipment/<group>/<item>
 *
 * What goes in which section comes from shop_sections() (inc/shop-lib.php);
 * categories with nothing in them are not shown. A big category is one row
 * that scrolls sideways (arrows: js/shop.js); one with fewer items than fit in
 * a row takes only that many columns (data-n, css), so small categories share
 * a row. Rendered on the server so
 * search engines see every item.
 */

require __DIR__ . '/inc/shop-lib.php';
require __DIR__ . '/inc/catalog-lib.php';

// Old product links were /shop?product=<slug>, drawn by JavaScript. Each
// product now has a real address.
if (isset($_GET['product']) && preg_match('/^[a-z0-9-]+$/', (string)$_GET['product'])) {
  header('Location: /shop/' . $_GET['product'], true, 301);
  exit;
}
// Order links used to be /shop?order=<token> (checkout lived in the basket
// panel); the checkout has its own page now.
if (isset($_GET['order']) && preg_match('/^[a-f0-9]{32}$/', (string)$_GET['order'])) {
  header('Location: /shop/checkout?order=' . $_GET['order'], true, 301);
  exit;
}

$sections = null; // null = could not load (shown as an error, not as "nothing for sale")
try {
  $sections = shop_sections(naf_db());
} catch (\Throwable $e) {
  error_log('shop: ' . $e->getMessage());
}

page_start([
  'title' => 'Дэлгүүр — Нүүдэлчин Агро Ферм',
  'desc' => 'Малын тэжээл, эрдэс, бэлэг дурсгал, фермийн тоног төхөөрөмж, тугал тэжээх хэрэгсэл, мал тэмдэглэгээ. Нүүдэлчин Агро Фермийн дэлгүүр.',
  'canonical' => '/shop',
  'active' => 'shop',
]);
?>

<section class="shop shop-page">
  <div class="shop-head<?= $sections ? ' shop-head--rows' : '' ?>">
    <h1>Дэлгүүр</h1>
    <?php if ($sections): // arrows for the category row; shown only when it really scrolls (js/shop.js) ?>
    <div class="eq-section__tools">
      <button type="button" class="eq-row__btn" data-row-step="-1" aria-label="Өмнөх ангиллууд" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg></button>
      <button type="button" class="eq-row__btn" data-row-step="1" aria-label="Дараагийн ангиллууд" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
    </div>
    <?php endif; ?>
  </div>

<?php if ($sections === null): ?>
  <p class="shop__empty" style="display:block">Бүтээгдэхүүн ачаалж чадсангүй — дараа дахин оролдоно уу.</p>
<?php elseif (!$sections): ?>
  <p class="shop__empty" style="display:block">Одоогоор бүтээгдэхүүн алга — удахгүй дахин орно уу.</p>
<?php else: ?>
  <nav class="shop-cats" aria-label="Ангилал" style="--n:<?= count($sections) ?>">
    <?php foreach ($sections as $s): ?>
      <a href="#<?= ce($s['slug']) ?>"><strong><?= ce($s['name']) ?></strong><span><?= (int)$s['count'] ?> бараа</span></a>
    <?php endforeach; ?>
  </nav>

  <div class="shop-sections">
  <?php foreach ($sections as $s): $n = (int)$s['count']; ?>
  <section class="eq-section" id="<?= ce($s['slug']) ?>" data-n="<?= $n ?>" aria-labelledby="sec-<?= ce($s['slug']) ?>">
    <div class="eq-section__head">
      <div class="eq-section__title">
        <h2 id="sec-<?= ce($s['slug']) ?>"><?= ce($s['name']) ?></h2>
        <?php if ($n > 2): ?><span class="eq-section__count"><?= $n ?> бараа</span><?php endif; ?>
      </div>
      <?php if ($n > 2): // arrows appear only when the row really scrolls (js/shop.js) ?>
      <div class="eq-section__tools">
        <?php if ($s['more']): ?>
          <a class="eq-section__more" href="<?= ce($s['more']['url']) ?>">Бүгдийг харах</a>
        <?php endif; ?>
        <button type="button" class="eq-row__btn" data-row-step="-1" aria-label="<?= ce($s['name']) ?> — өмнөх бараанууд" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg></button>
        <button type="button" class="eq-row__btn" data-row-step="1" aria-label="<?= ce($s['name']) ?> — дараагийн бараанууд" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
      </div>
      <?php endif; ?>
    </div>
    <div class="eq-row" data-row tabindex="-1">
      <?php foreach ($s['items'] as $item) shop_card($item); ?>
    </div>
  </section>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</section>

<?php page_end(['shop' => true]); ?>
