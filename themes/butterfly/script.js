(function () {
  "use strict";

  function initButterfly() {
    var root = document.documentElement;
    var body = document.body;
    var text = typeof window.sblogText === "function" ? window.sblogText : function (key, fallback) { return fallback; };
    var systemTheme = window.matchMedia("(prefers-color-scheme: dark)");
    var motion = window.matchMedia("(prefers-reduced-motion: reduce)");
    root.classList.add("bf-js");

    function stored(key) {
      try { return localStorage.getItem(key); } catch (error) { return null; }
    }

    function persist(key, value) {
      try { localStorage.setItem(key, value); } catch (error) {}
    }

    function pressed(selector, active) {
      document.querySelectorAll(selector).forEach(function (button) {
        button.setAttribute("aria-pressed", String(active));
      });
    }

    function setTheme(dark, save) {
      root.dataset.theme = dark ? "dark" : "light";
      pressed("[data-bf-theme-toggle]", dark);
      if (save) persist("butterfly-theme", root.dataset.theme);
    }

    var themeChoice = stored("butterfly-theme");
    setTheme(themeChoice === "dark" || (themeChoice !== "light" && systemTheme.matches), false);
    document.querySelectorAll("[data-bf-theme-toggle]").forEach(function (button) {
      button.addEventListener("click", function () {
        themeChoice = root.dataset.theme === "dark" ? "light" : "dark";
        setTheme(themeChoice === "dark", true);
      });
    });
    if (systemTheme.addEventListener) {
      systemTheme.addEventListener("change", function () {
        if (themeChoice !== "dark" && themeChoice !== "light") setTheme(systemTheme.matches, false);
      });
    }

    function setAside(hidden, save) {
      // Butterfly stores the single-column preference on <html>; keep the
      // project hook as well so existing compatibility rules continue to work.
      root.classList.toggle("hide-aside", hidden);
      body.classList.toggle("bf-aside-hidden", hidden);
      pressed("[data-bf-aside-toggle]", hidden);
      if (save) persist("butterfly-aside-hidden", String(hidden));
    }

    var readModeExit = null;
    function setReading(active) {
      body.classList.toggle("bf-reading", active);
      body.classList.toggle("read-mode", active);
      pressed("[data-bf-read-toggle]", active);
      if (active && !readModeExit) {
        readModeExit = document.createElement("button");
        readModeExit.type = "button";
        readModeExit.className = "exit-readmode bf-icon-button";
        readModeExit.title = text("butterfly_exit_reading", "退出阅读模式");
        readModeExit.setAttribute("aria-label", readModeExit.title);
        var exitGlyph = document.createElement("i");
        exitGlyph.className = "ri-close-line";
        exitGlyph.setAttribute("aria-hidden", "true");
        readModeExit.appendChild(exitGlyph);
        readModeExit.addEventListener("click", function () { setReading(false); });
        body.appendChild(readModeExit);
      }
      if (!active && readModeExit) {
        readModeExit.remove();
        readModeExit = null;
      }
    }

    function toggleSettings(button) {
      var panel = document.getElementById("rightside-config-hide");
      if (!panel) return;
      var open = panel.classList.toggle("show");
      button.classList.toggle("show", open);
      if (open) {
        panel.classList.add("status");
        window.setTimeout(function () { panel.classList.remove("status"); }, 300);
      }
    }

    setAside(stored("butterfly-aside-hidden") === "true", false);
    setReading(false);
    document.querySelectorAll("[data-bf-aside-toggle]").forEach(function (button) {
      button.addEventListener("click", function () { setAside(!body.classList.contains("bf-aside-hidden"), true); });
    });
    document.querySelectorAll("[data-bf-read-toggle]").forEach(function (button) {
      button.addEventListener("click", function () { setReading(!body.classList.contains("bf-reading")); });
    });
    document.querySelectorAll("[data-bf-top]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        window.scrollTo({ top: 0, behavior: motion.matches ? "auto" : "smooth" });
      });
    });
    var settingsButton = document.getElementById("rightside_config");
    if (settingsButton) settingsButton.addEventListener("click", function () { toggleSettings(settingsButton); });

    var nav = document.getElementById("nav");
    var menu = document.getElementById("sidebar-menus");
    var menuMask = document.getElementById("menu-mask");
    var menuButton = document.getElementById("bf-menu-toggle") || document.querySelector("#toggle-menu button, #toggle-menu a");
    var navWidth = 0;
    function adjustMenu(init) {
      if (!nav) return;
      if (init) {
        var blogLink = document.querySelector("#blog-info > a");
        var menus = document.getElementById("menus");
        navWidth = (blogLink ? blogLink.scrollWidth : 0) + (menus ? menus.scrollWidth : 0);
      }
      var needsMenu = window.innerWidth <= 768 || (navWidth > 0 && navWidth > nav.clientWidth - 120);
      nav.classList.toggle("hide-menu", needsMenu);
      if (!needsMenu && menu) setMenu(false, false);
    }
    function setMenu(open, restoreFocus) {
      if (!menu || !menuButton) return;
      menu.classList.toggle("open", open);
      menu.setAttribute("aria-hidden", String(!open));
      menuButton.setAttribute("aria-expanded", String(open));
      body.style.overflow = open ? "hidden" : "";
      if (menuMask) {
        menuMask.style.display = open ? "block" : "none";
        menuMask.setAttribute("aria-hidden", String(!open));
      }
      if (restoreFocus) menuButton.focus();
    }
    if (menu && menuButton) {
      menuButton.setAttribute("aria-controls", menu.id);
      setMenu(false, false);
      menuButton.addEventListener("click", function () { setMenu(!menu.classList.contains("open"), false); });
      if (menuMask) menuMask.addEventListener("click", function () { setMenu(false, true); });
      menu.addEventListener("click", function (event) {
        if (event.target instanceof Element && event.target.closest("a")) setMenu(false, false);
      });
    }
    adjustMenu(true);
    window.addEventListener("resize", function () { adjustMenu(true); }, { passive: true });

    var dialogOpeners = new WeakMap();
    function openDialog(dialog, opener) {
      if (!dialog || typeof dialog.showModal !== "function") return false;
      if (!dialog.open) {
        dialogOpeners.set(dialog, opener || document.activeElement);
        dialog.showModal();
      }
      return true;
    }
    document.querySelectorAll("dialog.bf-dialog, dialog.search-dialog, dialog.bf-lightbox").forEach(function (dialog) {
      dialog.addEventListener("click", function (event) {
        if (event.target !== dialog) return;
        if (dialog.classList.contains("bf-lightbox")) {
          dialog.close();
          return;
        }
        var bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
      });
      dialog.addEventListener("close", function () {
        var opener = dialogOpeners.get(dialog);
        if (opener && opener.isConnected) opener.focus({ preventScroll: true });
        dialogOpeners.delete(dialog);
      });
    });
    document.querySelectorAll("[data-bf-dialog-close]").forEach(function (button) {
      button.addEventListener("click", function () {
        var dialog = button.closest("dialog");
        if (dialog && typeof dialog.close === "function") dialog.close();
      });
    });
    var searchDialog = document.getElementById("bf-search-dialog") || document.querySelector("dialog.search-dialog");
    document.querySelectorAll("[data-bf-search-open]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        if (!openDialog(searchDialog, button)) return;
        event.preventDefault();
        setMenu(false, false);
        var field = searchDialog && searchDialog.querySelector('input[type="search"], input[name="q"], input[name="s"]');
        if (field) field.focus();
      });
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && menu && menu.classList.contains("open")) setMenu(false, true);
    });

    document.querySelectorAll("img[data-bf-fallback]").forEach(function (image) {
      function fallback() {
        var source = image.dataset.bfFallback;
        if (!source) return;
        delete image.dataset.bfFallback;
        image.removeAttribute("srcset");
        image.src = source;
      }
      image.addEventListener("error", fallback, { once: true });
      if (image.complete && image.naturalWidth === 0) fallback();
    });

    var lightbox = document.getElementById("bf-lightbox");
    var lightboxImage = lightbox && lightbox.querySelector("img");
    if (lightboxImage && typeof lightbox.showModal === "function") {
      document.querySelectorAll(".post-content img").forEach(function (image) {
        if (image.closest("a, button, .media-link-card, .media-card")) return;
        var picture = image.closest("picture");
        var target = picture || image;
        var button = document.createElement("button");
        button.type = "button";
        button.className = "bf-image-button";
        button.title = text("butterfly_view_image", "\u67e5\u770b\u56fe\u7247");
        button.setAttribute("aria-label", image.alt ? button.title + ": " + image.alt : button.title);
        target.before(button);
        button.appendChild(target);
        button.addEventListener("click", function () {
          lightboxImage.src = image.dataset.fullSrc || image.currentSrc || image.src;
          lightboxImage.alt = image.alt;
          openDialog(lightbox, button);
        });
      });
      lightbox.addEventListener("close", function () { lightboxImage.removeAttribute("src"); });
    }

    var usedIds = new Set();
    var idCounts = new Map();
    document.querySelectorAll("[id]").forEach(function (element) {
      usedIds.add(element.id);
      idCounts.set(element.id, (idCounts.get(element.id) || 0) + 1);
    });
    function uniqueId(base) {
      var id = base;
      var suffix = 2;
      while (usedIds.has(id)) id = base + "-" + suffix++;
      usedIds.add(id);
      return id;
    }

    function iconButton(icon, label) {
      var button = document.createElement("button");
      button.type = "button";
      button.className = "bf-icon-button";
      button.title = label;
      button.setAttribute("aria-label", label);
      var glyph = document.createElement("i");
      glyph.className = icon;
      glyph.setAttribute("aria-hidden", "true");
      button.appendChild(glyph);
      return button;
    }

    function fallbackCopy(value) {
      var focused = document.activeElement;
      var field = document.createElement("textarea");
      field.value = value;
      field.setAttribute("readonly", "");
      field.style.position = "fixed";
      field.style.left = "-9999px";
      body.appendChild(field);
      field.select();
      var copied = false;
      try { copied = document.execCommand("copy"); } catch (error) {}
      field.remove();
      if (focused && typeof focused.focus === "function") focused.focus({ preventScroll: true });
      return copied;
    }

    document.querySelectorAll(".post-content pre").forEach(function (pre, index) {
      if (pre.previousElementSibling && pre.previousElementSibling.classList.contains("bf-code-tools")) return;
      var code = pre.querySelector("code") || pre;
      var language = Array.from(code.classList).concat(Array.from(pre.classList)).find(function (name) { return /^(language|lang)-/.test(name); });
      var toolbar = document.createElement("div");
      toolbar.className = "bf-code-tools";
      var label = document.createElement("span");
      label.className = "bf-code-language";
      label.textContent = language ? language.replace(/^(language|lang)-/, "") : "Code";
      var status = document.createElement("span");
      status.className = "bf-copy-status";
      status.setAttribute("role", "status");
      status.setAttribute("aria-live", "polite");
      var copy = iconButton("ri-file-copy-line", text("butterfly_copy_code", "\u590d\u5236\u4ee3\u7801"));
      var wrap = iconButton("ri-text-wrap", text("butterfly_wrap_code", "\u4ee3\u7801\u81ea\u52a8\u6362\u884c"));
      var collapse = iconButton("ri-arrow-down-s-line", text("butterfly_collapse_code", "\u5c55\u5f00\u6216\u6536\u8d77\u4ee3\u7801"));
      if (!pre.id || idCounts.get(pre.id) > 1) pre.id = uniqueId("bf-code-" + (index + 1));
      wrap.setAttribute("aria-controls", pre.id);
      wrap.setAttribute("aria-pressed", "false");
      collapse.setAttribute("aria-controls", pre.id);
      collapse.setAttribute("aria-expanded", "true");
      toolbar.append(label, status, wrap, copy, collapse);
      pre.before(toolbar);
      wrap.addEventListener("click", function () {
        var active = pre.classList.toggle("bf-code-wrap");
        wrap.setAttribute("aria-pressed", String(active));
      });
      collapse.addEventListener("click", function () {
        var collapsed = pre.classList.toggle("bf-code-collapsed");
        pre.hidden = collapsed;
        collapse.setAttribute("aria-expanded", String(!collapsed));
      });
      var statusTimeout;
      copy.addEventListener("click", async function () {
        copy.disabled = true;
        var copied = false;
        try {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(code.textContent);
            copied = true;
          }
        } catch (error) {}
        if (!copied) copied = fallbackCopy(code.textContent);
        status.textContent = copied ? text("butterfly_code_copied", "\u5df2\u590d\u5236") : text("butterfly_copy_failed", "\u590d\u5236\u5931\u8d25");
        copy.disabled = false;
        window.clearTimeout(statusTimeout);
        statusTimeout = window.setTimeout(function () { status.textContent = ""; }, 2500);
      });
    });

    var tocSection = document.getElementById("bf-toc-section") || document.getElementById("card-toc");
    var toc = document.getElementById("bf-toc");
    var headings = [];
    var tocLinks = [];
    if (toc && tocSection) {
      if (!/^(OL|UL)$/.test(toc.tagName)) {
        var list = document.createElement("ol");
        toc.appendChild(list);
        toc = list;
      }
      toc.classList.add("toc");
      headings = Array.from(document.querySelectorAll(".post-content h1, .post-content h2, .post-content h3, .post-content h4, .post-content h5, .post-content h6")).filter(function (heading) { return heading.textContent.trim(); });
      var currentTocList = toc;
      var tocParents = [];
      var previousItem = null;
      var previousLevel = 0;
      headings.forEach(function (heading, index) {
        if (!heading.id || idCounts.get(heading.id) > 1) {
          var base = heading.textContent.trim().toLowerCase().replace(/\s+/g, "-").replace(/[#?&%/\\]/g, "");
          heading.id = uniqueId(base || "bf-section-" + (index + 1));
        }
        var level = Number(heading.tagName.slice(1));
        if (previousItem && level > previousLevel) {
          var childList = document.createElement("ol");
          childList.className = "toc-child";
          previousItem.appendChild(childList);
          tocParents.push({ level: previousLevel, list: currentTocList });
          currentTocList = childList;
        } else if (previousItem && level < previousLevel) {
          while (tocParents.length && level <= tocParents[tocParents.length - 1].level) {
            currentTocList = tocParents.pop().list;
          }
        }
        var item = document.createElement("li");
        item.className = "toc-item";
        var link = document.createElement("a");
        link.className = "toc-link";
        link.href = "#" + encodeURI(heading.id);
        var number = document.createElement("span");
        number.className = "toc-number";
        var label = document.createElement("span");
        label.className = "toc-text";
        label.textContent = heading.textContent.trim();
        link.append(number, label);
        item.appendChild(link);
        currentTocList.appendChild(item);
        tocLinks.push(link);
        previousItem = item;
        previousLevel = level;
        link.addEventListener("click", function (event) {
          event.preventDefault();
          heading.scrollIntoView({ behavior: motion.matches ? "auto" : "smooth", block: "start" });
          if (window.innerWidth < 900 && tocSection) tocSection.classList.remove("bf-toc-open");
        });
      });
      tocSection.hidden = headings.length === 0;
      tocSection.classList.remove("bf-toc-open");
    }
    document.querySelectorAll("[data-bf-toc-toggle]").forEach(function (button) {
      button.hidden = headings.length === 0;
      button.setAttribute("aria-expanded", "false");
      button.addEventListener("click", function () {
        if (!tocSection || !headings.length) return;
        setReading(false);
        setAside(false, true);
        tocSection.hidden = false;
        var open = tocSection.classList.toggle("bf-toc-open");
        button.setAttribute("aria-expanded", String(open));
      });
    });

    var progress = document.getElementById("bf-progress");
    var tocPercentage = document.querySelector("#card-toc .toc-percentage") || progress;
    var article = document.getElementById("article-container");
    var tocContent = toc && toc.closest(".toc-content");
    var pendingScroll = false;
    var activeIndex = -1;
    var lastScroll = window.scrollY || 0;
    var rightside = document.getElementById("rightside");
    var goUp = document.getElementById("go-up");
    function updateScroll() {
      pendingScroll = false;
      var currentTop = window.scrollY || document.documentElement.scrollTop || 0;
      var scrollingDown = currentTop > lastScroll;
      var scrollable = Math.max(0, root.scrollHeight - window.innerHeight);
      var header = document.getElementById("page-header");
      if (header && nav) {
        if (currentTop > 56) {
          header.classList.add("nav-fixed");
          header.classList.toggle("nav-visible", !scrollingDown);
        } else {
          header.classList.remove("nav-fixed", "nav-visible");
        }
      }
      if (rightside) {
        if (document.body.scrollHeight <= window.innerHeight + 56) {
          rightside.style.opacity = "1";
          rightside.style.transform = "translateX(-58px)";
        } else if (currentTop > 56) {
          rightside.style.opacity = "0.8";
          rightside.style.transform = "translateX(-58px)";
        } else {
          rightside.style.opacity = "";
          rightside.style.transform = "";
        }
      }
      if (progress) {
        var articleHeight = article ? article.clientHeight : 0;
        var articleTop = article ? article.offsetTop : 0;
        var articleScrollable = articleHeight > window.innerHeight ? articleHeight - window.innerHeight : scrollable;
        var articleRatio = articleScrollable > 0 ? (currentTop - articleTop) / articleScrollable : 1;
        var percent = Math.round(Math.min(1, Math.max(0, articleRatio)) * 100);
        progress.textContent = percent + "%";
      }
      if (tocPercentage) tocPercentage.textContent = progress ? progress.textContent : "";
      if (goUp) {
        var percentAtTop = scrollable ? Math.round(Math.min(1, Math.max(0, currentTop / scrollable)) * 100) : 100;
        goUp.classList.toggle("show-percent", percentAtTop < 95);
        var percentLabel = goUp.querySelector(".scroll-percent");
        if (percentLabel) percentLabel.textContent = percentAtTop + "%";
      }
      if (headings.length) {
        var nextIndex = 0;
        for (var index = 0; index < headings.length; index++) {
          if (headings[index].getBoundingClientRect().top <= 120) nextIndex = index;
        }
        if (nextIndex !== activeIndex) {
          activeIndex = nextIndex;
          if (toc) toc.querySelectorAll(".toc-item.active").forEach(function (item) { item.classList.remove("active"); });
          tocLinks.forEach(function (link, linkIndex) {
            var active = linkIndex === activeIndex;
            link.classList.toggle("active", active);
            if (active) link.setAttribute("aria-current", "location");
            else link.removeAttribute("aria-current");
            if (active) {
              var parent = link.parentElement;
              while (parent && parent !== toc) {
                if (parent.classList.contains("toc-item")) parent.classList.add("active");
                parent = parent.parentElement;
              }
              if (tocContent) {
                var linkTop = link.offsetTop;
                var linkBottom = linkTop + link.offsetHeight;
                if (linkTop < tocContent.scrollTop + 20) tocContent.scrollTop = Math.max(0, linkTop - 150);
                else if (linkBottom > tocContent.scrollTop + tocContent.clientHeight - 20) tocContent.scrollTop = linkTop - tocContent.clientHeight + 150;
              }
            }
          });
        }
      }
      lastScroll = currentTop;
    }
    function queueScroll() {
      if (pendingScroll) return;
      pendingScroll = true;
      requestAnimationFrame(updateScroll);
    }
    window.addEventListener("scroll", queueScroll, { passive: true });
    window.addEventListener("resize", queueScroll, { passive: true });
    window.addEventListener("load", queueScroll);
    if (typeof ResizeObserver === "function") new ResizeObserver(queueScroll).observe(body);
    updateScroll();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initButterfly, { once: true });
  else initButterfly();
}());
