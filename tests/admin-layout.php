<?php

declare(strict_types=1);

// Run: php tests/admin-layout.php [--compare-source /path/to/index-before.php]
// Load actual render functions without booting the blog, its database, or remote services.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

function admin_layout_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function admin_layout_load(string $path, array $names, string $prefix = ''): void
{
    $needed = array_fill_keys($names, true);
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
            $code .= is_array($token)
                ? ($prefix !== '' && $token[0] === T_STRING && in_array($token[1], $names, true) ? $prefix . $token[1] : $token[1])
                : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $started = true;
            } elseif ($token === '}' && --$depth === 0 && $started) { break; }
        }
        eval($code);
        unset($needed[$name]);
    }
    admin_layout_assert($needed === [], 'Missing core functions: ' . implode(', ', array_keys($needed)));
}

function require_admin(): void { $GLOBALS['admin_layout_authorizations']++; }
function current_admin(): array { return ['id' => 1, 'username' => '首页', 'avatar_url' => '']; }
function setting(string $name, string $fallback = ''): string { return $name === 'site_name' ? '首页' : $fallback; }
function default_settings(): array { return ['site_name' => '首页']; }
function url_for(string $action, array $params = []): string { return '/' . $action . ($params ? '?' . http_build_query($params) : ''); }
function url_with_query(string $url, array $params): string { return $url . '?' . http_build_query($params); }
function admin_comments_url(string $filter = 'all', string $search = '', int $page = 1): string { return '/admin_comments?filter=' . $filter; }
function asset_url(string $path): string { return '/' . $path; }
function script_url(): string { return '/index.php'; }
function theme_logo_url(): string { return '/logo.svg'; }
function csrf_token(): string { return 'test-csrf'; }
function csrf_field(): string { return '<input name="csrf" value="test-csrf">'; }
function unread_comment_count(): int { return 3; }
function pull_flash(): ?array { $flash = $GLOBALS['admin_layout_flash'] ?? null; unset($GLOBALS['admin_layout_flash']); return $flash; }
function database_upgrade_pending(): bool { return !empty($GLOBALS['admin_layout_upgrade']); }
function render_layout(string $title, string $content, array $options = []): void
{
    $GLOBALS['admin_layout_result'] = ['title' => $title, 'content' => $content, 'options' => $options, 'flash' => pull_flash()];
}
function plugin_filter(string $hook, mixed $value, array $context = []): mixed
{
    if ($hook === 'admin_sidebar_links') {
        $value[] = ['label' => 'plugin.link', 'note' => 'plugin.note', 'href' => '/plugin', 'active' => false, 'icon' => 'posts'];
    } elseif ($hook === 'editor_field_actions_html') {
        return '<button type="button" data-plugin-field="' . h((string)$context['field']) . '">Plugin</button>';
    } elseif ($hook === 'editor_settings_html') {
        return '<label data-plugin-settings><input name="plugin_option" type="checkbox" value="1">Plugin option</label>';
    } elseif ($hook === 'editor_after_form_html') {
        return '<div data-plugin-after-form>Plugin</div>';
    }
    return $value;
}
function admin_metrics(): array { return array_fill_keys(['avg_views', 'categories', 'comments', 'pending_comments', 'published', 'total_views'], 0); }
function github_update_info(): array { return ['available' => false, 'latest' => '1.0.0', 'database_schema_known' => true]; }
function recent_comment_notifications(): array { return []; }
function fetch_admin_posts(): array { return []; }
function fetch_admin_comments(string $filter, string $search, int $page): array { return [[], 0, 1, 1, $filter]; }
function comment_admin_counts(): array { return array_fill_keys(['all', 'unread', 'pending', 'approved', 'spam'], 0); }
function fetch_categories(): array { return []; }
function category_options(): array { return []; }
function tag_index_data(bool $publishedOnly = true): array
{
    return !empty($GLOBALS['admin_layout_empty_tags']) ? [] : [['label' => '首页', 'slug' => 'home', 'count' => 2]];
}
function all_rows(string $sql, array $params = []): array { return []; }
function val(string $sql, array $params = []): mixed { return 0; }
function one(string $sql, array $params = []): ?array
{
    return str_contains($sql, 'FROM users') ? ['username' => '首页', 'nickname' => '文章管理', 'email' => 'admin@example.com'] : null;
}
function active_theme_slug(): string { return 'default'; }
function active_plugin_slugs(bool $refresh = false): array { return []; }
function available_plugins(): array { return []; }
function available_themes(): array { return []; }
function extension_updates_for(string $type, array $items, bool $refresh = false): array { return ['items' => [], 'error' => '']; }
function extension_store_catalog(bool $refresh = false): array { return ['extensions' => [], 'error' => '', 'stale' => false]; }
function sblog_i18n_languages(): array { return ['zh-CN' => ['name' => '简体中文', 'plugin' => '']]; }
function managed_cache_targets(string $type): array { return []; }
function use_pretty_url(): bool { return false; }
function post_tags(array $post): array { return []; }
function datetime_local_value(int $timestamp): string { return '2026-10-13T12:00'; }

