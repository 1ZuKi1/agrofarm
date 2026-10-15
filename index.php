<?php
/**
 * index.php — the home page, and the only multilingual page (EN / MN / 中文).
 * Every translatable text is a key into lang/strings.php (EN / MN / 中文 side
 * by side); the page carries all three as data-en / data-mn / data-zh and
 * script.js's setLanguage() swaps them. The shared header and footer come from
 * inc/layout.php ('multilang' => true gives them the same attributes).
 * Was index.html; .htaccess sends /index.html here.
 */

require __DIR__ . '/inc/layout.php';
// ta('key') prints the data-en/data-mn/data-zh attributes, tx('key') the
// Mongolian text the page is sent with (the default language). The texts
// themselves live in lang/strings.php.

page_start([
  'title' => tmn('home.title'),
  'desc' => 'Нүүдэлчин Агро Ферм ХХК — Монголын кластер сүүний фермийн анхдагч. Төв аймгийн дөрвөн кластер фермд 1,620 үнээ маллаж, жил бүр дотоодын сүүний үйлдвэрүүдэд 4–5 сая литр сүү нийлүүлдэг.',
  'title_key' => 'home.title',
  'canonical' => '/',
  'multilang' => true,
  'home' => true,
  'head' => <<<'HTML'
<link rel="preload" as="image" href="/img/hero-cattle-arrival.jpg" fetchpriority="high">
<style>
/* Homepage: Facebook feed nested inside the News section */
.fb-home__embed{ position:relative; max-width:1100px; margin:1.5rem auto 0; max-height:950px; overflow:hidden; text-align:left; }
.fb-home__embed .sk-ww-facebook-page-posts{ width:100%; text-align:left; }
.fb-home__embed::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:340px; background:linear-gradient(to bottom, rgba(243,234,217,0) 0%, rgba(243,234,217,.92) 55%, rgba(243,234,217,1) 100%); pointer-events:none; }
.fb-home__more{ text-align:center; margin-top:1.75rem; }
</style>

HTML,
]);
?>

<!-- ============ HERO ============ -->
<section class="hero" id="hero">
  <div class="hero__bg"></div>
  <div class="hero__veil"></div>
  <div class="hero__inner">
    <p class="hero__kicker"<?= ta('hero.est-2015') ?>><?= tx('hero.est-2015') ?></p>
    <h1 class="hero__title">
      <span class="line"><span<?= ta('hero.from-the') ?>><?= tx('hero.from-the') ?></span></span>
      <span class="line"><span<?= ta('hero.mongolian-steppe') ?>><?= tx('hero.mongolian-steppe') ?></span></span>
      <span class="line"><span class="gold-ink"<?= ta('hero.to-every-table') ?>><?= tx('hero.to-every-table') ?></span></span>
    </h1>
    <p class="hero__slogan" lang="mn">«Сайн сайхны дээд, сааль сүүний дээж»</p>
    <p class="hero__lede"<?= ta('hero.mongolias-pioneer-of-cluster') ?>><?= tx('hero.mongolias-pioneer-of-cluster') ?></p>
    <div class="hero__actions">
      <a href="#journey" class="btn btn--gold"<?= ta('hero.our-journey') ?>><?= tx('hero.our-journey') ?></a>
      <a href="#services" class="btn btn--ghost"<?= ta('hero.what-we-do') ?>><?= tx('hero.what-we-do') ?></a>
    </div>
  </div>
  <a href="#stats" class="hero__scroll" aria-label="Scroll down">
    <svg viewBox="0 0 24 24" fill="none"><path d="M12 4v14m0 0 6-6m-6 6-6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </a>
</section>

<!-- ============ STATS ============ -->
<section class="stats" id="stats">
  <div class="stats__grid">
    <div class="stat">
      <span class="stat__num" data-count="1620">1,620</span>
      <span class="stat__label"<?= ta('stats.cows-across-four-cluster') ?>><?= tx('stats.cows-across-four-cluster') ?></span>
    </div>
    <div class="stat">
      <span class="stat__num" data-count="4" data-suffix-en="–5 M" data-suffix-mn="–5 сая" data-suffix-zh="–5 百万">4–5 сая</span>
      <span class="stat__label"<?= ta('stats.litres-of-milk-supplied') ?>><?= tx('stats.litres-of-milk-supplied') ?></span>
    </div>
    <div class="stat">
      <span class="stat__num" data-count="40">40</span>
      <span class="stat__label"<?= ta('stats.herder-households-across-the') ?>><?= tx('stats.herder-households-across-the') ?></span>
    </div>
    <div class="stat">
      <span class="stat__num" data-count="100" data-prefix="~">~100</span>
      <span class="stat__label"<?= ta('stats.permanent-jobs-created') ?>><?= tx('stats.permanent-jobs-created') ?></span>
    </div>
    <div class="stat">
      <span class="stat__num" data-count="450" data-suffix="+">450+</span>
      <span class="stat__label"<?= ta('stats.montbeliarde-heifers-imported') ?>><?= tx('stats.montbeliarde-heifers-imported') ?></span>
    </div>
  </div>
