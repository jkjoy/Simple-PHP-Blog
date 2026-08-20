<?php

declare(strict_types=1);

$owner = one('SELECT nickname, username, avatar_url, website_url, github_url, qq_url, wechat_url, weibo_url, x_url, telegram_url, mastodon_url, bilibili_url, instagram_url, tiktok_url, signature FROM users ORDER BY id ASC LIMIT 1') ?? [];
$ownerName = trim((string)($owner['nickname'] ?? '')) ?: trim((string)($owner['username'] ?? '')) ?: $siteName;
$signature = trim((string)($owner['signature'] ?? '')) ?: trim(setting('site_tagline'));
$avatarUrl = theme_logo_url();

$socialLinks = [];
$websiteUrl = safe_link_url((string)($owner['website_url'] ?? ''));
if ($websiteUrl !== '#') {
    $socialLinks[] = ['url' => $websiteUrl, 'label' => sblog_t('个人主页'), 'icon' => 'ri-home-heart-line'];
}
foreach (social_profile_definitions() as $definition) {
    $safeUrl = safe_link_url((string)($owner[$definition['column']] ?? ''));
    if ($safeUrl !== '#') {
        $socialLinks[] = ['url' => $safeUrl, 'label' => (string)$definition['label'], 'icon' => (string)$definition['icon']];
    }
}

$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$viewClass = (string)($_GET['a'] ?? '') === 'category' ? 'ying-view-category'
    : ($active === 'home' && $title === $siteName ? 'ying-view-home'
    : ($active === 'archives' ? 'ying-view-archives'
    : ($active === 'tags' ? 'ying-view-tags'
    : ($active === 'links' ? 'ying-view-links'
    : (str_starts_with($active, 'page:') ? 'ying-view-page' : 'ying-view-post')))));
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <link rel="stylesheet" href="<?= h(theme_asset_url('assets/font/result.css')) ?>?v=<?= h($themeVersion) ?>">
  <link rel="stylesheet" href="<?= h(theme_asset_url('assets/css/output.css')) ?>?v=<?= h($themeVersion) ?>">
  <link rel="stylesheet" href="<?= h(theme_asset_url('assets/css/main.css')) ?>?v=<?= h($themeVersion) ?>">
  <link rel="stylesheet" href="<?= h(theme_asset_url('assets/css/remixicon.css')) ?>?v=<?= h($themeVersion) ?>">
  <?= sblog_i18n_head() ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
  <?php theme_action('head', $themeContext); ?>
