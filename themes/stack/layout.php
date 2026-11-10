<?php
/** Stack for SBlog. Original design by Jimmy Cai; PHP port by jkjoy. */
$stackPost = stack_detail_post();
$stackDetail = $stackPost !== null;
$stackWidgets = stack_widgets($stackDetail);
$stackScheme = theme_setting('color_scheme', 'auto');
if (!in_array($stackScheme, ['light', 'dark', 'auto'], true)) { $stackScheme = 'auto'; }
$stackSubtitle = trim(theme_setting('sidebar_subtitle')) ?: setting('site_tagline', setting('site_description'));
$stackAvatar = stack_safe_image(theme_setting('sidebar_avatar')) ?: theme_logo_url();
$stackMenu = [];
foreach ([['home', '主页', 'home'], ['archives', '归档', 'archives'], ['categories', '分类', 'categories'], ['tags', '标签', 'tag'], ['links', '链接', 'link']] as [$route, $label, $icon]) {
    $stackMenu[] = ['name' => $label, 'url' => stack_url($route), 'icon' => $icon, 'active' => (string)($themeContext['active'] ?? '') === $route && !($route === 'home' && $stackDetail)];
}
if (stack_enabled('search_enabled')) {
    $stackMenu[] = ['name' => '搜索', 'url' => url_with_query(stack_url('home'), ['stack_search' => '']), 'icon' => 'search', 'active' => isset($_GET['stack_search'])];
    if (isset($_GET['stack_search'])) { $stackMenu[0]['active'] = false; }
}
foreach ($navPages as $page) { $stackMenu[] = ['name' => $page['title'], 'url' => stack_url('page', ['slug' => $page['slug']]), 'icon' => 'user', 'active' => ($themeContext['active'] ?? '') === 'page:' . $page['slug']]; }
$stackCustomMenu = stack_json('menu_items');
if ($stackCustomMenu !== []) { $stackMenu = $stackCustomMenu; }
$stackSince = theme_setting('footer_since', '2026');
$stackYear = date('Y');
$stackSince = ctype_digit($stackSince) && (int)$stackSince >= 1970 && (int)$stackSince < (int)$stackYear ? $stackSince . ' - ' : '';
$stackBeian = trim(theme_setting('footer_beian')) ?: trim(setting('footer_beian'));
$stackCss = theme_setting('custom_css');
$stackJs = theme_setting('custom_js');
$stackSearch = stack_enabled('search_enabled') && isset($_GET['stack_search']) ? stack_search_data() : [];
$stackTitle = isset($_GET['stack_search']) && stack_enabled('search_enabled') ? '搜索 · ' . $siteName : $fullTitle;
$stackDescription = $description;
$stackCanonical = $stackPost !== null ? content_permalink($stackPost) : (in_array(stack_action(), ['tag', 'category'], true) ? url_for(stack_action(), ['slug' => (string)($_GET['slug'] ?? '')]) : (stack_action() === 'home' ? home_page_url(max(1, (int)($_GET['p'] ?? 1))) : url_for(in_array(stack_action(), ['archives', 'tags', 'categories', 'links'], true) ? stack_action() : 'home')));
if (!in_array(stack_action(), ['home', 'post', 'page', 'tag', 'category', 'archives', 'tags', 'categories', 'links'], true)) {
    $stackRequestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if (str_starts_with($stackRequestUri, '/') && !str_starts_with($stackRequestUri, '//') && !preg_match('/[\x00-\x20\\\\]/', $stackRequestUri)) {
        $stackCanonical = $stackRequestUri;
    }
}
?>
<!DOCTYPE html>
<html class="stack-html" lang="<?= h(str_replace('_', '-', sblog_i18n_locale())) ?>" dir="ltr" data-scheme="<?= $stackScheme === 'dark' ? 'dark' : 'light' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($stackTitle) ?></title>
  <meta name="description" content="<?= h($stackDescription) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="<?= $stackScheme === 'dark' ? '#303030' : '#f5f5fa' ?>">
  <meta property="og:site_name" content="<?= h($siteName) ?>">
  <meta property="og:title" content="<?= h($stackTitle) ?>">
  <meta property="og:description" content="<?= h($stackDescription) ?>">
  <meta property="og:type" content="<?= $stackDetail ? 'article' : 'website' ?>">
  <meta property="og:url" content="<?= h(absolute_url($stackCanonical)) ?>">
  <link rel="canonical" href="<?= h(absolute_url($stackCanonical)) ?>">
  <meta name="twitter:card" content="<?= h(theme_setting('opengraph_twitter_card', 'summary_large_image')) ?>">
  <?php if (theme_setting('opengraph_twitter_site') !== ''): ?><meta name="twitter:site" content="<?= h(theme_setting('opengraph_twitter_site')) ?>"><?php endif; ?>
  <?php if ($stackPost && ($stackOgImage = stack_cover($stackPost)) !== ''): ?><meta property="og:image" content="<?= h(absolute_url($stackOgImage)) ?>"><?php endif; ?>
  <?php if (isset($_GET['stack_search'])): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <link rel="alternate" type="application/rss+xml" title="<?= h($siteName) ?>" href="<?= h(url_for('rss')) ?>">
  <script>(()=>{let s=<?= json_encode($stackScheme) ?>;try{const v=localStorage.getItem('StackColorScheme');if(['auto','light','dark'].includes(v))s=v;}catch(_){}const d=s==='dark'||(s==='auto'&&matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.dataset.scheme=d?'dark':'light';})();</script>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($stackCss !== ''): ?><style><?= str_ireplace('</style', '<\/style', $stackCss) ?></style><?php endif; ?>
  <?php if ($customHeadCode !== ''): ?><?= $customHeadCode ?><?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>" data-stack-scheme="<?= h($stackScheme) ?>" data-stack-toc="<?= stack_toc_enabled($stackPost) ? '1' : '0' ?>" data-stack-heading-anchor="<?= stack_enabled('article_heading_anchor', '0') ? '1' : '0' ?>" data-stack-line-numbers="<?= stack_enabled('article_line_numbers') ? '1' : '0' ?>" data-stack-code-copy="<?= stack_enabled('article_code_copy') ? '1' : '0' ?>" data-stack-image-zoom="<?= stack_enabled('article_image_zoom') ? '1' : '0' ?>" data-stack-highlight="<?= stack_enabled('article_highlight') ? '1' : '0' ?>" data-stack-vendor-url="<?= h(theme_asset_url('assets/vendor')) ?>" data-stack-math="<?= stack_post_flag($stackPost, 'math', 'article_math', '0') ? '1' : '0' ?>" data-stack-mermaid="<?= stack_post_flag($stackPost, 'mermaid', 'article_mermaid') ? '1' : '0' ?>" data-stack-mermaid-look="<?= h(theme_setting('mermaid_look', 'classic')) ?>" data-stack-mermaid-light-theme="<?= h(theme_setting('mermaid_light_theme', 'default')) ?>" data-stack-mermaid-dark-theme="<?= h(theme_setting('mermaid_dark_theme', 'dark')) ?>" data-stack-mermaid-transparent="<?= stack_enabled('mermaid_transparent_background', '0') ? '1' : '0' ?>">
<?php theme_action('body_open', $themeContext); ?>
<a class="stack-skip-link" href="#main-content">跳到正文</a>
<div class="container main-container flex on-phone--column <?= $stackWidgets ? 'extended' : 'compact' ?>">
  <?php theme_action('header_before', $themeContext); ?>
  <aside class="sidebar left-sidebar sticky<?= stack_enabled('sidebar_compact', '0') ? ' compact' : '' ?>">
    <button class="hamburger hamburger--spin" type="button" id="toggle-menu" aria-label="切换菜单" aria-controls="main-menu" aria-expanded="false"><span class="hamburger-box"><span class="hamburger-inner"></span></span></button>
    <header>
      <?php if (stack_enabled('sidebar_avatar_enabled')): ?><figure class="site-avatar"><a href="<?= h(stack_url('home')) ?>"><img src="<?= h($stackAvatar) ?>" width="120" height="120" class="site-logo" alt="<?= h($siteName) ?>" decoding="async"></a><?php if (theme_setting('sidebar_emoji', '🍥') !== ''): ?><span class="emoji" aria-hidden="true"><?= h(theme_setting('sidebar_emoji', '🍥')) ?></span><?php endif; ?></figure><?php endif; ?>
      <div class="site-meta"><h1 class="site-name"><a href="<?= h(stack_url('home')) ?>"><?= h($siteName) ?></a></h1><?php if ($stackSubtitle !== ''): ?><h2 class="site-description"><?= nl2br(h($stackSubtitle), false) ?></h2><?php endif; ?></div>
    </header>
    <?php $stackSocial = stack_json('social_links'); if ($stackSocial): ?><ol class="menu-social" aria-label="社交链接"><?php foreach ($stackSocial as $social): ?><?php if (!is_array($social) || !is_string($social['url'] ?? null) || ($socialUrl = safe_link_url($social['url'])) === '#') { continue; } ?><li><a href="<?= h($socialUrl) ?>" title="<?= h(stack_text($social['name'] ?? null, '社交链接')) ?>" aria-label="<?= h(stack_text($social['name'] ?? null, '社交链接')) ?>"<?= !empty($social['new_tab']) ? ' target="_blank"' : '' ?> rel="me noopener noreferrer"><?= stack_icon(stack_text($social['icon'] ?? null, 'link')) ?></a></li><?php endforeach; ?></ol><?php endif; ?>
    <ol class="menu" id="main-menu" aria-label="主导航">
      <?php foreach ($stackMenu as $item): ?><?php if (!is_array($item) || !is_string($item['url'] ?? null) || ($itemUrl = safe_link_url($item['url'])) === '#') { continue; } $itemActive = !empty($item['active']); ?>
        <li<?= $itemActive ? ' class="current"' : '' ?>><a href="<?= h($itemUrl) ?>"<?= $itemActive ? ' aria-current="page"' : '' ?><?= !empty($item['new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= stack_icon(stack_text($item['icon'] ?? null, 'link')) ?><span><?= h(stack_text($item['name'] ?? null, '链接')) ?></span></a></li>
      <?php endforeach; ?>
      <?php if (stack_enabled('color_scheme_toggle')): ?><li class="menu-bottom-section"><ol class="menu"><li><button id="dark-mode-toggle" type="button" aria-label="切换深浅色模式" aria-pressed="false"><?= stack_icon('toggle-left') ?><?= stack_icon('toggle-right') ?><span>暗色模式</span></button></li></ol></li><?php endif; ?>
    </ol>
    <noscript><style>@media(max-width:767px){body.stack-theme #main-menu{display:flex}body.stack-theme #toggle-menu{display:none}}</style></noscript>
  </aside>
  <?php theme_action('header_after', $themeContext); ?>
  <?php if ($stackWidgets): ?><aside class="sidebar right-sidebar sticky" aria-label="侧栏组件"><?= stack_render_widgets($stackWidgets) ?></aside><?php endif; ?>
  <main class="main full-width" id="main-content" tabindex="-1">
    <?php if ($flash): ?><div class="stack-notice" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?>
    <?= $content ?>
    <?php theme_action('content_after', $themeContext); ?>
    <?php theme_action('footer_before', $themeContext); ?>
    <footer class="site-footer">
      <section class="copyright">© <?= h($stackSince . $stackYear) ?> <?= h($siteName) ?></section>
      <section class="powerby">
        <?php if (theme_setting('footer_custom_text') !== ''): ?><?= nl2br(h(theme_setting('footer_custom_text')), false) ?><br><?php endif; ?>
        <?php if (stack_enabled('footer_show_powered')): ?>使用 <a href="https://github.com/jkjoy/Simple-PHP-Blog" target="_blank" rel="noopener noreferrer">SBlog</a> 构建<br><?php endif; ?>
        <?php if (stack_enabled('footer_show_theme')): ?><a href="https://github.com/CaiJimmy/hugo-theme-stack" target="_blank" rel="noopener noreferrer"><b>Stack</b></a> 由 <a href="https://jimmycai.com" target="_blank" rel="noopener noreferrer">Jimmy Cai</a> 设计<br>由 <a href="https://github.com/jkjoy" target="_blank" rel="noopener noreferrer">jkjoy</a> 移植至 SBlog<?php endif; ?>
        <?php if ($stackBeian !== ''): ?><br><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($stackBeian) ?></a><?php endif; ?>
      </section>
    </footer>
    <?php theme_action('footer_after', $themeContext); ?>
  </main>
</div>
<?php if ($stackSearch): ?><script type="application/json" id="stack-search-data"><?= json_encode($stackSearch, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?></script><?php endif; ?>
<?php theme_action('body_close', $themeContext); ?>
<script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
<script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h((string)filemtime(__DIR__ . '/script.js')) ?>" defer></script>
<?php if (is_file(__DIR__ . '/enhancements.js')): ?><script src="<?= h(theme_asset_url('enhancements.js')) ?>?v=<?= h((string)filemtime(__DIR__ . '/enhancements.js')) ?>" defer></script><?php endif; ?>
<?php if ($stackJs !== ''): ?><script><?= str_ireplace('</script', '<\/script', $stackJs) ?></script><?php endif; ?>
</body>
</html>
