<?php

declare(strict_types=1);

$isHome = butterfly_is_home($themeContext) && butterfly_query() === '';
$isSearch = butterfly_is_home($themeContext) && butterfly_query() !== '';
$post = butterfly_current_post();
$isPost = $post && (string)$post['kind'] === 'post';
$heroTitle = $isSearch ? sblog_t('搜索') . ': ' . butterfly_query() : $title;
$heroImage = $post ? butterfly_cover($post) : theme_asset_url('assets/hero.png');
$profile = one('SELECT username, nickname, avatar_url, website_url, github_url, signature FROM users ORDER BY id ASC LIMIT 1') ?? [];
$profileName = trim((string)($profile['nickname'] ?? '')) ?: (string)($profile['username'] ?? $siteName);
$profileAvatar = safe_link_url(trim((string)($profile['avatar_url'] ?? '')));
$profileAvatar = $profileAvatar !== '#' && $profileAvatar !== '' ? $profileAvatar : theme_logo_url();
$profileDescription = trim((string)($profile['signature'] ?? '')) ?: setting('site_tagline');
$categories = butterfly_categories();
$tags = tag_index_data();
$recentPosts = all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC LIMIT 5', ['post', 'published', time()]);
$recentComments = all_rows('SELECT c.id, c.author_name, c.author_email, c.content, c.created_at, p.kind AS post_kind, p.slug AS post_slug FROM comments c JOIN posts p ON p.id = c.post_id WHERE c.status = ? AND p.status = ? AND p.published_at <= ? ORDER BY c.created_at DESC, c.id DESC LIMIT 6', ['approved', 'published', time()]);
$siteStats = one('SELECT COUNT(*) AS total, COALESCE(SUM(views), 0) AS views, MIN(published_at) AS first_post, MAX(updated_at) AS last_update FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['post', 'published', time()]) ?? [];
$navItems = [
    ['home', 'ri-home-4-line', sblog_t('首页')], ['archives', 'ri-archive-line', sblog_t('归档')],
    ['tags', 'ri-price-tag-3-line', sblog_t('标签')], ['categories', 'ri-folder-2-line', sblog_t('分类')],
    ['links', 'ri-links-line', sblog_t('友链')],
];
$version = (string)filemtime(__DIR__ . '/script.js');
$sourceStyleVersion = (string)filemtime(__DIR__ . '/source-style.css');
$compatStyleVersion = (string)filemtime(__DIR__ . '/compat.css');
$action = (string)($_GET['a'] ?? '');
$isError = (int)($themeContext['options']['status'] ?? 200) >= 400;
$headerClass = $isHome ? 'full_page' : ($isPost ? 'post-bg' : ($isError ? 'not-top-img' : 'not-home-page'));
$headerStyle = $headerClass === 'not-top-img' ? '' : ' style="background-image:url(' . h($heroImage) . ')"';
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if (trim(setting('site_keywords')) !== ''): ?><meta name="keywords" content="<?= h(setting('site_keywords')) ?>"><?php endif; ?>
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#49b1f5">
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <link rel="alternate" type="application/rss+xml" title="<?= h($siteName) ?>" href="<?= h(url_for('rss')) ?>">
  <link rel="stylesheet" href="<?= h(theme_asset_url('assets/icons.css')) ?>?v=1">
  <script>(function(){var t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';try{var s=localStorage.getItem('butterfly-theme');if(s==='dark'||s==='light')t=s;}catch(e){}document.documentElement.dataset.theme=t;})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <link rel="stylesheet" href="<?= h(theme_asset_url('source-style.css')) ?>?v=<?= h($sourceStyleVersion) ?>">
  <link rel="stylesheet" href="<?= h(theme_asset_url('compat.css')) ?>?v=<?= h($compatStyleVersion) ?>">
  <?php if (trim(setting('custom_head_code')) !== ''): ?><?= setting('custom_head_code') ?><?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <a class="bf-skip-link" href="#content-inner"><?= h(sblog_t('跳转到正文')) ?></a>
  <div id="web_bg"></div>
  <div class="page" id="body-wrap">
    <?php theme_action('header_before', $themeContext); ?>
    <header id="page-header" class="<?= h($headerClass) ?>"<?= $headerStyle ?>>
      <?php if ($isHome): ?>
        <div id="site-info"><h1 id="site-title"><?= h($siteName) ?></h1><div id="site-subtitle"><?= h((string)setting('site_tagline')) ?></div></div>
        <div id="scroll-down"><a href="#content-inner" aria-label="<?= h(sblog_t('最新文章')) ?>"><i class="ri-arrow-down-s-line scroll-down-effects" aria-hidden="true"></i></a></div>
      <?php elseif ($isPost): ?>
        <div id="post-info">
          <h1 class="post-title"><?= h((string)$post['title']) ?></h1>
          <div id="post-meta">
            <div class="meta-firstline"><span class="post-meta-date"><i class="ri-calendar-line fa-fw post-meta-icon" aria-hidden="true"></i><span class="post-meta-label"><?= h(sblog_t('发表于')) ?></span><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('Y-m-d', (int)$post['published_at'])) ?></time></span><span class="post-meta-date"><span class="post-meta-separator">|</span><i class="ri-history-line fa-fw post-meta-icon" aria-hidden="true"></i><span class="post-meta-label"><?= h(sblog_t('更新于')) ?></span><time datetime="<?= h(date(DATE_ATOM, (int)$post['updated_at'])) ?>"><?= h(date('Y-m-d', (int)$post['updated_at'])) ?></time></span><?php foreach ($categories as $category): if ((int)$category['id'] !== (int)($post['category_id'] ?? 0)) { continue; } ?><span class="post-meta-categories"><span class="post-meta-separator">|</span><i class="ri-inbox-line fa-fw post-meta-icon" aria-hidden="true"></i><a href="<?= h(butterfly_url(url_for('category', ['slug' => (string)$category['slug']]))) ?>"><?= h((string)$category['name']) ?></a></span><?php endforeach; ?></div>
            <?php $words = str_len_u(preg_replace('/\s+/u', '', markdown_to_plain((string)$post['content'])) ?? ''); ?><div class="meta-secondline"><span class="post-meta-wordcount"><i class="ri-file-word-line fa-fw post-meta-icon" aria-hidden="true"></i><span class="post-meta-label"><?= h(sblog_t('字数总计')) ?>:</span><span class="word-count"><?= $words ?></span><span class="post-meta-separator">|</span><i class="ri-time-line fa-fw post-meta-icon" aria-hidden="true"></i><span class="post-meta-label"><?= h(sblog_t('阅读时长')) ?>:</span><span><?= max(1, (int)ceil($words / 400)) ?> <?= h(sblog_t('分钟')) ?></span><span class="post-meta-separator">|</span><span class="post-meta-pv-cv"><i class="ri-eye-line fa-fw post-meta-icon" aria-hidden="true"></i><span class="post-meta-label"><?= h(sblog_t('阅读量')) ?>:</span><span><?= (int)$post['views'] ?></span></span></span></div>
          </div>
        </div>
      <?php elseif (!$isError): ?><div id="page-site-info"><h1 id="site-title"><?= h($heroTitle) ?></h1></div><?php endif; ?>
      <nav id="nav" class="show" aria-label="<?= h(sblog_t('主菜单')) ?>">
        <span id="blog-info"><a href="<?= h(butterfly_url(url_for('home'))) ?>"><span class="site-name"><?= h($siteName) ?></span></a></span>
        <div id="menus"><div id="search-button"><button class="site-page social-icon search" type="button" data-bf-search-open aria-label="<?= h(sblog_t('搜索')) ?>" title="<?= h(sblog_t('搜索')) ?>"><i class="ri-search-line fa-fw" aria-hidden="true"></i><span><?= h(sblog_t('搜索')) ?></span></button></div><div id="toggle-menu"><button class="site-page" id="bf-menu-toggle" type="button" aria-controls="sidebar-menus" aria-expanded="false" aria-label="<?= h(sblog_t('打开菜单')) ?>" title="<?= h(sblog_t('打开菜单')) ?>"><i class="ri-menu-line fa-fw" aria-hidden="true"></i></button></div><div class="menus_items" id="bf-menu">
          <?php foreach ($navItems as [$route, $icon, $label]): ?><div class="menus_item"><a class="site-page" href="<?= h(butterfly_url(url_for($route))) ?>"<?= $active === $route && !$isSearch ? ' aria-current="page"' : '' ?>><i class="<?= h($icon) ?> fa-fw" aria-hidden="true"></i><span><?= h($label) ?></span></a></div><?php endforeach; ?>
          <?php foreach ($navPages as $navPage): ?><div class="menus_item"><a class="site-page" href="<?= h(butterfly_url(content_permalink($navPage))) ?>"<?= $active === 'page:' . $navPage['slug'] ? ' aria-current="page"' : '' ?>><i class="ri-article-line fa-fw" aria-hidden="true"></i><span><?= h((string)$navPage['title']) ?></span></a></div><?php endforeach; ?>
          <?php if ($admin): ?><div class="menus_item"><a class="site-page" href="<?= h(url_for('admin')) ?>"><i class="ri-user-line fa-fw" aria-hidden="true"></i><span><?= h(sblog_t('管理')) ?></span></a></div><?php endif; ?>
        </div></div>
      </nav>
    </header>
    <?php theme_action('header_after', $themeContext); ?>

    <main class="layout<?= $flash ? ' has-flash' : '' ?>" id="content-inner">
      <?php if ($flash): ?><section class="bf-notice" role="status"><?= h((string)$flash['message']) ?></section><?php endif; ?>
      <?php theme_action('content_before', $themeContext); ?>
      <?php if ($isPost): ?>
        <div id="post"><article class="post-content" id="article-container"><?= markdown_to_html((string)$post['content']) ?></article><div class="tag_share"><div class="post-meta__tag-list"><?= render_tag_chips($post) ?></div></div><?= render_comments_section($post) ?></div>
      <?php elseif ($post): ?><div id="page"><article class="post-content" id="article-container"><?= markdown_to_html((string)$post['content']) ?></article><?= render_comments_section($post) ?></div>
      <?php elseif ($isHome || $isSearch): ?><div class="recent-posts<?= $isSearch ? ' search' : '' ?>" id="recent-posts"><?= $content ?></div>
      <?php elseif (in_array($action, ['archives', 'tag', 'category'], true)): ?><?= $content ?>
      <?php elseif (in_array($action, ['tags', 'categories'], true)): ?><div id="page"><?= $content ?></div>
      <?php elseif ($action === 'links'): ?><div id="page"><div id="article-container"><?= $content ?></div></div>
      <?php else: ?><div id="page"><article class="post-content" id="article-container"><?= $content ?></article></div><?php endif; ?>
      <?php theme_action('content_after', $themeContext); ?>

      <aside class="aside-content" id="aside-content" role="complementary" aria-label="<?= h(sblog_t('侧栏')) ?>">
        <div class="card-widget card-info"><div class="card-info-avatar is-center"><div class="avatar-img"><img src="<?= h($profileAvatar) ?>" data-bf-fallback="<?= h(theme_logo_url()) ?>" width="110" height="110" alt="<?= h($profileName) ?>" loading="lazy"></div><div class="author-info__name"><?= h($profileName) ?></div><?php if ($profileDescription !== ''): ?><div class="author-info__description"><?= h($profileDescription) ?></div><?php endif; ?></div><div class="card-info-data site-data is-center"><a href="<?= h(butterfly_url(url_for('archives'))) ?>"><div class="headline"><?= h(sblog_t('文章')) ?></div><div class="length-num"><?= (int)($siteStats['total'] ?? 0) ?></div></a><a href="<?= h(butterfly_url(url_for('tags'))) ?>"><div class="headline"><?= h(sblog_t('标签')) ?></div><div class="length-num"><?= count($tags) ?></div></a><a href="<?= h(butterfly_url(url_for('categories'))) ?>"><div class="headline"><?= h(sblog_t('分类')) ?></div><div class="length-num"><?= count($categories) ?></div></a></div><a class="button--animated" id="card-info-btn" href="<?= h(url_for('rss')) ?>"><i class="ri-rss-line" aria-hidden="true"></i><span><?= h(sblog_t('订阅 RSS')) ?></span></a></div>
        <?php if (setting('site_description') !== ''): ?><div class="card-widget card-announcement"><div class="item-headline"><i class="ri-megaphone-line" aria-hidden="true"></i><span><?= h(sblog_t('公告')) ?></span></div><div class="announcement_content"><?= h(setting('site_description')) ?></div></div><?php endif; ?>
        <div class="sticky_layout">
          <?php if ($post): ?><div class="card-widget card-toc" id="card-toc" hidden><div class="item-headline"><i class="ri-list-check" aria-hidden="true"></i><span><?= h(sblog_t('目录')) ?></span><span class="toc-percentage" id="bf-progress">0%</span></div><div class="toc-content"><ol class="toc" id="bf-toc"></ol></div></div><?php endif; ?>
          <?php if ($recentPosts): ?><div class="card-widget card-recent-post"><div class="item-headline"><i class="ri-history-line" aria-hidden="true"></i><span><?= h(sblog_t('最新文章')) ?></span></div><div class="aside-list"><?php foreach ($recentPosts as $recent): ?><div class="aside-list-item"><a class="thumbnail" href="<?= h(butterfly_url(content_permalink($recent))) ?>"><img src="<?= h(butterfly_cover($recent)) ?>" data-bf-fallback="<?= h(theme_asset_url('assets/default-cover.jpg')) ?>" width="64" height="64" alt="" loading="lazy"></a><div class="content"><a class="title" href="<?= h(butterfly_url(content_permalink($recent))) ?>"><?= h((string)$recent['title']) ?></a><time datetime="<?= h(date(DATE_ATOM, (int)$recent['published_at'])) ?>"><?= h(date('Y-m-d', (int)$recent['published_at'])) ?></time></div></div><?php endforeach; ?></div></div><?php endif; ?>
          <?php if ($recentComments): ?><div class="card-widget" id="card-newest-comments"><div class="item-headline"><i class="ri-chat-3-line" aria-hidden="true"></i><span><?= h(sblog_t('最近评论')) ?></span></div><div class="aside-list"><?php foreach ($recentComments as $comment): ?><?php $commentUrl = butterfly_url(content_permalink(['kind' => (string)$comment['post_kind'], 'slug' => (string)$comment['post_slug']])) . '#comment-' . (int)$comment['id']; ?><div class="aside-list-item"><a class="thumbnail" href="<?= h($commentUrl) ?>"><img src="<?= h(gravatar_url((string)$comment['author_email'], 64)) ?>" width="64" height="64" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"></a><div class="content"><a class="comment" href="<?= h($commentUrl) ?>"><?= h(comment_excerpt((string)$comment['content'], 35)) ?></a><div class="name"><span title="<?= h(pretty_date((int)$comment['created_at'], true)) ?>"><?= h((string)$comment['author_name']) ?> / <?= h(pretty_date((int)$comment['created_at'])) ?></span></div></div></div><?php endforeach; ?></div></div><?php endif; ?>
          <?php if ($categories): ?><div class="card-widget card-categories"><div class="item-headline"><i class="ri-folder-2-line" aria-hidden="true"></i><span><?= h(sblog_t('分类')) ?></span></div><ul class="card-category-list"><?php foreach (array_slice($categories, 0, 8) as $category): ?><li class="card-category-list-item"><a href="<?= h(butterfly_url(url_for('category', ['slug' => (string)$category['slug']]))) ?>"><span class="card-category-list-name"><?= h((string)$category['name']) ?></span><span class="card-category-list-count"><?= (int)$category['post_count'] ?></span></a></li><?php endforeach; ?></ul></div><?php endif; ?>
          <?php if ($tags): ?><div class="card-widget card-tags"><div class="item-headline"><i class="ri-price-tag-3-line" aria-hidden="true"></i><span><?= h(sblog_t('标签')) ?></span></div><div class="card-tag-cloud"><?= butterfly_tags(array_slice($tags, 0, 30)) ?></div></div><?php endif; ?>
          <div class="card-widget card-webinfo"><div class="item-headline"><i class="ri-line-chart-line" aria-hidden="true"></i><span><?= h(sblog_t('网站资讯')) ?></span></div><div class="webinfo"><div class="webinfo-item"><div class="item-name"><?= h(sblog_t('文章数目')) ?></div><div class="item-count"><?= (int)($siteStats['total'] ?? 0) ?></div></div><div class="webinfo-item"><div class="item-name"><?= h(sblog_t('文章浏览')) ?></div><div class="item-count"><?= (int)($siteStats['views'] ?? 0) ?></div></div><?php if (!empty($siteStats['first_post'])): ?><div class="webinfo-item"><div class="item-name"><?= h(sblog_t('运行天数')) ?></div><div class="item-count"><?= max(1, (int)floor((time() - (int)$siteStats['first_post']) / 86400)) ?></div></div><?php endif; ?><div class="webinfo-item"><div class="item-name"><?= h(sblog_t('最后更新')) ?></div><div class="item-count"><?= !empty($siteStats['last_update']) ? h(date('Y-m-d', (int)$siteStats['last_update'])) : '-' ?></div></div></div></div>
        </div>
      </aside>
    </main>
    <?php theme_action('footer_before', $themeContext); ?><footer id="footer"><div id="footer-wrap"><div class="copyright"><div><?= h(site_footer_text()) ?></div><div class="framework-info"><span><?= h(sblog_t('由')) ?></span> <a href="https://github.com/jkjoy/Simple-PHP-Blog" target="_blank" rel="noopener noreferrer">Simple PHP Blog</a><span> &amp; </span><a href="https://github.com/wehaox/Typecho-Butterfly" target="_blank" rel="noopener noreferrer">Butterfly</a></div><?php if (trim(setting('footer_beian')) !== ''): ?><div class="footer_custom_text"><a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h(setting('footer_beian')) ?></a></div><?php endif; ?></div></div></footer><?php theme_action('footer_after', $themeContext); ?>
  </div>
  <div id="sidebar"><div id="menu-mask" style="display:none"></div><div id="sidebar-menus" aria-hidden="true">
    <div class="avatar-img is-center"><img src="<?= h($profileAvatar) ?>" data-bf-fallback="<?= h(theme_logo_url()) ?>" width="110" height="110" alt="<?= h($profileName) ?>" loading="lazy"></div>
    <div class="sidebar-site-data"><div class="site-data is-center"><a href="<?= h(butterfly_url(url_for('archives'))) ?>"><div class="headline"><?= h(sblog_t('文章')) ?></div><div class="length-num"><?= (int)($siteStats['total'] ?? 0) ?></div></a><a href="<?= h(butterfly_url(url_for('tags'))) ?>"><div class="headline"><?= h(sblog_t('标签')) ?></div><div class="length-num"><?= count($tags) ?></div></a><a href="<?= h(butterfly_url(url_for('categories'))) ?>"><div class="headline"><?= h(sblog_t('分类')) ?></div><div class="length-num"><?= count($categories) ?></div></a></div></div>
    <hr>
    <div class="menus_items">
      <?php foreach ($navItems as [$route, $icon, $label]): ?><div class="menus_item"><a class="site-page" href="<?= h(butterfly_url(url_for($route))) ?>"<?= $active === $route && !$isSearch ? ' aria-current="page"' : '' ?>><i class="<?= h($icon) ?> fa-fw" aria-hidden="true"></i><span><?= h($label) ?></span></a></div><?php endforeach; ?>
      <?php foreach ($navPages as $navPage): ?><div class="menus_item"><a class="site-page" href="<?= h(butterfly_url(content_permalink($navPage))) ?>"<?= $active === 'page:' . $navPage['slug'] ? ' aria-current="page"' : '' ?>><i class="ri-article-line fa-fw" aria-hidden="true"></i><span><?= h((string)$navPage['title']) ?></span></a></div><?php endforeach; ?>
      <?php if ($admin): ?><div class="menus_item"><a class="site-page" href="<?= h(url_for('admin')) ?>"><i class="ri-user-line fa-fw" aria-hidden="true"></i><span><?= h(sblog_t('管理')) ?></span></a></div><?php endif; ?>
    </div>
  </div></div>
  <div id="rightside"><div id="rightside-config-hide"><?php if ($post): ?><button id="readmode" type="button" data-bf-read-toggle title="<?= h(sblog_t('阅读模式')) ?>" aria-label="<?= h(sblog_t('阅读模式')) ?>"><i class="ri-book-open-line" aria-hidden="true"></i></button><button id="mobile-toc-button" type="button" data-bf-toc-toggle title="<?= h(sblog_t('文章目录')) ?>" aria-label="<?= h(sblog_t('文章目录')) ?>" hidden><i class="ri-list-check" aria-hidden="true"></i></button><?php endif; ?><button id="darkmode" type="button" data-bf-theme-toggle title="<?= h(sblog_t('切换明暗模式')) ?>" aria-label="<?= h(sblog_t('切换明暗模式')) ?>" aria-pressed="false"><i class="ri-moon-line" aria-hidden="true"></i></button><button id="hide-aside-btn" type="button" data-bf-aside-toggle title="<?= h(sblog_t('切换侧栏')) ?>" aria-label="<?= h(sblog_t('切换侧栏')) ?>" aria-pressed="false"><i class="ri-layout-right-line" aria-hidden="true"></i></button></div><div id="rightside-config-show"><button id="rightside_config" type="button" title="<?= h(sblog_t('设置')) ?>" aria-label="<?= h(sblog_t('设置')) ?>"><i class="ri-settings-3-line" aria-hidden="true"></i></button><button id="go-up" type="button" data-bf-top title="<?= h(sblog_t('返回顶部')) ?>" aria-label="<?= h(sblog_t('返回顶部')) ?>" class="show-percent"><span class="scroll-percent"></span><i class="ri-arrow-up-line" aria-hidden="true"></i></button></div></div>
  <div id="local-search"><dialog id="bf-search-dialog" class="search-dialog bf-dialog" aria-labelledby="bf-search-title"><div class="search-nav"><span class="search-dialog-title" id="bf-search-title"><?= h(sblog_t('搜索')) ?></span><button class="search-close-button" type="button" data-bf-dialog-close aria-label="<?= h(sblog_t('关闭')) ?>"><i class="ri-close-line" aria-hidden="true"></i></button></div><div class="search-wrap" style="display:block"><?= butterfly_search_form('bf-dialog-search') ?></div></dialog></div>
  <dialog id="bf-lightbox" class="bf-lightbox" aria-label="<?= h(sblog_t('图片预览')) ?>"><button class="bf-icon-button" type="button" data-bf-dialog-close aria-label="<?= h(sblog_t('关闭')) ?>"><i class="ri-close-line" aria-hidden="true"></i></button><img alt=""></dialog>
  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script><script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($version) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
