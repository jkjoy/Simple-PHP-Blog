(function () {
  "use strict";

  document.documentElement.classList.add("js");

  var root = document.documentElement;
  var body = document.body;
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var themeToggle = document.querySelector(".clay-theme-toggle");
  var themeColor = document.querySelector("meta[data-clay-theme-color]");

  function currentTheme() {
    return root.getAttribute("data-clay-theme") === "dark" ? "dark" : "light";
  }

  function setTheme(theme, persist) {
    root.setAttribute("data-clay-theme", theme);
    if (themeToggle) themeToggle.setAttribute("aria-pressed", theme === "dark" ? "true" : "false");
    if (themeColor) themeColor.setAttribute("content", theme === "dark" ? "#1d2227" : "#dceff4");
    if (persist) {
      try { localStorage.setItem("clay-color-mode", theme); } catch (error) {}
    }
  }

  setTheme(currentTheme(), false);
  if (themeToggle) {
    themeToggle.addEventListener("click", function () {
      setTheme(currentTheme() === "dark" ? "light" : "dark", true);
    });
  }

  var menuToggle = document.querySelector(".clay-menu-toggle");
  var menuBackdrop = document.querySelector("[data-clay-menu-backdrop]");

  function setMenu(open) {
    body.classList.toggle("clay-menu-open", open);
    if (menuToggle) {
      menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
      menuToggle.setAttribute("aria-label", open ? sblogText("close_menu", "关闭菜单") : sblogText("open_menu", "打开菜单"));
    }
  }

  if (menuToggle) menuToggle.addEventListener("click", function () { setMenu(!body.classList.contains("clay-menu-open")); });
  if (menuBackdrop) menuBackdrop.addEventListener("click", function () { setMenu(false); });
  document.querySelectorAll(".clay-nav__links a").forEach(function (link) {
    link.addEventListener("click", function () { setMenu(false); });
  });
  document.addEventListener("keydown", function (event) { if (event.key === "Escape") setMenu(false); });
  window.addEventListener("resize", function () { if (window.innerWidth > 900) setMenu(false); });

  var reveals = document.querySelectorAll(".clay-reveal");
  if (reducedMotion || !("IntersectionObserver" in window)) {
    reveals.forEach(function (item) { item.classList.add("is-visible"); });
  } else {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: "0px 0px -24px" });
    reveals.forEach(function (item) { observer.observe(item); });
  }

  var backTop = document.querySelector(".clay-backtop");
  function updateBackTop() {
    var nearBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 160;
    var commentForm = document.querySelector(".comment-form");
    var formVisible = false;
    if (commentForm) {
      var formRect = commentForm.getBoundingClientRect();
      formVisible = formRect.top < window.innerHeight && formRect.bottom > 0;
    }
    if (backTop) backTop.classList.toggle("is-visible", window.scrollY > 560 && !nearBottom && !formVisible);
  }
  updateBackTop();
  window.addEventListener("scroll", updateBackTop, { passive: true });
  if (backTop) backTop.addEventListener("click", function () {
    window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
  });
})();
