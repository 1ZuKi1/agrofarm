/* ==========================================================================
   Equipment catalogue — the inquiry list.

   Same shape as the shop's cart, minus everything to do with money: it holds
   {sku, name, quantity} and the whole list is submitted as one inquiry. It
   shows up in the shared basket drawer (shop.js) below the priced lines. The
   catalogue is server-rendered, so this file only adds the list, the gallery
   and the after-submit banner — never the page itself.
   ========================================================================== */
(() => {
  "use strict";

  const KEY = "naf_inquiry";

  const read = () => {
    try {
      const raw = JSON.parse(localStorage.getItem(KEY) || "[]");
      return Array.isArray(raw) ? raw : [];
    } catch {
      return [];
    }
  };
  const write = (items) => {
    try { localStorage.setItem(KEY, JSON.stringify(items)); } catch { /* private mode */ }
    if (window.updateCartBadge) window.updateCartBadge();
    if (window.renderCartDrawer) window.renderCartDrawer();
  };

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => (
    { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]
  ));

  /* ---------- the quote half of the basket ----------
     One basket drawer on every shop page (inc/layout.php), owned by shop.js.
     shop.js calls this after drawing the priced lines; it appends the quote
     lines and the inquiry form, and says whether there were any. */
  function renderInquirySection(container) {
    const items = read();
    if (!items.length || !container) return false;

    const box = document.createElement("div");
    box.className = "cart-drawer__quotes";
    box.innerHTML = `
      <p class="cart-drawer__group">Үнийн санал авах бараа</p>
      ${items.map((i) => `
      <div class="cart-drawer__item">
        <div>
          <p class="cart-drawer__item-name">${esc(i.name)}</p>
          <p class="cart-drawer__sku">${esc(i.sku)}</p>
        </div>
        <input type="number" class="cart-drawer__qty" min="1" max="9999"
               value="${Number(i.quantity) || 1}" data-sku="${esc(i.sku)}" aria-label="Тоо ширхэг">
        <button type="button" class="cart-drawer__remove" data-sku="${esc(i.sku)}"
                aria-label="Хасах">✕</button>
      </div>`).join("")}
      <form class="eq-form eq-form--drawer" method="post" action="/inquiry-create.php">
        <input type="hidden" name="items" class="inquiry-payload">
        <div class="eq-form__hp" aria-hidden="true">
          <label>Хаяг<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <label>Нэр <span>*</span><input type="text" name="name" required maxlength="255"></label>
        <label>Утас <span>*</span><input type="tel" name="phone" required maxlength="50"></label>
        <label>И-мэйл<input type="email" name="email" maxlength="255"></label>
        <label>Нэмэлт мэдээлэл<textarea name="message" rows="3" maxlength="2000"></textarea></label>
        <button type="submit" class="btn btn--gold">Үнийн санал хүсэх (${items.length})</button>
      </form>`;
    container.appendChild(box);

    box.querySelectorAll(".cart-drawer__qty").forEach((input) => {
      input.addEventListener("change", () => {
        const q = Math.max(1, Math.min(9999, parseInt(input.value, 10) || 1));
        write(read().map((i) => (i.sku === input.dataset.sku ? { ...i, quantity: q } : i)));
      });
    });
    box.querySelectorAll(".cart-drawer__remove").forEach((btn) => {
      btn.addEventListener("click", () => write(read().filter((i) => i.sku !== btn.dataset.sku)));
    });
    box.querySelector("form").addEventListener("submit", (e) => {
      e.target.querySelector(".inquiry-payload").value = JSON.stringify(read());
      // The list is cleared on the page the server sends back with
      // ?inquiry=ok (below) — only once the inquiry is really stored.
    });
    return true;
  }
  window.renderInquirySection = renderInquirySection;

  /* ---------- add to list (equipment product page) ---------- */
  const addBtn = document.getElementById("eqAddBtn");
  if (addBtn) {
    const host = document.querySelector(".eq-detail");
    const label = addBtn.textContent;
    addBtn.addEventListener("click", () => {
      const sku = host?.dataset.sku;
      const name = host?.dataset.name;
      if (!sku) return;
      const items = read();
      const found = items.find((i) => i.sku === sku);
      if (found) found.quantity += 1;
      else items.push({ sku, name, quantity: 1 });
      write(items);
      addBtn.textContent = "Жагсаалтад нэмэгдлээ ✓";
      setTimeout(() => { addBtn.textContent = label; }, 1800);
      if (window.openCartDrawer) window.openCartDrawer();
    });
  }

  /* ---------- gallery ---------- */
  const main = document.getElementById("eqGalleryMain");
  document.querySelectorAll(".eq-gallery__thumb").forEach((thumb) => {
    thumb.addEventListener("click", () => {
      if (!main) return;
      main.src = thumb.dataset.src;
      document.querySelectorAll(".eq-gallery__thumb")
        .forEach((t) => t.classList.toggle("is-active", t === thumb));
    });
  });

  /* ---------- result banner after a submit ---------- */
  const params = new URLSearchParams(location.search);
  const status = params.get("inquiry");
  if (status) {
    const messages = {
      ok: ["Хүсэлт хүлээн авлаа. Бид удахгүй холбогдоно.", "is-ok"],
      missing: ["Нэр, утасны дугаараа бөглөнө үү.", "is-err"],
      bademail: ["И-мэйл хаяг буруу байна.", "is-err"],
      empty: ["Жагсаалт хоосон байна.", "is-err"],
      error: ["Түр зуурын алдаа гарлаа. Дахин оролдоно уу.", "is-err"],
    };
    const [text, cls] = messages[status] || messages.error;
    if (status === "ok") {
      try { localStorage.removeItem(KEY); } catch {}
      if (window.updateCartBadge) window.updateCartBadge();
    }
    const bar = document.createElement("div");
    bar.className = "eq-flash " + cls;
    bar.textContent = text;
    document.querySelector("main")?.prepend(bar);
    setTimeout(() => bar.remove(), 8000);
  }
})();
