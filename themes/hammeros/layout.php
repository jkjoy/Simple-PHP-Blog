<?php

declare(strict_types=1);

$action = (string)($_GET['a'] ?? '');
$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$scriptFile = active_theme_file('script.js');
$scriptVersion = $scriptFile !== '' ? (string)filemtime($scriptFile) : $themeVersion;
$tagline = trim(setting('site_tagline')) ?: sblog_t('把复杂留给系统，把时间还给生活。');
$logoUrl = theme_logo_url();
$postCount = count_published_posts();
$tagCount = count(tag_index_data());
$firstPublished = (int)val('SELECT MIN(published_at) FROM posts WHERE kind = ? AND status = ?', ['post', 'published']);
$runningDays = $firstPublished > 0 ? max(1, (int)floor((time() - $firstPublished) / 86400)) : 0;
$adminOnline = admin_is_online();
$viewClass = match (true) {
    $action === 'archives' || $active === 'archives' => 'hammer-view-archives',
    $action === 'tag' => 'hammer-view-tag',
    $active === 'tags' => 'hammer-view-tags',
    $active === 'links' => 'hammer-view-links',
    $action === 'category' => 'hammer-view-category',
    str_starts_with($active, 'page:') => 'hammer-view-page',
    $active === 'home' && $title === $siteName => 'hammer-view-home',
    default => 'hammer-view-post',
};
$hour = (int)date('G');
$greeting = match (true) {
    $hour < 6 => sblog_t('夜深了，我是这里的系统管家。'),
    $hour < 11 => sblog_t('早上好，我是这里的系统管家。'),
    $hour < 14 => sblog_t('中午好，我是这里的系统管家。'),
    $hour < 18 => sblog_t('下午好，我是这里的系统管家。'),
    default => sblog_t('晚上好，我是这里的系统管家。'),
};
$adminStatusLabel = $adminOnline ? sblog_t('管理员当前在线') : sblog_t('管理员当前离线');
$adminStatusText = $adminOnline ? sblog_t('ONLINE') : sblog_t('OFFLINE');
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){try{var t=localStorage.getItem('hammeros-theme');if(t==='dark'||t==='light')document.documentElement.setAttribute('data-theme',t);else if(matchMedia('(prefers-color-scheme: dark)').matches)document.documentElement.setAttribute('data-theme','dark')}catch(e){}})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?> <?= h($viewClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>

  <div class="hammer-grain" aria-hidden="true"></div>
  <?php theme_action('header_before', $themeContext); ?>
  <header class="hammer-header">
    <div class="hammer-header__inner">
      <a class="hammer-brand" href="<?= h(url_for('home')) ?>">
        <span class="hammer-brand__mark"><img src="<?= h($logoUrl) ?>" alt="" width="34" height="34"></span>
        <span><strong><?= h($siteName) ?></strong><small><?= h(sblog_t('PERSONAL SYSTEM')) ?></small></span>
      </a>

      <nav class="hammer-nav" id="hammer-nav" aria-label="<?= h(sblog_t('主导航')) ?>">
        <a href="<?= h(url_for('home')) ?>" class="<?= $active === 'home' && $action !== 'category' ? 'is-active' : '' ?>"><?= h(sblog_t('首页')) ?></a>
        <a href="<?= h(url_for('archives')) ?>" class="<?= $active === 'archives' ? 'is-active' : '' ?>"><?= h(sblog_t('归档')) ?></a>
        <a href="<?= h(url_for('tags')) ?>" class="<?= $active === 'tags' ? 'is-active' : '' ?>"><?= h(sblog_t('标签')) ?></a>
        <a href="<?= h(url_for('links')) ?>" class="<?= $active === 'links' ? 'is-active' : '' ?>"><?= h(sblog_t('朋友')) ?></a>
        <?php foreach ($navPages as $page): ?>
          <a href="<?= h(content_permalink($page)) ?>" class="<?= $active === 'page:' . $page['slug'] ? 'is-active' : '' ?>"><?= h((string)$page['title']) ?></a>
        <?php endforeach; ?>
        <?php if ($admin): ?><a class="hammer-nav__account <?= $active === 'admin' ? 'is-active' : '' ?>" href="<?= h(url_for('admin')) ?>"><?= h(sblog_t('管理')) ?></a><?php endif; ?>
      </nav>

      <div class="hammer-tools">
        <span class="hammer-clock" data-hammer-clock><?= h(date('H:i')) ?></span>
        <button class="hammer-icon-button hammer-theme-toggle" type="button" aria-label="<?= h(sblog_t('切换深浅模式')) ?>" aria-pressed="false" title="<?= h(sblog_t('切换深浅模式')) ?>">
          <span class="hammer-theme-toggle__sun" aria-hidden="true">☀</span>
          <span class="hammer-theme-toggle__moon" aria-hidden="true">☾</span>
        </button>
        <button class="hammer-icon-button hammer-menu-toggle" type="button" aria-label="<?= h(sblog_t('打开菜单')) ?>" aria-controls="hammer-nav" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>
  <div class="hammer-nav-backdrop" data-hammer-nav-backdrop></div>
  <?php theme_action('header_after', $themeContext); ?>

  <div class="hammer-layout">
    <aside class="hammer-companion" aria-label="<?= h(sblog_t('系统管家')) ?>">
      <div class="hammer-companion__top">
        <span class="hammer-companion__label"><?= h(sblog_t('SYSTEM BUDDY')) ?></span>
        <span class="hammer-online <?= $adminOnline ? 'is-online' : 'is-offline' ?>" aria-label="<?= h($adminStatusLabel) ?>"><i></i> <?= h($adminStatusText) ?></span>
      </div>
      <button class="hammer-buddy" type="button" data-hammer-buddy aria-label="<?= h(sblog_t('和系统管家打个招呼')) ?>" title="<?= h(sblog_t('打个招呼')) ?>">
        <span class="hammer-buddy__ears" aria-hidden="true"></span>
        <span class="hammer-buddy__head" aria-hidden="true">
          <i class="hammer-eye hammer-eye--left"></i><i class="hammer-eye hammer-eye--right"></i><i class="hammer-mouth"></i>
        </span>
        <span class="hammer-buddy__neck" aria-hidden="true"></span>
        <span class="hammer-buddy__body" aria-hidden="true"><img src="<?= h($logoUrl) ?>" alt="" width="68" height="68"></span>
      </button>
      <div class="hammer-message">
        <span><?= h($greeting) ?></span>
        <strong data-hammer-message><?= h(sblog_t('内容已经替你整理好了。')) ?></strong>
      </div>
      <div class="hammer-stats" aria-label="<?= h(sblog_t('站点数据')) ?>">
        <div><strong><?= h((string)$postCount) ?></strong><span><?= h(sblog_t('文章')) ?></span></div>
        <div><strong><?= h((string)$tagCount) ?></strong><span><?= h(sblog_t('标签')) ?></span></div>
        <div><strong><?= h((string)$runningDays) ?></strong><span><?= h(sblog_t('天')) ?></span></div>
      </div>
      <p class="hammer-companion__quote"><?= h($tagline) ?></p>
    </aside>

    <main class="hammer-main">
      <?php if ($flash): ?><div class="hammer-flash hammer-flash--<?= h((string)$flash['type']) ?>" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
      <?php theme_action('content_before', $themeContext); ?>
      <?= $content ?>
      <?php theme_action('content_after', $themeContext); ?>
    </main>
  </div>

  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="hammer-footer">
    <div>
      <p><?= h(site_footer_text()) ?></p>
      <p>
        <?php $beian = trim(setting('footer_beian')); ?>
        <?php if ($beian !== ''): ?><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a><span> · </span><?php endif; ?>
        <a href="<?= h(url_for('rss')) ?>">RSS</a><span> · </span><a href="<?= h(url_for('sitemap')) ?>">Sitemap</a><span> · <?= h(sblog_t('HammerOS 锤伴')) ?></span>
      </p>
    </div>
    <span class="hammer-footer__seal" aria-hidden="true">H</span>
  </footer>
  <?php theme_action('footer_after', $themeContext); ?>

  <button class="hammer-backtop" type="button" aria-label="<?= h(sblog_t('回到顶部')) ?>" title="<?= h(sblog_t('回到顶部')) ?>"><span aria-hidden="true">↑</span></button>
  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
