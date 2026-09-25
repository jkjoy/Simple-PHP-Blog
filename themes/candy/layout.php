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
      <svg class="candy-hero__backdrop" viewBox="0 0 1000 500" preserveAspectRatio="none" aria-hidden="true" focusable="false"><defs><filter id="candy-hero-grain"><feTurbulence type="fractalNoise" baseFrequency=".5" numOctaves="3" stitchTiles="stitch"/></filter></defs><rect width="1000" height="500" fill="#000" filter="url(#candy-hero-grain)" opacity=".045"/></svg>
      <svg class="candy-hero__art" viewBox="0 0 640 500" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">
        <defs>
          <pattern id="candy-hero-stripes" width="15" height="15" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="15" height="15" fill="#FFE135"/><rect width="5" height="15" fill="#0A0A0A"/></pattern>
          <pattern id="candy-hero-dots" width="23" height="23" patternUnits="userSpaceOnUse"><circle cx="5" cy="5" r="2.8" fill="#0A0A0A"/></pattern>
        </defs>
        <path d="M45 279h138v138H45z" fill="url(#candy-hero-stripes)" stroke="#000" stroke-width="4"/>
        <circle cx="515" cy="105" r="81" fill="url(#candy-hero-dots)" opacity=".55"/>
        <path d="M131 72h350v338H131z" fill="#fff" stroke="#000" stroke-width="5" transform="rotate(-8 306 241)"/>
        <path d="M163 62h350v338H163z" fill="#FFE135" stroke="#000" stroke-width="5" transform="rotate(6 338 231)"/>
        <path d="M145 79h350v338H145z" fill="#FF1493" stroke="#000" stroke-width="6" transform="rotate(3 320 248)"/>
        <path d="M193 127h259v241H193z" fill="#FFF7CD" stroke="#000" stroke-width="5"/>
        <path d="M223 169h193M223 199h173M223 229h193M223 259h137" fill="none" stroke="#000" stroke-width="8"/>
        <path d="M263 306c14-23 36-23 50 0 14-23 36-23 50 0-4 30-50 53-50 53s-46-23-50-53Z" fill="#FF1493" stroke="#000" stroke-width="5"/>
        <path d="m491 330 84-173 22 12-84 174-33 31Z" fill="#00BFFF" stroke="#000" stroke-width="5"/>
        <path d="m575 157 12-24 22 12-12 24" fill="#FF8A36" stroke="#000" stroke-width="5"/>
        <circle cx="116" cy="387" r="37" fill="#A9EE57" stroke="#000" stroke-width="5"/>
        <path d="m97 386 14 14 27-30" fill="none" stroke="#000" stroke-width="6"/>
        <path d="M68 118h26m-13-13v26M554 394h30m-15-15v30" stroke="#000" stroke-width="7"/>
      </svg>
      <div class="candy-hero__inner">
        <span class="candy-hero__eyebrow candy-reveal"><svg class="candy-live-dot" viewBox="0 0 10 10" aria-hidden="true" focusable="false"><circle cx="5" cy="5" r="4" fill="#FF1493" stroke="#000" stroke-width="2"/></svg> <?= h(sblog_t('正在记录')) ?> / <?= h(date('Y')) ?></span>
        <h1 id="candy-hero-title" class="candy-reveal"><?= h($siteName) ?><span class="candy-hero__period">.</span></h1>
        <?php if ($tagline !== ''): ?><p class="candy-hero__intro candy-reveal"><?= h($tagline) ?></p><?php endif; ?>
        <div class="candy-hero__actions candy-reveal"><a class="candy-button candy-button--ink" href="#candy-feed"><?= h(sblog_t('开始阅读')) ?><?= candy_icon('arrow') ?></a><a class="candy-button candy-button--outline" href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('浏览归档')) ?><?= candy_icon('up-right') ?></a></div>
      </div>
    </section>
    <div class="candy-hero-strip" aria-hidden="true"><div class="candy-hero-strip__inner"><span>WORDS / IDEAS / EVERYDAY</span><span><?= h(sblog_tn('{count} 篇文章', count_published_posts())) ?> &nbsp; / &nbsp; <?= h(date('Y')) ?></span></div></div>
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
