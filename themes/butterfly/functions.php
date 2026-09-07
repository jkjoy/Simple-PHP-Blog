<?php

declare(strict_types=1);

function butterfly_icon(string $name): string
{
    return '<i class="bf-icon ri-' . h($name) . '" aria-hidden="true"></i>';
}

function butterfly_url(string $url): string
{
    $preview = (string)($_GET['theme_preview'] ?? '');
    return $preview === 'butterfly' && is_admin() ? url_with_query($url, ['theme_preview' => 'butterfly']) : $url;
}

function butterfly_query(): string
{
    return str_sub_u(trim((string)($_GET['bf_q'] ?? '')), 0, 100);
}

function butterfly_is_home(array $context): bool
{
    return in_array((string)($_GET['a'] ?? ''), ['', 'home'], true)
        && ($context['active'] ?? '') === 'home'
        && (int)($context['options']['status'] ?? 200) === 200;
}

function butterfly_current_post(): ?array
{
    static $loaded = false;
    static $post = null;
    if (!$loaded) {
        $loaded = true;
        $action = (string)($_GET['a'] ?? '');
        if (in_array($action, ['post', 'page'], true)) {
            $post = fetch_content_by_identifier($action, (string)($_GET['slug'] ?? ''), is_admin());
        }
    }
    return $post;
}

function butterfly_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url(html_entity_decode((string)$match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url !== '#') {
            return $url;
        }
    }
    return theme_asset_url('assets/default-cover.jpg');
}

function butterfly_categories(): array
{
    static $categories = null;
    if ($categories === null) {
        $categories = all_rows(
            'SELECT c.*, COUNT(p.id) AS post_count FROM categories c
             LEFT JOIN posts p ON p.category_id = c.id AND p.kind = ? AND p.status = ? AND p.published_at <= ?
             GROUP BY c.id ORDER BY c.sort_order ASC, c.id DESC',
            ['post', 'published', time()]
        );
    }
    return $categories;
}

function butterfly_post_meta(array $post): string
{
    $category = null;
    foreach (butterfly_categories() as $candidate) {
        if ((int)$candidate['id'] === (int)($post['category_id'] ?? 0)) {
            $category = $candidate;
            break;
        }
    }
    ob_start(); ?>
    <div class="bf-post-meta article-meta">
      <?php if (!empty($post['is_pinned'])): ?><span class="bf-pin sticky article-meta"><i class="post-meta-icon"><?= butterfly_icon('pushpin-line') ?></i><span class="article-meta-label"><?= h(sblog_t('置顶')) ?></span><span class="article-meta-separator">|</span></span><?php endif; ?>
      <span class="post-meta-date article-meta"><i class="post-meta-icon"><?= butterfly_icon('calendar-line') ?></i><span class="article-meta-label"><?= h(sblog_t('发表于')) ?></span><span class="post-meta-date-created" datetime="<?= h(date('Y-m-d', (int)$post['published_at'])) ?>" style="display:inline"><?= h(date('Y-m-d', (int)$post['published_at'])) ?></span></span>
      <?php if ($category): ?><span class="article-meta"><span class="article-meta-separator">|</span><i class="post-meta-icon"><?= butterfly_icon('folder-2-line') ?></i><a class="article-meta-link" href="<?= h(butterfly_url(url_for('category', ['slug' => (string)$category['slug']]))) ?>"><?= h((string)$category['name']) ?></a></span><?php endif; ?>
    </div>
    <?php return (string)ob_get_clean();
}

