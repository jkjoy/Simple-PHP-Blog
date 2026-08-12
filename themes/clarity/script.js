(function () {
  'use strict';

  var root = document.documentElement;
  var toggle = document.querySelector('.color_choice');
  var navBrand = document.querySelector('.nav_brand');
  var navBody = document.querySelector('.nav_body');

  function resolvedDark() {
    return root.dataset.mode === 'dim' || (!root.dataset.mode && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function syncToggle() {
    if (toggle) toggle.checked = resolvedDark();
  }

  if (toggle) {
    syncToggle();
    toggle.addEventListener('change', function () {
      root.dataset.mode = toggle.checked ? 'dim' : 'lit';
      try { localStorage.setItem('clarity-mode', root.dataset.mode); } catch (error) {}
    });
  }

  if (navBrand && navBody) {
    navBrand.addEventListener('click', function (event) {
      if (window.innerWidth > 768) return;
      event.preventDefault();
      var open = navBody.classList.toggle('nav_body_open');
      navBrand.classList.toggle('nav_brand_open', open);
      navBrand.setAttribute('aria-expanded', String(open));
    });
  }

  var headings = Array.prototype.slice.call(document.querySelectorAll('.post_body h2, .post_body h3, .post_body h4'));
  var toc = document.querySelector('.toc_list');
  var tocWidget = document.querySelector('.toc_widget');
  if (toc && tocWidget && headings.length) {
    headings.forEach(function (heading, index) {
      if (!heading.id) heading.id = 'section-' + (index + 1);
      var li = document.createElement('li');
      li.className = 'toc_item toc_level_' + heading.tagName.slice(1);
      var link = document.createElement('a');
      link.className = 'toc_link';
      link.href = '#' + heading.id;
      link.textContent = heading.textContent;
      li.appendChild(link);
      toc.appendChild(li);
    });
    tocWidget.hidden = false;
  }

  Array.prototype.slice.call(document.querySelectorAll('.post_body table')).forEach(function (table) {
    if (table.parentElement && table.parentElement.classList.contains('article-table-scroll')) return;
    var wrapper = document.createElement('div');
    wrapper.className = 'article-table-scroll';
    wrapper.tabIndex = 0;
    wrapper.setAttribute('role', 'region');
    wrapper.setAttribute('aria-label', '可横向滚动的表格');
    table.parentNode.insertBefore(wrapper, table);
    wrapper.appendChild(table);
  });

  var topButton = document.querySelector('.to_top');
  if (topButton) {
    function updateTopButton() { topButton.classList.toggle('is-visible', window.scrollY > 320); }
    window.addEventListener('scroll', updateTopButton, { passive: true });
    updateTopButton();
  }
})();
