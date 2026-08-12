(function () {
  "use strict";

  var root = document.documentElement;
  var center = document.querySelector(".site--header__center .inner");
  var searchToggle = document.querySelector(".search-toggle");
  var searchField = document.querySelector(".search-field");
  var themeColor = document.querySelector("[data-farallon-theme-color]");
  var themeModeButtons = document.querySelectorAll(".fixed--theme [data-theme-mode]");
  var systemTheme = window.matchMedia("(prefers-color-scheme: dark)");
  var backToTop = document.querySelector(".backToTop");
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function setSearch(open) {
    if (!center || !searchToggle) return;
    center.classList.toggle("search--active", open);
    searchToggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (open && searchField) window.setTimeout(function () { searchField.focus(); }, 120);
  }

  if (searchToggle) {
    searchToggle.addEventListener("click", function () {
      setSearch(!center.classList.contains("search--active"));
    });
  }
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") setSearch(false);
  });

  function storedThemeMode() {
    try {
      var mode = localStorage.getItem("farallon-theme") || "auto";
      return mode === "dark" || mode === "light" ? mode : "auto";
    } catch (error) {
      return "auto";
    }
  }

  function applyThemeMode(mode, persist) {
    var dark = mode === "dark" || (mode === "auto" && systemTheme.matches);
    root.classList.toggle("dark", dark);
    if (themeColor) themeColor.setAttribute("content", dark ? "#1e1e1e" : "#ffffff");
    themeModeButtons.forEach(function (button) {
      var active = button.dataset.themeMode === mode;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", active ? "true" : "false");
    });
    if (persist) {
      try { localStorage.setItem("farallon-theme", mode); } catch (error) {}
    }
  }

  applyThemeMode(storedThemeMode(), false);
  themeModeButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      applyThemeMode(button.dataset.themeMode, true);
    });
  });
  systemTheme.addEventListener("change", function () {
    if (storedThemeMode() === "auto") applyThemeMode("auto", false);
  });

  function joinCardText(elements) {
    return Array.prototype.map.call(elements, function (element) {
      return element.textContent.trim();
    }).filter(Boolean).join(" · ");
  }

  function adaptDoubanCards() {
    document.querySelectorAll(".media-link-card--douban").forEach(function (card) {
      var sourceImage = card.querySelector(".media-link-card__cover img");
      var sourceFallback = card.querySelector(".media-link-card__cover-fallback");
      var sourceTitle = card.querySelector(".media-link-card__title");
      var sourceFacts = card.querySelectorAll(".media-link-card__facts > span");
      var sourceCredits = card.querySelectorAll(".media-link-card__credits > span");
      var sourceRating = card.querySelector(".media-link-card__rating b");
      var sourceAction = card.querySelector(".media-link-card__action");

      var wrap = document.createElement("div");
      wrap.className = "doulist-item";
      var subject = document.createElement("div");
      subject.className = "doulist-subject";
      var poster = document.createElement("div");
      poster.className = "doulist-post";

      if (sourceImage) {
        var image = sourceImage.cloneNode(true);
        image.removeAttribute("style");
        poster.appendChild(image);
      } else {
        var fallback = document.createElement("span");
        fallback.className = "doulist-poster-fallback";
        fallback.textContent = sourceFallback ? sourceFallback.textContent.trim() : "豆";
        poster.appendChild(fallback);
      }

      var body = document.createElement("div");
      body.className = "doulist-content";
      var title = document.createElement("div");
      title.className = "doulist-title";
      var titleLink = document.createElement("a");
      titleLink.className = "cute";
      titleLink.href = sourceAction ? sourceAction.href : "#";
      titleLink.target = "_blank";
      titleLink.rel = "noopener noreferrer external nofollow";
      titleLink.textContent = sourceTitle ? sourceTitle.textContent.trim() : "豆瓣条目";
      title.appendChild(titleLink);

      var rating = document.createElement("div");
      rating.className = "rating";
      var ratingText = document.createElement("span");
      ratingText.className = "rating_nums";
      ratingText.textContent = sourceRating ? "豆瓣评分 : " + sourceRating.textContent.trim() : "豆瓣评分 : 暂无";
      rating.appendChild(ratingText);

      var abstract = document.createElement("div");
      abstract.className = "abstract";
      var facts = joinCardText(sourceFacts);
      var credits = Array.prototype.map.call(sourceCredits, function (element) {
        var label = element.querySelector("b");
        var value = element.querySelector("span");
        return label && value ? label.textContent.trim() + ": " + value.textContent.trim() : element.textContent.trim();
      }).filter(Boolean);
      abstract.textContent = [facts].concat(credits).filter(Boolean).join(" · ");

      body.appendChild(title);
      body.appendChild(rating);
      body.appendChild(abstract);
      subject.appendChild(poster);
      subject.appendChild(body);
      wrap.appendChild(subject);
      card.replaceWith(wrap);
    });
  }

  adaptDoubanCards();

  function initTableOfContents() {
    var content = document.querySelector(".post--single .graph");
    if (!content || document.getElementById("toc")) return;
    var headings = Array.prototype.filter.call(content.querySelectorAll("h1, h2, h3, h4, h5, h6"), function (heading) {
      return heading.textContent.trim().length > 0;
    });
    if (!headings.length) return;

    var toc = document.createElement("div");
    toc.id = "toc";
    var details = document.createElement("details");
    details.className = "toc";
    details.open = true;
    var summary = document.createElement("summary");
    summary.className = "toc-title";
    summary.textContent = "目录";
    var navigation = document.createElement("nav");
    navigation.id = "TableOfContents";
    navigation.setAttribute("aria-label", "文章目录");
    var rootList = document.createElement("ul");
    navigation.appendChild(rootList);
    details.appendChild(summary);
    details.appendChild(navigation);
    toc.appendChild(details);
    content.parentNode.insertBefore(toc, content);

    var usedIds = new Set();
    function ensureHeadingId(heading, index) {
      if (heading.id) {
        heading.id = heading.id.replace(/\s+/g, "-");
        usedIds.add(heading.id);
        return heading.id;
      }
      var base = heading.textContent.trim().replace(/\s+/g, "-").replace(/[#?&%]/g, "") || "heading-" + (index + 1);
      var id = base;
      var suffix = 1;
      while (usedIds.has(id) || document.getElementById(id)) {
        id = base + "-" + suffix++;
      }
      heading.id = id;
      usedIds.add(id);
      return id;
    }

    var firstLevel = parseInt(headings[0].tagName.substring(1), 10) || 1;
    var currentLevel = firstLevel;
    var stack = [rootList];
    headings.forEach(function (heading, index) {
      var level = parseInt(heading.tagName.substring(1), 10) || currentLevel;
      var id = ensureHeadingId(heading, index);
      while (level > currentLevel) {
        var parent = stack[stack.length - 1];
        var lastItem = parent.lastElementChild;
        var childList = document.createElement("ul");
        (lastItem || parent).appendChild(childList);
        stack.push(childList);
        currentLevel++;
      }
      while (level < currentLevel && stack.length > 1) {
        stack.pop();
        currentLevel--;
      }
      var item = document.createElement("li");
      var link = document.createElement("a");
      link.href = "#" + id;
      link.textContent = heading.textContent.trim();
      item.appendChild(link);
      stack[stack.length - 1].appendChild(item);
    });
  }

  initTableOfContents();

  function syncBackToTop() {
    if (backToTop) backToTop.classList.toggle("is-active", window.scrollY > 400);
  }
  syncBackToTop();
  window.addEventListener("scroll", syncBackToTop, { passive: true });
  if (backToTop) {
    backToTop.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
    });
  }
})();