</head>
<body class="<?= h($bodyClass) ?> <?= h($viewClass) ?> bg-gray-100 flex dark:!bg-gray-800/80 dark:text-gray-200 page-container">
  <?php theme_action('body_open', $themeContext); ?>
  <button aria-label="<?= h(sblog_t('返回顶部')) ?>" id="back-to-top" class="zindex fixed bottom-4 right-4 w-12 h-12 bg-blue-500 text-white rounded-full shadow-lg transition-opacity duration-300 hover:bg-blue-600 flex items-center justify-center opacity-0 pointer-events-none"><i class="ri-arrow-up-s-line text-xl" aria-hidden="true"></i></button>

  <main class="ying-shell w-full max-w-full bg-white shadow-lg mx-auto my-0 overflow-hidden p-8 min-h-fit dark:bg-zinc-900 sm:max-w-full sm:my-0 sm:p-8 md:max-w-2xl md:my-12 md:p-8 md:rounded-lg lg:max-w-3xl lg:my-16 lg:p-12 lg:rounded-lg">
    <?php theme_action('header_before', $themeContext); ?>
    <div class="ying-header">
      <div class="ying-profile-header">
        <img class="rounded-lg ying-avatar" src="<?= h($avatarUrl) ?>" width="80" height="80" alt="<?= h($ownerName) ?>" decoding="async" fetchpriority="high" onerror="this.onerror=null;this.src='<?= h(theme_logo_url()) ?>'">
        <div class="ying-profile-row">
          <nav aria-label="<?= h(sblog_t('个人链接')) ?>">
            <ul class="ying-social-list flex">
              <?php foreach ($socialLinks as $social): ?>
                <li><a href="<?= h((string)$social['url']) ?>" target="_blank" rel="me noopener noreferrer" aria-label="<?= h((string)$social['label']) ?>" title="<?= h((string)$social['label']) ?>"><i class="<?= h((string)$social['icon']) ?>" aria-hidden="true"></i></a></li>
              <?php endforeach; ?>
              <?php if ($admin): ?><li><a href="<?= h(url_for('admin')) ?>" aria-label="<?= h(sblog_t('管理后台')) ?>" title="<?= h(sblog_t('管理后台')) ?>"><i class="ri-user-line" aria-hidden="true"></i></a></li><?php endif; ?>
              <li><a href="<?= h(url_for('rss')) ?>" target="_blank" rel="noopener noreferrer" aria-label="RSS" title="RSS"><i class="ri-rss-fill" aria-hidden="true"></i></a></li>
            </ul>
          </nav>
          <h2 class="!text-sm"><span><?= h($signature) ?></span></h2>
        </div>
      </div>
      <hr class="border-solid border-gray-100 dark:!border-gray-300/50">

      <nav class="w-full flex my-4" aria-label="<?= h(sblog_t('主导航')) ?>">
        <div class="navh">
          <button class="menu-btn" id="menu-btn" type="button" aria-controls="main-menu" aria-expanded="false" aria-label="<?= h(sblog_t('打开菜单')) ?>"><i class="ri-menu-line" aria-hidden="true"></i></button>
          <div class="menu-backdrop" id="menu-backdrop"></div>
          <ul class="flex gap-3 items-center flex-gap-adjust main-menu" id="main-menu">
            <li class="close-btn"><button id="close-menu" type="button" aria-label="<?= h(sblog_t('关闭菜单')) ?>"><i class="ri-close-line" aria-hidden="true"></i></button></li>
            <li class="navli"><a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="<?= h(url_for('home')) ?>"><?= h(sblog_t('首页')) ?></a></li>
            <li class="navli"><a class="<?= $active === 'archives' ? 'is-active' : '' ?>" href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('归档')) ?></a></li>
            <li class="navli"><a class="<?= $active === 'tags' ? 'is-active' : '' ?>" href="<?= h(url_for('tags')) ?>"><?= h(sblog_t('标签')) ?></a></li>
            <li class="navli"><a class="<?= $active === 'links' ? 'is-active' : '' ?>" href="<?= h(url_for('links')) ?>"><?= h(sblog_t('友链')) ?></a></li>
            <?php foreach ($navPages as $page): ?>
              <li class="navli"><a class="<?= $active === 'page:' . $page['slug'] ? 'is-active' : '' ?>" href="<?= h(content_permalink($page)) ?>"><?= h((string)$page['title']) ?></a></li>
            <?php endforeach; ?>
          </ul>
          <ul class="flex items-center gap-4 other-icons">
            <li class="flex items-center"><button id="toggle-dark-mode" class="ying-icon-button" type="button" title="<?= h(sblog_t('黑夜模式')) ?>" aria-label="<?= h(sblog_t('切换深色模式')) ?>" aria-pressed="false"><i class="ri-moon-line" aria-hidden="true"></i></button></li>
          </ul>
        </div>
      </nav>
    </div>
    <?php theme_action('header_after', $themeContext); ?>

    <div id="pjax-content" class="action">
      <?php if ($flash): ?><div class="ying-flash ying-flash--<?= h((string)$flash['type']) ?>" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
      <?php if ($active === 'home' && $title === $siteName): ?><h2 class="mb-5 !text-sm text-gray-400 !font-bold dark:text-gray-300"><?= h(sblog_t('随笔')) ?><i class="ri-quill-pen-line" aria-hidden="true"></i></h2><?php endif; ?>
      <?php theme_action('content_before', $themeContext); ?>
      <?= $content ?>
      <?php theme_action('content_after', $themeContext); ?>
    </div>

    <?php theme_action('footer_before', $themeContext); ?>
    <footer class="footer text-right text-gray-500 text-xs mt-5 pt-5 dark:!border-gray-300/50 border-solid border-t border-gray-100">
      <nav class="flex flex-col gap-1">
        <p><?= h(site_footer_text()) ?></p>
        <p><?= h(sblog_t('Powered by')) ?> <a class="footercolor" href="https://github.com/jkjoy/Simple-PHP-Blog" target="_blank" rel="noopener noreferrer">Simple PHP Blog</a> <?= h(sblog_t('Theme by')) ?> <a class="footercolor" href="https://github.com/MagicBreeze/halo-theme-Ying" target="_blank" rel="noopener noreferrer">Ying</a></p>
        <?php $beian = trim(setting('footer_beian')); ?>
        <?php if ($beian !== ''): ?><p><a class="footercolor" href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a></p><?php endif; ?>
      </nav>
    </footer>
    <?php theme_action('footer_after', $themeContext); ?>
  </main>

  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>"></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($themeVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
