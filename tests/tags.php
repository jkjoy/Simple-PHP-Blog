<?php

declare(strict_types=1);

// Run: php tests/tags.php
// Load actual tag functions and routes without starting the blog or touching its database/cache.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "The tag tests require pdo_sqlite.\n");
    exit(1);
}

final class TagTestPDO extends PDO
{
    public array $queries = [];

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        return parent::prepare($query, $options);
    }
}

final class TagTestRedirect extends RuntimeException {}
final class TagTestNotFound extends RuntimeException {}

function tags_test_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function tags_test_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function db(): TagTestPDO { return $GLOBALS['tags_test_db']; }
function sblog_t(string $key, array $parameters = []): string
{
    $replacements = [];
    foreach ($parameters as $name => $value) { $replacements['{' . $name . '}'] = (string)$value; }
    return strtr($key, $replacements);
}
function sblog_tn(string $key, int $count, array $parameters = []): string
{
    return sblog_t($key, ['count' => $count] + $parameters);
}
function require_admin_post(string $url): void {}
function url_for(string $action): string { return '/' . $action; }
function set_flash(string $type, string $message): void {}
function redirect_to(string $url): never { throw new TagTestRedirect($url); }
function render_admin_tags_page(array $form = [], array $errors = []): never
{
    throw new RuntimeException('Unexpected tag validation failure: ' . implode('; ', $errors));
}
function simple_error_page(string $title, string $message, int $status): never
{
    throw new TagTestNotFound($title, $status);
}
function render_public_post_list(array $posts): string
{
    $GLOBALS['tags_test_rendered_posts'] = $posts;
    return 'post-list';
}
function render_layout(string $title, string $content, array $context): void
{
    $GLOBALS['tags_test_rendered_layout'] = [$title, $content, $context];
}

function tags_test_load_core(): void
{
    $source = (string)file_get_contents(dirname(__DIR__) . '/index.php');
    $needed = array_fill_keys([
        'h', 'str_len_u', 'str_sub_u', 'str_lower_u', 'q', 'one', 'all_rows', 'val',
        'content_kind', 'parse_tags_input', 'encode_tags', 'post_tags', 'tag_descriptors', 'slugify',
        'tag_meta_cache', 'tag_slug_for_label', 'tag_index_data', 'fetch_posts_by_tag_slug',
        'tag_label_by_slug', 'render_tag_page', 'render_public_layout', 'replace_tag_everywhere',
        'parse_reply_hidden_blocks', 'strip_reply_hidden_blocks', 'content_has_reply_hidden_blocks',
        'markdown_to_plain', 'derive_excerpt', 'content_requires_password',
        'public_content_description', 'public_content_context',
    ], true);
    $tokens = token_get_all($source);
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $nameIndex = $i + 1;
        while (isset($tokens[$nameIndex]) && (
            (is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_WHITESPACE)
            || (is_array($tokens[$nameIndex]) ? $tokens[$nameIndex][1] : $tokens[$nameIndex]) === '&'
        )) { $nameIndex++; }
        if (!isset($tokens[$nameIndex]) || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) { continue; }
        $name = $tokens[$nameIndex][1];
        if (!isset($needed[$name])) { continue; }
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
    tags_test_same([], array_keys($needed), 'Every tested function must come from the actual core source.');
    foreach (['save_tag', 'delete_tag'] as $action) {
        tags_test_assert(preg_match("/    case '" . $action . "':.*?(?=\r?\n    case ')/s", $source, $match) === 1, 'Missing actual tag route: ' . $action);
        $GLOBALS['tags_test_routes'][$action] = $match[0];
    }
}

function tags_test_action(string $action, array $input): void
{
    $_POST = $input;
    try {
        eval('switch ($action) {' . $GLOBALS['tags_test_routes'][$action] . '}');
        throw new RuntimeException('The actual tag route did not redirect.');
    } catch (TagTestRedirect $redirect) {
        tags_test_same('/admin_tags', $redirect->getMessage(), 'Tag changes must return to tag management.');
    } finally {
        $_POST = [];
    }
}

