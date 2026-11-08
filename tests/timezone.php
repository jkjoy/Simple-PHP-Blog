<?php

declare(strict_types=1);

// Run: php tests/timezone.php. Exercise the actual bootstrap, save route, and date helpers without loading site data.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

final class TimezoneTestRedirect extends RuntimeException {}

function timezone_test_same(mixed $expected, mixed $actual, string $message): void
{
    $GLOBALS['timezone_test_assertions']++;
    if ($expected !== $actual) { throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)); }
}

function timezone_test_load(string $source, array $names): void
{
    $needed = array_fill_keys($names, true);
    $tokens = token_get_all($source);
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $j = $i + 1;
        while (is_array($tokens[$j] ?? null) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
        $name = is_array($tokens[$j] ?? null) ? $tokens[$j][1] : '';
        if (!isset($needed[$name])) { continue; }
        $code = '';
        $depth = 0;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; }
            elseif ($token === '}' && --$depth === 0) { break; }
        }
        eval($code);
        unset($needed[$name]);
    }
    timezone_test_same([], array_keys($needed), 'All requested core functions must exist.');
}

function setting(string $key, string $default = ''): string { return $GLOBALS['timezone_test_settings'][$key] ?? $default; }
function is_installed(): bool { return true; }
function sblog_t(string $message, array $parameters = []): string
{
    if ($message === 'public_date.full') { $message = '{year}/{month}/{day}'; }
    foreach ($parameters as $key => $value) { $message = str_replace('{' . $key . '}', (string)$value, $message); }
    return $message;
}
function url_for(string $action): string { return '/?a=' . $action; }
function url_with_query(string $url, array $parameters): string { return $url . '&' . http_build_query($parameters); }
function require_admin_post(string $returnUrl): void {}
function valid_site_url(string $url): bool { return true; }
function sblog_i18n_locale(): string { return 'zh-CN'; }
function sblog_i18n_save_language(string $locale): bool { $GLOBALS['timezone_test_writes'][] = 'language'; return true; }
function save_settings(array $values): void { $GLOBALS['timezone_test_writes'][] = $values; }
function set_flash(string $type, string $message): void { $GLOBALS['timezone_test_flash'] = $type; }
function redirect_to(string $url, int $status = 302): void { throw new TimezoneTestRedirect($url, $status); }

function timezone_test_request(array $input): array
{
    $_POST = $input;
    $GLOBALS['timezone_test_writes'] = [];
    $GLOBALS['timezone_test_flash'] = '';
    try { eval("switch ('save_settings') {\n" . $GLOBALS['timezone_test_save_route'] . "\n}"); }
    catch (TimezoneTestRedirect $redirect) { timezone_test_same(303, $redirect->getCode(), 'Settings changes must redirect with 303.'); }
    return $GLOBALS['timezone_test_writes'];
}

