<?php
/**
 * shop-product.php — one priced product, /shop/<slug> (via .htaccess).
 *
 * Photo on the left, everything to buy on the right: name, options, price
 * and "Сагсанд нэмэх" first, then the description and the details, so on a
 * phone the price and the button are on the first screen. Every variant is
 * rendered here; the variant buttons only switch which panels are visible
 * (its photo, its price box and its details, shop.js), so the whole product —
 * price, stock, usage, storage, ingredients — is in the HTML for search
 * engines and for anyone whose JavaScript fails. "Сагсанд нэмэх" adds to the
 * basket through shop.js.
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
    <div class="sp">
      <div class="sp__media">
        <?php foreach ($product['variants'] as $i => $v): ?>
        <div class="shop-variant-panel sp__photo<?= $v['image_path'] ? '' : ' sp__photo--empty' ?>" data-variant-id="<?= (int)$v['id'] ?>"<?= $i === 0 ? '' : ' hidden' ?>>
          <?php if ($v['image_path']): ?>
            <img src="<?= ce(shop_img($v['image_path'])) ?>" alt="<?= ce($product['name_mn'] . ' — ' . $v['name_mn']) ?>">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="sp__info">
        <h1 class="sp__title"><?= ce($product['name_mn']) ?></h1>

        <?php if (count($product['variants']) > 1): ?>
        <p class="sp__label" id="variantLabel">Төрөл</p>
        <div class="shop-product__switcher" role="group" aria-labelledby="variantLabel">
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
          <div class="sp__buy">
            <p class="sp__price"><?= shop_price((int)$v['price']) ?><?php if ($v['weight_label']): ?><small><?= ce($v['weight_label']) ?></small><?php endif; ?></p>
            <p class="sp__stock<?= $inStock ? '' : ' sp__stock--out' ?>"><?= $inStock ? 'Нөөцөд бий: ' . (int)$v['available'] . ' ш' : 'Дууссан' ?></p>
            <div class="sp__row">
              <div class="sp__qty">
                <button type="button" aria-label="Нэгээр хасах"<?= $inStock ? '' : ' disabled' ?>>−</button>
                <input type="number" id="shopQty-<?= $id ?>" min="1" max="<?= max(1, (int)$v['available']) ?>" value="1" aria-label="Тоо ширхэг"<?= $inStock ? '' : ' disabled' ?>>
                <button type="button" aria-label="Нэгээр нэмэх"<?= $inStock ? '' : ' disabled' ?>>+</button>
              </div>
              <button type="button" class="btn btn--gold shop-add" data-variant-id="<?= $id ?>"
                      data-max="<?= (int)$v['available'] ?>"<?= $inStock ? '' : ' disabled' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.6a2 2 0 0 0 2-1.5L20.5 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/></svg>
                <span>Сагсанд нэмэх</span></button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if ($product['description_mn']): ?>
          <p class="sp__desc"><?= ce($product['description_mn']) ?></p>
        <?php endif; ?>

        <?php foreach ($product['variants'] as $i => $v): ?>
        <div class="shop-variant-panel" data-variant-id="<?= (int)$v['id'] ?>"<?= $i === 0 ? '' : ' hidden' ?>>
          <?php
            $facts = [];
            if ($v['benefits_text_mn'] && $v['benefits_text_mn'] !== $product['description_mn']) $facts[] = ['Ашиг тус', $v['benefits_text_mn']];
            if ($v['usage_text_mn']) $facts[] = ['Хэрэглээ', $v['usage_text_mn']];
            if ($v['storage_text_mn']) $facts[] = ['Хадгалалт', $v['storage_text_mn']];
            if ($v['standard_code']) $facts[] = ['Стандарт', $v['standard_code']];
          ?>
          <?php if ($facts || $v['ingredients']): ?>
          <div class="sp__facts">
            <?php foreach ($facts as [$h, $t]): ?>
              <div class="sp__fact"><h3><?= ce($h) ?></h3><p><?= ce($t) ?></p></div>
            <?php endforeach; ?>
            <?php if ($v['ingredients']): ?>
              <div class="sp__fact">
                <h3>Найрлага</h3>
                <div class="ingredient-table">
                  <?php foreach ($v['ingredients'] as $ing): ?>
                    <div class="ingredient-table__row"><span><?= ce($ing['name']) ?></span><span><?= ce($ing['percentage']) ?></span></div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php page_end(['shop' => true]); ?>
