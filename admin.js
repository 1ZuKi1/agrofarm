/* ==========================================================================
   Admin panel — password gate, CRUD, JSON export
   ========================================================================== */
(() => {
  "use strict";

  const loginScreen   = document.getElementById("loginScreen");
  const dashboardScreen = document.getElementById("dashboardScreen");
  const editorScreen  = document.getElementById("editorScreen");
  const passwordInput = document.getElementById("passwordInput");
  const loginBtn      = document.getElementById("loginBtn");
  const loginError    = document.getElementById("loginError");
  const addBtn        = document.getElementById("addBtn");
  const exportBtn     = document.getElementById("exportBtn");
  const publishBtn    = document.getElementById("publishBtn");
  const publishStatus = document.getElementById("publishStatus");
  const logoutBtn     = document.getElementById("logoutBtn");
  const postList      = document.getElementById("postList");
  const editorTitle   = document.getElementById("editorTitle");
  const postDate      = document.getElementById("postDate");
  const postTitleEn   = document.getElementById("postTitleEn");
  const postTitleMn   = document.getElementById("postTitleMn");
  const postBodyEn    = document.getElementById("postBodyEn");
  const postBodyMn    = document.getElementById("postBodyMn");
  const postImage     = document.getElementById("postImage");
  const postImageFile = document.getElementById("postImageFile");
  const uploadStatus  = document.getElementById("uploadStatus");
  const imagePreview  = document.getElementById("imagePreview");
  const saveBtn       = document.getElementById("saveBtn");
  const cancelBtn     = document.getElementById("cancelBtn");
  const saveSuccess   = document.getElementById("saveSuccess");
  const productsScreen = document.getElementById("productsScreen");
  const productsBtn   = document.getElementById("productsBtn");
  const backToNewsBtn = document.getElementById("backToNewsBtn");
  const productsTab   = document.getElementById("productsTab");
  const addProductBtn = document.getElementById("addProductBtn");
  const productEditor = document.getElementById("productEditor");

  let posts = [];
  let editingId = null;
  let productsCache = [];

  async function loadPosts() {
    try {
      const res = await fetch("news-data.json?v=" + Date.now());
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();
      posts = Array.isArray(data.posts) ? data.posts : [];
      localStorage.setItem("naf_news_cache", JSON.stringify(posts));
    } catch {
      const cached = localStorage.getItem("naf_news_cache");
      posts = cached ? JSON.parse(cached) : [];
    }
  }

  function saveToCache() {
    localStorage.setItem("naf_news_cache", JSON.stringify(posts));
  }

  function makeId() {
    const now = new Date();
    const ds = now.toISOString().slice(0, 10).replace(/-/g, "");
    const n = Math.random().toString(36).slice(2, 6);
    return `p-${ds}-${n}`;
  }

  function toDateInput(d) {
    if (typeof d === "string") return d.slice(0, 10);
    return d.toISOString().slice(0, 10);
  }

  function downloadJSON() {
    const data = JSON.stringify({ posts }, null, 2);
    const blob = new Blob([data], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "news-data.json";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  async function publishToLive() {
    const data = JSON.stringify({ posts }, null, 2);

    publishStatus.className = "admin-publish-status";
    publishStatus.textContent = "Publishing…";
    publishStatus.style.display = "block";

    try {
      const res = await fetch("publish.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "data=" + encodeURIComponent(data) + "&csrf=" + encodeURIComponent(sessionStorage.getItem("naf_csrf_token") || ""),
      });
      const text = await res.text();

      if (res.ok && text === "ok") {
        publishStatus.className = "admin-publish-status ok";
        publishStatus.textContent = "✅ Published! The live site is now updated.";
      } else if (res.status === 401 || res.status === 403) {
        // Session or CSRF token expired — send the admin back to the login screen
        sessionStorage.removeItem("naf_admin_auth");
        sessionStorage.removeItem("naf_csrf_token");
        publishStatus.className = "admin-publish-status err";
        publishStatus.textContent = "Session expired — please log in again.";
        showScreen("login");
      } else {
        publishStatus.className = "admin-publish-status err";
        publishStatus.textContent = text || "Publish failed (HTTP " + res.status + ")";
      }
    } catch (err) {
      publishStatus.className = "admin-publish-status err";
      publishStatus.textContent = "Could not reach publish.php — is PHP enabled on your server?";
    }

    setTimeout(() => {
      publishStatus.style.display = "none";
    }, 8000);
  }

  /** Upload the selected image to the server; store the returned path. */
  async function uploadImage() {
    const file = postImageFile.files[0];
    if (!file) return;

    uploadStatus.style.color = "var(--ink-soft)";
    uploadStatus.style.display = "inline";
    uploadStatus.textContent = "Uploading…";

    const form = new FormData();
    form.append("action", "upload");
    form.append("image", file);
    form.append("csrf", sessionStorage.getItem("naf_csrf_token") || "");

    try {
      const res = await fetch("publish.php", { method: "POST", body: form });
      const text = await res.text();

      if (res.ok && text.startsWith("img/news/")) {
        postImage.value = text;
        showPreview(text);
        uploadStatus.style.color = "var(--green)";
        uploadStatus.textContent = "✅ Uploaded";
      } else if (res.status === 401 || res.status === 403) {
        sessionStorage.removeItem("naf_admin_auth");
        sessionStorage.removeItem("naf_csrf_token");
        uploadStatus.style.color = "#c0392b";
        uploadStatus.textContent = "Session expired — log in again.";
        showScreen("login");
      } else {
        uploadStatus.style.color = "#c0392b";
        uploadStatus.textContent = text || "Upload failed";
      }
    } catch (err) {
      uploadStatus.style.color = "#c0392b";
      uploadStatus.textContent = "Could not reach the server — is PHP enabled?";
    }
  }

  /** Show the image preview for a given path/URL (or hide it if empty). */
  function showPreview(src) {
    if (src) {
      imagePreview.src = src;
      imagePreview.style.display = "block";
    } else {
      imagePreview.removeAttribute("src");
      imagePreview.style.display = "none";
    }
  }

  function renderDashboard() {
    if (!postList) return;

    if (posts.length === 0) {
      postList.innerHTML = `<p style="color:#999;text-align:center;padding:1.5rem 0;font-size:.9rem">No posts yet. Click "New post" to create one.</p>`;
      return;
    }

    const sorted = [...posts].sort((a, b) => new Date(b.date) - new Date(a.date));

    postList.innerHTML = sorted.map((post) => {
      const title = post.title?.en || post.title?.mn || "(untitled)";
      const d = post.date ? new Date(post.date).toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" }) : "";
      return `
        <div class="admin-post" data-id="${post.id}">
          <div class="admin-post-info">
            <strong>${escHtml(title)}</strong>
            <time>${d}</time>
          </div>
          <div class="admin-post-actions">
            <button class="edit-btn">Edit</button>
            <button class="del delete-btn">Delete</button>
          </div>
        </div>
      `;
    }).join("");

    postList.querySelectorAll(".edit-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const row = e.target.closest(".admin-post");
        editPost(row.dataset.id);
      });
    });

    postList.querySelectorAll(".delete-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const row = e.target.closest(".admin-post");
        deletePost(row.dataset.id);
      });
    });
  }

  function editPost(id) {
    const post = posts.find((p) => p.id === id);
    if (!post) return;
    editingId = id;
    editorTitle.textContent = "Edit post";
    postDate.value = toDateInput(post.date);
    postTitleEn.value = post.title?.en || "";
    postTitleMn.value = post.title?.mn || "";
    postBodyEn.value = post.body?.en || "";
    postBodyMn.value = post.body?.mn || "";
    postImage.value = post.image || "";
    postImageFile.value = "";
    uploadStatus.style.display = "none";
    showPreview(post.image || "");
    saveSuccess.style.display = "none";
    showScreen("editor");
  }

  function deletePost(id) {
    if (!confirm("Delete this post?")) return;
    posts = posts.filter((p) => p.id !== id);
    saveToCache();
    renderDashboard();
  }

  function newPost() {
    editingId = null;
    editorTitle.textContent = "New post";
    postDate.value = toDateInput(new Date());
    postTitleEn.value = "";
    postTitleMn.value = "";
    postBodyEn.value = "";
    postBodyMn.value = "";
    postImage.value = "";
    postImageFile.value = "";
    uploadStatus.style.display = "none";
    showPreview("");
    saveSuccess.style.display = "none";
    showScreen("editor");
  }

  function savePost() {
    const date = postDate.value || toDateInput(new Date());
    const titleEn = postTitleEn.value.trim();
    const titleMn = postTitleMn.value.trim();
    const bodyEn = postBodyEn.value.trim();
    const bodyMn = postBodyMn.value.trim();
    const image = postImage.value.trim() || undefined;

    if ((!titleEn || !bodyEn) && (!titleMn || !bodyMn)) {
      alert("Please fill in title and body in at least one language.");
      return;
    }

    if (editingId) {
      const idx = posts.findIndex((p) => p.id === editingId);
      if (idx !== -1) {
        posts[idx] = { ...posts[idx], date, title: { en: titleEn, mn: titleMn }, body: { en: bodyEn, mn: bodyMn }, image };
      }
    } else {
      posts.push({
        id: makeId(),
        date,
        title: { en: titleEn, mn: titleMn },
        body: { en: bodyEn, mn: bodyMn },
        image,
      });
    }

    saveToCache();
    saveSuccess.style.display = "block";
    setTimeout(() => { saveSuccess.style.display = "none"; }, 2000);
    renderDashboard();
    showScreen("dashboard");
  }

  function showScreen(screen) {
    loginScreen.style.display = screen === "login" ? "block" : "none";
    dashboardScreen.style.display = screen === "dashboard" ? "block" : "none";
    editorScreen.style.display = screen === "editor" ? "block" : "none";
    productsScreen.style.display = screen === "products" ? "block" : "none";
  }

  /** Load and render the Products tab (products + variants list). */
  async function renderProductsTab() {
    if (!productsTab) return;
    productsTab.innerHTML = "<p>Loading…</p>";

    try {
      const res = await fetch("products-admin.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list&csrf=" + encodeURIComponent(sessionStorage.getItem("naf_csrf_token") || ""),
      });
      const data = await res.json();

      if (res.status === 401 || res.status === 403) {
        sessionStorage.removeItem("naf_admin_auth");
        sessionStorage.removeItem("naf_csrf_token");
        showScreen("login");
        return;
      }

      if (!res.ok) {
        productsTab.innerHTML = `<p class="admin-error" style="display:block">${escHtml(data.error || "Failed to load products")}</p>`;
        return;
      }

      productsCache = data.products || [];

      if (productsCache.length === 0) {
        productsTab.innerHTML = `<p style="color:#999;text-align:center;padding:1.5rem 0;font-size:.9rem">No products yet. Click "Add product" to create one.</p>`;
        return;
      }

      productsTab.innerHTML = productsCache.map((product) => `
        <div class="admin-product" data-product-id="${product.id}">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:.6rem">
            <h3 style="margin:0">${escHtml(product.name_mn)}</h3>
            <div class="admin-row-actions">
              <button class="edit-product-btn" data-id="${product.id}">Edit</button>
              <button class="add-variant-btn" data-product-id="${product.id}">+ Add variant</button>
            </div>
          </div>
          <div class="admin-variants">
            ${product.variants.map((v) => `
              <div class="admin-variant" data-variant-id="${v.id}">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:.6rem">
                  <span><strong>${escHtml(v.name_mn)}</strong> — ${v.price.toLocaleString()}₮ — stock: ${v.stock} (available: ${v.available})</span>
                  <div class="admin-row-actions">
                    <button class="edit-variant-btn" data-id="${v.id}" data-product-id="${product.id}">Edit</button>
                  </div>
                </div>
              </div>
            `).join("")}
          </div>
        </div>
      `).join("");

      productsTab.querySelectorAll(".edit-product-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
          const product = productsCache.find((p) => Number(p.id) === Number(btn.dataset.id));
          openProductEditor(product);
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
    } catch (err) {
      productsTab.innerHTML = `<p class="admin-error" style="display:block">Could not reach the server — is PHP enabled?</p>`;
    }
  }

  /** Serialize a plain object into an application/x-www-form-urlencoded body string. */
  function toFormBody(params) {
    return Object.entries(params)
      .map(([k, v]) => encodeURIComponent(k) + "=" + encodeURIComponent(v))
      .join("&");
  }

  function closeEditor() {
    productEditor.style.display = "none";
    productEditor.innerHTML = "";
  }

  /** Open the modal editor for a product (pass null/undefined to create a new one). */
  function openProductEditor(product) {
    productEditor.style.display = "flex";
    productEditor.innerHTML = `
      <form id="productForm" class="admin-card admin-form" style="max-width:600px;max-height:85vh;overflow-y:auto">
        <h2 style="margin-top:0">${product ? "Edit product" : "New product"}</h2>

        <label for="pf_slug">Slug</label>
        <input type="text" id="pf_slug" value="${product ? escAttr(product.slug) : ""}" placeholder="erdest-doloots" required>

        <label for="pf_name_mn">Name (Монгол)</label>
        <input type="text" id="pf_name_mn" value="${product ? escAttr(product.name_mn) : ""}" required>

        <label for="pf_name_en">Name (English)</label>
        <input type="text" id="pf_name_en" value="${product ? escAttr(product.name_en || "") : ""}">

        <label for="pf_desc_mn">Description (Монгол)</label>
        <textarea id="pf_desc_mn">${product ? escHtml(product.description_mn || "") : ""}</textarea>

        <label for="pf_desc_en">Description (English)</label>
        <textarea id="pf_desc_en">${product ? escHtml(product.description_en || "") : ""}</textarea>

        <label style="display:flex;align-items:center;gap:.4rem;margin-top:.9rem">
          <input type="checkbox" id="pf_active" style="width:auto" ${!product || Number(product.active) ? "checked" : ""}> Active
        </label>

        <div class="admin-form-actions">
          <button type="submit" class="btn btn--gold">💾 Save</button>
          <button type="button" class="btn btn--ghost" style="border-color:#999;color:#666" id="cancelProductEdit">Cancel</button>
        </div>
        <p class="admin-error" id="productFormError" style="display:none"></p>
      </form>
    `;

    document.getElementById("cancelProductEdit").addEventListener("click", closeEditor);

    document.getElementById("productForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      const errEl = document.getElementById("productFormError");
      errEl.style.display = "none";

      const params = {
        action: "save_product",
        csrf: sessionStorage.getItem("naf_csrf_token") || "",
        slug: document.getElementById("pf_slug").value.trim(),
        name_mn: document.getElementById("pf_name_mn").value.trim(),
        name_en: document.getElementById("pf_name_en").value.trim(),
        description_mn: document.getElementById("pf_desc_mn").value.trim(),
        description_en: document.getElementById("pf_desc_en").value.trim(),
        active: document.getElementById("pf_active").checked ? "1" : "0",
      };
      if (product) params.id = product.id;

      try {
        const res = await fetch("products-admin.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: toFormBody(params),
        });
        const data = await res.json();

        if (res.status === 401 || res.status === 403) {
          sessionStorage.removeItem("naf_admin_auth");
          sessionStorage.removeItem("naf_csrf_token");
          closeEditor();
          showScreen("login");
          return;
        }
        if (!res.ok) {
          errEl.textContent = data.error || "Failed to save product";
          errEl.style.display = "block";
          return;
        }

        closeEditor();
        renderProductsTab();
      } catch (err) {
        errEl.textContent = "Could not reach the server — is PHP enabled?";
        errEl.style.display = "block";
      }
    });
  }

  /** Open the modal editor for a variant (pass null variant to create a new one under productId). */
  function openVariantEditor(variant, productId) {
    productEditor.style.display = "flex";

    const ingredientsHtml = (variant?.ingredients || []).map((ing) => `
      <div class="ingredient-row">
        <input class="ing-name" value="${escAttr(ing.name)}" placeholder="Нэр">
        <input class="ing-pct" value="${escAttr(ing.percentage)}" placeholder="90%">
        <button type="button" class="remove-ingredient">✕</button>
      </div>
    `).join("");

    productEditor.innerHTML = `
      <form id="variantForm" class="admin-card admin-form" style="max-width:600px;max-height:85vh;overflow-y:auto">
        <h2 style="margin-top:0">${variant ? "Edit variant" : "New variant"}</h2>

        <label for="vf_name_mn">Name (Монгол)</label>
        <input type="text" id="vf_name_mn" value="${variant ? escAttr(variant.name_mn) : ""}" required>

        <label for="vf_name_en">Name (English)</label>
        <input type="text" id="vf_name_en" value="${variant ? escAttr(variant.name_en || "") : ""}">

        <div class="admin-form-row">
          <div>
            <label for="vf_price">Price (₮)</label>
            <input type="number" id="vf_price" min="0" step="1" value="${variant ? variant.price : ""}" required>
          </div>
          <div>
            <label for="vf_stock">Stock</label>
            <input type="number" id="vf_stock" min="0" step="1" value="${variant ? variant.stock : ""}" required>
          </div>
        </div>

        <div class="admin-form-row">
          <div>
            <label for="vf_weight">Weight label</label>
            <input type="text" id="vf_weight" value="${variant ? escAttr(variant.weight_label || "") : ""}" placeholder="5кг">
          </div>
          <div>
            <label for="vf_std_code">Standard code</label>
            <input type="text" id="vf_std_code" value="${variant ? escAttr(variant.standard_code || "") : ""}" placeholder="MNS 5511:2005">
          </div>
        </div>

        <label for="vf_storage">Storage / shelf life (Монгол)</label>
        <textarea id="vf_storage">${variant ? escHtml(variant.storage_text_mn || "") : ""}</textarea>

        <label for="vf_benefits">Benefits (Монгол)</label>
        <textarea id="vf_benefits">${variant ? escHtml(variant.benefits_text_mn || "") : ""}</textarea>

        <label for="vf_usage">Usage instructions (Монгол)</label>
        <textarea id="vf_usage">${variant ? escHtml(variant.usage_text_mn || "") : ""}</textarea>

        <label for="vf_image">Image</label>
        <input type="file" id="vf_image" accept="image/jpeg,image/png,image/webp,image/gif">
        ${variant && variant.image_path ? `<img src="${escAttr(variant.image_path)}" alt="" style="display:block;max-width:160px;margin-top:.5rem;border-radius:8px;border:1px solid rgba(0,0,0,.1)">` : ""}

        <label style="display:flex;align-items:center;gap:.4rem;margin-top:.9rem">
          <input type="checkbox" id="vf_active" style="width:auto" ${!variant || Number(variant.active) ? "checked" : ""}> Active
        </label>

        <fieldset style="margin-top:1rem;border:1px solid rgba(0,0,0,.1);border-radius:8px;padding:.8rem">
          <legend style="font-size:.82rem;font-weight:600;color:#555">Ingredients</legend>
          <div id="ingredientRows">${ingredientsHtml}</div>
          <button type="button" class="btn btn--ghost" style="border-color:#999;color:#666;padding:.35rem .8rem;font-size:.8rem" id="addIngredientRow">+ Add ingredient</button>
        </fieldset>

        <div class="admin-form-actions">
          <button type="submit" class="btn btn--gold">💾 Save</button>
          <button type="button" class="btn btn--ghost" style="border-color:#999;color:#666" id="cancelVariantEdit">Cancel</button>
        </div>
        <p class="admin-error" id="variantFormError" style="display:none"></p>
      </form>
    `;

    document.getElementById("cancelVariantEdit").addEventListener("click", closeEditor);

    document.getElementById("addIngredientRow").addEventListener("click", () => {
      const row = document.createElement("div");
      row.className = "ingredient-row";
      row.innerHTML = `<input class="ing-name" placeholder="Нэр"><input class="ing-pct" placeholder="90%"><button type="button" class="remove-ingredient">✕</button>`;
      document.getElementById("ingredientRows").appendChild(row);
    });

    document.getElementById("ingredientRows").addEventListener("click", (e) => {
      if (e.target.classList.contains("remove-ingredient")) {
        e.target.closest(".ingredient-row").remove();
      }
    });

    document.getElementById("variantForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      const errEl = document.getElementById("variantFormError");
      errEl.style.display = "none";

      const priceVal = document.getElementById("vf_price").value;
      const stockVal = document.getElementById("vf_stock").value;
      if (priceVal === "" || stockVal === "") {
        errEl.textContent = "Price and stock are required";
        errEl.style.display = "block";
        return;
      }

      let imagePath = variant ? (variant.image_path || "") : "";
      const imageFile = document.getElementById("vf_image").files[0];

      if (imageFile && imageFile.size > 5 * 1024 * 1024) {
        errEl.textContent = "Image is too large (max 5 MB) — choose a smaller file";
        errEl.style.display = "block";
        return;
      }

      if (imageFile) {
        const uploadBody = new FormData();
        uploadBody.append("action", "upload_image");
        uploadBody.append("csrf", sessionStorage.getItem("naf_csrf_token") || "");
        uploadBody.append("image", imageFile);

        try {
          const uploadRes = await fetch("products-admin.php", { method: "POST", body: uploadBody });
          const uploadData = await uploadRes.json();

          if (uploadRes.status === 401 || uploadRes.status === 403) {
            sessionStorage.removeItem("naf_admin_auth");
            sessionStorage.removeItem("naf_csrf_token");
            closeEditor();
            showScreen("login");
            return;
          }
          if (!uploadRes.ok) {
            errEl.textContent = uploadData.error || "Image upload failed";
            errEl.style.display = "block";
            return;
          }
          imagePath = uploadData.path;
        } catch (err) {
          errEl.textContent = "Could not reach the server — is PHP enabled?";
          errEl.style.display = "block";
          return;
        }
      }

      const params = {
        action: "save_variant",
        csrf: sessionStorage.getItem("naf_csrf_token") || "",
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

      try {
        const res = await fetch("products-admin.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: toFormBody(params),
        });
        const data = await res.json();

        if (res.status === 401 || res.status === 403) {
          sessionStorage.removeItem("naf_admin_auth");
          sessionStorage.removeItem("naf_csrf_token");
          closeEditor();
          showScreen("login");
          return;
        }
        if (!res.ok) {
          errEl.textContent = data.error || "Failed to save variant";
          errEl.style.display = "block";
          return;
        }

        const ingredients = [...document.querySelectorAll("#ingredientRows .ingredient-row")].map((row) => ({
          name: row.querySelector(".ing-name").value.trim(),
          percentage: row.querySelector(".ing-pct").value.trim(),
        })).filter((ing) => ing.name && ing.percentage);

        const ingRes = await fetch("products-admin.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: toFormBody({
            action: "save_ingredients",
            csrf: sessionStorage.getItem("naf_csrf_token") || "",
            variant_id: data.id,
            ingredients: JSON.stringify(ingredients),
          }),
        });

        if (ingRes.status === 401 || ingRes.status === 403) {
          sessionStorage.removeItem("naf_admin_auth");
          sessionStorage.removeItem("naf_csrf_token");
          closeEditor();
          showScreen("login");
          return;
        }
        if (!ingRes.ok) {
          const ingData = await ingRes.json().catch(() => ({}));
          alert(ingData.error || "Variant saved, but ingredients failed to save");
        }

        closeEditor();
        renderProductsTab();
      } catch (err) {
        errEl.textContent = "Could not reach the server — is PHP enabled?";
        errEl.style.display = "block";
      }
    });
  }

  function escHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  /** Escape a value for use inside a double-quoted HTML attribute (escHtml doesn't escape quotes). */
  function escAttr(str) {
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  async function init() {
    const authed = sessionStorage.getItem("naf_admin_auth");
    if (authed === "1") {
      await loadPosts();
      renderDashboard();
      showScreen("dashboard");
    } else {
      showScreen("login");
    }

    async function doLogin() {
      const pwd = passwordInput.value;
      loginBtn.disabled = true;
      try {
        const res = await fetch("login.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "password=" + encodeURIComponent(pwd),
        });
        const body = (await res.text()).trim();
        if (res.ok) {
          // Response body is the fresh CSRF token issued at login
          sessionStorage.setItem("naf_admin_auth", "1");
          if (body) sessionStorage.setItem("naf_csrf_token", body);
          loginError.style.display = "none";
          passwordInput.value = "";
          await loadPosts();
          renderDashboard();
          showScreen("dashboard");
        } else {
          loginError.textContent =
            res.status === 403 ? "Incorrect password" :
            res.status === 429 ? (body || "Too many failed attempts — try again later.") :
            (body || "Login failed — is PHP enabled?");
          loginError.style.display = "block";
          passwordInput.value = "";
          passwordInput.focus();
        }
      } catch {
        loginError.textContent = "Could not reach the server — is PHP enabled?";
        loginError.style.display = "block";
      } finally {
        loginBtn.disabled = false;
      }
    }

    loginBtn.addEventListener("click", doLogin);

    passwordInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") doLogin();
    });

    logoutBtn.addEventListener("click", async () => {
      try { await fetch("logout.php", { method: "POST" }); } catch {}
      sessionStorage.removeItem("naf_admin_auth");
      sessionStorage.removeItem("naf_csrf_token");
      passwordInput.value = "";
      showScreen("login");
    });

    addBtn.addEventListener("click", newPost);
    cancelBtn.addEventListener("click", () => showScreen("dashboard"));
    saveBtn.addEventListener("click", savePost);
    exportBtn.addEventListener("click", downloadJSON);
    publishBtn.addEventListener("click", publishToLive);
    postImageFile.addEventListener("change", uploadImage);
    productsBtn.addEventListener("click", () => {
      showScreen("products");
      renderProductsTab();
    });
    backToNewsBtn.addEventListener("click", () => showScreen("dashboard"));
    addProductBtn.addEventListener("click", () => openProductEditor(null));
  }

  init();
})();
