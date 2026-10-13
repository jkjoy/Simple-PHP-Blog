<?php

declare(strict_types=1);

// Run: php tests/slugs.php [--compare-source /path/to/index-before.php]
// Actual core functions run against an in-memory database; the installed site is never loaded.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function slug_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function slug_load(string $file, array $names, string $prefix = ''): void
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
    slug_assert($needed === [], 'Missing core functions: ' . implode(', ', array_keys($needed)));
}

function q(string $sql, array $params = []): PDOStatement
{
    $GLOBALS['slug_queries'][] = [$sql, $params];
    $statement = $GLOBALS['slug_db']->prepare($sql);
    $statement->execute($params);
    return $statement;
}
function one(string $sql, array $params = []): ?array { return q($sql, $params)->fetch() ?: null; }
function all_rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }

slug_load(dirname(__DIR__) . '/index.php', ['str_lower_u', 'slugify', 'unique_content_slug', 'unique_slug', 'unique_category_slug']);
$baseline = ($argv[1] ?? '') === '--compare-source' ? ($argv[2] ?? '') : '';
if ($baseline !== '') { slug_load($baseline, ['unique_slug', 'unique_category_slug'], 'baseline_'); }

function slug_fixture(array $posts = [], array $categories = []): void
{
    $database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    foreach (['posts' => $posts, 'categories' => $categories] as $table => $slugs) {
        $database->exec('CREATE TABLE ' . $table . '(id INTEGER PRIMARY KEY, slug TEXT NOT NULL UNIQUE)');
        $insert = $database->prepare('INSERT INTO ' . $table . '(id,slug) VALUES(?,?)');
        foreach ($slugs as $id => $slug) { $insert->execute([$id, $slug]); }
    }
    $GLOBALS['slug_db'] = $database;
    $GLOBALS['slug_queries'] = [];
}

function slug_check(string $function, string $seed, ?int $exclude, string $expected, int $queries): void
{
    $GLOBALS['slug_queries'] = [];
    $actual = $function($seed, $exclude);
    slug_assert($actual === $expected, $function . ': expected ' . $expected . ', got ' . $actual);
    slug_assert(count($GLOBALS['slug_queries']) === $queries, $function . ': unexpected query count');
    if ($GLOBALS['baseline'] !== '') {
        $before = ('baseline_' . $function)($seed, $exclude);
        slug_assert($before === $actual, $function . ': allocation changed from the original implementation');
    }
}

$tests = [
    'free base keeps a single indexed lookup even when suffixes exist' => static function (): void {
        slug_fixture([1 => 'hello-2', 2 => 'hello-3']);
        slug_check('unique_slug', ' Hello! ', null, 'hello', 1);
    },
    'empty or punctuation seeds keep the post fallback' => static function (): void {
        slug_fixture();
        slug_check('unique_slug', '!!!', null, 'post', 1);
        slug_check('unique_category_slug', '', null, 'post', 1);
    },
    'one hundred occupied suffixes require two queries' => static function (): void {
        $posts = [1 => 'same'];
        for ($i = 2; $i <= 100; $i++) { $posts[$i] = 'same-' . $i; }
        slug_fixture($posts);
        slug_check('unique_slug', 'same', null, 'same-101', 2);
    },
    'allocation fills gaps instead of taking the largest suffix' => static function (): void {
        slug_fixture([1 => 'gap', 2 => 'gap-2', 3 => 'gap-4', 4 => 'gap-500']);
        slug_check('unique_slug', 'gap', null, 'gap-3', 2);
    },
    'category and post namespaces remain independent' => static function (): void {
        slug_fixture([1 => 'same', 2 => 'same-2'], [1 => 'same']);
        slug_check('unique_slug', 'same', null, 'same-3', 2);
        slug_check('unique_category_slug', 'same', null, 'same-2', 2);
    },
    'editing retains the base or reuses its excluded suffix' => static function (): void {
        slug_fixture([1 => 'edit', 2 => 'edit-2', 3 => 'edit-3'], [1 => 'edit', 2 => 'edit-2']);
        slug_check('unique_slug', 'edit', 1, 'edit', 1);
        slug_check('unique_slug', 'edit', 2, 'edit-2', 2);
        slug_check('unique_category_slug', 'edit', 2, 'edit-2', 2);
    },
    'Unicode seeds and suffixes use the same exact comparisons' => static function (): void {
        slug_fixture([1 => '优化文章', 2 => '优化文章-2'], [1 => '分类-名称']);
        slug_check('unique_slug', ' 优化文章 ', null, '优化文章-3', 2);
        slug_check('unique_category_slug', '分类 名称', null, '分类-名称-2', 2);
    },
    'similarly named and noncanonical suffixes do not occupy numeric slots' => static function (): void {
        slug_fixture([1 => 'draft', 2 => 'draft-02', 3 => 'draft-1', 4 => 'draft-extra', 5 => 'drafty-2', 6 => 'draft-٢']);
        slug_check('unique_slug', 'draft', null, 'draft-2', 2);
    },
    'a numbered seed has its own suffix namespace' => static function (): void {
        slug_fixture([1 => 'post-2', 2 => 'post-2-2', 3 => 'post-3']);
        slug_check('unique_slug', 'post-2', null, 'post-2-3', 2);
    },
    'zero excludes no row and negative IDs retain original behavior' => static function (): void {
        slug_fixture([0 => 'zero', -1 => 'negative', 1 => 'zero-2']);
        slug_check('unique_slug', 'zero', 0, 'zero-3', 2);
        slug_check('unique_slug', 'negative', -1, 'negative', 1);
    },
    'allocations observe writes and deletes in the same request' => static function (): void {
        slug_fixture();
        slug_check('unique_slug', 'live', null, 'live', 1);
        $GLOBALS['slug_db']->exec("INSERT INTO posts VALUES(1,'live')");
        slug_check('unique_slug', 'live', null, 'live-2', 2);
        $GLOBALS['slug_db']->exec('DELETE FROM posts');
        slug_check('unique_slug', 'live', null, 'live', 1);
    },
];

try {
    foreach ($tests as $name => $test) { $test(); fwrite(STDOUT, 'PASS ' . $name . "\n"); }
    fwrite(STDOUT, count($tests) . " slug tests, 0 failures.\n");
    if ($baseline !== '') { fwrite(STDOUT, "PASS All allocations match the original core functions.\n"); }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
