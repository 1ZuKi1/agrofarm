<?php
/**
 * db.php — shared MySQL connection (PDO) for the shop feature.
 *
 * Credentials live in config.php (gitignored), never here.
 */

require_once __DIR__ . '/config.php';

function naf_db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = 'mysql:host=' . NAF_DB_HOST . ';dbname=' . NAF_DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, NAF_DB_USER, NAF_DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  }
  return $pdo;
}
