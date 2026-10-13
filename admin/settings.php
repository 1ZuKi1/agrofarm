<?php
/**
 * settings.php — /admin/settings: Тохиргоо (Админ only). Filled in with the
 * delivery fee, closed days and Хэрэглэгчид in a later step.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo, 'admin');

admin_page_start($pdo, $me, ['title' => 'Тохиргоо', 'active' => 'settings', 'sub' => 'Хүргэлтийн төлбөр, амралтын өдөр, хэрэглэгчид.']);
echo '<div class="ad-panel"><p class="ad-muted">Бэлтгэгдэж байна.</p></div>';
admin_page_end();
