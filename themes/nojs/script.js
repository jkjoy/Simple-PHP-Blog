document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".article__content a").forEach((link) => {
    if (link instanceof HTMLAnchorElement && link.hostname && link.hostname !== window.location.hostname) {
      link.target = "_blank";
      link.rel = "noopener noreferrer";
    }
  });
});