function butterfly_post_list(array $posts): string
{
    ob_start(); ?>
      <?php foreach ($posts as $index => $post): ?>
        <?php $url = butterfly_url(content_permalink($post)); ?>
        <?php $coverSide = ($index % 2 === 0) ? 'left' : 'right'; ?>
        <article class="bf-post-card recent-post-item">
          <div class="bf-post-cover post_cover <?= h($coverSide) ?>">
            <a href="<?= h($url) ?>" aria-label="<?= h((string)$post['title']) ?>"><img class="post-bg" src="<?= h(butterfly_cover($post)) ?>" data-bf-fallback="<?= h(theme_asset_url('assets/default-cover.jpg')) ?>" alt="" width="640" height="400" loading="lazy" decoding="async"></a>
          </div>
          <div class="bf-post-info recent-post-info">
            <a class="article-title" href="<?= h($url) ?>"><?= h((string)$post['title']) ?></a>
            <div class="article-meta-wrap"><?= butterfly_post_meta($post) ?></div>
            <div class="bf-excerpt content"><?= h(trim((string)($post['excerpt'] ?? '')) ?: derive_excerpt((string)$post['content'], 150)) ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php return (string)ob_get_clean();
}

function butterfly_pager(int $page, int $totalPages, string $query = ''): string
{
    if ($totalPages <= 1) { return ''; }
    $pageUrl = static fn(int $number): string => butterfly_url($query === '' ? home_page_url($number) : url_with_query(url_for('home'), ['bf_q' => $query, 'bf_page' => $number]));
    $numbers = array_unique(array_merge([1, $totalPages], range(max(1, $page - 2), min($totalPages, $page + 2))));
    sort($numbers);
    ob_start(); ?>
    <nav id="pagination" class="bf-pagination" aria-label="<?= h(sblog_t('分页')) ?>"><div class="pagination">
      <?php if ($page > 1): ?><a class="extend prev" href="<?= h($pageUrl($page - 1)) ?>" rel="prev" aria-label="<?= h(sblog_t('上一页')) ?>"><i class="ri-arrow-left-s-line" aria-hidden="true"></i></a><?php endif; ?>
      <?php $previous = 0; foreach ($numbers as $number): ?>
        <?php if ($number - $previous > 1): ?><span class="space" aria-hidden="true">&hellip;</span><?php endif; ?>
        <?php if ($number === $page): ?><span class="page-number current" aria-current="page"><?= $number ?></span><?php else: ?><a class="page-number" href="<?= h($pageUrl($number)) ?>"><?= $number ?></a><?php endif; ?>
      <?php $previous = $number; endforeach; ?>
      <?php if ($page < $totalPages): ?><a class="extend next" href="<?= h($pageUrl($page + 1)) ?>" rel="next" aria-label="<?= h(sblog_t('下一页')) ?>"><i class="ri-arrow-right-s-line" aria-hidden="true"></i></a><?php endif; ?>
    </div></nav>
    <?php return (string)ob_get_clean();
}

function butterfly_search_form(string $id): string
{
    ob_start(); ?>
    <form class="bf-search-form local-search-box" role="search" method="get" action="<?= h(url_for('home')) ?>">
      <?php if (($_GET['theme_preview'] ?? '') === 'butterfly' && is_admin()): ?><input type="hidden" name="theme_preview" value="butterfly"><?php endif; ?>
      <label class="sr-only" for="<?= h($id) ?>"><?= h(sblog_t('搜索文章')) ?></label>
      <input id="<?= h($id) ?>" type="search" name="bf_q" value="<?= h(butterfly_query()) ?>" maxlength="100" placeholder="<?= h(sblog_t('搜索文章')) ?>" required>
      <button class="bf-icon-button" type="submit" title="<?= h(sblog_t('搜索')) ?>" aria-label="<?= h(sblog_t('搜索')) ?>"><?= butterfly_icon('search-line') ?></button>
    </form>
    <?php return (string)ob_get_clean();
}

