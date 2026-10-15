<?php
/**
 * inc/catalog-lib.php — data helpers and page pieces for the equipment catalogue,
 * which lives under /shop/equipment/… (it used to be /equipment/…; .htaccess
 * redirects the old addresses).
 *
 * Server-rendered so every product has a real URL with real markup and its
 * own <title> and meta description in the HTML response. All text is
 * Mongolian; the page shell comes from inc/layout.php like every other page.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/layout.php';

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

/**
 * The categories that hold equipment — the equipment sidebar lists only these.
 * The shared category list also holds QPay categories (Бэлэг дурсгал…), which
 * live on /shop itself, not under /shop/equipment/.
 */
function catalog_equipment_categories(PDO $pdo): array {
  return $pdo->query(
    'SELECT c.id, c.slug, c.name FROM categories c
     WHERE c.active = 1 AND c.parent_id IS NULL
       AND EXISTS (SELECT 1 FROM equipment_products p WHERE p.category_id = c.id AND p.active = 1)
     ORDER BY c.sort_order, c.id'
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
 * Opens the page. $desc becomes the meta description — the line Google shows
 * under the result — so it is worth filling per product rather than leaving
 * to a default.
 */
function catalog_head(string $title, string $desc, string $canonicalPath): void {
  page_start([
    'title' => $title,
    'desc' => $desc,
    'canonical' => $canonicalPath,
    'active' => 'shop',
  ]);
}

/** Closes the page; catalogue pages are shop pages and share its basket. */
function catalog_foot(): void {
  page_end(['shop' => true]);
}

/** Left-hand category tree. The current category is expanded to its products. */
function catalog_sidebar(PDO $pdo, ?string $currentCat, ?string $currentProduct): void {
  $cats = catalog_equipment_categories($pdo);
  echo '<aside class="cat-side"><nav aria-label="Ангилал"><ul class="cat-side__list">';
  foreach ($cats as $c) {
    $isCurrent = ($c['slug'] === $currentCat);
    printf('<li class="cat-side__item%s"><a href="/shop/equipment/%s">%s</a>',
      $isCurrent ? ' is-open' : '', ce($c['slug']), ce($c['name']));
    if ($isCurrent) {
      $kids = catalog_products_by_category($pdo, (int)$c['id']);
      if ($kids) {
        echo '<ul class="cat-side__sub">';
        foreach ($kids as $k) {
          printf('<li><a href="/shop/equipment/%s/%s"%s>%s</a></li>',
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

/**
 * One card, for every item in the shop: QPay products and price-on-request
 * equipment look the same apart from the bottom line (shop_sections() in
 * inc/shop-lib.php builds the $item):
 *
 *   ['kind' => 'priced'|'quote', 'name', 'text', 'url', 'image' => ?string,
 *    'price' => shop_price_label()|null]
 *
 * The button sits outside the link, so the card never nests one interactive
 * element inside another. js/shop.js handles .eq-card__add.
 */
function shop_card(array $item): void {
  $img = $item['image']
    ? sprintf('<img src="%s" alt="" loading="lazy" decoding="async">', ce($item['image']))
    : '<span class="eq-card__noimg" aria-hidden="true"></span>';
  $foot = '';
  if ($item['kind'] === 'quote') {
    $foot = '<span class="eq-card__price eq-card__price--quote">Үнийн санал авах</span>';
  } else {
    $price = $item['price'];
    $foot = '<span class="eq-card__price' . ($price['sold_out'] ? ' eq-card__price--out' : '') . '">' . ce($price['label']) . '</span>';
    if ($price['add']) {
      // A round basket button; the tick replaces it for a moment after a tap (js/shop.js).
      $foot .= sprintf('<button type="button" class="eq-card__add" data-variant-id="%d" aria-label="%s — сагсанд нэмэх" title="Сагсанд нэмэх">'
        . '<svg class="eq-card__cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.5L20.5 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/><path d="M13 9.5v4M11 11.5h4"/></svg>'
        . '<svg class="eq-card__done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>'
        . '</button>',
        (int)$price['add'], ce($item['name']));
    } elseif ($price['choose']) {
      $foot .= sprintf('<a class="eq-card__choose" href="%s" aria-label="%s — сонгох">Сонгох</a>', ce($item['url']), ce($item['name']));
    }
  }
  printf(
    '<article class="eq-card eq-card--%s">
       <a class="eq-card__link" href="%s">
         <div class="eq-card__media">%s</div>
         <div class="eq-card__body"><h3>%s</h3><p>%s</p></div>
       </a>
       <div class="eq-card__foot">%s</div>
     </article>',
    $item['kind'] === 'quote' ? 'quote' : 'priced', ce($item['url']), $img, ce($item['name']),
    ce(mb_strimwidth((string)$item['text'], 0, 110, '…', 'UTF-8')), $foot
  );
}

/** An equipment card (group pages, product "similar items") — the same card as /shop. */
function catalog_card(array $p, string $catSlug): void {
  shop_card([
    'kind' => 'quote',
    'name' => $p['name'],
    'text' => (string)$p['intro'],
    'url' => '/shop/equipment/' . $catSlug . '/' . $p['slug'],
    'image' => $p['thumb'] ? '/' . $p['thumb'] : null,
    'price' => null,
  ]);
}