function tags_test_post(int $id, string $tags, array $overrides = []): void
{
    $row = array_replace([
        'id' => $id, 'kind' => 'post', 'status' => 'published', 'published_at' => time() - 100,
        'updated_at' => time() - 100, 'is_pinned' => 0, 'tags' => $tags,
        'content' => 'Public content ' . $id, 'excerpt' => '', 'content_password_hash' => '',
    ], $overrides);
    q('INSERT INTO posts(' . implode(',', array_keys($row)) . ') VALUES(' . implode(',', array_fill(0, count($row), '?')) . ')', array_values($row));
}

function tags_test_meta(string $label, string $slug): void
{
    q('INSERT INTO tag_meta(label, slug, updated_at) VALUES(?,?,?)', [$label, $slug, time()]);
}

function tags_test_queries(string $pattern): array
{
    return array_values(array_filter(db()->queries, static fn(string $sql): bool => preg_match($pattern, $sql) === 1));
}

$tests = [];
$tests['repeated descriptors load metadata once and retain custom Unicode slugs'] = static function (): void {
    tags_test_meta('PHP', 'php-tips');
    tags_test_meta('中文', 'chinese');
    db()->queries = [];
    for ($i = 0; $i < 100; $i++) {
        tags_test_same([
            ['label' => 'PHP', 'slug' => 'php-tips'], ['label' => '中文', 'slug' => 'chinese'],
        ], tag_descriptors(['tags' => '["PHP", "中文", "PHP", null, 123]']), 'Descriptors must preserve labels, custom slugs and deduplication.');
    }
    tags_test_same(['SELECT label, slug FROM tag_meta'], db()->queries, 'Repeated occurrences must not cause repeated metadata queries.');
};

$tests['published index preserves counts and ordering while reading only tags'] = static function (): void {
    tags_test_meta('Beta', 'custom-beta');
    tags_test_meta('Alpha', 'alpha');
    tags_test_post(1, '["Beta", "Alpha", "Beta"]');
    tags_test_post(2, "Beta，Alpha\n");
    tags_test_post(3, '["Draft"]', ['status' => 'draft']);
    tags_test_post(4, '["Future"]', ['published_at' => time() + 86400]);
    tags_test_post(5, '["Page"]', ['kind' => 'page']);
    db()->queries = [];
    tags_test_same([
        ['slug' => 'alpha', 'label' => 'Alpha', 'count' => 2],
        ['slug' => 'custom-beta', 'label' => 'Beta', 'count' => 2],
    ], tag_index_data(), 'Only currently published articles must contribute, with alphabetical ties.');
    tags_test_same(2, count(db()->queries), 'A known-tag index needs one post query and one metadata query.');
    tags_test_assert(str_starts_with(db()->queries[0], 'SELECT tags FROM posts'), 'Index queries must omit article bodies.');
};

$tests['admin index includes drafts and scheduled posts but excludes pages'] = static function (): void {
    tags_test_post(1, '["Live"]');
    tags_test_post(2, '["Draft"]', ['status' => 'draft']);
    tags_test_post(3, '["Future"]', ['published_at' => time() + 86400]);
    tags_test_post(4, '["Page"]', ['kind' => 'page']);
    db()->queries = [];
    tags_test_same(['Draft', 'Future', 'Live'], array_column(tag_index_data(false), 'label'), 'Admin tag management must include every article status.');
    tags_test_same([], tags_test_queries('/SELECT \* FROM posts/'), 'Admin indexes must also omit article bodies.');
};

