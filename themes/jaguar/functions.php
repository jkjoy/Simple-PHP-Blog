<?php

declare(strict_types=1);

function jaguar_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        if ($url !== '#') {
            return $url;
        }
    }

    return theme_asset_url('assets/images/default.jpg');
}

function jaguar_post_excerpt(array $post): string
{
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    return $excerpt !== '' ? $excerpt : derive_excerpt((string)($post['content'] ?? ''), 120);
}

function jaguar_post_category(array $post): ?array
{
    $categoryId = (int)($post['category_id'] ?? 0);
    return $categoryId > 0 ? one('SELECT name, slug FROM categories WHERE id = ?', [$categoryId]) : null;
}

function jaguar_sns_icon(string $name): string
{
    $icons = [
        'rss' => ['sns', '0 0 24 24', '<path d="M12 17C12 14 10 12 7 12" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"></path><path d="M17 17C17 11 13 7 7 7" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"></path><path d="M7 17.01 7.01 16.9989" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M21 8V16C21 18.7614 18.7614 21 16 21H8C5.23858 21 3 18.7614 3 16V8C3 5.23858 5.23858 3 8 3H16C18.7614 3 21 5.23858 21 8Z" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path>'],
        'github' => ['', '0 0 24 24', '<path fill-rule="evenodd" clip-rule="evenodd" d="M20.999 5.958c.01.607-.066 1.368-.133 1.923a4.8 4.8 0 0 1-.101.544C21.623 10.01 22 11.917 22 14c0 4.937-4.748 8-10 8S2 18.937 2 14c0-2.083.377-3.99 1.235-5.575a4.8 4.8 0 0 1-.101-.544C3.067 7.326 2.99 6.565 3.001 5.958c.01-.683.1-1.366.199-2.044.046-.314.118-.609.459-.795.347-.19.713-.119 1.075-.017a18.7 18.7 0 0 1 3.434 1.411C9.3 4.173 10.578 4 12 4s2.7.173 3.832.513a18.7 18.7 0 0 1 3.434-1.411c.362-.102.728-.173 1.075.017.341.186.413.481.459.795.099.678.188 1.361.199 2.044ZM20 14c0-1.687-.388-4-2.5-4-.952 0-1.853.25-2.753.5-.899.25-1.798.5-2.747.5s-1.848-.25-2.747-.5C8.353 10.25 7.452 10 6.5 10 4.394 10 4 12.32 4 14c0 3.527 3.308 6 8 6s8-2.473 8-6Zm-10 .5c0 1.38-.672 2.5-1.5 2.5S7 15.88 7 14.5 7.672 12 8.5 12s1.5 1.12 1.5 2.5Zm5.5 2.5c.828 0 1.5-1.12 1.5-2.5s-.672-2.5-1.5-2.5-1.5 1.12-1.5 2.5.672 2.5 1.5 2.5Z"></path>'],
        'x' => ['', '0 0 30 30', '<path d="M26.37 26 17.575 13.178 25.52 4h-2.65l-6.46 7.48L11.28 4H4.33l8.211 11.971L3.88 26h2.65l7.182-8.322L19.42 26h6.95ZM10.23 6l12.34 18h-2.1L8.12 6h2.11Z"></path>'],
        'telegram' => ['', '0 0 30 30', '<path d="M25.154 3.984c-.325.015-.628.11-.894.217-.25.101-1.204.51-2.707 1.154l-5.621 2.415-11.475 4.937c-.092.04-.413.142-.754.408C3.362 13.38 3 13.933 3 14.547c0 .495.236.987.533 1.281.297.294.612.439.881.549l4.58 1.873 1.553 4.795c.168.543.327.883.535 1.152.208.27.49.42.691.48.153.05.307.081.444.081.585 0 .943-.322.943-.322l3.031-2.62 3.651 3.453c.051.073.53.731 1.588.731.627 0 1.125-.315 1.445-.65.32-.336.519-.688.604-1.131.079-.419 3.443-17.691 3.443-17.691.098-.45.124-.868.01-1.258-.109-.413-.395-.809-.75-1.022-.355-.213-.702-.278-1.027-.264Zm-.187 2.09-.006.061-3.447 17.711c.009-.049-.032.048-.074.107-.06-.041-.182-.094-.182-.094l-5.006-4.738-3.525 3.047 1.049-4.199s6.556-6.787 6.951-7.182c.318-.316.385-.426.385-.535 0-.146-.076-.252-.246-.252-.153 0-.359.149-.469.219-1.433.913-7.724 4.58-10.545 6.22l-4.617-1.888 19.732-8.477Z"></path>'],
        'instagram' => ['', '0 0 24 24', '<path fill-rule="evenodd" d="M10.825 2h2.349c1.675.004 2.06.019 2.949.059 1.064.049 1.791.218 2.427.465.658.255 1.216.597 1.772 1.153.555.556.897 1.114 1.153 1.772.247.636.416 1.363.465 2.427.047 1.031.059 1.384.06 3.86v.527c-.001 2.476-.013 2.829-.06 3.86-.049 1.064-.218 1.791-.465 2.427-.256.658-.598 1.216-1.153 1.772-.556.555-1.114.897-1.772 1.153-.636.247-1.363.416-2.427.465-1.031.047-1.384.059-3.86.06h-.527c-2.476-.001-2.829-.013-3.86-.06-1.064-.049-1.791-.218-2.427-.465-.658-.256-1.216-.598-1.772-1.153-.556-.556-.898-1.114-1.153-1.772-.247-.636-.416-1.363-.465-2.427C2.019 15.234 2.004 14.849 2 13.174v-2.349c.004-1.675.019-2.06.059-2.949.049-1.064.218-1.791.465-2.427.255-.658.597-1.216 1.153-1.772.556-.556 1.114-.898 1.772-1.153.636-.247 1.363-.416 2.427-.465C8.765 2.019 9.15 2.004 10.825 2Zm1.174 5c2.762 0 5.001 2.238 5.001 5s-2.239 5-5.001 5S7 14.761 7 12s2.238-5 4.999-5Zm0 1.754A3.246 3.246 0 1 0 12 15.245a3.246 3.246 0 0 0-.001-6.491Zm5.417-3.422a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM12 3.801c-2.67 0-2.987.01-4.041.058-.975.045-1.505.208-1.858.344-.466.182-.8.399-1.149.749-.35.35-.567.683-.749 1.149-.136.353-.3.882-.344 1.857-.048 1.055-.058 1.371-.058 4.042 0 2.67.01 2.986.058 4.041.045.975.208 1.505.344 1.857.182.467.399.8.749 1.15.35.349.683.566 1.149.748.353.137.882.3 1.858.344 1.054.048 1.371.058 4.041.058s2.986-.01 4.041-.058c.975-.045 1.505-.207 1.857-.344.467-.182.8-.399 1.15-.749.349-.35.566-.683.748-1.149.137-.352.3-.882.344-1.857.048-1.055.058-1.371.058-4.041 0-2.671-.01-2.987-.058-4.042-.045-.975-.207-1.504-.344-1.857-.182-.466-.399-.799-.749-1.149-.35-.35-.683-.567-1.149-.749-.352-.136-.882-.299-1.857-.344-1.055-.048-1.371-.058-4.041-.058Z"></path>'],
        'qq' => ['sns-icon--qq', '0 0 24 24', '<path d="M21.395 15.035a40 40 0 0 0-.803-2.264l-1.079-2.695c.001-.032.014-.562.014-.836C19.526 4.632 17.351 0 12 0S4.474 4.632 4.474 9.241c0 .274.013.804.014.836l-1.08 2.695a39 39 0 0 0-.802 2.264c-1.021 3.283-.69 4.643-.438 4.673.54.065 2.103-2.472 2.103-2.472 0 1.469.756 3.387 2.394 4.771-.612.188-1.363.479-1.845.835-.434.32-.379.646-.301.778.343.578 5.883.369 7.482.189 1.6.18 7.14.389 7.483-.189.078-.132.132-.458-.301-.778-.483-.356-1.233-.646-1.846-.836 1.637-1.384 2.393-3.302 2.393-4.771 0 0 1.563 2.537 2.103 2.472.251-.03.581-1.39-.438-4.673"></path>'],
        'wechat' => ['sns-icon--wechat', '1 0 24 24', '<path d="M9.7 3C5.45 3 2 5.83 2 9.32c0 1.98 1.12 3.74 2.86 4.9l-.72 2.13 2.48-1.24c.97.34 2 .52 3.08.52.3 0 .59-.02.88-.04a5.7 5.7 0 0 1-.18-1.42c0-3.55 3.46-6.42 7.73-6.42.16 0 .31 0 .47.02C17.74 5.03 14.2 3 9.7 3Zm-2.6 4.2a.9.9 0 1 1 0-1.8.9.9 0 0 1 0 1.8Zm5.2 0a.9.9 0 1 1 0-1.8.9.9 0 0 1 0 1.8Zm5.83 1.63c-3.24 0-5.87 2.15-5.87 4.8s2.63 4.8 5.87 4.8c.83 0 1.62-.14 2.34-.4l1.89.94-.55-1.62c1.33-.88 2.19-2.22 2.19-3.72 0-2.65-2.63-4.8-5.87-4.8Zm-1.96 3.18a.69.69 0 1 1 0-1.38.69.69 0 0 1 0 1.38Zm3.92 0a.69.69 0 1 1 0-1.38.69.69 0 0 1 0 1.38Z"></path>'],
        'weibo' => ['sns-icon--weibo', '4 0 24 24', '<path d="M17.6 10.1c-.5-.15-.85-.25-.58-.9.58-1.4.64-2.61.01-3.23-1.18-1.17-4.32.04-7.01 2.69-2.02 1.99-3.19 4.1-3.19 5.92 0 3.48 4.48 5.59 8.86 5.59 5.74 0 9.56-3.33 9.56-5.97 0-1.59-1.35-3.13-3.65-3.86ZM15.7 18.35c-3.48.35-6.49-1.23-6.72-3.53-.23-2.29 2.41-4.43 5.89-4.78 3.48-.35 6.49 1.23 6.72 3.52.23 2.3-2.41 4.44-5.89 4.79Zm-.49-6.31c-1.66-.43-3.54.4-4.19 1.86-.66 1.46.16 3 1.82 3.43 1.66.43 3.54-.4 4.2-1.86.65-1.46-.17-3-1.83-3.43Zm-1.22 3.75c-.57.38-1.34.27-1.72-.25-.37-.52-.21-1.25.36-1.63.57-.38 1.34-.27 1.71.25.38.52.22 1.25-.35 1.63ZM24 7.27a5.2 5.2 0 0 0-7.25-4.8l.72 1.49a3.54 3.54 0 0 1 4.87 4.65L24 9.1c.14-.59.17-1.21 0-1.83Zm-3.23.7a2.62 2.62 0 0 0-3.49-2.68l.73 1.5a.95.95 0 0 1 1.24.92l1.52.26Z"></path>'],
        'bilibili' => ['', '0 0 24 24', '<path d="m8.3 4.8-1.4-2 1.4-1 2.1 3h3.2l2.1-3 1.4 1-1.4 2H18a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4v-8a4 4 0 0 1 4-4h2.3ZM6 6.6a2.2 2.2 0 0 0-2.2 2.2v8A2.2 2.2 0 0 0 6 19h12a2.2 2.2 0 0 0 2.2-2.2v-8A2.2 2.2 0 0 0 18 6.6H6Zm2.2 4.2A1.2 1.2 0 1 1 8.2 13a1.2 1.2 0 0 1 0-2.2Zm7.6 0a1.2 1.2 0 1 1 0 2.2 1.2 1.2 0 0 1 0-2.2Zm-7.5 4.5h7.4v1.5H8.3v-1.5Z"></path>'],
        'tiktok' => ['sns-icon--tiktok', '2 0 24 24', '<path d="M14.5 2h3.1c.2 1.7 1.2 3.2 2.8 4.1.9.5 1.8.7 2.6.7V10c-1.8 0-3.5-.5-5-1.5v7.1A6.4 6.4 0 1 1 12.5 9v3.3a3.2 3.2 0 1 0 2 3V2Z"></path>'],
        'mastodon' => ['sns-icon--mastodon', '0 0 24 24', '<path d="M21.58 13.91c-.32 1.64-2.9 3.44-5.86 3.79-1.54.18-3.05.35-4.66.28-2.64-.12-4.72-.63-4.72-.63 0 .26.02.51.05.75.34 2.57 2.55 2.72 4.7 2.79 2.17.08 4.1-.53 4.1-.53l.09 1.96s-1.52.82-4.22.98c-1.49.08-3.33-.04-5.48-.61C.92 21.44.12 16.4 0 11.3-.04 9.8-.01 8.38-.01 7.2-.01 2.03 3.38.52 3.38.52 5.09-.26 8.01-.59 11.02-.61h.08c3.01.02 5.94.35 7.65 1.13 0 0 3.39 1.51 3.39 6.68 0 0 .04 3.81-.56 6.71ZM18.05 7.6c0-1.28-.33-2.3-.99-3.04-.68-.75-1.57-1.13-2.68-1.13-1.28 0-2.25.49-2.89 1.47L10.87 6l-.62-1.1C9.61 3.92 8.64 3.43 7.36 3.43c-1.11 0-2 .38-2.68 1.13-.66.74-.99 1.76-.99 3.04v6.24h2.48V7.78c0-1.28.54-1.93 1.61-1.93 1.19 0 1.78.77 1.78 2.29v3.37h2.47V8.14c0-1.52.6-2.29 1.79-2.29 1.07 0 1.61.65 1.61 1.93v6.06h2.48V7.6Z" transform="translate(1 1)"></path>'],
    ];
    [$class, $viewBox, $content] = $icons[$name] ?? ['', '0 0 24 24', '<circle cx="12" cy="12" r="9"></circle>'];
    return '<svg' . ($class !== '' ? ' class="' . h($class) . '"' : '') . ' viewBox="' . h($viewBox) . '" aria-hidden="true" focusable="false">' . $content . '</svg>';
}

