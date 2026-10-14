/* js/checkout.js — the /shop/checkout page (shop-checkout.php).
 *
 *   1. Хэрхэн авах вэ?   delivery (fee) or pick-up
 *   2. Хэзээ?            day buttons for that choice
 *   3. Таны мэдээлэл     name, phone, address (delivery only), note
 *   → QPay QR with countdown and bank-app buttons → confirmation
 *
 * Which days, the fee and the pick-up address come from the server
 * (inc/fulfillment.php), embedded as #checkoutOptions and re-read from
 * /api/checkout-options.php when the buyer returns to a tab left open —
 * "Өнөөдөр" disappears at 13:00 and every day moves on at midnight. The
 * order step re-checks the day anyway and answers 409 with fresh choices.
 *
 * The basket is localStorage (js/script.js: getCart/setCart). It is only
 * emptied once the order made from it is PAID (PENDING_KEY): an order that
 * expires leaves the basket ready for "Дахин захиалах".
 *
 * Mongolian only. QPay forbids frequent payment checks: plain polls read
 * our database (updated by qpay-callback.php); QPay itself is asked only
 * after a buyer action, a return to the tab, or the countdown reaching zero.
 */
(() => {
  "use strict";

  const PENDING_KEY = "naf_pending_order";
  const DAY_WORDS = ["Өнөөдөр", "Маргааш", "Нөгөөдөр"];
  const nf = new Intl.NumberFormat("en-US");
  const money = (n) => nf.format(Number(n) || 0) + "₮";

  const $ = (id) => document.getElementById(id);
  const optionsEl = $("checkoutOptions");
  if (!optionsEl) return; // the page could not load its options (server message shown)

  let options = JSON.parse(optionsEl.textContent);
  let products = [];        // from /api/shop-data.php
  let subtotal = 0;
  let qrTimers = [];        // intervals to clear when the QR screen goes away
  let qrVisibility = null;

  function esc(str) {
    const d = document.createElement("div");
    d.textContent = str == null ? "" : String(str);
    return d.innerHTML;
  }
  function escAttr(str) {
    return (str == null ? "" : String(str)).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  /** "Маргааш, 10-р сарын 14, Лхагва" — but "10-р сарын 19, Даваа" when the word is just the weekday. */
  function dayText(word, label) {
    return DAY_WORDS.includes(word) ? `${word}, ${label}` : label;
  }

  function show(which) {
    $("checkoutRoot").hidden = which !== "form";
    $("checkoutPay").hidden = which !== "pay";
    $("checkoutEmpty").hidden = which !== "empty";
    // The button the buyer just pressed is now hidden; without this, keyboard
    // focus is lost and a screen reader says nothing about the new screen.
    if (which === "pay") $("checkoutPay").focus({ preventScroll: true });
  }

  /* ---------------- form ---------------- */

  const form = $("checkoutForm");
  const method = () => (form.querySelector('input[name="fulfillment"]:checked') || {}).value || null;
  const chosenDay = () => (form.querySelector('input[name="delivery_date"]:checked') || {}).value || null;
  const fee = () => (method() === "delivery" ? Number(options.delivery_fee) || 0 : 0);

  function renderDays(keep) {
    const m = method();
    const fs = $("dayFieldset");
    if (!m) { fs.hidden = true; return; }
    const days = options[m] || [];
    $("dayList").innerHTML = days.map((d) => {
      const small = DAY_WORDS.includes(d.word) ? d.date_label : d.date_label.replace(/, [^,]+$/, "");
      return `
        <label class="checkout-day">
          <input type="radio" name="delivery_date" value="${escAttr(d.date)}"${d.date === keep ? " checked" : ""}>
          <span class="checkout-day__body">
            <span class="checkout-day__word">${esc(d.word)}</span>
            <span class="checkout-day__date">${esc(small)}</span>
          </span>
        </label>`;
    }).join("");
    $("dayHint").textContent = m === "pickup"
      ? `${options.office_hours}. Сонгосон өдрөө 18:00 цагаас өмнө ирж авна уу.`
      : "Манай ажилтан хүргэхээс өмнө тантай утсаар холбогдож цагаа тохирно.";
    fs.hidden = false;
  }

  function renderTotals() {
    const f = fee();
    $("sumSubtotal").textContent = money(subtotal);
    $("sumFeeRow").hidden = f === 0;
    $("sumFee").textContent = money(f);
    $("sumTotal").textContent = money(subtotal + f);
    $("summaryMini").textContent = money(subtotal + f);
    $("checkoutSubmitBtn").textContent = `QPay-ээр төлөх · ${money(subtotal + f)}`;
  }

  function onMethodChange() {
    const m = method();
    renderDays(chosenDay());
    $("addressField").hidden = m !== "delivery";
    $("checkoutAddress").required = m === "delivery";
    $("detailsNo").textContent = m ? "3" : "2";
    hideError();
    renderTotals();
  }

  function showError(msg, focusEl) {
    const el = $("checkoutError");
    el.textContent = msg;
    el.hidden = false;
    if (focusEl) focusEl.focus();
  }
  function hideError() { $("checkoutError").hidden = true; }

  /** Basket lines against live prices. Returns false if the basket can't be shown. */
  function renderSummary(cart) {
    const variants = products.flatMap((p) => p.variants.map((v) => ({ ...v, product: p.name_mn })));
    subtotal = 0;
    let missing = 0;
    $("summaryLines").innerHTML = cart.map((line) => {
      const v = variants.find((x) => Number(x.id) === Number(line.variantId));
      if (!v) { missing++; return ""; }
      const total = v.price * line.quantity;
      subtotal += total;
      return `
        <li class="checkout-summary__line">
          <span class="checkout-summary__name">${esc(v.name_mn === v.product ? v.product : `${v.product} — ${v.name_mn}`)}${v.weight_label ? ` <span class="checkout-summary__muted">(${esc(v.weight_label)})</span>` : ""}</span>
          <span class="checkout-summary__qty">${line.quantity} ш</span>
          <span class="checkout-summary__price">${money(total)}</span>
        </li>`;
    }).join("");
    if (missing) {
      $("summaryLines").insertAdjacentHTML("beforeend",
        `<li class="checkout-summary__warn">Сагсан дахь ${missing} бараа зарагдаж дууссан. <a href="/shop#cart">Сагсаа засна уу.</a></li>`);
    }
    renderTotals();
    return subtotal > 0;
  }

  async function refreshOptions() {
    try {
      const res = await fetch("/api/checkout-options.php", { cache: "no-store" });
      if (!res.ok) return;
      options = await res.json();
      renderDays(chosenDay()); // keeps the selection only if that day is still offered
      renderTotals();
    } catch { /* offline — the order step re-checks anyway */ }
  }

  form.addEventListener("change", (e) => {
    if (e.target.name === "fulfillment") onMethodChange();
    if (e.target.name === "delivery_date") hideError();
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    hideError();
    const m = method();
    if (!m) return showError("Хүргүүлэх эсвэл очиж авахаа сонгоно уу", form.querySelector('input[name="fulfillment"]'));
    const day = chosenDay();
    if (!day) return showError("Өдрөө сонгоно уу", form.querySelector('input[name="delivery_date"]'));
    const name = $("checkoutName").value.trim();
    if (!name) return showError("Нэрээ бичнэ үү", $("checkoutName"));
    // Same rule as order-create.php: 8 digits; spaces, dashes, +976 allowed.
    const phone = $("checkoutPhone").value.replace(/[\s\-().]/g, "").replace(/^\+?976(?=\d{8}$)/, "");
    if (!/^\d{8}$/.test(phone)) return showError("Утасны дугаар 8 оронтой байх ёстой", $("checkoutPhone"));
    const address = $("checkoutAddress").value.trim();
    if (m === "delivery" && !address) return showError("Хүргэлтийн хаягаа бичнэ үү", $("checkoutAddress"));

    const btn = $("checkoutSubmitBtn");
    btn.disabled = true;
    btn.textContent = "Түр хүлээнэ үү…";
    try {
      const res = await fetch("/api/order-create.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          items: getCart().map((l) => ({ variant_id: l.variantId, quantity: l.quantity })),
          buyer_name: name,
          buyer_phone: phone,
          buyer_address: m === "delivery" ? address : "",
          buyer_note: $("checkoutNote").value.trim(),
          fulfillment: m,
          delivery_date: day,
          // The total this page shows; the server refuses a different one.
          expected_total: subtotal + fee(),
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (res.status === 409 && data.options) {
        options = data.options;
        if (data.reason === "total") {
          // The basket, a price or the fee changed since the page drew it:
          // redraw from the current basket and live prices; the buyer checks
          // and presses again.
          await loadProducts();
          renderDays(chosenDay());
          renderSummary(getCart());
          showError(data.error);
          return;
        }
        // The day is no longer offered (13:00 passed, or midnight): redraw.
        renderDays(null);
        renderTotals();
        showError(data.error || "Сонгосон өдөр боломжгүй болсон — өөр өдөр сонгоно уу", form.querySelector('input[name="delivery_date"]'));
        return;
      }
      if (!res.ok) {
        showError(data.error || "Алдаа гарлаа — дахин оролдоно уу");
        return;
      }
      try { localStorage.setItem(PENDING_KEY, data.token); } catch {}
      history.replaceState(null, "", `/shop/checkout?order=${encodeURIComponent(data.token)}`);
      renderQrScreen(data);
    } catch {
      showError("Сервертэй холбогдож чадсангүй — дахин оролдоно уу");
    } finally {
      btn.disabled = false;
      renderTotals(); // the current total — it may have just been redrawn
    }
  });

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible" && !$("checkoutRoot").hidden) refreshOptions();
  });

  // The basket changed in another tab: redraw the summary so it never shows
  // a basket other than the one that will be sent. (Edits in this page's own
  // basket drawer are caught on submit by expected_total.)
  window.addEventListener("storage", (e) => {
    if (e.key === "naf_cart" && !$("checkoutRoot").hidden) renderSummary(getCart());
  });

  async function loadProducts() {
    try {
      const res = await fetch("/api/shop-data.php", { cache: "no-store" });
      products = (await res.json()).products || [];
    } catch {
      /* keep the last list */
    }
  }

  /* ---------------- QR screen ---------------- */

  function stopQr() {
    qrTimers.forEach(clearInterval);
    qrTimers = [];
    if (qrVisibility) document.removeEventListener("visibilitychange", qrVisibility);
    qrVisibility = null;
  }

  function orderLink(token) {
    return `${location.origin}/shop/checkout?order=${token}`;
  }

  function renderQrScreen(order) {
    stopQr();
    show("pay");
    const token = order.token;
    const banks = Array.isArray(order.banks) ? order.banks : [];
    const pay = $("checkoutPay");
    pay.innerHTML = `
      <div class="qr-screen${banks.length ? " has-banks" : ""}">
        <h2 class="checkout-pay__title">QPay-ээр төлөх</h2>
        <p class="qr-screen__total">${money(order.total)}</p>
        ${order.order_no ? `<p class="qr-screen__no">Захиалга ${esc(order.order_no)}</p>` : ""}
        <p class="qr-screen__timer" aria-live="off"></p>
        ${banks.length ? `
          <p class="qr-screen__banks-title">Банкны аппаа сонгоно уу</p>
          <div class="qr-screen__banks">${banks.map((b) => `
            <a class="qr-bank" href="${escAttr(b.link)}">
              ${b.logo ? `<img src="${escAttr(b.logo)}" alt="" loading="lazy">` : ""}
              <span>${esc(b.name)}</span>
            </a>`).join("")}
          </div>
          <p class="qr-screen__or">эсвэл өөр утаснаас QR уншуулна уу</p>` : ""}
        ${order.qr_image ? `<img class="qr-screen__img" src="data:image/png;base64,${escAttr(order.qr_image)}" alt="QPay QR код">` : ""}
        <p class="qr-screen__waiting" aria-live="polite">Төлбөр хүлээгдэж байна…</p>
        <button type="button" class="btn btn--gold qr-screen__check">Төлбөр шалгах</button>
        <p class="qr-screen__link">Дараа буцаж ирэх бол: ${esc(orderLink(token))}</p>
      </div>`;
    pay.querySelectorAll(".qr-bank img").forEach((img) => img.addEventListener("error", () => img.remove()));
    window.scrollTo({ top: 0 });

    const screen = pay.querySelector(".qr-screen");
    const setWaiting = (msg) => { const w = screen.querySelector(".qr-screen__waiting"); if (w) w.textContent = msg; };

    function handle(status) {
      if (status.status === "paid") { stopQr(); renderConfirmation(token, status); }
      else if (status.status === "expired" || status.status === "cancelled") { stopQr(); showExpired(); }
      return status;
    }
    // withCheck asks the server to verify with QPay (rate-limited server-side).
    function poll(withCheck) {
      return fetch(`/api/order-status.php?token=${encodeURIComponent(token)}${withCheck ? "&check=1" : ""}`)
        .then((r) => r.json()).then(handle);
    }

    function showExpired() {
      if (screen.classList.contains("is-expired")) return;
      screen.classList.add("is-expired");
      screen.querySelectorAll(".qr-screen__img, .qr-screen__banks, .qr-screen__banks-title, .qr-screen__or, .qr-screen__check, .qr-screen__timer, .qr-screen__link")
        .forEach((el) => el.remove());
      setWaiting("Хугацаа дууссан — энэ QR код ажиллахаа больсон.");
      const again = document.createElement("a");
      again.className = "btn btn--gold qr-screen__again";
      again.href = "/shop#cart";
      again.textContent = "Дахин захиалах";
      screen.appendChild(again);
    }

    // Countdown from the server's figure (the phone's clock doesn't matter).
    const timer = screen.querySelector(".qr-screen__timer");
    let left = Number.isFinite(Number(order.expires_in)) ? Number(order.expires_in) : null;
    const paint = () => {
      if (left === null) return;
      timer.textContent = `Үлдсэн хугацаа: ${Math.floor(left / 60)}:${String(left % 60).padStart(2, "0")}`;
      timer.classList.toggle("is-low", left <= 120);
    };
    if (left !== null) {
      paint();
      const id = setInterval(() => {
        left = Math.max(0, left - 1);
        paint();
        if (left === 0) {
          clearInterval(id);
          poll(true).then((s) => { if (s.status !== "paid") showExpired(); }).catch(showExpired);
        }
      }, 1000);
      qrTimers.push(id);
    }

    const check = screen.querySelector(".qr-screen__check");
    check.addEventListener("click", () => {
      check.disabled = true;
      setWaiting("Шалгаж байна…");
      poll(true)
        .then((s) => { if (s.status === "pending") setWaiting("Төлбөр хараахан ирээгүй байна. Төлсөн бол түр хүлээгээд дахин шалгана уу."); })
        .catch(() => setWaiting("Сервертэй холбогдож чадсангүй — дахин оролдоно уу"))
        .finally(() => setTimeout(() => { check.disabled = false; }, 10000));
    });

    // Back from the bank app on the same phone: one verified check.
    qrVisibility = () => { if (document.visibilityState === "visible") poll(true).catch(() => {}); };
    document.addEventListener("visibilitychange", qrVisibility);
    qrTimers.push(setInterval(() => poll(false).catch(() => {}), 3000));
  }

  /* ---------------- confirmation ---------------- */

  function renderConfirmation(token, s) {
    // Empty the basket — only if it was turned into THIS order (an old order
    // link opened later must not empty a new basket).
    try {
      if (localStorage.getItem(PENDING_KEY) === token) {
        setCart([]);
        localStorage.removeItem(PENDING_KEY);
      }
    } catch {}
    show("pay");
    let plan;
    if (s.delivery_slot) { // an order from before the 2026-10 checkout
      plan = `<p><strong>Хүргэлт:</strong> ${esc(s.date_label)} · ${esc(s.delivery_slot)}</p>`;
    } else if (s.fulfillment === "pickup") {
      plan = `<p><strong>Очиж авах:</strong> ${esc(dayText(s.day_word, s.date_label))}, 18:00 хүртэл</p>
              <p>${esc(options.pickup_address)} · <a href="${escAttr(options.map_url)}" target="_blank" rel="noopener">Газрын зураг ↗</a></p>`;
    } else {
      plan = `<p><strong>Хүргэлт:</strong> ${esc(dayText(s.day_word, s.date_label))}</p>
              <p>Манай ажилтан тантай холбогдож цагаа тохирно.</p>`;
    }
    $("checkoutPay").innerHTML = `
      <div class="order-confirmation">
        <svg class="order-confirmation__check" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m7.5 12.5 3 3 6-6.5"/></svg>
        <h2 class="checkout-pay__title">Төлбөр хүлээн авлаа</h2>
        <p class="order-confirmation__total">${money(s.total)}</p>
        ${s.order_no ? `<p class="order-confirmation__no">Захиалгын дугаар: <strong>${esc(s.order_no)}</strong></p>` : ""}
        <div class="order-confirmation__plan">${plan}</div>
        <p class="order-confirmation__link">Захиалгын холбоос: ${esc(orderLink(token))}</p>
        <a class="btn order-confirmation__back" href="/shop">Дэлгүүр рүү буцах</a>
      </div>`;
    window.scrollTo({ top: 0 });
  }

  function renderMessage(title, body) {
    show("pay");
    $("checkoutPay").innerHTML = `
      <div class="order-confirmation">
        <h2 class="checkout-pay__title">${esc(title)}</h2>
        <p>${esc(body)}</p>
        <a class="btn btn--gold" href="/shop">Дэлгүүр рүү буцах</a>
      </div>`;
  }

  /* ---------------- start ---------------- */

  async function start() {
    const token = new URLSearchParams(location.search).get("order");
    if (token) {
      // The order link. check=1: someone opening it may have just paid.
      try {
        const res = await fetch(`/api/order-status.php?token=${encodeURIComponent(token)}&check=1`);
        const s = await res.json();
        if (s.status === "paid" || s.status === "fulfilled") renderConfirmation(token, s);
        else if (s.status === "pending") renderQrScreen({ ...s, token });
        else if (s.status === "expired" || s.status === "cancelled") renderMessage("Захиалгын хугацаа дууссан", "Энэ захиалга идэвхгүй боллоо. Сагсаа шалгаад дахин захиална уу.");
        else renderMessage("Захиалга олдсонгүй", "Тухайн захиалга олдсонгүй.");
      } catch {
        renderMessage("Захиалга олдсонгүй", "Тухайн захиалга олдсонгүй.");
      }
      return;
    }

    const cart = typeof getCart === "function" ? getCart() : [];
    if (!cart.length) { show("empty"); return; }
    await loadProducts();
    show("form");
    if (!products.length) {
      $("summaryLines").innerHTML = `<li class="checkout-summary__warn">Сагсаа ачаалж чадсангүй — хуудсаа дахин ачаална уу.</li>`;
      $("checkoutSubmitBtn").disabled = true;
      return;
    }
    if (!renderSummary(cart)) $("checkoutSubmitBtn").disabled = true;
    onMethodChange();
  }

  document.addEventListener("DOMContentLoaded", start);
})();