</section>

<!-- ============ ABOUT ============ -->
<section class="about" id="about">
  <div class="about__grid">
    <div class="about__head">
      <h2<?= ta('about.nomadic-heritage-modern-agriculture') ?>><?= tx('about.nomadic-heritage-modern-agriculture') ?></h2>
    </div>
    <div class="about__text">
      <p<?= ta('about.nuudelchin-agro-farm-llc') ?>><?= tx('about.nuudelchin-agro-farm-llc') ?></p>
      <div class="about__mv">
        <div class="mv">
          <h3<?= ta('about.mission') ?>><?= tx('about.mission') ?></h3>
          <p<?= ta('about.to-develop-farming-intensive') ?>><?= tx('about.to-develop-farming-intensive') ?></p>
        </div>
        <div class="mv">
          <h3<?= ta('about.vision') ?>><?= tx('about.vision') ?></h3>
          <p class="mv__lead"<?= ta('about.a-solution-for-rural') ?>><?= tx('about.a-solution-for-rural') ?></p>
          <p<?= ta('about.to-bring-mongolias-dairy') ?>><?= tx('about.to-bring-mongolias-dairy') ?></p>
        </div>
      </div>
    </div>
    <figure class="about__media">
      <div class="about__photo"></div>
      <figcaption<?= ta('about.first-cluster-farm-opening') ?>><?= tx('about.first-cluster-farm-opening') ?></figcaption>
      <div class="about__badge">
        <span class="about__badge-num">2017</span>
        <span<?= ta('about.first-cluster-farm-in') ?>><?= tx('about.first-cluster-farm-in') ?></span>
      </div>
    </figure>
  </div>
</section>

