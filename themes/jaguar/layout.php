<?php

declare(strict_types=1);

$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$scriptFile = active_theme_file('script.js');
$scriptVersion = $scriptFile !== '' ? (string)filemtime($scriptFile) : $themeVersion;
$owner = one('SELECT github_url, qq_url, wechat_url, weibo_url, x_url, telegram_url, mastodon_url, bilibili_url, instagram_url, tiktok_url FROM users ORDER BY id ASC LIMIT 1') ?? [];
$navItems = [
    ['label' => sblog_t('首页'), 'url' => url_for('home'), 'active' => $active === 'home'],
    ['label' => sblog_t('归档'), 'url' => url_for('archives'), 'active' => $active === 'archives'],
    ['label' => sblog_t('标签'), 'url' => url_for('tags'), 'active' => $active === 'tags'],
    ['label' => sblog_t('链接'), 'url' => url_for('links'), 'active' => $active === 'links'],
];
foreach ($navPages as $page) {
    $navItems[] = ['label' => (string)$page['title'], 'url' => content_permalink($page), 'active' => $active === 'page:' . $page['slug']];
}
?>
<!doctype html>
<html class="jaguar-root" lang="<?= h(sblog_i18n_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <meta name="theme-color" content="#ffffff" data-jaguar-theme-color>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){try{var t=localStorage.getItem('jaguar-theme')||'auto';var d=t==='dark'||(t==='auto'&&matchMedia('(prefers-color-scheme:dark)').matches);if(d)document.documentElement.classList.add('dark')}catch(e){}})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <div class="surface--content">
    <?php theme_action('header_before', $themeContext); ?>
    <header class="metabar metabar--bordered">
      <div class="metabar--block">
        <h1 class="metabar--headline">
          <a href="<?= h(url_for('home')) ?>" class="metabar--logo" title="<?= h($siteName) ?>"><?= h($siteName) ?></a>
        </h1>
        <form method="get" class="search-form" action="<?= h(url_for('home')) ?>" role="search">
          <?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="home"><?php endif; ?>
          <svg width="24" height="24" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M4.092 11.06a6.95 6.95 0 1 1 13.9 0 6.95 6.95 0 0 1-13.9 0m6.95-8.05a8.05 8.05 0 1 0 5.13 14.26l3.75 3.75a.56.56 0 1 0 .79-.79l-3.73-3.73A8.05 8.05 0 0 0 11.042 3z" clip-rule="evenodd"></path></svg>
          <label><span class="screen-reader-text"><?= h(sblog_t('搜索')) ?></span><input type="search" class="search-field" placeholder="Search" name="s" value="<?= h((string)($_GET['s'] ?? '')) ?>"></label>
        </form>
        <nav class="site--nav" aria-label="<?= h(sblog_t('主导航')) ?>">
          <ul class="nav--list">
            <?php foreach ($navItems as $item): ?><li class="menu-item"><a class="<?= $item['active'] ? 'current' : '' ?>" href="<?= h((string)$item['url']) ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>><?= h((string)$item['label']) ?></a></li><?php endforeach; ?>
          </ul>
          <span class="u-xs-show nav--copyright"><?= h($siteName) ?> <?= h(date('Y')) ?></span>
        </nav>
        <button class="menu--icon" type="button" aria-label="<?= h(sblog_t('打开菜单')) ?>" title="<?= h(sblog_t('打开菜单')) ?>" aria-expanded="false">
          <svg viewBox="0 0 24 14" fill="none" aria-hidden="true"><path d="M24 1H0M24 7H4M24 13H8"></path></svg>
        </button>
      </div>
    </header>
    <?php theme_action('header_after', $themeContext); ?>
    <button class="mask" type="button" aria-label="<?= h(sblog_t('关闭菜单')) ?>" tabindex="-1"></button>

    <?php if ($flash): ?><div class="notice--wrapper is-active" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?>
    <?= $content ?>
    <?php theme_action('content_after', $themeContext); ?>

    <?php theme_action('footer_before', $themeContext); ?>
    <footer class="jFooter">
      <div class="jFooter--inner">
        <div class="jFooter--icons">
          <a href="<?= h(url_for('rss')) ?>" target="_blank" rel="noopener noreferrer" aria-label="RSS" title="RSS"><?= jaguar_sns_icon('rss') ?></a>
          <?php foreach (social_profile_definitions() as $key => $definition): ?>
            <?php $url = safe_link_url((string)($owner[$definition['column']] ?? '')); ?>
            <?php if ($url !== '#'): ?><a href="<?= h($url) ?>" target="_blank" rel="me noopener noreferrer" aria-label="<?= h((string)$definition['label']) ?>" title="<?= h((string)$definition['label']) ?>"><?= jaguar_sns_icon((string)$key) ?></a><?php endif; ?>
          <?php endforeach; ?>
        </div>
        <div class="jFooter--copyright">© <?= h(date('Y')) ?> <?= h($siteName) ?></div>
        <div class="fixed--theme" role="group" aria-label="<?= h(sblog_t('外观模式')) ?>">
          <button type="button" data-theme-mode="dark" aria-label="<?= h(sblog_t('夜间模式')) ?>" title="<?= h(sblog_t('夜间模式')) ?>" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg></button>
          <button type="button" data-theme-mode="light" aria-label="<?= h(sblog_t('日间模式')) ?>" title="<?= h(sblog_t('日间模式')) ?>" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg></button>
          <button type="button" data-theme-mode="auto" aria-label="<?= h(sblog_t('跟随系统')) ?>" title="<?= h(sblog_t('跟随系统')) ?>" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"></rect><path d="M8 21h8M12 17v4"></path></svg></button>
        </div>
      </div>
    </footer>
    <?php theme_action('footer_after', $themeContext); ?>
  </div>

  <button class="backToTop" type="button" aria-label="<?= h(sblog_t('回到顶部')) ?>" title="<?= h(sblog_t('回到顶部')) ?>"><svg class="svgIcon" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true"><path d="M7.5.425A.75.75 0 0 0 6.438.425L.728 6.132a.75.75 0 1 0 1.06 1.06l4.428-4.427v10.259a.75.75 0 0 0 1.5 0V2.765l4.428 4.427a.75.75 0 0 0 1.06-1.06L7.5.425Z"></path></svg></button>
  <script src="<?= h(asset_url('index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
