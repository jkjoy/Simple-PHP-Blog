(function () {
  "use strict";

  var root = document.documentElement;
  var body = document.body;
  var menu = document.querySelector(".site--nav");
  var menuButton = document.querySelector(".menu--icon");
  var menuClose = document.querySelector(".nav--close");
  var mask = document.querySelector(".mask");
  var backToTop = document.querySelector(".backToTop");
  var themeColor = document.querySelector("[data-jaguar-theme-color]");
  var themeButtons = document.querySelectorAll("[data-theme-mode]");
  var systemTheme = window.matchMedia("(prefers-color-scheme: dark)");
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function setMenu(open) {
    if (!menu || !menuButton) return;
    menu.classList.toggle("is-active", open);
    body.classList.toggle("menu--actived", open);
    menuButton.setAttribute("aria-expanded", open ? "true" : "false");
    if (open && menuClose) menuClose.focus();
  }

  if (menuButton) menuButton.addEventListener("click", function () { setMenu(true); });
  if (menuClose) menuClose.addEventListener("click", function () { setMenu(false); });
  if (mask) mask.addEventListener("click", function () { setMenu(false); });
  if (menu) menu.addEventListener("click", function (event) {
    if (event.target.closest("a") && window.innerWidth <= 900) setMenu(false);
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") setMenu(false);
  });

  function storedTheme() {
    try {
      var value = localStorage.getItem("jaguar-theme") || "auto";
      return value === "dark" || value === "light" ? value : "auto";
    } catch (error) {
      return "auto";
    }
  }

  function applyTheme(mode, persist) {
    var dark = mode === "dark" || (mode === "auto" && systemTheme.matches);
    root.classList.toggle("dark", dark);
    if (themeColor) themeColor.setAttribute("content", dark ? "#1e1e1e" : "#ffffff");
    themeButtons.forEach(function (button) {
      var active = button.dataset.themeMode === mode;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", active ? "true" : "false");
    });
    if (persist) {
      try { localStorage.setItem("jaguar-theme", mode); } catch (error) {}
    }
  }

  applyTheme(storedTheme(), false);
  themeButtons.forEach(function (button) {
    button.addEventListener("click", function () { applyTheme(button.dataset.themeMode, true); });
  });
  systemTheme.addEventListener("change", function () {
    if (storedTheme() === "auto") applyTheme("auto", false);
  });

  function initTableOfContents() {
    var content = document.querySelector(".jArticle .jGraph");
    if (!content || document.getElementById("jaguar-toc")) return;
    var headings = Array.prototype.filter.call(content.querySelectorAll("h2, h3, h4, h5, h6"), function (heading) {
      return heading.textContent.trim() !== "";
    });
    if (!headings.length) return;

    var details = document.createElement("details");
    details.id = "jaguar-toc";
    details.className = "jArticle--toc";
    details.open = true;
    var summary = document.createElement("summary");
    summary.textContent = "目录";
    var list = document.createElement("ul");
    var used = new Set();
    headings.forEach(function (heading, index) {
      var base = heading.id || heading.textContent.trim().replace(/\s+/g, "-").replace(/[^\w\u4e00-\u9fff-]/g, "") || "heading-" + (index + 1);
      var id = base;
      var suffix = 2;
      while (used.has(id) || (document.getElementById(id) && document.getElementById(id) !== heading)) id = base + "-" + suffix++;
      heading.id = id;
      used.add(id);
      var item = document.createElement("li");
      item.style.marginLeft = Math.max(0, parseInt(heading.tagName.slice(1), 10) - 2) * 14 + "px";
      var link = document.createElement("a");
      link.href = "#" + id;
      link.textContent = heading.textContent.trim();
      item.appendChild(link);
      list.appendChild(item);
    });
    details.appendChild(summary);
    details.appendChild(list);
    content.parentNode.insertBefore(details, content);
  }

  initTableOfContents();

  document.querySelectorAll(".post--share").forEach(function (button) {
    button.addEventListener("click", function () {
      navigator.clipboard.writeText(window.location.href).then(function () {
        var notice = document.createElement("div");
        notice.className = "notice--wrapper is-active";
        notice.textContent = "复制成功";
        body.appendChild(notice);
        window.setTimeout(function () { notice.remove(); }, 3000);
      });
    });
  });

  function syncBackToTop() {
    if (backToTop) backToTop.classList.toggle("is-active", window.scrollY > 200);
  }
  syncBackToTop();
  window.addEventListener("scroll", syncBackToTop, { passive: true });
  if (backToTop) backToTop.addEventListener("click", function () {
    window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
  });
})();
