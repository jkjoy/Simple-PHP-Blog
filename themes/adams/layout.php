<?php

declare(strict_types=1);

$owner = one('SELECT github_url, qq_url, wechat_url, weibo_url, x_url, telegram_url, mastodon_url, bilibili_url, instagram_url, tiktok_url FROM users ORDER BY id ASC LIMIT 1') ?? [];
$socialLinks = [];

$adamsSocialIcons = [
    'github' => 'czs-github',
    'qq' => 'czs-qq',
    'wechat' => 'czs-weixin',
    'weibo' => 'czs-weibo',
    'x' => 'czs-twitter',
    'telegram' => 'czs-telegram',
    'mastodon' => 'czs-network',
    'bilibili' => 'czs-bilibili',
    'instagram' => 'czs-camera',
    'tiktok' => 'czs-music-note',
];
foreach (social_profile_definitions() as $key => $definition) {
    $socialUrl = safe_link_url((string)($owner[$definition['column']] ?? ''));
    if ($socialUrl !== '#') {
        $socialLinks[] = [
            'url' => $socialUrl,
            'label' => (string)$definition['label'],
            'icon' => $adamsSocialIcons[$key] ?? 'czs-earth',
        ];
    }
}

$action = (string)($_GET['a'] ?? '');
$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$tagline = trim(setting('site_tagline')) ?: trim(setting('site_description'));
$viewClass = match (true) {
    $action === 'archives' || $active === 'archives' => 'adams-view-archives',
    $action === 'tag' => 'adams-view-tag',
    $active === 'tags' => 'adams-view-tags',
    $active === 'links' => 'adams-view-links',
    $action === 'category' => 'adams-view-category',
    str_starts_with($active, 'page:') => 'adams-view-page',
    $active === 'home' && $title === $siteName => 'adams-view-home',
    default => 'adams-view-post',
};

