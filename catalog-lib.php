<?php
/**
 * catalog-lib.php — shared helpers and page chrome for the equipment catalogue.
 *
 * The catalogue is server-rendered, unlike shop.html which builds itself in
 * JavaScript from shop-data.php. That difference is deliberate: this section
 * exists to be found in search, so every product needs a real URL with real
 * markup and its own <title> and meta description in the HTML response.
 *
 * All product text is Mongolian. Only the surrounding chrome (nav, buttons)
 * carries data-mn/data-en, so the site-wide language toggle keeps working
 * without ever translating a product name.
 */

require_once __DIR__ . '/db.php';

/** Escape for HTML text and attribute contexts. */
function ce(?string $s): string {
  return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Decode a features/spec_table column. These are written as JSON into
 * LONGTEXT (see sql/equipment-schema.sql for why not a JSON column), so a
 * malformed or empty value must degrade to "no such block on the page"
 * rather than to a fatal error on a public page.
 */
function cjson(?string $raw, $fallback = []) {
  if ($raw === null || $raw === '') return $fallback;
  $v = json_decode($raw, true);
  return (json_last_error() === JSON_ERROR_NONE && is_array($v)) ? $v : $fallback;
}

function catalog_categories(PDO $pdo): array {
  return $pdo->query(
    'SELECT id, slug, name FROM categories WHERE active = 1 AND parent_id IS NULL
     ORDER BY sort_order, id'
  )->fetchAll();
}

function catalog_products_by_category(PDO $pdo, int $categoryId): array {
  $st = $pdo->prepare(
    'SELECT p.id, p.slug, p.sku, p.name, p.intro,
            (SELECT path FROM equipment_images i WHERE i.product_id = p.id
             ORDER BY sort_order, id LIMIT 1) AS thumb
     FROM equipment_products p
     WHERE p.category_id = ? AND p.active = 1
     ORDER BY p.sort_order, p.id'
  );
  $st->execute([$categoryId]);
  return $st->fetchAll();
}

/**
 * Opens the document and renders the header. $desc becomes the meta
 * description — the line Google shows under the result — so it is worth
 * filling per product rather than leaving to a default.
 */
function catalog_head(string $title, string $desc, string $canonicalPath): void {
  $t = ce($title);
  $d = ce($desc);
  $c = ce('https://agrofarm.mn' . $canonicalPath);
  echo <<<HTML
<!DOCTYPE html>
<html lang="mn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<title>$t</title>
<meta name="description" content="$d">
<link rel="canonical" href="$c">
<meta property="og:type" content="website">
<meta property="og:title" content="$t">
<meta property="og:description" content="$d">
<meta property="og:url" content="$c">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/style.css">
</head>
<body>

<div class="progress-bar" id="progressBar" aria-hidden="true"></div>
<div class="grain" aria-hidden="true"></div>

<header class="nav nav--solid" id="nav">
  <a href="/#hero" class="nav__brand" aria-label="Nuudelchin Agro Farm — Home">
    <span class="nav__mark">
      <img src="/img/naf_logo_full.svg" alt="" width="320" height="160">
    </span>
  </a>
  <nav class="nav__links" id="navLinks" aria-label="Main">
    <a href="/#about" data-en="About" data-mn="Бидний тухай">Бидний тухай</a>
    <a href="/#farms" data-en="Farms" data-mn="Фермүүд">Фермүүд</a>
    <a href="/equipment" class="is-current" data-en="Equipment" data-mn="Тоног төхөөрөмж">Тоног төхөөрөмж</a>
    <a href="/shop" data-en="Shop" data-mn="Дэлгүүр">Дэлгүүр</a>
    <a href="/news" data-en="News" data-mn="Мэдээ">Мэдээ</a>
    <a href="/#contact" data-en="Contact" data-mn="Холбоо барих">Холбоо барих</a>
  </nav>
  <a href="/#contact" class="btn nav__cta" data-en="Get in touch" data-mn="Холбогдох">Холбогдох</a>
  <button type="button" id="inquiryToggle" class="cart-toggle" aria-label="Хүсэлтийн жагсаалт">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>
    <span class="cart-toggle__badge" id="inquiryBadge" hidden>0</span>
  </button>
  <button class="lang-toggle" id="langToggle" aria-label="Switch language">EN</button>
  <button class="nav__burger" id="burger" aria-label="Open menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>
</header>

<main>
HTML;
}

function catalog_foot(): void {
  echo <<<HTML
</main>

<div class="cart-drawer" id="inquiryDrawer">
  <div class="cart-drawer__backdrop" id="inquiryBackdrop"></div>
  <div class="cart-drawer__panel">
    <div class="cart-drawer__head">
      <h2>Хүсэлтийн жагсаалт</h2>
      <button type="button" id="inquiryClose" aria-label="Хаах">✕</button>
    </div>
    <div class="cart-drawer__items" id="inquiryItems"></div>
    <div class="cart-drawer__footer" id="inquiryFooter"></div>
  </div>
</div>

<footer class="footer">
  <p>© <span id="year"></span> Nuudelchin Agro Farm LLC · Нүүдэлчин Агро Ферм ХХК · agrofarm.mn</p>
  <p data-en="Independent Mongolian dairy company — est. 2015." data-mn="Бие даасан Монгол сүүний компани — 2015 оноос.">Бие даасан Монгол сүүний компани — 2015 оноос.</p>
</footer>

<script src="/script.js"></script>
<script src="/catalog.js"></script>
</body>
</html>
HTML;
}

/** Left-hand category tree. The current category is expanded to its products. */
function catalog_sidebar(PDO $pdo, ?string $currentCat, ?string $currentProduct): void {
  $cats = catalog_categories($pdo);
  echo '<aside class="cat-side"><nav aria-label="Ангилал"><ul class="cat-side__list">';
  foreach ($cats as $c) {
    $isCurrent = ($c['slug'] === $currentCat);
    printf('<li class="cat-side__item%s"><a href="/equipment/%s">%s</a>',
      $isCurrent ? ' is-open' : '', ce($c['slug']), ce($c['name']));
    if ($isCurrent) {
      $kids = catalog_products_by_category($pdo, (int)$c['id']);
      if ($kids) {
        echo '<ul class="cat-side__sub">';
        foreach ($kids as $k) {
          printf('<li><a href="/equipment/%s/%s"%s>%s</a></li>',
            ce($c['slug']), ce($k['slug']),
            $k['slug'] === $currentProduct ? ' class="is-current"' : '',
            ce($k['name']));
        }
        echo '</ul>';
      }
    }
    echo '</li>';
  }
  echo '</ul></nav></aside>';
}

/** Breadcrumb trail. Also the structure Google uses for result breadcrumbs. */
function catalog_crumbs(array $trail): void {
  echo '<nav class="crumbs" aria-label="Замчлал">';
  $last = count($trail) - 1;
  foreach ($trail as $i => [$label, $href]) {
    if ($i === $last || $href === null) {
      printf('<span aria-current="page">%s</span>', ce($label));
    } else {
      printf('<a href="%s">%s</a><span class="crumbs__sep">/</span>', ce($href), ce($label));
    }
  }
  echo '</nav>';
}

/** Product card, shared by the category grid and the related-products strip. */
function catalog_card(array $p, string $catSlug): void {
  $img = $p['thumb']
    ? sprintf('<img src="/%s" alt="%s" loading="lazy" decoding="async">', ce($p['thumb']), ce($p['name']))
    : '<span class="eq-card__noimg" aria-hidden="true"></span>';
  printf(
    '<article class="eq-card reveal reveal--scale">
       <a href="/equipment/%s/%s">
         <div class="eq-card__media">%s</div>
         <div class="eq-card__body"><h3>%s</h3><p>%s</p></div>
       </a>
     </article>',
    ce($catSlug), ce($p['slug']), $img, ce($p['name']),
    ce(mb_strimwidth((string)$p['intro'], 0, 110, '…', 'UTF-8'))
  );
}
