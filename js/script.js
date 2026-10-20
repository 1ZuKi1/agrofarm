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

  /* ---------- Timeline: a row that scrolls sideways ----------
     The page scrolls straight past it. The slider under the row moves it:
     the pill shows where you are and how much of the row is on screen; drag
     it or click the rail. Swiping and sideways trackpad scrolling work too. */
  const journey = document.getElementById("journey");
  const journeyRow = journey && journey.querySelector(".timeline");
  const journeyTrack = journey && journey.querySelector(".timeline__track");
  const tlSlider = journey && journey.querySelector(".tl-slider");
  const tlRail = tlSlider && tlSlider.querySelector(".tl-slider__rail");
  const tlThumb = tlSlider && tlSlider.querySelector(".tl-slider__thumb");
  const clamp01 = (n) => Math.min(1, Math.max(0, n));
  const paintSlider = () => {
    const max = journeyRow.scrollWidth - journeyRow.clientWidth;
    const on = max > 4;
    tlSlider.hidden = !on;
    journey.classList.toggle("has-slider", on);
    if (!on) return;
    const size = Math.max(0.12, journeyRow.clientWidth / journeyRow.scrollWidth);
    tlThumb.style.width = `${size * 100}%`;
    tlThumb.style.left = `${clamp01(journeyRow.scrollLeft / max) * (1 - size) * 100}%`;
  };
  const goJourney = (p) => {
    journeyRow.scrollLeft = p * (journeyRow.scrollWidth - journeyRow.clientWidth);
  };
  // After a drag, ease onto the nearest card instead of letting the
  // browser's snap jump there. The snap stays off (.is-dragging) until then.
  let settleAnim = 0;
  const stopSettle = () => { cancelAnimationFrame(settleAnim); settleAnim = 0; };
  const easeToCard = () => {
    stopSettle();
    const done = () => journey.classList.remove("is-dragging", "is-grabbing");
    const pad = parseFloat(getComputedStyle(journeyRow).paddingLeft) || 0;
    const max = journeyRow.scrollWidth - journeyRow.clientWidth;
    const rowLeft = journeyRow.getBoundingClientRect().left;
    const from = journeyRow.scrollLeft;
    let to = from, best = Infinity;
    journeyTrack.querySelectorAll(".tl").forEach((card) => {
      const x = Math.min(max, Math.max(0, card.getBoundingClientRect().left - rowLeft + from - pad));
      if (Math.abs(x - from) < best) { best = Math.abs(x - from); to = x; }
    });
    if (reduceMotion || Math.abs(to - from) < 1) { journeyRow.scrollLeft = to; done(); return; }
    // Slow and soft: starts gently, glides, lands gently (ease-in-out sine).
    const dur = Math.min(1600, 900 + Math.abs(to - from) * 3);
    const t0 = performance.now();
    const step = (now) => {
      const k = Math.min(1, (now - t0) / dur);
      journeyRow.scrollLeft = from + (to - from) * (0.5 - 0.5 * Math.cos(Math.PI * k));
      if (k < 1) settleAnim = requestAnimationFrame(step);
      else { settleAnim = 0; done(); }
    };
    settleAnim = requestAnimationFrame(step);
  };
  if (tlRail && journeyRow) {
    let grab = 0; // where on the pill it was picked up, in px
    const toP = (e) => {
      const r = tlRail.getBoundingClientRect();
      const t = tlThumb.offsetWidth;
      return clamp01((e.clientX - r.left - grab) / Math.max(1, r.width - t));
    };
    tlRail.addEventListener("pointerdown", (e) => {
      stopSettle();
      const t = tlThumb.getBoundingClientRect();
      // On the pill: keep the grip point. On the rail: centre the pill there.
      grab = e.target === tlThumb ? e.clientX - t.left : t.width / 2;
      tlRail.setPointerCapture(e.pointerId);
      tlSlider.classList.add("is-dragging");
      journey.classList.add("is-dragging");
      goJourney(toP(e));
      e.preventDefault();
    });
    tlRail.addEventListener("pointermove", (e) => {
      if (tlRail.hasPointerCapture(e.pointerId)) goJourney(toP(e));
    });
    const drop = () => {
      if (!tlSlider.classList.contains("is-dragging")) return;
      tlSlider.classList.remove("is-dragging");
      easeToCard();
    };
    tlRail.addEventListener("pointerup", drop);
    tlRail.addEventListener("pointercancel", drop);
    journeyRow.addEventListener("scroll", paintSlider, { passive: true });
    addEventListener("resize", paintSlider);
    // The cards change size when the fonts arrive or the language changes.
    new ResizeObserver(paintSlider).observe(journeyTrack);
    paintSlider();
  }

  /* Grab the cards with the mouse and pull them sideways. Let go and the row
     keeps gliding at the speed it was thrown, easing to a stop. (Touch
     screens already swipe natively, so this is for mice only.) */
  if (journeyRow) {
    let down = false, moved = false, startX = 0, startLeft = 0;
    let lastX = 0, lastT = 0, v = 0, glide = 0;
    const stopGlide = () => { cancelAnimationFrame(glide); glide = 0; };
    journeyRow.addEventListener("pointerdown", (e) => {
      if (e.pointerType !== "mouse" || e.button !== 0 || !journey.classList.contains("has-slider")) return;
      stopGlide();
      stopSettle();
      down = true; moved = false;
      startX = lastX = e.clientX; startLeft = journeyRow.scrollLeft;
      lastT = performance.now(); v = 0;
      journey.classList.add("is-dragging", "is-grabbing");
      journeyRow.setPointerCapture(e.pointerId);
      e.preventDefault(); // no text selection while dragging
    });
    journeyRow.addEventListener("pointermove", (e) => {
      if (!down) return;
      const now = performance.now();
      const dt = Math.max(1, now - lastT);
      // Smoothed speed (px per ms), so the throw matches the hand's speed.
      v = 0.8 * ((e.clientX - lastX) / dt) + 0.2 * v;
      lastX = e.clientX; lastT = now;
      if (Math.abs(e.clientX - startX) > 3) moved = true;
      journeyRow.scrollLeft = startLeft - (e.clientX - startX);
    });
    const release = () => {
      if (!down) return;
      down = false;
      journey.classList.remove("is-grabbing");
      // A pause before letting go means no throw.
      if (reduceMotion || performance.now() - lastT > 80 || Math.abs(v) < 0.05) { easeToCard(); return; }
      let prev = performance.now();
      const step = (now) => {
        const dt = now - prev; prev = now;
        const before = journeyRow.scrollLeft;
        journeyRow.scrollLeft = before - v * dt;
        v *= Math.pow(0.94, dt / 16); // friction, the same at any frame rate
        // Slow enough (or at an end): hand over to the gentle settle onto a card.
        if (Math.abs(v) < 0.05 || journeyRow.scrollLeft === before) { glide = 0; easeToCard(); return; }
        glide = requestAnimationFrame(step);
      };
      glide = requestAnimationFrame(step);
    };
    journeyRow.addEventListener("pointerup", release);
    journeyRow.addEventListener("pointercancel", release);
    // A drag is not a click on whatever was under the mouse.
    journeyRow.addEventListener("click", (e) => { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
    // Sideways trackpad scrolling or the slider takes over from a glide in
    // progress (an ordinary up/down wheel just scrolls the page past).
    journeyRow.addEventListener("wheel", (e) => {
      if ((!glide && !settleAnim) || Math.abs(e.deltaX) <= Math.abs(e.deltaY)) return;
      stopGlide(); stopSettle();
      journey.classList.remove("is-dragging", "is-grabbing");
    }, { passive: true });
    if (tlRail) tlRail.addEventListener("pointerdown", () => { stopGlide(); });
  }

  /* ---------- rAF-throttled scroll handler ----------
     A single passive listener sets a flag; the work happens at most once per
     frame. Handles: solid nav, FAB visibility and the mobile action bar. */
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
    const imgHtml = post.image ? `<img src="${escHtml(post.image).replace(/"/g, "&quot;")}" alt="" class="news__post-img" loading="lazy">` : "";

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
    const imgHtml = post.image ? `<img src="${escHtml(post.image).replace(/"/g, "&quot;")}" alt="" class="news__card-img" loading="lazy">` : "";
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
