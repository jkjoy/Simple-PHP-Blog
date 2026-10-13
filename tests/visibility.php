<?php

declare(strict_types=1);

// Run: php tests/visibility.php
// Extract real core functions; each case uses a fresh process and an in-memory database.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "The visibility tests require pdo_sqlite.\n"); exit(1); }

final class VisibilityTestDatabase extends PDO
{
    public array $queries = [];
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        return parent::prepare($query, $options);
    }
}

function visibility_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}
function visibility_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
function db(): VisibilityTestDatabase { return $GLOBALS['visibility_db']; }
function update_admin_presence(?int $adminId): void { $GLOBALS['visibility_presence'][] = $adminId; }
function sblog_t(string $message): string { return $message; }
// Keep presentation outside this permission/query test; parsing and permission checks remain real.
function markdown_to_html(string $markdown): string { return '<p>' . h($markdown) . '</p>'; }
function render_reply_content_gate(array $post): string { return '<reply-gate>'; }
function render_password_content_gate(array $post): string { return '<password-gate>'; }

function visibility_load_core(): void
{
    $source = (string)file_get_contents(dirname(__DIR__) . '/index.php');
    foreach (['ADMIN_SESSION_IDLE_TIMEOUT', 'ADMIN_SESSION_ABSOLUTE_TIMEOUT'] as $constant) {
        visibility_assert(preg_match('/const ' . $constant . ' = [0-9]+;/', $source, $match) === 1, 'Missing core authentication constant.');
        eval($match[0]);
    }
    $needed = array_fill_keys([
        'h', 'q', 'one', 'all_rows', 'str_lower_u', 'str_len_u', 'str_sub_u', 'content_kind',
        'current_admin', 'clear_admin_authentication', 'is_admin', 'authenticated_comment_identity',
        'remember_reply_hidden_comment', 'can_view_reply_hidden_content',
        'content_requires_password', 'content_password_is_unlocked', 'remember_content_password_unlock',
        'parse_reply_hidden_blocks', 'strip_reply_hidden_blocks', 'content_has_reply_hidden_blocks',
        'markdown_to_plain', 'derive_excerpt', 'public_content_description', 'public_content_context',
        'render_content_html', 'fetch_published_posts', 'fetch_feed_posts',
    ], true);
    $tokens = token_get_all($source);
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $j = $i + 1;
        while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
        if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_STRING || !isset($needed[$tokens[$j][1]])) { continue; }
        $name = $tokens[$j][1];
        $code = '';
        $depth = 0;
        $started = false;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $started = true;
            } elseif ($token === '}' && --$depth === 0 && $started) { break; }
        }
        eval($code);
        unset($needed[$name]);
    }
    visibility_same([], array_keys($needed), 'All functions must come from the actual core.');
}

function visibility_comment(int $id, int $postId = 1, string $status = 'pending'): void
{
    q('INSERT INTO comments(id, post_id, status) VALUES(?,?,?)', [$id, $postId, $status]);
}
function visibility_post(array $values = []): array
{
    return array_replace(['id' => 1, 'kind' => 'post', 'status' => 'published', 'published_at' => time() - 100,
        'is_pinned' => 0, 'content' => 'Public', 'excerpt' => '', 'content_password_hash' => ''], $values);
}
function visibility_admin(array $values = []): void
{
    $user = array_replace(['id' => 1, 'username' => 'admin', 'password_hash' => 'hash',
        'nickname' => ' Admin   Name ', 'email' => ' ADMIN@EXAMPLE.COM ', 'avatar_url' => '',
        'website_url' => 'https://example.com/', 'created_at' => 1], $values);
    q('INSERT INTO users(' . implode(',', array_keys($user)) . ') VALUES(' . implode(',', array_fill(0, count($user), '?')) . ')', array_values($user));
    $_SESSION['admin_id'] = 1;
}