function jaguar_image_count(array $post): int
{
    $content = (string)($post['content'] ?? '');
    preg_match_all('/<img\b[^>]*>|!\[[^\]]*\]\([^\)]+\)/i', $content, $matches);
    return count($matches[0] ?? []);
}

function jaguar_relative_date(int $timestamp): string
{
    $diff = max(0, time() - $timestamp);
    if ($diff < 60) {
        return sblog_t('刚刚');
    }
    if ($diff < 3600) {
        return sblog_tn('{count} 分钟前', max(1, (int)floor($diff / 60)));
    }
    if ($diff < 86400) {
        return sblog_tn('{count} 小时前', max(1, (int)floor($diff / 3600)));
    }
    if ($diff < 2592000) {
        return sblog_tn('{count} 天前', max(1, (int)floor($diff / 86400)));
    }
    return date('m-d', $timestamp);
}

function jaguar_render_post_items(array $posts): string
{
    ob_start();
    foreach ($posts as $post):
        $permalink = content_permalink($post);
        $category = jaguar_post_category($post);
        $imageCount = jaguar_image_count($post);
        ?>
        <article class="jBlock--item<?= !empty($post['is_pinned']) ? ' is-pinned' : '' ?>" itemscope itemtype="https://schema.org/Article">
          <a href="<?= h($permalink) ?>" title="<?= h((string)$post['title']) ?>" class="jBlock--imageLink">
            <img src="<?= h(jaguar_post_cover($post)) ?>" alt="<?= h((string)$post['title']) ?>" class="jBlock--image" itemprop="image" loading="lazy" decoding="async">
            <?php if (!empty($post['is_pinned'])): ?><span class="jaguar-pinned"><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
          </a>
          <div class="jBlock--content">
            <h2 class="jBlock--title" itemprop="headline"><a href="<?= h($permalink) ?>"><?= h((string)$post['title']) ?></a></h2>
            <div class="jBlock--excerpt" itemprop="description"><?= h(jaguar_post_excerpt($post)) ?></div>
            <div class="jBlock--info">
              <time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>" itemprop="datePublished"><?= h(jaguar_relative_date((int)$post['published_at'])) ?></time>
              <?php if ($category): ?><span class="middotDivider"></span><a href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>" itemprop="articleSection"><?= h((string)$category['name']) ?></a><?php endif; ?>
              <span class="middotDivider"></span><span><?= h((string)(int)($post['views'] ?? 0)) ?> views</span>
              <?php if ($imageCount > 0): ?><span class="middotDivider"></span><span><?= h((string)$imageCount) ?> shots</span><?php endif; ?>
            </div>
          </div>
        </article>
        <?php
    endforeach;
    return (string)ob_get_clean();
}

