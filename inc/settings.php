<?php
/**
 * inc/settings.php — the few values staff change in /admin/ → Тохиргоо,
 * kept in the `settings` table (sql/orders-schema-v4.sql) as text:
 *
 *   delivery_fee   whole tögrög, e.g. '8000'
 *   closed_days    JSON list of 'YYYY-MM-DD' days the office is shut
 *                  (Цагаан сар, Наадам…) — skipped like weekends
 *
 * Read through inc/fulfillment.php, which turns the text into a fee and a
 * date list and copes with a broken value. If the table does not exist yet
 * (the v4 SQL was not run on the server), settings_get() throws a clear
 * RuntimeException instead of a bare PDO error, so the log says what to do.
 */

function settings_get(PDO $pdo, string $key, string $default): string {
  try {
    $st = $pdo->prepare('SELECT v FROM settings WHERE k = ?');
    $st->execute([$key]);
  } catch (PDOException $e) {
    if ($e->getCode() === '42S02') { // table not found
      throw new RuntimeException('settings table missing — run sql/orders-schema-v4.sql', 0, $e);
    }
    throw $e;
  }
  $v = $st->fetchColumn();
  return $v === false ? $default : (string)$v;
}

function settings_set(PDO $pdo, string $key, string $value): void {
  $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
  $st->execute([$key, $value]);
}
