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
 */

require __DIR__ . '/config.php';

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
  echo 'Method not allowed';
  exit;
}

// Check the lockout BEFORE verifying the password, so a locked-out session
// learns nothing about whether the supplied password was correct.
if (isset($_SESSION['naf_lockout_until']) && $_SESSION['naf_lockout_until'] > time()) {
  $minutes = (int)ceil(($_SESSION['naf_lockout_until'] - time()) / 60);
  http_response_code(429);
  echo "Too many failed attempts — try again in {$minutes} minute(s).";
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
  echo 'Too many failed attempts — locked for 15 minutes.';
  exit;
}

http_response_code(403);
echo 'Invalid password';
