<?php

declare(strict_types=1);

$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$scriptFile = active_theme_file('script.js');
$scriptVersion = $scriptFile !== '' ? (string)filemtime($scriptFile) : $themeVersion;
$logoUrl = theme_logo_url();
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
<html lang="<?= h(sblog_i18n_locale()) ?>" data-once-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <meta name="theme-color" content="#ffffff" data-once-theme-color>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){try{var m=localStorage.getItem('once-theme')||'auto';var d=m==='dark'||(m==='auto'&&matchMedia('(prefers-color-scheme:dark)').matches);document.documentElement.dataset.onceTheme=d?'dark':'light'}catch(e){}})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <div class="once-site">
    <?php theme_action('header_before', $themeContext); ?>
    <header class="once-header">
      <div class="once-container once-header__inner">
        <div class="once-header__left">
          <a class="once-logo" href="<?= h(url_for('home')) ?>" aria-label="<?= h($siteName) ?>"><img src="<?= h($logoUrl) ?>" width="30" height="30" alt=""><strong><?= h($siteName) ?></strong></a>
          <nav class="once-desktop-nav" aria-label="<?= h(sblog_t('主导航')) ?>"><ul><?php foreach ($navItems as $item): ?><li><a<?= $item['active'] ? ' class="is-active" aria-current="page"' : '' ?> href="<?= h((string)$item['url']) ?>"><?= h((string)$item['label']) ?></a></li><?php endforeach; ?></ul></nav>
        </div>
        <div class="once-header__actions">
          <button class="once-icon-button" type="button" data-once-theme-toggle aria-label="<?= h(sblog_t('切换主题')) ?>" title="<?= h(sblog_t('切换主题')) ?>"><span class="once-theme-icon once-theme-icon--moon"><?= once_icon('moon', 15) ?></span><span class="once-theme-icon once-theme-icon--sun"><?= once_icon('sun', 15) ?></span></button>
          <button class="once-icon-button" type="button" data-once-search-toggle aria-label="<?= h(sblog_t('搜索')) ?>" title="<?= h(sblog_t('搜索')) ?>" aria-expanded="false"><?= once_icon('search', 15) ?></button>
          <button class="once-icon-button once-mobile-menu-button" type="button" data-once-menu-open aria-label="<?= h(sblog_t('打开菜单')) ?>" title="<?= h(sblog_t('打开菜单')) ?>" aria-expanded="false" aria-controls="once-mobile-drawer"><?= once_icon('menu', 19) ?></button>
        </div>
      </div>
      <div class="once-search-panel" data-once-search-panel hidden><div class="once-container"><form method="get" action="<?= h(url_for('home')) ?>" role="search"><?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="home"><?php endif; ?><label class="sr-only" for="once-header-search"><?= h(sblog_t('搜索')) ?></label><input id="once-header-search" name="s" type="search" placeholder="<?= h(sblog_t('输入关键词搜索')) ?>" value="<?= h((string)($_GET['s'] ?? '')) ?>"><button type="submit"><?= once_icon('search', 16) ?><span class="sr-only"><?= h(sblog_t('搜索')) ?></span></button></form></div></div>
    </header>
    <?php theme_action('header_after', $themeContext); ?>

    <div class="once-drawer-backdrop" data-once-menu-close hidden></div>
    <aside class="once-drawer" id="once-mobile-drawer" aria-hidden="true">
      <div class="once-drawer__head"><a class="once-logo" href="<?= h(url_for('home')) ?>"><img src="<?= h($logoUrl) ?>" width="28" height="28" alt=""><strong><?= h($siteName) ?></strong></a><button class="once-icon-button" type="button" data-once-menu-close aria-label="<?= h(sblog_t('关闭菜单')) ?>" title="<?= h(sblog_t('关闭菜单')) ?>"><?= once_icon('close', 20) ?></button></div>
      <nav aria-label="<?= h(sblog_t('移动导航')) ?>"><ul><?php foreach ($navItems as $item): ?><li><a<?= $item['active'] ? ' class="is-active" aria-current="page"' : '' ?> href="<?= h((string)$item['url']) ?>"><?= h((string)$item['label']) ?></a></li><?php endforeach; ?></ul></nav>
    </aside>

    <div class="once-container once-main">
      <?php if ($flash): ?><div class="once-flash once-flash--<?= h((string)($flash['type'] ?? 'success')) ?>" role="status"><?= h((string)($flash['message'] ?? '')) ?></div><?php endif; ?>
      <?php theme_action('content_before', $themeContext); ?>
      <?= $content ?>
      <?php theme_action('content_after', $themeContext); ?>
    </div>

    <?php theme_action('footer_before', $themeContext); ?>
    <section class="once-footer-links"><div class="once-container"><span><?= h(sblog_t('友情链接')) ?>：</span><?php foreach (all_rows('SELECT name, url FROM links ORDER BY sort_order ASC, id DESC LIMIT 10') as $link): ?><a href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$link['name']) ?></a><?php endforeach; ?></div></section>
    <footer class="once-footer"><div class="once-container"><p>© <?= h(date('Y')) ?> <?= h($siteName) ?>.</p><p><?= h(site_footer_text()) ?><?php if (trim(setting('footer_beian')) !== ''): ?> · <?= h(setting('footer_beian')) ?><?php endif; ?></p></div></footer>
    <?php theme_action('footer_after', $themeContext); ?>
  </div>
  <button class="once-back-top" type="button" data-once-back-top aria-label="<?= h(sblog_t('回到顶部')) ?>" title="<?= h(sblog_t('回到顶部')) ?>"><?= once_icon('arrow-up', 18) ?></button>
  <script src="<?= h(asset_url('index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
