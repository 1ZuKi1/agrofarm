<?php
/**
 * login.php — /admin/login: the login form, and the one-time first-Админ setup.
 *
 * While admin_users is empty (just after sql/orders-schema-v6.sql), this page
 * shows "Анхны тохиргоо" instead: the old shared password (NAF_ADMIN_PASSWORD_HASH
 * in config.php) is asked for once, only to create the first Админ — name,
 * email, new password. As soon as one account exists, that form and the old
 * password are gone for good.
 *
 * ?next=/admin/… returns the person to the page they were on.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();

try {
  $pdo = naf_db();
  $userCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
} catch (PDOException $e) {
  if ($e->getCode() === '42S02') { error_log('admin login: run sql/orders-schema-v6.sql'); admin_schema_missing(); }
  throw $e;
}

$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
if (strpos($next, '/admin/') !== 0 || strpos($next, '//') !== false || strpos($next, '/admin/login') === 0) $next = '/admin/';

// Already logged in → straight in.
if ($userCount > 0 && admin_current_user($pdo)) admin_redirect($next);

$setup = ($userCount === 0);
$error = '';
$email = '';
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  admin_check_csrf();
  if ($setup) {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');
    if (!defined('NAF_ADMIN_PASSWORD_HASH')) {
      $error = 'config.php-д NAF_ADMIN_PASSWORD_HASH алга — анхны Админыг үүсгэх боломжгүй.';
    } elseif (!password_verify((string)($_POST['shared'] ?? ''), NAF_ADMIN_PASSWORD_HASH)) {
      $error = 'Хуучин нууц үг буруу байна';
    } elseif ($pass !== $pass2) {
      $error = 'Нууц үг таарахгүй байна';
    } else {
      try {
        // Re-check inside the request: two people must not both become "first".
        if ((int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0) admin_redirect('/admin/login');
        $id = admin_user_create($pdo, $email, $name, 'admin', $pass, false);
        $st = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
        $st->execute([$id]);
        $user = $st->fetch();
        admin_login_session($user);
        admin_log($pdo, $user, 'user_create', 'user', $id, 'Анхны Админ');
        admin_flash('ok', 'Бүртгэл үүслээ. Тавтай морил!');
        admin_redirect('/admin/');
      } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
      }
    }
  } else {
    $email = trim((string)($_POST['email'] ?? ''));
    $r = admin_attempt_login($pdo, $email, (string)($_POST['password'] ?? ''), fulfillment_now());
    if ($r['ok']) {
      admin_login_session($r['user']);
      admin_redirect((int)$r['user']['must_change'] === 1 ? '/admin/account' : $next);
    }
    $error = $r['error'];
  }
}

admin_head($setup ? 'Анхны тохиргоо' : 'Нэвтрэх');
?>
<body class="ad ad--bare">
<main class="ad-auth" id="main">
  <div class="ad-auth__card">
    <img class="ad-auth__logo" src="/img/naf_logo_full.svg" alt="Нүүдэлчин Агро Ферм" width="120" height="60">
<?php if ($setup): ?>
    <h1>Анхны тохиргоо</h1>
    <p class="ad-auth__lead">Ажлын орчинд одоогоор бүртгэл алга. Эхний Админ бүртгэлээ үүсгэнэ үү — хуучин нийтийн нууц үгийг нэг л удаа асууна.</p>
    <?php if ($error): ?><div class="ad-flash ad-flash--err" role="alert"><?= ad_icon('alert') ?><span><?= ce($error) ?></span></div><?php endif; ?>
    <form id="setupForm" method="post" action="/admin/login" novalidate>
      <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
      <div class="ad-field"><label for="setupShared">Хуучин нийтийн нууц үг</label>
        <input type="password" id="setupShared" name="shared" autocomplete="off" required></div>
      <div class="ad-field"><label for="setupName">Таны нэр</label>
        <input type="text" id="setupName" name="name" value="<?= ce($name) ?>" autocomplete="name" required></div>
      <div class="ad-field"><label for="setupEmail">Ажлын имэйл</label>
        <input type="email" id="setupEmail" name="email" value="<?= ce($email) ?>" autocomplete="email" required></div>
      <div class="ad-field"><label for="setupPass">Шинэ нууц үг</label>
        <input type="password" id="setupPass" name="password" autocomplete="new-password" minlength="8" required aria-describedby="setupHint">
        <span class="ad-hint" id="setupHint">Дор хаяж 8 тэмдэгт.</span></div>
      <div class="ad-field"><label for="setupPass2">Шинэ нууц үг (дахин)</label>
        <input type="password" id="setupPass2" name="password2" autocomplete="new-password" minlength="8" required></div>
      <button type="submit" class="ad-btn ad-btn--primary">Бүртгэл үүсгэх</button>
    </form>
    <p class="ad-auth__note">Үүний дараа хуучин нийтийн нууц үг ажиллахаа болино. Бусад ажилтныг Тохиргоо → Хэрэглэгчид хэсгээс нэмнэ.</p>
<?php else: ?>
    <h1>Ажлын орчинд нэвтрэх</h1>
    <?php if ($error): ?><div class="ad-flash ad-flash--err" role="alert"><?= ad_icon('alert') ?><span><?= ce($error) ?></span></div><?php endif; ?>
    <form id="loginForm" method="post" action="/admin/login" novalidate>
      <input type="hidden" name="csrf" value="<?= ce(admin_csrf()) ?>">
      <input type="hidden" name="next" value="<?= ce($next) ?>">
      <div class="ad-field"><label for="loginEmail">Имэйл</label>
        <input type="email" id="loginEmail" name="email" value="<?= ce($email) ?>" autocomplete="username" inputmode="email" required autofocus></div>
      <div class="ad-field"><label for="loginPassword">Нууц үг</label>
        <input type="password" id="loginPassword" name="password" autocomplete="current-password" required></div>
      <button type="submit" class="ad-btn ad-btn--primary">Нэвтрэх</button>
    </form>
    <p class="ad-auth__note">Нууц үгээ мартсан бол Админд хандаж түр нууц үг авна уу.</p>
<?php endif; ?>
  </div>
</main>
</body>
</html>
