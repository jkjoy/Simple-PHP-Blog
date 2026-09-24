<?php

declare(strict_types=1);

function candy_icon(string $name): string
{
    $paths = [
        'arrow' => '<path d="M4 12h15m-6-6 6 6-6 6"/>',
        'up-right' => '<path d="M5 19 19 5M7 5h12v12"/>',
        'up' => '<path d="M12 20V4m-7 7 7-7 7 7"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="M5 5 19 19M19 5 5 19"/>',
        'rss' => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1" fill="currentColor" stroke="none"/>',
        'spark' => '<path d="m12 2 2.2 7.8L22 12l-7.8 2.2L12 22l-2.2-7.8L2 12l7.8-2.2L12 2Z"/>',
    ];
    return isset($paths[$name])
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>'
        : '';
}

function candy_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        return $url === '#' ? '' : $url;
    }
    return '';
}

function candy_post_cards(array $posts): string
{
    ob_start();
    ?>
    <div class="candy-grid">
      <?php foreach ($posts as $index => $post): ?>
        <?php
        $url = url_for('post', ['slug' => (string)$post['slug']]);
        $cover = candy_post_cover($post);
        $excerpt = trim((string)($post['excerpt'] ?? '')) ?: derive_excerpt((string)($post['content'] ?? ''), 116);
        $tags = tag_descriptors($post);
        $tone = ($index % 6) + 1;
        ?>
        <article class="candy-card candy-tone-<?= $tone ?> candy-reveal">
          <div class="candy-card__art" aria-hidden="true">
            <?php if ($cover !== ''): ?><img src="<?= h($cover) ?>" alt="" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async" onerror="this.remove()"> <?php endif; ?>
            <svg class="candy-card__pattern" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
              <defs><pattern id="candy-dots-<?= $index ?>" width="19" height="19" patternUnits="userSpaceOnUse"><circle cx="3" cy="3" r="2" fill="#0a0a0a"/></pattern></defs>
              <rect x="0" y="0" width="400" height="220" fill="url(#candy-dots-<?= $index ?>)" opacity=".14"/>
              <?php if ($cover === ''): ?>
              <path d="m155 220 245-62v62Z" fill="var(--tone-light)" stroke="#000" stroke-width="3"/>
              <circle cx="302" cy="106" r="80" fill="none" stroke="#0a0a0a" stroke-width="3"/>
              <circle cx="302" cy="106" r="57" fill="none" stroke="#0a0a0a" stroke-width="3"/>
              <path d="M278 130 326 82m-40 0h40v40" fill="none" stroke="#0a0a0a" stroke-width="8" stroke-linejoin="miter"/>
              <?php endif; ?>
            </svg>
            <span class="candy-card__number"><?= h(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
            <?php if (!empty($post['is_pinned'])): ?><span class="candy-pin"><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
          </div>
          <div class="candy-card__body">
            <div class="candy-card__meta"><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('Y.m.d', (int)$post['published_at'])) ?></time><?php if ($tags): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tags[0]['slug']])) ?>">#<?= h((string)$tags[0]['label']) ?></a><?php endif; ?></div>
            <h3><a href="<?= h($url) ?>"><?= h((string)$post['title']) ?></a></h3>
            <p><?= h($excerpt) ?></p>
            <a class="candy-card__read" href="<?= h($url) ?>" aria-label="<?= h(sblog_t('阅读文章：{title}', ['title' => (string)$post['title']])) ?>"><span><?= h(sblog_t('继续阅读')) ?></span><?= candy_icon('arrow') ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function candy_heading(string $eyebrow, string $title, string $detail = ''): string
{
    ob_start();
    ?><header class="candy-section-head candy-reveal"><div><span class="candy-kicker"><?= h($eyebrow) ?></span><h1><?= h($title) ?></h1></div><?php if ($detail !== ''): ?><p><?= h($detail) ?></p><?php endif; ?></header><?php
    return (string)ob_get_clean();
}

function candy_empty(string $message): string
{
    return '<div class="candy-empty candy-reveal"><p>' . h($message) . '</p></div>';
}

function candy_render_home(): string
{
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $pages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);
    ob_start();
    ?><section class="candy-feed" id="candy-feed" aria-labelledby="candy-feed-title">
      <div class="candy-feed__head candy-reveal"><div><span class="candy-kicker"><?= h(sblog_t('THE LATEST')) ?></span><h2 id="candy-feed-title"><?= h($page > 1 ? sblog_t('第 {page} 页', ['page' => $page]) : sblog_t('最新文章')) ?></h2></div><a href="<?= h(url_for('archives')) ?>"><?= h(sblog_t('全部归档')) ?><?= candy_icon('up-right') ?></a></div>
      <?= $posts ? candy_post_cards($posts) : candy_empty(sblog_t('还没有已发布的文章。')) ?>
      <?php if ($pages > 1): ?><nav class="candy-pager" aria-label="<?= h(sblog_t('分页')) ?>">
        <?php if ($page > 1): ?><a href="<?= h(home_page_url($page - 1)) ?>"><?= h(sblog_t('上一页')) ?></a><?php endif; ?>
        <span><?= h((string)$page) ?> / <?= h((string)$pages) ?></span>
        <?php if ($page < $pages): ?><a href="<?= h(home_page_url($page + 1)) ?>"><?= h(sblog_t('下一页')) ?><?= candy_icon('arrow') ?></a><?php endif; ?>
      </nav><?php endif; ?>
    </section><?php
    return (string)ob_get_clean();
}

