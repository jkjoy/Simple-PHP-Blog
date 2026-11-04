<?php

declare(strict_types=1);

namespace SBlogAdminExtensionsTests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Stringable;
use Throwable;

// Run: php tests/admin-extensions.php. Exercise actual renderers without a database or network.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function same(mixed $expected, mixed $actual, string $message): void
{
    $GLOBALS['extension_assertions']++;
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function load_core(): void
{
    $needed = array_fill_keys([
        'h', 'url_for', 'url_with_query', 'extension_store_search_query',
        'render_admin_store_page', 'render_admin_plugins_page', 'render_admin_themes_page',
    ], true);
    $tokens = token_get_all((string)file_get_contents(dirname(__DIR__) . '/index.php'));
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $j = $i + 1;
        while (is_array($tokens[$j] ?? null) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
        if (!is_array($tokens[$j] ?? null) || !isset($needed[$tokens[$j][1]])) { continue; }
        $name = $tokens[$j][1];
        $code = '';
        $depth = 0;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; }
            elseif ($token === '}' && --$depth === 0) { break; }
        }
        eval('namespace ' . __NAMESPACE__ . '; use \\Stringable; ' . $code);
        unset($needed[$name]);
    }
    same([], array_keys($needed), 'Every exercised renderer and URL helper must come from the application.');
}

function require_admin(): void { $GLOBALS['extension_authorizations']++; }
function setting(string $key, string $default = ''): string { return $key === 'active_theme' ? ($GLOBALS['extension_active_theme'] ?? 'default') : $default; }
function use_pretty_url(): bool { return $GLOBALS['extension_pretty'] ?? false; }
function app_path(string $path): string { return '/blog' . $path; }
function script_url(): string { return '/blog/index.php'; }
function sblog_t(string $message, array $parameters = []): string
{
    foreach ($parameters as $key => $value) { $message = str_replace('{' . $key . '}', (string)$value, $message); }
    return $message;
}
function sblog_tn(string $message, int $count): string { return sblog_t($message, ['count' => $count]); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="test-token">'; }
function admin_icon(string $name): string { return ''; }
function render_admin_sidebar(string $active): string { return ''; }
function render_admin_content_start(string $title, string $sidebar): string { return '<main>'; }
function render_admin_content_end(string $title, string $active, string $description): void { $GLOBALS['extension_html'] = (string)ob_get_clean() . '</main>'; }
function available_plugins(): array { return $GLOBALS['extension_plugins']; }
function available_themes(): array { return $GLOBALS['extension_themes']; }
function active_plugin_slugs(bool $refresh = false): array { return $GLOBALS['extension_active_plugins'] ?? []; }
function active_theme_slug(): string { return $GLOBALS['extension_active_theme'] ?? 'default'; }
function plugin_display_metadata(string $slug, array $manifest): array { return $manifest; }
function theme_display_metadata(string $slug, array $manifest): array { return $manifest; }
function theme_settings_schema(string $slug): array { return []; }
function extension_updates_for(string $type, array $items, bool $refresh = false): array
{
    $GLOBALS['extension_update_inventory'] = array_keys($items);
    $GLOBALS['extension_update_refresh'] = $refresh;
    return ['items' => array_fill_keys(array_keys($items), ['version' => '2.0.0', 'requires' => '']), 'error' => ''];
}
function extension_store_catalog(bool $refresh = false): array
{
    $GLOBALS['extension_catalog_refresh'] = $refresh;
    return ['extensions' => $GLOBALS['extension_catalog'], 'error' => '', 'stale' => false];
}
function extension_installed_manifest(string $type, string $slug): ?array { return null; }
function normalize_version(string $version): string { return $version; }
function extension_is_compatible(array $extension): bool { return true; }

function inventory(int $count, string $type): array
{
    $items = [];
    for ($i = 0; $i < $count; $i++) {
        $slug = $type === 'theme' && $i === 0 ? 'default' : $type . '-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $items[$slug] = ['name' => $slug, 'description' => 'Description <safe>', 'version' => '1.0.0', 'author' => 'Author', 'url' => '', 'locale' => '', 'settings_action' => '', 'exclusive_group' => ''];
    }
    return $items;
}

function capture(string $renderer, array $query = []): DOMXPath
{
    $_GET = $query;
    $GLOBALS['extension_authorizations'] = 0;
    $GLOBALS['extension_html'] = '';
    $level = ob_get_level();
    $renderer();
    same($level, ob_get_level(), 'Renderers must close their own output buffers.');
    same(1, $GLOBALS['extension_authorizations'], 'Extension pages must require administrator access.');
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $GLOBALS['extension_html']);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}

