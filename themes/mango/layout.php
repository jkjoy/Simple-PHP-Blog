<?php

declare(strict_types=1);

$keywords = trim(setting('site_keywords'));
$customHeadCode = trim(setting('custom_head_code'));
$themeVersion = (string)($theme['version'] ?? '1.0.0');
$scriptFile = active_theme_file('script.js');
$scriptVersion = $scriptFile !== '' ? (string)filemtime($scriptFile) : $themeVersion;
$logoUrl = theme_logo_url();
$categories = fetch_categories();
$popularPosts = all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY views DESC, published_at DESC LIMIT 3', ['post', 'published', time()]);
$popularTags = array_slice(tag_index_data(), 0, 14);
$recentComments = all_rows("SELECT c.id, c.author_name, c.author_email, c.content, c.created_at, p.slug AS post_slug FROM comments c JOIN posts p ON p.id = c.post_id WHERE c.status = ? AND p.kind = ? AND p.status = ? AND p.published_at <= ? ORDER BY c.created_at DESC, c.id DESC LIMIT 5", ['approved', 'post', 'published', time()]);
$footerLinks = all_rows('SELECT name, url, description FROM links ORDER BY sort_order ASC, id DESC LIMIT 12');
$navItems = [
    ['label' => sblog_t('首页'), 'icon' => 'home', 'url' => url_for('home'), 'active' => $active === 'home'],
    ['label' => sblog_t('归档'), 'icon' => 'archive', 'url' => url_for('archives'), 'active' => $active === 'archives'],
    ['label' => sblog_t('标签'), 'icon' => 'tag', 'url' => url_for('tags'), 'active' => $active === 'tags'],
    ['label' => sblog_t('链接'), 'icon' => 'link', 'url' => url_for('links'), 'active' => $active === 'links'],
];
foreach ($navPages as $page) {
    $navItems[] = ['label' => (string)$page['title'], 'icon' => 'archive', 'url' => content_permalink($page), 'active' => $active === 'page:' . $page['slug']];
}
$homeLike = $active === 'home' && (string)($_GET['a'] ?? '') !== 'post';
?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <meta name="theme-color" content="#ffffff" data-mango-theme-color>
  <meta name="mango-like-url" content="<?= h(url_for('like_post')) ?>">
  <meta name="mango-csrf-token" content="<?= h(csrf_token()) ?>">
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <script>(function(){try{if(localStorage.getItem('mango-theme')==='dark')document.documentElement.classList.add('dark')}catch(e){}})();</script>
  <?= sblog_i18n_head() ?>
  <?php theme_action('head', $themeContext); ?>
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php theme_action('body_open', $themeContext); ?>
  <?php theme_action('header_before', $themeContext); ?>
  <header class="header">
    <div class="container top">
      <button class="mobile_an" type="button" aria-label="<?= h(sblog_t('打开菜单')) ?>" title="<?= h(sblog_t('打开菜单')) ?>" aria-expanded="false"><?= mango_icon('menu') ?></button>
      <div class="top_l">
        <a class="logo" href="<?= h(url_for('home')) ?>" title="<?= h($siteName) ?>"><span class="logo-mark"><img src="<?= h($logoUrl) ?>" alt=""></span><strong><?= h($siteName) ?></strong></a>
        <nav class="header-menu" aria-label="<?= h(sblog_t('主导航')) ?>">
          <ul><?php foreach ($navItems as $item): ?><li><a class="<?= $item['active'] ? 'current' : '' ?>" href="<?= h((string)$item['url']) ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>><?= mango_icon((string)$item['icon']) ?><span><?= h((string)$item['label']) ?></span></a></li><?php endforeach; ?></ul>
        </nav>
      </div>
      <div class="top_r">
        <button class="top_r_an theme-switch" type="button" aria-label="<?= h(sblog_t('切换外观')) ?>" title="<?= h(sblog_t('切换外观')) ?>" aria-pressed="false"><?= mango_icon('sun') ?></button>
        <button class="top_r_an search-toggle" type="button" aria-label="<?= h(sblog_t('搜索')) ?>" title="<?= h(sblog_t('搜索')) ?>" aria-expanded="false"><?= mango_icon('search') ?></button>
      </div>
    </div>
  </header>
  <?php theme_action('header_after', $themeContext); ?>

  <div class="search-drawer" aria-hidden="true">
    <div class="search-drawer-inner">
      <button class="drawer-close" type="button" aria-label="<?= h(sblog_t('关闭')) ?>" title="<?= h(sblog_t('关闭')) ?>"><?= mango_icon('close') ?></button>
      <form action="<?= h(url_for('home')) ?>" method="get" role="search">
        <?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="home"><?php endif; ?>
        <label><span class="sr-only"><?= h(sblog_t('搜索')) ?></span><input name="s" type="search" placeholder="<?= h(sblog_t('搜索')) ?>" value="<?= h((string)($_GET['s'] ?? '')) ?>" required></label>
        <button type="submit" aria-label="<?= h(sblog_t('搜索')) ?>"><?= mango_icon('search') ?></button>
      </form>
    </div>
  </div>

  <aside class="mobile_nav" aria-hidden="true">
    <div class="mobile_head"><a class="logo" href="<?= h(url_for('home')) ?>"><span class="logo-mark"><img src="<?= h($logoUrl) ?>" alt=""></span><strong><?= h($siteName) ?></strong></a><button class="mobile-close" type="button" aria-label="<?= h(sblog_t('关闭菜单')) ?>"><?= mango_icon('close') ?></button></div>
    <nav aria-label="<?= h(sblog_t('主导航')) ?>"><ul><?php foreach ($navItems as $item): ?><li><a class="<?= $item['active'] ? 'current' : '' ?>" href="<?= h((string)$item['url']) ?>"><?= mango_icon((string)$item['icon']) ?><span><?= h((string)$item['label']) ?></span></a></li><?php endforeach; ?></ul></nav>
    <?php if ($categories): ?><div class="mobile-categories"><h2><?= h(sblog_t('分类')) ?></h2><?php foreach ($categories as $category): ?><a href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"><?= h((string)$category['name']) ?><span><?= h((string)$category['post_count']) ?></span></a><?php endforeach; ?></div><?php endif; ?>
  </aside>
  <button class="mango-mask" type="button" tabindex="-1" aria-label="<?= h(sblog_t('关闭菜单')) ?>"></button>

  <?php if ($flash): ?><div class="mango-notice" role="status"><?= h((string)$flash['message']) ?></div><?php endif; ?>
  <?php if ($homeLike && !trim((string)($_GET['s'] ?? '')) && $popularPosts): $featured = $popularPosts[0]; ?>
    <section class="index_banner"><div class="container"><a href="<?= h(content_permalink($featured)) ?>"><img src="<?= h(mango_post_cover($featured)) ?>" alt="<?= h((string)$featured['title']) ?>" loading="eager" decoding="async"><span><?= h(sblog_t('推荐')) ?></span><h2><?= h((string)$featured['title']) ?></h2></a></div></section>
  <?php endif; ?>

  <section class="index_area">
    <div class="container mango-grid">
      <?php theme_action('content_before', $themeContext); ?>
      <?= $content ?>
      <?php theme_action('content_after', $themeContext); ?>

      <aside class="sidebar" aria-label="<?= h(sblog_t('侧边栏')) ?>">
        <section class="widget widget-search"><form action="<?= h(url_for('home')) ?>" method="get" role="search"><?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="home"><?php endif; ?><label><span class="sr-only"><?= h(sblog_t('搜索')) ?></span><input name="s" type="search" placeholder="<?= h(sblog_t('搜索')) ?>" value="<?= h((string)($_GET['s'] ?? '')) ?>"></label><button type="submit" aria-label="<?= h(sblog_t('搜索')) ?>"><?= mango_icon('search') ?></button></form></section>
        <?= mango_render_author_card() ?>
        <?php if ($popularPosts): ?><section class="widget"><h2 class="widget-title"><?= h(sblog_t('热门文章')) ?></h2><ul class="widget_hot_ul"><?php foreach ($popularPosts as $index => $post): ?><li class="<?= $index === 0 ? 'featured' : '' ?>"><img src="<?= h(mango_post_cover($post)) ?>" alt="" loading="lazy" decoding="async"><div><h3><a href="<?= h(content_permalink($post)) ?>"><?= h((string)$post['title']) ?></a></h3><p><?= h((string)approved_comment_count((int)$post['id'])) ?> <?= h(sblog_t('条留言')) ?></p></div></li><?php endforeach; ?></ul></section><?php endif; ?>
        <?php if ($popularTags): ?><section class="widget"><h2 class="widget-title"><?= h(sblog_t('热门标签')) ?></h2><div class="tagcloud"><?php foreach ($popularTags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><?= h((string)$tag['label']) ?></a><?php endforeach; ?></div></section><?php endif; ?>
        <?php if ($recentComments): ?><section class="widget"><h2 class="widget-title"><?= h(sblog_t('最近评论')) ?></h2><ul class="widget_comment_ul"><?php foreach ($recentComments as $comment): ?><li><img src="<?= h(gravatar_url((string)$comment['author_email'], 48)) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><div><a href="<?= h(url_for('post', ['slug' => (string)$comment['post_slug']])) ?>#comment-<?= h((string)$comment['id']) ?>"><?= h(derive_excerpt((string)$comment['content'], 42)) ?></a><span><?= h((string)$comment['author_name']) ?> · <?= h(mango_relative_date((int)$comment['created_at'])) ?></span></div></li><?php endforeach; ?></ul></section><?php endif; ?>
        <?= mango_render_site_stats() ?>
      </aside>
    </div>
  </section>

  <?php theme_action('footer_before', $themeContext); ?>
  <?php if ($footerLinks): ?><section class="links"><div class="container"><span><?= h(sblog_t('友情链接')) ?>：</span><?php foreach ($footerLinks as $link): ?><a href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer" title="<?= h((string)$link['description']) ?>"><?= h((string)$link['name']) ?></a><?php endforeach; ?></div></section><?php endif; ?>
  <footer class="footbox"><div class="container"><div>© <?= h(date('Y')) ?> <?= h($siteName) ?>. Powered by Simple PHP Blog.</div><a href="<?= h(url_for('rss')) ?>" aria-label="RSS" title="RSS"><?= mango_icon('rss') ?></a></div></footer>
  <?php theme_action('footer_after', $themeContext); ?>
  <button class="scrollToTopBtn" type="button" aria-label="<?= h(sblog_t('回到顶部')) ?>" title="<?= h(sblog_t('回到顶部')) ?>"><?= mango_icon('up') ?></button>
  <script src="<?= h(asset_url('assets/index.js')) ?>?v=<?= h(APP_VERSION) ?>" defer></script>
  <script src="<?= h(theme_asset_url('script.js')) ?>?v=<?= h($scriptVersion) ?>" defer></script>
  <?php theme_action('body_close', $themeContext); ?>
</body>
</html>
