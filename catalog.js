/* ==========================================================================
   Equipment catalogue — the inquiry list.

   Same shape as the shop's cart, minus everything to do with money: it holds
   {sku, name, quantity} and the whole list is submitted as one inquiry. The
   catalogue is server-rendered, so this file only adds the list, the drawer
   and the gallery — never the page itself.
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
    paintBadge();
    paintDrawer();
  };

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => (
    { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]
  ));

  /* ---------- badge ---------- */
  function paintBadge() {
    const badge = document.getElementById("inquiryBadge");
    if (!badge) return;
    const n = read().reduce((sum, i) => sum + i.quantity, 0);
    badge.textContent = String(n);
    badge.hidden = n === 0;
  }

  /* ---------- drawer ---------- */
  const drawer = document.getElementById("inquiryDrawer");

  function openDrawer() { drawer?.classList.add("is-open"); document.body.style.overflow = "hidden"; }
  function closeDrawer() { drawer?.classList.remove("is-open"); document.body.style.overflow = ""; }

  function paintDrawer() {
    const list = document.getElementById("inquiryItems");
    const foot = document.getElementById("inquiryFooter");
    if (!list || !foot) return;

    const items = read();
    if (!items.length) {
      list.innerHTML = '<p class="cart-drawer__empty">Жагсаалт хоосон байна.</p>';
      foot.innerHTML = "";
      return;
    }

    list.innerHTML = items.map((i) => `
      <div class="cart-drawer__item">
        <div>
          <p class="cart-drawer__item-name">${esc(i.name)}</p>
          <p class="cart-drawer__sku">${esc(i.sku)}</p>
        </div>
        <input type="number" class="cart-drawer__qty" min="1" max="9999"
               value="${i.quantity}" data-sku="${esc(i.sku)}" aria-label="Тоо ширхэг">
        <button type="button" class="cart-drawer__remove" data-sku="${esc(i.sku)}"
                aria-label="Хасах">✕</button>
      </div>`).join("");

    foot.innerHTML = `
      <form class="eq-form eq-form--drawer" method="post" action="/inquiry-create.php">
        <input type="hidden" name="items" id="inquiryPayload">
        <div class="eq-form__hp" aria-hidden="true">
          <label>Хаяг<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <label>Нэр <span>*</span><input type="text" name="name" required maxlength="255"></label>
        <label>Утас <span>*</span><input type="tel" name="phone" required maxlength="50"></label>
        <label>И-мэйл<input type="email" name="email" maxlength="255"></label>
        <label>Нэмэлт мэдээлэл<textarea name="message" rows="3" maxlength="2000"></textarea></label>
        <button type="submit" class="btn btn--gold">Хүсэлт илгээх (${items.length})</button>
      </form>`;

    list.querySelectorAll(".cart-drawer__qty").forEach((input) => {
      input.addEventListener("change", () => {
        const q = Math.max(1, Math.min(9999, parseInt(input.value, 10) || 1));
        const next = read().map((i) => (i.sku === input.dataset.sku ? { ...i, quantity: q } : i));
        write(next);
      });
    });
    list.querySelectorAll(".cart-drawer__remove").forEach((btn) => {
      btn.addEventListener("click", () => {
        write(read().filter((i) => i.sku !== btn.dataset.sku));
      });
    });

    const form = foot.querySelector("form");
    form?.addEventListener("submit", () => {
      document.getElementById("inquiryPayload").value = JSON.stringify(read());
      // The list is cleared only after the browser has posted it.
      setTimeout(() => { try { localStorage.removeItem(KEY); } catch {} }, 250);
    });
  }

  document.getElementById("inquiryToggle")?.addEventListener("click", openDrawer);
  document.getElementById("inquiryClose")?.addEventListener("click", closeDrawer);
  document.getElementById("inquiryBackdrop")?.addEventListener("click", closeDrawer);
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeDrawer(); });

  /* ---------- add to list ---------- */
  const addBtn = document.getElementById("eqAddBtn");
  if (addBtn) {
    const host = document.querySelector(".eq-detail");
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
      setTimeout(() => { addBtn.textContent = "Хүсэлтийн жагсаалтад нэмэх"; }, 1800);
      openDrawer();
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
    const bar = document.createElement("div");
    bar.className = "eq-flash " + cls;
    bar.textContent = text;
    document.querySelector("main")?.prepend(bar);
    if (status === "ok") closeDrawer();
    setTimeout(() => bar.remove(), 8000);
  }

  paintBadge();
  paintDrawer();
})();
