<?php

declare(strict_types=1);

function paper_owner(): array
{
    return one('SELECT nickname, username, avatar_url, website_url, github_url, qq_url, wechat_url, weibo_url, x_url, telegram_url, mastodon_url, bilibili_url, instagram_url, tiktok_url, signature FROM users ORDER BY id ASC LIMIT 1') ?? [];
}

function paper_social_links(array $owner): array
{
    $links = [];
    foreach (social_profile_definitions() as $key => $definition) {
        $safeUrl = safe_link_url((string)($owner[$definition['column']] ?? ''));
        if ($safeUrl !== '#') {
            $links[] = ['url' => $safeUrl, 'label' => (string)$definition['label'], 'key' => (string)$key];
        }
    }
    return $links;
}

function paper_social_content(string $key, string $label): string
{
    if (in_array($key, ['github', 'qq', 'wechat', 'weibo', 'x', 'telegram', 'mastodon', 'bilibili', 'instagram', 'tiktok'], true)) {
        return '<img src="' . h(theme_asset_url('assets/icons/' . $key . '.svg')) . '" width="24" height="24" alt="">';
    }
    return paper_icon('globe');
}

function paper_post_list(array $posts): string
{
    ob_start();
    ?>
    <div class="paper-post-list">
      <?php foreach ($posts as $index => $post): ?>
        <?php $publishedAt = (int)$post['published_at']; ?>
        <article class="paper-post-entry" style="--paper-order:<?= h((string)min($index, 8)) ?>">
          <?php if (!empty($post['is_pinned'])): ?><span class="paper-featured"><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
          <h2><a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a></h2>
          <time datetime="<?= h(date(DATE_ATOM, $publishedAt)) ?>"><?= h(date('Y-m-d', $publishedAt)) ?></time>
        </article>
      <?php endforeach; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function paper_pager(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    ob_start();
    ?>
    <nav class="paper-pager" aria-label="<?= h(sblog_t('分页')) ?>">
      <?php if ($page > 1): ?>
        <a class="paper-button paper-button--previous" href="<?= h(home_page_url($page - 1)) ?>"><span aria-hidden="true">&larr;</span> <?= h(sblog_t('上一页')) ?></a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a class="paper-button paper-button--next" href="<?= h(home_page_url($page + 1)) ?>"><?= h(sblog_t('下一页')) ?> <span aria-hidden="true">&rarr;</span></a>
      <?php endif; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function paper_render_home(): string
{
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);
    $owner = paper_owner();
    $ownerName = trim((string)($owner['nickname'] ?? '')) ?: trim((string)($owner['username'] ?? '')) ?: setting('site_name');
    $websiteUrl = safe_link_url((string)($owner['website_url'] ?? ''));
    $signature = trim((string)($owner['signature'] ?? '')) ?: trim(setting('site_tagline'));
    $socialLinks = paper_social_links($owner);

    ob_start();
    if ($page === 1): ?>
      <section class="paper-profile">
        <span class="paper-avatar-shell">
          <img src="<?= h(theme_logo_url()) ?>" width="72" height="72" alt="<?= h($ownerName) ?>" decoding="async" fetchpriority="high">
        </span>
        <div class="paper-profile__copy">
          <h1><?php if ($websiteUrl !== '#'): ?><a class="paper-profile__website" href="<?= h($websiteUrl) ?>" target="_blank" rel="me noopener noreferrer"><?= h($ownerName) ?></a><?php else: ?><?= h($ownerName) ?><?php endif; ?></h1>
          <?php if ($signature !== ''): ?><p><?= h($signature) ?></p><?php endif; ?>
          <?php if ($socialLinks): ?>
            <nav class="paper-profile__socials" aria-label="<?= h(sblog_t('个人链接')) ?>">
              <?php foreach ($socialLinks as $social): ?>
                <a href="<?= h((string)$social['url']) ?>" target="_blank" rel="me noopener noreferrer" aria-label="<?= h((string)$social['label']) ?>" title="<?= h((string)$social['label']) ?>"><?= paper_social_content((string)$social['key'], (string)$social['label']) ?></a>
              <?php endforeach; ?>
            </nav>
          <?php endif; ?>
        </div>
      </section>
    <?php endif;

    if ($posts) {
        echo paper_post_list($posts);
        echo paper_pager($page, $totalPages);
    } else { ?>
      <div class="empty-notice"><p><?= h(sblog_t('还没有已发布的文章。')) ?></p><?php if (is_admin()): ?><p><a href="<?= h(url_for('write')) ?>"><?= h(sblog_t('写第一篇文章')) ?></a></p><?php endif; ?></div>
    <?php }
    return (string)ob_get_clean();
}

function paper_render_archive(): string
{
    $years = [];
    foreach (fetch_archive_posts() as $post) {
        $years[date('Y', (int)$post['published_at'])][] = $post;
    }

    ob_start();
    ?>
    <header class="paper-page-header">
      <h1><?= h(sblog_t('归档')) ?></h1>
      <p><?= h(sblog_tn('共有 {count} 篇文章', count_published_posts())) ?></p>
    </header>
    <?php if ($years): ?>
      <div class="paper-archive">
        <?php foreach ($years as $year => $posts): ?>
          <section class="paper-archive-year">
            <h2><?= h((string)$year) ?></h2>
            <ol>
              <?php foreach ($posts as $post): ?>
                <li>
                  <time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('m-d', (int)$post['published_at'])) ?></time>
                  <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a>
                </li>
              <?php endforeach; ?>
            </ol>
          </section>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('归档还是空的。')) ?></p></div>
    <?php endif;
    return (string)ob_get_clean();
}