function jaguar_render_pager(int $page, int $totalPages, ?callable $urlBuilder = null): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $urlBuilder ??= static fn(int $number): string => home_page_url($number);
    ob_start();
    ?>
    <nav class="nav-links" aria-label="<?= h(sblog_t('分页')) ?>">
      <?php for ($number = 1; $number <= $totalPages; $number++): ?>
        <?php if ($number === 1 || $number === $totalPages || abs($number - $page) <= 2): ?>
          <?php if ($number === $page): ?><span class="page-numbers current" aria-current="page"><?= h((string)$number) ?></span><?php else: ?><a class="page-numbers" href="<?= h($urlBuilder($number)) ?>"><?= h((string)$number) ?></a><?php endif; ?>
        <?php elseif ($number === 2 || $number === $totalPages - 1): ?><span class="page-numbers dots">...</span><?php endif; ?>
      <?php endfor; ?>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function jaguar_term_header(string $title, string $description = ''): string
{
    return '<header class="jTerm--header"><h1 class="jTerm--headline">' . h($title) . '</h1>'
        . ($description !== '' ? '<div class="jTerm--description">' . h($description) . '</div>' : '') . '</header>';
}

function jaguar_render_list(array $posts, string $header = '', string $pager = ''): string
{
    ob_start();
    ?>
    <main class="layoutSingleColumn layoutSingleColumn--wide u-paddingTop50">
      <?= $header ?>
      <?php if ($posts): ?><div class="jBlock--list"><?= jaguar_render_post_items($posts) ?></div><?= $pager ?><?php else: ?><div class="jBlock--empty"><?= h(sblog_t('这里还没有内容。')) ?></div><?php endif; ?>
    </main>
    <?php
    return (string)ob_get_clean();
}

