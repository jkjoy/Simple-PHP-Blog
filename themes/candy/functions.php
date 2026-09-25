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
            <?php if ($cover !== ''): ?><img src="<?= h($cover) ?>" alt="" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async" onerror="this.remove()"> <?php else: ?>
              <svg class="candy-card__pattern" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
                <defs>
                  <pattern id="candy-dots-<?= $index ?>" width="19" height="19" patternUnits="userSpaceOnUse"><circle cx="3" cy="3" r="2" fill="#0a0a0a"/></pattern>
                  <pattern id="candy-lines-<?= $index ?>" width="15" height="15" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="15" height="15" fill="var(--tone-light)"/><rect width="4" height="15" fill="#000"/></pattern>
                </defs>
                <circle cx="330" cy="48" r="72" fill="url(#candy-dots-<?= $index ?>)" opacity=".32"/>
                <path d="M0 192h400" stroke="#000" stroke-width="3"/>
                <?php if ($tone === 1): ?>
                  <path d="M95 31h190v161H95z" fill="#fff" stroke="#000" stroke-width="4" transform="rotate(-8 190 111)"/>
                  <path d="M122 30h190v161H122z" fill="var(--tone)" stroke="#000" stroke-width="4" transform="rotate(5 217 111)"/>
                  <path d="M160 70h120v95H160z" fill="#FFE135" stroke="#000" stroke-width="4"/>
                  <path d="M179 91h82m-82 20h67m-67 20h82" stroke="#000" stroke-width="5"/>
                <?php elseif ($tone === 2): ?>
                  <circle cx="202" cy="110" r="75" fill="var(--tone)" stroke="#000" stroke-width="4"/>
                  <path d="M125 115c38-29 72-29 111 0s72 29 111 0v77H125z" fill="#fff" stroke="#000" stroke-width="4"/>
                  <path d="M64 69h55v55H64z" fill="url(#candy-lines-<?= $index ?>)" stroke="#000" stroke-width="4"/>
                <?php elseif ($tone === 3): ?>
                  <circle cx="226" cy="109" r="78" fill="var(--tone)" stroke="#000" stroke-width="4"/>
                  <path d="M169 117h114l-12 59H181z" fill="#fff" stroke="#000" stroke-width="4"/>
                  <path d="M280 128h20c27 0 27 38-6 38h-18M194 92c-11-12 8-23 0-34m31 34c-11-12 8-23 0-34m31 34c-11-12 8-23 0-34" fill="none" stroke="#000" stroke-width="4"/>
                <?php elseif ($tone === 4): ?>
                  <path d="M108 37h195v150H108z" fill="var(--tone)" stroke="#000" stroke-width="4"/>
                  <path d="M122 53h165v102H122z" fill="#fff" stroke="#000" stroke-width="4"/>
                  <path d="M151 132V85h32v47m15 0V69h39v63m16 0V96h19v36" fill="var(--tone-light)" stroke="#000" stroke-width="4"/>
                <?php elseif ($tone === 5): ?>
                  <path d="M136 51h140l-16 126H152z" fill="#fff" stroke="#000" stroke-width="4"/>
                  <path d="M181 79c15-20 38-20 53 0 15-20 38-20 53 0-3 37-53 70-53 70s-50-33-53-70Z" fill="var(--tone)" stroke="#000" stroke-width="4"/>
                  <path d="M111 78h-30m30 21H65m267 37h37m-37 21h55" stroke="#000" stroke-width="5"/>
                <?php else: ?>
                  <path d="M100 61h210v126H100z" fill="#fff" stroke="#000" stroke-width="4" transform="rotate(-7 205 124)"/>
                  <path d="M109 69h210v126H109z" fill="var(--tone)" stroke="#000" stroke-width="4"/>
                  <path d="m109 70 105 83L319 70M109 195l83-69m127 69-83-69" fill="none" stroke="#000" stroke-width="4"/>
                <?php endif; ?>
              </svg>
            <?php endif; ?>
            <span class="candy-card__number"><?= h(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
            <?php if (!empty($post['is_pinned'])): ?><span class="candy-pin"><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
          </div>
          <div class="candy-card__body">
            <div class="candy-card__meta"><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('Y.m.d', (int)$post['published_at'])) ?></time><?php if ($tags): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tags[0]['slug']])) ?>">#<?= h((string)$tags[0]['label']) ?></a><?php endif; ?></div>
            <h3><a href="<?= h($url) ?>"><?= h((string)$post['title']) ?></a></h3>
            <p><?= h($excerpt) ?></p>
            <a class="candy-card__read" href="<?= h($url) ?>" aria-label="<?= h(sblog_t('阅读文章：{title}', ['title' => (string)$post['title']])) ?>"><span><?= h(sblog_t('继续阅读')) ?></span><span class="candy-card__arrow"><?= candy_icon('arrow') ?></span></a>
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

function candy_category_nav(string $currentSlug = ''): string
{
    $categories = all_rows(
        'SELECT c.name, c.slug, COUNT(p.id) AS post_count FROM categories c
         JOIN posts p ON p.category_id = c.id AND p.kind = ? AND p.status = ? AND p.published_at <= ?
         GROUP BY c.id ORDER BY c.sort_order ASC, c.id DESC',
        ['post', 'published', time()]
    );
    if (count($categories) < 2) {
        return '';
    }

    ob_start();
    ?>
    <nav class="candy-category-nav candy-reveal" aria-label="<?= h(sblog_t('文章分类')) ?>">
      <a href="<?= h(url_for('home')) ?>"<?= $currentSlug === '' ? ' aria-current="page"' : '' ?>><?= h(sblog_t('全部')) ?></a>
      <?php foreach ($categories as $category): ?>
        <a href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"<?= $currentSlug === (string)$category['slug'] ? ' aria-current="page"' : '' ?>><?= h((string)$category['name']) ?><span><?= h((string)$category['post_count']) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
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
      <?= candy_category_nav() ?>
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
            return candy_heading('CATEGORY', (string)$category['name'], (string)$category['description'])
                . candy_category_nav($slug)
                . ($posts ? candy_post_cards($posts) : candy_empty(sblog_t('这里还没有文章。')));
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
