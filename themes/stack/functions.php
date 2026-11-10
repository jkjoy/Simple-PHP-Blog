<?php

declare(strict_types=1);

require_once __DIR__ . '/markdown.php';

function stack_enabled(string $key, string $default = '1'): bool
{
    return theme_setting($key, $default) === '1';
}

function stack_number(string $key, int $default, int $min, int $max): int
{
    return max($min, min($max, (int)theme_setting($key, (string)$default)));
}

function stack_action(): string
{
    return (string)($GLOBALS['sblog_current_action'] ?? $_GET['a'] ?? 'home');
}

function stack_url(string $route, array $params = []): string
{
    $url = $route === 'home' && isset($params['page']) ? home_page_url((int)$params['page']) : url_for($route, $params);
    if (isset($_GET['theme_preview']) && is_admin()) {
        $url = url_with_query($url, ['theme_preview' => 'stack']);
    }
    return $url;
}

function stack_permalink(array $post): string
{
    return stack_url(content_kind($post) === 'page' ? 'page' : 'post', ['slug' => (string)$post['slug']]);
}

function stack_json(string $key): array
{
    $decoded = json_decode(theme_setting($key), true);
    return is_array($decoded) ? $decoded : [];
}

function stack_text(mixed $value, string $fallback = ''): string
{
    return is_string($value) ? $value : $fallback;
}

function stack_post_flag(?array $post, string $key, string $setting, string $default = '1'): bool
{
    $value = $post !== null ? (stack_override($post)[$key] ?? null) : null;
    return is_bool($value) ? $value : stack_enabled($setting, $default);
}

function stack_override(array $post): array
{
    $all = stack_json('article_overrides');
    $item = $all[(string)($post['id'] ?? '')] ?? $all[(string)($post['slug'] ?? '')] ?? [];
    return is_array($item) ? $item : [];
}

function stack_icon(string $name): string
{
    static $icons = [];
    if (!preg_match('/^[a-z-]+$/', $name) || !is_file(__DIR__ . '/assets/icons/' . $name . '.svg')) {
        $name = 'link';
    }
    if (!isset($icons[$name])) {
        $svg = (string)file_get_contents(__DIR__ . '/assets/icons/' . $name . '.svg');
        $icons[$name] = preg_replace('/<svg\b/', '<svg aria-hidden="true" focusable="false"', $svg, 1) ?? '';
    }
    return $icons[$name];
}

function stack_safe_image(string $value): string
{
    $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($value === '' || str_contains($value, '\\') || preg_match('/[\s\x00-\x1f]/', $value) || str_starts_with($value, '//')) {
        return '';
    }
    if (preg_match('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL)) {
        return $value;
    }
    if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
        return $value;
    }
    if (str_starts_with($value, 'uploads/')) {
        return asset_url($value);
    }
    return '';
}

function stack_cover(array $post): string
{
    if (!stack_enabled('article_cover_enabled')) {
        return '';
    }
    // Always redact before reading a cover or excerpt, including on unlocked detail pages.
    $post = public_content_context($post);
    $override = stack_override($post);
    if (array_key_exists('cover', $override)) {
        return is_string($override['cover']) ? stack_safe_image($override['cover']) : '';
    }
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\(\s*(?:<([^>]+)>|([^\s)]+))/u', $content, $matches)) {
        return stack_safe_image((string)($matches[1] !== '' ? $matches[1] : ($matches[2] ?? '')));
    }
    if (preg_match('/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $content, $matches)) {
        return stack_safe_image($matches[1]);
    }
    return '';
}

