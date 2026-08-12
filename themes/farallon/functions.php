<?php

declare(strict_types=1);

function farallon_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        return $url !== '#' ? $url : '';
    }

    return '';
}

function farallon_post_excerpt(array $post): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? $excerpt : derive_excerpt((string)($post['content'] ?? ''), 180);
}

function farallon_icon(string $name): string
{
    $paths = [
        'clock' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>',
        'folder' => '<path d="M3 6h6l2 2h10v10H3z"></path>',
        'eye' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path><circle cx="12" cy="12" r="2.5"></circle>',
        'comment' => '<path d="M4 5h16v11H9l-5 4z"></path>',
    ];
    $path = $paths[$name] ?? $paths['clock'];
    return '<svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function farallon_sns_icon(string $name): string
{
    $icons = [
        'rss' => ['24', '<path d="M12 17c0-3-2-5-5-5M17 17c0-6-4-10-10-10M7 17.01l.01-.01"></path><path d="M21 8v8a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5V8a5 5 0 0 1 5-5h8a5 5 0 0 1 5 5Z"></path>', false],
        'github' => ['24', '<path d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.87c-2.78.6-3.37-1.18-3.37-1.18-.45-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.61.07-.61 1 .07 1.53 1.03 1.53 1.03.9 1.53 2.34 1.09 2.91.83.09-.65.35-1.09.64-1.34-2.22-.25-4.55-1.11-4.55-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.64 0 0 .84-.27 2.75 1.02A9.6 9.6 0 0 1 12 6.82a9.6 9.6 0 0 1 2.5.34c1.91-1.29 2.75-1.02 2.75-1.02.55 1.37.2 2.39.1 2.64.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.68-4.57 4.93.36.31.68.92.68 1.85V21c0 .27.18.58.69.48A10 10 0 0 0 12 2Z"></path>', true],
        'x' => ['24', '<path d="M18.9 2H22l-6.77 7.74L23.2 22h-6.24l-4.89-6.39L6.48 22H3.36l7.26-8.3L2.98 2h6.4l4.42 5.84L18.9 2Zm-1.1 17.84h1.72L8.44 4.05H6.6L17.8 19.84Z"></path>', true],
        'instagram' => ['24', '<rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.5 6.5h.01"></path>', false],
        'telegram' => ['24', '<path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path>', false],
        'mastodon' => ['24', '<path d="M5 19c-2-3-2-10 0-13 2-3 12-3 14 0 1.5 2 1.5 8 0 10-1.4 2-5 2.5-8 2"></path><path d="M8 15V9c0-2 3-2 4 0 1-2 4-2 4 0v6"></path>', false],
        'bilibili' => ['24', '<rect x="3" y="6" width="18" height="14" rx="3"></rect><path d="m8 6-2-3M16 6l2-3M8 12v2M16 12v2"></path>', false],
        'tiktok' => ['24', '<path d="M15 3v11.5a4.5 4.5 0 1 1-4-4.47"></path><path d="M15 3c.6 2.7 2.3 4.3 5 5"></path>', false],
        'qq' => ['24', '<path d="M8 18c-3 1-4-1-2-3-1-5 1-11 6-11s7 6 6 11c2 2 1 4-2 3-2 2-6 2-8 0Z"></path><path d="M9 11h.01M15 11h.01"></path>', false],
        'wechat' => ['24', '<path d="M14 16c-5 1-10-1-10-5s4-7 9-7 8 3 8 6c0 2-1 3-2 4l1 3-3-1"></path><path d="M8 10h.01M14 10h.01"></path>', false],
        'weibo' => ['24', '<path d="M17 9c3 1 4 4 2 7-2 4-9 5-13 2-3-2-1-7 3-9 2-1 5-1 8 0Z"></path><path d="M16 6c2-1 5 1 5 3M16 3c4-1 8 2 8 6"></path><circle cx="11" cy="14" r="2"></circle>', false],
    ];
    [$viewBox, $paths, $filled] = $icons[$name] ?? ['24', '<circle cx="12" cy="12" r="9"></circle><path d="M8 12h8M12 8v8"></path>', false];
    $class = 'sns-icon' . ($filled ? ' sns-icon--fill' : '');
    $paint = $filled ? ' fill="currentColor"' : ' fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"';
    return '<svg class="' . $class . '" viewBox="0 0 ' . $viewBox . ' ' . $viewBox . '"' . $paint . ' aria-hidden="true" focusable="false">' . $paths . '</svg>';
}

function farallon_post_category(array $post): ?array
{
    $categoryId = (int)($post['category_id'] ?? 0);
    return $categoryId > 0 ? one('SELECT name, slug FROM categories WHERE id = ?', [$categoryId]) : null;
}

function farallon_render_post_items(array $posts): string
{
    ob_start();
    foreach ($posts as $post):
        $permalink = url_for('post', ['slug' => (string)$post['slug']]);
        $cover = farallon_post_cover($post);
        $category = farallon_post_category($post);
        $comments = approved_comment_count((int)$post['id']);
        ?>
        <div class="loadpost">
          <article class="post--item<?= !empty($post['is_pinned']) ? ' is-pinned' : '' ?>" itemscope itemtype="https://schema.org/Article">
            <div class="content">
              <h2 class="post--title" itemprop="headline">
                <a href="<?= h($permalink) ?>"><?= h((string)$post['title']) ?><?php if (!empty($post['is_pinned'])): ?><span class="farallon-pinned"><?= h(sblog_t('置顶')) ?></span><?php endif; ?></a>
              </h2>
              <div class="description" itemprop="description"><?= h(farallon_post_excerpt($post)) ?></div>
              <div class="meta">
                <?= farallon_icon('clock') ?><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('Y-m-d', (int)$post['published_at'])) ?></time>
                <?php if ($category): ?><?= farallon_icon('folder') ?><a href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"><?= h((string)$category['name']) ?></a><?php endif; ?>
                <?= farallon_icon('eye') ?><span><?= h((string)(int)($post['views'] ?? 0)) ?></span>
                <?= farallon_icon('comment') ?><a href="<?= h($permalink) ?>#comments"><?= h((string)$comments) ?></a>
              </div>
            </div>
            <?php if ($cover !== ''): ?>
              <a href="<?= h($permalink) ?>" class="cover--link" tabindex="-1" aria-hidden="true">
                <img src="<?= h($cover) ?>" alt="" class="cover" loading="lazy" decoding="async" onerror="this.parentElement.remove()">
              </a>
            <?php endif; ?>
          </article>
        </div>
        <?php
    endforeach;
    return (string)ob_get_clean();
}

