<?php

declare(strict_types=1);

/**
 * Nebula 星云 — functions.php
 * 通过 content filter 重渲染首页 / 归档 / 标签 / 友链等列表页，
 * 文章与独立页面沿用内置结构，由 style.css 负责视觉。
 */

function nebula_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        return $url !== '#' ? $url : '';
    }

    return '';
}

function nebula_post_excerpt(array $post): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? $excerpt : derive_excerpt((string)($post['content'] ?? ''), 90);
}

function nebula_reveal_delay(int $index, float $step = 0.08, float $max = 0.4): string
{
    $delay = min($index * $step, $max);
    return $delay > 0 ? ' style="--d:' . h(number_format($delay, 2, '.', '')) . 's"' : '';
}

function nebula_render_post_grid(array $posts): string
{
    ob_start();
    ?>
    <div class="post-grid">
      <?php foreach ($posts as $index => $post): ?>
        <?php
        $cover = nebula_post_cover($post);
        $tags = tag_descriptors($post);
        $coverVariant = 'cover-' . (($index % 6) + 1);
        $label = $tags !== [] ? (string)$tags[0]['label'] : date('Y', (int)$post['published_at']);
        ?>
        <a class="post-card reveal" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"<?= nebula_reveal_delay($index % 3) ?>>
          <div class="post-cover <?= $cover === '' ? h($coverVariant) : 'cover-img' ?>">
            <?php if ($cover !== ''): ?>
              <img src="<?= h($cover) ?>" alt="" loading="lazy" decoding="async" onerror="this.parentElement.classList.add('<?= h($coverVariant) ?>');this.remove()">
            <?php else: ?>
              <span class="cover-label"><?= h(strtoupper($label)) ?></span>
            <?php endif; ?>
            <?php if (!empty($post['is_pinned'])): ?><span class="pin-badge"><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
          </div>
          <div class="post-body">
            <h3><?= h((string)$post['title']) ?></h3>
            <p><?= h(nebula_post_excerpt($post)) ?></p>
            <div class="post-meta">
              <span><?= h(date('Y-m-d', (int)$post['published_at'])) ?></span>
              <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                <span class="tag"><?= h((string)$tag['label']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function nebula_render_pager(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $numbers = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages || abs($i - $page) <= 1) {
            $numbers[] = $i;
        }
    }

    ob_start();
    ?>
    <nav class="pager reveal" aria-label="<?= h(sblog_t('分页')) ?>">
      <?php if ($page > 1): ?><a href="<?= h(home_page_url($page - 1)) ?>"><?= h(sblog_t('上一页')) ?></a><?php else: ?><span><?= h(sblog_t('上一页')) ?></span><?php endif; ?>
      <?php $prev = 0; foreach ($numbers as $number): ?>
        <?php if ($number - $prev > 1): ?><span class="ellipsis">…</span><?php endif; ?>
        <?php if ($number === $page): ?>
          <span class="current"><?= h((string)$number) ?></span>
        <?php else: ?>
          <a href="<?= h(home_page_url($number)) ?>"><?= h((string)$number) ?></a>
        <?php endif; ?>
        <?php $prev = $number; ?>
      <?php endforeach; ?>
      <?php if ($page < $totalPages): ?><a href="<?= h(home_page_url($page + 1)) ?>"><?= h(sblog_t('下一页')) ?></a><?php else: ?><span><?= h(sblog_t('下一页')) ?></span><?php endif; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function nebula_render_home(): string
{
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);

    ob_start();
    if ($posts): ?>
      <section id="posts">
        <h2 class="section-title reveal"><?= h($page > 1 ? sblog_t('第 {page} 页', ['page' => $page]) : sblog_t('最新文章')) ?></h2>
        <?= nebula_render_post_grid($posts) ?>
        <?= nebula_render_pager($page, $totalPages) ?>
      </section>
    <?php else: ?>
      <div class="empty-notice">
        <p><?= h(sblog_t('还没有已发布的文章。')) ?></p>
        <?php if (is_admin()): ?><p><a href="<?= h(url_for('write')) ?>"><?= h(sblog_t('写第一篇文章')) ?></a></p><?php endif; ?>
      </div>
    <?php endif;

    return (string)ob_get_clean();
}

