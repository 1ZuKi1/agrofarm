<?php
/**
 * tests/php74_functions_test.php — the live server runs PHP 7.4. The syntax
 * check (php-parser in 7.4 mode) catches PHP 8 syntax, but not calls to
 * functions that only exist in PHP 8 — those parse fine and then crash with
 * "Call to undefined function" on the live site, usually in an error branch
 * nobody tries locally. This scans every .php file for them.
 *
 *   php tests/php74_functions_test.php
 *
 * Exits 1 and prints FAIL file:line for each hit. Not deployed.
 */

$root = dirname(__DIR__);
$php8Only = ['str_contains', 'str_starts_with', 'str_ends_with', 'array_is_list', 'get_debug_type', 'fdiv'];
$pattern = '/\b(' . implode('|', $php8Only) . ')\s*\(/';
$skip = ['/tools/', '/_archive/', '/_to_delete/', '/tests/php74_functions_test.php'];

$hits = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
  $path = $file->getPathname();
  if (substr($path, -4) !== '.php') continue;
  foreach ($skip as $s) if (strpos($path, $s) !== false) continue 2;
  foreach (file($path) as $n => $line) {
    if (preg_match($pattern, $line, $m)) {
      echo 'FAIL ' . substr($path, strlen($root) + 1) . ':' . ($n + 1) . '  ' . $m[1] . "() does not exist in PHP 7.4\n";
      $hits++;
    }
  }
}
echo $hits ? "$hits PHP 8-only call(s)\n" : "no PHP 8-only function calls\n";
exit($hits ? 1 : 0);
