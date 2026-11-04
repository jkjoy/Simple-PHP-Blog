<?php

declare(strict_types=1);

namespace SBlogAdminPostsTests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PDO;
use PDOStatement;
use RuntimeException;
use Stringable;
use Throwable;

// Run: php tests/admin-posts.php. Real core functions use an in-memory database only.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function same(mixed $expected, mixed $actual, string $message): void
{
    $GLOBALS['post_assertions']++;
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

class PostStatement extends PDOStatement
{
    protected function __construct() {}
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = parent::fetchAll($mode, ...$args);
        $GLOBALS['post_fetched_rows'][] = count($rows);
        return $rows;
    }
}

function db(): PDO { return $GLOBALS['post_database']; }
function require_admin(): void { $GLOBALS['post_authorizations']++; }
function use_pretty_url(): bool { return $GLOBALS['post_pretty'] ?? false; }
function app_path(string $path): string { return '/blog' . $path; }
function script_url(): string { return '/blog/index.php'; }
function sblog_t(string $message, array $parameters = []): string
{
    foreach ($parameters as $key => $value) { $message = str_replace('{' . $key . '}', (string)$value, $message); }
    return $message;
}
function sblog_tn(string $message, int $count): string { return sblog_t($message, ['count' => $count]); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="fixture-token">'; }
function admin_icon(string $name): string { return ''; }
function render_admin_sidebar(string $active): string { return ''; }
function render_admin_content_start(string $title, string $sidebar): string { return '<main>'; }
function render_admin_content_end(string $title, string $active, string $description): void { $GLOBALS['post_html'] = (string)ob_get_clean() . '</main>'; }

function load_core(): void
{
    $needed = array_fill_keys([
        'h', 'q', 'all_rows', 'val', 'url_for', 'url_with_query', 'pretty_date',
        'content_kind', 'content_type_label', 'content_permalink', 'post_state',
        'fetch_admin_posts', 'render_admin_posts_page',
    ], true);
    $tokens = token_get_all((string)file_get_contents(dirname(__DIR__) . '/index.php'));
    for ($i = 0, $length = count($tokens); $i < $length; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $j = $i + 1;
        while (is_array($tokens[$j] ?? null) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
        if (!is_array($tokens[$j] ?? null) || !isset($needed[$tokens[$j][1]])) { continue; }
        $name = $tokens[$j][1];
        $code = '';
        $depth = 0;
        for (; $i < $length; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; }
            elseif ($token === '}' && --$depth === 0) { break; }
        }
        eval('namespace ' . __NAMESPACE__ . '; use \\PDOStatement; use \\Stringable; ' . $code);
        unset($needed[$name]);
    }
    same([], array_keys($needed), 'Every exercised database, URL and rendering function must come from the application.');
}

function fixture(int $count): array
{
    $GLOBALS['post_database'] = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_STATEMENT_CLASS => [PostStatement::class],
    ]);
    db()->exec('CREATE TABLE categories(id INTEGER PRIMARY KEY,name TEXT); INSERT INTO categories VALUES(1,\'Fixture category\')');
    db()->exec('CREATE TABLE posts(id INTEGER PRIMARY KEY,slug TEXT,title TEXT,kind TEXT,status TEXT,published_at INTEGER,updated_at INTEGER,is_pinned INTEGER,category_id INTEGER)');
    $insert = db()->prepare('INSERT INTO posts VALUES(?,?,?,?,?,?,?,?,?)');
    $rows = [];
    $now = time();
    db()->beginTransaction();
    for ($id = 1; $id <= $count; $id++) {
        $status = $id % 4 === 0 ? 'draft' : 'published';
        $publishedAt = $id % 4 === 1 ? $now + 3600 + ($id % 5) * 60 : $now - 3600 - ($id % 5) * 60;
        $row = [
            'id' => $id, 'slug' => 'entry-' . $id, 'title' => 'Entry <safe> ' . $id,
            'kind' => $id % 3 === 0 ? 'page' : 'post', 'status' => $status,
            'published_at' => $publishedAt, 'updated_at' => $now - 1000 + ($id % 7) * 60,
            'is_pinned' => $id % 6 === 0 ? 1 : 0, 'category_id' => $id % 2 === 0 ? 1 : 2,
        ];
        $insert->execute(array_values($row));
        $rows[] = $row;
    }
    db()->commit();
    usort($rows, static function (array $left, array $right): int {
        $leftDate = $left['status'] === 'published' ? $left['published_at'] : $left['updated_at'];
        $rightDate = $right['status'] === 'published' ? $right['published_at'] : $right['updated_at'];
        return ($rightDate <=> $leftDate) ?: ($right['id'] <=> $left['id']);
    });
    $GLOBALS['post_fetched_rows'] = [];
    return $rows;
}