function jaguar_search_posts(string $term): array
{
    $term = str_sub_u(trim($term), 0, 100);
    if ($term === '') {
        return [];
    }
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    return all_rows(
        "SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? AND (title LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\') ORDER BY is_pinned DESC, published_at DESC, id DESC LIMIT 100",
        ['post', 'published', time(), $like, $like, $like]
    );
}

function jaguar_render_home(): string
{
    $search = trim((string)($_GET['s'] ?? ''));
    if ($search !== '') {
        return jaguar_render_list(jaguar_search_posts($search), jaguar_term_header(sblog_t('搜索：{keyword}', ['keyword' => $search])));
    }
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    return jaguar_render_list(fetch_published_posts($perPage, ($page - 1) * $perPage), '', jaguar_render_pager($page, $totalPages));
}

function jaguar_render_archive(): string
{
    $posts = fetch_archive_posts();
    $years = [];
    foreach ($posts as $post) {
        $years[date('Y', (int)$post['published_at'])][date('n', (int)$post['published_at'])][] = $post;
    }
    ob_start();
    ?>
    <main class="layoutSingleColumn u-paddingTop50"><article class="jArticle">
      <header class="jArticle--header"><h1 class="jArticle--headline"><?= h(sblog_t('归档')) ?></h1></header>
      <?php foreach ($years as $year => $months): ?><section class="jArchive--area"><h2 class="jArchive--monthly"><?= h($year) ?></h2>
        <?php foreach ($months as $month => $monthPosts): ?><div class="jArchive--list" data-year="<?= h($year . ' - ' . $month) ?>">
          <?php foreach ($monthPosts as $post): ?><div class="jArchive--item"><div class="jArchive--title"><a href="<?= h(content_permalink($post)) ?>"><?= h((string)$post['title']) ?></a></div><time class="jArchive--meta" datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(date('m-d', (int)$post['published_at'])) ?></time></div><?php endforeach; ?>
        </div><?php endforeach; ?>
      </section><?php endforeach; ?>
      <?php if (!$years): ?><div class="jBlock--empty"><?= h(sblog_t('归档还是空的。')) ?></div><?php endif; ?>
    </article></main>
    <?php
    return (string)ob_get_clean();
}

