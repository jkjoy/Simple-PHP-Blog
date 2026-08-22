<?php

declare(strict_types=1);

function clay_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        return $url !== '#' ? $url : '';
    }

    return '';
}

function clay_post_excerpt(array $post): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? $excerpt : derive_excerpt((string)($post['content'] ?? ''), 110);
}

function clay_reveal_delay(int $index, float $step = 0.05, float $max = 0.25): string
{
    $delay = min($index * $step, $max);
    return $delay > 0 ? ' style="--reveal-delay:' . h(number_format($delay, 2, '.', '')) . 's"' : '';
}

function clay_render_post_grid(array $posts): string
{
    ob_start();
    ?>
    <div class="clay-post-grid">
      <?php foreach ($posts as $index => $post): ?>
        <?php
        $cover = clay_post_cover($post);
        $tags = tag_descriptors($post);
        $featuredClass = $index === 0 ? ' clay-post-card--featured' : '';
        ?>
        <article class="clay-post-card clay-surface clay-tone-<?= h((string)(($index % 4) + 1)) ?><?= $featuredClass ?> clay-reveal"<?= clay_reveal_delay($index % 6) ?>>
          <a class="clay-post-card__media" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>" tabindex="-1" aria-hidden="true">
            <?php if ($cover !== ''): ?>
              <img src="<?= h($cover) ?>" alt="" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async" onerror="this.remove()">
            <?php endif; ?>
            <span class="clay-post-card__glyph"><?= h(str_sub_u((string)$post['title'], 0, 1)) ?></span>
            <?php if (!empty($post['is_pinned'])): ?><span class="clay-pin"><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
          </a>
          <div class="clay-post-card__body">
            <div class="clay-post-card__meta">
              <time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('Y.m.d', (int)$post['published_at'])) ?></time>
              <?php if ($tags !== []): ?><span><?= h((string)$tags[0]['label']) ?></span><?php endif; ?>
            </div>
            <h2><a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a></h2>
            <p><?= h(clay_post_excerpt($post)) ?></p>
            <a class="clay-read-link" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>">
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

function clay_render_pager(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    ob_start();
    ?>
    <nav class="clay-pager clay-surface clay-reveal" aria-label="<?= h(sblog_t('分页')) ?>">
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

function clay_render_home(): string
{
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '8'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);

    ob_start();
    ?>
    <section class="clay-feed" aria-labelledby="clay-feed-title">
      <div class="clay-section-head clay-reveal">
        <div><span class="clay-kicker"><?= h(sblog_t('FRESH NOTES')) ?></span><h2 id="clay-feed-title"><?= h($page > 1 ? sblog_t('第 {page} 页', ['page' => $page]) : sblog_t('最近更新')) ?></h2></div>
        <a href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('浏览全部')) ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
      </div>
      <?php if ($posts): ?>
        <?= clay_render_post_grid($posts) ?>
        <?= clay_render_pager($page, $totalPages) ?>
      <?php else: ?>
        <div class="clay-empty clay-surface"><p><?= h(sblog_t('还没有已发布的文章。')) ?></p><?php if (is_admin()): ?><a href="<?= h(url_for('write')) ?>"><?= h(sblog_t('写第一篇文章')) ?></a><?php endif; ?></div>
      <?php endif; ?>
    </section>
    <?php
    return (string)ob_get_clean();
}

