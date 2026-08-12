<?php

declare(strict_types=1);

function timellow_icon(string $name): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.8-3.8"></path>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"></path>',
        'message' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['search']) . '</svg>';
}

function timellow_sns_icon(string $type): string
{
    $paths = [
        'mastodon' => '<path d="M5 19c-2-3-2-10 0-13 2-3 12-3 14 0 1.5 2 1.5 8 0 10-1.4 2-5 2.5-8 2"></path><path d="M8 15V9c0-2 3-2 4 0 1-2 4-2 4 0v6"></path>',
        'telegram' => '<path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path>',
        'x' => '<path class="sns-icon-fill" d="M18.9 2H22l-6.77 7.74L23.2 22h-6.24l-4.89-6.39L6.48 22H3.36l7.26-8.3L2.98 2h6.4l4.42 5.84L18.9 2Zm-1.1 17.84h1.72L8.44 4.05H6.6L17.8 19.84Z"></path>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.5 6.5h.01"></path>',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path>',
        'rss' => '<path d="M5 11a8 8 0 0 1 8 8M5 5a14 14 0 0 1 14 14"></path><circle class="sns-icon-fill" cx="5" cy="19" r="1.5"></circle>',
        'sitemap' => '<rect x="9" y="3" width="6" height="5" rx="1"></rect><rect x="3" y="16" width="6" height="5" rx="1"></rect><rect x="15" y="16" width="6" height="5" rx="1"></rect><path d="M12 8v4M6 16v-4h12v4"></path>',
    ];
    $path = $paths[$type] ?? '';
    return $path === '' ? '' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

function timellow_sns_links(): array
{
    $owner = one('SELECT email, mastodon_url, telegram_url, x_url, instagram_url FROM users ORDER BY id ASC LIMIT 1') ?? [];
    $links = [];
    $profiles = [
        ['type' => 'mastodon', 'label' => 'Mastodon', 'column' => 'mastodon_url', 'rel' => 'me noopener noreferrer'],
        ['type' => 'telegram', 'label' => 'Telegram', 'column' => 'telegram_url', 'rel' => 'noopener noreferrer'],
        ['type' => 'x', 'label' => 'X', 'column' => 'x_url', 'rel' => 'noopener noreferrer'],
        ['type' => 'instagram', 'label' => 'Instagram', 'column' => 'instagram_url', 'rel' => 'noopener noreferrer'],
    ];
    foreach ($profiles as $profile) {
        $url = safe_link_url((string)($owner[$profile['column']] ?? ''));
        if ($url !== '#') {
            $links[] = $profile + ['url' => $url, 'target' => '_blank'];
        }
    }

    $email = trim((string)($owner['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $links[] = ['type' => 'email', 'label' => 'Email', 'url' => 'mailto:' . $email, 'target' => '', 'rel' => ''];
    }
    $links[] = ['type' => 'rss', 'label' => 'RSS', 'url' => url_for('rss'), 'target' => '', 'rel' => ''];
    $links[] = ['type' => 'sitemap', 'label' => sblog_t('网站地图'), 'url' => url_for('sitemap'), 'target' => '', 'rel' => ''];
    return $links;
}

function timellow_render_sns_links(): string
{
    ob_start();
    ?>
    <nav class="sns-links" aria-label="<?= h(sblog_t('社交链接')) ?>">
      <?php foreach (timellow_sns_links() as $link): ?>
        <a class="sns-link sns-link-<?= h((string)$link['type']) ?>" href="<?= h((string)$link['url']) ?>"<?= $link['target'] !== '' ? ' target="' . h((string)$link['target']) . '"' : '' ?><?= $link['rel'] !== '' ? ' rel="' . h((string)$link['rel']) . '"' : '' ?> aria-label="<?= h((string)$link['label']) ?>" title="<?= h((string)$link['label']) ?>"><?= timellow_sns_icon((string)$link['type']) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function timellow_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        if ($url !== '#') {
            return $url;
        }
    }

    $pool = ['1.jpg', '2.jpg', '3.jpg', '4.jpg'];
    return theme_asset_url('assets/cover/' . $pool[abs((int)($post['id'] ?? 0)) % count($pool)]);
}

function timellow_first_character(string $text): string
{
    $text = trim($text);
    return $text === '' ? 'T' : str_sub_u($text, 0, 1);
}

function timellow_excerpt(array $post, int $length = 92): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? str_sub_u($excerpt, 0, $length) : derive_excerpt((string)($post['content'] ?? ''), $length);
}

function timellow_category(array $post): ?array
{
    $id = (int)($post['category_id'] ?? 0);
    return $id > 0 ? one('SELECT name, slug FROM categories WHERE id = ?', [$id]) : null;
}

function timellow_page_hero(string $title, string $description): string
{
    return '<section class="page-hero"><h1 class="page-title">' . h($title) . '</h1><p class="page-description">' . h($description) . '</p></section>';
}

function timellow_render_posts(array $posts, string $hero = '', string $pagination = ''): string
{
    ob_start();
    ?>
    <main class="site-main">
      <?= $hero ?>
      <?php if ($posts): ?>
        <div class="post-list" data-post-list>
          <?php foreach ($posts as $post): ?>
            <?php $permalink = content_permalink($post); $category = timellow_category($post); ?>
            <article class="post-card<?= !empty($post['is_pinned']) ? ' is-sticky' : '' ?>" data-post-cid="<?= h((string)$post['id']) ?>" itemscope itemtype="https://schema.org/BlogPosting">
              <a class="post-thumb-link" href="<?= h($permalink) ?>" aria-label="<?= h((string)$post['title']) ?>">
                <img class="post-thumb" src="<?= h(timellow_post_cover($post)) ?>" alt="<?= h((string)$post['title']) ?>" loading="lazy" decoding="async">
              </a>
              <div class="post-body">
                <h2 class="post-title" itemprop="headline">
                  <?php if (!empty($post['is_pinned'])): ?><span class="post-sticky-badge"><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
                  <a href="<?= h($permalink) ?>" itemprop="url"><?= h((string)$post['title']) ?></a>
                </h2>
                <div class="post-meta">
                  <time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>" itemprop="datePublished"><?= h(date('Y-m-d', (int)$post['published_at'])) ?></time>
                  <?php if ($category): ?><span class="meta-separator"></span><a href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"><?= h((string)$category['name']) ?></a><?php endif; ?>
                </div>
                <p class="post-excerpt" itemprop="description"><?= h(timellow_excerpt($post)) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <?= $pagination ?>
      <?php else: ?>
        <section class="empty-state"><p><?= h(sblog_t('这里还没有发布任何文章。')) ?></p></section>
      <?php endif; ?>
    </main>
    <?php
    return (string)ob_get_clean();
}

function timellow_pagination(int $page, int $totalPages, callable $urlBuilder): string
{
    if ($totalPages <= 1) {
        return '';
    }
    ob_start();
    ?>
    <nav class="pagination" aria-label="<?= h(sblog_t('分页')) ?>">
      <?php if ($page > 1): ?><a class="page-link" href="<?= h($urlBuilder($page - 1)) ?>"><?= h(sblog_t('上一页')) ?></a><?php else: ?><span class="page-link is-disabled"><?= h(sblog_t('上一页')) ?></span><?php endif; ?>
      <span class="page-status"><?= h((string)$page) ?> / <?= h((string)$totalPages) ?></span>
      <?php if ($page < $totalPages): ?><a class="page-link" href="<?= h($urlBuilder($page + 1)) ?>"><?= h(sblog_t('下一页')) ?></a><?php else: ?><span class="page-link is-disabled"><?= h(sblog_t('下一页')) ?></span><?php endif; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function timellow_search_posts(string $term): array
{
    $term = str_sub_u(trim($term), 0, 100);
    if ($term === '') {
        return [];
    }
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    return all_rows("SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? AND (title LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\') ORDER BY is_pinned DESC, published_at DESC, id DESC LIMIT 100", ['post', 'published', time(), $like, $like, $like]);
}

function timellow_render_home(): string
{
    $search = trim((string)($_GET['s'] ?? $_POST['s'] ?? ''));
    if ($search !== '') {
        return timellow_render_posts(timellow_search_posts($search), timellow_page_hero(sblog_t('搜索：{keyword}', ['keyword' => $search]), sblog_t('搜索结果')));
    }
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $totalPages = max(1, (int)ceil(count_published_posts() / $perPage));
    return timellow_render_posts(
        fetch_published_posts($perPage, ($page - 1) * $perPage),
        '',
        timellow_pagination($page, $totalPages, static fn(int $number): string => home_page_url($number))
    );
}

function timellow_render_category(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) {
        return '';
    }
    $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', (int)$category['id'], 'published', time()]);
    return timellow_render_posts($posts, timellow_page_hero((string)$category['name'], trim((string)$category['description']) ?: sblog_t('分类下的文章')));
}

function timellow_render_tag(string $slug): string
{
    $label = tag_label_by_slug($slug) ?? $slug;
    return timellow_render_posts(fetch_posts_by_tag_slug($slug), timellow_page_hero('#' . $label, sblog_t('标签下的文章')));
}

function timellow_render_archives(): string
{
    $years = [];
    foreach (fetch_archive_posts() as $post) {
        $years[date('Y', (int)$post['published_at'])][] = $post;
    }
    ob_start();
    ?>
    <main class="site-main">
      <?= timellow_page_hero(sblog_t('文章归档'), sblog_t('按年份回看所有已发布文章。')) ?>
      <?php if ($years): ?><div class="archive-list">
        <?php foreach ($years as $year => $posts): ?><section class="archive-year-card">
          <header class="archive-year-header"><h2 class="archive-year-title"><?= h($year) ?></h2><span class="archive-year-count"><?= h(sblog_tn('{count} 篇文章', count($posts))) ?></span></header>
          <div class="archive-items"><?php foreach ($posts as $post): ?><div class="archive-item"><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('m-d', (int)$post['published_at'])) ?></time><a href="<?= h(content_permalink($post)) ?>"><?= h((string)$post['title']) ?></a></div><?php endforeach; ?></div>
        </section><?php endforeach; ?>
      </div><?php else: ?><section class="empty-state"><p><?= h(sblog_t('归档还是空的。')) ?></p></section><?php endif; ?>
    </main>
    <?php
    return (string)ob_get_clean();
}

