<?php

declare(strict_types=1);

namespace SBlogManifestTests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

// Run: php tests/manifests.php
// Extract actual core functions; all writes and extension packages stay in a temporary directory.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
if (!extension_loaded('zip')) {
    fwrite(STDERR, "The manifest tests require ZipArchive.\n");
    exit(1);
}

function same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function scandir(string $directory): array|false
{
    if ($directory === PLUGINS_DIR) {
        $GLOBALS['manifest_scans']++;
    }
    return \scandir($directory);
}

function file_get_contents(string $filename): string|false
{
    if (basename($filename) === 'plugin.json') {
        $GLOBALS['manifest_reads']++;
    }
    return \file_get_contents($filename);
}

function rename(string $from, string $to): bool
{
    if (($GLOBALS['fail_plugin_swap'] ?? false) && str_contains(basename($from), '-install-')) {
        return false;
    }
    return \rename($from, $to);
}

function rmdir(string $directory): bool
{
    if (($GLOBALS['fail_plugin_delete'] ?? '') === $directory) {
        return false;
    }
    return \rmdir($directory);
}

function setting(string $name, string $default = ''): string
{
    return $GLOBALS['cached_settings'][$name] ?? $default;
}

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
    foreach ($parameters as $name => $value) {
        $message = str_replace('{' . $name . '}', (string)$value, $message);
    }
    return $message;
}

function theme_manifest(string $slug): ?array
{
    throw new RuntimeException('Plugin tests must not read theme manifests.');
}

function extension_remote_download(string $url, string $target, int $maxBytes, int $timeout): void
{
    same('https://example.test/plugin.zip', $url, 'Only the local test package may be downloaded.');
    if (!copy($GLOBALS['manifest_test_package'], $target)) {
        throw new RuntimeException('Could not copy the local test package.');
    }
}

function load_core(): void
{
    $needed = array_fill_keys([
        'ensure_runtime_dirs', 'str_sub_u', 'str_lower_u', 'normalize_version',
        'plugin_inventory_cache', 'refresh_plugin_cache', 'plugin_manifest', 'available_plugins',
        'normalize_active_plugin_slugs', 'active_plugin_slugs', 'save_active_plugins', 'sblog_i18n_languages',
        'extension_is_compatible', 'extension_installed_manifest', 'remove_extension_tree',
        'delete_managed_directory', 'uninstall_extension', 'validate_extension_zip',
        'extension_source_directory', 'validate_extension_source', 'copy_extension_tree', 'install_store_extension',
    ], true);
    $tokens = token_get_all(\file_get_contents(dirname(__DIR__) . '/index.php'));
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $nameIndex = $i + 1;
        while (isset($tokens[$nameIndex]) && ((is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_WHITESPACE)
            || (is_array($tokens[$nameIndex]) ? $tokens[$nameIndex][1] : $tokens[$nameIndex]) === '&')) {
            $nameIndex++;
        }
        if (!isset($tokens[$nameIndex]) || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING
            || !isset($needed[$tokens[$nameIndex][1]])) {
            continue;
        }
        $name = $tokens[$nameIndex][1];
        $code = '';
        $depth = 0;
        $started = false;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $started = true;
            } elseif ($token === '}' && --$depth === 0 && $started) {
                break;
            }
        }
        eval('namespace ' . __NAMESPACE__ . '; use \\RuntimeException; use \\Throwable; use \\ZipArchive; '
            . 'use \\FilesystemIterator; use \\RecursiveDirectoryIterator; use \\RecursiveIteratorIterator; ' . $code);
        unset($needed[$name]);
    }
    same([], array_keys($needed), 'Missing actual core functions.');
}

