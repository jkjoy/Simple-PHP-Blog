(function () {
  "use strict";

  var root = document.documentElement;
  var searchToggle = document.querySelector("[data-once-search-toggle]");
  var searchPanel = document.querySelector("[data-once-search-panel]");
  var searchInput = document.getElementById("once-header-search");
  var drawer = document.getElementById("once-mobile-drawer");
  var backdrop = document.querySelector(".once-drawer-backdrop");
  var openButton = document.querySelector("[data-once-menu-open]");
  var closeButtons = document.querySelectorAll("[data-once-menu-close]");
  var themeToggle = document.querySelector("[data-once-theme-toggle]");
  var themeColor = document.querySelector("[data-once-theme-color]");
  var backTop = document.querySelector("[data-once-back-top]");
  var systemTheme = window.matchMedia("(prefers-color-scheme: dark)");
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function setSearch(open) {
    if (!searchPanel || !searchToggle) return;
    searchPanel.hidden = !open;
    searchToggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (open && searchInput) window.setTimeout(function () { searchInput.focus(); }, 30);
  }

  if (searchToggle) searchToggle.addEventListener("click", function () { setSearch(searchPanel.hidden); });

  function setDrawer(open) {
    if (!drawer || !backdrop || !openButton) return;
    document.body.classList.toggle("once-menu-open", open);
    drawer.setAttribute("aria-hidden", open ? "false" : "true");
    backdrop.hidden = !open;
    openButton.setAttribute("aria-expanded", open ? "true" : "false");
  }

  if (openButton) openButton.addEventListener("click", function () { setDrawer(true); });
  closeButtons.forEach(function (button) { button.addEventListener("click", function () { setDrawer(false); }); });

  function currentMode() {
    try { return localStorage.getItem("once-theme") || "auto"; } catch (error) { return "auto"; }
  }

  function applyTheme(mode, persist) {
    var dark = mode === "dark" || (mode === "auto" && systemTheme.matches);
    root.dataset.onceTheme = dark ? "dark" : "light";
    if (themeColor) themeColor.setAttribute("content", dark ? "#191919" : "#ffffff");
    if (themeToggle) themeToggle.setAttribute("aria-pressed", dark ? "true" : "false");
    if (persist) {
      try { localStorage.setItem("once-theme", mode); } catch (error) {}
    }
  }

  applyTheme(currentMode(), false);
  if (themeToggle) themeToggle.addEventListener("click", function () {
    applyTheme(root.dataset.onceTheme === "dark" ? "light" : "dark", true);
  });
  systemTheme.addEventListener("change", function () { if (currentMode() === "auto") applyTheme("auto", false); });

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape") return;
    setSearch(false);
    setDrawer(false);
  });

  function syncBackTop() {
    if (backTop) backTop.classList.toggle("is-visible", window.scrollY > 420);
  }
  syncBackTop();
  window.addEventListener("scroll", syncBackTop, { passive: true });
  if (backTop) backTop.addEventListener("click", function () {
    window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
  });
})();
