<?php
/**
 * equipment.php — catalogue landing page: every category, with its products
 * listed underneath. Reached as /shop/equipment via .htaccess.
 */

require __DIR__ . '/inc/catalog-lib.php';

try {
  $pdo = naf_db();
  $cats = catalog_categories($pdo);
} catch (\Throwable $e) {
  http_response_code(500);
  catalog_head('Алдаа гарлаа — Нүүдэлчин Агро Ферм', 'Түр хүлээгээд дахин оролдоно уу.', '/shop/equipment');
  echo '<section class="eq-wrap"><p class="shop__empty">Түр зуурын алдаа гарлаа. Дахин оролдоно уу.</p></section>';
  catalog_foot();
  exit;
}

catalog_head(
  'Фермийн тоног төхөөрөмж, хэрэгсэл — Нүүдэлчин Агро Ферм',
  'Саалтуурын эд анги, тугал тэжээх хэрэгсэл, мал тэмдэглэгээ, цэвэрлэгээний хэрэгсэл. Сонгосон чанартай бүтээгдэхүүн, шууд нийлүүлэлт.',
  '/shop/equipment'
);
?>
<section class="eq-hero">
  <h1>Фермд хэрэгтэй <em>бүхэн</em></h1>
  <p class="eq-hero__lead">
    Бид өөрсдийн таван ферм дээр ашигладаг тоног төхөөрөмж, хэрэгслээ
    Монголын малчид, фермерүүдэд нийлүүлж байна. Бүтээгдэхүүнээ сонгоод
    хүсэлт илгээхэд бид үнийн санал, нийлүүлэх хугацааг эргэн мэдэгдэнэ.
  </p>
</section>

<section class="eq-wrap eq-wrap--full">
<?php if (!$cats): ?>
  <p class="shop__empty">Бүтээгдэхүүн удахгүй нэмэгдэнэ.</p>
<?php else: foreach ($cats as $c):
        $items = catalog_products_by_category($pdo, (int)$c['id']);
        if (!$items) continue; ?>
  <div class="eq-section">
    <div class="eq-section__head">
      <h2><?= ce($c['name']) ?></h2>
      <a class="eq-section__more" href="/shop/equipment/<?= ce($c['slug']) ?>">
        Бүгдийг харах (<?= count($items) ?>)
      </a>
    </div>
    <div class="eq-grid">
      <?php foreach (array_slice($items, 0, 4) as $p) catalog_card($p, $c['slug']); ?>
    </div>
  </div>
<?php endforeach; endif; ?>
</section>
<?php catalog_foot(); ?>
