<?php
/**
 * sales.php — /admin/sales: Борлуулалт, what the shop has sold and earned.
 *
 *   period   7 хоног · 30 хоног (default) · 12 сар, all ending today
 *   numbers  money taken (delivery fees included), orders, items sold,
 *            average order; each next to the same period before it
 *   chart    money taken per day (per month for 12 сар). Hover or tab to a
 *            column for its numbers (admin/js/sales.js); the same numbers are
 *            in the table under it, so nothing depends on the hover
 *   top      the options that brought in the most money
 *
 * Counts paid and delivered orders by the day they were paid, UB time — the
 * same rule as the Борлуулалт box on Нүүр. Data: admin_sales_report().
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo);

$period = (string)($_GET['p'] ?? '30d');
if (!isset(ADMIN_SALES_PERIODS[$period])) $period = '30d';
[$periodLabel, $step] = ADMIN_SALES_PERIODS[$period];
$now = fulfillment_now();
$r = admin_sales_report($pdo, $period, $now);
$cur = $r['now'];
$prev = $r['before'];
$beforeLabel = ['7d' => 'Өмнөх 7 хоногт', '30d' => 'Өмнөх 30 хоногт', '12m' => 'Өмнөх 12 сард'][$period];

/** "Өмнөх 30 хоногт 9,450,000₮ (+18%)" — plain words, no coloured arrow.
 *  A jump of 3× or more reads "(14 дахин их)": "+1300%" says the same less clearly. */
function sales_before(string $label, int $now, int $before, callable $fmt): string {
  $s = $label . ' ' . $fmt($before);
  if ($before > 0 && $now >= 3 * $before) return $s . ' (' . (int)round($now / $before) . ' дахин их)';
  if ($before > 0) {
    $pct = (int)round(($now - $before) / $before * 100);
    $s .= ' (' . ($pct > 0 ? '+' : '') . $pct . '%)';
  }
  return $s;
}
$money = function (int $n): string { return admin_money($n); };
$plain = function (int $n): string { return number_format($n); };

// Chart scale: a round top value and four gridlines (0, ¼, ½, ¾, top).
$max = 0;
foreach ($r['series'] as $d) $max = max($max, $d['revenue']);
$stepVal = 1;
if ($max > 0) {
  $raw = $max / 4;
  $mag = 10 ** (int)floor(log10($raw));
  foreach ([1, 2, 2.5, 5, 10] as $m) { if ($m * $mag >= $raw) { $stepVal = (int)($m * $mag); break; } }
}
$top = max($stepVal * 4, 1);

/** Column label under the chart, and which ones phones hide. */
function sales_x(array $d, int $i, int $n, string $step): array {
  if ($step === 'month') return [$d['start']->format('n') . ' сар', ($n - 1 - $i) % 2 === 1];
  if ($n <= 7) return [$d['start']->format('m/d'), false];
  $fromEnd = $n - 1 - $i; // label today and every 5th day back from it
  if ($fromEnd % 5 !== 0) return ['', false];
  return [$d['start']->format('m/d'), $fromEnd % 10 !== 0];
}
/** "10-р сарын 16, Баасан" or "2026 оны 10-р сар". */
function sales_when(array $d, string $step): string {
  return $step === 'month' ? $d['start']->format('Y') . ' оны ' . $d['start']->format('n') . '-р сар'
                           : fulfillment_date_label($d['start']->format('Y-m-d'));
}

admin_page_start($pdo, $me, [
  'title' => 'Борлуулалт',
  'active' => 'sales',
  'sub' => 'Дэлгүүрээс төлсөн болон хүргэсэн захиалга, төлсөн өдрөөр. Хүргэлтийн төлбөр орлогод багтсан.',
]);
?>
<nav class="ad-tabs" aria-label="Хугацаа">
  <?php foreach (ADMIN_SALES_PERIODS as $key => [$label]): ?>
  <a href="/admin/sales<?= $key === '30d' ? '' : '?p=' . ce($key) ?>"<?= $key === $period ? ' aria-current="page"' : '' ?>>Сүүлийн <?= ce($label) ?></a>
  <?php endforeach; ?>
</nav>

<section class="ad-panel ad-sales-sum" aria-label="Сүүлийн <?= ce($periodLabel) ?>">
  <div class="ad-fig ad-fig--hero">
    <span class="ad-fig__label">Орлого</span>
    <strong class="ad-fig__value"><?= ce(admin_money($cur['revenue'])) ?></strong>
    <span class="ad-fig__before"><?= ce(sales_before($beforeLabel, $cur['revenue'], $prev['revenue'], $money)) ?></span>
  </div>
  <div class="ad-figs">
    <div class="ad-fig">
      <span class="ad-fig__label">Захиалга</span>
      <strong class="ad-fig__value"><?= number_format($cur['orders']) ?></strong>
      <span class="ad-fig__before"><?= ce(sales_before($beforeLabel, $cur['orders'], $prev['orders'], $plain)) ?></span>
    </div>
    <div class="ad-fig">
      <span class="ad-fig__label">Зарагдсан бараа</span>
      <strong class="ad-fig__value"><?= number_format($cur['items']) ?> ш</strong>
      <span class="ad-fig__before"><?= ce(sales_before($beforeLabel, $cur['items'], $prev['items'], $plain)) ?></span>
    </div>
    <div class="ad-fig">
      <span class="ad-fig__label">Дундаж захиалга</span>
      <strong class="ad-fig__value"><?= ce(admin_money($cur['orders'] ? (int)round($cur['revenue'] / $cur['orders']) : 0)) ?></strong>
      <span class="ad-fig__before">Хүргэлтийн төлбөр нийт <?= ce(admin_money($cur['fees'])) ?></span>
    </div>
  </div>
