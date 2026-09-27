function sblogText(key, fallback, variables = {}) {
  const messages = window.sblogI18n;
  const text = typeof messages?.[key] === "string" ? messages[key] : fallback;
  return text.replace(/\{([A-Za-z_][A-Za-z0-9_.-]*)\}/g, (placeholder, name) => (
    Object.prototype.hasOwnProperty.call(variables, name) ? String(variables[name]) : placeholder
  ));
}

function initComments() {
  document.querySelectorAll(".comments").forEach((root) => {
    const form = root.querySelector(".comment-form");
    if (!form) {
      root.addEventListener("click", (event) => event.stopPropagation());
      return;
    }

    const parentInput = form.querySelector("[data-comment-parent-id]");
    const replyState = form.querySelector("[data-comment-reply-state]");
    const replyName = form.querySelector("[data-comment-reply-name]");
    const cancelButton = form.querySelector("[data-comment-reply-cancel]");
    const content = form.querySelector("#comment-content");
    const replyButtons = [...root.querySelectorAll("[data-comment-reply]")];
    let activeReplyButton = null;

    const setReply = (button, focusContent = true) => {
      const commentId = button.dataset.commentId || "";
      const author = button.dataset.commentAuthor || "";
      if (!parentInput || !replyState || !replyName || !content || !/^\d+$/.test(commentId)) return;

      parentInput.value = commentId;
      replyState.hidden = false;
      activeReplyButton = button;
      replyButtons.forEach((item) => {
        item.setAttribute("aria-pressed", item === button ? "true" : "false");
      });
      cancelButton?.setAttribute("aria-label", sblogText("cancel_reply_to", "取消回复 @{author}", { author }));

      if (focusContent) {
        replyName.textContent = "";
        requestAnimationFrame(() => {
          if (parentInput.value !== commentId) return;
          replyName.textContent = `@${author}`;
          form.scrollIntoView({
            behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
            block: "start",
          });
          requestAnimationFrame(() => {
            if (parentInput.value === commentId) content.focus({ preventScroll: true });
          });
        });
      } else {
        replyName.textContent = `@${author}`;
      }
    };

    const clearReply = () => {
      if (!parentInput || !replyState || !replyName) return;
      const returnTarget = activeReplyButton;
      parentInput.value = "";
      replyName.textContent = "";
      replyState.hidden = true;
      activeReplyButton = null;
      replyButtons.forEach((item) => item.setAttribute("aria-pressed", "false"));
      cancelButton?.setAttribute("aria-label", sblogText("cancel_reply", "取消回复"));
      (returnTarget || content)?.focus();
    };

    root.addEventListener("click", (event) => {
      event.stopPropagation();
      if (!(event.target instanceof Element)) return;
      const replyButton = event.target.closest("[data-comment-reply]");
      if (replyButton && root.contains(replyButton)) {
        setReply(replyButton);
        return;
      }
      if (event.target.closest("[data-comment-reply-cancel]")) clearReply();
    });

    const initialReply = replyButtons.find((button) => button.dataset.commentId === parentInput?.value);
    if (initialReply) setReply(initialReply, false);
  });
}

function initSiteTools() {
  const tools = document.querySelector("[data-site-tools]");
  if (!tools) return;

  const progress = tools.querySelector("[data-scroll-progress]");
  const backToTop = tools.querySelector("[data-back-to-top]");
  const percent = tools.querySelector("[data-scroll-percent]");
  const themeToggle = tools.querySelector("[data-public-theme-toggle]");
  const root = document.documentElement;
  const systemDark = window.matchMedia("(prefers-color-scheme: dark)");
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  let progressFrame = 0;

  const updateProgress = () => {
    progressFrame = 0;
    const scrollable = Math.max(0, root.scrollHeight - root.clientHeight);
    const value = scrollable > 0 ? Math.round(Math.min(1, Math.max(0, window.scrollY / scrollable)) * 100) : 0;
    progress.style.setProperty("--scroll-progress", `${value}%`);
    percent.textContent = `${value}%`;
  };
  const scheduleProgress = () => {
    if (!progressFrame) progressFrame = window.requestAnimationFrame(updateProgress);
  };

  backToTop.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: reducedMotion.matches ? "auto" : "smooth" });
  });
  window.addEventListener("scroll", scheduleProgress, { passive: true });
  window.addEventListener("resize", scheduleProgress);
  window.addEventListener("pageshow", scheduleProgress);
  updateProgress();

  const isDark = () => root.dataset.publicTheme === "dark"
    || (!root.dataset.publicTheme && systemDark.matches);
  const updateThemeToggle = () => {
    const dark = isDark();
    const label = dark
      ? sblogText("switch_to_light", "切换到浅色模式")
      : sblogText("switch_to_dark", "切换到深色模式");
    themeToggle.setAttribute("aria-label", label);
    themeToggle.setAttribute("aria-pressed", dark ? "true" : "false");
    themeToggle.setAttribute("title", label);
  };

  themeToggle.addEventListener("click", () => {
    const next = isDark() ? "light" : "dark";
    root.dataset.publicTheme = next;
    try {
      localStorage.setItem("sblog-public-theme", next);
    } catch (error) {
      // Keep the selected mode for this page even if storage is unavailable.
    }
    updateThemeToggle();
  });
  if (typeof systemDark.addEventListener === "function") {
    systemDark.addEventListener("change", updateThemeToggle);
  } else {
    systemDark.addListener(updateThemeToggle);
  }
  updateThemeToggle();
}

document.addEventListener("DOMContentLoaded", initComments);
document.addEventListener("DOMContentLoaded", initSiteTools);