function capture(array $query = []): DOMXPath
{
    $_GET = $query;
    $GLOBALS['post_authorizations'] = 0;
    $GLOBALS['post_fetched_rows'] = [];
    $level = ob_get_level();
    render_admin_posts_page();
    same($level, ob_get_level(), 'The article list must close its output buffer.');
    same(1, $GLOBALS['post_authorizations'], 'Article management must still require administrator access.');
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $GLOBALS['post_html']);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}

function row_ids(DOMXPath $xpath): array
{
    $ids = [];
    foreach ($xpath->query('//tbody/tr') as $row) { $ids[] = (int)$xpath->query('.//input[@name="id"]', $row)[0]?->getAttribute('value'); }
    return $ids;
}

function query_of(DOMElement $link): array
{
    parse_str((string)parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
    return $query;
}

$GLOBALS['post_assertions'] = 0;
$GLOBALS['post_fetched_rows'] = [];
load_core();
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new RuntimeException($message . ' at ' . $file . ':' . $line); });
$tests = [];

$tests['database pagination retains all content kinds and established ordering while fetching one page'] = static function (): void {
    foreach ([0, 12, 13, 24, 25] as $count) {
        $ordered = fixture($count);
        $pages = max(1, (int)ceil($count / 12));
        foreach ([1, 2, 3, 0, -1, PHP_INT_MAX] as $requested) {
            $expectedPage = max(1, min($pages, $requested));
            $GLOBALS['post_fetched_rows'] = [];
            [$posts, $total, $page, $totalPages] = fetch_admin_posts($requested);
            same($count, $total, 'Total must count articles and independent pages without dropping missing categories.');
            same($expectedPage, $page, 'The data query must clamp out-of-range pages before calculating its offset.');
            same($pages, $totalPages, 'The data query must retain at least one valid page.');
            same(array_column(array_slice($ordered, ($page - 1) * 12, 12), 'id'), array_column($posts, 'id'), 'Published, scheduled, draft, page and pinned entries must retain the existing date and ID ordering.');
            same([count($posts)], $GLOBALS['post_fetched_rows'], 'Only the visible page may be fetched from SQLite, rather than loading the entire inventory.');
            foreach ($posts as $post) { same($post['category_id'] === 1 ? 'Fixture category' : null, $post['category_name'], 'Category metadata must survive pagination and the left join.'); }
        }
    }
};

$tests['renderer normalizes page inputs and preserves page state and safe output in both URL modes'] = static function (): void {
    $ordered = fixture(25);
    foreach ([false, true] as $pretty) {
        $GLOBALS['post_pretty'] = $pretty;
        foreach ([['1', 1], ['2', 2], ['3', 3], ['9999999999999999999999999999', 3], ['0', 1], ['-1', 1], ['2.5', 1], ['2x', 1], [['2'], 1], ['<script>', 1]] as [$requested, $page]) {
            $xpath = capture(['p' => $requested]);
            same(array_column(array_slice($ordered, ($page - 1) * 12, 12), 'id'), row_ids($xpath), 'The rendered page must use normalized page input and show the expected entries.');
            same([count(row_ids($xpath))], $GLOBALS['post_fetched_rows'], 'Rendering must not fetch hidden pages.');
            same(0, $xpath->query('//safe|//script')->length, 'Article titles and malformed page inputs must never create markup.');
            foreach ($xpath->query('//tbody//form[@method="post"]') as $form) {
                same(1, $xpath->query('.//input[@name="csrf"]', $form)->length, 'Each article write action must retain CSRF protection.');
                same((string)$page, $xpath->query('.//input[@name="return_page"]', $form)[0]?->getAttribute('value'), 'Publish, draft and delete actions must retain the normalized current page.');
            }
            $nav = $xpath->query('//nav[contains(@class,"admin-pagination")]');
            same(1, $nav->length, 'Multiple article pages need an accessible pagination control.');
            $current = $xpath->query('.//*[@aria-current="page"]', $nav[0]);
            same(1, $current->length, 'Exactly one visible page number must identify the current page.');
            same((string)$page, trim($current[0]->textContent), 'The highlighted page number must match the rendered page.');
            foreach ($xpath->query('.//a', $nav[0]) as $link) {
                $query = query_of($link);
                same($pretty ? '/blog/admin/posts' : '/blog/index.php', parse_url($link->getAttribute('href'), PHP_URL_PATH), 'Article pagination must preserve pretty and plain route paths.');
                same($pretty ? null : 'admin_posts', $query['a'] ?? null, 'Plain article pagination must preserve the action route.');
                same(true, isset($query['p']) && (int)$query['p'] >= 1 && (int)$query['p'] <= 3 && (int)$query['p'] !== $page, 'Pagination links must target another valid page.');
            }
        }
    }
};

