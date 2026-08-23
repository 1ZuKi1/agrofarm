(() => {
  "use strict";

  let shopProducts = [];
  let activeVariantId = null;
  let qrPollInterval = null; // active QPay status-poll interval, if any (see stopQrPolling)

  // ?order=<token> — the bookmarkable confirmation link. Read once, at
  // module load, so both loadShop() below and the ?order= handler further
  // down agree on the same value without re-parsing location.search.
  const orderToken = new URLSearchParams(location.search).get("order");

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
    //
    // Skipped entirely when ?order=<token> is present: that handler (below,
    // in the final DOMContentLoaded listener) owns #cartDrawerItems for the
    // whole page load in that case, and frequently resolves before this
    // fetch does (order-status.php is a single indexed lookup; shop-data.php
    // is N+1). Without this guard, this check would see is-open already
    // true — set by that handler, not by a real cart-open action — and call
    // openCartDrawer(), silently overwriting the order confirmation/status
    // screen with the empty/generic cart view the moment shop-data.php
    // caught up.
    const drawer = document.getElementById("cartDrawer");
    if (!orderToken && (location.hash === "#cart" || drawer?.classList.contains("is-open"))) {
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
    // available (stock minus everything reserved by pending/paid/fulfilled
    // orders) reflects true availability — the raw stock column can be
    // higher than what's actually left to buy. See shop-data.php.
    const inStock = variant.available > 0;
    const displayName = variantDisplayName(variant);

    const stockEn = inStock ? `In stock: ${variant.available}` : "Out of stock";
    const stockMn = inStock ? `Нөөцөд бий: ${variant.available}` : "Дууссан";

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
            <input type="number" id="shopQty" min="1" max="${variant.available}" value="1" ${inStock ? '' : 'disabled'}>
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
        const qty = Math.max(1, Math.min(variant.available, Number(document.getElementById("shopQty").value) || 1));
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
      const footer = document.querySelector(".cart-drawer__footer");
      if (footer) footer.style.display = "none";
      return;
    }
    const footer = document.querySelector(".cart-drawer__footer");
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
  }

  /** Restores the drawer head/footer to their static "Cart" state. Checkout
   * mode repurposes #cartDrawerItems for the form and hides the footer (see
   * renderCheckoutForm below) without ever removing #cartDrawerClose or
   * #cartCheckoutBtn from the DOM, so their listeners (bound once, below)
   * never need rebinding — only this cosmetic reset is needed on return. */
  function resetCartPanelToCartView() {
    const heading = document.querySelector(".cart-drawer__head h2");
    heading.dataset.en = "Cart";
    heading.dataset.mn = "Сагс";
    heading.textContent = t("Cart", "Сагс");
    document.querySelector(".cart-drawer__footer").style.display = "";
  }

  /** Clears the QPay status-poll interval started by renderQrScreen, if one
   * is running. Called whenever the drawer leaves the QR screen (closed via
   * #cartDrawerClose/backdrop, or reopened to the cart view) so the poll
   * never keeps firing after its screen is gone. */
  function stopQrPolling() {
    if (qrPollInterval) {
      clearInterval(qrPollInterval);
      qrPollInterval = null;
    }
  }

  function openCartDrawer() {
    stopQrPolling();
    resetCartPanelToCartView();
    renderCartDrawer();
    document.getElementById("cartDrawer").classList.add("is-open");
  }
  function closeCartDrawer() {
    stopQrPolling();
    document.getElementById("cartDrawer").classList.remove("is-open");
  }

  /* ---------- Checkout form ----------
     Repurposes #cartDrawerItems (normally the cart line list) to host the
     buyer form + delivery slot picker, and hides .cart-drawer__footer (the
     total/checkout button, not needed here). #cartDrawerClose keeps closing
     the whole drawer throughout. "Back to cart" just calls openCartDrawer()
     again, which resets the head/footer and re-renders the cart lines. */
  async function loadSlots() {
    const res = await fetch("slots.php");
    const data = await res.json();
    return data.days || [];
  }

  function formatSlotDate(dateStr) {
    const d = new Date(dateStr + "T00:00:00");
    return {
      en: d.toLocaleDateString("en-US", { weekday: "short", month: "short", day: "numeric" }),
      mn: d.toLocaleDateString("mn-MN", { weekday: "short", month: "short", day: "numeric" }),
    };
  }

  function showCheckoutError(en, mn) {
    const errorEl = document.getElementById("checkoutError");
    errorEl.dataset.en = en;
    errorEl.dataset.mn = mn;
    errorEl.textContent = t(en, mn);
    errorEl.style.display = "block";
  }

  function renderCheckoutForm() {
    const heading = document.querySelector(".cart-drawer__head h2");
    heading.dataset.en = "Checkout";
    heading.dataset.mn = "Захиалга баталгаажуулах";
    heading.textContent = t("Checkout", "Захиалга баталгаажуулах");
    document.querySelector(".cart-drawer__footer").style.display = "none";

    const items = document.getElementById("cartDrawerItems");
    items.innerHTML = `
      <form id="checkoutForm" class="checkout-form">
        <div class="checkout-form__field">
          <label for="checkoutName" data-en="Name" data-mn="Нэр">${escHtmlShop(t("Name", "Нэр"))}</label>
          <input type="text" id="checkoutName" name="buyer_name" required>
        </div>
        <div class="checkout-form__field">
          <label for="checkoutPhone" data-en="Phone" data-mn="Утас">${escHtmlShop(t("Phone", "Утас"))}</label>
          <input type="tel" id="checkoutPhone" name="buyer_phone" required>
        </div>
        <div class="checkout-form__field">
          <label for="checkoutAddress" data-en="Delivery address" data-mn="Хүргэлтийн хаяг">${escHtmlShop(t("Delivery address", "Хүргэлтийн хаяг"))}</label>
          <textarea id="checkoutAddress" name="buyer_address" required></textarea>
        </div>
        <div class="checkout-form__field">
          <label for="checkoutNote" data-en="Note (optional)" data-mn="Тэмдэглэл (заавал биш)">${escHtmlShop(t("Note (optional)", "Тэмдэглэл (заавал биш)"))}</label>
          <textarea id="checkoutNote" name="buyer_note"></textarea>
        </div>
        <div id="slotPicker" class="slot-picker" data-en="Loading delivery times…" data-mn="Хүргэлтийн цагийг ачааллаж байна…">${escHtmlShop(t("Loading delivery times…", "Хүргэлтийн цагийг ачааллаж байна…"))}</div>
        <p id="checkoutError" class="checkout-form__error" style="display:none"></p>
        <button type="submit" class="btn btn--gold" id="checkoutSubmitBtn" data-en="Pay with QPay" data-mn="QPay-ээр төлөх">${escHtmlShop(t("Pay with QPay", "QPay-ээр төлөх"))}</button>
        <button type="button" id="checkoutBackBtn" data-en="Back to cart" data-mn="Сагс руу буцах">${escHtmlShop(t("Back to cart", "Сагс руу буцах"))}</button>
      </form>
    `;

    document.getElementById("checkoutBackBtn").addEventListener("click", openCartDrawer);

    let selectedDate = null;
    let selectedSlot = null;

    const picker = document.getElementById("slotPicker");
    loadSlots().then((days) => {
      if (!days.length) {
        picker.dataset.en = "No delivery slots available";
        picker.dataset.mn = "Хүргэлтийн цаг алга байна";
        picker.textContent = t("No delivery slots available", "Хүргэлтийн цаг алга байна");
        return;
      }
      delete picker.dataset.en;
      delete picker.dataset.mn;
      picker.innerHTML = days.map((day) => {
        const label = formatSlotDate(day.date);
        return `
          <div class="slot-picker__day" data-date="${escAttrShop(day.date)}">
            <div class="slot-picker__date" data-en="${escAttrShop(label.en)}" data-mn="${escAttrShop(label.mn)}">${escHtmlShop(t(label.en, label.mn))}</div>
            <div class="slot-picker__slots">
              ${day.slots.map((s) => `<button type="button" class="variant-pill slot-btn" data-date="${escAttrShop(day.date)}" data-slot="${escAttrShop(s.slot)}" ${s.available ? '' : 'disabled style="opacity:.4"'}>${escHtmlShop(s.slot)}</button>`).join('')}
            </div>
          </div>
        `;
      }).join('');

      picker.querySelectorAll(".slot-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
          picker.querySelectorAll(".slot-btn").forEach((b) => b.classList.remove("is-active"));
          btn.classList.add("is-active");
          selectedDate = btn.dataset.date;
          selectedSlot = btn.dataset.slot;
        });
      });
    }).catch(() => {
      picker.dataset.en = "Could not load delivery times — please try again";
      picker.dataset.mn = "Хүргэлтийн цагийг ачаалж чадсангүй — дахин оролдоно уу";
      picker.textContent = t("Could not load delivery times — please try again", "Хүргэлтийн цагийг ачаалж чадсангүй — дахин оролдоно уу");
    });

    document.getElementById("checkoutForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      const errorEl = document.getElementById("checkoutError");
      errorEl.style.display = "none";

      if (!selectedSlot) {
        showCheckoutError("Please pick a delivery date and time", "Хүргэлтийн огноо, цагийг сонгоно уу");
        return;
      }

      const form = new FormData(e.target);
      const submitBtn = document.getElementById("checkoutSubmitBtn");
      submitBtn.disabled = true;

      const cart = getCart();
      const body = {
        items: cart.map((line) => ({ variant_id: line.variantId, quantity: line.quantity })),
        buyer_name: form.get("buyer_name"),
        buyer_phone: form.get("buyer_phone"),
        buyer_address: form.get("buyer_address"),
        buyer_note: form.get("buyer_note"),
        delivery_date: selectedDate,
        delivery_slot: selectedSlot,
      };

      try {
        const res = await fetch("order-create.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) {
          if (data.error) {
            errorEl.textContent = data.error; // server message — Mongolian only, not translatable
            errorEl.style.display = "block";
          } else {
            showCheckoutError("Something went wrong — please try again", "Алдаа гарлаа — дахин оролдоно уу");
          }
          submitBtn.disabled = false;
          return;
        }
        setCart([]); // order created — clear the cart
        if (window.renderQrScreen) window.renderQrScreen(data);
      } catch {
        showCheckoutError("Could not reach the server — please try again", "Сервертэй холбогдож чадсангүй — дахин оролдоно уу");
        submitBtn.disabled = false;
      }
    });
  }

  /* ---------- QR payment screen + polling ----------
     Rendered by renderCheckoutForm's submit handler once order-create.php
     returns successfully (window.renderQrScreen call above). Repurposes
     #cartDrawerItems the same way renderCheckoutForm does, and leaves
     #cartDrawerClose alone — it's already bound once at the bottom of this
     file and, via closeCartDrawer's stopQrPolling() call, stops the poll
     when clicked without needing a listener here. */
  function renderQrScreen(orderData) {
    stopQrPolling();

    const heading = document.querySelector(".cart-drawer__head h2");
    heading.dataset.en = "Scan to pay";
    heading.dataset.mn = "Сканнердаж төлнө үү";
    heading.textContent = t("Scan to pay", "Сканнердаж төлнө үү");
    document.querySelector(".cart-drawer__footer").style.display = "none";

    const items = document.getElementById("cartDrawerItems");
    // qr_image can be null if QPay's response omitted it (order-create.php
    // falls back to null) — skip the <img> rather than rendering a broken
    // image; the waiting text below still makes the state clear.
    const qrImg = orderData.qr_image
      ? `<img class="qr-screen__img" src="data:image/png;base64,${escAttrShop(orderData.qr_image)}" alt="QPay QR code">`
      : "";
    const token = orderData.token;
    // Shown so a buyer who navigates away mid-payment (closes the drawer,
    // switches tabs, loses their connection) has a way back to this exact
    // screen — order-status.php keeps returning qr_image/qr_text for as
    // long as the order stays 'pending', and the ?order= handler below
    // re-renders this same screen from that link.
    const orderLink = `${location.origin}/shop.html?order=${token}`;
    items.innerHTML = `
      <div class="qr-screen">
        ${qrImg}
        <p class="qr-screen__total">${orderData.total.toLocaleString()}₮</p>
        <p class="qr-screen__waiting" data-en="Waiting for payment…" data-mn="Төлбөр хүлээгдэж байна…">${escHtmlShop(t("Waiting for payment…", "Төлбөр хүлээгдэж байна…"))}</p>
        <p class="qr-screen__link"><span data-en="Come back to this later" data-mn="Дараа буцаж ирэх бол">${escHtmlShop(t("Come back to this later", "Дараа буцаж ирэх бол"))}</span>: ${escHtmlShop(orderLink)}</p>
      </div>
    `;

    qrPollInterval = setInterval(() => {
      fetch(`order-status.php?token=${encodeURIComponent(token)}`)
        .then((r) => r.json())
        .then((status) => {
          if (status.status === "paid") {
            stopQrPolling();
            renderConfirmation(token, status);
          } else if (status.status === "expired" || status.status === "cancelled") {
            stopQrPolling();
            const waiting = document.querySelector(".qr-screen__waiting");
            if (waiting) {
              const enMsg = "This order expired — please try again";
              const mnMsg = "Энэ захиалга хугацаа дууссан — дахин оролдоно уу";
              waiting.dataset.en = enMsg;
              waiting.dataset.mn = mnMsg;
              waiting.textContent = t(enMsg, mnMsg);
            }
          }
        })
        .catch(() => {}); // transient network error — next poll retries
    }, 3000);
  }
  window.renderQrScreen = renderQrScreen;

  function renderConfirmation(token, status) {
    const heading = document.querySelector(".cart-drawer__head h2");
    heading.dataset.en = "Payment received";
    heading.dataset.mn = "Төлбөр хүлээн авлаа";
    heading.textContent = t("Payment received", "Төлбөр хүлээн авлаа");
    document.querySelector(".cart-drawer__footer").style.display = "none";

    const items = document.getElementById("cartDrawerItems");
    const orderLink = `${location.origin}/shop.html?order=${token}`;
    // "Delivery"/"Order link" labels are wrapped in their own data-en/data-mn
    // span (same pattern as #cartDrawerTotal in renderCartDrawer above) so a
    // language toggle only swaps the label, not the dynamic value after it —
    // putting data-en/data-mn on the whole <p> would have setLanguage()
    // overwrite the date/slot/link with the static attribute text.
    items.innerHTML = `
      <div class="order-confirmation">
        <svg class="order-confirmation__check" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
        <p class="order-confirmation__total">${status.total.toLocaleString()}₮</p>
        <p><span data-en="Delivery" data-mn="Хүргэлт">${escHtmlShop(t("Delivery", "Хүргэлт"))}</span>: ${escHtmlShop(status.delivery_date)} · ${escHtmlShop(status.delivery_slot)}</p>
        <p class="order-confirmation__link"><span data-en="Order link" data-mn="Захиалгын холбоос">${escHtmlShop(t("Order link", "Захиалгын холбоос"))}</span>: ${escHtmlShop(orderLink)}</p>
      </div>
    `;
  }

  /** Shown for the ?order=<token> deep link (see the DOMContentLoaded
   * handler below) when the order isn't paid — pending, expired/cancelled,
   * or not found. Reuses .cart-drawer__empty's centered-message styling
   * rather than adding a new class for what's just a sentence of text. */
  function renderOrderStatusMessage(headingEn, headingMn, bodyEn, bodyMn) {
    const heading = document.querySelector(".cart-drawer__head h2");
    heading.dataset.en = headingEn;
    heading.dataset.mn = headingMn;
    heading.textContent = t(headingEn, headingMn);
    document.querySelector(".cart-drawer__footer").style.display = "none";

    const items = document.getElementById("cartDrawerItems");
    items.innerHTML = `<p class="cart-drawer__empty" data-en="${escAttrShop(bodyEn)}" data-mn="${escAttrShop(bodyMn)}">${escHtmlShop(t(bodyEn, bodyMn))}</p>`;
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

    const checkoutBtn = document.getElementById("cartCheckoutBtn");
    if (checkoutBtn) {
      checkoutBtn.disabled = false;
      checkoutBtn.removeAttribute("title");
      checkoutBtn.addEventListener("click", renderCheckoutForm);
    }

    // Bookmarkable confirmation link: ?order=<token> (a query param, distinct
    // from the #cart hash handled inside loadShop()) opens the drawer
    // straight to that order's current status. Independent of shopProducts —
    // order-status.php doesn't need product data — so no race to guard here.
    // (orderToken itself is module-scoped, read once at the top of the file —
    // see the comment there and in loadShop() for why loadShop() must not
    // clobber this handler's render once shop-data.php resolves.)
    if (orderToken) {
      document.getElementById("cartDrawer")?.classList.add("is-open");
      fetch(`order-status.php?token=${encodeURIComponent(orderToken)}`)
        .then((r) => r.json())
        .then((status) => {
          if (status.status === "paid") {
            renderConfirmation(orderToken, status);
          } else if (status.status === "pending") {
            // order-status.php returns qr_image/qr_text alongside the rest
            // of a still-pending order's status — re-render the same QR
            // screen (with polling) rather than a dead-end text message, so
            // this link is an actual way to finish paying, not just a status
            // check.
            renderQrScreen({ ...status, token: orderToken });
          } else if (status.status === "expired" || status.status === "cancelled") {
            renderOrderStatusMessage("Order expired", "Захиалгын хугацаа дууссан", "This order is no longer active.", "Энэ захиалга идэвхгүй боллоо.");
          } else {
            renderOrderStatusMessage("Order not found", "Захиалга олдсонгүй", "We couldn't find that order.", "Тухайн захиалга олдсонгүй.");
          }
        })
        .catch(() => {
          renderOrderStatusMessage("Order not found", "Захиалга олдсонгүй", "We couldn't find that order.", "Тухайн захиалга олдсонгүй.");
        });
    }
  });
})();
