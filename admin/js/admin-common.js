/* admin/js/admin-common.js — loaded on every /admin/ page (inc/admin-layout.php).
 *
 *   - the phone menu ("Цэс"): opens the sidebar as a panel, Escape or a tap
 *     outside closes it, focus goes back to the button;
 *   - data-confirm on a form or button: asks before submitting;
 *   - window.NAF.* helpers for the product and news editors: escaping,
 *     money, dates, the CSRF token (from <meta name="csrf-token">), and a
 *     fetch wrapper that sends staff back to the login page when the
 *     session has ended.
 */
(() => {
  "use strict";

  // ---- phone menu
  const btn = document.querySelector(".ad-menu-btn");
  const side = document.getElementById("adSide");
  const scrim = document.querySelector(".ad-scrim");
  function setMenu(open) {
    if (!btn || !side) return;
    btn.setAttribute("aria-expanded", String(open));
    side.classList.toggle("is-open", open);
    if (scrim) scrim.hidden = !open;
    if (open) {
      const first = side.querySelector("a, button");
      if (first) first.focus();
    }
  }
  if (btn && side) {
    btn.addEventListener("click", () => setMenu(btn.getAttribute("aria-expanded") !== "true"));
    if (scrim) scrim.addEventListener("click", () => { setMenu(false); btn.focus(); });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && side.classList.contains("is-open")) { setMenu(false); btn.focus(); }
    });
    matchMedia("(min-width: 901px)").addEventListener("change", (m) => { if (m.matches) setMenu(false); });
  }

  // ---- confirm before an action
  document.addEventListener("submit", (e) => {
    const form = e.target;
    const by = e.submitter;
    const msg = (by && by.dataset.confirm) || form.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // ---- helpers shared by the editors
  function escHtml(str) {
    const d = document.createElement("div");
    d.textContent = str == null ? "" : String(str);
    return d.innerHTML;
  }
  function escAttr(str) {
    return (str == null ? "" : String(str)).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }
  function csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : "";
  }
  function toFormBody(params) {
    return Object.entries(params).map(([k, v]) => encodeURIComponent(k) + "=" + encodeURIComponent(v == null ? "" : v)).join("&");
  }
  /** POST to an admin endpoint; a 401 sends you to the login page and back. */
  async function post(url, body) {
    const isForm = body instanceof FormData;
    if (isForm) body.append("csrf", csrfToken());
    const res = await fetch(url, {
      method: "POST",
      headers: isForm ? { "X-CSRF-Token": csrfToken() } : { "Content-Type": "application/x-www-form-urlencoded", "X-CSRF-Token": csrfToken() },
      body: isForm ? body : toFormBody({ ...body, csrf: csrfToken() }),
    });
    if (res.status === 401) {
      location.href = "/admin/login?next=" + encodeURIComponent(location.pathname);
      throw new Error("Нэвтрэх хугацаа дууссан");
    }
    return res;
  }
  const nf = new Intl.NumberFormat("en-US");
  function money(n) { return nf.format(Number(n) || 0) + "₮"; }
  function fmtDate(value) {
    if (!value) return "";
    const d = new Date(String(value).replace(" ", "T"));
    if (isNaN(d)) return String(value);
    return `${d.getMonth() + 1}/${d.getDate()}/${d.getFullYear()}`;
  }
  window.NAF = { escHtml, escAttr, csrfToken, toFormBody, post, money, fmtDate };
})();
