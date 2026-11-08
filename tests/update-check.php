<?php

declare(strict_types=1);

namespace SBlogUpdateCheckTests;

use RuntimeException;
use Throwable;

// Run: php tests/update-check.php. Network and authentication fixtures never load or alter the installed blog.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

final class Response extends RuntimeException
{
    public function __construct(public array $response) { parent::__construct('Captured response'); }
}

function same(mixed $expected, mixed $actual, string $message): void
{
    $GLOBALS['check_assertions']++;
    if ($expected !== $actual) { throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)); }
}

function load_core(string $source, array $names): void
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
        eval('namespace ' . __NAMESPACE__ . '; use \\RuntimeException; use \\Throwable; ' . $code);
        unset($needed[$name]);
    }
    same([], array_keys($needed), 'Every tested helper must come from the current core.');
}

function time(): int { return $GLOBALS['check_now']; }
function function_exists(string $name): bool { return $name === 'curl_init' ? !$GLOBALS['check_no_curl'] : \function_exists($name); }
function ensure_runtime_dirs(): void { $GLOBALS['check_runtime_calls']++; }
function database_schema_version(): int { return $GLOBALS['check_schema']; }
function database_upgrade_pending(): bool { return false; }
function curl_trust_options(): array { return []; }
function curl_init(string $url): object { $GLOBALS['check_http_calls']++; $GLOBALS['check_request_url'] = $url; return new \stdClass(); }
function curl_setopt_array(object $curl, array $options): bool { return true; }
function curl_exec(object $curl): string|false { return $GLOBALS['check_http_body']; }
function curl_getinfo(object $curl, int $option): int { return $GLOBALS['check_http_status']; }
function curl_error(object $curl): string { return $GLOBALS['check_http_error']; }
function curl_close(object $curl): void {}
function session_write_close(): bool { return true; }
function sblog_t(string $message, array $parameters = []): string
{
    foreach ($parameters as $key => $value) { $message = str_replace('{' . $key . '}', (string)$value, $message); }
    return $message;
}
function current_admin(): ?array { return $GLOBALS['check_logged_in'] ? ['id' => 1] : null; }
function url_for(string $action): string { return '/blog/index.php?a=' . $action; }
function redirect_to(string $url, int $status = 302): never { throw new Response(['kind' => 'redirect', 'url' => $url, 'status' => $status]); }
function json_response(array $payload, int $status = 200): never { throw new Response(['kind' => 'json', 'payload' => $payload, 'status' => $status]); }
function simple_error_page(string $title, string $message, int $status = 400): never { throw new Response(['kind' => 'error', 'status' => $status]); }
function install_github_update(array $update): string { $GLOBALS['check_installs']++; throw new RuntimeException('An update check must never install files.'); }

function release(): array
{
    return [
        'available' => true, 'current' => 'v0.0.1', 'latest' => 'v2.0.0',
        'download_url' => 'https://api.github.com/repos/jkjoy/Simple-PHP-Blog/zipball/v2.0.0',
        'database_schema' => 1, 'database_schema_known' => true,
        'database_schema_incompatible' => false, 'requires_database_upgrade' => false,
        'release_notes' => "## Changes\n- Fix timestamps", 'release_url' => 'https://github.com/jkjoy/Simple-PHP-Blog/releases/tag/v2.0.0', 'error' => '',
    ];
}

function write_cache(array|string $cache, int $age = 0): void
{
    file_put_contents(UPDATE_CACHE_FILE, is_array($cache) ? json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $cache);
    touch(UPDATE_CACHE_FILE, time() - $age);
    clearstatcache(true, UPDATE_CACHE_FILE);
}

