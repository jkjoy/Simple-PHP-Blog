/* Stack for SBlog. Original theme: Jimmy Cai (CaiJimmy), GPL-3.0-only. */
(() => {
  'use strict';

  const body = document.body;
  if (!body.classList.contains('stack-theme')) return;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const enabled = (name) => body.dataset[name] !== '0';

  const systemScheme = window.matchMedia('(prefers-color-scheme: dark)');
  const validScheme = (value) => ['auto', 'light', 'dark'].includes(value);
  let scheme = validScheme(body.dataset.stackScheme) ? body.dataset.stackScheme : 'auto';
  try {
    const saved = localStorage.getItem('StackColorScheme');
    if (validScheme(saved)) scheme = saved;
  } catch (_) { /* A private browser may disable storage. */ }
  const modeToggle = document.getElementById('dark-mode-toggle');
  function applyScheme() {
    const dark = scheme === 'dark' || (scheme === 'auto' && systemScheme.matches);
    document.documentElement.dataset.scheme = dark ? 'dark' : 'light';
    if (modeToggle) modeToggle.setAttribute('aria-pressed', String(dark));
    const themeColor = document.querySelector('meta[name="theme-color"]');
    if (themeColor) themeColor.content = dark ? '#303030' : '#f5f5fa';
    window.dispatchEvent(new CustomEvent('onColorSchemeChange', { detail: dark ? 'dark' : 'light' }));
  }
  function toggleScheme() {
    scheme = document.documentElement.dataset.scheme === 'dark' ? 'light' : 'dark';
    if ((scheme === 'dark') === systemScheme.matches) scheme = 'auto';
    try { localStorage.setItem('StackColorScheme', scheme); } catch (_) { /* Storage is optional. */ }
    applyScheme();
  }
  if (modeToggle) {
    modeToggle.addEventListener('click', toggleScheme);
    if (modeToggle.tagName !== 'BUTTON') {
      modeToggle.tabIndex = 0;
      modeToggle.setAttribute('role', 'button');
      modeToggle.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          toggleScheme();
        }
      });
    }
  }
  systemScheme.addEventListener('change', applyScheme);
  applyScheme();

  const menuToggle = document.getElementById('toggle-menu');
  const menu = document.getElementById('main-menu');
  if (menuToggle && menu) {
    function setMenu(open) {
      body.classList.toggle('show-menu', open);
      menu.classList.toggle('show', open);
      menuToggle.classList.toggle('is-active', open);
      menuToggle.setAttribute('aria-expanded', String(open));
    }
    menuToggle.setAttribute('aria-controls', 'main-menu');
    menuToggle.setAttribute('aria-expanded', 'false');
    menuToggle.addEventListener('click', () => setMenu(!body.classList.contains('show-menu')));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && body.classList.contains('show-menu')) {
        setMenu(false);
        menuToggle.focus();
      }
    });
    menu.addEventListener('click', (event) => {
      if (event.target.closest('a')) setMenu(false);
    });
    window.matchMedia('(min-width: 768px)').addEventListener('change', () => setMenu(false));
  }

  const pagination = document.querySelector('.pagination[data-total]');
  const jumpDialog = document.getElementById('pagination-jump-dialog');
  const jumpTriggers = [...document.querySelectorAll('.pagination-jump-trigger')];
  const jumpInput = document.getElementById('pagination-jump-input');
  const jumpForm = jumpDialog && jumpDialog.querySelector('.pagination-jump-form');
  if (pagination && jumpDialog && jumpTriggers.length && jumpInput && jumpForm
      && typeof jumpDialog.showModal === 'function' && typeof jumpDialog.close === 'function') {
    const totalPages = Number(pagination.dataset.total);
    let lastFocusedElement = null;
    let closeTimer;
    jumpInput.type = 'number';
    jumpInput.min = '1';
    jumpInput.max = String(totalPages);
    jumpInput.step = '1';
    jumpInput.required = true;

    function finishJumpClose() {
      window.clearTimeout(closeTimer);
      jumpDialog.removeEventListener('animationend', onJumpCloseAnimation);
      jumpDialog.classList.remove('closing');
      if (jumpDialog.open) jumpDialog.close();
    }
    function onJumpCloseAnimation(event) {
      if (event.target === jumpDialog && event.animationName === 'scale-down') finishJumpClose();
    }
    function closeJumpDialog() {
      if (!jumpDialog.open || jumpDialog.classList.contains('closing')) return;
      if (reducedMotion.matches) {
        finishJumpClose();
        return;
      }
      jumpDialog.classList.add('closing');
      jumpDialog.addEventListener('animationend', onJumpCloseAnimation);
      closeTimer = window.setTimeout(finishJumpClose, 250);
    }
    jumpDialog.addEventListener('close', () => {
      window.clearTimeout(closeTimer);
      jumpDialog.removeEventListener('animationend', onJumpCloseAnimation);
      jumpDialog.classList.remove('closing');
      if (lastFocusedElement && lastFocusedElement.isConnected) lastFocusedElement.focus({ preventScroll: true });
    });
    jumpTriggers.forEach((trigger) => trigger.addEventListener('click', () => {
      if (!Number.isSafeInteger(totalPages) || totalPages < 2 || jumpDialog.open) return;
      lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : trigger;
      jumpInput.value = '';
      jumpInput.setCustomValidity('');
      jumpDialog.showModal();
      jumpInput.focus();
    }));
    jumpDialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      closeJumpDialog();
    });
    jumpDialog.addEventListener('click', (event) => {
      const rect = jumpDialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) {
        closeJumpDialog();
      }
    });
    jumpInput.addEventListener('input', () => jumpInput.setCustomValidity(''));
    jumpInput.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      if (jumpForm.reportValidity()) jumpForm.requestSubmit();
    });
    jumpForm.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!jumpForm.reportValidity()) return;
      const targetPage = jumpInput.valueAsNumber;
      if (!Number.isSafeInteger(targetPage) || targetPage < 1 || targetPage > totalPages) return;
      try {
        const rawUrl = targetPage === 1 ? pagination.dataset.firstUrl : pagination.dataset.formatUrl;
        if (!rawUrl) return;
        const target = new URL(rawUrl, location.href);
        if (target.origin !== location.origin || !['http:', 'https:'].includes(target.protocol)) return;
        if (targetPage !== 1) {
          if (target.searchParams.has('p')) target.searchParams.set('p', String(targetPage));
          else {
            if (!/\/page\/2(?=\/?$|\.html$)/.test(target.pathname)) return;
            target.pathname = target.pathname.replace(/\/page\/2(?=\/?$|\.html$)/, `/page/${targetPage}`);
          }
        }
        closeJumpDialog();
        window.location.assign(target.href);
      } catch (_) { /* Keep the dialog open when the pagination URL is unavailable. */ }
    });
  }

  const article = document.querySelector('.article-content');
  if (article) {
    const headings = [...article.querySelectorAll('h1,h2,h3,h4,h5,h6')]
      .filter((heading) => !heading.closest('.content-gate, [data-no-toc]'));
    const tocLinks = new Map();
    headings.forEach((heading, index) => {
      if (!heading.id) {
        const stem = heading.textContent.trim().toLowerCase().normalize('NFKC')
          .replace(/[^\p{L}\p{N}_-]+/gu, '-').replace(/^-+|-+$/g, '') || `section-${index + 1}`;
        let id = stem;
        let suffix = 2;
        while (document.getElementById(id)) id = `${stem}-${suffix++}`;
        heading.id = id;
      }
      if (enabled('stackHeadingAnchor') && !heading.querySelector('.header-anchor')) {
        const anchor = document.createElement('a');
        anchor.className = 'header-anchor';
        anchor.href = `#${encodeURIComponent(heading.id)}`;
        anchor.setAttribute('aria-label', `链接到：${heading.textContent.trim()}`);
        heading.prepend(anchor);
      }
    });
    if (enabled('stackToc')) {
      document.querySelectorAll('#TableOfContents, [data-stack-toc-list]').forEach((toc) => {
        if (!headings.length) {
          const wrapper = toc.closest('[data-stack-toc-container]') || toc.closest('.widget--toc, .stack-inline-toc');
          if (wrapper) wrapper.hidden = true;
          return;
        }
        const list = document.createElement('ol');
        const baseLevel = Math.min(...headings.map((heading) => Number(heading.tagName[1])));
        const levels = [{ level: baseLevel, list }];
        headings.forEach((heading) => {
          const level = Number(heading.tagName[1]);
          while (levels.length > 1 && levels[levels.length - 1].level > level) levels.pop();
          if (level > levels[levels.length - 1].level) {
            const previous = levels[levels.length - 1].list.lastElementChild;
            if (previous) {
              const nested = document.createElement('ol');
              previous.append(nested);
              levels.push({ level, list: nested });
            }
          }
          const item = document.createElement('li');
          item.className = `toc-level-${level}`;
          const link = document.createElement('a');
          link.href = `#${encodeURIComponent(heading.id)}`;
          link.textContent = [...heading.childNodes].filter((node) => node.nodeType !== 1 || !node.classList.contains('header-anchor'))
            .map((node) => node.textContent).join('').trim();
          link.addEventListener('click', (event) => {
            event.preventDefault();
            heading.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'start' });
            history.replaceState(null, '', `#${encodeURIComponent(heading.id)}`);
          });
          item.append(link);
          levels[levels.length - 1].list.append(item);
          if (!tocLinks.has(heading.id)) tocLinks.set(heading.id, []);
          tocLinks.get(heading.id).push(item);
        });
        toc.replaceChildren(list);
      });
      let ticking = false;
      let activeId = '';
      function updateToc() {
        ticking = false;
        let current = headings[0];
        headings.forEach((heading) => {
          if (heading.getBoundingClientRect().top <= 100) current = heading;
        });
        if (!current || current.id === activeId) return;
        activeId = current.id;
        tocLinks.forEach((items, id) => items.forEach((item) => {
          const active = id === activeId;
          item.classList.toggle('active-class', active);
          item.classList.toggle('is-active', active);
          const link = item.querySelector('a');
          if (active) link.setAttribute('aria-current', 'location');
          else link.removeAttribute('aria-current');
        }));
      }
      window.addEventListener('scroll', () => {
        if (!ticking) {
          ticking = true;
          window.requestAnimationFrame(updateToc);
        }
      }, { passive: true });
      updateToc();
    }

    article.querySelectorAll('pre > code').forEach((code) => {
      const pre = code.parentElement;
      if (code.classList.contains('language-mermaid') || pre.classList.contains('mermaid')
          || pre.closest('.content-gate, [data-no-code], .stack-code-block')) return;
      const option = (name) => code.dataset[name] ?? pre.dataset[name];
      const mode = option('stackLinenos');
      const lineNumbers = mode === undefined ? enabled('stackLineNumbers') : !['false', '0', 'off', 'no'].includes(mode.toLowerCase());
      const startValue = Number(option('stackLinenostart') || '1');
      const firstLine = Number.isSafeInteger(startValue) && startValue >= 1 && startValue <= 1000000000 ? startValue : 1;
      const ranges = (String(option('stackHlLines') || '').match(/\d+(?:\s*-\s*\d+)?/g) || []).slice(0, 100).map((value) => {
        const [start, end] = value.split('-').map(Number);
        return [start, end === undefined ? start : end];
      }).filter(([start, end]) => Number.isSafeInteger(start) && Number.isSafeInteger(end) && start >= 1 && end >= start);
      if (!lineNumbers && !ranges.length) return;

      const block = document.createElement('div');
      block.className = 'highlight stack-code-block';
      const chroma = document.createElement('div');
      chroma.className = 'chroma';
      pre.before(block);
      block.append(chroma);
      pre.classList.add('chroma');
      let gutterCode = null;
      if (lineNumbers) {
        const table = document.createElement('table');
        table.className = 'lntable';
        table.setAttribute('role', 'presentation');
        const row = table.createTBody().insertRow();
        const gutter = row.insertCell();
        gutter.className = 'lntd stack-code-gutter';
        gutter.setAttribute('aria-hidden', 'true');
        const gutterPre = document.createElement('pre');
        gutterPre.className = 'chroma';
        gutterPre.dataset.stackGutter = '1';
        gutterCode = document.createElement('code');
        gutterPre.append(gutterCode);
        gutter.append(gutterPre);
        const source = row.insertCell();
        source.className = 'lntd stack-code-source';
        source.append(pre);
        chroma.append(table);
      } else chroma.append(pre);

      let observer;
      function renderCodeLines() {
        if (observer) observer.disconnect();
        const source = code.textContent;
        const fragments = [document.createDocumentFragment()];
        const containers = [];
        const current = () => containers.length ? containers[containers.length - 1].clone : fragments[fragments.length - 1];
        function visit(node) {
          if (node.nodeType === Node.TEXT_NODE) {
            const pieces = node.textContent.split('\n');
            pieces.forEach((piece, index) => {
              if (index) {
                current().append(document.createTextNode('\n'));
                fragments.push(document.createDocumentFragment());
                let parent = fragments[fragments.length - 1];
                containers.forEach((container) => {
                  container.clone = container.node.cloneNode(false);
                  parent.append(container.clone);
                  parent = container.clone;
                });
              }
              if (piece) current().append(document.createTextNode(piece));
            });
          } else if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.classList.contains('line') || node.classList.contains('cl')) {
              [...node.childNodes].forEach(visit);
              return;
            }
            const clone = node.cloneNode(false);
            current().append(clone);
            containers.push({ node, clone });
            [...node.childNodes].forEach(visit);
            containers.pop();
          }
        }
        [...code.childNodes].forEach(visit);
        if (source.endsWith('\n') && fragments.length > 1) fragments.pop();
        const lines = document.createDocumentFragment();
        const numbers = document.createDocumentFragment();
        fragments.forEach((fragment, index) => {
          const marked = ranges.some(([start, end]) => index + 1 >= start && index + 1 <= end);
          const line = document.createElement('span');
          line.className = `line${marked ? ' hl' : ''}`;
          const content = document.createElement('span');
          content.className = 'cl';
          content.append(fragment);
          line.append(content);
          lines.append(line);
          if (gutterCode) {
            const number = document.createElement('span');
            number.className = `lnt${marked ? ' hl' : ''}`;
            number.textContent = `${firstLine + index}\n`;
            numbers.append(number);
          }
        });
        code.replaceChildren(lines);
        if (gutterCode) gutterCode.replaceChildren(numbers);
        code.dataset.stackLineCount = String(fragments.length);
        if (observer) observer.observe(code, { childList: true, characterData: true, subtree: true });
      }
      renderCodeLines();
      observer = new MutationObserver(renderCodeLines);
      observer.observe(code, { childList: true, characterData: true, subtree: true });
    });

    if (enabled('stackCodeCopy')) article.querySelectorAll('pre').forEach((pre) => {
      if (pre.dataset.stackGutter === '1') return;
      const code = pre.querySelector('code');
      if (!code || pre.querySelector('.copyCodeButton')) return;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'copyCodeButton';
      button.textContent = '复制';
      button.setAttribute('aria-label', '复制代码');
      button.setAttribute('aria-live', 'polite');
      (pre.closest('.stack-code-block') || pre).append(button);
      button.addEventListener('click', async () => {
        try {
          if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(code.textContent);
          else {
            const textarea = document.createElement('textarea');
            textarea.value = code.textContent;
            textarea.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
            body.append(textarea);
            textarea.select();
            const copied = document.execCommand('copy');
            textarea.remove();
            button.focus({ preventScroll: true });
            if (!copied) throw new Error('Clipboard unavailable');
          }
          button.textContent = '已复制';
        } catch (_) {
          button.textContent = '复制失败';
        }
        window.setTimeout(() => { button.textContent = '复制'; }, 1800);
      });
    });

    article.querySelectorAll(':scope > p, .stack-shortcode-gallery > p').forEach((paragraph) => {
      if (paragraph.textContent.trim() || paragraph.closest('.content-gate, [data-no-gallery], [data-no-zoom]')) return;
      const nodes = [...paragraph.childNodes];
      const images = [];
      const imageOnly = nodes.every((node) => {
        if (node.nodeType === Node.TEXT_NODE) return !node.textContent.trim();
        if (node.nodeType !== Node.ELEMENT_NODE) return node.nodeType === Node.COMMENT_NODE;
        if (node.tagName === 'BR') return true;
        if (node.tagName === 'IMG') {
          images.push(node);
          return true;
        }
        if (node.tagName === 'A') {
          const children = [...node.childNodes].filter((child) => child.nodeType !== Node.COMMENT_NODE
            && (child.nodeType !== Node.TEXT_NODE || child.textContent.trim()));
          if (children.length === 1 && children[0].nodeType === Node.ELEMENT_NODE && children[0].tagName === 'IMG') {
            images.push(children[0]);
            return true;
          }
        }
        return false;
      });
      if (!imageOnly || !images.length || images.some((image) => image.closest('[data-no-gallery], [data-no-zoom]'))) return;
      const gallery = document.createElement('div');
      gallery.className = 'gallery';
      images.forEach((image) => {
        const figure = document.createElement('figure');
        figure.className = 'gallery-image';
        image.classList.add('gallery-image');
        let link = image.parentElement.tagName === 'A' ? image.parentElement : null;
        if (!link) {
          link = document.createElement('a');
          link.href = image.currentSrc || image.src;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.append(image);
        }
        link.classList.add('image-link');
        figure.append(link);
        const text = image.getAttribute('title') || image.getAttribute('alt');
        if (text) {
          const caption = document.createElement('figcaption');
          caption.textContent = text;
          figure.append(caption);
        }
        function sizeFigure() {
          const width = image.naturalWidth;
          const height = image.naturalHeight;
          if (!width || !height) return;
          figure.style.flexGrow = String(width * 100 / height);
          figure.style.flexBasis = `${width * 240 / height}px`;
          link.dataset.pswpWidth = String(width);
          link.dataset.pswpHeight = String(height);
        }
        figure.style.flexGrow = '1';
        figure.style.flexBasis = '0';
        image.addEventListener('load', sizeFigure);
        sizeFigure();
        gallery.append(figure);
      });
      paragraph.replaceWith(gallery);
    });

    if (enabled('stackImageZoom')) {
      const images = [...article.querySelectorAll('img')].filter((img) => {
        if (img.closest('pre, code, .content-gate, [data-no-zoom]')) return false;
        if (img.closest('a')) {
          const link = img.closest('a');
          try {
            const target = new URL(link.href, location.href);
            const image = new URL(img.currentSrc || img.src, location.href);
            return target.href === image.href || /\.(avif|gif|jpe?g|png|webp|svg)(\?|$)/i.test(target.href);
          } catch (_) { return false; }
        }
        return true;
      });
      if (images.length) {
        const viewer = document.createElement('dialog');
        viewer.className = 'stack-image-viewer';
        viewer.setAttribute('aria-label', '图片查看器');
        const close = document.createElement('button');
        close.type = 'button';
        close.dataset.stackViewerClose = '';
        close.setAttribute('aria-label', '关闭图片');
        close.textContent = '×';
        const previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'stack-viewer-prev';
        previous.setAttribute('aria-label', '上一张图片');
        previous.textContent = '‹';
        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'stack-viewer-next';
        next.setAttribute('aria-label', '下一张图片');
        next.textContent = '›';
        const figure = document.createElement('figure');
        const fullImage = document.createElement('img');
        const caption = document.createElement('figcaption');
        caption.setAttribute('aria-live', 'polite');
        figure.append(fullImage, caption);
        viewer.append(close, previous, figure, next);
        body.append(viewer);
        previous.hidden = next.hidden = images.length < 2;
        let activeImage = 0;
        let originalOverflow = '';
        let returnFocus = null;
        function showImage(index) {
          activeImage = (index + images.length) % images.length;
          const image = images[activeImage];
          const link = image.closest('a');
          fullImage.src = link ? link.href : image.currentSrc || image.src;
          fullImage.alt = image.alt;
          caption.textContent = `${image.alt ? `${image.alt} · ` : ''}${activeImage + 1} / ${images.length}`;
        }
        function openViewer(index, trigger) {
          showImage(index);
          originalOverflow = body.style.overflow;
          body.style.overflow = 'hidden';
          returnFocus = trigger;
          viewer.showModal();
          close.focus();
        }
        close.addEventListener('click', () => viewer.close());
        viewer.addEventListener('close', () => {
          body.style.overflow = originalOverflow;
          if (returnFocus) returnFocus.focus({ preventScroll: true });
        });
        viewer.addEventListener('click', (event) => { if (event.target === viewer) viewer.close(); });
        previous.addEventListener('click', () => showImage(activeImage - 1));
        next.addEventListener('click', () => showImage(activeImage + 1));
        viewer.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowLeft') { event.preventDefault(); showImage(activeImage - 1); }
          if (event.key === 'ArrowRight') { event.preventDefault(); showImage(activeImage + 1); }
        });
        images.forEach((img, index) => {
          const trigger = img.closest('a') || img;
          if (trigger === img) {
            img.tabIndex = 0;
            img.setAttribute('role', 'button');
            img.setAttribute('aria-label', img.alt ? `查看图片：${img.alt}` : '查看图片');
            img.addEventListener('keydown', (event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openViewer(index, trigger);
              }
            });
          }
          trigger.classList.add('stack-zoomable');
          trigger.addEventListener('click', (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            openViewer(index, trigger);
          });
        });
      }
    }
  }

  if (article && body.dataset.stackMermaid === '1') {
    let modal = null;
    let viewport;
    let canvas;
    let closeButton;
    let activeDiagram;
    let returnFocus;
    let oldOverflow;
    let width = 1;
    let height = 1;
    let scale = 1;
    let offsetX = 0;
    let offsetY = 0;
    let drag = null;
    function transformDiagram() {
      canvas.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale})`;
    }
    function fitDiagram() {
      scale = Math.max(0.05, Math.min(10, (viewport.clientWidth - 60) / width, (viewport.clientHeight - 60) / height));
      offsetX = (viewport.clientWidth - width * scale) / 2;
      offsetY = (viewport.clientHeight - height * scale) / 2;
      transformDiagram();
    }
    function zoomDiagram(factor, x = viewport.clientWidth / 2, y = viewport.clientHeight / 2) {
      const nextScale = Math.max(0.05, Math.min(10, scale * factor));
      const ratio = nextScale / scale;
      offsetX = x - (x - offsetX) * ratio;
      offsetY = y - (y - offsetY) * ratio;
      scale = nextScale;
      transformDiagram();
    }
    function refreshDiagram() {
      const original = activeDiagram && activeDiagram.querySelector('.mermaid > svg');
      if (!original || !canvas) return;
      const svg = original.cloneNode(true);
      const box = original.viewBox.baseVal;
      const rect = original.getBoundingClientRect();
      width = box.width || rect.width || 800;
      height = box.height || rect.height || 600;
      svg.setAttribute('width', String(width));
      svg.setAttribute('height', String(height));
      svg.style.width = `${width}px`;
      svg.style.height = `${height}px`;
      svg.style.maxWidth = 'none';
      canvas.replaceChildren(svg);
      fitDiagram();
    }
    function createDiagramDialog() {
      modal = document.createElement('dialog');
      modal.id = 'mermaid-modal';
      modal.className = 'mermaid-modal stack-mermaid-dialog';
      modal.setAttribute('aria-label', 'Mermaid 图表全屏查看器');
      modal.style.cssText = 'border:0;margin:0;padding:0;max-width:none;max-height:none';
      const header = document.createElement('div');
      header.className = 'mermaid-modal-header';
      const controls = document.createElement('div');
      controls.className = 'mermaid-modal-controls';
      [['-1', '− 缩小'], ['0', '重置 (100%)'], ['1', '+ 放大'], ['fit', '适应屏幕']].forEach(([action, label]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.zoom = action;
        button.textContent = label;
        button.addEventListener('click', () => {
          if (action === 'fit') fitDiagram();
          else if (action === '0') { scale = 1; offsetX = offsetY = 0; transformDiagram(); }
          else zoomDiagram(action === '1' ? 1.25 : 0.8);
        });
        controls.append(button);
      });
      closeButton = document.createElement('button');
      closeButton.type = 'button';
      closeButton.className = 'mermaid-modal-close';
      closeButton.id = 'mermaid-modal-close';
      closeButton.textContent = '✕ 关闭 (Esc)';
      closeButton.addEventListener('click', () => modal.close());
      header.append(controls, closeButton);
      const modalBody = document.createElement('div');
      modalBody.className = 'mermaid-modal-body';
      viewport = document.createElement('div');
      viewport.className = 'mermaid-modal-content';
      viewport.id = 'mermaid-modal-content';
      viewport.style.touchAction = 'none';
      canvas = document.createElement('div');
      canvas.className = 'mermaid-panzoom-container ready';
      viewport.append(canvas);
      modalBody.append(viewport);
      modal.append(header, modalBody);
      body.append(modal);
      modal.addEventListener('close', () => {
        modal.classList.remove('active');
        body.style.overflow = oldOverflow;
        activeDiagram = null;
        canvas.replaceChildren();
        if (returnFocus && returnFocus.isConnected) returnFocus.focus({ preventScroll: true });
      });
      viewport.addEventListener('wheel', (event) => {
        event.preventDefault();
        const rect = viewport.getBoundingClientRect();
        zoomDiagram(Math.exp(-event.deltaY * 0.002), event.clientX - rect.left, event.clientY - rect.top);
      }, { passive: false });
      viewport.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) return;
        event.preventDefault();
        drag = { x: event.clientX, y: event.clientY, offsetX, offsetY };
        viewport.setPointerCapture(event.pointerId);
      });
      viewport.addEventListener('pointermove', (event) => {
        if (!drag) return;
        offsetX = drag.offsetX + event.clientX - drag.x;
        offsetY = drag.offsetY + event.clientY - drag.y;
        transformDiagram();
      });
      const stopDrag = () => { drag = null; };
      viewport.addEventListener('pointerup', stopDrag);
      viewport.addEventListener('pointercancel', stopDrag);
      window.addEventListener('resize', () => { if (modal.open) fitDiagram(); });
    }
    function installDiagramTools() {
      article.querySelectorAll('.stack-mermaid').forEach((diagram) => {
        if (!diagram.querySelector('.mermaid > svg') || diagram.querySelector('.mermaid-toolbar')) return;
        const toolbar = document.createElement('div');
        toolbar.className = 'mermaid-toolbar';
        const expand = document.createElement('button');
        expand.type = 'button';
        expand.textContent = '⛶ 展开';
        expand.setAttribute('aria-label', '全屏查看图表，可缩放和拖动');
        expand.addEventListener('click', () => {
          if (!modal) createDiagramDialog();
          if (typeof modal.showModal !== 'function') return;
          activeDiagram = diagram;
          returnFocus = expand;
          oldOverflow = body.style.overflow;
          body.style.overflow = 'hidden';
          modal.classList.add('active');
          modal.showModal();
          refreshDiagram();
          closeButton.focus();
        });
        toolbar.append(expand);
        diagram.append(toolbar);
      });
      if (modal && modal.open) refreshDiagram();
    }
    const diagramObserver = new MutationObserver(installDiagramTools);
    diagramObserver.observe(article, { childList: true, subtree: true });
    installDiagramTools();
  }

  const searchData = document.getElementById('stack-search-data');
  const searchResults = document.querySelector('[data-stack-search-results]');
  if (searchData && searchResults) {
    let documents = [];
    try {
      const parsed = JSON.parse(searchData.textContent);
      if (Array.isArray(parsed)) documents = parsed;
    } catch (_) { /* The server rendered search page remains usable. */ }
    const inputs = [...document.querySelectorAll('[data-stack-search]')];
    const status = document.querySelector('[data-stack-search-status]');
    const normalize = (value) => String(value || '').normalize('NFKC').toLocaleLowerCase();
    function renderSearch(query) {
      const started = performance.now();
      const words = normalize(query).trim().split(/\s+/u).filter(Boolean);
      const fragment = document.createDocumentFragment();
      if (!words.length) {
        searchResults.replaceChildren();
        if (status) status.textContent = '输入关键词，搜索文章标题、摘要和标签。';
        return;
      }
      const matches = documents.map((item) => {
        const title = normalize(item.title);
        const summary = normalize(item.excerpt);
        const tags = normalize(Array.isArray(item.tags) ? item.tags.join(' ') : item.tags);
        const searchable = `${title} ${summary} ${tags} ${normalize(item.content)}`;
        if (!words.every((word) => searchable.includes(word))) return null;
        return { item, score: words.reduce((score, word) => score + (title.includes(word) ? 5 : 0) + (tags.includes(word) ? 2 : 0) + (summary.includes(word) ? 1 : 0), 0) };
      }).filter(Boolean).sort((a, b) => b.score - a.score);
      matches.forEach(({ item }) => {
        let url;
        try { url = new URL(item.url, location.href); } catch (_) { return; }
        if (!['http:', 'https:'].includes(url.protocol)) return;
        const result = document.createElement('article');
        const link = document.createElement('a');
        link.href = url.href;
        const details = document.createElement('div');
        details.className = 'article-details';
        const title = document.createElement('h2');
        title.className = 'article-title';
        title.textContent = item.title;
        details.append(title);
        if (item.excerpt) {
          const excerpt = document.createElement('p');
          excerpt.className = 'article-subtitle';
          const characters = Array.from(String(item.excerpt));
          excerpt.textContent = characters.slice(0, 160).join('') + (characters.length > 160 ? '…' : '');
          details.append(excerpt);
        }
        if (item.date) {
          const time = document.createElement('footer');
          time.className = 'article-meta';
          time.textContent = item.date;
          details.append(time);
        }
        link.append(details);
        result.append(link);
        fragment.append(result);
      });
      searchResults.replaceChildren(fragment);
      if (status) status.textContent = `找到 ${matches.length} 篇文章（用时 ${((performance.now() - started) / 1000).toFixed(2)} 秒）`;
    }
    let timer;
    inputs.forEach((input) => {
      input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
          const value = input.value;
          inputs.forEach((other) => { if (other !== input) other.value = value; });
          renderSearch(value);
          const url = new URL(location.href);
          url.searchParams.set('stack_search', value);
          history.replaceState(null, '', url);
        }, 100);
      });
      const form = input.closest('form');
      if (form) form.addEventListener('submit', (event) => {
        event.preventDefault();
        const value = input.value;
        renderSearch(value);
        const url = new URL(location.href);
        url.searchParams.set('stack_search', value);
        history.replaceState(null, '', url);
      });
    });
    const query = new URL(location.href).searchParams.get('stack_search') || '';
    inputs.forEach((input) => { input.value = query; });
    renderSearch(query);
  }
})();
