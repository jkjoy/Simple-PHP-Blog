<?php

declare(strict_types=1);

$action = (string)($_GET['a'] ?? 'home');
$isHome = $active === 'home' && $action === 'home';
$tagline = trim(setting('site_tagline')) ?: trim(setting('site_description'));
$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$scriptFile = __DIR__ . '/script.js';
$scriptVersion = is_file($scriptFile) ? (string)filemtime($scriptFile) : (string)($theme['version'] ?? '1.0.0');
$navItems = [
    ['label' => sblog_t('首页'), 'url' => url_for('home'), 'current' => $isHome],
    ['label' => sblog_t('归档'), 'url' => url_for('archives'), 'current' => $active === 'archives'],
    ['label' => sblog_t('标签'), 'url' => url_for('tags'), 'current' => $active === 'tags'],
    ['label' => sblog_t('分类'), 'url' => url_for('categories'), 'current' => $active === 'categories'],
    ['label' => sblog_t('友链'), 'url' => url_for('links'), 'current' => $active === 'links'],
];
foreach ($navPages as $page) {
    $navItems[] = ['label' => (string)$page['title'], 'url' => content_permalink($page), 'current' => $active === 'page:' . $page['slug']];
}
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <meta name="theme-color" content="#F5F5F0">
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?><?= $isHome ? ' candy-home' : '' ?>">
  <a class="candy-skip" href="#main-content"><?= h(sblog_t('跳转到内容')) ?></a>
  <?php theme_action('body_open', $themeContext); ?>
  <?php theme_action('header_before', $themeContext); ?>
  <header class="candy-header">
    <div class="candy-header__inner">
      <a class="candy-brand" href="<?= h(url_for('home')) ?>" aria-label="<?= h($siteName) ?>">
        <span class="candy-brand__icon"><?= candy_icon('spark') ?></span>
        <strong><?= h($siteName) ?></strong><span class="candy-brand__dot">.</span>
      </a>
      <nav class="candy-nav" id="candy-navigation" aria-label="<?= h(sblog_t('主导航')) ?>">
        <?php foreach ($navItems as $item): ?><a href="<?= h((string)$item['url']) ?>"<?= $item['current'] ? ' aria-current="page"' : '' ?>><?= h((string)$item['label']) ?></a><?php endforeach; ?>
        <?php if ($admin): ?><a href="<?= h(url_for('admin')) ?>"><?= h(sblog_t('管理')) ?></a><?php endif; ?>
      </nav>
      <a class="candy-header__rss" href="<?= h(url_for('rss')) ?>" aria-label="RSS" title="RSS"><?= candy_icon('rss') ?></a>
      <button class="candy-menu" type="button" aria-controls="candy-navigation" aria-expanded="false" aria-label="<?= h(sblog_t('打开菜单')) ?>" title="<?= h(sblog_t('打开菜单')) ?>" data-open-label="<?= h(sblog_t('打开菜单')) ?>" data-close-label="<?= h(sblog_t('关闭菜单')) ?>"><span class="candy-menu__open"><?= candy_icon('menu') ?></span><span class="candy-menu__close"><?= candy_icon('close') ?></span></button>
    </div>
    <div class="candy-progress" role="progressbar" aria-label="<?= h(sblog_t('阅读进度')) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div>
  </header>
  <?php theme_action('header_after', $themeContext); ?>

  <?php if ($isHome): ?>
    <section class="candy-hero" aria-labelledby="candy-hero-title">
      <svg class="candy-hero__backdrop" viewBox="0 0 1000 500" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M720 0h280v500H600Z" fill="#FFF4BD" stroke="#000" stroke-width="3"/></svg>
      <svg class="candy-hero__art" viewBox="0 0 640 500" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">
        <defs>
          <pattern id="candy-hero-stripes" width="14" height="14" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="14" height="14" fill="#DFF6FF"/><rect width="5" height="14" fill="#0A0A0A"/></pattern>
          <pattern id="candy-hero-dots" width="23" height="23" patternUnits="userSpaceOnUse"><circle cx="5" cy="5" r="3.2" fill="#0A0A0A"/></pattern>
          <filter id="candy-noise"><feTurbulence type="fractalNoise" baseFrequency=".7" numOctaves="3" stitchTiles="stitch"/></filter>
        </defs>
        <circle cx="383" cy="243" r="166" fill="#FFE135" stroke="#000" stroke-width="4"/>
        <circle cx="383" cy="243" r="165" fill="#fff" filter="url(#candy-noise)" opacity=".12"/>
        <path d="M54 333 206 106l229 152-152 227Z" fill="url(#candy-hero-stripes)" stroke="#000" stroke-width="4"/>
        <rect x="322" y="16" width="220" height="136" fill="#CFFBAA" stroke="#000" stroke-width="4" transform="rotate(12 432 84)"/>
        <rect x="344" y="24" width="220" height="136" fill="url(#candy-hero-dots)" transform="rotate(12 454 92)"/>
        <path d="m410 130 38 61 69-12-43 57 38 62-71-21-43 56 1-72-69-22 70-20Z" fill="#FF1493" stroke="#000" stroke-width="4"/>
        <circle cx="131" cy="128" r="45" fill="#00BFFF" stroke="#000" stroke-width="4"/>
        <circle cx="131" cy="128" r="26" fill="none" stroke="#000" stroke-width="4"/>
        <path d="M550 333v112m-56-56h112" stroke="#000" stroke-width="11"/>
        <path d="M43 402h69m-34-34v69" stroke="#000" stroke-width="8"/>
      </svg>
      <div class="candy-hero__inner">
        <span class="candy-hero__eyebrow candy-reveal"><svg class="candy-live-dot" viewBox="0 0 10 10" aria-hidden="true" focusable="false"><circle cx="5" cy="5" r="4" fill="#FF1493" stroke="#000" stroke-width="2"/></svg> <?= h(sblog_t('正在记录')) ?> / <?= h(date('Y')) ?></span>
        <h1 id="candy-hero-title" class="candy-reveal"><?= h($siteName) ?><span class="candy-hero__period">.</span></h1>
        <?php if ($tagline !== ''): ?><p class="candy-hero__intro candy-reveal"><?= h($tagline) ?></p><?php endif; ?>
        <div class="candy-hero__actions candy-reveal"><a class="candy-button candy-button--pink" href="#candy-feed"><?= h(sblog_t('开始阅读')) ?><?= candy_icon('arrow') ?></a><a class="candy-button candy-button--outline" href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('浏览归档')) ?><?= candy_icon('up-right') ?></a></div>
        <span class="candy-hero__serial">N&deg; <?= h(str_pad((string)count_published_posts(), 3, '0', STR_PAD_LEFT)) ?> <?= h(sblog_t('篇文章')) ?></span>
      </div>
    </section>
  <?php endif; ?>

  <main class="candy-main" id="main-content" tabindex="-1">
    <?php if ($flash): ?><div class="candy-flash" role="status"><?= h((string)($flash['message'] ?? '')) ?></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?>
    <?= $content ?>
    <?php theme_action('content_after', $themeContext); ?>
  </main>

  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="candy-footer">
    <div class="candy-footer__inner">
      <div class="candy-footer__top"><div><span class="candy-footer__kicker">END OF PAGE / <?= h(date('Y')) ?></span><a class="candy-footer__brand" href="<?= h(url_for('home')) ?>"><?= h($siteName) ?><span>.</span></a></div><a class="candy-footer__toplink" href="#main-content" aria-label="<?= h(sblog_t('返回内容')) ?>" title="<?= h(sblog_t('返回内容')) ?>"><?= candy_icon('up') ?></a></div>
      <div class="candy-footer__bottom"><p>&copy; <?= h(date('Y')) ?> <?= h($siteName) ?>. <?= h(site_footer_text()) ?></p><nav aria-label="<?= h(sblog_t('页脚导航')) ?>"><a href="<?= h(url_for('rss')) ?>">RSS</a><a href="<?= h(url_for('sitemap')) ?>">Sitemap</a><?php $beian = trim(setting('footer_beian')); if ($beian !== ''): ?><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a><?php endif; ?></nav></div>
    </div>
  </footer>
  <?php theme_action('footer_after', $themeContext); ?>
  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
