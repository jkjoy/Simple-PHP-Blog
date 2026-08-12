<?php

declare(strict_types=1);

$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$scriptFile = __DIR__ . '/script.js';
$scriptVersion = is_file($scriptFile) ? (string)filemtime($scriptFile) : (string)($theme['version'] ?? '1.0.0');
$footerText = site_footer_text();
$navItems = [
    ['label' => '首页', 'url' => url_for('home'), 'active' => $active === 'home' && (string)($_GET['a'] ?? '') !== 'category'],
    ['label' => '归档', 'url' => url_for('archives'), 'active' => $active === 'archives'],
    ['label' => '标签', 'url' => url_for('tags'), 'active' => $active === 'tags'],
    ['label' => '链接', 'url' => url_for('links'), 'active' => $active === 'links'],
];
foreach ($navPages as $page) {
    $navItems[] = ['label' => (string)$page['title'], 'url' => content_permalink($page), 'active' => $active === 'page:' . $page['slug']];
}
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>" data-mode="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <meta name="theme-color" content="#002538">
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <link rel="stylesheet" href="<?= h(theme_asset_url('assets/css/styles.css')) ?>">
  <script>(function(){try{var m=localStorage.getItem('clarity-mode');if(m==='lit'||m==='dim')document.documentElement.dataset.mode=m;else document.documentElement.removeAttribute('data-mode')}catch(e){}})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?><?= $customHeadCode . "\n" ?><?php endif; ?>
</head>
<body id="documentTop" class="<?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <?php theme_action('header_before', $themeContext); ?>
  <header class="nav_header"><nav class="nav" aria-label="主导航">
    <a class="nav_brand nav_item" href="<?= h(url_for('home')) ?>" title="<?= h($siteName) ?>">
      <img class="logo" src="<?= h(theme_logo_url()) ?>" alt="<?= h($siteName) ?>">
      <span class="nav_site_name"><?= h($siteName) ?></span>
      <span class="nav_close" aria-hidden="true"><?= clarity_icon('menu') ?><?= clarity_icon('close') ?></span>
    </a>
    <div class="nav_body nav_body_left"><?php foreach ($navItems as $item): ?><div class="nav_parent<?= $item['active'] ? ' nav_active' : '' ?>"><a class="nav_item" href="<?= h((string)$item['url']) ?>"><?= h((string)$item['label']) ?></a></div><?php endforeach; ?>
      <div class="follow"><a href="<?= h(url_for('rss')) ?>" aria-label="RSS" title="RSS"><?= clarity_icon('rss') ?></a><div class="color_mode"><input id="mode" class="color_choice" type="checkbox" aria-label="切换深浅色模式"></div></div>
    </div>
  </nav></header>
  <?php theme_action('header_after', $themeContext); ?>
  <main>
    <?php if ($flash): ?><div class="wrap content notice info" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?><?= $content ?><?php theme_action('content_after', $themeContext); ?>
  </main>
  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="footer"><div class="footer_inner wrap pale"><img src="<?= h(theme_asset_url('assets/icons/apple-touch-icon.png')) ?>" class="icon icon_2 transparent" alt="<?= h($siteName) ?>"><p><?= h($footerText) ?></p><a class="to_top" href="#documentTop" aria-label="回到顶部" title="回到顶部"><?= clarity_icon('top') ?></a></div></footer>
  <?php theme_action('footer_after', $themeContext); ?>
  <script src="<?= h(asset_url('index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