function query_of(DOMElement $link): array
{
    parse_str((string)parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
    return $query;
}

function row_slugs(DOMXPath $xpath, string $type): array
{
    $slugs = [];
    foreach ($xpath->query('//tbody/tr') as $row) {
        $input = $xpath->query('.//input[@name="' . ($type === 'theme' ? 'theme' : 'plugin') . '"]', $row)[0];
        $slugs[] = $input?->getAttribute('value');
    }
    return $slugs;
}

function store_slugs(DOMXPath $xpath): array
{
    $slugs = [];
    foreach ($xpath->query('//article//input[@name="slug"]') as $input) { $slugs[] = $input->getAttribute('value'); }
    return $slugs;
}

$GLOBALS['extension_assertions'] = 0;
$GLOBALS['extension_plugins'] = [];
$GLOBALS['extension_themes'] = [];
$GLOBALS['extension_catalog'] = [];
load_core();
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new RuntimeException($message . ' at ' . $file . ':' . $line); });
$tests = [];

$tests['installed inventories paginate at boundaries without losing keyed slugs'] = static function (): void {
    foreach (['plugin', 'theme'] as $type) {
        $global = $type === 'plugin' ? 'extension_plugins' : 'extension_themes';
        $renderer = __NAMESPACE__ . '\\render_admin_' . ($type === 'plugin' ? 'plugins' : 'themes') . '_page';
        foreach ([0, 1, 12, 13, 24, 25] as $count) {
            $GLOBALS[$global] = inventory($count, $type);
            $xpath = capture($renderer);
            same(array_slice(array_keys($GLOBALS[$global]), 0, 12), row_slugs($xpath, $type), $type . ' first page must show the first twelve keyed entries.');
            same($count > 12 ? 1 : 0, $xpath->query('//nav[contains(@class,"admin-pagination")]')->length, 'Pagination is only useful for multiple pages.');
            same(array_keys($GLOBALS[$global]), $GLOBALS['extension_update_inventory'], 'Update checks must inspect the full inventory.');
        }
        $GLOBALS[$global] = inventory(25, $type);
        foreach ([['2', 2], ['3', 3], ['999999999999999999999999999999', 3], ['0', 1], ['-1', 1], ['2.5', 1], ['2x', 1], [['2'], 1]] as [$request, $page]) {
            $xpath = capture($renderer, ['p' => $request, 'check_updates' => '1']);
            same(array_slice(array_keys($GLOBALS[$global]), ($page - 1) * 12, 12), row_slugs($xpath, $type), $type . ' must normalize or clamp the requested page.');
            same(true, $GLOBALS['extension_update_refresh'], 'Check-updates must still refresh the full inventory.');
            foreach ($xpath->query('//tbody//form[@method="post"]') as $form) {
                same(1, $xpath->query('.//input[@name="csrf"]', $form)->length, 'Paginated actions must retain CSRF protection.');
                $hidden = $xpath->query('.//input[@name="return_page"]', $form);
                same(1, $hidden->length, 'Every row action must retain its current page.');
                same((string)$page, $hidden[0]->getAttribute('value'), 'Action return pages must use the clamped current page.');
            }
            foreach ($xpath->query('//a[contains(@href,"check_updates")]') as $link) { same((string)$page, query_of($link)['p'] ?? '1', 'Refresh must preserve the current page.'); }
            $links = $xpath->query('//nav[contains(@class,"admin-pagination")]/a');
            same($page === 2 ? 2 : 1, $links->length, 'Pagination must expose only available previous/next pages.');
            foreach ($links as $link) {
                $target = (int)(query_of($link)['p'] ?? 1);
                same(true, $target >= 1 && $target <= 3 && abs($target - $page) === 1, 'Pagination links must point to adjacent valid pages.');
            }
        }
    }
};