function butterfly_home(): string
{
    $query = butterfly_query();
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $page = max(1, (int)($_GET[$query === '' ? 'p' : 'bf_page'] ?? 1));
    if ($query !== '') {
        $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
        $where = "kind = ? AND status = ? AND published_at <= ? AND (title LIKE ? ESCAPE '!' OR content LIKE ? ESCAPE '!' OR excerpt LIKE ? ESCAPE '!')";
        $params = ['post', 'published', time(), $term, $term, $term];
        $total = (int)val('SELECT COUNT(*) FROM posts WHERE ' . $where, $params);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $posts = all_rows('SELECT * FROM posts WHERE ' . $where . ' ORDER BY published_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
    } else {
        $total = count_published_posts();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);
    }
    ob_start(); ?>
    <?php if ($query !== ''): ?>
      <?= butterfly_search_form('bf-page-search') ?>
      <p class="bf-search-count"><?= h(sblog_tn('找到 {count} 篇文章', $total)) ?></p>
    <?php endif; ?>
    <?php if ($posts): ?>
      <?= butterfly_post_list($posts) ?>
      <?= butterfly_pager($page, $totalPages, $query) ?>
    <?php else: ?>
      <div class="bf-empty"><p><?= h(sblog_t($query !== '' ? '没有找到相关文章。' : '还没有已发布的文章。')) ?></p>
      <?php if ($query === '' && is_admin()): ?><a href="<?= h(url_for('write')) ?>"><?= h(sblog_t('写第一篇文章')) ?></a><?php endif; ?></div>
    <?php endif; ?>
    <?php return (string)ob_get_clean();
}

function butterfly_timeline(array $posts): string
{
    usort($posts, static fn(array $left, array $right): int =>
        (int)$right['published_at'] <=> (int)$left['published_at'] ?: (int)$right['id'] <=> (int)$left['id']);
    ob_start(); ?>
    <div id="archive" class="bf-timeline">
      <div class="article-sort-title bf-archive-count"><?= h(sblog_tn('文章总览 - {count}', count($posts))) ?></div>
      <div class="article-sort">
      <?php $year = ''; foreach ($posts as $post): ?>
        <?php $postYear = date('Y', (int)$post['published_at']); ?>
        <?php if ($year !== $postYear): $year = $postYear; ?><div class="article-sort-item year"><?= h($year) ?> <?= h(sblog_t('年')) ?></div><?php endif; ?>
        <div class="bf-timeline-item article-sort-item">
          <a class="bf-timeline-cover article-sort-item-img" href="<?= h(butterfly_url(content_permalink($post))) ?>" tabindex="-1" aria-hidden="true"><img src="<?= h(butterfly_cover($post)) ?>" data-bf-fallback="<?= h(theme_asset_url('assets/default-cover.jpg')) ?>" width="80" height="80" alt="" loading="lazy"></a>
          <div class="article-sort-item-info">
            <div class="article-sort-item-time"><i class="post-meta-icon"><?= butterfly_icon('calendar-line') ?></i><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('Y-m-d', (int)$post['published_at'])) ?></time></div>
            <a class="bf-timeline-title article-sort-item-title" href="<?= h(butterfly_url(content_permalink($post))) ?>"><?= h((string)$post['title']) ?></a>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php return (string)ob_get_clean();
}

function butterfly_tags(array $tags): string
{
    $max = max(1, ...array_column($tags, 'count'));
    ob_start(); ?>
    <div class="bf-tag-cloud tag-cloud-list">
      <?php foreach ($tags as $tag): ?>
        <a class="bf-tag-size-<?= min(5, max(1, (int)ceil((int)$tag['count'] / $max * 5))) ?>" rel="tag" href="<?= h(butterfly_url(url_for('tag', ['slug' => (string)$tag['slug']]))) ?>"><?= h((string)$tag['label']) ?><sup><?= (int)$tag['count'] ?></sup></a>
      <?php endforeach; ?>
    </div>
    <?php return (string)ob_get_clean();
}

function butterfly_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start(); ?>
    <?php if (!$links): ?><p class="bf-empty"><?= h(sblog_t('还没有添加友情链接。')) ?></p><?php endif; ?>
    <div class="bf-link-grid flink">
      <div class="flink-list">
      <?php foreach ($links as $link): ?>
        <?php $icon = safe_link_url(trim((string)$link['icon_url'])); ?>
        <div class="bf-friend flink-list-item">
          <a href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer">
            <div class="flink-item-icon"><img src="<?= h($icon !== '#' && $icon !== '' ? $icon : theme_logo_url()) ?>" data-bf-fallback="<?= h(theme_logo_url()) ?>" width="60" height="60" alt="" loading="lazy"></div>
            <div class="flink-item-name"><?= h((string)$link['name']) ?></div>
            <div class="flink-item-desc" title="<?= h((string)$link['description']) ?>"><?= h((string)$link['description']) ?></div>
          </a>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php return (string)ob_get_clean();
}

