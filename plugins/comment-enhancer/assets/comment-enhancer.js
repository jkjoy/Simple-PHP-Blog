(() => {
  "use strict";

  const selector = ".comment-enhancer-meta__icon";
  const icons = [...document.querySelectorAll(selector)];

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    const focused = document.activeElement?.matches?.(selector) ? document.activeElement : null;
    const icon = focused || document.querySelector(`${selector}:hover`);
    if (!icon) return;
    icon.dataset.tooltipDismissed = "";
    icon.blur();
  });

  icons.forEach((icon) => {
    icon.addEventListener("pointerleave", () => delete icon.dataset.tooltipDismissed);
    icon.addEventListener("focusin", () => delete icon.dataset.tooltipDismissed);
    icon.addEventListener("focusout", () => {
      if (!icon.matches(":hover")) delete icon.dataset.tooltipDismissed;
    });
  });
})();
