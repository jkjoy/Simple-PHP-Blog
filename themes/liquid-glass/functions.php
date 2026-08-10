<?php

declare(strict_types=1);

function aqua_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        return $url !== '#' ? $url : '';
    }

    return '';
}

function aqua_post_excerpt(array $post): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? $excerpt : derive_excerpt((string)($post['content'] ?? ''), 110);
}

function aqua_reveal_delay(int $index, float $step = 0.06, float $max = 0.3): string
{
    $delay = min($index * $step, $max);
    return $delay > 0 ? ' style="--reveal-delay:' . h(number_format($delay, 2, '.', '')) . 's"' : '';
}

function aqua_render_post_grid(array $posts): string
{
    ob_start();
    ?>
    <div class="aqua-post-grid">
      <?php foreach ($posts as $index => $post): ?>
        <?php
        $cover = aqua_post_cover($post);
        $tags = tag_descriptors($post);
        $featuredClass = $index === 0 ? ' aqua-post-card--featured' : '';
        ?>
        <article class="aqua-post-card<?= $featuredClass ?> aqua-reveal"<?= aqua_reveal_delay($index % 6) ?>>
          <a class="aqua-post-card__media aqua-cover-<?= h((string)(($index % 6) + 1)) ?>" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>" tabindex="-1" aria-hidden="true">
            <?php if ($cover !== ''): ?>
              <img src="<?= h($cover) ?>" alt="" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async" onerror="this.remove()">
            <?php endif; ?>
            <span class="aqua-post-card__glyph"><?= h(str_sub_u((string)$post['title'], 0, 1)) ?></span>
            <?php if (!empty($post['is_pinned'])): ?><span class="aqua-pin"><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
          </a>
          <div class="aqua-post-card__body">
            <div class="aqua-post-card__meta">
              <time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('Y.m.d', (int)$post['published_at'])) ?></time>
              <?php if ($tags !== []): ?><span><?= h((string)$tags[0]['label']) ?></span><?php endif; ?>
            </div>
            <h2><a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a></h2>
            <p><?= h(aqua_post_excerpt($post)) ?></p>
            <a class="aqua-read-link" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>">
              <span><?= h(sblog_t('继续阅读')) ?></span>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function aqua_render_pager(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    ob_start();
    ?>
    <nav class="aqua-pager aqua-glass aqua-reveal" aria-label="<?= h(sblog_t('分页')) ?>">
      <?php if ($page > 1): ?>
        <a href="<?= h(home_page_url($page - 1)) ?>" aria-label="<?= h(sblog_t('上一页')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg><span><?= h(sblog_t('上一页')) ?></span></a>
      <?php else: ?><span class="is-disabled"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg><span><?= h(sblog_t('上一页')) ?></span></span><?php endif; ?>
      <strong><?= h((string)$page) ?> <i>/</i> <?= h((string)$totalPages) ?></strong>
      <?php if ($page < $totalPages): ?>
        <a href="<?= h(home_page_url($page + 1)) ?>"><span><?= h(sblog_t('下一页')) ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></a>
      <?php else: ?><span class="is-disabled"><span><?= h(sblog_t('下一页')) ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></span><?php endif; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function aqua_render_home(): string
{
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '8'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);

    ob_start();
    ?>
    <section class="aqua-feed" aria-labelledby="aqua-feed-title">
      <div class="aqua-section-head aqua-reveal">
        <div><span class="aqua-kicker"><?= h(sblog_t('JOURNAL')) ?></span><h2 id="aqua-feed-title"><?= h($page > 1 ? sblog_t('第 {page} 页', ['page' => $page]) : sblog_t('最近更新')) ?></h2></div>
        <a href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('浏览全部')) ?> <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
      </div>
      <?php if ($posts): ?>
        <?= aqua_render_post_grid($posts) ?>
        <?= aqua_render_pager($page, $totalPages) ?>
      <?php else: ?>
        <div class="aqua-empty aqua-glass"><p><?= h(sblog_t('还没有已发布的文章。')) ?></p><?php if (is_admin()): ?><a href="<?= h(url_for('write')) ?>"><?= h(sblog_t('写第一篇文章')) ?></a><?php endif; ?></div>
      <?php endif; ?>
    </section>
    <?php
    return (string)ob_get_clean();
}

