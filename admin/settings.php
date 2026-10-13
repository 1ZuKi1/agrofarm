<?php
/**
 * settings.php — /admin/settings: Тохиргоо (Админ only).
 *
 *   Хүргэлтийн төлбөр  whole tögrög, 0–100,000 (inc/fulfillment.php reads it)
 *   Амралтын өдөр       closed days the checkout skips like weekends: add a day
 *                       or a range (at most 31 days), remove one; past days drop off
 *   Хэрэглэгчид         add (temporary password → must change), reset a password,
 *                       change the role, deactivate / reactivate. Nobody can demote
 *                       or deactivate themselves, and the last active Админ stays
 *                       (admin_user_update in inc/admin-auth.php).
 *
 * Every form posts here, then redirects (with a message). A staff account gets
 * the 403 page for GET and POST alike — admin_require() runs first.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';
require_once dirname(__DIR__) . '/inc/settings.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo, 'admin');

/** 'Y-m-d' → DateTimeImmutable in UB time, or null if not a real date. */
function settings_date(string $s): ?DateTimeImmutable {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
  $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s, new DateTimeZone(NAF_TZ));
  return ($d && $d->format('Y-m-d') === $s) ? $d : null;
}

/** A temporary password that is easy to read out over the phone. */
function settings_temp_password(): string {
  $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
  $out = '';
  for ($i = 0; $i < 10; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
  return substr($out, 0, 5) . '-' . substr($out, 5);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  admin_check_csrf();
  $action = (string)($_POST['action'] ?? '');
  $anchor = '';
  try {
    if ($action === 'fee') {
      $anchor = '#fee';
      $fee = trim((string)($_POST['fee'] ?? ''));
      if (!preg_match('/^\d{1,6}$/', $fee) || (int)$fee > 100000) throw new InvalidArgumentException('Хүргэлтийн төлбөр 0–100,000₮ байна');
      $old = fulfillment_delivery_fee($pdo);
      settings_set($pdo, 'delivery_fee', (string)(int)$fee);
      if ($old !== (int)$fee) admin_log($pdo, $me, 'settings_fee', 'settings', null, $old . ' → ' . (int)$fee);
      admin_flash('ok', 'Хадгалсан: хүргэлтийн төлбөр ' . admin_money((int)$fee));

    } elseif ($action === 'closed_add') {
      $anchor = '#closed';
      $from = settings_date(trim((string)($_POST['from'] ?? '')));
      $toRaw = trim((string)($_POST['to'] ?? ''));
      $to = $toRaw === '' ? $from : settings_date($toRaw);
      if (!$from || !$to) throw new InvalidArgumentException('Огноогоо сонгоно уу');
      if ($to < $from) throw new InvalidArgumentException('Дуусах өдөр эхлэх өдрөөс өмнө байж болохгүй');
      if ((int)$from->diff($to)->format('%a') > 30) throw new InvalidArgumentException('Нэг удаад 31 хоногоос ихгүй хугацаа нэмнэ');
      $today = fulfillment_now()->format('Y-m-d');
      $days = fulfillment_closed_days($pdo);
      for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) $days[] = $d->format('Y-m-d');
      $days = array_values(array_unique(array_filter($days, function ($d) use ($today) { return $d >= $today; })));
      sort($days);
      settings_set($pdo, 'closed_days', json_encode($days));
      $range = $from->format('Y-m-d') . ($to > $from ? ' – ' . $to->format('Y-m-d') : '');
      admin_log($pdo, $me, 'settings_closed_add', 'settings', null, $range);
      admin_flash('ok', 'Амралтын өдөр нэмэгдлээ: ' . $range);

    } elseif ($action === 'closed_remove') {
      $anchor = '#closed';
      $date = (string)($_POST['date'] ?? '');
      $days = array_values(array_filter(fulfillment_closed_days($pdo), function ($d) use ($date) { return $d !== $date; }));
      settings_set($pdo, 'closed_days', json_encode($days));
      admin_log($pdo, $me, 'settings_closed_remove', 'settings', null, $date);
      admin_flash('ok', 'Амралтын өдөр хасагдлаа: ' . $date);

    } elseif ($action === 'user_add') {
      $anchor = '#users';
      $name = trim((string)($_POST['name'] ?? ''));
      $email = (string)($_POST['email'] ?? '');
      $role = (string)($_POST['role'] ?? 'staff');
      try {
        $newId = admin_user_create($pdo, $email, $name, $role, (string)($_POST['password'] ?? ''), true);
      } catch (InvalidArgumentException $e) {
        $_SESSION['admin_old_user'] = ['name' => $name, 'email' => $email, 'role' => $role];
        throw $e;
      }
      admin_log($pdo, $me, 'user_add', 'user', $newId, $name . ' — ' . admin_role_label($role));
      admin_flash('ok', $name . ' нэмэгдлээ. Түр нууц үгээ өөрт нь хэлээрэй — анх нэвтрэхдээ шинэ нууц үг сонгоно.');

    } elseif ($action === 'user') {
      $anchor = '#users';
      $id = (int)($_POST['id'] ?? 0);
      $change = (string)($_POST['change'] ?? '');
      $map = [
        'admin' => ['role' => 'admin'], 'staff' => ['role' => 'staff'],
        'activate' => ['active' => 1], 'deactivate' => ['active' => 0],
      ];
      if ($change === 'reset') {
        $tmp = settings_temp_password();
        admin_user_update($pdo, $me, $id, ['password' => $tmp, 'must_change' => 1]);
        admin_flash('ok', 'Түр нууц үг: ' . $tmp . ' — өөрт нь хэлээрэй. Нэвтэрмэгц шинэ нууц үг сонгоно.');
      } elseif (isset($map[$change])) {
        admin_user_update($pdo, $me, $id, $map[$change]);
        admin_flash('ok', 'Хадгалагдлаа');
      } else {
        throw new InvalidArgumentException('Тодорхойгүй үйлдэл');
      }
    } else {
      throw new InvalidArgumentException('Тодорхойгүй үйлдэл');
    }
  } catch (InvalidArgumentException $e) {
    admin_flash('err', $e->getMessage());
  }
  admin_redirect('/admin/settings' . $anchor);
}

