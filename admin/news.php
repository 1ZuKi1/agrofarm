<?php
/**
 * news.php — /admin/news: Мэдээ. Write and edit the site's news posts, then
 * "Сайт дээр нийтлэх" writes them all to news-data.json (admin/publish.php,
 * logged as news_publish with who did it).
 *
 * Posts are edited in the browser first (kept in localStorage as a draft
 * cache) and only reach the site when published — the same flow as before,
 * moved from the old one-page admin into admin/js/news.js.
 */

require dirname(__DIR__) . '/inc/admin-layout.php';

admin_session_start();
$pdo = naf_db();
$me = admin_require($pdo);

admin_page_start($pdo, $me, [
  'title' => 'Мэдээ',
  'active' => 'news',
  'sub' => 'Засварласан мэдээ "Сайт дээр нийтлэх" дарсны дараа сайтад гарна.',
  'actions' => '<div class="ad-btn-row" id="newsActions">'
    . '<button type="button" class="ad-btn" id="exportBtn">JSON татах</button>'
    . '<button type="button" class="ad-btn" id="addBtn">+ Шинэ мэдээ</button>'
    . '<button type="button" class="ad-btn ad-btn--primary" id="publishBtn">Сайт дээр нийтлэх</button>'
    . '</div>',
]);
?>
<p class="ad-flash" id="publishStatus" role="status" hidden></p>

<div id="newsListView">
  <div class="ad-filters">
    <div class="ad-field" style="flex:1 1 220px;max-width:340px">
      <label for="newsSearch" class="ad-sr">Мэдээ хайх</label>
      <input type="search" id="newsSearch" placeholder="Мэдээ хайх…">
    </div>
    <span class="ad-small ad-muted" id="newsCount" style="margin-left:auto"></span>
  </div>
  <div id="postList"><p class="ad-empty">Ачааллаж байна…</p></div>
</div>

<section class="ad-panel" id="editorScreen" aria-labelledby="editorTitle" hidden>
  <div class="ad-panel__head"><h2 id="editorTitle">Шинэ мэдээ</h2></div>
  <div class="ad-field" style="max-width:240px"><label for="postDate">Огноо</label><input type="date" id="postDate"></div>
  <div class="ad-form-row">
    <div class="ad-field"><label for="postTitleMn">Гарчиг (Монгол)</label><input type="text" id="postTitleMn" placeholder="Гарчиг"></div>
    <div class="ad-field"><label for="postTitleEn">Гарчиг (Англи)</label><input type="text" id="postTitleEn" placeholder="Title"></div>
  </div>
  <div class="ad-form-row">
    <div class="ad-field"><label for="postBodyMn">Агуулга (Монгол)</label><textarea id="postBodyMn" rows="9" placeholder="Мэдээг монголоор бичнэ үү…"></textarea></div>
    <div class="ad-field"><label for="postBodyEn">Агуулга (Англи)</label><textarea id="postBodyEn" rows="9" placeholder="Write the news article in English…"></textarea></div>
  </div>
  <div class="ad-field">
    <label for="postImageFile">Зураг (заавал биш)</label>
    <input type="file" id="postImageFile" accept="image/jpeg,image/png,image/webp,image/gif">
    <span class="ad-hint" id="uploadStatus" role="status" hidden></span>
  </div>
  <div class="ad-field">
    <label for="postImage">эсвэл зургийн хаяг</label>
    <input type="text" id="postImage" placeholder="img/news/photo.jpg эсвэл https://example.com/photo.jpg">
    <img id="imagePreview" class="ad-thumb" alt="Сонгосон зураг" hidden>
  </div>
  <p class="ad-flash ad-flash--err" id="editorError" role="alert" hidden></p>
  <div class="ad-form-actions ad-btn-row">
    <button type="button" class="ad-btn ad-btn--primary" id="saveBtn">Хадгалах</button>
    <button type="button" class="ad-btn" id="cancelBtn">Болих</button>
  </div>
</section>
<?php admin_page_end(['scripts' => ['/admin/js/news.js']]); ?>
