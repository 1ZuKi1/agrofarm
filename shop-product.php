<?php
/**
 * shop-product.php — one priced product, /shop/<slug> (via .htaccess).
 *
 * Every variant is rendered here; the variant buttons only switch which
 * panel is visible (shop.js), so the whole product — price, stock, usage,
 * storage, ingredients — is in the HTML for search engines and for anyone
 * whose JavaScript fails. "Сагслах" adds to the basket through shop.js.
 */

require __DIR__ . '/inc/shop-lib.php';
require __DIR__ . '/inc/catalog-lib.php'; // catalog_crumbs()

$slug = (string)($_GET['slug'] ?? '');
$product = null;
try {
  if (preg_match('/^[a-z0-9-]+$/', $slug)) {
    $found = shop_products(naf_db(), $slug);
    if ($found && $found[0]['variants']) $product = $found[0];
  }
} catch (\Throwable $e) {
  $product = null;
}

if (!$product) {
  http_response_code(404);
  page_start(['title' => 'Бүтээгдэхүүн олдсонгүй — Нүүдэлчин Агро Ферм', 'active' => 'shop']);
  echo '<section class="shop"><div class="shop-product">'
     . '<h1 class="shop-product__title">Бүтээгдэхүүн олдсонгүй</h1>'
     . '<p><a class="btn" href="/shop">Дэлгүүр рүү буцах</a></p></div></section>';
  page_end(['shop' => true]);
  exit;
}

$first = $product['variants'][0];
$desc = $product['description_mn'] ?: ($first['benefits_text_mn'] ?: $product['name_mn']);

page_start([
  'title' => $product['name_mn'] . ' — Нүүдэлчин Агро Ферм',
  'desc' => mb_substr($desc, 0, 300, 'UTF-8'),
  'canonical' => '/shop/' . $product['slug'],
  'active' => 'shop',
]);
?>
<section class="shop">
  <div class="shop-product">
    <?php catalog_crumbs([
      ['Нүүр', '/'],
      ['Дэлгүүр', '/shop'],
      [$product['name_mn'], null],
    ]); ?>
    <h1 class="shop-product__title"><?= ce($product['name_mn']) ?></h1>

    <?php if (count($product['variants']) > 1): ?>
    <p class="shop-product__switcher-label">Төрлөө сонгоно уу</p>
    <div class="shop-product__switcher">
      <?php foreach ($product['variants'] as $i => $v): ?>
        <button type="button" class="variant-pill<?= $i === 0 ? ' is-active' : '' ?>"
                data-variant-id="<?= (int)$v['id'] ?>"
                aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><?= ce($v['name_mn']) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($product['variants'] as $i => $v):
      $id = (int)$v['id'];
      $inStock = $v['available'] > 0;
    ?>
    <div class="shop-variant-panel" data-variant-id="<?= $id ?>"<?= $i === 0 ? '' : ' hidden' ?>>
      <div class="shop-variant<?= $v['image_path'] ? ' shop-variant--with-img' : '' ?>">
        <?php if ($v['image_path']): ?>
          <img class="shop-variant__img" src="<?= ce(shop_img($v['image_path'])) ?>" alt="<?= ce($v['name_mn']) ?>">
        <?php endif; ?>
        <div class="shop-variant__body">
          <h2 class="shop-variant__name"><?= ce($v['name_mn']) ?></h2>
          <p class="shop-variant__price"><?= shop_price((int)$v['price']) ?> <span class="shop-variant__weight"><?= ce($v['weight_label']) ?></span></p>
          <p class="shop-variant__stock"><?= $inStock ? 'Нөөцөд бий: ' . (int)$v['available'] : 'Дууссан' ?></p>
          <?php if ($v['benefits_text_mn']): ?>
            <p class="shop-variant__benefits"><?= ce($v['benefits_text_mn']) ?></p>
          <?php endif; ?>
          <div class="shop-variant__qty">
            <label for="shopQty-<?= $id ?>">Тоо ширхэг</label>
            <input type="number" id="shopQty-<?= $id ?>" min="1" max="<?= (int)$v['available'] ?>" value="1"<?= $inStock ? '' : ' disabled' ?>>
            <button type="button" class="btn btn--gold shop-add" data-variant-id="<?= $id ?>"
                    data-max="<?= (int)$v['available'] ?>"<?= $inStock ? '' : ' disabled' ?>>Сагслах</button>
          </div>
          <?php if ($v['usage_text_mn']): ?>
            <div class="shop-variant__section"><h3>Хэрэглээ</h3><p><?= ce($v['usage_text_mn']) ?></p></div>
          <?php endif; ?>
          <?php if ($v['storage_text_mn']): ?>
            <div class="shop-variant__section"><h3>Хадгалалт</h3><p><?= ce($v['storage_text_mn']) ?></p></div>
          <?php endif; ?>
          <?php if ($v['ingredients']): ?>
            <div class="shop-variant__section">
              <h3>Найрлага</h3>
              <div class="ingredient-table">
                <?php foreach ($v['ingredients'] as $ing): ?>
                  <div class="ingredient-table__row"><span><?= ce($ing['name']) ?></span><span><?= ce($ing['percentage']) ?></span></div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php page_end(['shop' => true]); ?>