function candy_render_listing(string $eyebrow, string $title, string $detail, array $posts): string
{
    return candy_heading($eyebrow, $title, $detail)
        . ($posts ? candy_post_cards($posts) : candy_empty(sblog_t('这里还没有文章。')));
}

function candy_render_archives(): string
{
    $years = [];
    foreach (fetch_archive_posts() as $post) {
        $years[date('Y', (int)$post['published_at'])][] = $post;
    }
    ob_start();
    echo candy_heading('ALL STORIES', sblog_t('归档'), sblog_tn('共 {count} 篇文章', count_published_posts()));
    foreach ($years as $year => $posts): ?>
      <section class="candy-archive candy-reveal" aria-label="<?= h((string)$year) ?>">
        <h2><?= h((string)$year) ?></h2><ol>
          <?php foreach ($posts as $post): ?><li><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('m.d', (int)$post['published_at'])) ?></time><a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a><?= candy_icon('up-right') ?></li><?php endforeach; ?>
        </ol>
      </section>
    <?php endforeach;
    if (!$years) echo candy_empty(sblog_t('归档还是空的。'));
    return (string)ob_get_clean();
}

function candy_render_tags(): string
{
    $tags = tag_index_data();
    ob_start();
    echo candy_heading('PICK A TOPIC', sblog_t('标签'), sblog_tn('共 {count} 个标签', count($tags)));
    if ($tags): ?><div class="candy-tag-grid">
      <?php foreach ($tags as $index => $tag): ?><a class="candy-tag candy-tone-<?= ($index % 6) + 1 ?> candy-reveal" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><span>#</span><strong><?= h((string)$tag['label']) ?></strong><small><?= h(sblog_tn('{count} 篇文章', (int)$tag['count'])) ?></small><?= candy_icon('up-right') ?></a><?php endforeach; ?>
    </div><?php else: echo candy_empty(sblog_t('还没有标签。')); endif;
    return (string)ob_get_clean();
}

function candy_render_categories(): string
{
    $categories = fetch_categories();
    ob_start();
    echo candy_heading('BROWSE BY CATEGORY', sblog_t('分类'));
    if ($categories): ?><div class="candy-tag-grid">
      <?php foreach ($categories as $index => $category): ?><a class="candy-tag candy-tone-<?= ($index % 6) + 1 ?> candy-reveal" href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"><strong><?= h((string)$category['name']) ?></strong><small><?= h(sblog_tn('{count} 篇文章', (int)$category['post_count'])) ?></small><?= candy_icon('up-right') ?></a><?php endforeach; ?>
    </div><?php else: echo candy_empty(sblog_t('还没有分类。')); endif;
    return (string)ob_get_clean();
}

function candy_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    echo candy_heading('GOOD COMPANY', sblog_t('友链'), sblog_t('一些值得访问的网站与朋友。'));
    if ($links): ?><div class="candy-link-grid">
      <?php foreach ($links as $index => $link): ?><a class="candy-link candy-tone-<?= ($index % 6) + 1 ?> candy-reveal" href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer"><span class="candy-link__mark"><?= h(str_sub_u((string)$link['name'], 0, 1)) ?></span><strong><?= h((string)$link['name']) ?></strong><small><?= h((string)$link['description']) ?></small><?= candy_icon('up-right') ?></a><?php endforeach; ?>
    </div><?php else: echo candy_empty(sblog_t('还没有添加友情链接。')); endif;
    return (string)ob_get_clean();
}

add_theme_filter('body_class', static fn(string $classes): string => trim($classes . ' candy-theme'));

add_theme_filter('comments_labels', static function (array $labels): array {
    return array_replace($labels, [
        'title' => sblog_t('评论'),
        'form_title' => sblog_t('写下评论'),
        'submit' => sblog_t('提交评论'),
        'cancel_reply' => sblog_t('取消回复'),
        'cancel_reply_aria' => sblog_t('取消回复'),
        'empty' => sblog_t('还没有评论'),
        'closed' => sblog_t('评论已关闭'),
    ]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? 'home');
    $slug = (string)($_GET['slug'] ?? '');
    if ($action === 'category') {
        $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
        if ($category) {
            $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', (int)$category['id'], 'published', time()]);
            return candy_render_listing('CATEGORY', (string)$category['name'], (string)$category['description'], $posts);
        }
    }
    if ($action === 'tag') {
        $label = tag_label_by_slug($slug);
        $posts = fetch_posts_by_tag_slug($slug);
        if ($label !== null || $posts) return candy_render_listing('TOPIC', '#' . ($label ?? $slug), sblog_tn('{count} 篇文章', count($posts)), $posts);
    }
    if ($active === 'home' && $action === 'home') return candy_render_home();
    if ($active === 'archives') return candy_render_archives();
    if ($active === 'tags') return candy_render_tags();
    if ($active === 'categories') return candy_render_categories();
    if ($active === 'links') return candy_render_links();
    return $content;
});