$originalTimezone = date_default_timezone_get();
$GLOBALS['timezone_test_assertions'] = 0;
$GLOBALS['timezone_test_settings'] = [];
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
try {
    $source = (string)file_get_contents(dirname(__DIR__) . '/index.php');
    timezone_test_load($source, ['default_settings', 'site_timezone', 'pretty_date', 'public_date', 'datetime_local_value', 'admin_settings_sections', 'admin_settings_section', 'admin_settings_values']);
    timezone_test_load((string)file_get_contents(dirname(__DIR__) . '/install.php'), ['i_default_settings']);
    timezone_test_load((string)file_get_contents(dirname(__DIR__) . '/update.php'), ['update_default_settings']);
    timezone_test_same(default_settings()['site_timezone'], i_default_settings()['site_timezone'], 'Installation must persist the same timezone default as the runtime.');
    timezone_test_same(default_settings()['site_timezone'], update_default_settings()['site_timezone'], 'Updates must keep the same timezone default as the runtime.');
    $bootstrapStart = strrpos($source, 'if (!is_installed()) {');
    $pluginsStart = strpos($source, 'load_active_plugins();', $bootstrapStart);
    $bootstrap = substr($source, $bootstrapStart, $pluginsStart - $bootstrapStart);
    $routeStart = strpos($source, "    case 'save_settings':");
    $routeEnd = strpos($source, "\n        break;", $routeStart);
    $GLOBALS['timezone_test_save_route'] = substr($source, $routeStart, $routeEnd - $routeStart) . "\n        break;";

    date_default_timezone_set('UTC');
    eval($bootstrap);
    timezone_test_same('Asia/Shanghai', date_default_timezone_get(), 'An existing site without a setting must override the host timezone with UTC+8 before loading plugins.');
    timezone_test_same('Asia/Shanghai', default_settings()['site_timezone'], 'New sites must default to Shanghai time.');

    $timestamp = (new DateTimeImmutable('2026-12-31T20:30:00+00:00'))->getTimestamp();
    foreach ([
        'Asia/Shanghai' => ['2027-01-01 04:30', '2027/1/1', '+08:00'],
        'UTC' => ['2026-12-31 20:30', '2026/12/31', '+00:00'],
        'America/New_York' => ['2026-12-31 15:30', '2026/12/31', '-05:00'],
        'Asia/Kathmandu' => ['2027-01-01 02:15', '2027/1/1', '+05:45'],
    ] as $timezone => [$display, $publicDate, $offset]) {
        $GLOBALS['timezone_test_settings'] = ['site_timezone' => $timezone];
        eval($bootstrap);
        timezone_test_same($timezone, date_default_timezone_get(), 'The saved timezone must be applied at bootstrap.');
        timezone_test_same($display, pretty_date($timestamp, true), 'Article and comment dates must follow the selected timezone across day and year boundaries.');
        timezone_test_same($publicDate, public_date($timestamp), 'Public article dates must use the same calendar day.');
        timezone_test_same(str_replace(' ', 'T', $display), datetime_local_value($timestamp), 'The editor must display the selected local time.');
        timezone_test_same($timestamp, strtotime(str_replace('T', ' ', datetime_local_value($timestamp))), 'Saving an unchanged editor date must preserve its timestamp.');
        timezone_test_same($offset, date('P', $timestamp), 'Machine-readable article and comment timestamps must carry the selected offset.');
    }

    foreach (['', 'Invalid/Timezone', '+08:00', '<script>'] as $invalid) {
        $GLOBALS['timezone_test_settings'] = ['site_timezone' => $invalid];
        eval($bootstrap);
        timezone_test_same('Asia/Shanghai', date_default_timezone_get(), 'Invalid stored settings must fall back safely to UTC+8.');
    }

    $form = ['settings_section' => 'general', 'site_name' => 'Timezone test', 'site_url' => ''];
    foreach (['Invalid/Timezone', '+08:00', '', [], ['UTC'], null, true, 8] as $invalid) {
        timezone_test_same([], timezone_test_request($form + ['site_timezone' => $invalid]), 'Invalid selections must be rejected before any settings or language write.');
        timezone_test_same('error', $GLOBALS['timezone_test_flash'], 'Invalid selections must display an error.');
    }
    $writes = timezone_test_request($form + ['site_timezone' => '  UTC  ']);
    timezone_test_same('UTC', $writes[1]['site_timezone'], 'A valid selection must be normalized and persisted.');
    timezone_test_same('success', $GLOBALS['timezone_test_flash'], 'A valid selection must succeed.');

    $GLOBALS['timezone_test_settings'] = ['site_timezone' => 'America/New_York'];
    $writes = timezone_test_request($form);
    timezone_test_same('America/New_York', $writes[1]['site_timezone'], 'A general settings form opened before this update must preserve the saved timezone.');
    $legacy = array_fill_keys(['site_name', 'site_tagline', 'site_url', 'favicon_url', 'posts_per_page', 'site_description', 'site_keywords', 'pretty_url', 'footer_beian', 'site_footer', 'custom_head_code'], '');
    $writes = timezone_test_request($legacy);
    timezone_test_same('America/New_York', $writes[1]['site_timezone'], 'The older combined settings form must preserve the saved timezone too.');

    fwrite(STDOUT, 'PASS ' . $GLOBALS['timezone_test_assertions'] . " timezone assertions: bootstrap, article/comment dates, editor round trips, fallback, and save validation.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    exit(1);
} finally {
    date_default_timezone_set($originalTimezone);
    restore_error_handler();
}
