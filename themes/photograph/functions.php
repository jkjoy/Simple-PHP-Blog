<?php

declare(strict_types=1);

function photograph_icon(string $name, string $class = ''): string
{
    $paths = [
        'menu' => '<rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><rect x="14" y="14" width="6" height="6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'up' => '<path d="m6 15 6-6 6 6"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 20h2M20 14h1"/>',
        'comment' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'text' => '<path d="M5 5h14M12 5v14M8 19h8"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    ];
    $body = $paths[$name] ?? $paths['image'];
    return '<svg class="photograph-icon' . ($class !== '' ? ' ' . h($class) : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

function photograph_post_images(array $post, int $limit = PHP_INT_MAX): array
{
    $content = (string)($post['content'] ?? '');
    preg_match_all('/!\[[^\]]*\]\((?:<)?([^\s)>]+)(?:>)?(?:\s+["\'][^"\']*["\'])?\)|<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER);
    $images = [];
    foreach ($matches as $match) {
        $candidate = html_entity_decode((string)(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($candidate, '//')) $candidate = 'https:' . $candidate;
        if ($candidate !== '' && !preg_match('#^(?:https?://|/|\#)#i', $candidate) && !str_starts_with($candidate, 'data:')) {
            $candidate = app_path('/' . ltrim($candidate, './'));
        }
        $url = safe_link_url($candidate);
        if ($url !== '#' && !in_array($url, $images, true)) $images[] = $url;
    }
    preg_match_all('#https?://[^\s<>"\']+?\.(?:jpe?g|png|gif|webp|avif)(?:\?[^\s<>"\']*)?#i', $content, $directMatches);
    foreach ($directMatches[0] ?? [] as $candidate) {
        $url = safe_link_url(html_entity_decode((string)$candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url !== '#' && !in_array($url, $images, true)) $images[] = $url;
    }
    return array_slice($images, 0, max(1, $limit));
}

function photograph_post_cover(array $post): string
{
    return photograph_post_images($post, 1)[0] ?? theme_asset_url('assets/noimage.svg');
}

function photograph_post_format(array $post): string
{
    return (string)($post['post_format'] ?? 'text') === 'image' ? 'image' : 'text';
}

function photograph_search_posts(string $term): array
{
    $term = str_sub_u(trim($term), 0, 100);
    if ($term === '') return [];
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    return all_rows("SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? AND (title LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\') ORDER BY is_pinned DESC, published_at DESC, id DESC LIMIT 100", ['post', 'published', time(), $like, $like, $like]);
}

function photograph_render_card(array $post): string
{
    $images = photograph_post_images($post);
    $count = count($images);
    $cover = $images[0] ?? theme_asset_url('assets/noimage.svg');
    $permalink = content_permalink($post);
    ob_start(); ?>
    <article class="photo-item<?= !empty($post['is_pinned']) ? ' is-pinned' : '' ?>">
      <img class="photo-item__image" src="<?= h($cover) ?>" alt="<?= h((string)$post['title']) ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= h(theme_asset_url('assets/noimage.svg')) ?>'">
      <div class="photo-item__overlay">
        <a class="photo-item__link" href="<?= h($permalink) ?>">
          <span class="photo-item__title"><?= h((string)$post['title']) ?><?php if (!empty($post['is_pinned'])): ?><small><?= h(sblog_t('置顶')) ?></small><?php endif; ?></span>
        </a>
        <span class="photo-item__count">[<?= h((string)$count) ?>P] <?= photograph_icon($count > 0 ? 'image' : 'text') ?></span>
      </div>
    </article>
    <?php return (string)ob_get_clean();
}

function photograph_render_grid(array $posts, string $heading = '', string $description = '', string $pager = ''): string
{
    ob_start(); ?>
    <main class="photo-aggregate">
      <?php if ($heading !== ''): ?><header class="photo-archive-head"><h1><?= h($heading) ?></h1><?php if ($description !== ''): ?><p><?= h($description) ?></p><?php endif; ?></header><?php endif; ?>
      <?php if ($posts): ?><div class="photo-grid" id="masonry"><?php foreach ($posts as $post) echo photograph_render_card($post); ?></div><?= $pager ?>
      <?php else: ?><div class="photo-empty"><img src="<?= h(theme_asset_url('assets/nocontent.svg')) ?>" alt=""><p><?= h(sblog_t('这里还没有内容。')) ?></p></div><?php endif; ?>
    </main>
    <?php return (string)ob_get_clean();
}

function photograph_render_pager(int $page, int $totalPages): string
{
    if ($totalPages <= 1) return '';
    ob_start(); ?><nav class="photo-pager" aria-label="<?= h(sblog_t('分页')) ?>">
      <?php if ($page > 1): ?><a href="<?= h(home_page_url($page - 1)) ?>" aria-label="<?= h(sblog_t('上一页')) ?>">&laquo;</a><?php endif; ?>
      <?php for ($number = 1; $number <= $totalPages; $number++): if ($number === 1 || $number === $totalPages || abs($number - $page) <= 2): ?>
        <?php if ($number === $page): ?><span aria-current="page"><?= h((string)$number) ?></span><?php else: ?><a href="<?= h(home_page_url($number)) ?>"><?= h((string)$number) ?></a><?php endif; ?>
      <?php elseif ($number === 2 || $number === $totalPages - 1): ?><i>...</i><?php endif; endfor; ?>
      <?php if ($page < $totalPages): ?><a href="<?= h(home_page_url($page + 1)) ?>" aria-label="<?= h(sblog_t('下一页')) ?>">&raquo;</a><?php endif; ?>
    </nav><?php return (string)ob_get_clean();
}

function photograph_render_home(): string
{
    $search = trim((string)($_GET['s'] ?? ''));
    if ($search !== '') return photograph_render_grid(photograph_search_posts($search), sblog_t('搜索：{keyword}', ['keyword' => $search]));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '24'));
    $totalPages = max(1, (int)ceil(count_published_posts() / $perPage));
    return photograph_render_grid(fetch_published_posts($perPage, ($page - 1) * $perPage), '', '', photograph_render_pager($page, $totalPages));
}

function photograph_render_tags(): string
{
    $tags = tag_index_data();
    ob_start(); ?><main class="photo-readable"><article class="photo-page"><h1><?= h(sblog_t('标签')) ?></h1>
      <?php if ($tags): ?><div class="photo-tags photo-tags--large"><?php foreach ($tags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>" title="<?= h(sblog_tn('{count} 篇文章', (int)$tag['count'])) ?>"><?= h((string)$tag['label']) ?></a><?php endforeach; ?></div><?php else: ?><p class="photo-inline-empty"><?= h(sblog_t('没有任何标签')) ?></p><?php endif; ?>
    </article></main><?php return (string)ob_get_clean();
}

function photograph_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start(); ?><main class="photo-readable"><article class="photo-page"><h1><?= h(sblog_t('链接')) ?></h1>
      <?php if ($links): ?><div class="photo-links"><?php foreach ($links as $link): ?><a href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer"><strong><?= h((string)$link['name']) ?></strong><span><?= h((string)$link['description']) ?></span></a><?php endforeach; ?></div><?php else: ?><p class="photo-inline-empty"><?= h(sblog_t('还没有添加友情链接。')) ?></p><?php endif; ?>
    </article></main><?php return (string)ob_get_clean();
}

function photograph_render_post(array $post, string $originalContent): string
{
    $images = photograph_post_images($post);
    $postFormat = photograph_post_format($post);
    $comments = '';
    $commentPosition = strpos($originalContent, '<section class="comments"');
    if ($commentPosition !== false) $comments = substr($originalContent, $commentPosition);
    $tags = tag_descriptors($post);
    $timestamp = (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
    $description = trim((string)($post['excerpt'] ?? '')) ?: derive_excerpt((string)$post['content']);

    if ($postFormat === 'text') {
        ob_start(); ?>
        <main class="photo-article-main">
          <article class="photo-page photo-text-post" itemscope itemtype="https://schema.org/BlogPosting">
            <h1 itemprop="name headline"><?= h((string)$post['title']) ?></h1>
            <div class="post-content" itemprop="articleBody"><?= markdown_to_html((string)$post['content']) ?></div>
            <div class="photo-post-info">
              <span><?= photograph_icon('text') ?><b><?= h(sblog_t('标题：')) ?></b><?= h((string)$post['title']) ?></span>
              <span><b><?= h(sblog_t('日期：')) ?></b><?= h(date('Y/m/d', $timestamp)) ?></span>
              <span><b><?= h(sblog_t('浏览：')) ?></b><?= h((string)(int)($post['views'] ?? 0)) ?><?= h(sblog_t('次')) ?></span>
              <span><b><?= h(sblog_t('描述：')) ?></b><?= h($description !== '' ? $description : sblog_t('未填写')) ?></span>
            </div>
            <?php if ($tags): ?><nav class="photo-tags" aria-label="<?= h(sblog_t('标签')) ?>"><?php foreach ($tags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><?= h((string)$tag['label']) ?></a><?php endforeach; ?></nav><?php endif; ?>
          </article>
          <?php if ($comments !== ''): ?><div class="photo-article-comments"><?= $comments ?></div><?php endif; ?>
        </main>
        <?php return (string)ob_get_clean();
    }

    $images = $images !== [] ? $images : [theme_asset_url('assets/noimage.svg')];
    ob_start(); ?>
    <main class="photo-post-main">
      <div class="photo-post-grid" id="masonry" aria-label="<?= h((string)$post['title']) ?>"><?php foreach ($images as $index => $image): ?>
        <button class="photo-post-item" type="button" data-photo-lightbox data-src="<?= h($image) ?>" data-caption="<?= h((string)$post['title']) ?> [<?= h((string)($index + 1)) ?>]"><img src="<?= h($image) ?>" alt="<?= h((string)$post['title']) ?> [<?= h((string)($index + 1)) ?>]" loading="<?= $index < 4 ? 'eager' : 'lazy' ?>" decoding="async"></button>
      <?php endforeach; ?></div>
      <div class="photo-post-info">
        <span><?= photograph_icon('image') ?><b><?= h(sblog_t('标题：')) ?></b><?= h((string)$post['title']) ?></span>
        <span><b><?= h(sblog_t('日期：')) ?></b><?= h(date('Y/m/d', $timestamp)) ?></span>
        <span><b><?= h(sblog_t('浏览：')) ?></b><?= h((string)(int)($post['views'] ?? 0)) ?><?= h(sblog_t('次')) ?></span>
        <span><b><?= h(sblog_t('描述：')) ?></b><?= h($description !== '' ? $description : sblog_t('未填写')) ?></span>
      </div>
      <?php if ($tags): ?><nav class="photo-tags" aria-label="<?= h(sblog_t('标签')) ?>"><?php foreach ($tags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><?= h((string)$tag['label']) ?></a><?php endforeach; ?></nav><?php endif; ?>
    </main>
    <?php if ($comments !== ''): ?><aside class="photo-comments-panel" id="post-comments" aria-hidden="true"><button class="photo-comments-close" type="button" data-comments-close aria-label="<?= h(sblog_t('关闭')) ?>"><?= photograph_icon('close') ?></button><?= $comments ?></aside><?php endif; ?>
    <?php return (string)ob_get_clean();
}

function photograph_adapt_page(string $content): string
{
    $content = preg_replace('/<article>/', '<article class="photo-page">', $content, 1) ?? $content;
    return '<main class="photo-readable">' . $content . '</main>';
}

add_theme_filter('body_class', static function (string $classes, array $context): string {
    $action = (string)($_GET['a'] ?? '');
    $viewClass = '';
    if ($action === 'post' && ($post = fetch_post_by_identifier((string)($_GET['slug'] ?? ''), true))) {
        $viewClass = photograph_post_format($post) === 'text' ? ' photograph-article-view' : ' photograph-album-view';
    }
    return trim($classes . ' photograph-theme' . ($action === 'post' ? ' photograph-post-view' : '') . $viewClass);
});

add_theme_filter('comments_labels', static function (array $labels): array {
    return array_merge($labels, ['title' => sblog_t('评论'), 'form_title' => sblog_t('添加新评论'), 'submit' => sblog_t('提交评论'), 'empty' => sblog_t('暂无评论')]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');
    if ($action === 'category') {
        $category = one('SELECT * FROM categories WHERE slug = ?', [trim((string)($_GET['slug'] ?? ''))]);
        if (!$category) return $content;
        $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', (int)$category['id'], 'published', time()]);
        return photograph_render_grid($posts, (string)$category['name'], (string)$category['description']);
    }
    if ($action === 'tag') {
        $slug = (string)($_GET['slug'] ?? '');
        return photograph_render_grid(fetch_posts_by_tag_slug($slug), sblog_t('标签 {name} 下的文章', ['name' => tag_label_by_slug($slug) ?? $slug]));
    }
    if ($active === 'archives') return photograph_render_grid(fetch_archive_posts(), sblog_t('归档'));
    if ($active === 'tags') return photograph_render_tags();
    if ($active === 'links') return photograph_render_links();
    if ($action === 'post' && ($post = fetch_post_by_identifier((string)($_GET['slug'] ?? ''), true))) return photograph_render_post($post, $content);
    if ($action === 'page' || str_starts_with($active, 'page:')) return photograph_adapt_page($content);
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '') && $action !== 'post') return photograph_render_home();
    return $content;
});
