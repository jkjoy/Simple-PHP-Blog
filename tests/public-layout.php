<?php

declare(strict_types=1);

// Run: php tests/public-layout.php [--compare-source /path/to/index-before.php]
// Execute actual public renderers with isolated model fixtures, without loading the blog or its database/cache.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

final class PublicLayoutNotFound extends RuntimeException {}

function public_layout_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) { throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)); }
}
function public_layout_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
function public_layout_functions(string $path, array $names): array
{
    $needed = array_fill_keys($names, true);
    $result = [];
    $tokens = token_get_all((string)file_get_contents($path));
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $name = $tokens[$i + 2][1] ?? '';
        if (!isset($needed[$name])) { continue; }
        $code = '';
        $depth = 0;
        $started = false;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $started = true;
            } elseif ($token === '}' && --$depth === 0 && $started) { break; }
        }
        $result[$name] = $code;
        unset($needed[$name]);
    }
    public_layout_same([], array_keys($needed), 'Missing actual functions.');
    return $result;
}
function public_layout_load(string $path, array $names, string $prefix = ''): void
{
    foreach (public_layout_functions($path, $names) as $code) {
        if ($prefix !== '') {
            $renamed = '';
            foreach (token_get_all('<?php ' . $code) as $index => $token) {
                if ($index === 0) { continue; }
                $renamed .= is_array($token)
                    ? ($token[0] === T_STRING && in_array($token[1], $names, true) ? $prefix . $token[1] : $token[1])
                    : $token;
            }
            $code = $renamed;
        }
        eval($code);
    }
}
function public_layout_event(string $name, array $args = []): void
{
    $GLOBALS['public_layout_trace'][] = [$name, $args, ob_get_level()];
}
function url_for(string $action, array $params = []): string { return '/' . $action . ($params ? '?' . http_build_query($params) : ''); }
function is_admin(): bool { return !empty($GLOBALS['public_layout_fixture']['admin']); }
function content_password_is_unlocked(array $post): bool { return !empty($GLOBALS['public_layout_fixture']['unlocked']); }
function send_private_content_headers(array $post): void { public_layout_event(__FUNCTION__, [(int)$post['id']]); }
function increment_content_views(array $post): void { public_layout_event(__FUNCTION__, [(int)$post['id']]); }
function pull_comment_feedback(int $id): array { public_layout_event(__FUNCTION__, [$id]); return [['content' => '缓存评论'], ['已有错误']]; }
function post_neighbors(array $post): array
{
    public_layout_event(__FUNCTION__, [(int)$post['id']]);
    return !empty($GLOBALS['public_layout_fixture']['no_neighbors'])
        ? ['newer' => null, 'older' => null]
        : ['newer' => ['slug' => 'newer', 'title' => '首页'], 'older' => ['slug' => 'older', 'title' => '文章管理']];
}
function public_date(int $timestamp): string { return date('Y-m-d', $timestamp); }
function friendly_public_date(int $timestamp): string { return date('Y-m-d', $timestamp); }
function public_content_description(array $post): string { public_layout_event(__FUNCTION__, [(int)$post['id']]); return (string)$post['description']; }
function render_content_html(array $post): string
{
    public_layout_event(__FUNCTION__, [(int)$post['id']]);
    return content_requires_password($post) && !content_password_is_unlocked($post)
        ? '<aside data-password-gate>Locked</aside>'
        : '<p data-content>' . h((string)$post['content']) . '</p><span data-plugin-content>Plugin</span>';
}
function render_comments_section(array $post, array $form = [], array $errors = []): string
{
    public_layout_event(__FUNCTION__, [(int)$post['id'], $form, $errors]);
    return '<section data-comments>' . h((string)($form['content'] ?? '')) . h(implode(';', $errors)) . '</section>';
}
function tag_descriptors(array $post): array { return $post['tags'] ? [['slug' => 'known', 'label' => '首页']] : []; }
function public_layout_posts(): array { return !empty($GLOBALS['public_layout_fixture']['empty']) ? [] : [$GLOBALS['public_layout_post']]; }
function fetch_archive_posts(): array { return public_layout_posts(); }
function fetch_categories(): array { return !empty($GLOBALS['public_layout_fixture']['empty']) ? [] : [$GLOBALS['public_layout_category']]; }
function tag_index_data(bool $publishedOnly = true): array { return !empty($GLOBALS['public_layout_fixture']['empty']) ? [] : [['slug' => 'known', 'label' => '首页', 'count' => 2]]; }
function tag_meta_cache(): array { return ['slugs' => ['known' => '首页']]; }
function fetch_posts_by_tag_slug(string $slug): array { return trim($slug) === 'missing' ? [] : public_layout_posts(); }
function render_public_post_list(array $posts): string
{
    public_layout_event(__FUNCTION__, [array_column($posts, 'id')]);
    return '<ul data-public-posts><li>' . h((string)$posts[0]['title']) . '</li></ul>';
}
function one(string $sql, array $params = []): ?array
{
    public_layout_event(__FUNCTION__, [$sql, $params]);
    if (str_contains($sql, 'FROM posts p')) {
        return ['views' => 8, 'username' => '首页', 'nickname' => '文章管理', 'category_name' => '首页', 'category_slug' => 'known'];
    }
    if (str_contains($sql, 'FROM categories')) { return $params[0] === 'missing' ? null : $GLOBALS['public_layout_category']; }
    throw new RuntimeException('Unexpected isolated query: ' . $sql);
}
function all_rows(string $sql, array $params = []): array
{
    public_layout_event(__FUNCTION__, [$sql]);
    if (str_contains($sql, 'FROM posts')) { return public_layout_posts(); }
    if (str_contains($sql, 'FROM links')) {
        return !empty($GLOBALS['public_layout_fixture']['empty']) ? [] : [
            ['name' => '首页 & 网站', 'url' => 'https://example.com/', 'icon_url' => 'https://example.com/icon.png', 'description' => '文章管理 <说明>'],
            ['name' => '朋友', 'url' => 'https://example.org/', 'icon_url' => 'javascript:alert(1)', 'description' => ''],
        ];
    }
    throw new RuntimeException('Unexpected isolated query: ' . $sql);
}
function simple_error_page(string $title, string $message, int $status): never
{
    public_layout_event(__FUNCTION__, [$title, $message, $status]);
    throw new PublicLayoutNotFound($title, $status);
}
function render_layout(string $title, string $content, array $options = []): void
{
    public_layout_event(__FUNCTION__, [$title, $options]);
    $GLOBALS['public_layout_result'] = ['title' => $title, 'content' => $content, 'options' => $options, 'flash' => $GLOBALS['public_layout_flash']];
    unset($GLOBALS['public_layout_flash']);
}