$tests['legacy tags register once per distinct label and preserve slug collisions'] = static function (): void {
    tags_test_meta('Reserved', 'a-b');
    tags_test_post(1, '["A B", "A-B", "中文"]');
    tags_test_post(2, '["A-B"]', ['published_at' => time() - 90]);
    tags_test_post(3, '["A B", "中文"]', ['published_at' => time() - 80]);
    db()->queries = [];
    $tags = tag_index_data();
    tags_test_same(['a-b-2', '中文', 'a-b-3'], array_column($tags, 'slug'), 'Distinct raw labels sharing a slug must receive unique suffixes; same-post duplicates retain existing parse behavior.');
    tags_test_same([2, 2, 1], array_column($tags, 'count'), 'Legacy count semantics must remain intact.');
    tags_test_same(3, count(tags_test_queries('/^INSERT OR IGNORE INTO tag_meta/')), 'A repeated legacy label must insert only once.');
    tags_test_same(1, count(tags_test_queries('/^SELECT label, slug FROM tag_meta/')), 'Legacy registration must reuse the metadata mapping.');
};

$tests['detail fetch reads only matched bodies and preserves order and protected content'] = static function (): void {
    tags_test_meta('PHP', 'custom-php');
    tags_test_meta('Other', 'other');
    tags_test_post(1, '["PHP"]', ['published_at' => time() - 20]);
    tags_test_post(2, '["PHP"]', ['published_at' => time() - 20]);
    tags_test_post(3, '["PHP"]', ['is_pinned' => 1, 'published_at' => time() - 200, 'content_password_hash' => 'secret-hash', 'content' => 'SECRET']);
    tags_test_post(4, '["PHP"]', ['published_at' => time() - 100, 'content' => "Visible\n[reply]\nHIDDEN\n[/reply]"]);
    tags_test_post(5, '["Other"]', ['content' => str_repeat('unrelated body', 10000)]);
    tags_test_post(6, '["PHP"]', ['status' => 'draft']);
    tags_test_post(7, '["PHP"]', ['published_at' => time() + 86400]);
    tags_test_post(8, '["PHP"]', ['kind' => 'page']);
    db()->queries = [];
    $posts = fetch_posts_by_tag_slug(' custom-php ');
    tags_test_same([3, 2, 1, 4], array_column($posts, 'id'), 'Pinned, time and ID ordering must remain unchanged; hidden statuses and pages must be excluded.');
    tags_test_same('', $posts[0]['content'], 'Passwords must hide bodies.');
    tags_test_assert(!isset($posts[0]['content_password_hash']), 'Password hashes must not enter public context.');
    tags_test_same('Visible', $posts[3]['content'], 'Reply-hidden text must not enter public lists.');
    tags_test_same(3, count(db()->queries), 'Detail queries must be constant for registered tags.');
    $fullQueries = tags_test_queries('/^SELECT \* FROM posts/');
    tags_test_same(1, count($fullQueries), 'Only one full-row query should run.');
    tags_test_assert(str_contains($fullQueries[0], 'id IN (2,1,4,3)'), 'Full rows must be limited to matching IDs.');
};

$tests['unknown details still discover legacy tags without reading bodies'] = static function (): void {
    tags_test_post(1, '["Legacy"]');
    db()->queries = [];
    tags_test_same([], fetch_posts_by_tag_slug('missing'), 'An unknown tag must return no posts.');
    tags_test_same('legacy', tag_slug_for_label('Legacy'), 'Unknown requests must still register visible legacy tags.');
    tags_test_same([], tags_test_queries('/^SELECT \* FROM posts/'), 'An unknown tag must not load article bodies.');
    tags_test_same('Legacy', tag_label_by_slug('legacy'), 'The existing label API must discover a visible legacy tag.');
    tags_test_same([], fetch_posts_by_tag_slug(''), 'An empty slug must return immediately.');
};