$currentPost = null;
if ($action === 'post') {
    $currentPost = fetch_post_by_identifier((string)($_GET['slug'] ?? ''), true);
} elseif ($action === 'page') {
    $currentPost = fetch_page_by_identifier((string)($_GET['slug'] ?? ''), true);
} elseif (str_starts_with($active, 'page:')) {
    $currentPost = fetch_page_by_identifier(substr($active, 5), true);
}
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <link rel="stylesheet" href="<?= h(theme_asset_url('static/caomei/style.css')) ?>?v=<?= h($themeVersion) ?>">
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h(trim($bodyClass . ' ' . $viewClass)) ?>">
  <script>(function(){try{var c=localStorage.adams_color_style||'';var f=localStorage.adams_font_style||'';if(c)document.body.classList.add(c);if(f)document.body.classList.add(f)}catch(e){}})();</script>
  <?php theme_action('body_open', $themeContext); ?>

  <?php theme_action('header_before', $themeContext); ?>
  <header class="header">
    <section class="container">
      <hgroup itemscope itemtype="https://schema.org/WPHeader">
        <h1 class="fullname"><a href="<?= h(url_for('home')) ?>"><?= h($title) ?></a></h1>
      </hgroup>

      <nav class="social" aria-label="<?= h(sblog_t('社交链接')) ?>">
        <ul class="menu">
          <?php foreach ($socialLinks as $social): ?>
            <li class="<?= h((string)$social['icon']) ?>"><a href="<?= h((string)$social['url']) ?>" target="_blank" rel="me noopener noreferrer" aria-label="<?= h((string)$social['label']) ?>" title="<?= h((string)$social['label']) ?>"></a></li>
          <?php endforeach; ?>
          <li class="czs-rss"><a href="<?= h(url_for('rss')) ?>" aria-label="RSS" title="RSS"></a></li>
          <?php if ($admin): ?><li class="czs-setting"><a href="<?= h(url_for('admin')) ?>" aria-label="<?= h(sblog_t('管理')) ?>" title="<?= h(sblog_t('管理')) ?>"></a></li><?php endif; ?>
        </ul>
      </nav>

      <nav class="header_nav" aria-label="<?= h(sblog_t('主导航')) ?>">
        <ul class="menu">
          <li class="<?= $active === 'home' && $action !== 'category' ? 'current-menu-item' : '' ?>"><a href="<?= h(url_for('home')) ?>"><?= h(sblog_t('首页')) ?></a></li>
          <li class="<?= $active === 'archives' ? 'current-menu-item' : '' ?>"><a href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('归档')) ?></a></li>
          <li class="<?= $active === 'tags' ? 'current-menu-item' : '' ?>"><a href="<?= h(url_for('tags')) ?>"><?= h(sblog_t('标签')) ?></a></li>
          <li class="<?= $active === 'links' ? 'current-menu-item' : '' ?>"><a href="<?= h(url_for('links')) ?>"><?= h(sblog_t('友链')) ?></a></li>
          <?php foreach ($navPages as $page): ?>
            <li class="<?= $active === 'page:' . $page['slug'] ? 'current-menu-item' : '' ?>"><a href="<?= h(content_permalink($page)) ?>"><?= h((string)$page['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
    </section>

    <section class="infos" data-adams-infos>
      <div class="container">
        <?php if ($currentPost): ?>
          <?php
          $displayTime = (int)($currentPost['published_at'] ?: $currentPost['updated_at'] ?: $currentPost['created_at']);
          $commentCount = approved_comment_count((int)$currentPost['id']);
          ?>
          <h2 class="fixed-title"></h2>
          <div class="fields">
            <span><i class="czs-time-l"></i> <time datetime="<?= h(date(DATE_ATOM, $displayTime)) ?>" title="<?= h(date(DATE_ATOM, $displayTime)) ?>"><?= h(date('Y-m-d', $displayTime)) ?></time></span> /
            <a href="#comments"><i class="czs-talk-l"></i> <?= h((string)$commentCount) ?>评</a> /
            <span><i class="czs-analysis"></i> <?= h((string)(int)($currentPost['views'] ?? 0)) ?>阅</span>
          </div>
          <div class="socials">
            <div class="share">
              <a href="javascript:void(0)" aria-label="<?= h(sblog_t('分享')) ?>"><i class="czs-scan-l s"></i><i class="czs-qrcode-l h"></i> 码</a>
              <div class="qrcode"><div class="img-box"><img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&amp;margin=10&amp;data=<?= h(rawurlencode(absolute_url(content_permalink($currentPost)))) ?>" width="120" height="120" alt=""></div><i><?= h(sblog_t('移动设备上继续阅读')) ?></i></div>
            </div>
          </div>
        <?php else: ?>
          <h2 class="fixed-title"></h2>
          <div class="fixed-menus"></div>
          <div class="placard"><?= h($tagline !== '' ? $tagline : $siteName) ?></div>
        <?php endif; ?>
      </div>
    </section>
  </header>
  <?php theme_action('header_after', $themeContext); ?>

  <div class="site-content">
    <?php if ($flash): ?><div class="container"><div class="butterBar butterBar--center" role="status"><?= h((string)$flash['message']) ?></div></div><?php endif; ?>
    <?php theme_action('content_before', $themeContext); ?>
    <?= $content ?>
    <?php theme_action('content_after', $themeContext); ?>
  </div>

  <?php theme_action('footer_before', $themeContext); ?>
  <footer class="footer">
    <section class="container">
      <ul class="menu">
        <li><a href="<?= h(url_for('rss')) ?>">RSS</a></li>
        <li><a href="<?= h(url_for('sitemap')) ?>">Sitemap</a></li>
      </ul>
      <div class="footer-row">
        <div class="left">
          <span>&copy; <?= h(date('Y')) ?> <a href="<?= h(url_for('home')) ?>"><?= h($siteName) ?></a></span>
          <?php $beian = trim(setting('footer_beian')); ?>
          <?php if ($beian !== ''): ?><span> · <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a></span><?php endif; ?>
        </div>
        <div class="right"><span>Theme by <a href="https://github.com/Tokinx/Adams" target="_blank" rel="noopener noreferrer">Adams</a></span></div>
      </div>
    </section>
  </footer>
  <?php theme_action('footer_after', $themeContext); ?>

  <div class="setting_tool iconfont">
    <a class="back2top" style="display:none" role="button" tabindex="0" aria-label="<?= h(sblog_t('回到顶部')) ?>" title="<?= h(sblog_t('回到顶部')) ?>"><i class="czs-arrow-up-l"></i></a>
    <?php if ($active !== 'home' || $action === 'category'): ?><a class="home" href="<?= h(url_for('home')) ?>" aria-label="<?= h(sblog_t('首页')) ?>" title="<?= h(sblog_t('首页')) ?>"><i class="czs-home-l"></i></a><?php endif; ?>
    <a class="sosearch" role="button" tabindex="0" aria-label="<?= h(sblog_t('搜索')) ?>" title="<?= h(sblog_t('搜索')) ?>"><i class="czs-search-l"></i></a>
    <a class="socolor" role="button" tabindex="0" aria-label="<?= h(sblog_t('阅读设置')) ?>" title="<?= h(sblog_t('阅读设置')) ?>"><i class="czs-clothes-l"></i></a>
    <div class="s">
      <form method="get" action="<?= h(url_for('home')) ?>" class="search">
        <?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="home"><?php endif; ?>
        <input class="search-key" name="s" autocomplete="off" placeholder="<?= h(sblog_t('输入关键词...')) ?>" type="search" value="<?= h((string)($_GET['s'] ?? '')) ?>" required>
      </form>
    </div>
    <div class="c">
      <ul>
        <li class="color undefined" data-adams-color="default">默认</li>
        <li class="color sepia" data-adams-color="sepia">护眼</li>
        <li class="color night" data-adams-color="night">夜晚</li>
        <li class="hr"></li>
        <li class="font serif" data-adams-font="serif">Serif</li>
        <li class="font sans" data-adams-font="sans">Sans</li>
      </ul>
    </div>
  </div>

  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($themeVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
