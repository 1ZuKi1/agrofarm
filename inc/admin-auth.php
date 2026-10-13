<?php
/**
 * inc/admin-auth.php — who is using /admin/ and what they may do.
 *
 *   Accounts    admin_users (sql/orders-schema-v6.sql): name, work email,
 *               password, role 'admin' (Админ) or 'staff' (Ажилтан).
 *   Login       email + password. 5 wrong tries lock that email for 15
 *               minutes; unknown emails are counted per session the same
 *               way, and both get the same message, so nobody can probe
 *               which emails have accounts.
 *   Session     14 days on a device. The account is re-read on every request,
 *               so deactivating someone logs them out at their next click.
 *   Roles       admin_require($pdo, 'admin') guards Тохиргоо (fee, closed
 *               days, Хэрэглэгчид); everything else needs 'staff' or above.
 *   CSRF        one token per session, in <meta name="csrf-token"> on every
 *               page; every POST must carry it (field `csrf` or header
 *               X-CSRF-Token).
 *   Log         admin_log(): who did what, shown as each order's / request's
 *               history ("Хүргэсэн — Бат, 10/15 14:20").
 *
 * The old one-password admin set $_SESSION['naf_admin']; that flag means
 * nothing here — only admin_user_id does.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fulfillment.php'; // NAF_TZ, fulfillment_now()

const ADMIN_MAX_FAILS = 5;
const ADMIN_LOCK_MINUTES = 15;
const ADMIN_MIN_PASSWORD = 8;
const ADMIN_SESSION_DAYS = 14;
const ADMIN_BAD_LOGIN = 'Имэйл эсвэл нууц үг буруу байна';

function admin_session_start(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $life = ADMIN_SESSION_DAYS * 86400;
  ini_set('session.gc_maxlifetime', (string)$life);
  session_set_cookie_params([
    'lifetime' => $life,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']),
  ]);
  session_start();
}

/** null when acceptable, otherwise the Mongolian reason. */
function admin_password_problem(string $pw): ?string {
  return mb_strlen($pw, 'UTF-8') < ADMIN_MIN_PASSWORD ? 'Нууц үг дор хаяж 8 тэмдэгт байна' : null;
}

function admin_normalize_email(string $email): string {
  return mb_strtolower(trim($email), 'UTF-8');
}

/** Creates an account; throws InvalidArgumentException with a message staff can read. */
function admin_user_create(PDO $pdo, string $email, string $name, string $role, string $password, bool $mustChange): int {
  $email = admin_normalize_email($email);
  $name = trim($name);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Имэйл хаяг буруу байна');
  if ($name === '') throw new InvalidArgumentException('Нэрээ бичнэ үү');
  if ($role !== 'admin' && $role !== 'staff') throw new InvalidArgumentException('Эрх буруу байна');
  $problem = admin_password_problem($password);
  if ($problem) throw new InvalidArgumentException($problem);
  $dup = $pdo->prepare('SELECT 1 FROM admin_users WHERE email = ?');
  $dup->execute([$email]);
  if ($dup->fetchColumn()) throw new InvalidArgumentException('Энэ имэйлээр бүртгэл аль хэдийн байна');
  $st = $pdo->prepare('INSERT INTO admin_users (email, name, role, password_hash, must_change) VALUES (?, ?, ?, ?, ?)');
  $st->execute([$email, $name, $role, password_hash($password, PASSWORD_DEFAULT), $mustChange ? 1 : 0]);
  return (int)$pdo->lastInsertId();
}

function admin_lock_message(int $minutes): string {
  return 'Оролдлого хэт олон удаа буруу байна — ' . max(1, $minutes) . ' минутын дараа дахин оролдоно уу.';
}

/**
 * Checks an email + password. $now (UB) is passed in so the lock can be tested.
 * Returns ['ok' => true, 'user' => row] or ['ok' => false, 'error' => text, 'locked' => bool].
 */
