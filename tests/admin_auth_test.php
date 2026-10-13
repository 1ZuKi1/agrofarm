<?php
/**
 * tests/admin_auth_test.php — accounts, login lockout, roles and the activity
 * log in inc/admin-auth.php. Run from the project root, after
 * sql/orders-schema-v6.sql:
 *
 *   php tests/admin_auth_test.php
 *
 * Creates users with emails ending @auth-test.mn and removes them at the end.
 * Not deployed.
 */

require dirname(__DIR__) . '/inc/admin-auth.php';

$failed = 0;
function check(bool $ok, string $name): void {
  global $failed;
  echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
  if (!$ok) $failed++;
}
function throws(callable $f): string {
  try { $f(); } catch (InvalidArgumentException $e) { return $e->getMessage(); }
  return '';
}

$_SESSION = [];
$pdo = naf_db();
$clean = function () use ($pdo) {
  $pdo->exec("DELETE FROM admin_log WHERE user_name LIKE 'АТ %'");
  $pdo->exec("DELETE FROM admin_users WHERE email LIKE '%@auth-test.mn'");
};
$clean();
$now = new DateTimeImmutable('2026-10-15 10:00', new DateTimeZone('Asia/Ulaanbaatar'));

try {
  // ---- password rule
  check(admin_password_problem('1234567') === 'Нууц үг дор хаяж 8 тэмдэгт байна', '7 characters refused');
  check(admin_password_problem('12345678') === null, '8 characters accepted');

  // ---- create
  $a1 = admin_user_create($pdo, ' Bat@Auth-Test.mn ', 'АТ Бат', 'admin', 'secret-pass-1', false);
  check($a1 > 0, 'admin created');
  $email = $pdo->query("SELECT email FROM admin_users WHERE id = $a1")->fetchColumn();
  check($email === 'bat@auth-test.mn', 'email stored trimmed and lower case');
  check(throws(function () use ($pdo) { admin_user_create($pdo, 'bat@auth-test.mn', 'x', 'staff', 'secret-pass-2', true); })
    === 'Энэ имэйлээр бүртгэл аль хэдийн байна', 'duplicate email refused');
  check(throws(function () use ($pdo) { admin_user_create($pdo, 'nope', 'x', 'staff', 'secret-pass-2', true); }) !== '', 'bad email refused');
  check(throws(function () use ($pdo) { admin_user_create($pdo, 'x@auth-test.mn', 'x', 'boss', 'secret-pass-2', true); }) !== '', 'bad role refused');
  check(throws(function () use ($pdo) { admin_user_create($pdo, 'y@auth-test.mn', 'x', 'staff', 'short', true); }) === 'Нууц үг дор хаяж 8 тэмдэгт байна', 'short password refused');

  // ---- login
  $r = admin_attempt_login($pdo, 'BAT@auth-test.mn', 'secret-pass-1', $now);
  check($r['ok'] && (int)$r['user']['id'] === $a1, 'login ok (email case-insensitive)');
  $r = admin_attempt_login($pdo, 'bat@auth-test.mn', 'wrong-pass', $now);
  check(!$r['ok'] && $r['error'] === 'Имэйл эсвэл нууц үг буруу байна', 'wrong password → generic error');
  check((int)$pdo->query("SELECT failed_logins FROM admin_users WHERE id = $a1")->fetchColumn() === 1, 'failure counted');
  for ($i = 0; $i < 4; $i++) $r = admin_attempt_login($pdo, 'bat@auth-test.mn', 'wrong-pass', $now);
  check(!$r['ok'] && !empty($r['locked']) && strpos($r['error'], 'Оролдлого хэт олон удаа буруу байна — 15 минутын дараа') === 0, '5th failure locks (' . $r['error'] . ')');
  $r = admin_attempt_login($pdo, 'bat@auth-test.mn', 'secret-pass-1', $now->modify('+5 minutes'));
  check(!$r['ok'] && !empty($r['locked']), 'right password refused while locked');
  $r = admin_attempt_login($pdo, 'bat@auth-test.mn', 'secret-pass-1', $now->modify('+16 minutes'));
  check($r['ok'], 'after 15 minutes the right password works');
  check((int)$pdo->query("SELECT failed_logins FROM admin_users WHERE id = $a1")->fetchColumn() === 0, 'counter reset after success');
  $r = admin_attempt_login($pdo, 'nobody@auth-test.mn', 'whatever-1', $now);
  check(!$r['ok'] && $r['error'] === 'Имэйл эсвэл нууц үг буруу байна', 'unknown email → the same generic error');
  for ($i = 0; $i < 4; $i++) $r = admin_attempt_login($pdo, 'nobody2@auth-test.mn', 'whatever-1', $now);
  check(!empty($r['locked']), 'unknown emails lock the session after 5 tries too');
  $_SESSION = [];
  $s1 = admin_user_create($pdo, 'dorj@auth-test.mn', 'АТ Дорж', 'staff', 'secret-pass-3', true);
  $pdo->exec("UPDATE admin_users SET active = 0 WHERE id = $s1");
  $r = admin_attempt_login($pdo, 'dorj@auth-test.mn', 'secret-pass-3', $now);
  check(!$r['ok'] && $r['error'] === 'Имэйл эсвэл нууц үг буруу байна', 'inactive account → the generic error');
  $pdo->exec("UPDATE admin_users SET active = 1 WHERE id = $s1");

  // ---- user changes: self, last admin
  $me = $pdo->query("SELECT * FROM admin_users WHERE id = $a1")->fetch();
  check(throws(function () use ($pdo, $me, $a1) { admin_user_update($pdo, $me, $a1, ['role' => 'staff']); })
    === 'Өөрийн эрхийг бууруулах, идэвхгүй болгох боломжгүй', 'cannot demote yourself');
  check(throws(function () use ($pdo, $me, $a1) { admin_user_update($pdo, $me, $a1, ['active' => 0]); })
    === 'Өөрийн эрхийг бууруулах, идэвхгүй болгох боломжгүй', 'cannot deactivate yourself');
  $a2 = admin_user_create($pdo, 'saraa@auth-test.mn', 'АТ Сараа', 'admin', 'secret-pass-4', false);
  $others = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='admin' AND active=1 AND email NOT LIKE '%@auth-test.mn'")->fetchColumn();
  admin_user_update($pdo, $me, $a2, ['active' => 0]);
  check((int)$pdo->query("SELECT active FROM admin_users WHERE id = $a2")->fetchColumn() === 0, 'an admin can deactivate another admin');
  $me2 = $pdo->query("SELECT * FROM admin_users WHERE id = $a2")->fetch();
  $pdo->exec("UPDATE admin_users SET active = 1 WHERE id = $a2");
  $me2 = $pdo->query("SELECT * FROM admin_users WHERE id = $a2")->fetch();
  if ($others === 0) {
    // Only test admins exist: deactivating Бат leaves Сараа; then Сараа can't remove the last one.
    admin_user_update($pdo, $me2, $a1, ['active' => 0]);
    check(throws(function () use ($pdo, $me, $a2) { admin_user_update($pdo, $me, $a2, ['active' => 0]); })
      === 'Хамгийн багадаа нэг идэвхтэй Админ байх ёстой', 'the last active admin cannot be removed');
  } else {
    echo "SKIP last-admin check (other admins exist in this DB)\n";
  }
  check(admin_users_count_admins($pdo) >= 1, 'at least one active admin remains');
  admin_user_update($pdo, $me2, $s1, ['password' => 'new-temp-pass', 'must_change' => 1]);
  $row = $pdo->query("SELECT password_hash, must_change FROM admin_users WHERE id = $s1")->fetch();
  check(password_verify('new-temp-pass', $row['password_hash']) && (int)$row['must_change'] === 1, 'password reset sets a temporary password + must change');

  // ---- log
  admin_log($pdo, $me, 'order_fulfill', 'order', 999001, 'test');
  admin_log($pdo, $me2, 'order_cancel', 'order', 999001, null);
  $h = admin_history($pdo, 'order', 999001);
  check(count($h) === 2 && $h[0]['action'] === 'order_cancel' && $h[0]['user_name'] === 'АТ Сараа' && $h[1]['user_name'] === 'АТ Бат', 'history newest first with name snapshots');
  $logged = (int)$pdo->query("SELECT COUNT(*) FROM admin_log WHERE action='user_update' AND target_id = $s1")->fetchColumn();
  check($logged >= 1, 'user changes are logged');
} finally {
  $pdo->exec("DELETE FROM admin_log WHERE target_id = 999001");
  $clean();
}

echo $failed ? "$failed check(s) failed\n" : "all checks passed\n";
exit($failed ? 1 : 0);