$pageFunctions = [
    'render_admin_page', 'render_admin_posts_page', 'render_admin_comments_page',
    'render_admin_categories_page', 'render_admin_links_page', 'render_admin_tags_page',
    'render_admin_users_page', 'render_admin_media_page', 'render_admin_store_page',
    'render_admin_plugins_page', 'render_admin_themes_page', 'render_admin_settings_page', 'render_editor_page',
];
$sharedFunctions = ['render_admin_sidebar', 'render_admin_topbar', 'translated_admin_form_errors'];
admin_layout_load(dirname(__DIR__) . '/index.php', array_merge($pageFunctions, $sharedFunctions, [
    'h', 'str_sub_u', 'admin_icon', 'render_admin_theme_toggle', 'social_profile_definitions',
    'render_admin_content_start', 'render_admin_content_end', 'render_editor_media_picker', 'sblog_default_translations',
    'sblog_i18n_register', 'sblog_i18n_set_locale', 'sblog_i18n_locale', 'sblog_i18n_message',
    'sblog_i18n_format', 'sblog_i18n_plural_form', 'sblog_t', 'sblog_tn',
]));
admin_layout_load(dirname(__DIR__) . '/plugins/english-language/plugin.php', ['sblog_english_language_map']);
admin_layout_load(dirname(__DIR__) . '/plugins/russian-language/plugin.php', ['sblog_russian_language_map']);
sblog_i18n_register('en', sblog_english_language_map() + ['plugin.link' => 'Plugin menu', 'plugin.note' => 'Plugin help']);
sblog_i18n_register('ru', sblog_russian_language_map() + ['plugin.link' => 'Меню плагина', 'plugin.note' => 'Справка плагина']);
$baseline = ($argv[1] ?? '') === '--compare-source' ? ($argv[2] ?? '') : '';
if ($baseline !== '') { admin_layout_load($baseline, array_merge($pageFunctions, $sharedFunctions), 'baseline_'); }

function admin_layout_capture(string $function, array $args = []): array
{
    $GLOBALS['admin_layout_authorizations'] = 0;
    $GLOBALS['admin_layout_result'] = null;
    $GLOBALS['admin_layout_flash'] = ['type' => 'success', 'message' => 'Notice <saved>'];
    $GLOBALS['admin_layout_upgrade'] = false;
    $_GET = [];
    $level = ob_get_level();
    $function(...$args);
    admin_layout_assert(ob_get_level() === $level, $function . ' leaked an output buffer.');
    admin_layout_assert($GLOBALS['admin_layout_authorizations'] === 1, $function . ' must require administrator access.');
    $result = $GLOBALS['admin_layout_result'];
    admin_layout_assert(is_array($result), $function . ' did not render a document.');
    admin_layout_assert($result['flash'] === null, $function . ' must consume notices in its topbar.');
    admin_layout_assert(substr_count($result['content'], 'Notice &lt;saved&gt;') === 1, $function . ' must display notices once, escaped.');
    admin_layout_assert(substr_count($result['content'], 'class="admin-shell"') === 1, $function . ' must render one shell.');
    admin_layout_assert(substr_count($result['content'], 'class="admin-main"') === 1, $function . ' must render one main container.');
    admin_layout_assert($result['options']['wide'] === true, $function . ' lost its layout options.');
    return $result;
}

