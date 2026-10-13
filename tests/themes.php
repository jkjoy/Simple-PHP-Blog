<?php

declare(strict_types=1);

namespace SBlogThemeTests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

// Run: php tests/themes.php; php tests/themes.php --measure
// Real core functions, instrumented file reads and local ZIP fixtures; no installed blog data is loaded.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!extension_loaded('zip')) { fwrite(STDERR, "Theme tests require ZipArchive.\n"); exit(1); }

function same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function scandir(string $directory): array|false
{
    if ($directory === THEMES_DIR) { $GLOBALS['theme_scans']++; }
    return \scandir($directory);
}

function file_get_contents(string $filename): string|false
{
    if (basename($filename) === 'theme.json') { $GLOBALS['theme_reads']++; }
    return \file_get_contents($filename);
}

function rename(string $from, string $to): bool
{
    if (($GLOBALS['fail_theme_swap'] ?? false) && str_contains(basename($from), '-install-')) { return false; }
    return \rename($from, $to);
}

function rmdir(string $directory): bool
{
    return ($GLOBALS['fail_theme_delete'] ?? '') === $directory ? false : \rmdir($directory);
}

function setting(string $name, string $default = ''): string { return $GLOBALS['cached_settings'][$name] ?? $default; }
function is_admin(): bool { return $GLOBALS['theme_test_admin'] ?? false; }
function asset_url(string $path): string { return '/blog/' . $path; }
function val(string $sql, array $parameters = []): mixed
{
    same('SELECT value FROM settings WHERE name = ?', $sql, 'Unexpected settings query.');
    return $GLOBALS['stored_settings'][$parameters[0]] ?? false;
}
function save_settings(array $values): void
{
    $GLOBALS['stored_settings'] = array_replace($GLOBALS['stored_settings'], $values);
    $GLOBALS['cached_settings'] = $GLOBALS['stored_settings'];
}
function sblog_t(string $message, array $parameters = []): string
{
    foreach ($parameters as $name => $value) { $message = str_replace('{' . $name . '}', (string)$value, $message); }
    return $message;
}
function plugin_manifest(string $slug): ?array { throw new RuntimeException('Theme tests must not inspect plugins.'); }
function active_plugin_slugs(bool $fresh = false): array { throw new RuntimeException('Theme tests must not load plugins.'); }
function refresh_plugin_cache(): void { throw new RuntimeException('Theme operations must not invalidate plugin inventory.'); }
function extension_remote_download(string $url, string $target, int $maxBytes, int $timeout): void
{
    same('https://example.test/theme.zip', $url, 'Only a local test package may be downloaded.');
    if (!copy($GLOBALS['theme_test_package'], $target)) { throw new RuntimeException('Cannot copy fixture ZIP.'); }
}

function load_core(bool $measure): void
{
    $needed = array_fill_keys([
        'ensure_runtime_dirs', 'str_sub_u', 'str_lower_u', 'normalize_version',
        'theme_manifest', 'available_themes', 'active_theme_slug', 'active_theme', 'active_theme_file', 'theme_asset_url',
        'theme_inventory_cache', 'refresh_theme_cache',
        'extension_is_compatible', 'extension_installed_manifest', 'remove_extension_tree', 'delete_managed_directory',
        'uninstall_extension', 'validate_extension_zip', 'extension_source_directory', 'validate_extension_source',
        'copy_extension_tree', 'install_store_extension',
    ], true);
    $tokens = token_get_all(\file_get_contents(dirname(__DIR__) . '/index.php'));
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $nameIndex = $i + 1;
        while (isset($tokens[$nameIndex]) && ((is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_WHITESPACE)
            || (is_array($tokens[$nameIndex]) ? $tokens[$nameIndex][1] : $tokens[$nameIndex]) === '&')) { $nameIndex++; }
        if (!is_array($tokens[$nameIndex] ?? null) || $tokens[$nameIndex][0] !== T_STRING
            || !isset($needed[$tokens[$nameIndex][1]])) { continue; }
        $name = $tokens[$nameIndex][1];
        $code = '';
        $depth = 0;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; }
            elseif ($token === '}' && --$depth === 0) { break; }
        }
        eval('namespace ' . __NAMESPACE__ . '; use \\RuntimeException; use \\Throwable; use \\ZipArchive; '
            . 'use \\FilesystemIterator; use \\RecursiveDirectoryIterator; use \\RecursiveIteratorIterator; ' . $code);
        unset($needed[$name]);
    }
    if ($measure) { unset($needed['theme_inventory_cache'], $needed['refresh_theme_cache']); }
    same([], array_keys($needed), 'Missing actual core functions.');
}

