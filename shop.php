<?php
/**
 * shop.php — /shop, the single storefront. Everything the company sells:
 *
 *   priced products   fixed price + stock, basket → checkout → QPay
 *                     (each has its own page: /shop/<slug>, shop-product.php)
 *   equipment         no price, added to the basket as a quote request
 *                     (/shop/equipment/…, equipment.php / category.php / product.php)
 *
 * Both halves are rendered here on the server. The priced grid used to be
 * drawn by shop.js after the page loaded, which search engines could not see;
 * shop.js now only runs the basket, checkout and QPay.
 */

require __DIR__ . '/inc/shop-lib.php';
require __DIR__ . '/inc/catalog-lib.php';

// Order links used to be /shop?order=<token> (checkout lived in the basket
// panel); the checkout has its own page now.
if (isset($_GET['order']) && preg_match('/^[a-f0-9]{32}$/', (string)$_GET['order'])) {
  header('Location: /shop/checkout?order=' . $_GET['order'], true, 301);
  exit;
}

// Old product links were /shop?product=<slug>, drawn by JavaScript. Each
// product now has a real address.
if (isset($_GET['product']) && preg_match('/^[a-z0-9-]+$/', (string)$_GET['product'])) {
  header('Location: /shop/' . $_GET['product'], true, 301);
  exit;
}

$products = null; // null = could not load (shown as an error, not as "no products")
$cats = [];
try {
  $pdo = naf_db();
  $products = shop_products($pdo);
  foreach (catalog_categories($pdo) as $c) {
    $items = catalog_products_by_category($pdo, (int)$c['id']);
    if ($items) $cats[] = $c + ['items' => $items];
  }
} catch (\Throwable $e) {
  // An outage must not take the whole page down — the affected block shows a
  // short message instead.
}

$buyable = array_values(array_filter($products ?? [], function ($p) { return count($p['variants']) > 0; }));

page_start([
  'title' => 'Дэлгүүр — Нүүдэлчин Агро Ферм',
  'desc' => 'Сүүн бүтээгдэхүүн, фермийн тоног төхөөрөмж, тугал тэжээх хэрэгсэл, мал тэмдэглэгээ. Нүүдэлчин Агро Фермийн дэлгүүр.',
  'canonical' => '/shop',
  'active' => 'shop',
]);
?>

<!-- ============ PRICED PRODUCTS ============ -->
<section class="shop">
  <div class="shop__head">
    <p class="kicker reveal">Дэлгүүр</p>
    <h1 class="reveal">Бидний <em>нийлүүлдэг</em> бүхэн</h1>
  </div>
  <div id="shopRoot">
  <?php if ($products === null): ?>
    <p class="shop__empty" style="display:block">Бүтээгдэхүүн ачаалж чадсангүй — дараа дахин оролдоно уу.</p>
  <?php elseif (!$buyable): ?>
    <p class="shop__empty" style="display:block">Одоогоор бүтээгдэхүүн алга — удахгүй дахин орно уу.</p>
  <?php else: ?>
    <div class="products__grid shop-grid">
    <?php foreach ($buyable as $p):
      $inStock = false;
      $prices = [];
      foreach ($p['variants'] as $v) {
        if ($v['available'] > 0) $inStock = true;
        $prices[] = (int)$v['price'];
      }
      $img = $p['variants'][0]['image_path'];
      $media = $img
        ? sprintf("background-image:linear-gradient(rgba(44,44,44,.15), rgba(44,44,44,.3)), url('%s'); background-size:cover; background-position:center", ce(shop_img($img)))
        : 'background:linear-gradient(135deg, var(--green), var(--green-deep))';
    ?>
      <article class="prod reveal reveal--scale">
        <a href="/shop/<?= ce($p['slug']) ?>" class="shop-grid__card-link">
          <div class="prod__media" style="<?= $media ?>"></div>
          <div class="prod__body">
            <h3><?= ce($p['name_mn']) ?></h3>
            <?php if ($p['description_mn']): ?><p class="shop-grid__desc"><?= ce($p['description_mn']) ?></p><?php endif; ?>
            <p class="shop-grid__price"><?= $inStock
              ? '<span>Эхлэх үнэ</span> ' . shop_price(min($prices))
              : '<span>Дууссан</span>' ?></p>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>
</section>

<?php if ($cats): ?>
<!-- ============ EQUIPMENT ============ -->
<section class="eq-wrap eq-wrap--inshop" id="equipment">
  <div class="eq-shop-head">
    <p class="kicker">Тоног төхөөрөмж</p>
    <h2>Фермийн тоног төхөөрөмж, хэрэгсэл</h2>
    <p class="eq-shop-head__lead">Тоног төхөөрөмжийн үнийг сайтад харуулахгүй. Хэрэгтэйгээ сагсандаа нэмээд хүсэлт илгээхэд бид үнийн санал, нийлүүлэх хугацааг эргэн мэдэгдэнэ.</p>
  </div>

  <?php foreach ($cats as $c): ?>
  <div class="eq-section">
    <div class="eq-section__head">
      <h3><?= ce($c['name']) ?></h3>
      <a class="eq-section__more" href="/shop/equipment/<?= ce($c['slug']) ?>">
        Бүгдийг харах (<?= count($c['items']) ?>)
      </a>
    </div>
    <div class="eq-grid">
      <?php foreach (array_slice($c['items'], 0, 4) as $item) catalog_card($item, $c['slug']); ?>
    </div>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php page_end(['shop' => true]); ?>
