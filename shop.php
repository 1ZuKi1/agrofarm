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
 * categories with nothing in them are not shown. Rendered on the server so
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
  <div class="shop-head">
    <h1>Дэлгүүр</h1>
    <p class="shop-head__lead">Фермийн бүтээгдэхүүн, бэлэг дурсгал, тоног төхөөрөмж — QPay-ээр төлөх эсвэл үнийн санал авах.</p>
  </div>

<?php if ($sections === null): ?>
  <p class="shop__empty" style="display:block">Бүтээгдэхүүн ачаалж чадсангүй — дараа дахин оролдоно уу.</p>
<?php elseif (!$sections): ?>
  <p class="shop__empty" style="display:block">Одоогоор бүтээгдэхүүн алга — удахгүй дахин орно уу.</p>
<?php else: ?>
  <nav class="shop-cats" aria-label="Ангилал">
    <?php foreach ($sections as $s): ?>
      <a href="#<?= ce($s['slug']) ?>"><?= ce($s['name']) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php
  $noteShown = false;
  foreach ($sections as $s):
    $hasQuote = in_array('quote', array_column($s['items'], 'kind'), true);
    if ($hasQuote && !$noteShown):
      $noteShown = true; ?>
  <p class="shop-note">Тоног төхөөрөмжийн үнийг сайтад харуулахгүй. Хэрэгтэйгээ сагсандаа нэмээд хүсэлт илгээхэд бид үнийн санал, нийлүүлэх хугацааг эргэн мэдэгдэнэ.</p>
  <?php endif; ?>
  <section class="eq-section" id="<?= ce($s['slug']) ?>" aria-labelledby="sec-<?= ce($s['slug']) ?>">
    <div class="eq-section__head">
      <h2 id="sec-<?= ce($s['slug']) ?>"><?= ce($s['name']) ?></h2>
      <?php if ($s['more']): ?>
        <a class="eq-section__more" href="<?= ce($s['more']['url']) ?>">Бүгдийг харах (<?= (int)$s['more']['total'] ?>)</a>
      <?php endif; ?>
    </div>
    <div class="eq-grid">
      <?php foreach ($s['items'] as $item) shop_card($item); ?>
    </div>
  </section>
  <?php endforeach; ?>
<?php endif; ?>
</section>

<?php page_end(['shop' => true]); ?>
