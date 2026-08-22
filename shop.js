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
      root.innerHTML = '<p class="news-archive__empty" style="display:block">Could not load products — try again later.</p>';
      return;
    }

    if (!shopProducts.length || !shopProducts[0].variants.length) {
      root.innerHTML = '<p class="news-archive__empty" style="display:block">No products yet — check back soon.</p>';
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
            <button type="button" class="variant-pill${v.id === activeVariantId ? ' is-active' : ''}" data-variant-id="${v.id}">${escHtmlShop(v.name_mn)}</button>
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
    const variant = product.variants.find((v) => v.id === activeVariantId);
    const detail = document.getElementById("shopVariantDetail");
    const inStock = variant.stock > 0;

    detail.innerHTML = `
      <div class="shop-variant">
        ${variant.image_path ? `<img class="shop-variant__img" src="${escAttrShop(variant.image_path)}" alt="${escAttrShop(variant.name_mn)}">` : ''}
        <div class="shop-variant__body">
          <h2 class="shop-variant__name">${escHtmlShop(variant.name_mn)}</h2>
          <p class="shop-variant__price">${variant.price.toLocaleString()}₮ <span class="shop-variant__weight">${escHtmlShop(variant.weight_label || '')}</span></p>
          <p class="shop-variant__stock">${inStock ? `In stock: ${variant.stock}` : 'Out of stock'}</p>
          ${variant.benefits_text_mn ? `<p class="shop-variant__benefits">${escHtmlShop(variant.benefits_text_mn)}</p>` : ''}
          <div class="shop-variant__qty">
            <label for="shopQty">Qty</label>
            <input type="number" id="shopQty" min="1" max="${variant.stock}" value="1" ${inStock ? '' : 'disabled'}>
            <button type="button" class="btn btn--gold" id="shopAddToCart" ${inStock ? '' : 'disabled'}>Add to cart</button>
          </div>
          ${variant.usage_text_mn ? `<div class="shop-variant__section"><h3>Usage</h3><p>${escHtmlShop(variant.usage_text_mn)}</p></div>` : ''}
          ${variant.storage_text_mn ? `<div class="shop-variant__section"><h3>Storage</h3><p>${escHtmlShop(variant.storage_text_mn)}</p></div>` : ''}
          ${variant.ingredients.length ? `
            <div class="shop-variant__section">
              <h3>Ingredients</h3>
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
})();
