<?php

declare(strict_types=1);

// Run: php tests/language.php
// Extract actual core functions without starting the application or opening its database.
// Every case runs in a fresh process, with an in-memory database and temporary plugin copies.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "The language tests require pdo_sqlite.\n");
    exit(1);
}

function language_test_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function language_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function language_test_fixture(string $slug, array $metadata, string $entry = '<?php'): void
{
    $directory = PLUGINS_DIR . '/' . $slug;
    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    file_put_contents($directory . '/plugin.json', json_encode(array_merge([
        'name' => $slug,
        'version' => '1.0.0',
        'author' => 'Language tests',
    ], $metadata), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    file_put_contents($directory . '/plugin.php', $entry);
}

function language_test_remove_directory(string $directory): void
{
    $root = realpath((string)($GLOBALS['language_test_directory'] ?? ''));
    $target = realpath($directory);
    if ($root === false || $target === false || ($target !== $root
        && strncasecmp($target, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0)) {
        throw new RuntimeException('Refusing to remove a directory outside the language test fixture.');
    }
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            language_test_remove_directory($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

function language_test_load_core(): void
{
    $needed = array_fill_keys([
        'ensure_runtime_dirs', 'str_sub_u', 'str_lower_u', 'q', 'all_rows', 'val',
        'default_settings', 'settings_cache', 'setting', 'save_settings',
        'plugin_inventory_cache', 'refresh_plugin_cache', 'plugin_manifest', 'available_plugins',
        'normalize_active_plugin_slugs', 'active_plugin_slugs', 'save_active_plugins',
        'add_plugin_action', 'add_plugin_filter', 'plugin_callbacks', 'plugin_action', 'plugin_filter',
        'sblog_default_translations', 'sblog_t', 'sblog_tn', 'load_active_plugins',
        'public_date',
    ], true);
    $tokens = token_get_all((string)file_get_contents(dirname(__DIR__) . '/index.php'));
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $nameIndex = $i + 1;
        while (isset($tokens[$nameIndex]) && ((is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_WHITESPACE)
            || (is_array($tokens[$nameIndex]) ? $tokens[$nameIndex][1] : $tokens[$nameIndex]) === '&')) {
            $nameIndex++;
        }
        if (!isset($tokens[$nameIndex]) || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) {
            continue;
        }
        $name = $tokens[$nameIndex][1];
        if (!isset($needed[$name]) && !str_starts_with($name, 'sblog_i18n_')) {
            continue;
        }
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
        eval($code);
        unset($needed[$name]);
    }
    foreach (array_keys($needed) as $missing) {
        throw new RuntimeException('Missing actual core function: ' . $missing);
    }
    foreach (['sblog_i18n_languages', 'sblog_i18n_apply_locale', 'sblog_i18n_save_language'] as $function) {
        language_test_assert(function_exists($function), 'Missing language API: ' . $function);
    }
}

function db(): PDO
{
    return $GLOBALS['language_test_db'];
}

$tests = [];

$tests['installed but disabled languages appear beside the Chinese default'] = static function (): void {
    language_test_same('zh-CN', default_settings()['site_language'], 'The default language must be Simplified Chinese.');
    language_test_same([], active_plugin_slugs(), 'Fresh settings must leave plugins disabled.');
    $languages = sblog_i18n_languages();
    language_test_same(['name' => '简体中文', 'plugin' => ''], $languages['zh-CN'], 'Chinese must be built in.');
    language_test_same(['name' => 'English', 'plugin' => 'english-language'], $languages['en'], 'An installed English provider must be selectable before activation.');
    language_test_same('russian-language', $languages['ru']['plugin'], 'An installed Russian provider must be selectable before activation.');
    language_test_assert($languages['ru']['name'] !== '', 'Russian must have a readable language name.');
    load_active_plugins();
    language_test_same('zh-CN', sblog_i18n_locale(), 'Installation alone must not apply a language.');
};

$tests['all language packs can remain enabled without changing the default'] = static function (): void {
    save_active_plugins(['english-language', 'russian-language']);
    language_test_same(['english-language', 'russian-language'], active_plugin_slugs(true), 'All enabled languages must survive persistence.');
    load_active_plugins();
    language_test_same(['english-language', 'russian-language'], $GLOBALS['sblog_loaded_plugins'], 'Both language packs must load successfully.');
    language_test_same('zh-CN', sblog_i18n_locale(), 'Enabled packs must not replace the selected Chinese default.');
    language_test_same('首页', sblog_t('首页'), 'Server messages must remain Chinese.');
    language_test_same([], sblog_i18n_client_messages(), 'Other languages must not leak client messages into Chinese.');
};

foreach ([
    'en' => ['english-language', 'Home', 'Open menu', '10/13/2026', '1 post', '2 posts'],
    'ru' => ['russian-language', 'Главная', 'Открыть меню', '13.10.2026', '1 запись', '2 записи'],
] as $locale => [$provider, $home, $menu, $date, $single, $plural]) {
    $tests['saving ' . $locale . ' activates its provider and applies after loading'] = static function () use ($locale, $provider, $home, $menu, $date, $single, $plural): void {
        $otherProvider = $provider === 'english-language' ? 'russian-language' : 'english-language';
        save_active_plugins([$otherProvider]);
        language_test_same(true, sblog_i18n_save_language($locale), 'An installed language must save successfully.');
        language_test_same($locale, setting('site_language'), 'The selected language must be persisted.');
        $cached = include SETTINGS_CACHE_FILE;
        language_test_same($locale, $cached['site_language'], 'The persisted cache must reflect the selected language.');
        language_test_same([$otherProvider, $provider], active_plugin_slugs(true), 'Selecting a language must enable its provider and retain other packs.');
        language_test_same('zh-CN', sblog_i18n_locale(), 'Saving must not apply an unloaded language immediately.');
        load_active_plugins();
        language_test_same($locale, sblog_i18n_locale(), 'The saved language must apply after providers load.');
        language_test_same($home, sblog_t('首页'), 'The selected server catalog must be used.');
        language_test_same($menu, sblog_i18n_client_messages()['open_menu'], 'The selected client catalog must be used.');
        language_test_same($date, public_date(strtotime('2026-10-13 12:00:00 UTC')), 'Date formatting must use the selected catalog.');
        language_test_same($single, sblog_tn('{count} 篇文章', 1), 'Singular translations must remain correct.');
        language_test_same($plural, sblog_tn('{count} 篇文章', 2), 'Plural translations must remain correct.');
        language_test_assert(str_ends_with(sblog_t('post_navigation.previous_label', ['title' => '首页']), '首页'), 'User supplied parameters must not be translated.');
        language_test_assert(str_contains(sblog_i18n_head(), $menu), 'The client script must contain the selected language.');
    };
}

$tests['returning to Chinese retains enabled language packs'] = static function (): void {
    save_active_plugins(['russian-language', 'english-language']);
    save_settings(['site_language' => 'en']);
    language_test_same(true, sblog_i18n_save_language('zh-CN'), 'The built-in language must be selectable.');
    language_test_same(['russian-language', 'english-language'], active_plugin_slugs(true), 'Returning to Chinese must retain every enabled language pack.');
    load_active_plugins();
    language_test_same('zh-CN', sblog_i18n_locale(), 'Chinese must apply regardless of plugin load order.');
};

$tests['invalid and uninstalled choices do not write settings or enable plugins'] = static function (): void {
    save_active_plugins(['english-language']);
    save_settings(['site_language' => 'en']);
    $before = all_rows('SELECT name, value FROM settings ORDER BY name');
    foreach (['fr', 'invalid_locale', '', 'en-US', '../../en'] as $locale) {
        language_test_same(false, sblog_i18n_save_language($locale), 'An unavailable choice must be rejected: ' . $locale);
        language_test_same($before, all_rows('SELECT name, value FROM settings ORDER BY name'), 'Rejected choices must not write settings.');
    }
};

$tests['a disabled selected provider falls back to Chinese'] = static function (): void {
    save_settings(['site_language' => 'en']);
    save_active_plugins(['russian-language']);
    load_active_plugins();
    language_test_same('zh-CN', sblog_i18n_locale(), 'A selected but disabled provider must fall back.');
    language_test_same('首页', sblog_t('首页'), 'Another enabled pack must not override the fallback.');
};

$tests['removing a selected provider falls back and removes its option'] = static function (): void {
    save_settings(['site_language' => 'en']);
    save_active_plugins(['english-language', 'russian-language']);
    language_test_remove_directory(PLUGINS_DIR . '/english-language');
    refresh_plugin_cache();
    language_test_assert(!isset(sblog_i18n_languages()['en']), 'A removed provider must disappear from the options.');
    load_active_plugins();
    language_test_same('zh-CN', sblog_i18n_locale(), 'A missing selected provider must fall back.');
};

$tests['failed language providers roll back their catalogs and fall back'] = static function (): void {
    language_test_fixture('french-language', ['locale' => 'fr', 'language_name' => 'Français'], <<<'PHP'
<?php
sblog_i18n_register('fr', ['首页' => 'Accueil']);
sblog_i18n_register_client('fr', ['open_menu' => 'Ouvrir le menu']);
sblog_i18n_set_locale('fr');
throw new RuntimeException('Intentional language test failure');
PHP);
    language_test_same(true, sblog_i18n_save_language('fr'), 'A valid installed provider must be selectable before it loads.');
    load_active_plugins();
    language_test_same('zh-CN', sblog_i18n_locale(), 'A failed selected provider must fall back.');
    language_test_assert(isset($GLOBALS['sblog_plugin_errors']['french-language']), 'The provider failure must be recorded.');
    language_test_assert(!isset($GLOBALS['sblog_i18n_catalogs']['fr']), 'Failed server catalogs must be rolled back.');
    language_test_assert(!isset($GLOBALS['sblog_i18n_client_catalogs']['fr']), 'Failed client catalogs must be rolled back.');
};

$tests['a loaded provider without a server catalog cannot apply its locale'] = static function (): void {
    language_test_fixture('french-language', ['locale' => 'fr', 'language_name' => 'Français']);
    language_test_same(true, sblog_i18n_save_language('fr'), 'Installed language metadata must be selectable.');
    load_active_plugins();
    language_test_same(['french-language'], $GLOBALS['sblog_loaded_plugins'], 'The empty provider fixture must load.');
    language_test_same('zh-CN', sblog_i18n_locale(), 'A provider without translations must fall back.');
};

$tests['regional languages retain their locale while using a base language catalog'] = static function (): void {
    language_test_fixture('regional-language', ['locale' => 'en-US', 'language_name' => 'English (US)'], <<<'PHP'
<?php
sblog_i18n_register('en', ['首页' => 'Home']);
sblog_i18n_register_client('en', ['open_menu' => 'Open menu']);
PHP);
    language_test_same(true, sblog_i18n_save_language('en-US'), 'An installed regional language must be selectable.');
    load_active_plugins();
    language_test_same('en-US', sblog_i18n_locale(), 'A regional locale must remain selected when a base catalog is available.');
    language_test_same('Home', sblog_t('首页'), 'Server messages must fall back to the base language catalog.');
    language_test_same('Open menu', sblog_i18n_client_messages()['open_menu'], 'Client messages must fall back to the base language catalog.');
};

$tests['legacy language exclusivity is ignored while ordinary exclusivity remains'] = static function (): void {
    foreach (['english-language', 'russian-language'] as $slug) {
        $manifestPath = PLUGINS_DIR . '/' . $slug . '/plugin.json';
        $manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest['exclusive_group'] = 'language';
        file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
    language_test_fixture('storage-a', ['exclusive_group' => 'storage']);
    language_test_fixture('storage-b', ['exclusive_group' => 'storage']);
    $configured = ['english-language', 'storage-a', 'russian-language', 'storage-b'];
    save_settings(['active_plugins' => json_encode($configured)]);
    $expected = ['english-language', 'russian-language', 'storage-b'];
    language_test_same($expected, active_plugin_slugs(true), 'Reads must retain all language packs and only the last ordinary exclusive provider.');
    save_active_plugins($configured);
    language_test_same($expected, json_decode(setting('active_plugins'), true), 'Writes must preserve the same exclusivity rules.');
};

foreach (['zh-CN', 'en', 'ru'] as $selectedLocale) {
    $tests['old bundled manifests and locale setters respect the ' . $selectedLocale . ' selection'] = static function () use ($selectedLocale): void {
        foreach (['english-language' => 'en', 'russian-language' => 'ru'] as $slug => $locale) {
            $manifestPath = PLUGINS_DIR . '/' . $slug . '/plugin.json';
            $manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            unset($manifest['locale'], $manifest['language_name']);
            $manifest['exclusive_group'] = 'language';
            file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            file_put_contents(PLUGINS_DIR . '/' . $slug . '/plugin.php', "\nsblog_i18n_set_locale('" . $locale . "');\n", FILE_APPEND);
        }
        language_test_fixture('unknown-language', ['exclusive_group' => 'language']);
        $languages = sblog_i18n_languages();
        language_test_same(['name' => 'English', 'plugin' => 'english-language'], $languages['en'], 'Old bundled English metadata must remain recognized.');
        language_test_same(['name' => 'Русский', 'plugin' => 'russian-language'], $languages['ru'], 'Old bundled Russian metadata must remain recognized.');
        language_test_same(3, count($languages), 'An unknown legacy exclusive group must not infer a language.');
        save_active_plugins(['english-language', 'russian-language']);
        language_test_same(true, sblog_i18n_save_language($selectedLocale), 'The selected language must remain available with legacy manifests.');
        load_active_plugins();
        language_test_same(['english-language', 'russian-language'], $GLOBALS['sblog_loaded_plugins'], 'Both legacy packs must load.');
        language_test_same($selectedLocale, sblog_i18n_locale(), 'Direct locale setters in legacy providers must not override the system selection.');
    };
}

$tests['invalid metadata and translation supplements do not become language options'] = static function (): void {
    language_test_fixture('invalid-language', ['locale' => 'bad_locale', 'language_name' => 'Invalid']);
    language_test_fixture('feature-plugin', [], "<?php\nsblog_i18n_register('de', ['Feature' => 'Funktion']);\n");
    language_test_same('', plugin_manifest('invalid-language')['locale'], 'Invalid locale metadata must be ignored.');
    save_active_plugins(['feature-plugin']);
    load_active_plugins();
    $languages = sblog_i18n_languages();
    language_test_assert(!isset($languages['bad_locale']), 'Invalid locale metadata must not create an option.');
    language_test_assert(!isset($languages['de']), 'A feature translation supplement must not create a complete language option.');
};

if (($argv[1] ?? '') !== '--case') {
    $failed = 0;
    foreach (array_keys($tests) as $index => $name) {
        $process = proc_open([PHP_BINARY, __FILE__, '--case', (string)$index], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start an isolated language test process.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status === 0) {
            fwrite(STDOUT, 'PASS ' . $name . "\n");
        } else {
            $failed++;
            fwrite(STDERR, 'FAIL ' . $name . ': ' . trim($stderr . $stdout) . "\n");
        }
    }
    fwrite(STDOUT, sprintf("\n%d tests, %d failures. The installed blog was not loaded or modified.\n", count($tests), $failed));
    exit($failed === 0 ? 0 : 1);
}

$test = array_values($tests)[(int)($argv[2] ?? -1)] ?? null;
if (!is_callable($test)) {
    fwrite(STDERR, "Unknown language test case.\n");
    exit(1);
}

$temporaryDirectory = sys_get_temp_dir() . '/sblog-language-tests-' . bin2hex(random_bytes(8));
mkdir($temporaryDirectory, 0700);
$GLOBALS['language_test_directory'] = $temporaryDirectory;
define('DATA_DIR', $temporaryDirectory . '/data');
define('CACHE_DIR', $temporaryDirectory . '/cache');
define('UPLOAD_DIR', $temporaryDirectory . '/uploads');
define('THEMES_DIR', $temporaryDirectory . '/themes');
define('PLUGINS_DIR', $temporaryDirectory . '/plugins');
define('SETTINGS_CACHE_FILE', CACHE_DIR . '/settings.php');
mkdir(PLUGINS_DIR, 0700);
date_default_timezone_set('UTC');
ini_set('error_log', $temporaryDirectory . '/errors.log');

try {
    $GLOBALS['language_test_db'] = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    db()->exec('CREATE TABLE settings(name TEXT PRIMARY KEY, value TEXT NOT NULL)');
    foreach (['english-language', 'russian-language'] as $slug) {
        mkdir(PLUGINS_DIR . '/' . $slug, 0700);
        foreach (['plugin.php', 'plugin.json'] as $file) {
            copy(dirname(__DIR__) . '/plugins/' . $slug . '/' . $file, PLUGINS_DIR . '/' . $slug . '/' . $file);
        }
    }
    language_test_load_core();
    $test();
    $exitCode = 0;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    $exitCode = 1;
} finally {
    $GLOBALS['language_test_db'] = null;
    language_test_remove_directory($temporaryDirectory);
}
exit($exitCode);