function clay_render_timeline(array $posts, bool $groupByYear = true): string
{
    ob_start();
    ?>
    <div class="clay-timeline">
      <?php if ($groupByYear): ?>
        <?php $years = []; foreach ($posts as $post) { $years[date('Y', (int)$post['published_at'])][] = $post; } ?>
        <?php foreach ($years as $year => $yearPosts): ?>
          <section class="clay-year clay-reveal">
            <header><h2><?= h((string)$year) ?></h2><span><?= h(sblog_tn('{count} 篇', count($yearPosts))) ?></span></header>
            <div class="clay-year__posts clay-surface">
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
        <section class="clay-year clay-reveal"><div class="clay-year__posts clay-surface">
          <?php foreach ($posts as $post): ?>
            <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>">
              <time><?= h(date('Y.m.d', (int)$post['published_at'])) ?></time>
              <strong>
                <?php if (!empty($post['is_pinned'])): ?>
                  <span class="clay-timeline-pin"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17v5M5 17h14l-1-7H6l-1 7ZM9 10V4h6v6"/></svg><?= h(sblog_t('置顶')) ?></span>
                <?php endif; ?>
                <span class="clay-year__title"><?= h((string)$post['title']) ?></span>
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

function clay_page_head(string $eyebrow, string $title, string $description): string
{
    ob_start();
    ?>
    <header class="clay-page-head clay-reveal">
      <span class="clay-kicker"><?= h($eyebrow) ?></span>
      <h1><?= h($title) ?></h1>
      <p><?= h($description) ?></p>
    </header>
    <?php
    return (string)ob_get_clean();
}

function clay_render_archives(): string
{
    $posts = fetch_archive_posts();
    ob_start();
    echo clay_page_head(sblog_t('ARCHIVE'), sblog_t('所有文章'), sblog_tn('按时间回看写过的 {count} 篇文章。', count($posts)));
    if ($posts) {
        echo clay_render_timeline($posts);
    } else {
        echo '<div class="clay-empty clay-surface"><p>' . h(sblog_t('归档还是空的。')) . '</p></div>';
    }
    return (string)ob_get_clean();
}

function clay_render_tags(): string
{
    $tags = tag_index_data();
    ob_start();
    echo clay_page_head(sblog_t('TOPICS'), sblog_t('文章标签'), sblog_tn('{count} 个主题，找到你感兴趣的内容。', count($tags)));
    if ($tags): ?>
      <div class="clay-tag-grid">
        <?php foreach ($tags as $index => $tag): ?>
          <a class="clay-tag clay-surface clay-tone-<?= h((string)(($index % 4) + 1)) ?> clay-reveal" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"<?= clay_reveal_delay($index % 6) ?>>
            <span>#</span><strong><?= h((string)$tag['label']) ?></strong><small><?= h(sblog_tn('{count} 篇', (int)$tag['count'])) ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="clay-empty clay-surface"><p><?= h(sblog_t('还没有标签。')) ?></p></div><?php endif;
    return (string)ob_get_clean();
}

function clay_render_tag_page(string $slug): string
{
    $label = tag_label_by_slug($slug);
    $posts = fetch_posts_by_tag_slug($slug);
    if ($label === null && $posts === []) {
        return '';
    }
    $label = $label ?? $slug;
    return clay_page_head(sblog_t('TOPIC'), '# ' . $label, sblog_tn('这个标签下共有 {count} 篇文章。', count($posts)))
        . ($posts ? clay_render_timeline($posts, false) : '<div class="clay-empty clay-surface"><p>' . h(sblog_t('这个标签下还没有文章。')) . '</p></div>');
}

function clay_render_category_page(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) {
        return '';
    }
    $posts = all_rows(
        'SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC',
        ['post', (int)$category['id'], 'published', time()]
    );
    $description = trim((string)$category['description']) ?: sblog_tn('这个分类下共有 {count} 篇文章。', count($posts));
    return clay_page_head(sblog_t('CATEGORY'), (string)$category['name'], $description)
        . ($posts ? clay_render_timeline($posts, false) : '<div class="clay-empty clay-surface"><p>' . h(sblog_t('这个分类下还没有文章。')) . '</p></div>');
}

function clay_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    echo clay_page_head(sblog_t('FRIENDS'), sblog_t('朋友们'), sblog_t('去看看互联网另一端的有趣灵魂。'));
    if ($links): ?>
      <div class="clay-link-grid">
        <?php foreach ($links as $index => $link): ?>
          <?php $name = trim((string)$link['name']); $iconUrl = trim((string)$link['icon_url']); ?>
          <a class="clay-link-card clay-surface clay-tone-<?= h((string)(($index % 4) + 1)) ?> clay-reveal" href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer"<?= clay_reveal_delay($index % 6) ?>>
            <span class="clay-link-card__icon"><b><?= h(str_sub_u($name, 0, 1)) ?></b><?php if ($iconUrl !== ''): ?><img src="<?= h($iconUrl) ?>" alt="" width="56" height="56" loading="lazy" onerror="this.remove()"><?php endif; ?></span>
            <span><strong><?= h($name) ?></strong><small><?= h(trim((string)$link['description']) ?: sblog_t('访问这个网站')) ?></small></span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17 17 7M7 7h10v10"/></svg>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="clay-empty clay-surface"><p><?= h(sblog_t('还没有添加友情链接。')) ?></p></div><?php endif;
    return (string)ob_get_clean();
}