$tests['active theme status is resolved before slicing and removed last pages clamp'] = static function (): void {
    $GLOBALS['extension_themes'] = inventory(25, 'theme');
    $GLOBALS['extension_active_theme'] = 'theme-24';
    $xpath = capture(__NAMESPACE__ . '\\render_admin_themes_page');
    same(0, $xpath->query('//tbody/tr[contains(@class,"is-active")]')->length, 'An active theme on a later page must not make default appear active.');
    $xpath = capture(__NAMESPACE__ . '\\render_admin_themes_page', ['p' => '3']);
    same('theme-24', $xpath->query('//tbody/tr[contains(@class,"is-active")]')[0]?->getAttribute('data-theme-slug'), 'The active theme must remain active on its own page.');
    unset($GLOBALS['extension_themes']['theme-24']);
    $xpath = capture(__NAMESPACE__ . '\\render_admin_themes_page', ['p' => '3']);
    same(array_slice(array_keys($GLOBALS['extension_themes']), 12, 12), row_slugs($xpath, 'theme'), 'Removing the only item on the last page must show the preceding page.');
    $GLOBALS['extension_active_theme'] = 'default';
};

$tests['store searches each metadata field with category filtering'] = static function (): void {
    $fixtures = [
        ['theme', 'aurora', '晨光 Aurora', 'A calm theme', 'Alice'],
        ['plugin', 'slug-needle', 'Night mode', '中文排版', 'BOB'],
        ['plugin', 'writer', 'Writer', 'Needle in description', 'Carol'],
        ['theme', 'canvas', 'Canvas', 'A plain theme', 'Needle Author'],
        ['plugin', 'cyrillic', 'ЁЛКА', 'Unicode', 'ДМИТРИЙ'],
        ['plugin', 'literal', 'Literal', 'An [indexed] list <img src=x onerror=alert(1)>', 'Editor'],
    ];
    foreach ($fixtures as [$type, $slug, $name, $description, $author]) {
        $GLOBALS['extension_catalog'][$type . ':' . $slug] = compact('type', 'slug', 'name', 'description', 'author') + ['version' => '1.0.0', 'requires' => ''];
    }
    $cases = [
        [['q' => ' aUrOrA '], ['aurora']],
        [['q' => 'needle'], ['slug-needle', 'writer', 'canvas']],
        [['q' => 'needle', 'type' => 'theme'], ['canvas']],
        [['q' => 'needle', 'type' => 'plugin'], ['slug-needle', 'writer']],
        [['q' => 'bob'], ['slug-needle']],
        [['q' => '中文'], ['slug-needle']],
        [['q' => '[indexed]'], ['literal']],
        [['q' => 'ёлка'], ['cyrillic']],
        [['q' => 'missing'], []],
        [['q' => ['needle'], 'type' => ['theme']], array_column($fixtures, 1)],
        [['q' => '  '], array_column($fixtures, 1)],
        [['q' => "needle\xff"], array_column($fixtures, 1)],
        [['q' => str_repeat('中', 300) . "\xff"], array_column($fixtures, 1)],
    ];
    foreach ($cases as [$query, $expected]) {
        $xpath = capture(__NAMESPACE__ . '\\render_admin_store_page', $query);
        same($expected, store_slugs($xpath), 'Store keyword searches must match metadata and intersect the category.');
        same(count($expected) . ' 个扩展', trim($xpath->query('//*[contains(@class,"extension-store__count")]')[0]->textContent), 'Visible extension count must reflect search results.');
    }
};

