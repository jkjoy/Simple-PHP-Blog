/* Rebuild after installing sass and postcss: node build.cjs */
const fs = require('node:fs');
const path = require('node:path');
const sass = require('sass');
const postcss = require('postcss');

const upstream = sass.compile(path.join(__dirname, 'style.scss'), {
  style: 'expanded',
  silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
});
const css = postcss.parse(upstream.css);
css.walkRules(rule => {
  let ancestor = rule.parent;
  while (ancestor) {
    if (ancestor.type === 'atrule' && /keyframes$/i.test(ancestor.name)) return;
    ancestor = ancestor.parent;
  }
  rule.selectors = rule.selectors.map(selector => {
    if (/^:root\b/.test(selector)) return selector.replace(/^:root\b/, 'html.stack-html');
    if (/^html\b/.test(selector)) return selector.replace(/^html\b/, 'html.stack-html');
    if (/^body\b/.test(selector)) return selector.replace(/^body\b/, 'body.stack-theme');
    if (/^\.(?:article-page|template-[a-z-]+)\b/.test(selector)) return 'body.stack-theme' + selector;
    if (/^\[(?:data-scheme|dir)=/.test(selector)) return 'html.stack-html' + selector;
    return 'body.stack-theme ' + selector;
  });
});
const integration = sass.compile(path.join(__dirname, 'sblog.scss'), {style: 'expanded'});
fs.writeFileSync(path.resolve(__dirname, '../../style.css'), css.toString() + '\n' + integration.css + '\n');
console.log('Built Stack original styles and SBlog integration.');