function jaguar_render_tags(): string
{
    $tags = tag_index_data();
    ob_start();
    ?>
    <main class="layoutSingleColumn u-paddingTop50"><article class="jArticle">
      <header class="jArticle--header"><h1 class="jArticle--headline"><?= h(sblog_t('标签')) ?></h1></header>
      <?php if ($tags): ?><div class="archive--tagList"><?php foreach ($tags as $tag): ?><a class="archive--tagItem" href="<?= h(url_for('tag', ['slug' => (string)$tag['slug']])) ?>"><?= h((string)$tag['label']) ?> (<?= h((string)$tag['count']) ?>)</a><?php endforeach; ?></div><?php else: ?><div class="jBlock--empty"><?= h(sblog_t('暂无标签')) ?></div><?php endif; ?>
    </article></main>
    <?php
    return (string)ob_get_clean();
}

function jaguar_render_tag_page(string $slug): string
{
    $label = tag_label_by_slug($slug) ?? $slug;
    return jaguar_render_list(fetch_posts_by_tag_slug($slug), jaguar_term_header(sblog_t('标签 {name} 下的文章', ['name' => $label])));
}

function jaguar_render_category_page(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) {
        return '';
    }
    $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', (int)$category['id'], 'published', time()]);
    return jaguar_render_list($posts, jaguar_term_header((string)$category['name'], trim((string)$category['description'])));
}

