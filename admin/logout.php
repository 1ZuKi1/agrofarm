<?php
/**
 * logout.php — /admin/logout: ends the session (POST from the sidebar button,
 * with the CSRF token, so another site can't log staff out).
 */

require dirname(__DIR__) . '/inc/admin-auth.php';

admin_session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  admin_check_csrf();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}
header('Location: /admin/login', true, 303);
