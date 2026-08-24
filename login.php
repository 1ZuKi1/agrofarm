<?php
/**
 * login.php — validates the admin password and opens a server session.
 *
 * Called via POST from admin.js. On success it sets $_SESSION['naf_admin'],
 * regenerates the session id (defeats session fixation), and returns a fresh
 * CSRF token as the response body — the admin panel must send that token with
 * every publish/upload. The password itself is never stored in the browser;
 * only its bcrypt hash lives in config.php.
 *
 * Brute-force protection: failed attempts are counted per session. After
 * MAX_FAILS the session is locked out for LOCKOUT_SECONDS and returns 429.
 *
 * config.php should live ONE DIRECTORY ABOVE public_html (outside the web
 * root) so it can never be served over HTTP, no matter how the server is
 * configured. This checks that location first and falls back to the old
 * in-webroot spot only if it hasn't been moved yet — safe during migration.
 */

require (file_exists(__DIR__ . '/../config.php'))
  ? __DIR__ . '/../config.php'
  : __DIR__ . '/config.php';

session_set_cookie_params([
  'httponly' => true,
  'samesite' => 'Lax',
  'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();
header('Content-Type: text/plain; charset=utf-8');

const MAX_FAILS = 5;
const LOCKOUT_SECONDS = 15 * 60;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Метод зөвшөөрөгдөөгүй';
  exit;
}

// Check the lockout BEFORE verifying the password, so a locked-out session
// learns nothing about whether the supplied password was correct.
if (isset($_SESSION['naf_lockout_until']) && $_SESSION['naf_lockout_until'] > time()) {
  $minutes = (int)ceil(($_SESSION['naf_lockout_until'] - time()) / 60);
  http_response_code(429);
  echo "Оролдлого хэт олон удаа буруу байна — {$minutes} минутын дараа дахин оролдоно уу.";
  exit;
}

$password = $_POST['password'] ?? '';

if (password_verify($password, NAF_ADMIN_PASSWORD_HASH)) {
  session_regenerate_id(true);
  $_SESSION['naf_admin'] = true;
  $_SESSION['naf_fails'] = 0;
  unset($_SESSION['naf_lockout_until']);
  $_SESSION['naf_csrf_token'] = bin2hex(random_bytes(32));
  echo $_SESSION['naf_csrf_token'];
  exit;
}

$_SESSION['naf_fails'] = ($_SESSION['naf_fails'] ?? 0) + 1;
if ($_SESSION['naf_fails'] >= MAX_FAILS) {
  $_SESSION['naf_lockout_until'] = time() + LOCKOUT_SECONDS;
  $_SESSION['naf_fails'] = 0;
  http_response_code(429);
  echo 'Оролдлого хэт олон удаа буруу байна — 15 минутын турш түгжигдлээ.';
  exit;
}

http_response_code(403);
echo 'Нууц үг буруу байна';