function fixture(string $slug, array $metadata = [], ?string $root = null): void
{
    $directory = ($root ?? THEMES_DIR) . '/' . $slug;
    if (!is_dir($directory)) { mkdir($directory, 0700, true); }
    file_put_contents($directory . '/theme.json', json_encode(array_replace(['name' => $slug, 'version' => '1.0.0'], $metadata), JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/functions.php', '<?php');
    file_put_contents($directory . '/layout.php', '<?php echo $content;');
    file_put_contents($directory . '/style.css', 'body{}');
}

function package(string $slug, array $metadata = []): array
{
    $root = $GLOBALS['theme_test_directory'] . '/packages/' . bin2hex(random_bytes(4));
    fixture($slug, $metadata, $root);
    $filename = $root . '/package.zip';
    $zip = new ZipArchive();
    same(true, $zip->open($filename, ZipArchive::CREATE), 'Cannot create fixture ZIP.');
    foreach (['theme.json', 'functions.php', 'layout.php', 'style.css'] as $file) { $zip->addFile($root . '/' . $slug . '/' . $file, $slug . '/' . $file); }
    $zip->close();
    $GLOBALS['theme_test_package'] = $filename;
    return [
        'type' => 'theme', 'slug' => $slug, 'version' => $metadata['version'] ?? '1.0.0', 'requires' => '', 'archive_path' => $slug,
        'download_url' => 'https://example.test/theme.zip', 'sha256' => hash_file('sha256', $filename),
    ];
}

function remove_fixture_tree(string $directory): void
{
    $root = realpath($GLOBALS['theme_test_directory']);
    $target = realpath($directory);
    if ($root === false || $target === false || ($target !== $root
        && strncasecmp($target, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0)) {
        throw new RuntimeException('Refusing deletion outside the theme fixture directory.');
    }
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { remove_fixture_tree($entry->getPathname()); }
        else { unlink($entry->getPathname()); }
    }
    \rmdir($directory);
}

function prepare_fixture(): void
{
    if (is_dir(THEMES_DIR)) { remove_fixture_tree(THEMES_DIR); }
    mkdir(THEMES_DIR, 0700);
    fixture('alpha', ['name' => 'Alpha', 'url' => 'https://example.test/author']);
    fixture('beta', ['name' => 'Beta']);
    fixture('broken', ['name' => '']);
    if (function_exists(__NAMESPACE__ . '\\refresh_theme_cache')) { refresh_theme_cache(); }
    $GLOBALS['theme_scans'] = $GLOBALS['theme_reads'] = 0;
    $GLOBALS['cached_settings'] = $GLOBALS['stored_settings'] = ['active_theme' => 'alpha'];
    $GLOBALS['theme_test_admin'] = false;
    $_GET = [];
}

function measure_reads(): void
{
    // Calls made by public theme startup, render_layout, three assets in a custom layout and inventory consumers.
    active_theme_file('functions.php');
    active_theme_file('style.css');
    active_theme();
    active_theme_file('style.css');
    theme_asset_url('style.css');
    active_theme_file('layout.php');
    foreach (['script.js', 'logo.svg', 'background.jpg'] as $asset) { theme_asset_url($asset); }
    available_themes();
    available_themes();
    fwrite(STDOUT, sprintf("MEASURE theme helpers and repeated inventory: %d scans, %d JSON reads.\n", $GLOBALS['theme_scans'], $GLOBALS['theme_reads']));
}

$tests = [];
$tests['public theme helpers and inventory share cached manifests'] = static function (): void {
    measure_reads();
    same(1, $GLOBALS['theme_scans'], 'Repeated inventories must scan once.');
    same(3, $GLOBALS['theme_reads'], 'Each of two valid and one invalid JSON manifests must be read once.');
    same(['default', 'alpha', 'beta'], array_keys(available_themes()), 'Built-in default and alphabetical ordering must remain.');
    same('https://example.test/author', active_theme()['url'], 'Normalized metadata must remain intact.');
};
$tests['built-in default remains available without a themes directory'] = static function (): void {
    remove_fixture_tree(THEMES_DIR);
    refresh_theme_cache();
    same(['default'], array_keys(available_themes()), 'Default must be available without custom themes.');
    same('default', active_theme_slug(), 'A missing configured theme must fall back.');
    same(APP_VERSION, active_theme()['version'], 'Default must preserve the running application version.');
    same('', active_theme_file('layout.php'), 'Default must retain the core layout.');
    same(0, $GLOBALS['theme_scans'], 'A missing themes directory must not be scanned.');
    same(0, $GLOBALS['theme_reads'], 'The built-in default must not read JSON.');
};
$tests['missing and malformed manifests refresh explicitly'] = static function (): void {
    same(null, theme_manifest('new-theme'), 'The new theme must be absent.');
    same(null, theme_manifest('broken'), 'An empty name must remain invalid.');
    fixture('new-theme');
    fixture('broken', ['name' => 'Repaired']);
    same(null, theme_manifest('new-theme'), 'Missing results must be cached.');
    same(null, theme_manifest('broken'), 'Malformed results must be cached.');
    same('Repaired', available_themes(true)['broken']['name'], 'Explicit refresh must detect repairs.');
    same('new-theme', theme_manifest('new-theme')['slug'], 'Explicit refresh must detect new directories.');
};
$tests['settings changes select the current theme without caching a slug'] = static function (): void {
    same('alpha', active_theme_slug(), 'Initial configuration must apply.');
    foreach (['beta' => 'beta', 'default' => 'default', 'missing' => 'default', '../alpha' => 'default', 'alpha' => 'alpha'] as $selected => $expected) {
        save_settings(['active_theme' => $selected]);
        same($expected, active_theme_slug(), 'Theme changes must be evaluated immediately: ' . $selected);
        same($expected, active_theme()['slug'], 'Metadata must follow the selected theme.');
    }
    same(2, $GLOBALS['theme_reads'], 'Selecting cached themes must not reread JSON.');
};
$tests['previews retain current admin authorization and validate the requested theme'] = static function (): void {
    $_GET['theme_preview'] = 'beta';
    same('alpha', active_theme_slug(), 'Visitors must not preview a theme.');
    $GLOBALS['theme_test_admin'] = true;
    same('beta', active_theme_slug(), 'Authorized previews must apply.');
    $_GET['theme_preview'] = '../alpha';
    same('alpha', active_theme_slug(), 'Unsafe preview paths must be ignored.');
    $_GET['theme_preview'] = 'missing';
    same('alpha', active_theme_slug(), 'Missing previews must use the configured theme.');
    $_GET['theme_preview'] = 'beta';
    $GLOBALS['theme_test_admin'] = false;
    same('alpha', active_theme_slug(), 'A previous admin preview must not remain cached after permission changes.');
};
$tests['asset and layout file helpers retain their existing paths and validation'] = static function (): void {
    same(THEMES_DIR . '/alpha/layout.php', active_theme_file('layout.php'), 'Layout path must remain unchanged.');
    same('', active_theme_file('theme.json'), 'Files outside the allowed PHP/CSS list must be rejected.');
    same('/blog/themes/alpha/assets/icon.svg', theme_asset_url('assets/icon.svg'), 'Asset URL must remain unchanged.');
    same('', theme_asset_url('../secret'), 'Asset traversal must be rejected.');
    unlink(THEMES_DIR . '/alpha/layout.php');
    clearstatcache(true);
    same('', active_theme_file('layout.php'), 'File existence must still be checked with cached metadata.');
    same(null, theme_manifest('../alpha'), 'Manifest traversal must remain rejected.');
};
$tests['installations invalidate missing themes and cached inventory'] = static function (): void {
    available_themes();
    same(null, theme_manifest('new-theme'), 'New theme must start absent.');
    same(['backup' => ''], install_store_extension(package('new-theme')), 'A new install must not create a backup.');
    same(true, isset(available_themes()['new-theme']), 'Installation must update inventory immediately.');
    save_settings(['active_theme' => 'new-theme']);
    same('new-theme', active_theme_slug(), 'A newly installed theme must be usable in this request.');
    same(2, $GLOBALS['theme_scans'], 'Installation must refresh inventory.');
};
$tests['upgrades refresh active theme metadata and preserve backups'] = static function (): void {
    same('1.0.0', active_theme()['version'], 'Previous metadata must be cached first.');
    $result = install_store_extension(package('alpha', ['name' => 'Upgraded', 'version' => '2.0.0']));
    same(true, is_dir(CACHE_DIR . '/' . $result['backup']), 'The previous theme must be backed up.');
    same('2.0.0', active_theme()['version'], 'An active theme must reflect upgraded metadata.');
    same('Upgraded', available_themes()['alpha']['name'], 'Inventory must reflect upgraded metadata.');
};
$tests['uninstall removes inactive themes and protects the active and default themes'] = static function (): void {
    available_themes();
    uninstall_extension('theme', 'beta');
    same(null, theme_manifest('beta'), 'Deleted metadata must not remain cached.');
    same(false, isset(available_themes()['beta']), 'Deleted theme must disappear from inventory.');
    foreach (['alpha' => '请先停用主题，再卸载。', 'default' => '不能卸载该扩展。'] as $slug => $message) {
        try { uninstall_extension('theme', $slug); throw new RuntimeException('Protected uninstall unexpectedly succeeded.'); }
        catch (RuntimeException $exception) { same($message, $exception->getMessage(), 'Theme uninstall protections must remain.'); }
    }
    same('alpha', active_theme_slug(), 'Rejected uninstallation must retain the selected theme.');
};
$tests['failed upgrades restore the old theme and invalidate inventory'] = static function (): void {
    available_themes();
    $GLOBALS['fail_theme_swap'] = true;
    try { install_store_extension(package('alpha', ['version' => '2.0.0'])); throw new RuntimeException('Simulated upgrade unexpectedly succeeded.'); }
    catch (RuntimeException $exception) { same('无法启用新扩展文件。', $exception->getMessage(), 'Failure must reach rollback.'); }
    finally { $GLOBALS['fail_theme_swap'] = false; }
    same('1.0.0', available_themes()['alpha']['version'], 'Rollback must restore previous metadata.');
    same(2, $GLOBALS['theme_scans'], 'Failed installs must refresh inventory after rollback.');
};
$tests['partial uninstall failures invalidate themes with deleted files'] = static function (): void {
    available_themes();
    $GLOBALS['fail_theme_delete'] = realpath(THEMES_DIR . '/beta');
    try { uninstall_extension('theme', 'beta'); throw new RuntimeException('Simulated uninstall unexpectedly succeeded.'); }
    catch (RuntimeException $exception) { same(true, str_starts_with($exception->getMessage(), '无法删除目录 '), 'Failure must happen after file deletion.'); }
    finally { $GLOBALS['fail_theme_delete'] = ''; }
    same(null, theme_manifest('beta'), 'A partially deleted theme must no longer be valid.');
    same(false, isset(available_themes()['beta']), 'Inventory must reject partially deleted themes.');
};

$temporaryDirectory = sys_get_temp_dir() . '/sblog-theme-tests-' . bin2hex(random_bytes(8));
mkdir($temporaryDirectory, 0700);
$GLOBALS['theme_test_directory'] = $temporaryDirectory;
foreach (['DATA_DIR' => 'data', 'CACHE_DIR' => 'cache', 'UPLOAD_DIR' => 'uploads', 'THEMES_DIR' => 'themes', 'PLUGINS_DIR' => 'plugins'] as $name => $directory) { define($name, $temporaryDirectory . '/' . $directory); }
define('APP_VERSION', '1.6.0');
define('EXTENSION_PACKAGE_MAX_BYTES', 67108864);
define('EXTENSION_PACKAGE_MAX_EXTRACTED_BYTES', 134217728);
define('EXTENSION_PACKAGE_MAX_FILES', 3000);
$measure = ($argv[1] ?? '') === '--measure';
$failed = 0;
try {
    load_core($measure);
    ensure_runtime_dirs();
    if ($measure) { prepare_fixture(); measure_reads(); }
    else {
        foreach ($tests as $name => $test) {
            prepare_fixture();
            try { $test(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
            catch (Throwable $exception) { $failed++; fwrite(STDERR, 'FAIL ' . $name . ': ' . $exception->getMessage() . "\n"); }
        }
        fwrite(STDOUT, sprintf("\n%d theme tests, %d failures. The installed blog was not loaded or modified.\n", count($tests), $failed));
    }
} catch (Throwable $exception) { $failed++; fwrite(STDERR, $exception->getMessage() . "\n"); }
finally { remove_fixture_tree($temporaryDirectory); }
exit($failed === 0 ? 0 : 1);