$renderers = [
    'render_archives', 'render_post_page', 'render_page_view', 'render_tags_index',
    'render_categories_index', 'render_tag_page', 'render_category_page', 'render_links_page',
];
$source = dirname(__DIR__) . '/index.php';
public_layout_load($source, array_merge($renderers, [
    'render_public_layout', 'render_tag_chips', 'h', 'safe_link_url', 'content_kind',
    'is_live_content', 'is_live_post', 'post_state', 'content_allows_comments', 'content_requires_password',
    'sblog_default_translations', 'sblog_i18n_register', 'sblog_i18n_set_locale', 'sblog_i18n_locale',
    'sblog_i18n_message', 'sblog_i18n_format', 'sblog_i18n_plural_form', 'sblog_t', 'sblog_tn',
]));
public_layout_load(dirname(__DIR__) . '/plugins/english-language/plugin.php', ['sblog_english_language_map']);
public_layout_load(dirname(__DIR__) . '/plugins/russian-language/plugin.php', ['sblog_russian_language_map']);
sblog_i18n_register('en', sblog_english_language_map());
sblog_i18n_register('ru', sblog_russian_language_map());
date_default_timezone_set('UTC');
$baseline = ($argv[1] ?? '') === '--compare-source' ? ($argv[2] ?? '') : '';
if ($baseline !== '') {
    public_layout_load($baseline, $renderers, 'baseline_');
    public_layout_same(public_layout_functions($baseline, ['render_layout', 'render_home']), public_layout_functions($source, ['render_layout', 'render_home']), 'Theme layout scope/hooks and the home document title must remain unchanged.');
}

