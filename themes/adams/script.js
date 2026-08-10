(() => {
  'use strict';

  const body = document.body;
  const infos = document.querySelector('.infos');
  const settingsTool = document.querySelector('.setting_tool');
  const backTop = document.querySelector('.back2top');
  const fixedTitle = document.querySelector('.infos .fixed-title');
  const fixedMenus = document.querySelector('.infos .fixed-menus');

  const storageGet = (key) => {
    try { return localStorage.getItem(key) || ''; } catch (error) { return ''; }
  };

  const storageSet = (key, value) => {
    try {
      if (value) localStorage.setItem(key, value);
      else localStorage.removeItem(key);
    } catch (error) {}
  };

  const applyPreferences = () => {
    body.classList.remove('sepia', 'night', 'serif');
    const color = storageGet('adams_color_style');
    const font = storageGet('adams_font_style');
    if (color === 'sepia' || color === 'night') body.classList.add(color);
    if (font === 'serif') body.classList.add('serif');
  };

  const updateScrollState = () => {
    const fixed = window.scrollY > 200;
    if (backTop) backTop.style.display = fixed ? 'block' : 'none';
    if (!infos) return;
    infos.classList.toggle('fixed', fixed);
    if (fixed && fixedTitle) {
      fixedTitle.innerHTML = document.querySelector('h1.fullname')?.innerHTML || document.title;
    }
    if (fixed && fixedMenus) {
      fixedMenus.innerHTML = document.querySelector('nav.header_nav')?.innerHTML || '';
    }
  };

  const activateTool = (target) => {
    if (!settingsTool) return;
    if (target.closest('.back2top')) {
      window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      return;
    }
    if (target.closest('.sosearch')) {
      const open = !settingsTool.classList.contains('search');
      settingsTool.classList.toggle('search', open);
      settingsTool.classList.remove('colors');
      if (open) window.setTimeout(() => document.querySelector('.search-key')?.focus(), 50);
      return;
    }
    if (target.closest('.socolor')) {
      const open = !settingsTool.classList.contains('colors');
      settingsTool.classList.toggle('colors', open);
      settingsTool.classList.remove('search');
    }
  };

  settingsTool?.addEventListener('click', (event) => {
    const color = event.target.closest('[data-adams-color]');
    const font = event.target.closest('[data-adams-font]');
    if (color) {
      const value = color.dataset.adamsColor || 'default';
      storageSet('adams_color_style', value === 'default' ? '' : value);
      applyPreferences();
      return;
    }
    if (font) {
      const value = font.dataset.adamsFont || 'sans';
      storageSet('adams_font_style', value === 'serif' ? 'serif' : '');
      applyPreferences();
      return;
    }
    activateTool(event.target);
  });

  settingsTool?.addEventListener('keydown', (event) => {
    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('a[role="button"]')) {
      event.preventDefault();
      activateTool(event.target);
    }
  });

  document.addEventListener('click', (event) => {
    if (settingsTool && !settingsTool.contains(event.target)) {
      settingsTool.classList.remove('search', 'colors');
    }
  });

  const share = document.querySelector('.infos .share');
  share?.addEventListener('click', () => {
    infos?.classList.remove('donate-close');
    infos?.classList.toggle('share-close');
  });

  const qrBox = document.querySelector('.qrcode .img-box');
  if (qrBox && typeof window.QRCode === 'function') {
    new window.QRCode(qrBox, window.location.href);
  }

  const relativeTime = (timestamp) => {
    const seconds = Math.max(1, Math.floor((Date.now() - timestamp) / 1000));
    if (seconds >= 31536000) return `${Math.floor(seconds / 31536000)}年前`;
    if (seconds >= 2592000) return `${Math.floor(seconds / 2592000)}个月前`;
    if (seconds >= 86400) return `${Math.floor(seconds / 86400)}天前`;
    if (seconds >= 3600) return `${Math.floor(seconds / 3600)}小时前`;
    if (seconds >= 60) return `${Math.floor(seconds / 60)}分钟前`;
    return `${seconds}秒前`;
  };

  document.querySelectorAll('.infos time, .post-list time, .comment-item__time').forEach((time) => {
    const timestamp = Date.parse(time.dateTime || time.getAttribute('title') || '');
    if (!Number.isNaN(timestamp)) {
      time.title = time.dateTime || new Date(timestamp).toISOString();
      time.textContent = relativeTime(timestamp);
    }
  });

  const closeViewer = () => document.querySelector('.view-img')?.remove();
  document.querySelectorAll('.post_article .post-content img').forEach((image) => {
    image.addEventListener('click', (event) => {
      event.preventDefault();
      closeViewer();
      const viewer = document.createElement('div');
      viewer.className = 'view-img';
      viewer.innerHTML = '<span>loading...</span>';
      const fullImage = new Image();
      fullImage.alt = image.alt || 'ViewImage';
      fullImage.onload = () => viewer.replaceChildren(fullImage);
      fullImage.src = image.currentSrc || image.src;
      viewer.addEventListener('click', closeViewer);
      document.body.appendChild(viewer);
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      settingsTool?.classList.remove('search', 'colors');
      infos?.classList.remove('share-close', 'donate-close');
      closeViewer();
    }
  });

  applyPreferences();
  updateScrollState();
  window.addEventListener('scroll', updateScrollState, { passive: true });
})();
