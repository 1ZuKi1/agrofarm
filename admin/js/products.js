/* admin/js/products.js — /admin/products: the product and option (төрөл)
 * editors, moved from the old one-page admin.js. Data comes from
 * /admin/products-admin.php (JSON); NAF.post (admin-common.js) adds the CSRF
 * token and sends staff to the login page when the session has ended.
 *
 *   renderProductsTab()   fetch the list, then paintProducts()
 *   paintProducts()       stats, the stock tabs and the table, from the cache
 *   openProductEditor()   dialog: slug, names, category, descriptions, active
 *   openVariantEditor()   dialog: names, price, stock, texts, image, ingredients
 */
(() => {
  "use strict";
  const { escHtml, escAttr, money, post, siteUrl } = window.NAF;

  const productsTab = document.getElementById("productsTab");
  const productEditor = document.getElementById("productEditor");
  const searchBox = document.getElementById("productSearch");

  let productsCache = [];
  let categoriesCache = []; // [{id, name}] for the product form's "Ангилал"
  const categoryName = (id) => (categoriesCache.find((c) => Number(c.id) === Number(id)) || {}).name || "";
  let productFilter = "all";
  let searchTerm = "";
  let lastFocus = null;

  const NET_ERROR = "Серверт холбогдож чадсангүй — интернэтээ шалгаад дахин оролдоно уу.";

  /** Case-insensitive "does any of these fields contain the search term". */
  function matchesSearch(...fields) {
    if (!searchTerm) return true;
    return fields.some((f) => String(f ?? "").toLowerCase().includes(searchTerm));
  }

  async function readJson(res) {
    try { return await res.json(); } catch { return {}; }
  }

  /** Load and render the products + variants list. */
  async function renderProductsTab() {
    if (!productsCache.length) productsTab.innerHTML = `<p class="ad-empty">Ачааллаж байна…</p>`;
    try {
      const res = await post("/admin/products-admin.php", { action: "list" });
      const data = await readJson(res);
      if (!res.ok) {
        productsTab.innerHTML = `<p class="ad-flash ad-flash--err" role="alert">${escHtml(data.error || "Бүтээгдэхүүн ачаалахад алдаа гарлаа")}</p>`;
        return;
      }
      productsCache = data.products || [];
      categoriesCache = data.categories || [];
      paintProducts();
    } catch (err) {
      if (!String(err.message).includes("Нэвтрэх")) productsTab.innerHTML = `<p class="ad-flash ad-flash--err" role="alert">${NET_ERROR}</p>`;
    }
  }

  /** An option is "low" once availability drops to this or below. */
  const LOW_STOCK = 5;

  function variantState(v) {
    if (Number(v.active) === 0) return "hidden";
    if (v.available <= 0) return "out";
    if (v.available <= LOW_STOCK) return "low";
    return "in";
  }

  /** Stats + filter tabs + the table, from productsCache (no fetch). */
  function paintProducts() {
    const allVariants = productsCache.flatMap((p) => p.variants);
    const counts = {
      all: allVariants.length,
      in: allVariants.filter((v) => variantState(v) === "in").length,
      low: allVariants.filter((v) => variantState(v) === "low").length,
      out: allVariants.filter((v) => variantState(v) === "out").length,
    };
    const unitsAvailable = allVariants.reduce((sum, v) => sum + Number(v.available || 0), 0);
    const restock = counts.low + counts.out;

    document.getElementById("productStats").innerHTML = `
      <div class="ad-stat"><strong>${productsCache.length}</strong><span>Бүтээгдэхүүн</span></div>
      <div class="ad-stat"><strong>${counts.all}</strong><span>Төрөл</span></div>
      <div class="ad-stat"><strong>${unitsAvailable.toLocaleString("en-US")}</strong><span>Бэлэн тоо</span></div>
      <div class="ad-stat${restock > 0 ? " is-hot" : ""}"><strong>${restock}</strong><span>Нөөц дүүргэх шаардлагатай</span></div>
    `;

    const tabs = [
      ["all", "Бүгд", counts.all],
      ["in", "Нөөцөд бий", counts.in],
      ["low", "Бага нөөцтэй", counts.low],
      ["out", "Дууссан", counts.out],
    ];
    const tabsEl = document.getElementById("productTabs");
    tabsEl.innerHTML = tabs.map(([key, label, n]) =>
      `<button type="button" data-filter="${key}" aria-pressed="${productFilter === key}">${label} <span>${n}</span></button>`
    ).join("");
    tabsEl.querySelectorAll("button").forEach((btn) => {
      btn.addEventListener("click", () => { productFilter = btn.dataset.filter; paintProducts(); });
    });

    if (productsCache.length === 0) {
      productsTab.innerHTML = `<p class="ad-empty">Одоогоор бүтээгдэхүүн алга — эхнийг нэмэхийн тулд "Бүтээгдэхүүн нэмэх" дээр дарна уу.</p>`;
      return;
    }

    const STATUS = {
      in:     ["ad-tag--ok", "Нөөцөд бий"],
      low:    ["ad-tag--warn", "Бага нөөцтэй"],
      out:    ["ad-tag--bad", "Дууссан"],
      hidden: ["ad-tag--mute", "Нуугдсан"],
    };

    // Each product contributes a header row plus its matching options; a
    // product whose options are all filtered out drops away entirely.
    const groups = productsCache.map((product) => {
      const visible = product.variants.filter((v) => {
        const state = variantState(v);
        const passesFilter = productFilter === "all" || state === productFilter;
        return passesFilter && matchesSearch(v.name_mn, v.name_en, product.name_mn, product.name_en);
      });
      return { product, visible };
    }).filter((g) => g.visible.length > 0 || (productFilter === "all" && matchesSearch(g.product.name_mn, g.product.name_en)));

    if (groups.length === 0) {
      productsTab.innerHTML = `<p class="ad-empty">Энэ шүүлтэд тохирох зүйл алга.</p>`;
      return;
    }

    productsTab.innerHTML = `
      <div class="ad-table-wrap">
        <table class="ad-table ad-table--cards">
          <thead><tr>
            <th scope="col">Төрөл</th><th scope="col">Жин</th><th scope="col" class="ad-r">Үнэ</th><th scope="col" class="ad-r">Нөөц</th>
            <th scope="col" class="ad-r">Бэлэн</th><th scope="col">Төлөв</th><th scope="col"><span class="ad-sr">Үйлдэл</span></th>
          </tr></thead>
          <tbody>
            ${groups.map(({ product, visible }) => `
              <tr class="ad-group">
                <td colspan="7">
                  <div class="ad-group__row">
                    <div>
                      <strong>${escHtml(product.name_mn)}</strong>${Number(product.active) ? "" : ` <span class="ad-tag ad-tag--mute">Нуугдсан</span>`}
                      <span class="ad-sub">${product.category_id ? escHtml(categoryName(product.category_id)) : "Ангилалгүй — дэлгүүрт «Бусад» хэсэгт гарна"}</span>
                    </div>
                    <div class="ad-btn-row">
                      <button type="button" class="ad-btn ad-btn--sm edit-product-btn" data-id="${product.id}">Бүтээгдэхүүн засах</button>
                      <button type="button" class="ad-btn ad-btn--sm add-variant-btn" data-product-id="${product.id}">+ Төрөл нэмэх</button>
                    </div>
                  </div>
                </td>
              </tr>
              ${visible.length === 0
                ? `<tr><td colspan="7" class="ad-muted ad-small">Одоогоор төрөл алга.</td></tr>`
                : visible.map((v) => {
                    const [cls, label] = STATUS[variantState(v)];
                    return `
                      <tr>
                        <td><strong>${escHtml(v.name_mn)}</strong>${v.name_en ? `<span class="ad-sub">${escHtml(v.name_en)}</span>` : ""}</td>
                        <td>${escHtml(v.weight_label || "—")}</td>
                        <td class="ad-r ad-num">${money(v.price)}</td>
                        <td class="ad-r ad-num"><span class="ad-cardlabel">Нөөц </span>${v.stock}</td>
                        <td class="ad-r ad-num"><span class="ad-cardlabel">Бэлэн </span>${v.available}</td>
                        <td><span class="ad-tag ${cls}">${label}</span></td>
                        <td><button type="button" class="ad-btn ad-btn--sm edit-variant-btn" data-id="${v.id}" data-product-id="${product.id}">Засах<span class="ad-sr"> — ${escHtml(v.name_mn)}</span></button></td>
                      </tr>
                    `;
                  }).join("")}
            `).join("")}
          </tbody>
        </table>
      </div>
    `;

    productsTab.querySelectorAll(".edit-product-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        openProductEditor(productsCache.find((p) => Number(p.id) === Number(btn.dataset.id)));
      });
    });
    productsTab.querySelectorAll(".edit-variant-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        const productId = Number(btn.dataset.productId);
        const product = productsCache.find((p) => Number(p.id) === productId);
        const variant = product && product.variants.find((v) => Number(v.id) === Number(btn.dataset.id));
        openVariantEditor(variant, productId);
      });
    });
    productsTab.querySelectorAll(".add-variant-btn").forEach((btn) => {
      btn.addEventListener("click", () => openVariantEditor(null, Number(btn.dataset.productId)));
    });
  }

  // ---- the dialog ----------------------------------------------------------

  function openDialog(html) {
    lastFocus = document.activeElement;
    productEditor.innerHTML = html;
    productEditor.hidden = false;
    document.body.classList.add("ad-noscroll");
    const first = productEditor.querySelector("input, select, textarea");
    if (first) first.focus();
  }

  function closeEditor() {
    productEditor.hidden = true;
    productEditor.innerHTML = "";
    document.body.classList.remove("ad-noscroll");
    if (lastFocus && document.contains(lastFocus)) lastFocus.focus();
  }

  productEditor.addEventListener("click", (e) => { if (e.target === productEditor) closeEditor(); });
  document.addEventListener("keydown", (e) => {
    if (productEditor.hidden) return;
    if (e.key === "Escape") { closeEditor(); return; }
    if (e.key === "Tab") { // keep focus inside the dialog
      const f = [...productEditor.querySelectorAll("input, select, textarea, button")].filter((el) => !el.disabled && el.offsetParent !== null);
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  });

  function showError(el, text) {
    el.textContent = text;
    el.hidden = false;
  }

  /** The editor for a product (null creates a new one). */
  function openProductEditor(product) {
    openDialog(`
      <form id="productForm" class="ad-modal__card" novalidate>
        <h2 id="editorHeading">${product ? "Бүтээгдэхүүн засах" : "Шинэ бүтээгдэхүүн"}</h2>

        <div class="ad-field"><label for="pf_slug">Slug (URL хаяг)</label>
          <input type="text" id="pf_slug" value="${product ? escAttr(product.slug) : ""}" placeholder="erdest-doloots" required aria-describedby="pf_slug_hint">
          <span class="ad-hint" id="pf_slug_hint">Латин жижиг үсэг, тоо, зураас. agrofarm.mn/shop/<em>slug</em></span></div>

        <div class="ad-form-row">
          <div class="ad-field"><label for="pf_name_mn">Нэр (Монгол)</label>
            <input type="text" id="pf_name_mn" value="${product ? escAttr(product.name_mn) : ""}" required></div>
          <div class="ad-field"><label for="pf_name_en">Нэр (Англи)</label>
            <input type="text" id="pf_name_en" value="${product ? escAttr(product.name_en || "") : ""}"></div>
        </div>

        <div class="ad-field"><label for="pf_category">Ангилал</label>
          <select id="pf_category">
            <option value="">— ангилалгүй</option>
            ${categoriesCache.map((c) => `<option value="${escAttr(c.id)}"${product && Number(product.category_id) === Number(c.id) ? " selected" : ""}>${escHtml(c.name)}</option>`).join("")}
          </select></div>

        <div class="ad-field"><label for="pf_desc_mn">Тайлбар (Монгол)</label>
          <textarea id="pf_desc_mn" rows="3">${product ? escHtml(product.description_mn || "") : ""}</textarea></div>
        <div class="ad-field"><label for="pf_desc_en">Тайлбар (Англи)</label>
          <textarea id="pf_desc_en" rows="3">${product ? escHtml(product.description_en || "") : ""}</textarea></div>

        <label class="ad-check"><input type="checkbox" id="pf_active" ${!product || Number(product.active) ? "checked" : ""}> Идэвхтэй (дэлгүүрт харагдана)</label>

        <p class="ad-flash ad-flash--err" id="productFormError" role="alert" hidden></p>
        <div class="ad-form-actions ad-btn-row">
          <button type="submit" class="ad-btn ad-btn--primary">Хадгалах</button>
          <button type="button" class="ad-btn" id="cancelProductEdit">Болих</button>
        </div>
      </form>
    `);

    document.getElementById("cancelProductEdit").addEventListener("click", closeEditor);

    document.getElementById("productForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      const errEl = document.getElementById("productFormError");
      errEl.hidden = true;
      const params = {
        action: "save_product",
        slug: document.getElementById("pf_slug").value.trim(),
        name_mn: document.getElementById("pf_name_mn").value.trim(),
        name_en: document.getElementById("pf_name_en").value.trim(),
        description_mn: document.getElementById("pf_desc_mn").value.trim(),
        description_en: document.getElementById("pf_desc_en").value.trim(),
        active: document.getElementById("pf_active").checked ? "1" : "0",
        category_id: document.getElementById("pf_category").value,
      };
      if (product) params.id = product.id;

      try {
        const res = await post("/admin/products-admin.php", params);
        const data = await readJson(res);
        if (!res.ok) { showError(errEl, data.error || "Бүтээгдэхүүн хадгалахад алдаа гарлаа"); return; }
        closeEditor();
        renderProductsTab();
      } catch (err) {
        showError(errEl, NET_ERROR);
      }
    });
  }

  function ingredientRow(ing) {
    return `
      <div class="ad-ing-row">
        <input class="ing-name" value="${escAttr(ing ? ing.name : "")}" placeholder="Нэр" aria-label="Найрлагын нэр">
        <input class="ing-pct" value="${escAttr(ing ? ing.percentage : "")}" placeholder="90%" aria-label="Хувь">
        <button type="button" class="ad-btn ad-btn--sm remove-ingredient" aria-label="Энэ найрлагыг хасах">✕</button>
      </div>`;
  }

  /** The editor for an option (null creates a new one under productId). */
  function openVariantEditor(variant, productId) {
    openDialog(`
      <form id="variantForm" class="ad-modal__card" novalidate>
        <h2 id="editorHeading">${variant ? "Төрөл засах" : "Шинэ төрөл"}</h2>

        <div class="ad-form-row">
          <div class="ad-field"><label for="vf_name_mn">Нэр (Монгол)</label>
            <input type="text" id="vf_name_mn" value="${variant ? escAttr(variant.name_mn) : ""}" required></div>
          <div class="ad-field"><label for="vf_name_en">Нэр (Англи)</label>
            <input type="text" id="vf_name_en" value="${variant ? escAttr(variant.name_en || "") : ""}"></div>
        </div>
        <div class="ad-form-row">
          <div class="ad-field"><label for="vf_price">Үнэ (₮)</label>
            <input type="number" id="vf_price" min="0" step="1" inputmode="numeric" value="${variant ? variant.price : ""}" required></div>
          <div class="ad-field"><label for="vf_stock">Нөөц (ширхэг)</label>
            <input type="number" id="vf_stock" min="0" step="1" inputmode="numeric" value="${variant ? variant.stock : ""}" required></div>
        </div>
        <div class="ad-form-row">
          <div class="ad-field"><label for="vf_weight">Жингийн шошго</label>
            <input type="text" id="vf_weight" value="${variant ? escAttr(variant.weight_label || "") : ""}" placeholder="5кг"></div>
          <div class="ad-field"><label for="vf_std_code">Стандартын код</label>
            <input type="text" id="vf_std_code" value="${variant ? escAttr(variant.standard_code || "") : ""}" placeholder="MNS 5511:2005"></div>
        </div>

        <div class="ad-field"><label for="vf_storage">Хадгалалт / хугацаа (Монгол)</label>
          <textarea id="vf_storage" rows="2">${variant ? escHtml(variant.storage_text_mn || "") : ""}</textarea></div>
        <div class="ad-field"><label for="vf_benefits">Ашиг тус (Монгол)</label>
          <textarea id="vf_benefits" rows="2">${variant ? escHtml(variant.benefits_text_mn || "") : ""}</textarea></div>
        <div class="ad-field"><label for="vf_usage">Хэрэглэх заавар (Монгол)</label>
          <textarea id="vf_usage" rows="2">${variant ? escHtml(variant.usage_text_mn || "") : ""}</textarea></div>

        <div class="ad-field"><label for="vf_image">Зураг</label>
          <input type="file" id="vf_image" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="vf_image_hint">
          <span class="ad-hint" id="vf_image_hint">JPG, PNG, WEBP эсвэл GIF, дээд тал нь 5 MB.</span>
          ${variant && variant.image_path ? `<img class="ad-thumb" src="${escAttr(siteUrl(variant.image_path))}" alt="Одоогийн зураг">` : ""}</div>

        <label class="ad-check"><input type="checkbox" id="vf_active" ${!variant || Number(variant.active) ? "checked" : ""}> Идэвхтэй (дэлгүүрт харагдана)</label>

        <fieldset class="ad-fieldset">
          <legend>Найрлага</legend>
          <div id="ingredientRows">${(variant?.ingredients || []).map(ingredientRow).join("")}</div>
          <button type="button" class="ad-btn ad-btn--sm" id="addIngredientRow">+ Найрлага нэмэх</button>
        </fieldset>

        <p class="ad-flash ad-flash--err" id="variantFormError" role="alert" hidden></p>
        <div class="ad-form-actions ad-btn-row">
          <button type="submit" class="ad-btn ad-btn--primary">Хадгалах</button>
          <button type="button" class="ad-btn" id="cancelVariantEdit">Болих</button>
        </div>
      </form>
    `);

    document.getElementById("cancelVariantEdit").addEventListener("click", closeEditor);
    document.getElementById("addIngredientRow").addEventListener("click", () => {
      document.getElementById("ingredientRows").insertAdjacentHTML("beforeend", ingredientRow(null));
      const rows = document.querySelectorAll("#ingredientRows .ing-name");
      rows[rows.length - 1].focus();
    });
    document.getElementById("ingredientRows").addEventListener("click", (e) => {
      const btn = e.target.closest(".remove-ingredient");
      if (btn) btn.closest(".ad-ing-row").remove();
    });

    document.getElementById("variantForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      const errEl = document.getElementById("variantFormError");
      errEl.hidden = true;

      const priceVal = document.getElementById("vf_price").value;
      const stockVal = document.getElementById("vf_stock").value;
      if (priceVal === "" || stockVal === "") { showError(errEl, "Үнэ болон нөөцийг заавал бөглөнө үү"); return; }

      let imagePath = variant ? (variant.image_path || "") : "";
      const imageFile = document.getElementById("vf_image").files[0];
      if (imageFile && imageFile.size > 5 * 1024 * 1024) {
        showError(errEl, "Зураг хэт том байна (дээд тал нь 5 MB) — жижиг файл сонгоно уу");
        return;
      }

      try {
        if (imageFile) {
          const uploadBody = new FormData();
          uploadBody.append("action", "upload_image");
          uploadBody.append("image", imageFile);
          const uploadRes = await post("/admin/products-admin.php", uploadBody);
          const uploadData = await readJson(uploadRes);
          if (!uploadRes.ok) { showError(errEl, uploadData.error || "Зураг байршуулахад алдаа гарлаа"); return; }
          imagePath = uploadData.path;
        }

        const params = {
          action: "save_variant",
          product_id: productId,
          name_mn: document.getElementById("vf_name_mn").value.trim(),
          name_en: document.getElementById("vf_name_en").value.trim(),
          price: priceVal,
          stock: stockVal,
          weight_label: document.getElementById("vf_weight").value.trim(),
          standard_code: document.getElementById("vf_std_code").value.trim(),
          storage_text_mn: document.getElementById("vf_storage").value.trim(),
          benefits_text_mn: document.getElementById("vf_benefits").value.trim(),
          usage_text_mn: document.getElementById("vf_usage").value.trim(),
          image_path: imagePath || "",
          active: document.getElementById("vf_active").checked ? "1" : "0",
          sort_order: variant ? variant.sort_order : 0,
        };
        if (variant) params.id = variant.id;

        const res = await post("/admin/products-admin.php", params);
        const data = await readJson(res);
        if (!res.ok) { showError(errEl, data.error || "Төрөл хадгалахад алдаа гарлаа"); return; }

        const ingredients = [...document.querySelectorAll("#ingredientRows .ad-ing-row")].map((row) => ({
          name: row.querySelector(".ing-name").value.trim(),
          percentage: row.querySelector(".ing-pct").value.trim(),
        })).filter((ing) => ing.name && ing.percentage);

        const ingRes = await post("/admin/products-admin.php", {
          action: "save_ingredients",
          variant_id: data.id,
          ingredients: JSON.stringify(ingredients),
        });
        if (!ingRes.ok) {
          const ingData = await readJson(ingRes);
          showError(errEl, ingData.error || "Төрөл хадгалагдсан ч найрлага хадгалагдсангүй");
          renderProductsTab();
          return;
        }
        closeEditor();
        renderProductsTab();
      } catch (err) {
        showError(errEl, NET_ERROR);
      }
    });
  }

  document.getElementById("addProductBtn").addEventListener("click", () => openProductEditor(null));
  searchBox.addEventListener("input", () => {
    searchTerm = searchBox.value.trim().toLowerCase();
    paintProducts(); // repaint from cache — never re-fetch on a keystroke
  });

  renderProductsTab();
})();
