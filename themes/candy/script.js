(function () {
  "use strict";

  var root = document.documentElement;
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  var menuButton = document.querySelector(".candy-menu");
  var menu = document.getElementById("candy-navigation");
  var progress = document.querySelector(".candy-progress");
  var progressFill = progress ? progress.querySelector("span") : null;

  function setMenu(open) {
    if (!menu || !menuButton) return;
    menu.classList.toggle("is-open", open);
    menuButton.setAttribute("aria-expanded", String(open));
    var label = menuButton.getAttribute(open ? "data-close-label" : "data-open-label");
    menuButton.setAttribute("aria-label", label);
    menuButton.setAttribute("title", label);
    if (open) {
      var firstLink = menu.querySelector("a");
      if (firstLink) firstLink.focus();
    } else if (menu.contains(document.activeElement)) {
      menuButton.focus();
    }
  }

  if (menuButton && menu) {
    menuButton.addEventListener("click", function () {
      setMenu(menuButton.getAttribute("aria-expanded") !== "true");
    });
    menu.addEventListener("click", function (event) {
      if (event.target.closest("a")) setMenu(false);
    });
    document.addEventListener("click", function (event) {
      if (!menu.contains(event.target) && !menuButton.contains(event.target)) setMenu(false);
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && menuButton.getAttribute("aria-expanded") === "true") {
        setMenu(false);
      }
    });
    window.addEventListener("resize", function () {
      if (window.innerWidth > 800) setMenu(false);
    });
  }

  var progressFrame = 0;
  function updateProgress() {
    progressFrame = 0;
    if (!progress || !progressFill) return;
    var scrollable = Math.max(0, root.scrollHeight - window.innerHeight);
    var amount = scrollable ? Math.min(1, Math.max(0, window.scrollY / scrollable)) : 1;
    progressFill.style.transform = "scaleX(" + amount + ")";
    progress.setAttribute("aria-valuenow", String(Math.round(amount * 100)));
  }
  function scheduleProgress() {
    if (!progressFrame) progressFrame = window.requestAnimationFrame(updateProgress);
  }
  updateProgress();
  window.addEventListener("scroll", scheduleProgress, { passive: true });
  window.addEventListener("resize", scheduleProgress);
  window.addEventListener("load", scheduleProgress);

  var reveals = document.querySelectorAll(".candy-reveal");
  if (reducedMotion.matches || !("IntersectionObserver" in window)) {
    reveals.forEach(function (item) { item.classList.add("is-visible"); });
  } else {
    root.classList.add("candy-js");
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.06, rootMargin: "0px 0px -24px 0px" });
    reveals.forEach(function (item) { observer.observe(item); });
  }

  if (reducedMotion.addEventListener) {
    reducedMotion.addEventListener("change", function (event) {
      if (event.matches) reveals.forEach(function (item) { item.classList.add("is-visible"); });
    });
  }
})();
