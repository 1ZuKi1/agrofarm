<?php
/**
 * inc/i18n.php — reads lang/strings.php (EN / MN / 中文 side by side).
 *
 * The home page carries all three versions in the HTML as data-en / data-mn /
 * data-zh, and script.js's setLanguage() swaps them instantly in the browser.
 * These helpers print those attributes from the one strings file, so a text
 * is written once instead of three times in the markup:
 *
 *   <h2<?= ta('about.who-we-are') ?>><?= tx('about.who-we-are') ?></h2>
 *
 * ta() prints the three attributes, tx() the English text that shows before
 * script.js applies a saved language. A missing key is a bug in the page, so
 * it fails loudly in the server log and shows the key itself on the page.
 */

function strings(): array {
  static $s = null;
  if ($s === null) $s = require dirname(__DIR__) . '/lang/strings.php';
  return $s;
}

/** The EN/MN/ZH triple for a key. */
function str3(string $key): array {
  $s = strings();
  if (!isset($s[$key])) {
    error_log("i18n: missing key '$key'");
    return ['en' => $key, 'mn' => $key, 'zh' => $key];
  }
  return $s[$key];
}

/** data-en / data-mn / data-zh attributes for a key, with a leading space. */
function ta(string $key): string {
  $t = str3($key);
  $esc = function ($v) { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
  return sprintf(' data-en="%s" data-mn="%s" data-zh="%s"', $esc($t['en']), $esc($t['mn']), $esc($t['zh']));
}

/** The English text for a key, as HTML (may contain <em>, <strong>, <br>). */
function tx(string $key): string {
  return str3($key)['en'];
}

/** The Mongolian text for a key — what every Mongolian-only page shows. */
function tmn(string $key): string {
  return str3($key)['mn'];
}
