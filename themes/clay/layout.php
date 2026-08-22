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
    $action === 'archives' || $active === 'archives' => 'clay-view-archives',
    $action === 'tag' => 'clay-view-tag',
    $active === 'tags' => 'clay-view-tags',
    $active === 'links' => 'clay-view-links',
    $action === 'category' => 'clay-view-category',
    str_starts_with($active, 'page:') => 'clay-view-page',
    $active === 'home' && $title === $siteName => 'clay-view-home',
    default => 'clay-view-post',
};
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>" data-clay-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){try{var t=localStorage.getItem('clay-color-mode');if(t==='dark'||t==='light')document.documentElement.setAttribute('data-clay-theme',t);else if(matchMedia('(prefers-color-scheme:dark)').matches)document.documentElement.setAttribute('data-clay-theme','dark')}catch(e){}})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?> <?= h($viewClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>

  <?php theme_action('header_before', $themeContext); ?>
  <header class="clay-header">
    <div class="clay-nav clay-surface">
      <a class="clay-brand" href="<?= h(url_for('home')) ?>">
        <span class="clay-brand__mark"><img src="<?= h($logoUrl) ?>" width="38" height="38" alt=""></span>
        <span><strong><?= h($siteName) ?></strong><small><?= h(sblog_t('随手记，也认真写')) ?></small></span>
      </a>

      <nav class="clay-nav__links" id="clay-nav-links" aria-label="<?= h(sblog_t('主导航')) ?>">
        <a href="<?= h(url_for('home')) ?>" class="<?= $active === 'home' && $action !== 'category' ? 'is-active' : '' ?>"><?= h(sblog_t('首页')) ?></a>
        <a href="<?= h(url_for('archives')) ?>" class="<?= $active === 'archives' ? 'is-active' : '' ?>"><?= h(sblog_t('归档')) ?></a>
        <a href="<?= h(url_for('tags')) ?>" class="<?= $active === 'tags' ? 'is-active' : '' ?>"><?= h(sblog_t('标签')) ?></a>
        <a href="<?= h(url_for('links')) ?>" class="<?= $active === 'links' ? 'is-active' : '' ?>"><?= h(sblog_t('朋友')) ?></a>
        <?php foreach ($navPages as $page): ?>
          <a href="<?= h(content_permalink($page)) ?>" class="<?= $active === 'page:' . $page['slug'] ? 'is-active' : '' ?>"><?= h((string)$page['title']) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="clay-nav__tools">
        <?php if ($admin): ?><a class="clay-icon-button" href="<?= h(url_for('admin')) ?>" aria-label="<?= h(sblog_t('管理后台')) ?>" title="<?= h(sblog_t('管理后台')) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
        </a><?php endif; ?>
        <button class="clay-icon-button clay-theme-toggle" type="button" aria-label="<?= h(sblog_t('切换深浅模式')) ?>" aria-pressed="false" title="<?= h(sblog_t('切换深浅模式')) ?>">
          <svg class="clay-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
          <svg class="clay-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
        </button>
        <button class="clay-icon-button clay-menu-toggle" type="button" aria-label="<?= h(sblog_t('打开菜单')) ?>" aria-controls="clay-nav-links" aria-expanded="false">
          <svg class="clay-icon-menu" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
          <svg class="clay-icon-close" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
      </div>
    </div>
  </header>
  <div class="clay-menu-backdrop" data-clay-menu-backdrop></div>
  <?php theme_action('header_after', $themeContext); ?>

  <?php if ($viewClass === 'clay-view-home'): ?>
    <section class="clay-hero">
      <div class="clay-hero__copy clay-reveal">
        <span class="clay-kicker"><?= h(sblog_t('HELLO, THIS IS')) ?></span>
        <h1><?= h($siteName) ?></h1>
        <p><?= h($tagline) ?></p>
        <div class="clay-hero__actions">
          <a class="clay-primary-button clay-surface" href="#clay-feed-title"><?= h(sblog_t('开始阅读')) ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m7-7-7 7-7-7"/></svg></a>
          <a class="clay-secondary-link" href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('全部文章')) ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
        </div>
      </div>
      <div class="clay-hero__object clay-reveal" style="--reveal-delay:.08s" aria-hidden="true">
        <div class="clay-hero__logo clay-surface"><img src="<?= h($logoUrl) ?>" width="112" height="112" alt=""></div>
        <span class="clay-shape clay-shape--coral"></span>
        <span class="clay-shape clay-shape--yellow"></span>
        <div class="clay-hero__stats"><span><strong><?= h((string)$postCount) ?></strong><?= h(sblog_t('篇文章')) ?></span><span><strong><?= h((string)$tagCount) ?></strong><?= h(sblog_t('个标签')) ?></span></div>
      </div>
    </section>
  <?php endif; ?>

  <main class="clay-main">
    <?php if ($flash): ?><div class="clay-flash clay-surface clay-flash--<?= h((string)$flash['type']) ?>" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?>
    <?= $content ?>
    <?php theme_action('content_after', $themeContext); ?>
  </main>

  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="clay-footer">
    <div class="clay-footer__inner">
      <p><?= h(site_footer_text()) ?></p>
      <nav aria-label="<?= h(sblog_t('页脚导航')) ?>"><a href="<?= h(url_for('rss')) ?>">RSS</a><a href="<?= h(url_for('sitemap')) ?>">Sitemap</a><?php $beian = trim(setting('footer_beian')); ?><?php if ($beian !== ''): ?><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a><?php endif; ?></nav>
    </div>
  </footer>
  <?php theme_action('footer_after', $themeContext); ?>

  <button class="clay-backtop clay-surface" type="button" aria-label="<?= h(sblog_t('返回顶部')) ?>" title="<?= h(sblog_t('返回顶部')) ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
  </button>

  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