function timellow_render_tags(): string
{
    $tags = tag_index_data();
    $max = $tags ? max(array_map(static fn(array $tag): int => (int)$tag['count'], $tags)) : 1;
    ob_start();
    ?>
    <main class="site-main">
      <?= timellow_page_hero(sblog_t('标签'), sblog_t('用关键词浏览文章。')) ?>
      <?php if ($tags): ?><div class="tag-cloud"><?php foreach ($tags as $tag): ?><a class="tag-chip" style="--tag-scale:<?= h((string)((int)$tag['count'] / $max)) ?>" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><span class="tag-hash">#</span><span><?= h((string)$tag['label']) ?></span><small><?= h((string)$tag['count']) ?></small></a><?php endforeach; ?></div><?php else: ?><section class="empty-state"><p><?= h(sblog_t('暂无标签')) ?></p></section><?php endif; ?>
    </main>
    <?php
    return (string)ob_get_clean();
}

function timellow_link_host(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) ? preg_replace('/^www\./i', '', $host) ?? '' : '';
}

function timellow_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    ?>
    <main class="site-main">
      <?= timellow_page_hero(sblog_t('友情链接'), sblog_t('朋友们与值得访问的网站。')) ?>
      <?php if ($links): ?><section class="links-group"><h2 class="links-group-title"><span><?= h(sblog_t('朋友们')) ?></span><span class="links-group-count"><?= h((string)count($links)) ?></span></h2><div class="links-grid">
        <?php foreach ($links as $link): ?><article class="link-card"><a class="link-avatar" href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener nofollow"><span class="link-avatar-fallback"><?= h(timellow_first_character((string)$link['name'])) ?></span></a><div class="link-main"><div class="link-heading"><a class="link-name" href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener nofollow"><?= h((string)$link['name']) ?></a><span class="link-host"><?= h(timellow_link_host((string)$link['url'])) ?></span></div><?php if (trim((string)$link['description']) !== ''): ?><p class="link-desc"><?= h((string)$link['description']) ?></p><?php endif; ?></div></article><?php endforeach; ?>
      </div></section><?php else: ?><section class="empty-state"><p><?= h(sblog_t('暂时还没有友情链接。')) ?></p></section><?php endif; ?>
    </main>
    <?php
    return (string)ob_get_clean();
}