<!-- ============ JOURNEY / TIMELINE ============ -->
<section class="journey" id="journey">
  <div class="journey__head">
    <h2<?= ta('journey.a-journey-measured-in') ?>><?= tx('journey.a-journey-measured-in') ?></h2>
  </div>

  <div class="timeline" id="timeline">
    <!-- Gold spine: SVG path whose stroke draws in as you scroll (stroke-dashoffset linked to scroll progress in JS) -->
    <svg class="timeline__spine" viewBox="0 0 60 1600" preserveAspectRatio="none" aria-hidden="true">
      <path id="spinePath" d="M30 0 C 10 100, 50 200, 30 300 C 10 400, 50 500, 30 600 C 10 700, 50 800, 30 900 C 10 1000, 50 1100, 30 1200 C 10 1300, 50 1400, 30 1600" fill="none" stroke="var(--gold)" stroke-width="2"/>
    </svg>

    <article class="tl"><span class="tl__year">2013–14</span><div class="tl__card"><span class="tl__tag"<?= ta('journey.before-the-company') ?>><?= tx('journey.before-the-company') ?></span><h3<?= ta('journey.french-bloodlines') ?>><?= tx('journey.french-bloodlines') ?></h3><p<?= ta('journey.montbeliarde-heifers-imported') ?>><?= tx('journey.montbeliarde-heifers-imported') ?></p></div></article>
    <article class="tl"><span class="tl__year">2015</span><div class="tl__card tl__card--major"><h3<?= ta('journey.the-company-is-founded') ?>><?= tx('journey.the-company-is-founded') ?></h3><p<?= ta('journey.nuudelchin-agro-farm-llc') ?>><?= tx('journey.nuudelchin-agro-farm-llc') ?></p></div></article>
    <article class="tl"><span class="tl__year">2016</span><div class="tl__card"><h3<?= ta('journey.on-the-national-stage') ?>><?= tx('journey.on-the-national-stage') ?></h3><p<?= ta('journey.director-j-battugs-speaks') ?>><?= tx('journey.director-j-battugs-speaks') ?></p></div></article>
    <article class="tl"><span class="tl__year">2017</span><div class="tl__card tl__card--major"><h3<?= ta('journey.first-cluster-farm') ?>><?= tx('journey.first-cluster-farm') ?></h3><p<?= ta('journey.mongolias-first-400-cow') ?>><?= tx('journey.mongolias-first-400-cow') ?></p></div></article>
    <article class="tl"><span class="tl__year">2019–20</span><div class="tl__card"><h3<?= ta('journey.growing-the-herd') ?>><?= tx('journey.growing-the-herd') ?></h3><p<?= ta('journey.france-mongolia-animal-husbandry') ?>><?= tx('journey.france-mongolia-animal-husbandry') ?></p></div></article>
    <article class="tl"><span class="tl__year">2023</span><div class="tl__card"><h3<?= ta('journey.third-cluster') ?>><?= tx('journey.third-cluster') ?></h3><p<?= ta('journey.240-cow-cluster-farm') ?>><?= tx('journey.240-cow-cluster-farm') ?></p></div></article>
    <article class="tl"><span class="tl__year">2024</span><div class="tl__card tl__card--major"><h3<?= ta('journey.presidential-visit') ?>><?= tx('journey.presidential-visit') ?></h3><p<?= ta('journey.h-e-khurelsukh-ukhnaa') ?>><?= tx('journey.h-e-khurelsukh-ukhnaa') ?></p></div></article>
    <article class="tl"><span class="tl__year">2025</span><div class="tl__card tl__card--major"><h3<?= ta('journey.fourth-cluster-first-holsteins') ?>><?= tx('journey.fourth-cluster-first-holsteins') ?></h3><p<?= ta('journey.the-500-cow-bayan') ?>><?= tx('journey.the-500-cow-bayan') ?></p></div></article>
    <article class="tl"><span class="tl__year">2027</span><div class="tl__card"><h3<?= ta('journey.the-green-complex-opens') ?>><?= tx('journey.the-green-complex-opens') ?></h3><p<?= ta('journey.the-600-cow-green') ?>><?= tx('journey.the-600-cow-green') ?></p></div></article>
    <article class="tl"><span class="tl__year">2030</span><div class="tl__card tl__card--future"><h3<?= ta('journey.the-select-herd') ?>><?= tx('journey.the-select-herd') ?></h3><p<?= ta('journey.target-import-1-000') ?>><?= tx('journey.target-import-1-000') ?></p></div></article>
  </div>
</section>

<!-- ============ GREEN ENERGY ============ -->
<section class="green" id="green">
  <div class="green__inner">
    <div class="green__head">
      <h2<?= ta('green.from-the-barn-floor') ?>><?= tx('green.from-the-barn-floor') ?></h2>
      <p class="green__lede"<?= ta('green.the-next-step-is') ?>><?= tx('green.the-next-step-is') ?></p>
    </div>
    <div class="green__grid">
      <article class="gcard">
        <svg class="gcard__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z"/></svg>
        <h3<?= ta('green.energy-from-manure') ?>><?= tx('green.energy-from-manure') ?></h3>
        <p<?= ta('green.together-we-are-solving') ?>><?= tx('green.together-we-are-solving') ?></p>
      </article>
      <article class="gcard">
        <svg class="gcard__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21v-7"/><path d="M12 14c0-4 3-7 8-7 0 5-3.5 7-8 7Z"/><path d="M12 17c0-3-2.5-5.5-6.5-5.5C5.5 15.5 8.5 17 12 17Z"/></svg>
        <h3<?= ta('green.organic-fertiliser') ?>><?= tx('green.organic-fertiliser') ?></h3>
        <p<?= ta('green.cattle-slurry-is-processed') ?>><?= tx('green.cattle-slurry-is-processed') ?></p>
      </article>
      <article class="gcard">
        <svg class="gcard__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V11l7-5 7 5v10"/><path d="M12 21v-4.5"/><path d="M12 16.5c0-2 1.6-3.5 4-3.5 0 2.4-1.8 3.5-4 3.5Z"/></svg>
        <h3<?= ta('green.organic-farm') ?>><?= tx('green.organic-farm') ?></h3>
        <p<?= ta('green.at-ugtaaltsaidam-fodder-fertiliser') ?>><?= tx('green.at-ugtaaltsaidam-fodder-fertiliser') ?></p>
      </article>
    </div>
  </div>