function nebula_render_timeline(array $posts, bool $groupByYear = true): string
{
    ob_start();
    ?>
    <div class="timeline">
      <?php if ($groupByYear): ?>
        <?php
        $years = [];
        foreach ($posts as $post) {
            $years[date('Y', (int)$post['published_at'])][] = $post;
        }
        ?>
        <?php foreach ($years as $year => $yearPosts): ?>
          <h2 class="t-year reveal"><?= h((string)$year) ?><span class="t-cnt"><?= h(sblog_tn('· {count} 篇', count($yearPosts))) ?></span></h2>
          <?php foreach ($yearPosts as $index => $post): ?>
            <a class="t-item reveal" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"<?= nebula_reveal_delay($index % 8, 0.05) ?>>
              <span class="t-date"><?= h(date('m-d', (int)$post['published_at'])) ?></span>
              <span class="t-title"><?= h((string)$post['title']) ?></span>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($posts as $index => $post): ?>
          <a class="t-item reveal" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"<?= nebula_reveal_delay($index % 8, 0.05) ?>>
            <span class="t-date"><?= h(date('Y-m-d', (int)$post['published_at'])) ?></span>
            <span class="t-title"><?php if (!empty($post['is_pinned'])): ?><span class="t-pin"><?= h(sblog_t('置顶')) ?></span><?php endif; ?><?= h((string)$post['title']) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function nebula_total_word_count(): int
{
    $total = 0;
    $contents = all_rows(
        'SELECT content FROM posts WHERE kind = ? AND status = ? AND published_at <= ?',
        ['post', 'published', time()]
    );

    foreach ($contents as $row) {
        $plainText = markdown_to_plain((string)($row['content'] ?? ''));
        $plainText = preg_replace('/\s+/u', '', $plainText) ?? $plainText;
        $total += str_len_u($plainText);
    }

    return $total;
}

function nebula_render_archives(): string
{
    $posts = fetch_archive_posts();
    $total = count($posts);
    $categoryCount = count(fetch_categories());
    $tagCount = count(tag_index_data());
    $linkCount = (int)val('SELECT COUNT(*) FROM links');
    $wordCount = nebula_total_word_count();
    $firstPublished = (int)val('SELECT MIN(published_at) FROM posts WHERE kind = ? AND status = ?', ['post', 'published']);
    $runningDays = $firstPublished > 0 ? max(1, (int)floor((time() - $firstPublished) / 86400)) : 0;

    ob_start();
    ?>
    <div class="page-head reveal">
      <h1 class="grad-text"><?= h(sblog_t('归档')) ?></h1>
      <p><?= h(sblog_tn('时间轴上的 {count} 篇文章 · 总共写了 {words} 字', $total, ['words' => $wordCount])) ?></p>
    </div>
    <?php if ($posts): ?>
      <div class="stats stats--bordered reveal"<?= nebula_reveal_delay(1) ?>>
        <div class="stat"><div class="num"><?= h((string)$categoryCount) ?></div><div class="label"><?= h(sblog_t('分类')) ?></div></div>
        <div class="stat"><div class="num"><?= h((string)$tagCount) ?></div><div class="label"><?= h(sblog_t('标签')) ?></div></div>
        <div class="stat"><div class="num"><?= h((string)$linkCount) ?></div><div class="label"><?= h(sblog_t('友链')) ?></div></div>
        <?php if ($runningDays > 0): ?><div class="stat"><div class="num"><?= h((string)$runningDays) ?></div><div class="label"><?= h(sblog_t('运行天数')) ?></div></div><?php endif; ?>
      </div>
      <?= nebula_render_timeline($posts) ?>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('归档还是空的。')) ?></p></div>
    <?php endif;

    return (string)ob_get_clean();
}

function nebula_render_tags(): string
{
    $tags = tag_index_data();
    $total = count_published_posts();
    $maxCount = 1;
    foreach ($tags as $tag) {
        $maxCount = max($maxCount, (int)$tag['count']);
    }

    ob_start();
    ?>
    <div class="page-head reveal">
      <h1 class="grad-text"><?= h(sblog_t('标签')) ?></h1>
      <p><?= h(sblog_t('{tags} 个标签，{posts} 篇文章 · 每一枚标签，都是一颗星', ['tags' => count($tags), 'posts' => $total])) ?></p>
    </div>
    <?php if ($tags): ?>
      <div class="cloud-wrap reveal"<?= nebula_reveal_delay(1) ?>>
        <div class="tag-cloud">
          <?php foreach ($tags as $tag): ?>
            <?php $ratio = (int)$tag['count'] / $maxCount; ?>
            <?php $size = $ratio >= 0.8 ? 5 : ($ratio >= 0.6 ? 4 : ($ratio >= 0.4 ? 3 : ($ratio >= 0.2 ? 2 : 1))); ?>
            <a class="tag-item tg-<?= h((string)$size) ?>" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><?= h((string)$tag['label']) ?><span class="cnt">×<?= h((string)$tag['count']) ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('还没有标签。')) ?></p></div>
    <?php endif;

    return (string)ob_get_clean();
}

