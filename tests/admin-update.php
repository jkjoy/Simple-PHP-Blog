<?php

declare(strict_types=1);

namespace SBlogAdminUpdateTests;

use RuntimeException;
use Throwable;

// Run: php tests/admin-update.php. No network access or application files are changed.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function same(mixed $expected, mixed $actual, string $message): void
{
    $GLOBALS['update_assertions']++;
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

class Response extends RuntimeException
{
    public function __construct(public array $response) { parent::__construct('Captured response'); }
}

function sblog_t(string $message, array $parameters = []): string
{
    foreach ($parameters as $key => $value) { $message = str_replace('{' . $key . '}', (string)$value, $message); }
    return $message;
}
function url_for(string $action): string { return '/blog/index.php?a=' . $action; }
function current_admin(): ?array { return $GLOBALS['update_logged_in'] ? ['id' => 1] : null; }
function redirect_to(string $url, int $status = 302): never { throw new Response(['kind' => 'redirect', 'url' => $url, 'status' => $status]); }
function json_response(array $payload, int $status = 200): never { throw new Response(['kind' => 'json', 'payload' => $payload, 'status' => $status]); }
function simple_error_page(string $title, string $message, int $status = 400): never { throw new Response(['kind' => 'error', 'status' => $status]); }
function github_update_info(bool $force = false): array
{
    same(true, $force, 'Installation must recheck release metadata.');
    $GLOBALS['update_metadata_calls']++;
    return $GLOBALS['update_release'];
}
function install_github_update(array $update): string
{
    $GLOBALS['update_download_calls']++;
    if ($GLOBALS['update_download_error'] !== '') { throw new RuntimeException($GLOBALS['update_download_error']); }
    $GLOBALS['update_writes']++;
    return (string)$update['latest'];
}

function load_core(): void
{
    $source = (string)file_get_contents(dirname(__DIR__) . '/index.php');
    $needed = array_fill_keys(['set_flash', 'pull_flash', 'is_admin', 'require_admin', 'require_admin_post', 'csrf_is_valid', 'verify_csrf', 'install_release_files'], true);
    $tokens = token_get_all($source);
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
        eval('namespace ' . __NAMESPACE__ . '; use \\RuntimeException; use \\Throwable; ' . $code);
        unset($needed[$name]);
    }
    same([], array_keys($needed), 'All tested authorization, flash and file installation functions must come from the core.');
    $start = strpos($source, "    case 'install_update':");
    $end = $start === false ? false : strpos($source, "    case 'check_update':", $start);
    same(true, $start !== false && $end !== false, 'The real update route must be available.');
    eval('namespace ' . __NAMESPACE__ . '; use \\RuntimeException; use \\Throwable; function dispatch_update(): void { switch ("install_update") {' . substr($source, $start, $end - $start) . '} }');
}

function fixture(bool $json = true): void
{
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_ACCEPT' => $json ? 'application/json' : 'text/html'];
    $_SESSION = ['csrf_token' => 'fixture-csrf'];
    $_POST = ['csrf_token' => 'fixture-csrf', 'expected_version' => 'v99.1.0', 'expected_database_schema_known' => '1', 'expected_database_schema' => '1'];
    $GLOBALS['update_logged_in'] = true;
    $GLOBALS['update_release'] = ['latest' => 'v99.1.0', 'available' => true, 'database_schema' => 1, 'database_schema_known' => true, 'database_schema_incompatible' => false, 'requires_database_upgrade' => false, 'error' => ''];
    $GLOBALS['update_metadata_calls'] = $GLOBALS['update_download_calls'] = $GLOBALS['update_writes'] = 0;
    $GLOBALS['update_download_error'] = '';
}

function dispatch(): array
{
    try { dispatch_update(); } catch (Response $response) { return $response->response; }
    throw new RuntimeException('The update route must terminate with a response.');
}

function temporary_directory(string $name): string
{
    $directory = $GLOBALS['update_temp'] . '/' . $name;
    mkdir($directory, 0700, true);
    return $directory;
}

function php_fixture(string $path, string $value): void
{
    file_put_contents($path, '<?php return ' . var_export($value, true) . ';');
    touch($path, time() - 10);
    clearstatcache(true, $path);
}

$GLOBALS['update_assertions'] = 0;
load_core();
$GLOBALS['update_temp'] = sys_get_temp_dir() . '/sblog-admin-update-' . bin2hex(random_bytes(8));
mkdir($GLOBALS['update_temp'], 0700);
$opcache = function_exists('opcache_get_status') && opcache_get_status(false) !== false;
$tests = [];