$cases = array_fill_keys($pageFunctions, []);
$cases['render_editor_page'] = [null, ['title' => '首页', 'content' => '文章管理']];
$cases['edit'] = [['id' => 7, 'title' => '首页', 'content' => '文章管理', 'published_at' => 1, 'status' => 'published']];
$runs = 0;
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    foreach (['zh-CN', 'en', 'ru'] as $locale) {
        sblog_i18n_set_locale($locale);
        foreach ($cases as $name => $args) {
            $function = $name === 'edit' ? 'render_editor_page' : $name;
            $result = admin_layout_capture($function, $args);
            if ($function === 'render_editor_page') {
                admin_layout_assert(str_contains($result['content'], 'value="首页"'), 'User titles must keep their source language.');
                admin_layout_assert(str_contains($result['content'], '>文章管理</textarea>'), 'User content must keep its source language.');
                admin_layout_assert(substr_count($result['content'], 'data-plugin-field=') === 3, 'Editor action hooks must survive.');
                admin_layout_assert(preg_match('/<details[^>]+data-editor-settings[^>]*>.*data-plugin-settings.*<\/details>/s', $result['content']) === 1, 'Plugin controls must remain inside More settings.');
                admin_layout_assert(str_contains($result['content'], 'data-plugin-after-form'), 'Editor form hook must survive.');
                admin_layout_assert(strpos($result['content'], 'id="title"') < strpos($result['content'], 'id="kind"'), 'The title must precede the content type.');
                admin_layout_assert(str_contains($result['content'], 'name="status" value="draft"') && str_contains($result['content'], 'name="status" value="published"'), 'Both draft and publish actions must survive.');
                admin_layout_assert(str_contains($result['content'], 'data-slug-check-url=') && str_contains($result['content'], 'data-media-picker-open'), 'Slug validation and media selection must coexist.');
                admin_layout_assert(preg_match('/<\/form>\s*<dialog[^>]+data-media-picker/', $result['content']) === 1, 'Media picker search must remain outside the editor form.');
            }
            if ($locale === 'zh-CN' && $baseline !== '') {
                $before = admin_layout_capture('baseline_' . $function, $args);
                foreach (['content', 'title', 'options'] as $field) {
                    $normalize = static fn($value) => is_string($value) ? preg_replace('/>\s+</u', '><', trim($value)) : $value;
                    admin_layout_assert($normalize($before[$field]) === $normalize($result[$field]), $name . ' changed its ' . $field . '.');
                }
            }
            $runs++;
        }
        $sidebar = render_admin_sidebar('store');
        admin_layout_assert(str_contains($sidebar, h(sblog_t('主题与插件'))), 'Store navigation must translate its note.');
        admin_layout_assert(str_contains($sidebar, h(sblog_t('plugin.link'))), 'Plugin navigation must use the same translation API.');
        admin_layout_assert(str_contains($sidebar, '<span class="admin-side__footer-text">首页</span>'), 'Account names must not be translated.');
        $errors = ['用户名不能为空。', '头像地址格式不正确。', '微信链接格式不正确。', '首页'];
        $translated = translated_admin_form_errors($errors);
        admin_layout_assert($translated === [sblog_t($errors[0]), sblog_t('{field}格式不正确。', ['field' => sblog_t('头像地址')]), sblog_t('{field}链接格式不正确。', ['field' => sblog_t('微信')]), '首页'], 'Only known core errors and fields may be translated.');
    }
    $GLOBALS['admin_layout_empty_tags'] = true;
    admin_layout_capture('render_admin_tags_page');
    $GLOBALS['admin_layout_upgrade'] = true;
    $GLOBALS['admin_layout_flash'] = ['type' => 'warning', 'message' => 'Upgrade', 'context' => 'database_upgrade'];
    $bar = render_admin_topbar('站点设置', 'Action', '/action');
    admin_layout_assert(substr_count($bar, 'admin-global-notice"') === 1, 'Database upgrade notices must not duplicate the flash.');
    admin_layout_assert(str_contains($bar, 'href="/action">Action</a>'), 'Plugin topbar action arguments must survive.');
    fwrite(STDOUT, 'PASS ' . $runs . " page renders, translation boundaries, notices, and plugin hooks.\n");
    if ($baseline !== '') { fwrite(STDOUT, "PASS All 12 admin pages and both editor modes match their original HTML and layout options.\n"); }
} catch (Throwable $exception) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    restore_error_handler();
}
