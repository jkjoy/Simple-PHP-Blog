<?php

declare(strict_types=1);

function hammeros_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        return $url !== '#' ? $url : '';
    }

    return '';
}

function hammeros_excerpt(array $post): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? $excerpt : derive_excerpt((string)($post['content'] ?? ''), 96);
}

function hammeros_post_list(array $posts): string
{
    ob_start();
    ?>
    <div class="hammer-posts">
      <?php foreach ($posts as $index => $post): ?>
        <?php
        $cover = hammeros_post_cover($post);
        $tags = tag_descriptors($post);
        $publishedAt = (int)$post['published_at'];
        ?>
        <article class="hammer-post reveal" style="--reveal-order:<?= h((string)min($index, 5)) ?>">
          <a class="hammer-post__media hammer-post__media--<?= h((string)(($index % 4) + 1)) ?>" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>" aria-label="<?= h(sblog_t('阅读文章：{title}', ['title' => (string)$post['title']])) ?>">
            <?php if ($cover !== ''): ?>
              <img src="<?= h($cover) ?>" alt="" loading="lazy" decoding="async" onerror="this.remove()">
            <?php else: ?>
              <span class="hammer-post__day"><?= h(date('d', $publishedAt)) ?></span>
              <span class="hammer-post__month"><?= h(strtoupper(date('M', $publishedAt))) ?></span>
            <?php endif; ?>
          </a>
          <div class="hammer-post__body">
            <div class="hammer-post__meta">
              <time datetime="<?= h(date(DATE_ATOM, $publishedAt)) ?>"><?= h(date('Y.m.d', $publishedAt)) ?></time>
              <?php if (!empty($post['is_pinned'])): ?><span class="hammer-pin"><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
              <?php if ($tags !== []): ?><span><?= h((string)$tags[0]['label']) ?></span><?php endif; ?>
            </div>
            <h2><a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a></h2>
            <p><?= h(hammeros_excerpt($post)) ?></p>
            <a class="hammer-post__more" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>" aria-label="<?= h(sblog_t('继续阅读：{title}', ['title' => (string)$post['title']])) ?>"><?= h(sblog_t('继续阅读')) ?> <span aria-hidden="true">→</span></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function hammeros_pager(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    ob_start();
    ?>
    <nav class="hammer-pager" aria-label="<?= h(sblog_t('分页')) ?>">
      <?php if ($page > 1): ?><a href="<?= h(home_page_url($page - 1)) ?>"><span aria-hidden="true">←</span> <?= h(sblog_t('上一页')) ?></a><?php else: ?><span></span><?php endif; ?>
      <span class="hammer-pager__count"><?= h((string)$page) ?> / <?= h((string)$totalPages) ?></span>
      <?php if ($page < $totalPages): ?><a href="<?= h(home_page_url($page + 1)) ?>"><?= h(sblog_t('下一页')) ?> <span aria-hidden="true">→</span></a><?php else: ?><span></span><?php endif; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function hammeros_render_home(): string
{
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);
    $tagline = trim(setting('site_tagline')) ?: sblog_t('把复杂留给系统，把时间还给生活。');

    ob_start();
    ?>
    <section class="hammer-home-head">
      <div>
        <span class="hammer-eyebrow"><?= h($page > 1 ? sblog_t('CONTINUE READING') : sblog_t('TODAY')) ?></span>
        <h1><?= h($page > 1 ? sblog_t('继续翻阅，第 {page} 页', ['page' => $page]) : sblog_t('今天，也有一些值得读的事')) ?></h1>
      </div>
      <p><?= h($tagline) ?></p>
    </section>
    <?php if ($posts): ?>
      <?= hammeros_post_list($posts) ?>
      <?= hammeros_pager($page, $totalPages) ?>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('内容柜还是空的。')) ?></p><?php if (is_admin()): ?><p><a href="<?= h(url_for('write')) ?>"><?= h(sblog_t('写下第一篇文章')) ?></a></p><?php endif; ?></div>
    <?php endif;
    return (string)ob_get_clean();
}

function hammeros_render_archive(): string
{
    $posts = fetch_archive_posts();
    $years = [];
    foreach ($posts as $post) {
        $years[date('Y', (int)$post['published_at'])][] = $post;
    }

    ob_start();
    ?>
    <header class="hammer-page-head">
      <span class="hammer-eyebrow"><?= h(sblog_t('MEMORY DRAWER')) ?></span>
      <h1><?= h(sblog_t('归档')) ?></h1>
      <p><?= h(sblog_tn('共收好 {count} 篇文章，按时间整齐放置。', count($posts))) ?></p>
    </header>
    <?php if ($years): ?>
      <div class="hammer-archive">
        <?php foreach ($years as $year => $yearPosts): ?>
          <section class="hammer-year reveal">
            <div class="hammer-year__label"><strong><?= h((string)$year) ?></strong><span><?= h(sblog_tn('{count} 篇', count($yearPosts))) ?></span></div>
            <ol>
              <?php foreach ($yearPosts as $post): ?>
                <li>
                  <time><?= h(date('m.d', (int)$post['published_at'])) ?></time>
                  <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a>
                  <span aria-hidden="true">→</span>
                </li>
              <?php endforeach; ?>
            </ol>
          </section>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="empty-notice"><p><?= h(sblog_t('归档抽屉还是空的。')) ?></p></div><?php endif;
    return (string)ob_get_clean();
}

