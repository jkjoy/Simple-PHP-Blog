<?php

declare(strict_types=1);

$owner = paper_owner();
$ownerName = trim((string)($owner['nickname'] ?? '')) ?: trim((string)($owner['username'] ?? '')) ?: $siteName;
$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$scriptFile = __DIR__ . '/script.js';
$scriptVersion = is_file($scriptFile) ? (string)filemtime($scriptFile) : $themeVersion;
$beian = trim(setting('footer_beian'));
$currentYear = date('Y');

$viewClass = match (true) {
    $active === 'archives' => 'paper-view-archives',
    $active === 'tags' => 'paper-view-tags',
    $active === 'links' => 'paper-view-links',
    str_starts_with($active, 'page:') => 'paper-view-page',
    $active === 'home' && $title === $siteName => 'paper-view-home',
    default => 'paper-view-post',
};
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>" data-paper-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <meta name="theme-color" content="#faf8f1" data-paper-theme-color>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){var r=document.documentElement,m=window.matchMedia('(prefers-color-scheme: dark)').matches,t=null;try{t=localStorage.getItem('paper-theme')}catch(e){}r.dataset.paperTheme=t==='dark'||t==='light'?t:(m?'dark':'light')})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?> <?= h($viewClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <?php theme_action('header_before', $themeContext); ?>
  <header class="paper-site-header">
    <div class="paper-header-inner">
      <div class="paper-brand-row">
        <a class="paper-brand" href="<?= h(url_for('home')) ?>"><?= h($siteName) ?></a>
        <button class="paper-theme-toggle" id="paper-theme-toggle" type="button" aria-label="<?= h(sblog_t('切换深色模式')) ?>" aria-pressed="false">
          <span class="paper-toggle-icon paper-toggle-icon--sun"><?= paper_icon('sun') ?></span>
          <span class="paper-toggle-icon paper-toggle-icon--moon"><?= paper_icon('moon') ?></span>
        </button>
      </div>

      <button class="paper-menu-toggle" id="paper-menu-toggle" type="button" aria-controls="paper-nav-panel" aria-expanded="false" aria-label="<?= h(sblog_t('打开菜单')) ?>">
        <span></span><span></span>
      </button>

      <div class="paper-nav-panel" id="paper-nav-panel">
        <nav class="paper-primary-nav" aria-label="<?= h(sblog_t('主导航')) ?>">
          <a class="paper-home-link <?= $active === 'home' ? 'is-active' : '' ?>" href="<?= h(url_for('home')) ?>"><?= h(sblog_t('首页')) ?></a>
          <a class="<?= $active === 'archives' ? 'is-active' : '' ?>" href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('归档')) ?></a>
          <a class="<?= $active === 'tags' ? 'is-active' : '' ?>" href="<?= h(url_for('tags')) ?>"><?= h(sblog_t('标签')) ?></a>
          <a class="<?= $active === 'links' ? 'is-active' : '' ?>" href="<?= h(url_for('links')) ?>"><?= h(sblog_t('友链')) ?></a>
          <?php foreach ($navPages as $page): ?>
            <a class="<?= $active === 'page:' . $page['slug'] ? 'is-active' : '' ?>" href="<?= h(content_permalink($page)) ?>"><?= h((string)$page['title']) ?></a>
          <?php endforeach; ?>
        </nav>

        <nav class="paper-social-nav" aria-label="<?= h(sblog_t('个人链接')) ?>">
          <a href="<?= h(url_for('rss')) ?>" target="_blank" rel="alternate noopener noreferrer" aria-label="RSS" title="RSS"><?= paper_icon('rss') ?></a>
          <?php if ($admin): ?><a href="<?= h(url_for('admin')) ?>" aria-label="<?= h(sblog_t('管理后台')) ?>" title="<?= h(sblog_t('管理后台')) ?>"><?= paper_icon('user') ?></a><?php endif; ?>
        </nav>
      </div>
    </div>
  </header>
  <?php theme_action('header_after', $themeContext); ?>

  <main class="paper-main" id="main-content">
    <?php if ($flash): ?><div class="paper-flash paper-flash--<?= h((string)($flash['type'] ?? 'success')) ?>" role="status"><?= h((string)($flash['message'] ?? '')) ?></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?>
    <?= $content ?>
    <?php theme_action('content_after', $themeContext); ?>
  </main>

  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="paper-footer">
    <div class="paper-footer-inner">
      <p>&copy; <?= h($currentYear) ?> <a href="<?= h(url_for('home')) ?>"><?= h($siteName) ?></a></p>
      <p class="paper-footer-links"><span><?= h(site_footer_text()) ?></span></p>
      <?php if ($beian !== ''): ?><p><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a></p><?php endif; ?>
    </div>
  </footer>
  <?php theme_action('footer_after', $themeContext); ?>

  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>"></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
