<?php

declare(strict_types=1);

// CLI-only regression checks use actual core functions and isolated temporary data.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function runtime_assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function runtime_same(mixed $expected, mixed $actual, string $message): void
{
    runtime_assert($expected === $actual, $message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
}

function runtime_functions(): array
{
    $tokens = token_get_all((string)file_get_contents(dirname(__DIR__) . '/index.php'));
    $functions = [];
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
        $j = $i + 1;
        while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
        if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_STRING) { continue; }
        $name = $tokens[$j][1];
        $code = '';
        $depth = 0;
        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; }
            elseif ($token === '}' && --$depth === 0) { break; }
        }
        $functions[$name] = $code;
    }
    return $functions;
}

class RuntimeDatabase extends PDO
{
    public array $queries = [];
    public function __construct() { parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $this->queries[] = $query;
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$args);
    }
    public function exec(string $statement): int|false { $this->queries[] = $statement; return parent::exec($statement); }
    public function prepare(string $query, array $options = []): PDOStatement|false { $this->queries[] = $query; return parent::prepare($query, $options); }
}

function db(): PDO { return $GLOBALS['runtime_db']; }
function asset_url(string $path): string { return '/blog/' . $path; }
function sblog_t(string $message, array $parameters = []): string
{
    foreach ($parameters as $key => $value) { $message = str_replace('{' . $key . '}', (string)$value, $message); }
    return $message;
}

function runtime_next_request(): void
{
    eval(str_replace('function ensure_schema(', 'function runtime_new_request_schema(', $GLOBALS['runtime_functions']['ensure_schema']));
    runtime_new_request_schema(db());
}