function fixture(string $slug, array $metadata, ?string $root = null): void
{
    $directory = ($root ?? PLUGINS_DIR) . '/' . $slug;
    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    file_put_contents($directory . '/plugin.json', json_encode(array_replace([
        'name' => $slug, 'version' => '1.0.0',
    ], $metadata), JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/plugin.php', '<?php');
}

function package(string $slug, array $metadata): array
{
    $sourceRoot = $GLOBALS['manifest_test_directory'] . '/packages/' . bin2hex(random_bytes(4));
    fixture($slug, $metadata, $sourceRoot);
    $zipFile = $sourceRoot . '/package.zip';
    $zip = new ZipArchive();
    same(true, $zip->open($zipFile, ZipArchive::CREATE), 'Could not create test ZIP.');
    foreach (['plugin.json', 'plugin.php'] as $file) {
        $zip->addFile($sourceRoot . '/' . $slug . '/' . $file, $slug . '/' . $file);
    }
    $zip->close();
    $GLOBALS['manifest_test_package'] = $zipFile;
    return [
        'type' => 'plugin', 'slug' => $slug, 'version' => $metadata['version'] ?? '1.0.0',
        'requires' => '', 'archive_path' => $slug, 'download_url' => 'https://example.test/plugin.zip',
        'sha256' => hash_file('sha256', $zipFile),
    ];
}

function remove_fixture_tree(string $directory): void
{
    $root = realpath($GLOBALS['manifest_test_directory']);
    $target = realpath($directory);
    if ($root === false || $target === false || ($target !== $root
        && strncasecmp($target, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0)) {
        throw new RuntimeException('Refusing to delete outside the manifest test directory.');
    }
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            remove_fixture_tree($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    \rmdir($directory);
}

$tests = [];
$tests['startup, language options and plugin management share one scan'] = static function (): void {
    save_active_plugins(['english-language', 'russian-language']);
    same(['english-language', 'russian-language'], active_plugin_slugs(true), 'Enabled language packs must coexist.');
    plugin_manifest('english-language');
    plugin_manifest('russian-language');
    same(3, count(sblog_i18n_languages()), 'Installed languages must be selectable.');
    available_plugins();
    active_plugin_slugs();
    same(1, $GLOBALS['manifest_scans'], 'Repeated inventory use must scan once.');
    same(5, $GLOBALS['manifest_reads'], 'Each valid or invalid JSON manifest must be read once.');
    fwrite(STDOUT, "MEASURE repeated plugin inventory: 1 scan, 5 JSON reads for 5 installed fixtures.\n");
};
$tests['missing and malformed manifests stay cached until explicit refresh'] = static function (): void {
    same(null, plugin_manifest('missing'), 'Missing plugin must be rejected.');
    same(null, plugin_manifest('broken'), 'An empty name must be rejected.');
    fixture('missing', ['name' => 'New']);
    fixture('broken', ['name' => 'Fixed']);
    same(null, plugin_manifest('missing'), 'A missing result must be cached.');
    same(null, plugin_manifest('broken'), 'An invalid result must be cached.');
    same(1, $GLOBALS['manifest_reads'], 'Cached invalid manifests must not be reread.');
    same('Fixed', available_plugins(true)['broken']['name'], 'Explicit refresh must detect manifest changes.');
    same('New', plugin_manifest('missing')['name'], 'Explicit refresh must detect new directories.');
};
$tests['read and write normalization retain language and ordinary exclusivity rules'] = static function (): void {
    $slugs = ['russian-language', 'storage-a', ' english-language ', 'storage-b', 'english-language', 'missing', '../bad'];
    $GLOBALS['stored_settings']['active_plugins'] = json_encode($slugs);
    same([], active_plugin_slugs(), 'A normal read must use the configured settings cache.');
    $expected = ['russian-language', 'english-language', 'storage-b'];
    same($expected, active_plugin_slugs(true), 'A fresh settings read must normalize the stored configuration.');
    save_active_plugins($slugs);
    same($expected, active_plugin_slugs(), 'Writes must use the same normalization.');
    same(1, $GLOBALS['manifest_scans'], 'Fresh settings reads must reuse plugin inventory.');
    same(null, plugin_manifest('../bad'), 'Unsafe slugs must remain rejected.');
};
$tests['installation refreshes previously missing plugins and language choices'] = static function (): void {
    same(null, plugin_manifest('french-language'), 'The language must be absent before installation.');
    available_plugins();
    same(['backup' => ''], install_store_extension(package('french-language', ['locale' => 'fr', 'language_name' => 'Français'])), 'A new install must not create a backup.');
    same('french-language', sblog_i18n_languages()['fr']['plugin'], 'The newly installed language must appear in this request.');
    same(2, $GLOBALS['manifest_scans'], 'Installing a plugin must invalidate the cached inventory.');
};
$tests['upgrades refresh metadata and preserve their backup'] = static function (): void {
    same('1.0.0', available_plugins()['english-language']['version'], 'The old version must be cached first.');
    $result = install_store_extension(package('english-language', ['version' => '2.0.0', 'locale' => 'en-GB', 'language_name' => 'English (UK)']));
    same(true, is_dir(CACHE_DIR . '/' . $result['backup']), 'The previous plugin must remain backed up.');
    same('2.0.0', plugin_manifest('english-language')['version'], 'Direct manifest reads must see the upgrade.');
    same('english-language', sblog_i18n_languages()['en-GB']['plugin'], 'Language options must reflect upgraded metadata.');
    same(false, isset(sblog_i18n_languages()['en']), 'The old locale must disappear in this request.');
};
$tests['uninstalling an inactive plugin removes its cached language option'] = static function (): void {
    available_plugins();
    uninstall_extension('plugin', 'english-language');
    same(null, plugin_manifest('english-language'), 'The uninstalled manifest must not stay cached.');
    same(false, isset(sblog_i18n_languages()['en']), 'The uninstalled language must disappear.');
    same(2, $GLOBALS['manifest_scans'], 'Uninstallation must invalidate inventory.');
};
$tests['active plugins remain protected from uninstallation'] = static function (): void {
    save_active_plugins(['english-language']);
    try {
        uninstall_extension('plugin', 'english-language');
        throw new RuntimeException('Uninstalling an active plugin unexpectedly succeeded.');
    } catch (RuntimeException $exception) {
        same('请先停用插件，再卸载。', $exception->getMessage(), 'The active-plugin protection must remain.');
    }
    same(true, isset(available_plugins()['english-language']), 'A rejected uninstall must preserve the plugin.');
};
$tests['failed swaps restore the previous plugin and refresh inventory'] = static function (): void {
    available_plugins();
    $GLOBALS['fail_plugin_swap'] = true;
    try {
        install_store_extension(package('english-language', ['version' => '2.0.0', 'locale' => 'en-GB']));
        throw new RuntimeException('The simulated swap failure unexpectedly succeeded.');
    } catch (RuntimeException $exception) {
        same('无法启用新扩展文件。', $exception->getMessage(), 'The simulated failure must reach rollback.');
    } finally {
        $GLOBALS['fail_plugin_swap'] = false;
    }
    same('1.0.0', available_plugins()['english-language']['version'], 'Rollback must preserve the previous manifest.');
    same(2, $GLOBALS['manifest_scans'], 'Failed installs must refresh inventory after rollback.');
};
$tests['partial uninstall failures invalidate deleted manifest entries'] = static function (): void {
    available_plugins();
    $GLOBALS['fail_plugin_delete'] = realpath(PLUGINS_DIR . '/english-language');
    try {
        uninstall_extension('plugin', 'english-language');
        throw new RuntimeException('The simulated directory-delete failure unexpectedly succeeded.');
    } catch (RuntimeException $exception) {
        same(true, str_starts_with($exception->getMessage(), '无法删除目录 '), 'The simulated failure must happen after file deletion.');
    } finally {
        $GLOBALS['fail_plugin_delete'] = '';
    }
    same(null, plugin_manifest('english-language'), 'Deleted files must invalidate the old valid manifest.');
    same(false, isset(sblog_i18n_languages()['en']), 'A partially deleted language must disappear.');
};

$temporaryDirectory = sys_get_temp_dir() . '/sblog-manifest-tests-' . bin2hex(random_bytes(8));
mkdir($temporaryDirectory, 0700);
$GLOBALS['manifest_test_directory'] = $temporaryDirectory;
foreach (['DATA_DIR' => 'data', 'CACHE_DIR' => 'cache', 'UPLOAD_DIR' => 'uploads', 'THEMES_DIR' => 'themes', 'PLUGINS_DIR' => 'plugins'] as $name => $directory) {
    define($name, $temporaryDirectory . '/' . $directory);
}
define('APP_VERSION', '1.6.0');
define('EXTENSION_PACKAGE_MAX_BYTES', 67108864);
define('EXTENSION_PACKAGE_MAX_EXTRACTED_BYTES', 134217728);
define('EXTENSION_PACKAGE_MAX_FILES', 3000);
$failed = 0;
try {
    load_core();
    ensure_runtime_dirs();
    foreach ($tests as $name => $test) {
        foreach (new FilesystemIterator(PLUGINS_DIR, FilesystemIterator::SKIP_DOTS) as $entry) {
            remove_fixture_tree($entry->getPathname());
        }
        fixture('english-language', ['locale' => 'en', 'language_name' => 'English', 'exclusive_group' => 'language']);
        fixture('russian-language', ['locale' => 'ru', 'language_name' => 'Russian', 'exclusive_group' => 'language']);
        fixture('storage-a', ['exclusive_group' => 'storage']);
        fixture('storage-b', ['exclusive_group' => 'storage']);
        fixture('broken', ['name' => '']);
        refresh_plugin_cache();
        $GLOBALS['manifest_scans'] = $GLOBALS['manifest_reads'] = 0;
        $GLOBALS['cached_settings'] = $GLOBALS['stored_settings'] = ['active_plugins' => '[]'];
        try {
            $test();
            fwrite(STDOUT, 'PASS ' . $name . "\n");
        } catch (Throwable $exception) {
            $failed++;
            fwrite(STDERR, 'FAIL ' . $name . ': ' . $exception->getMessage() . "\n");
        }
    }
} catch (Throwable $exception) {
    $failed++;
    fwrite(STDERR, $exception->getMessage() . "\n");
} finally {
    remove_fixture_tree($temporaryDirectory);
}
fwrite(STDOUT, sprintf("\n%d tests, %d failures. The installed blog was not loaded or modified.\n", count($tests), $failed));
exit($failed === 0 ? 0 : 1);