</section>

<!-- ============ FARMS ============ -->
<section class="farms" id="farms">
  <div class="farms__layout">
  <div class="farms__grid">
    <article class="farm">
      <div class="farm__photo"><img src="img/farm-bayandelger.jpg" width="1200" height="675" loading="lazy" decoding="async" alt="Aerial view of the Bayandelger cluster farm at sunset"></div>
      <div class="farm__body">
        <span class="farm__count">400</span>
        <h3<?= ta('farms.bayandelger') ?>><?= tx('farms.bayandelger') ?></h3>
        <p class="farm__meta"<?= ta('farms.established-2017-mongolias-first') ?>><?= tx('farms.established-2017-mongolias-first') ?></p>
      </div>
    </article>
    <article class="farm">
      <div class="farm__photo"><img src="img/farm-argalant.jpg" width="1200" height="902" loading="lazy" decoding="async" alt="The Argalant cluster farm buildings with green roofs and landscaped grounds"></div>
      <div class="farm__body">
        <span class="farm__count">480</span>
        <h3<?= ta('farms.argalant') ?>><?= tx('farms.argalant') ?></h3>
        <p class="farm__meta"<?= ta('farms.established-2020-second-cluster') ?>><?= tx('farms.established-2020-second-cluster') ?></p>
      </div>
    </article>
    <article class="farm">
      <div class="farm__photo"><img src="img/farm-bayankhangai.jpg" width="1200" height="519" loading="lazy" decoding="async" alt="Aerial view of the Bayankhangai cluster farm laid out around a central circle"></div>
      <div class="farm__body">
        <span class="farm__count">240</span>
        <h3<?= ta('farms.bayankhangai') ?>><?= tx('farms.bayankhangai') ?></h3>
        <p class="farm__meta"<?= ta('farms.established-2023-third-cluster') ?>><?= tx('farms.established-2023-third-cluster') ?></p>
      </div>
    </article>
    <article class="farm">
      <div class="farm__photo"><img src="img/farm-bayan.jpg" width="1200" height="675" loading="lazy" decoding="async" alt="Aerial view of the Bayan cluster farm barns on open steppe"></div>
      <div class="farm__body">
        <span class="farm__count">500</span>
        <h3<?= ta('farms.bayan') ?>><?= tx('farms.bayan') ?></h3>
        <p class="farm__meta"<?= ta('farms.established-2025-our-largest') ?>><?= tx('farms.established-2025-our-largest') ?></p>
      </div>
    </article>
  </div>
  <div class="farms__head">
    <div class="farms__title">
      <h2<?= ta('farms.four-clusters-one-steppe') ?>><?= tx('farms.four-clusters-one-steppe') ?></h2>
    </div>
    <p class="farms__lede"<?= ta('farms.each-cluster-farm-unites') ?>><?= tx('farms.each-cluster-farm-unites') ?></p>
  </div>
  </div>

  <!-- Ugtaaltsaidam is a single integrated complex rather than a ten-household
       cluster, so it sits apart from the four cluster cards above. -->
  <div class="ugtaal">
    <figure class="ugtaal__media">
      <img src="img/ugtaal-complex.jpg" width="1600" height="813" loading="lazy" decoding="async" alt="Architectural visualization of the planned 600-cow Ugtaaltsaidam farm complex">
      <figcaption<?= ta('farms.architects-visualization') ?>><?= tx('farms.architects-visualization') ?></figcaption>
    </figure>
    <div class="ugtaal__text">
      <h3<?= ta('farms.ugtaaltsaidam') ?>><?= tx('farms.ugtaaltsaidam') ?></h3>
      <p<?= ta('farms.a-600-cow-green') ?>><?= tx('farms.a-600-cow-green') ?></p>
      <!-- Ugtaaltsaidam is open to participants: you join by owning a herd of
           20, 40 or 100 cows within the complex. The herd sizes are named here,
           so the foot band carries the CTA into #contact rather than a second
           listing of the same three numbers. -->
      <p<?= ta('farms.the-project-opens-the') ?>><?= tx('farms.the-project-opens-the') ?></p>
    </div>
    <!-- The facts and the CTA run full width under both columns, so the text
         column stays as tall as the render beside it and the render is never
         left floating in dead space. -->
    <div class="ugtaal__foot">
      <ul class="ugtaal__facts">
        <li><b>600</b><span<?= ta('farms.cows') ?>><?= tx('farms.cows') ?></span></li>
        <li><b>2027</b><span<?= ta('farms.enters-operation') ?>><?= tx('farms.enters-operation') ?></span></li>
        <li><b<?= ta('farms.t-v') ?>><?= tx('farms.t-v') ?></b><span<?= ta('farms.ugtaaltsaidam-soum') ?>><?= tx('farms.ugtaaltsaidam-soum') ?></span></li>
      </ul>
      <div class="ugtaal__cta">
        <a href="#contact" class="btn btn--gold"<?= ta('farms.become-a-participant') ?>><?= tx('farms.become-a-participant') ?></a>
      </div>
    </div>
  </div>
