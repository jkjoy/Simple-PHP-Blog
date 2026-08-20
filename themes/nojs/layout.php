<?php

declare(strict_types=1);

$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$beian = trim(setting('footer_beian'));
$totalPosts = count_published_posts();
$totalTags = count(tag_index_data());
$totalCategories = (int)val('SELECT COUNT(*) FROM categories');
$totalLinks = (int)val('SELECT COUNT(*) FROM links');
$totalViews = (int)val('SELECT COALESCE(SUM(views), 0) FROM posts WHERE status = ? AND published_at <= ?', ['published', time()]);
$oldest = (int)(val('SELECT MIN(published_at) FROM posts WHERE status = ? AND published_at > 0 AND published_at <= ?', ['published', time()]) ?: time());
$siteDays = max(1, (int)ceil((time() - $oldest) / 86400));
$scriptFile = __DIR__ . '/script.js';
?>
<!doctype html>
<html class="html" lang="<?= h(sblog_i18n_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <meta name="theme-color" content="#ffffff">
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?><?= $customHeadCode . "\n" ?><?php endif; ?>
</head>
<body class="body <?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <div class="content">
    <?php theme_action('header_before', $themeContext); ?>
    <header class="header">
      <div class="header__wrapper">
        <a href="<?= h(url_for('home')) ?>" class="brand"><?= h($siteName) ?></a>
        <span class="header__subtitle"><?= h(setting('site_tagline')) ?></span>
        <nav class="header__menu" aria-label="<?= h(sblog_t('主导航')) ?>">
          <ul class="header__list">
            <li class="header__list-item"><a href="<?= h(url_for('home')) ?>">首页</a></li>
            <li class="header__list-item"><a href="<?= h(url_for('archives')) ?>">归档<sup><?= h((string)$totalPosts) ?></sup></a></li>
            <li class="header__list-item"><a href="<?= h(url_for('tags')) ?>">标签<sup><?= h((string)$totalTags) ?></sup></a></li>
            <li class="header__list-item"><a href="<?= h(url_for('categories')) ?>">分类<sup><?= h((string)$totalCategories) ?></sup></a></li>
            <li class="header__list-item"><a href="<?= h(url_for('links')) ?>">好友<sup><?= h((string)$totalLinks) ?></sup></a></li>
            <?php foreach ($navPages as $page): ?><?php if (!in_array(strtolower((string)$page['slug']), ['archives', 'tags', 'categories', 'links'], true)): ?><li class="header__list-item"><a href="<?= h(content_permalink($page)) ?>"><?= h((string)$page['title']) ?></a></li><?php endif; ?><?php endforeach; ?>
            <li class="header__list-item"><a href="<?= h($admin ? url_for('admin') : url_for('login')) ?>"<?= $admin ? '' : ' target="_blank"' ?>><?= $admin ? '管理' : '登录' ?></a></li>
          </ul>
        </nav>
      </div>
    </header>
    <?php theme_action('header_after', $themeContext); ?>

    <?php if ($flash): ?><div class="nojs-notice" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
    <main id="main-content">
      <?php theme_action('content_before', $themeContext); ?>
      <?= $content ?>
      <?php theme_action('content_after', $themeContext); ?>
    </main>
    <div class="content__push"></div>
  </div>

  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="footer">
    <span class="footer__item">Copyright&nbsp;<?= h(date('Y')) ?>&nbsp;<?= h($siteName) ?>&nbsp; Powered by <a class="footer__link" href="https://github.com/jkjoy/Simple-PHP-Blog" target="_blank" rel="noopener noreferrer">SBlog</a> &amp; <a class="footer__link" href="https://github.com/jkjoy/typecho-theme-nojs" target="_blank" rel="noopener noreferrer">Nojs</a> <span>&nbsp;本站共计 <?= h((string)$totalViews) ?> 人浏览 运营时间至今有 <small><?= h((string)$siteDays) ?>天</small></span><?php if ($beian !== ''): ?> <a class="footer__link" href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a><?php endif; ?></span>
  </footer>
  <?php theme_action('footer_after', $themeContext); ?>
  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>"></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h(is_file($scriptFile) ? (string)filemtime($scriptFile) : '1.2.0') ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
