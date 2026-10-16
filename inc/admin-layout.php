<?php
/**
 * inc/admin-layout.php — the frame every /admin/ page is drawn in, after the
 * МОХ team workspace: a dark green sidebar (logo, menu with counters, help,
 * the person and logout), and the page itself on cream.
 *
 *   admin_page_start($pdo, $me, ['title', 'active', 'sub', 'head'])
 *   admin_page_end(['scripts' => ['/admin/js/x.js', …]])
 *   admin_flash('ok'|'err', text)   shown once, at the top of the next page
 *   admin_redirect('/admin/…')      303 — every form posts, then redirects
 *
 * Phones (≤ 900px): the sidebar becomes a panel opened by the "Цэс" button
 * (admin/js/admin-common.js). Pages are never cached: they show live orders.
 */

require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/layout.php'; // ce(), asset()
require_once __DIR__ . '/admin-data.php'; // admin_counts() for the menu badges

/** The menu, in order: key, label, address, icon, who sees it. */
const ADMIN_NAV = [
  ['home',     'Нүүр',          '/admin/',         'home',     'staff'],
  ['orders',   'Захиалга',      '/admin/orders',   'orders',   'staff'],
  ['quotes',   'Үнийн санал',   '/admin/quotes',   'inbox',    'staff'],
  ['products', 'Бүтээгдэхүүн',  '/admin/products', 'box',      'staff'],
  ['news',     'Мэдээ',         '/admin/news',     'news',     'staff'],
  ['settings', 'Тохиргоо',      '/admin/settings', 'settings', 'admin'],
];

function admin_flash(string $type, string $text): void {
  $_SESSION['admin_flash'] = ['type' => $type === 'err' ? 'err' : 'ok', 'text' => $text];
}

function admin_redirect(string $path): void {
  header('Location: ' . $path, true, 303);
  exit;
}