function fixture(): void
{
    if (is_file(UPDATE_CACHE_FILE)) { unlink(UPDATE_CACHE_FILE); }
    clearstatcache(true, UPDATE_CACHE_FILE);
    $GLOBALS['check_now'] = \time();
    $GLOBALS['check_schema'] = 1;
    $GLOBALS['check_http_calls'] = $GLOBALS['check_runtime_calls'] = $GLOBALS['check_installs'] = 0;
    $GLOBALS['check_no_curl'] = false;
    $GLOBALS['check_http_status'] = 200;
    $GLOBALS['check_http_error'] = '';
    $GLOBALS['check_http_body'] = json_encode([
        'tag_name' => 'v3.0.0', 'zipball_url' => 'https://api.github.com/repos/jkjoy/Simple-PHP-Blog/zipball/v3.0.0',
        'html_url' => 'https://untrusted.example.test/release',
        'body' => "## Changes\n- Fix timezone\n<script>alert(1)</script>\n<!-- sblog-release-meta: {\"database_schema\": 2} -->",
    ]);
    $GLOBALS['check_logged_in'] = true;
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_ACCEPT' => 'application/json'];
    $_SESSION = ['csrf_token' => 'fixture-csrf'];
    $_POST = ['csrf_token' => 'fixture-csrf', 'automatic' => '1'];
}

function dispatch(): array
{
    try { dispatch_check(); } catch (Response $response) { return $response->response; }
    throw new RuntimeException('Update checks must terminate with a response.');
}

