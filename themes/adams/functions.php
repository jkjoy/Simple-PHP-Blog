<?php

declare(strict_types=1);

function adams_post_excerpt(array $post): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? $excerpt : derive_excerpt((string)($post['content'] ?? ''), 110);
}

function adams_render_post_items(array $posts): string
{
    ob_start();
    foreach ($posts as $post):
        $permalink = url_for('post', ['slug' => (string)$post['slug']]);
        $comments = approved_comment_count((int)$post['id']);
        ?>
        <article class="meta<?= !empty($post['is_pinned']) ? ' is-pinned' : '' ?>" itemscope itemtype="https://schema.org/BlogPosting">
          <header>
            <a href="<?= h($permalink) ?>" itemprop="url"><h2><?php if (!empty($post['is_pinned'])): ?><span class="adams-pinned">[<?= h(sblog_t('置顶')) ?>]</span><?php endif; ?><span itemprop="name headline"><?= h((string)$post['title']) ?></span></h2></a>
          </header>
          <main>
            <p itemprop="articleBody"><?= h(adams_post_excerpt($post)) ?></p>
          </main>
          <footer>
            <span class="time"><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>" title="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('Y-m-d', (int)$post['published_at'])) ?></time><?= h(sblog_t('发布')) ?></span>
            <span class="hr"></span>
            <span class="comments"><?= h(sblog_tn('{count} 条评论', $comments)) ?></span>
          </footer>
        </article>
        <?php
    endforeach;
    return (string)ob_get_clean();
}

function adams_render_pager(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $numbers = [];
    for ($number = 1; $number <= $totalPages; $number++) {
        if ($number === 1 || $number === $totalPages || abs($number - $page) <= 2) {
            $numbers[] = $number;
        }
    }

    ob_start();
    ?>
    <nav class="reade_more" aria-label="<?= h(sblog_t('分页')) ?>">
      <?php if ($page > 1): ?><a class="page-numbers prev" href="<?= h(home_page_url($page - 1)) ?>">«</a><?php endif; ?>
      <?php $previous = 0; foreach ($numbers as $number): ?>
        <?php if ($number - $previous > 1): ?><span class="page-numbers dots">…</span><?php endif; ?>
        <?php if ($number === $page): ?><span class="page-numbers current"><?= h((string)$number) ?></span><?php else: ?><a class="page-numbers" href="<?= h(home_page_url($number)) ?>"><?= h((string)$number) ?></a><?php endif; ?>
        <?php $previous = $number; ?>
      <?php endforeach; ?>
      <?php if ($page < $totalPages): ?><a class="page-numbers next" href="<?= h(home_page_url($page + 1)) ?>">»</a><?php endif; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function adams_render_post_list(array $posts, string $pager = ''): string
{
    ob_start();
    ?>
    <section class="posts main-load">
      <div class="container">
        <div class="post-list">
          <?php if ($posts): ?>
            <?= adams_render_post_items($posts) ?>
          <?php else: ?>
            <article class="meta"><h3 class="empty-title">Sorry!</h3><p><?= h(sblog_t('这个页面没有你要找的内容。')) ?></p></article>
          <?php endif; ?>
          <?= $pager ?>
        </div>
      </div>
    </section>
    <?php
    return (string)ob_get_clean();
}

function adams_search_posts(string $term): array
{
    $term = str_sub_u(trim($term), 0, 100);
    if ($term === '') {
        return [];
    }

    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    return all_rows(
        "SELECT * FROM posts
         WHERE kind = ? AND status = ? AND published_at <= ?
           AND (title LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\')
         ORDER BY is_pinned DESC, published_at DESC, id DESC LIMIT 100",
        ['post', 'published', time(), $like, $like, $like]
    );
}

function adams_render_home(): string
{
    $search = trim((string)($_GET['s'] ?? ''));
    if ($search !== '') {
        return adams_render_post_list(adams_search_posts($search));
    }

    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);

    return adams_render_post_list($posts, adams_render_pager($page, $totalPages));
}

function adams_render_archive(): string
{
    $posts = fetch_archive_posts();
    $years = [];
    foreach ($posts as $post) {
        $years[date('Y', (int)$post['published_at'])][] = $post;
    }

    ob_start();
    ?>
    <section class="container">
      <article class="post_article archives" itemscope itemtype="https://schema.org/Article">
        <?php if ($years): ?>
          <?php foreach ($years as $year => $yearPosts): ?>
            <h3><?= h((string)$year) ?></h3>
            <table><tbody>
              <?php foreach ($yearPosts as $post): ?>
                <tr>
                  <td width="80" style="text-align:right"><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('m-d', (int)$post['published_at'])) ?></time></td>
                  <td><a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?> - <?= h((string)approved_comment_count((int)$post['id'])) ?></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody></table>
          <?php endforeach; ?>
        <?php else: ?><p><?= h(sblog_t('归档还是空的。')) ?></p><?php endif; ?>
      </article>
    </section>
    <?php
    return (string)ob_get_clean();
}

