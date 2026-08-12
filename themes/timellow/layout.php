<?php

declare(strict_types=1);

$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$beian = trim(setting('footer_beian'));
$scriptFile = __DIR__ . '/script.js';
$scriptVersion = is_file($scriptFile) ? (string)filemtime($scriptFile) : (string)($theme['version'] ?? '1.0.0');
$tagline = trim(setting('site_tagline'));
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="renderer" content="webkit">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
  <meta name="color-scheme" content="light dark">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){var k='timellow-theme',t='light';try{var s=localStorage.getItem(k);t=s==='light'||s==='dark'?s:(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light')}catch(e){}document.documentElement.setAttribute('data-theme',t)})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?><?= $customHeadCode . "\n" ?><?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <div class="site-shell"><div class="container">
    <?php theme_action('header_before', $themeContext); ?>
    <header class="site-header">
      <div class="header-inner">
        <div class="brand"><a class="brand-link" href="<?= h(url_for('home')) ?>" aria-label="<?= h($siteName) ?>"><span class="brand-copy"><span class="brand-title"><?= h($siteName) ?></span><span class="brand-description"><?= h($tagline) ?></span></span></a></div>
        <div class="header-actions">
          <nav class="site-nav" aria-label="<?= h(sblog_t('主导航')) ?>">
            <a<?= $active === 'home' ? ' class="is-current"' : '' ?> href="<?= h(url_for('home')) ?>"><?= h(sblog_t('首页')) ?></a>
            <a<?= $active === 'archives' ? ' class="is-current"' : '' ?> href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('归档')) ?></a>
            <a<?= $active === 'tags' ? ' class="is-current"' : '' ?> href="<?= h(url_for('tags')) ?>"><?= h(sblog_t('标签')) ?></a>
            <a<?= $active === 'links' ? ' class="is-current"' : '' ?> href="<?= h(url_for('links')) ?>"><?= h(sblog_t('友链')) ?></a>
            <?php foreach ($navPages as $page): ?><a<?= $active === 'page:' . $page['slug'] ? ' class="is-current"' : '' ?> href="<?= h(content_permalink($page)) ?>"><?= h((string)$page['title']) ?></a><?php endforeach; ?>
          </nav>
          <div class="header-tools">
            <button type="button" class="search-toggle" data-search-toggle aria-expanded="false" aria-controls="site-search-panel" title="<?= h(sblog_t('搜索')) ?>"><span class="screen-reader-text"><?= h(sblog_t('打开搜索')) ?></span><?= timellow_icon('search') ?></button>
            <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false"><span class="screen-reader-text" data-theme-label><?= h(sblog_t('切换深色模式')) ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><circle class="theme-icon-light" cx="12" cy="12" r="8.5"></circle><path class="theme-icon-dark" d="M12 3.5a8.5 8.5 0 0 0 0 17z"></path><circle class="theme-icon-ring" cx="12" cy="12" r="8.5"></circle></svg></button>
          </div>
        </div>
      </div>
      <div class="search-panel" id="site-search-panel" data-search-panel hidden><form class="search-form" method="get" action="<?= h(url_for('home')) ?>" role="search"><label for="timellow-search" class="screen-reader-text"><?= h(sblog_t('搜索关键字')) ?></label><input id="timellow-search" type="search" name="s" placeholder="<?= h(sblog_t('搜索文章、页面或关键词')) ?>"><button type="submit"><?= h(sblog_t('搜索')) ?></button></form></div>
    </header>
    <?php theme_action('header_after', $themeContext); ?>
    <div class="site-layout">
      <?php if ($flash): ?><div class="notice-card" role="status"><p><?= h((string)($flash['message'] ?? '')) ?></p></div><?php endif; ?>
      <?php theme_action('content_before', $themeContext); ?>
      <?= $content ?>
      <?php theme_action('content_after', $themeContext); ?>
    </div>
    <?= timellow_render_sns_links() ?>
    <?php theme_action('footer_before', $themeContext); ?>
    <footer class="site-footer"><p>&copy; <?= h(date('Y')) ?> <a href="<?= h(url_for('home')) ?>"><?= h($siteName) ?></a><?php if ($beian !== ''): ?><span class="footer-divider">·</span><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a><?php endif; ?><span class="footer-divider">·</span><span><?= h(sblog_t('由')) ?> SBlog <?= h(sblog_t('驱动')) ?></span><span class="footer-divider">·</span><span>Theme <a href="https://www.timellow.com/" target="_blank" rel="noopener noreferrer">Timellow</a></span></p></footer>
    <?php theme_action('footer_after', $themeContext); ?>
    <nav class="floating-actions" aria-label="<?= h(sblog_t('快捷操作')) ?>"><button class="floating-action floating-action-top" type="button" data-back-to-top hidden aria-label="<?= h(sblog_t('返回顶部')) ?>" title="<?= h(sblog_t('返回顶部')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"></path></svg></button><?php if ($admin): ?><a class="floating-action" href="<?= h(url_for('admin')) ?>" aria-label="<?= h(sblog_t('管理后台')) ?>" title="<?= h(sblog_t('管理后台')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><path d="m10 17 5-5-5-5"></path><path d="M15 12H3"></path></svg></a><?php endif; ?></nav>
  </div></div>
  <script src="<?= h(asset_url('index.js')) ?>?v=<?= h(APP_VERSION) ?>"></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
