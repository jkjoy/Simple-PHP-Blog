<?php

declare(strict_types=1);

// CLI-only regression checks use actual core functions and temporary upload directories.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function upload_access_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$needed = array_fill_keys(['ensure_runtime_dirs', 'ensure_upload_year_dir'], true);
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
upload_access_assert($needed === [], 'Upload checks must use the actual core functions.');

$root = sys_get_temp_dir() . '/sblog-upload-access-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
foreach (['DATA_DIR' => 'data', 'CACHE_DIR' => 'cache', 'UPLOAD_DIR' => 'uploads', 'THEMES_DIR' => 'themes', 'PLUGINS_DIR' => 'plugins'] as $constant => $subdirectory) {
    define($constant, $root . '/' . $subdirectory);
}

// This is the previous release's complete generated file, retained as a migration fixture.
$legacy = "Options -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .py .rb .asp .aspx .jsp\n<IfModule mod_headers.c>\n  Header set X-Content-Type-Options nosniff\n</IfModule>\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phar|cgi|pl|py|rb|asp|aspx|jsp)$\">\n  Require all denied\n</FilesMatch>\n";
$status = 0;
try {
    ensure_runtime_dirs();
    foreach ([DATA_DIR, CACHE_DIR, UPLOAD_DIR, THEMES_DIR, PLUGINS_DIR] as $directory) {
        upload_access_assert(is_dir($directory), 'Normal runtime directories must still be created.');
    }
    $accessFile = UPLOAD_DIR . '/.htaccess';
    $generated = (string)file_get_contents($accessFile);
    upload_access_assert(str_contains($generated, 'RewriteEngine On'), 'New upload directories need rewrite-based protection.');
    upload_access_assert(preg_match('/^\s*(?:Options|Require|RemoveHandler)\b/m', $generated) === 0, 'Generated protection must not require Options or AuthConfig overrides.');
    echo "PASS runtime initialization creates FileInfo-compatible upload protection\n";

    foreach ([$legacy, str_replace("\n", "\r\n", $legacy)] as $previous) {
        file_put_contents($accessFile, $previous);
        ensure_runtime_dirs();
        upload_access_assert(file_get_contents($accessFile) === $generated, 'Visiting the application must migrate the old generated file without another upload.');
    }
    echo "PASS existing LF and CRLF generated rules migrate during runtime initialization\n";

    [$year, $directory] = ensure_upload_year_dir();
    upload_access_assert($year === date('Y') && $directory === UPLOAD_DIR . '/' . $year && is_dir($directory), 'Uploads must retain the current year directory.');
    echo "PASS upload year directory and return values remain compatible\n";

    $knownTime = 946684800;
    touch($accessFile, $knownTime);
    clearstatcache(true, $accessFile);
    $before = filemtime($accessFile);
    ensure_runtime_dirs();
    ensure_upload_year_dir();
    clearstatcache(true, $accessFile);
    upload_access_assert(file_get_contents($accessFile) === $generated && filemtime($accessFile) === $before, 'Current generated rules must not be rewritten on every request or upload.');
    echo "PASS current generated rules are not repeatedly rewritten\n";

    foreach (["# Custom hosting policy\nRewriteEngine On\nRewriteRule ^private/ - [F,L]\n", $legacy . "# Hosting administrator addition\n"] as $custom) {
        file_put_contents($accessFile, $custom);
        touch($accessFile, $knownTime);
        clearstatcache(true, $accessFile);
        $before = filemtime($accessFile);
        ensure_runtime_dirs();
        ensure_upload_year_dir();
        clearstatcache(true, $accessFile);
        upload_access_assert(file_get_contents($accessFile) === $custom && filemtime($accessFile) === $before, 'Initialization and uploads must preserve custom hosting configuration, including additions to an old template.');
    }
    echo "PASS custom rules and administrator additions are preserved\n";

    // This checks matching only; Apache integration checks additionally exercise directive permissions.
    preg_match_all('/^RewriteRule\s+(\S+)\s+-\s+\[([^\]]+)\]$/m', $generated, $rules, PREG_SET_ORDER);
    upload_access_assert(count($rules) >= 2, 'Generated rules must protect hidden files and script paths.');
    $denied = static function (string $path) use ($rules): bool {
        foreach ($rules as $rule) {
            if (!in_array('F', explode(',', $rule[2]), true)) { continue; }
            $flags = in_array('NC', explode(',', $rule[2]), true) ? 'i' : '';
            if (preg_match('~' . $rule[1] . '~' . $flags, ltrim($path, '/')) === 1) { return true; }
        }
        return false;
    };
    $publicPaths = ['2026/photo.jpg', '2026/photo.JPEG', '2026/photo.png', '2026/photo.gif', '2026/photo.webp', '2026/manual.pdf', '2026/notes.txt', '2026/readme.md', '2026/archive.zip'];
    foreach ($publicPaths as $path) { upload_access_assert(!$denied($path), 'Ordinary uploaded media must stay accessible: ' . $path); }
    $privatePaths = ['2026/test.php', '2026/test.PHP', '2026/test.php8', '2026/test.PHP9', '2026/test.phtml', '2026/test.phar', '2026/test.cgi', '2026/test.pl', '2026/test.py', '2026/test.rb', '2026/test.asp', '2026/test.aspx', '2026/test.jsp', '2026/test.php.jpg', '2026/test.PHTML.png', '2026/test.php/path.jpg', '2026/test.cgi/extra', '.htaccess', '2026/.secret.jpg', '2026/.private/photo.png'];
    foreach ($privatePaths as $path) { upload_access_assert($denied($path), 'Script and hidden paths must be rejected: ' . $path); }
    echo "PASS ordinary media paths are allowed while script, double-extension, PathInfo and hidden paths are denied\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    $status = 1;
} finally {
    $resolved = realpath($root);
    upload_access_assert($resolved !== false && str_starts_with($resolved, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR), 'Cleanup must stay in the system temporary directory.');
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); }
        else { unlink($entry->getPathname()); }
    }
    rmdir($resolved);
}
exit($status);