function nebula_render_tag_page(string $slug): string
{
    $label = tag_label_by_slug($slug);
    $posts = fetch_posts_by_tag_slug($slug);
    if ($label === null && $posts === []) {
        return '';
    }
    $label = $label ?? $slug;

    ob_start();
    ?>
    <div class="page-head reveal">
      <h1><span class="grad-text">#</span> <?= h($label) ?></h1>
      <p><?= h(sblog_tn('共 {count} 篇文章', count($posts))) ?></p>
    </div>
    <?php if ($posts): ?>
      <?= nebula_render_timeline($posts, false) ?>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('这个标签下还没有文章。')) ?></p></div>
    <?php endif;

    return (string)ob_get_clean();
}

function nebula_render_category_page(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) {
        return '';
    }
    $posts = all_rows(
        'SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC',
        ['post', (int)$category['id'], 'published', time()]
    );
    $description = trim((string)$category['description']);

    ob_start();
    ?>
    <div class="page-head reveal">
      <h1><span class="grad-text"><?= h(sblog_t('分类')) ?></span> <?= h((string)$category['name']) ?></h1>
      <p><?= h(sblog_tn('共 {count} 篇文章', count($posts))) ?><?= $description !== '' ? ' · ' . h($description) : '' ?></p>
    </div>
    <?php if ($posts): ?>
      <?= nebula_render_timeline($posts, false) ?>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('这个分类下还没有已发布文章。')) ?></p></div>
    <?php endif;

    return (string)ob_get_clean();
}

function nebula_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');

    ob_start();
    ?>
    <div class="page-head reveal">
      <h1 class="grad-text"><?= h(sblog_t('友链')) ?></h1>
      <p><?= h(sblog_t('一些值得访问的网站与朋友')) ?></p>
    </div>
    <?php if ($links): ?>
      <div class="link-grid">
        <?php foreach ($links as $index => $link): ?>
          <?php $name = trim((string)$link['name']); ?>
          <?php $iconUrl = trim((string)$link['icon_url']); ?>
          <a class="link-card reveal" href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer"<?= nebula_reveal_delay($index % 4, 0.06) ?>>
            <span class="link-avatar" aria-hidden="true">
              <span><?= h(str_sub_u($name, 0, 1)) ?></span>
              <?php if ($iconUrl !== ''): ?><img src="<?= h($iconUrl) ?>" width="52" height="52" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
            </span>
            <span class="link-info">
              <span class="name"><?= h($name) ?></span>
              <span class="desc"><?= h(trim((string)$link['description']) ?: sblog_t('欢迎访问这个网站')) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('还没有添加友情链接。')) ?></p></div>
    <?php endif;

    return (string)ob_get_clean();
}

add_theme_filter('body_class', static function (string $classes, array $context): string {
    return trim($classes . ' nebula-theme');
});

add_theme_filter('comments_labels', static function (array $labels, array $context): array {
    return array_replace($labels, [
        'title' => sblog_t('评论'),
        'form_title' => sblog_t('写下评论'),
        'submit' => sblog_t('提交评论'),
        'cancel_reply' => sblog_t('取消'),
        'cancel_reply_aria' => sblog_t('取消回复'),
        'empty' => sblog_t('暂无评论，来抢沙发吧'),
        'closed' => sblog_t('评论已关闭'),
    ]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');

    if ($active === 'home' && $action === 'category') {
        $rendered = nebula_render_category_page((string)($_GET['slug'] ?? ''));
        return $rendered !== '' ? $rendered : $content;
    }
    if ($active === 'tags' && $action === 'tag') {
        $rendered = nebula_render_tag_page((string)($_GET['slug'] ?? ''));
        return $rendered !== '' ? $rendered : $content;
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '')) {
        return nebula_render_home();
    }
    if ($active === 'archives') {
        return nebula_render_archives();
    }
    if ($active === 'tags') {
        return nebula_render_tags();
    }
    if ($active === 'links') {
        return nebula_render_links();
    }

    return $content;
});

add_theme_action('head', static function (array $context): string {
    return '<meta name="theme-color" content="#070b14">' . "\n";
});
