<?php

declare(strict_types=1);

$action = (string)($_GET['a'] ?? '');
$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$scriptFile = active_theme_file('script.js');
$scriptVersion = $scriptFile !== '' ? (string)filemtime($scriptFile) : $themeVersion;

$viewClass = match (true) {
    $action === 'archives' || $active === 'archives' => 'page-archives',
    $action === 'tag' => 'page-tag',
    $active === 'tags' => 'page-tags',
    $active === 'links' => 'page-links',
    $action === 'category' => 'page-category',
    str_starts_with($active, 'page:') => 'page-page',
    $active === 'home' && $title === $siteName => 'page-index',
    default => 'page-post',
};
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){document.documentElement.classList.add("js");try{var t=localStorage.getItem("nebula-theme");if(t==="light"||t==="dark"){document.documentElement.setAttribute("data-theme",t)}}catch(e){}})();</script>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>

  <canvas id="bg-canvas"></canvas>
  <div class="aurora" aria-hidden="true">
    <span></span>
    <span></span>
    <span></span>
  </div>

  <?php theme_action('header_before', $themeContext); ?>
  <header class="site-header">
    <div class="container nav-inner">
      <a class="brand" href="<?= h(url_for('home')) ?>"><?= h($siteName) ?></a>

      <nav class="site-nav">
        <a href="<?= h(url_for('home')) ?>" class="<?= $active === 'home' ? 'active' : '' ?>">首页</a>
        <a href="<?= h(url_for('archives')) ?>" class="<?= $active === 'archives' ? 'active' : '' ?>">归档</a>
        <a href="<?= h(url_for('tags')) ?>" class="<?= $active === 'tags' ? 'active' : '' ?>">标签</a>
        <a href="<?= h(url_for('links')) ?>" class="<?= $active === 'links' ? 'active' : '' ?>">友链</a>
        <?php foreach ($navPages as $page): ?>
          <a href="<?= h(content_permalink($page)) ?>" class="<?= $active === 'page:' . $page['slug'] ? 'active' : '' ?>"><?= h((string)$page['title']) ?></a>
        <?php endforeach; ?>
        <?php if ($admin): ?><a href="<?= h(url_for('admin')) ?>" class="<?= $active === 'admin' ? 'active' : '' ?>">管理</a><?php endif; ?>
      </nav>

      <div class="nav-actions">
        <button id="theme-toggle" type="button" aria-label="切换主题">
          <svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
          </svg>
          <svg class="icon-sun" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
          </svg>
        </button>
        <button id="menu-toggle" type="button" aria-label="打开菜单">
          <span></span>
          <span></span>
          <span></span>
        </button>
      </div>
    </div>
  </header>
  <?php theme_action('header_after', $themeContext); ?>

  <main class="container <?= h($viewClass) ?>">
    <?php if ($flash): ?><div class="nebula-flash nebula-flash--<?= h((string)$flash['type']) ?>" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?>
    <?= $content ?>
    <?php theme_action('content_after', $themeContext); ?>
  </main>

  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="site-footer">
    <div class="container">
      <p><?= h(site_footer_text()) ?></p>
      <p class="site-footer__meta">
        <?php $beian = trim(setting('footer_beian')); ?>
        <?php if ($beian !== ''): ?><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a><span aria-hidden="true"> · </span><?php endif; ?>
        <a href="<?= h(url_for('rss')) ?>">RSS</a><span aria-hidden="true"> · </span><a href="<?= h(url_for('sitemap')) ?>">Sitemap</a><span aria-hidden="true"> · </span>Powered by <a href="https://github.com/jkjoy/Simple-PHP-Blog" target="_blank" rel="noopener noreferrer">Simple PHP Blog</a> <span aria-hidden="true">·</span> <span class="grad-text">Nebula</span> Theme
      </p>
    </div>
  </footer>
  <?php theme_action('footer_after', $themeContext); ?>

  <button id="back-top" type="button" aria-label="回到顶部">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 19V5M5 12l7-7 7 7"/>
    </svg>
  </button>

  <script src="<?= h(asset_url('index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
