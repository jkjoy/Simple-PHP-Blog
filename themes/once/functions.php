<?php

declare(strict_types=1);

function once_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        return $url !== '#' ? $url : '';
    }
    return '';
}

function once_excerpt(array $post, int $length = 150): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? $excerpt : derive_excerpt((string)($post['content'] ?? ''), $length);
}

function once_category(array $post): ?array
{
    $categoryId = (int)($post['category_id'] ?? 0);
    return $categoryId > 0 ? one('SELECT name, slug FROM categories WHERE id = ?', [$categoryId]) : null;
}

function once_icon(string $name, int $size = 14): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"></path>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"></path>',
        'sun' => '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>',
        'moon' => '<path d="M20.5 14.5A8 8 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z"></path>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path>',
        'folder' => '<path d="M3 6h6l2 2h10v10H3z"></path>',
        'eye' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path><circle cx="12" cy="12" r="2.5"></circle>',
        'comment' => '<path d="M4 5h16v11H9l-5 4z"></path>',
        'tag' => '<path d="m20 13-7 7L4 11V4h7l9 9Z"></path><circle cx="8.5" cy="8.5" r="1"></circle>',
        'arrow-up' => '<path d="m6 15 6-6 6 6"></path>',
        'external' => '<path d="M15 3h6v6M10 14 21 3"></path><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>',
    ];
    $path = $paths[$name] ?? $paths['calendar'];
    return '<svg class="once-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function once_post_meta(array $post, bool $includeTags = true): string
{
    $category = once_category($post);
    $comments = approved_comment_count((int)$post['id']);
    $tags = $includeTags ? array_slice(tag_descriptors($post), 0, 2) : [];
    ob_start(); ?>
    <div class="once-post-meta">
      <span><?= once_icon('folder') ?><?php if ($category): ?><a href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"><?= h((string)$category['name']) ?></a><?php else: ?><?= h(sblog_t('未分类')) ?><?php endif; ?></span>
      <span><?= once_icon('calendar') ?><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('Y.m.d', (int)$post['published_at'])) ?></time></span>
      <span><?= once_icon('eye') ?><?= h((string)(int)($post['views'] ?? 0)) ?><?= h(sblog_t('次浏览')) ?></span>
      <?php if ($comments > 0): ?><span><?= once_icon('comment') ?><?= h((string)$comments) ?></span><?php endif; ?>
      <?php if ($tags): ?><span class="once-post-meta__tags"><?= once_icon('tag') ?><?php foreach ($tags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><?= h((string)$tag['label']) ?></a><?php endforeach; ?></span><?php endif; ?>
    </div><?php
    return (string)ob_get_clean();
}

function once_render_post_card(array $post): string
{
    $cover = once_post_cover($post);
    $permalink = content_permalink($post);
    ob_start(); ?>
    <article class="once-post-card<?= !empty($post['is_pinned']) ? ' is-pinned' : '' ?>">
      <?php if ($cover !== ''): ?><a class="once-post-card__cover" href="<?= h($permalink) ?>" tabindex="-1" aria-hidden="true"><img src="<?= h($cover) ?>" alt="" loading="lazy" decoding="async"></a><?php endif; ?>
      <div class="once-post-card__body">
        <div>
          <h2><a href="<?= h($permalink) ?>"><?= h((string)$post['title']) ?></a><?php if (!empty($post['is_pinned'])): ?><span class="once-pin"><?= h(sblog_t('置顶')) ?></span><?php endif; ?></h2>
          <p><?= h(once_excerpt($post)) ?></p>
        </div>
        <?= once_post_meta($post) ?>
      </div>
    </article><?php
    return (string)ob_get_clean();
}

function once_render_feature(array $post, string $class, string $fallbackLabel): string
{
    $cover = once_post_cover($post);
    $category = once_category($post);
    ob_start(); ?>
    <a class="once-feature <?= h($class) ?><?= $cover === '' ? ' once-feature--empty' : '' ?>" href="<?= h(content_permalink($post)) ?>">
      <?php if ($cover !== ''): ?><img src="<?= h($cover) ?>" alt="" loading="<?= $class === 'once-feature--main' ? 'eager' : 'lazy' ?>" decoding="async"><?php else: ?><span class="once-feature__fallback" aria-hidden="true"><span><?= h($fallbackLabel) ?></span></span><?php endif; ?>
      <span class="once-feature__shade"></span>
      <?php if ($category): ?><span class="once-feature__category"><?= h((string)$category['name']) ?></span><?php endif; ?>
      <strong><?= h((string)$post['title']) ?></strong>
      <?php if ($class === 'once-feature--side'): ?><small><?= once_icon('calendar', 12) ?><?= h(date('Y-m-d', (int)$post['published_at'])) ?></small><?php endif; ?>
    </a><?php
    return (string)ob_get_clean();
}

