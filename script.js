/* ==========================================================================
   NUUDELCHIN AGRO FARM — interaction layer
   Everything runs off two cheap primitives:
     1. IntersectionObserver  → reveals, counters, scroll-spy
     2. rAF-throttled scroll  → progress bar, parallax, timeline spine, nav state
   No libraries; all animation is compositor-friendly (transform/opacity only).
   ========================================================================== */

(() => {
  "use strict";
  const reduceMotion = matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Footer year ---------- */
  document.getElementById("year").textContent = new Date().getFullYear();

  /* ---------- Bilingual toggle (MN/EN) ----------
     Every translatable element carries data-mn + data-en. We swap with
     innerHTML (not textContent) because several headings/paragraphs contain
     inline markup (<br>, <em>, <strong>) that must survive the switch.
     All strings are static author-authored attributes — no user input flows
     through innerHTML. Preference persists in localStorage; default is EN. */
  const langToggle = document.getElementById("langToggle");
  let currentLang = localStorage.getItem("lang") || "en";
  function setLanguage(lang) {
    currentLang = lang;
    localStorage.setItem("lang", lang);
    document.querySelectorAll("[data-mn][data-en]").forEach((el) => {
      el.innerHTML = el.getAttribute(`data-${lang}`);
    });
    // Re-render counters with language-aware prefix/suffix
    document.querySelectorAll(".stat__num").forEach((el) => {
      const target = +el.dataset.count;
      const prefix = el.dataset[`prefix${lang === "mn" ? "Mn" : "En"}`] || el.dataset.prefix || "";
      const suffix = el.dataset[`suffix${lang === "mn" ? "Mn" : "En"}`] || el.dataset.suffix || "";
      el.textContent = prefix + target.toLocaleString() + suffix;
    });
    document.documentElement.lang = lang;
    // The button shows the language you would switch TO
    langToggle.textContent = lang === "mn" ? "EN" : "MN";
    langToggle.setAttribute("aria-label", lang === "mn" ? "Switch to English" : "Switch to Mongolian");
  }
  langToggle.addEventListener("click", () => setLanguage(currentLang === "mn" ? "en" : "mn"));
  if (currentLang !== "en") setLanguage(currentLang); // apply saved preference on load

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
    const count = getCart().reduce((sum, item) => sum + item.quantity, 0);
    badge.textContent = String(count);
    badge.hidden = count === 0;
  }

  window.getCart = getCart;
  window.setCart = setCart;
  window.addToCart = addToCart;
  window.updateCartBadge = updateCartBadge;
  updateCartBadge(); // reflect existing cart state on every page load

  /* ---------- Hero headline: staggered line-mask reveal ----------
     Each .line clips its inner span; adding .in slides the span up.
     150 ms stagger per line gives the "typeset" feel. */
  document.querySelectorAll(".hero__title .line").forEach((line, i) => {
    setTimeout(() => line.classList.add("in"), 250 + i * 150);
  });

  /* ---------- Homepage shop card: live starting price ---------- */
  const shopCardPrice = document.getElementById("shopCardPrice");
  if (shopCardPrice) {
    fetch("shop-data.php").then((r) => r.json()).then((data) => {
      const variants = data.products?.[0]?.variants || [];
      if (!variants.length) return;
      const minPrice = Math.min(...variants.map((v) => v.price));
      shopCardPrice.textContent = `From ${minPrice.toLocaleString()}₮`;
    }).catch(() => {}); // silent — the static fallback text already covers this
  }

  /* ---------- Generic scroll reveal ----------
     One observer for every .reveal element. Siblings that enter together get
     an incremental transition-delay (--d) so grids cascade instead of popping
     in as a block. Elements are unobserved after first reveal (fire once). */
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add("in");
      revealObserver.unobserve(entry.target);
    });
  }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });

  document.querySelectorAll(".reveal").forEach((el) => {
    // Stagger within the same parent: index among .reveal siblings × 90ms
    const siblings = [...el.parentElement.querySelectorAll(":scope > .reveal")];
    el.style.setProperty("--d", `${(siblings.indexOf(el) > 0 ? siblings.indexOf(el) : 0) * 0.09}s`);
    revealObserver.observe(el);
  });

  /** Observe a dynamically-added .reveal element */
  function observeReveal(el) {
    const siblings = [...el.parentElement.querySelectorAll(":scope > .reveal")];
    el.style.setProperty("--d", `${(siblings.indexOf(el) > 0 ? siblings.indexOf(el) : 0) * 0.09}s`);
    revealObserver.observe(el);
  }

  /* ---------- Animated number counters ----------
     Counts 0 → data-count over 1.6s with ease-out cubic so the last digits
     settle slowly. Runs once when the stats band becomes visible. */
  const easeOut = (t) => 1 - Math.pow(1 - t, 3);
  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      counterObserver.unobserve(el);
      const target = +el.dataset.count;
      const prefix = el.dataset.prefix || "";
      const suffix = el.dataset[`suffix${currentLang === "mn" ? "Mn" : "En"}`] || el.dataset.suffix || "";
      if (reduceMotion) { el.textContent = prefix + target.toLocaleString() + suffix; return; }
      const t0 = performance.now();
      const tick = (now) => {
        const p = Math.min((now - t0) / 1600, 1);
        el.textContent = prefix + Math.round(easeOut(p) * target).toLocaleString() + suffix;
        if (p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  }, { threshold: 0.5 });
  document.querySelectorAll(".stat__num").forEach((el) => counterObserver.observe(el));

  /* ---------- Scroll-spy dots + dark-section inversion ----------
     #dots is homepage-only — shop.html and news.html have no such element,
     so skip this setup entirely there rather than throwing (a throw here
     would abort every later top-level statement in this IIFE, including the
     burger-menu wiring and the window.fetchNews/window.renderNewsPost
     assignments those pages depend on). */
  const dotsNav = document.getElementById("dots");
  if (dotsNav) {
    const dotLinks = [...dotsNav.querySelectorAll("a")];
    const darkSections = new Set(["hero", "farms", "recognition"]);
    // Fires when a section's boundary crosses the vertical middle of the
    // viewport, so it works regardless of how tall the section is (a
    // threshold-based ratio like 0.45 never reaches that fraction for
    // sections much taller than the viewport, e.g. Journey, Recognition).
    const spyObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const id = entry.target.id;
        dotLinks.forEach((a) => a.classList.toggle("is-active", a.dataset.spy === id));
        // Invert dot colors while a dark section fills the viewport
        dotsNav.classList.toggle("dots--light", darkSections.has(id));
        // Mirror active state in the header links
        document.querySelectorAll(".nav__links a").forEach((a) =>
          a.classList.toggle("is-active", a.getAttribute("href") === "#" + id));
      });
    }, { rootMargin: "-50% 0px -50% 0px", threshold: 0 });
    dotLinks.forEach((a) => {
      const section = document.getElementById(a.dataset.spy);
      if (section) spyObserver.observe(section);
    });
  }

  /* ---------- rAF-throttled scroll handler ----------
     A single passive listener sets a flag; the work happens at most once per
     frame. Handles: progress bar, solid nav, FAB visibility, hero parallax,
     and the self-drawing timeline spine. */
  const progressBar = document.getElementById("progressBar");
  const nav = document.getElementById("nav");
  const fab = document.getElementById("fab");
  const mobilebar = document.getElementById("mobilebar");
  const farmsSection = document.getElementById("farms");
  const heroBg = document.querySelector("[data-parallax]");
  const parallaxFactor = heroBg ? +heroBg.dataset.parallax : 0;
  const heroInner = document.querySelector(".hero__inner");
  // Pages without a #hero (news.html, shop.html) hardcode nav--solid in their
  // markup since there's no dark hero for the nav to start transparent over —
  // the scroll-based toggle below must never touch that class there, or the
  // nav text flips to the hero-only cream color against a light page background.
  const hasHero = !!document.getElementById("hero");

  // Timeline spine: measure the SVG path once, then reveal its stroke in
  // proportion to how far the timeline has scrolled through the viewport.
  const spine = document.getElementById("spinePath");
  const timeline = document.getElementById("timeline");
  let spineLen = 0;
  if (spine) {
    spineLen = spine.getTotalLength();
    spine.style.strokeDasharray = spineLen;
    spine.style.strokeDashoffset = reduceMotion ? 0 : spineLen;
  }

  let ticking = false;
  const onScroll = () => {
    const y = scrollY;
    const max = document.documentElement.scrollHeight - innerHeight;

    // Progress bar — scaleX keeps it off the layout thread
    progressBar.style.transform = `scaleX(${max > 0 ? y / max : 0})`;

    if (hasHero) nav.classList.toggle("nav--solid", y > 60);
    if (fab) fab.classList.toggle("show", y > innerHeight * 0.8);

    // Sticky action bar only appears once the Farms section is reached
    if (mobilebar && farmsSection) {
      mobilebar.classList.toggle(
        "mobilebar--show",
        farmsSection.getBoundingClientRect().top <= innerHeight * 0.6
      );
    }

    if (!reduceMotion) {
      // Hero parallax: background pans at a fraction of scroll speed
      if (heroBg && y < innerHeight * 1.2) {
        heroBg.style.transform = `translateY(${y * parallaxFactor}px)`;
      }
      // Hero content recedes as it scrolls away — a gentle fade + scale
      // (Apple's "scroll exit"). The type settles back into the page
      // rather than scrolling off as a rigid block.
      if (heroInner && y < innerHeight * 1.2) {
        const p = Math.min(y / (innerHeight * 0.85), 1);
        heroInner.style.opacity = String(Math.max(1 - p, 0));
        heroInner.style.transform = `scale(${(1 - p * 0.05).toFixed(4)})`;
      }
      // Spine draw: 0 when the timeline top hits 80% of viewport,
      // 1 when its bottom reaches 60% — clamped
      if (spine) {
        const r = timeline.getBoundingClientRect();
        const p = Math.min(Math.max((innerHeight * 0.8 - r.top) / (r.height * 0.9), 0), 1);
        spine.style.strokeDashoffset = spineLen * (1 - p);
      }
    }
    ticking = false;
  };
  addEventListener("scroll", () => {
    if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
  }, { passive: true });
  onScroll();

  /* ---------- Cursor glow ----------
     The glow lerps toward the pointer each frame (12% per frame) instead of
     snapping, which reads as weight/inertia. Skipped on touch devices. */
  const glow = document.getElementById("cursorGlow");
  if (glow && matchMedia("(hover: hover)").matches && !reduceMotion) {
    let mx = innerWidth / 2, my = innerHeight / 2, gx = mx, gy = my;
    addEventListener("pointermove", (e) => { mx = e.clientX; my = e.clientY; }, { passive: true });
    (function follow() {
      gx += (mx - gx) * 0.12;
      gy += (my - gy) * 0.12;
      glow.style.transform = `translate(${gx}px, ${gy}px)`;
      requestAnimationFrame(follow);
    })();
  }

  /* ---------- 3D tilt on [data-tilt] cards ----------
     Pointer position within the card maps to rotateX/rotateY (max ±7°).
     Perspective is applied per-card; transform resets with a soft ease on
     leave. Disabled for touch / reduced motion. */
  if (matchMedia("(hover: hover)").matches && !reduceMotion) {
    document.querySelectorAll("[data-tilt]").forEach((card) => {
      card.addEventListener("pointermove", (e) => {
        const r = card.getBoundingClientRect();
        const px = (e.clientX - r.left) / r.width - 0.5;
        const py = (e.clientY - r.top) / r.height - 0.5;
        card.style.transition = "transform .1s linear";
        card.style.transform = `perspective(700px) rotateX(${-py * 7}deg) rotateY(${px * 7}deg)`;
      });
      card.addEventListener("pointerleave", () => {
        card.style.transition = "transform .5s cubic-bezier(.22,1,.36,1)";
        card.style.transform = "perspective(700px) rotateX(0) rotateY(0)";
      });
    });
  }

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
      const res = await fetch("news-data.json?v=" + Date.now());
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

  /** Minimal HTML-escapes a string (safe for user-provided content). */
  function escHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  // Expose for news.html archive page
  window.fetchNews = fetchNews;
  window.renderNewsPost = renderNewsPost;
  window.observeReveal = observeReveal;
})();