$tests = [];
$tests['eight remembered comments require one approval query'] = static function (): void {
    for ($id = 1; $id <= 8; $id++) { visibility_comment($id, 1, $id === 1 ? 'approved' : 'pending'); }
    $_SESSION['reply_hidden_comment_ids'][1] = range(1, 8);
    db()->queries = [];
    visibility_same(true, can_view_reply_hidden_content(['id' => 1]), 'An approved remembered comment must grant access.');
    visibility_same(1, count(db()->queries), 'Checking eight candidates must issue a single approval query.');
    visibility_assert(str_contains(db()->queries[0], 'id IN (?,?,?,?,?,?,?,?)'), 'Candidates must use parameter placeholders.');
};
$tests['pending spam missing and wrong-post comments do not grant access'] = static function (): void {
    visibility_comment(1, 1, 'pending');
    visibility_comment(2, 1, 'spam');
    visibility_comment(3, 2, 'approved');
    $_SESSION['reply_hidden_comment_ids'][1] = [1, 2, 3, 4];
    db()->queries = [];
    visibility_same(false, can_view_reply_hidden_content(['id' => 1]), 'Approval must belong to this exact post and remembered comment.');
    visibility_same(1, count(db()->queries), 'Denied candidates must also use one approval query.');
};
$tests['only the latest eight stored candidates are considered'] = static function (): void {
    for ($id = 1; $id <= 9; $id++) { visibility_comment($id, 1, $id === 1 ? 'approved' : 'pending'); }
    $_SESSION['reply_hidden_comment_ids'][1] = range(1, 9);
    db()->queries = [];
    visibility_same(false, can_view_reply_hidden_content(['id' => 1]), 'An approval outside the last eight session entries must not unlock content.');
    visibility_same(1, count(db()->queries), 'The capped candidate list must be checked once.');
};
$tests['invalid candidate lists perform no SQL and preserve legacy integer coercion'] = static function (): void {
    foreach ([[], [0, -1, null, 'invalid'], 'invalid'] as $stored) {
        $_SESSION['reply_hidden_comment_ids'][1] = $stored;
        db()->queries = [];
        visibility_same(false, can_view_reply_hidden_content(['id' => 1]), 'Invalid candidate lists must not authorize.');
        visibility_same([], db()->queries, 'Empty or invalid candidates must not query SQLite.');
    }
    visibility_comment(6, 1, 'approved');
    $_SESSION['reply_hidden_comment_ids'][1] = ['legacy-key' => '6suffix'];
    visibility_same(true, can_view_reply_hidden_content(['id' => 1]), 'Existing integer conversion behavior must remain intact.');
    visibility_same(false, can_view_reply_hidden_content(['id' => 0]), 'Invalid post IDs must never authorize.');
};
$tests['approval changes and deletion take effect in the same request'] = static function (): void {
    visibility_comment(1);
    $_SESSION['reply_hidden_comment_ids'][1] = [1];
    visibility_same(false, can_view_reply_hidden_content(['id' => 1]), 'Pending starts locked.');
    q("UPDATE comments SET status = 'approved' WHERE id = 1");
    visibility_same(true, can_view_reply_hidden_content(['id' => 1]), 'Approval must unlock immediately.');
    q("UPDATE comments SET status = 'spam' WHERE id = 1");
    visibility_same(false, can_view_reply_hidden_content(['id' => 1]), 'Revoking approval must relock immediately.');
    q("UPDATE comments SET status = 'approved' WHERE id = 1");
    visibility_same(true, can_view_reply_hidden_content(['id' => 1]), 'Reapproval must be re-read.');
    q('DELETE FROM comments WHERE id = 1');
    visibility_same(false, can_view_reply_hidden_content(['id' => 1]), 'Deletion must immediately remove authorization.');
};
$tests['newly remembered comments apply immediately and retain the eight-entry bound'] = static function (): void {
    $_SESSION['reply_hidden_comment_ids'][1] = range(1, 8);
    visibility_comment(9, 1, 'approved');
    visibility_same(false, can_view_reply_hidden_content(['id' => 1]), 'A comment outside the current session list must not authorize.');
    remember_reply_hidden_comment(1, 9);
    visibility_same(range(2, 9), $_SESSION['reply_hidden_comment_ids'][1], 'Remembering a comment must retain only the latest eight IDs.');
    visibility_same(true, can_view_reply_hidden_content(['id' => 1]), 'A newly remembered approved comment must authorize immediately.');
};
$tests['ordinary rendering skips unused reply and administrator authorization reads'] = static function (): void {
    visibility_admin();
    visibility_comment(1, 1, 'approved');
    $_SESSION['reply_hidden_comment_ids'][1] = [1];
    db()->queries = [];
    visibility_same('<p>Plain text</p>', render_content_html(visibility_post(['content' => 'Plain text'])), 'Ordinary text must render unchanged.');
    visibility_same([], db()->queries, 'Content without hidden segments must not query permissions or current admin.');
};
$tests['hidden rendering checks once for every hidden segment and respects fresh approvals'] = static function (): void {
    visibility_comment(1);
    $_SESSION['reply_hidden_comment_ids'][1] = [1];
    $post = visibility_post(['content' => "Before\n[reply]\nSECRET ONE\n[/reply]\nBetween\n[reply]\nSECRET TWO\n[/reply]\nAfter"]);
    db()->queries = [];
    $locked = render_content_html($post);
    visibility_assert(!str_contains($locked, 'SECRET') && substr_count($locked, '<reply-gate>') === 2, 'Every hidden segment must render a gate while locked.');
    visibility_same(1, count(db()->queries), 'Several hidden segments must reuse one live approval check per render.');
    q("UPDATE comments SET status = 'approved' WHERE id = 1");
    db()->queries = [];
    $unlocked = render_content_html($post);
    visibility_assert(str_contains($unlocked, 'SECRET ONE') && str_contains($unlocked, 'SECRET TWO'), 'Approval must expose both segments on the next same-request render.');
    visibility_same(1, count(db()->queries), 'A fresh unlocked render must still check current approval once.');
};
$tests['password gates and public contexts retain their protection after unlock'] = static function (): void {
    $post = visibility_post(['content_password_hash' => 'private-hash', 'content' => 'PASSWORD SECRET', 'excerpt' => 'PRIVATE EXCERPT']);
    db()->queries = [];
    visibility_same('<password-gate>', render_content_html($post), 'A locked password post must return its gate before querying reply permissions.');
    visibility_same([], db()->queries, 'An anonymous password gate must not query comment authorization.');
    remember_content_password_unlock($post);
    visibility_same('<p>PASSWORD SECRET</p>', render_content_html($post), 'A valid session unlock must render password content.');
    $public = public_content_context($post);
    visibility_same('', $public['content'], 'Public lists must hide password bodies even after session unlock.');
    visibility_same('此文章受密码保护。', $public['excerpt'], 'Private excerpts must not leak through public lists.');
    visibility_assert(!isset($public['content_password_hash']), 'Password hashes must be removed from public contexts.');
    visibility_same('<password-gate>', render_content_html(array_replace($post, ['content_password_hash' => 'changed-hash'])), 'A changed password must invalidate the old session unlock.');
};
$tests['public home and feed lists sanitize bodies without identity queries'] = static function (): void {
    foreach ([visibility_post(), visibility_post(['id' => 2, 'content' => "Visible\n[reply]\nSECRET\n[/reply]", 'excerpt' => 'SECRET EXCERPT']),
        visibility_post(['id' => 3, 'content_password_hash' => 'hash', 'content' => 'PASSWORD SECRET'])] as $post) {
        q('INSERT INTO posts(' . implode(',', array_keys($post)) . ') VALUES(' . implode(',', array_fill(0, count($post), '?')) . ')', array_values($post));
    }
    visibility_admin();
    db()->queries = [];
    foreach ([fetch_published_posts(20, 0), fetch_feed_posts(20)] as $posts) {
        visibility_same(3, count($posts), 'Both real list APIs must return every visible article.');
        foreach ($posts as $post) {
            visibility_assert(!str_contains($post['content'] . $post['excerpt'], 'SECRET'), 'Feed and home contexts must exclude protected bodies and excerpts.');
            visibility_assert(!isset($post['content_password_hash']), 'Both APIs must remove password hashes.');
        }
    }
    visibility_same(2, count(db()->queries), 'Two list APIs require only their two post queries, with no per-post identity/permission reads.');
};
$tests['authenticated comment identity already reuses one validated administrator query'] = static function (): void {
    visibility_admin();
    db()->queries = [];
    for ($i = 0; $i < 100; $i++) {
        visibility_same(['user_id' => 1, 'author_name' => 'Admin Name', 'author_email' => 'admin@example.com', 'author_url' => 'https://example.com/'],
            authenticated_comment_identity(), 'Real identity normalization must remain intact.');
        visibility_same(true, can_view_reply_hidden_content(['id' => 1]), 'Administrators must bypass reply approval checks.');
    }
    visibility_same(1, count(db()->queries), 'Repeated administrator and identity checks already share the current_admin cache.');
    visibility_same([1], $GLOBALS['visibility_presence'], 'Presence must be updated once without accessing production files.');
};
foreach (['password fingerprint', 'idle timeout', 'absolute timeout'] as $rejection) {
    $tests[$rejection . ' rejects administrator bypass and authenticated identity'] = static function () use ($rejection): void {
        visibility_admin();
        if ($rejection === 'password fingerprint') { $_SESSION['admin_password_fingerprint'] = hash('sha256', 'old-password'); }
        elseif ($rejection === 'idle timeout') { $_SESSION['admin_last_seen_at'] = time() - ADMIN_SESSION_IDLE_TIMEOUT - 10; }
        else { $_SESSION['admin_authenticated_at'] = time() - ADMIN_SESSION_ABSOLUTE_TIMEOUT - 10; }
        visibility_comment(1, 1, 'pending');
        $_SESSION['reply_hidden_comment_ids'][1] = [1];
        db()->queries = [];
        visibility_same(false, can_view_reply_hidden_content(['id' => 1]), 'Rejected sessions must lose administrator bypass.');
        visibility_same(null, authenticated_comment_identity(), 'Rejected administrator sessions must have no authenticated comment identity.');
        visibility_assert(!isset($_SESSION['admin_id']), 'Rejected sessions must clear authentication.');
        visibility_same(2, count(db()->queries), 'Rejected auth must perform one user read and one live comment check.');
    };
}