$tests['JSON installation retains its flash notice for the refreshed administration page'] = static function (): void {
    foreach (['known', 'unknown', 'upgrade'] as $schema) {
        fixture();
        if ($schema === 'unknown') {
            $_POST['expected_database_schema_known'] = '0';
            $_POST['expected_database_schema'] = '';
            $GLOBALS['update_release']['database_schema_known'] = false;
            $GLOBALS['update_release']['database_schema'] = null;
        } elseif ($schema === 'upgrade') {
            $_POST['expected_database_schema'] = '2';
            $GLOBALS['update_release']['database_schema'] = 2;
            $GLOBALS['update_release']['requires_database_upgrade'] = true;
        }
        $response = dispatch();
        same('json', $response['kind'], 'JSON requests must receive structured responses.');
        same(200, $response['status'], 'Successful updates must return HTTP 200.');
        same(true, $response['payload']['ok'], 'Successful updates must permit page refresh.');
        same(url_for('admin'), $response['payload']['redirect_url'], 'Refresh must use the administration URL, including its subdirectory.');
        same(1, $GLOBALS['update_writes'], 'Confirmed releases must be installed once.');
        $flash = pull_flash();
        same($schema === 'known' ? 'success' : 'warning', $flash['type'], 'Unknown and newer schemas must retain their warning severity.');
        same($schema === 'known' ? '' : 'database_upgrade', $flash['context'] ?? '', 'Database notices must preserve their upgrade action context.');
        same($response['payload']['message'], $flash['message'], 'The refreshed page must display the same success or warning message.');
        $expectedMessage = match ($schema) {
            'known' => '已更新到 v99.1.0。',
            'unknown' => '已更新到 v99.1.0，但发布信息未声明数据库版本，请检查是否需要执行数据库升级。',
            'upgrade' => '已更新到 v99.1.0，数据库升级尚未完成，请继续执行数据库升级。',
        };
        same($expectedMessage, $flash['message'], 'The notice must identify the installed version and its precise database requirement.');
        same(null, pull_flash(), 'The refreshed page must consume the notice only once.');
    }
};

$tests['stale metadata and installation failures return 422 without successful writes'] = static function (): void {
    foreach (['missing-version', 'version', 'schema', 'schema-known', 'metadata-error', 'incompatible', 'download'] as $failure) {
        fixture();
        if ($failure === 'missing-version') { unset($_POST['expected_version']); }
        elseif ($failure === 'version') { $_POST['expected_version'] = 'v99.0.0'; }
        elseif ($failure === 'schema') { $_POST['expected_database_schema'] = '2'; }
        elseif ($failure === 'schema-known') { $_POST['expected_database_schema_known'] = '0'; }
        elseif ($failure === 'metadata-error') { $GLOBALS['update_release']['error'] = 'Metadata unavailable'; }
        elseif ($failure === 'incompatible') { $GLOBALS['update_release']['database_schema_incompatible'] = true; }
        else { $GLOBALS['update_download_error'] = 'Download failed'; }
        $response = dispatch();
        same('json', $response['kind'], $failure . ': JSON failures must remain structured.');
        same(422, $response['status'], $failure . ': Failed installation must return 422.');
        same(false, $response['payload']['ok'], $failure . ': Failed installation must prevent refresh.');
        same(true, trim($response['payload']['error']) !== '', $failure . ': Failure details must be available to the custom notice.');
        same(false, isset($response['payload']['redirect_url']), $failure . ': Failure must not advertise a success destination.');
        same(0, $GLOBALS['update_writes'], $failure . ': Rejected or failed releases must never count as installed.');
        same($failure === 'download' ? 1 : 0, $GLOBALS['update_download_calls'], $failure . ': Metadata validation must precede download.');
        same(null, $_SESSION['flash'] ?? null, $failure . ': JSON errors are returned to the current page without a stale success flash.');
    }
};

$tests['ordinary POST requests retain success and error redirect behavior without JavaScript'] = static function (): void {
    foreach ([false, true] as $fail) {
        fixture(false);
        if ($fail) { $GLOBALS['update_download_error'] = 'Download failed'; }
        same(['kind' => 'redirect', 'url' => url_for('admin'), 'status' => 302], dispatch(), 'Ordinary form submissions must redirect to the refreshed administration page.');
        same($fail ? 'error' : 'success', $_SESSION['flash']['type'], 'Ordinary form submissions must retain the proper feedback message.');
        same($fail ? 0 : 1, $GLOBALS['update_writes'], 'Ordinary forms must install only successful downloads.');
    }
};