function adams_render_tags(): string
{
    $tags = tag_index_data();
    ob_start();
    ?>
    <section class="container">
      <article class="post_article archives tag-archive" itemscope itemtype="https://schema.org/Article">
        <h3><?= h(sblog_t('标签')) ?></h3>
        <?php if ($tags): ?><table><tbody>
          <?php foreach ($tags as $tag): ?>
            <tr><td width="80">#</td><td><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><?= h((string)$tag['label']) ?> - <?= h((string)$tag['count']) ?></a></td></tr>
          <?php endforeach; ?>
        </tbody></table><?php else: ?><p><?= h(sblog_t('还没有标签。')) ?></p><?php endif; ?>
      </article>
    </section>
    <?php
    return (string)ob_get_clean();
}

function adams_render_tag_page(string $slug): string
{
    return adams_render_post_list(fetch_posts_by_tag_slug($slug));
}

function adams_render_category_page(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) {
        return '';
    }

    return adams_render_post_list(all_rows(
        'SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC',
        ['post', (int)$category['id'], 'published', time()]
    ));
}

function adams_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    ?>
    <section class="container">
      <article class="post_article" itemscope itemtype="https://schema.org/Article">
        <?php if ($links): ?><ul class="links">
          <?php foreach ($links as $link): ?>
            <?php
            $host = (string)(parse_url((string)$link['url'], PHP_URL_HOST) ?: $link['url']);
            $icon = trim((string)$link['icon_url']);
            if ($icon === '') {
                $icon = 'https://www.google.com/s2/favicons?domain=' . rawurlencode($host);
            }
            ?>
            <li>
              <a href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer">
                <strong><?= h((string)$link['name']) ?></strong>
                <?php if (trim((string)$link['description']) !== ''): ?><br><small><?= h((string)$link['description']) ?></small><?php endif; ?>
              </a>
              <div class="bg" style="background-image:url('<?= h($icon) ?>')"></div>
            </li>
          <?php endforeach; ?>
        </ul><?php else: ?><p><?= h(sblog_t('还没有添加友情链接。')) ?></p><?php endif; ?>
      </article>
    </section>
    <?php
    return (string)ob_get_clean();
}

function adams_adapt_article_content(string $content): string
{
    $content = preg_replace('#\s*<h1 class="post-title"[^>]*>.*?</h1>\s*#s', '', $content, 1) ?? $content;
    $content = preg_replace('#\s*<div class="meta">.*?</div>\s*#s', '', $content, 1) ?? $content;
    $content = preg_replace('/<article>/', '<article class="post_article" itemscope itemtype="https://schema.org/Article">', $content, 1) ?? $content;
    $content = str_replace('class="pagination"', 'class="pagination nearbypost"', $content);

    return '<section class="container adams-core">' . $content . '</section>';
}

add_theme_filter('body_class', static function (string $classes): string {
    return trim($classes . ' adams-theme');
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
        return adams_render_category_page((string)($_GET['slug'] ?? '')) ?: $content;
    }
    if ($action === 'tag') {
        return adams_render_tag_page((string)($_GET['slug'] ?? ''));
    }
    if ($active === 'archives') {
        return adams_render_archive();
    }
    if ($active === 'tags') {
        return adams_render_tags();
    }
    if ($active === 'links') {
        return adams_render_links();
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '') && $action !== 'post') {
        return adams_render_home();
    }
    if ($action === 'post' || $action === 'page' || str_starts_with($active, 'page:')) {
        return adams_adapt_article_content($content);
    }
    return $content;
});