function farallon_render_pager(int $page, int $totalPages, ?callable $urlBuilder = null): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $urlBuilder ??= static fn(int $number): string => home_page_url($number);
    $numbers = [];
    for ($number = 1; $number <= $totalPages; $number++) {
        if ($number === 1 || $number === $totalPages || abs($number - $page) <= 2) {
            $numbers[] = $number;
        }
    }

    ob_start();
    ?>
    <nav class="nav-links" aria-label="<?= h(sblog_t('分页')) ?>">
      <?php $previous = 0; foreach ($numbers as $number): ?>
        <?php if ($number - $previous > 1): ?><span class="page-numbers dots"><span>…</span></span><?php endif; ?>
        <?php if ($number === $page): ?><span class="page-numbers current" aria-current="page"><span><?= h((string)$number) ?></span></span><?php else: ?><a class="page-numbers" href="<?= h($urlBuilder($number)) ?>"><span><?= h((string)$number) ?></span></a><?php endif; ?>
        <?php $previous = $number; ?>
      <?php endforeach; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function farallon_render_list(array $posts, string $header = '', string $pager = ''): string
{
    ob_start();
    if ($header !== '') {
        echo $header;
    }
    ?>
    <main class="site--main">
      <div class="articleList">
        <?php if ($posts): ?><div id="loadposts"><?= farallon_render_post_items($posts) ?></div><?= $pager ?><?php else: ?><div class="empty-notice"><?= h(sblog_t('这里还没有内容。')) ?></div><?php endif; ?>
      </div>
    </main>
    <?php
    return (string)ob_get_clean();
}

function farallon_search_posts(string $term): array
{
    $term = str_sub_u(trim($term), 0, 100);
    if ($term === '') {
        return [];
    }
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    return all_rows(
        "SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? AND (title LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\') ORDER BY is_pinned DESC, published_at DESC, id DESC LIMIT 100",
        ['post', 'published', time(), $like, $like, $like]
    );
}

function farallon_render_home(): string
{
    $search = trim((string)($_GET['s'] ?? ''));
    if ($search !== '') {
        $header = '<header class="archive--header"><h1 class="post--single__title">' . h(sblog_t('搜索：{keyword}', ['keyword' => $search])) . '</h1></header>';
        return farallon_render_list(farallon_search_posts($search), $header);
    }

    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);
    return farallon_render_list($posts, '', farallon_render_pager($page, $totalPages));
}