$tests['authentication, POST and CSRF checks run before metadata access or download'] = static function (): void {
    foreach (['anonymous', 'get', 'csrf', 'missing-csrf', 'missing-session-csrf'] as $failure) {
        fixture();
        if ($failure === 'anonymous') { $GLOBALS['update_logged_in'] = false; }
        elseif ($failure === 'get') { $_SERVER['REQUEST_METHOD'] = 'GET'; }
        elseif ($failure === 'csrf') { $_POST['csrf_token'] = 'wrong-csrf'; }
        elseif ($failure === 'missing-csrf') { unset($_POST['csrf_token']); }
        else { unset($_SESSION['csrf_token']); }
        $response = dispatch();
        same(in_array($failure, ['anonymous', 'get'], true) ? 'redirect' : 'error', $response['kind'], 'Invalid update access must be rejected by the actual authorization helpers.');
        same(in_array($failure, ['anonymous', 'get'], true) ? 302 : 422, $response['status'], 'Invalid access must preserve its existing response status.');
        if ($failure === 'anonymous') { same(url_for('login'), $response['url'], 'Anonymous requests must first authenticate.'); }
        if ($failure === 'get') { same(url_for('admin'), $response['url'], 'GET requests must return to the administration page.'); }
        same(0, $GLOBALS['update_metadata_calls'], 'Authorization must precede remote metadata access.');
        same(0, $GLOBALS['update_download_calls'], 'Authorization must precede download.');
        same(0, $GLOBALS['update_writes'], 'Invalid access must never install files.');
    }
};

$tests['real core file installation keeps backups and makes the updated PHP code available'] = static function () use ($opcache): void {
    $source = temporary_directory('success/source');
    $target = temporary_directory('success/target');
    $backup = temporary_directory('success/backup');
    php_fixture($target . '/index.php', 'old version');
    php_fixture($source . '/index.php', 'new version');
    mkdir($source . '/assets');
    file_put_contents($source . '/assets/admin.css', 'new CSS');
    file_put_contents($target . '/site-data.txt', 'preserve me');
    if ($opcache) {
        same(true, opcache_compile_file($target . '/index.php'), 'The original PHP fixture must compile into OPcache.');
        same(true, opcache_is_script_cached($target . '/index.php'), 'The fixture must actually be cached before testing invalidation.');
    }
    same('old version', include $target . '/index.php', 'The original PHP fixture must execute.');
    install_release_files($source, $target, $backup);
    same(file_get_contents($source . '/index.php'), file_get_contents($target . '/index.php'), 'The installer must copy the new core exactly.');
    same("<?php return 'old version';", file_get_contents($backup . '/index.php'), 'The previous core must remain available in the backup.');
    same('new CSS', file_get_contents($target . '/assets/admin.css'), 'The installer must create and copy missing asset directories.');
    same('preserve me', file_get_contents($target . '/site-data.txt'), 'The installer must preserve files outside the core list.');
    same('new version', include $target . '/index.php', 'An immediate refresh must execute the new core, including with OPcache active.');
};

$tests['failed real file installation restores overwritten PHP and removes newly created files'] = static function () use ($opcache): void {
    $source = temporary_directory('rollback/source');
    $target = temporary_directory('rollback/target');
    $backup = temporary_directory('rollback/backup');
    php_fixture($target . '/index.php', 'original core');
    php_fixture($source . '/index.php', 'rejected core');
    php_fixture($source . '/install.php', 'new installer');
    php_fixture($source . '/update.php', 'new migrator');
    mkdir($target . '/update.php');
    file_put_contents($target . '/update.php/keep.txt', 'preserve directory');
    if ($opcache) {
        same(true, opcache_compile_file($target . '/index.php'), 'The rollback fixture must compile into OPcache.');
        same(true, opcache_is_script_cached($target . '/index.php'), 'The rollback fixture must really be cached.');
    }
    same('original core', include $target . '/index.php', 'The initial rollback fixture must execute.');
    $failed = false;
    try { install_release_files($source, $target, $backup); } catch (RuntimeException $exception) { $failed = true; }
    same(true, $failed, 'A conflicting non-file target must abort the update.');
    same("<?php return 'original core';", file_get_contents($target . '/index.php'), 'Rollback must restore the overwritten core.');
    same(file_get_contents($target . '/index.php'), file_get_contents($backup . '/index.php'), 'Rollback must retain the original backup.');
    same(false, file_exists($target . '/install.php'), 'Rollback must remove newly created core files.');
    same('preserve directory', file_get_contents($target . '/update.php/keep.txt'), 'Rollback must preserve the conflicting existing directory.');
    same('original core', include $target . '/index.php', 'After rollback, refreshed requests must execute the restored PHP code.');
};

$status = 0;
try {
    foreach ($tests as $name => $test) { $test(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
    fwrite(STDOUT, ($opcache ? 'PASS cached PHP replacement and rollback with OPcache active' : 'SKIP OPcache assertions: enable the Zend OPcache extension and opcache.enable_cli=1 to exercise cached PHP') . "\n");
    fwrite(STDOUT, 'PASS ' . count($tests) . ' update scenarios, ' . $GLOBALS['update_assertions'] . " assertions.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    $status = 1;
} finally {
    $resolved = realpath($GLOBALS['update_temp']);
    if ($resolved === false || !str_starts_with($resolved, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR)) { throw new RuntimeException('Fixture cleanup must remain inside the temporary directory.'); }
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($resolved, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($resolved);
}
exit($status);
