<?php
/**
 * index.php — /admin/: Нүүр, "what to do now".
 *
 *   the strip     today's jobs · tomorrow's jobs · new quote requests · awaiting payment
 *   Хоцорсон      paid orders whose day has passed (only when there are any), first, in red
 *   Өнөөдөр, Маргааш   one row per paid order for that day, with "Хүргэсэн"
 *   side column   new quote requests, low stock, sales this week / month
 *
 * "Хүргэсэн" posts to the order page (admin/order.php) with back=/admin/, so
 * the logic and the messages live in one place.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo);

orders_expire_stale($pdo); // so "awaiting payment" never counts an abandoned order
$now = fulfillment_now();
$today = $now->format('Y-m-d');
$tomorrow = $now->modify('+1 day')->format('Y-m-d');
$nums = admin_home_numbers($pdo, $today, $tomorrow);
$late = admin_jobs_late($pdo, $today);
$jobsToday = admin_jobs($pdo, $today);
$jobsTomorrow = admin_jobs($pdo, $tomorrow);
$low = admin_low_stock($pdo);
$sales = admin_sales($pdo, $now);
$st = $pdo->query("SELECT i.id, i.buyer_name, i.buyer_company, i.created_at, (SELECT COUNT(*) FROM inquiry_items ii WHERE ii.inquiry_id = i.id) AS n
                   FROM inquiries i WHERE i.status = 'new' ORDER BY i.created_at DESC, i.id DESC LIMIT 5");
$quotes = $st->fetchAll();

/** One job row: method icon, name, phone, items, address (delivery only), Хүргэсэн. */
function home_job(array $o, DateTimeImmutable $now, bool $showDay): void {
  $pickup = $o['fulfillment'] === 'pickup';
  $tel = preg_replace('/[^\d+]/', '', $o['buyer_phone']);
  $no = order_number((int)$o['id']);
  ?>
  <li class="ad-job" data-order="<?= (int)$o['id'] ?>">
    <span class="ad-job__icon<?= $pickup ? ' ad-job__icon--pickup' : '' ?>" aria-hidden="true"><?= ad_icon($pickup ? 'store' : 'truck') ?></span>
    <div class="ad-job__main">
      <div class="ad-job__title"><a href="/admin/orders/<?= (int)$o['id'] ?>"><?= ce($o['buyer_name']) ?></a>
        <span class="ad-tag <?= $pickup ? 'ad-tag--warn' : 'ad-tag--info' ?>"><?= ce(fulfillment_method_label($o['fulfillment'])) ?></span>
        <span class="ad-small ad-muted ad-num"><?= ce($no) ?></span></div>
      <div class="ad-job__meta">
        <a class="ad-tel" href="tel:<?= ce($tel) ?>"><?= ad_icon('phone') ?><?= ce($o['buyer_phone']) ?></a>
        <?php if ($showDay || $o['delivery_slot']): ?> · <?= ce(admin_order_day($o, $now)) ?><?php endif; ?>
      </div>
    </div>
    <div class="ad-job__meta" style="grid-column:2">
      <?= ce(admin_items_short($o['items'])) ?> · <span class="ad-num"><?= ce(admin_money((int)$o['total'])) ?></span>
      <?php if (!$pickup && $o['buyer_address']): ?><br>Хаяг: <?= ce($o['buyer_address']) ?><?php endif; ?>
      <?php if ($o['buyer_note']): ?><br><em><?= ce($o['buyer_note']) ?></em><?php endif; ?>
    </div>
    <form class="ad-job__act" method="post" action="/admin/orders/<?= (int)$o['id'] ?>"
          data-confirm="<?= ce($no . ' — ' . $o['buyer_name'] . ': ' . ($pickup ? 'барааг өгсөн' : 'хүргэсэн') . ' гэж тэмдэглэх үү?') ?>">
      <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
      <input type="hidden" name="action" value="fulfill">
      <input type="hidden" name="back" value="/admin/">
      <button type="submit" class="ad-btn ad-btn--primary ad-btn--sm"><?= ad_icon('check') ?> Хүргэсэн</button>
    </form>
  </li>
  <?php
}

function home_jobs_panel(string $id, string $title, string $icon, array $jobs, string $empty, DateTimeImmutable $now, string $extraClass = '', bool $showDay = false): void {
  ?>
  <section class="ad-panel<?= $extraClass ?>" id="<?= ce($id) ?>" aria-labelledby="<?= ce($id) ?>-h">
    <div class="ad-panel__head">
      <h2 id="<?= ce($id) ?>-h"><?= ad_icon($icon) ?> <?= ce($title) ?> <span class="ad-tag ad-tag--mute"><?= count($jobs) ?></span></h2>
    </div>
    <?php if (!$jobs): ?>
      <p class="ad-empty"><?= ce($empty) ?></p>
    <?php else: ?>
      <ul class="ad-jobs"><?php foreach ($jobs as $o) home_job($o, $now, $showDay); ?></ul>
    <?php endif; ?>
  </section>
  <?php
}