function aqua_render_timeline(array $posts, bool $groupByYear = true): string
{
    ob_start();
    ?>
    <div class="aqua-timeline">
      <?php if ($groupByYear): ?>
        <?php $years = []; foreach ($posts as $post) { $years[date('Y', (int)$post['published_at'])][] = $post; } ?>
        <?php foreach ($years as $year => $yearPosts): ?>
          <section class="aqua-year aqua-reveal">
            <header><h2><?= h((string)$year) ?></h2><span><?= h(sblog_tn('{count} 篇', count($yearPosts))) ?></span></header>
            <div class="aqua-year__posts">
              <?php foreach ($yearPosts as $post): ?>
                <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>">
                  <time><?= h(date('m.d', (int)$post['published_at'])) ?></time>
                  <strong><?= h((string)$post['title']) ?></strong>
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </a>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      <?php else: ?>
        <section class="aqua-year aqua-reveal"><div class="aqua-year__posts">
          <?php foreach ($posts as $post): ?>
            <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>">
              <time><?= h(date('Y.m.d', (int)$post['published_at'])) ?></time>
              <strong>
                <?php if (!empty($post['is_pinned'])): ?>
                  <span class="aqua-timeline-pin"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17v5M5 17h14l-1-7H6l-1 7ZM9 10V4h6v6"/></svg><?= h(sblog_t('置顶')) ?></span>
                <?php endif; ?>
                <span class="aqua-year__title"><?= h((string)$post['title']) ?></span>
              </strong>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </a>
          <?php endforeach; ?>
        </div></section>
      <?php endif; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function aqua_page_head(string $eyebrow, string $title, string $description): string
{
    ob_start();
    ?>
    <header class="aqua-page-head aqua-reveal">
      <span class="aqua-kicker"><?= h($eyebrow) ?></span>
      <h1><?= h($title) ?></h1>
      <p><?= h($description) ?></p>
    </header>
    <?php
    return (string)ob_get_clean();
}

function aqua_render_archives(): string
{
    $posts = fetch_archive_posts();
    ob_start();
    echo aqua_page_head(
        sblog_t('ARCHIVE'),
        sblog_t('所有文章'),
        sblog_tn('按时间回看写过的 {count} 篇文章。', count($posts))
    );
    if ($posts) {
        echo aqua_render_timeline($posts);
    } else {
        echo '<div class="aqua-empty aqua-glass"><p>' . h(sblog_t('归档还是空的。')) . '</p></div>';
    }
    return (string)ob_get_clean();
}

function aqua_render_tags(): string
{
    $tags = tag_index_data();
    ob_start();
    echo aqua_page_head(
        sblog_t('TOPICS'),
        sblog_t('文章标签'),
        sblog_tn('{count} 个主题，找到你感兴趣的内容。', count($tags))
    );
    if ($tags): ?>
      <div class="aqua-tag-grid">
        <?php foreach ($tags as $index => $tag): ?>
          <a class="aqua-tag aqua-glass aqua-reveal" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"<?= aqua_reveal_delay($index % 6) ?>>
            <span>#</span><strong><?= h((string)$tag['label']) ?></strong><small><?= h(sblog_tn('{count} 篇', (int)$tag['count'])) ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="aqua-empty aqua-glass"><p><?= h(sblog_t('还没有标签。')) ?></p></div><?php endif;
    return (string)ob_get_clean();
}

function aqua_render_tag_page(string $slug): string
{
    $label = tag_label_by_slug($slug);
    $posts = fetch_posts_by_tag_slug($slug);
    if ($label === null && $posts === []) {
        return '';
    }
    $label = $label ?? $slug;
    return aqua_page_head(
        sblog_t('TOPIC'),
        '# ' . $label,
        sblog_tn('这个标签下共有 {count} 篇文章。', count($posts))
    ) . ($posts
        ? aqua_render_timeline($posts, false)
        : '<div class="aqua-empty aqua-glass"><p>' . h(sblog_t('这个标签下还没有文章。')) . '</p></div>');
}

function aqua_render_category_page(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) {
        return '';
    }
    $posts = all_rows(
        'SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC',
        ['post', (int)$category['id'], 'published', time()]
    );
    $description = trim((string)$category['description'])
        ?: sblog_tn('这个分类下共有 {count} 篇文章。', count($posts));
    return aqua_page_head(sblog_t('CATEGORY'), (string)$category['name'], $description)
        . ($posts
            ? aqua_render_timeline($posts, false)
            : '<div class="aqua-empty aqua-glass"><p>' . h(sblog_t('这个分类下还没有文章。')) . '</p></div>');
}

function aqua_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    echo aqua_page_head(sblog_t('FRIENDS'), sblog_t('朋友们'), sblog_t('去看看互联网另一端的有趣灵魂。'));
    if ($links): ?>
      <div class="aqua-link-grid">
        <?php foreach ($links as $index => $link): ?>
          <?php $name = trim((string)$link['name']); $iconUrl = trim((string)$link['icon_url']); ?>
          <a class="aqua-link-card aqua-glass aqua-reveal" href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer"<?= aqua_reveal_delay($index % 6) ?>>
            <span class="aqua-link-card__icon"><b><?= h(str_sub_u($name, 0, 1)) ?></b><?php if ($iconUrl !== ''): ?><img src="<?= h($iconUrl) ?>" alt="" width="56" height="56" loading="lazy" onerror="this.remove()"><?php endif; ?></span>
            <span><strong><?= h($name) ?></strong><small><?= h(trim((string)$link['description']) ?: sblog_t('访问这个网站')) ?></small></span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="aqua-empty aqua-glass"><p><?= h(sblog_t('还没有添加友情链接。')) ?></p></div><?php endif;
    return (string)ob_get_clean();
}

add_theme_filter('body_class', static function (string $classes, array $context): string {
    return trim($classes . ' aqua-theme');
});

add_theme_filter('comments_labels', static function (array $labels, array $context): array {
    return array_replace($labels, [
        'title' => sblog_t('评论'),
        'form_title' => sblog_t('写下评论'),
        'submit' => sblog_t('提交评论'),
        'cancel_reply' => sblog_t('取消'),
        'cancel_reply_aria' => sblog_t('取消回复'),
        'empty' => sblog_t('暂无评论，来留下第一条吧。'),
        'closed' => sblog_t('评论已关闭'),
    ]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');

    if ($active === 'home' && $action === 'category') {
        $rendered = aqua_render_category_page((string)($_GET['slug'] ?? ''));
        return $rendered !== '' ? $rendered : $content;
    }
    if ($active === 'tags' && $action === 'tag') {
        $rendered = aqua_render_tag_page((string)($_GET['slug'] ?? ''));
        return $rendered !== '' ? $rendered : $content;
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '')) {
        return aqua_render_home();
    }
    if ($active === 'archives') {
        return aqua_render_archives();
    }
    if ($active === 'tags') {
        return aqua_render_tags();
    }
    if ($active === 'links') {
        return aqua_render_links();
    }

    return $content;
});

add_theme_action('head', static function (array $context): string {
    return '<meta name="theme-color" content="#eef2f7" data-aqua-theme-color>' . "\n";
});
