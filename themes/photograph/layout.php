<?php

declare(strict_types=1);

$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$scriptFile = active_theme_file('script.js');
$scriptVersion = $scriptFile !== '' ? (string)filemtime($scriptFile) : $themeVersion;
$categories = fetch_categories();
$footerLinks = all_rows('SELECT name, url, description FROM links ORDER BY sort_order ASC, id DESC LIMIT 12');
$navItems = [
    ['label' => sblog_t('首页'), 'url' => url_for('home'), 'active' => $active === 'home'],
];
foreach ($categories as $category) {
    $navItems[] = ['label' => (string)$category['name'], 'url' => url_for('category', ['slug' => (string)$category['slug']]), 'active' => (string)($_GET['a'] ?? '') === 'category' && (string)($_GET['slug'] ?? '') === (string)$category['slug']];
}
$navItems[] = ['label' => sblog_t('标签'), 'url' => url_for('tags'), 'active' => $active === 'tags'];
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
  <meta name="theme-color" content="#ffffff" data-photograph-theme-color>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){try{if(localStorage.getItem('photograph-theme')==='dark')document.documentElement.classList.add('photo-dark')}catch(e){}})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?><?= $customHeadCode . "\n" ?><?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <?php theme_action('header_before', $themeContext); ?>
  <nav class="photo-navbar" aria-label="<?= h(sblog_t('主导航')) ?>">
    <div class="photo-navbar__inner">
      <button class="photo-menu-toggle" type="button" data-menu-toggle aria-label="<?= h(sblog_t('打开菜单')) ?>" aria-expanded="false"><?= photograph_icon('menu') ?></button>
      <a class="photo-brand" href="<?= h(url_for('home')) ?>"><?php $logo = theme_logo_url(); if ($logo !== ''): ?><img src="<?= h($logo) ?>" alt="<?= h($siteName) ?>"><?php else: ?><?= h($siteName) ?><?php endif; ?></a>
      <div class="photo-navbar__collapse" data-menu>
        <ul class="photo-nav-list"><?php foreach ($navItems as $item): ?><li><a class="<?= $item['active'] ? 'is-active' : '' ?>" href="<?= h((string)$item['url']) ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>><?= h((string)$item['label']) ?></a></li><?php endforeach; ?></ul>
        <form class="photo-search" action="<?= h(url_for('home')) ?>" method="get" role="search"><?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="home"><?php endif; ?><label><span class="sr-only"><?= h(sblog_t('搜索')) ?></span><input name="s" type="search" value="<?= h((string)($_GET['s'] ?? '')) ?>" placeholder="<?= h(sblog_t('输入关键字搜索')) ?>"></label><button type="submit" aria-label="<?= h(sblog_t('搜索')) ?>"><?= photograph_icon('search') ?></button></form>
        <a class="photo-admin-link" href="<?= h(url_for('admin')) ?>"><?= photograph_icon('settings') ?><span><?= h(sblog_t('管理')) ?></span></a>
      </div>
      <button class="photo-theme-toggle" type="button" data-theme-toggle aria-label="<?= h(sblog_t('切换外观')) ?>" title="<?= h(sblog_t('切换外观')) ?>"><?= photograph_icon('sun') ?></button>
    </div>
  </nav>
  <?php theme_action('header_after', $themeContext); ?>
  <?php if ($flash): ?><div class="photo-notice" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
  <?php theme_action('content_before', $themeContext); ?>
  <?= $content ?>
  <?php theme_action('content_after', $themeContext); ?>

  <div class="photo-side-tools" aria-label="<?= h(sblog_t('页面工具')) ?>">
    <button type="button" data-scroll-top aria-label="<?= h(sblog_t('回到顶部')) ?>" title="<?= h(sblog_t('回到顶部')) ?>"><?= photograph_icon('up') ?></button>
    <button type="button" data-scroll-bottom aria-label="<?= h(sblog_t('滚动到底部')) ?>" title="<?= h(sblog_t('滚动到底部')) ?>"><?= photograph_icon('down') ?></button>
    <button type="button" data-qr-toggle aria-label="<?= h(sblog_t('二维码')) ?>" title="<?= h(sblog_t('二维码')) ?>"><?= photograph_icon('qr') ?></button>
    <?php if (str_contains($bodyClass, 'photograph-album-view')): ?><button type="button" data-comments-toggle aria-label="<?= h(sblog_t('评论')) ?>" title="<?= h(sblog_t('评论')) ?>"><?= photograph_icon('comment') ?></button><?php endif; ?>
  </div>
  <div class="photo-qr-modal" data-qr-modal hidden><button type="button" data-qr-close aria-label="<?= h(sblog_t('关闭')) ?>"></button><div data-qr-code></div></div>
  <div class="photo-lightbox" data-lightbox hidden><button class="photo-lightbox__close" type="button" data-lightbox-close aria-label="<?= h(sblog_t('关闭')) ?>"><?= photograph_icon('close') ?></button><button class="photo-lightbox__backdrop" type="button" data-lightbox-close tabindex="-1" aria-label="<?= h(sblog_t('关闭')) ?>"></button><figure><img src="" alt=""><figcaption></figcaption></figure></div>

  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="photo-footer">
    <?php if ($footerLinks): ?><p>友情链接：<?php foreach ($footerLinks as $link): ?><a href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer" title="<?= h((string)$link['description']) ?>"><?= h((string)$link['name']) ?></a> <?php endforeach; ?></p><?php endif; ?>
    <p>POWERED BY SIMPLE PHP BLOG / THEME BY SIITAKE</p>
    <p>&copy; <?= h(date('Y')) ?> <a href="<?= h(url_for('home')) ?>"><?= h($siteName) ?></a><?php $beian = trim(setting('footer_beian')); if ($beian !== ''): ?> <span><?= h($beian) ?></span><?php endif; ?></p>
  </footer>
  <?php theme_action('footer_after', $themeContext); ?>
  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('assets/qrcode.js')) ?>?v=2.0.9" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
