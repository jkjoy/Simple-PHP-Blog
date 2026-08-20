<?php

declare(strict_types=1);

$action = (string)($_GET['a'] ?? '');
$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$scriptFile = active_theme_file('script.js');
$scriptVersion = $scriptFile !== '' ? (string)filemtime($scriptFile) : $themeVersion;
$logoUrl = theme_logo_url();
$owner = one('SELECT github_url, qq_url, wechat_url, weibo_url, x_url, telegram_url, mastodon_url, bilibili_url, instagram_url, tiktok_url FROM users ORDER BY id ASC LIMIT 1') ?? [];
$socialLinks = [];
foreach (social_profile_definitions() as $key => $definition) {
    $url = safe_link_url((string)($owner[$definition['column']] ?? ''));
    if ($url !== '#') {
        $socialLinks[] = ['key' => $key, 'url' => $url, 'label' => (string)$definition['label']];
    }
}
$navItems = [
    ['label' => sblog_t('归档'), 'url' => url_for('archives'), 'active' => $active === 'archives'],
    ['label' => sblog_t('标签'), 'url' => url_for('tags'), 'active' => $active === 'tags'],
    ['label' => sblog_t('链接'), 'url' => url_for('links'), 'active' => $active === 'links'],
];
foreach ($navPages as $page) {
    $navItems[] = ['label' => (string)$page['title'], 'url' => content_permalink($page), 'active' => $active === 'page:' . $page['slug']];
}
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <meta name="theme-color" content="#ffffff" data-farallon-theme-color>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){try{var t=localStorage.getItem('farallon-theme')||'auto';var d=t==='dark'||(t==='auto'&&matchMedia('(prefers-color-scheme: dark)').matches);if(d)document.documentElement.classList.add('dark')}catch(e){}})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <div class="main">
    <?php theme_action('header_before', $themeContext); ?>
    <header class="site--header">
      <a href="<?= h(url_for('home')) ?>" class="site--url" aria-label="<?= h($siteName) ?>">
        <img src="<?= h($logoUrl) ?>" class="avatar" width="48" height="48" alt="<?= h($siteName) ?>">
      </a>
      <span class="u-xs-show"><a href="<?= h(url_for('home')) ?>"><?= h($siteName) ?></a></span>
      <div class="site--header__center">
        <div class="inner">
          <nav aria-label="<?= h(sblog_t('主导航')) ?>"><ul>
            <?php foreach ($navItems as $item): ?><li><a class="<?= $item['active'] ? 'current' : '' ?>" href="<?= h((string)$item['url']) ?>"><?= h((string)$item['label']) ?></a></li><?php endforeach; ?>
          </ul></nav>
          <div class="search--area">
            <form method="get" action="<?= h(url_for('home')) ?>" role="search" class="search-form">
              <?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="home"><?php endif; ?>
              <label><span class="sr-only"><?= h(sblog_t('搜索')) ?></span><input type="search" name="s" class="search-field" placeholder="Search" value="<?= h((string)($_GET['s'] ?? '')) ?>" required></label>
              <button type="submit" class="search-submit"><?= h(sblog_t('搜索')) ?></button>
            </form>
          </div>
        </div>
      </div>
      <div class="header-actions">
        <button class="icon-button search-toggle" type="button" aria-label="<?= h(sblog_t('搜索')) ?>" title="<?= h(sblog_t('搜索')) ?>" aria-expanded="false">
          <svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>
        </button>
      </div>
    </header>
    <?php theme_action('header_after', $themeContext); ?>

    <?php if ($flash): ?><div class="notice--wrapper" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?>
    <?= $content ?>
    <?php theme_action('content_after', $themeContext); ?>

    <?php theme_action('footer_before', $themeContext); ?>
    <footer class="site--footer">
      <div class="site--footer__content">
        <div class="site--footer__sns" aria-label="<?= h(sblog_t('社交链接')) ?>">
          <a href="<?= h(url_for('rss')) ?>" aria-label="RSS" title="RSS"><?= farallon_sns_icon('rss') ?></a>
          <?php foreach ($socialLinks as $social): ?><a href="<?= h((string)$social['url']) ?>" target="_blank" rel="me noopener noreferrer" aria-label="<?= h((string)$social['label']) ?>" title="<?= h((string)$social['label']) ?>"><?= farallon_sns_icon((string)$social['key']) ?></a><?php endforeach; ?>
        </div>
        <div class="copyright">© <?= h(date('Y')) ?> <?= h($siteName) ?> <svg viewBox="0 0 24 24" width="16" height="16" aria-label="Love"><path d="M12 21s-7-4.6-9.4-9C.5 8.1 2.7 4 6.8 4c2.2 0 3.6 1.2 5.2 3 1.6-1.8 3-3 5.2-3 4.1 0 6.3 4.1 4.2 8-2.4 4.4-9.4 9-9.4 9z"></path></svg></div>
      </div>
    </footer>
    <?php theme_action('footer_after', $themeContext); ?>
  </div>

  <div class="fixed--theme" role="group" aria-label="<?= h(sblog_t('外观模式')) ?>">
    <button type="button" data-theme-mode="dark" aria-label="<?= h(sblog_t('夜间模式')) ?>" title="<?= h(sblog_t('夜间模式')) ?>" aria-pressed="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
    </button>
    <button type="button" data-theme-mode="light" aria-label="<?= h(sblog_t('日间模式')) ?>" title="<?= h(sblog_t('日间模式')) ?>" aria-pressed="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path></svg>
    </button>
    <button type="button" data-theme-mode="auto" aria-label="<?= h(sblog_t('跟随系统')) ?>" title="<?= h(sblog_t('跟随系统')) ?>" aria-pressed="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><path d="M8 21h8M12 17v4"></path></svg>
    </button>
  </div>
  <button class="backToTop" type="button" aria-label="<?= h(sblog_t('回到顶部')) ?>" title="<?= h(sblog_t('回到顶部')) ?>">
    <svg class="svgIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"></path></svg>
  </button>
  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
