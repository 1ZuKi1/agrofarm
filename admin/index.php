<?php
/**
 * index.php — /admin/: Нүүр, "what to do now". (Filled in with today's and
 * tomorrow's jobs, quote requests, low stock and sales in the next step.)
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo);

$first = preg_split('/\s+/u', trim($me['name']))[0] ?? $me['name'];
$today = fulfillment_now();
admin_page_start($pdo, $me, [
  'title' => 'Сайн байна уу, ' . $first,
  'active' => 'home',
  'sub' => fulfillment_date_label($today->format('Y-m-d')) . ' · ' . admin_role_label($me['role']),
]);
?>
<div class="ad-panel"><p class="ad-muted">Нүүр хуудас бэлтгэгдэж байна.</p></div>
<?php admin_page_end(); ?>
