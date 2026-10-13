<?php
/**
 * quotes.php — /admin/quotes: Үнийн санал, the quote requests sent from the
 * equipment pages (api/inquiry-create.php).
 *   tabs     Шинэ (default) · Хариулсан · Хаагдсан · Бүгд
 *   a panel per request: who (tel:, mailto:), company, date, message, items,
 *   the staff note, the buttons, and its history (who did what, when)
 *   buttons  Хариулсан (new) · Хаах (new, replied) · Буцааж нээх (replied, closed)
 *
 * Every button posts here, then redirects back to the same tab and request.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  admin_check_csrf();
  $id = (int)($_POST['id'] ?? 0);
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'note') {
    admin_quote_note($pdo, $id, (string)($_POST['note'] ?? ''), $me);
    admin_flash('ok', 'Тэмдэглэл хадгалагдлаа');
  } else {
    $r = admin_quote_set_status($pdo, $id, (string)($_POST['status'] ?? ''), $me);
    admin_flash($r['ok'] ? 'ok' : 'err', $r['message']);
  }
  $tab = (string)($_POST['tab'] ?? 'new');
  if (!isset(ADMIN_QUOTE_TABS[$tab])) $tab = 'new';
  admin_redirect('/admin/quotes' . ($tab === 'new' ? '' : '?tab=' . $tab) . '#q' . $id);
}

$list = admin_quotes_list($pdo, (string)($_GET['tab'] ?? 'new'));
$tab = $list['tab'];

/** One status button as its own little form. */
function quote_button(int $id, string $tab, string $status, string $label, string $class, string $confirm = ''): void {
  ?>
  <form class="ad-inline" method="post" action="/admin/quotes"<?= $confirm ? ' data-confirm="' . ce($confirm) . '"' : '' ?>>
    <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="tab" value="<?= ce($tab) ?>">
    <input type="hidden" name="action" value="status">
    <input type="hidden" name="status" value="<?= ce($status) ?>">
    <button type="submit" class="ad-btn <?= $class ?>"><?= $label ?></button>
  </form>
  <?php
}

admin_page_start($pdo, $me, [
  'title' => 'Үнийн санал',
  'active' => 'quotes',
  'sub' => 'Тоног төхөөрөмжийн хуудаснаас ирсэн үнийн саналын хүсэлтүүд.',
]);
?>
<nav class="ad-tabs" aria-label="Хүсэлтийн төлөв">
  <?php foreach (ADMIN_QUOTE_TABS as $key => $t): ?>
  <a href="/admin/quotes<?= $key === 'new' ? '' : '?tab=' . $key ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>><?= ce($t[0]) ?> <span><?= (int)$list['counts'][$key] ?></span></a>
  <?php endforeach; ?>
</nav>

<?php if (!$list['rows']): ?>
  <p class="ad-empty"><?= $tab === 'new' ? 'Шинэ хүсэлт алга.' : 'Энэ хэсэгт хүсэлт алга.' ?></p>
<?php endif; ?>

<?php foreach ($list['rows'] as $qr):
  $id = (int)$qr['id'];
  [$label, $tag] = admin_quote_status($qr['status']);
  $tel = preg_replace('/[^\d+]/', '', $qr['buyer_phone']); ?>
<section class="ad-panel ad-quote" id="q<?= $id ?>" data-quote="<?= $id ?>" aria-labelledby="q<?= $id ?>-h">
  <div class="ad-panel__head">
    <h2 id="q<?= $id ?>-h"><?= ce($qr['buyer_name']) ?> <span class="ad-tag ad-tag--<?= $tag ?>"><?= ce($label) ?></span></h2>
    <span class="ad-small ad-muted ad-num"><?= ce(fulfillment_date_label(substr($qr['created_at'], 0, 10))) ?>, <?= ce(substr($qr['created_at'], 11, 5)) ?></span>
  </div>
  <div class="ad-grid ad-grid--side">
    <div class="ad-col">
      <dl class="ad-kv">
        <dt>Утас</dt><dd><a class="ad-tel" href="tel:<?= ce($tel) ?>"><?= ad_icon('phone') ?><?= ce($qr['buyer_phone']) ?></a></dd>
        <?php if ($qr['buyer_email']): ?><dt>Имэйл</dt><dd><a class="ad-tel" href="mailto:<?= ce($qr['buyer_email']) ?>"><?= ad_icon('mail') ?><?= ce($qr['buyer_email']) ?></a></dd><?php endif; ?>
        <?php if ($qr['buyer_company']): ?><dt>Байгууллага</dt><dd><?= ce($qr['buyer_company']) ?></dd><?php endif; ?>
      </dl>
      <?php if ($qr['message']): ?>
        <p class="ad-quote__msg"><?= ce($qr['message']) ?></p>
      <?php endif; ?>
      <?php if ($qr['items']): ?>
      <ul class="ad-quote__items">
        <?php foreach ($qr['items'] as $it): ?>
        <li><span><?= ce($it['product_name_snapshot']) ?><?php if ($it['sku_snapshot']): ?> <span class="ad-muted ad-small">(<?= ce($it['sku_snapshot']) ?>)</span><?php endif; ?></span>
          <span class="ad-num">× <?= (int)$it['quantity'] ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <form method="post" action="/admin/quotes" class="ad-quote__note">
        <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="tab" value="<?= ce($tab) ?>">
        <input type="hidden" name="action" value="note">
        <div class="ad-field">
          <label for="note<?= $id ?>">Ажилтны тэмдэглэл</label>
          <textarea id="note<?= $id ?>" name="note" rows="2" maxlength="2000" placeholder="Жишээ нь: Үнийн санал имэйлээр явуулсан 10/15"><?= ce($qr['staff_note']) ?></textarea>
        </div>
        <button type="submit" class="ad-btn ad-btn--sm">Тэмдэглэл хадгалах</button>
      </form>

      <div class="ad-btn-row">
        <?php if ($qr['status'] === 'new') quote_button($id, $tab, 'replied', ad_icon('check') . ' Хариулсан', 'ad-btn--primary'); ?>
        <?php if ($qr['status'] !== 'closed') quote_button($id, $tab, 'closed', 'Хаах', '', $qr['buyer_name'] . '-ийн хүсэлтийг хаах уу?'); ?>
        <?php if ($qr['status'] !== 'new') quote_button($id, $tab, 'new', 'Буцааж нээх', ''); ?>
      </div>
    </div>
    <div class="ad-col">
      <h3 class="ad-small ad-muted" style="margin:0 0 8px">Түүх</h3>
      <ol class="ad-timeline">
        <?php foreach ($qr['history'] as $h): ?>
        <li><?= ce($h['text']) ?> <span class="ad-when ad-num"><?= ce(admin_when($h['at'])) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>
</section>
<?php endforeach; ?>
<?php admin_page_end(); ?>