function jaguar_render_links(): string
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    ?>
    <main class="layoutSingleColumn u-paddingTop50"><article class="jArticle">
      <header class="jArticle--header"><h1 class="jArticle--headline"><?= h(sblog_t('链接')) ?></h1></header>
      <?php if ($links): ?><div class="jLink--list"><?php foreach ($links as $link): ?>
        <?php $iconUrl = safe_link_url((string)($link['icon_url'] ?? '')); $iconUrl = $iconUrl !== '#' ? $iconUrl : theme_asset_url('assets/images/default.jpg'); ?>
        <div class="jLink--item"><a class="link-item-inner" href="<?= h(safe_link_url((string)$link['url'])) ?>" title="<?= h((string)$link['description']) ?>" target="_blank" rel="noopener noreferrer"><span class="sitename"><img src="<?= h($iconUrl) ?>" alt="<?= h((string)$link['name']) ?>" class="avatar" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= h(theme_asset_url('assets/images/default.jpg')) ?>'"><strong><?= h((string)$link['name']) ?></strong><?= h((string)$link['description']) ?></span></a></div>
      <?php endforeach; ?></div><?php else: ?><div class="jBlock--empty"><?= h(sblog_t('还没有添加友情链接。')) ?></div><?php endif; ?>
    </article></main>
    <?php
    return (string)ob_get_clean();
}

function jaguar_reading_time(array $post): string
{
    $plain = trim(strip_tags((string)($post['content'] ?? '')));
    $characters = function_exists('mb_strlen') ? mb_strlen($plain, 'UTF-8') : strlen($plain);
    return sblog_tn('{count} 分钟阅读', max(1, (int)ceil($characters / 500)));
}

