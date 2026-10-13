<?php

declare(strict_types=1);

namespace SBlogHookTests;

use InvalidArgumentException;
use FilesystemIterator;
use RuntimeException;
use Throwable;

// Run: php tests/hooks.php
// Actual core functions run with isolated registries, catalogs and one temporary theme bootstrap file.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}
function error_log(string $message): bool { $GLOBALS['hook_test_errors'][] = $message; return true; }
function active_theme_file(string $filename): string
{
    $GLOBALS['hook_test_theme_files'][] = $filename;
    return $GLOBALS['hook_test_theme_paths'][$filename] ?? '';
}
function load_core(): void
{
    $needed = array_fill_keys([
        'add_plugin_action', 'add_plugin_filter', 'plugin_callbacks', 'plugin_action', 'plugin_filter',
        'add_theme_action', 'add_theme_filter', 'theme_callbacks', 'theme_action', 'theme_filter', 'load_active_theme',
        'plugin_display_metadata', 'theme_display_metadata', 'h', 'sblog_default_translations',
        'sblog_i18n_register', 'sblog_i18n_set_locale', 'sblog_i18n_locale', 'sblog_i18n_message', 'sblog_i18n_format', 'sblog_t',
    ], true);
    $tokens = token_get_all((string)file_get_contents(dirname(__DIR__) . '/index.php'));
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
        eval('namespace ' . __NAMESPACE__ . '; use \\InvalidArgumentException; use \\RuntimeException; use \\Throwable; ' . $code);
        unset($needed[$name]);
    }
    same([], array_keys($needed), 'Every tested function must come from the actual core.');
}

