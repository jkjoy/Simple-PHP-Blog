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

  document.querySelectorAll(".comment-reply-button").forEach(function (button) {
    button.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M12.344 11.458A5.28 5.28 0 0 0 14 7.526C14 4.483 11.391 2 8.051 2S2 4.483 2 7.527c0 3.051 2.712 5.526 6.059 5.526a6.6 6.6 0 0 0 1.758-.236q.255.223.554.414c.784.51 1.626.768 2.512.768a.37.37 0 0 0 .355-.214.37.37 0 0 0-.03-.384 4.7 4.7 0 0 1-.857-1.958v.014z"></path></svg>';
  });

  document.querySelectorAll(".jGraph table").forEach(function (table) {
    if (table.parentElement && table.parentElement.classList.contains("jTable--scroll")) return;
    var wrapper = document.createElement("div");
    wrapper.className = "jTable--scroll";
    wrapper.tabIndex = 0;
    wrapper.setAttribute("role", "region");
    wrapper.setAttribute("aria-label", table.querySelector("caption") ? table.querySelector("caption").textContent.trim() : "表格");
    table.parentNode.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });

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