if (($argv[1] ?? '') !== '--case') {
    $failed = 0;
    foreach (array_keys($tests) as $index => $name) {
        $process = proc_open([PHP_BINARY, __FILE__, '--case', (string)$index], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Could not start an isolated visibility test.'); }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $status = proc_close($process);
        if ($status === 0) { fwrite(STDOUT, 'PASS ' . $name . "\n"); }
        else { $failed++; fwrite(STDERR, 'FAIL ' . $name . ': ' . trim($stderr . $stdout) . "\n"); }
    }
    fwrite(STDOUT, sprintf("\n%d tests, %d failures. No installed database or cache was used.\n", count($tests), $failed));
    exit($failed === 0 ? 0 : 1);
}
$test = array_values($tests)[(int)($argv[2] ?? -1)] ?? null;
if (!is_callable($test)) { fwrite(STDERR, "Unknown visibility test case.\n"); exit(1); }
try {
    $GLOBALS['visibility_db'] = new VisibilityTestDatabase('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    db()->exec('CREATE TABLE comments(id INTEGER PRIMARY KEY, post_id INTEGER, status TEXT)');
    db()->exec('CREATE TABLE users(id INTEGER PRIMARY KEY, username TEXT, password_hash TEXT, nickname TEXT, email TEXT, avatar_url TEXT, website_url TEXT, created_at INTEGER)');
    db()->exec('CREATE TABLE posts(id INTEGER PRIMARY KEY, kind TEXT, status TEXT, published_at INTEGER, is_pinned INTEGER, content TEXT, excerpt TEXT, content_password_hash TEXT)');
    $_SESSION = [];
    $GLOBALS['visibility_presence'] = [];
    visibility_load_core();
    $test();
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
