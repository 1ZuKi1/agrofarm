<?php
/**
 * product.php — one product page. Reached as /equipment/<cat>/<slug>.
 *
 * Layout follows the reference site's anatomy: gallery, intro, spec table,
 * features, inquiry call-to-action, related products. No price anywhere —
 * buyers ask, we quote.
 */

require __DIR__ . '/catalog-lib.php';

$catSlug  = (string)($_GET['cat'] ?? '');
$prodSlug = (string)($_GET['slug'] ?? '');

try {
  $pdo = naf_db();
  $st = $pdo->prepare(
    'SELECT p.*, c.slug AS cat_slug, c.name AS cat_name
     FROM equipment_products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.active = 1 AND c.active = 1'
  );
  $st->execute([$prodSlug]);
  $p = $st->fetch();
} catch (\Throwable $e) {
  $p = null;
}

if (!$p) {
  http_response_code(404);
  catalog_head('Бүтээгдэхүүн олдсонгүй — Нүүдэлчин Агро Ферм',
    'Хүссэн бүтээгдэхүүн олдсонгүй.', '/equipment');
  echo '<section class="eq-wrap"><h1 class="eq-404">Бүтээгдэхүүн олдсонгүй</h1>'
     . '<p><a class="btn" href="/equipment">Каталог руу буцах</a></p></section>';
  catalog_foot();
  exit;
}

// The category in the URL must match the product's real category, so the same
// page can't be reached at several addresses — one canonical URL per product.
if ($catSlug !== $p['cat_slug']) {
  header('Location: /equipment/' . rawurlencode($p['cat_slug']) . '/' . rawurlencode($p['slug']), true, 301);
  exit;
}

$imgSt = $pdo->prepare('SELECT path, alt FROM equipment_images WHERE product_id = ? ORDER BY sort_order, id');
$imgSt->execute([$p['id']]);
$images = $imgSt->fetchAll();

$features = cjson($p['features']);
$spec     = cjson($p['spec_table'], []);
$columns  = (isset($spec['columns']) && is_array($spec['columns'])) ? $spec['columns'] : [];
$rows     = (isset($spec['rows']) && is_array($spec['rows'])) ? $spec['rows'] : [];

$related = array_values(array_filter(
  catalog_products_by_category($pdo, (int)$p['category_id']),
  fn($r) => $r['slug'] !== $p['slug']
));
$related = array_slice($related, 0, 3);

$canonical = '/equipment/' . $p['cat_slug'] . '/' . $p['slug'];
catalog_head(
  ($p['meta_title'] ?: $p['name'] . ' — Нүүдэлчин Агро Ферм'),
  ($p['meta_description'] ?: mb_substr((string)$p['intro'], 0, 300, 'UTF-8')),
  $canonical
);
?>
<section class="eq-wrap">
  <?php catalog_crumbs([
    ['Нүүр', '/'],
    ['Тоног төхөөрөмж', '/equipment'],
    [$p['cat_name'], '/equipment/' . $p['cat_slug']],
    [$p['name'], null],
  ]); ?>

  <div class="eq-layout">
    <?php catalog_sidebar($pdo, $p['cat_slug'], $p['slug']); ?>

    <article class="eq-main eq-detail"
             data-sku="<?= ce($p['sku']) ?>"
             data-name="<?= ce($p['name']) ?>">

      <div class="eq-detail__top">
        <div class="eq-gallery">
          <?php if ($images): ?>
            <img class="eq-gallery__main" id="eqGalleryMain"
                 src="/<?= ce($images[0]['path']) ?>"
                 alt="<?= ce($images[0]['alt'] ?: $p['name']) ?>">
            <?php if (count($images) > 1): ?>
              <div class="eq-gallery__thumbs">
                <?php foreach ($images as $i => $im): ?>
                  <button type="button" class="eq-gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>"
                          data-src="/<?= ce($im['path']) ?>"
                          aria-label="Зураг <?= $i + 1 ?>">
                    <img src="/<?= ce($im['path']) ?>" alt="" loading="lazy" decoding="async">
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="eq-gallery__empty" aria-hidden="true"></div>
          <?php endif; ?>
        </div>

        <div class="eq-detail__intro">
          <h1><?= ce($p['name']) ?></h1>
          <p class="eq-detail__sku">Код: <?= ce($p['sku']) ?></p>
          <?php if ($p['intro']): ?>
            <p class="eq-detail__lead"><?= nl2br(ce($p['intro'])) ?></p>
          <?php endif; ?>

          <div class="eq-detail__actions">
            <button type="button" class="btn btn--gold" id="eqAddBtn">
              Хүсэлтийн жагсаалтад нэмэх
            </button>
            <a class="eq-detail__ask" href="#eqInquiry">Шууд үнийн санал авах</a>
          </div>
          <p class="eq-detail__note">
            Үнийг хүсэлтээр мэдэгдэнэ. Хүсэлт илгээснээс хойш ажлын 1–2 өдөрт
            бид үнийн санал, нийлүүлэх хугацааг эргэн мэдэгдэнэ.
          </p>
        </div>
      </div>

      <?php if ($columns && $rows): ?>
      <section class="eq-block">
        <h2>Загвар, үзүүлэлт</h2>
        <div class="eq-table-wrap">
          <table class="eq-table">
            <thead><tr>
              <?php foreach ($columns as $col): ?><th><?= ce((string)$col) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr><?php foreach ($columns as $i => $_): ?>
                  <td><?= ce((string)($row[$i] ?? '')) ?></td>
                <?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($features): ?>
      <section class="eq-block">
        <h2>Онцлог</h2>
        <ul class="eq-features">
          <?php foreach ($features as $f): ?><li><?= ce((string)$f) ?></li><?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <section class="eq-block eq-inquiry" id="eqInquiry">
        <h2>Үнийн санал авах</h2>
        <p class="eq-inquiry__lead">
          Утас, нэрээ үлдээгээрэй. Бид эргэн холбогдож үнийн санал илгээнэ.
        </p>
        <form class="eq-form" method="post" action="/inquiry-create.php">
          <input type="hidden" name="items"
                 value='<?= ce(json_encode([[
                   'sku' => $p['sku'], 'name' => $p['name'], 'quantity' => 1,
                 ]], JSON_UNESCAPED_UNICODE)) ?>'>
          <!-- Honeypot: a real person never fills a field they cannot see. -->
          <div class="eq-form__hp" aria-hidden="true">
            <label>Хаяг<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
          </div>
          <div class="eq-form__grid">
            <label>Нэр <span>*</span><input type="text" name="name" required maxlength="255"></label>
            <label>Утас <span>*</span><input type="tel" name="phone" required maxlength="50"></label>
            <label>И-мэйл<input type="email" name="email" maxlength="255"></label>
            <label>Байгууллага<input type="text" name="company" maxlength="255"></label>
          </div>
          <label class="eq-form__msg">Нэмэлт мэдээлэл
            <textarea name="message" rows="4" maxlength="2000"
              placeholder="Тоо ширхэг, загвар, хүргэх хаяг гэх мэт"></textarea></label>
          <button type="submit" class="btn btn--gold">Хүсэлт илгээх</button>
        </form>
      </section>

      <?php if ($related): ?>
      <section class="eq-block">
        <h2>Холбоотой бүтээгдэхүүн</h2>
        <div class="eq-grid eq-grid--3">
          <?php foreach ($related as $r) catalog_card($r, $p['cat_slug']); ?>
        </div>
      </section>
      <?php endif; ?>
    </article>
  </div>
</section>
<?php catalog_foot(); ?>
