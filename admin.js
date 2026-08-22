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

  let posts = [];
  let editingId = null;

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
  }

  function escHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
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
  }

  init();
})();
