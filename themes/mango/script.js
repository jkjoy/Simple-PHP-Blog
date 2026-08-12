(function () {
  "use strict";

  var root = document.documentElement;
  var body = document.body;
  var mobileNav = document.querySelector(".mobile_nav");
  var mobileButton = document.querySelector(".mobile_an");
  var mobileClose = document.querySelector(".mobile-close");
  var mask = document.querySelector(".mango-mask");
  var searchDrawer = document.querySelector(".search-drawer");
  var searchButton = document.querySelector(".search-toggle");
  var searchClose = document.querySelector(".drawer-close");
  var themeButton = document.querySelector(".theme-switch");
  var themeColor = document.querySelector("[data-mango-theme-color]");
  var backToTop = document.querySelector(".scrollToTopBtn");
  var likeUrlMeta = document.querySelector('meta[name="mango-like-url"]');
  var csrfMeta = document.querySelector('meta[name="mango-csrf-token"]');
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function setMenu(open) {
    if (!mobileNav) return;
    mobileNav.classList.toggle("is-open", open);
    mobileNav.setAttribute("aria-hidden", open ? "false" : "true");
    body.classList.toggle("mango-menu-open", open);
    if (mobileButton) mobileButton.setAttribute("aria-expanded", open ? "true" : "false");
    if (open && mobileClose) mobileClose.focus();
  }

  function setSearch(open) {
    if (!searchDrawer) return;
    searchDrawer.classList.toggle("is-open", open);
    searchDrawer.setAttribute("aria-hidden", open ? "false" : "true");
    body.classList.toggle("mango-search-open", open);
    if (searchButton) searchButton.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      var input = searchDrawer.querySelector('input[type="search"]');
      if (input) window.setTimeout(function () { input.focus(); }, 120);
    }
  }

  if (mobileButton) mobileButton.addEventListener("click", function () { setMenu(true); });
  if (mobileClose) mobileClose.addEventListener("click", function () { setMenu(false); });
  if (mask) mask.addEventListener("click", function () { setMenu(false); });
  if (searchButton) searchButton.addEventListener("click", function () { setSearch(true); });
  if (searchClose) searchClose.addEventListener("click", function () { setSearch(false); });
  if (searchDrawer) searchDrawer.addEventListener("click", function (event) { if (event.target === searchDrawer) setSearch(false); });
  document.addEventListener("keydown", function (event) { if (event.key === "Escape") { setMenu(false); setSearch(false); } });

  function applyTheme(dark, persist) {
    root.classList.toggle("dark", dark);
    if (themeButton) themeButton.setAttribute("aria-pressed", dark ? "true" : "false");
    if (themeColor) themeColor.setAttribute("content", dark ? "#191919" : "#ffffff");
    if (persist) try { localStorage.setItem("mango-theme", dark ? "dark" : "light"); } catch (error) {}
  }
  applyTheme(root.classList.contains("dark"), false);
  if (themeButton) themeButton.addEventListener("click", function () { applyTheme(!root.classList.contains("dark"), true); });

  document.querySelectorAll(".wznrys table").forEach(function (table) {
    if (table.parentElement && table.parentElement.classList.contains("table-scroll")) return;
    var wrapper = document.createElement("div");
    wrapper.className = "table-scroll";
    wrapper.tabIndex = 0;
    table.parentNode.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });

  function showToast(message, type) {
    var toast = document.createElement("div");
    toast.className = "mango-toast mango-toast--" + (type || "info");
    toast.setAttribute("role", type === "error" ? "alert" : "status");
    toast.textContent = message;
    body.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add("is-visible"); });
    window.setTimeout(function () {
      toast.classList.remove("is-visible");
      window.setTimeout(function () { toast.remove(); }, 220);
    }, 2200);
  }

  document.addEventListener("click", function (event) {
    var button = event.target.closest("[data-post-like]");
    if (!button || !body.contains(button)) return;
    event.preventDefault();
    event.stopPropagation();

    if (button.classList.contains("loading")) return;
    if (button.classList.contains("done")) {
      showToast("請勿重複點讚", "warning");
      return;
    }

    var postId = button.dataset.postId || "";
    var likeUrl = likeUrlMeta ? likeUrlMeta.content : "";
    var csrfToken = csrfMeta ? csrfMeta.content : "";
    if (!/^\d+$/.test(postId) || !likeUrl || !csrfToken) {
      showToast("點讚暫時不可用", "error");
      return;
    }

    button.classList.add("loading");
    button.disabled = true;
    fetch(likeUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8", "Accept": "application/json" },
      body: new URLSearchParams({ post_id: postId, csrf_token: csrfToken }).toString()
    }).then(function (response) {
      return response.json().catch(function () { throw new Error("invalid-response"); }).then(function (data) {
        if (!response.ok || !data.ok) throw new Error(data.error || "request-failed");
        return data;
      });
    }).then(function (data) {
      document.querySelectorAll('[data-post-like][data-post-id="' + postId + '"]').forEach(function (item) {
        item.classList.add("done");
        item.setAttribute("aria-pressed", "true");
        item.setAttribute("aria-label", "已點讚");
        item.setAttribute("title", "已點讚");
        var count = item.querySelector(".count");
        if (count) count.textContent = String(data.count || 0);
      });
      showToast(data.created ? "點讚成功" : "請勿重複點讚", data.created ? "success" : "warning");
    }).catch(function (error) {
      showToast(error.message && error.message !== "request-failed" && error.message !== "invalid-response" ? error.message : "點讚失敗，請稍後重試", "error");
    }).finally(function () {
      document.querySelectorAll('[data-post-like][data-post-id="' + postId + '"]').forEach(function (item) {
        item.classList.remove("loading");
        item.disabled = false;
      });
    });
  });

  function syncTopButton() {
    if (backToTop) backToTop.classList.toggle("showBtn", window.scrollY > 500);
  }
  syncTopButton();
  window.addEventListener("scroll", syncTopButton, { passive: true });
  if (backToTop) backToTop.addEventListener("click", function () { window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" }); });
})();
