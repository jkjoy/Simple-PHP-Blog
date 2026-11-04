<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function rate_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function ensure_runtime_dirs(): void
{
    if (!is_dir(CACHE_DIR)) { mkdir(CACHE_DIR, 0700, true); }
}

class FailedRateWriteStream
{
    public $context;
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool { return true; }
    public function stream_lock(int $operation): bool { return true; }
    public function stream_eof(): bool { return true; }
    public function stream_read(int $count): string { return ''; }
    public function stream_stat(): array { return []; }
    public function stream_truncate(int $size): bool { return true; }
    public function stream_seek(int $offset, int $whence): bool { return true; }
    public function stream_tell(): int { return 0; }
    public function stream_write(string $data): int { return 0; }
    public function stream_flush(): bool { return true; }
}

function load_rate_functions(): void
{
    $needed = array_fill_keys(['login_rate_file', 'login_rate_state', 'password_reset_rate_file', 'password_reset_rate_state', 'authentication_rate_state'], true);
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
    rate_assert($needed === [], 'All rate functions must be extracted from the actual core.');
}

$worker = ($argv[1] ?? '') === '--worker';
$directory = $worker ? $argv[2] : sys_get_temp_dir() . '/sblog-security-rate-' . bin2hex(random_bytes(8));
define('CACHE_DIR', $directory);
ensure_runtime_dirs();
load_rate_functions();
$_SERVER['REMOTE_ADDR'] = '192.0.2.45';

if ($worker) {
    $deadline = microtime(true) + 10;
    while (!is_file(CACHE_DIR . '/start.flag')) {
        rate_assert(microtime(true) < $deadline, 'Parent must release the concurrency barrier.');
        usleep(10000);
    }
    $state = $argv[3] === 'login' ? login_rate_state(true) : password_reset_rate_state(true);
    echo json_encode($state);
    exit;
}

function concurrent_attempts(string $kind): int
{
    @unlink(CACHE_DIR . '/start.flag');
    $workers = [];
    for ($i = 0; $i < 16; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', CACHE_DIR, $kind], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        rate_assert(is_resource($process), 'Concurrent PHP worker must start.');
        $workers[] = [$process, $pipes];
    }
    file_put_contents(CACHE_DIR . '/start.flag', 'go');
    $allowed = 0;
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        rate_assert(proc_close($process) === 0, 'Worker failed: ' . $errors);
        $state = json_decode($output, true);
        rate_assert(is_array($state) && isset($state['allowed']), 'Worker must report actual reservation result.');
        $allowed += $state['allowed'] ? 1 : 0;
    }
    return $allowed;
}

$status = 0;
try {
    rate_assert(concurrent_attempts('login') === 5, 'Exactly five of sixteen simultaneous login attempts may reserve a slot.');
    rate_assert(login_rate_state()['count'] === 5, 'Rejected login attempts must not increase the reserved count.');
    rate_assert(login_rate_state(true)['allowed'] === false, 'Sequential requests after exhaustion must be denied.');
    echo "PASS sixteen concurrent login requests admit exactly five\n";

    rate_assert(concurrent_attempts('reset') === 3, 'Exactly three of sixteen simultaneous reset requests may reserve a slot.');
    rate_assert(password_reset_rate_state()['count'] === 3, 'Reset counter must stop at its own limit.');
    rate_assert(login_rate_state()['count'] === 5, 'Reset requests must not alter login reservations.');
    echo "PASS reset requests reserve atomically and use an independent limit\n";

    rate_assert(login_rate_state(false, true)['count'] === 0, 'Successful authentication must clear login reservations.');
    rate_assert(login_rate_state(true)['allowed'] === true, 'A cleared login window must admit a new attempt.');
    rate_assert(password_reset_rate_state()['count'] === 3, 'Successful login must not clear password-reset throttling.');
    echo "PASS successful authentication clears only login throttling\n";

    file_put_contents(login_rate_file(), json_encode(['count' => 5, 'since' => time() - 901]));
    $state = login_rate_state(true);
    rate_assert($state['allowed'] === true && $state['count'] === 1, 'An expired window must atomically restart with the reserved attempt.');
    echo "PASS expired windows reopen with one reserved attempt\n";

    $state = authentication_rate_state(CACHE_DIR . '/missing/counter.json', true, false, 5);
    rate_assert($state['allowed'] === false && $state['count'] === 5, 'Unavailable rate storage must deny authentication attempts.');
    $state = authentication_rate_state('php://memory', true, false, 5);
    rate_assert($state['allowed'] === false, 'A stream that cannot be locked must deny authentication attempts.');
    stream_wrapper_register('ratefail', FailedRateWriteStream::class);
    $state = authentication_rate_state('ratefail://counter', true, false, 5);
    rate_assert($state['allowed'] === false, 'A failed counter write must deny authentication attempts.');
    stream_wrapper_unregister('ratefail');
    echo "PASS unavailable, un-lockable and unwritable storage fail closed\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    $status = 1;
} finally {
    $resolved = realpath(CACHE_DIR);
    rate_assert($resolved !== false && str_starts_with($resolved, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR), 'Cleanup must remain in the system temporary directory.');
    foreach (new DirectoryIterator($resolved) as $entry) {
        if ($entry->isDot()) { continue; }
        rate_assert($entry->isFile(), 'The fixture must contain files only.');
        unlink($entry->getPathname());
    }
    rmdir($resolved);
}
exit($status);
