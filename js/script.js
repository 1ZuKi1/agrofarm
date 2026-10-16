/* ==========================================================================
   NUUDELCHIN AGRO FARM — interaction layer
   Everything runs off two cheap primitives:
     1. IntersectionObserver  → scroll-spy for the header links
     2. rAF-throttled scroll  → nav state, back-to-top button, mobile action bar,
                                 the sideways timeline
   No libraries.
   ========================================================================== */

(() => {
  "use strict";
  const reduceMotion = matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Footer year ---------- */
  document.getElementById("year").textContent = new Date().getFullYear();

  /* ---------- Language toggle (EN / MN / 中文) ----------
     Every translatable element carries data-mn + data-en; the home page also
     carries data-zh. We swap with innerHTML (not textContent) because several
     headings/paragraphs contain inline markup (<br>, <em>, <strong>) that must
     survive the switch. All strings are static author-authored attributes — no
     user input flows through innerHTML.

     Only the home page is multilingual, and only it has #langToggle (see
     inc/layout.php). Every other page is Mongolian only: no toggle, so none
     of this runs there and <html lang="mn"> stays as the server sent it —
     while the saved preference is left alone for the next visit home. */
  const langToggle = document.getElementById("langToggle");
  // Mongolian is the default and comes first: the page arrives in Mongolian
  // and the toggle cycles MN → EN → 中文.
  const LANGS = document.querySelector("[data-zh]") ? ["mn", "en", "zh"] : ["mn", "en"];
  const LANG_LABEL = { en: "EN", mn: "MN", zh: "中文" };
  // Each label is written in the language it switches to, so a screen-reader
  // user hears it in a language they can understand.
  const LANG_ARIA = { en: "Switch to English", mn: "Монгол хэл рүү шилжих", zh: "切换到中文" };
  const nextLang = (lang) => LANGS[(LANGS.indexOf(lang) + 1) % LANGS.length];
  let currentLang = langToggle ? (localStorage.getItem("lang") || "mn") : document.documentElement.lang;
  if (langToggle && !LANGS.includes(currentLang)) currentLang = "mn";
  function setLanguage(lang) {
    currentLang = lang;
    localStorage.setItem("lang", lang);
    document.querySelectorAll("[data-mn][data-en]").forEach((el) => {
      // Fall back to English for any element the page hasn't translated yet
      el.innerHTML = el.getAttribute(`data-${lang}`) || el.getAttribute("data-en");
    });
    // Re-render the stat numbers with language-aware prefix/suffix (data-suffix-en / -mn / -zh)
    const Lang = lang[0].toUpperCase() + lang.slice(1);
    document.querySelectorAll(".stat__num").forEach((el) => {
      const target = +el.dataset.count;
      const prefix = el.dataset[`prefix${Lang}`] || el.dataset.prefix || "";
      const suffix = el.dataset[`suffix${Lang}`] || el.dataset.suffix || "";
      el.textContent = prefix + target.toLocaleString() + suffix;
    });
    document.documentElement.lang = lang;
    // The button shows the language you would switch TO
    langToggle.textContent = LANG_LABEL[nextLang(lang)];
    langToggle.setAttribute("aria-label", LANG_ARIA[nextLang(lang)]);
  }
  if (langToggle) {
    langToggle.addEventListener("click", () => setLanguage(nextLang(currentLang)));
    if (currentLang !== "mn") setLanguage(currentLang); // apply a saved English/Chinese choice on load
  }

  /* ---------- Cart (shared across every page via localStorage) ----------
     Holds {variantId, quantity} only — never a price. Display price is always
     looked up live from shop-data.php's response, matching the site's
     "price is server-authoritative" principle end to end. */
  const CART_KEY = "naf_cart";

  function getCart() {
    try {
      const raw = JSON.parse(localStorage.getItem(CART_KEY) || "[]");
      return Array.isArray(raw) ? raw : [];
    } catch {
      return [];
    }
  }

  function setCart(items) {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
    updateCartBadge();
  }

  function addToCart(variantId, quantity) {
    const cart = getCart();
    const existing = cart.find((item) => Number(item.variantId) === Number(variantId));
    if (existing) {
      existing.quantity += quantity;
    } else {
      cart.push({ variantId, quantity });
    }
    setCart(cart);
  }

  function updateCartBadge() {
    const badge = document.getElementById("cartBadge");
    if (!badge) return;
    // One basket in the UI, two stores underneath: priced lines live in
    // naf_cart, equipment quote lines in naf_inquiry (owned by catalog.js).
    // The badge shows the sum so the buyer sees a single number — read here
    // directly, so it is right on pages that don't load catalog.js too.
    let quotes = [];
    try { quotes = JSON.parse(localStorage.getItem("naf_inquiry") || "[]"); } catch { quotes = []; }
    const count = getCart().reduce((sum, item) => sum + item.quantity, 0)
      + (Array.isArray(quotes) ? quotes.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0) : 0);
    badge.textContent = String(count);
    badge.hidden = count === 0;
  }

  window.getCart = getCart;
  window.setCart = setCart;
  window.addToCart = addToCart;
  window.updateCartBadge = updateCartBadge;
  updateCartBadge(); // reflect existing cart state on every page load

  /* ---------- Homepage shop card: live starting price ----------
     #shopCardPrice itself carries no data-en/data-mn (so setLanguage()'s
     innerHTML sweep never touches it directly) — only its static fallback
     <span> does, until this fetch replaces it with a translatable "From"
     label span + a plain value span holding the fetched number. The label
     span still has data-en/data-mn, so it keeps translating correctly on
     every later toggle; the value span never does, so a toggle can never
     erase the fetched price back to the static fallback text. */
  const shopCardPrice = document.getElementById("shopCardPrice");
  if (shopCardPrice) {
    fetch("/api/shop-data.php").then((r) => r.json()).then((data) => {
      const variants = data.products?.[0]?.variants || [];
      if (!variants.length) return;
      const minPrice = Math.min(...variants.map((v) => v.price));
      const lang = document.documentElement.lang || "en";
      const label = { en: "From", mn: "Эхлэх үнэ", zh: "起价" };
      shopCardPrice.innerHTML = `<span data-en="${label.en}" data-mn="${label.mn}" data-zh="${label.zh}">${label[lang] || label.en}</span> <span id="shopCardPriceValue">${minPrice.toLocaleString()}₮</span>`;
    }).catch(() => {}); // silent — the static fallback text already covers this
  }

  /* ---------- Scroll-spy: underline the header link of the section in view ----------
     Only in-page links (href="#id") take part, so pages whose nav links
     point back to the homepage simply skip this. Every section is observed,
     not just the linked ones, so reaching an unlinked section (the hero, the
     stats) clears the underline. Fires when a section's boundary crosses the
     vertical middle of the viewport, so it works regardless of its height. */
  const spyLinks = [...document.querySelectorAll('.nav__links a[href^="#"]')];
  if (spyLinks.length) {
    const spyObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        spyLinks.forEach((a) => a.classList.toggle("is-active", a.getAttribute("href") === "#" + entry.target.id));
      });
    }, { rootMargin: "-50% 0px -50% 0px", threshold: 0 });
    document.querySelectorAll("main > section[id]").forEach((section) => spyObserver.observe(section));
  }

  /* ---------- Timeline: left to right as the page scrolls ----------
     The section is made as tall as the row is wide; its inner block is
     sticky, so it stays on screen while scrolling down moves the row left.
     Off when the visitor asks for reduced motion, or when the block would not
     fit the screen: the row then simply scrolls sideways (css/style.css). */
  const journey = document.getElementById("journey");
  const journeyPin = journey && journey.querySelector(".journey__pin");
  const journeyRow = journey && journey.querySelector(".timeline");
  const journeyTrack = journey && journey.querySelector(".timeline__track");
  let journeyDistance = 0;
  const moveJourney = () => {
    if (!journeyDistance) return;
    const p = Math.min(1, Math.max(0, -journey.getBoundingClientRect().top / journeyDistance));
    journeyTrack.style.transform = `translate3d(${-Math.round(p * journeyDistance)}px, 0, 0)`;
  };
  const measureJourney = () => {
    journey.classList.add("is-pinned");
    journeyTrack.style.transform = "";
    const pad = parseFloat(getComputedStyle(journeyRow).paddingLeft) || 0;
    journeyDistance = Math.max(0, journeyTrack.offsetWidth + 2 * pad - journeyRow.clientWidth);
    if (!journeyDistance || journeyPin.scrollHeight > journeyPin.clientHeight + 1) {
      journey.classList.remove("is-pinned");
      journey.style.height = "";
      journeyDistance = 0;
      return;
    }
    journey.style.height = `${journeyPin.offsetHeight + journeyDistance}px`;
    moveJourney();
  };
  if (journeyTrack && !reduceMotion) {
    addEventListener("resize", measureJourney);
    // The cards change size when the fonts arrive or the language changes.
    new ResizeObserver(measureJourney).observe(journeyTrack);
  }

  /* ---------- rAF-throttled scroll handler ----------
     A single passive listener sets a flag; the work happens at most once per
     frame. Handles: solid nav, FAB visibility, the mobile action bar and the
     timeline. */
  const nav = document.getElementById("nav");
  const fab = document.getElementById("fab");
  const mobilebar = document.getElementById("mobilebar");
  const farmsSection = document.getElementById("farms");
  // Pages without a #hero (every page but home) get nav--solid from
  // inc/layout.php since there's no dark hero for the nav to start transparent over —
  // the scroll-based toggle below must never touch that class there, or the
  // nav text flips to the hero-only cream color against a light page background.
  const hasHero = !!document.getElementById("hero");

  let ticking = false;
  const onScroll = () => {
    const y = scrollY;

    if (hasHero) nav.classList.toggle("nav--solid", y > 60);
    if (journeyDistance) moveJourney();
    if (fab) fab.classList.toggle("show", y > innerHeight * 0.8);

    // Sticky action bar only appears once the Farms section is reached
    if (mobilebar && farmsSection) {
      mobilebar.classList.toggle(
        "mobilebar--show",
        farmsSection.getBoundingClientRect().top <= innerHeight * 0.6
      );
    }

    ticking = false;
  };
  addEventListener("scroll", () => {
    if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
  }, { passive: true });
  onScroll();

  /* ---------- FAB: smooth scroll to top ---------- */
  if (fab) fab.addEventListener("click", () => scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" }));

  /* ---------- Mobile menu ---------- */
  const burger = document.getElementById("burger");
  const navLinks = document.getElementById("navLinks");
  burger.addEventListener("click", () => {
    const open = nav.classList.toggle("menu-open");
    burger.setAttribute("aria-expanded", open);
  });
  // Close the menu when a link is chosen
  navLinks.addEventListener("click", (e) => {
    if (e.target.closest("a")) {
      nav.classList.remove("menu-open");
      burger.setAttribute("aria-expanded", "false");
    }
  });

  /* ---------- Swipe to close the mobile menu ----------
     A horizontal swipe of 60px+ (dominantly horizontal) anywhere on the open
     menu dismisses it — matches the native drawer gesture users expect. */
  let touchX = 0, touchY = 0;
  navLinks.addEventListener("touchstart", (e) => {
    touchX = e.changedTouches[0].clientX;
    touchY = e.changedTouches[0].clientY;
  }, { passive: true });
  navLinks.addEventListener("touchend", (e) => {
    const dx = e.changedTouches[0].clientX - touchX;
    const dy = e.changedTouches[0].clientY - touchY;
    if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy)) {
      nav.classList.remove("menu-open");
      burger.setAttribute("aria-expanded", "false");
    }
  }, { passive: true });

  /* ---------- Escape closes the mobile menu ----------
     The other close paths (link tap, swipe, re-tapping the burger) all
     require a pointer/touch. Keyboard users need an exit too — Escape is
     the standard convention for a full-screen overlay like this one. */
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && nav.classList.contains("menu-open")) {
      nav.classList.remove("menu-open");
      burger.setAttribute("aria-expanded", "false");
      burger.focus();
    }
  });

  /* ========================================================================
     NEWS FEED — shared rendering for homepage + archive page
     ======================================================================== */

  /** Fetch posts from news-data.json. Returns the posts array (or [] on error). */
  async function fetchNews() {
    try {
      const res = await fetch("/news-data.json?v=" + Date.now());
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();
      return Array.isArray(data.posts) ? data.posts : [];
    } catch {
      return [];
    }
  }

  /** Return the active language ("en" or "mn") from the <html> lang attribute. */
  function newsLang() {
    return document.documentElement.lang || "en";
  }

  /**
   * Render a full news post (used on news.html archive page).
   * Returns an HTML string.
   */
  function renderNewsPost(post) {
    const lang = newsLang();
    const title = post.title?.[lang] || post.title?.en || "";
    const body = post.body?.[lang] || post.body?.en || "";
    const date = post.date ? new Date(post.date).toLocaleDateString(lang === "mn" ? "mn-MN" : "en-US", { year: "numeric", month: "long", day: "numeric" }) : "";
    const imgHtml = post.image ? `<img src="${post.image}" alt="" class="news__post-img" loading="lazy">` : "";

    return `
      <article class="news__post">
        ${imgHtml}
        <time class="news__date" datetime="${post.date}">${date}</time>
        <h2 class="news__post-title">${escHtml(title)}</h2>
        <div class="news__post-body">${escHtml(body).replace(/\n/g, "<br>")}</div>
      </article>
    `;
  }

  /** Render a short news card for the homepage. Returns an HTML string. */
  function renderNewsCard(post) {
    const lang = newsLang();
    const title = post.title?.[lang] || post.title?.en || "";
    const body = post.body?.[lang] || post.body?.en || "";
    const excerpt = body.length > 160 ? body.slice(0, 160).trimEnd() + "…" : body;
    const date = post.date ? new Date(post.date).toLocaleDateString(lang === "mn" ? "mn-MN" : lang === "zh" ? "zh-CN" : "en-US", { year: "numeric", month: "long", day: "numeric" }) : "";
    const imgHtml = post.image ? `<img src="${post.image}" alt="" class="news__card-img" loading="lazy">` : "";
    return `
      <a class="news__card" href="/news">
        ${imgHtml}
        <div class="news__card-body">
          <time class="news__date" datetime="${post.date}">${date}</time>
          <h3 class="news__card-title">${escHtml(title)}</h3>
          <p class="news__card-excerpt">${escHtml(excerpt)}</p>
        </div>
      </a>
    `;
  }

  /** Minimal HTML-escapes a string (safe for user-provided content). */
  function escHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  // Homepage: the three latest posts. The grid stays hidden when there are none.
  const newsGrid = document.getElementById("newsGrid");
  if (newsGrid) {
    fetchNews().then((posts) => {
      if (!posts.length) return;
      posts.sort((a, b) => new Date(b.date) - new Date(a.date));
      newsGrid.innerHTML = posts.slice(0, 3).map(renderNewsCard).join("");
      newsGrid.hidden = false;
    });
  }

  // Expose for news.html archive page
  window.fetchNews = fetchNews;
  window.renderNewsPost = renderNewsPost;
})();
