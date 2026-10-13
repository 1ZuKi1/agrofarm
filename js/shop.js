(() => {
  "use strict";

  let shopProducts = [];
  let activeVariantId = null;
  let qrPollInterval = null; // active QPay status-poll interval, if any (see stopQrPolling)
  let qrVisibilityHandler = null; // re-check on return from a bank app (see renderQrScreen)
  let qrCountdown = null; // 1-second countdown timer on the QR screen
  // The order this browser is paying for right now. The basket is only
  // emptied once THAT order is paid — if it expires, the basket is still
  // there to try again (it used to be emptied as soon as the QR appeared).
  const PENDING_KEY = "naf_pending_order";

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

  /** Loads live prices and stock for the basket. The pages themselves are
   * rendered by PHP (shop.php, shop-product.php); this data is only what the
   * basket drawer and checkout need. */
  async function loadShop() {
    try {
      const res = await fetch("/api/shop-data.php");
      const data = await res.json();
      shopProducts = data.products || [];
    } catch {
      shopProducts = [];
    }

    // shopProducts is now populated — if the cart drawer should be showing
    // (either the #cart deep link on first load, or something opened it
    // while this fetch was still in flight), sync it now so it never renders
    // against the still-empty pre-fetch shopProducts array.
    //
    // Skipped entirely when ?order=<token> is present: that handler (below,
    // in the final DOMContentLoaded listener) owns #cartDrawerItems for the
    // whole page load in that case, and frequently resolves before this
    // fetch does. Without this guard, this check would see is-open already
    // true — set by that handler, not by a real cart-open action — and call
    // openCartDrawer(), silently overwriting the order confirmation/status
    // screen with the cart view the moment shop-data.php caught up.
    const drawer = document.getElementById("cartDrawer");
    if (!orderToken && (location.hash === "#cart" || drawer?.classList.contains("is-open"))) {
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

  window.getShopProducts = () => shopProducts;
  document.addEventListener("DOMContentLoaded", () => { initProductPage(); loadShop(); });

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
    // Footer visibility is decided by renderCartDrawer (priced lines only).
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
    if (qrVisibilityHandler) {
      document.removeEventListener("visibilitychange", qrVisibilityHandler);
      qrVisibilityHandler = null;
    }
    if (qrCountdown) {
      clearInterval(qrCountdown);
      qrCountdown = null;
    }
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
    stopQrPolling();
    resetCartPanelToCartView();
    renderCartDrawer();
    showDrawer();
  }
  // catalog.js opens the same drawer after "Хүсэлтийн жагсаалтад нэмэх" and
  // re-renders it when a quote line changes.
  window.openCartDrawer = openCartDrawer;
  window.renderCartDrawer = () => { if (document.getElementById("cartDrawer")?.classList.contains("is-open")) renderCartDrawer(); };
  function closeCartDrawer() {
    stopQrPolling();
    const drawer = document.getElementById("cartDrawer");
    drawer.classList.remove("is-open");
    drawer.inert = true;
    if (drawerOpener && document.contains(drawerOpener)) drawerOpener.focus({ preventScroll: true });
    drawerOpener = null;
  }
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && document.getElementById("cartDrawer")?.classList.contains("is-open")) closeCartDrawer();
  });

  /* ---------- Checkout form ----------
     Repurposes #cartDrawerItems (normally the cart line list) to host the
     buyer form + delivery slot picker, and hides .cart-drawer__footer (the
     total/checkout button, not needed here). #cartDrawerClose keeps closing
     the whole drawer throughout. "Back to cart" just calls openCartDrawer()
     again, which resets the head/footer and re-renders the cart lines. */
  async function loadSlots() {
    const res = await fetch("/api/slots.php");
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
          <input type="tel" id="checkoutPhone" name="buyer_phone" inputmode="numeric" autocomplete="tel" placeholder="99112233" required>
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
      // Same rule as order-create.php: 8 digits; spaces, dashes, +976 allowed.
      const phone = String(form.get("buyer_phone") || "").replace(/[\s\-().]/g, "").replace(/^\+?976(?=\d{8}$)/, "");
      if (!/^\d{8}$/.test(phone)) {
        showCheckoutError("Phone number must be 8 digits", "Утасны дугаар 8 оронтой байх ёстой");
        document.getElementById("checkoutPhone").focus();
        return;
      }
      const submitBtn = document.getElementById("checkoutSubmitBtn");
      submitBtn.disabled = true;

      const cart = getCart();
      const body = {
        items: cart.map((line) => ({ variant_id: line.variantId, quantity: line.quantity })),
        buyer_name: form.get("buyer_name"),
        buyer_phone: phone,
        buyer_address: form.get("buyer_address"),
        buyer_note: form.get("buyer_note"),
        delivery_date: selectedDate,
        delivery_slot: selectedSlot,
      };

      try {
        const res = await fetch("/api/order-create.php", {
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
        // The basket stays until this order is paid (see PENDING_KEY).
        try { localStorage.setItem(PENDING_KEY, data.token); } catch {}
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
    heading.dataset.en = "Pay with QPay";
    heading.dataset.mn = "QPay-ээр төлөх";
    heading.textContent = t("Pay with QPay", "QPay-ээр төлөх");
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
    const orderLink = `${location.origin}/shop?order=${token}`;
    // Bank-app buttons: a buyer on a phone can't scan a QR on their own
    // screen, so each button opens that bank's app with this invoice filled
    // in. Shown only on phones/tablets via CSS (.qr-screen__banks); the links
    // come from QPay's invoice `urls`, trimmed and scheme-checked server-side
    // in qpay_bank_links().
    const banks = Array.isArray(orderData.banks) ? orderData.banks : [];
    const banksHtml = banks.length
      ? `<p class="qr-screen__banks-title" data-en="Choose your bank app" data-mn="Банкны аппаа сонгоно уу">${escHtmlShop(t("Choose your bank app", "Банкны аппаа сонгоно уу"))}</p>
         <div class="qr-screen__banks">${banks.map((b) => `
           <a class="qr-bank" href="${escAttrShop(b.link)}">
             ${b.logo ? `<img src="${escAttrShop(b.logo)}" alt="" loading="lazy">` : ""}
             <span>${escHtmlShop(b.name)}</span>
           </a>`).join("")}
         </div>
         <p class="qr-screen__or" data-en="or scan the QR from another phone" data-mn="эсвэл өөр утаснаас QR уншуулна уу">${escHtmlShop(t("or scan the QR from another phone", "эсвэл өөр утаснаас QR уншуулна уу"))}</p>`
      : "";
    items.innerHTML = `
      <div class="qr-screen${banks.length ? " has-banks" : ""}">
        <p class="qr-screen__total">${orderData.total.toLocaleString()}₮</p>
        ${orderData.order_no ? `<p class="qr-screen__no">${escHtmlShop(t("Order", "Захиалга"))} ${escHtmlShop(orderData.order_no)}</p>` : ""}
        <p class="qr-screen__timer" aria-live="off"></p>
        ${banksHtml}
        ${qrImg}
        <p class="qr-screen__waiting" data-en="Waiting for payment…" data-mn="Төлбөр хүлээгдэж байна…">${escHtmlShop(t("Waiting for payment…", "Төлбөр хүлээгдэж байна…"))}</p>
        <button type="button" class="btn btn--gold qr-screen__check" data-en="Check payment" data-mn="Төлбөр шалгах">${escHtmlShop(t("Check payment", "Төлбөр шалгах"))}</button>
        <p class="qr-screen__link"><span data-en="Come back to this later" data-mn="Дараа буцаж ирэх бол">${escHtmlShop(t("Come back to this later", "Дараа буцаж ирэх бол"))}</span>: ${escHtmlShop(orderLink)}</p>
      </div>
    `;

    // A logo that fails to load is removed so the tile shows just the name,
    // not a broken-image icon.
    items.querySelectorAll(".qr-bank img").forEach((img) => {
      img.addEventListener("error", () => img.remove());
    });

    function setWaiting(enMsg, mnMsg) {
      const waiting = document.querySelector(".qr-screen__waiting");
      if (waiting) {
        waiting.dataset.en = enMsg;
        waiting.dataset.mn = mnMsg;
        waiting.textContent = t(enMsg, mnMsg);
      }
    }

    function handleStatus(status) {
      if (status.status === "paid") {
        stopQrPolling();
        renderConfirmation(token, status);
      } else if (status.status === "expired" || status.status === "cancelled") {
        stopQrPolling();
        showExpired();
      }
    }

    // The QR is no longer payable: hide everything used to pay and offer to
    // start again from the basket, which is still intact.
    function showExpired() {
      const screen = items.querySelector(".qr-screen");
      if (!screen || screen.classList.contains("is-expired")) return;
      screen.classList.add("is-expired");
      screen.querySelectorAll(".qr-screen__img, .qr-screen__banks, .qr-screen__banks-title, .qr-screen__or, .qr-screen__check, .qr-screen__timer, .qr-screen__link")
        .forEach((el) => el.remove());
      setWaiting("Time is up — this QR code no longer works.", "Хугацаа дууссан — энэ QR код ажиллахаа больсон.");
      const again = document.createElement("button");
      again.type = "button";
      again.className = "btn btn--gold qr-screen__again";
      again.textContent = t("Order again", "Дахин захиалах");
      again.addEventListener("click", openCartDrawer);
      screen.appendChild(again);
    }

    // Countdown to the end of the order's 20 minutes (the server's figure,
    // so the phone's clock doesn't matter). At zero: one verified check —
    // the buyer may have paid in the last seconds — then the expired view.
    // Plain polls keep running until the server reports the final status.
    const timerEl = items.querySelector(".qr-screen__timer");
    let secondsLeft = Number.isFinite(Number(orderData.expires_in)) ? Number(orderData.expires_in) : null;
    function paintTimer() {
      if (!timerEl || secondsLeft === null) return;
      const m = Math.floor(secondsLeft / 60);
      const sec = String(secondsLeft % 60).padStart(2, "0");
      timerEl.textContent = `${t("Time left", "Үлдсэн хугацаа")}: ${m}:${sec}`;
      timerEl.classList.toggle("is-low", secondsLeft <= 120);
    }
    if (secondsLeft !== null) {
      paintTimer();
      qrCountdown = setInterval(() => {
        secondsLeft = Math.max(0, secondsLeft - 1);
        paintTimer();
        if (secondsLeft === 0) {
          clearInterval(qrCountdown);
          qrCountdown = null;
          poll(true).then((status) => {
            if (status.status !== "paid") showExpired();
          }).catch(() => showExpired());
        }
      }, 1000);
    }

    // withCheck=true asks the server to verify with QPay (rate-limited to
    // once per 10s per order server-side); plain polls only read our DB,
    // which qpay-callback.php updates — QPay forbids frequent checks.
    function poll(withCheck) {
      return fetch(`/api/order-status.php?token=${encodeURIComponent(token)}${withCheck ? "&check=1" : ""}`)
        .then((r) => r.json())
        .then((status) => { handleStatus(status); return status; });
    }

    const checkBtn = items.querySelector(".qr-screen__check");
    checkBtn.addEventListener("click", () => {
      checkBtn.disabled = true;
      setWaiting("Checking…", "Шалгаж байна…");
      poll(true)
        .then((status) => {
          if (status.status === "pending") {
            setWaiting("Payment not received yet. If you've paid, wait a moment and check again.", "Төлбөр хараахан ирээгүй байна. Төлсөн бол түр хүлээгээд дахин шалгана уу.");
          }
        })
        .catch(() => setWaiting("Could not reach the server — please try again", "Сервертэй холбогдож чадсангүй — дахин оролдоно уу"))
        .finally(() => setTimeout(() => { checkBtn.disabled = false; }, 10000));
    });

    // Coming back to this tab (typically from a bank app on the same phone)
    // triggers one verified check, so the buyer sees the result right away
    // even if QPay's callback is slow.
    qrVisibilityHandler = () => {
      if (document.visibilityState === "visible") poll(true).catch(() => {});
    };
    document.addEventListener("visibilitychange", qrVisibilityHandler);

    qrPollInterval = setInterval(() => {
      poll(false).catch(() => {}); // transient network error — next poll retries
    }, 3000);
  }
  window.renderQrScreen = renderQrScreen;

  function renderConfirmation(token, status) {
    // Paid: empty the basket — but only if this is the order the basket was
    // turned into (an old order link opened later must not empty a new basket).
    try {
      if (localStorage.getItem(PENDING_KEY) === token) {
        setCart([]);
        localStorage.removeItem(PENDING_KEY);
      }
    } catch {}
    const heading = document.querySelector(".cart-drawer__head h2");
    heading.dataset.en = "Payment received";
    heading.dataset.mn = "Төлбөр хүлээн авлаа";
    heading.textContent = t("Payment received", "Төлбөр хүлээн авлаа");
    document.querySelector(".cart-drawer__footer").style.display = "none";

    const items = document.getElementById("cartDrawerItems");
    const orderLink = `${location.origin}/shop?order=${token}`;
    // "Delivery"/"Order link" labels are wrapped in their own data-en/data-mn
    // span (same pattern as #cartDrawerTotal in renderCartDrawer above) so a
    // language toggle only swaps the label, not the dynamic value after it —
    // putting data-en/data-mn on the whole <p> would have setLanguage()
    // overwrite the date/slot/link with the static attribute text.
    items.innerHTML = `
      <div class="order-confirmation">
        <svg class="order-confirmation__check" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
        <p class="order-confirmation__total">${status.total.toLocaleString()}₮</p>
        ${status.order_no ? `<p class="order-confirmation__no"><span>${escHtmlShop(t("Order number", "Захиалгын дугаар"))}</span>: <strong>${escHtmlShop(status.order_no)}</strong></p>` : ""}
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
      showDrawer();
      // check=1: someone opening this link may have just paid (e.g. the phone
      // browser reloaded the page after the bank app) — verify once with QPay.
      fetch(`/api/order-status.php?token=${encodeURIComponent(orderToken)}&check=1`)
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
