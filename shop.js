(() => {
  "use strict";

  let shopProducts = [];
  let activeVariantId = null;

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

  async function loadShop() {
    const root = document.getElementById("shopRoot");
    try {
      const res = await fetch("shop-data.php");
      const data = await res.json();
      shopProducts = data.products || [];
    } catch {
      const msgEn = "Could not load products — try again later.";
      const msgMn = "Бүтээгдэхүүн ачаалж чадсангүй — дараа дахин оролдоно уу.";
      root.innerHTML = `<p class="news-archive__empty" style="display:block" data-en="${escAttrShop(msgEn)}" data-mn="${escAttrShop(msgMn)}">${escHtmlShop(t(msgEn, msgMn))}</p>`;
      return;
    }

    // shopProducts is now populated — if the cart drawer should be showing
    // (either the #cart deep link on first load, or something opened it
    // while this fetch was still in flight), sync it now so it never renders
    // against the still-empty pre-fetch shopProducts array.
    const drawer = document.getElementById("cartDrawer");
    if (location.hash === "#cart" || drawer?.classList.contains("is-open")) {
      openCartDrawer();
    }

    if (!shopProducts.length || !shopProducts[0].variants.length) {
      const msgEn = "No products yet — check back soon.";
      const msgMn = "Одоогоор бүтээгдэхүүн алга — удахгүй дахин орно уу.";
      root.innerHTML = `<p class="news-archive__empty" style="display:block" data-en="${escAttrShop(msgEn)}" data-mn="${escAttrShop(msgMn)}">${escHtmlShop(t(msgEn, msgMn))}</p>`;
      return;
    }

    renderShop(shopProducts[0]);
  }

  function renderShop(product) {
    const root = document.getElementById("shopRoot");
    activeVariantId = product.variants[0].id;

    root.innerHTML = `
      <div class="shop-product">
        <div class="shop-product__switcher" role="tablist">
          ${product.variants.map((v) => `
            <button type="button" class="variant-pill${v.id === activeVariantId ? ' is-active' : ''}" data-variant-id="${v.id}" data-en="${escAttrShop(v.name_en || v.name_mn)}" data-mn="${escAttrShop(v.name_mn)}">${escHtmlShop(variantDisplayName(v))}</button>
          `).join('')}
        </div>
        <div id="shopVariantDetail"></div>
      </div>
    `;

    root.querySelectorAll('.variant-pill').forEach((btn) => {
      btn.addEventListener('click', () => {
        activeVariantId = Number(btn.dataset.variantId);
        root.querySelectorAll('.variant-pill').forEach((b) => b.classList.toggle('is-active', Number(b.dataset.variantId) === activeVariantId));
        renderVariantDetail(product);
      });
    });

    renderVariantDetail(product);
  }

  function renderVariantDetail(product) {
    const variant = product.variants.find((v) => Number(v.id) === Number(activeVariantId));
    const detail = document.getElementById("shopVariantDetail");
    const inStock = variant.stock > 0;
    const displayName = variantDisplayName(variant);

    const stockEn = inStock ? `In stock: ${variant.stock}` : "Out of stock";
    const stockMn = inStock ? `Нөөцөд бий: ${variant.stock}` : "Дууссан";

    detail.innerHTML = `
      <div class="shop-variant">
        ${variant.image_path ? `<img class="shop-variant__img" src="${escAttrShop(variant.image_path)}" alt="${escAttrShop(displayName)}">` : ''}
        <div class="shop-variant__body">
          <h2 class="shop-variant__name" data-en="${escAttrShop(variant.name_en || variant.name_mn)}" data-mn="${escAttrShop(variant.name_mn)}">${escHtmlShop(displayName)}</h2>
          <p class="shop-variant__price">${variant.price.toLocaleString()}₮ <span class="shop-variant__weight">${escHtmlShop(variant.weight_label || '')}</span></p>
          <p class="shop-variant__stock" data-en="${escAttrShop(stockEn)}" data-mn="${escAttrShop(stockMn)}">${escHtmlShop(t(stockEn, stockMn))}</p>
          ${variant.benefits_text_mn ? `<p class="shop-variant__benefits">${escHtmlShop(variant.benefits_text_mn)}</p>` : ''}
          <div class="shop-variant__qty">
            <label for="shopQty" data-en="Qty" data-mn="Тоо ширхэг">${escHtmlShop(t("Qty", "Тоо ширхэг"))}</label>
            <input type="number" id="shopQty" min="1" max="${variant.stock}" value="1" ${inStock ? '' : 'disabled'}>
            <button type="button" class="btn btn--gold" id="shopAddToCart" data-en="Add to cart" data-mn="Сагслах" ${inStock ? '' : 'disabled'}>${escHtmlShop(t("Add to cart", "Сагслах"))}</button>
          </div>
          ${variant.usage_text_mn ? `<div class="shop-variant__section"><h3 data-en="Usage" data-mn="Хэрэглээ">${escHtmlShop(t("Usage", "Хэрэглээ"))}</h3><p>${escHtmlShop(variant.usage_text_mn)}</p></div>` : ''}
          ${variant.storage_text_mn ? `<div class="shop-variant__section"><h3 data-en="Storage" data-mn="Хадгалалт">${escHtmlShop(t("Storage", "Хадгалалт"))}</h3><p>${escHtmlShop(variant.storage_text_mn)}</p></div>` : ''}
          ${variant.ingredients.length ? `
            <div class="shop-variant__section">
              <h3 data-en="Ingredients" data-mn="Найрлага">${escHtmlShop(t("Ingredients", "Найрлага"))}</h3>
              <div class="ingredient-table">
                ${variant.ingredients.map((ing) => `<div class="ingredient-table__row"><span>${escHtmlShop(ing.name)}</span><span>${escHtmlShop(ing.percentage)}</span></div>`).join('')}
              </div>
            </div>
          ` : ''}
        </div>
      </div>
    `;

    const addBtn = document.getElementById("shopAddToCart");
    if (addBtn) {
      addBtn.addEventListener("click", () => {
        const qty = Math.max(1, Math.min(variant.stock, Number(document.getElementById("shopQty").value) || 1));
        if (window.addToCart) window.addToCart(variant.id, qty);
      });
    }
  }

  window.getShopProducts = () => shopProducts;
  document.addEventListener("DOMContentLoaded", loadShop);

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

    if (!cart.length) {
      items.innerHTML = `<p class="cart-drawer__empty" data-en="Your cart is empty." data-mn="Таны сагс хоосон байна.">${escHtmlShop(t("Your cart is empty.", "Таны сагс хоосон байна."))}</p>`;
      totalEl.textContent = "";
      return;
    }

    // Resolve each line against the currently loaded product data.
    const allVariants = shopProducts.flatMap((p) => p.variants);
    let total = 0;

    items.innerHTML = cart.map((line) => {
      const variant = allVariants.find((v) => Number(v.id) === Number(line.variantId));
      if (!variant) return "";
      const lineTotal = variant.price * line.quantity;
      total += lineTotal;
      const displayName = variantDisplayName(variant);
      return `
        <div class="cart-drawer__item" data-variant-id="${variant.id}">
          <div class="cart-drawer__item-name" data-en="${escAttrShop(variant.name_en || variant.name_mn)}" data-mn="${escAttrShop(variant.name_mn)}">${escHtmlShop(displayName)}</div>
          <div class="cart-drawer__item-row">
            <input type="number" class="cart-drawer__qty" min="1" max="${variant.stock}" step="1" value="${line.quantity}" data-variant-id="${escAttrShop(variant.id)}">
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
  }

  function openCartDrawer() {
    renderCartDrawer();
    document.getElementById("cartDrawer").classList.add("is-open");
  }
  function closeCartDrawer() {
    document.getElementById("cartDrawer").classList.remove("is-open");
  }

  document.addEventListener("DOMContentLoaded", () => {
    const toggle = document.getElementById("cartToggle");
    if (toggle) {
      toggle.addEventListener("click", (e) => { e.preventDefault(); openCartDrawer(); });
    }
    document.getElementById("cartDrawerClose")?.addEventListener("click", closeCartDrawer);
    document.getElementById("cartDrawerBackdrop")?.addEventListener("click", closeCartDrawer);
    // The #cart deep-link open is handled inside loadShop()'s success path
    // (above), after shopProducts is populated — opening it here too would
    // race the still-in-flight fetch and render the drawer against an empty
    // shopProducts array (see loadShop's comment).
  });
})();
