<?php

declare(strict_types=1);

// Run: php tests/content.php [--compare-source /path/to/index-before.php]
// Test actual core parsing and duplicate checks without loading the installed blog.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function content_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) { throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true)); }
}

function content_load(string $file, array $names, string $prefix = ''): void
{
    $needed = array_fill_keys($names, true);
    $tokens = token_get_all((string)file_get_contents($file));
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $name = $tokens[$i + 2][1] ?? '';
        if (!isset($needed[$name])) { continue; }
        $code = '';
        $depth = 0;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token)
                ? ($prefix !== '' && $token[0] === T_STRING && in_array($token[1], $names, true) ? $prefix . $token[1] : $token[1])
                : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; }
            elseif ($token === '}' && --$depth === 0) { break; }
        }
        eval($code);
        unset($needed[$name]);
    }
    content_same([], array_keys($needed), 'Missing core functions.');
}

function sblog_t(string $message): string { return $message; }
function val(string $sql, array $params = []): mixed
{
    $GLOBALS['content_queries'][] = [$sql, $params];
    if (!empty($GLOBALS['content_profile'])) {
        // An always-true predicate measures rows visited while retaining the real query's conditions.
        $limit = strpos($sql, ' LIMIT 1');
        $sql = $limit === false ? $sql . ' AND duplicate_probe(id)' : substr_replace($sql, ' AND duplicate_probe(id)', $limit, 0);
    }
    $statement = $GLOBALS['content_database']->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

$names = [
    'str_len_u', 'str_sub_u', 'parse_reply_hidden_blocks', 'reply_hidden_syntax_errors',
    'strip_reply_hidden_blocks', 'content_has_reply_hidden_blocks', 'markdown_to_plain', 'derive_excerpt',
    'content_kind', 'content_requires_password', 'public_content_description', 'public_content_context', 'duplicate_comment_error',
];
content_load(dirname(__DIR__) . '/index.php', $names);
$baseline = ($argv[1] ?? '') === '--compare-source' ? ($argv[2] ?? '') : '';
if ($baseline !== '') { content_load($baseline, $names, 'baseline_'); }

function content_check(string $function, array $args, mixed $expected): void
{
    $actual = $function(...$args);
    content_same($expected, $actual, $function . ' changed its result.');
    if ($GLOBALS['baseline'] !== '') { content_same(('baseline_' . $function)(...$args), $actual, $function . ' differs from the original implementation.'); }
}

$visible = static fn(string $markdown): array => ['hidden' => false, 'markdown' => $markdown];
$hidden = static fn(string $markdown): array => ['hidden' => true, 'markdown' => $markdown];
$closingError = '回复可见结束标记缺少对应的开始标记。';
$missingError = '回复可见内容缺少结束标记。';
$fixtures = [
    'empty string retains its visible empty segment' => ['', [$visible('')], '', false, []],
    'blank lines retain parsing whitespace and trim only when stripping' => [" \t\r\n\n ", [$visible(" \t\n\n ")], '', false, []],
    'ordinary Unicode text normalizes CRLF and CR' => ["  第一行\r\n第二行\r第三行  ", [$visible("  第一行\n第二行\n第三行  ")], "第一行\n第二行\n第三行", false, []],
    'isolated closing markers remain visible and invalid' => ["[/reply]\n末尾", [$visible("[/reply]\n末尾")], "[/reply]\n末尾", false, [$closingError]],
    'inline markers remain ordinary text' => ['before [reply] text [/reply]', [$visible('before [reply] text [/reply]')], 'before [reply] text [/reply]', false, []],
    'marker recognition remains case sensitive' => ["[REPLY]\ntext\n[/REPLY]", [$visible("[REPLY]\ntext\n[/REPLY]")], "[REPLY]\ntext\n[/REPLY]", false, []],
    'fenced marker examples remain visible' => ["```php\n[reply]\nexample\n[/reply]\n```", [$visible("```php\n[reply]\nexample\n[/reply]\n```")], "```php\n[reply]\nexample\n[/reply]\n```", false, []],
    'hidden content is removed between visible segments' => ["before\r[reply]\rSECRET\r[/reply]\rafter", [$visible('before'), $hidden('SECRET'), $visible('after')], "before\n\nafter", true, []],
    'empty hidden blocks retain their original empty segment list' => ["[reply]\n[/reply]", [], '', false, []],
    'unclosed blocks protect the remaining text' => ["before\n[reply]\nSECRET", [$visible('before'), $hidden('SECRET')], 'before', true, [$missingError]],
    'nested invalid blocks retain legacy stripping and error order' => ["before\n[reply]\nSECRET\n[reply]\nMORE\n[/reply]\n[/reply]\nafter", [$visible('before'), $hidden("SECRET\nMORE"), $visible('after')], "before\n\nafter", true, ['回复可见标记不能嵌套。', $closingError]],
    'invalid UTF8 without markers retains its bytes' => ["bad\xff\rnext", [$visible("bad\xff\nnext")], "bad\xff\nnext", false, []],
];

$tests = [];
foreach ($fixtures as $name => [$markdown, $segments, $public, $hasHidden, $errors]) {
    $tests[$name] = static function () use ($markdown, $segments, $public, $hasHidden, $errors): void {
        content_check('parse_reply_hidden_blocks', [$markdown], $segments);
        content_check('strip_reply_hidden_blocks', [$markdown], $public);
        content_check('content_has_reply_hidden_blocks', [$markdown], $hasHidden);
        content_check('reply_hidden_syntax_errors', [$markdown], $errors);
    };
}
$tests['plain excerpts retain Markdown cleanup and Unicode truncation'] = static function (): void {
    content_check('markdown_to_plain', ["# Heading\n[link](https://example.test) **bold**\n![cover](cover.png)\n```php\ncode\n```"], 'Heading link bold');
    content_check('derive_excerpt', ['一二三四五六', 4], '一二三四…');
    content_check('derive_excerpt', ["[reply]\nSECRET\n[/reply]"], '');
};
$tests['public contexts keep hidden text and custom excerpts private'] = static function (): void {
    $post = ['id' => 1, 'kind' => 'post', 'content' => "VISIBLE\n[reply]\nSECRET\n[/reply]", 'excerpt' => 'SECRET EXCERPT', 'content_password_hash' => '', 'plugin_data' => ['original' => true]];
    $expected = $post;
    $expected['content'] = 'VISIBLE';
    $expected['excerpt'] = 'VISIBLE';
    unset($expected['content_password_hash']);
    content_check('public_content_context', [$post], $expected);
    content_check('public_content_description', [array_replace($post, ['content' => "[reply]\nSECRET\n[/reply]"])], '此内容回复后可见');
};
$tests['password contexts remain private and plain excerpts remain unchanged'] = static function (): void {
    $post = ['id' => 2, 'kind' => 'post', 'content' => 'PASSWORD SECRET', 'excerpt' => 'SECRET EXCERPT', 'content_password_hash' => 'hash'];
    content_check('public_content_context', [$post], ['id' => 2, 'kind' => 'post', 'content' => '', 'excerpt' => '此文章受密码保护。']);
    $post = ['kind' => 'post', 'content' => "  ordinary\r\nbody  ", 'excerpt' => ' Custom excerpt '];
    content_check('public_content_context', [$post], ['kind' => 'post', 'content' => "ordinary\nbody", 'excerpt' => 'Custom excerpt']);
};

function duplicate_fixture(): void
{
    $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database->exec('CREATE TABLE comments(id INTEGER PRIMARY KEY,post_id INTEGER,parent_id INTEGER,user_id INTEGER,author_email TEXT,content TEXT,created_at INTEGER,status TEXT)');
    $database->exec('CREATE INDEX idx_comments_post ON comments(post_id,created_at)');
    $database->sqliteCreateFunction('duplicate_probe', static function (int $id): int { $GLOBALS['duplicate_visits']++; return 1; }, 1);
    $GLOBALS['content_database'] = $database;
    $GLOBALS['content_queries'] = [];
    $GLOBALS['content_profile'] = false;
}
function duplicate_insert(int $postId = 1, ?int $parentId = null, ?int $userId = null, string $email = 'visitor@example.test', string $body = 'Same content', ?int $createdAt = null, string $status = 'pending'): void
{
    $insert = $GLOBALS['content_database']->prepare('INSERT INTO comments(post_id,parent_id,user_id,author_email,content,created_at,status) VALUES(?,?,?,?,?,?,?)');
    $insert->execute([$postId, $parentId, $userId, $email, $body, $createdAt ?? time(), $status]);
}
$duplicateError = '这条评论已经提交过了。';
$tests['visitor duplicates match exact identity content and post in every status'] = static function () use ($duplicateError): void {
    duplicate_fixture();
    foreach (['pending', 'approved', 'spam'] as $status) { duplicate_insert(body: $status, status: $status); content_check('duplicate_comment_error', [1, 0, 0, 'visitor@example.test', $status], $duplicateError); }
    content_check('duplicate_comment_error', [2, 0, 0, 'visitor@example.test', 'pending'], '');
    content_check('duplicate_comment_error', [1, 0, 0, 'other@example.test', 'pending'], '');
    content_check('duplicate_comment_error', [1, 0, 0, 'Visitor@example.test', 'pending'], '');
    content_check('duplicate_comment_error', [1, 0, 0, 'visitor@example.test', 'Pending'], '');
};
$tests['authenticated duplicates use user IDs independently of email'] = static function () use ($duplicateError): void {
    duplicate_fixture(); duplicate_insert(userId: 7);
    content_check('duplicate_comment_error', [1, 0, 7, 'changed@example.test', 'Same content'], $duplicateError);
    content_check('duplicate_comment_error', [1, 0, 8, 'visitor@example.test', 'Same content'], '');
    content_check('duplicate_comment_error', [1, 0, 0, 'visitor@example.test', 'Same content'], '');
};
$tests['top level and reply duplicates preserve null and negative parent handling'] = static function () use ($duplicateError): void {
    duplicate_fixture(); duplicate_insert(parentId: 5);
    content_check('duplicate_comment_error', [1, 5, 0, 'visitor@example.test', 'Same content'], $duplicateError);
    content_check('duplicate_comment_error', [1, 6, 0, 'visitor@example.test', 'Same content'], '');
    content_check('duplicate_comment_error', [1, 0, 0, 'visitor@example.test', 'Same content'], '');
    duplicate_insert(parentId: 0);
    content_check('duplicate_comment_error', [1, 0, 0, 'visitor@example.test', 'Same content'], '');
    duplicate_insert();
    content_check('duplicate_comment_error', [1, -5, -7, 'visitor@example.test', 'Same content'], $duplicateError);
};
$tests['duplicate window and same request writes remain live'] = static function () use ($duplicateError): void {
    duplicate_fixture(); duplicate_insert(createdAt: time() - 90000);
    content_check('duplicate_comment_error', [1, 0, 0, 'visitor@example.test', 'Same content'], '');
    duplicate_insert(createdAt: time() - 86000);
    content_check('duplicate_comment_error', [1, 0, 0, 'visitor@example.test', 'Same content'], $duplicateError);
    $GLOBALS['content_database']->exec('DELETE FROM comments');
    content_check('duplicate_comment_error', [1, 0, 0, 'visitor@example.test', 'Same content'], '');
};
$tests['duplicate existence checks stop after the first matching record'] = static function () use ($duplicateError): void {
    duplicate_fixture();
    for ($i = 0; $i < 1000; $i++) { duplicate_insert(); }
    $GLOBALS['duplicate_visits'] = 0;
    $GLOBALS['content_profile'] = true;
    content_same($duplicateError, duplicate_comment_error(1, 0, 0, 'visitor@example.test', 'Same content'), 'Many duplicates must remain rejected.');
    content_same(1, $GLOBALS['duplicate_visits'], 'Existence checks should visit one matching record.');
    content_same(1, count($GLOBALS['content_queries']), 'The duplicate check must remain one query.');
    if ($GLOBALS['baseline'] !== '') {
        $GLOBALS['duplicate_visits'] = 0;
        content_same($duplicateError, baseline_duplicate_comment_error(1, 0, 0, 'visitor@example.test', 'Same content'), 'The original decision must match.');
        content_same(1000, $GLOBALS['duplicate_visits'], 'The original COUNT must scan every matching duplicate.');
    }
    $GLOBALS['content_profile'] = false;
};

try {
    foreach ($tests as $name => $test) { $test(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
    fwrite(STDOUT, count($tests) . " content tests, 0 failures.\n");
    if ($baseline !== '') { fwrite(STDOUT, "PASS Results match the original parsing, excerpt, privacy, and duplicate APIs.\n"); }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