</section>

<section class="ad-panel" aria-labelledby="chart-h">
  <div class="ad-panel__head"><h2 id="chart-h"><?= ad_icon('chart') ?> Орлого, <?= $step === 'day' ? 'өдрөөр' : 'сараар' ?></h2></div>
  <?php if (!$cur['orders']): ?>
    <p class="ad-empty">Энэ хугацаанд төлсөн захиалга алга.</p>
  <?php else: $n = count($r['series']); ?>
  <div class="ad-chart" data-chart>
    <div class="ad-chart__plot">
      <?php for ($t = 0; $t <= 4; $t++): ?>
      <div class="ad-chart__tick" style="bottom:<?= $t * 25 ?>%" aria-hidden="true"><span><?= ce(admin_money_short($stepVal * $t)) ?></span></div>
      <?php endfor; ?>
      <ol class="ad-chart__cols" style="grid-template-columns:repeat(<?= $n ?>, minmax(0, 1fr))">
        <?php foreach ($r['series'] as $d):
          $when = sales_when($d, $step);
          $sub = $when . ' · ' . $d['orders'] . ' захиалга';
        ?>
        <li class="ad-chart__col" tabindex="0" aria-label="<?= ce($when . ': ' . admin_money($d['revenue']) . ', ' . $d['orders'] . ' захиалга') ?>"
            data-tip-value="<?= ce(admin_money($d['revenue'])) ?>" data-tip-label="<?= ce($sub) ?>">
          <span class="ad-chart__bar" style="height:<?= round($d['revenue'] / $top * 100, 2) ?>%"></span>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
    <ol class="ad-chart__x" style="grid-template-columns:repeat(<?= $n ?>, minmax(0, 1fr))" aria-hidden="true">
      <?php foreach ($r['series'] as $i => $d): [$x, $minor] = sales_x($d, $i, $n, $step); ?>
      <li<?= $minor ? ' class="is-minor"' : '' ?>><?= ce($x) ?></li>
      <?php endforeach; ?>
    </ol>
    <div class="ad-chart__tip" hidden><strong></strong><span></span></div>
  </div>

  <details class="ad-details">
    <summary>Хүснэгтээр харах</summary>
    <div class="ad-table-wrap">
      <table class="ad-table">
        <thead><tr><th><?= $step === 'day' ? 'Өдөр' : 'Сар' ?></th><th class="ad-r">Захиалга</th><th class="ad-r">Орлого</th></tr></thead>
        <tbody>
          <?php foreach (array_reverse($r['series']) as $d): ?>
          <tr><td><?= ce(sales_when($d, $step)) ?></td><td class="ad-r ad-num"><?= $d['orders'] ?></td><td class="ad-r ad-num"><?= ce(admin_money($d['revenue'])) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
  <?php endif; ?>
</section>

<section class="ad-panel" aria-labelledby="top-h">
  <div class="ad-panel__head"><h2 id="top-h"><?= ad_icon('box') ?> Хамгийн их зарагдсан</h2><a href="/admin/products">Бүтээгдэхүүн →</a></div>
  <?php if (!$r['top']): ?>
    <p class="ad-empty">Энэ хугацаанд зарагдсан бараа алга.</p>
  <?php else: $topMax = max(1, $r['top'][0]['revenue']); ?>
  <div class="ad-table-wrap">
    <table class="ad-table ad-table--cards ad-best">
      <thead><tr><th>Бараа</th><th class="ad-r">Тоо</th><th class="ad-r">Орлого</th><th><span class="ad-sr">Харьцуулалт</span></th></tr></thead>
      <tbody>
        <?php foreach ($r['top'] as $t): ?>
        <tr>
          <td><?= ce($t['name']) ?></td>
          <td class="ad-r ad-num"><span class="ad-cardlabel">Тоо: </span><?= number_format($t['qty']) ?> ш</td>
          <td class="ad-r ad-num"><span class="ad-cardlabel">Орлого: </span><?= ce(admin_money($t['revenue'])) ?></td>
          <td class="ad-best__bar" aria-hidden="true"><span class="ad-share"><span style="width:<?= round($t['revenue'] / $topMax * 100, 1) ?>%"></span></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php admin_page_end(['scripts' => ['/admin/js/sales.js']]); ?>