$tests['detail rendering does not scan the tag index again'] = static function (): void {
    tags_test_meta('PHP', 'custom-php');
    for ($i = 1; $i <= 100; $i++) { tags_test_post($i, '["PHP"]'); }
    db()->queries = [];
    render_tag_page('custom-php');
    tags_test_same('#PHP', $GLOBALS['tags_test_rendered_layout'][0], 'Rendered title must use the tag label.');
    tags_test_same(100, count($GLOBALS['tags_test_rendered_posts']), 'All matched posts must render.');
    tags_test_same(3, count(db()->queries), '100 repeated tags must render with three core SQL statements.');
    tags_test_same([], tags_test_queries('/^SELECT tags FROM posts/'), 'Details must not rebuild the tag index.');
};

$tests['legacy detail registration preserves chronological collision allocation before pinning'] = static function (): void {
    tags_test_post(1, '["A-B"]', ['is_pinned' => 1, 'published_at' => time() - 200]);
    tags_test_post(2, '["A B"]', ['published_at' => time() - 10]);
    render_tag_page('a-b');
    tags_test_same('#A B', $GLOBALS['tags_test_rendered_layout'][0], 'A newer legacy label must claim the base slug as it did when the tag index loaded first.');
    tags_test_same([2], array_column($GLOBALS['tags_test_rendered_posts'], 'id'), 'Pinned collisions must not take another label\'s legacy URL.');
    tags_test_same('a-b-2', tag_slug_for_label('A-B'), 'The older pinned label must retain the collision suffix.');
};

$tests['orphan metadata and invisible tags remain 404'] = static function (): void {
    tags_test_meta('Orphan', 'orphan');
    tags_test_meta('Draft', 'draft');
    tags_test_meta('Future', 'future');
    tags_test_post(1, '["Draft"]', ['status' => 'draft']);
    tags_test_post(2, '["Future"]', ['published_at' => time() + 86400]);
    foreach (['orphan', 'draft', 'future', 'missing'] as $slug) {
        tags_test_same(null, tag_label_by_slug($slug), 'Metadata alone must not make an invisible tag public.');
        try { render_tag_page($slug); throw new RuntimeException('An invisible tag rendered.'); }
        catch (TagTestNotFound $exception) { tags_test_same(404, $exception->getCode(), 'Invisible tags must return 404.'); }
    }
};

$tests['saving a custom slug refreshes an already loaded mapping'] = static function (): void {
    tags_test_meta('PHP', 'old-php');
    tags_test_post(1, '["PHP"]');
    tags_test_same('old-php', tag_slug_for_label('PHP'), 'Warm the original mapping.');
    tags_test_action('save_tag', ['old_tag' => 'PHP', 'new_tag' => 'PHP', 'tag_slug' => 'new-php']);
    tags_test_same('new-php', tag_slug_for_label('PHP'), 'A same-request slug change must immediately apply.');
    tags_test_same([], fetch_posts_by_tag_slug('old-php'), 'An obsolete slug must no longer match.');
    tags_test_same([1], array_column(fetch_posts_by_tag_slug('new-php'), 'id'), 'The saved slug must match immediately.');
};

$tests['renaming tags updates legacy article and page values and deduplicates'] = static function (): void {
    tags_test_meta('PHP', 'old-php');
    tags_test_meta('Other', 'other');
    tags_test_post(1, '["PHP", "Other"]');
    tags_test_post(2, 'php, New', ['status' => 'draft']);
    tags_test_post(3, '["PHP"]', ['kind' => 'page']);
    tag_slug_for_label('PHP');
    tags_test_action('save_tag', ['old_tag' => 'PHP', 'new_tag' => 'New', 'tag_slug' => 'new-tag']);
    tags_test_same(['New', 'Other'], post_tags(one('SELECT tags FROM posts WHERE id = 1')), 'Article rename must retain other tags.');
    tags_test_same(['New'], post_tags(one('SELECT tags FROM posts WHERE id = 2')), 'Case-insensitive legacy renames must deduplicate.');
    tags_test_same(['New'], post_tags(one('SELECT tags FROM posts WHERE id = 3')), 'Existing page tag replacements must remain intact.');
    tags_test_same('new-tag', tag_slug_for_label('New'), 'Renamed tags must use the requested slug immediately.');
    tags_test_same(null, tag_label_by_slug('old-php'), 'Renamed labels must not remain public.');
};

