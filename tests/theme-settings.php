<?php

declare(strict_types=1);

namespace SBlogThemeSettingsTests;

use RuntimeException;
use Throwable;

// Run: php tests/theme-settings.php. Actual core functions use disposable theme files and isolated settings.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) { throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)); }
}

class Response extends RuntimeException
{
    public function __construct(public string $location, public int $status) { parent::__construct($location); }
}

function setting(string $key, string $default = ''): string { return $GLOBALS['theme_settings_store'][$key] ?? $default; }
function save_settings(array $values): void { $GLOBALS['theme_settings_writes'][] = $values; $GLOBALS['theme_settings_store'] = array_replace($GLOBALS['theme_settings_store'], $values); }
function is_admin(): bool { return $GLOBALS['theme_settings_admin']; }
function sblog_t(string $message, array $parameters = []): string { return strtr($message, array_combine(array_map(static fn(string $key): string => '{' . $key . '}', array_keys($parameters)), array_values($parameters)) ?: []); }
function url_for(string $route): string { return '/blog/index.php?a=' . $route; }
function redirect_to(string $url, int $status = 302): void { throw new Response($url, $status); }
function set_flash(string $type, string $message): void { $GLOBALS['theme_settings_flash'] = [$type, $message]; }
function simple_error_page(string $title, string $message, int $status = 400): void { throw new Response($message, $status); }
function render_admin_sidebar(string $active): string { return '<aside>' . $active . '</aside>'; }
function render_admin_content_start(string $title, string $sidebar): string { return '<div class="admin-shell">' . $sidebar . '<div class="admin-main">'; }
function render_layout(string $title, string $content, array $options = []): void { $GLOBALS['theme_settings_render'] = compact('title', 'content', 'options'); }
function render_admin_content_end(string $title, string $active, string $description): void { render_layout($title, (string)ob_get_clean() . '</div></div>', ['active' => $active, 'description' => $description]); }
function extension_updates_for(string $type, array $themes, bool $refresh): array { return ['items' => [], 'error' => '']; }
function admin_icon(string $name): string { return ''; }
function sblog_tn(string $message, int $count): string { return sblog_t($message, ['count' => $count]); }
function theme_display_metadata(string $slug, array $theme): array { return $theme; }

function load_core(): void
{
    $needed = array_fill_keys([
        'str_len_u', 'str_sub_u', 'str_lower_u', 'h', 'valid_http_url', 'url_with_query',
        'theme_inventory_cache', 'refresh_theme_cache', 'theme_manifest', 'active_theme_slug', 'available_themes',
        'theme_settings_schema', 'theme_setting_value_error', 'theme_settings', 'theme_setting', 'theme_settings_values',
        'require_admin', 'csrf_token', 'csrf_field', 'csrf_is_valid', 'verify_csrf', 'render_admin_theme_settings_page', 'render_admin_themes_page',
    ], true);
    $tokens = token_get_all((string)file_get_contents(dirname(__DIR__) . '/index.php'));
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $j = $i + 1;
        while (isset($tokens[$j]) && ((is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) || (is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j]) === '&')) { $j++; }
        if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_STRING || !isset($needed[$tokens[$j][1]])) { continue; }
        $name = $tokens[$j][1];
        $code = '';
        $depth = 0;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; }
            elseif ($token === '}' && --$depth === 0) { break; }
        }
        eval('namespace ' . __NAMESPACE__ . '; use \\RuntimeException; use \\Throwable; ' . $code);
        unset($needed[$name]);
    }
    same([], array_keys($needed), 'Every tested function must come from the core.');
}

function fixture(string $slug, array $schema): void
{
    if (!is_dir(THEMES_DIR . '/' . $slug)) { mkdir(THEMES_DIR . '/' . $slug); }
    file_put_contents(THEMES_DIR . '/' . $slug . '/theme.json', json_encode(['name' => $slug]));
    file_put_contents(THEMES_DIR . '/' . $slug . '/settings.json', json_encode($schema));
    refresh_theme_cache();
}

