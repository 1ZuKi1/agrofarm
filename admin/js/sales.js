/* admin/js/sales.js — the Борлуулалт chart's tooltip (admin/sales.php).
 *
 * Each column carries its numbers in data-tip-value / data-tip-label; the
 * same words are its aria-label and are in the table under the chart, so the
 * tooltip only repeats what is already readable. Shown on hover and on
 * keyboard focus. Text goes in with textContent.
 */
(() => {
  "use strict";
  const chart = document.querySelector("[data-chart]");
  if (!chart) return;
  const tip = chart.querySelector(".ad-chart__tip");
  const value = tip.querySelector("strong");
  const label = tip.querySelector("span");

  function show(col) {
    value.textContent = col.dataset.tipValue;
    label.textContent = col.dataset.tipLabel;
    tip.hidden = false;
    const box = chart.getBoundingClientRect();
    const c = col.getBoundingClientRect();
    const bar = col.querySelector(".ad-chart__bar").getBoundingClientRect();
    const half = tip.offsetWidth / 2;
    const x = Math.min(Math.max(c.left + c.width / 2 - box.left, half), box.width - half);
    const y = Math.min(bar.top, c.bottom) - box.top - tip.offsetHeight - 8;
    tip.style.left = `${x - half}px`;
    tip.style.top = `${Math.max(0, y)}px`;
  }
  function hide() { tip.hidden = true; }

  chart.addEventListener("pointerover", (e) => {
    const col = e.target.closest(".ad-chart__col");
    if (col) show(col);
  });
  chart.addEventListener("pointerleave", hide);
  chart.addEventListener("focusin", (e) => {
    const col = e.target.closest(".ad-chart__col");
    if (col) show(col);
  });
  chart.addEventListener("focusout", hide);
})();
