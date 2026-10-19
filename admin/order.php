<?php
/**
 * order.php — /admin/orders/<id>: one order, everything about it, its history,
 * and the two buttons:
 *   Хүргэсэн  (paid)            → order_fulfill()
 *   Цуцлах    (pending or paid) → order_cancel(); the confirm warns that paid
 *                                 money is refunded by hand in the QPay portal
 *
 * Both post here (also from Нүүр, with back=/admin/), then redirect with a
 * message — so a refresh never repeats an action, and a second person pressing
 * the same button gets a calm "already done by …" instead of a double action.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo);
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  admin_check_csrf();
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'fulfill') $r = order_fulfill($pdo, $id, $me);
  elseif ($action === 'cancel') $r = order_cancel($pdo, $id, $me);
  else $r = ['ok' => false, 'message' => 'Тодорхойгүй үйлдэл'];
  admin_flash($r['ok'] ? 'ok' : 'err', $r['message']);
  $back = (string)($_POST['back'] ?? '');
  admin_redirect(preg_match('#^/admin/[A-Za-z0-9/?=&_-]*$#', $back) ? $back : '/admin/orders/' . $id);
}

$o = $id > 0 ? admin_order($pdo, $id) : null;
if (!$o) {
  http_response_code(404);
  admin_page_start($pdo, $me, ['title' => 'Захиалга олдсонгүй', 'active' => 'orders']);
  echo '<div class="ad-panel"><p>Ийм дугаартай захиалга алга. <a href="/admin/orders">Захиалгын жагсаалт руу буцах</a></p></div>';
  admin_page_end();
  exit;
}

$now = fulfillment_now();
$no = order_number((int)$o['id']);
[$statusLabel, $statusTag] = admin_order_status($o['status'], $o['fulfillment']);
$pickup = $o['fulfillment'] === 'pickup';
$tel = preg_replace('/[^\d+]/', '', $o['buyer_phone']);
$canFulfil = $o['status'] === 'paid';
$canCancel = in_array($o['status'], ['pending', 'paid'], true);
$cancelConfirm = $o['status'] === 'paid'
  ? "$no захиалгыг цуцлах уу?\n\nТөлбөр автоматаар буцаагдахгүй — QPay мерчант порталаас худалдан авагчид буцааж олгоно уу."
  : "$no захиалгыг цуцлах уу? Төлбөрийн QR код ажиллахаа болино.";

admin_page_start($pdo, $me, [
  'title' => 'Захиалга ' . $no,
  'active' => 'orders',
  'sub' => $o['buyer_name'] . ' · ' . admin_order_day($o, $now),
  'actions' => '<a class="ad-btn ad-btn--sm" href="/admin/orders">← Жагсаалт</a>',
]);
?>
<div class="ad-grid ad-grid--side">
  <div class="ad-col">
    <section class="ad-panel" aria-labelledby="who-h">
      <div class="ad-panel__head">
        <h2 id="who-h"><?= ad_icon($pickup ? 'store' : 'truck') ?> <?= ce(fulfillment_method_label($o['fulfillment'])) ?></h2>
        <span class="ad-tag ad-tag--<?= $statusTag ?>"><?= ce($statusLabel) ?></span>
      </div>
      <dl class="ad-kv">
        <dt>Нэр</dt><dd><?= ce($o['buyer_name']) ?></dd>
        <dt>Утас</dt><dd><a class="ad-tel" href="tel:<?= ce($tel) ?>"><?= ad_icon('phone') ?><?= ce($o['buyer_phone']) ?></a></dd>
        <dt>Өдөр</dt><dd><?= ce(admin_order_day($o, $now)) ?></dd>
        <?php if ($pickup): ?>
          <dt>Хаана</dt><dd><?= ce(NAF_PICKUP_ADDRESS) ?></dd>
        <?php else: ?>
          <dt>Хаяг</dt><dd><?= ce($o['buyer_address'] ?: '—') ?></dd>
        <?php endif; ?>
        <?php if ($o['buyer_note']): ?><dt>Тэмдэглэл</dt><dd><?= ce($o['buyer_note']) ?></dd><?php endif; ?>
      </dl>
      <?php if ($canFulfil || $canCancel): ?>
      <div class="ad-btn-row" style="margin-top:16px">
        <?php if ($canFulfil): ?>
        <form class="ad-inline" method="post" action="/admin/orders/<?= (int)$o['id'] ?>"
              data-confirm="<?= ce($no . ': ' . ($pickup ? 'ирж авсан' : 'хүргэсэн') . ' гэж тэмдэглэх үү?') ?>">
          <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
          <input type="hidden" name="action" value="fulfill">
          <button type="submit" class="ad-btn ad-btn--primary"><?= ad_icon('check') ?> <?= $pickup ? 'Ирж авсан' : 'Хүргэсэн' ?></button>
        </form>
        <?php endif; ?>
        <?php if ($canCancel): ?>
        <form class="ad-inline" method="post" action="/admin/orders/<?= (int)$o['id'] ?>" data-confirm="<?= ce($cancelConfirm) ?>">
          <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
          <input type="hidden" name="action" value="cancel">
          <button type="submit" class="ad-btn ad-btn--danger">Цуцлах</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </section>

    <section class="ad-panel" aria-labelledby="items-h">
      <div class="ad-panel__head"><h2 id="items-h"><?= ad_icon('box') ?> Бараа</h2></div>
      <div class="ad-table-wrap">
        <table class="ad-table ad-table--cards">
          <thead><tr><th scope="col">Бараа</th><th scope="col" class="ad-r">Үнэ</th><th scope="col" class="ad-r">Тоо</th><th scope="col" class="ad-r">Дүн</th></tr></thead>
          <tbody>
            <?php foreach ($o['items'] as $it): ?>
            <tr>
              <td><?= ce($it['variant_name_snapshot']) ?></td>
              <td class="ad-r ad-num"><?= ce(admin_money((int)$it['unit_price_snapshot'])) ?></td>
              <td class="ad-r ad-num">× <?= (int)$it['quantity'] ?></td>
              <td class="ad-r ad-num"><?= ce(admin_money((int)$it['line_total'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <dl class="ad-kv" style="margin-top:14px">
        <dt>Барааны дүн</dt><dd class="ad-num"><?= ce(admin_money((int)$o['subtotal'])) ?></dd>
        <dt>Хүргэлт</dt><dd class="ad-num"><?= (int)$o['delivery_fee'] > 0 ? ce(admin_money((int)$o['delivery_fee'])) : '—' ?></dd>
        <dt>Нийт</dt><dd class="ad-num"><strong><?= ce(admin_money((int)$o['total'])) ?></strong></dd>
      </dl>
    </section>
  </div>

  <div class="ad-col">
    <section class="ad-panel" aria-labelledby="hist-h">
      <div class="ad-panel__head"><h2 id="hist-h"><?= ad_icon('clock') ?> Түүх</h2></div>
      <ol class="ad-timeline">
        <?php foreach ($o['history'] as $h): ?>
        <li><?= ce($h['text']) ?> <span class="ad-when ad-num"><?= ce(admin_when($h['at'])) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>
</div>
<?php admin_page_end(); ?>