function hammeros_render_tags(): string
{
    $tags = tag_index_data();
    ob_start();
    ?>
    <header class="hammer-page-head">
      <span class="hammer-eyebrow"><?= h(sblog_t('INDEX LABELS')) ?></span>
      <h1><?= h(sblog_t('标签')) ?></h1>
      <p><?= h(sblog_tn('{count} 枚索引，帮你快速找回感兴趣的内容。', count($tags))) ?></p>
    </header>
    <?php if ($tags): ?>
      <div class="hammer-tags">
        <?php foreach ($tags as $index => $tag): ?>
          <a class="hammer-tag reveal" style="--reveal-order:<?= h((string)min($index, 5)) ?>" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>">
            <span># <?= h((string)$tag['label']) ?></span><strong><?= h((string)$tag['count']) ?></strong>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="empty-notice"><p><?= h(sblog_t('还没有标签。')) ?></p></div><?php endif;
    return (string)ob_get_clean();
}

function hammeros_render_tag_page(string $slug): string
{
    $label = tag_label_by_slug($slug);
    $posts = fetch_posts_by_tag_slug($slug);
    if ($label === null && $posts === []) {
        return '';
    }
    $label = $label ?? $slug;

    ob_start();
    ?>
    <header class="hammer-page-head">
      <span class="hammer-eyebrow"><?= h(sblog_t('FILTERED')) ?></span>
      <h1># <?= h($label) ?></h1>
      <p><?= h(sblog_tn('为你找到 {count} 篇文章。', count($posts))) ?></p>
    </header>
    <?= $posts ? hammeros_post_list($posts) : '<div class="empty-notice"><p>' . h(sblog_t('这个标签下还没有文章。')) . '</p></div>' ?>
    <?php
    return (string)ob_get_clean();
}

function hammeros_render_category(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) {
        return '';
    }
    $posts = all_rows(
        'SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC',
        ['post', (int)$category['id'], 'published', time()]
    );

    ob_start();
    ?>
    <header class="hammer-page-head">
      <span class="hammer-eyebrow"><?= h(sblog_t('COLLECTION')) ?></span>
      <h1><?= h((string)$category['name']) ?></h1>
      <p><?= h(trim((string)$category['description']) ?: sblog_tn('这个分类里共有 {count} 篇文章。', count($posts))) ?></p>
    </header>
    <?= $posts ? hammeros_post_list($posts) : '<div class="empty-notice"><p>' . h(sblog_t('这个分类下还没有文章。')) . '</p></div>' ?>
    <?php
    return (string)ob_get_clean();
}

function hammeros_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    ?>
    <header class="hammer-page-head">
      <span class="hammer-eyebrow"><?= h(sblog_t('NEIGHBORS')) ?></span>
      <h1><?= h(sblog_t('朋友们')) ?></h1>
      <p><?= h(sblog_t('一些常来常往、值得敲门拜访的地方。')) ?></p>
    </header>
    <?php if ($links): ?>
      <div class="hammer-links">
        <?php foreach ($links as $index => $link): ?>
          <?php $name = trim((string)$link['name']); $icon = trim((string)$link['icon_url']); ?>
          <a class="hammer-link reveal" style="--reveal-order:<?= h((string)min($index, 5)) ?>" href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer">
            <span class="hammer-link__avatar"><?php if ($icon !== ''): ?><img src="<?= h($icon) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?><span><?= h(str_sub_u($name, 0, 1)) ?></span></span>
            <span class="hammer-link__copy"><strong><?= h($name) ?></strong><small><?= h(trim((string)$link['description']) ?: sblog_t('去看看他们最近在做什么')) ?></small></span>
            <span class="hammer-link__arrow" aria-hidden="true">↗</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="empty-notice"><p><?= h(sblog_t('邻居名册还是空的。')) ?></p></div><?php endif;
    return (string)ob_get_clean();
}

add_theme_filter('body_class', static function (string $classes, array $context): string {
    return trim($classes . ' hammeros-theme');
});

add_theme_filter('comments_labels', static function (array $labels, array $context): array {
    return array_replace($labels, [
        'title' => sblog_t('来信'),
        'form_title' => sblog_t('写封回信'),
        'submit' => sblog_t('寄出回信'),
        'cancel_reply' => sblog_t('取消'),
        'cancel_reply_aria' => sblog_t('取消回复'),
        'empty' => sblog_t('信箱里还没有新消息。'),
        'closed' => sblog_t('这篇文章暂不接收回信。'),
    ]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');

    if ($active === 'home' && $action === 'category') {
        return hammeros_render_category((string)($_GET['slug'] ?? '')) ?: $content;
    }
    if ($active === 'tags' && $action === 'tag') {
        return hammeros_render_tag_page((string)($_GET['slug'] ?? '')) ?: $content;
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '')) {
        return hammeros_render_home();
    }
    if ($active === 'archives') {
        return hammeros_render_archive();
    }
    if ($active === 'tags') {
        return hammeros_render_tags();
    }
    if ($active === 'links') {
        return hammeros_render_links();
    }

    return $content;
});

add_theme_action('head', static function (array $context): string {
    return '<meta name="theme-color" content="#e7eaed" data-hammer-theme-color>' . "\n";
});