function once_sidebar(): string
{
    $popular = all_rows("SELECT id, slug, title, content, views FROM posts WHERE kind = 'post' AND status = 'published' AND published_at <= ? ORDER BY views DESC, id DESC LIMIT 5", [time()]);
    $comments = all_rows(
        "SELECT c.id, c.author_name, c.author_email, c.content, c.created_at, p.slug, p.title AS post_title
         FROM comments c
         INNER JOIN posts p ON p.id = c.post_id
         WHERE c.status = 'approved' AND p.kind = 'post' AND p.status = 'published' AND p.published_at <= ?
         ORDER BY c.created_at DESC, c.id DESC LIMIT 5",
        [time()]
    );
    $tags = array_slice(tag_index_data(), 0, 14);
    ob_start(); ?>
    <aside class="once-sidebar" aria-label="<?= h(sblog_t('侧边栏')) ?>">
      <section class="once-widget once-widget--search">
        <h3><?= h(sblog_t('搜索')) ?></h3>
        <form method="get" action="<?= h(url_for('home')) ?>" role="search"><?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="home"><?php endif; ?><label class="sr-only" for="once-sidebar-search"><?= h(sblog_t('搜索')) ?></label><input id="once-sidebar-search" name="s" type="search" value="<?= h((string)($_GET['s'] ?? '')) ?>"><button type="submit"><?= h(sblog_t('搜索')) ?></button></form>
      </section>
      <?php if ($popular): ?><section class="once-widget"><h3><?= h(sblog_t('热门文章')) ?></h3><ul class="once-popular"><?php foreach ($popular as $post): $cover = once_post_cover($post); ?><li><a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?php if ($cover !== ''): ?><img src="<?= h($cover) ?>" alt="" loading="lazy"><?php else: ?><span class="once-popular__fallback"></span><?php endif; ?><span><strong><?= h((string)$post['title']) ?></strong><small><?= h((string)(int)$post['views']) ?> <?= h(sblog_t('次浏览')) ?></small></span></a></li><?php endforeach; ?></ul></section><?php endif; ?>
      <?php if ($comments): ?><section class="once-widget"><h3><?= h(sblog_t('最近评论')) ?></h3><ul class="once-comments-widget"><?php foreach ($comments as $comment): ?><li><a href="<?= h(url_for('post', ['slug' => (string)$comment['slug']]) . '#comment-' . (int)$comment['id']) ?>"><img src="<?= h(gravatar_url((string)$comment['author_email'])) ?>" width="34" height="34" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"><span><strong><?= h((string)$comment['author_name']) ?></strong><span><?= h(comment_excerpt((string)$comment['content'], 46)) ?></span><small><?= h(date('Y-m-d', (int)$comment['created_at'])) ?> · <?= h((string)$comment['post_title']) ?></small></span></a></li><?php endforeach; ?></ul></section><?php endif; ?>
      <?php if ($tags): ?><section class="once-widget"><h3><?= h(sblog_t('热门标签')) ?></h3><div class="once-tagcloud"><?php foreach ($tags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><?= h((string)$tag['label']) ?><small><?= h((string)$tag['count']) ?></small></a><?php endforeach; ?></div></section><?php endif; ?>
    </aside><?php
    return (string)ob_get_clean();
}

function once_render_pagination(int $page, int $totalPages, ?callable $urlBuilder = null): string
{
    if ($totalPages <= 1) return '';
    $urlBuilder ??= static fn(int $number): string => home_page_url($number);
    ob_start(); ?><nav class="once-pagination" aria-label="<?= h(sblog_t('分页')) ?>"><?php for ($number = 1; $number <= $totalPages; $number++): if ($number > 1 && $number < $page - 2 && $number !== $totalPages) continue; if ($number < $totalPages && $number > $page + 2 && $number !== 1) continue; ?><?php if ($number === $page): ?><span aria-current="page"><?= $number ?></span><?php else: ?><a href="<?= h($urlBuilder($number)) ?>"><?= $number ?></a><?php endif; ?><?php endfor; ?></nav><?php
    return (string)ob_get_clean();
}

function once_search_posts(string $term): array
{
    $term = str_sub_u(trim($term), 0, 100);
    if ($term === '') return [];
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    return all_rows("SELECT * FROM posts WHERE kind = 'post' AND status = 'published' AND published_at <= ? AND (title LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\') ORDER BY is_pinned DESC, published_at DESC, id DESC LIMIT 100", [time(), $like, $like, $like]);
}

function once_render_home(): string
{
    $search = trim((string)($_GET['s'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '24'));
    if ($search !== '') {
        $posts = once_search_posts($search);
        return once_render_listing($posts, sblog_t('搜索：{keyword}', ['keyword' => $search]));
    }
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);
    $features = [];
    if ($page === 1) {
        $featurePool = $posts;
        usort($featurePool, static function (array $left, array $right): int {
            $coverOrder = (once_post_cover($right) !== '' ? 1 : 0) <=> (once_post_cover($left) !== '' ? 1 : 0);
            return $coverOrder !== 0 ? $coverOrder : ((int)$right['published_at'] <=> (int)$left['published_at']);
        });
        $features = array_slice($featurePool, 0, 4);
    }
    $listPosts = $posts;
    ob_start(); ?>
    <?php if ($features): ?><section class="once-feature-grid" aria-label="<?= h(sblog_t('推荐文章')) ?>"><?php foreach ($features as $index => $post): ?><?= once_render_feature($post, $index === 0 ? 'once-feature--main' : ($index === 3 ? 'once-feature--side' : 'once-feature--small'), $index === 0 ? 'ONCE' : (string)($index + 1)) ?><?php endforeach; ?></section><?php endif; ?>
    <div class="once-columns"><main class="once-feed"><?php foreach ($listPosts as $post) echo once_render_post_card($post); ?><?= once_render_pagination($page, $totalPages) ?></main><?= once_sidebar() ?></div><?php
    return (string)ob_get_clean();
}

function once_render_listing(array $posts, string $title, string $description = ''): string
{
    ob_start(); ?><div class="once-columns"><main class="once-feed"><header class="once-page-head"><h1><?= h($title) ?></h1><?php if ($description !== ''): ?><p><?= h($description) ?></p><?php endif; ?></header><?php if ($posts): foreach ($posts as $post) echo once_render_post_card($post); else: ?><div class="empty-notice once-empty"><?= h(sblog_t('这里还没有内容。')) ?></div><?php endif; ?></main><?= once_sidebar() ?></div><?php return (string)ob_get_clean();
}

function once_render_archives(): string
{
    $posts = fetch_archive_posts();
    $years = [];
    foreach ($posts as $post) $years[date('Y', (int)$post['published_at'])][] = $post;
    ob_start(); ?><div class="once-columns"><main class="once-page"><header class="once-page-head"><h1><?= h(sblog_t('归档')) ?></h1><p><?= h(sblog_tn('共包含 {count} 篇文章', count($posts))) ?></p></header><div class="once-archive"><?php foreach ($years as $year => $yearPosts): ?><section><h2><?= h($year) ?></h2><ul><?php foreach ($yearPosts as $post): ?><li><time><?= h(date('m-d', (int)$post['published_at'])) ?></time><a href="<?= h(content_permalink($post)) ?>"><?= h((string)$post['title']) ?></a></li><?php endforeach; ?></ul></section><?php endforeach; ?></div></main><?= once_sidebar() ?></div><?php return (string)ob_get_clean();
}

function once_render_tags(): string
{
    $tags = tag_index_data();
    ob_start(); ?><div class="once-columns"><main class="once-page"><header class="once-page-head"><h1><?= h(sblog_t('标签')) ?></h1><p><?= h(sblog_t('浏览全部文章标签')) ?></p></header><div class="once-tags-page"><?php foreach ($tags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><strong><?= h((string)$tag['label']) ?></strong><span><?= h(sblog_tn('{count} 篇文章', (int)$tag['count'])) ?></span></a><?php endforeach; ?></div></main><?= once_sidebar() ?></div><?php return (string)ob_get_clean();
}

function once_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start(); ?><div class="once-columns"><main class="once-page"><header class="once-page-head"><h1><?= h(sblog_t('链接')) ?></h1><p><?= h(sblog_t('一些值得访问的网站与朋友。')) ?></p></header><div class="once-links-page"><?php foreach ($links as $link): ?><a href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer"><span class="once-link-avatar"><?php if (trim((string)$link['icon_url']) !== ''): ?><img src="<?= h((string)$link['icon_url']) ?>" alt="" loading="lazy"><?php else: ?><?= h(str_sub_u((string)$link['name'], 0, 1)) ?><?php endif; ?></span><span><strong><?= h((string)$link['name']) ?></strong><small><?= h((string)$link['description']) ?></small></span><?= once_icon('external') ?></a><?php endforeach; ?></div></main><?= once_sidebar() ?></div><?php return (string)ob_get_clean();
}

function once_adapt_article(string $content, array $context): string
{
    $action = (string)($_GET['a'] ?? '');
    $active = (string)($context['active'] ?? '');
    $post = null;
    if ($action === 'post') $post = fetch_post_by_identifier((string)($_GET['slug'] ?? ''), true);
    elseif ($action === 'page') $post = fetch_page_by_identifier((string)($_GET['slug'] ?? ''), true);
    elseif (str_starts_with($active, 'page:')) $post = fetch_page_by_identifier(substr($active, 5), true);
    $content = preg_replace('/<article>/', '<article class="once-article">', $content, 1) ?? $content;
    $content = str_replace('class="post-title"', 'class="post-title once-article__title"', $content);
    $content = str_replace('class="post-content"', 'class="post-content once-article__content"', $content);
    $content = preg_replace('/(<table>.*?<\/table>)/s', '<div class="once-table-scroll">$1</div>', $content) ?? $content;
    if ($post) {
        $content = preg_replace('/<div class="meta">.*?<\/div>/s', once_post_meta($post, false), $content, 1) ?? $content;
    }
    $content = preg_replace_callback(
        '/<a class="post-tag"([^>]*)>#([^<]*)<\/a>/',
        static fn(array $match): string => '<a class="post-tag"' . $match[1] . '>' . $match[2] . '</a>',
        $content
    ) ?? $content;
    $content = preg_replace_callback(
        '/<a([^>]*data-post-title="([^"]*)"[^>]*)>([^<]*)<\/a>/',
        static function (array $match): string {
            $title = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = trim(strip_tags($match[3]));
            return '<a' . $match[1] . '><span class="once-nav-label">' . h($label) . '</span><strong class="once-nav-title">' . h($title) . '</strong></a>';
        },
        $content
    ) ?? $content;
    return '<div class="once-columns"><main class="once-article-wrap">' . $content . '</main>' . once_sidebar() . '</div>';
}

add_theme_filter('body_class', static fn(string $classes): string => trim($classes . ' once-theme'));

add_theme_filter('comments_labels', static function (array $labels): array {
    return array_merge($labels, ['title' => sblog_t('评论'), 'form_title' => sblog_t('发布评论'), 'submit' => sblog_t('提交评论'), 'cancel_reply' => sblog_t('取消回复'), 'empty' => sblog_t('暂无评论'), 'closed' => sblog_t('评论已关闭')]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');
    if ($action === 'category') {
        $category = one('SELECT * FROM categories WHERE slug = ?', [trim((string)($_GET['slug'] ?? ''))]);
        return $category ? once_render_listing(all_rows("SELECT * FROM posts WHERE kind = 'post' AND category_id = ? AND status = 'published' AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC", [(int)$category['id'], time()]), (string)$category['name'], (string)$category['description']) : $content;
    }
    if ($action === 'tag') {
        $slug = (string)($_GET['slug'] ?? '');
        return once_render_listing(fetch_posts_by_tag_slug($slug), sblog_t('标签 {name} 下的文章', ['name' => tag_label_by_slug($slug) ?? $slug]));
    }
    if ($active === 'archives') return once_render_archives();
    if ($active === 'tags') return once_render_tags();
    if ($active === 'links') return once_render_links();
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '') && $action !== 'post') return once_render_home();
    if ($action === 'post' || $action === 'page' || str_starts_with($active, 'page:')) return once_adapt_article($content, $context);
    return $content;
});
