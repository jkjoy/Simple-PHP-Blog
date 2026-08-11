(function () {
  "use strict";

  var root = document.documentElement;
  var body = document.body;
  var themeButton = document.getElementById("paper-theme-toggle");
  var menuButton = document.getElementById("paper-menu-toggle");
  var navPanel = document.getElementById("paper-nav-panel");
  var themeMeta = document.querySelector("[data-paper-theme-color]");
  var scheme = window.matchMedia("(prefers-color-scheme: dark)");
  var storageKey = "paper-theme";

  function setTheme(theme, persist) {
    root.dataset.paperTheme = theme;
    if (themeButton) {
      themeButton.setAttribute("aria-pressed", theme === "dark" ? "true" : "false");
    }
    if (themeMeta) {
      themeMeta.setAttribute("content", theme === "dark" ? "#242424" : "#faf8f1");
    }
    if (persist) {
      try { localStorage.setItem(storageKey, theme); } catch (error) { /* Storage may be unavailable. */ }
    }
  }

  function closeMenu() {
    body.classList.remove("paper-menu-open");
    if (menuButton) {
      menuButton.setAttribute("aria-expanded", "false");
      menuButton.setAttribute("aria-label", menuButton.dataset.openLabel || "Open menu");
    }
  }

  setTheme(root.dataset.paperTheme === "dark" ? "dark" : "light", false);

  if (themeButton) {
    themeButton.addEventListener("click", function () {
      setTheme(root.dataset.paperTheme === "dark" ? "light" : "dark", true);
    });
  }

  if (menuButton && navPanel) {
    menuButton.dataset.openLabel = menuButton.getAttribute("aria-label") || "Open menu";
    menuButton.dataset.closeLabel = document.documentElement.lang.indexOf("zh") === 0 ? "关闭菜单" : "Close menu";
    menuButton.addEventListener("click", function () {
      var open = !body.classList.contains("paper-menu-open");
      body.classList.toggle("paper-menu-open", open);
      menuButton.setAttribute("aria-expanded", open ? "true" : "false");
      menuButton.setAttribute("aria-label", open ? menuButton.dataset.closeLabel : menuButton.dataset.openLabel);
    });
    navPanel.addEventListener("click", function (event) {
      if (event.target.closest("a")) closeMenu();
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeMenu();
  });

  window.addEventListener("resize", function () {
    if (window.innerWidth >= 860) closeMenu();
  });

  scheme.addEventListener("change", function (event) {
    var saved = null;
    try { saved = localStorage.getItem(storageKey); } catch (error) { /* Storage may be unavailable. */ }
    if (saved !== "light" && saved !== "dark") {
      setTheme(event.matches ? "dark" : "light", false);
    }
  });
})();