function farallon_archive_header(string $title, string $description = ''): string
{
    return '<header class="archive--header"><h1 class="post--single__title">' . h($title) . '</h1>'
        . ($description !== '' ? '<div class="post--single__subtitle">' . h($description) . '</div>' : '') . '</header>';
}

function farallon_render_archive(): string
{
    $posts = fetch_archive_posts();
    $years = [];
    foreach ($posts as $post) {
        $years[date('Y', (int)$post['published_at'])][date('m', (int)$post['published_at'])][] = $post;
    }
    ob_start();
    ?>
    <section class="site--main">
      <?= farallon_archive_header(sblog_t('归档'), sblog_tn('共包含 {count} 篇文章', count($posts))) ?>
      <div class="page--archive"><div class="archives">
        <?php foreach ($years as $year => $months): ?><section class="archive-year">
          <h2 class="archive--title__year"><?= h($year) ?><?= h(sblog_t('年')) ?></h2>
          <?php foreach ($months as $month => $monthPosts): ?><h3 class="archive--title__month"><?= h($month) ?><?= h(sblog_t('月')) ?></h3><ul class="archive--list">
            <?php foreach ($monthPosts as $post): ?><li class="archive--item"><div class="archive--title"><a href="<?= h(content_permalink($post)) ?>"><?= h((string)$post['title']) ?></a></div><div class="archive--meta"><?= h(date('m月d日', (int)$post['published_at'])) ?></div></li><?php endforeach; ?>
          </ul><?php endforeach; ?>
        </section><?php endforeach; ?>
        <?php if (!$years): ?><div class="empty-notice"><?= h(sblog_t('归档还是空的。')) ?></div><?php endif; ?>
      </div></div>
    </section>
    <?php
    return (string)ob_get_clean();
}

function farallon_render_tags(): string
{
    $tags = tag_index_data();
    ob_start();
    ?>
    <section class="site--main">
      <?= farallon_archive_header(sblog_t('标签'), sblog_t('浏览全部文章标签')) ?>
      <?php if ($tags): ?><div class="archive--tagList"><?php foreach ($tags as $tag): ?><a class="archive--tagItem" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><?= h((string)$tag['label']) ?> (<?= h((string)$tag['count']) ?>)</a><?php endforeach; ?></div><?php else: ?><div class="empty-notice"><?= h(sblog_t('暂无标签')) ?></div><?php endif; ?>
    </section>
    <?php
    return (string)ob_get_clean();
}

function farallon_render_tag_page(string $slug): string
{
    $label = tag_label_by_slug($slug) ?? $slug;
    return farallon_render_list(fetch_posts_by_tag_slug($slug), farallon_archive_header(sblog_t('标签 {name} 下的文章', ['name' => $label])));
}

function farallon_render_category_page(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) {
        return '';
    }
    $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', (int)$category['id'], 'published', time()]);
    return farallon_render_list($posts, farallon_archive_header((string)$category['name'], trim((string)$category['description'])));
}

function farallon_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    ?>
    <section class="site--main">
      <?= farallon_archive_header(sblog_t('链接'), sblog_t('一些值得访问的网站与朋友。')) ?>
      <div class="template--linksWrap">
        <?php if ($links): ?><ul class="link-items"><?php foreach ($links as $link): ?><li class="link-item"><a class="link-item-inner" href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer"><span class="sitename"><strong><?= h((string)$link['name']) ?></strong><span class="description"><?= h((string)$link['description']) ?></span></span></a></li><?php endforeach; ?></ul><?php else: ?><div class="empty-notice"><?= h(sblog_t('还没有添加友情链接。')) ?></div><?php endif; ?>
      </div>
    </section>
    <?php
    return (string)ob_get_clean();
}

