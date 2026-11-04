<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function mail_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function setting(string $key, string $default = ''): string { return $GLOBALS['mail_settings'][$key] ?? $default; }
function default_settings(): array { return ['site_name' => 'Test blog']; }
function ensure_runtime_dirs(): void {}
function url_for(string $route): string { return '/blog/index.php?a=' . $route; }
function sblog_t(string $message, array $parameters = []): string
{
    foreach ($parameters as $key => $value) { $message = str_replace('{' . $key . '}', (string)$value, $message); }
    return $message;
}
function send_site_mail(string $recipient, string $subject, string $body): bool
{
    $GLOBALS['mail_sent'][] = compact('recipient', 'subject', 'body');
    return true;
}

$needed = array_fill_keys([
    'str_lower_u', 'valid_http_url', 'valid_site_url', 'configured_site_root_url',
    'site_root_url', 'absolute_url', 'app_base_path', 'request_is_https', 'url_with_query',
    'password_reset_notice_path', 'send_password_reset_notice',
], true);
$tokens = token_get_all((string)file_get_contents(dirname(__DIR__) . '/index.php'));
for ($i = 0, $count = count($tokens); $i < $count; $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
    $j = $i + 1;
    while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
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
    eval($code);
    unset($needed[$name]);
}
mail_assert($needed === [], 'Mail security checks must use the actual core functions.');

$directory = sys_get_temp_dir() . '/sblog-security-mail-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
define('CACHE_DIR', $directory);
$_SERVER = ['SCRIPT_NAME' => '/blog/index.php', 'SERVER_NAME' => 'request.example.test', 'HTTP_HOST' => 'request.example.test', 'SERVER_PORT' => '80'];
$GLOBALS['mail_sent'] = [];
$user = ['email' => 'admin@example.invalid'];
$token = str_repeat('a', 64);
$status = 0;
try {
    foreach (['', 'not-a-url', 'https://owner.example/blog?query=1', 'https://owner.example/blog#fragment'] as $configured) {
        $GLOBALS['mail_settings'] = ['site_url' => $configured];
        mail_assert(site_root_url(true) === '', 'Missing or invalid configuration must never trust a request hostname.');
        mail_assert(absolute_url('/blog/index.php?a=reset_password', true) === '', 'A trusted absolute URL requires valid configuration.');
        mail_assert(send_password_reset_notice($user, $token, time() + 3600) === false, 'Password recovery must decline delivery without a trusted site URL.');
        mail_assert($GLOBALS['mail_sent'] === [] && glob(CACHE_DIR . '/*') === [], 'Rejected notices must not send mail or write a reset link.');
    }
    echo "PASS missing and invalid site URLs cannot create outbound reset links\n";

    $GLOBALS['mail_settings'] = ['site_url' => 'https://owner.example/blog/'];
    $expected = 'https://owner.example/blog/index.php?a=reset_password&token=' . $token;
    mail_assert(absolute_url('/blog/index.php?a=reset_password&token=' . $token, true) === $expected, 'Configured subdirectory URLs must preserve the path exactly once.');
    mail_assert(send_password_reset_notice($user, $token, time() + 3600), 'Configured password recovery must continue sending notices.');
    $body = $GLOBALS['mail_sent'][0]['body'];
    mail_assert(str_contains($body, $expected) && !str_contains($body, 'request.example.test'), 'Reset email must contain only the configured domain.');
    mail_assert(str_contains((string)file_get_contents(password_reset_notice_path($token)), $expected), 'The local fallback notice must use the same trusted link.');
    echo "PASS configured recovery mail and fallback file use the trusted subdirectory URL\n";

    $GLOBALS['mail_settings'] = ['site_url' => ''];
    mail_assert(site_root_url() === 'http://request.example.test/blog', 'Ordinary public URL generation must remain available without site configuration.');
    echo "PASS public URL generation retains its existing fallback\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    $status = 1;
} finally {
    $resolved = realpath(CACHE_DIR);
    mail_assert($resolved !== false && str_starts_with($resolved, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR), 'Cleanup must remain in the temporary directory.');
    foreach (new DirectoryIterator($resolved) as $entry) {
        if ($entry->isDot()) { continue; }
        mail_assert($entry->isFile(), 'Only fixture files may be removed.');
        unlink($entry->getPathname());
    }
    rmdir($resolved);
}
exit($status);