$tests = [];
$tests['registration preserves each error message and its translation boundary'] = static function (): void {
    sblog_i18n_register('en', [
        '无效的主题钩子名称：{hook}' => 'Invalid theme action: {hook}',
        '无效的主题过滤器名称：{hook}' => 'Invalid theme filter: {hook}',
        '无效的插件钩子名称：BAD' => 'This plugin message must remain untranslated',
    ]);
    sblog_i18n_set_locale('en');
    foreach ([
        'add_plugin_action' => '无效的插件钩子名称：BAD', 'add_plugin_filter' => '无效的插件过滤器名称：BAD',
        'add_theme_action' => 'Invalid theme action: BAD', 'add_theme_filter' => 'Invalid theme filter: BAD',
    ] as $name => $expected) {
        $register = __NAMESPACE__ . '\\' . $name;
        try { $register('BAD', static function (): void {}); throw new RuntimeException('Invalid registration unexpectedly succeeded.'); }
        catch (InvalidArgumentException $exception) { same($expected, $exception->getMessage(), 'Error message must remain compatible.'); }
        $register('valid.event-name_2', static function (): void {}, -10);
    }
    foreach (['plugin', 'theme'] as $scope) {
        foreach (['actions', 'filters'] as $kind) {
            same([-10], array_keys($GLOBALS['sblog_' . $scope . '_' . $kind]['valid.event-name_2']), 'Negative priorities must remain supported.');
        }
    }
};
$tests['sorted callbacks preserve negative priorities and registration order'] = static function (): void {
    foreach (['plugin', 'theme'] as $scope) {
        $callbacks = __NAMESPACE__ . '\\' . $scope . '_callbacks';
        foreach (['action', 'filter'] as $kind) {
            $register = __NAMESPACE__ . '\\add_' . $scope . '_' . $kind;
            foreach ([[10, 'first'], [-5, 'negative'], [0, 'zero'], [10, 'second']] as [$priority, $label]) {
                $register('ordered', static fn(): string => $label, $priority);
            }
            $registry = 'sblog_' . $scope . '_' . $kind . 's';
            same(['negative', 'zero', 'first', 'second'], array_map(static fn(callable $callback): string => $callback(), $callbacks($registry, 'ordered')), 'Sorting must preserve equal-priority order.');
            same([10, -5, 0], array_keys($GLOBALS[$registry]['ordered']), 'Retrieval must not reorder the original registry.');
        }
    }
};
$tests['callbacks retain numeric sorting for direct string priority keys'] = static function (): void {
    $groups = [];
    foreach ([['10', 'ten'], [-2, 'negative'], ['01', 'string-one'], ['1.5', 'fraction'], [1, 'integer-one'], ['text', 'text-zero'], [0, 'zero']] as [$priority, $label]) {
        $groups[$priority] = [static fn(): string => $label];
    }
    $GLOBALS['custom_registry']['ordered'] = $groups;
    $expected = ['negative', 'text-zero', 'zero', 'string-one', 'integer-one', 'fraction', 'ten'];
    foreach (['plugin_callbacks', 'theme_callbacks'] as $name) {
        $callbacks = __NAMESPACE__ . '\\' . $name;
        same($expected, array_map(static fn(callable $callback): string => $callback(), $callbacks('custom_registry', 'ordered')), 'String priority keys must use existing SORT_NUMERIC semantics.');
    }
};
$tests['missing empty and nonarray callback groups return empty arrays'] = static function (): void {
    $GLOBALS['custom_registry']['empty'] = [];
    $GLOBALS['custom_registry']['invalid'] = 'invalid';
    foreach (['plugin_callbacks', 'theme_callbacks'] as $name) {
        $callbacks = __NAMESPACE__ . '\\' . $name;
        foreach (['missing', 'empty', 'invalid'] as $hook) { same([], $callbacks('custom_registry', $hook), 'Invalid or missing groups must have no callbacks.'); }
    }
};
$tests['actions added during dispatch begin on the next dispatch'] = static function (): void {
    foreach (['plugin', 'theme'] as $scope) {
        $register = __NAMESPACE__ . '\\add_' . $scope . '_action';
        $dispatch = __NAMESPACE__ . '\\' . $scope . '_action';
        $events = [];
        $added = false;
        $register('dynamic', static function (array $context) use (&$events, &$added, $register): void {
            $events[] = 'initial-' . $context['pass'];
            if (!$added) {
                $added = true;
                $register('dynamic', static function (array $context) use (&$events): void { $events[] = 'new-early-' . $context['pass']; }, -100);
                $register('dynamic', static function (array $context) use (&$events): void { $events[] = 'new-late-' . $context['pass']; }, 10);
            }
        }, 10);
        $register('dynamic', static function (array $context) use (&$events): void { $events[] = 'tail-' . $context['pass']; }, 10);
        $dispatch('dynamic', ['pass' => 1]);
        same(['initial-1', 'tail-1'], $events, 'New registrations must not enter the current snapshot.');
        $events = [];
        $dispatch('dynamic', ['pass' => 2]);
        same(['new-early-2', 'initial-2', 'tail-2', 'new-late-2'], $events, 'Next dispatch must see new priorities and registration order.');
    }
};
$tests['filters retain the previous value after exceptions and see newly registered filters next time'] = static function (): void {
    foreach (['plugin', 'theme'] as $scope) {
        $register = __NAMESPACE__ . '\\add_' . $scope . '_filter';
        $dispatch = __NAMESPACE__ . '\\' . $scope . '_filter';
        $added = false;
        $register('dynamic', static function (string $value): string { throw new RuntimeException('fixture filter failure'); }, 0);
        $register('dynamic', static function (string $value, array $context) use (&$added, $register): string {
            if (!$added) { $added = true; $register('dynamic', static fn(string $value): string => $value . 'N', -1); }
            return $value . $context['suffix'];
        }, 1);
        $register('dynamic', static fn(string $value): string => $value . 'B', 2);
        same('AB', $dispatch('dynamic', '', ['suffix' => 'A']), 'Failures must leave the previous value available to later filters.');
        same('NAB', $dispatch('dynamic', '', ['suffix' => 'A']), 'New filters must begin on the next dispatch.');
    }
    same([
        'Plugin filter dynamic failed: fixture filter failure', 'Plugin filter dynamic failed: fixture filter failure',
        'Theme filter dynamic failed: fixture filter failure', 'Theme filter dynamic failed: fixture filter failure',
    ], $GLOBALS['hook_test_errors'], 'Each failed callback must retain its scope-specific logging boundary.');
};
$tests['theme actions echo eligible returns while plugin actions retain direct callback output only'] = static function (): void {
    foreach (['plugin' => 'direct|', 'theme' => 'direct|return|2tail|'] as $scope => $expected) {
        $register = __NAMESPACE__ . '\\add_' . $scope . '_action';
        $dispatch = __NAMESPACE__ . '\\' . $scope . '_action';
        $register('output', static function (): string { echo 'direct|'; return 'return|'; });
        $register('output', static function (): void { throw new RuntimeException('fixture action failure'); });
        $register('output', static fn(): int => 2);
        $register('output', static fn(): bool => true);
        $register('output', static fn(): array => ['ignored']);
        $register('output', static fn(): string => 'tail|');
        ob_start();
        $dispatch('output');
        same($expected, (string)ob_get_clean(), 'Action output behavior must remain scope-specific.');
    }
    same(['Plugin action output failed: fixture action failure', 'Theme action output failed: fixture action failure'], $GLOBALS['hook_test_errors'], 'Action failures must continue to later callbacks and log the original scope.');
};
$tests['retrieved callbacks are snapshots and later registry mutations remain visible'] = static function (): void {
    foreach (['plugin', 'theme'] as $scope) {
        $register = __NAMESPACE__ . '\\add_' . $scope . '_action';
        $callbacks = __NAMESPACE__ . '\\' . $scope . '_callbacks';
        $registry = 'sblog_' . $scope . '_actions';
        $register('snapshot', static fn(): string => 'first');
        $snapshot = $callbacks($registry, 'snapshot');
        $register('snapshot', static fn(): string => 'second');
        same(1, count($snapshot), 'A returned callback snapshot must not change afterward.');
        same(2, count($callbacks($registry, 'snapshot')), 'Later reads must observe new registrations.');
    }
};
$tests['plugin metadata retains regional translations and raw fallback while custom themes stay raw'] = static function (): void {
    $manifest = ['name' => 'Original', 'description' => 'Original description'];
    sblog_i18n_register('en', ['plugin.fixture.name' => 'Translated', 'theme.fixture.name' => 'Ignored theme translation']);
    sblog_i18n_set_locale('en-US');
    same(['name' => 'Translated', 'description' => 'Original description'], plugin_display_metadata('fixture', $manifest), 'Plugin fields must translate independently and fall back to manifest values.');
    same($manifest, theme_display_metadata('fixture', $manifest), 'Custom theme metadata must retain its existing raw values.');
    same(['name' => '', 'description' => ''], plugin_display_metadata('missing', []), 'Missing metadata must retain empty-string fallback.');
};
$tests['metadata retains empty translations exact-key fallback and default-theme catalogs'] = static function (): void {
    sblog_i18n_register('en', [
        'plugin.fixture.name' => '', 'plugin.fixture.description' => 'plugin.fixture.description',
        'theme.default.name' => 'Default translated', 'theme.default.description' => 'Default description',
    ]);
    sblog_i18n_set_locale('en');
    same(['name' => '', 'description' => 'Raw description'], plugin_display_metadata('fixture', ['name' => 'Raw', 'description' => 'Raw description']), 'Empty translations must apply while unchanged keys retain raw fallback.');
    same(['name' => 'Default translated', 'description' => 'Default description'], theme_display_metadata('default', ['name' => 'Ignored']), 'The built-in theme must use its fixed translation keys.');
    sblog_i18n_set_locale('fr');
    same(['name' => 'Default 黑白文字', 'description' => '程序内置的极简黑白文字主题，专注清晰阅读与内容本身。'], theme_display_metadata('default', []), 'The built-in theme must retain its Chinese fallback catalog.');
};
$tests['theme bootstrap remains lazy runs once and preserves registrations made before its exception'] = static function (): void {
    $filename = $GLOBALS['hook_test_directory'] . '/functions.php';
    file_put_contents($filename, '<?php namespace ' . __NAMESPACE__ . '; add_theme_action("head", static fn(): string => "bootstrap|", 10); throw new \\RuntimeException("fixture bootstrap failure");');
    $GLOBALS['hook_test_theme_paths'] = ['functions.php' => $filename, 'style.css' => $GLOBALS['hook_test_directory'] . '/style.css'];
    add_theme_action('head', static fn(): string => 'early|', 10);
    same([], $GLOBALS['hook_test_theme_files'], 'Registering hooks must not load the active theme.');
    load_active_theme();
    same(['functions.php', 'style.css'], $GLOBALS['hook_test_theme_files'], 'Bootstrap must retain the file lookup order.');
    ob_start();
    theme_action('head', ['style_url' => '/theme.css?v=1&x=2']);
    same('<link rel="stylesheet" href="/theme.css?v=1&amp;x=2">' . "\n" . 'early|bootstrap|', (string)ob_get_clean(), 'Styles must run first and partially registered bootstrap hooks must survive as before.');
    load_active_theme();
    same(['functions.php', 'style.css'], $GLOBALS['hook_test_theme_files'], 'Subsequent bootstrap calls must remain no-ops.');
    same(['Theme bootstrap failed: fixture bootstrap failure'], $GLOBALS['hook_test_errors'], 'Bootstrap must keep its independent exception boundary.');
};

