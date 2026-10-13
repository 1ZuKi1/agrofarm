/* admin/js/news.js — /admin/news: the news editor, moved from the old
 * one-page admin.js. Posts load from /news-data.json, are edited here (a copy
 * is cached in localStorage), and "Сайт дээр нийтлэх" sends them all to
 * /admin/publish.php, which writes news-data.json. Images upload through the
 * same endpoint (action=upload → "img/news/…").
 */
(() => {
  "use strict";
  const { escHtml, escAttr, post, siteUrl } = window.NAF;
  const $ = (id) => document.getElementById(id);

  const postList = $("postList"), listView = $("newsListView"), editorScreen = $("editorScreen");
  const editorTitle = $("editorTitle"), postDate = $("postDate");
  const postTitleEn = $("postTitleEn"), postTitleMn = $("postTitleMn");
  const postBodyEn = $("postBodyEn"), postBodyMn = $("postBodyMn");
  const postImage = $("postImage"), postImageFile = $("postImageFile");
  const uploadStatus = $("uploadStatus"), imagePreview = $("imagePreview");
  const publishStatus = $("publishStatus"), editorError = $("editorError");
  const searchBox = $("newsSearch"), actions = $("newsActions");

  let posts = [];
  let editingId = null;
  let searchTerm = "";
  let publishTimer = null;

  const NET_ERROR = "Серверт холбогдож чадсангүй — интернэтээ шалгаад дахин оролдоно уу.";
  const cache = {
    get() { try { return JSON.parse(localStorage.getItem("naf_news_cache") || "null"); } catch { return null; } },
    set(v) { try { localStorage.setItem("naf_news_cache", JSON.stringify(v)); } catch {} },
  };

  async function loadPosts() {
    try {
      const res = await fetch("/news-data.json?v=" + Date.now());
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();
      posts = Array.isArray(data.posts) ? data.posts : [];
      cache.set(posts);
    } catch {
      posts = cache.get() || [];
    }
  }

  function makeId() {
    const ds = toDateInput(new Date()).replace(/-/g, "");
    return `p-${ds}-${Math.random().toString(36).slice(2, 6)}`;
  }

  /** A date as 'Y-m-d' in the computer's own time zone (not UTC, which is yesterday before 08:00 in UB). */
  function toDateInput(d) {
    if (typeof d === "string") return d.slice(0, 10);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  /** '2026-10-14' → '10-р сарын 14, 2026' */
  function fmtDate(value) {
    const m = String(value || "").match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${Number(m[2])}-р сарын ${Number(m[3])}, ${m[1]}` : "—";
  }

  function matchesSearch(...fields) {
    if (!searchTerm) return true;
    return fields.some((f) => String(f ?? "").toLowerCase().includes(searchTerm));
  }

  function downloadJSON() {
    const blob = new Blob([JSON.stringify({ posts }, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url; a.download = "news-data.json";
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  }

  function setPublish(kind, text) {
    publishStatus.className = "ad-flash" + (kind === "ok" ? " ad-flash--ok" : kind === "err" ? " ad-flash--err" : "");
    publishStatus.textContent = text;
    publishStatus.hidden = false;
    clearTimeout(publishTimer);
    if (kind !== "busy") publishTimer = setTimeout(() => { publishStatus.hidden = true; }, 8000);
  }

  async function publishToLive() {
    const btn = $("publishBtn");
    btn.disabled = true;
    setPublish("busy", "Нийтэлж байна…");
    try {
      const res = await post("/admin/publish.php", { data: JSON.stringify({ posts }, null, 2) });
      const text = await res.text();
      if (res.ok && text === "ok") setPublish("ok", "Нийтлэгдлээ! Сайт шинэчлэгдлээ.");
      else setPublish("err", (text && !text.startsWith("{")) ? text : "Нийтлэхэд алдаа гарлаа (HTTP " + res.status + ")");
    } catch (err) {
      if (!String(err.message).includes("Нэвтрэх")) setPublish("err", NET_ERROR);
    } finally {
      btn.disabled = false;
    }
  }

  /** Upload the chosen image; keep the returned path. */
  async function uploadImage() {
    const file = postImageFile.files[0];
    if (!file) return;
    uploadStatus.hidden = false;
    uploadStatus.textContent = "Байршуулж байна…";
    const form = new FormData();
    form.append("action", "upload");
    form.append("image", file);
    try {
      const res = await post("/admin/publish.php", form);
      const text = await res.text();
      if (res.ok && text.startsWith("img/news/")) {
        postImage.value = text;
        showPreview(text);
        uploadStatus.textContent = "Байршуулсан";
      } else {
        uploadStatus.textContent = (text && !text.startsWith("{")) ? text : "Байршуулахад алдаа гарлаа";
      }
    } catch (err) {
      uploadStatus.textContent = NET_ERROR;
    }
  }

  function showPreview(src) {
    if (src) { imagePreview.src = siteUrl(src); imagePreview.hidden = false; }
    else { imagePreview.removeAttribute("src"); imagePreview.hidden = true; }
  }

  function renderDashboard() {
    $("newsCount").textContent = posts.length + " мэдээ";
    if (posts.length === 0) {
      postList.innerHTML = `<p class="ad-empty">Одоогоор мэдээ алга — эхний мэдээгээ бичихийн тулд "Шинэ мэдээ" дээр дарна уу.</p>`;
      return;
    }
    const sorted = [...posts]
      .sort((a, b) => new Date(b.date) - new Date(a.date))
      .filter((p) => matchesSearch(p.title?.en, p.title?.mn, p.body?.en, p.body?.mn));
    if (sorted.length === 0) {
      postList.innerHTML = `<p class="ad-empty">“${escHtml(searchTerm)}” тохирох мэдээ олдсонгүй.</p>`;
      return;
    }
    postList.innerHTML = `
      <div class="ad-table-wrap">
        <table class="ad-table ad-table--cards">
          <thead><tr><th scope="col">Огноо</th><th scope="col">Гарчиг</th><th scope="col">Англи</th><th scope="col">Зураг</th><th scope="col"><span class="ad-sr">Үйлдэл</span></th></tr></thead>
          <tbody>
            ${sorted.map((p) => {
              const title = p.title?.mn || p.title?.en || "(гарчиггүй)";
              return `
                <tr data-id="${escAttr(p.id)}">
                  <td class="ad-num">${escHtml(fmtDate(p.date))}</td>
                  <td><strong>${escHtml(title)}</strong></td>
                  <td>${p.title?.en ? `<span class="ad-tag ad-tag--ok">Орчуулсан</span>` : `<span class="ad-tag ad-tag--warn">Англи дутуу</span>`}</td>
                  <td>${p.image ? `<span class="ad-tag ad-tag--info">Зурагтай</span>` : `<span class="ad-tag ad-tag--mute">Зураггүй</span>`}</td>
                  <td><div class="ad-btn-row">
                    <button type="button" class="ad-btn ad-btn--sm edit-btn">Засах<span class="ad-sr"> — ${escHtml(title)}</span></button>
                    <button type="button" class="ad-btn ad-btn--sm ad-btn--danger delete-btn">Устгах<span class="ad-sr"> — ${escHtml(title)}</span></button>
                  </div></td>
                </tr>`;
            }).join("")}
          </tbody>
        </table>
      </div>`;
    postList.querySelectorAll(".edit-btn").forEach((b) => b.addEventListener("click", (e) => editPost(e.target.closest("tr").dataset.id)));
    postList.querySelectorAll(".delete-btn").forEach((b) => b.addEventListener("click", (e) => deletePost(e.target.closest("tr").dataset.id)));
  }

  function showEditor(open) {
    editorScreen.hidden = !open;
    listView.hidden = open;
    actions.hidden = open;
    editorError.hidden = true;
    if (open) postTitleMn.focus();
  }

  function fillEditor(p) {
    postDate.value = toDateInput(p ? p.date : new Date());
    postTitleEn.value = p?.title?.en || "";
    postTitleMn.value = p?.title?.mn || "";
    postBodyEn.value = p?.body?.en || "";
    postBodyMn.value = p?.body?.mn || "";
    postImage.value = p?.image || "";
    postImageFile.value = "";
    uploadStatus.hidden = true;
    showPreview(p?.image || "");
  }

  function editPost(id) {
    const p = posts.find((x) => x.id === id);
    if (!p) return;
    editingId = id;
    editorTitle.textContent = "Мэдээ засах";
    fillEditor(p);
    showEditor(true);
  }

  function newPost() {
    editingId = null;
    editorTitle.textContent = "Шинэ мэдээ";
    fillEditor(null);
    showEditor(true);
  }

  function deletePost(id) {
    if (!confirm("Энэ мэдээг устгах уу? Сайтаас арилгахын тулд дараа нь \"Сайт дээр нийтлэх\" дарна.")) return;
    posts = posts.filter((p) => p.id !== id);
    cache.set(posts);
    renderDashboard();
  }

  function savePost() {
    const date = postDate.value || toDateInput(new Date());
    const titleEn = postTitleEn.value.trim(), titleMn = postTitleMn.value.trim();
    const bodyEn = postBodyEn.value.trim(), bodyMn = postBodyMn.value.trim();
    const image = postImage.value.trim() || undefined;
    if ((!titleEn || !bodyEn) && (!titleMn || !bodyMn)) {
      editorError.textContent = "Гарчиг, агуулгыг Монгол эсвэл Англи хэлний аль нэгээр нь бөглөнө үү.";
      editorError.hidden = false;
      return;
    }
    const fields = { date, title: { en: titleEn, mn: titleMn }, body: { en: bodyEn, mn: bodyMn }, image };
    if (editingId) {
      const idx = posts.findIndex((p) => p.id === editingId);
      if (idx !== -1) posts[idx] = { ...posts[idx], ...fields };
    } else {
      posts.push({ id: makeId(), ...fields });
    }
    cache.set(posts);
    showEditor(false);
    renderDashboard();
    setPublish("ok", "Мэдээ хадгалагдлаа — сайтад гаргахын тулд \"Сайт дээр нийтлэх\" дарна уу.");
  }

  $("addBtn").addEventListener("click", newPost);
  $("cancelBtn").addEventListener("click", () => showEditor(false));
  $("saveBtn").addEventListener("click", savePost);
  $("exportBtn").addEventListener("click", downloadJSON);
  $("publishBtn").addEventListener("click", publishToLive);
  postImageFile.addEventListener("change", uploadImage);
  postImage.addEventListener("change", () => showPreview(postImage.value.trim()));
  searchBox.addEventListener("input", () => {
    searchTerm = searchBox.value.trim().toLowerCase();
    renderDashboard();
  });

  loadPosts().then(renderDashboard);
})();