/** Inline icons, 20px, stroke = currentColor (the same style as MNSA's). */
function ad_icon(string $name, string $class = ''): string {
  $p = [
    'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
    'orders'   => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 12.2a1.6 1.6 0 0 0 1.6 1.3h8.7a1.6 1.6 0 0 0 1.6-1.2L21 7H6"/>',
    'inbox'    => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5.5 5h13L21 13v6H3v-6z"/>',
    'box'      => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
    'news'     => '<path d="M4 4h13v16H6a2 2 0 0 1-2-2z"/><path d="M17 8h3v10a2 2 0 0 1-2 2"/><path d="M8 8h5M8 12h5M8 16h3"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.2a2.5 2.5 0 0 1 4.8 1c0 1.7-2.4 2.2-2.4 3.6"/><path d="M12 17.2h.01"/>',
    'external' => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
    'logout'   => '<path d="M9 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
    'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
    'truck'    => '<path d="M1 4h13v12H1z"/><path d="M14 8h4.5L22 11.5V16h-8z"/><circle cx="5.5" cy="18" r="2"/><circle cx="17.5" cy="18" r="2"/>',
    'store'    => '<path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/>',
    'alert'    => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
    'check'    => '<path d="m5 12 4.5 4.5L19 7"/>',
    'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'mail'     => '<path d="M3 5h18v14H3z"/><path d="m3 6 9 7 9-7"/>',
    'calendar' => '<path d="M4 5h16v16H4z"/><path d="M4 10h16M9 3v4M15 3v4"/>',
    'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
    'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  ];
  return '<svg class="ad-ic' . ($class ? ' ' . $class : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    . ($p[$name] ?? '') . '</svg>';
}

/** "Бат Дорж" → "БД"; one word → its first two letters. */
function admin_initials(string $name): string {
  $parts = preg_split('/\s+/u', trim($name)) ?: [];
  if (count($parts) >= 2) return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
  return mb_strtoupper(mb_substr($name, 0, 2));
}

/** Menu counters: jobs to do, new quote requests (inc/admin-data.php once loaded). */
function admin_nav_counts(PDO $pdo): array {
  if (function_exists('admin_counts')) {
    try { return admin_counts($pdo); } catch (\Throwable $e) { error_log('admin counts: ' . $e->getMessage()); }
  }
  return ['todo' => 0, 'quotes' => 0];
}

function admin_head(string $title, string $extra = ''): void {
  header('Cache-Control: no-store');
  header('Content-Type: text/html; charset=utf-8');
  naf_send_csp('strict');
  echo '<!DOCTYPE html><html lang="mn"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<meta name="robots" content="noindex, nofollow">'
    . '<title>' . ce($title) . ' · NAF ажлын орчин</title>'
    . '<link rel="icon" href="/img/naf_logo_full.svg" type="image/svg+xml">'
    . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link rel="stylesheet" href="' . ce(asset('/admin/admin.css')) . '">'
    . $extra . '</head>';
}

function admin_page_start(PDO $pdo, array $me, array $o): void {
  $active = $o['active'] ?? '';
  $counts = admin_nav_counts($pdo);
  admin_head($o['title'], ($o['head'] ?? '') . '<meta name="csrf-token" content="' . ce(admin_csrf()) . '">');
  echo '<body class="ad">';
  echo '<a class="ad-skip" href="#main">Үндсэн агуулга руу шилжих</a>';

  // Phone top bar
  echo '<header class="ad-top">'
    . '<a class="ad-top__brand" href="/admin/" aria-label="Нүүр хуудас"><img src="/img/naf_logo_full.svg" alt="" width="84" height="42"></a>'
    . '<button type="button" class="ad-menu-btn" aria-expanded="false" aria-controls="adSide">' . ad_icon('menu') . '<span>Цэс</span></button>'
    . '</header>';

  // Sidebar
  echo '<aside class="ad-side" id="adSide" aria-label="Ажлын орчны цэс">';
  echo '<a class="ad-brand" href="/admin/"><span class="ad-brand__logo"><img src="/img/naf_logo_full.svg" alt="" width="64" height="32"></span>'
    . '<span class="ad-brand__text"><strong>NAF</strong><span>Ажлын орчин</span></span></a>';
  echo '<nav class="ad-nav" aria-label="Үндсэн цэс"><ul>';
  foreach (ADMIN_NAV as [$key, $label, $href, $icon, $who]) {
    if ($who === 'admin' && $me['role'] !== 'admin') continue;
    $badge = '';
    if ($key === 'orders' && $counts['todo'] > 0) $badge = '<span class="ad-badge" aria-label="' . (int)$counts['todo'] . ' хийх ажил">' . (int)$counts['todo'] . '</span>';
    if ($key === 'quotes' && $counts['quotes'] > 0) $badge = '<span class="ad-badge" aria-label="' . (int)$counts['quotes'] . ' шинэ хүсэлт">' . (int)$counts['quotes'] . '</span>';
    $cur = $key === $active ? ' aria-current="page"' : '';
    echo '<li><a href="' . $href . '"' . $cur . '>' . ad_icon($icon) . '<span>' . ce($label) . '</span>' . $badge . '</a></li>';
  }
  echo '</ul></nav>';
  echo '<div class="ad-side__foot">';
  echo '<a class="ad-side__link" href="/admin/help"' . ($active === 'help' ? ' aria-current="page"' : '') . '>' . ad_icon('help') . '<span>Тусламж</span></a>';
  echo '<a class="ad-side__link" href="/" target="_blank" rel="noopener">' . ad_icon('external') . '<span>Сайтыг үзэх</span><span class="ad-sr"> (шинэ цонхонд)</span></a>';
  echo '<div class="ad-me">'
    . '<a class="ad-me__who" href="/admin/account" title="Миний бүртгэл"' . ($active === 'account' ? ' aria-current="page"' : '') . '>'
    . '<span class="ad-avatar" aria-hidden="true">' . ce(admin_initials($me['name'])) . '</span>'
    . '<span class="ad-me__text"><strong>' . ce($me['name']) . '</strong><span>' . ce(admin_role_label($me['role'])) . '</span></span></a>'
    . '<form class="ad-logout" method="post" action="/admin/logout"><input type="hidden" name="csrf" value="' . ce(admin_csrf()) . '">'
    . '<button type="submit" title="Гарах" aria-label="Гарах">' . ad_icon('logout') . '</button></form>'
    . '</div>';
  echo '</div></aside>';
  echo '<div class="ad-scrim" hidden></div>';

  // Page
  echo '<main class="ad-main" id="main" tabindex="-1"><div class="ad-wrap">';
  $flash = $_SESSION['admin_flash'] ?? null;
  unset($_SESSION['admin_flash']);
  if ($flash) {
    echo '<div class="ad-flash ad-flash--' . $flash['type'] . '" role="' . ($flash['type'] === 'err' ? 'alert' : 'status') . '">'
      . ad_icon($flash['type'] === 'err' ? 'alert' : 'check') . '<span>' . ce($flash['text']) . '</span></div>';
  }
  if (!empty($o['title'])) {
    echo '<header class="ad-head"><div><h1>' . ce($o['title']) . '</h1>'
      . (!empty($o['sub']) ? '<p class="ad-head__sub">' . ce($o['sub']) . '</p>' : '') . '</div>'
      . ($o['actions'] ?? '') . '</header>';
  }
}

function admin_page_end(array $o = []): void {
  echo '</div></main>';
  echo '<script src="' . ce(asset('/admin/js/admin-common.js')) . '"></script>';
  foreach ($o['scripts'] ?? [] as $src) echo '<script src="' . ce(asset($src)) . '"></script>';
  echo '</body></html>';
}

/** The page shown when v6 hasn't been run (no admin_users table). */
function admin_schema_missing(): void {
  http_response_code(503);
  admin_head('Тохиргоо дутуу');
  echo '<body class="ad ad--bare"><main class="ad-auth" id="main"><div class="ad-auth__card">'
    . '<img class="ad-auth__logo" src="/img/naf_logo_full.svg" alt="NAF" width="120" height="60">'
    . '<h1>Өгөгдлийн сангийн шинэчлэл хийгдээгүй байна</h1>'
    . '<p>phpMyAdmin дээр <code>sql/orders-schema-v6.sql</code> файлыг ажиллуулсны дараа энэ хуудас нээгдэнэ.</p>'
    . '</div></main></body></html>';
  exit;
}