function request(string $method = 'GET', array $input = []): void
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = ['theme' => 'alpha'];
    $_POST = $input;
    $_SESSION = ['csrf_token' => 'test-token'];
    $GLOBALS['theme_settings_admin'] = true;
    $GLOBALS['theme_settings_writes'] = [];
    $GLOBALS['theme_settings_render'] = null;
}

function remove_tree(string $directory): void
{
    foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
        if ($file->isDir() && !$file->isLink()) { remove_tree($file->getPathname()); }
        else { unlink($file->getPathname()); }
    }
    rmdir($directory);
}

$temporary = sys_get_temp_dir() . '/sblog-theme-settings-' . bin2hex(random_bytes(5));
mkdir($temporary);
define(__NAMESPACE__ . '\\THEMES_DIR', $temporary);
define(__NAMESPACE__ . '\\APP_VERSION', 'test');
load_core();
$schema = ['sections' => [['title' => '<Settings>', 'fields' => [
    ['key' => 'title', 'label' => '<Title>', 'default' => 'Default', 'required' => true, 'maxlength' => 30],
    ['key' => 'description', 'type' => 'textarea', 'description' => 'Text <only>', 'default' => 'Description'],
    ['key' => 'cover', 'type' => 'url', 'default' => '/blog/cover.jpg'],
    ['key' => 'accent', 'type' => 'color', 'default' => '#fe9600'],
    ['key' => 'enabled', 'type' => 'checkbox', 'default' => true],
    ['key' => 'layout', 'type' => 'select', 'default' => 'left', 'options' => ['left' => 'Left', 'right' => 'Right']],
    ['key' => 'since', 'type' => 'date', 'default' => '2024-02-29'],
]]]];
$tests = [];
$tests['defaults and theme accessors isolate each installed theme'] = static function () use ($schema): void {
    fixture('alpha', $schema);
    fixture('beta', $schema);
    $GLOBALS['theme_settings_store'] = ['active_theme' => 'alpha', 'theme_settings_beta' => '{"title":"Beta","enabled":"0","unknown":"ignored"}'];
    same('Default', theme_setting('title'), 'Active theme must use defaults.');
    same('Beta', theme_setting('title', '', 'beta'), 'Explicit theme must read its own settings.');
    same('1', theme_setting('enabled'), 'Boolean schema defaults normalize to strings.');
    same('0', theme_setting('enabled', '', 'beta'), 'Saved unchecked values must not revert to defaults.');
    same('fallback', theme_setting('unknown', 'fallback', 'beta'), 'Unknown settings must not enter the public API.');
    same([], theme_settings('../alpha'), 'Unsafe theme slugs must be rejected.');
    $GLOBALS['theme_settings_store']['active_theme'] = 'beta';
    same('Beta', theme_setting('title'), 'Active-theme changes apply without stale value caches.');
    $GLOBALS['theme_settings_store']['theme_settings_beta'] = '{bad JSON';
    same('Default', theme_setting('title', '', 'beta'), 'Corrupt stored data falls back to schema defaults.');
    $updated = $schema;
    $updated['sections'][0]['fields'][0]['default'] = 'Refreshed default';
    fixture('beta', $updated);
    same('Refreshed default', theme_setting('title', '', 'beta'), 'Inventory refresh must invalidate cached values together with the schema.');
};
$tests['typed values reject invalid data and preserve entered text'] = static function (): void {
    $valid = ['title' => '  New title  ', 'description' => "First\nSecond", 'cover' => '/blog/a.jpg?x=1', 'accent' => '#ABC123', 'layout' => 'right', 'since' => '2020-02-29'];
    $result = theme_settings_values('alpha', $valid + ['unknown' => 'ignored']);
    same([], $result['errors'], 'Valid values must be accepted.');
    same('New title', $result['values']['title'], 'Ordinary fields should trim edge whitespace.');
    same('0', $result['values']['enabled'], 'Missing checkbox means unchecked.');
    same(false, isset($result['values']['unknown']), 'Only declared fields may be saved.');
    foreach (['javascript:alert(1)', '//example.test/image', '/\\example.test/image', '/foo bar', "/a\nb", 'ftp://example.test/image'] as $url) {
        $invalid = theme_settings_values('alpha', array_replace($valid, ['cover' => $url]));
        same(true, isset($invalid['errors']['cover']), 'Unsafe URL must fail: ' . $url);
    }
    same([], theme_settings_values('alpha', array_replace($valid, ['cover' => 'https://example.test/a.jpg']))['errors'], 'HTTPS image URLs must be accepted.');
    $invalid = theme_settings_values('alpha', ['title' => '', 'description' => '<script>entered text</script>', 'cover' => 'javascript:alert(1)', 'accent' => 'red', 'enabled' => ['1'], 'layout' => 'other', 'since' => '2023-02-29']);
    same(['title', 'cover', 'accent', 'enabled', 'layout', 'since'], array_keys($invalid['errors']), 'Every invalid field must be reported.');
    same('<script>entered text</script>', $invalid['values']['description'], 'Errors must preserve entered text.');
    same(true, isset(theme_settings_values('alpha', array_replace($valid, ['title' => str_repeat('a', 31)]))['errors']['title']), 'Field length limits must be enforced.');
    same(true, isset(theme_settings_values('alpha', array_replace($valid, ['description' => "bad\xff"]))['errors']['description']), 'Invalid UTF-8 must fail validation before JSON storage.');
};
$tests['invalid schemas and external settings symlinks cannot create controls'] = static function () use ($schema, $temporary): void {
    fixture('invalid', ['sections' => [['fields' => [['key' => 'x'], ['key' => 'x']]]]]);
    same([], theme_settings_schema('invalid'), 'Duplicate keys invalidate the schema.');
    fixture('invalid', ['sections' => [['fields' => [['key' => 'x', 'type' => 'html']]]]]);
    same([], theme_settings_schema('invalid'), 'Unsupported field types invalidate the schema.');
    fixture('invalid', $schema);
    file_put_contents(THEMES_DIR . '/invalid/settings.json', '{');
    refresh_theme_cache();
    same([], theme_settings_schema('invalid'), 'Malformed JSON is ignored.');
    file_put_contents($temporary . '/external.json', json_encode($schema));
    unlink(THEMES_DIR . '/invalid/settings.json');
    symlink($temporary . '/external.json', THEMES_DIR . '/invalid/settings.json');
    refresh_theme_cache();
    same([], theme_settings_schema('invalid'), 'A settings schema must remain inside its theme directory.');
    $GLOBALS['theme_settings_store']['theme_settings_alpha'] = '{"cover":"javascript:alert(1)","layout":"other"}';
    same('/blog/cover.jpg', theme_setting('cover', '', 'alpha'), 'Stored invalid URLs fall back safely.');
    same('left', theme_setting('layout', '', 'alpha'), 'Stored invalid select values fall back safely.');
};
$tests['settings page requires login and a valid CSRF token before writes'] = static function (): void {
    request();
    $GLOBALS['theme_settings_admin'] = false;
    try { render_admin_theme_settings_page(); throw new RuntimeException('Guest was accepted.'); }
    catch (Response $response) { same('/blog/index.php?a=login', $response->location, 'Guest must be sent to login.'); }
    request('POST', ['csrf_token' => 'wrong']);
    try { render_admin_theme_settings_page(); throw new RuntimeException('Bad token was accepted.'); }
    catch (Response $response) { same(422, $response->status, 'Invalid CSRF must fail.'); }
    same([], $GLOBALS['theme_settings_writes'], 'Invalid CSRF must not save anything.');
    request();
    $_GET['theme'] = '../alpha';
    try { render_admin_theme_settings_page(); throw new RuntimeException('Unsafe theme was accepted.'); }
    catch (Response $response) { same(404, $response->status, 'Unsafe theme must return 404.'); }
};
$tests['invalid form retains input and leaves every theme unchanged'] = static function (): void {
    request('POST', ['csrf_token' => 'test-token', 'settings' => ['title' => '<My title>', 'description' => '<script>draft</script>', 'cover' => 'bad-url', 'accent' => 'red', 'layout' => 'right', 'since' => '2023-02-29']]);
    $before = $GLOBALS['theme_settings_store'];
    render_admin_theme_settings_page();
    $page = $GLOBALS['theme_settings_render'];
    same(422, $page['options']['status'], 'Invalid form must render a validation response.');
    same(true, str_contains($page['content'], 'value="&lt;My title&gt;"'), 'Valid entered title must survive validation.');
    same(true, str_contains($page['content'], '&lt;script&gt;draft&lt;/script&gt;'), 'Textarea input must be retained and escaped.');
    same(true, str_contains($page['content'], 'value="bad-url"'), 'Invalid URL must stay editable.');
    same(true, str_contains($page['content'], '&lt;Settings&gt;'), 'Schema headings must be escaped.');
    same(false, str_contains($page['content'], '<script>'), 'No entered HTML may execute.');
    same([], $GLOBALS['theme_settings_writes'], 'Partial valid values must never be saved on errors.');
    same($before, $GLOBALS['theme_settings_store'], 'Failed validation must leave all stored settings unchanged.');
};
$tests['inactive theme can save with PRG and without affecting active theme'] = static function (): void {
    $GLOBALS['theme_settings_store']['active_theme'] = 'beta';
    $beta = $GLOBALS['theme_settings_store']['theme_settings_beta'];
    request('POST', ['csrf_token' => 'test-token', 'settings' => ['title' => 'Alpha configured', 'description' => 'Description', 'cover' => '/cover.jpg', 'accent' => '#fe9600', 'layout' => 'right', 'since' => '2024-02-29']]);
    try { render_admin_theme_settings_page(); throw new RuntimeException('Successful save must redirect.'); }
    catch (Response $response) {
        same(303, $response->status, 'Successful save must use PRG.');
        same('/blog/index.php?a=admin_theme_settings&theme=alpha', $response->location, 'Redirect must retain the configured theme.');
    }
    same('Alpha configured', theme_setting('title', '', 'alpha'), 'Saved values must apply immediately.');
    same('0', theme_setting('enabled', '', 'alpha'), 'Unchecked checkbox must persist.');
    same($beta, $GLOBALS['theme_settings_store']['theme_settings_beta'], 'Other theme settings must remain unchanged.');
    same('beta', setting('active_theme'), 'Configuring a theme must not activate it.');
    same(['theme_settings_alpha'], array_keys($GLOBALS['theme_settings_writes'][0]), 'Only one namespaced setting may be written.');
};
$tests['theme manager exposes native settings inside More for active and inactive themes'] = static function (): void {
    request();
    render_admin_themes_page();
    $content = $GLOBALS['theme_settings_render']['content'];
    foreach (['alpha', 'beta'] as $slug) {
        preg_match('/<tr[^>]+data-theme-slug="' . $slug . '"[^>]*>(.*?)<\/tr>/s', $content, $row);
        same(true, isset($row[1]), 'Both configured and inactive themes must be listed.');
        same(1, preg_match('/<details[^>]+data-extension-menu>.*href="[^"]*a=admin_theme_settings[^\"]*theme=' . $slug . '".*<\/details>/s', $row[1]), 'Theme settings must remain in the More menu.');
    }
    same(2, substr_count($content, 'a=admin_theme_settings'), 'The built-in theme must not gain a settings link.');
};

$failed = 0;
try {
    foreach ($tests as $name => $test) {
        try { $test(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
        catch (Throwable $error) { $failed++; fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . "\n"); }
    }
} finally { remove_tree($temporary); }
exit($failed > 0 ? 1 : 0);