function public_layout_capture(string $function, array $fixture, string $variant = ''): array
{
    $GLOBALS['public_layout_fixture'] = $fixture;
    $GLOBALS['public_layout_trace'] = [];
    $GLOBALS['public_layout_result'] = null;
    $GLOBALS['public_layout_flash'] = ['type' => 'success', 'message' => '保存完成'];
    $post = array_replace([
        'id' => 1, 'kind' => 'post', 'slug' => 'known', 'title' => '首页', 'content' => '文章管理 <正文>',
        'description' => '文章管理', 'status' => 'published', 'published_at' => 1700000000,
        'created_at' => 1600000000, 'updated_at' => 1700000000, 'views' => 7, 'tags' => ['首页'],
        'allow_comments' => 1, 'content_password_hash' => '',
    ], $fixture['post'] ?? []);
    $GLOBALS['public_layout_post'] = $post;
    $GLOBALS['public_layout_category'] = array_replace(['id' => 1, 'slug' => 'known', 'name' => '首页', 'description' => '文章管理', 'post_count' => 2], $fixture['category'] ?? []);
    $coreName = str_replace('baseline_', '', $function);
    $args = match ($coreName) {
        'render_post_page' => $variant === 'form' ? [$post, ['content' => '输入评论'], ['请填写昵称。']] : [$post],
        'render_page_view' => [array_replace($post, ['kind' => 'page'])],
        'render_tag_page', 'render_category_page' => [$variant === '' ? ' known ' : $variant],
        default => [],
    };
    $level = ob_get_level();
    ob_start();
    try { $function(...$args); }
    catch (PublicLayoutNotFound $exception) { $GLOBALS['public_layout_result'] = ['status' => $exception->getCode()]; }
    public_layout_same($level + 1, ob_get_level(), $function . ' leaked an output buffer.');
    public_layout_same('', (string)ob_get_clean(), $function . ' output escaped its layout content.');
    $result = $GLOBALS['public_layout_result'];
    public_layout_assert(is_array($result), $function . ' must render or return 404.');
    if (isset($result['options'])) {
        public_layout_same(['active', 'mode', 'description'], array_keys($result['options']), $function . ' must preserve option key order.');
        public_layout_same('public', $result['options']['mode'], $function . ' must remain public.');
        public_layout_assert(!isset($GLOBALS['public_layout_flash']), $function . ' must let its layout consume the notice.');
    }
    return [$result, $GLOBALS['public_layout_trace']];
}

$cases = [];
foreach ($renderers as $function) { $cases[] = [$function, []]; }
foreach (['render_archives', 'render_tags_index', 'render_categories_index', 'render_category_page', 'render_links_page'] as $function) { $cases[] = [$function, ['empty' => true]]; }
$cases = array_merge($cases, [
    ['render_post_page', ['post' => ['content_password_hash' => 'hash']]],
    ['render_post_page', ['unlocked' => true, 'post' => ['content_password_hash' => 'hash']]],
    ['render_post_page', ['admin' => true, 'post' => ['status' => 'draft', 'published_at' => 0]]],
    ['render_post_page', ['admin' => true, 'post' => ['published_at' => 4102444800]]],
    ['render_post_page', ['no_neighbors' => true, 'post' => ['tags' => []]]],
    ['render_post_page', [], 'form'],
    ['render_page_view', ['post' => ['allow_comments' => 0]]],
    ['render_page_view', ['admin' => true, 'post' => ['status' => 'draft', 'published_at' => 0]]],
    ['render_page_view', ['admin' => true, 'post' => ['published_at' => 4102444800]]],
    ['render_category_page', ['category' => ['description' => '   ']]],
    ['render_tag_page', [], 'legacy'],
    ['render_tag_page', [], 'missing'],
    ['render_category_page', [], 'missing'],
]);
$runs = 0;
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
try {
    foreach (['zh-CN', 'en', 'ru'] as $locale) {
        sblog_i18n_set_locale($locale);
        foreach ($cases as $case) {
            [$function, $fixture] = $case;
            $variant = $case[2] ?? '';
            $actual = public_layout_capture($function, $fixture, $variant);
            if ($baseline !== '') { public_layout_same(public_layout_capture('baseline_' . $function, $fixture, $variant), $actual, $locale . ': public HTML, titles, options, buffer timing or call order changed for ' . $function); }
            if (($actual[0]['status'] ?? 200) === 404) { public_layout_same(404, $actual[0]['status'], 'Invisible content must remain 404.'); }
            if (in_array($function, ['render_post_page', 'render_page_view'], true)) {
                public_layout_same('首页', $actual[0]['title'], 'User titles must not be translated.');
                public_layout_same('文章管理', $actual[0]['options']['description'], 'User descriptions must not be translated.');
            }
            if ($function === 'render_post_page' && !empty($fixture['post']['content_password_hash']) && empty($fixture['unlocked'])) {
                public_layout_assert(str_contains($actual[0]['content'], 'data-password-gate'), 'Protected content must retain its gate.');
                public_layout_assert(!str_contains($actual[0]['content'], 'data-comments'), 'Locked articles must not render comments.');
                public_layout_assert(!in_array('increment_content_views', array_column($actual[1], 0), true), 'Locked articles must not count views.');
            }
            $runs++;
        }
    }
    fwrite(STDOUT, 'PASS ' . $runs . " public renders: languages, previews, comments, protection, empty pages, 404s and layout options.\n");
    if ($baseline !== '') { fwrite(STDOUT, "PASS Original HTML, titles, option order and buffer/call timing match exactly; theme layout and home functions are unchanged.\n"); }
} catch (Throwable $exception) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally { restore_error_handler(); }
