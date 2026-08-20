<?php

declare(strict_types=1);

$action = (string)($_GET['a'] ?? '');
$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$scriptFile = active_theme_file('script.js');
$scriptVersion = $scriptFile !== '' ? (string)filemtime($scriptFile) : $themeVersion;
$tagline = trim(setting('site_tagline')) ?: trim(setting('home_intro')) ?: sblog_t('记录技术、灵感与生活片段。');
$logoUrl = theme_logo_url();
$postCount = count_published_posts();
$tagCount = count(tag_index_data());
$viewClass = match (true) {
    $action === 'archives' || $active === 'archives' => 'aqua-view-archives',
    $action === 'tag' => 'aqua-view-tag',
    $active === 'tags' => 'aqua-view-tags',
    $active === 'links' => 'aqua-view-links',
    $action === 'category' => 'aqua-view-category',
    str_starts_with($active, 'page:') => 'aqua-view-page',
    $active === 'home' && $title === $siteName => 'aqua-view-home',
    default => 'aqua-view-post',
};
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){try{var t=localStorage.getItem('aqua-theme');if(t==='dark'||t==='light')document.documentElement.setAttribute('data-theme',t);else if(matchMedia('(prefers-color-scheme:dark)').matches)document.documentElement.setAttribute('data-theme','dark')}catch(e){}})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?> <?= h($viewClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <div class="aqua-backdrop" aria-hidden="true"><span></span><span></span><span></span></div>

  <?php theme_action('header_before', $themeContext); ?>
  <header class="aqua-header">
    <div class="aqua-nav aqua-glass">
      <a class="aqua-brand" href="<?= h(url_for('home')) ?>">
        <img src="<?= h($logoUrl) ?>" width="34" height="34" alt="">
        <strong><?= h($siteName) ?></strong>
      </a>

      <nav class="aqua-nav__links" id="aqua-nav-links" aria-label="<?= h(sblog_t('主导航')) ?>">
        <a href="<?= h(url_for('home')) ?>" class="<?= $active === 'home' && $action !== 'category' ? 'is-active' : '' ?>"><?= h(sblog_t('首页')) ?></a>
        <a href="<?= h(url_for('archives')) ?>" class="<?= $active === 'archives' ? 'is-active' : '' ?>"><?= h(sblog_t('归档')) ?></a>
        <a href="<?= h(url_for('tags')) ?>" class="<?= $active === 'tags' ? 'is-active' : '' ?>"><?= h(sblog_t('标签')) ?></a>
        <a href="<?= h(url_for('links')) ?>" class="<?= $active === 'links' ? 'is-active' : '' ?>"><?= h(sblog_t('朋友')) ?></a>
        <?php foreach ($navPages as $page): ?>
          <a href="<?= h(content_permalink($page)) ?>" class="<?= $active === 'page:' . $page['slug'] ? 'is-active' : '' ?>"><?= h((string)$page['title']) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="aqua-nav__tools">
        <?php if ($admin): ?><a class="aqua-icon-button" href="<?= h(url_for('admin')) ?>" aria-label="<?= h(sblog_t('管理后台')) ?>" title="<?= h(sblog_t('管理后台')) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
        </a><?php endif; ?>
        <button class="aqua-icon-button aqua-theme-toggle" type="button" aria-label="<?= h(sblog_t('切换深浅模式')) ?>" aria-pressed="false" title="<?= h(sblog_t('切换深浅模式')) ?>">
          <svg class="aqua-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
          <svg class="aqua-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
        </button>
        <button class="aqua-icon-button aqua-menu-toggle" type="button" aria-label="<?= h(sblog_t('打开菜单')) ?>" aria-controls="aqua-nav-links" aria-expanded="false">
          <svg class="aqua-icon-menu" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
          <svg class="aqua-icon-close" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
      </div>
    </div>
  </header>
  <div class="aqua-menu-backdrop" data-aqua-menu-backdrop></div>
  <?php theme_action('header_after', $themeContext); ?>

  <?php if ($viewClass === 'aqua-view-home'): ?>
    <section class="aqua-hero">
      <div class="aqua-hero__copy aqua-reveal">
        <span class="aqua-kicker"><?= h(sblog_t('PERSONAL JOURNAL')) ?></span>
        <h1><?= h($siteName) ?></h1>
        <p><?= h($tagline) ?></p>
        <div class="aqua-hero__actions">
          <a class="aqua-primary-button" href="#aqua-feed-title"><?= h(sblog_t('阅读文章')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m7-7-7 7-7-7"/></svg></a>
          <a class="aqua-text-button" href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('查看归档')) ?></a>
        </div>
      </div>
      <div class="aqua-hero__material aqua-glass aqua-reveal" style="--reveal-delay:.08s">
        <img src="<?= h($logoUrl) ?>" width="96" height="96" alt="<?= h($siteName) ?>">
        <div><strong><?= h((string)$postCount) ?></strong><span><?= h(sblog_t('篇文章')) ?></span></div>
        <i></i>
        <div><strong><?= h((string)$tagCount) ?></strong><span><?= h(sblog_t('个标签')) ?></span></div>
      </div>
    </section>
  <?php endif; ?>

  <main class="aqua-main">
    <?php if ($flash): ?><div class="aqua-flash aqua-flash--<?= h((string)$flash['type']) ?> aqua-glass" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?>
    <?= $content ?>
    <?php theme_action('content_after', $themeContext); ?>
  </main>

  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="aqua-footer">
    <div>
      <a class="aqua-footer__brand" href="<?= h(url_for('home')) ?>"><img src="<?= h($logoUrl) ?>" width="28" height="28" alt=""><strong><?= h($siteName) ?></strong></a>
      <p><?= h(site_footer_text()) ?></p>
      <nav aria-label="<?= h(sblog_t('页脚导航')) ?>"><a href="<?= h(url_for('rss')) ?>">RSS</a><a href="<?= h(url_for('sitemap')) ?>">Sitemap</a><?php $beian = trim(setting('footer_beian')); ?><?php if ($beian !== ''): ?><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a><?php endif; ?></nav>
    </div>
  </footer>
  <?php theme_action('footer_after', $themeContext); ?>

  <button class="aqua-backtop aqua-glass" type="button" aria-label="<?= h(sblog_t('返回顶部')) ?>" title="<?= h(sblog_t('返回顶部')) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
  </button>

  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
