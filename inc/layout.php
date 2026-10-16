<?php
/**
 * inc/layout.php — the one page shell every public page is built from.
 *
 *   page_start([...])  prints <head>, the header + menu, and opens <main>.
 *   page_end([...])    closes <main>, prints the footer, the cart drawer on
 *                      shop pages, and the scripts.
 *
 * Before this file existed, index.html, news.html, shop.php and
 * catalog-lib.php each carried their own copy of the head, header and footer,
 * and the copies had drifted apart. The menu now lives in NAF_NAV below and
 * nowhere else.
 *
 * LANGUAGE. The home page is the only multilingual page (EN / MN / 中文). It
 * passes 'multilang' => true: the shared header and footer then carry
 * data-en / data-mn / data-zh for script.js's setLanguage(), and the language
 * toggle is shown. Every other page is Mongolian only — plain Mongolian text,
 * <html lang="mn">, no toggle, no data-* copies to keep in sync. Every text
 * that exists in several languages lives in lang/strings.php (inc/i18n.php).
 *
 * This file is included, never requested: .htaccess refuses /inc/ over HTTP.
 * PHP 7.4 on the live host — no str_contains, match, nullsafe ?->, etc.
 */

require_once __DIR__ . '/i18n.php';

/** Escape for HTML text and attribute contexts. */
function ce(?string $s): string {
  return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Content-Security-Policy: tells the browser what a page may load, so that
 * text injected by mistake could not run as a script or send data elsewhere.
 *   'strict'  shop, checkout and admin pages: scripts only from this site.
 *   'basic'   pages that embed the Facebook feed (home, news): that widget
 *             pulls scripts and frames from hosts we cannot test from here,
 *             so only the directives that cannot break it are sent.
 */
function naf_send_csp(string $mode = 'strict'): void {
  $base = "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
  $strict = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
          . "font-src https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; ";
  header('Content-Security-Policy: ' . ($mode === 'basic' ? '' : $strict) . $base);
}

/**
 * URL of a file in the site root, with its modification time appended as a
 * version (?v=…). A deploy that changes style.css or shop.js changes the URL,
 * so no browser keeps running yesterday's copy from its cache.
 */
function asset(string $path): string {
  $mtime = @filemtime(dirname(__DIR__) . $path);
  return $path . ($mtime ? '?v=' . $mtime : '');
}

/**
 * The main menu, in order. Columns: key, href on the home page, href on every
 * other page. Labels are 'nav.<key>' in lang/strings.php. On the home page
 * the section links stay bare #anchors, because script.js's scroll-spy
 * matches header links against "#<section id>".
 */
const NAF_NAV = [
  ['about',    '#about',    '/#about'],
  ['journey',  '#journey',  '/#journey'],
  ['farms',    '#farms',    '/#farms'],
  ['services', '#services', '/#services'],
  ['products', '#products', '/#products'],
  ['shop',     '/shop',     '/shop'],
  ['news',     '#news',     '/news'],
  ['contact',  '#contact',  '/#contact'],
];

/**
 * One piece of shared-chrome text from lang/strings.php. On the multilingual
 * home page: the data-en/data-mn/data-zh attributes plus the Mongolian default
 * (Mongolian is the default language; script.js applies a saved English or
 * Chinese choice after load). On every other page: just the Mongolian text.
 * Returns [attributes, inner HTML].
 */
function chrome_text(bool $multi, string $key): array {
  return $multi ? [ta($key), tx($key)] : ['', tmn($key)];
}

/** Print a page_start/page_end extra: an HTML string, or a function that prints. */
function layout_extra($x): void {
  if ($x instanceof Closure) $x();
  elseif (is_string($x)) echo $x;
}

/**
 * Options:
 *   title       <title> text (required)
 *   title_key   lang/strings.php key that translates the <title> on the
 *               multilingual home page (setLanguage() swaps it like any text)
 *   desc        meta description — the line search engines show under the result
 *   canonical   path of the page's one official address, e.g. '/shop'
 *   multilang   true on the home page only (see the file comment)
 *   home        true on the home page: transparent header over the hero,
 *               menu links as #anchors
 *   active      NAF_NAV key of the current section ('shop', 'news') — marked
 *               is-current and aria-current="page"
 *   csp         'strict' (default) or 'basic' — see naf_send_csp()
 *   head        extra HTML for <head> (page-only <style>, preloads)
 *   body_start  extra HTML before the header
 *   after_nav   extra HTML between the header and <main>
 * The three extras take an HTML string or a function that prints it (use a
 * function when the HTML calls ta()/tx()).
 */
function page_start(array $o): void {
  naf_send_csp($o['csp'] ?? 'strict');
  $multi  = !empty($o['multilang']);
  $home   = !empty($o['home']);
  $active = $o['active'] ?? null;
  $title  = ce($o['title']);
  $desc   = ce($o['desc'] ?? '');
  $canon  = isset($o['canonical']) ? ce('https://agrofarm.mn' . $o['canonical']) : '';
  $lang   = 'mn'; // every page is sent in Mongolian; home can switch after load
  $css    = ce(asset('/css/style.css'));

  echo "<!DOCTYPE html>\n<html lang=\"$lang\">\n<head>\n";
  echo "<meta charset=\"UTF-8\">\n";
  echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes\">\n";
  $titleAttrs = ($multi && isset($o['title_key'])) ? ta($o['title_key']) : '';
  echo "<title$titleAttrs>$title</title>\n";
  if ($desc !== '') echo "<meta name=\"description\" content=\"$desc\">\n";
  if ($canon !== '') echo "<link rel=\"canonical\" href=\"$canon\">\n";
  echo "<meta property=\"og:type\" content=\"website\">\n";
  echo "<meta property=\"og:title\" content=\"$title\">\n";
  if ($desc !== '') echo "<meta property=\"og:description\" content=\"$desc\">\n";
  if ($canon !== '') echo "<meta property=\"og:url\" content=\"$canon\">\n";
  // Cormorant Garamond (serif headlines) + Fira Sans (body) — full Latin and
  // Mongolian Cyrillic incl. ө/ү.
  echo <<<HTML
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&family=Fira+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

HTML;
  echo "<link rel=\"stylesheet\" href=\"$css\">\n";
  // After style.css, so a page's own <style> wins over it as before.
  layout_extra($o['head'] ?? '');
  echo "</head>\n<body>\n\n";
  // First thing a keyboard or screen-reader user reaches: jump past the menu.
  [$attrs, $text] = chrome_text($multi, 'a11y.skip');
  echo "<a class=\"skip-link\" href=\"#main\"$attrs>$text</a>\n";
  layout_extra($o['body_start'] ?? '');
  echo "\n";

  // ---- header ----
  $navClass = $home ? 'nav' : 'nav nav--solid';
  $brandHref = $home ? '#hero' : '/#hero';
  echo "<header class=\"$navClass\" id=\"nav\">\n";
  echo "  <a href=\"$brandHref\" class=\"nav__brand\" aria-label=\"Nuudelchin Agro Farm — Home\">\n";
  echo "    <span class=\"nav__mark\">\n      <img src=\"/img/naf_logo_full.svg\" alt=\"\" width=\"320\" height=\"160\">\n    </span>\n  </a>\n";
  echo "  <nav class=\"nav__links\" id=\"navLinks\" aria-label=\"Main\">\n";
  foreach (NAF_NAV as [$key, $hrefHome, $href]) {
    [$attrs, $text] = chrome_text($multi, 'nav.' . $key);
    $cur = ($key === $active) ? ' class="is-current" aria-current="page"' : '';
    printf("    <a href=\"%s\"%s%s>%s</a>\n", ce($home ? $hrefHome : $href), $cur, $attrs, $text);
  }
  echo "  </nav>\n";
  [$attrs, $text] = chrome_text($multi, 'nav.cta');
  printf("  <a href=\"%s\" class=\"btn nav__cta\"%s>%s</a>\n", $home ? '#contact' : '/#contact', $attrs, $text);
  // The basket. On shop pages shop.js turns this link into a drawer toggle;
  // anywhere else it simply goes to the shop with the drawer open.
  $cartLabel = 'Сагс';
  echo <<<HTML
  <a href="/shop#cart" id="cartToggle" class="cart-toggle" aria-label="$cartLabel">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="22" height="22"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    <span class="cart-toggle__badge" id="cartBadge" hidden>0</span>
  </a>

HTML;
  if ($multi) {
    // Shows the language you would switch TO (MN → EN → 中文 → MN);
    // script.js keeps it current.
    echo "  <button class=\"lang-toggle\" id=\"langToggle\" aria-label=\"Switch to English\">EN</button>\n";
  }
  $menuLabel = 'Цэс нээх';
  echo <<<HTML
  <button class="nav__burger" id="burger" aria-label="$menuLabel" aria-expanded="false" aria-controls="navLinks">
    <span></span><span></span><span></span>
  </button>
</header>

HTML;
  layout_extra($o['after_nav'] ?? '');
  // tabindex="-1" so the skip link moves keyboard focus here, not just the view.
  echo "\n<main id=\"main\" tabindex=\"-1\">\n";
}

/** The two-line site footer. The home page prints it inside its contact section. */
function site_footer(bool $multi = false): void {
  [$attrs, $text] = chrome_text($multi, 'footer.tagline');
  echo <<<HTML
<footer class="footer">
  <p>© <span id="year"></span> Nuudelchin Agro Farm LLC · Нүүдэлчин Агро Ферм ХХК · agrofarm.mn</p>
  <p$attrs>$text</p>
</footer>

HTML;
}

/**
 * Options:
 *   footer      false when the page prints site_footer() itself (home)
 *   shop        true on every /shop page: adds the basket drawer and loads
 *               shop.js (priced items, checkout, QPay) + catalog.js
 *               (equipment quote list, gallery)
 *   after_main  extra HTML after </main> (home: scroll-to-top, mobile bar)
 *   scripts     extra HTML after script.js (page-only scripts, widgets)
 */
function page_end(array $o = []): void {
  echo "\n</main>\n\n";
  layout_extra($o['after_main'] ?? '');
  if (!empty($o['shop'])) {
    // One basket, two kinds of line: priced items (shop.js, paid by QPay) and
    // equipment quote requests (catalog.js, sent as an inquiry).
    echo <<<HTML
<div class="cart-drawer" id="cartDrawer" inert>
  <div class="cart-drawer__backdrop" id="cartDrawerBackdrop"></div>
  <div class="cart-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="cartDrawerTitle">
    <div class="cart-drawer__head">
      <h2 id="cartDrawerTitle">Сагс</h2>
      <button type="button" id="cartDrawerClose" aria-label="Хаах">✕</button>
    </div>
    <div class="cart-drawer__items" id="cartDrawerItems"></div>
    <div class="cart-drawer__footer">
      <p class="cart-drawer__total" id="cartDrawerTotal"></p>
      <button type="button" class="btn btn--gold" id="cartCheckoutBtn" disabled>Захиалга үргэлжлүүлэх</button>
    </div>
  </div>
</div>

HTML;
  }
  if (!isset($o['footer']) || $o['footer']) site_footer();
  printf("\n<script src=\"%s\"></script>\n", ce(asset('/js/script.js')));
  if (!empty($o['shop'])) {
    printf("<script src=\"%s\"></script>\n", ce(asset('/js/shop.js')));
    printf("<script src=\"%s\"></script>\n", ce(asset('/js/catalog.js')));
  }
  layout_extra($o['scripts'] ?? '');
  echo "</body>\n</html>\n";
}
