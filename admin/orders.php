<?php
/**
 * orders.php — /admin/orders: the orders list.
 *   tabs    Хийх (paid, not delivered — default, by day, oldest first) ·
 *           Хүлээгдэж буй · Хүргэсэн · Цуцлагдсан (+ expired) · Бүгд
 *   filter  day (Өнөөдөр / Маргааш / ойрын 7 хоног) and a search box
 *           (name, phone or #000012)
 *   50 per page. Each row links to /admin/orders/<id>.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo);

orders_expire_stale($pdo); // never show an abandoned pending order as live
$tab = (string)($_GET['tab'] ?? 'todo');
$day = (string)($_GET['day'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$page = (int)($_GET['page'] ?? 1);
$list = admin_orders_list($pdo, $tab, $day, $q, $page);
$tab = $list['tab']; $day = $list['day']; $page = $list['page'];
$now = fulfillment_now();
$pages = max(1, (int)ceil($list['total'] / ADMIN_ORDERS_PER_PAGE));

/** This list's address with some parameters changed. */
function orders_url(array $change): string {
  global $tab, $day, $q;
  $p = array_merge(['tab' => $tab, 'day' => $day, 'q' => $q, 'page' => 1], $change);
  if ($p['tab'] === 'todo') unset($p['tab']);
  if ($p['day'] === '') unset($p['day']);
  if ($p['q'] === '') unset($p['q']);
  if ((int)$p['page'] <= 1) unset($p['page']);
  return '/admin/orders' . ($p ? '?' . http_build_query($p) : '');
}

admin_page_start($pdo, $me, [
  'title' => 'Захиалга',
  'active' => 'orders',
  'sub' => 'Дэлгүүрээс QPay-ээр төлсөн захиалгууд. "Хийх" — төлөгдсөн, хараахан хүргээгүй.',
]);
?>
<nav class="ad-tabs" aria-label="Захиалгын төлөв">
  <?php foreach (ADMIN_ORDER_TABS as $key => $t): ?>
  <a href="<?= ce(orders_url(['tab' => $key])) ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>><?= ce($t[0]) ?> <span><?= (int)$list['counts'][$key] ?></span></a>
  <?php endforeach; ?>
</nav>

<form class="ad-filters" method="get" action="/admin/orders" role="search">
  <?php if ($tab !== 'todo'): ?><input type="hidden" name="tab" value="<?= ce($tab) ?>"><?php endif; ?>
  <div class="ad-field">
    <label for="ordDay">Өдөр</label>
    <select id="ordDay" name="day">
      <?php foreach (ADMIN_ORDER_DAYS as $k => $label): ?>
      <option value="<?= ce($k) ?>"<?= $k === $day ? ' selected' : '' ?>><?= ce($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="ad-field" style="flex:1 1 220px">
    <label for="ordQ">Хайх</label>
    <input type="search" id="ordQ" name="q" value="<?= ce($q) ?>" placeholder="Нэр, утас эсвэл #000012">
  </div>
  <button type="submit" class="ad-btn">Шүүх</button>
  <?php if ($day !== '' || $q !== ''): ?><a class="ad-btn" href="<?= ce(orders_url(['day' => '', 'q' => ''])) ?>">Арилгах</a><?php endif; ?>
</form>

<?php if (!$list['rows']): ?>
  <p class="ad-empty"><?= $q !== '' || $day !== '' ? 'Шүүлтэд тохирох захиалга алга.' : 'Энэ хэсэгт захиалга алга.' ?></p>
<?php else: ?>
<div class="ad-table-wrap">
  <table class="ad-table ad-table--cards">
    <caption class="ad-sr"><?= ce(ADMIN_ORDER_TABS[$tab][0]) ?> — <?= (int)$list['total'] ?> захиалга</caption>
    <thead><tr>
      <th scope="col">Дугаар</th><th scope="col">Худалдан авагч</th><th scope="col">Хэлбэр, өдөр</th>
      <th scope="col" class="ad-r">Нийт</th><th scope="col">Төлөв</th>
    </tr></thead>
    <tbody>
      <?php foreach ($list['rows'] as $o):
        [$label, $tag] = admin_order_status($o['status'], $o['fulfillment']);
        $tel = preg_replace('/[^\d+]/', '', $o['buyer_phone']); ?>
      <tr data-order="<?= (int)$o['id'] ?>">
        <td><a class="ad-row-link ad-num" href="/admin/orders/<?= (int)$o['id'] ?>"><?= ce(order_number((int)$o['id'])) ?></a>
          <span class="ad-sub"><?= ce(admin_when($o['created_at'])) ?></span></td>
        <td><?= ce($o['buyer_name']) ?>
          <span class="ad-sub"><a class="ad-tel" href="tel:<?= ce($tel) ?>"><?= ce($o['buyer_phone']) ?></a></span></td>
        <td><?= ad_icon($o['fulfillment'] === 'pickup' ? 'store' : 'truck') ?> <?= ce(fulfillment_method_label($o['fulfillment'])) ?>
          <span class="ad-sub"><?= ce(admin_order_day($o, $now)) ?></span></td>
        <td class="ad-r ad-num"><?= ce(admin_money((int)$o['total'])) ?></td>
        <td><span class="ad-tag ad-tag--<?= $tag ?>"><?= ce($label) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($pages > 1): ?>
<nav class="ad-btn-row" style="margin-top:14px" aria-label="Хуудас">
  <?php if ($page > 1): ?><a class="ad-btn ad-btn--sm" href="<?= ce(orders_url(['page' => $page - 1])) ?>">← Өмнөх</a><?php endif; ?>
  <span class="ad-small ad-muted"><?= $page ?> / <?= $pages ?></span>
  <?php if ($page < $pages): ?><a class="ad-btn ad-btn--sm" href="<?= ce(orders_url(['page' => $page + 1])) ?>">Дараах →</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php admin_page_end(); ?>