$temporaryDirectory = sys_get_temp_dir() . '/sblog-hook-tests-' . bin2hex(random_bytes(8));
mkdir($temporaryDirectory, 0700);
$GLOBALS['hook_test_directory'] = $temporaryDirectory;
$failed = 0;
try {
    load_core();
    foreach ($tests as $name => $test) {
        foreach (['plugin', 'theme'] as $scope) { foreach (['actions', 'filters'] as $kind) { $GLOBALS['sblog_' . $scope . '_' . $kind] = []; } }
        $GLOBALS['custom_registry'] = $GLOBALS['sblog_i18n_catalogs'] = $GLOBALS['hook_test_errors'] = $GLOBALS['hook_test_theme_files'] = $GLOBALS['hook_test_theme_paths'] = [];
        sblog_i18n_set_locale('zh-CN');
        try { $test(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
        catch (Throwable $exception) { $failed++; fwrite(STDERR, 'FAIL ' . $name . ': ' . $exception->getMessage() . "\n"); }
    }
} catch (Throwable $exception) { $failed++; fwrite(STDERR, $exception->getMessage() . "\n"); }
finally {
    foreach (new FilesystemIterator($temporaryDirectory, FilesystemIterator::SKIP_DOTS) as $file) { unlink($file->getPathname()); }
    rmdir($temporaryDirectory);
}
fwrite(STDOUT, sprintf("\n%d hook tests, %d failures. The installed blog was not loaded or modified.\n", count($tests), $failed));
exit($failed === 0 ? 0 : 1);