$tests = [];
$tests['fresh database creates complete schema and records successful maintenance'] = static function (): void {
    ensure_schema(db());
    runtime_same('1', val("SELECT value FROM settings WHERE name = 'database_maintenance_version'"), 'Maintenance completion must be persisted.');
    runtime_same(14, (int)val("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name <> 'sqlite_sequence'"), 'All core tables must exist.');
    runtime_assert(isset(table_columns(db(), 'posts')['content_password_hash']), 'Password protection must remain supported.');
    runtime_same(1, (int)val("SELECT COUNT(*) FROM categories WHERE slug = 'default'"), 'Default category must be created once.');
};
$tests['completed databases execute one read and never rewrite legitimate pages'] = static function (): void {
    ensure_schema(db());
    q("INSERT INTO posts(slug,title,content,kind,status,post_format,created_at,updated_at) VALUES('about','About','User content','page','published','text',1,1)");
    db()->exec('CREATE TABLE page_updates(id INTEGER)');
    db()->exec('CREATE TRIGGER record_page_updates AFTER UPDATE ON posts BEGIN INSERT INTO page_updates VALUES(new.id); END');
    $before = (int)val('SELECT total_changes()');
    db()->queries = [];
    runtime_next_request();
    runtime_same(1, count(db()->queries), 'A new normal request must only read the maintenance version.');
    runtime_same($before, (int)val('SELECT total_changes()'), 'No database writes are allowed for completed maintenance.');
    runtime_same(0, (int)val('SELECT COUNT(*) FROM page_updates'), 'Valid page rows must never be updated again.');
};
$tests['old databases marked schema one still receive every compatibility repair'] = static function (): void {
    db()->exec("CREATE TABLE settings(name TEXT PRIMARY KEY,value TEXT NOT NULL); INSERT INTO settings VALUES('database_schema_version','1')");
    db()->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,password_hash TEXT,created_at INTEGER); INSERT INTO users VALUES(7,'admin','hash',1)");
    db()->exec("CREATE TABLE posts(id INTEGER PRIMARY KEY,slug TEXT UNIQUE,title TEXT,content TEXT,status TEXT,published_at INTEGER,created_at INTEGER,updated_at INTEGER); INSERT INTO posts VALUES(3,'legacy','Legacy','![photo](image.png)','published',1,1,1)");
    ensure_schema(db());
    $post = one('SELECT * FROM posts WHERE id=3');
    runtime_same('image', $post['post_format'], 'Legacy image format detection must remain intact.');
    runtime_same(7, $post['author_id'], 'Missing author must be repaired.');
    runtime_assert($post['category_id'] > 0, 'Missing category must be repaired.');
    runtime_same('[]', $post['tags'], 'Missing tags must have their compatible default.');
    runtime_same('1', val("SELECT value FROM settings WHERE name='database_schema_version'"), 'Compatibility maintenance must not bypass explicit database upgrade tracking.');
    runtime_assert(isset(table_columns(db(), 'users')['github_url'], table_columns(db(), 'comments')['reply_notified_at']), 'Historical user and comment fields must be added.');
};
$tests['failed maintenance rolls back schema repairs and never records success'] = static function (): void {
    db()->exec('CREATE TABLE settings(name TEXT PRIMARY KEY,value TEXT NOT NULL)');
    db()->exec("CREATE TRIGGER reject_marker BEFORE INSERT ON settings WHEN new.name='database_maintenance_version' BEGIN SELECT RAISE(ABORT,'fixture failure'); END");
    $failed = false;
    try { ensure_schema(db()); } catch (PDOException) { $failed = true; }
    runtime_assert($failed, 'The fixture must interrupt maintenance.');
    runtime_same(false, val("SELECT value FROM settings WHERE name='database_maintenance_version'"), 'Failed work must not be marked complete.');
    runtime_same(0, (int)val("SELECT COUNT(*) FROM sqlite_master WHERE name='posts'"), 'DDL must roll back with failed maintenance.');
    db()->exec('DROP TRIGGER reject_marker');
    ensure_schema(db());
    runtime_same('1', val("SELECT value FROM settings WHERE name='database_maintenance_version'"), 'Maintenance must be retryable after failure.');
};
$tests['caller transactions retain ownership and rollback does not poison maintenance cache'] = static function (): void {
    db()->exec('BEGIN IMMEDIATE');
    ensure_schema(db());
    runtime_assert(db()->inTransaction(), 'Maintenance must leave a caller transaction open.');
    db()->exec('ROLLBACK');
    runtime_same(0, (int)val("SELECT COUNT(*) FROM sqlite_master WHERE name='settings'"), 'Caller rollback must undo maintenance and its marker.');
    ensure_schema(db());
    runtime_same('1', val("SELECT value FROM settings WHERE name='database_maintenance_version'"), 'A rolled back caller transaction must allow a retry.');
};
$tests['higher stored maintenance revisions skip legacy repairs'] = static function (): void {
    db()->exec("CREATE TABLE settings(name TEXT PRIMARY KEY,value TEXT NOT NULL); INSERT INTO settings VALUES('database_maintenance_version','2')");
    db()->queries = [];
    ensure_schema(db());
    runtime_same(1, count(db()->queries), 'An older runtime must not rerun newer maintenance.');
};
$tests['dashboard aggregation preserves every metric and uses four total queries'] = static function (): void {
    ensure_schema(db());
    $now = time();
    foreach ([['post','published',$now-60,10],['page','published',$now-60,20],['post','draft',0,100],['post','published',$now+3600,30]] as $i => [$kind,$status,$published,$views]) {
        q('INSERT INTO posts(slug,title,content,kind,status,published_at,views,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', ['p'.$i,'Post '.$i,'Content',$kind,$status,$published,$views,1,1]);
    }
    q("INSERT INTO comments(post_id,author_name,author_email,content,status,created_at,updated_at) VALUES(1,'Visitor','v@example.com','Comment','pending',1,1)");
    db()->queries = [];
    $metrics = admin_metrics();
    runtime_same(4, count(db()->queries), 'Aggregate, categories, comment counts and top viewed must be the only queries.');
    foreach (['total_posts'=>1,'published'=>1,'pages'=>1,'drafts'=>1,'scheduled'=>1,'categories'=>1,'total_views'=>30,'avg_views'=>30,'comments'=>1,'pending_comments'=>1] as $key => $value) { runtime_same($value, $metrics[$key], 'Metric must remain compatible: '.$key); }
    runtime_same('p0', $metrics['top_viewed'][0]['slug'], 'Top viewed retains its API and excludes pages/future posts.');
};
$tests['empty dashboard retains zero totals and no division errors'] = static function (): void {
    ensure_schema(db());
    $metrics = admin_metrics();
    runtime_same(0, $metrics['published'], 'Empty post count must remain zero.');
    runtime_same(0, $metrics['avg_views'], 'Empty average must remain zero.');
    runtime_same([], $metrics['top_viewed'], 'Empty ranked posts must stay empty.');
};
$tests['login and password reset limits remain independent and retain expiration and reset'] = static function (): void {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    runtime_same(0, login_rate_state()['count'], 'Initial login counter must be zero.');
    runtime_same(1, login_rate_state(true)['count'], 'Failed login increments its counter.');
    runtime_same(1, password_reset_rate_state(true)['count'], 'Password reset uses its own counter.');
    runtime_same(1, login_rate_state()['count'], 'Read must not increment login failures.');
    runtime_same(0, login_rate_state(false,true)['count'], 'Successful login resets only its counter.');
    runtime_same(1, password_reset_rate_state()['count'], 'Resetting login must leave reset attempts unchanged.');
    file_put_contents(password_reset_rate_file(), json_encode(['count'=>3,'since'=>time()-901]));
    runtime_same(1, password_reset_rate_state(true)['count'], 'Expired attempts start a new window.');
    file_put_contents(login_rate_file(), 'malformed');
    runtime_same(0, login_rate_state()['count'], 'Malformed state must recover as before.');
};
$tests['shared extension options preserve actual redirects, size limits and HTTP errors'] = static function (): void {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    runtime_assert(is_resource($socket), 'A local fixture port must be available.');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $router = CACHE_DIR . '/http.php';
    file_put_contents($router, "<?php if (\$_SERVER['REQUEST_URI']==='/redirect') { header('Location: /ok'); exit; } if (\$_SERVER['REQUEST_URI']==='/missing') { http_response_code(404); } echo 'fixture-body';");
    $server = proc_open([PHP_BINARY,'-S',$address,$router], [0=>['pipe','r'],1=>['file',CACHE_DIR.'/server.log','a'],2=>['file',CACHE_DIR.'/server.log','a']], $pipes);
    runtime_assert(is_resource($server), 'Local HTTP fixture must start.');
    fclose($pipes[0]);
    try {
        $ready = false;
        for ($i=0;$i<50;$i++) { $probe = @stream_socket_client('tcp://'.$address,$errno,$error,0.1); if ($probe) { fclose($probe); $ready=true; break; } usleep(100000); }
        runtime_assert($ready, 'Local fixture must become ready.');
        $url='http://'.$address;
        runtime_same('fixture-body', extension_remote_get($url.'/redirect',64,3), 'Redirected bounded memory requests must succeed.');
        $target=CACHE_DIR.'/download.zip';
        extension_remote_download($url.'/ok',$target,64,3);
        runtime_same('fixture-body', file_get_contents($target), 'Streamed downloads must preserve bytes.');
        foreach ([static fn()=>extension_remote_get($url.'/ok',2,3),static fn()=>extension_remote_download($url.'/ok',$target,2,3),static fn()=>extension_remote_get($url.'/missing',64,3),static fn()=>extension_remote_download('file:///private',$target,64,3)] as $operation) {
            $rejected=false; try { $operation(); } catch (RuntimeException) { $rejected=true; }
            runtime_assert($rejected, 'Oversized, failed and unsafe transfers must be rejected.');
        }
    } finally { proc_terminate($server); proc_close($server); }
};

