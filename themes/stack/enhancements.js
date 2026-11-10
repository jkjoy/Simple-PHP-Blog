/* Optional local KaTeX and Mermaid rendering for Stack. */
(() => {
  'use strict';
  const body = document.body;
  if (!body.classList.contains('stack-theme')) return;
  const article = document.querySelector('.main-article');
  if (!article || !body.dataset.stackVendorUrl) return;
  let vendor;
  try {
    vendor = new URL(`${body.dataset.stackVendorUrl.replace(/\/$/, '')}/`, location.href);
    if (vendor.origin !== location.origin) return;
  } catch (_) { return; }

  const scripts = new Map();
  function loadScript(path) {
    if (scripts.has(path)) return scripts.get(path);
    const promise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = new URL(path, vendor).href;
      script.onload = resolve;
      script.onerror = reject;
      document.head.append(script);
    });
    scripts.set(path, promise);
    return promise;
  }
  function loadStyle(path) {
    return new Promise((resolve, reject) => {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = new URL(path, vendor).href;
      link.onload = resolve;
      link.onerror = reject;
      document.head.append(link);
    });
  }

  if (body.dataset.stackHighlight === '1') {
    const supported = new Set(('bash sh zsh c h cpp cc c++ h++ hpp hh hxx cxx csharp cs c# css diff patch go golang graphql gql '
      + 'ini toml java jsp javascript js jsx mjs cjs json jsonc kotlin kt kts less lua pluto makefile mk mak make markdown md mkdown mkd '
      + 'objectivec mm objc obj-c obj-c++ objective-c++ perl pl pm php php-template python py gyp ipython python-repl pycon r ruby rb gemspec '
      + 'podspec thor irb rust rs scss shell console shellsession sql swift typescript ts tsx mts cts vbnet vb wasm xml html xhtml rss atom '
      + 'xjb xsd xsl plist wsf svg yaml yml').split(' '));
    const blocks = [...article.querySelectorAll('pre > code[class*="language-"]')].filter((code) => {
      const language = [...code.classList].find((name) => name.startsWith('language-'));
      return language && supported.has(language.slice('language-'.length));
    });
    if (blocks.length) loadScript('highlight/highlight.min.js').then(() => {
      const highlighter = window.hljs;
      if (!highlighter) return;
      const colors = {
        keyword: 'k', string: 's', comment: 'c', number: 'm', title: 'nf',
        built_in: 'nb', literal: 'kc', regexp: 'sr', meta: 'cp', attr: 'na',
        attribute: 'na', type: 'kt', params: 'n', name: 'nt', variable: 'nv',
        symbol: 'ss', addition: 'gi', deletion: 'gd', operator: 'o',
        punctuation: 'p', selector_class: 'nc', selector_id: 'ni',
        selector_tag: 'nt', subst: 's', template_variable: 'nv'
      };
      blocks.forEach((code) => {
        const token = [...code.classList].find((name) => name.startsWith('language-'));
        const language = token.slice('language-'.length);
        if (!highlighter.getLanguage(language)) return;
        try {
          const source = code.textContent;
          code.innerHTML = highlighter.highlight(source, { language, ignoreIllegals: true }).value;
          code.querySelectorAll('span').forEach((span) => {
            [...span.classList].forEach((name) => {
              if (!name.startsWith('hljs-')) return;
              const key = name.slice(5).replace(/-/g, '_');
              const mapped = key === 'title' && span.classList.contains('class_') ? 'nc' : colors[key];
              if (mapped) span.classList.add(mapped);
            });
          });
          code.parentElement.classList.add('chroma');
          code.dataset.stackHighlighted = 'true';
        } catch (_) { /* Leave unsupported or malformed source readable. */ }
      });
    }).catch(() => { /* Original code remains available without highlighting. */ });
  }

  if (body.dataset.stackMath === '1') {
    const walker = document.createTreeWalker(article, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        return node.parentElement.closest('pre, code, script, style, textarea, .content-gate, .katex, .mermaid, .stack-mermaid')
          ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
      }
    });
    let hasMath = false;
    while (walker.nextNode()) {
      if (/\$\$[\s\S]+?\$\$|\$[^\n$]+?\$|\\\([\s\S]+?\\\)|\\\[[\s\S]+?\\\]/.test(walker.currentNode.textContent)) {
        hasMath = true;
        break;
      }
    }
    if (hasMath) {
      Promise.all([loadStyle('katex/katex.min.css'), loadScript('katex/katex.min.js')])
        .then(() => loadScript('katex/contrib/auto-render.min.js'))
        .then(() => {
          document.querySelectorAll('.main-article, .widget--toc').forEach((element) => {
            window.renderMathInElement(element, {
              delimiters: [
                { left: '$$', right: '$$', display: true },
                { left: '$', right: '$', display: false },
                { left: '\\(', right: '\\)', display: false },
                { left: '\\[', right: '\\]', display: true }
              ],
              ignoredClasses: ['gist', 'content-gate', 'mermaid', 'stack-mermaid'],
              throwOnError: false,
              trust: false,
              strict: 'ignore'
            });
          });
        }).catch(() => { /* Preserve readable formula source if local resources fail. */ });
    }
  }

  if (body.dataset.stackMermaid !== '1') return;
  const diagrams = [...article.querySelectorAll('pre > code.language-mermaid')].map((code, index) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'mermaid-wrapper stack-mermaid';
    wrapper.setAttribute('role', 'img');
    wrapper.setAttribute('aria-label', 'Mermaid 图表');
    wrapper.hidden = true;
    const canvas = document.createElement('div');
    canvas.className = 'mermaid';
    canvas.dataset.processed = 'true';
    wrapper.append(canvas);
    const pre = code.parentElement;
    pre.after(wrapper);
    return { source: code.textContent, pre, wrapper, canvas, index };
  });
  if (!diagrams.length) return;

  loadScript('mermaid/mermaid.min.js').then(() => {
    const mermaid = window.mermaid;
    if (!mermaid || typeof mermaid.render !== 'function') return;
    const themes = ['default', 'neutral', 'dark', 'forest', 'base'];
    let requested = 0;
    let running = false;
    async function renderDiagrams() {
      requested++;
      if (running) return;
      running = true;
      let rendered = 0;
      try {
        while (rendered < requested) {
          const revision = requested;
          const dark = document.documentElement.dataset.scheme === 'dark';
          const preference = dark ? body.dataset.stackMermaidDarkTheme : body.dataset.stackMermaidLightTheme;
          const transparent = body.dataset.stackMermaidTransparent === '1';
          mermaid.initialize({
            startOnLoad: false,
            securityLevel: 'strict',
            secure: ['secure', 'securityLevel', 'startOnLoad', 'maxTextSize', 'maxEdges', 'suppressErrorRendering'],
            suppressErrorRendering: true,
            theme: themes.includes(preference) ? preference : dark ? 'dark' : 'default',
            look: body.dataset.stackMermaidLook === 'handDrawn' ? 'handDrawn' : 'classic',
            maxTextSize: 50000,
            maxEdges: 500,
            flowchart: { htmlLabels: false, useMaxWidth: true },
            ...(transparent ? { themeVariables: { background: 'transparent' } } : {})
          });
          for (const diagram of diagrams) {
            if (revision !== requested) break;
            const id = `stack-mermaid-${revision}-${diagram.index}`;
            try {
              const result = await mermaid.render(id, diagram.source);
              if (revision !== requested) break;
              diagram.canvas.innerHTML = result.svg;
              if (typeof result.bindFunctions === 'function') result.bindFunctions(diagram.canvas);
              if (transparent) {
                const svg = diagram.wrapper.querySelector('svg');
                if (svg) svg.style.backgroundColor = 'transparent';
              }
              diagram.wrapper.hidden = false;
              diagram.pre.hidden = true;
            } catch (_) {
              diagram.canvas.replaceChildren();
              diagram.wrapper.hidden = true;
              diagram.pre.hidden = false;
              const temporary = document.getElementById(`d${id}`);
              if (temporary) temporary.remove();
            }
          }
          rendered = revision;
        }
      } finally {
        running = false;
      }
    }
    window.addEventListener('onColorSchemeChange', renderDiagrams);
    renderDiagrams();
  }).catch(() => { /* Keep the original code blocks when rendering cannot load. */ });
})();