$GLOBALS['check_assertions'] = 0;
$directory = sys_get_temp_dir() . '/sblog-update-check-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
define(__NAMESPACE__ . '\\CACHE_DIR', $directory);
define(__NAMESPACE__ . '\\UPDATE_CACHE_FILE', $directory . '/github-update.json');
define(__NAMESPACE__ . '\\APP_VERSION', 'v1.0.0');
define(__NAMESPACE__ . '\\DATABASE_SCHEMA_VERSION', 1);
define(__NAMESPACE__ . '\\UPDATE_REPOSITORY', 'jkjoy/Simple-PHP-Blog');
$status = 0;
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new \ErrorException($message, 0, $severity, $file, $line); });
try {
    $source = (string)file_get_contents(dirname(__DIR__) . '/index.php');
    same(1, preg_match('/const UPDATE_CHECK_INTERVAL = ([0-9]+);/', $source, $interval), 'The update interval must be declared in the core.');
    define(__NAMESPACE__ . '\\UPDATE_CHECK_INTERVAL', (int)$interval[1]);
    same(86400, UPDATE_CHECK_INTERVAL, 'Automatic checks must wait a full 24 hours.');
    load_core($source, [
        'is_ascii_digits', 'normalize_version', 'update_available_for', 'release_database_schema',
        'update_info_with_database_requirement', 'cached_github_update_info', 'github_update_info',
        'set_flash', 'is_admin', 'require_admin', 'require_admin_post', 'csrf_is_valid', 'verify_csrf',
    ]);
    $start = strpos($source, "    case 'check_update':");
    $end = $start === false ? false : strpos($source, "\n        break;", $start);
    same(true, $start !== false && $end !== false, 'The actual update-check route must exist.');
    eval('namespace ' . __NAMESPACE__ . '; use \\Throwable; function dispatch_check(): void { switch ("check_update") {' . substr($source, $start, $end - $start) . "\n break; } }");

    fixture();
    same(null, cached_github_update_info(), 'A missing cache must remain pending without a remote request.');
    same(0, $GLOBALS['check_http_calls'], 'The dashboard cache reader must never contact GitHub.');
    foreach ([86399 => true, 86400 => false, 86401 => false, -60 => false] as $age => $fresh) {
        write_cache(release(), $age);
        same($fresh, cached_github_update_info() !== null, 'Cache freshness must respect the exact 24-hour boundary and reject future timestamps.');
    }

    fixture();
    $cache = release();
    $cache['latest'] = APP_VERSION;
    $cache['database_schema_incompatible'] = $cache['requires_database_upgrade'] = true;
    write_cache($cache);
    $cached = cached_github_update_info();
    same(APP_VERSION, $cached['current'], 'Upgrading the program must replace cached current-version information.');
    same(false, $cached['available'], 'A release already installed must no longer be advertised.');
    same(false, $cached['database_schema_incompatible'], 'Database compatibility must be recalculated.');
    same(false, $cached['requires_database_upgrade'], 'Completed migrations must remove cached upgrade requirements.');
    $cache['database_schema'] = 2;
    write_cache($cache);
    same(true, cached_github_update_info()['requires_database_upgrade'], 'A newer release schema must require migration.');
    $GLOBALS['check_schema'] = 3;
    same(true, cached_github_update_info()['database_schema_incompatible'], 'A now-newer installed schema must prevent downgrading.');
    same(false, cached_github_update_info()['requires_database_upgrade'], 'An incompatible lower schema must not request migration.');

    fixture();
    write_cache(release());
    $bytes = file_get_contents(UPDATE_CACHE_FILE);
    $mtime = filemtime(UPDATE_CACHE_FILE);
    for ($i = 0; $i < 3; $i++) { same('v2.0.0', github_update_info()['latest'], 'Repeated visits must reuse a fresh cached release.'); }
    same(0, $GLOBALS['check_http_calls'], 'Fresh checks must perform no network requests.');
    same($bytes, file_get_contents(UPDATE_CACHE_FILE), 'Cache reuse must preserve the previous response.');
    same($mtime, filemtime(UPDATE_CACHE_FILE), 'Cache reuse must not extend the 24-hour interval.');

    foreach (['release_notes', 'release_url', 'database_schema', 'database_schema_known'] as $missing) {
        fixture();
        $old = release(); unset($old[$missing]); write_cache($old);
        same(null, cached_github_update_info(), 'Caches from older application versions must refresh missing field ' . $missing . '.');
        same('v3.0.0', github_update_info()['latest'], 'An incomplete old cache must trigger a new request.');
        same(1, $GLOBALS['check_http_calls'], 'An incomplete cache must refresh exactly once.');
    }
    foreach (['{broken-json', 'null', '[]'] as $invalid) {
        fixture(); write_cache($invalid);
        same(null, cached_github_update_info(), 'Malformed JSON or structures must not count as a completed check.');
    }
    foreach (['release_notes', 'release_url'] as $key) {
        fixture(); $bad = release(); $bad[$key] = []; write_cache($bad);
        same(null, cached_github_update_info(), 'Malformed release details must refresh safely.');
    }

    fixture(); write_cache(release());
    $remote = github_update_info(true);
    same(1, $GLOBALS['check_http_calls'], 'Manual checks must bypass a fresh cache.');
    same('https://api.github.com/repos/jkjoy/Simple-PHP-Blog/releases/latest', $GLOBALS['check_request_url'], 'Checks must use the configured GitHub repository.');
    same('v3.0.0', $remote['latest'], 'Manual refresh must replace stale release information.');
    same(2, $remote['database_schema'], 'Hidden release metadata must still drive database upgrade checks.');
    same("## Changes\n- Fix timezone\n<script>alert(1)</script>", $remote['release_notes'], 'Displayed release notes must exclude the hidden metadata comment.');
    same('https://github.com/jkjoy/Simple-PHP-Blog/releases/tag/v3.0.0', $remote['release_url'], 'Release detail links must use the canonical repository URL.');
    same($remote['release_notes'], cached_github_update_info()['release_notes'], 'Release details must survive cache reuse.');

    foreach (['http-error', 'no-curl', 'invalid-release'] as $failure) {
        fixture();
        if ($failure === 'http-error') { $GLOBALS['check_http_status'] = 503; $GLOBALS['check_http_error'] = 'Fixture unavailable'; }
        elseif ($failure === 'no-curl') { $GLOBALS['check_no_curl'] = true; }
        else { $GLOBALS['check_http_body'] = '{}'; }
        $failed = github_update_info();
        same(true, $failed['error'] !== '', 'Failed checks must retain a useful error.');
        same(false, $failed['available'], 'Failed checks must not advertise an installable version.');
        same($failed['error'], cached_github_update_info()['error'], 'Failures must be cached for the same interval.');
        $calls = $GLOBALS['check_http_calls'];
        $GLOBALS['check_no_curl'] = false;
        same($failed['error'], github_update_info()['error'], 'Repeated visits must reuse cached failures.');
        same($calls, $GLOBALS['check_http_calls'], 'Failures must not retry on every admin page visit.');
    }

    fixture(); write_cache(release());
    $response = dispatch();
    same('json', $response['kind'], 'Automatic checks must return JSON without redirecting.');
    same(true, $response['payload']['ok'], 'Successful automatic checks must be acknowledged.');
    same(true, $response['payload']['available'], 'Automatic checks must report a new version.');
    same(url_for('admin'), $response['payload']['details_url'], 'The notice must reload the dashboard so newly discovered release details are rendered.');
    same(0, $GLOBALS['check_http_calls'], 'Automatic checks must preserve the cache interval.');
    same(null, $_SESSION['flash'] ?? null, 'Automatic checks must not leave duplicate flash notices.');
    same(0, $GLOBALS['check_installs'], 'Automatic checks must never install releases.');

    fixture(); $cache = release(); $cache['latest'] = APP_VERSION; write_cache($cache);
    same(false, dispatch()['payload']['available'], 'Up-to-date automatic checks must stay quiet.');
    fixture(); $GLOBALS['check_http_status'] = 503;
    same(false, dispatch()['payload']['ok'], 'Automatic network failures must return a structured failure.');
    same(0, $GLOBALS['check_installs'], 'Failed checks must never install releases.');

    fixture(); write_cache(release()); unset($_POST['automatic']);
    same('redirect', dispatch()['kind'], 'Manual checks must preserve existing form redirects.');
    same(1, $GLOBALS['check_http_calls'], 'Manual checks must force a request despite a fresh cache.');
    same('warning', $_SESSION['flash']['type'], 'Manual feedback must preserve database migration warnings.');
    same(0, $GLOBALS['check_installs'], 'Manual checks must also avoid installing files.');

    foreach (['anonymous', 'get', 'csrf', 'missing-csrf', 'missing-session-csrf'] as $failure) {
        fixture();
        if ($failure === 'anonymous') { $GLOBALS['check_logged_in'] = false; }
        elseif ($failure === 'get') { $_SERVER['REQUEST_METHOD'] = 'GET'; }
        elseif ($failure === 'csrf') { $_POST['csrf_token'] = 'wrong-csrf'; }
        elseif ($failure === 'missing-csrf') { unset($_POST['csrf_token']); }
        else { unset($_SESSION['csrf_token']); }
        $response = dispatch();
        same(in_array($failure, ['anonymous', 'get'], true) ? 'redirect' : 'error', $response['kind'], 'Automatic checks must enforce real authentication, POST, and CSRF guards.');
        same(0, $GLOBALS['check_runtime_calls'], 'Authorization must precede cache access.');
        same(0, $GLOBALS['check_http_calls'], 'Authorization must precede remote requests.');
        same(0, $GLOBALS['check_installs'], 'Rejected requests must never install files.');
    }

    fwrite(STDOUT, 'PASS ' . $GLOBALS['check_assertions'] . " update-check assertions: 24-hour cache, failure caching, release notes, manual refresh, and automatic request authorization.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    $status = 1;
} finally {
    restore_error_handler();
    $resolved = realpath($directory);
    if ($resolved === false || !str_starts_with($resolved, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR)) { throw new RuntimeException('Cleanup must remain inside the temporary directory.'); }
    foreach (new \DirectoryIterator($resolved) as $file) { if (!$file->isDot() && $file->isFile()) { unlink($file->getPathname()); } }
    rmdir($resolved);
}
exit($status);