$first = preg_split('/\s+/u', trim($me['name']))[0] ?? $me['name'];
admin_page_start($pdo, $me, [
  'title' => 'Сайн байна уу, ' . $first,
  'active' => 'home',
  'sub' => fulfillment_date_label($today) . ' · ' . admin_role_label($me['role']),
]);
?>
<nav class="ad-stats" aria-label="Өнөөдрийн тойм">
  <a class="ad-stat<?= $nums['today'] ? ' is-hot' : '' ?>" href="/admin/orders?day=today"><strong><?= $nums['today'] ?></strong><span>Өнөөдрийн ажил</span></a>
  <a class="ad-stat" href="/admin/orders?day=tomorrow"><strong><?= $nums['tomorrow'] ?></strong><span>Маргаашийн ажил</span></a>
  <a class="ad-stat<?= $nums['quotes'] ? ' is-hot' : '' ?>" href="/admin/quotes"><strong><?= $nums['quotes'] ?></strong><span>Шинэ үнийн санал</span></a>
  <a class="ad-stat" href="/admin/orders?tab=pending"><strong><?= $nums['pending'] ?></strong><span>Төлбөр хүлээгдэж буй</span></a>
</nav>

<div class="ad-grid ad-grid--side">
  <div class="ad-col">
    <?php if ($late) home_jobs_panel('late', 'Хоцорсон', 'alert', $late, '', $now, ' ad-panel--alert', true); ?>
    <?php home_jobs_panel('today', 'Өнөөдөр', 'calendar', $jobsToday, 'Өнөөдөр хүргэх, олгох захиалга алга.', $now, ' ad-panel--todo'); ?>
    <?php home_jobs_panel('tomorrow', 'Маргааш', 'clock', $jobsTomorrow, 'Маргааш хүргэх, олгох захиалга алга.', $now); ?>
  </div>

  <div class="ad-col">
    <section class="ad-panel" id="quotes" aria-labelledby="quotes-h">
      <div class="ad-panel__head"><h2 id="quotes-h"><?= ad_icon('inbox') ?> Шинэ үнийн санал</h2><a href="/admin/quotes">Бүгд →</a></div>
      <?php if (!$quotes): ?>
        <p class="ad-empty">Шинэ хүсэлт алга.</p>
      <?php else: ?>
        <ul class="ad-jobs">
          <?php foreach ($quotes as $q): ?>
          <li class="ad-job" style="grid-template-columns:minmax(0,1fr) auto">
            <div class="ad-job__main">
              <div class="ad-job__title"><a href="/admin/quotes#q<?= (int)$q['id'] ?>"><?= ce($q['buyer_name']) ?></a></div>
              <div class="ad-job__meta"><?= ce(trim(($q['buyer_company'] ? $q['buyer_company'] . ' · ' : '') . (int)$q['n'] . ' бараа')) ?></div>
            </div>
            <span class="ad-small ad-muted ad-num"><?= ce(admin_when($q['created_at'])) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="ad-panel" id="lowstock" aria-labelledby="low-h">
      <div class="ad-panel__head"><h2 id="low-h"><?= ad_icon('box') ?> Нөөц багассан</h2><a href="/admin/products">Бүтээгдэхүүн →</a></div>
      <?php if (!$low): ?>
        <p class="ad-empty">Бүх бараа хангалттай байна.</p>
      <?php else: ?>
        <ul class="ad-jobs">
          <?php foreach ($low as $v): $left = (int)$v['available']; ?>
          <li class="ad-job" style="grid-template-columns:minmax(0,1fr) auto">
            <div class="ad-job__main">
              <div class="ad-job__title"><a href="/admin/products"><?= ce($v['variant']) ?></a></div>
              <div class="ad-job__meta"><?= ce($v['product']) ?></div>
            </div>
            <?php if ($left <= 0): ?><span class="ad-tag ad-tag--bad">Дууссан</span>
            <?php else: ?><span class="ad-tag ad-tag--warn">үлдсэн <?= $left ?></span><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="ad-panel" id="sales" aria-labelledby="sales-h">
      <div class="ad-panel__head"><h2 id="sales-h"><?= ad_icon('chart') ?> Борлуулалт</h2></div>
      <dl class="ad-kv">
        <?php foreach (['week' => 'Энэ 7 хоног', 'month' => 'Энэ сар'] as $k => $label): $s = $sales[$k]; ?>
        <dt><?= ce($label) ?></dt>
        <dd><strong class="ad-num"><?= ce(admin_money($s['revenue'])) ?></strong>
          <span class="ad-sub ad-small ad-muted"><?= $s['orders'] ?> захиалга · хүргэлтийн төлбөр <?= ce(admin_money($s['fees'])) ?></span></dd>
        <?php endforeach; ?>
      </dl>
      <p class="ad-small ad-muted" style="margin:10px 0 0">Төлсөн болон хүргэсэн захиалга, төлсөн өдрөөр (Даваа–Ням).</p>
    </section>
  </div>
</div>
<?php admin_page_end(); ?>