$tests['deleting selected tags refreshes cached slugs without altering other tags'] = static function (): void {
    tags_test_meta('PHP', 'custom-php');
    tags_test_meta('Other', 'other');
    tags_test_post(1, '["PHP", "Other"]');
    tags_test_post(2, 'php', ['status' => 'draft']);
    tag_slug_for_label('PHP');
    tags_test_action('delete_tag', ['tag_ids' => ['PHP', 'PHP', '']]);
    tags_test_same(['Other'], post_tags(one('SELECT tags FROM posts WHERE id = 1')), 'Deleting tags must retain unselected values.');
    tags_test_same([], post_tags(one('SELECT tags FROM posts WHERE id = 2')), 'Deleting tags must apply to drafts as before.');
    tags_test_same(null, tag_label_by_slug('custom-php'), 'Deleted tags must no longer be public.');
    tags_test_same('php', tag_slug_for_label('PHP'), 'A re-created label must not reuse a stale deleted custom slug.');
};

$tests['registration refreshes stale metadata after another writer claims a slug or label'] = static function (): void {
    tag_meta_cache();
    tags_test_meta('Other', 'new');
    tags_test_same('new-2', tag_slug_for_label('New'), 'A concurrently claimed slug must receive a safe unique suffix.');
    tags_test_meta('Later', 'custom-later');
    tags_test_same('custom-later', tag_slug_for_label('Later'), 'A concurrently registered label must retain its chosen slug.');
    tags_test_meta('Empty', '');
    tags_test_same('empty', tag_slug_for_label('Empty'), 'Legacy empty metadata must retain its old generated-slug fallback without looping.');
};

$tests['large matching sets avoid SQLite bound-parameter limits'] = static function (): void {
    tags_test_meta('All', 'all');
    db()->beginTransaction();
    for ($id = 1; $id <= 1200; $id++) { tags_test_post($id, '["All"]'); }
    db()->commit();
    db()->queries = [];
    tags_test_same(1200, count(fetch_posts_by_tag_slug('all')), 'Large tag lists must return every matching post.');
    tags_test_same(3, count(db()->queries), 'Large lists must keep the same SQL query count.');
    tags_test_assert(substr_count(db()->queries[2], '?') === 3, 'Matched IDs must not consume bound SQL parameters.');
};

if (($argv[1] ?? '') !== '--case') {
    $failed = 0;
    foreach (array_keys($tests) as $index => $name) {
        $process = proc_open([PHP_BINARY, __FILE__, '--case', (string)$index], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Could not start an isolated tag test process.'); }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status === 0) { fwrite(STDOUT, 'PASS ' . $name . "\n"); }
        else { $failed++; fwrite(STDERR, 'FAIL ' . $name . ': ' . trim($stderr . $stdout) . "\n"); }
    }
    fwrite(STDOUT, sprintf("\n%d tests, %d failures. The installed blog was not loaded or modified.\n", count($tests), $failed));
    exit($failed === 0 ? 0 : 1);
}

$test = array_values($tests)[(int)($argv[2] ?? -1)] ?? null;
if (!is_callable($test)) { fwrite(STDERR, "Unknown tag test case.\n"); exit(1); }
try {
    $GLOBALS['tags_test_db'] = new TagTestPDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    db()->exec('CREATE TABLE tag_meta(label TEXT NOT NULL UNIQUE, slug TEXT NOT NULL UNIQUE, updated_at INTEGER NOT NULL)');
    db()->exec('CREATE TABLE posts(id INTEGER PRIMARY KEY, kind TEXT NOT NULL, status TEXT NOT NULL, published_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL, is_pinned INTEGER NOT NULL, tags TEXT NOT NULL, content TEXT NOT NULL, excerpt TEXT NOT NULL, content_password_hash TEXT NOT NULL)');
    tags_test_load_core();
    $test();
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