</section>

<!-- ============ SERVICES ============ -->
<section class="services" id="services">
  <div class="services__head">
    <h2<?= ta('services.we-build-and-run') ?>><?= tx('services.we-build-and-run') ?></h2>
  </div>
  <div class="services__grid">
    <article class="svc">
      <svg class="svc__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6"/></svg>
      <h3<?= ta('services.farm-construction') ?>><?= tx('services.farm-construction') ?></h3>
      <p<?= ta('services.planning-engineering-infrastructure-and') ?>><?= tx('services.planning-engineering-infrastructure-and') ?></p>
    </article>
    <article class="svc">
      <svg class="svc__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3m0 12v3m9-9h-3M6 12H3m14.5-5.5-2 2m-7 7-2 2m11 0-2-2m-7-7-2-2"/><circle cx="12" cy="12" r="3.5"/></svg>
      <h3<?= ta('services.consulting') ?>><?= tx('services.consulting') ?></h3>
      <p<?= ta('services.business-plans-investment-models') ?>><?= tx('services.business-plans-investment-models') ?></p>
    </article>
    <article class="svc svc--training">
      <svg class="svc__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V6a2 2 0 0 1 2-2h14v13H6a2 2 0 0 0-2 2Zm0 0a2 2 0 0 0 2 2h14"/><path d="M9 8h7M9 12h5"/></svg>
      <h3<?= ta('services.training') ?>><?= tx('services.training') ?></h3>
      <p<?= ta('services.professional-courses-and-hands') ?>><?= tx('services.professional-courses-and-hands') ?></p>
    </article>
    <article class="svc">
      <svg class="svc__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21c-4.5-2.5-8-5.5-8-10a8 8 0 0 1 16 0c0 4.5-3.5 7.5-8 10Z"/><path d="M12 3c2 3 2 7 0 10-2-3-2-7 0-10Z"/></svg>
      <h3<?= ta('services.feed-nutrition') ?>><?= tx('services.feed-nutrition') ?></h3>
      <p<?= ta('services.formulation-pasture-planning-and') ?>><?= tx('services.formulation-pasture-planning-and') ?></p>
    </article>
    <article class="svc">
      <svg class="svc__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6M10 3v4L6 15a4.5 4.5 0 0 0 4 6.5h4A4.5 4.5 0 0 0 18 15L14 7V3"/><path d="M7.5 13h9"/></svg>
      <h3<?= ta('services.milk-processing') ?>><?= tx('services.milk-processing') ?></h3>
      <p<?= ta('services.collection-cooling-processing-lines') ?>><?= tx('services.collection-cooling-processing-lines') ?></p>
    </article>
    <article class="svc">
      <svg class="svc__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="6" r="2.5"/><circle cx="19" cy="6" r="2.5"/><circle cx="12" cy="18" r="2.5"/><path d="M7 7.5 10.5 16M17 7.5 13.5 16M7.5 6h9"/></svg>
      <h3<?= ta('services.value-chain-development') ?>><?= tx('services.value-chain-development') ?></h3>
      <p<?= ta('services.connecting-production-to-market') ?>><?= tx('services.connecting-production-to-market') ?></p>
    </article>
  </div>
</section>