function clay_render_post_navigation_item(array $post, string $direction, int $tone): string
{
    $fullPost = one('SELECT * FROM posts WHERE id = ?', [(int)$post['id']]) ?? $post;
    $cover = clay_post_cover($fullPost);
    $isPrevious = $direction === 'previous';
    $label = $isPrevious ? sblog_t('post_navigation.previous') : sblog_t('post_navigation.next');
    $ariaLabel = $isPrevious
        ? sblog_t('post_navigation.previous_label', ['title' => (string)$post['title']])
        : sblog_t('post_navigation.next_label', ['title' => (string)$post['title']]);

    ob_start();
    ?>
    <a class="clay-post-navigation__item clay-post-navigation__item--<?= h($direction) ?> clay-tone-<?= h((string)$tone) ?><?= $cover !== '' ? ' has-cover' : '' ?>" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>" aria-label="<?= h($ariaLabel) ?>">
      <?php if ($cover !== ''): ?><img class="clay-post-navigation__cover" src="<?= h($cover) ?>" alt="" loading="lazy" decoding="async" onerror="this.parentElement.classList.remove('has-cover');this.remove()"><?php endif; ?>
      <span class="clay-post-navigation__fallback" aria-hidden="true"><?= h(str_sub_u((string)$post['title'], 0, 1)) ?></span>
      <span class="clay-post-navigation__shade" aria-hidden="true"></span>
      <span class="clay-post-navigation__content">
        <span class="clay-post-navigation__label">
          <?php if ($isPrevious): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg><?php endif; ?>
          <?= h($label) ?>
          <?php if (!$isPrevious): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg><?php endif; ?>
        </span>
        <strong><?= h((string)$post['title']) ?></strong>
      </span>
    </a>
    <?php
    return (string)ob_get_clean();
}

function clay_render_post_navigation(array $post): string
{
    $neighbors = post_neighbors($post);
    if (!$neighbors['newer'] && !$neighbors['older']) {
        return '';
    }

    ob_start();
    ?>
    <nav class="clay-post-navigation" aria-label="<?= h(sblog_t('文章导航')) ?>">
      <?php if ($neighbors['newer']): ?><?= clay_render_post_navigation_item($neighbors['newer'], 'previous', 2) ?><?php endif; ?>
      <?php if ($neighbors['older']): ?><?= clay_render_post_navigation_item($neighbors['older'], 'next', 1) ?><?php endif; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

add_theme_filter('body_class', static function (string $classes, array $context): string {
    return trim($classes . ' clay-theme');
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
        $rendered = clay_render_category_page((string)($_GET['slug'] ?? ''));
        return $rendered !== '' ? $rendered : $content;
    }
    if ($active === 'tags' && $action === 'tag') {
        $rendered = clay_render_tag_page((string)($_GET['slug'] ?? ''));
        return $rendered !== '' ? $rendered : $content;
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '')) {
        return clay_render_home();
    }
    if ($active === 'archives') {
        return clay_render_archives();
    }
    if ($active === 'tags') {
        return clay_render_tags();
    }
    if ($active === 'links') {
        return clay_render_links();
    }

    if ($action === 'post' || $action === 'page' || str_starts_with($active, 'page:')) {
        $content = preg_replace(
            '/(<table\b[^>]*>.*?<\/table>)/si',
            '<div class="clay-table-scroll">$1</div>',
            $content
        ) ?? $content;
    }

    if ($action === 'post') {
        $post = fetch_post_by_identifier((string)($_GET['slug'] ?? ''), is_admin());
        if ($post) {
            $navigation = clay_render_post_navigation($post);
            $content = preg_replace(
                '/<ul class="pagination">.*?<\/ul>/si',
                $navigation,
                $content,
                1
            ) ?? $content;
        }
    }

    return $content;
});

add_theme_action('head', static function (array $context): string {
    return '<meta name="theme-color" content="#dceff4" data-clay-theme-color>' . "\n";
});