$fee = fulfillment_delivery_fee($pdo);
$closed = fulfillment_closed_days($pdo);
$users = $pdo->query("SELECT id, email, name, role, active, must_change, locked_until, last_login_at FROM admin_users
                      ORDER BY active DESC, role = 'admin' DESC, name")->fetchAll();
$old = $_SESSION['admin_old_user'] ?? ['name' => '', 'email' => '', 'role' => 'staff'];
unset($_SESSION['admin_old_user']);
$today = fulfillment_now()->format('Y-m-d');

/** One small form with a single button for a user row. */
function settings_user_button(int $id, string $change, string $label, string $class = '', string $confirm = ''): void {
  ?>
  <form class="ad-inline" method="post" action="/admin/settings"<?= $confirm ? ' data-confirm="' . ce($confirm) . '"' : '' ?>>
    <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
    <input type="hidden" name="action" value="user">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="change" value="<?= ce($change) ?>">
    <button type="submit" class="ad-btn ad-btn--sm <?= $class ?>"><?= ce($label) ?></button>
  </form>
  <?php
}

admin_page_start($pdo, $me, [
  'title' => 'Тохиргоо',
  'active' => 'settings',
  'sub' => 'Хүргэлтийн төлбөр, амралтын өдөр, хэрэглэгчид. Зөвхөн Админ үзнэ.',
]);
?>
<div class="ad-grid ad-grid--side">
  <div class="ad-col">
    <section class="ad-panel" id="users" aria-labelledby="users-h">
      <div class="ad-panel__head"><h2 id="users-h"><?= ad_icon('user') ?> Хэрэглэгчид</h2></div>
      <div class="ad-table-wrap">
        <table class="ad-table ad-table--cards" id="usersTable">
          <thead><tr><th scope="col">Нэр, имэйл</th><th scope="col">Эрх</th><th scope="col">Сүүлд нэвтэрсэн</th><th scope="col">Үйлдэл</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u):
              $uid = (int)$u['id']; $self = $uid === (int)$me['id']; $active = (int)$u['active'] === 1; ?>
            <tr data-user="<?= $uid ?>">
              <td><strong><?= ce($u['name']) ?></strong><?= $self ? ' <span class="ad-small ad-muted">(та)</span>' : '' ?>
                <span class="ad-sub"><?= ce($u['email']) ?></span></td>
              <td>
                <span class="ad-tag <?= $u['role'] === 'admin' ? 'ad-tag--info' : 'ad-tag--mute' ?>"><?= ce(admin_role_label($u['role'])) ?></span>
                <?php if (!$active): ?><span class="ad-tag ad-tag--bad">Идэвхгүй</span><?php endif; ?>
                <?php if ((int)$u['must_change'] === 1 && $active): ?><span class="ad-tag ad-tag--warn">Түр нууц үгтэй</span><?php endif; ?>
                <?php if ($u['locked_until'] && $u['locked_until'] > fulfillment_now()->format('Y-m-d H:i:s')): // stored in UB time ?><span class="ad-tag ad-tag--bad">Түгжээтэй</span><?php endif; ?>
              </td>
              <td class="ad-num"><?= $u['last_login_at'] ? ce(admin_when($u['last_login_at'])) : '<span class="ad-muted">Нэвтрээгүй</span>' ?></td>
              <td><div class="ad-btn-row">
                <?php if (!$self): ?>
                  <?php if ($u['role'] === 'admin') settings_user_button($uid, 'staff', 'Ажилтан болгох', '', $u['name'] . '-г Ажилтан болгох уу? Тохиргоо хэсгийг үзэхээ болино.');
                        else settings_user_button($uid, 'admin', 'Админ болгох', '', $u['name'] . '-г Админ болгох уу? Тохиргоо, хэрэглэгчдийг өөрчилж чадна.'); ?>
                  <?php settings_user_button($uid, 'reset', 'Нууц үг шинэчлэх', '', $u['name'] . '-ийн нууц үгийг шинэчлэх үү? Түр нууц үг гарна.'); ?>
                  <?php if ($active) settings_user_button($uid, 'deactivate', 'Идэвхгүй болгох', 'ad-btn--danger', $u['name'] . '-г идэвхгүй болгох уу? Дараагийн дарахад системээс гарна.');
                        else settings_user_button($uid, 'activate', 'Идэвхжүүлэх'); ?>
                <?php else: ?>
                  <a class="ad-btn ad-btn--sm" href="/admin/account">Миний нууц үг</a>
                <?php endif; ?>
              </div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h3 style="margin:20px 0 10px">Хэрэглэгч нэмэх</h3>
      <form id="userAddForm" method="post" action="/admin/settings" class="ad-form-grid" novalidate>
        <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
        <input type="hidden" name="action" value="user_add">
        <div class="ad-field"><label for="uName">Нэр</label><input id="uName" name="name" type="text" value="<?= ce($old['name']) ?>" autocomplete="off" required></div>
        <div class="ad-field"><label for="uEmail">Имэйл</label><input id="uEmail" name="email" type="email" value="<?= ce($old['email']) ?>" autocomplete="off" required></div>
        <div class="ad-field"><label for="uRole">Эрх</label>
          <select id="uRole" name="role" aria-describedby="uRoleHint">
            <option value="staff"<?= $old['role'] !== 'admin' ? ' selected' : '' ?>>Ажилтан</option>
            <option value="admin"<?= $old['role'] === 'admin' ? ' selected' : '' ?>>Админ</option>
          </select>
          <span class="ad-hint" id="uRoleHint">Ажилтан: захиалга, үнийн санал, бүтээгдэхүүн, мэдээ. Админ: мөн тохиргоо, хэрэглэгчид.</span></div>
        <div class="ad-field"><label for="uPass">Түр нууц үг</label>
          <input id="uPass" name="password" type="text" value="<?= ce(settings_temp_password()) ?>" autocomplete="off" minlength="8" required aria-describedby="uPassHint">
          <span class="ad-hint" id="uPassHint">Дор хаяж 8 тэмдэгт. Өөрт нь хэлнэ — анх нэвтрэхдээ шинэ нууц үг сонгоно.</span></div>
        <div class="ad-form-actions"><button type="submit" class="ad-btn ad-btn--primary">Нэмэх</button></div>
      </form>
    </section>
  </div>

  <div class="ad-col">
    <section class="ad-panel" id="fee" aria-labelledby="fee-h">
      <div class="ad-panel__head"><h2 id="fee-h"><?= ad_icon('truck') ?> Хүргэлтийн төлбөр</h2></div>
      <form id="feeForm" method="post" action="/admin/settings" novalidate>
        <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
        <input type="hidden" name="action" value="fee">
        <div class="ad-field"><label for="feeInput">Улаанбаатар доторх хүргэлт (₮)</label>
          <input id="feeInput" name="fee" type="number" inputmode="numeric" min="0" max="100000" step="500" value="<?= $fee ?>" required aria-describedby="feeHint">
          <span class="ad-hint" id="feeHint">Худалдан авагч хүргүүлэх сонголт хийхэд нэмэгдэнэ. Очиж авахад төлбөргүй.</span></div>
        <div class="ad-form-actions"><button type="submit" class="ad-btn ad-btn--primary">Хадгалах</button></div>
      </form>
    </section>

    <section class="ad-panel" id="closed" aria-labelledby="closed-h">
      <div class="ad-panel__head"><h2 id="closed-h"><?= ad_icon('calendar') ?> Амралтын өдөр</h2></div>
      <p class="ad-small ad-muted" style="margin:0 0 12px">Эдгээр өдөр хүргэлт, очиж авах аль аль нь хийгдэхгүй (Цагаан сар, Наадам гэх мэт). Бямба, Ням өөрөө амралт.</p>
      <?php if (!$closed): ?>
        <p class="ad-empty" style="margin-bottom:12px">Амралтын өдөр нэмээгүй байна.</p>
      <?php endif; ?>
      <ul class="ad-quote__items" id="closedList" style="margin-bottom:14px"<?= $closed ? '' : ' hidden' ?>>
        <?php foreach ($closed as $d): ?>
        <li><span><?= ce(fulfillment_date_label($d)) ?> <span class="ad-small ad-muted ad-num"><?= ce($d) ?></span></span>
          <form class="ad-inline" method="post" action="/admin/settings">
            <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
            <input type="hidden" name="action" value="closed_remove">
            <input type="hidden" name="date" value="<?= ce($d) ?>">
            <button type="submit" class="ad-btn ad-btn--sm" data-date="<?= ce($d) ?>" aria-label="<?= ce(fulfillment_date_label($d)) ?>-г хасах">Хасах</button>
          </form></li>
        <?php endforeach; ?>
      </ul>
      <form id="closedForm" method="post" action="/admin/settings" novalidate>
        <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
        <input type="hidden" name="action" value="closed_add">
        <div class="ad-field"><label for="closedFrom">Эхлэх өдөр</label><input id="closedFrom" name="from" type="date" min="<?= ce($today) ?>" required></div>
        <div class="ad-field"><label for="closedTo">Дуусах өдөр <span class="ad-muted">(нэг өдөр бол хоосон)</span></label><input id="closedTo" name="to" type="date" min="<?= ce($today) ?>"></div>
        <div class="ad-form-actions"><button type="submit" class="ad-btn ad-btn--primary">Нэмэх</button></div>
      </form>
    </section>
  </div>
</div>
<?php admin_page_end(); ?>
