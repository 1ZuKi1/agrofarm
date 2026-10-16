<?php
/**
 * news.php — /news, the news archive (posts from news-data.json, drawn by
 * script.js) and the Facebook feed. Mongolian only. Was news.html; .htaccess
 * sends /news.html and /news here.
 */

require __DIR__ . '/inc/layout.php';

page_start([
  'title' => 'Мэдээ — Нүүдэлчин Агро Ферм',
  'desc' => 'Нүүдэлчин Агро Ферм ХХК-ийн мэдээ, мэдээлэл.',
  'canonical' => '/news',
  'csp' => 'basic', // embeds the Facebook feed
  'active' => 'news',
  'head' => <<<'HTML'
<style>
/* Facebook feed section */
.fb-feed{ padding:1rem var(--gutter) 5rem; text-align:center; }
.fb-feed__head{ max-width:720px; margin:0 auto 2rem; }
.fb-feed__note{ color:var(--ink-soft); font-size:.95rem; margin-top:.6rem; }
.fb-feed__embed{ display:flex; justify-content:center; text-align:left; }
.fb-feed__embed .sk-ww-facebook-page-posts{ width:100%; max-width:1300px; text-align:left; }
</style>

HTML,
]);
?>

<!-- ============ NEWS ARCHIVE ============ -->
<section class="news-archive">
  <div class="news-archive__head">
    <h1>Мэдээ &amp; <em>Мэдээлэл</em></h1>
  </div>
  <div class="news-archive__list" id="newsArchiveList">
    <p class="news-archive__empty" id="archiveEmpty">Мэдээ байхгүй байна.</p>
    <div class="news__loader" id="archiveLoader">Ачааллаж байна…</div>
  </div>
</section>

<!-- ============ FACEBOOK FEED ============ -->
<section class="fb-feed">
  <div class="fb-feed__head">
    <h2>Манай <em>Фэйсбүүк</em></h2>
    <p class="fb-feed__note">Албан ёсны Фэйсбүүк хуудаснаас шууд орж ирэх шинэ мэдээлэл.</p>
  </div>
  <div class="fb-feed__embed">
    <div class="sk-ww-facebook-page-posts" data-embed-id="25699003"></div>
  </div>
</section>

<?php page_end([
  'scripts' => <<<'HTML'
<script>
/* Archive page: load and render all posts */
(async function() {
  const list = document.getElementById("newsArchiveList");
  const loader = document.getElementById("archiveLoader");
  const empty = document.getElementById("archiveEmpty");
  if (!list) return;

  const posts = await fetchNews();
  if (loader) loader.remove();

  if (!posts.length) {
    if (empty) empty.style.display = "block";
    return;
  }

  if (empty) empty.style.display = "none";

  posts.sort((a, b) => new Date(b.date) - new Date(a.date));

  posts.forEach((post) => {
    const html = renderNewsPost(post);
    const wrapper = document.createElement("div");
    wrapper.innerHTML = html;
    const el = wrapper.firstElementChild;
    if (el) list.appendChild(el);
  });
})();
</script>

<!-- SociableKit — renders the Facebook Page feed in the section above. -->
<script src="https://widgets.sociablekit.com/facebook-page-posts/widget.js" defer></script>
HTML,
]);
