(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', callback, { once: true });
    else callback();
  }

  ready(function () {
    var body = document.body;
    var menu = document.querySelector('[data-menu]');
    var menuToggle = document.querySelector('[data-menu-toggle]');
    var themeToggle = document.querySelector('[data-theme-toggle]');
    var comments = document.querySelector('#post-comments');
    var commentsToggle = document.querySelector('[data-comments-toggle]');
    var commentsClose = document.querySelector('[data-comments-close]');
    var topButton = document.querySelector('[data-scroll-top]');
    var bottomButton = document.querySelector('[data-scroll-bottom]');
    var qrModal = document.querySelector('[data-qr-modal]');
    var qrCode = document.querySelector('[data-qr-code]');
    var lightbox = document.querySelector('[data-lightbox]');

    function setMenu(open) {
      if (!menu || !menuToggle) return;
      menu.classList.toggle('is-open', open);
      menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (menuToggle) menuToggle.addEventListener('click', function () { setMenu(!menu.classList.contains('is-open')); });
    document.addEventListener('click', function (event) {
      if (menu && menu.classList.contains('is-open') && !event.target.closest('.photo-navbar')) setMenu(false);
    });

    if (themeToggle) themeToggle.addEventListener('click', function () {
      var dark = document.documentElement.classList.toggle('photo-dark');
      try { localStorage.setItem('photograph-theme', dark ? 'dark' : 'light'); } catch (error) {}
      var meta = document.querySelector('[data-photograph-theme-color]');
      if (meta) meta.setAttribute('content', dark ? '#1d1d1d' : '#ffffff');
    });

    function setComments(open) {
      if (!comments) return;
      comments.classList.toggle('is-open', open);
      comments.setAttribute('aria-hidden', open ? 'false' : 'true');
      body.classList.toggle('is-comments-open', open);
    }

    if (commentsToggle) commentsToggle.addEventListener('click', function () { setComments(!comments.classList.contains('is-open')); });
    if (commentsClose) commentsClose.addEventListener('click', function () { setComments(false); });
    if (comments && window.location.hash.indexOf('#comment-') === 0) setComments(true);

    function updateScrollTools() {
      if (topButton) topButton.classList.toggle('is-visible', window.scrollY > 100);
      if (bottomButton) bottomButton.style.visibility = window.scrollY > document.documentElement.scrollHeight - window.innerHeight - 100 ? 'hidden' : 'visible';
    }
    window.addEventListener('scroll', updateScrollTools, { passive: true });
    updateScrollTools();
    if (topButton) topButton.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    if (bottomButton) bottomButton.addEventListener('click', function () { window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' }); });

    function closeQr() { if (qrModal) qrModal.hidden = true; }
    var qrToggle = document.querySelector('[data-qr-toggle]');
    if (qrToggle) qrToggle.addEventListener('click', function () {
      if (!qrModal || !qrCode) return;
      qrModal.hidden = false;
      if (!qrCode.hasChildNodes() && typeof window.AraleQRCode === 'function') {
        var qr = new window.AraleQRCode({ render: 'canvas', correctLevel: 1, text: window.location.href, size: 200, background: '#fff', foreground: '#000' });
        var image = new Image();
        image.alt = '';
        image.src = qr.toDataURL('image/png');
        qrCode.appendChild(image);
      }
    });
    var qrClose = document.querySelector('[data-qr-close]');
    if (qrClose) qrClose.addEventListener('click', closeQr);

    function closeLightbox() {
      if (!lightbox) return;
      lightbox.hidden = true;
      body.style.overflow = '';
    }
    document.querySelectorAll('[data-photo-lightbox]').forEach(function (item) {
      item.addEventListener('click', function () {
        if (!lightbox) return;
        var image = lightbox.querySelector('img');
        var caption = lightbox.querySelector('figcaption');
        image.src = item.getAttribute('data-src') || '';
        image.alt = item.getAttribute('data-caption') || '';
        caption.textContent = item.getAttribute('data-caption') || '';
        lightbox.hidden = false;
        body.style.overflow = 'hidden';
        var closeButton = lightbox.querySelector('.photo-lightbox__close');
        if (closeButton) closeButton.focus();
      });
    });
    document.querySelectorAll('[data-lightbox-close]').forEach(function (button) { button.addEventListener('click', closeLightbox); });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      setMenu(false);
      closeQr();
      closeLightbox();
      setComments(false);
    });
  });
})();