function jaguar_post_meta(array $post): string
{
    $meta = one('SELECT p.views, c.name AS category_name, c.slug AS category_slug FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [(int)$post['id']]) ?? [];
    $timestamp = (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
    ob_start();
    ?><div class="jArticle--meta">
      <time datetime="<?= h(date(DATE_ATOM, $timestamp)) ?>"><?= h(jaguar_relative_date($timestamp)) ?></time>
      <?php if (trim((string)($meta['category_slug'] ?? '')) !== ''): ?><span class="middotDivider"></span><a href="<?= h(url_for('category', ['slug' => (string)$meta['category_slug']])) ?>"><?= h((string)$meta['category_name']) ?></a><?php endif; ?>
      <span class="middotDivider"></span><span><?= h(jaguar_reading_time($post)) ?></span>
      <span class="middotDivider"></span><span><?= h((string)(int)($meta['views'] ?? 0)) ?> views</span>
    </div><?php
    return (string)ob_get_clean();
}

function jaguar_render_post_navigation(array $post): string
{
    $neighbors = post_neighbors($post);
    if (!$neighbors['newer'] && !$neighbors['older']) {
        return '';
    }

    $previous = $neighbors['older'] ? fetch_post_by_id((int)$neighbors['older']['id']) : null;
    $next = $neighbors['newer'] ? fetch_post_by_id((int)$neighbors['newer']['id']) : null;
    ob_start();
    ?>
    <nav class="navigation post-navigation" aria-label="<?= h(sblog_t('文章导航')) ?>">
      <div class="nav-links">
        <?php if ($previous): ?>
          <div class="nav-previous">
            <a href="<?= h(content_permalink($previous)) ?>" rel="prev" title="<?= h((string)$previous['title']) ?>">
              <span class="meta-nav"><?= h(sblog_t('post_navigation.previous')) ?></span>
              <span class="post-title"><?= h((string)$previous['title']) ?></span>
              <img src="<?= h(jaguar_post_cover($previous)) ?>" alt="<?= h((string)$previous['title']) ?>" class="post-thumbnail" loading="lazy" decoding="async">
            </a>
          </div>
        <?php endif; ?>
        <?php if ($next): ?>
          <div class="nav-next">
            <a href="<?= h(content_permalink($next)) ?>" rel="next" title="<?= h((string)$next['title']) ?>">
              <span class="meta-nav"><?= h(sblog_t('post_navigation.next')) ?></span>
              <span class="post-title"><?= h((string)$next['title']) ?></span>
              <img src="<?= h(jaguar_post_cover($next)) ?>" alt="<?= h((string)$next['title']) ?>" class="post-thumbnail" loading="lazy" decoding="async">
            </a>
          </div>
        <?php endif; ?>
      </div>
    </nav>
    <?php
    return (string)ob_get_clean();
}

function jaguar_adapt_comments_header(string $content): string
{
    return preg_replace_callback(
        '/<header class="comments__head">\s*<h2 class="section-header" id="comments-title">.*?<\/h2>\s*<span class="comments__count">(.*?)<\/span>\s*<\/header>/s',
        static function (array $matches): string {
            $label = html_entity_decode(strip_tags((string)$matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            preg_match_all('/\d[\d,.]*/', $label, $counts);
            $values = $counts[0] ?? [];
            $count = $values ? (string)end($values) : '0';
            $icon = '<svg class="comments__icon" width="24" height="24" viewBox="0 0 29 29" aria-hidden="true" focusable="false"><g fill-rule="evenodd"><path d="M6.79 7.84a9.33 9.33 0 00-5.78 8.54 9 9 0 002.75 6.67 5.42 5.42 0 01-.15.75 8.08 8.08 0 01-1.28 2.58.63.63 0 00-.05.65.66.66 0 00.6.36 7.46 7.46 0 004.13-1.33 7.85 7.85 0 00.92-.7c.96.272 1.952.41 2.95.41a10.49 10.49 0 006.86-2.5 12.85 12.85 0 01-1.69-.15 9.49 9.49 0 01-5.21 1.53 9.72 9.72 0 01-2.83-.43l-.39-.09c-.385.36-.792.693-1.22 1a6.43 6.43 0 01-2.67 1.06 8.52 8.52 0 00.89-2.08c.089-.3.153-.609.19-.92a3.1 3.1 0 000-.37v-.32l-.24-.21a7.69 7.69 0 01-2.5-5.92A8.15 8.15 0 016.31 9.3c.125-.497.286-.985.48-1.46z"></path><path d="M20.95 19.22a9.72 9.72 0 01-2.85.42c-5 0-9-3.71-9-8.26s4-8.26 9-8.26a8.47 8.47 0 018.77 8.27 7.69 7.69 0 01-2.5 5.92l-.24.21v.32a3.1 3.1 0 000 .37c.037.311.101.62.19.92.203.73.502 1.43.89 2.08a6.43 6.43 0 01-2.67-1.06 12.22 12.22 0 01-1.22-1l-.37.07zm4.32-1.16a9 9 0 002.74-6.68A9.61 9.61 0 0018.1 2C12.53 2 8.01 6.21 8.01 11.38c0 5.17 4.53 9.38 10.1 9.38a10.79 10.79 0 002.9-.4 7.8 7.8 0 00.92.7 7.46 7.46 0 004.19 1.31.66.66 0 00.6-.36.63.63 0 00-.05-.65 8.08 8.08 0 01-1.28-2.58 5.42 5.42 0 01-.15-.75l.03.03z"></path></g></svg>';
            return '<header class="comments__head"><h2 class="section-header" id="comments-title" aria-label="' . h(sblog_t('评论')) . '">' . $icon . '</h2><span class="comments__count">' . h($count) . '</span></header>';
        },
        $content,
        1
    ) ?? $content;
}

function jaguar_adapt_article_content(string $content, array $context): string
{
    $action = (string)($_GET['a'] ?? '');
    $active = (string)($context['active'] ?? '');
    $isPage = $action === 'page' || str_starts_with($active, 'page:');
    $post = null;
    if ($action === 'post') {
        $post = fetch_post_by_identifier((string)($_GET['slug'] ?? ''), true);
    } elseif ($action === 'page') {
        $post = fetch_page_by_identifier((string)($_GET['slug'] ?? ''), true);
    } elseif ($isPage) {
        $post = fetch_page_by_identifier(substr($active, 5), true);
    }
    $content = preg_replace('/<article>/', '<article class="jArticle">', $content, 1) ?? $content;
    $content = str_replace('class="post-title"', 'class="jArticle--headline"', $content);
    $content = str_replace('class="post-content"', 'class="jGraph jArticle--content"', $content);
    if ($post) {
        $content = preg_replace('/<div class="meta">.*?<\/div>/s', jaguar_post_meta($post), $content, 1) ?? $content;
    }
    $content = str_replace('class="post-tags"', 'class="post-tags jArticle--tags"', $content);
    $content = preg_replace('/(<a class="post-tag"[^>]*>)#/', '$1', $content) ?? $content;
    if ($post && content_kind($post) === 'post') {
        $content = preg_replace('/<ul class="pagination">.*?<\/ul>/s', jaguar_render_post_navigation($post), $content, 1) ?? $content;
    }
    $content = jaguar_adapt_comments_header($content);
    return '<main class="layoutSingleColumn u-paddingTop50">' . $content . '</main>';
}

add_theme_filter('body_class', static function (string $classes): string {
    return trim($classes . ' jaguar-theme');
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
        return jaguar_render_category_page((string)($_GET['slug'] ?? '')) ?: $content;
    }
    if ($action === 'tag') {
        return jaguar_render_tag_page((string)($_GET['slug'] ?? ''));
    }
    if ($active === 'archives') {
        return jaguar_render_archive();
    }
    if ($active === 'tags') {
        return jaguar_render_tags();
    }
    if ($active === 'links') {
        return jaguar_render_links();
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '') && $action !== 'post') {
        return jaguar_render_home();
    }
    if ($action === 'post' || $action === 'page' || str_starts_with($active, 'page:')) {
        return jaguar_adapt_article_content($content, $context);
    }
    return $content;
});
