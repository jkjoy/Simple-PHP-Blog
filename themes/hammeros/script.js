(function () {
  "use strict";

  document.documentElement.classList.add("js");

  var root = document.documentElement;
  var body = document.body;
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var themeToggle = document.querySelector(".hammer-theme-toggle");
  var themeColor = document.querySelector("meta[data-hammer-theme-color]");

  function currentTheme() {
    return root.getAttribute("data-theme") === "dark" ? "dark" : "light";
  }

  function syncTheme(theme, persist) {
    root.setAttribute("data-theme", theme);
    if (themeToggle) themeToggle.setAttribute("aria-pressed", theme === "dark" ? "true" : "false");
    if (themeColor) themeColor.setAttribute("content", theme === "dark" ? "#1c1d1c" : "#e7eaed");
    if (persist) {
      try { localStorage.setItem("hammeros-theme", theme); } catch (error) {}
    }
  }

  syncTheme(currentTheme(), false);
  if (themeToggle) {
    themeToggle.addEventListener("click", function () {
      syncTheme(currentTheme() === "dark" ? "light" : "dark", true);
    });
  }

  var menuToggle = document.querySelector(".hammer-menu-toggle");
  var navBackdrop = document.querySelector("[data-hammer-nav-backdrop]");

  function setMenu(open) {
    body.classList.toggle("nav-open", open);
    if (menuToggle) {
      menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
      menuToggle.setAttribute("aria-label", open
        ? sblogText("close_menu", "关闭菜单")
        : sblogText("open_menu", "打开菜单"));
    }
  }

  if (menuToggle) menuToggle.addEventListener("click", function () { setMenu(!body.classList.contains("nav-open")); });
  if (navBackdrop) navBackdrop.addEventListener("click", function () { setMenu(false); });
  document.querySelectorAll(".hammer-nav a").forEach(function (link) {
    link.addEventListener("click", function () { setMenu(false); });
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") setMenu(false);
  });
  window.addEventListener("resize", function () {
    if (window.innerWidth > 980) setMenu(false);
  });

  var clock = document.querySelector("[data-hammer-clock]");
  function updateClock() {
    if (!clock) return;
    clock.textContent = new Intl.DateTimeFormat("zh-CN", { hour: "2-digit", minute: "2-digit", hour12: false }).format(new Date());
  }
  updateClock();
  window.setInterval(updateClock, 30000);

  var backTop = document.querySelector(".hammer-backtop");
  function updateBackTop() {
    if (backTop) backTop.classList.toggle("is-visible", window.scrollY > 520);
  }
  updateBackTop();
  window.addEventListener("scroll", updateBackTop, { passive: true });
  if (backTop) backTop.addEventListener("click", function () { window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" }); });

  var reveals = document.querySelectorAll(".reveal");
  if (reveals.length) {
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
      }, { threshold: 0.08, rootMargin: "0px 0px -28px" });
      reveals.forEach(function (item) { observer.observe(item); });
    }
  }

  var buddy = document.querySelector("[data-hammer-buddy]");
  var buddyMessage = document.querySelector("[data-hammer-message]");
  var buddyTimer = 0;
  var messages = [
    sblogText("hammer_message_1", "今天也辛苦了，慢慢读。"),
    sblogText("hammer_message_2", "我会把重要的内容放在前面。"),
    sblogText("hammer_message_3", "偶尔停下来，看看远处也很好。"),
    sblogText("hammer_message_4", "系统正常，心情也要在线。")
  ];
  var messageIndex = 0;

  if (buddy) {
    buddy.addEventListener("click", function () {
      window.clearTimeout(buddyTimer);
      buddy.classList.add("is-happy");
      if (buddyMessage) {
        buddyMessage.textContent = messages[messageIndex % messages.length];
        messageIndex += 1;
      }
      buddyTimer = window.setTimeout(function () { buddy.classList.remove("is-happy"); }, 1400);
    });

    if (!reducedMotion && window.matchMedia("(pointer: fine)").matches) {
      var pendingFrame = 0;
      document.addEventListener("pointermove", function (event) {
        if (pendingFrame) return;
        pendingFrame = window.requestAnimationFrame(function () {
          var rect = buddy.getBoundingClientRect();
          var x = Math.max(-3, Math.min(3, (event.clientX - (rect.left + rect.width / 2)) / 90));
          var y = Math.max(-2, Math.min(2, (event.clientY - (rect.top + 50)) / 110));
          buddy.style.setProperty("--eye-x", x.toFixed(2) + "px");
          buddy.style.setProperty("--eye-y", y.toFixed(2) + "px");
          pendingFrame = 0;
        });
      }, { passive: true });
    }
  }
})();
