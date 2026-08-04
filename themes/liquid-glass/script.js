(function () {
  "use strict";

  document.documentElement.classList.add("js");

  var root = document.documentElement;
  var body = document.body;
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var themeToggle = document.querySelector(".aqua-theme-toggle");
  var themeColor = document.querySelector("meta[data-aqua-theme-color]");

  function currentTheme() {
    return root.getAttribute("data-theme") === "dark" ? "dark" : "light";
  }

  function setTheme(theme, persist) {
    root.setAttribute("data-theme", theme);
    if (themeToggle) themeToggle.setAttribute("aria-pressed", theme === "dark" ? "true" : "false");
    if (themeColor) themeColor.setAttribute("content", theme === "dark" ? "#111316" : "#eef2f7");
    if (persist) {
      try { localStorage.setItem("aqua-theme", theme); } catch (error) {}
    }
  }

  setTheme(currentTheme(), false);
  if (themeToggle) {
    themeToggle.addEventListener("click", function () {
      setTheme(currentTheme() === "dark" ? "light" : "dark", true);
    });
  }

  var menuToggle = document.querySelector(".aqua-menu-toggle");
  var menuBackdrop = document.querySelector("[data-aqua-menu-backdrop]");

  function setMenu(open) {
    body.classList.toggle("aqua-menu-open", open);
    if (menuToggle) {
      menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
      menuToggle.setAttribute("aria-label", open ? "关闭菜单" : "打开菜单");
    }
  }

  if (menuToggle) menuToggle.addEventListener("click", function () { setMenu(!body.classList.contains("aqua-menu-open")); });
  if (menuBackdrop) menuBackdrop.addEventListener("click", function () { setMenu(false); });
  document.querySelectorAll(".aqua-nav__links a").forEach(function (link) {
    link.addEventListener("click", function () { setMenu(false); });
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") setMenu(false);
  });
  window.addEventListener("resize", function () {
    if (window.innerWidth > 980) setMenu(false);
  });

  var reveals = document.querySelectorAll(".aqua-reveal");
  if (reducedMotion || !("IntersectionObserver" in window)) {
    reveals.forEach(function (item) { item.classList.add("is-visible"); });
  } else {
    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: "0px 0px -30px" });
    reveals.forEach(function (item) { revealObserver.observe(item); });
  }

  var backTop = document.querySelector(".aqua-backtop");
  function updateBackTop() {
    if (backTop) backTop.classList.toggle("is-visible", window.scrollY > 560);
  }
  updateBackTop();
  window.addEventListener("scroll", updateBackTop, { passive: true });
  if (backTop) {
    backTop.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
    });
  }
})();
