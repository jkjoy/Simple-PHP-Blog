<?php

declare(strict_types=1);

function clarity_icon(string $name, string $label = ''): string
{
    $paths = [
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'rss' => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>',
        'top' => '<path d="m6 15 6-6 6 6"/>',
        'reply' => '<path d="m9 17-5-5 5-5M20 18v-2a4 4 0 0 0-4-4H4"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    $title = $label !== '' ? '<title>' . h($label) . '</title>' : '';
    return '<svg class="icon clarity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="' . ($label === '' ? 'true' : 'false') . '">' . $title . $paths[$name] . '</svg>';
}

function clarity_timestamp(array $post): int
{
    return (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
}

function clarity_reading_minutes(array $post): int
{
    $text = html_entity_decode(strip_tags((string)($post['content'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    preg_match_all('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text, $cjk);
    $nonCjk = preg_replace('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', ' ', $text) ?? $text;
    preg_match_all('/[A-Za-z0-9]+(?:[\'\-][A-Za-z0-9]+)*/u', $nonCjk, $words);
    return max(1, (int)ceil(max(count($cjk[0]) / 500, count($words[0]) / 200)));
}

function clarity_post_meta(array $post, bool $withTags = true): string
{
    $timestamp = clarity_timestamp($post);
    $minutes = clarity_reading_minutes($post);
    ob_start();
    ?>
    <div class="post_meta">
      <span><?= clarity_icon('calendar') ?></span>
      <span class="post_date"><time datetime="<?= h(date(DATE_ATOM, $timestamp)) ?>"><?= h(date('M j, Y', $timestamp)) ?></time></span>
      <span class="post_meta_sep">&nbsp;·</span>
      <span class="post_reading_time"><?= h($minutes === 1 ? '约 1 分钟阅读' : '约 ' . $minutes . ' 分钟阅读') ?></span>
      <?php if ($withTags && tag_descriptors($post)): ?>
        <span class="post_meta_sep">&nbsp;·</span>
        <span class="post_tags_inline">
          <?php foreach (tag_descriptors($post) as $tag): ?>
            <a class="post_tag button button_translucent" href="<?= h(url_for('tag', ['slug' => $tag['slug']])) ?>"><?= h($tag['label']) ?></a>
          <?php endforeach; ?>
        </span>
      <?php endif; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function clarity_render_excerpt(array $post): string
{
    $excerpt = trim((string)($post['excerpt'] ?? '')) ?: derive_excerpt((string)($post['content'] ?? ''), 200);
    ob_start();
    ?>
    <li class="post_item">
      <div class="excerpt">
        <div class="excerpt_header">
          <h3 class="post_link"><a href="<?= h(content_permalink($post)) ?>"><?= h((string)$post['title']) ?><?php if ((int)($post['is_pinned'] ?? 0) === 1): ?><span class="post_sticky_badge">[置顶]</span><?php endif; ?></a></h3>
          <?= clarity_post_meta($post) ?>
        </div>
        <div class="excerpt_footer"><div class="pale"><p><?= h($excerpt) ?></p><br><a class="excerpt_more button" href="<?= h(content_permalink($post)) ?>">阅读全文</a></div></div>
      </div>
    </li>
    <?php
    return (string)ob_get_clean();
}

function clarity_render_post_list(array $posts, bool $paginate = false): string
{
    ob_start(); ?>
    <div><ul class="posts" id="posts"><?php foreach ($posts as $post): ?><?= clarity_render_excerpt($post) ?><?php endforeach; ?></ul>
    <?php if ($paginate):
        $page = max(1, (int)($_GET['p'] ?? 1));
        $perPage = max(1, (int)setting('posts_per_page', '6'));
        $pages = max(1, (int)ceil(count_published_posts() / $perPage)); ?>
      <ul class="pagination">
        <?php if ($page > 1): ?><li class="page-item prev"><a href="<?= h(home_page_url($page - 1)) ?>" aria-label="上一页">&laquo;</a></li><?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?><li class="page-item<?= $i === $page ? ' active' : '' ?>"><a href="<?= h(home_page_url($i)) ?>"><?= h((string)$i) ?></a></li><?php endfor; ?>
        <?php if ($page < $pages): ?><li class="page-item next"><a href="<?= h(home_page_url($page + 1)) ?>" aria-label="下一页">&raquo;</a></li><?php endif; ?>
      </ul>
    <?php endif; ?></div>
    <?php return (string)ob_get_clean();
}

function clarity_sidebar(bool $article = false): string
{
    $recent = fetch_published_posts(5, 0);
    $categories = array_values(array_filter(fetch_categories(), static fn(array $row): bool => (int)$row['post_count'] > 0));
    $tags = array_slice(tag_index_data(), 0, 20);
    ob_start(); ?>
    <aside class="sidebar"><section class="sidebar_inner"><br>
      <?php if ($article): ?><section class="toc_widget" hidden><h2 class="mt-4">目录</h2><nav class="toc_nav" aria-label="目录"><ol class="toc_list"></ol></nav></section><?php endif; ?>
      <form class="search" method="get" action="<?= h(url_for('home')) ?>" role="search"><?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="home"><?php endif; ?><label class="sr-only" for="clarity-search">搜索</label><input id="clarity-search" class="search_field" type="search" name="s" placeholder="搜索" value="<?= h((string)($_GET['s'] ?? '')) ?>"></form>
      <?php if (trim(setting('home_intro')) !== ''): ?><h2 class="mt-4"><?= h(setting('author_name', $GLOBALS['siteName'] ?? '')) ?></h2><div class="author_bio"><p><?= h(setting('home_intro')) ?></p></div><?php endif; ?>
      <?php if ($recent): ?><h2 class="mt-4">最近文章</h2><ul class="flex-column"><?php foreach ($recent as $post): ?><li><a class="nav-link" href="<?= h(content_permalink($post)) ?>"><?= h((string)$post['title']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($categories): ?><h2 class="mt-4">分类</h2><div class="post_tags_widget"><?php foreach ($categories as $category): ?><a class="post_tag button button_translucent" href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"><?= h((string)$category['name']) ?><span class="button_tally"><?= h((string)$category['post_count']) ?></span></a><?php endforeach; ?></div><?php endif; ?>
      <?php if ($tags): ?><h2 class="mt-4">标签</h2><div class="post_tags_widget"><?php foreach ($tags as $tag): ?><a class="post_tag button button_translucent" href="<?= h(url_for('tag', ['slug' => $tag['slug']])) ?>"><?= h($tag['label']) ?><span class="button_tally"><?= h((string)$tag['count']) ?></span></a><?php endforeach; ?></div><?php endif; ?>
    </section></aside>
    <?php return (string)ob_get_clean();
}

function clarity_render_home(): string
{
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    return '<div class="grid-inverse wrap content">' . clarity_render_post_list(fetch_published_posts($perPage, ($page - 1) * $perPage), true) . clarity_sidebar() . '</div>';
}

function clarity_render_taxonomy(string $title, array $posts, string $description = ''): string
{
    $heading = '<h1 class="post_title">' . h($title) . '</h1>' . ($description !== '' ? '<p class="pale">' . h($description) . '</p>' : '');
    $list = $posts ? clarity_render_post_list($posts) : '<p class="notice info">暂无文章。</p>';
    return '<div class="grid-inverse wrap content"><div>' . $heading . $list . '</div>' . clarity_sidebar() . '</div>';
}

function clarity_render_archives(): string
{
    $groups = [];
    foreach (fetch_archive_posts() as $post) {
        $year = date('Y', (int)$post['published_at']);
        $month = date('m', (int)$post['published_at']);
        $groups[$year][$month][] = $post;
    }
    ob_start(); ?>
    <div class="grid-inverse wrap content"><article class="post_content archives_page"><h1 class="post_title">归档</h1><div class="post_body"><div class="archives_wrap">
      <?php foreach ($groups as $year => $months): ?><div class="archives_year"><h2 class="mt-4 archives_year_title"><?= h($year) ?>年</h2><?php foreach ($months as $month => $posts): ?><h3 class="archives_month_title"><?= h($month) ?>月</h3><ul class="archives_list"><?php foreach ($posts as $post): ?><li class="archives_item"><a class="archives_link" href="<?= h(content_permalink($post)) ?>"><span class="archives_date"><?= h(date('m-d', (int)$post['published_at'])) ?></span><span class="archives_title"><?= h((string)$post['title']) ?></span></a></li><?php endforeach; ?></ul><?php endforeach; ?></div><?php endforeach; ?>
    </div></div></article><?= clarity_sidebar() ?></div>
    <?php return (string)ob_get_clean();
}

function clarity_render_tags(): string
{
    $tags = tag_index_data();
    ob_start(); ?>
    <div class="grid-inverse wrap content"><article class="post_content"><h1 class="post_title">标签</h1><div class="post_body"><div class="tags_index"><?php foreach ($tags as $tag): ?><a class="post_tag button button_translucent" href="<?= h(url_for('tag', ['slug' => $tag['slug']])) ?>"><?= h($tag['label']) ?><span class="button_tally"><?= h((string)$tag['count']) ?></span></a><?php endforeach; ?></div></div></article><?= clarity_sidebar() ?></div>
    <?php return (string)ob_get_clean();
}

function clarity_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start(); ?>
    <div class="grid-inverse wrap content"><article class="post_content links_page"><h1 class="post_title">链接</h1><div class="post_body"><p>一些值得访问的网站与朋友。</p><div class="links_wrap"><div class="links_grid"><?php foreach ($links as $link): ?><a class="link_card" href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer"><?php if (trim((string)$link['icon_url']) !== ''): ?><span class="link_avatar"><img src="<?= h(safe_link_url((string)$link['icon_url'])) ?>" width="48" height="48" alt=""></span><?php endif; ?><span class="link_body"><span class="link_name"><?= h((string)$link['name']) ?></span><span class="link_desc"><?= h((string)$link['description']) ?></span></span></a><?php endforeach; ?></div></div></div></article><?= clarity_sidebar() ?></div>
    <?php return (string)ob_get_clean();
}

function clarity_current_content(array $context): ?array
{
    $action = (string)($_GET['a'] ?? '');
    if ($action === 'post') {
        return fetch_post_by_identifier((string)($_GET['slug'] ?? ''), true);
    }
    if ($action === 'page') {
        return fetch_page_by_identifier((string)($_GET['slug'] ?? ''), true);
    }
    $active = (string)($context['active'] ?? '');
    return str_starts_with($active, 'page:') ? fetch_page_by_identifier(substr($active, 5), true) : null;
}

function clarity_adapt_comments(string $comments): string
{
    $comments = str_replace('class="comments"', 'class="post_comments"', $comments);
    $comments = str_replace('class="comments__head"', 'class="comments__head comment_form_header"', $comments);
    $comments = str_replace('class="section-header"', 'class="mt-4"', $comments);
    $comments = str_replace('class="comment-list"', 'class="comment-thread"', $comments);
    $comments = str_replace('class="comment-item', 'class="comment_item', $comments);
    $comments = str_replace('class="comment-item__meta"', 'class="comment_header"', $comments);
    $comments = str_replace('class="comment-item__avatar"', 'class="avatar comment-item__avatar"', $comments);
    $comments = str_replace('class="comment-item__body"', 'class="comment_body"', $comments);
    $comments = str_replace('class="comment-reply-button"', 'class="comment-reply-button button button_translucent"', $comments);
    $comments = str_replace('class="comment-form"', 'class="comment_form comment_card"', $comments);
    $comments = str_replace('class="comment-form__grid"', 'class="comment_form_grid"', $comments);
    $comments = str_replace('class="comment-field', 'class="comment_field', $comments);
    $comments = str_replace('class="terminal-action"', 'class="button"', $comments);
    return $comments;
}

function clarity_adapt_article(string $content, array $context): string
{
    $post = clarity_current_content($context);
    if (!$post) {
        return $content;
    }
    $isPost = content_kind($post) === 'post';
    $content = preg_replace('/<article>/', '<article class="post_content">', $content, 1) ?? $content;
    $content = str_replace('class="post-title"', 'class="post_title"', $content);
    $content = str_replace('class="post-content"', 'class="post_body"', $content);
    if ($isPost) {
        $content = preg_replace('/<div class="meta">.*?<\/div>/s', clarity_post_meta($post), $content, 1) ?? $content;
    }
    $content = clarity_adapt_comments($content);
    $content = str_replace('class="pagination"', 'class="pagination post_navigation"', $content);
    return '<div class="grid-inverse wrap content">' . '<div>' . $content . '</div>' . clarity_sidebar(true) . '</div>';
}

add_theme_filter('body_class', static fn(string $classes): string => trim($classes . ' clarity-theme'));
add_theme_filter('comments_labels', static fn(array $labels): array => array_merge($labels, ['title' => '评论', 'form_title' => '添加新评论', 'submit' => '提交评论', 'cancel_reply' => '取消回复', 'empty' => '暂无评论', 'closed' => '评论已关闭']));
add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');
    if ($action === 'category') {
        $category = one('SELECT * FROM categories WHERE slug = ?', [(string)($_GET['slug'] ?? '')]);
        if (!$category) { return $content; }
        $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', (int)$category['id'], 'published', time()]);
        return clarity_render_taxonomy((string)$category['name'], $posts, (string)$category['description']);
    }
    if ($action === 'tag') {
        $slug = (string)($_GET['slug'] ?? '');
        return clarity_render_taxonomy('#' . (tag_label_by_slug($slug) ?? $slug), fetch_posts_by_tag_slug($slug));
    }
    if ($active === 'archives') { return clarity_render_archives(); }
    if ($active === 'tags') { return clarity_render_tags(); }
    if ($active === 'links') { return clarity_render_links(); }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '') && $action !== 'post') { return clarity_render_home(); }
    if ($action === 'post' || $action === 'page' || str_starts_with($active, 'page:')) { return clarity_adapt_article($content, $context); }
    return $content;
});
