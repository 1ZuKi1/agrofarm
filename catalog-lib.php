<?php
/**
 * catalog-lib.php — data helpers and page pieces for the equipment catalogue,
 * which lives under /shop/equipment/… (it used to be /equipment/…; .htaccess
 * redirects the old addresses).
 *
 * Server-rendered so every product has a real URL with real markup and its
 * own <title> and meta description in the HTML response. All text is
 * Mongolian; the page shell comes from inc/layout.php like every other page.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/layout.php';

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
  $cats = catalog_categories($pdo);
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

/** Product card, shared by the category grid and the related-products strip. */
function catalog_card(array $p, string $catSlug): void {
  $img = $p['thumb']
    ? sprintf('<img src="/%s" alt="%s" loading="lazy" decoding="async">', ce($p['thumb']), ce($p['name']))
    : '<span class="eq-card__noimg" aria-hidden="true"></span>';
  printf(
    '<article class="eq-card reveal reveal--scale">
       <a href="/shop/equipment/%s/%s">
         <div class="eq-card__media">%s</div>
         <div class="eq-card__body"><h3>%s</h3><p>%s</p></div>
       </a>
     </article>',
    ce($catSlug), ce($p['slug']), $img, ce($p['name']),
    ce(mb_strimwidth((string)$p['intro'], 0, 110, '…', 'UTF-8'))
  );
}