$tests['search controls preserve routing category and keyword with safe output'] = static function (): void {
    $needle = '<img src=x onerror=alert(1)>';
    foreach ([false, true] as $pretty) {
        $GLOBALS['extension_pretty'] = $pretty;
        $xpath = capture(__NAMESPACE__ . '\\render_admin_store_page', ['q' => $needle, 'type' => 'plugin', 'refresh' => '1']);
        same(['literal'], store_slugs($xpath), 'Markup-shaped keywords must be treated as literal search text.');
        same(0, $xpath->query('//img|//script')->length, 'Search text and matching metadata must never create executable markup.');
        $forms = $xpath->query('//form[.//input[@name="q"]]');
        same(1, $forms->length, 'Store must expose one keyword search form.');
        $form = $forms[0];
        same('get', strtolower($form->getAttribute('method')), 'Search must use a bookmarkable GET request.');
        $input = $xpath->query('.//input[@name="q"]', $form)[0];
        same($needle, $input->getAttribute('value'), 'The keyword input must safely retain the exact search text.');
        same(true, $input->getAttribute('aria-label') !== '' || $xpath->query('//label[@for="' . $input->getAttribute('id') . '"]')->length > 0, 'Search input needs an accessible label.');
        same('plugin', $xpath->query('.//input[@name="type"]', $form)[0]?->getAttribute('value'), 'New searches must retain the selected category.');
        same($pretty ? 0 : 1, $xpath->query('.//input[@name="a" and @value="admin_store"]', $form)->length, 'Plain URL forms must preserve the route when browsers replace their query string.');
        same(true, $GLOBALS['extension_catalog_refresh'], 'Manual refresh must continue reaching the catalog service.');
        foreach ($xpath->query('//nav[contains(@class,"extension-store__tabs")]/a|//a[contains(@href,"refresh=")]') as $link) {
            same($needle, query_of($link)['q'] ?? null, 'Category tabs and refresh must preserve the keyword.');
        }
        foreach ($xpath->query('//form[@data-extension-install]') as $install) {
            same($needle, $xpath->query('.//input[@name="return_q"]', $install)[0]?->getAttribute('value'), 'Installing from search must preserve the keyword.');
            same('plugin', $xpath->query('.//input[@name="return_type"]', $install)[0]?->getAttribute('value'), 'Installing from search must preserve the category.');
        }
        $clear = $xpath->query('.//a', $form);
        same(1, $clear->length, 'An active search needs a clear control.');
        $cleared = query_of($clear[0]);
        same('plugin', $cleared['type'] ?? null, 'Clearing search must retain the selected category.');
        same(false, isset($cleared['q']), 'Clearing search must remove its keyword.');
    }
    foreach ([
        [str_repeat('中', 300), str_repeat('中', 255)],
        [str_repeat('中', 254) . '😀tail', str_repeat('中', 254) . '😀'],
        ["needle\xff", ''],
        [str_repeat('中', 300) . "\xff", ''],
    ] as [$query, $expected]) {
        $xpath = capture(__NAMESPACE__ . '\\render_admin_store_page', ['q' => $query]);
        same($expected, $xpath->query('//input[@name="q"]')[0]->getAttribute('value'), 'Queries must retain up to 255 complete Unicode characters and reject invalid UTF-8.');
    }
};

try {
    foreach ($tests as $name => $test) { $test(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
    fwrite(STDOUT, 'PASS ' . count($tests) . ' extension scenarios, ' . $GLOBALS['extension_assertions'] . " assertions.\n");
} catch (Throwable $exception) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    fwrite(STDERR, $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    exit(1);
} finally {
    restore_error_handler();
}