<!-- ============ PRODUCTS ============ -->
<section class="products" id="products">
  <div class="products__head">
    <h2<?= ta('products.what-the-steppe-provides') ?>><?= tx('products.what-the-steppe-provides') ?></h2>
  </div>
  <div class="products__grid">
    <article class="prod">
      <a href="/equipment" style="display:block;text-decoration:none;color:inherit">
      <div class="prod__media prod__media--equipment"></div>
      <div class="prod__body">
        <h3<?= ta('products.farm-equipment') ?>><?= tx('products.farm-equipment') ?></h3>
        <p<?= ta('products.milking-machines-cooling-tanks') ?>><?= tx('products.milking-machines-cooling-tanks') ?></p>
      </div>
    </a>
    </article>
    <article class="prod">
      <div class="prod__media prod__media--milk"></div>
      <div class="prod__body">
        <h3<?= ta('products.raw-milk-dairy') ?>><?= tx('products.raw-milk-dairy') ?></h3>
        <p<?= ta('products.healthy-safe-high-quality') ?>><?= tx('products.healthy-safe-high-quality') ?></p>
      </div>
    </article>
    <article class="prod">
      <div class="prod__media prod__media--livestock"></div>
      <div class="prod__body">
        <h3<?= ta('products.breeding-livestock') ?>><?= tx('products.breeding-livestock') ?></h3>
        <p<?= ta('products.montbeliarde-breed-from') ?>><?= tx('products.montbeliarde-breed-from') ?></p>
      </div>
    </article>
    <article class="prod">
      <a href="/shop" style="display:block;text-decoration:none;color:inherit">
        <div class="prod__media" id="shopCardMedia" style="background:var(--green)"></div>
        <div class="prod__body">
          <h3<?= ta('products.erdest-doloots') ?>><?= tx('products.erdest-doloots') ?></h3>
          <p id="shopCardPrice"><span<?= ta('products.mineral-lick-block-for') ?>><?= tx('products.mineral-lick-block-for') ?></span></p>
        </div>
      </a>
    </article>
  </div>
  <p class="products__cta"><a class="btn btn--gold" href="/shop"<?= ta('products.shop-cta') ?>><?= tx('products.shop-cta') ?></a></p>
</section>
<!-- ============ RECOGNITION & VOICES ============ -->
<section class="recognition" id="recognition">
  <div class="recognition__inner">
    <h2<?= ta('recognition.a-national-movement-a') ?>><?= tx('recognition.a-national-movement-a') ?></h2>
    <p class="recognition__body"<?= ta('recognition.on-december-10-2024') ?>><?= tx('recognition.on-december-10-2024') ?></p>

    <figure class="recognition__photo">
      <img src="img/president-visit-2024.jpg" width="1800" height="1200" loading="lazy" decoding="async" alt="The presidential delegation and farm staff walking through the Nuudelchin Agro Farm site during the December 2024 visit">
      <figcaption<?= ta('recognition.the-presidential-delegation-touring') ?>><?= tx('recognition.the-presidential-delegation-touring') ?></figcaption>
    </figure>

      <div class="quotes">
      <blockquote class="quote">
        <p<?= ta('recognition.if-this-incentive-is') ?>><?= tx('recognition.if-this-incentive-is') ?></p>
        <footer<?= ta('recognition.j-battugs-director-nuudelchin') ?>><?= tx('recognition.j-battugs-director-nuudelchin') ?></footer>
      </blockquote>
      <blockquote class="quote">
        <p<?= ta('recognition.we-aim-to-import') ?>><?= tx('recognition.we-aim-to-import') ?></p>
        <footer<?= ta('recognition.j-battugs-director-nuudelchin') ?>><?= tx('recognition.j-battugs-director-nuudelchin') ?></footer>
      </blockquote>
    </div>

    <div class="story">
      <h3<?= ta('recognition.a-herders-story') ?>><?= tx('recognition.a-herders-story') ?></h3>
      <p<?= ta('recognition.the-family-of-b') ?>><?= tx('recognition.the-family-of-b') ?></p>
      <p class="story__src"<?= ta('recognition.source-montsame-news-agency') ?>><?= tx('recognition.source-montsame-news-agency') ?></p>
    </div>

    <div class="partners">
      <h3<?= ta('recognition.partners') ?>><?= tx('recognition.partners') ?></h3>
      <ul>
        <li><strong<?= ta('recognition.khan-bank') ?>><?= tx('recognition.khan-bank') ?></strong><span<?= ta('recognition.financing-partner-for-cluster') ?>><?= tx('recognition.financing-partner-for-cluster') ?></span></li>
        <li><strong<?= ta('recognition.french-government') ?>><?= tx('recognition.french-government') ?></strong><span<?= ta('recognition.animal-husbandry-cooperation') ?>><?= tx('recognition.animal-husbandry-cooperation') ?></span></li>
        <li><strong<?= ta('recognition.mongolian-government') ?>><?= tx('recognition.mongolian-government') ?></strong><span<?= ta('recognition.food-supply-and-security') ?>><?= tx('recognition.food-supply-and-security') ?></span></li>
        <li><strong<?= ta('recognition.evajura') ?>><?= tx('recognition.evajura') ?></strong><span<?= ta('recognition.french-supplier-of-montbeliarde') ?>><?= tx('recognition.french-supplier-of-montbeliarde') ?></span></li>
        <li><strong<?= ta('recognition.coopex') ?>><?= tx('recognition.coopex') ?></strong><span<?= ta('recognition.french-breeding-cattle-export') ?>><?= tx('recognition.french-breeding-cattle-export') ?></span></li>
      </ul>
    </div>
  </div>