if (($argv[1] ?? '') !== '--case') {
    $failures = 0;
    foreach (array_keys($tests) as $i=>$name) {
        $child = proc_open([PHP_BINARY,__FILE__,'--case',(string)$i], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($child)) { throw new RuntimeException('Cannot launch isolated test.'); }
        $output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $status=proc_close($child); if ($status!==0) { $failures++; }
        fwrite($status===0 ? STDOUT : STDERR, ($status===0 ? 'PASS ' : 'FAIL ').$name."\n".$output.$errors);
    }
    fwrite(STDOUT,sprintf("%d runtime tests, %d failures.\n",count($tests),$failures));
    exit($failures===0 ? 0 : 1);
}

$runtimeDirectory = sys_get_temp_dir().'/sblog-runtime-tests-'.bin2hex(random_bytes(8));
mkdir($runtimeDirectory,0700);
foreach (['DATA_DIR'=>'data','CACHE_DIR'=>'cache','UPLOAD_DIR'=>'uploads','THEMES_DIR'=>'themes','PLUGINS_DIR'=>'plugins'] as $constant=>$subdirectory) { define($constant,$runtimeDirectory.'/'.$subdirectory); }
define('DATABASE_MAINTENANCE_VERSION',1); define('APP_VERSION','test');
$GLOBALS['runtime_db']=new RuntimeDatabase();
$GLOBALS['runtime_functions']=runtime_functions();
foreach (['ensure_schema','migrate_legacy_schema','table_columns','content_contains_image','import_legacy_local_media','ensure_comment_columns','ensure_runtime_dirs','q','one','all_rows','val','admin_metrics','comment_admin_counts','authentication_rate_state','login_rate_file','login_rate_state','password_reset_rate_file','password_reset_rate_state','curl_trust_options','extension_remote_url_is_safe','extension_remote_options','extension_remote_get','extension_remote_download'] as $name) { eval($GLOBALS['runtime_functions'][$name]); }
ensure_runtime_dirs();
try { array_values($tests)[(int)$argv[2]](); $exitCode=0; }
catch (Throwable $exception) { fwrite(STDERR,$exception->getMessage()."\n".$exception->getTraceAsString()."\n"); $exitCode=1; }
finally {
    $GLOBALS['runtime_db']=null;
    $resolvedRoot=realpath($runtimeDirectory);
    runtime_assert($resolvedRoot!==false && str_starts_with($resolvedRoot,realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR), 'Cleanup must remain in the OS temporary directory.');
    $entries=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolvedRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) { if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); } else { unlink($entry->getPathname()); } }
    rmdir($resolvedRoot);
}
exit($exitCode);