add_theme_filter('body_class', static function (string $classes, array $context): string {
    $home = butterfly_is_home($context);
    $post = butterfly_current_post();
    $classes = trim((string)preg_replace('/(?:^|\\s)theme-public(?=\\s|$)/', ' ', $classes));
    return $classes . ' butterfly-theme ' . ($home && butterfly_query() === '' ? 'bf-home' : 'bf-inner')
        . ($home && butterfly_query() !== '' ? ' bf-search' : '')
        . ($post ? ((string)$post['kind'] === 'post' ? ' bf-post' : ' bf-page') : '')
        . ((int)($context['options']['status'] ?? 200) === 404 ? ' bf-error' : '');
});

add_theme_filter('document_title', static function (string $title, array $context): string {
    return butterfly_is_home($context) && butterfly_query() !== ''
        ? sblog_t('搜索') . ': ' . butterfly_query() . ' | ' . (string)$context['site_name'] : $title;
});

add_theme_filter('comments_labels', static function (array $labels): array {
    return array_replace($labels, [
        'title' => sblog_t('评论'), 'form_title' => sblog_t('发表评论'),
        'submit' => sblog_t('提交评论'), 'cancel_reply' => sblog_t('取消回复'),
        'empty' => sblog_t('暂无评论'), 'closed' => sblog_t('评论已关闭'),
    ]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    if ((int)($context['options']['status'] ?? 200) !== 200) { return $content; }
    $action = (string)($_GET['a'] ?? '');
    if (butterfly_is_home($context)) { return butterfly_home(); }
    if ($action === 'archives') {
        return butterfly_timeline(all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC', ['post', 'published', time()]));
    }
    if ($action === 'tag') {
        return '<div class="recent-posts category_ui" id="recent-posts">'
            . butterfly_post_list(fetch_posts_by_tag_slug((string)($_GET['slug'] ?? ''))) . '</div>';
    }
    if ($action === 'category') {
        $category = one('SELECT * FROM categories WHERE slug = ?', [(string)($_GET['slug'] ?? '')]);
        if (!$category) { return $content; }
        $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? AND category_id = ? ORDER BY published_at DESC, id DESC', ['post', 'published', time(), (int)$category['id']]);
        return '<div class="recent-posts category_ui" id="recent-posts">' . butterfly_post_list($posts) . '</div>';
    }
    if ($action === 'tags') {
        $tags = tag_index_data();
        return '<div class="tag-cloud-title is-center">' . h(sblog_t('标签')) . ' - <span class="tag-cloud-amount">'
            . count($tags) . '</span></div>'
            . ($tags ? butterfly_tags($tags) : '<p class="bf-empty">' . h(sblog_t('还没有标签。')) . '</p>');
    }
    if ($action === 'categories') {
        $categories = butterfly_categories();
        $html = '<div class="category-lists"><div class="category-title is-center">' . h(sblog_t('分类'))
            . ' - <span class="category-amount">' . count($categories) . '</span></div><ul class="bf-category-list category-list" id="aside-cat-list">';
        foreach ($categories as $category) {
            $html .= '<li class="category-list-item"><a class="category-list-link" href="' . h(butterfly_url(url_for('category', ['slug' => (string)$category['slug']]))) . '"><span class="category-list-name">' . h((string)$category['name']) . '</span></a><span class="category-list-count">' . (int)$category['post_count'] . '</span></li>';
        }
        return $html . '</ul></div>' . ($categories ? '' : '<p class="bf-empty">' . h(sblog_t('还没有分类。')) . '</p>');
    }
    if ($action === 'links') { return butterfly_links(); }
    return $content;
});