</section>

<!-- ============ NEWS (admin posts once news-data.json has any, then the Facebook feed) ============ -->
<section class="news" id="news">
  <div class="news__head">
    <h2<?= ta('news.news-updates') ?>><?= tx('news.news-updates') ?></h2>
  </div>

  <!-- Posts published from the admin panel (script.js fills this). It stays
       hidden until there is at least one post, and unlike the Facebook
       feed below it loads for readers who cannot reach Facebook. -->
  <div class="news__grid" id="newsGrid" hidden></div>

  <!-- Live Facebook feed -->
  <div class="fb-home__embed">
    <div class="sk-ww-facebook-page-posts" data-embed-id="25699003"></div>
  </div>

  <div class="fb-home__more">
    <a href="/news" class="btn btn--gold"<?= ta('news.see-all-news') ?>><?= tx('news.see-all-news') ?></a>
  </div>
</section>

<!-- ============ CONTACT ============ -->
<section class="contact" id="contact">
  <div class="contact__grid">
    <div class="contact__intro">
      <h2<?= ta('contact.lets-build-the-next') ?>><?= tx('contact.lets-build-the-next') ?></h2>
      <p lang="mn"<?= ta('contact.rural-development-solution') ?>><?= tx('contact.rural-development-solution') ?></p>
    </div>
    <ul class="contact__list">
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
        <div><span<?= ta('contact.email') ?>><?= tx('contact.email') ?></span><a href="mailto:info@agrofarm.mn">info@agrofarm.mn</a></div>
      </li>
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>
        <div><span<?= ta('contact.phone') ?>><?= tx('contact.phone') ?></span><a href="tel:+97670000789">+976 7000-0789</a></div>
      </li>
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21c-4.5-4.5-7-8-7-11a7 7 0 0 1 14 0c0 3-2.5 6.5-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
        <div><span<?= ta('contact.location') ?>><?= tx('contact.location') ?></span><p<?= ta('contact.room-402-tsoma-office') ?>><?= tx('contact.room-402-tsoma-office') ?></p></div>
      </li>
      <li>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9h3V5.5A13 13 0 0 0 14.5 5C11.5 5 10 6.8 10 10v2H7v4h3v6h4v-6h3l1-4h-4v-1.5c0-1 .3-1.5 1.5-1.5Z" transform="scale(0.9) translate(1.3 1.3)"/></svg>
        <div><span>Facebook</span><a href="https://www.facebook.com/61569851705695" target="_blank" rel="noopener">Нүүдэлчин Агро Ферм</a></div>
      </li>
    </ul>
  </div>
  <?php site_footer(true); ?>
</section>

<?php page_end([
  'footer' => false, // printed inside the contact section above
  'after_main' => function () { ?>

<!-- Scroll-to-top FAB -->
<button class="fab" id="fab" aria-label="Scroll to top">
  <svg viewBox="0 0 24 24" fill="none"><path d="M12 20V6m0 0-6 6m6-6 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>

<!-- Sticky mobile action bar -->
<div class="mobilebar" id="mobilebar">
  <a href="tel:+97670000789" class="mobilebar__call"<?= ta('mobilebar.call') ?>><?= tx('mobilebar.call') ?></a>
  <a href="#contact" class="mobilebar__quote"<?= ta('mobilebar.get-a-quote') ?>><?= tx('mobilebar.get-a-quote') ?></a>
</div>


<?php },
  'scripts' => <<<'HTML'
<!-- SociableKit — renders the Facebook teaser above -->
<script src="https://widgets.sociablekit.com/facebook-page-posts/widget.js" defer></script>

HTML,
]);