function admin_attempt_login(PDO $pdo, string $email, string $password, DateTimeImmutable $now): array {
  $email = admin_normalize_email($email);
  $nowS = $now->setTimezone(new DateTimeZone(NAF_TZ))->format('Y-m-d H:i:s');
  $st = $pdo->prepare('SELECT * FROM admin_users WHERE email = ?');
  $st->execute([$email]);
  $u = $st->fetch();

  if (!$u) {
    // Unknown email: count per session, same message as a wrong password.
    $s = $_SESSION['admin_unknown_fails'] ?? ['n' => 0, 'until' => 0];
    if ($s['until'] > $now->getTimestamp()) {
      return ['ok' => false, 'error' => admin_lock_message((int)ceil(($s['until'] - $now->getTimestamp()) / 60)), 'locked' => true];
    }
    $s['n']++;
    if ($s['n'] >= ADMIN_MAX_FAILS) {
      $_SESSION['admin_unknown_fails'] = ['n' => 0, 'until' => $now->getTimestamp() + ADMIN_LOCK_MINUTES * 60];
      return ['ok' => false, 'error' => admin_lock_message(ADMIN_LOCK_MINUTES), 'locked' => true];
    }
    $_SESSION['admin_unknown_fails'] = $s;
    return ['ok' => false, 'error' => ADMIN_BAD_LOGIN, 'locked' => false];
  }

  if ($u['locked_until'] !== null && $u['locked_until'] > $nowS) {
    $left = (int)ceil((strtotime($u['locked_until']) - strtotime($nowS)) / 60);
    return ['ok' => false, 'error' => admin_lock_message($left), 'locked' => true];
  }

  if ((int)$u['active'] === 1 && password_verify($password, $u['password_hash'])) {
    $pdo->prepare('UPDATE admin_users SET failed_logins = 0, locked_until = NULL, last_login_at = ? WHERE id = ?')
        ->execute([$nowS, $u['id']]);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
      $u['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
      $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([$u['password_hash'], $u['id']]);
    }
    return ['ok' => true, 'user' => $u];
  }

  $fails = (int)$u['failed_logins'] + 1;
  if ($fails >= ADMIN_MAX_FAILS) {
    $until = $now->setTimezone(new DateTimeZone(NAF_TZ))->modify('+' . ADMIN_LOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s');
    $pdo->prepare('UPDATE admin_users SET failed_logins = 0, locked_until = ? WHERE id = ?')->execute([$until, $u['id']]);
    return ['ok' => false, 'error' => admin_lock_message(ADMIN_LOCK_MINUTES), 'locked' => true];
  }
  $pdo->prepare('UPDATE admin_users SET failed_logins = ? WHERE id = ?')->execute([$fails, $u['id']]);
  return ['ok' => false, 'error' => ADMIN_BAD_LOGIN, 'locked' => false];
}

/**
 * A short fingerprint of the stored password hash, kept in the session. When
 * the password changes (the person's own change, or an Админ's reset), every
 * other session of that account no longer matches and is logged out at its
 * next click — so a reset after a lost phone also closes the phone's session.
 */
function admin_password_sig(string $hash): string {
  return substr(hash('sha256', $hash), 0, 20);
}

/** Starts the logged-in session (after a successful login or the first-Админ setup). $user needs password_hash. */
function admin_login_session(array $user): void {
  session_regenerate_id(true);
  unset($_SESSION['naf_admin'], $_SESSION['naf_csrf_token'], $_SESSION['naf_fails'], $_SESSION['naf_lockout_until'], $_SESSION['admin_unknown_fails']);
  $_SESSION['admin_user_id'] = (int)$user['id'];
  $_SESSION['admin_pw_sig'] = admin_password_sig((string)$user['password_hash']);
  $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

/** The logged-in, active account — re-read every request — or null. */
function admin_current_user(PDO $pdo): ?array {
  $id = (int)($_SESSION['admin_user_id'] ?? 0);
  if ($id <= 0) return null;
  $st = $pdo->prepare('SELECT id, email, name, role, must_change, active, last_login_at, password_hash FROM admin_users WHERE id = ?');
  $st->execute([$id]);
  $u = $st->fetch();
  $sig = (string)($_SESSION['admin_pw_sig'] ?? '');
  if (!$u || (int)$u['active'] !== 1 || !hash_equals(admin_password_sig((string)$u['password_hash']), $sig)) {
    unset($_SESSION['admin_user_id'], $_SESSION['admin_pw_sig']);
    return null;
  }
  unset($u['password_hash']);
  return $u;
}

function admin_role_label(string $role): string {
  return $role === 'admin' ? 'Админ' : 'Ажилтан';
}

/**
 * The account using this request, or the request ends here:
 *   not logged in   pages → /admin/login?next=…   JSON → 401
 *   wrong role      pages → a 403 page            JSON → 403
 *   must change     pages → /admin/account (only that page is allowed)
 */
function admin_require(PDO $pdo, string $role = 'staff', bool $json = false): array {
  $me = admin_current_user($pdo);
  if (!$me) {
    if ($json) admin_json_fail(401, 'Нэвтрэх хугацаа дууссан — дахин нэвтэрнэ үү.');
    $next = (string)($_SERVER['REQUEST_URI'] ?? '/admin/');
    header('Location: /admin/login' . (strpos($next, '/admin/') === 0 ? '?next=' . rawurlencode($next) : ''), true, 303);
    exit;
  }
  if ($role === 'admin' && $me['role'] !== 'admin') {
    if ($json) admin_json_fail(403, 'Эрх хүрэхгүй байна');
    http_response_code(403);
    if (function_exists('admin_page_start')) {
      admin_page_start($pdo, $me, ['title' => 'Эрх хүрэхгүй', 'active' => '', 'sub' => '']);
      echo '<div class="ad-panel"><p>Энэ хуудсыг зөвхөн Админ үзнэ.</p><p><a href="/admin/">← Нүүр хуудас руу буцах</a></p></div>';
      admin_page_end();
    } else {
      echo 'Энэ хуудсыг зөвхөн Админ үзнэ.';
    }
    exit;
  }
  if ((int)$me['must_change'] === 1 && !$json) {
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (rtrim((string)$path, '/') !== '/admin/account') {
      header('Location: /admin/account', true, 303);
      exit;
    }
  }
  return $me;
}

function admin_json_fail(int $code, string $message): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

function admin_csrf(): string {
  if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['admin_csrf'];
}

/** Ends the request with 403 unless the POST carries this session's token. */
function admin_check_csrf(bool $json = false): void {
  $sent = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
  if (empty($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $sent)) {
    $msg = 'Аюулгүй байдлын токен буруу эсвэл дутуу байна — хуудсаа дахин ачаалаад оролдоно уу.';
    if ($json) admin_json_fail(403, $msg);
    http_response_code(403);
    echo $msg;
    exit;
  }
}

function admin_log(PDO $pdo, ?array $user, string $action, string $targetType, ?int $targetId, ?string $detail = null): void {
  try {
    $pdo->prepare('INSERT INTO admin_log (user_id, user_name, action, target_type, target_id, detail) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$user ? (int)$user['id'] : null, $user ? $user['name'] : 'Систем', $action, $targetType, $targetId, $detail]);
  } catch (\Throwable $e) {
    error_log('admin_log: ' . $e->getMessage()); // the action itself already happened
  }
}

/** One item's history, newest first. */
function admin_history(PDO $pdo, string $targetType, int $targetId): array {
  $st = $pdo->prepare('SELECT user_name, action, detail, created_at FROM admin_log WHERE target_type = ? AND target_id = ? ORDER BY id DESC');
  $st->execute([$targetType, $targetId]);
  return $st->fetchAll();
}

function admin_users_count_admins(PDO $pdo): int {
  return (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND active = 1")->fetchColumn();
}

/**
 * Changes another person's role, active flag or password.
 * $changes: 'role' => 'admin'|'staff', 'active' => 0|1, 'password' => string (+ 'must_change' => 0|1).
 * Refuses demoting/deactivating yourself and leaving no active Админ.
 */
function admin_user_update(PDO $pdo, array $actor, int $userId, array $changes): void {
  $st = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
  $st->execute([$userId]);
  $target = $st->fetch();
  if (!$target) throw new InvalidArgumentException('Хэрэглэгч олдсонгүй');

  $demote = isset($changes['role']) && $changes['role'] !== 'admin' && $target['role'] === 'admin';
  $deactivate = isset($changes['active']) && (int)$changes['active'] === 0 && (int)$target['active'] === 1;
  if ((int)$actor['id'] === $userId && ($demote || $deactivate)) {
    throw new InvalidArgumentException('Өөрийн эрхийг бууруулах, идэвхгүй болгох боломжгүй');
  }
  if (($demote || $deactivate) && $target['role'] === 'admin' && (int)$target['active'] === 1 && admin_users_count_admins($pdo) <= 1) {
    throw new InvalidArgumentException('Хамгийн багадаа нэг идэвхтэй Админ байх ёстой');
  }

  $set = [];
  $args = [];
  $what = [];
  if (isset($changes['role'])) {
    if ($changes['role'] !== 'admin' && $changes['role'] !== 'staff') throw new InvalidArgumentException('Эрх буруу байна');
    $set[] = 'role = ?'; $args[] = $changes['role']; $what[] = 'эрх: ' . admin_role_label($changes['role']);
  }
  if (isset($changes['active'])) {
    $set[] = 'active = ?'; $args[] = (int)$changes['active'] ? 1 : 0; $what[] = (int)$changes['active'] ? 'идэвхжүүлсэн' : 'идэвхгүй болгосон';
  }
  if (isset($changes['password'])) {
    $problem = admin_password_problem((string)$changes['password']);
    if ($problem) throw new InvalidArgumentException($problem);
    $set[] = 'password_hash = ?'; $args[] = password_hash((string)$changes['password'], PASSWORD_DEFAULT);
    $set[] = 'must_change = ?'; $args[] = (int)($changes['must_change'] ?? 1) ? 1 : 0;
    $set[] = 'failed_logins = 0'; $set[] = 'locked_until = NULL';
    $what[] = 'нууц үг шинэчилсэн';
  }
  if (!$set) return;
  $args[] = $userId;
  $pdo->prepare('UPDATE admin_users SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($args);
  admin_log($pdo, $actor, 'user_update', 'user', $userId, $target['name'] . ' — ' . implode(', ', $what));
}
