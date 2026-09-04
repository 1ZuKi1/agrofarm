<?php
/**
 * shop.php — the single storefront. Everything the company sells lives here:
 * the dairy products (priced, cart, QPay) and the equipment catalogue
 * (no price, inquiry only).
 *
 * Replaces shop.html as the target of /shop. The dairy half is unchanged —
 * #shopRoot is still filled by shop.js from shop-data.php, so the cart,
 * checkout, delivery slots and QPay flow are untouched. The equipment half is
 * server-rendered here, and links out to the existing /equipment/... pages,
 * which stay where they are so their URLs keep working.
 */

require __DIR__ . '/catalog-lib.php';

$cats = [];
try {
  $pdo = naf_db();
  foreach (catalog_categories($pdo) as $c) {
    $items = catalog_products_by_category($pdo, (int)$c['id']);
    if ($items) $cats[] = $c + ['items' => $items];
  }
} catch (\Throwable $e) {
  // A catalogue outage must not take the dairy shop down with it — the
  // equipment block simply doesn't render.
  $cats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title>Дэлгүүр — Нүүдэлчин Агро Ферм</title>
<meta name="description" content="Сүүн бүтээгдэхүүн, фермийн тоног төхөөрөмж, тугал тэжээх хэрэгсэл, мал тэмдэглэгээ. Нүүдэлчин Агро Фермийн дэлгүүр.">
<link rel="canonical" href="https://agrofarm.mn/shop">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/style.css">
</head>
<body>

<!-- Scroll progress bar -->
<div class="progress-bar" id="progressBar" aria-hidden="true"></div>

<!-- Grain overlay -->
<div class="grain" aria-hidden="true"></div>

<!-- ============ NAVIGATION ============ -->
<header class="nav nav--solid" id="nav">
  <a href="/#hero" class="nav__brand" aria-label="Nuudelchin Agro Farm — Home">
    <span class="nav__mark">
      <img src="/img/naf_logo_full.svg" alt="" width="320" height="160">
    </span>
  </a>
  <nav class="nav__links" id="navLinks" aria-label="Main">
    <a href="/#about" data-en="About" data-mn="Бидний тухай">About</a>
    <a href="/#journey" data-en="Journey" data-mn="Замнал">Journey</a>
    <a href="/#farms" data-en="Farms" data-mn="Фермүүд">Farms</a>
    <a href="/#services" data-en="Services" data-mn="Үйлчилгээ">Services</a>
    <a href="/#products" data-en="Products" data-mn="Бүтээгдэхүүн">Products</a>
    <a href="/shop" data-en="Shop" data-mn="Дэлгүүр">Shop</a>
    <a href="/news" data-en="News" data-mn="Мэдээ">News</a>
    <a href="/#contact" data-en="Contact" data-mn="Холбоо барих">Contact</a>
  </nav>
  <a href="/#contact" class="btn nav__cta" data-en="Get in touch" data-mn="Холбогдох">Get in touch</a>
  <a href="/shop#cart" id="cartToggle" class="cart-toggle" aria-label="Cart">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    <span class="cart-toggle__badge" id="cartBadge" hidden>0</span>
  </a>
  <button class="lang-toggle" id="langToggle" aria-label="Switch language">MN</button>
  <button class="nav__burger" id="burger" aria-label="Open menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</header>

<main>

<div class="cart-drawer" id="cartDrawer">
  <div class="cart-drawer__backdrop" id="cartDrawerBackdrop"></div>
  <div class="cart-drawer__panel">
    <div class="cart-drawer__head">
      <h2 data-en="Cart" data-mn="Сагс">Cart</h2>
      <button type="button" id="cartDrawerClose" aria-label="Close cart">✕</button>
    </div>
    <div class="cart-drawer__items" id="cartDrawerItems"></div>
    <div class="cart-drawer__footer">
      <p class="cart-drawer__total" id="cartDrawerTotal"></p>
      <button type="button" class="btn btn--gold" id="cartCheckoutBtn" disabled title="Coming soon" data-en="Proceed to checkout" data-mn="Захиалга үргэлжлүүлэх">Proceed to checkout</button>
    </div>
  </div>
</div>

<!-- ============ SHOP ============ -->
<section class="shop">
  <div class="shop__head">
    <p class="kicker reveal" data-en="Shop" data-mn="Дэлгүүр">Shop</p>
    <h1 class="reveal" data-en="Everything we <em>sell</em>" data-mn="Бидний <em>нийлүүлдэг</em> бүхэн">Everything we <em>sell</em></h1>
  </div>
  <div id="shopRoot">
    <p class="shop__empty" id="shopLoading" data-en="Loading…" data-mn="Ачааллаж байна…">Loading…</p>
  </div>
</section>

<?php if ($cats): ?>
<!-- ============ EQUIPMENT ============ -->
<section class="eq-wrap eq-wrap--inshop" id="equipment">
  <div class="eq-shop-head">
    <p class="kicker" data-en="Equipment" data-mn="Тоног төхөөрөмж">Equipment</p>
    <h2 data-en="Farm equipment and tools" data-mn="Фермийн тоног төхөөрөмж, хэрэгсэл">Фермийн тоног төхөөрөмж, хэрэгсэл</h2>
    <p class="eq-shop-head__lead" data-en="No prices online — add what you need to your basket and we'll send you a quote and a delivery time." data-mn="Тоног төхөөрөмжийн үнийг сайтад харуулахгүй. Хэрэгтэйгээ сагсандаа нэмээд хүсэлт илгээхэд бид үнийн санал, нийлүүлэх хугацааг эргэн мэдэгдэнэ.">Тоног төхөөрөмжийн үнийг сайтад харуулахгүй. Хэрэгтэйгээ сагсандаа нэмээд хүсэлт илгээхэд бид үнийн санал, нийлүүлэх хугацааг эргэн мэдэгдэнэ.</p>
  </div>

  <?php foreach ($cats as $c): ?>
  <div class="eq-section">
    <div class="eq-section__head">
      <h3><?= ce($c['name']) ?></h3>
      <a class="eq-section__more" href="/equipment/<?= ce($c['slug']) ?>">
        Бүгдийг харах (<?= count($c['items']) ?>)
      </a>
    </div>
    <div class="eq-grid">
      <?php foreach (array_slice($c['items'], 0, 4) as $p) catalog_card($p, $c['slug']); ?>
    </div>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

</main>

<!-- ============ FOOTER ============ -->
<footer class="footer">
  <p>© <span id="year"></span> Nuudelchin Agro Farm LLC · Нүүдэлчин Агро Ферм ХХК · agrofarm.mn</p>
  <p data-en="Independent Mongolian dairy company — est. 2015." data-mn="Бие даасан Монгол сүүний компани — 2015 оноос.">Independent Mongolian dairy company — est. 2015.</p>
</footer>

<script src="/script.js"></script>
<script src="/shop.js"></script>
<script src="/catalog.js"></script>
</body>
</html>