function paper_render_listing(string $title, string $description, array $posts): string
{
    ob_start();
    ?>
    <header class="paper-page-header">
      <h1><?= h($title) ?></h1>
      <?php if ($description !== ''): ?><p><?= h($description) ?></p><?php endif; ?>
    </header>
    <?php if ($posts): ?>
      <?= paper_post_list($posts) ?>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('这里还没有文章。')) ?></p></div>
    <?php endif;
    return (string)ob_get_clean();
}

function paper_render_tag(string $slug): string
{
    $label = tag_label_by_slug($slug);
    $posts = fetch_posts_by_tag_slug($slug);
    if ($label === null && $posts === []) {
        return '';
    }
    $label = $label ?? $slug;
    return paper_render_listing($label, sblog_tn('{count} 篇文章', count($posts)), $posts);
}

function paper_render_tags(): string
{
    $tags = tag_index_data();
    $counts = array_map(static fn(array $tag): int => (int)$tag['count'], $tags);
    $minimum = $counts === [] ? 0 : min($counts);
    $maximum = $counts === [] ? 0 : max($counts);

    ob_start();
    ?>
    <header class="paper-page-header">
      <h1><?= h(sblog_t('标签')) ?></h1>
      <?php if ($tags): ?><p><?= h(sblog_tn('共有 {count} 个标签', count($tags))) ?></p><?php endif; ?>
    </header>
    <?php if ($tags): ?>
      <nav class="tag-cloud" aria-label="<?= h(sblog_t('标签')) ?>">
        <?php foreach ($tags as $tag): ?>
          <?php
          $count = (int)$tag['count'];
          $weight = $maximum === $minimum ? 3 : 1 + (int)round(($count - $minimum) * 4 / ($maximum - $minimum));
          ?>
          <a class="tag-index-link" data-weight="<?= h((string)$weight) ?>" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>">
            <span><?= h((string)$tag['label']) ?></span>
            <strong aria-label="<?= h(sblog_tn('{count} 篇文章', $count)) ?>"><?= h((string)$count) ?></strong>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('还没有标签。')) ?></p></div>
    <?php endif;
    return (string)ob_get_clean();
}

function paper_render_category(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) {
        return '';
    }
    $posts = all_rows(
        'SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC',
        ['post', (int)$category['id'], 'published', time()]
    );
    $description = trim((string)$category['description']) ?: sblog_tn('{count} 篇文章', count($posts));
    return paper_render_listing((string)$category['name'], $description, $posts);
}

function paper_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    ?>
    <header class="paper-page-header">
      <h1><?= h(sblog_t('友链')) ?></h1>
      <p><?= h(sblog_t('一些值得访问的网站与朋友。')) ?></p>
    </header>
    <?php if ($links): ?>
      <div class="paper-links">
        <?php foreach ($links as $link): ?>
          <?php $name = trim((string)$link['name']); $icon = trim((string)$link['icon_url']); ?>
          <a class="paper-link" href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer">
            <span class="paper-link__avatar" aria-hidden="true">
              <span><?= h(str_sub_u($name, 0, 1)) ?></span>
              <?php if ($icon !== ''): ?><img src="<?= h($icon) ?>" width="44" height="44" alt="" loading="lazy" decoding="async" onerror="this.remove()"><?php endif; ?>
            </span>
            <span class="paper-link__copy"><strong><?= h($name) ?></strong><small><?= h(trim((string)$link['description']) ?: sblog_t('欢迎访问这个网站')) ?></small></span>
            <span class="paper-link__arrow" aria-hidden="true">&nearr;</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-notice"><p><?= h(sblog_t('还没有添加友情链接。')) ?></p></div>
    <?php endif;
    return (string)ob_get_clean();
}

function paper_icon(string $name): string
{
    $icons = [
        'globe' => '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>',
        'rss' => '<path d="M5 11a8 8 0 0 1 8 8M5 5a14 14 0 0 1 14 14"></path><circle cx="6" cy="18" r="1"></circle>',
        'user' => '<circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"></path>',
        'sun' => '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"></path>',
    ];
    if (!isset($icons[$name])) {
        return '';
    }
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
}

add_theme_filter('body_class', static function (string $classes, array $context): string {
    return trim($classes . ' paper-theme');
});

add_theme_filter('comments_labels', static function (array $labels, array $context): array {
    return array_replace($labels, [
        'title' => sblog_t('评论'),
        'form_title' => sblog_t('写下评论'),
        'submit' => sblog_t('提交评论'),
        'cancel_reply' => sblog_t('取消'),
        'cancel_reply_aria' => sblog_t('取消回复'),
        'empty' => sblog_t('还没有评论'),
        'closed' => sblog_t('评论已关闭'),
    ]);
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');

    if ($active === 'home' && $action === 'category') {
        return paper_render_category((string)($_GET['slug'] ?? '')) ?: $content;
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '')) {
        return paper_render_home();
    }
    if ($active === 'archives') {
        return paper_render_archive();
    }
    if ($active === 'tags' && $action === 'tag') {
        return paper_render_tag((string)($_GET['slug'] ?? '')) ?: $content;
    }
    if ($active === 'tags') {
        return paper_render_tags();
    }
    if ($active === 'links') {
        return paper_render_links();
    }

    return preg_replace(
        '/(<a\b[^>]*class="[^"]*\bpost-tag\b[^"]*"[^>]*>)#/i',
        '$1',
        $content
    ) ?? $content;
});