function stack_excerpt(array $post, ?int $length = null): string
{
    $post = public_content_context($post);
    $text = (string)($post['content'] ?? '');
    if ($length === PHP_INT_MAX) { $text = preg_replace('/^```[^\r\n]*\R?|^```[ \t]*$/mu', '', $text) ?? ''; }
    $text = preg_replace('/!\[[^\]]*\]\([^)]*\)|```[\s\S]*?```|<[^>]+>/', ' ', $text) ?? '';
    $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $text) ?? '';
    $text = preg_replace('/^[ \t]*(?:#{1,6}|>)[ \t]+/mu', '', $text) ?? '';
    $text = preg_replace('/(?<![\p{L}\p{N}])[*_~]{1,3}|[*_~]{1,3}(?![\p{L}\p{N}])/u', '', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', str_replace('`', '', $text)) ?? '';
    $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $length ??= stack_number('article_excerpt_length', 120, 0, 500);
    return $length > 0 ? str_sub_u($text, 0, $length) . (str_len_u($text) > $length ? '…' : '') : '';
}

function stack_date(int $timestamp, bool $updated = false): string
{
    $format = theme_setting($updated ? 'date_updated' : 'date_published', 'Y年m月d日');
    return date($format !== '' ? $format : 'Y-m-d', $timestamp);
}

function stack_categories(): array
{
    static $items;
    return $items ??= all_rows('SELECT c.*, COUNT(p.id) AS post_count FROM categories c LEFT JOIN posts p ON p.category_id = c.id AND p.kind = ? AND p.status = ? AND p.published_at <= ? GROUP BY c.id HAVING COUNT(p.id) > 0 ORDER BY post_count DESC, c.sort_order ASC, c.id DESC', ['post', 'published', time()]);
}

function stack_category_style(array $category): string
{
    $custom = stack_json('category_colors');
    $color = $custom[(string)$category['slug']] ?? $custom[(string)$category['name']] ?? [];
    if (is_array($color) && preg_match('/^#[0-9a-f]{6}$/i', stack_text($color['background'] ?? null)) && preg_match('/^#[0-9a-f]{6}$/i', stack_text($color['color'] ?? null))) {
        return 'background-color:' . $color['background'] . ';color:' . $color['color'];
    }
    $hue = (int)hexdec(hash('fnv1a32', (string)$category['name'])) % 360;
    return 'background-color:hsl(' . $hue . ',60%,84%);color:hsl(' . $hue . ',60%,15%)';
}

function stack_post_category(array $post): ?array
{
    foreach (stack_categories() as $category) {
        if ((int)$category['id'] === (int)($post['category_id'] ?? 0)) {
            return $category;
        }
    }
    return null;
}

function stack_article_header(array $post, bool $list = true): string
{
    $publicPost = public_content_context($post);
    $cover = stack_cover($publicPost);
    $override = stack_override($publicPost);
    $summaryPost = $publicPost;
    $summaryPost['content'] = trim((string)($publicPost['excerpt'] ?? '')) ?: (string)($publicPost['content'] ?? '');
    $subtitle = isset($override['subtitle']) && is_string($override['subtitle']) ? $override['subtitle'] : ($list ? stack_excerpt($summaryPost) : '');
    $category = stack_post_category($publicPost);
    $read = stack_post_flag($publicPost, 'reading_time', 'article_reading_time');
    $words = str_len_u(strip_tags((string)($publicPost['content'] ?? '')));
    $minutes = max(1, (int)ceil($words / stack_number('article_reading_speed', 250, 50, 1000)));
    $time = (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
    ob_start(); ?>
    <header class="article-header">
      <?php if ($cover !== ''): ?><div class="article-image"><a href="<?= h(stack_permalink($post)) ?>"><?= stack_image_html($cover, (string)$post['title'], $list ? 'lazy' : 'eager') ?></a></div><?php endif; ?>
      <div class="article-details">
        <?php if ($category): ?><header class="article-category">
          <a href="<?= h(stack_url('category', ['slug' => $category['slug']])) ?>" style="<?= h(stack_category_style($category)) ?>"><?= h($category['name']) ?></a>
        </header><?php endif; ?>
        <div class="article-title-wrapper">
          <?php if ($list): ?><h2 class="article-title"><a href="<?= h(stack_permalink($post)) ?>"><?= h($post['title']) ?></a><?php if (!empty($post['is_pinned'])): ?><span class="stack-pinned">置顶</span><?php endif; ?></h2><?php else: ?><h1 class="article-title"><a href="<?= h(stack_permalink($post)) ?>"><?= h($post['title']) ?></a><?php if (!empty($post['is_pinned'])): ?><span class="stack-pinned">置顶</span><?php endif; ?></h1><?php endif; ?>
          <?php if ($subtitle !== ''): ?><h3 class="article-subtitle"><?= h($subtitle) ?></h3><?php endif; ?>
        </div>
        <footer class="article-meta">
          <div class="inline-meta">
            <?php if (stack_enabled('article_show_date')): ?><?= stack_icon('date') ?><time class="article-time--published" datetime="<?= h(date(DATE_ATOM, $time)) ?>"><?= h(stack_date($time)) ?></time><?php endif; ?>
            <?php if ($read): ?><?= stack_icon('clock') ?><span class="article-time--reading">阅读时长: <?= $minutes ?> 分钟</span><?php endif; ?>
          </div>
          <?php if ($list && stack_enabled('article_list_tags', '0') && tag_descriptors($publicPost)): ?><div class="article-tags inline-meta"><?= stack_icon('tag') ?><?php foreach (tag_descriptors($publicPost) as $tag): ?><a class="link" href="<?= h(stack_url('tag', ['slug' => $tag['slug']])) ?>"><?= h($tag['label']) ?></a><?php endforeach; ?></div><?php endif; ?>
        </footer>
      </div>
    </header>
    <?php return (string)ob_get_clean();
}

function stack_cards(array $posts, bool $compact = false, bool $pinned = true): string
{
    ob_start(); ?>
    <section class="<?= $compact ? 'article-list--compact' : 'article-list' ?>">
      <?php foreach ($posts as $post): ?>
        <?php if ($compact): ?>
          <article><a href="<?= h(stack_permalink($post)) ?>"><div class="article-details"><h2 class="article-title"><?= h($post['title']) ?><?php if ($pinned && !empty($post['is_pinned'])): ?><span class="stack-pinned">置顶</span><?php endif; ?></h2><footer class="article-meta"><time datetime="<?= h(date(DATE_ATOM, (int)$post['published_at'])) ?>"><?= h(stack_date((int)$post['published_at'])) ?></time></footer></div><?php if (($cover = stack_cover($post)) !== ''): ?><div class="article-image"><img src="<?= h($cover) ?>" width="60" height="60" alt="" loading="lazy"></div><?php endif; ?></a></article>
        <?php else: ?><article class="<?= stack_cover($post) !== '' ? 'has-image' : '' ?>"><?= stack_article_header($post) ?></article><?php endif; ?>
      <?php endforeach; ?>
    </section>
    <?php return (string)ob_get_clean();
}

function stack_empty(string $message): string
{
    return '<div class="stack-empty">' . h($message) . '</div>';
}

function stack_section(string $title, int $count, string $description = ''): string
{
    return '<header><h3 class="section-title">探索</h3><div class="section-card"><div class="section-details"><h3 class="section-count">' . $count . ' 个项目</h3><h1 class="section-term">' . h($title) . '</h1>' . ($description !== '' ? '<h2 class="section-description">' . h($description) . '</h2>' : '') . '</div></div></header>';
}

function stack_pagination(int $page, int $pages): string
{
    if ($pages < 2) { return ''; }
    $start = 2;
    $end = $pages - 1;
    if ($pages > 5) {
        $start = max(2, $page - 1);
        $end = $start + 2;
        if ($end > $pages - 1) {
            $end = $pages - 1;
            $start = $end - 2;
        }
    }
    ob_start(); ?>
    <nav class="pagination" aria-label="分页" data-total="<?= $pages ?>" data-first-url="<?= h(stack_url('home')) ?>" data-format-url="<?= h(stack_url('home', ['page' => 2])) ?>">
      <a class="page-link<?= $page <= 1 ? ' disabled' : '' ?>" aria-label="上一页"<?= $page > 1 ? ' href="' . h(stack_url('home', ['page' => $page - 1])) . '"' : ' aria-disabled="true"' ?>><?= stack_icon('chevron-left') ?></a>
      <a class="page-link<?= $page === 1 ? ' current' : '' ?>" aria-label="第 1 页"<?= $page === 1 ? ' aria-current="page"' : ' href="' . h(stack_url('home')) . '"' ?>>1</a>
      <?php if ($start > 2): ?><button type="button" class="page-link pagination-link pagination-jump-trigger" aria-label="跳转到页码"><?= stack_icon('dots') ?></button><?php endif; ?>
      <?php for ($i = $start; $i <= $end; $i++): ?>
        <a class="page-link<?= $i === $page ? ' current' : '' ?><?= abs($i - $page) > 1 ? ' hide-on-mobile' : '' ?>"<?= $i === $page ? ' aria-current="page"' : '' ?> href="<?= h(stack_url('home', ['page' => $i])) ?>" aria-label="第 <?= $i ?> 页"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($end < $pages - 1): ?><button type="button" class="page-link pagination-link pagination-jump-trigger" aria-label="跳转到页码"><?= stack_icon('dots') ?></button><?php endif; ?>
      <a class="page-link<?= $page === $pages ? ' current' : '' ?>" aria-label="第 <?= $pages ?> 页"<?= $page === $pages ? ' aria-current="page"' : ' href="' . h(stack_url('home', ['page' => $pages])) . '"' ?>><?= $pages ?></a>
      <a class="page-link<?= $page >= $pages ? ' disabled' : '' ?>" aria-label="下一页"<?= $page < $pages ? ' href="' . h(stack_url('home', ['page' => $page + 1])) . '"' : ' aria-disabled="true"' ?>><?= stack_icon('chevron-right') ?></a>
    </nav>
    <dialog id="pagination-jump-dialog" aria-labelledby="pagination-jump-title">
      <form method="dialog" class="pagination-jump-form">
        <header><label id="pagination-jump-title" class="title" for="pagination-jump-input">跳转到页码</label><span class="subtitle">(1 - <?= $pages ?>)</span></header>
        <div class="pagination-jump-input-group"><input type="number" id="pagination-jump-input" min="1" max="<?= $pages ?>" step="1" autofocus autocomplete="off" required><button type="submit" class="btn btn-primary">跳转</button></div>
        <div class="pagination-jump-hint"><kbd>Enter</kbd><span>按 Enter 键跳转</span></div>
      </form>
    </dialog>
    <?php return (string)ob_get_clean();
}

function stack_detail_post(): ?array
{
    static $post, $loaded = false;
    if ($loaded) { return $post; }
    $loaded = true;
    $action = stack_action();
    $slug = trim((string)($_GET['slug'] ?? $_GET['id'] ?? ''));
    $post = match ($action) {
        'post' => fetch_post_by_identifier($slug, is_admin()),
        'page' => fetch_page_by_identifier($slug, is_admin()),
        default => null,
    };
    return $post;
}

function stack_toc_enabled(?array $post): bool
{
    if ($post === null || (content_requires_password($post) && !content_password_is_unlocked($post))) { return false; }
    if (empty($GLOBALS['stack_article_has_headings'])) { return false; }
    return stack_post_flag($post, 'toc', 'article_toc');
}

function stack_extract_content(string $html): ?array
{
    if (!class_exists('DOMDocument')) { return null; }
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><div id="stack-source">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($document);
    $body = $xpath->query('//*[@id="stack-source"]/article[1]//*[contains(concat(" ", normalize-space(@class), " "), " post-content ")]')->item(0);
    if (!$body) { return null; }
    $GLOBALS['stack_article_has_headings'] = $xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6][not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " content-gate ") or @data-no-toc])]', $body)->length > 0;
    // Adapt the already gated core HTML to Stack's markup without parsing private source again.
    foreach ($xpath->query('.//img[not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " content-gate ")])]', $body) as $image) {
        stack_responsive_image($image);
    }
    foreach ($xpath->query('.//table[not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " table-wrapper ")])]', $body) as $table) {
        $wrapper = $document->createElement('div');
        $wrapper->setAttribute('class', 'table-wrapper');
        $table->parentNode->insertBefore($wrapper, $table);
        $wrapper->appendChild($table);
    }
    foreach ($xpath->query('.//a[not(normalize-space(@class)) and not(.//img) and not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " content-gate ")])]', $body) as $link) {
        $link->setAttribute('class', 'link');
    }
    foreach ($xpath->query('.//blockquote', $body) as $quote) {
        $paragraph = $xpath->query('./p[1]', $quote)->item(0);
        if (!$paragraph || !$paragraph->firstChild || $paragraph->firstChild->nodeType !== XML_TEXT_NODE) { continue; }
        if (!preg_match('/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*/', $paragraph->firstChild->nodeValue, $alert)) { continue; }
        $type = strtolower($alert[1]);
        $defaults = ['note' => ['📝', '说明'], 'tip' => ['💡', '提示'], 'important' => ['📌', '重要'], 'warning' => ['⚠️', '警告'], 'caution' => ['🚨', '注意']];
        $paragraph->firstChild->nodeValue = substr($paragraph->firstChild->nodeValue, strlen($alert[0]));
        $customTitle = $document->createDocumentFragment();
        while ($paragraph->firstChild && strtolower($paragraph->firstChild->nodeName) !== 'br') {
            $customTitle->appendChild($paragraph->firstChild);
        }
        if ($paragraph->firstChild) { $paragraph->removeChild($paragraph->firstChild); }
        if (!$paragraph->hasChildNodes()) { $quote->removeChild($paragraph); }
        $quote->setAttribute('class', trim($quote->getAttribute('class') . ' alert alert-' . $type));
        $header = $document->createElement('div');
        $header->setAttribute('class', 'alert-header');
        $icon = $document->createElement('span');
        $icon->setAttribute('class', 'alert-icon');
        $icon->appendChild($document->createTextNode(theme_setting('article_alert_' . $type, $defaults[$type][0])));
        $label = $document->createElement('span');
        $label->setAttribute('class', 'alert-title');
        if (trim($customTitle->textContent) !== '') {
            $label->appendChild($customTitle);
        } else {
            $label->appendChild($document->createTextNode($defaults[$type][1]));
        }
        $header->appendChild($icon);
        $header->appendChild($label);
        $inner = $document->createElement('div');
        $inner->setAttribute('class', 'alert-body');
        while ($quote->firstChild) { $inner->appendChild($quote->firstChild); }
        $quote->appendChild($header);
        $quote->appendChild($inner);
    }
    $content = '';
    foreach ($body->childNodes as $child) { $content .= $document->saveHTML($child); }
    $comments = $xpath->query('//*[@id="comments"]')->item(0);
    return [$content, $comments ? $document->saveHTML($comments) : ''];
}

function stack_related(array $post): array
{
    $tags = post_tags(public_content_context($post));
    $candidates = fetch_feed_posts(200);
    $scores = [];
    foreach ($candidates as $candidate) {
        if ((int)$candidate['id'] === (int)$post['id']) { continue; }
        $score = count(array_intersect($tags, post_tags($candidate))) * 2;
        if (!empty($post['category_id']) && (int)$post['category_id'] === (int)($candidate['category_id'] ?? 0)) { $score++; }
        if ($score > 0) { $scores[(int)$candidate['id']] = $score; }
    }
    $candidates = array_values(array_filter($candidates, static fn(array $item): bool => isset($scores[(int)$item['id']])));
    usort($candidates, static fn(array $a, array $b): int => $scores[(int)$b['id']] <=> $scores[(int)$a['id']] ?: (int)$b['published_at'] <=> (int)$a['published_at']);
    return array_slice($candidates, 0, stack_number('article_related_limit', 3, 1, 6));
}

function stack_detail(array $post, array $parts): string
{
    [$body, $comments] = $parts;
    $public = public_content_context($post);
    $override = stack_override($public);
    $locked = content_requires_password($post) && !content_password_is_unlocked($post);
    $license = $override['license'] ?? theme_setting('article_license_default', 'Licensed under CC BY-NC-SA 4.0');
    $licenseUrl = safe_link_url(stack_text($override['license_url'] ?? null, theme_setting('article_license_url')));
    $comments = str_replace('<textarea id="comment-content"', '<textarea placeholder="' . h(theme_setting('comments_placeholder', '欢迎留下你的想法…')) . '" id="comment-content"', $comments);
    ob_start(); ?>
    <article class="main-article<?= stack_cover($public) !== '' ? ' has-image' : '' ?>">
      <?= stack_article_header($post, false) ?>
      <?php if (stack_toc_enabled($post)): ?><aside class="article-toc" data-stack-toc-container><details><summary><div class="article-toc-title"><?= stack_icon('chevron-right') ?><span>目录</span></div></summary><nav class="toc-nav" data-stack-toc-list></nav></details></aside><?php endif; ?>
      <section class="article-content"><?= $body ?></section>
      <footer class="article-meta article-footer">
        <?php if (!$locked && tag_descriptors($public)): ?><section class="article-tags"><?php foreach (tag_descriptors($public) as $tag): ?><a href="<?= h(stack_url('tag', ['slug' => $tag['slug']])) ?>"><?= h($tag['label']) ?></a><?php endforeach; ?></section><?php endif; ?>
        <?php if (!$locked && stack_enabled('article_license_enabled', '0') && is_string($license) && $license !== ''): ?><section class="article-copyright inline-meta"><?= stack_icon('copyright') ?><?php if ($licenseUrl !== '#'): ?><a href="<?= h($licenseUrl) ?>" rel="license noopener" target="_blank"><?= h($license) ?></a><?php else: ?><span><?= h($license) ?></span><?php endif; ?></section><?php endif; ?>
        <?php if (stack_enabled('article_show_updated', '0') && (int)$post['updated_at'] > (int)$post['published_at']): ?><section class="article-lastmod inline-meta"><?= stack_icon('clock') ?><span>最后更新于 <?= h(stack_date((int)$post['updated_at'], true)) ?></span></section><?php endif; ?>
      </footer>
    </article>
    <?php if (!$locked && content_kind($post) === 'post' && stack_enabled('article_related') && ($related = stack_related($post))): ?><aside><h2 class="section-title">相关文章</h2><div class="subsection-list"><div class="article-list--tile"><?php foreach ($related as $item): ?><?php $image = stack_cover($item); ?><article class="<?= $image !== '' ? 'has-image' : '' ?>"><a href="<?= h(stack_permalink($item)) ?>"><?php if ($image !== ''): ?><div class="article-image"><img src="<?= h($image) ?>" alt="" loading="lazy"></div><?php endif; ?><div class="article-details"><h2 class="article-title"><?= h($item['title']) ?></h2></div></a></article><?php endforeach; ?></div></div></aside><?php endif; ?>
    <?php if (stack_enabled('comments_enabled')): ?><?= $comments ?><?php endif; ?>
    <?php return (string)ob_get_clean();
}

function stack_search_data(): array
{
    if (!stack_enabled('search_enabled')) { return []; }
    // Public helpers remove password bodies and reply-only blocks before indexing.
    $posts = array_map('public_content_context', all_rows('SELECT * FROM posts WHERE status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC', ['published', time()]));
    return array_map(static fn(array $post): array => ['title' => (string)$post['title'], 'url' => stack_permalink($post), 'excerpt' => stack_excerpt($post, 160), 'content' => stack_excerpt($post, PHP_INT_MAX), 'tags' => post_tags($post), 'date' => stack_date((int)$post['published_at'])], $posts);
}

function stack_search_form(bool $widget = false): string
{
    ob_start(); ?>
    <form action="<?= h(stack_url('home')) ?>" class="search-form<?= $widget ? ' widget' : '' ?>" role="search">
      <?php if (isset($_GET['theme_preview']) && is_admin()): ?><input type="hidden" name="theme_preview" value="stack"><?php endif; ?>
      <p><label for="<?= $widget ? 'stack-widget-search' : 'stack-search' ?>">搜索</label><input id="<?= $widget ? 'stack-widget-search' : 'stack-search' ?>" name="stack_search" value="<?= h((string)($_GET['stack_search'] ?? '')) ?>" placeholder="<?= h(theme_setting('search_placeholder', '输入关键词搜索…')) ?>"<?= $widget ? '' : ' data-stack-search' ?> type="search" autocomplete="off"></p>
      <button type="submit" title="搜索" aria-label="搜索"><?= stack_icon('search') ?></button>
    </form>
    <?php return (string)ob_get_clean();
}

function stack_filter_content(string $content, array $context): string
{
    $action = stack_action();
    if (($context['options']['status'] ?? 200) !== 200) { return '<section class="stack-empty">' . $content . '</section>'; }
    if ($action === 'home' && isset($_GET['stack_search']) && stack_enabled('search_enabled')) {
        $query = str_sub_u(trim((string)$_GET['stack_search']), 0, 200);
        $results = array_values(array_filter(stack_search_data(), static function (array $item) use ($query): bool {
            $haystack = str_lower_u($item['title'] . ' ' . $item['content'] . ' ' . implode(' ', $item['tags']));
            foreach (preg_split('/\s+/u', str_lower_u($query)) ?: [] as $term) {
                if ($term !== '' && !str_contains($haystack, $term)) { return false; }
            }
            return $query !== '';
        }));
        $html = stack_search_form() . '<section class="search-result"><h3 class="search-result--title section-title" data-stack-search-status aria-live="polite">' . count($results) . ' 个结果</h3><div class="search-result--list article-list--compact stack-search-results" data-stack-search-results>';
        foreach ($results as $item) { $html .= '<article><a href="' . h($item['url']) . '"><div class="article-details"><h2 class="article-title">' . h($item['title']) . '</h2><p class="article-subtitle">' . h(str_sub_u($item['excerpt'], 0, 160)) . '</p><footer class="article-meta">' . h($item['date']) . '</footer></div></a></article>'; }
        return $html . '</div></section>';
    }
    if ($action === 'home' && ($context['title'] ?? '') === ($context['site_name'] ?? '')) {
        $page = max(1, (int)($_GET['p'] ?? 1));
        $perPage = max(1, (int)setting('posts_per_page', '6'));
        $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);
        return ($posts ? stack_cards($posts) : stack_empty('还没有已发布的文章。')) . stack_pagination($page, max(1, (int)ceil(count_published_posts() / $perPage)));
    }
    if ($action === 'archives') {
        $groups = [];
        foreach (fetch_archive_posts() as $post) { $groups[date('Y', (int)$post['published_at'])][] = $post; }
        $html = '';
        if ($categories = stack_categories()) {
            $html .= '<header><h2 class="section-title">分类</h2><div class="subsection-list"><div class="article-list--tile">';
            foreach ($categories as $category) { $html .= '<article><a href="' . h(stack_url('category', ['slug' => $category['slug']])) . '"><div class="article-details" style="' . h(stack_category_style($category)) . '"><h2 class="article-title">' . h($category['name']) . '</h2></div></a></article>'; }
            $html .= '</div></div></header>';
        }
        foreach ($groups as $year => $posts) { $html .= '<div class="archives-group" id="' . h($year) . '"><h2 class="archives-date section-title"><a href="#' . h($year) . '">' . h($year) . '</a></h2>' . stack_cards($posts, true, false) . '</div>'; }
        return $html !== '' ? $html : stack_empty('归档还是空的。');
    }
    if ($action === 'tags' || $action === 'categories') {
        $tags = $action === 'tags';
        $items = $tags ? tag_index_data() : stack_categories();
        $html = stack_section($tags ? '标签' : '分类', count($items)) . '<section class="article-list--compact">';
        foreach ($items as $item) {
            $html .= '<article><a href="' . h(stack_url($tags ? 'tag' : 'category', ['slug' => $item['slug']])) . '"><div class="article-details"><h2 class="article-title">' . h($tags ? $item['label'] : $item['name']) . '</h2><footer class="article-meta">' . h($tags ? $item['count'] : $item['post_count']) . ' 篇文章</footer></div></a></article>';
        }
        return $html . '</section>';
    }
    if ($action === 'tag' || $action === 'category') {
        $slug = (string)($_GET['slug'] ?? '');
        $category = $action === 'category' ? one('SELECT * FROM categories WHERE slug = ?', [$slug]) : null;
        $posts = $category ? array_map('public_content_context', all_rows('SELECT * FROM posts WHERE category_id = ? AND kind = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', [(int)$category['id'], 'post', 'published', time()])) : fetch_posts_by_tag_slug($slug);
        return stack_section((string)($context['title'] ?? ''), count($posts), (string)($category['description'] ?? '')) . ($posts ? stack_cards($posts, true) : stack_empty('这里还没有文章。'));
    }
    if ($post = stack_detail_post()) {
        $parts = stack_extract_content($content);
        if ($parts !== null) { return stack_detail($post, $parts); }
    }
    if ($action === 'links') {
        return '<article class="main-article"><section class="article-content">' . $content . '</section></article>';
    }
    return '<section class="stack-fallback">' . $content . '</section>';
}

function stack_widgets(bool $detail): array
{
    $keys = preg_split('/[\s,]+/', theme_setting($detail ? 'widgets_page' : 'widgets_home', $detail ? 'toc' : 'search,archives,categories,tag-cloud')) ?: [];
    $keys = array_values(array_unique(array_intersect($keys, ['search', 'archives', 'categories', 'tag-cloud', 'toc'])));
    return array_values(array_filter($keys, static fn(string $key): bool => ($key !== 'search' || stack_enabled('search_enabled')) && ($key !== 'toc' || stack_toc_enabled(stack_detail_post()))));
}

function stack_render_widgets(array $widgets): string
{
    ob_start();
    foreach ($widgets as $widget) {
        if ($widget === 'search') { echo stack_search_form(true); continue; }
        $toc = $widget === 'toc';
        $archive = $widget === 'archives';
        $category = $widget === 'categories';
        $title = $toc ? '目录' : ($archive ? '归档' : ($category ? '分类' : '标签云'));
        $icon = $toc ? 'hash' : ($archive ? 'infinity' : ($category ? 'categories' : 'tag')); ?>
        <section class="widget <?= $toc || $archive ? 'archives' : 'tagCloud' ?>"<?= $toc ? ' data-stack-toc-container' : '' ?>>
          <div class="widget-icon"><?= stack_icon($icon) ?></div><h2 class="widget-title section-title"><?php if (!$toc): ?><a href="<?= h(stack_url($archive ? 'archives' : ($category ? 'categories' : 'tags'))) ?>"><?php endif; ?><?= h($title) ?><?php if (!$toc): ?></a><?php endif; ?></h2>
          <?php if ($toc): ?><div class="widget--toc"><nav class="toc-nav" id="TableOfContents" data-stack-toc-list></nav></div>
          <?php elseif ($archive): ?>
            <div class="widget-archive--list"><?php $years = []; foreach (fetch_archive_posts() as $post) { $year = date('Y', (int)$post['published_at']); $years[$year] = ($years[$year] ?? 0) + 1; } $limit = stack_number('widget_archives_limit', 5, 1, 20); foreach (array_slice($years, 0, $limit, true) as $year => $count): ?><div class="archives-year"><a href="<?= h(stack_url('archives')) ?>#<?= h($year) ?>"><span class="year"><?= h($year) ?></span><span class="count"><?= h($count) ?></span></a></div><?php endforeach; ?><?php if (count($years) > $limit): ?><div class="archives-year"><a href="<?= h(stack_url('archives')) ?>"><span class="year">更多</span></a></div><?php endif; ?></div>
          <?php else: ?><div class="tagCloud-tags"><?php $items = $category ? stack_categories() : tag_index_data(); $limit = stack_number($category ? 'widget_categories_limit' : 'widget_tags_limit', 10, 1, 50); foreach (array_slice($items, 0, $limit) as $item): ?><a href="<?= h(stack_url($category ? 'category' : 'tag', ['slug' => $item['slug']])) ?>"><?= h($category ? $item['name'] : $item['label']) ?></a><?php endforeach; ?></div><?php endif; ?>
        </section>
    <?php }
    return (string)ob_get_clean();
}

add_theme_filter('body_class', static function (string $classes, array $context): string {
    $detail = stack_detail_post() !== null;
    return $classes . ' stack-theme ' . ($detail ? 'article-page' : (stack_action() === 'archives' ? 'template-archives' : (isset($_GET['stack_search']) ? 'template-search' : '')));
});
add_theme_filter('content', 'stack_filter_content', 20);
add_theme_filter('comments_labels', static function (array $labels): array {
    $labels['title'] = theme_setting('comments_title', '评论');
    return $labels;
});
