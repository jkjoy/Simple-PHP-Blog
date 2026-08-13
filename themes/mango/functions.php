<?php

declare(strict_types=1);

function mango_icon(string $name, string $class = ''): string
{
    $paths = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"></path><path d="M5.5 10v10h13V10M9.5 20v-6h5v6"></path>',
        'folder' => '<path d="M3 6.5h6l2 2h10v10H3z"></path>',
        'archive' => '<rect x="4" y="4" width="16" height="16" rx="2"></rect><path d="M8 2v4M16 2v4M4 9h16"></path>',
        'tag' => '<path d="m20 13-7 7L4 11V4h7l9 9Z"></path><circle cx="8.5" cy="8.5" r="1"></circle>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1.2 1.2"></path><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1.2-1.2"></path>',
        'search' => '<circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path>',
        'sun' => '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"></path>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"></path>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"></path>',
        'comment' => '<path d="M4 5h16v12H9l-5 4z"></path>',
        'eye' => '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"></path><circle cx="12" cy="12" r="2.5"></circle>',
        'heart' => '<path d="M20.8 5.7a5.4 5.4 0 0 0-7.7 0L12 6.8l-1.1-1.1a5.4 5.4 0 0 0-7.7 7.7L12 22l8.8-8.6a5.4 5.4 0 0 0 0-7.7Z"></path>',
        'more' => '<circle cx="5" cy="12" r="1"></circle><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle>',
        'up' => '<path d="m6 15 6-6 6 6"></path>',
        'rss' => '<path d="M5 11a8 8 0 0 1 8 8M5 5a14 14 0 0 1 14 14"></path><circle cx="5" cy="19" r="1"></circle>',
        'book' => '<path d="M4 5.5A3.5 3.5 0 0 1 7.5 2H20v17H7.5a3.5 3.5 0 0 0 0 7H20"></path><path d="M4 5.5v13M8 6h8M8 10h8"></path>',
        'globe' => '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>',
        'github' => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3.3-.4 6.8-1.6 6.8-7A5.4 5.4 0 0 0 19.4 4 5 5 0 0 0 19.3.5S18.2.1 15 1.8a13.4 13.4 0 0 0-7 0C4.8.1 3.7.5 3.7.5A5 5 0 0 0 3.6 4a5.4 5.4 0 0 0-1.4 3.7c0 5.3 3.5 6.5 6.8 6.9A4.8 4.8 0 0 0 8 18v4"></path><path d="M8 19c-3 .9-3-1.5-4-2"></path>',
        'message' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path><path d="M8 9h8M8 13h5"></path>',
        'at' => '<circle cx="12" cy="12" r="4"></circle><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"></path>',
        'x-social' => '<path d="M5 4l14 16M19 4 5 20"></path>',
        'send' => '<path d="m22 2-7 20-4-9-9-4zM22 2 11 13"></path>',
        'network' => '<circle cx="12" cy="5" r="2"></circle><circle cx="5" cy="19" r="2"></circle><circle cx="19" cy="19" r="2"></circle><path d="M12 7v5M12 12l-7 5M12 12l7 5"></path>',
        'tv' => '<rect x="3" y="6" width="18" height="14" rx="2"></rect><path d="m8 2 4 4 4-4M8 12v2M16 12v2"></path>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1"></circle>',
        'music' => '<path d="M9 18V5l10-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="16" cy="16" r="3"></circle>',
        'clock' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>',
        'text' => '<path d="M4 6V3h16v3M9 21h6M12 3v18"></path>',
    ];
    $body = $paths[$name] ?? $paths['more'];
    return '<svg class="mango-icon' . ($class !== '' ? ' ' . h($class) : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

function mango_post_images(array $post, int $limit = 9): array
{
    $content = (string)($post['content'] ?? '');
    preg_match_all('/!\[[^\]]*\]\((?:<)?([^\s)>]+)(?:>)?(?:\s+["\'][^"\']*["\'])?\)|<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER);
    $images = [];
    foreach ($matches as $match) {
        $candidate = html_entity_decode((string)(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($candidate, '//')) {
            $candidate = 'https:' . $candidate;
        }
        if ($candidate !== '' && !preg_match('#^(?:https?://|/|\#)#i', $candidate) && !str_starts_with($candidate, 'data:')) {
            $candidate = app_path('/' . ltrim($candidate, './'));
        }
        $url = safe_link_url($candidate);
        if ($url !== '#' && !in_array($url, $images, true)) {
            $images[] = $url;
        }
    }

    preg_match_all('#https?://[^\s<>"\']+?\.(?:jpe?g|png|gif|webp|avif)(?:\?[^\s<>"\']*)?#i', $content, $directMatches);
    foreach ($directMatches[0] ?? [] as $candidate) {
        $url = safe_link_url(html_entity_decode((string)$candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url !== '#' && !in_array($url, $images, true)) {
            $images[] = $url;
        }
    }
    return array_slice($images, 0, max(1, $limit));
}

function mango_post_cover(array $post): string
{
    return mango_post_images($post, 1)[0] ?? theme_asset_url('assets/nopic.svg');
}

function mango_post_excerpt(array $post, int $length = 180): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? $excerpt : derive_excerpt((string)($post['content'] ?? ''), $length);
}

function mango_relative_date(int $timestamp): string
{
    $diff = max(0, time() - $timestamp);
    if ($diff < 60) return sblog_t('刚刚');
    if ($diff < 3600) return sblog_tn('{count} 分钟前', max(1, (int)floor($diff / 60)));
    if ($diff < 86400) return sblog_tn('{count} 小时前', max(1, (int)floor($diff / 3600)));
    if ($diff < 2592000) return sblog_tn('{count} 天前', max(1, (int)floor($diff / 86400)));
    if ($diff < 31536000) return sblog_tn('{count} 个月前', max(1, (int)floor($diff / 2592000)));
    return date('Y-m-d', $timestamp);
}

function mango_post_category(array $post): ?array
{
    $categoryId = (int)($post['category_id'] ?? 0);
    return $categoryId > 0 ? one('SELECT name, slug, description FROM categories WHERE id = ?', [$categoryId]) : null;
}

function mango_author(): array
{
    $owner = one('SELECT * FROM users ORDER BY id ASC LIMIT 1') ?? [];
    $owner['display_name'] = trim((string)($owner['nickname'] ?? '')) ?: ((string)($owner['username'] ?? '') ?: 'Admin');
    $avatar = safe_link_url((string)($owner['avatar_url'] ?? ''));
    $owner['avatar'] = $avatar !== '#' ? $avatar : gravatar_url((string)($owner['email'] ?? ''), 96);
    return $owner;
}

function mango_author_social_links(array $author): array
{
    $links = [];
    $website = safe_link_url((string)($author['website_url'] ?? ''));
    if ($website !== '#') {
        $links[] = ['url' => $website, 'label' => sblog_t('个人主页'), 'icon' => 'globe'];
    }
    $icons = [
        'github' => 'github', 'qq' => 'message', 'wechat' => 'comment', 'weibo' => 'at', 'x' => 'x-social',
        'telegram' => 'send', 'mastodon' => 'network', 'bilibili' => 'tv', 'instagram' => 'instagram', 'tiktok' => 'music',
    ];
    foreach (social_profile_definitions() as $key => $definition) {
        $url = safe_link_url((string)($author[$definition['column']] ?? ''));
        if ($url !== '#') {
            $links[] = ['url' => $url, 'label' => (string)$definition['label'], 'icon' => $icons[$key] ?? 'link'];
        }
    }
    return $links;
}

function mango_render_author_card(): string
{
    $author = mango_author();
    $authorId = (int)($author['id'] ?? 0);
    $description = trim((string)($author['signature'] ?? '')) ?: trim(setting('site_tagline'));
    $postCount = $authorId > 0
        ? (int)val('SELECT COUNT(*) FROM posts WHERE author_id = ? AND kind = ? AND status = ? AND published_at <= ?', [$authorId, 'post', 'published', time()])
        : count_published_posts();
    $commentCount = $authorId > 0
        ? (int)val('SELECT COUNT(*) FROM comments WHERE user_id = ? AND status = ?', [$authorId, 'approved'])
        : 0;
    $socialLinks = mango_author_social_links($author);
    $website = safe_link_url((string)($author['website_url'] ?? ''));
    ob_start();
    ?>
    <section class="author_show_box" aria-label="<?= h(sblog_t('作者信息')) ?>">
      <div class="author_show_head">
        <?php if ($website !== '#'): ?><a class="author_show_avatar" href="<?= h($website) ?>" target="_blank" rel="me noopener noreferrer"><?php endif; ?>
        <img src="<?= h((string)$author['avatar']) ?>" width="80" height="80" alt="<?= h((string)$author['display_name']) ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer">
        <?php if ($website !== '#'): ?></a><?php endif; ?>
        <h2><?= h((string)$author['display_name']) ?></h2>
        <?php if ($description !== ''): ?><p><?= h($description) ?></p><?php endif; ?>
        <?php if ($socialLinks): ?><nav class="author_show_social" aria-label="<?= h(sblog_t('个人链接')) ?>"><?php foreach ($socialLinks as $social): ?><a href="<?= h((string)$social['url']) ?>" target="_blank" rel="me noopener noreferrer" aria-label="<?= h((string)$social['label']) ?>" title="<?= h((string)$social['label']) ?>"><?= mango_icon((string)$social['icon']) ?></a><?php endforeach; ?></nav><?php endif; ?>
      </div>
      <div class="author_show_info">
        <span><?= mango_icon('book') ?><b><?= h(sblog_t('文章')) ?></b><strong><?= h((string)$postCount) ?></strong></span>
        <span><?= mango_icon('comment') ?><b><?= h(sblog_t('评论')) ?></b><strong><?= h((string)$commentCount) ?></strong></span>
      </div>
    </section>
    <?php
    return (string)ob_get_clean();
}

function mango_render_site_stats(): string
{
    $posts = all_rows('SELECT content FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['post', 'published', time()]);
    $wordCount = 0;
    foreach ($posts as $post) {
        $plain = html_entity_decode(strip_tags(markdown_to_plain((string)$post['content'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', '', $plain) ?? $plain;
        $wordCount += str_len_u($plain);
    }
    $createdAt = (int)(val('SELECT MIN(created_at) FROM (SELECT created_at FROM users UNION ALL SELECT created_at FROM posts)') ?: time());
    $runningDays = max(1, (int)floor((time() - $createdAt) / 86400) + 1);
    $linkCount = (int)val('SELECT COUNT(*) FROM links');
    $categoryCount = (int)val('SELECT COUNT(*) FROM categories');
    $tagCount = count(tag_index_data());
    $viewCount = (int)val('SELECT COALESCE(SUM(views), 0) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['post', 'published', time()]);
    $stats = [
        ['icon' => 'clock', 'label' => sblog_t('建站天数'), 'value' => number_format($runningDays), 'suffix' => sblog_t('天')],
        ['icon' => 'text', 'label' => sblog_t('总字数'), 'value' => number_format($wordCount), 'suffix' => sblog_t('字')],
        ['icon' => 'link', 'label' => sblog_t('友情链接数'), 'value' => number_format($linkCount), 'suffix' => sblog_t('个')],
        ['icon' => 'folder', 'label' => sblog_t('分类数'), 'value' => number_format($categoryCount), 'suffix' => sblog_t('个')],
        ['icon' => 'tag', 'label' => sblog_t('标签数'), 'value' => number_format($tagCount), 'suffix' => sblog_t('个')],
        ['icon' => 'eye', 'label' => sblog_t('总浏览量'), 'value' => number_format($viewCount), 'suffix' => sblog_t('次')],
    ];
    ob_start();
    ?>
    <section class="widget mango-site-stats">
      <h2 class="widget-title"><?= h(sblog_t('站点统计')) ?></h2>
      <dl><?php foreach ($stats as $stat): ?><div><?= mango_icon((string)$stat['icon']) ?><dt><?= h((string)$stat['label']) ?></dt><dd><strong><?= h((string)$stat['value']) ?></strong><span><?= h((string)$stat['suffix']) ?></span></dd></div><?php endforeach; ?></dl>
    </section>
    <?php
    return (string)ob_get_clean();
}

function mango_like_button(array $post, string $class = ''): string
{
    $postId = (int)($post['id'] ?? 0);
    $liked = visitor_liked_post($postId);
    $classes = trim('specsZan ' . $class . ($liked ? ' done' : ''));
    return '<button class="' . h($classes) . '" type="button" data-post-like data-post-id="' . h((string)$postId)
        . '" aria-pressed="' . ($liked ? 'true' : 'false') . '" aria-label="' . h($liked ? sblog_t('已点赞') : sblog_t('点赞'))
        . '" title="' . h($liked ? sblog_t('已点赞') : sblog_t('点赞')) . '">' . mango_icon('heart')
        . '<span class="count">' . h((string)post_like_count($postId)) . '</span></button>';
}

function mango_render_post_items(array $posts): string
{
    $author = mango_author();
    ob_start();
    foreach ($posts as $post):
        $permalink = content_permalink($post);
        $allImages = mango_post_images($post, PHP_INT_MAX);
        $totalImageCount = count($allImages);
        $images = array_slice($allImages, 0, 9);
        $tags = tag_descriptors($post);
        $comments = approved_comment_count((int)$post['id']);
        ?>
        <article class="post_loop<?= !empty($post['is_pinned']) ? ' is-pinned' : '' ?>" itemscope itemtype="https://schema.org/Article">
          <header class="post_loop_head">
            <div class="post_loop_head_author">
              <img class="images_author" src="<?= h((string)$author['avatar']) ?>" width="52" height="52" alt="<?= h((string)$author['display_name']) ?>" loading="lazy" decoding="async" referrerpolicy="no-referrer">
              <div class="images_author_name"><h3><?= h((string)$author['display_name']) ?></h3><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(mango_relative_date((int)$post['published_at'])) ?></time></div>
            </div>
            <a class="post_loop_more" href="<?= h($permalink) ?>" aria-label="<?= h((string)$post['title']) ?>" title="<?= h((string)$post['title']) ?>"><?= mango_icon('more') ?></a>
          </header>
          <div class="post_loop_content">
            <div class="post_loop_title_box">
              <h2 class="post_loop_title" itemprop="headline"><a href="<?= h($permalink) ?>"><?= h((string)$post['title']) ?><?php if (!empty($post['is_pinned'])): ?><span class="mango-pinned"><?= h(sblog_t('置顶')) ?></span><?php endif; ?></a></h2>
              <?php $excerpt = mango_post_excerpt($post); if ($excerpt !== ''): ?><p itemprop="description"><?= h($excerpt) ?></p><?php endif; ?>
            </div>
            <?php if ($images): ?><div class="post_images post_img_<?= h((string)min(count($images), 9)) ?>">
              <?php foreach ($images as $index => $image): ?><a href="<?= h($permalink) ?>" tabindex="-1" aria-hidden="true"><img class="post-thumbnail" src="<?= h($image) ?>" alt="" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= h(theme_asset_url('assets/nopic.svg')) ?>'"><?php if ($index === 8 && $totalImageCount > 9): ?><span class="image-more">+<?= h((string)($totalImageCount - 9)) ?></span><?php endif; ?></a><?php endforeach; ?>
            </div><?php endif; ?>
            <?php if ($tags): ?><div class="post_loop_tag"><?php foreach ($tags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"># <?= h((string)$tag['label']) ?></a><?php endforeach; ?></div><?php endif; ?>
            <footer class="post_info_footer">
              <a href="<?= h($permalink) ?>#comments"><?= mango_icon('comment') ?><span><?= h((string)$comments) ?><?= h(sblog_t('评论')) ?></span></a>
              <span><?= mango_icon('eye') ?><?= h((string)(int)($post['views'] ?? 0)) ?><?= h(sblog_t('浏览')) ?></span>
              <?= mango_like_button($post) ?>
            </footer>
          </div>
        </article>
        <?php
    endforeach;
    return (string)ob_get_clean();
}

function mango_render_pager(int $page, int $totalPages, ?callable $urlBuilder = null): string
{
    if ($totalPages <= 1) return '';
    $urlBuilder ??= static fn(int $number): string => home_page_url($number);
    ob_start();
    ?><nav class="posts-nav" aria-label="<?= h(sblog_t('分页')) ?>"><?php for ($number = 1; $number <= $totalPages; $number++): ?>
      <?php if ($number === 1 || $number === $totalPages || abs($number - $page) <= 2): ?>
        <?php if ($number === $page): ?><span class="post-page-numbers current" aria-current="page"><?= h((string)$number) ?></span><?php else: ?><a class="post-page-numbers" href="<?= h($urlBuilder($number)) ?>"><?= h((string)$number) ?></a><?php endif; ?>
      <?php elseif ($number === 2 || $number === $totalPages - 1): ?><span class="post-page-numbers dots">...</span><?php endif; ?>
    <?php endfor; ?></nav><?php
    return (string)ob_get_clean();
}

function mango_term_header(string $title, string $description = '', string $image = ''): string
{
    $image = $image !== '' ? $image : theme_asset_url('assets/nopic.svg');
    return '<header class="cat_head"><img src="' . h($image) . '" alt="" loading="lazy"><div><h1># ' . h($title) . '</h1>'
        . ($description !== '' ? '<p>' . h($description) . '</p>' : '') . '</div></header>';
}

function mango_render_list(array $posts, string $header = '', string $pager = ''): string
{
    ob_start();
    ?><main class="mango-main"><?= $header ?><?php if ($posts): ?><div class="post_box"><?= mango_render_post_items($posts) ?></div><?= $pager ?><?php else: ?><div class="mango-empty"><?= h(sblog_t('这里还没有内容。')) ?></div><?php endif; ?></main><?php
    return (string)ob_get_clean();
}

function mango_search_posts(string $term): array
{
    $term = str_sub_u(trim($term), 0, 100);
    if ($term === '') return [];
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    return all_rows("SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? AND (title LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\') ORDER BY is_pinned DESC, published_at DESC, id DESC LIMIT 100", ['post', 'published', time(), $like, $like, $like]);
}

function mango_render_home(): string
{
    $search = trim((string)($_GET['s'] ?? ''));
    if ($search !== '') return mango_render_list(mango_search_posts($search), mango_term_header(sblog_t('搜索：{keyword}', ['keyword' => $search])));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $totalPages = max(1, (int)ceil(count_published_posts() / $perPage));
    return mango_render_list(fetch_published_posts($perPage, ($page - 1) * $perPage), '', mango_render_pager($page, $totalPages));
}

function mango_render_category_page(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) return '';
    $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', (int)$category['id'], 'published', time()]);
    return mango_render_list($posts, mango_term_header((string)$category['name'], trim((string)$category['description']), $posts ? mango_post_cover($posts[0]) : ''));
}

function mango_render_tag_page(string $slug): string
{
    $label = tag_label_by_slug($slug) ?? $slug;
    $posts = fetch_posts_by_tag_slug($slug);
    return mango_render_list($posts, mango_term_header($label, sblog_tn('共包含 {count} 篇文章', count($posts)), $posts ? mango_post_cover($posts[0]) : ''));
}

function mango_render_archive(): string
{
    $posts = fetch_archive_posts();
    $years = [];
    foreach ($posts as $post) $years[date('Y', (int)$post['published_at'])][] = $post;
    ob_start(); ?>
    <main class="mango-main"><article class="mango-panel mango-archive"><header class="mango-page-head"><h1><?= h(sblog_t('归档')) ?></h1><p><?= h(sblog_tn('共包含 {count} 篇文章', count($posts))) ?></p></header>
      <?php foreach ($years as $year => $items): ?><section><h2><?= h($year) ?></h2><ol><?php foreach ($items as $post): ?><li><time><?= h(date('m-d', (int)$post['published_at'])) ?></time><a href="<?= h(content_permalink($post)) ?>"><?= h((string)$post['title']) ?></a></li><?php endforeach; ?></ol></section><?php endforeach; ?>
      <?php if (!$posts): ?><div class="mango-empty"><?= h(sblog_t('归档还是空的。')) ?></div><?php endif; ?>
    </article></main><?php return (string)ob_get_clean();
}

function mango_render_tags(): string
{
    $tags = tag_index_data();
    ob_start(); ?><main class="mango-main"><article class="mango-panel"><header class="mango-page-head"><h1><?= h(sblog_t('标签')) ?></h1><p><?= h(sblog_t('浏览全部文章标签')) ?></p></header>
      <?php if ($tags): ?><div class="mango-tag-cloud"><?php foreach ($tags as $tag): ?><a href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"># <?= h((string)$tag['label']) ?><span><?= h((string)$tag['count']) ?></span></a><?php endforeach; ?></div><?php else: ?><div class="mango-empty"><?= h(sblog_t('暂无标签')) ?></div><?php endif; ?>
    </article></main><?php return (string)ob_get_clean();
}

function mango_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start(); ?><main class="mango-main"><article class="mango-panel"><header class="mango-page-head"><h1><?= h(sblog_t('链接')) ?></h1><p><?= h(sblog_t('一些值得访问的网站与朋友。')) ?></p></header>
      <?php if ($links): ?><div class="mango-links"><?php foreach ($links as $link): $icon = safe_link_url((string)($link['icon_url'] ?? '')); ?><a href="<?= h(safe_link_url((string)$link['url'])) ?>" target="_blank" rel="noopener noreferrer"><?php if ($icon !== '#'): ?><img src="<?= h($icon) ?>" alt="" loading="lazy"><?php endif; ?><span><strong><?= h((string)$link['name']) ?></strong><small><?= h((string)$link['description']) ?></small></span></a><?php endforeach; ?></div><?php else: ?><div class="mango-empty"><?= h(sblog_t('还没有添加友情链接。')) ?></div><?php endif; ?>
    </article></main><?php return (string)ob_get_clean();
}

function mango_post_meta(array $post): string
{
    $meta = one('SELECT p.views, u.username, u.nickname, c.name AS category_name, c.slug AS category_slug FROM posts p LEFT JOIN users u ON u.id = p.author_id LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [(int)$post['id']]) ?? [];
    $author = trim((string)($meta['nickname'] ?? '')) ?: (string)($meta['username'] ?? 'Admin');
    $timestamp = (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
    ob_start(); ?><div class="post_container_meta"><span><?= h($author) ?></span><time datetime="<?= h(date(DATE_ATOM, $timestamp)) ?>"><?= h(date('Y-m-d', $timestamp)) ?></time><?php if (trim((string)($meta['category_slug'] ?? '')) !== ''): ?><a href="<?= h(url_for('category', ['slug' => (string)$meta['category_slug']])) ?>"># <?= h((string)$meta['category_name']) ?></a><?php endif; ?><span><?= mango_icon('eye') ?><?= h((string)(int)($meta['views'] ?? 0)) ?></span></div><?php return (string)ob_get_clean();
}

function mango_post_like_bar(array $post): string
{
    $author = mango_author();
    return '<footer class="post_author"><div class="post_author_l"><img src="' . h((string)$author['avatar']) . '" width="42" height="42" alt="' . h((string)$author['display_name']) . '"><span>' . h((string)$author['display_name'])
        . '</span></div><div class="post_author_r"><a href="#comments" aria-label="' . h(sblog_t('评论')) . '">' . mango_icon('comment') . '<span>' . h((string)approved_comment_count((int)$post['id']))
        . '</span></a>' . mango_like_button($post, 'post-like-button') . '</div></footer>';
}

function mango_comment_tree(array $comments): array
{
    $byId = [];
    foreach ($comments as $comment) {
        $byId[(int)$comment['id']] = $comment;
    }

    $children = [];
    $roots = [];
    foreach ($comments as $comment) {
        $id = (int)$comment['id'];
        $parentId = (int)$comment['parent_id'];
        if ($parentId > 0 && $parentId !== $id && isset($byId[$parentId])) {
            $children[$parentId][] = $id;
        } else {
            $roots[] = $id;
        }
    }

    return [$byId, $children, $roots];
}

function mango_render_comment_items(array $ids, array $byId, array $children, bool $accepting, int $replyTargetId, array &$visited, int $depth = 0): string
{
    ob_start();
    foreach ($ids as $id):
        $id = (int)$id;
        if (isset($visited[$id]) || !isset($byId[$id])) {
            continue;
        }
        $visited[$id] = true;
        $comment = $byId[$id];
        $authorUrl = safe_link_url((string)$comment['author_url']);
        $replyName = trim((string)$comment['reply_to_name']);
        $parentId = (int)$comment['parent_id'];
        $childIds = $children[$id] ?? [];
        ?>
        <li class="comment-item<?= $depth > 0 ? ' comment-item--reply' : '' ?>" id="comment-<?= h((string)$id) ?>" data-comment-depth="<?= h((string)$depth) ?>">
          <article class="comment-item__box comment-body">
            <header class="comment-item__meta">
              <img class="comment-item__avatar" src="<?= h(gravatar_url((string)$comment['author_email'])) ?>" width="40" height="40" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
              <?php if ($authorUrl !== '#'): ?>
                <a class="comment-item__author" href="<?= h($authorUrl) ?>" target="_blank" rel="ugc nofollow noopener noreferrer"><?= h((string)$comment['author_name']) ?></a>
              <?php else: ?>
                <strong class="comment-item__author"><?= h((string)$comment['author_name']) ?></strong>
              <?php endif; ?>
              <time class="comment-item__time" datetime="<?= h(date(DATE_ATOM, (int)$comment['created_at'])) ?>"><?= h(pretty_date((int)$comment['created_at'], true)) ?></time>
              <?php if ($accepting): ?>
                <button class="comment-reply-button" type="button" data-comment-reply data-comment-id="<?= h((string)$id) ?>" data-comment-author="<?= h((string)$comment['author_name']) ?>" aria-controls="comment-form" aria-pressed="<?= $replyTargetId === $id ? 'true' : 'false' ?>" aria-label="<?= h(sblog_t('回复 @{author}', ['author' => (string)$comment['author_name']])) ?>" title="<?= h(sblog_t('回复 @{author}', ['author' => (string)$comment['author_name']])) ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 17-5-5 5-5"></path><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg>
                  <span><?= h(sblog_t('回复')) ?></span>
                </button>
              <?php endif; ?>
            </header>
            <div class="comment-item__body">
              <?php if ($replyName !== ''): ?>
                <?php if ($parentId > 0 && isset($byId[$parentId])): ?><a class="comment-item__reply-target" href="#comment-<?= h((string)$parentId) ?>">@<?= h($replyName) ?></a><?php else: ?><span class="comment-item__reply-target">@<?= h($replyName) ?></span><?php endif; ?>
              <?php endif; ?>
              <span class="comment-item__content"><?= nl2br(h((string)$comment['content']), false) ?></span>
            </div>
          </article>
          <?php if ($childIds): ?>
            <ol class="children" aria-label="<?= h(sblog_t('对 {author} 的回复', ['author' => (string)$comment['author_name']])) ?>">
              <?= mango_render_comment_items($childIds, $byId, $children, $accepting, $replyTargetId, $visited, $depth + 1) ?>
            </ol>
          <?php endif; ?>
        </li>
        <?php
    endforeach;
    return (string)ob_get_clean();
}

function mango_render_comment_list(int $postId, bool $accepting, int $replyTargetId): string
{
    $comments = public_comments_for_post($postId);
    if ($comments === []) {
        return '';
    }

    [$byId, $children, $roots] = mango_comment_tree($comments);
    $visited = [];
    $items = mango_render_comment_items($roots, $byId, $children, $accepting, $replyTargetId, $visited);
    $remaining = array_values(array_diff(array_keys($byId), array_keys($visited)));
    if ($remaining !== []) {
        $items .= mango_render_comment_items($remaining, $byId, $children, $accepting, $replyTargetId, $visited);
    }

    return '<ol class="comment-list">' . $items . '</ol>';
}

function mango_prepare_comments(string $content, int $postId): string
{
    if (!str_contains($content, '<ol class="comment-list">')) {
        return $content;
    }

    $accepting = str_contains($content, 'id="comment-form"');
    $replyTargetId = preg_match('/name="parent_id" value="(\d*)"/', $content, $matches) ? (int)$matches[1] : 0;
    $list = mango_render_comment_list($postId, $accepting, $replyTargetId);
    if ($list === '') {
        return $content;
    }

    return preg_replace('/<ol class="comment-list">.*?<\/ol>/s', $list, $content, 1) ?? $content;
}

function mango_adapt_article_content(string $content, array $context): string
{
    $action = (string)($_GET['a'] ?? '');
    $active = (string)($context['active'] ?? '');
    $isPage = $action === 'page' || str_starts_with($active, 'page:');
    $post = $action === 'post' ? fetch_post_by_identifier((string)($_GET['slug'] ?? ''), true) : ($action === 'page' ? fetch_page_by_identifier((string)($_GET['slug'] ?? ''), true) : ($isPage ? fetch_page_by_identifier(substr($active, 5), true) : null));
    $content = preg_replace('/<article>/', '<article class="post_container mango-panel">', $content, 1) ?? $content;
    $content = str_replace('class="post-title"', 'class="post_container_title"', $content);
    $content = str_replace('class="post-content"', 'class="wznrys post-content"', $content);
    if ($post && content_kind($post) === 'post') {
        $content = preg_replace('/<div class="meta">.*?<\/div>/s', mango_post_meta($post), $content, 1) ?? $content;
        $content = preg_replace('/<\/article>/', mango_post_like_bar($post) . '</article>', $content, 1) ?? $content;
    }
    $content = str_replace('class="post-tags"', 'class="post-tags post_loop_tag"', $content);
    $content = str_replace('class="pagination"', 'class="pagination mango-post-nav"', $content);
    $content = preg_replace_callback(
        '/(<a\b[^>]*\bdata-post-title="([^"]*)"[^>]*>)(.*?)(<\/a>)/s',
        static fn(array $match): string => $match[1] . '<small>' . $match[3] . '</small><strong>' . $match[2] . '</strong>' . $match[4],
        $content
    ) ?? $content;
    if ($post) $content = mango_prepare_comments($content, (int)$post['id']);
    return '<main class="mango-main mango-single">' . $content . '</main>';
}

add_theme_filter('body_class', static fn(string $classes): string => trim($classes . ' mango-theme'));

add_theme_filter('comments_labels', static fn(array $labels): array => array_merge($labels, [
    'title' => sblog_t('评论'), 'form_title' => sblog_t('发表评论'), 'submit' => sblog_t('提交评论'),
    'cancel_reply' => sblog_t('取消回复'), 'empty' => sblog_t('暂无评论'), 'closed' => sblog_t('评论已关闭'),
]));

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    $action = (string)($_GET['a'] ?? '');
    if ($action === 'category') return mango_render_category_page((string)($_GET['slug'] ?? '')) ?: $content;
    if ($action === 'tag') return mango_render_tag_page((string)($_GET['slug'] ?? ''));
    if ($active === 'archives') return mango_render_archive();
    if ($active === 'tags') return mango_render_tags();
    if ($active === 'links') return mango_render_links();
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '') && $action !== 'post') return mango_render_home();
    if ($action === 'post' || $action === 'page' || str_starts_with($active, 'page:')) return mango_adapt_article_content($content, $context);
    return $content;
});