function farallon_post_meta(array $post): string
{
    $meta = one('SELECT p.views, u.username, u.nickname, c.name AS category_name, c.slug AS category_slug FROM posts p LEFT JOIN users u ON u.id = p.author_id LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [(int)$post['id']]) ?? [];
    $author = trim((string)($meta['nickname'] ?? '')) ?: (string)($meta['username'] ?? 'Admin');
    $timestamp = (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
    ob_start();
    ?><div class="post--single__meta">
      <?= farallon_icon('clock') ?><time datetime="<?= h(date(DATE_ATOM, $timestamp)) ?>"><?= h(date('Y-m-d', $timestamp)) ?></time>
      <span class="meta-dot">·</span><span><?= h($author) ?></span>
      <?php if (trim((string)($meta['category_slug'] ?? '')) !== ''): ?><span class="meta-dot">·</span><?= farallon_icon('folder') ?><a href="<?= h(url_for('category', ['slug' => (string)$meta['category_slug']])) ?>"><?= h((string)$meta['category_name']) ?></a><?php endif; ?>
      <span class="meta-dot">·</span><?= farallon_icon('eye') ?><span><?= h((string)(int)($meta['views'] ?? 0)) ?></span>
    </div><?php
    return (string)ob_get_clean();
}

function farallon_render_post_navigation(array $post): string
{
    $neighbors = post_neighbors($post);
    if (!$neighbors['newer'] && !$neighbors['older']) {
        return '';
    }

    ob_start();
    ?>
    <nav class="navigation post-navigation" aria-label="<?= h(sblog_t('文章导航')) ?>">
      <div class="nav-links">
        <?php if ($neighbors['newer']): ?>
          <div class="nav-previous">
            <a href="<?= h(content_permalink($neighbors['newer'])) ?>" rel="prev">
              <span class="meta-nav"><?= h(sblog_t('post_navigation.previous')) ?></span>
              <span class="post-title"><?= h((string)$neighbors['newer']['title']) ?></span>
            </a>
          </div>
        <?php endif; ?>
        <?php if ($neighbors['older']): ?>
          <div class="nav-next">
            <a href="<?= h(content_permalink($neighbors['older'])) ?>" rel="next">
              <span class="meta-nav"><?= h(sblog_t('post_navigation.next')) ?></span>
              <span class="post-title"><?= h((string)$neighbors['older']['title']) ?></span>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </nav>
    <?php
    return trim((string)ob_get_clean());
}

function farallon_adapt_article_content(string $content, array $context): string
{
    $action = (string)($_GET['a'] ?? '');
    $active = (string)($context['active'] ?? '');
    $isPage = $action === 'page' || str_starts_with($active, 'page:');
    $post = null;
    if ($action === 'post') {
        $post = fetch_post_by_identifier((string)($_GET['slug'] ?? ''), true);
    } elseif ($action === 'page') {
        $post = fetch_page_by_identifier((string)($_GET['slug'] ?? ''), true);
    } elseif (str_starts_with($active, 'page:')) {
        $post = fetch_page_by_identifier(substr($active, 5), true);
    }

    $articleClass = $isPage ? 'post--single post--page' : 'post--single';
    $content = preg_replace('/<article>/', '<article class="' . $articleClass . '">', $content, 1) ?? $content;
    $content = str_replace('class="post-title"', 'class="post--single__title"', $content);
    $content = str_replace('class="post-content"', 'class="post-content graph"', $content);
    if ($post && content_kind($post) === 'post') {
        $content = preg_replace('/<div class="meta">.*?<\/div>/s', farallon_post_meta($post), $content, 1) ?? $content;
        $content = preg_replace('/<ul class="pagination">.*?<\/ul>/s', farallon_render_post_navigation($post), $content, 1) ?? $content;
    }
    return '<main class="site--main">' . $content . '</main>';
}

add_theme_filter('body_class', static function (string $classes): string {
    return trim($classes . ' farallon-theme');
});

add_theme_filter('comments_labels', static function (array $labels): array {
    return array_merge($labels, [
        'title' => sblog_t('评论'),
        'form_title' => sblog_t('发表评论'),
        'submit' => sblog_t('提交评论'),
        'cancel_reply' => sblog_t('取消回复'),
        'empty' => sblog_t('暂无评论'),
        'closed' => sblog_t('评论已关闭'),
    ]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');

    if ($action === 'category') {
        return farallon_render_category_page((string)($_GET['slug'] ?? '')) ?: $content;
    }
    if ($action === 'tag') {
        return farallon_render_tag_page((string)($_GET['slug'] ?? ''));
    }
    if ($active === 'archives') {
        return farallon_render_archive();
    }
    if ($active === 'tags') {
        return farallon_render_tags();
    }
    if ($active === 'links') {
        return farallon_render_links();
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '') && $action !== 'post') {
        return farallon_render_home();
    }
    if ($action === 'post' || $action === 'page' || str_starts_with($active, 'page:')) {
        return farallon_adapt_article_content($content, $context);
    }
    return $content;
});
