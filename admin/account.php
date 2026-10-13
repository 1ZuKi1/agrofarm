<?php
/**
 * account.php — /admin/account: "Миний бүртгэл" — change your own password.
 *
 * Someone who logged in with a temporary password (set by an Админ) lands
 * here and can't open any other page until they choose a new one; for them
 * the current password isn't asked again.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo);
$forced = (int)$me['must_change'] === 1;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  admin_check_csrf();
  $new = (string)($_POST['new'] ?? '');
  $new2 = (string)($_POST['new2'] ?? '');
  $st = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
  $st->execute([$me['id']]);
  $hash = (string)$st->fetchColumn();
  if (!$forced && !password_verify((string)($_POST['current'] ?? ''), $hash)) {
    $error = 'Одоогийн нууц үг буруу байна';
  } elseif ($problem = admin_password_problem($new)) {
    $error = $problem;
  } elseif ($new !== $new2) {
    $error = 'Нууц үг таарахгүй байна';
  } elseif (password_verify($new, $hash)) {
    $error = 'Шинэ нууц үг хуучнаасаа өөр байх ёстой';
  } else {
    $pdo->prepare('UPDATE admin_users SET password_hash = ?, must_change = 0 WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
    admin_log($pdo, $me, 'password_change', 'user', (int)$me['id'], null);
    admin_flash('ok', 'Нууц үг шинэчлэгдлээ');
    admin_redirect($forced ? '/admin/' : '/admin/account');
  }
}

admin_page_start($pdo, $me, [
  'title' => 'Миний бүртгэл',
  'active' => 'account',
  'sub' => $forced ? 'Түр нууц үгээр нэвтэрсэн тул эхлээд өөрийн нууц үгээ сонгоно уу.' : 'Нууц үгээ солих.',
]);
?>
<div class="ad-grid ad-grid--side">
  <section class="ad-panel" aria-labelledby="pw-h">
    <div class="ad-panel__head"><h2 id="pw-h"><?= ad_icon('shield') ?> Нууц үг солих</h2></div>
    <?php if ($error): ?><div class="ad-flash ad-flash--err" role="alert"><?= ad_icon('alert') ?><span><?= ce($error) ?></span></div><?php endif; ?>
    <form id="accountForm" method="post" action="/admin/account" novalidate>
      <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
      <?php if (!$forced): ?>
      <div class="ad-field"><label for="accCurrent">Одоогийн нууц үг</label>
        <input type="password" id="accCurrent" name="current" autocomplete="current-password" required></div>
      <?php endif; ?>
      <div class="ad-field"><label for="accNew">Шинэ нууц үг</label>
        <input type="password" id="accNew" name="new" autocomplete="new-password" minlength="8" required aria-describedby="accHint">
        <span class="ad-hint" id="accHint">Дор хаяж 8 тэмдэгт. Бусдад хэлж болохгүй.</span></div>
      <div class="ad-field"><label for="accNew2">Шинэ нууц үг (дахин)</label>
        <input type="password" id="accNew2" name="new2" autocomplete="new-password" minlength="8" required></div>
      <div class="ad-form-actions"><button type="submit" class="ad-btn ad-btn--primary">Хадгалах</button></div>
    </form>
  </section>
  <section class="ad-panel" aria-labelledby="me-h">
    <div class="ad-panel__head"><h2 id="me-h"><?= ad_icon('user') ?> Бүртгэл</h2></div>
    <dl class="ad-kv">
      <dt>Нэр</dt><dd><?= ce($me['name']) ?></dd>
      <dt>Имэйл</dt><dd><?= ce($me['email']) ?></dd>
      <dt>Эрх</dt><dd><?= ce(admin_role_label($me['role'])) ?></dd>
    </dl>
    <p class="ad-small ad-muted" style="margin-top:12px">Нэр, имэйл, эрхийг Админ Тохиргоо → Хэрэглэгчид хэсгээс өөрчилнө.</p>
  </section>
</div>
<?php admin_page_end(); ?>