function timellow_render_article(string $content, array $context): string
{
    $action = (string)($_GET['a'] ?? '');
    $active = (string)($context['active'] ?? '');
    $isPage = $action === 'page' || str_starts_with($active, 'page:');
    $post = null;
    if ($action === 'post') {
        $post = fetch_post_by_identifier((string)($_GET['slug'] ?? ''), true);
    } elseif ($action === 'page') {
        $post = fetch_page_by_identifier((string)($_GET['slug'] ?? ''), true);
    } elseif ($isPage) {
        $post = fetch_page_by_identifier(substr($active, 5), true);
    }
    if (!$post) {
        return '<main class="site-main">' . $content . '</main>';
    }

    $body = markdown_to_html((string)$post['content']);
    $timestamp = (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
    $category = timellow_category($post);
    $comments = '';
    if (preg_match('/<section class="comments".*<\/section>/s', $content, $match)) {
        $comments = $match[0];
    }
    $navigation = '';
    if (!$isPage && preg_match('/<ul class="pagination">(.*?)<\/ul>/s', $content, $match)) {
        $newer = '';
        $older = '';
        if (preg_match('/<li class="page-item page-previous">(.*?)<\/li>/s', $match[1], $item)) {
            $newer = trim($item[1]);
        }
        if (preg_match('/<li class="page-item page-next">(.*?)<\/li>/s', $match[1], $item)) {
            $older = trim($item[1]);
        }
        if ($newer !== '' || $older !== '') {
            $navigation = '<nav class="article-nav" aria-label="' . h(sblog_t('文章切换')) . '">'
                . '<div class="article-nav-card"><span class="article-nav-label">' . h(sblog_t('上一篇')) . '</span><p class="article-nav-title">' . ($newer !== '' ? $newer : h(sblog_t('已经是第一篇了'))) . '</p></div>'
                . '<div class="article-nav-card"><span class="article-nav-label">' . h(sblog_t('下一篇')) . '</span><p class="article-nav-title">' . ($older !== '' ? $older : h(sblog_t('已经是最后一篇了'))) . '</p></div>'
                . '</nav>';
        }
    }
    ob_start();
    ?>
    <main class="site-main">
      <?php if ($isPage): ?><?= timellow_page_hero((string)$post['title'], timellow_excerpt($post, 96) ?: setting('site_tagline', '')) ?><?php endif; ?>
      <article class="content-card" itemscope itemtype="<?= $isPage ? 'https://schema.org/WebPage' : 'https://schema.org/BlogPosting' ?>">
        <?php if (!$isPage): ?><header class="article-header"><h1 class="article-title" itemprop="headline"><?= h((string)$post['title']) ?></h1><div class="article-meta"><time datetime="<?= h(date(DATE_ATOM, $timestamp)) ?>" itemprop="datePublished"><?= h(date('Y-m-d', $timestamp)) ?></time><?php if ($category): ?><span class="meta-separator"></span><a href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"><?= h((string)$category['name']) ?></a><?php endif; ?><span class="meta-separator"></span><a href="#comments"><?= h(sblog_tn('{count} 条评论', approved_comment_count((int)$post['id']))) ?></a></div></header><?php endif; ?>
        <div class="article-body" itemprop="articleBody"><?= $body ?></div>
        <?php $tags = tag_descriptors($post); if ($tags): ?><footer class="article-footer"><div class="tag-list"><?php foreach ($tags as $tag): ?><a class="tag-chip" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><span class="tag-hash">#</span><span><?= h((string)$tag['label']) ?></span></a><?php endforeach; ?></div></footer><?php endif; ?>
      </article>
      <?= $navigation ?>
      <?= $comments ?>
    </main>
    <?php
    return (string)ob_get_clean();
}

add_theme_filter('body_class', static fn(string $classes): string => trim($classes . ' timellow-theme timellow-no-sidebar'));

add_theme_filter('comments_labels', static function (array $labels): array {
    return array_merge($labels, ['title' => sblog_t('评论'), 'form_title' => sblog_t('发表评论'), 'submit' => sblog_t('提交评论'), 'cancel_reply' => sblog_t('取消回复'), 'empty' => sblog_t('暂无评论'), 'closed' => sblog_t('评论已关闭')]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');
    if ($action === 'category') {
        return timellow_render_category((string)($_GET['slug'] ?? '')) ?: $content;
    }
    if ($action === 'tag') {
        return timellow_render_tag((string)($_GET['slug'] ?? ''));
    }
    if ($active === 'archives') {
        return timellow_render_archives();
    }
    if ($active === 'tags') {
        return timellow_render_tags();
    }
    if ($active === 'links') {
        return timellow_render_links();
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '') && $action !== 'post') {
        return timellow_render_home();
    }
    if ($action === 'post' || $action === 'page' || str_starts_with($active, 'page:')) {
        return timellow_render_article($content, $context);
    }
    return $content;
});
