(() => {
  "use strict";

  let shopProducts = [];
  let activeVariantId = null;
  // Checkout, the QR screen and the order link live on their own page,
  // /shop/checkout (shop-checkout.php + js/checkout.js). This file runs the
  // basket drawer and the product page.

  function newsLangSafe() {
    // Mirrors script.js's newsLang() but shop.js is a separate file — this
    // page's language state is the same document.documentElement.lang
    // script.js's setLanguage() already maintains, just read fresh here.
    return document.documentElement.lang || "en";
  }

  /** Pick the EN/MN string for the current language, read fresh at render time.
   * script.js's setLanguage() sets document.documentElement.lang synchronously
   * before any shop.js render can run (script.js executes first and applies
   * the saved preference before DOMContentLoaded), so this is always accurate
   * — including for content shop.js creates AFTER a language toggle, which
   * setLanguage()'s one-time querySelectorAll sweep would otherwise miss. */
  function t(en, mn) {
    return newsLangSafe() === "mn" ? mn : en;
  }

  /** Product/variant display name per the spec's carve-out: names translate
   * normally (falling back to the MN name until name_en is populated), unlike
   * benefits/usage/storage/ingredients which stay Mongolian-only. */
  function variantDisplayName(v) {
    return t(v.name_en || v.name_mn, v.name_mn);
  }

  function escHtmlShop(str) {
    const div = document.createElement("div");
    div.textContent = str == null ? "" : String(str);
    return div.innerHTML;
  }

  /** Escape a value for use inside a double-quoted HTML attribute (escHtmlShop doesn't escape quotes). */
  function escAttrShop(str) {
    return (str == null ? "" : String(str)).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  /** Loads live prices and stock for the basket. The pages themselves are
   * rendered by PHP (shop.php, shop-product.php); this data is only what the
   * basket drawer needs. */
  async function loadShop() {
    try {
      const res = await fetch("/api/shop-data.php");
      const data = await res.json();
      shopProducts = data.products || [];
    } catch {
      shopProducts = [];
    }

    // shopProducts is now populated — if the basket drawer should be showing
    // (the #cart deep link, or something opened it while this fetch was still
    // in flight), draw it now rather than against the empty pre-fetch list.
    const drawer = document.getElementById("cartDrawer");
    if (location.hash === "#cart" || drawer?.classList.contains("is-open")) {
      openCartDrawer();
    }
  }

  /* ---------- Product page (/shop/<slug>) ----------
     shop-product.php renders every variant as a panel; the pills only switch
     which panel is visible. Each panel has its own quantity box and
     "Сагслах" button carrying its variant id. */
  function initProductPage() {
    const pills = document.querySelectorAll(".shop-product .variant-pill");
    pills.forEach((pill) => {
      pill.addEventListener("click", () => {
        activeVariantId = Number(pill.dataset.variantId);
        pills.forEach((p) => {
          const on = Number(p.dataset.variantId) === activeVariantId;
          p.classList.toggle("is-active", on);
          p.setAttribute("aria-pressed", on ? "true" : "false");
        });
        document.querySelectorAll(".shop-variant-panel").forEach((panel) => {
          panel.hidden = Number(panel.dataset.variantId) !== activeVariantId;
        });
      });
    });

    document.querySelectorAll(".shop-add").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = Number(btn.dataset.variantId);
        const max = Number(btn.dataset.max) || 1;
        const input = document.getElementById(`shopQty-${id}`);
        const qty = Math.max(1, Math.min(max, Number(input?.value) || 1));
        if (window.addToCart) window.addToCart(id, qty);
        const label = btn.textContent;
        btn.textContent = t("Added ✓", "Сагсанд нэмэгдлээ ✓");
        setTimeout(() => { btn.textContent = label; }, 1800);
      });
    });
  }

  /* ---------- Card "Сагсанд нэмэх" (/shop, one-option items) ----------
     shop_card() (inc/catalog-lib.php) prints the button with the option's
     id. One tap = one more in the basket; the basket and the order step
     already refuse more than the stock. */
  function initCardButtons() {
    document.querySelectorAll(".eq-card__add[data-variant-id]").forEach((btn) => {
      btn.addEventListener("click", () => {
        if (window.addToCart) window.addToCart(Number(btn.dataset.variantId), 1);
        btn.textContent = "Сагсанд нэмэгдлээ ✓";
        btn.classList.add("is-added");
        clearTimeout(btn._reset);
        btn._reset = setTimeout(() => { btn.textContent = "Сагсанд нэмэх"; btn.classList.remove("is-added"); }, 1800);
      });
    });
  }

  window.getShopProducts = () => shopProducts;
  document.addEventListener("DOMContentLoaded", () => { initProductPage(); initCardButtons(); loadShop(); });

  /* ---------- Cart drawer ----------
     Renders window.getCart() lines against the product data already loaded
     by loadShop() above. Variant ids come from JSON (shop-data.php) on one
     side and from localStorage (via window.addToCart) on the other, so all
     lookups compare with Number(...) === Number(...) rather than raw ===
     to stay safe regardless of how either side's id was typed. */
  function renderCartDrawer() {
    const cart = getCart();
    const items = document.getElementById("cartDrawerItems");
    const totalEl = document.getElementById("cartDrawerTotal");

    const footer = document.querySelector(".cart-drawer__footer");
    if (!cart.length) {
      // An empty priced cart does not mean an empty basket — equipment quote
      // lines are held separately and must still render (and be sendable).
      // The checkout footer only belongs to priced lines.
      items.innerHTML = "";
      totalEl.textContent = "";
      if (footer) footer.style.display = "none";
      const hasQuotes = window.renderInquirySection ? window.renderInquirySection(items) : false;
      if (!hasQuotes) {
        items.innerHTML = `<p class="cart-drawer__empty" data-en="Your cart is empty." data-mn="Таны сагс хоосон байна.">${escHtmlShop(t("Your cart is empty.", "Таны сагс хоосон байна."))}</p>`;
      }
      return;
    }

    // shopProducts can be empty because loadShop()'s fetch genuinely failed
    // (not because there are no products) — showing an empty-looking cart
    // with a live checkout button in that state would silently let a buyer
    // "check out" a cart order-create.php can only reject. Show a retry
    // message instead of guessing.
    if (!shopProducts.length) {
      items.innerHTML = `<p class="cart-drawer__empty" data-en="Couldn't load your cart — try again." data-mn="Сагсаа ачаалж чадсангүй — дахин оролдоно уу.">${escHtmlShop(t("Couldn't load your cart — try again.", "Сагсаа ачаалж чадсангүй — дахин оролдоно уу."))}</p>`;
      totalEl.textContent = "";
      // Hide the footer entirely rather than leaving a live checkout button
      // the buyer could still click through — order-create.php can only
      // reject it once product data hasn't actually loaded.
      if (footer) footer.style.display = "none";
      if (window.renderInquirySection) window.renderInquirySection(items);
      return;
    }
    if (footer) footer.style.display = "";

    // Resolve each line against the currently loaded product data.
    const allVariants = shopProducts.flatMap((p) => p.variants);
    let total = 0;

    items.innerHTML = cart.map((line) => {
      const variant = allVariants.find((v) => Number(v.id) === Number(line.variantId));
      if (!variant) {
        // Stays visible (and removable) rather than silently vanishing —
        // an invisible-but-still-submitted line is what made checkout fail
        // with no way for the buyer to see or fix which line was the problem.
        return `
          <div class="cart-drawer__item cart-drawer__item--unavailable" data-variant-id="${escAttrShop(line.variantId)}">
            <div class="cart-drawer__item-name" data-en="No longer available" data-mn="Дууссан">${escHtmlShop(t("No longer available", "Дууссан"))}</div>
            <div class="cart-drawer__item-row">
              <button type="button" class="cart-drawer__remove" data-variant-id="${escAttrShop(line.variantId)}" aria-label="Remove">✕</button>
            </div>
          </div>
        `;
      }
      const lineTotal = variant.price * line.quantity;
      total += lineTotal;
      const displayName = variantDisplayName(variant);
      return `
        <div class="cart-drawer__item" data-variant-id="${variant.id}">
          <div class="cart-drawer__item-name" data-en="${escAttrShop(variant.name_en || variant.name_mn)}" data-mn="${escAttrShop(variant.name_mn)}">${escHtmlShop(displayName)}</div>
          <div class="cart-drawer__item-row">
            <input type="number" class="cart-drawer__qty" min="1" max="${variant.available}" step="1" value="${line.quantity}" data-variant-id="${escAttrShop(variant.id)}">
            <span>${lineTotal.toLocaleString()}₮</span>
            <button type="button" class="cart-drawer__remove" data-variant-id="${escAttrShop(variant.id)}" aria-label="Remove">✕</button>
          </div>
        </div>
      `;
    }).join("");

    totalEl.innerHTML = `<span data-en="Total" data-mn="Нийт">${escHtmlShop(t("Total", "Нийт"))}</span>: ${total.toLocaleString()}₮`;

    items.querySelectorAll(".cart-drawer__qty").forEach((input) => {
      input.addEventListener("change", () => {
        const id = Number(input.dataset.variantId);
        const qty = Math.max(1, Number(input.value) || 1);
        const cart = getCart();
        const line = cart.find((l) => Number(l.variantId) === id);
        if (line) { line.quantity = qty; setCart(cart); renderCartDrawer(); }
      });
    });
    items.querySelectorAll(".cart-drawer__remove").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = Number(btn.dataset.variantId);
        setCart(getCart().filter((l) => Number(l.variantId) !== id));
        renderCartDrawer();
      });
    });

    // Equipment quote lines render below the priced ones. catalog.js owns
    // that half; this is the only place the two baskets meet.
    if (window.renderInquirySection) window.renderInquirySection(items);
  }

  // The drawer is `inert` while closed (inc/layout.php), so keyboard focus
  // can't wander into its off-screen fields. Opening moves focus to the close
  // button; closing returns it to whatever opened the drawer.
  let drawerOpener = null;
  function showDrawer() {
    const drawer = document.getElementById("cartDrawer");
    if (!drawer.classList.contains("is-open")) drawerOpener = document.activeElement;
    drawer.inert = false;
    drawer.classList.add("is-open");
    document.getElementById("cartDrawerClose")?.focus({ preventScroll: true });
  }
  function openCartDrawer() {
    renderCartDrawer();
    showDrawer();
  }
  // catalog.js opens the same drawer after "Хүсэлтийн жагсаалтад нэмэх" and
  // re-renders it when a quote line changes.
  window.openCartDrawer = openCartDrawer;
  window.renderCartDrawer = () => { if (document.getElementById("cartDrawer")?.classList.contains("is-open")) renderCartDrawer(); };
  function closeCartDrawer() {
    const drawer = document.getElementById("cartDrawer");
    drawer.classList.remove("is-open");
    drawer.inert = true;
    if (drawerOpener && document.contains(drawerOpener)) drawerOpener.focus({ preventScroll: true });
    drawerOpener = null;
  }
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && document.getElementById("cartDrawer")?.classList.contains("is-open")) closeCartDrawer();
  });

  document.addEventListener("DOMContentLoaded", () => {
    const toggle = document.getElementById("cartToggle");
    if (toggle) {
      toggle.addEventListener("click", (e) => { e.preventDefault(); openCartDrawer(); });
    }
    document.getElementById("cartDrawerClose")?.addEventListener("click", closeCartDrawer);
    document.getElementById("cartDrawerBackdrop")?.addEventListener("click", closeCartDrawer);
    // The #cart deep-link open is handled inside loadShop() (above), after
    // shopProducts is populated.

    // "Захиалга үргэлжлүүлэх" goes to the checkout page.
    const checkoutBtn = document.getElementById("cartCheckoutBtn");
    if (checkoutBtn) {
      checkoutBtn.disabled = false;
      checkoutBtn.removeAttribute("title");
      checkoutBtn.addEventListener("click", () => { location.href = "/shop/checkout"; });
    }
  });
})();