$tests['large lists use a bounded numbered pager and deletion clamps the last page'] = static function (): void {
    foreach ([1, 12] as $count) {
        fixture($count);
        $xpath = capture();
        same('1', trim($xpath->query('//nav[contains(@class,"admin-pagination")]//*[@aria-current="page"]')[0]->textContent), 'A single populated page must still display its current page number.');
        same(true, str_contains($GLOBALS['post_html'], '第 1 / 1 页'), 'Single-page lists must show their current and total page count.');
        same(0, $xpath->query('//nav[contains(@class,"admin-pagination")]//a')->length, 'Single-page lists must not offer unavailable navigation.');
    }
    fixture(1200);
    foreach ([1, 2, 50, 99, 100] as $page) {
        $xpath = capture(['p' => (string)$page]);
        same(12, count(row_ids($xpath)), 'Even large inventories must load just twelve visible articles.');
        same([12], $GLOBALS['post_fetched_rows'], 'The query must not materialize the large inventory.');
        $nav = $xpath->query('//nav[contains(@class,"admin-pagination")]')[0];
        same(true, $xpath->query('.//a', $nav)->length <= 9, 'The numbered pager must remain bounded as the inventory grows.');
        $numbers = [];
        foreach ($xpath->query('.//a|.//*[@aria-current="page"]', $nav) as $control) {
            $label = trim($control->textContent);
            if (preg_match('/^[0-9]+$/D', $label) === 1) { $numbers[] = (int)$label; }
        }
        same(true, in_array(1, $numbers, true) && in_array(100, $numbers, true), 'Large pagers must expose their first and last pages.');
        same(true, in_array($page, $numbers, true), 'The current page must remain a visible number in a large pager.');
        foreach ([$page - 1, $page + 1] as $neighbor) {
            if ($neighbor >= 1 && $neighbor <= 100) { same(true, in_array($neighbor, $numbers, true), 'The numbered pager must expose neighboring pages.'); }
        }
    }
    $ordered = fixture(25);
    db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$ordered[24]['id']]);
    $xpath = capture(['p' => '3']);
    same(array_column(array_slice($ordered, 12, 12), 'id'), row_ids($xpath), 'Deleting the final entry on the last page must render the preceding page.');
    foreach ($xpath->query('//tbody//input[@name="return_page"]') as $input) { same('2', $input->getAttribute('value'), 'Subsequent actions must use the new last page after deletion.'); }
    fixture(0);
    $xpath = capture(['p' => '9999999999999999999']);
    same([], row_ids($xpath), 'An empty database must retain the existing empty state.');
    same(0, $xpath->query('//form[@method="post"]')->length, 'An empty article inventory must expose no article write actions.');
};

try {
    foreach ($tests as $name => $test) { $test(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
    fwrite(STDOUT, 'PASS ' . count($tests) . ' article pagination scenarios, ' . $GLOBALS['post_assertions'] . " assertions.\n");
} catch (Throwable $exception) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    fwrite(STDERR, $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    exit(1);
} finally {
    restore_error_handler();
    $GLOBALS['post_database'] = null;
}
