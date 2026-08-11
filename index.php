<?php

declare(strict_types=1);

const ADMIN_SESSION_IDLE_TIMEOUT = 43200;
const ADMIN_SESSION_ABSOLUTE_TIMEOUT = 86400;
const ADMIN_PRESENCE_TIMEOUT = 300;

$sessionSecure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
$sessionScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$sessionCookiePath = rtrim(str_replace('\\', '/', dirname($sessionScript)), '/');
$sessionCookiePath = $sessionCookiePath === '' || $sessionCookiePath === '.' ? '/' : $sessionCookiePath . '/';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.gc_maxlifetime', (string)ADMIN_SESSION_ABSOLUTE_TIMEOUT);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $sessionCookiePath,
    'secure' => $sessionSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

const APP_VERSION = 'v1.7.0';
const DATA_DIR = __DIR__ . '/data';
const CACHE_DIR = __DIR__ . '/cache';
const ADMIN_PRESENCE_FILE = CACHE_DIR . '/admin-presence.json';
const UPLOAD_DIR = __DIR__ . '/uploads';
const THEMES_DIR = __DIR__ . '/themes';
const PLUGINS_DIR = __DIR__ . '/plugins';
const DB_CONFIG_FILE = DATA_DIR . '/config.php';
const INSTALL_LOCK_FILE = DATA_DIR . '/install.lock';
const SETTINGS_CACHE_FILE = CACHE_DIR . '/settings.php';
const UPDATE_REPOSITORY = 'jkjoy/Simple-PHP-Blog';
const UPDATE_CACHE_FILE = CACHE_DIR . '/github-update.json';
const BUNDLED_RELEASE_FILES = [
    'themes/hammeros/theme.json',
    'themes/liquid-glass/theme.json',
    'themes/nebula/theme.json',
    'themes/starter/theme.json',
    'themes/ying/theme.json',
    'plugins/ai-assistant/plugin.json',
    'plugins/ai-assistant/plugin.php',
    'plugins/email-notifications/plugin.json',
    'plugins/email-notifications/plugin.php',
    'plugins/english-language/plugin.json',
    'plugins/english-language/plugin.php',
    'plugins/russian-language/plugin.json',
    'plugins/russian-language/plugin.php',
    'plugins/s3-storage/plugin.json',
    'plugins/s3-storage/plugin.php',
];

function db_file_path(): string
{
    if (is_file(DB_CONFIG_FILE)) {
        $config = include DB_CONFIG_FILE;
        $name = is_array($config) ? basename((string)($config['db_file'] ?? '')) : '';
        if ($name !== '' && $name !== 'blog.sqlite' && preg_match('/^blog-[a-f0-9]{16}\.sqlite$/', $name)) {
            return DATA_DIR . '/' . $name;
        }
    }

    return '';
}

define('DB_FILE', db_file_path());

function is_installed(): bool
{
    return is_file(INSTALL_LOCK_FILE) && DB_FILE !== '' && is_file(DB_FILE);
}

function ensure_runtime_dirs(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }

    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0755, true);
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
}

function redirect_to(string $url, int $status = 302): void
{
    header('Location: ' . $url, true, $status);
    exit;
}

function h(string|int|float|bool|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function x(string|int|float|bool|null $value): string
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function str_len_u(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function str_sub_u(string $text, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($text, $start, null, 'UTF-8') : mb_substr($text, $start, $length, 'UTF-8');
    }

    return $length === null ? substr($text, $start) : substr($text, $start, $length);
}

function str_lower_u(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function is_ascii_digits(string $value): bool
{
    return $value !== '' && preg_match('/^[0-9]+$/D', $value) === 1;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function table_columns(PDO $pdo, string $table): array
{
    $table = preg_replace('/[^a-z_]/i', '', $table) ?: $table;
    $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    $columns = [];

    foreach ($rows as $row) {
        $columns[(string)$row['name']] = true;
    }

    return $columns;
}

function import_legacy_local_media(PDO $pdo): void
{
    $migration = $pdo->prepare('SELECT value FROM settings WHERE name = ?');
    $migration->execute(['media_library_local_imported']);
    if ($migration->fetchColumn() !== false) {
        return;
    }

    if (is_dir(UPLOAD_DIR)) {
        $insert = $pdo->prepare(
            'INSERT OR IGNORE INTO media(original_name, title, alt_text, caption, url, storage_driver, storage_key, local_path, mime_type, file_size, is_image, width, height, created_at, updated_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS)
        );
        $uploadsRoot = rtrim(str_replace('\\', '/', (string)realpath(UPLOAD_DIR)), '/') . '/';
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
                continue;
            }
            $realPath = realpath($file->getPathname());
            $normalized = $realPath !== false ? str_replace('\\', '/', $realPath) : '';
            if ($normalized === '' || !str_starts_with($normalized, $uploadsRoot)) {
                continue;
            }
            $relativePath = substr($normalized, strlen($uploadsRoot));
            if ($relativePath === '' || str_starts_with(basename($relativePath), '.')) {
                continue;
            }
            $imageInfo = @getimagesize($realPath);
            $isImage = is_array($imageInfo);
            $name = basename($relativePath);
            $title = trim(pathinfo($name, PATHINFO_FILENAME)) ?: $name;
            $timestamp = max(1, (int)($file->getMTime() ?: time()));
            $mime = $finfo->file($realPath) ?: 'application/octet-stream';
            $insert->execute([
                $name, $title, '', '', asset_url('uploads/' . str_replace('%2F', '/', rawurlencode($relativePath))),
                'local', '', $relativePath, $mime, max(0, (int)$file->getSize()), $isImage ? 1 : 0,
                $isImage ? (int)($imageInfo[0] ?? 0) : 0, $isImage ? (int)($imageInfo[1] ?? 0) : 0,
                $timestamp, $timestamp,
            ]);
        }
    }

    $pdo->prepare('INSERT OR REPLACE INTO settings(name, value) VALUES(?, ?)')
        ->execute(['media_library_local_imported', '1']);
}

function ensure_comment_columns(PDO $pdo): void
{
    $columns = table_columns($pdo, 'comments');
    if (isset($columns['parent_id'], $columns['reply_to_name'], $columns['user_id'], $columns['ip_address'], $columns['reply_notified_at'])) {
        return;
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->exec('BEGIN IMMEDIATE');
    }

    try {
        $columns = table_columns($pdo, 'comments');
        if (!isset($columns['parent_id'])) { $pdo->exec('ALTER TABLE comments ADD COLUMN parent_id INTEGER REFERENCES comments(id) ON DELETE SET NULL'); }
        if (!isset($columns['reply_to_name'])) { $pdo->exec("ALTER TABLE comments ADD COLUMN reply_to_name TEXT NOT NULL DEFAULT ''"); }
        if (!isset($columns['user_id'])) { $pdo->exec('ALTER TABLE comments ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE SET NULL'); }
        if (!isset($columns['ip_address'])) { $pdo->exec("ALTER TABLE comments ADD COLUMN ip_address TEXT NOT NULL DEFAULT ''"); }
        if (!isset($columns['reply_notified_at'])) { $pdo->exec('ALTER TABLE comments ADD COLUMN reply_notified_at INTEGER NOT NULL DEFAULT 0'); }
        if ($ownsTransaction) {
            $pdo->exec('COMMIT');
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction) {
            try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        }
        throw $exception;
    }
}

function ensure_schema(PDO $pdo): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS settings(
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ai_settings(
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS mail_settings(
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS s3_settings(
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            nickname TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            avatar_url TEXT NOT NULL DEFAULT '',
            website_url TEXT NOT NULL DEFAULT '',
            github_url TEXT NOT NULL DEFAULT '',
            qq_url TEXT NOT NULL DEFAULT '',
            wechat_url TEXT NOT NULL DEFAULT '',
            weibo_url TEXT NOT NULL DEFAULT '',
            x_url TEXT NOT NULL DEFAULT '',
            telegram_url TEXT NOT NULL DEFAULT '',
            mastodon_url TEXT NOT NULL DEFAULT '',
            bilibili_url TEXT NOT NULL DEFAULT '',
            instagram_url TEXT NOT NULL DEFAULT '',
            tiktok_url TEXT NOT NULL DEFAULT '',
            signature TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at INTEGER NOT NULL,
            used_at INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS posts(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_id INTEGER,
            category_id INTEGER,
            slug TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            excerpt TEXT NOT NULL DEFAULT '',
            content TEXT NOT NULL,
            kind TEXT NOT NULL DEFAULT 'post',
            tags TEXT NOT NULL DEFAULT '[]',
            views INTEGER NOT NULL DEFAULT 0,
            is_pinned INTEGER NOT NULL DEFAULT 0,
            allow_comments INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'draft',
            published_at INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS categories(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            description TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS links(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            url TEXT NOT NULL,
            icon_url TEXT NOT NULL DEFAULT '',
            description TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS tag_meta(
            label TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE,
            updated_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS comments(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            user_id INTEGER,
            parent_id INTEGER,
            reply_to_name TEXT NOT NULL DEFAULT '',
            author_name TEXT NOT NULL,
            author_email TEXT NOT NULL,
            author_url TEXT NOT NULL DEFAULT '',
            content TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            is_read INTEGER NOT NULL DEFAULT 0,
            ip_hash TEXT NOT NULL DEFAULT '',
            ip_address TEXT NOT NULL DEFAULT '',
            user_agent TEXT NOT NULL DEFAULT '',
            reply_notified_at INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY(parent_id) REFERENCES comments(id) ON DELETE SET NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS post_views(
            post_id INTEGER NOT NULL,
            ip_hash TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            PRIMARY KEY(post_id, ip_hash),
            FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) WITHOUT ROWID"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS media(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            original_name TEXT NOT NULL,
            title TEXT NOT NULL DEFAULT '',
            alt_text TEXT NOT NULL DEFAULT '',
            caption TEXT NOT NULL DEFAULT '',
            url TEXT NOT NULL,
            storage_driver TEXT NOT NULL DEFAULT 'local',
            storage_key TEXT NOT NULL DEFAULT '',
            local_path TEXT NOT NULL DEFAULT '',
            mime_type TEXT NOT NULL,
            file_size INTEGER NOT NULL DEFAULT 0,
            is_image INTEGER NOT NULL DEFAULT 0,
            width INTEGER NOT NULL DEFAULT 0,
            height INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )"
    );

    $columns = table_columns($pdo, 'posts');
    $userColumns = table_columns($pdo, 'users');

    foreach (['nickname', 'email', 'avatar_url', 'website_url', 'github_url', 'qq_url', 'wechat_url', 'weibo_url', 'x_url', 'telegram_url', 'mastodon_url', 'bilibili_url', 'instagram_url', 'tiktok_url', 'signature'] as $column) {
        if (!isset($userColumns[$column])) { $pdo->exec("ALTER TABLE users ADD COLUMN {$column} TEXT NOT NULL DEFAULT ''"); }
    }
    if (isset($userColumns['social_links'])) { $pdo->exec('ALTER TABLE users DROP COLUMN social_links'); }

    $linkColumns = table_columns($pdo, 'links');
    if (!isset($linkColumns['icon_url'])) { $pdo->exec("ALTER TABLE links ADD COLUMN icon_url TEXT NOT NULL DEFAULT ''"); }

    ensure_comment_columns($pdo);

    if (!isset($columns['author_id'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN author_id INTEGER");
    }

    if (!isset($columns['category_id'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN category_id INTEGER");
    }

    if (!isset($columns['kind'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN kind TEXT NOT NULL DEFAULT 'post'");
    }

    if (!isset($columns['tags'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN tags TEXT NOT NULL DEFAULT '[]'");
    }

    if (!isset($columns['views'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN views INTEGER NOT NULL DEFAULT 0");
    }
    if (!isset($columns['is_pinned'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0");
    }

    if (!isset($columns['allow_comments'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN allow_comments INTEGER NOT NULL DEFAULT 0");
    }

    $pdo->exec("UPDATE posts SET kind = 'post' WHERE kind IS NULL OR trim(kind) = ''");
    $pdo->exec("UPDATE posts SET tags = '[]' WHERE tags IS NULL OR trim(tags) = ''");
    $pdo->exec("UPDATE posts SET views = 0 WHERE views IS NULL");
    $pdo->exec("UPDATE posts SET is_pinned = 0 WHERE is_pinned IS NULL");
    $pdo->exec("UPDATE posts SET allow_comments = 0 WHERE allow_comments IS NULL");
    $defaultAuthorId = (int)($pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
    if ($defaultAuthorId > 0) {
        $pdo->prepare('UPDATE posts SET author_id = ? WHERE author_id IS NULL OR author_id NOT IN (SELECT id FROM users)')->execute([$defaultAuthorId]);
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_public_pinned ON posts(kind, status, is_pinned DESC, published_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_kind_updated ON posts(kind, updated_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category_id, kind, status, published_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_categories_sort ON categories(sort_order ASC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_links_sort ON links(sort_order ASC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_post_public ON comments(post_id, status, created_at, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_moderation ON comments(status, created_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_unread ON comments(is_read, created_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_ip_recent ON comments(ip_hash, created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_parent ON comments(parent_id, created_at, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_user_recent ON comments(user_id, created_at DESC)');
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comments_visitor_email_approval ON comments(author_email COLLATE NOCASE, status) WHERE user_id IS NULL");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_media_created ON media(created_at DESC, id DESC)');
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_media_local_path ON media(local_path) WHERE local_path <> ''");

    import_legacy_local_media($pdo);

    $defaultCategoryId = (int)($pdo->query("SELECT id FROM categories WHERE slug = 'default' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
    if ($defaultCategoryId < 1) {
        $now = time();
        $statement = $pdo->prepare('INSERT INTO categories(name, slug, description, sort_order, created_at, updated_at) VALUES(?,?,?,?,?,?)');
        $statement->execute(['默认分类', 'default', '系统默认文章分类。', 0, $now, $now]);
        $defaultCategoryId = (int)$pdo->lastInsertId();
    }
    $statement = $pdo->prepare("UPDATE posts SET category_id = ? WHERE kind = 'post' AND (category_id IS NULL OR category_id NOT IN (SELECT id FROM categories))");
    $statement->execute([$defaultCategoryId]);

    $pluginMigration = $pdo->prepare('SELECT value FROM settings WHERE name = ?');
    $pluginMigration->execute(['core_feature_plugins_migrated']);
    if ($pluginMigration->fetchColumn() === false) {
        $activeStatement = $pdo->prepare('SELECT value FROM settings WHERE name = ?');
        $activeStatement->execute(['active_plugins']);
        $configured = json_decode((string)($activeStatement->fetchColumn() ?: '[]'), true);
        $configured = is_array($configured) ? array_map('strval', $configured) : [];
        foreach (['ai-assistant', 'email-notifications', 's3-storage'] as $pluginSlug) {
            if (!in_array($pluginSlug, $configured, true)) {
                $configured[] = $pluginSlug;
            }
        }
        $savePluginSetting = $pdo->prepare('INSERT OR REPLACE INTO settings(name, value) VALUES(?, ?)');
        $savePluginSetting->execute(['active_plugins', json_encode($configured, JSON_UNESCAPED_SLASHES)]);
        $savePluginSetting->execute(['core_feature_plugins_migrated', '1']);
    }
    $done = true;
}

function db(): PDO
{
    static $db;

    if ($db instanceof PDO) {
        ensure_schema($db);
        return $db;
    }

    if (DB_FILE === '') { throw new RuntimeException(sblog_t('博客尚未安装或数据库配置无效。')); }
    ensure_runtime_dirs();

    $db = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    foreach (
        [
            'PRAGMA journal_mode=WAL',
            'PRAGMA synchronous=NORMAL',
            'PRAGMA temp_store=MEMORY',
            'PRAGMA busy_timeout=5000',
            'PRAGMA foreign_keys=ON',
        ] as $sql
    ) {
        $db->exec($sql);
    }

    ensure_schema($db);

    return $db;
}

function q(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row ?: null;
}

function all_rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = []): mixed
{
    return q($sql, $params)->fetchColumn();
}

function default_settings(): array
{
    return [
        'site_name' => 'Simple PHP Blog',
        'site_url' => '',
        'site_tagline' => 'A small PHP blog running on one main entry file.',
        'site_description' => 'A simple PHP + SQLite blog inspired by Hugo Paper.',
        'site_keywords' => '',
        'site_footer' => '',
        'custom_head_code' => '',
        'active_theme' => 'nebula',
        'active_plugins' => '["ai-assistant","email-notifications","s3-storage"]',
        'favicon_url' => 'favicon.png',
        'footer_beian' => '',
        'posts_per_page' => '6',
        'pretty_url' => '0',
        'comments_enabled' => '1',
        'comments_require_approval' => '1',
        'comments_notify' => '1',
    ];
}

function settings_cache(bool $refresh = false): array
{
    static $settings = null;

    if (!$refresh && is_array($settings)) {
        return $settings;
    }

    $settings = default_settings();

    if (!$refresh && is_file(SETTINGS_CACHE_FILE)) {
        $cached = include SETTINGS_CACHE_FILE;
        if (is_array($cached)) {
            return $settings = array_merge($settings, $cached);
        }
    }

    try {
        foreach (all_rows('SELECT name, value FROM settings') as $row) {
            $settings[(string)$row['name']] = (string)$row['value'];
        }

        ensure_runtime_dirs();
        file_put_contents(SETTINGS_CACHE_FILE, "<?php\nreturn " . var_export($settings, true) . ";\n", LOCK_EX);
    } catch (Throwable) {
    }

    return $settings;
}

function setting(string $key, string $default = ''): string
{
    $settings = settings_cache();
    return (string)($settings[$key] ?? $default);
}

function save_settings(array $values): void
{
    $statement = db()->prepare('INSERT OR REPLACE INTO settings(name, value) VALUES(?, ?)');

    foreach ($values as $name => $value) {
        $statement->execute([(string)$name, (string)$value]);
    }

    settings_cache(true);
}

function plugin_manifest(string $slug): ?array
{
    if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
        return null;
    }

    $pluginsRoot = realpath(PLUGINS_DIR);
    $pluginDir = realpath(PLUGINS_DIR . '/' . $slug);
    if ($pluginsRoot === false || $pluginDir === false || !is_dir($pluginDir)) {
        return null;
    }

    $rootPrefix = rtrim($pluginsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncasecmp($pluginDir . DIRECTORY_SEPARATOR, $rootPrefix, strlen($rootPrefix)) !== 0) {
        return null;
    }

    $manifestFile = $pluginDir . '/plugin.json';
    $entryFile = $pluginDir . '/plugin.php';
    if (!is_file($manifestFile) || !is_file($entryFile) || filesize($manifestFile) > 65536) {
        return null;
    }

    $manifest = json_decode((string)file_get_contents($manifestFile), true);
    $name = is_array($manifest) ? trim((string)($manifest['name'] ?? '')) : '';
    if ($name === '') {
        return null;
    }
    $settingsAction = trim((string)($manifest['settings_action'] ?? ''));
    if ($settingsAction !== '' && !preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $settingsAction)) {
        $settingsAction = '';
    }
    $url = trim((string)($manifest['url'] ?? ''));
    $urlParts = $url !== '' ? parse_url($url) : false;
    if (strlen($url) > 300 || !filter_var($url, FILTER_VALIDATE_URL) || !is_array($urlParts)
        || !in_array(str_lower_u((string)($urlParts['scheme'] ?? '')), ['http', 'https'], true)
        || trim((string)($urlParts['host'] ?? '')) === '' || isset($urlParts['user']) || isset($urlParts['pass'])) {
        $url = '';
    }
    $exclusiveGroup = trim((string)($manifest['exclusive_group'] ?? ''));
    if ($exclusiveGroup !== '' && !preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $exclusiveGroup)) {
        $exclusiveGroup = '';
    }

    return [
        'slug' => $slug,
        'name' => str_sub_u($name, 0, 100),
        'version' => str_sub_u(trim((string)($manifest['version'] ?? '')), 0, 40),
        'author' => str_sub_u(trim((string)($manifest['author'] ?? '')), 0, 100),
        'url' => $url,
        'description' => str_sub_u(trim((string)($manifest['description'] ?? '')), 0, 300),
        'settings_action' => $settingsAction,
        'exclusive_group' => $exclusiveGroup,
        'entry' => $entryFile,
    ];
}

function available_plugins(): array
{
    $plugins = [];
    if (!is_dir(PLUGINS_DIR)) {
        return $plugins;
    }

    foreach (scandir(PLUGINS_DIR) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $manifest = plugin_manifest($entry);
        if ($manifest !== null) {
            $plugins[$entry] = $manifest;
        }
    }

    uasort($plugins, static fn(array $left, array $right): int => strcasecmp((string)$left['name'], (string)$right['name']));
    return $plugins;
}

function active_plugin_slugs(bool $fresh = false): array
{
    $configuredValue = setting('active_plugins', '[]');
    if ($fresh) {
        try {
            $stored = val('SELECT value FROM settings WHERE name = ?', ['active_plugins']);
            if (is_string($stored)) {
                $configuredValue = $stored;
            }
        } catch (Throwable) {
        }
    }
    $configured = json_decode($configuredValue, true);
    if (!is_array($configured)) {
        return [];
    }

    $available = available_plugins();
    $active = [];
    foreach ($configured as $slug) {
        $slug = trim((string)$slug);
        if (isset($available[$slug]) && !in_array($slug, $active, true)) {
            $group = (string)$available[$slug]['exclusive_group'];
            if ($group !== '') {
                $active = array_values(array_filter($active, static fn(string $activeSlug): bool => (string)$available[$activeSlug]['exclusive_group'] !== $group));
            }
            $active[] = $slug;
        }
    }
    return $active;
}

function save_active_plugins(array $slugs): void
{
    $available = available_plugins();
    $valid = [];
    foreach ($slugs as $slug) {
        $slug = trim((string)$slug);
        if (isset($available[$slug]) && !in_array($slug, $valid, true)) {
            $group = (string)$available[$slug]['exclusive_group'];
            if ($group !== '') {
                $valid = array_values(array_filter($valid, static fn(string $activeSlug): bool => (string)$available[$activeSlug]['exclusive_group'] !== $group));
            }
            $valid[] = $slug;
        }
    }
    save_settings(['active_plugins' => json_encode($valid, JSON_UNESCAPED_SLASHES)]);
}

function sblog_default_translations(): array
{
    return [
        'post_navigation.previous' => '上一篇',
        'post_navigation.next' => '下一篇',
        'post_navigation.previous_label' => '上一篇：{title}',
        'post_navigation.next_label' => '下一篇：{title}',
        'plugin.ai-assistant.name' => 'AI 助手',
        'plugin.ai-assistant.description' => '为文章提供 Slug 生成、摘要生成和正文润色功能。',
        'plugin.email-notifications.name' => '邮件通知',
        'plugin.email-notifications.description' => '通过 SMTP 或 PHP mail 发送密码重置和评论通知邮件。',
        'plugin.english-language.name' => '英文语言包',
        'plugin.english-language.description' => '将博客前台、登录页面和后台管理界面翻译为英文。',
        'plugin.russian-language.name' => '俄语语言包',
        'plugin.russian-language.description' => '将博客前台、登录页面和后台管理界面翻译为俄语。',
        'plugin.s3-storage.name' => 'S3 存储',
        'plugin.s3-storage.description' => '将编辑器新上传的附件保存到 Amazon S3 或兼容的对象存储。',
        'theme.default.name' => '内置终端主题',
        'theme.default.description' => '程序自带的终端风格前台主题。',
        'theme.hammeros.name' => 'HammerOS 锤伴',
        'theme.hammeros.description' => '拟人化内容主题：瓷白机身、实体键感、系统管家与安静的阅读工作台。',
        'theme.liquid-glass.name' => 'Aqua Glass 液态玻璃',
        'theme.liquid-glass.description' => '明亮、通透的苹果风格阅读主题。支持深浅模式、响应式导航、文章封面、玻璃质感控件与完整内容页面。',
        'theme.nebula.name' => 'Nebula 星云',
        'theme.nebula.description' => '深空极光 · 玻璃拟态 · 暗色优先。星空粒子背景、渐变封面卡片、时间轴归档与标签云，支持亮暗主题切换。',
        'theme.starter.name' => 'Starter Contrast',
        'theme.starter.description' => '演示样式覆盖、head action 与 body_class filter 的入门主题。',
        'theme.ying.name' => 'Ying',
        'theme.ying.description' => '移植自 Halo Theme Ying 的白色极简内容主题，适配当前博客的文章、评论、归档、标签与友链。',
    ];
}

function sblog_i18n_register(string $locale, array $messages): void
{
    if (!preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale)) {
        throw new InvalidArgumentException('无效的语言代码：' . $locale);
    }

    $catalog = [];
    foreach ($messages as $key => $message) {
        if (!is_string($key) || $key === '' || (!is_string($message) && !is_array($message))) {
            throw new InvalidArgumentException('语言目录只能包含有效的翻译键和值。');
        }
        if (is_array($message)) {
            foreach ($message as $form => $translation) {
                if (!in_array($form, ['zero', 'one', 'two', 'few', 'many', 'other'], true) || !is_string($translation)) {
                    throw new InvalidArgumentException('复数翻译必须使用有效形式和字符串值。');
                }
            }
        }
        $catalog[$key] = $message;
    }

    $existing = $GLOBALS['sblog_i18n_catalogs'][$locale] ?? [];
    $GLOBALS['sblog_i18n_catalogs'][$locale] = array_replace(is_array($existing) ? $existing : [], $catalog);
}

function sblog_i18n_set_locale(string $locale): void
{
    if (!preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale)) {
        throw new InvalidArgumentException('无效的语言代码：' . $locale);
    }
    $GLOBALS['sblog_i18n_locale'] = $locale;
}

function sblog_i18n_locale(): string
{
    $locale = (string)($GLOBALS['sblog_i18n_locale'] ?? 'zh-CN');
    return preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale) ? $locale : 'zh-CN';
}

function sblog_i18n_message(string $key): string|array|null
{
    $catalogs = $GLOBALS['sblog_i18n_catalogs'] ?? [];
    $locale = sblog_i18n_locale();
    $locales = [$locale];
    if (str_contains($locale, '-')) {
        $locales[] = explode('-', $locale, 2)[0];
    }
    $locales[] = 'zh-CN';

    foreach (array_unique($locales) as $candidate) {
        $catalog = is_array($catalogs[$candidate] ?? null) ? $catalogs[$candidate] : [];
        if (isset($catalog[$key]) && (is_string($catalog[$key]) || is_array($catalog[$key]))) {
            return $catalog[$key];
        }
    }

    $defaults = sblog_default_translations();
    return $defaults[$key] ?? null;
}

function sblog_i18n_format(string $message, array $parameters): string
{
    if ($parameters === []) {
        return $message;
    }

    $replacements = [];
    foreach ($parameters as $name => $value) {
        if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $name)) {
            throw new InvalidArgumentException('无效的翻译参数名称。');
        }
        if ($value !== null && !is_scalar($value) && !($value instanceof Stringable)) {
            throw new InvalidArgumentException('翻译参数必须是标量、字符串对象或 null。');
        }
        $replacements['{' . $name . '}'] = $value === null ? '' : (string)$value;
    }

    return strtr($message, $replacements);
}

function sblog_t(string $key, array $parameters = []): string
{
    $message = sblog_i18n_message($key);
    if (is_array($message)) {
        $message = (string)($message['other'] ?? reset($message) ?: $key);
    }
    return sblog_i18n_format(is_string($message) ? $message : $key, $parameters);
}

function sblog_i18n_plural_form(int $count, ?string $locale = null): string
{
    $language = strtolower(explode('-', $locale ?? sblog_i18n_locale(), 2)[0]);
    if ($language === 'ru') {
        $mod10 = abs($count) % 10;
        $mod100 = abs($count) % 100;
        if ($mod10 === 1 && $mod100 !== 11) {
            return 'one';
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 'few';
        }
        return 'many';
    }
    return $count === 1 ? 'one' : 'other';
}

function sblog_tn(string $key, int $count, array $parameters = []): string
{
    $message = sblog_i18n_message($key);
    if (is_array($message)) {
        $form = sblog_i18n_plural_form($count);
        $message = (string)($message[$form] ?? $message['other'] ?? reset($message) ?: $key);
    }
    $parameters['count'] = $count;
    return sblog_i18n_format(is_string($message) ? $message : $key, $parameters);
}

function plugin_display_metadata(string $slug, array $manifest): array
{
    $translated = match ($slug) {
        'ai-assistant' => [
            'name' => sblog_t('plugin.ai-assistant.name'),
            'description' => sblog_t('plugin.ai-assistant.description'),
        ],
        'email-notifications' => [
            'name' => sblog_t('plugin.email-notifications.name'),
            'description' => sblog_t('plugin.email-notifications.description'),
        ],
        'english-language' => [
            'name' => sblog_t('plugin.english-language.name'),
            'description' => sblog_t('plugin.english-language.description'),
        ],
        'russian-language' => [
            'name' => sblog_t('plugin.russian-language.name'),
            'description' => sblog_t('plugin.russian-language.description'),
        ],
        's3-storage' => [
            'name' => sblog_t('plugin.s3-storage.name'),
            'description' => sblog_t('plugin.s3-storage.description'),
        ],
        default => null,
    };

    return $translated ?? [
        'name' => (string)($manifest['name'] ?? ''),
        'description' => (string)($manifest['description'] ?? ''),
    ];
}

function theme_display_metadata(string $slug, array $manifest): array
{
    $translated = match ($slug) {
        'default' => [
            'name' => sblog_t('theme.default.name'),
            'description' => sblog_t('theme.default.description'),
        ],
        'hammeros' => [
            'name' => sblog_t('theme.hammeros.name'),
            'description' => sblog_t('theme.hammeros.description'),
        ],
        'liquid-glass' => [
            'name' => sblog_t('theme.liquid-glass.name'),
            'description' => sblog_t('theme.liquid-glass.description'),
        ],
        'nebula' => [
            'name' => sblog_t('theme.nebula.name'),
            'description' => sblog_t('theme.nebula.description'),
        ],
        'starter' => [
            'name' => sblog_t('theme.starter.name'),
            'description' => sblog_t('theme.starter.description'),
        ],
        'ying' => [
            'name' => sblog_t('theme.ying.name'),
            'description' => sblog_t('theme.ying.description'),
        ],
        default => null,
    };

    return $translated ?? [
        'name' => (string)($manifest['name'] ?? ''),
        'description' => (string)($manifest['description'] ?? ''),
    ];
}

function sblog_i18n_register_client(string $locale, array $messages): void
{
    if (!preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale)) {
        throw new InvalidArgumentException('无效的语言代码：' . $locale);
    }
    foreach ($messages as $key => $message) {
        if (!is_string($key) || $key === '' || !is_string($message)) {
            throw new InvalidArgumentException('客户端语言目录只能包含字符串翻译键和值。');
        }
    }
    $existing = $GLOBALS['sblog_i18n_client_catalogs'][$locale] ?? [];
    $GLOBALS['sblog_i18n_client_catalogs'][$locale] = array_replace(is_array($existing) ? $existing : [], $messages);
}

function sblog_i18n_client_messages(): array
{
    $catalogs = $GLOBALS['sblog_i18n_client_catalogs'] ?? [];
    $locale = sblog_i18n_locale();
    $messages = [];
    $candidates = str_contains($locale, '-') ? [explode('-', $locale, 2)[0], $locale] : [$locale];
    foreach ($candidates as $candidate) {
        if (is_array($catalogs[$candidate] ?? null)) {
            $messages = array_replace($messages, $catalogs[$candidate]);
        }
    }
    return $messages;
}

function sblog_i18n_head(): string
{
    $messages = json_encode(
        sblog_i18n_client_messages(),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if (!is_string($messages)) {
        return '';
    }
    return '<script>window.sblogI18n=Object.assign({},window.sblogI18n||{},' . $messages . ');</script>';
}

function add_plugin_action(string $hook, callable $callback, int $priority = 10): void
{
    if (!preg_match('/^[a-z][a-z0-9_.-]*$/', $hook)) {
        throw new InvalidArgumentException('无效的插件钩子名称：' . $hook);
    }
    $GLOBALS['sblog_plugin_actions'][$hook][$priority][] = $callback;
}

function add_plugin_filter(string $hook, callable $callback, int $priority = 10): void
{
    if (!preg_match('/^[a-z][a-z0-9_.-]*$/', $hook)) {
        throw new InvalidArgumentException('无效的插件过滤器名称：' . $hook);
    }
    $GLOBALS['sblog_plugin_filters'][$hook][$priority][] = $callback;
}

function plugin_callbacks(string $type, string $hook): array
{
    $groups = $GLOBALS[$type][$hook] ?? [];
    if (!is_array($groups) || $groups === []) {
        return [];
    }
    ksort($groups, SORT_NUMERIC);
    return array_merge(...array_values($groups));
}

function plugin_action(string $hook, array $context = []): void
{
    foreach (plugin_callbacks('sblog_plugin_actions', $hook) as $callback) {
        try {
            $callback($context);
        } catch (Throwable $exception) {
            error_log('Plugin action ' . $hook . ' failed: ' . $exception->getMessage());
        }
    }
}

function plugin_filter(string $hook, mixed $value, array $context = []): mixed
{
    foreach (plugin_callbacks('sblog_plugin_filters', $hook) as $callback) {
        try {
            $value = $callback($value, $context);
        } catch (Throwable $exception) {
            error_log('Plugin filter ' . $hook . ' failed: ' . $exception->getMessage());
        }
    }
    return $value;
}

function plugin_asset_url(string $slug, string $path): string
{
    if (plugin_manifest($slug) === null) {
        return '';
    }
    $path = trim(str_replace('\\', '/', $path), '/');
    $segments = $path === '' ? [] : explode('/', $path);
    if (!$segments || array_filter($segments, static fn(string $segment): bool => $segment === '' || $segment === '.' || $segment === '..')) {
        return '';
    }
    if (preg_match('/\.(?:php|json|md)$/i', (string)end($segments))) {
        return '';
    }
    return asset_url('plugins/' . rawurlencode($slug) . '/' . implode('/', array_map('rawurlencode', $segments)));
}

function load_active_plugins(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $GLOBALS['sblog_loaded_plugins'] = [];
    $GLOBALS['sblog_plugin_errors'] = [];

    foreach (active_plugin_slugs(true) as $slug) {
        $manifest = plugin_manifest($slug);
        if ($manifest === null) {
            continue;
        }
        $actionsBefore = $GLOBALS['sblog_plugin_actions'] ?? [];
        $filtersBefore = $GLOBALS['sblog_plugin_filters'] ?? [];
        $catalogsBefore = $GLOBALS['sblog_i18n_catalogs'] ?? [];
        $clientCatalogsBefore = $GLOBALS['sblog_i18n_client_catalogs'] ?? [];
        $localeBefore = $GLOBALS['sblog_i18n_locale'] ?? 'zh-CN';
        try {
            require (string)$manifest['entry'];
            $GLOBALS['sblog_loaded_plugins'][] = $slug;
        } catch (Throwable $exception) {
            $GLOBALS['sblog_plugin_actions'] = $actionsBefore;
            $GLOBALS['sblog_plugin_filters'] = $filtersBefore;
            $GLOBALS['sblog_i18n_catalogs'] = $catalogsBefore;
            $GLOBALS['sblog_i18n_client_catalogs'] = $clientCatalogsBefore;
            $GLOBALS['sblog_i18n_locale'] = $localeBefore;
            $GLOBALS['sblog_plugin_errors'][$slug] = $exception->getMessage();
            error_log('Plugin ' . $slug . ' failed: ' . $exception->getMessage());
        }
    }
}

function plugin_output_buffer(string $output): string
{
    if ($output === '') {
        return $output;
    }
    $filtered = plugin_filter('output_html', $output, [
        'action' => (string)($GLOBALS['sblog_current_action'] ?? ''),
        'content_type' => implode('; ', headers_list()),
    ]);
    return is_string($filtered) ? $filtered : $output;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $token)) {
        simple_error_page(sblog_t('请求已失效'), sblog_t('请刷新页面后重试。'), 422);
    }
}

function normalize_version(string $version): string
{
    return preg_replace('/[^0-9A-Za-z.+-]/', '', ltrim(trim($version), 'vV')) ?: '0';
}

function update_available_for(string $latest, string $current = APP_VERSION): bool
{
    $latest = normalize_version($latest);
    $current = normalize_version($current);
    return $latest !== $current && version_compare($latest, $current, '>');
}

function bundled_release_files_missing(): bool
{
    foreach (BUNDLED_RELEASE_FILES as $file) {
        if (!is_file(__DIR__ . '/' . $file)) { return true; }
    }
    return false;
}

function curl_trust_options(): array
{
    static $options = null;
    if (is_array($options)) {
        return $options;
    }

    $programFiles = rtrim((string)(getenv('ProgramFiles') ?: 'C:/Program Files'), '/\\');
    $programFilesX86 = rtrim((string)(getenv('ProgramFiles(x86)') ?: ''), '/\\');
    $candidates = [
        (string)ini_get('curl.cainfo'),
        (string)ini_get('openssl.cafile'),
        (string)(getenv('CURL_CA_BUNDLE') ?: ''),
        (string)(getenv('SSL_CERT_FILE') ?: ''),
        $programFiles . '/Git/mingw64/etc/ssl/certs/ca-bundle.crt',
        $programFiles . '/Git/usr/ssl/certs/ca-bundle.crt',
        $programFilesX86 !== '' ? $programFilesX86 . '/Git/mingw64/etc/ssl/certs/ca-bundle.crt' : '',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
    ];
    foreach (array_unique($candidates) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
            return $options = [CURLOPT_CAINFO => $candidate];
        }
    }

    return $options = [];
}

function github_update_info(bool $refresh = false): array
{
    ensure_runtime_dirs();
    if (!$refresh && is_file(UPDATE_CACHE_FILE) && time() - (int)filemtime(UPDATE_CACHE_FILE) < 21600) {
        $cached = json_decode((string)file_get_contents(UPDATE_CACHE_FILE), true);
        if (is_array($cached)) {
            $cached['current'] = APP_VERSION;
            $cached['available'] = update_available_for((string)($cached['latest'] ?? ''));
            $cached['repair'] = !$cached['available']
                && normalize_version((string)($cached['latest'] ?? '')) === normalize_version(APP_VERSION)
                && bundled_release_files_missing();
            return $cached;
        }
    }
    $result = ['available' => false, 'repair' => false, 'current' => APP_VERSION, 'latest' => '', 'download_url' => '', 'error' => ''];
    if (!function_exists('curl_init')) {
        $result['error'] = sblog_t('服务器未启用 cURL，无法检查更新。');
        return $result;
    }
    $curl = curl_init('https://api.github.com/repos/' . UPDATE_REPOSITORY . '/releases/latest');
    curl_setopt_array($curl, array_replace([
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'Simple-PHP-Blog/' . APP_VERSION,
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
    ], curl_trust_options()));
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    $release = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($release)) {
        $result['error'] = $curlError !== '' ? $curlError : sblog_t('GitHub 暂时无法访问。');
    } else {
        $latest = trim((string)($release['tag_name'] ?? ''));
        $download = (string)($release['zipball_url'] ?? '');
        if ($latest !== '' && filter_var($download, FILTER_VALIDATE_URL)) {
            $result['latest'] = $latest;
            $result['download_url'] = $download;
            $result['available'] = update_available_for($latest);
            $result['repair'] = !$result['available']
                && normalize_version($latest) === normalize_version(APP_VERSION)
                && bundled_release_files_missing();
        }
    }
    file_put_contents(UPDATE_CACHE_FILE, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $result;
}

function install_release_files(string $source, string $targetRoot, string $backup): void
{
    $files = ['index.php', 'index.css', 'index.js', 'install.php', 'update.php', 'README.md', 'README-EN.md', 'logo.png', 'favicon.png', '.htaccess'];
    foreach (['themes', 'plugins'] as $extensionDirectory) {
        $extensionRoot = $source . '/' . $extensionDirectory;
        if (!is_dir($extensionRoot)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extensionRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relative = substr($item->getPathname(), strlen($extensionRoot) + 1);
                $files[] = $extensionDirectory . '/' . str_replace('\\', '/', $relative);
            }
        }
    }

    $targetRoot = rtrim($targetRoot, '/\\');
    $replaced = [];
    $created = [];
    $createdDirectories = [];
    try {
        foreach ($files as $file) {
            $from = $source . '/' . $file;
            if (!is_file($from)) { continue; }
            $target = $targetRoot . '/' . $file;
            $targetDirectory = dirname($target);
            if (!is_dir($targetDirectory)) {
                if (!mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) { throw new RuntimeException(sblog_t('无法创建目录 {file}', ['file' => $file])); }
                $createdDirectories[] = $targetDirectory;
            }
            if (is_file($target)) {
                $saved = $backup . '/' . $file;
                $savedDirectory = dirname($saved);
                if (!is_dir($savedDirectory) && !mkdir($savedDirectory, 0755, true) && !is_dir($savedDirectory)) { throw new RuntimeException(sblog_t('无法创建备份目录 {file}', ['file' => $file])); }
                if (!copy($target, $saved)) { throw new RuntimeException(sblog_t('无法备份 {file}', ['file' => $file])); }
                $replaced[] = $file;
            } elseif (file_exists($target)) {
                throw new RuntimeException(sblog_t('更新目标不是文件：{file}', ['file' => $file]));
            } else {
                $created[] = $file;
            }
            if (!copy($from, $target)) { throw new RuntimeException(sblog_t('无法覆盖 {file}', ['file' => $file])); }
        }
    } catch (Throwable $exception) {
        foreach (array_reverse($created) as $file) { @unlink($targetRoot . '/' . $file); }
        foreach (array_reverse($replaced) as $file) {
            $saved = $backup . '/' . $file;
            if (is_file($saved)) { copy($saved, $targetRoot . '/' . $file); }
        }
        foreach (array_reverse(array_unique($createdDirectories)) as $directory) {
            $current = $directory;
            while ($current !== $targetRoot && str_starts_with($current, $targetRoot) && is_dir($current) && @rmdir($current)) {
                $current = dirname($current);
            }
        }
        throw $exception;
    }
}

function install_github_update(array $update): string
{
    $isRepair = !empty($update['repair']);
    if ((empty($update['available']) && !$isRepair) || !filter_var((string)($update['download_url'] ?? ''), FILTER_VALIDATE_URL)) { throw new RuntimeException(sblog_t('当前没有可安装的更新。')); }
    if (!class_exists('ZipArchive')) { throw new RuntimeException(sblog_t('服务器未启用 ZipArchive，无法解压更新包。')); }
    ensure_runtime_dirs();
    $workDir = CACHE_DIR . '/update-' . bin2hex(random_bytes(6));
    $zipFile = $workDir . '/release.zip';
    if (!mkdir($workDir, 0755, true) && !is_dir($workDir)) { throw new RuntimeException(sblog_t('无法创建更新临时目录。')); }
    try {
        $handle = fopen($zipFile, 'wb');
        if ($handle === false) { throw new RuntimeException(sblog_t('无法创建更新包。')); }
        $curl = curl_init((string)$update['download_url']);
        curl_setopt_array($curl, array_replace([
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'Simple-PHP-Blog/' . APP_VERSION,
        ], curl_trust_options()));
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);
        if (!$ok || $status !== 200) { throw new RuntimeException(sblog_t('更新包下载失败：{error}', ['error' => $error ?: 'HTTP ' . $status])); }
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true || !$zip->extractTo($workDir . '/source')) { throw new RuntimeException(sblog_t('更新包无法解压。')); }
        $zip->close();
        $roots = glob($workDir . '/source/*', GLOB_ONLYDIR) ?: [];
        $source = (string)($roots[0] ?? '');
        $newIndex = $source . '/index.php';
        if (!is_file($newIndex)) { throw new RuntimeException(sblog_t('更新包结构无效。')); }
        $code = (string)file_get_contents($newIndex);
        if (!preg_match("/const APP_VERSION = '([^']+)'/", $code, $match)) { throw new RuntimeException(sblog_t('更新包版本无效。')); }
        $packageVersion = (string)$match[1];
        $versionIsValid = $isRepair
            ? normalize_version($packageVersion) === normalize_version(APP_VERSION)
            : update_available_for($packageVersion);
        if (!$versionIsValid) { throw new RuntimeException(sblog_t('更新包版本无效或不高于当前版本。')); }
        $backup = CACHE_DIR . '/update-backup-' . date('Ymd-His');
        mkdir($backup, 0755, true);
        install_release_files($source, __DIR__, $backup);
        @unlink(UPDATE_CACHE_FILE);
        return $packageVersion;
    } finally {
        $items = is_dir($workDir) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) : [];
        foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        if (is_dir($workDir)) { @rmdir($workDir); }
    }
}

function login_rate_file(): string
{
    $client = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return CACHE_DIR . '/login-' . hash('sha256', $client) . '.json';
}

function login_rate_state(bool $recordFailure = false, bool $clear = false): array
{
    ensure_runtime_dirs();
    $file = login_rate_file();
    $handle = fopen($file, 'c+');
    if ($handle === false) { return ['count' => 0, 'since' => time()]; }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = json_decode($raw ?: '', true);
    $now = time();
    if (!is_array($state) || $now - (int)($state['since'] ?? 0) >= 900) {
        $state = ['count' => 0, 'since' => $now];
    }
    if ($recordFailure) { $state['count'] = (int)$state['count'] + 1; }
    if ($clear) { $state = ['count' => 0, 'since' => $now]; }
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $state;
}

function password_reset_rate_file(): string
{
    $client = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return CACHE_DIR . '/password-reset-rate-' . hash('sha256', $client) . '.json';
}

function password_reset_rate_state(bool $recordAttempt = false): array
{
    ensure_runtime_dirs();
    $file = password_reset_rate_file();
    $handle = fopen($file, 'c+');
    if ($handle === false) { return ['count' => 0, 'since' => time()]; }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = json_decode($raw ?: '', true);
    $now = time();
    if (!is_array($state) || $now - (int)($state['since'] ?? 0) >= 900) {
        $state = ['count' => 0, 'since' => $now];
    }
    if ($recordAttempt) { $state['count'] = (int)$state['count'] + 1; }
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $state;
}

function public_ip_address(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    if (!headers_sent()) {
        header('Content-Language: ' . sblog_i18n_locale());
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensure_upload_year_dir(): array
{
    ensure_runtime_dirs();

    $year = date('Y');
    $dir = UPLOAD_DIR . '/' . $year;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $htaccess = UPLOAD_DIR . '/.htaccess';
    $htaccessRules = "Options -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .py .rb .asp .aspx .jsp\n<IfModule mod_headers.c>\n  Header set X-Content-Type-Options nosniff\n</IfModule>\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phar|cgi|pl|py|rb|asp|aspx|jsp)$\">\n  Require all denied\n</FilesMatch>\n";
    if (!is_file($htaccess) || file_get_contents($htaccess) !== $htaccessRules) {
        file_put_contents($htaccess, $htaccessRules, LOCK_EX);
    }

    return [$year, $dir];
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sblog_t('文件超过服务器允许的大小。'),
        UPLOAD_ERR_PARTIAL => sblog_t('文件只上传了一部分。'),
        UPLOAD_ERR_NO_FILE => sblog_t('没有选择文件。'),
        UPLOAD_ERR_NO_TMP_DIR => sblog_t('服务器缺少临时目录。'),
        UPLOAD_ERR_CANT_WRITE => sblog_t('服务器无法写入文件。'),
        UPLOAD_ERR_EXTENSION => sblog_t('上传被服务器扩展拦截。'),
        default => sblog_t('上传失败。'),
    };
}

function media_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return max(0, $bytes) . ' B';
    }
    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    foreach ($units as $index => $unit) {
        if ($value < 1024 || $index === count($units) - 1) {
            return number_format($value, $value >= 10 ? 0 : 1) . ' ' . $unit;
        }
        $value /= 1024;
    }
    return $bytes . ' B';
}

function media_local_file(string $relativePath): ?string
{
    $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
    if ($relativePath === '' || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $relativePath) || str_contains($relativePath, "\0")) {
        return null;
    }
    $uploadsRoot = realpath(UPLOAD_DIR);
    if ($uploadsRoot === false) {
        return null;
    }
    $candidate = UPLOAD_DIR . '/' . $relativePath;
    $existingParent = dirname($candidate);
    while (!file_exists($existingParent) && dirname($existingParent) !== $existingParent) {
        $existingParent = dirname($existingParent);
    }
    $parent = realpath($existingParent);
    $rootPrefix = rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($parent === false || strncasecmp(rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $rootPrefix, strlen($rootPrefix)) !== 0) {
        return null;
    }
    return $candidate;
}

function delete_media_storage(array $media): array
{
    $driver = trim((string)($media['storage_driver'] ?? 'local')) ?: 'local';
    if ($driver === 'local') {
        $result = ['ok' => true, 'error' => ''];
    } else {
        $result = plugin_filter('attachment_delete', [
            'ok' => false,
            'error' => sblog_t('当前存储插件不可用，无法删除远端文件。'),
        ], ['media' => $media, 'storage_driver' => $driver, 'storage_key' => (string)($media['storage_key'] ?? '')]);
    }
    if (!is_array($result) || empty($result['ok'])) {
        return ['ok' => false, 'error' => trim((string)($result['error'] ?? sblog_t('删除存储文件失败。'))) ?: sblog_t('删除存储文件失败。')];
    }

    $localPath = trim((string)($media['local_path'] ?? ''));
    if ($localPath !== '') {
        $file = media_local_file($localPath);
        if ($file === null) {
            return ['ok' => false, 'error' => sblog_t('媒体文件路径无效，已停止删除。')];
        }
        if (is_file($file) && !@unlink($file)) {
            return ['ok' => false, 'error' => sblog_t('服务器无法删除本地媒体文件。')];
        }
    }
    return ['ok' => true, 'error' => ''];
}

function handle_attachment_upload(): void
{
    require_admin();
    verify_csrf();

    $files = $_FILES['attachments'] ?? null;
    if (!is_array($files) || !isset($files['name'], $files['tmp_name'], $files['error'], $files['size'])) {
        json_response(['ok' => false, 'error' => sblog_t('没有收到附件。')], 400);
    }

    $year = date('Y');
    [, $dir] = ensure_upload_year_dir();
    $maxSize = 30 * 1024 * 1024;
    $allowedTypes = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'], 'pdf' => ['application/pdf'],
        'txt' => ['text/plain'], 'md' => ['text/plain'], 'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
    $uploaded = [];
    $failed = [];

    foreach ($names as $i => $originalName) {
        $originalName = (string)$originalName;
        $error = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string)($tmpNames[$i] ?? '');
        $size = (int)($sizes[$i] ?? 0);

        if ($error !== UPLOAD_ERR_OK) {
            $failed[] = ['name' => $originalName, 'error' => upload_error_message($error)];
            continue;
        }

        if ($size < 1 || $size > $maxSize) {
            $failed[] = ['name' => $originalName, 'error' => sblog_t('每个附件最大 30M。')];
            continue;
        }

        if (!is_uploaded_file($tmpName)) {
            $failed[] = ['name' => $originalName, 'error' => sblog_t('临时文件无效。')];
            continue;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = 'bin';
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName) ?: 'application/octet-stream';
        if (!isset($allowedTypes[$extension]) || !in_array($mime, $allowedTypes[$extension], true)) {
            $failed[] = ['name' => $originalName, 'error' => sblog_t('文件类型不在允许列表中。')];
            continue;
        }

        $safeExtension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin';
        $timestamp = str_replace('.', '', sprintf('%.6F', microtime(true)));
        $filename = $timestamp . '-' . bin2hex(random_bytes(3)) . '.' . $safeExtension;
        $target = $dir . '/' . $filename;
        $imageInfo = @getimagesize($tmpName);
        $isImage = is_array($imageInfo);

        if (!move_uploaded_file($tmpName, $target)) {
            $failed[] = ['name' => $originalName, 'error' => sblog_t('保存附件失败。')];
            continue;
        }

        $localUrl = asset_url('uploads/' . $year . '/' . $filename);
        $storage = plugin_filter('attachment_storage', [
            'ok' => true,
            'url' => $localUrl,
            'error' => '',
            'remove_local' => false,
            'storage_driver' => 'local',
            'storage_key' => '',
        ], [
            'file' => $target,
            'year' => $year,
            'filename' => $filename,
            'mime' => $mime,
            'size' => $size,
            'original_name' => $originalName,
            'local_url' => $localUrl,
        ]);
        if (!is_array($storage) || empty($storage['ok']) || trim((string)($storage['url'] ?? '')) === '') {
            @unlink($target);
            $message = is_array($storage) ? trim((string)($storage['error'] ?? '')) : '';
            $failed[] = ['name' => $originalName, 'error' => $message !== '' ? $message : sblog_t('附件存储插件处理失败。')];
            continue;
        }
        $url = trim((string)$storage['url']);
        if (!empty($storage['remove_local']) && is_file($target)) { @unlink($target); }
        $label = trim(pathinfo($originalName, PATHINFO_FILENAME)) ?: $filename;
        $markdown = $isImage ? '![' . $label . '](' . $url . ')' : '[' . $label . '](' . $url . ')';

        try {
            $now = time();
            q(
                'INSERT INTO media(original_name, title, alt_text, caption, url, storage_driver, storage_key, local_path, mime_type, file_size, is_image, width, height, created_at, updated_at)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    str_sub_u($originalName, 0, 255), str_sub_u($label, 0, 255), '', '', $url,
                    str_sub_u(trim((string)($storage['storage_driver'] ?? 'local')), 0, 80) ?: 'local',
                    str_sub_u(trim((string)($storage['storage_key'] ?? '')), 0, 1000),
                    !empty($storage['remove_local']) ? '' : $year . '/' . $filename,
                    str_sub_u($mime, 0, 255), $size, $isImage ? 1 : 0,
                    $isImage ? (int)($imageInfo[0] ?? 0) : 0, $isImage ? (int)($imageInfo[1] ?? 0) : 0,
                    $now, $now,
                ]
            );
            $mediaId = (int)db()->lastInsertId();
        } catch (Throwable) {
            $storageDriver = trim((string)($storage['storage_driver'] ?? 'local')) ?: 'local';
            if ($storageDriver !== 'local') {
                plugin_filter('attachment_delete', ['ok' => false, 'error' => ''], [
                    'storage_driver' => $storageDriver,
                    'storage_key' => (string)($storage['storage_key'] ?? ''),
                    'media' => ['storage_driver' => $storageDriver, 'storage_key' => (string)($storage['storage_key'] ?? '')],
                ]);
            }
            if (is_file($target)) { @unlink($target); }
            $failed[] = ['name' => $originalName, 'error' => sblog_t('媒体资料登记失败。')];
            continue;
        }

        $uploaded[] = [
            'id' => $mediaId,
            'name' => $originalName,
            'url' => $url,
            'markdown' => $markdown,
            'is_image' => $isImage,
            'size' => $size,
        ];
    }

    json_response([
        'ok' => $uploaded !== [],
        'files' => $uploaded,
        'errors' => $failed,
    ], $uploaded !== [] ? 200 : 400);
}

function update_admin_presence(?int $adminId): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || session_id() === '') {
        return;
    }

    ensure_runtime_dirs();
    $stream = @fopen(ADMIN_PRESENCE_FILE, 'c+');
    if ($stream === false || !flock($stream, LOCK_EX)) {
        if (is_resource($stream)) { fclose($stream); }
        return;
    }

    rewind($stream);
    $stored = json_decode((string)stream_get_contents($stream), true);
    $sessions = is_array($stored['sessions'] ?? null) ? $stored['sessions'] : [];
    $now = time();
    foreach ($sessions as $key => $presence) {
        if (!is_array($presence) || $now - (int)($presence['last_seen_at'] ?? 0) > ADMIN_PRESENCE_TIMEOUT) {
            unset($sessions[$key]);
        }
    }

    $sessionKey = hash('sha256', session_id());
    if ($adminId !== null && $adminId > 0) {
        $sessions[$sessionKey] = ['admin_id' => $adminId, 'last_seen_at' => $now];
    } else {
        unset($sessions[$sessionKey]);
    }

    rewind($stream);
    ftruncate($stream, 0);
    fwrite($stream, (string)json_encode(['sessions' => $sessions], JSON_UNESCAPED_SLASHES));
    fflush($stream);
    flock($stream, LOCK_UN);
    fclose($stream);
}

function admin_is_online(): bool
{
    $stream = @fopen(ADMIN_PRESENCE_FILE, 'rb');
    if ($stream === false || !flock($stream, LOCK_SH)) {
        if (is_resource($stream)) { fclose($stream); }
        return false;
    }

    $stored = json_decode((string)stream_get_contents($stream), true);
    flock($stream, LOCK_UN);
    fclose($stream);
    $sessions = is_array($stored['sessions'] ?? null) ? $stored['sessions'] : [];
    $now = time();
    foreach ($sessions as $presence) {
        if (is_array($presence) && $now - (int)($presence['last_seen_at'] ?? 0) <= ADMIN_PRESENCE_TIMEOUT) {
            return true;
        }
    }
    return false;
}

function current_admin(): ?array
{
    static $loaded = false;
    static $admin = null;

    if ($loaded) {
        return $admin;
    }

    $loaded = true;
    $id = (int)($_SESSION['admin_id'] ?? 0);

    if ($id < 1) {
        return $admin = null;
    }

    $admin = one('SELECT id, username, password_hash, nickname, email, avatar_url, website_url, created_at FROM users WHERE id = ?', [$id]);
    if ($admin === null) {
        clear_admin_authentication();
        return null;
    }

    $now = time();
    $authenticatedAt = (int)($_SESSION['admin_authenticated_at'] ?? $now);
    $lastSeenAt = (int)($_SESSION['admin_last_seen_at'] ?? $now);
    $currentFingerprint = hash('sha256', (string)$admin['password_hash']);
    $storedFingerprint = (string)($_SESSION['admin_password_fingerprint'] ?? $currentFingerprint);
    $expired = $now - $lastSeenAt > ADMIN_SESSION_IDLE_TIMEOUT
        || $now - $authenticatedAt > ADMIN_SESSION_ABSOLUTE_TIMEOUT;
    if ($expired || !hash_equals($storedFingerprint, $currentFingerprint)) {
        clear_admin_authentication();
        return $admin = null;
    }

    $_SESSION['admin_authenticated_at'] = $authenticatedAt;
    $_SESSION['admin_last_seen_at'] = $now;
    $_SESSION['admin_password_fingerprint'] = $currentFingerprint;
    update_admin_presence((int)$admin['id']);
    unset($admin['password_hash']);
    return $admin;
}

function clear_admin_authentication(): void
{
    update_admin_presence(null);
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_authenticated_at'],
        $_SESSION['admin_last_seen_at'],
        $_SESSION['admin_password_fingerprint']
    );
    if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
        session_regenerate_id(true);
    }
}

function destroy_current_session(): void
{
    update_admin_presence(null);
    $_SESSION = [];
    if ((bool)ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string)$params['path'],
            'domain' => (string)$params['domain'],
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => (string)($params['samesite'] ?? 'Lax'),
        ]);
    }
    session_destroy();
}

function authenticated_comment_identity(): ?array
{
    $admin = current_admin();
    if ($admin === null) {
        return null;
    }

    $name = trim((string)($admin['nickname'] ?? '')) ?: trim((string)$admin['username']);
    $name = trim((string)(preg_replace('/\s+/u', ' ', $name) ?? $name));
    $name = str_sub_u($name, 0, 50);
    $email = str_lower_u(trim((string)($admin['email'] ?? '')));
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160)) {
        $email = '';
    }

    $url = trim((string)($admin['website_url'] ?? ''));
    $scheme = str_lower_u((string)parse_url($url, PHP_URL_SCHEME));
    if ($url !== '' && (strlen($url) > 300 || !filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true))) {
        $url = '';
    }

    return [
        'user_id' => (int)$admin['id'],
        'author_name' => $name,
        'author_email' => $email,
        'author_url' => $url,
    ];
}

function is_admin(): bool
{
    return current_admin() !== null;
}

function require_admin(): void
{
    if (!is_admin()) {
        set_flash('error', sblog_t('请先登录后台。'));
        redirect_to(url_for('login'));
    }
}

function require_admin_post(string $fallbackUrl): void
{
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect_to($fallbackUrl);
    }
    verify_csrf();
}

function positive_int_ids(mixed $values, int $limit = 100): array
{
    $values = is_array($values) ? $values : [$values];
    return array_slice(
        array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $id): bool => $id > 0))),
        0,
        $limit
    );
}

function set_route_params(array $params): void
{
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $_GET[$key] = (string)$value;
        $_REQUEST[$key] = (string)$value;
    }
}

function mark_route_not_found(): void
{
    $_GET['__route_not_found'] = '1';
    $_REQUEST['__route_not_found'] = '1';
}

function apply_pretty_route(): void
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($uri, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
        return;
    }

    $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

    if ($script !== '' && $script !== '/' && ($path === $script || str_starts_with($path, $script . '/'))) {
        $path = substr($path, strlen($script)) ?: '/';
    } elseif ($base !== '' && $base !== '/' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base)) ?: '/';
    }

    $path = '/' . trim(rawurldecode($path), '/');

    if ($path === '/' || $path === '/index.php') {
        return;
    }

    if (preg_match('#^/rss\.xml$#i', $path)) {
        set_route_params(['a' => 'rss']);
        return;
    }

    if (preg_match('#^/sitemap\.xml$#i', $path)) {
        set_route_params(['a' => 'sitemap']);
        return;
    }

    if (preg_match('#^/page/(\d+)/?$#i', $path, $matches)) {
        set_route_params(['a' => 'home', 'p' => $matches[1]]);
        return;
    }

    if (preg_match('#^/tags/?$#i', $path)) {
        set_route_params(['a' => 'tags']);
        return;
    }

    if (preg_match('#^/links/?$#i', $path)) {
        set_route_params(['a' => 'links']);
        return;
    }

    if (preg_match('#^/tag/(.+)$#u', $path, $matches)) {
        set_route_params(['a' => 'tag', 'slug' => trim($matches[1], '/')]);
        return;
    }

    if (preg_match('#^/category/(.+)$#u', $path, $matches)) {
        set_route_params(['a' => 'category', 'slug' => trim($matches[1], '/')]);
        return;
    }

    if (preg_match('#^/pages/(.+)$#u', $path, $matches)) {
        set_route_params(['a' => 'page', 'slug' => trim($matches[1], '/')]);
        return;
    }

    if (preg_match('#^/archives/?$#i', $path)) {
        set_route_params(['a' => 'archives']);
        return;
    }

    if (preg_match('#^/admin/(posts|comments|categories|tags|links|users|media|ai|mail|s3|themes|settings|plugins)/?$#i', $path, $matches)) {
        set_route_params(['a' => 'admin_' . str_lower_u($matches[1])]);
        return;
    }

    if (preg_match('#^/forgot-password/?$#i', $path)) {
        set_route_params(['a' => 'forgot_password']);
        return;
    }

    if (preg_match('#^/reset-password/?$#i', $path)) {
        set_route_params(['a' => 'reset_password']);
        return;
    }

    if (preg_match('#^/(login|logout|admin|write)/?$#i', $path, $matches)) {
        set_route_params(['a' => str_lower_u($matches[1])]);
        return;
    }

    if (preg_match('#^/edit/(\d+)/?$#', $path, $matches)) {
        set_route_params(['a' => 'edit', 'id' => $matches[1]]);
        return;
    }

    if (preg_match('#^/post/(.+)$#u', $path, $matches)) {
        header('Location: ' . app_path('/archive/' . rawurlencode(trim($matches[1], '/'))), true, 301);
        exit;
    }

    if (preg_match('#^/archive/(.+)$#u', $path, $matches)) {
        set_route_params(['a' => 'post', 'slug' => trim($matches[1], '/')]);
        return;
    }

    if (preg_match('#^/([^/]+)/?$#u', $path, $matches)) {
        set_route_params(['a' => 'page', 'slug' => trim($matches[1], '/')]);
        return;
    }

    mark_route_not_found();
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $dir === '' || $dir === '.' ? '' : $dir;
}

function app_path(string $path = '/'): string
{
    $base = app_base_path();
    $path = '/' . ltrim($path, '/');
    return ($base !== '' ? $base : '') . ($path === '/' ? '/' : $path);
}

function script_url(): string
{
    return app_path('/index.php');
}

function install_url(): string
{
    return app_path('/install.php');
}

function asset_url(string $path): string
{
    return app_path('/' . ltrim($path, '/'));
}

function theme_manifest(string $slug): ?array
{
    if ($slug === 'default') {
        return [
            'slug' => 'default',
            'name' => '内置终端主题',
            'version' => APP_VERSION,
            'author' => 'Simple PHP Blog',
            'url' => 'https://github.com/jkjoy/Simple-PHP-Blog',
            'description' => '程序自带的终端风格前台主题。',
        ];
    }

    if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
        return null;
    }

    $themesRoot = realpath(THEMES_DIR);
    $themeDir = realpath(THEMES_DIR . '/' . $slug);
    if ($themesRoot === false || $themeDir === false || !is_dir($themeDir)) {
        return null;
    }

    $rootPrefix = rtrim($themesRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncasecmp($themeDir . DIRECTORY_SEPARATOR, $rootPrefix, strlen($rootPrefix)) !== 0) {
        return null;
    }

    $manifestFile = $themeDir . '/theme.json';
    if (!is_file($manifestFile) || filesize($manifestFile) > 65536) {
        return null;
    }

    $manifest = json_decode((string)file_get_contents($manifestFile), true);
    $name = is_array($manifest) ? trim((string)($manifest['name'] ?? '')) : '';
    if ($name === '') {
        return null;
    }
    $url = trim((string)($manifest['url'] ?? ''));
    $urlParts = $url !== '' ? parse_url($url) : false;
    if (strlen($url) > 300 || !filter_var($url, FILTER_VALIDATE_URL) || !is_array($urlParts)
        || !in_array(str_lower_u((string)($urlParts['scheme'] ?? '')), ['http', 'https'], true)
        || trim((string)($urlParts['host'] ?? '')) === '' || isset($urlParts['user']) || isset($urlParts['pass'])) {
        $url = '';
    }

    return [
        'slug' => $slug,
        'name' => str_sub_u($name, 0, 100),
        'version' => str_sub_u(trim((string)($manifest['version'] ?? '')), 0, 40),
        'author' => str_sub_u(trim((string)($manifest['author'] ?? '')), 0, 100),
        'url' => $url,
        'description' => str_sub_u(trim((string)($manifest['description'] ?? '')), 0, 300),
    ];
}

function available_themes(): array
{
    $defaultTheme = theme_manifest('default');
    $themes = [];

    if (!is_dir(THEMES_DIR)) {
        return ['default' => $defaultTheme];
    }

    foreach (scandir(THEMES_DIR) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $manifest = theme_manifest($entry);
        if ($manifest !== null) {
            $themes[$entry] = $manifest;
        }
    }

    uasort($themes, static fn(array $left, array $right): int => strcasecmp((string)$left['name'], (string)$right['name']));
    return ['default' => $defaultTheme] + $themes;
}

function active_theme_slug(): string
{
    $preview = trim((string)($_GET['theme_preview'] ?? ''));
    if ($preview !== '' && is_admin() && theme_manifest($preview) !== null) {
        return $preview;
    }

    $configured = trim(setting('active_theme', 'default'));
    return theme_manifest($configured) !== null ? $configured : 'default';
}

function active_theme(): array
{
    return theme_manifest(active_theme_slug()) ?? theme_manifest('default');
}

function active_theme_file(string $filename): string
{
    $slug = active_theme_slug();
    if ($slug === 'default' || !in_array($filename, ['functions.php', 'layout.php', 'style.css'], true)) {
        return '';
    }

    $file = THEMES_DIR . '/' . $slug . '/' . $filename;
    return is_file($file) ? $file : '';
}

function theme_asset_url(string $path): string
{
    $slug = active_theme_slug();
    $path = trim(str_replace('\\', '/', $path), '/');
    $segments = $path === '' ? [] : explode('/', $path);

    if ($slug === 'default' || !$segments || array_filter($segments, static fn(string $segment): bool => $segment === '' || $segment === '.' || $segment === '..')) {
        return '';
    }

    return asset_url('themes/' . rawurlencode($slug) . '/' . implode('/', array_map('rawurlencode', $segments)));
}

function add_theme_action(string $hook, callable $callback, int $priority = 10): void
{
    if (!preg_match('/^[a-z][a-z0-9_.-]*$/', $hook)) {
        throw new InvalidArgumentException(sblog_t('无效的主题钩子名称：{hook}', ['hook' => $hook]));
    }

    $GLOBALS['sblog_theme_actions'][$hook][$priority][] = $callback;
}

function add_theme_filter(string $hook, callable $callback, int $priority = 10): void
{
    if (!preg_match('/^[a-z][a-z0-9_.-]*$/', $hook)) {
        throw new InvalidArgumentException(sblog_t('无效的主题过滤器名称：{hook}', ['hook' => $hook]));
    }

    $GLOBALS['sblog_theme_filters'][$hook][$priority][] = $callback;
}

function theme_callbacks(string $type, string $hook): array
{
    $groups = $GLOBALS[$type][$hook] ?? [];
    if (!is_array($groups)) {
        return [];
    }

    ksort($groups, SORT_NUMERIC);
    return array_merge(...array_values($groups));
}

function theme_action(string $hook, array $context = []): void
{
    foreach (theme_callbacks('sblog_theme_actions', $hook) as $callback) {
        try {
            $output = $callback($context);
            if (is_string($output) || is_numeric($output)) {
                echo $output;
            }
        } catch (Throwable $exception) {
            error_log('Theme action ' . $hook . ' failed: ' . $exception->getMessage());
        }
    }
}

function theme_filter(string $hook, mixed $value, array $context = []): mixed
{
    foreach (theme_callbacks('sblog_theme_filters', $hook) as $callback) {
        try {
            $value = $callback($value, $context);
        } catch (Throwable $exception) {
            error_log('Theme filter ' . $hook . ' failed: ' . $exception->getMessage());
        }
    }

    return $value;
}

function load_active_theme(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $loaded = true;
    $functionsFile = active_theme_file('functions.php');
    if ($functionsFile !== '') {
        try {
            require $functionsFile;
        } catch (Throwable $exception) {
            error_log('Theme bootstrap failed: ' . $exception->getMessage());
        }
    }

    if (active_theme_file('style.css') !== '') {
        add_theme_action('head', static function (array $context): string {
            $styleUrl = (string)($context['style_url'] ?? '');
            return $styleUrl !== '' ? '<link rel="stylesheet" href="' . h($styleUrl) . '">' . "\n" : '';
        }, -1000);
    }
}

function use_pretty_url(): bool
{
    return setting('pretty_url', '0') === '1';
}

function url_for(string $route, array $params = []): string
{
    $pretty = use_pretty_url();

    return match ($route) {
        'home' => $pretty ? app_path('/') : script_url(),
        'rss' => $pretty ? app_path('/rss.xml') : script_url() . '?a=rss',
        'sitemap' => $pretty ? app_path('/sitemap.xml') : script_url() . '?a=sitemap',
        'archives' => $pretty ? app_path('/archives') : script_url() . '?a=archives',
        'tags' => $pretty ? app_path('/tags') : script_url() . '?a=tags',
        'links' => $pretty ? app_path('/links') : script_url() . '?a=links',
        'tag' => $pretty ? app_path('/tag/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=tag&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'category' => $pretty ? app_path('/category/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=category&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'page' => $pretty ? app_path('/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=page&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'login' => $pretty ? app_path('/login') : script_url() . '?a=login',
        'forgot_password' => $pretty ? app_path('/forgot-password') : script_url() . '?a=forgot_password',
        'reset_password' => $pretty ? app_path('/reset-password') : script_url() . '?a=reset_password',
        'logout' => $pretty ? app_path('/logout') : script_url() . '?a=logout',
        'admin' => $pretty ? app_path('/admin') : script_url() . '?a=admin',
        'admin_posts' => $pretty ? app_path('/admin/posts') : script_url() . '?a=admin_posts',
        'admin_comments' => $pretty ? app_path('/admin/comments') : script_url() . '?a=admin_comments',
        'admin_categories' => $pretty ? app_path('/admin/categories') : script_url() . '?a=admin_categories',
        'admin_tags' => $pretty ? app_path('/admin/tags') : script_url() . '?a=admin_tags',
        'admin_links' => $pretty ? app_path('/admin/links') : script_url() . '?a=admin_links',
        'admin_users' => $pretty ? app_path('/admin/users') : script_url() . '?a=admin_users',
        'admin_media' => $pretty ? app_path('/admin/media') : script_url() . '?a=admin_media',
        'admin_ai' => $pretty ? app_path('/admin/ai') : script_url() . '?a=admin_ai',
        'admin_mail' => $pretty ? app_path('/admin/mail') : script_url() . '?a=admin_mail',
        'admin_s3' => $pretty ? app_path('/admin/s3') : script_url() . '?a=admin_s3',
        'admin_themes' => $pretty ? app_path('/admin/themes') : script_url() . '?a=admin_themes',
        'admin_settings' => $pretty ? app_path('/admin/settings') : script_url() . '?a=admin_settings',
        'admin_plugins' => $pretty ? app_path('/admin/plugins') : script_url() . '?a=admin_plugins',
        'write' => $pretty ? app_path('/write') : script_url() . '?a=write',
        'edit' => $pretty ? app_path('/edit/' . (int)($params['id'] ?? 0)) : script_url() . '?a=edit&id=' . (int)($params['id'] ?? 0),
        'post' => $pretty ? app_path('/archive/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=post&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'save_settings' => script_url() . '?a=save_settings',
        'save_ai_settings' => script_url() . '?a=save_ai_settings',
        'save_mail_settings' => script_url() . '?a=save_mail_settings',
        'save_s3_settings' => script_url() . '?a=save_s3_settings',
        'activate_theme' => script_url() . '?a=activate_theme',
        'toggle_plugin' => script_url() . '?a=toggle_plugin',
        'ai_generate' => script_url() . '?a=ai_generate',
        'save_category' => script_url() . '?a=save_category',
        'delete_category' => script_url() . '?a=delete_category',
        'save_tag' => script_url() . '?a=save_tag',
        'delete_tag' => script_url() . '?a=delete_tag',
        'save_link' => script_url() . '?a=save_link',
        'delete_link' => script_url() . '?a=delete_link',
        'save_user' => script_url() . '?a=save_user',
        'upload_attachment' => script_url() . '?a=upload_attachment',
        'save_media' => script_url() . '?a=save_media',
        'delete_media' => script_url() . '?a=delete_media',
        'delete_post' => script_url() . '?a=delete_post',
        'change_status' => script_url() . '?a=change_status',
        'submit_comment' => script_url() . '?a=submit_comment',
        'moderate_comments' => script_url() . '?a=moderate_comments',
        'mark_comments_read' => script_url() . '?a=mark_comments_read',
        'install_update' => script_url() . '?a=install_update',
        'check_update' => script_url() . '?a=check_update',
        default => script_url(),
    };
}

function url_with_query(string $url, array $params): string
{
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
}

function home_page_url(int $page): string
{
    if ($page <= 1) {
        return url_for('home');
    }

    return use_pretty_url() ? app_path('/page/' . $page) : script_url() . '?p=' . $page;
}

function site_footer_text(): string
{
    $footer = trim(setting('site_footer'));
    if ($footer === '') {
        $footer = '© 2026 - {year} Theme by jkjoy.';
    }
    return str_replace('{year}', date('Y'), $footer);
}

function pretty_date(int $timestamp, bool $withTime = false): string
{
    return date($withTime ? 'Y-m-d H:i' : 'Y-m-d', $timestamp);
}

function datetime_local_value(int $timestamp): string
{
    return date('Y-m-d\TH:i', $timestamp);
}

function site_root_url(): string
{
    $configured = trim(setting('site_url'));
    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
        return rtrim($configured, '/');
    }

    $https = ((string)($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');

    return rtrim($scheme . '://' . $host . app_base_path(), '/');
}

function absolute_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $base = site_root_url();
    $appBase = app_base_path();

    if ($appBase !== '') {
        if ($path === $appBase) {
            $path = '/';
        } elseif (str_starts_with($path, $appBase . '/')) {
            $path = substr($path, strlen($appBase));
        }
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function content_kind(array $row): string
{
    return (string)($row['kind'] ?? 'post') === 'page' ? 'page' : 'post';
}

function content_type_label(array $row): string
{
    return content_kind($row) === 'page' ? sblog_t('页面') : sblog_t('文章');
}

function content_permalink(array $row): string
{
    return content_kind($row) === 'page'
        ? url_for('page', ['slug' => (string)$row['slug']])
        : url_for('post', ['slug' => (string)$row['slug']]);
}

function parse_tags_input(string $raw): array
{
    $parts = preg_split('/[\n,，]+/u', $raw);
    if (!is_array($parts)) {
        $parts = [$raw];
    }

    $map = [];

    foreach ($parts as $part) {
        $label = trim($part);
        if ($label === '') {
            continue;
        }

        $slug = slugify($label);
        if (!isset($map[$slug])) {
            $map[$slug] = $label;
        }
    }

    return array_values($map);
}

function encode_tags(array $tags): string
{
    return json_encode(array_values($tags), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function post_tags(array $post): array
{
    $raw = (string)($post['tags'] ?? '[]');
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        $tags = [];
        foreach ($decoded as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                $tags[] = $value;
            }
        }
        return parse_tags_input(implode(', ', $tags));
    }

    return parse_tags_input($raw);
}

function tag_descriptors(array $post): array
{
    $tags = [];

    foreach (post_tags($post) as $label) {
        $tags[] = ['label' => $label, 'slug' => tag_slug_for_label($label)];
    }

    return $tags;
}

function slugify(string $text): string
{
    $text = str_lower_u(trim($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'post';
}

function unique_slug(string $seed, ?int $excludeId = null): string
{
    $base = slugify($seed);
    $slug = $base;
    $index = 2;

    while (true) {
        $row = $excludeId
            ? one('SELECT id FROM posts WHERE slug = ? AND id != ?', [$slug, $excludeId])
            : one('SELECT id FROM posts WHERE slug = ?', [$slug]);

        if ($row === null) {
            return $slug;
        }

        $slug = $base . '-' . $index;
        $index++;
    }
}

function markdown_to_plain(string $markdown): string
{
    $text = preg_replace('/```.*?```/su', ' ', $markdown) ?? $markdown;
    $text = preg_replace('/!\[[^\]]*]\([^)]+\)/u', ' ', $text) ?? $text;
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/u', '$1', $text) ?? $text;
    $text = preg_replace('/^[#>\-\*\d\.\s]+/mu', '', $text) ?? $text;
    $text = str_replace(['`', '*', '_', '~'], ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function derive_excerpt(string $content, int $length = 140): string
{
    $text = markdown_to_plain($content);

    if ($text === '') {
        return '';
    }

    if (str_len_u($text) <= $length) {
        return $text;
    }

    return rtrim(str_sub_u($text, 0, $length)) . '…';
}

function safe_link_url(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '#';
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return '#';
    }

    if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }

    if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
        return $url;
    }

    return '#';
}

function gravatar_url(string $email, int $size = 72): string
{
    $hash = md5(strtolower(trim($email)));
    $size = max(16, min(512, $size));
    return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size . '&d=identicon&r=g';
}

function social_profile_definitions(): array
{
    return [
        'github' => ['column' => 'github_url', 'label' => 'GitHub', 'icon' => 'ri-github-fill', 'placeholder' => 'https://github.com/...'],
        'qq' => ['column' => 'qq_url', 'label' => 'QQ', 'icon' => 'ri-qq-fill', 'placeholder' => 'https://qm.qq.com/q/...'],
        'wechat' => ['column' => 'wechat_url', 'label' => sblog_t('微信'), 'source_label' => '微信', 'icon' => 'ri-wechat-fill', 'placeholder' => 'https://example.com/wechat'],
        'weibo' => ['column' => 'weibo_url', 'label' => sblog_t('微博'), 'source_label' => '微博', 'icon' => 'ri-weibo-fill', 'placeholder' => 'https://weibo.com/...'],
        'x' => ['column' => 'x_url', 'label' => 'X', 'icon' => 'ri-twitter-x-fill', 'placeholder' => 'https://x.com/...'],
        'telegram' => ['column' => 'telegram_url', 'label' => 'Telegram', 'icon' => 'ri-telegram-fill', 'placeholder' => 'https://t.me/...'],
        'mastodon' => ['column' => 'mastodon_url', 'label' => 'Mastodon', 'icon' => 'ri-mastodon-fill', 'placeholder' => 'https://mastodon.social/@...'],
        'bilibili' => ['column' => 'bilibili_url', 'label' => sblog_t('哔哩哔哩'), 'source_label' => '哔哩哔哩', 'icon' => 'ri-bilibili-fill', 'placeholder' => 'https://space.bilibili.com/...'],
        'instagram' => ['column' => 'instagram_url', 'label' => 'Instagram', 'icon' => 'ri-instagram-fill', 'placeholder' => 'https://instagram.com/...'],
        'tiktok' => ['column' => 'tiktok_url', 'label' => 'TikTok', 'icon' => 'ri-tiktok-fill', 'placeholder' => 'https://tiktok.com/@...'],
    ];
}

function tag_slug_for_label(string $label): string
{
    $stored = val('SELECT slug FROM tag_meta WHERE label = ?', [$label]);
    if (is_string($stored) && $stored !== '') { return $stored; }
    $base = slugify($label);
    $slug = $base;
    $suffix = 2;
    while (one('SELECT label FROM tag_meta WHERE slug = ?', [$slug])) { $slug = $base . '-' . $suffix++; }
    q('INSERT OR IGNORE INTO tag_meta(label, slug, updated_at) VALUES(?,?,?)', [$label, $slug, time()]);
    return (string)(val('SELECT slug FROM tag_meta WHERE label = ?', [$label]) ?: $slug);
}

function split_bare_url_suffix(string $url): array
{
    $suffix = '';
    $pairs = [')' => '(', ']' => '['];

    while ($url !== '') {
        if (preg_match("/[.,!?;:*']+$/", $url, $matches)) {
            $ending = $matches[0];
            $url = substr($url, 0, -strlen($ending));
            $suffix = $ending . $suffix;
            continue;
        }

        if (str_ends_with($url, '~~')) {
            $url = substr($url, 0, -2);
            $suffix = '~~' . $suffix;
            continue;
        }

        $last = substr($url, -1);
        if (isset($pairs[$last]) && substr_count($url, $last) > substr_count($url, $pairs[$last])) {
            $url = substr($url, 0, -1);
            $suffix = $last . $suffix;
            continue;
        }

        break;
    }

    return [$url, $suffix];
}

function render_inline(string $text): string
{
    $parts = preg_split('/(`[^`]+`)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        $parts = [$text];
    }

    $html = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if ($part[0] === '`' && substr($part, -1) === '`') {
            $html .= '<code>' . h(substr($part, 1, -1)) . '</code>';
            continue;
        }

        // Tokenize bare URLs so existing Markdown links and images cannot be linked twice.
        $marker = "\x1A";
        while (str_contains($part, $marker)) {
            $marker .= "\x1A";
        }

        $bareLinks = [];
        $part = preg_replace_callback(
            '~\[!\[.*?\]\([^\s)]+\)\]\([^\s)]+\)|!\[.*?\]\([^\s)]+\)|(?<!!)\[.+?\]\([^\s)]+\)|(?<![A-Z0-9_])(?<bare_url>https?://[A-Z0-9._:/?#\[\]@!$&\'()*+,;=%\~-]+)~iu',
            static function (array $matches) use (&$bareLinks, $marker): string {
                $matchedUrl = (string)($matches['bare_url'] ?? '');
                if ($matchedUrl === '') {
                    return $matches[0];
                }

                [$url, $suffix] = split_bare_url_suffix($matchedUrl);
                $href = safe_link_url($url);
                if ($href === '#') {
                    return $matches[0];
                }

                $token = $marker . count($bareLinks) . $marker;
                $bareLinks[$token] = '<a href="' . h($href) . '" target="_blank" rel="noopener noreferrer">' . h($url) . '</a>';
                return $token . $suffix;
            },
            $part
        ) ?? $part;

        $escaped = h($part);

        $escaped = preg_replace_callback(
            '/!\[(.*?)]\(([^\s)]+)\)/u',
            static function (array $matches): string {
                $src = safe_link_url($matches[2]);
                if ($src === '#') {
                    return $matches[0];
                }

                return '<img src="' . h($src) . '" alt="' . $matches[1] . '" loading="lazy">';
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/(?<!!)\[(.+?)]\(([^\s)]+)\)/u',
            static function (array $matches): string {
                $href = safe_link_url($matches[2]);
                if ($href === '#') {
                    return $matches[0];
                }

                $external = preg_match('#^https?://#i', $href) === 1;
                $attrs = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
                return '<a href="' . h($href) . '"' . $attrs . '>' . $matches[1] . '</a>';
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/~~(.+?)~~/u', '<del>$1</del>', $escaped) ?? $escaped;

        $html .= strtr($escaped, $bareLinks);
    }

    return $html;
}

function media_host_matches(string $host, string $domain): bool
{
    return $host === $domain || str_ends_with($host, '.' . $domain);
}

function media_url_from_paragraph(string $text): string
{
    $text = trim($text);
    if (preg_match('/^<(?<url>https?:\/\/[^<>\s]+)>$/iu', $text, $matches)
        || preg_match('/^\[[^\]\r\n]+]\((?<url>https?:\/\/[^\s)]+)\)$/iu', $text, $matches)
        || preg_match('/^(?<url>https?:\/\/\S+)$/iu', $text, $matches)) {
        [$url] = split_bare_url_suffix((string)$matches['url']);
        return $url;
    }

    return '';
}

function media_iframe_html(string $provider, string $src, string $kind = 'video', int $height = 0): string
{
    $class = $kind === 'audio' ? 'media-embed media-embed--audio' : 'media-embed media-embed--video';
    $style = $height > 0 ? ' style="--media-height:' . $height . 'px"' : '';
    $allow = $kind === 'video'
        ? ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen'
        : '';

    return '<figure class="' . $class . '"' . $style . '><iframe src="' . h($src) . '" title="' . h($provider)
        . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"' . $allow . '></iframe></figure>';
}

function media_text_value(mixed $value, int $limit = 180): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $text = trim((string)preg_replace('/\s+/u', ' ', (string)$value));
    return $text === '' ? '' : str_sub_u($text, 0, $limit);
}

function media_name_list(mixed $value, int $limit = 5): array
{
    if (!is_array($value)) {
        return [];
    }

    $names = [];
    foreach ($value as $item) {
        $name = is_array($item) ? media_text_value($item['name'] ?? '') : media_text_value($item);
        if ($name !== '' && !in_array($name, $names, true)) {
            $names[] = $name;
        }
        if (count($names) >= $limit) {
            break;
        }
    }
    return $names;
}

function douban_subject_type(string $host, string $path): string
{
    foreach (['movie', 'book', 'music'] as $type) {
        if (media_host_matches($host, $type . '.douban.com')
            || preg_match('#/' . $type . '/subject/#i', $path)) {
            return $type;
        }
    }
    return '';
}

function douban_normalize_subject(array $subject, string $type): array
{
    $title = media_text_value($subject['title'] ?? '', 120);
    if ($title === '') {
        return [];
    }

    $labels = ['movie' => '豆瓣电影', 'book' => '豆瓣读书', 'music' => '豆瓣音乐'];
    $subtitle = $type === 'movie'
        ? media_text_value($subject['original_title'] ?? '', 120)
        : media_text_value($subject['subtitle'] ?? '', 120);
    if ($subtitle === $title) {
        $subtitle = '';
    }

    $pubdates = media_name_list($subject['pubdate'] ?? [], 1);
    $year = media_text_value($subject['year'] ?? '', 12);
    if ($year === '' && $pubdates !== [] && preg_match('/(?:19|20)\d{2}/', $pubdates[0], $match)) {
        $year = $match[0];
    }

    $genres = media_name_list($subject['genres'] ?? [], 4);
    $credits = [];
    if ($type === 'movie') {
        $directors = media_name_list($subject['directors'] ?? [], 3);
        $actors = media_name_list($subject['actors'] ?? [], 5);
        if ($directors !== []) { $credits[] = ['label' => '导演', 'value' => implode(' / ', $directors)]; }
        if ($actors !== []) { $credits[] = ['label' => '主演', 'value' => implode(' / ', $actors)]; }
    } elseif ($type === 'book') {
        $authors = media_name_list($subject['author'] ?? [], 4);
        $publishers = media_name_list($subject['press'] ?? [], 2);
        if ($authors !== []) { $credits[] = ['label' => '作者', 'value' => implode(' / ', $authors)]; }
        if ($publishers !== []) { $credits[] = ['label' => '出版', 'value' => implode(' / ', $publishers)]; }
    } elseif ($type === 'music') {
        $artists = media_name_list($subject['singer'] ?? [], 4);
        $publishers = media_name_list($subject['publisher'] ?? [], 2);
        if ($artists !== []) { $credits[] = ['label' => '表演者', 'value' => implode(' / ', $artists)]; }
        if ($publishers !== []) { $credits[] = ['label' => '发行', 'value' => implode(' / ', $publishers)]; }
    }

    $rating = (float)($subject['rating']['value'] ?? 0);
    $ratingCount = max(0, (int)($subject['rating']['count'] ?? 0));
    $cover = media_text_value($subject['cover_url'] ?? '', 500);
    $coverParts = $cover !== '' ? parse_url($cover) : false;
    $coverHost = is_array($coverParts) ? str_lower_u((string)($coverParts['host'] ?? '')) : '';
    if (!is_array($coverParts) || (string)($coverParts['scheme'] ?? '') !== 'https' || !media_host_matches($coverHost, 'doubanio.com')) {
        $cover = '';
    }

    return [
        'type' => $type,
        'label' => $labels[$type] ?? '豆瓣资料',
        'title' => $title,
        'subtitle' => $subtitle,
        'cover' => $cover,
        'meta' => array_values(array_filter(array_merge([$year], $genres), static fn(string $item): bool => $item !== '')),
        'credits' => array_slice($credits, 0, 2),
        'rating' => $rating > 0 ? number_format($rating, 1, '.', '') : '',
        'rating_count' => $ratingCount,
    ];
}

function douban_subject_data(string $type, string $id): array
{
    static $memory = [];
    $key = $type . ':' . $id;
    if (isset($memory[$key])) {
        return $memory[$key];
    }

    ensure_runtime_dirs();
    $cacheFile = CACHE_DIR . '/media-douban-' . $type . '-' . $id . '.json';
    $cached = is_file($cacheFile) ? json_decode((string)file_get_contents($cacheFile), true) : null;
    $cachedData = is_array($cached['data'] ?? null) ? $cached['data'] : [];
    $cachedAt = (int)($cached['fetched_at'] ?? 0);
    $ttl = $cachedData !== [] ? 604800 : 3600;
    if ($cachedAt > 0 && time() - $cachedAt < $ttl) {
        return $memory[$key] = $cachedData;
    }

    $normalized = [];
    if (function_exists('curl_init')) {
        $endpoint = 'https://m.douban.com/rexxar/api/v2/' . $type . '/' . $id . '?ck=&for_mobile=1';
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, array_replace([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SimplePHPBlog/' . APP_VERSION . ')',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: zh-CN,zh;q=0.9',
                'Referer: https://m.douban.com/' . $type . '/subject/' . $id . '/',
            ],
        ], curl_trust_options()));
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($status === 200 && is_string($body) && strlen($body) <= 1048576) {
            $subject = json_decode($body, true);
            if (is_array($subject)) {
                $normalized = douban_normalize_subject($subject, $type);
            }
        }
    }

    if ($normalized !== []) {
        @file_put_contents($cacheFile, json_encode([
            'fetched_at' => time(),
            'data' => $normalized,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $memory[$key] = $normalized;
    }

    if ($cachedData === []) {
        @file_put_contents($cacheFile, json_encode([
            'fetched_at' => time(),
            'data' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return $memory[$key] = $cachedData;
}

function render_douban_cover(): never
{
    $type = trim((string)($_GET['type'] ?? ''));
    $id = trim((string)($_GET['id'] ?? ''));
    if (!in_array($type, ['movie', 'book', 'music'], true) || !preg_match('/^\d{1,12}$/', $id)) {
        http_response_code(404);
        exit;
    }

    ensure_runtime_dirs();
    $cacheFile = CACHE_DIR . '/media-douban-cover-' . $type . '-' . $id . '.jpg';
    $sendFile = static function (string $file): never {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string)filesize($file));
        header('Cache-Control: public, max-age=86400, stale-if-error=604800');
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: same-origin');
        readfile($file);
        exit;
    };

    if (is_file($cacheFile) && time() - (int)filemtime($cacheFile) < 604800) {
        $sendFile($cacheFile);
    }

    $subject = douban_subject_data($type, $id);
    $coverUrl = (string)($subject['cover'] ?? '');
    $image = '';
    $contentType = '';
    if ($coverUrl !== '' && function_exists('curl_init')) {
        $curl = curl_init($coverUrl);
        curl_setopt_array($curl, array_replace([
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SimplePHPBlog/' . APP_VERSION . ')',
            CURLOPT_HTTPHEADER => [
                'Accept: image/jpeg',
                'Referer: https://m.douban.com/' . $type . '/subject/' . $id . '/',
            ],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$image): int {
                if (strlen($image) + strlen($chunk) > 2097152) {
                    return 0;
                }
                $image .= $chunk;
                return strlen($chunk);
            },
        ], curl_trust_options()));
        curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = str_lower_u(trim(explode(';', (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE))[0]));
        curl_close($curl);

        if ($status !== 200 || $contentType !== 'image/jpeg' || strlen($image) < 128) {
            $image = '';
        }
    }

    if ($image !== '') {
        @file_put_contents($cacheFile, $image, LOCK_EX);
        if (is_file($cacheFile)) {
            $sendFile($cacheFile);
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string)strlen($image));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        echo $image;
        exit;
    }

    if (is_file($cacheFile)) {
        $sendFile($cacheFile);
    }

    header('Cache-Control: no-store');
    http_response_code(404);
    exit;
}

function douban_media_card_html(string $url, string $host, string $type, string $id): string
{
    $subject = $type !== '' ? douban_subject_data($type, $id) : [];
    $detailed = $subject !== [];
    $label = match ($type) {
        'movie' => sblog_t('豆瓣电影'),
        'book' => sblog_t('豆瓣读书'),
        'music' => sblog_t('豆瓣音乐'),
        default => sblog_t('豆瓣资料'),
    };
    $title = $detailed ? (string)$subject['title'] : sblog_t('豆瓣媒体资料');
    $class = 'media-link-card media-link-card--douban' . ($detailed ? ' media-link-card--detailed media-link-card--' . h($type) : '');
    $cover = '<span class="media-link-card__cover" aria-hidden="true"><span class="media-link-card__cover-fallback">豆</span>';
    if ($detailed && (string)$subject['cover'] !== '') {
        $coverUrl = script_url() . '?a=douban_cover&type=' . rawurlencode($type) . '&id=' . rawurlencode($id);
        $cover .= '<img src="' . h($coverUrl) . '" alt="" loading="lazy" decoding="async" onerror="this.remove()">';
    }
    $cover .= '</span>';

    $subtitle = $detailed && (string)$subject['subtitle'] !== ''
        ? '<small class="media-link-card__subtitle">' . h((string)$subject['subtitle']) . '</small>'
        : '';
    $facts = '';
    foreach (($detailed ? $subject['meta'] : [$host]) as $fact) {
        $facts .= '<span>' . h((string)$fact) . '</span>';
    }
    $credits = '';
    $creditLabels = [
        '导演' => sblog_t('导演'),
        '主演' => sblog_t('主演'),
        '作者' => sblog_t('作者'),
        '出版' => sblog_t('出版'),
        '表演者' => sblog_t('表演者'),
        '发行' => sblog_t('发行'),
    ];
    foreach (($detailed ? $subject['credits'] : []) as $credit) {
        $sourceLabel = (string)$credit['label'];
        $credits .= '<span><b>' . h($creditLabels[$sourceLabel] ?? $sourceLabel) . '</b><span>' . h((string)$credit['value']) . '</span></span>';
    }
    $rating = '';
    if ($detailed && (string)$subject['rating'] !== '') {
        $ratingCount = (int)$subject['rating_count'];
        $ratingLabel = $ratingCount > 0
            ? sblog_tn('豆瓣评分 {rating} 分，{count} 人评价', $ratingCount, ['rating' => (string)$subject['rating']])
            : sblog_t('豆瓣评分 {rating} 分', ['rating' => (string)$subject['rating']]);
        $rating = '<span class="media-link-card__rating" aria-label="' . h($ratingLabel) . '"><b>'
            . h((string)$subject['rating']) . '</b><small>' . h(sblog_t('豆瓣评分')) . '</small></span>';
    }

    return '<aside class="' . $class . '">' . $cover
        . '<span class="media-link-card__body"><small class="media-link-card__eyebrow">' . h($label) . '</small>'
        . '<strong class="media-link-card__title">' . h($title) . '</strong>' . $subtitle
        . '<span class="media-link-card__facts">' . $facts . '</span>'
        . ($credits !== '' ? '<span class="media-link-card__credits">' . $credits . '</span>' : '') . '</span>'
        . '<span class="media-link-card__side">' . $rating
        . '<a class="media-link-card__action" href="' . h($url) . '" target="_blank" rel="noopener noreferrer">' . h(sblog_t('查看详情')) . '<span aria-hidden="true"> →</span></a>'
        . '</span></aside>';
}

function media_embed_html(string $url): string
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }

    $parts = parse_url($url);
    $scheme = str_lower_u((string)($parts['scheme'] ?? ''));
    $host = str_lower_u((string)($parts['host'] ?? ''));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $path = (string)($parts['path'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return '';
    }

    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);

    if (media_host_matches($host, 'music.163.com')) {
        $route = trim($path, '/');
        $fragment = (string)($parts['fragment'] ?? '');
        if (str_contains($fragment, '?')) {
            [$fragmentRoute, $fragmentQuery] = explode('?', $fragment, 2);
            $route = trim($fragmentRoute, '/');
            parse_str($fragmentQuery, $query);
        }

        $types = [
            'song' => ['type' => 2, 'height' => 86],
            'playlist' => ['type' => 0, 'height' => 450],
            'album' => ['type' => 1, 'height' => 450],
        ];
        $id = (string)($query['id'] ?? '');
        if (isset($types[$route]) && is_ascii_digits($id)) {
            $config = $types[$route];
            $src = 'https://music.163.com/outchain/player?type=' . $config['type'] . '&id=' . rawurlencode($id)
                . '&auto=0&height=' . ($config['height'] - 20);
            return media_iframe_html(sblog_t('网易云音乐'), $src, 'audio', $config['height']);
        }
    }

    if (media_host_matches($host, 'bilibili.com')
        && preg_match('#/(?:video/)?(BV[0-9A-Za-z]+|av(\d+))#i', $path, $matches)) {
        $parameter = stripos($matches[1], 'BV') === 0
            ? 'bvid=' . rawurlencode($matches[1])
            : 'aid=' . rawurlencode($matches[2]);
        $page = isset($query['p']) && is_ascii_digits((string)$query['p']) ? '&page=' . (int)$query['p'] : '';
        return media_iframe_html(
            sblog_t('哔哩哔哩视频'),
            'https://player.bilibili.com/player.html?' . $parameter . $page . '&high_quality=1&danmaku=0&as_wide=1'
        );
    }

    if (media_host_matches($host, 'youtube.com') || $host === 'youtu.be') {
        $videoId = '';
        if ($host === 'youtu.be') {
            $videoId = trim($path, '/');
        } elseif (isset($query['v'])) {
            $videoId = (string)$query['v'];
        } elseif (preg_match('#/(?:embed|shorts|live)/([^/?]+)#', $path, $matches)) {
            $videoId = $matches[1];
        }

        if (preg_match('/^[0-9A-Za-z_-]{6,15}$/', $videoId)) {
            return media_iframe_html(
                sblog_t('YouTube 视频'),
                'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoId)
            );
        }
    }

    if (media_host_matches($host, 'douban.com') && preg_match('#/subject/(\d+)#', $path, $matches)) {
        return douban_media_card_html($url, $host, douban_subject_type($host, $path), $matches[1]);
    }

    return '';
}

function markdown_table_cells(string $line): array
{
    $line = trim($line);
    $hasLeadingPipe = str_starts_with($line, '|');
    $hasTrailingPipe = str_ends_with($line, '|');
    $cells = [];
    $cell = '';
    $length = strlen($line);

    for ($index = 0; $index < $length; $index++) {
        $character = $line[$index];
        if ($character === '\\' && $index + 1 < $length && $line[$index + 1] === '|') {
            $cell .= '|';
            $index++;
            continue;
        }
        if ($character === '|') {
            $cells[] = trim($cell);
            $cell = '';
            continue;
        }
        $cell .= $character;
    }
    $cells[] = trim($cell);

    if ($hasLeadingPipe && $cells !== [] && $cells[0] === '') {
        array_shift($cells);
    }
    if ($hasTrailingPipe && $cells !== [] && $cells[count($cells) - 1] === '') {
        array_pop($cells);
    }

    return $cells;
}

function markdown_table_alignments(string $line): ?array
{
    $cells = markdown_table_cells($line);
    if ($cells === []) {
        return null;
    }

    $alignments = [];
    foreach ($cells as $cell) {
        $cell = trim($cell);
        if (!preg_match('/^:?-{3,}:?$/', $cell)) {
            return null;
        }
        $left = str_starts_with($cell, ':');
        $right = str_ends_with($cell, ':');
        $alignments[] = $left && $right ? 'center' : ($right ? 'right' : ($left ? 'left' : ''));
    }

    return $alignments;
}

function markdown_to_html(string $markdown): string
{
    $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));

    if ($markdown === '') {
        return '<p>' . h(sblog_t('暂无内容。')) . '</p>';
    }

    $lines = explode("\n", $markdown);
    $html = [];
    $paragraph = [];
    $quoteLines = [];
    $listType = null;
    $listItems = [];
    $inCode = false;
    $codeLang = '';
    $codeLines = [];

    $flushParagraph = static function () use (&$paragraph, &$html): void {
        if ($paragraph === []) {
            return;
        }

        $paragraphLines = array_map('trim', $paragraph);
        $text = trim(implode(' ', $paragraphLines));
        if ($text !== '') {
            $mediaUrl = media_url_from_paragraph($text);
            $mediaHtml = $mediaUrl !== '' ? media_embed_html($mediaUrl) : '';
            $renderedLines = array_map('render_inline', $paragraphLines);
            $html[] = $mediaHtml !== '' ? $mediaHtml : '<p>' . implode('<br>', $renderedLines) . '</p>';
        }

        $paragraph = [];
    };

    $flushList = static function () use (&$listType, &$listItems, &$html): void {
        if ($listType === null || $listItems === []) {
            $listType = null;
            $listItems = [];
            return;
        }

        $items = [];
        foreach ($listItems as $item) {
            $items[] = '<li>' . render_inline(trim($item)) . '</li>';
        }

        $html[] = '<' . $listType . '>' . implode('', $items) . '</' . $listType . '>';
        $listType = null;
        $listItems = [];
    };

    $flushQuote = static function () use (&$quoteLines, &$html): void {
        if ($quoteLines === []) {
            return;
        }

        $html[] = '<blockquote>' . markdown_to_html(implode("\n", $quoteLines)) . '</blockquote>';
        $quoteLines = [];
    };

    $flushCode = static function () use (&$inCode, &$codeLang, &$codeLines, &$html): void {
        if (!$inCode) {
            return;
        }

        $class = $codeLang !== '' ? ' class="language-' . h($codeLang) . '"' : '';
        $html[] = '<pre><code' . $class . '>' . h(implode("\n", $codeLines)) . '</code></pre>';
        $inCode = false;
        $codeLang = '';
        $codeLines = [];
    };

    $lineCount = count($lines);
    for ($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++) {
        $line = $lines[$lineIndex];
        if (preg_match('/^```([\w-]+)?\s*$/', $line, $matches)) {
            if ($inCode) {
                $flushCode();
            } else {
                $flushParagraph();
                $flushList();
                $flushQuote();
                $inCode = true;
                $codeLang = trim((string)($matches[1] ?? ''));
                $codeLines = [];
            }
            continue;
        }

        if ($inCode) {
            $codeLines[] = $line;
            continue;
        }

        if (str_contains($line, '|') && $lineIndex + 1 < $lineCount) {
            $alignments = markdown_table_alignments($lines[$lineIndex + 1]);
            $headers = markdown_table_cells($line);
            if ($alignments !== null && count($headers) === count($alignments)) {
                $flushParagraph();
                $flushList();
                $flushQuote();
                $headerHtml = [];
                foreach ($headers as $column => $header) {
                    $align = $alignments[$column];
                    $attribute = $align !== '' ? ' style="text-align:' . $align . '"' : '';
                    $headerHtml[] = '<th' . $attribute . '>' . render_inline($header) . '</th>';
                }

                $rowsHtml = [];
                $rowIndex = $lineIndex + 2;
                while ($rowIndex < $lineCount && trim($lines[$rowIndex]) !== '' && str_contains($lines[$rowIndex], '|')) {
                    $cells = markdown_table_cells($lines[$rowIndex]);
                    $cells = array_slice(array_pad($cells, count($headers), ''), 0, count($headers));
                    $cellHtml = [];
                    foreach ($cells as $column => $cell) {
                        $align = $alignments[$column];
                        $attribute = $align !== '' ? ' style="text-align:' . $align . '"' : '';
                        $cellHtml[] = '<td' . $attribute . '>' . render_inline($cell) . '</td>';
                    }
                    $rowsHtml[] = '<tr>' . implode('', $cellHtml) . '</tr>';
                    $rowIndex++;
                }
                $html[] = '<table><thead><tr>' . implode('', $headerHtml) . '</tr></thead><tbody>' . implode('', $rowsHtml) . '</tbody></table>';
                $lineIndex = $rowIndex - 1;
                continue;
            }
        }

        if (preg_match('/^\s*$/', $line)) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            continue;
        }

        if (preg_match('/^>\s?(.*)$/u', $line, $matches)) {
            $flushParagraph();
            $flushList();
            $quoteLines[] = $matches[1];
            continue;
        }

        $flushQuote();

        if (preg_match('/^---{2,}\s*$/', $line)) {
            $flushParagraph();
            $flushList();
            $html[] = '<hr>';
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.+)$/u', $line, $matches)) {
            $flushParagraph();
            $flushList();
            $level = strlen($matches[1]);
            $html[] = '<h' . $level . '>' . render_inline(trim($matches[2])) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^\s*[-*]\s+(.+)$/u', $line, $matches)) {
            $flushParagraph();
            if ($listType !== 'ul') {
                $flushList();
                $listType = 'ul';
            }
            $listItems[] = $matches[1];
            continue;
        }

        if (preg_match('/^\s*\d+\.\s+(.+)$/u', $line, $matches)) {
            $flushParagraph();
            if ($listType !== 'ol') {
                $flushList();
                $listType = 'ol';
            }
            $listItems[] = $matches[1];
            continue;
        }

        $paragraph[] = $line;
    }

    if ($inCode) {
        $flushCode();
    }

    $flushQuote();
    $flushList();
    $flushParagraph();

    return implode("\n", $html);
}

function post_state(array $post): array
{
    if ((string)$post['status'] !== 'published') {
        return ['label' => sblog_t('草稿'), 'class' => 'draft'];
    }

    if ((int)$post['published_at'] > time()) {
        return ['label' => sblog_t('定时'), 'class' => 'scheduled'];
    }

    return ['label' => sblog_t('已发布'), 'class' => 'published'];
}

function is_live_content(array $post): bool
{
    return (string)$post['status'] === 'published' && (int)$post['published_at'] > 0 && (int)$post['published_at'] <= time();
}

function is_live_post(array $post): bool
{
    return content_kind($post) === 'post' && is_live_content($post);
}

function content_allows_comments(array $post): bool
{
    return content_kind($post) === 'post' || (int)($post['allow_comments'] ?? 0) === 1;
}

function fetch_published_posts(int $limit, int $offset): array
{
    $limit = max(1, $limit);
    $offset = max(0, $offset);

    return all_rows(
        'SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
        ['post', 'published', time()]
    );
}

function count_published_posts(): int
{
    return (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['post', 'published', time()]);
}

function fetch_content_by_identifier(string $kind, string $slug, bool $allowPreview = false): ?array
{
    if ($slug === '') {
        return null;
    }

    if (is_ascii_digits($slug)) {
        $row = one('SELECT * FROM posts WHERE id = ? AND kind = ?', [(int)$slug, $kind]);
        if ($row && ($allowPreview || is_live_content($row))) {
            return $row;
        }
    }

    $row = one('SELECT * FROM posts WHERE slug = ? AND kind = ?', [$slug, $kind]);
    if ($row && ($allowPreview || is_live_content($row))) {
        return $row;
    }

    return null;
}

function fetch_post_by_identifier(string $slug, bool $allowPreview = false): ?array
{
    return fetch_content_by_identifier('post', $slug, $allowPreview);
}

function fetch_page_by_identifier(string $slug, bool $allowPreview = false): ?array
{
    return fetch_content_by_identifier('page', $slug, $allowPreview);
}

function fetch_post_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM posts WHERE id = ?', [$id]) : null;
}

function increment_content_views(array $post): void
{
    if (is_admin() || !is_live_content($post)) {
        return;
    }

    $database = db();
    $database->exec('BEGIN IMMEDIATE');
    try {
        $inserted = q(
            'INSERT OR IGNORE INTO post_views(post_id, ip_hash, created_at) VALUES(?,?,?)',
            [(int)$post['id'], client_ip_hash(), time()]
        )->rowCount();
        if ($inserted === 1) {
            q('UPDATE posts SET views = views + 1 WHERE id = ?', [(int)$post['id']]);
        }
        $database->exec('COMMIT');
    } catch (Throwable $exception) {
        try { $database->exec('ROLLBACK'); } catch (Throwable) {}
        throw $exception;
    }
}

function fetch_categories(): array
{
    return all_rows(
        'SELECT c.*, COUNT(p.id) AS post_count
         FROM categories c
         LEFT JOIN posts p ON p.category_id = c.id AND p.kind = ?
         GROUP BY c.id
         ORDER BY c.sort_order ASC, c.id DESC',
        ['post']
    );
}

function category_options(): array
{
    return all_rows('SELECT id, name FROM categories ORDER BY sort_order ASC, id DESC');
}

function unique_category_slug(string $seed, ?int $excludeId = null): string
{
    $base = slugify($seed);
    $slug = $base;
    $i = 2;

    while (true) {
        $existing = $excludeId
            ? one('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $excludeId])
            : one('SELECT id FROM categories WHERE slug = ?', [$slug]);

        if (!$existing) {
            return $slug;
        }

        $slug = $base . '-' . $i++;
    }
}

function validate_category_input(array $input, ?array $existing = null): array
{
    $name = trim((string)($input['name'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $sortOrder = (int)($input['sort_order'] ?? 0);
    $errors = [];

    if ($name === '') {
        $errors[] = '分类名称不能为空。';
    }

    $slug = unique_category_slug($slugInput !== '' ? $slugInput : $name, $existing ? (int)$existing['id'] : null);

    return [[
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'sort_order' => $sortOrder,
    ], $errors];
}

function fetch_archive_posts(): array
{
    return all_rows('SELECT id, slug, title, published_at, tags, kind, is_pinned FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC', ['post', 'published', time()]);
}

function archive_groups(): array
{
    $groups = [];

    foreach (fetch_archive_posts() as $post) {
        $label = sblog_t('{year} 年 {month} 月', [
            'year' => date('Y', (int)$post['published_at']),
            'month' => date('m', (int)$post['published_at']),
        ]);
        $groups[$label][] = $post;
    }

    return $groups;
}

function theme_logo_url(): string
{
    return asset_url('logo.png');
}

function theme_favicon_url(): string
{
    $value = trim(setting('favicon_url', default_settings()['favicon_url']));
    if ($value === '') { $value = 'favicon.png'; }
    if (preg_match('#^https?://#i', $value) || str_starts_with($value, '/')) { return $value; }
    return asset_url($value);
}

function public_quote(): string
{
    $quote = trim(setting('site_tagline'));

    return $quote !== '' ? $quote : setting('site_name', default_settings()['site_name']);
}

function render_public_post_list(array $posts): string
{
    ob_start();
    ?>
    <?php foreach ($posts as $post): ?>
      <div class="posts">
        <div class="post">
          <div class="time"><?= h(date('F j, Y', (int)$post['published_at'])) ?></div>
          <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?php if (!empty($post['is_pinned'])): ?><span class="pinned-badge"><?= h(sblog_t('置顶')) ?></span><?php endif; ?><?= h((string)$post['title']) ?></a>
        </div>
      </div>
    <?php endforeach; ?>
    <?php
    return (string)ob_get_clean();
}

function fetch_admin_posts(): array
{
    return all_rows(
        "SELECT p.*, c.name AS category_name
         FROM posts p
         LEFT JOIN categories c ON c.id = p.category_id
         ORDER BY
            CASE WHEN p.status = 'published' THEN p.published_at ELSE p.updated_at END DESC,
            p.id DESC"
    );
}

function admin_metrics(): array
{
    $now = time();
    $totalPosts = (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['post', 'published', $now]);
    $publishedPosts = (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['post', 'published', $now]);
    $totalViews = (int)val('SELECT COALESCE(SUM(views), 0) FROM posts WHERE status = ? AND published_at <= ?', ['published', $now]);
    $commentCounts = comment_admin_counts();

    return [
        'total_posts' => $totalPosts,
        'published' => $publishedPosts,
        'pages' => (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['page', 'published', $now]),
        'drafts' => (int)val("SELECT COUNT(*) FROM posts WHERE status = 'draft'"),
        'scheduled' => (int)val('SELECT COUNT(*) FROM posts WHERE status = ? AND published_at > ?', ['published', $now]),
        'categories' => (int)val('SELECT COUNT(*) FROM categories'),
        'total_views' => $totalViews,
        'avg_views' => $totalPosts > 0 ? (int)floor($totalViews / $totalPosts) : 0,
        'comments' => $commentCounts['all'],
        'pending_comments' => $commentCounts['pending'],
        'top_viewed' => all_rows('SELECT id, slug, title, views FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY views DESC, updated_at DESC LIMIT 5', ['post', 'published', $now]),
    ];
}

function comment_status_meta(string $status): array
{
    return match ($status) {
        'approved' => ['label' => sblog_t('已通过'), 'class' => 'approved'],
        'spam' => ['label' => sblog_t('垃圾评论'), 'class' => 'spam'],
        default => ['label' => sblog_t('待审核'), 'class' => 'pending'],
    };
}

function public_comments_for_post(int $postId, int $limit = 100): array
{
    $limit = max(1, min(200, $limit));
    return all_rows(
        "SELECT id, parent_id, reply_to_name, author_name, author_email, author_url, content, created_at
         FROM (
             SELECT id, parent_id, reply_to_name, author_name, author_email, author_url, content, created_at
             FROM comments
             WHERE post_id = ? AND status = 'approved'
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}
         )
         ORDER BY created_at ASC, id ASC",
        [$postId]
    );
}

function approved_comment_count(int $postId): int
{
    return (int)val('SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = ?', [$postId, 'approved']);
}

function approved_reply_target(int $postId, int $parentId): ?array
{
    if ($parentId < 1) {
        return null;
    }

    return one(
        'SELECT id, author_name FROM comments WHERE id = ? AND post_id = ? AND status = ?',
        [$parentId, $postId, 'approved']
    );
}

function visitor_email_has_approved_comment(string $email): bool
{
    $email = str_lower_u(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    return val(
        'SELECT 1 FROM comments WHERE user_id IS NULL AND author_email = ? COLLATE NOCASE AND status = ? LIMIT 1',
        [$email, 'approved']
    ) !== false;
}

function comment_admin_counts(): array
{
    static $counts = null;
    if (is_array($counts)) {
        return $counts;
    }

    $row = one(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) AS unread,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved,
            COALESCE(SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END), 0) AS spam
         FROM comments"
    ) ?? [];

    return $counts = [
        'all' => (int)($row['total'] ?? 0),
        'unread' => (int)($row['unread'] ?? 0),
        'pending' => (int)($row['pending'] ?? 0),
        'approved' => (int)($row['approved'] ?? 0),
        'spam' => (int)($row['spam'] ?? 0),
    ];
}

function unread_comment_count(): int
{
    return comment_admin_counts()['unread'];
}

function recent_comment_notifications(int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    return all_rows(
        "SELECT c.id, c.author_name, c.reply_to_name, c.content, c.created_at, p.kind AS post_kind, p.slug AS post_slug, p.title AS post_title
         FROM comments c
         INNER JOIN posts p ON p.id = c.post_id
         WHERE c.is_read = 0
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT {$limit}"
    );
}

function admin_comments_url(string $filter = 'all', string $search = '', int $page = 1): string
{
    if (!in_array($filter, ['all', 'unread', 'pending', 'approved', 'spam'], true)) {
        $filter = 'all';
    }
    $search = str_sub_u(trim($search), 0, 100);
    $params = [];
    if ($filter !== 'all') { $params['filter'] = $filter; }
    if ($search !== '') { $params['q'] = $search; }
    if ($page > 1) { $params['p'] = $page; }
    $url = url_for('admin_comments');
    return $params === [] ? $url : url_with_query($url, $params);
}

function fetch_admin_comments(string $filter, string $search, int $page, int $perPage = 20): array
{
    $allowed = ['all', 'unread', 'pending', 'approved', 'spam'];
    $filter = in_array($filter, $allowed, true) ? $filter : 'all';
    $where = [];
    $params = [];

    if ($filter === 'unread') {
        $where[] = 'c.is_read = 0';
    } elseif (in_array($filter, ['pending', 'approved', 'spam'], true)) {
        $where[] = 'c.status = ?';
        $params[] = $filter;
    }

    if ($search !== '') {
        $where[] = '(c.author_name LIKE ? OR c.author_email LIKE ? OR c.ip_address LIKE ? OR c.reply_to_name LIKE ? OR c.content LIKE ? OR p.title LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term, $term, $term);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int)val('SELECT COUNT(*) FROM comments c INNER JOIN posts p ON p.id = c.post_id' . $whereSql, $params);
    $perPage = max(1, min(100, $perPage));
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $rows = all_rows(
        "SELECT c.*, p.kind AS post_kind, p.slug AS post_slug, p.title AS post_title
         FROM comments c
         INNER JOIN posts p ON p.id = c.post_id
         {$whereSql}
         ORDER BY c.is_read ASC, c.created_at DESC, c.id DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );

    return [$rows, $total, $page, $totalPages, $filter];
}

function comment_excerpt(string $content, int $length = 100): string
{
    $excerpt = trim((string)(preg_replace('/\s+/u', ' ', $content) ?? $content));
    return str_len_u($excerpt) > $length ? rtrim(str_sub_u($excerpt, 0, $length)) . '…' : $excerpt;
}

function comment_form_started_at(int $postId): int
{
    if (!isset($_SESSION['comment_forms']) || !is_array($_SESSION['comment_forms'])) {
        $_SESSION['comment_forms'] = [];
    }
    $cutoff = time() - 7200;
    foreach ($_SESSION['comment_forms'] as $storedPostId => $timestamps) {
        if (!is_array($timestamps)) {
            unset($_SESSION['comment_forms'][$storedPostId]);
            continue;
        }
        $_SESSION['comment_forms'][$storedPostId] = array_filter(
            $timestamps,
            static fn(mixed $value, int|string $timestamp): bool => (int)$timestamp >= $cutoff,
            ARRAY_FILTER_USE_BOTH
        );
        if ($_SESSION['comment_forms'][$storedPostId] === []) {
            unset($_SESSION['comment_forms'][$storedPostId]);
        }
    }
    $startedAt = time();
    $_SESSION['comment_forms'][$postId][(string)$startedAt] = true;
    return $startedAt;
}

function forget_comment_form(int $postId, int $startedAt): void
{
    unset($_SESSION['comment_forms'][$postId][(string)$startedAt]);
    if (empty($_SESSION['comment_forms'][$postId])) {
        unset($_SESSION['comment_forms'][$postId]);
    }
}

function set_comment_feedback(int $postId, array $form, array $errors): void
{
    $_SESSION['comment_feedback'][$postId] = ['form' => $form, 'errors' => $errors];
}

function pull_comment_feedback(int $postId): array
{
    $feedback = $_SESSION['comment_feedback'][$postId] ?? null;
    unset($_SESSION['comment_feedback'][$postId]);
    if (!is_array($feedback)) {
        return [[], []];
    }
    return [
        is_array($feedback['form'] ?? null) ? $feedback['form'] : [],
        is_array($feedback['errors'] ?? null) ? $feedback['errors'] : [],
    ];
}

function set_comment_notice(int $postId, string $type, string $message): void
{
    $_SESSION['comment_notices'][$postId] = ['type' => $type, 'message' => $message];
}

function pull_comment_notice(int $postId): ?array
{
    $notice = $_SESSION['comment_notices'][$postId] ?? null;
    unset($_SESSION['comment_notices'][$postId]);
    return is_array($notice) ? $notice : null;
}

function client_ip_hash(): string
{
    $address = client_ip_address();
    return hash_hmac('sha256', $address, DB_FILE !== '' ? DB_FILE : __FILE__);
}

function client_ip_address(): string
{
    $address = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($address, FILTER_VALIDATE_IP) ? $address : '';
}

function send_site_mail(string $recipient, string $subject, string $body): bool
{
    $recipient = trim($recipient);
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return plugin_filter('site_mail_send', false, [
        'recipient' => $recipient,
        'subject' => $subject,
        'body' => $body,
    ]) === true;
}

function send_comment_reply_notice(int $commentId): void
{
    $reply = one(
        "SELECT c.id, c.author_name, c.author_email, c.content, c.reply_notified_at,
                parent.author_name AS recipient_name, parent.author_email AS recipient_email,
                p.kind AS post_kind, p.slug AS post_slug, p.title AS post_title
         FROM comments c
         INNER JOIN comments parent ON parent.id = c.parent_id
         INNER JOIN posts p ON p.id = c.post_id
         WHERE c.id = ? AND c.status = 'approved'",
        [$commentId]
    );
    if (!$reply || (int)$reply['reply_notified_at'] > 0) {
        return;
    }

    $recipient = str_lower_u(trim((string)$reply['recipient_email']));
    $authorEmail = str_lower_u(trim((string)$reply['author_email']));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || ($authorEmail !== '' && $recipient === $authorEmail)) {
        q('UPDATE comments SET reply_notified_at = ? WHERE id = ? AND reply_notified_at = 0', [time(), $commentId]);
        return;
    }

    $siteName = setting('site_name', default_settings()['site_name']);
    $url = absolute_url(content_permalink(['kind' => (string)$reply['post_kind'], 'slug' => (string)$reply['post_slug']])) . '#comment-' . $commentId;
    $subject = sblog_t('[{site}] {author} 回复了你的评论', [
        'site' => $siteName,
        'author' => (string)$reply['author_name'],
    ]);
    $body = sblog_t('{recipient}，你好：', ['recipient' => (string)$reply['recipient_name']]) . "\n\n"
        . sblog_t('{author} 在《{post}》中回复了你：', [
            'author' => (string)$reply['author_name'],
            'post' => (string)$reply['post_title'],
        ]) . "\n\n"
        . (string)$reply['content'] . "\n\n"
        . sblog_t('查看回复：{url}', ['url' => $url]);

    if (send_site_mail($recipient, $subject, $body)) {
        q('UPDATE comments SET reply_notified_at = ? WHERE id = ? AND reply_notified_at = 0', [time(), $commentId]);
    }
}

function send_approved_reply_notices(array $commentIds): void
{
    foreach ($commentIds as $commentId) {
        try {
            send_comment_reply_notice((int)$commentId);
        } catch (Throwable $exception) {
            error_log('Reply notification failed: ' . $exception->getMessage());
        }
    }
}

function comment_rate_file(): string
{
    $address = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return CACHE_DIR . '/comment-' . hash('sha256', $address) . '.json';
}

function comment_rate_guard_file(): string
{
    return CACHE_DIR . '/comment-rate.lock';
}

function prune_comment_rate_files(): void
{
    $guard = @fopen(comment_rate_guard_file(), 'c+');
    if ($guard === false) {
        return;
    }
    if (!flock($guard, LOCK_EX | LOCK_NB)) {
        fclose($guard);
        return;
    }

    $checked = 0;
    $visited = 0;
    $cutoff = time() - 86400;
    rewind($guard);
    $storedCursor = trim((string)stream_get_contents($guard));
    $cursor = is_ascii_digits($storedCursor) ? (int)$storedCursor : 0;
    $nextCursor = $cursor;

    try {
        $iterator = new DirectoryIterator(CACHE_DIR);
        if ($cursor > 0) {
            $iterator->seek($cursor);
        }

        while ($iterator->valid() && $visited < 64 && $checked < 8) {
            $filename = $iterator->getFilename();
            $isRateFile = $iterator->isFile() && preg_match('/^comment-[a-f0-9]{64}\.json$/', $filename);
            $path = $isRateFile ? $iterator->getPathname() : '';
            $mtime = $isRateFile ? $iterator->getMTime() : 0;
            $iterator->next();
            $nextCursor++;
            $visited++;

            if (!$isRateFile) {
                continue;
            }
            $checked++;
            if ($mtime >= $cutoff) {
                continue;
            }

            $candidate = @fopen($path, 'r');
            if ($candidate === false) {
                continue;
            }
            if (flock($candidate, LOCK_EX | LOCK_NB)) {
                $stat = fstat($candidate);
                if (is_array($stat) && (int)($stat['mtime'] ?? PHP_INT_MAX) < $cutoff) {
                    @unlink($path);
                }
                flock($candidate, LOCK_UN);
            }
            fclose($candidate);
        }

        if (!$iterator->valid()) {
            $nextCursor = 0;
        }
    } catch (Throwable) {
        $nextCursor = 0;
    }

    $encodedCursor = (string)$nextCursor;
    if (ftruncate($guard, 0) && rewind($guard)) {
        $written = fwrite($guard, $encodedCursor);
        if (is_int($written) && $written === strlen($encodedCursor)) {
            fflush($guard);
        }
    }
    flock($guard, LOCK_UN);
    fclose($guard);
}

function record_comment_attempt(): bool
{
    ensure_runtime_dirs();
    prune_comment_rate_files();
    $guard = @fopen(comment_rate_guard_file(), 'c+');
    if ($guard === false) {
        return false;
    }
    if (!flock($guard, LOCK_SH)) {
        fclose($guard);
        return false;
    }

    $handle = @fopen(comment_rate_file(), 'c+');
    if ($handle === false) {
        flock($guard, LOCK_UN);
        fclose($guard);
        return false;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        flock($guard, LOCK_UN);
        fclose($guard);
        return false;
    }
    $raw = stream_get_contents($handle);
    $state = json_decode($raw ?: '', true);
    $now = time();
    if (!is_array($state) || $now - (int)($state['since'] ?? 0) >= 600) {
        $state = ['count' => 0, 'since' => $now];
    }
    $allowed = (int)$state['count'] < 3;
    if ($allowed) {
        $state['count'] = (int)$state['count'] + 1;
    }
    $encoded = json_encode($state);
    $stored = is_string($encoded) && rewind($handle);
    if ($stored) {
        $length = strlen($encoded);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($handle, substr($encoded, $offset));
            if (!is_int($written) || $written < 1) {
                $stored = false;
                break;
            }
            $offset += $written;
        }
        if ($stored) {
            $stored = ftruncate($handle, $length) && fflush($handle);
        }
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    flock($guard, LOCK_UN);
    fclose($guard);
    return $allowed && $stored;
}

function validate_comment_input(array $input, bool $requireEmail = true): array
{
    $name = trim((string)($input['author_name'] ?? ''));
    $name = trim((string)(preg_replace('/\s+/u', ' ', $name) ?? $name));
    $email = str_lower_u(trim((string)($input['author_email'] ?? '')));
    $url = trim((string)($input['author_url'] ?? ''));
    $content = trim(str_replace(["\r\n", "\r"], "\n", (string)($input['content'] ?? '')));
    $errors = [];
    $rawParentId = $input['parent_id'] ?? '';
    $parentText = is_scalar($rawParentId) ? trim((string)$rawParentId) : '';
    $parentId = 0;
    if (!is_scalar($rawParentId) || ($parentText !== '' && $parentText !== '0' && (!is_ascii_digits($parentText) || (int)$parentText < 1))) {
        $errors[] = '回复目标不存在或当前不可用。';
    } elseif ($parentText !== '' && $parentText !== '0') {
        $parentId = (int)$parentText;
    }

    if ($name === '') { $errors[] = '请填写昵称。'; }
    elseif (str_len_u($name) > 50) { $errors[] = '昵称不能超过 50 个字符。'; }
    if (($requireEmail && $email === '') || ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160))) { $errors[] = '请填写有效的邮箱地址。'; }
    if ($url !== '') {
        $scheme = str_lower_u((string)parse_url($url, PHP_URL_SCHEME));
        if (strlen($url) > 300 || !filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            $errors[] = '网站地址必须是有效的 HTTP 或 HTTPS 链接。';
        }
    }
    if ($content === '') { $errors[] = '请填写评论内容。'; }
    elseif (str_len_u($content) > 3000) { $errors[] = '评论内容不能超过 3000 个字符。'; }

    return [[
        'author_name' => str_sub_u($name, 0, 50),
        'author_email' => str_sub_u($email, 0, 160),
        'author_url' => str_sub_u($url, 0, 300),
        'content' => str_sub_u($content, 0, 3000),
        'parent_id' => (string)$parentId,
    ], $errors];
}

function duplicate_comment_error(int $postId, int $parentId, int $userId, string $email, string $content): string
{
    $identitySql = $userId > 0 ? 'user_id = ?' : 'user_id IS NULL AND author_email = ?';
    $identityValue = $userId > 0 ? $userId : $email;
    if ($parentId > 0) {
        $duplicate = (int)val(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND parent_id = ? AND ' . $identitySql . ' AND content = ? AND created_at >= ?',
            [$postId, $parentId, $identityValue, $content, time() - 86400]
        );
    } else {
        $duplicate = (int)val(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND parent_id IS NULL AND ' . $identitySql . ' AND content = ? AND created_at >= ?',
            [$postId, $identityValue, $content, time() - 86400]
        );
    }
    return $duplicate > 0 ? '这条评论已经提交过了。' : '';
}

function post_neighbors(array $post): array
{
    if (!is_live_post($post)) {
        return ['newer' => null, 'older' => null];
    }

    $publishedAt = (int)$post['published_at'];
    $id = (int)$post['id'];

    $newer = one(
        'SELECT id, slug, title FROM posts
         WHERE kind = ? AND status = ? AND published_at <= ? AND (published_at > ? OR (published_at = ? AND id > ?))
         ORDER BY published_at ASC, id ASC LIMIT 1',
        ['post', 'published', time(), $publishedAt, $publishedAt, $id]
    );

    $older = one(
        'SELECT id, slug, title FROM posts
         WHERE kind = ? AND status = ? AND published_at <= ? AND (published_at < ? OR (published_at = ? AND id < ?))
         ORDER BY published_at DESC, id DESC LIMIT 1',
        ['post', 'published', time(), $publishedAt, $publishedAt, $id]
    );

    return ['newer' => $newer, 'older' => $older];
}

function fetch_nav_pages(): array
{
    return all_rows(
        'SELECT id, slug, title, kind, status, published_at, updated_at, created_at FROM posts
         WHERE kind = ? AND status = ? AND published_at <= ?
         ORDER BY published_at ASC, id ASC LIMIT 6',
        ['page', 'published', time()]
    );
}

function fetch_feed_posts(int $limit = 20): array
{
    return all_rows(
        'SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC LIMIT ' . max(1, $limit),
        ['post', 'published', time()]
    );
}

function tag_index_data(bool $publishedOnly = true): array
{
    $map = [];
    $posts = $publishedOnly
        ? all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC', ['post', 'published', time()])
        : all_rows('SELECT * FROM posts WHERE kind = ? ORDER BY updated_at DESC, id DESC', ['post']);

    foreach ($posts as $post) {
        foreach (post_tags($post) as $label) {
            $slug = tag_slug_for_label($label);
            if (!isset($map[$slug])) {
                $map[$slug] = ['slug' => $slug, 'label' => $label, 'count' => 0];
            }
            $map[$slug]['count']++;
        }
    }

    $tags = array_values($map);
    usort(
        $tags,
        static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcmp((string)$a['label'], (string)$b['label']);
        }
    );

    return $tags;
}

function fetch_posts_by_tag_slug(string $slug): array
{
    $slug = trim($slug);
    if ($slug === '') {
        return [];
    }

    $matches = [];
    $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', 'published', time()]);

    foreach ($posts as $post) {
        foreach (tag_descriptors($post) as $tag) {
            if ($tag['slug'] === $slug) {
                $matches[] = $post;
                break;
            }
        }
    }

    return $matches;
}

function tag_label_by_slug(string $slug): ?string
{
    foreach (tag_index_data() as $tag) {
        if ((string)$tag['slug'] === $slug) {
            return (string)$tag['label'];
        }
    }

    return null;
}

function validate_post_input(array $input, ?array $existing = null): array
{
    $title = trim((string)($input['title'] ?? ''));
    $content = trim((string)($input['content'] ?? ''));
    $excerpt = trim((string)($input['excerpt'] ?? ''));
    $kind = (string)($input['kind'] ?? 'post');
    $categoryId = (int)($input['category_id'] ?? 0);
    $tagsInput = trim((string)($input['tags_input'] ?? ''));
    $status = (string)($input['status'] ?? 'draft');
    $publishedInput = trim((string)($input['published_at'] ?? ''));
    $isPinned = isset($input['is_pinned']) && (string)$input['is_pinned'] === '1' ? 1 : 0;
    $allowComments = isset($input['allow_comments']) && (string)$input['allow_comments'] === '1' ? 1 : 0;
    $errors = [];

    if ($title === '') {
        $errors[] = '标题不能为空。';
    }

    if ($content === '') {
        $errors[] = '正文不能为空。';
    }

    $kind = $kind === 'page' ? 'page' : 'post';
    $categoryId = $kind === 'post' && $categoryId > 0 && one('SELECT id FROM categories WHERE id = ?', [$categoryId]) ? $categoryId : null;
    if ($kind === 'post' && $categoryId === null) {
        $errors[] = '文章必须选择一个分类。';
    }
    $status = $status === 'published' ? 'published' : 'draft';
    $publishedAt = (int)($existing['published_at'] ?? 0);

    if ($publishedInput !== '') {
        $parsed = strtotime(str_replace('T', ' ', $publishedInput));
        if ($parsed === false) {
            $errors[] = '发布时间格式不正确。';
        } else {
            $publishedAt = $parsed;
        }
    }

    if ($status === 'published' && $publishedAt < 1) {
        $publishedAt = time();
    }

    $seed = trim((string)($input['slug'] ?? ''));
    if ($errors === [] && ($seed === '' || $excerpt === '')) {
        $defaultFields = plugin_filter('post_fields_before_defaults', [
            'slug' => $seed,
            'excerpt' => $excerpt,
        ], [
            'title' => $title,
            'content' => $content,
            'kind' => $kind,
            'post_id' => $existing ? (int)$existing['id'] : null,
        ]);
        if (is_array($defaultFields)) {
            if ($seed === '' && is_string($defaultFields['slug'] ?? null)) {
                $seed = trim($defaultFields['slug']);
            }
            if ($excerpt === '' && is_string($defaultFields['excerpt'] ?? null)) {
                $excerpt = trim($defaultFields['excerpt']);
            }
        }
    }
    $slug = unique_slug($seed !== '' ? $seed : $title, $existing ? (int)$existing['id'] : null);
    $excerpt = $excerpt !== '' ? $excerpt : derive_excerpt($content);
    $tags = encode_tags(parse_tags_input($tagsInput));

    return [[
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'content' => $content,
        'kind' => $kind,
        'category_id' => $categoryId,
        'tags' => $tags,
        'status' => $status,
        'published_at' => $publishedAt,
        'is_pinned' => $kind === 'post' ? $isPinned : 0,
        'allow_comments' => $kind === 'page' ? $allowComments : 1,
    ], $errors];
}

function save_post(array $data, ?int $id = null): int
{
    $filteredData = plugin_filter('post_data_before_save', $data, ['post_id' => $id]);
    if (is_array($filteredData)) {
        $data = $filteredData;
    }
    $values = [
        $data['kind'],
        $data['category_id'],
        $data['slug'],
        $data['title'],
        $data['tags'],
        $data['excerpt'],
        $data['content'],
        $data['status'],
        $data['published_at'],
        $data['is_pinned'],
        $data['allow_comments'],
    ];
    $now = time();

    if ($id !== null) {
        q(
            'UPDATE posts SET kind = ?, category_id = ?, slug = ?, title = ?, tags = ?, excerpt = ?, content = ?, status = ?, published_at = ?, is_pinned = ?, allow_comments = ?, updated_at = ? WHERE id = ?',
            array_merge($values, [$now, $id])
        );
        plugin_action('post_saved', ['post_id' => $id, 'created' => false, 'data' => $data]);
        return $id;
    }

    q(
        'INSERT INTO posts(author_id, kind, category_id, slug, title, tags, excerpt, content, status, published_at, is_pinned, allow_comments, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array_merge([(int)(current_admin()['id'] ?? 0)], $values, [$now, $now])
    );
    $postId = (int)db()->lastInsertId();
    plugin_action('post_saved', ['post_id' => $postId, 'created' => true, 'data' => $data]);
    return $postId;
}

function post_form_from_request(array $input): array
{
    return [
        'kind' => (string)($input['kind'] ?? 'post'),
        'category_id' => (string)($input['category_id'] ?? ''),
        'title' => (string)($input['title'] ?? ''),
        'slug' => (string)($input['slug'] ?? ''),
        'tags_input' => (string)($input['tags_input'] ?? ''),
        'excerpt' => (string)($input['excerpt'] ?? ''),
        'content' => (string)($input['content'] ?? ''),
        'status' => (string)($input['status'] ?? 'draft'),
        'published_at' => (string)($input['published_at'] ?? ''),
        'is_pinned' => isset($input['is_pinned']) ? '1' : '0',
        'allow_comments' => isset($input['allow_comments']) ? '1' : '0',
    ];
}

function render_layout(string $title, string $content, array $options = []): void
{
    $siteName = setting('site_name', default_settings()['site_name']);
    $fullTitle = $title === $siteName ? $siteName : $title . ' · ' . $siteName;
    $description = (string)($options['description'] ?? setting('site_description', setting('site_tagline')));
    $keywords = trim(setting('site_keywords'));
    $active = (string)($options['active'] ?? '');
    $wide = !empty($options['wide']);
    $mode = (string)($options['mode'] ?? 'admin');
    $flash = pull_flash();
    $admin = current_admin();
    $navPages = fetch_nav_pages();
    $status = (int)($options['status'] ?? 200);
    $bodyClass = $mode === 'public' ? 'theme-public' : 'theme-admin';
    $customHeadCode = $mode === 'public' ? trim(setting('custom_head_code')) : '';
    $theme = theme_manifest('default');
    $themeContext = [];
    $themeStyleUrl = '';

    if ($mode !== 'public' && !$admin) {
        $bodyClass .= ' theme-admin--guest';
    }

    if ($mode === 'public') {
        load_active_theme();
        $theme = active_theme();
        $themeContext = [
            'title' => $title,
            'full_title' => $fullTitle,
            'description' => $description,
            'content' => $content,
            'options' => $options,
            'site_name' => $siteName,
            'active' => $active,
            'admin' => $admin,
            'nav_pages' => $navPages,
            'theme' => $theme,
        ];
        $fullTitle = (string)theme_filter('document_title', $fullTitle, $themeContext);
        $description = (string)theme_filter('description', $description, $themeContext);
        $bodyClass = trim((string)theme_filter('body_class', $bodyClass, $themeContext));
        $content = (string)theme_filter('content', $content, $themeContext);
        $themeStyleFile = active_theme_file('style.css');
        $themeStyleUrl = $themeStyleFile !== '' ? theme_asset_url('style.css') . '?v=' . rawurlencode((string)filemtime($themeStyleFile)) : '';
        $themeContext = array_merge($themeContext, [
            'full_title' => $fullTitle,
            'description' => $description,
            'content' => $content,
            'body_class' => $bodyClass,
            'style_url' => $themeStyleUrl,
            'flash' => $flash,
        ]);
    }

    http_response_code($status);
    if (!headers_sent()) {
        header('Content-Language: ' . sblog_i18n_locale());
    }

    $themeLayout = $mode === 'public' ? active_theme_file('layout.php') : '';
    if ($themeLayout !== '') {
        ob_start();
        try {
            require $themeLayout;
            echo (string)ob_get_clean();
            exit;
        } catch (Throwable $exception) {
            ob_end_clean();
            error_log('Theme layout failed: ' . $exception->getMessage());
        }
    }
    ?>
<!doctype html>
<html lang="<?= h(sblog_i18n_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <title><?= h($fullTitle) ?></title>
  <?php if ($mode !== 'public'): ?>
  <meta name="color-scheme" content="light dark">
  <script>
    (() => {
      const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      try {
        const saved = localStorage.getItem('sblog-admin-theme');
        document.documentElement.dataset.adminTheme = saved === 'light' || saved === 'dark'
          ? saved
          : (systemDark ? 'dark' : 'light');
      } catch (error) {
        document.documentElement.dataset.adminTheme = systemDark ? 'dark' : 'light';
      }
    })();
  </script>
  <?php endif; ?>
  <?= sblog_i18n_head() ?>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(asset_url('index.css')) ?>?v=<?= h(APP_VERSION) ?>">
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
  <?php if ($mode === 'public') { theme_action('head', $themeContext); } ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php if ($mode === 'public'): ?>
    <?php theme_action('body_open', $themeContext); ?>
    <div class="crt-turn-on" id="turn-on"></div><div class="crt-vignette"></div><div class="scanlines" id="scanlines"></div><div class="crt-flicker"></div>
    <div class="terminal" data-home="<?= h(url_for('home')) ?>" data-tags="<?= h(url_for('tags')) ?>" data-links="<?= h(url_for('links')) ?>" data-archives="<?= h(url_for('archives')) ?>">
      <?php theme_action('header_before', $themeContext); ?>
      <header class="terminal-header"><div class="window-controls"><span class="dot red"></span><span class="dot yellow"></span><span class="dot green"></span></div><div class="title">visitor@<?= h($siteName) ?>: ~ — devlog-sh 0.9</div><div class="info"><span class="signal"></span><span id="term-info">80×24</span></div></header>
      <?php theme_action('header_after', $themeContext); ?>
      <main class="output" id="output" aria-live="polite">
        <div class="boot-banner"><b><?= h($siteName) ?> <?= h(APP_VERSION) ?> — <?= h(public_quote()) ?></b><br><span>type "help" to begin · type "ls" to look around</span></div>
        <nav class="terminal-menu" aria-label="<?= h(sblog_t('主菜单')) ?>">
          <span class="terminal-menu__label">menu:</span>
          <a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="<?= h(url_for('home')) ?>">[<?= h(sblog_t('首页')) ?>]</a>
          <a class="<?= $active === 'tags' ? 'is-active' : '' ?>" href="<?= h(url_for('tags')) ?>">[<?= h(sblog_t('标签')) ?>]</a>
          <a class="<?= $active === 'archives' ? 'is-active' : '' ?>" href="<?= h(url_for('archives')) ?>">[<?= h(sblog_t('归档')) ?>]</a>
          <a class="<?= $active === 'links' ? 'is-active' : '' ?>" href="<?= h(url_for('links')) ?>">[<?= h(sblog_t('链接')) ?>]</a>
          <?php $adminLinkRendered = false; ?>
          <?php foreach ($navPages as $page): ?>
            <a class="<?= $active === 'page:' . $page['slug'] ? 'is-active' : '' ?>" href="<?= h(content_permalink($page)) ?>">[<?= h($page['title']) ?>]</a>
            <?php if ($admin && !$adminLinkRendered && (strtolower((string)$page['slug']) === 'about' || trim((string)$page['title']) === '关于')): ?>
              <a class="<?= $active === 'admin' ? 'is-active' : '' ?>" href="<?= h(url_for('admin')) ?>">[<?= h(sblog_t('管理')) ?>]</a>
              <?php $adminLinkRendered = true; ?>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if ($admin && !$adminLinkRendered): ?>
            <a class="<?= $active === 'admin' ? 'is-active' : '' ?>" href="<?= h(url_for('admin')) ?>">[<?= h(sblog_t('管理')) ?>]</a>
          <?php endif; ?>
        </nav>
        <div class="cmd-echo"><span class="prompt-part">visitor@<?= h($siteName) ?></span><span class="path-part">:~</span>$ cat <?= h(strtolower(str_replace(' ', '-', $title))) ?>.md</div>
        <?php if ($flash): ?><div class="line amber"><?= h((string)$flash['message']) ?></div><?php endif; ?>
        <?php theme_action('content_before', $themeContext); ?>
        <section class="md-content"><?= $content ?></section>
        <?php theme_action('content_after', $themeContext); ?>
        <div class="line dim">-- EOF --</div>
        <?php theme_action('footer_before', $themeContext); ?>
        <footer class="terminal-footer">
          <span><?= h(site_footer_text()) ?></span>
          <?php $beian = trim(setting('footer_beian')); ?>
          <?php if ($beian !== ''): ?>
            <span class="terminal-footer__separator">·</span>
            <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a>
          <?php endif; ?>
          <span class="terminal-footer__separator">·</span>
          <a href="<?= h(url_for('rss')) ?>"><?= h(sblog_t('RSS')) ?></a>
          <span class="terminal-footer__separator">·</span>
          <a href="<?= h(url_for('sitemap')) ?>"><?= h(sblog_t('Sitemap')) ?></a>
        </footer>
        <?php theme_action('footer_after', $themeContext); ?>
      </main>
      <footer class="prompt-line"><span class="prompt"><span>visitor@<?= h($siteName) ?></span><span class="path" id="prompt-path">:~</span><span class="symbol">$</span>&nbsp;</span><span class="input-text" id="input-text"></span><span class="cursor"></span><span class="ghost-text" id="ghost-text"></span><input id="input" type="text" autofocus autocomplete="off" spellcheck="false" aria-label="<?= h(sblog_t('终端输入')) ?>"></footer>
    </div>
  <?php else: ?>
    <div class="site-frame">
      <header class="site-header">
        <div class="site-header__inner">
          <a class="site-brand" href="<?= h($admin ? url_for('admin') : url_for('home')) ?>">
            <img class="site-brand__logo" src="<?= h(theme_logo_url()) ?>" width="44" height="44" alt="<?= h($siteName) ?>">
            <span class="site-brand__copy">
              <strong class="site-brand__title"><?= h($siteName) ?></strong>
              <span class="site-brand__meta"><?= h($admin ? sblog_t('Simple-PHP-Blog Admin') : sblog_t('管理后台')) ?></span>
            </span>
          </a>
          <?php if ($admin): ?>
            <nav class="site-nav site-nav--admin" aria-label="<?= h(sblog_t('主导航')) ?>">
              <a class="nav-link<?= $active === 'admin' ? ' is-active' : '' ?>" href="<?= h(url_for('admin')) ?>"><?= h(sblog_t('管理后台')) ?></a>
              <a class="nav-link nav-link--pill<?= in_array($active, ['write', 'edit'], true) ? ' is-active' : '' ?>" href="<?= h(url_for('write')) ?>"><?= h(sblog_t('撰写文章')) ?></a>
              <form class="nav-logout-form" method="post" action="<?= h(url_for('logout')) ?>">
                <?= csrf_field() ?>
                <button class="nav-link" type="submit"><?= h(sblog_t('退出')) ?></button>
              </form>
            </nav>
          <?php else: ?>
            <?= render_admin_theme_toggle() ?>
          <?php endif; ?>
        </div>
      </header>

      <main class="main-wrap<?= $wide ? ' main-wrap--wide' : '' ?>">
        <?php if (!$admin): ?>
          <div class="auth-stage">
            <?php if ($flash): ?>
              <?php $flashType = (string)$flash['type']; ?>
              <div class="flash flash--<?= h($flashType) ?> auth-notice" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>">
                <span class="auth-notice__icon"><?= admin_icon($flashType === 'success' ? 'check-circle' : 'alert-circle') ?></span>
                <span class="auth-notice__message"><?= h((string)$flash['message']) ?></span>
              </div>
            <?php endif; ?>
            <?= $content ?>
          </div>
        <?php else: ?>
          <?php if ($flash): ?>
            <div class="flash flash--<?= h((string)$flash['type']) ?>"><?= h((string)$flash['message']) ?></div>
          <?php endif; ?>
          <?= $content ?>
        <?php endif; ?>
      </main>

      <footer class="site-footer">
        <div class="site-footer__inner">
          <span><?= h(site_footer_text()) ?></span>
          <span class="site-footer__meta"><?= h(sblog_t('Powered by Simple PHP Blog {version}', ['version' => APP_VERSION])) ?></span>
        </div>
      </footer>
    </div>
  <?php endif; ?>
  <script src="<?= h(asset_url('index.js')) ?>?v=<?= h(APP_VERSION) ?>"></script>
  <?php if ($mode === 'public') { theme_action('body_close', $themeContext); } ?>
</body>
</html>
<?php
    exit;
}

function admin_icon(string $name): string
{
    $attrs = 'class="admin-side__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

    $paths = match ($name) {
        'overview' => '<rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect>',
        'home' => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path>',
        'write' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>',
        'media' => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="8.5" cy="9" r="1.5"></circle><path d="m21 15-5-5L5 20"></path><path d="m14 14-2-2-6 6"></path>',
        'posts' => '<path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path>',
        'comments' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path>',
        'categories' => '<path d="M3 6h7l2 2h9v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"></path>',
        'tags' => '<path d="M12.6 2.6H5a2.4 2.4 0 0 0-2.4 2.4v7.6a2.4 2.4 0 0 0 .7 1.7l6.4 6.4a2.4 2.4 0 0 0 3.4 0l7.6-7.6a2.4 2.4 0 0 0 0-3.4l-6.4-6.4a2.4 2.4 0 0 0-1.7-.7z"></path><circle cx="8" cy="8" r="1"></circle>',
        'links' => '<path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"></path><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7.1 7.1l1.1-1.1"></path>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'ai' => '<path d="m12 3-1.4 3.6L7 8l3.6 1.4L12 13l1.4-3.6L17 8l-3.6-1.4L12 3z"></path><path d="m5 14-.8 2.2L2 17l2.2.8L5 20l.8-2.2L8 17l-2.2-.8L5 14z"></path><path d="m19 13-1 2.5-2.5 1 2.5 1L19 20l1-2.5 2.5-1-2.5-1L19 13z"></path>',
        'mail' => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"></path><path d="m22 6-10 7L2 6"></path>',
        'storage' => '<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5v6c0 1.7 4 3 9 3s9-1.3 9-3V5"></path><path d="M3 11v6c0 1.7 4 3 9 3s9-1.3 9-3v-6"></path>',
        'themes' => '<path d="M12 3a9 9 0 1 0 0 18h1.5a2 2 0 0 0 0-4H12a2 2 0 0 1 0-4h2a7 7 0 0 0 0-14h-2z"></path><circle cx="7.5" cy="10" r=".5"></circle><circle cx="9" cy="6.5" r=".5"></circle><circle cx="14" cy="6.5" r=".5"></circle><circle cx="16.5" cy="10" r=".5"></circle>',
        'settings' => '<path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 1 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9L4.2 7A2 2 0 1 1 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5h.1a1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"></path>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"></path>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"></path>',
        'sun' => '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"></path>',
        'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle>',
        'eye-off' => '<path d="m3 3 18 18"></path><path d="M10.6 5.2A11.7 11.7 0 0 1 12 5c6.5 0 10 7 10 7a18.7 18.7 0 0 1-2.2 3.1"></path><path d="M6.6 6.6C3.6 8.6 2 12 2 12s3.5 7 10 7a9.8 9.8 0 0 0 4.1-.9"></path><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>',
        'alert-circle' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path>',
        'check-circle' => '<circle cx="12" cy="12" r="9"></circle><path d="m8 12 2.5 2.5L16 9"></path>',
        'refresh' => '<path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 4v5h5"></path><path d="M4 13a8.1 8.1 0 0 0 15.5 2M20 20v-5h-5"></path>',
        'pin' => '<path d="M12 17v5"></path><path d="M5 17h14"></path><path d="M6 17h12v-2a4 4 0 0 0-4-4V5l1-2H9l1 2v6a4 4 0 0 0-4 4v2Z"></path>',
        'plugins' => '<path d="M8.5 3H5a2 2 0 0 0-2 2v3.5a2.5 2.5 0 1 1 0 5V19a2 2 0 0 0 2 2h3.5a2.5 2.5 0 1 1 5 0H19a2 2 0 0 0 2-2v-5.5a2.5 2.5 0 1 1 0-5V5a2 2 0 0 0-2-2h-5.5a2.5 2.5 0 1 1-5 0z"></path>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path>',
        default => '<circle cx="12" cy="12" r="8"></circle>',
    };

    return '<svg ' . $attrs . '>' . $paths . '</svg>';
}

function render_admin_theme_toggle(): string
{
    $label = h(sblog_t('切换到深色模式'));
    return '<button class="admin-icon-btn admin-theme-toggle" type="button" data-admin-theme-toggle aria-label="' . $label . '" title="' . $label . '">'
        . '<span class="admin-theme-toggle__icons" aria-hidden="true">'
        . '<span class="admin-theme-toggle__icon admin-theme-toggle__icon--moon">' . admin_icon('moon') . '</span>'
        . '<span class="admin-theme-toggle__icon admin-theme-toggle__icon--sun">' . admin_icon('sun') . '</span>'
        . '</span></button>';
}

function render_admin_sidebar(string $active, array $summary = []): string
{
    $siteName = setting('site_name', default_settings()['site_name']);
    $admin = current_admin();
    $adminName = (string)($admin['username'] ?? 'Admin');
    $adminAvatarUrl = trim((string)($admin['avatar_url'] ?? ''));
    $adminInitial = str_sub_u($adminName, 0, 1);
    $userSettingsUrl = url_for('admin_users');
    $unreadComments = unread_comment_count();
    $links = [
        [
            'label' => '博客概览',
            'icon' => 'overview',
            'note' => '浏览与统计',
            'href' => url_for('admin'),
            'active' => $active === 'admin',
        ],
        [
            'label' => '撰写文章',
            'icon' => 'write',
            'note' => '发布文章或页面',
            'href' => url_for('write'),
            'active' => in_array($active, ['write', 'edit'], true),
        ],
        [
            'label' => '媒体库',
            'icon' => 'media',
            'note' => '上传与文件管理',
            'href' => url_for('admin_media'),
            'active' => $active === 'media',
        ],
        [
            'label' => '文章管理',
            'icon' => 'posts',
            'note' => '列表与发布',
            'href' => url_for('admin_posts'),
            'active' => $active === 'posts',
        ],
        [
            'label' => '评论管理',
            'icon' => 'comments',
            'note' => '审核与通知',
            'href' => url_for('admin_comments'),
            'active' => $active === 'comments',
            'badge' => $unreadComments,
        ],
        [
            'label' => '分类管理',
            'icon' => 'categories',
            'note' => '分类与排序',
            'href' => url_for('admin_categories'),
            'active' => $active === 'categories',
        ],
        [
            'label' => '标签管理',
            'icon' => 'tags',
            'note' => '重命名与清理',
            'href' => url_for('admin_tags'),
            'active' => $active === 'tags',
        ],
        [
            'label' => '友情链接',
            'icon' => 'links',
            'note' => '添加、排序与维护',
            'href' => url_for('admin_links'),
            'active' => $active === 'links',
        ],
        [
            'label' => '主题管理',
            'icon' => 'themes',
            'note' => '预览与切换',
            'href' => url_for('admin_themes'),
            'active' => $active === 'themes',
        ],
        [
            'label' => '插件管理',
            'icon' => 'plugins',
            'note' => '扩展与语言包',
            'href' => url_for('admin_plugins'),
            'active' => $active === 'plugins',
        ],
        [
            'label' => '站点设置',
            'icon' => 'settings',
            'note' => '基础配置',
            'href' => url_for('admin_settings'),
            'active' => $active === 'settings',
        ],
    ];
    $filteredLinks = plugin_filter('admin_sidebar_links', $links, ['active' => $active, 'admin' => $admin]);
    if (is_array($filteredLinks)) {
        $links = $filteredLinks;
    }

    ob_start();
    ?>
    <button class="admin-side-backdrop" type="button" data-admin-nav-close tabindex="-1" aria-hidden="true"></button>
    <aside class="admin-side admin-animate admin-animate--1" id="admin-sidebar" aria-label="<?= h(sblog_t('后台导航')) ?>">
      <button class="admin-icon-btn admin-side__close" type="button" data-admin-nav-close aria-label="<?= h(sblog_t('关闭后台菜单')) ?>" title="<?= h(sblog_t('关闭后台菜单')) ?>">
        <?= admin_icon('close') ?>
      </button>
      <a class="admin-side__brand" href="<?= h(url_for('admin')) ?>" title="<?= h($siteName) ?>" aria-label="<?= h($siteName) ?>">
        <?= admin_icon('home') ?>
        <span class="admin-side__brand-text"><?= h($siteName) ?></span>
      </a>

      <section class="admin-side__panel admin-side__panel--nav">
        <p class="admin-side__eyebrow"><?= h(sblog_t('管理导航')) ?></p>
        <nav class="admin-side__nav" aria-label="<?= h(sblog_t('后台导航')) ?>">
          <?php foreach ($links as $link): ?>
            <?php $linkBadge = (int)($link['badge'] ?? 0); ?>
            <?php $linkDisplayLabel = match ((string)$link['label']) {
                '博客概览' => sblog_t('博客概览'),
                '撰写文章' => sblog_t('撰写文章'),
                '媒体库' => sblog_t('媒体库'),
                '文章管理' => sblog_t('文章管理'),
                '评论管理' => sblog_t('评论管理'),
                '分类管理' => sblog_t('分类管理'),
                '标签管理' => sblog_t('标签管理'),
                '友情链接' => sblog_t('友情链接'),
                '主题管理' => sblog_t('主题管理'),
                '插件管理' => sblog_t('插件管理'),
                '站点设置' => sblog_t('站点设置'),
                default => (string)$link['label'],
            }; ?>
            <?php $linkDisplayNote = match ((string)$link['note']) {
                '浏览与统计' => sblog_t('浏览与统计'),
                '发布文章或页面' => sblog_t('发布文章或页面'),
                '上传与文件管理' => sblog_t('上传与文件管理'),
                '列表与发布' => sblog_t('列表与发布'),
                '审核与通知' => sblog_t('审核与通知'),
                '分类与排序' => sblog_t('分类与排序'),
                '重命名与清理' => sblog_t('重命名与清理'),
                '添加、排序与维护' => sblog_t('添加、排序与维护'),
                '预览与切换' => sblog_t('预览与切换'),
                '扩展与语言包' => sblog_t('扩展与语言包'),
                '基础配置' => sblog_t('基础配置'),
                default => (string)$link['note'],
            }; ?>
            <?php $linkLabel = $linkBadge > 0
                ? sblog_tn('{label}，{count} 条未读评论', $linkBadge, ['label' => $linkDisplayLabel])
                : $linkDisplayLabel; ?>
            <a class="admin-side__link<?= $link['active'] ? ' is-active' : '' ?>" href="<?= h((string)$link['href']) ?>" title="<?= h($linkDisplayLabel) ?>" aria-label="<?= h($linkLabel) ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?>>
              <?= admin_icon((string)$link['icon']) ?>
              <strong><?= h($linkDisplayLabel) ?></strong>
              <span><?= h($linkDisplayNote) ?></span>
              <?php if ($linkBadge > 0): ?>
                <small class="admin-count-badge" aria-hidden="true"><?= h((string)min(99, $linkBadge)) ?><?= $linkBadge > 99 ? '+' : '' ?></small>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </nav>
      </section>

      <?php if ($summary !== []): ?>
        <section class="admin-side__panel admin-side__panel--subtle">
          <p class="admin-side__eyebrow"><?= h((string)($summary['title'] ?? sblog_t('说明'))) ?></p>

          <?php if (!empty($summary['stats']) && is_array($summary['stats'])): ?>
            <dl class="admin-side__stats">
              <?php foreach ($summary['stats'] as $item): ?>
                <?php if (!is_array($item)) { continue; } ?>
                <div>
                  <dt><?= h((string)($item['label'] ?? '')) ?></dt>
                  <dd><?= h((string)($item['value'] ?? '')) ?></dd>
                </div>
              <?php endforeach; ?>
            </dl>
          <?php elseif (!empty($summary['items']) && is_array($summary['items'])): ?>
            <ul class="admin-side__list">
              <?php foreach ($summary['items'] as $item): ?>
                <li><?= h((string)$item) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <div class="admin-side__footer">
        <details class="admin-side__account" data-admin-account>
          <summary class="admin-side__account-toggle" aria-label="<?= h(sblog_t('打开用户菜单：{name}', ['name' => $adminName])) ?>">
            <span class="admin-side__avatar">
              <span aria-hidden="true"><?= h($adminInitial) ?></span>
              <?php if ($adminAvatarUrl !== ''): ?>
                <img src="<?= h($adminAvatarUrl) ?>" alt="" decoding="async" onerror="this.remove()">
              <?php endif; ?>
            </span>
            <span class="admin-side__footer-text"><?= h($adminName) ?></span>
            <span class="admin-side__account-caret" aria-hidden="true"></span>
          </summary>
          <div class="admin-side__account-menu" role="menu">
            <a class="admin-side__account-item" role="menuitem" href="<?= h($userSettingsUrl) ?>">
              <?= admin_icon('users') ?>
              <span><?= h(sblog_t('用户设置')) ?></span>
            </a>
            <form class="admin-side__logout-form" method="post" action="<?= h(url_for('logout')) ?>">
              <?= csrf_field() ?>
              <button class="admin-side__account-item admin-side__account-item--danger" role="menuitem" type="submit">
                <?= admin_icon('logout') ?>
                <span><?= h(sblog_t('退出登录')) ?></span>
              </button>
            </form>
          </div>
        </details>
      </div>
    </aside>
    <?php

    return (string)ob_get_clean();
}

function render_admin_topbar(string $title, string $actionLabel = '', string $actionUrl = ''): string
{
    $unreadComments = unread_comment_count();
    $notificationUrl = $unreadComments > 0 ? admin_comments_url('unread') : url_for('admin_comments');
    $flash = pull_flash();
    $displayTitle = match ($title) {
        '博客数据预览' => sblog_t('博客数据预览'),
        '文章管理' => sblog_t('文章管理'),
        '评论管理' => sblog_t('评论管理'),
        '分类管理' => sblog_t('分类管理'),
        '友情链接' => sblog_t('友情链接'),
        '标签管理' => sblog_t('标签管理'),
        '用户设置' => sblog_t('用户设置'),
        '媒体库' => sblog_t('媒体库'),
        '插件管理' => sblog_t('插件管理'),
        '主题管理' => sblog_t('主题管理'),
        '站点设置' => sblog_t('站点设置'),
        '编辑内容' => sblog_t('编辑内容'),
        '撰写文章' => sblog_t('撰写文章'),
        default => $title,
    };
    ob_start();
    ?>
    <div class="admin-topbar">
      <div class="admin-topbar__leading">
        <button class="admin-icon-btn admin-nav-toggle" type="button" data-admin-nav-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="<?= h(sblog_t('打开后台菜单')) ?>" title="<?= h(sblog_t('打开后台菜单')) ?>">
          <?= admin_icon('menu') ?>
        </button>
        <div class="admin-crumb"><span><?= h(sblog_t('控制台 /')) ?></span> <b><?= h($displayTitle) ?></b></div>
      </div>
      <div class="admin-topbar__actions">
        <?= render_admin_theme_toggle() ?>
        <form class="admin-update-check" method="post" action="<?= h(url_for('check_update')) ?>">
          <?= csrf_field() ?>
          <button class="button button--secondary button--compact" type="submit" aria-label="<?= h(sblog_t('检测更新')) ?>" title="<?= h(sblog_t('检测更新')) ?>">
            <?= admin_icon('refresh') ?>
            <span><?= h(sblog_t('检测更新')) ?></span>
          </button>
        </form>
        <a class="admin-icon-btn admin-icon-btn--notifications" href="<?= h($notificationUrl) ?>" title="<?= h(sblog_t('评论通知')) ?>" aria-label="<?= h($unreadComments > 0 ? sblog_tn('{count} 条未读评论', $unreadComments) : sblog_t('暂无未读评论')) ?>">
          <?= admin_icon('bell') ?>
          <?php if ($unreadComments > 0): ?><small class="admin-count-badge"><?= h((string)min(99, $unreadComments)) ?><?= $unreadComments > 99 ? '+' : '' ?></small><?php endif; ?>
        </a>
        <a class="admin-icon-btn" href="<?= h(url_for('home')) ?>" target="_blank" rel="noopener noreferrer" title="<?= h(sblog_t('网站首页')) ?>" aria-label="<?= h(sblog_t('网站首页')) ?>">
          <?= admin_icon('home') ?>
        </a>
        <?php if ($actionLabel !== '' && $actionUrl !== ''): ?>
          <a class="button" href="<?= h($actionUrl) ?>"><?= h($actionLabel) ?></a>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($flash): ?>
      <?php $flashType = (string)($flash['type'] ?? 'success'); ?>
      <div class="flash flash--<?= h($flashType) ?> admin-global-notice" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>">
        <span class="admin-global-notice__icon"><?= admin_icon($flashType === 'success' ? 'check-circle' : 'alert-circle') ?></span>
        <span><?= h((string)($flash['message'] ?? '')) ?></span>
      </div>
    <?php endif; ?>
    <?php
    return (string)ob_get_clean();
}

function simple_error_page(string $title, string $message, int $status = 400): void
{
    ob_start();
    ?>
    <article class="post">
      <h1 class="post-title"><?= h($title) ?></h1>
      <div class="post-content">
        <p><?= h($message) ?></p>
        <p><a href="<?= h(url_for('home')) ?>"><?= h(sblog_t('回到首页')) ?></a></p>
      </div>
    </article>
    <?php
    $content = (string)ob_get_clean();

    render_layout($title, $content, [
        'mode' => 'public',
        'status' => $status,
        'description' => $message,
    ]);
}

function render_tag_chips(array $post, string $class = 'post-tag-list'): string
{
    $tags = tag_descriptors($post);
    if ($tags === []) {
        return '';
    }

    ob_start();
    ?>
    <div class="<?= h($class) ?>">
      <?php foreach ($tags as $tag): ?>
        <a class="post-tag" href="<?= h(url_for('tag', ['slug' => $tag['slug']])) ?>">#<?= h($tag['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function render_home(int $page): void
{
    $page = max(1, $page);
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));

    if ($page > $totalPages && $total > 0) {
        simple_error_page(sblog_t('页面不存在'), sblog_t('分页超出了范围。'), 404);
    }

    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);
    $siteName = setting('site_name', default_settings()['site_name']);
    $tagline = setting('site_tagline', '');

    ob_start();
    ?>
    <?php if ($posts): ?>
      <article>
        <div class="recent-posts section">
          <?= render_public_post_list($posts) ?>
        </div>
      </article>

      <?php if ($totalPages > 1): ?>
        <ul class="pagination">
          <li class="page-item page-previous">
            <?php if ($page > 1): ?>
              <a href="<?= h(home_page_url($page - 1)) ?>"><?= h(sblog_t('上一页')) ?></a>
            <?php endif; ?>
          </li>
          <li class="page-item page-next">
            <?php if ($page < $totalPages): ?>
              <a href="<?= h(home_page_url($page + 1)) ?>"><?= h(sblog_t('下一页')) ?></a>
            <?php endif; ?>
          </li>
        </ul>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-notice">
        <p><?= h(sblog_t('还没有已发布的文章。')) ?></p>
        <?php if (is_admin()): ?>
          <p><a href="<?= h(url_for('write')) ?>"><?= h(sblog_t('写第一篇文章')) ?></a></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout($siteName, $content, [
        'active' => 'home',
        'mode' => 'public',
        'description' => setting('site_description', $tagline),
    ]);
}

function render_archives(): void
{
    $groups = archive_groups();

    ob_start();
    ?>
    <h1 class="post-title" itemprop="name headline"><?= h(sblog_t('归档')) ?></h1>
    <?php if ($groups): ?>
      <div class="post-content" itemprop="articleBody">
        <ul>
          <?php foreach ($groups as $label => $posts): ?>
            <li class="archives-item">
              <div class="archives-item-content">
                <h3 class="archives-item-title"><?= h((string)$label) ?></h3>
                <?php foreach ($posts as $post): ?>
                  <p>
                    <span class="archives-time"><?= h(date('m-d', (int)$post['published_at'])) ?></span>
                    <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a>
                  </p>
                <?php endforeach; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php else: ?>
      <div class="empty-notice">
        <p><?= h(sblog_t('归档还是空的。')) ?></p>
      </div>
    <?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout(sblog_t('归档'), $content, [
        'active' => 'archives',
        'mode' => 'public',
        'description' => sblog_t('已发布文章归档'),
    ]);
}

function render_comments_section(array $post, array $form = [], array $errors = []): string
{
    $postId = (int)$post['id'];
    $comments = public_comments_for_post($postId);
    $total = approved_comment_count($postId);
    $accepting = setting('comments_enabled', '1') === '1' && content_allows_comments($post) && is_live_content($post);
    $notice = pull_comment_notice($postId);

    if (!$accepting && $total === 0 && $notice === null) {
        return '';
    }

    $authenticatedIdentity = authenticated_comment_identity();
    $identity = $authenticatedIdentity ?? (is_array($_SESSION['comment_identity'] ?? null) ? $_SESSION['comment_identity'] : []);
    $values = array_merge([
        'author_name' => (string)($identity['author_name'] ?? ''),
        'author_email' => (string)($identity['author_email'] ?? ''),
        'author_url' => (string)($identity['author_url'] ?? ''),
        'content' => '',
        'parent_id' => '',
    ], $form);
    if ($authenticatedIdentity !== null) {
        $values = array_merge($values, $authenticatedIdentity);
    }
    $replyTarget = approved_reply_target($postId, (int)$values['parent_id']);
    $replyTargetId = (int)($replyTarget['id'] ?? 0);
    $replyTargetName = (string)($replyTarget['author_name'] ?? '');
    $visibleCommentIds = [];
    foreach ($comments as $visibleComment) {
        $visibleCommentIds[(int)$visibleComment['id']] = true;
    }
    $invalidFields = [
        'author_name' => false,
        'author_email' => false,
        'author_url' => false,
        'content' => false,
    ];
    foreach ($errors as $error) {
        $error = (string)$error;
        if (str_contains($error, '昵称')) { $invalidFields['author_name'] = true; }
        if (str_contains($error, '邮箱')) { $invalidFields['author_email'] = true; }
        if (str_contains($error, '网站地址')) { $invalidFields['author_url'] = true; }
        if (str_contains($error, '评论内容') || str_contains($error, '已经提交过')) { $invalidFields['content'] = true; }
    }

    load_active_theme();
    $defaultLabels = [
        'title' => 'comments.log',
        'form_title' => 'new-comment',
        'submit' => '[' . sblog_t('提交评论') . ']',
        'cancel_reply' => '[' . sblog_t('取消回复') . ']',
        'cancel_reply_aria' => sblog_t('取消回复'),
        'empty' => '// ' . sblog_t('暂无评论'),
        'closed' => '// ' . sblog_t('评论已关闭'),
    ];
    $filteredLabels = theme_filter('comments_labels', $defaultLabels, [
        'post' => $post,
        'accepting' => $accepting,
        'total' => $total,
    ]);
    $labels = $defaultLabels;
    if (is_array($filteredLabels)) {
        foreach ($defaultLabels as $key => $fallback) {
            if (isset($filteredLabels[$key]) && (is_string($filteredLabels[$key]) || is_numeric($filteredLabels[$key]))) {
                $labels[$key] = (string)$filteredLabels[$key];
            }
        }
    }

    ob_start();
    ?>
    <section class="comments" id="comments" aria-labelledby="comments-title">
      <header class="comments__head">
        <h2 class="section-header" id="comments-title"><?= h($labels['title']) ?></h2>
        <span class="comments__count"><?= h($total > count($comments)
            ? sblog_tn('最新 {visible} / 共 {count} 条评论', $total, ['visible' => count($comments)])
            : sblog_tn('{count} 条评论', $total)) ?></span>
      </header>

      <?php if ($notice): ?>
        <div class="comment-notice<?= (string)($notice['type'] ?? '') === 'error' ? ' comment-notice--error' : '' ?>" role="<?= (string)($notice['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= h((string)($notice['message'] ?? '')) ?></div>
      <?php endif; ?>

      <?php if ($comments): ?>
        <ol class="comment-list">
          <?php foreach ($comments as $comment): ?>
            <?php $authorUrl = safe_link_url((string)$comment['author_url']); ?>
            <?php $replyName = trim((string)$comment['reply_to_name']); ?>
            <?php $replyParentId = (int)$comment['parent_id']; ?>
            <?php $replyAnchorVisible = $replyParentId > 0 && isset($visibleCommentIds[$replyParentId]); ?>
            <li class="comment-item<?= $replyName !== '' ? ' comment-item--reply' : '' ?>" id="comment-<?= h((string)$comment['id']) ?>">
              <header class="comment-item__meta">
                <img class="comment-item__avatar" src="<?= h(gravatar_url((string)$comment['author_email'])) ?>" width="36" height="36" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                <?php if ($authorUrl !== '#'): ?>
                  <a class="comment-item__author" href="<?= h($authorUrl) ?>" target="_blank" rel="ugc nofollow noopener noreferrer"><?= h((string)$comment['author_name']) ?></a>
                <?php else: ?>
                  <strong class="comment-item__author"><?= h((string)$comment['author_name']) ?></strong>
                <?php endif; ?>
                <time class="comment-item__time" datetime="<?= h(date(DATE_ATOM, (int)$comment['created_at'])) ?>"><?= h(pretty_date((int)$comment['created_at'], true)) ?></time>
                <?php if ($accepting): ?>
                  <button class="comment-reply-button" type="button" data-comment-reply data-comment-id="<?= h((string)$comment['id']) ?>" data-comment-author="<?= h((string)$comment['author_name']) ?>" aria-controls="comment-form" aria-pressed="<?= $replyTargetId === (int)$comment['id'] ? 'true' : 'false' ?>" aria-label="<?= h(sblog_t('回复 @{author}', ['author' => (string)$comment['author_name']])) ?>" title="<?= h(sblog_t('回复 @{author}', ['author' => (string)$comment['author_name']])) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 17-5-5 5-5"></path><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg>
                    <span><?= h(sblog_t('回复')) ?></span>
                  </button>
                <?php endif; ?>
              </header>
              <div class="comment-item__body">
                <?php if ($replyName !== ''): ?>
                  <?php if ($replyAnchorVisible): ?>
                    <a class="comment-item__reply-target" href="#comment-<?= h((string)$replyParentId) ?>"><span class="sr-only"><?= h(sblog_t('回复给 @{author}', ['author' => $replyName])) ?></span><span class="comment-item__reply-label" aria-hidden="true">@<?= h($replyName) ?></span></a>
                  <?php else: ?>
                    <span class="comment-item__reply-target"><span class="sr-only"><?= h(sblog_t('回复给 @{author}', ['author' => $replyName])) ?></span><span class="comment-item__reply-label" aria-hidden="true">@<?= h($replyName) ?></span></span>
                  <?php endif; ?>
                <?php endif; ?>
                <span class="comment-item__content"><?= nl2br(h((string)$comment['content']), false) ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <div class="comments__empty empty-notice" data-comment-state="empty"><?= h($labels['empty']) ?></div>
      <?php endif; ?>

      <?php if ($accepting): ?>
        <form class="comment-form" id="comment-form" method="post" action="<?= h(url_for('submit_comment')) ?>#comments">
          <?= csrf_field() ?>
          <input type="hidden" name="post_id" value="<?= h((string)$postId) ?>">
          <input type="hidden" name="parent_id" value="<?= $replyTargetId > 0 ? h((string)$replyTargetId) : '' ?>" data-comment-parent-id>
          <input type="hidden" name="comment_started_at" value="<?= h((string)comment_form_started_at($postId)) ?>">
          <div class="comment-honeypot" aria-hidden="true">
            <label for="comment-company">Company</label>
            <input id="comment-company" name="company" type="text" tabindex="-1" autocomplete="off">
          </div>

          <h3 class="comment-form__title"><?= h($labels['form_title']) ?></h3>
          <div class="comment-reply-state" data-comment-reply-state<?= $replyTargetId > 0 ? '' : ' hidden' ?>>
            <span class="comment-reply-state__text" role="status" aria-live="polite" aria-atomic="true">reply-to: <strong data-comment-reply-name><?= $replyTargetId > 0 ? '@' . h($replyTargetName) : '' ?></strong></span>
            <button class="comment-reply-cancel" type="button" data-comment-reply-cancel aria-label="<?= h($labels['cancel_reply_aria']) ?>"><?= h($labels['cancel_reply']) ?></button>
          </div>
          <?php if ($errors): ?>
            <div class="comment-notice comment-notice--error" id="comment-errors" role="alert">
              <ul>
                <?php foreach ($errors as $error): ?>
                  <?php $displayError = match ((string)$error) {
                      '回复目标不存在或当前不可用。' => sblog_t('回复目标不存在或当前不可用。'),
                      '请填写昵称。' => sblog_t('请填写昵称。'),
                      '昵称不能超过 50 个字符。' => sblog_t('昵称不能超过 50 个字符。'),
                      '请填写有效的邮箱地址。' => sblog_t('请填写有效的邮箱地址。'),
                      '网站地址必须是有效的 HTTP 或 HTTPS 链接。' => sblog_t('网站地址必须是有效的 HTTP 或 HTTPS 链接。'),
                      '请填写评论内容。' => sblog_t('请填写评论内容。'),
                      '评论内容不能超过 3000 个字符。' => sblog_t('评论内容不能超过 3000 个字符。'),
                      '这条评论已经提交过了。' => sblog_t('这条评论已经提交过了。'),
                      '提交过于频繁，请稍后再试。' => sblog_t('提交过于频繁，请稍后再试。'),
                      '提交过快，请稍后再试。' => sblog_t('提交过快，请稍后再试。'),
                      '评论表单已失效，请刷新文章后重试。' => sblog_t('评论表单已失效，请刷新文章后重试。'),
                      '回复目标已不可用，请重新选择。' => sblog_t('回复目标已不可用，请重新选择。'),
                      default => (string)$error,
                  }; ?>
                  <li><?= h($displayError) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if ($authenticatedIdentity === null): ?>
            <div class="comment-form__grid">
              <div class="comment-field">
                <label for="comment-author"><?= h(sblog_t('昵称')) ?></label>
                <input id="comment-author" name="author_name" value="<?= h((string)$values['author_name']) ?>" maxlength="50" autocomplete="name"<?= $invalidFields['author_name'] ? ' aria-invalid="true" aria-describedby="comment-errors"' : '' ?> required>
              </div>
              <div class="comment-field">
                <label for="comment-email"><?= h(sblog_t('邮箱')) ?></label>
                <input id="comment-email" name="author_email" type="email" value="<?= h((string)$values['author_email']) ?>" maxlength="160" autocomplete="email"<?= $invalidFields['author_email'] ? ' aria-invalid="true" aria-describedby="comment-errors"' : '' ?> required>
              </div>
              <div class="comment-field comment-field--wide">
                <label for="comment-url"><?= h(sblog_t('网站（可选）')) ?></label>
                <input id="comment-url" name="author_url" type="url" value="<?= h((string)$values['author_url']) ?>" maxlength="300" autocomplete="url" placeholder="https://example.com"<?= $invalidFields['author_url'] ? ' aria-invalid="true" aria-describedby="comment-errors"' : '' ?>>
              </div>
            </div>
          <?php endif; ?>
          <div class="comment-field">
            <label for="comment-content"><?= h(sblog_t('评论')) ?></label>
            <textarea id="comment-content" name="content" rows="6" maxlength="3000"<?= $invalidFields['content'] ? ' aria-invalid="true" aria-describedby="comment-errors"' : '' ?> required><?= h((string)$values['content']) ?></textarea>
          </div>
          <div class="comment-form__actions">
            <button class="terminal-action" type="submit"><?= h($labels['submit']) ?></button>
          </div>
        </form>
      <?php elseif ($total > 0): ?>
        <div class="comments__empty empty-notice" data-comment-state="closed"><?= h($labels['closed']) ?></div>
      <?php endif; ?>
    </section>
    <?php
    return (string)ob_get_clean();
}

function render_post_page(array $post, array $commentForm = [], array $commentErrors = []): void
{
    increment_content_views($post);

    if ($commentForm === [] && $commentErrors === []) {
        [$commentForm, $commentErrors] = pull_comment_feedback((int)$post['id']);
    }

    $neighbors = post_neighbors($post);
    $state = post_state($post);
    $meta = one(
        'SELECT p.views, u.username, u.nickname, c.name AS category_name, c.slug AS category_slug
         FROM posts p
         LEFT JOIN users u ON u.id = p.author_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = ?',
        [(int)$post['id']]
    ) ?? [];
    $author = trim((string)($meta['nickname'] ?? '')) ?: (string)($meta['username'] ?? 'Admin');
    $categoryName = array_key_exists('category_name', $meta) && $meta['category_name'] !== null
        ? (string)$meta['category_name']
        : sblog_t('未分类');
    $categorySlug = (string)($meta['category_slug'] ?? '');
    $viewCount = (int)($meta['views'] ?? $post['views'] ?? 0);
    $displayTime = (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
    $tagsMarkup = render_tag_chips($post);

    ob_start();
    ?>
    <article>
      <h1 class="post-title" itemprop="name headline"><?= h($post['title']) ?></h1>
      <div class="meta">
        <span><?= h(date('F j, Y', $displayTime)) ?></span>
        <span><?= h(sblog_t('作者：{author}', ['author' => $author])) ?></span>
        <span><?= h(sblog_t('分类：')) ?><?php if ($categorySlug !== ''): ?><a href="<?= h(url_for('category', ['slug' => $categorySlug])) ?>"><?= h($categoryName) ?></a><?php else: ?><?= h($categoryName) ?><?php endif; ?></span>
        <span><?= h(sblog_tn('浏览：{count}', $viewCount)) ?></span>
        <?php if (!is_live_post($post) && is_admin()): ?>
          <span><?= h(sblog_t('{state}预览', ['state' => (string)$state['label']])) ?></span>
        <?php endif; ?>
      </div>
      <div class="post-content" itemprop="articleBody">
        <?= markdown_to_html((string)$post['content']) ?>
      </div>
      <?php if ($tagsMarkup !== ''): ?>
        <div class="post-tags">
          <nav class="nav tags">
            <?= $tagsMarkup ?>
          </nav>
        </div>
      <?php endif; ?>
    </article>

    <?php if ($neighbors['newer'] || $neighbors['older']): ?>
      <ul class="pagination">
        <li class="page-item page-previous">
          <?php if ($neighbors['newer']): ?>
            <a href="<?= h(url_for('post', ['slug' => (string)$neighbors['newer']['slug']])) ?>" data-post-title="<?= h((string)$neighbors['newer']['title']) ?>" aria-label="<?= h(sblog_t('post_navigation.previous_label', ['title' => (string)$neighbors['newer']['title']])) ?>"><?= h(sblog_t('post_navigation.previous')) ?></a>
          <?php endif; ?>
        </li>
        <li class="page-item page-next">
          <?php if ($neighbors['older']): ?>
            <a href="<?= h(url_for('post', ['slug' => (string)$neighbors['older']['slug']])) ?>" data-post-title="<?= h((string)$neighbors['older']['title']) ?>" aria-label="<?= h(sblog_t('post_navigation.next_label', ['title' => (string)$neighbors['older']['title']])) ?>"><?= h(sblog_t('post_navigation.next')) ?></a>
          <?php endif; ?>
        </li>
      </ul>
    <?php endif; ?>

    <?= render_comments_section($post, $commentForm, $commentErrors) ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout((string)$post['title'], $content, [
        'active' => 'home',
        'mode' => 'public',
        'description' => trim((string)$post['excerpt']) !== '' ? (string)$post['excerpt'] : derive_excerpt((string)$post['content']),
    ]);
}

function render_page_view(array $page): void
{
    increment_content_views($page);
    $allowComments = content_allows_comments($page);
    [$commentForm, $commentErrors] = $allowComments ? pull_comment_feedback((int)$page['id']) : [[], []];

    ob_start();
    ?>
    <article>
      <h1 class="post-title" itemprop="name headline"><?= h($page['title']) ?></h1>
      <?php if (!is_live_content($page) && is_admin()): ?>
        <?php $state = post_state($page); ?>
        <div class="meta"><span><?= h(sblog_t('{state}预览', ['state' => (string)$state['label']])) ?></span></div>
      <?php endif; ?>
      <div class="post-content" itemprop="articleBody">
        <?= markdown_to_html((string)$page['content']) ?>
      </div>
    </article>
    <?php if ($allowComments): ?><?= render_comments_section($page, $commentForm, $commentErrors) ?><?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout((string)$page['title'], $content, [
        'active' => 'page:' . (string)$page['slug'],
        'mode' => 'public',
        'description' => trim((string)$page['excerpt']) !== '' ? (string)$page['excerpt'] : derive_excerpt((string)$page['content']),
    ]);
}

function render_tags_index(): void
{
    $tags = tag_index_data();

    ob_start();
    ?>
    <h1 class="post-title" itemprop="name headline"><?= h(sblog_t('标签')) ?></h1>

    <?php if ($tags): ?>
      <div class="post-content">
        <div class="tag-cloud">
          <?php foreach ($tags as $tag): ?>
            <a class="tag-index-link" href="<?= h(url_for('tag', ['slug' => $tag['slug']])) ?>">
              <span>#<?= h($tag['label']) ?></span>
              <strong><?= h((string)$tag['count']) ?></strong>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-notice">
        <p><?= h(sblog_t('还没有标签。')) ?></p>
      </div>
    <?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout(sblog_t('标签'), $content, [
        'active' => 'tags',
        'mode' => 'public',
        'description' => sblog_t('标签索引'),
    ]);
}

function render_tag_page(string $slug): void
{
    $label = tag_label_by_slug($slug);
    $posts = fetch_posts_by_tag_slug($slug);

    if ($label === null && $posts === []) {
        simple_error_page(sblog_t('标签不存在'), sblog_t('没有找到这个标签下的文章。'), 404);
    }

    $label = $label ?? $slug;

    ob_start();
    ?>
    <h1 class="post-title" itemprop="name headline">#<?= h($label) ?></h1>
    <div class="meta"><?= h(sblog_tn('{count} 篇文章', count($posts))) ?></div>

    <?php if ($posts): ?>
      <article>
        <div class="recent-posts section">
          <h2 class="section-header"><?= h(sblog_t('文章')) ?></h2>
          <?= render_public_post_list($posts) ?>
        </div>
      </article>
    <?php else: ?>
      <div class="empty-notice">
        <p><?= h(sblog_t('这个标签下还没有文章。')) ?></p>
      </div>
    <?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout('#' . $label, $content, [
        'active' => 'tags',
        'mode' => 'public',
        'description' => sblog_t('标签 {label} 下的文章', ['label' => $label]),
    ]);
}

function render_category_page(string $slug): void
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) { simple_error_page(sblog_t('分类不存在'), sblog_t('没有找到这个文章分类。'), 404); }
    $posts = all_rows(
        'SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC',
        ['post', (int)$category['id'], 'published', time()]
    );
    ob_start(); ?>
    <h1 class="post-title"><?= h((string)$category['name']) ?></h1>
    <?php if (trim((string)$category['description']) !== ''): ?><div class="meta"><?= h((string)$category['description']) ?></div><?php endif; ?>
    <?php if ($posts): ?><article><div class="recent-posts section"><?= render_public_post_list($posts) ?></div></article><?php else: ?><div class="empty-notice"><p><?= h(sblog_t('这个分类下还没有已发布文章。')) ?></p></div><?php endif; ?>
    <?php
    render_layout((string)$category['name'], (string)ob_get_clean(), ['active' => 'home', 'mode' => 'public', 'description' => trim((string)$category['description']) ?: sblog_t('分类文章')]);
}

function render_rss_feed(): void
{
    $siteName = setting('site_name', default_settings()['site_name']);
    $description = setting('site_description', setting('site_tagline', ''));
    $home = absolute_url(url_for('home'));
    $items = fetch_feed_posts(20);

    header('Content-Type: application/rss+xml; charset=UTF-8');
    header('Content-Language: ' . sblog_i18n_locale());
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    ?>
<rss version="2.0">
  <channel>
    <title><?= x($siteName) ?></title>
    <link><?= x($home) ?></link>
    <description><?= x($description) ?></description>
    <language><?= x(sblog_i18n_locale()) ?></language>
    <lastBuildDate><?= x(date(DATE_RSS)) ?></lastBuildDate>
    <?php foreach ($items as $item): ?>
      <?php $link = absolute_url(content_permalink($item)); ?>
      <item>
        <title><?= x($item['title']) ?></title>
        <link><?= x($link) ?></link>
        <guid><?= x($link) ?></guid>
        <pubDate><?= x(date(DATE_RSS, (int)$item['published_at'])) ?></pubDate>
        <description><?= x(trim((string)$item['excerpt']) !== '' ? (string)$item['excerpt'] : derive_excerpt((string)$item['content'])) ?></description>
      </item>
    <?php endforeach; ?>
  </channel>
</rss>
<?php
    exit;
}

function render_login_page(string $error = '', array $form = []): void
{
    ob_start();
    ?>
    <div class="auth-layout">
      <section class="panel auth-panel admin-animate admin-animate--1">
        <div class="panel__body">
          <?php if ($error !== ''): ?>
            <div class="flash flash--error" role="alert"><?= h($error) ?></div>
          <?php endif; ?>

          <form class="form-stack" method="post" action="<?= h(url_for('login')) ?>">
            <?= csrf_field() ?>
            <div class="field">
              <label for="username"><?= h(sblog_t('用户名')) ?></label>
              <input id="username" name="username" type="text" value="<?= h((string)($form['username'] ?? '')) ?>" autocomplete="username" required autofocus>
            </div>
            <div class="field">
              <div class="auth-field-row">
                <label for="password"><?= h(sblog_t('密码')) ?></label>
                <a href="<?= h(url_for('forgot_password')) ?>"><?= h(sblog_t('忘记密码？')) ?></a>
              </div>
              <div class="auth-password">
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="auth-password-toggle" type="button" data-password-toggle="password" aria-label="<?= h(sblog_t('显示密码')) ?>" aria-pressed="false" title="<?= h(sblog_t('显示密码')) ?>">
                  <span class="auth-password-toggle__icon auth-password-toggle__icon--show"><?= admin_icon('eye') ?></span>
                  <span class="auth-password-toggle__icon auth-password-toggle__icon--hide"><?= admin_icon('eye-off') ?></span>
                </button>
              </div>
            </div>
            <div class="action-row auth-actions">
              <button class="button" type="submit"><?= h(sblog_t('登录后台')) ?></button>
            </div>
          </form>
        </div>
      </section>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout(sblog_t('登录'), $content, [
        'active' => 'login',
        'description' => sblog_t('博客后台登录'),
    ]);
}

function password_reset_notice_path(string $token): string
{
    ensure_runtime_dirs();
    return CACHE_DIR . '/password-reset-' . substr(hash('sha256', $token), 0, 16) . '.txt';
}

function create_password_reset(array $user): array
{
    $token = bin2hex(random_bytes(32));
    $now = time();
    $expiresAt = $now + 3600;

    q('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at = 0', [$now, (int)$user['id']]);
    q(
        'INSERT INTO password_resets(user_id, token_hash, expires_at, used_at, created_at) VALUES(?,?,?,?,?)',
        [(int)$user['id'], hash('sha256', $token), $expiresAt, 0, $now]
    );

    return [$token, $expiresAt];
}

function send_password_reset_notice(array $user, string $token, int $expiresAt): bool
{
    $link = absolute_url(url_with_query(url_for('reset_password'), ['token' => $token]));
    $siteName = setting('site_name', default_settings()['site_name']);
    $subject = sblog_t('重置 {site} 管理员密码', ['site' => $siteName]);
    $body = sblog_t(
        "你正在重置 {site} 的管理员密码。\n\n重置链接：{link}\n\n链接将在 {expires_at} 过期。如果不是你本人操作，请忽略这封邮件。",
        ['site' => $siteName, 'link' => $link, 'expires_at' => date('Y-m-d H:i:s', $expiresAt)]
    );
    $email = trim((string)($user['email'] ?? ''));
    $sent = send_site_mail($email, $subject, $body);

    file_put_contents(password_reset_notice_path($token), $body . "\n", LOCK_EX);
    return $sent;
}

function comment_notify_email(): string
{
    $adminEmail = (string)val("SELECT email FROM users WHERE email <> '' ORDER BY id ASC LIMIT 1");
    $default = filter_var($adminEmail, FILTER_VALIDATE_EMAIL) ? $adminEmail : '';
    $email = trim((string)plugin_filter('notification_recipient', $default, []));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function send_comment_notification(array $post, array $comment, string $status): bool
{
    $email = comment_notify_email();
    if ($email === '') {
        return false;
    }

    $siteName = setting('site_name', default_settings()['site_name']);
    $subject = sblog_t('新评论：{title}', ['title' => (string)$post['title']]);
    $body = sblog_t(
        "站点：{site}\n文章：{title}\n状态：{status}\n评论人：{author}\n邮箱：{email}\nIP：{ip}\n链接：{link}\n\n评论内容：\n{content}",
        [
            'site' => $siteName,
            'title' => (string)$post['title'],
            'status' => $status === 'approved' ? sblog_t('已发布') : sblog_t('待审核'),
            'author' => (string)$comment['author_name'],
            'email' => (string)$comment['author_email'],
            'ip' => client_ip_address() ?: sblog_t('未知'),
            'link' => absolute_url(content_permalink($post)) . '#comments',
            'content' => (string)$comment['content'],
        ]
    );

    return send_site_mail($email, $subject, $body);
}

function password_reset_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $reset = one(
        'SELECT r.*, u.username, u.email FROM password_resets r INNER JOIN users u ON u.id = r.user_id WHERE r.token_hash = ? AND r.used_at = 0 AND r.expires_at >= ?',
        [hash('sha256', $token), time()]
    );

    return $reset ?: null;
}

function render_forgot_password_page(string $notice = '', string $error = '', array $form = []): void
{
    ob_start();
    ?>
    <div class="auth-layout">
      <section class="panel auth-panel admin-animate admin-animate--1">
        <div class="panel__body">
          <header class="auth-heading">
            <p class="auth-heading__eyebrow"><?= h(sblog_t('密码重置')) ?></p>
            <h1><?= h(sblog_t('找回密码')) ?></h1>
            <p><?= h(sblog_t('输入管理员用户名或邮箱，系统会生成一次性重置链接。')) ?></p>
          </header>

          <?php if ($notice !== ''): ?><div class="flash flash--success" role="status"><?= h($notice) ?></div><?php endif; ?>
          <?php if ($error !== ''): ?><div class="flash flash--error" role="alert"><?= h($error) ?></div><?php endif; ?>

          <form class="form-stack" method="post" action="<?= h(url_for('forgot_password')) ?>">
            <?= csrf_field() ?>
            <div class="field">
              <label for="account"><?= h(sblog_t('用户名或邮箱')) ?></label>
              <input id="account" name="account" type="text" value="<?= h((string)($form['account'] ?? '')) ?>" autocomplete="username" required autofocus>
            </div>
            <div class="action-row auth-actions">
              <button class="button" type="submit"><?= h(sblog_t('发送重置链接')) ?></button>
            </div>
            <p class="auth-link-row"><a href="<?= h(url_for('login')) ?>"><?= h(sblog_t('返回登录')) ?></a></p>
          </form>
        </div>
      </section>
    </div>
    <?php
    render_layout(sblog_t('找回密码'), (string)ob_get_clean(), [
        'active' => 'login',
        'description' => sblog_t('找回博客后台密码'),
    ]);
}

function render_reset_password_page(string $token, string $error = ''): void
{
    ob_start();
    ?>
    <div class="auth-layout">
      <section class="panel auth-panel admin-animate admin-animate--1">
        <div class="panel__body">
          <header class="auth-heading">
            <p class="auth-heading__eyebrow"><?= h(sblog_t('密码重置')) ?></p>
            <h1><?= h(sblog_t('设置新密码')) ?></h1>
            <p><?= h(sblog_t('新密码至少需要 8 个字符。')) ?></p>
          </header>

          <?php if ($error !== ''): ?><div class="flash flash--error" role="alert"><?= h($error) ?></div><?php endif; ?>

          <form class="form-stack" method="post" action="<?= h(url_for('reset_password')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <div class="field">
              <label for="password"><?= h(sblog_t('新密码')) ?></label>
              <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
            </div>
            <div class="field">
              <label for="password_confirm"><?= h(sblog_t('确认新密码')) ?></label>
              <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            <div class="action-row auth-actions">
              <button class="button" type="submit"><?= h(sblog_t('更新密码')) ?></button>
            </div>
          </form>
        </div>
      </section>
    </div>
    <?php
    render_layout(sblog_t('设置新密码'), (string)ob_get_clean(), [
        'active' => 'login',
        'description' => sblog_t('设置博客后台新密码'),
    ]);
}

function translated_admin_form_errors(array $errors): array
{
    $systemMessages = [
        '分类名称不能为空。' => sblog_t('分类名称不能为空。'),
        '请填写网站名称。' => sblog_t('请填写网站名称。'),
        '请填写有效的 HTTP 或 HTTPS 地址。' => sblog_t('请填写有效的 HTTP 或 HTTPS 地址。'),
        '网站图标地址格式不正确。' => sblog_t('网站图标地址格式不正确。'),
        '原标签和新标签不能为空。' => sblog_t('原标签和新标签不能为空。'),
        '新标签不能包含逗号。' => sblog_t('新标签不能包含逗号。'),
        'Slug 格式不正确。' => sblog_t('Slug 格式不正确。'),
        'Slug 已被其他标签使用。' => sblog_t('Slug 已被其他标签使用。'),
        '用户名不能为空。' => sblog_t('用户名不能为空。'),
        '昵称不能为空。' => sblog_t('昵称不能为空。'),
        '邮箱地址格式不正确。' => sblog_t('邮箱地址格式不正确。'),
        '用户名已存在。' => sblog_t('用户名已存在。'),
        '请输入原密码。' => sblog_t('请输入原密码。'),
        '原密码不正确。' => sblog_t('原密码不正确。'),
        '新密码至少需要 8 个字符。' => sblog_t('新密码至少需要 8 个字符。'),
        '两次输入的密码不一致。' => sblog_t('两次输入的密码不一致。'),
        '标题不能为空。' => sblog_t('标题不能为空。'),
        '正文不能为空。' => sblog_t('正文不能为空。'),
        '文章必须选择一个分类。' => sblog_t('文章必须选择一个分类。'),
        '发布时间格式不正确。' => sblog_t('发布时间格式不正确。'),
    ];
    $formatFields = [
        '头像地址' => sblog_t('头像地址'),
        '网站地址' => sblog_t('网站地址'),
    ];
    $linkFields = [
        'GitHub' => sblog_t('GitHub'),
        'QQ' => sblog_t('QQ'),
        '微信' => sblog_t('微信'),
        '微博' => sblog_t('微博'),
        'X' => sblog_t('X'),
        'Telegram' => sblog_t('Telegram'),
        'Mastodon' => sblog_t('Mastodon'),
        '哔哩哔哩' => sblog_t('哔哩哔哩'),
        'Instagram' => sblog_t('Instagram'),
        'TikTok' => sblog_t('TikTok'),
    ];

    return array_map(static function ($error) use ($systemMessages, $formatFields, $linkFields): string {
        $message = (string)$error;
        if (isset($systemMessages[$message])) {
            return $systemMessages[$message];
        }
        foreach ($formatFields as $source => $translated) {
            if ($message === $source . '格式不正确。') {
                return sblog_t('{field}格式不正确。', ['field' => $translated]);
            }
        }
        foreach ($linkFields as $source => $translated) {
            if ($message === $source . '链接格式不正确。') {
                return sblog_t('{field}链接格式不正确。', ['field' => $translated]);
            }
        }
        return $message;
    }, $errors);
}

function render_admin_page(): void
{
    require_admin();

    $metrics = admin_metrics();
    $update = github_update_info();
    $commentNotifications = recent_comment_notifications();
    $sidebar = render_admin_sidebar('admin');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('博客数据预览')) ?>

        <div class="admin-grid">
          <?php if (!empty($update['available']) || !empty($update['repair'])): ?>
            <section class="panel update-notice admin-animate">
              <div class="panel__body">
                <div>
                  <strong><?= h(!empty($update['repair']) ? sblog_t('发布文件需要补全') : sblog_t('发现新版本 {version}', ['version' => (string)$update['latest']])) ?></strong>
                  <p><?= h(!empty($update['repair']) ? sblog_t('当前程序版本完整，但发布包中的内置主题或插件尚未同步。') : sblog_t('当前版本 {version}。更新会自动备份并覆盖程序、内置主题和内置插件文件，站点数据、上传文件及其他自定义主题和插件不受影响。', ['version' => APP_VERSION])) ?></p>
                </div>
                <?php $updateConfirm = !empty($update['repair']) ? sblog_t('确定从当前发布包补全内置主题和插件吗？') : sblog_t('确定更新到 {version} 吗？更新期间请勿关闭页面。', ['version' => (string)$update['latest']]); ?>
                <form method="post" action="<?= h(url_for('install_update')) ?>" onsubmit="return confirm(<?= h(json_encode($updateConfirm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>);">
                  <?= csrf_field() ?><button class="button button--primary" type="submit"><?= h(!empty($update['repair']) ? sblog_t('同步发布文件') : sblog_t('立即更新')) ?></button>
                </form>
              </div>
            </section>
          <?php endif; ?>
          <?php if ($commentNotifications): ?>
            <section class="panel admin-list-panel comment-notifications admin-animate admin-animate--2">
              <div class="panel__header">
                <div class="admin-head">
                  <div class="admin-head-left">
                    <h2><?= h(sblog_t('评论通知')) ?></h2>
                    <p class="panel__meta"><?= h(sblog_tn('{count} 条待审核，最近未读如下。', (int)$metrics['pending_comments'])) ?></p>
                  </div>
                  <a class="button button--secondary" href="<?= h(admin_comments_url('unread')) ?>"><?= h(sblog_t('查看全部')) ?></a>
                </div>
              </div>
              <div class="panel__body panel__body--flush">
                <ol class="comment-notice-list">
                  <?php foreach ($commentNotifications as $notification): ?>
                    <li class="comment-notice-item is-unread">
                      <div class="comment-notice-item__body">
                        <div class="comment-notice-item__meta">
                          <strong><?= h((string)$notification['author_name']) ?></strong>
                          <?php if (trim((string)$notification['reply_to_name']) !== ''): ?><span class="comment-notice-item__reply"><?= h(sblog_t('回复 @{name}', ['name' => (string)$notification['reply_to_name']])) ?></span><?php endif; ?>
                          <time datetime="<?= h(date(DATE_ATOM, (int)$notification['created_at'])) ?>"><?= h(pretty_date((int)$notification['created_at'], true)) ?></time>
                        </div>
                        <p class="comment-notice-item__excerpt"><?= h(comment_excerpt((string)$notification['content'], 140)) ?></p>
                        <a href="<?= h(content_permalink(['kind' => (string)$notification['post_kind'], 'slug' => (string)$notification['post_slug']])) ?>"><?= h((string)$notification['post_title']) ?></a>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ol>
              </div>
            </section>
          <?php endif; ?>
          <section class="panel admin-list-panel admin-animate admin-animate--3">
            <div class="panel__header">
              <h2><?= h(sblog_t('博客概览')) ?></h2>
              <p class="panel__meta"><?= h(sblog_t('只显示访问和内容统计数据。')) ?></p>
            </div>
            <div class="panel__body">
              <div class="metric-grid">
                <div class="metric-card">
                  <span class="metric-card__label"><?= h(sblog_t('总浏览量')) ?></span>
                  <strong class="metric-card__value"><?= h((string)$metrics['total_views']) ?></strong>
                  <span class="metric-card__trend"><?= h(sblog_t('公开文章与页面累计')) ?></span>
                </div>
                <div class="metric-card">
                  <span class="metric-card__label"><?= h(sblog_t('已发布文章')) ?></span>
                  <strong class="metric-card__value"><?= h((string)$metrics['published']) ?></strong>
                  <span class="metric-card__trend"><?= h(sblog_t('前台可访问内容')) ?></span>
                </div>
                <div class="metric-card">
                  <span class="metric-card__label"><?= h(sblog_t('分类数')) ?></span>
                  <strong class="metric-card__value"><?= h((string)$metrics['categories']) ?></strong>
                  <span class="metric-card__trend"><?= h(sblog_t('文章分类总数')) ?></span>
                </div>
                <div class="metric-card">
                  <span class="metric-card__label"><?= h(sblog_t('平均浏览')) ?></span>
                  <strong class="metric-card__value"><?= h((string)$metrics['avg_views']) ?></strong>
                  <span class="metric-card__trend"><?= h(sblog_t('按文章数粗略计算')) ?></span>
                </div>
                <div class="metric-card">
                  <span class="metric-card__label"><?= h(sblog_t('评论总数')) ?></span>
                  <strong class="metric-card__value"><?= h((string)$metrics['comments']) ?></strong>
                  <span class="metric-card__trend"><?= h(sblog_t('包含所有审核状态')) ?></span>
                </div>
                <div class="metric-card">
                  <span class="metric-card__label"><?= h(sblog_t('待审核评论')) ?></span>
                  <strong class="metric-card__value"><?= h((string)$metrics['pending_comments']) ?></strong>
                  <span class="metric-card__trend"><?= h(sblog_t('需要管理员处理')) ?></span>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout(sblog_t('后台概览'), $content, [
        'active' => 'admin',
        'wide' => true,
        'description' => sblog_t('博客后台概览'),
    ]);
}

function render_sitemap(): void
{
    $now = time();
    $rows = all_rows(
        'SELECT slug, kind, updated_at, published_at FROM posts WHERE status = ? AND published_at <= ? ORDER BY updated_at DESC',
        ['published', $now]
    );
    $signature = (string)($rows[0]['updated_at'] ?? 0) . ':' . count($rows) . ':' . (string)val('SELECT COALESCE(MAX(updated_at), 0) FROM tag_meta');
    $etag = '"' . sha1($signature) . '"';
    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: public, max-age=900, stale-while-revalidate=3600');
    header('ETag: ' . $etag);
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }

    $urls = [];
    $add = static function (string $url, int $updatedAt = 0, string $priority = '0.6') use (&$urls): void {
        $urls[$url] = ['url' => absolute_url($url), 'updated_at' => $updatedAt, 'priority' => $priority];
    };
    $add(url_for('home'), $now, '1.0');
    $add(url_for('archives'), $now, '0.7');
    $add(url_for('tags'), $now, '0.7');
    $add(url_for('links'), $now, '0.5');
    foreach ($rows as $row) {
        $route = (string)$row['kind'] === 'page' ? 'page' : 'post';
        $add(url_for($route, ['slug' => (string)$row['slug']]), (int)$row['updated_at'], $route === 'post' ? '0.8' : '0.6');
    }
    foreach (tag_index_data() as $tag) {
        $add(url_for('tag', ['slug' => (string)$tag['slug']]), $now, '0.5');
    }
    foreach (all_rows('SELECT slug, updated_at FROM categories ORDER BY id') as $category) {
        $add(url_for('category', ['slug' => (string)$category['slug']]), (int)$category['updated_at'], '0.5');
    }

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $item) {
        echo "  <url>\n";
        echo '    <loc>' . x((string)$item['url']) . "</loc>\n";
        if ((int)$item['updated_at'] > 0) { echo '    <lastmod>' . gmdate('Y-m-d\TH:i:s\Z', (int)$item['updated_at']) . "</lastmod>\n"; }
        echo '    <priority>' . x((string)$item['priority']) . "</priority>\n";
        echo "  </url>\n";
    }
    echo '</urlset>';
    exit;
}

function render_links_page(): void
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    ?>
    <article class="links-page">
      <h1 class="post-title"><?= h(sblog_t('链接')) ?></h1>
      <p class="meta"><?= h(sblog_t('一些值得访问的网站与朋友。')) ?></p>
      <?php if ($links): ?>
        <div class="friend-links">
          <?php foreach ($links as $link): ?>
            <?php $host = (string)(parse_url((string)$link['url'], PHP_URL_HOST) ?: $link['url']); ?>
            <a class="friend-link" href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer">
              <span class="friend-link__head"><?php if (trim((string)$link['icon_url']) !== ''): ?><img src="<?= h((string)$link['icon_url']) ?>" width="24" height="24" alt=""><?php endif; ?><strong><?= h((string)$link['name']) ?></strong></span>
              <?php if (trim((string)$link['description']) !== ''): ?><span><?= h((string)$link['description']) ?></span><?php endif; ?>
              <small><?= h($host) ?> ↗</small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-notice"><p><?= h(sblog_t('还没有添加友情链接。')) ?></p></div>
      <?php endif; ?>
    </article>
    <?php
    render_layout(sblog_t('链接'), (string)ob_get_clean(), [
        'active' => 'links',
        'mode' => 'public',
        'description' => sblog_t('友情链接'),
    ]);
}

function render_admin_posts_page(): void
{
    require_admin();

    $posts = fetch_admin_posts();
    $sidebar = render_admin_sidebar('posts');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('文章管理')) ?>

        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header">
            <div class="admin-head">
              <div class="admin-head-left">
                <h2><?= h(sblog_t('文章管理')) ?></h2>
                <p class="panel__meta"><?= h(sblog_t('管理文章、独立页面、分类、状态和浏览量。')) ?></p>
              </div>
            </div>
          </div>
          <div class="panel__body panel__body--flush">
            <?php if ($posts): ?>
              <div class="table-wrap">
                <table class="admin-table">
                  <thead>
                  <tr>
                    <th><?= h(sblog_t('类型')) ?></th>
                    <th><?= h(sblog_t('标题')) ?></th>
                    <th><?= h(sblog_t('状态')) ?></th>
                    <th><?= h(sblog_t('更新时间')) ?></th>
                    <th><?= h(sblog_t('操作')) ?></th>
                  </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($posts as $post): ?>
                    <?php
                    $state = post_state($post);
                    $isPinned = !empty($post['is_pinned']) && content_kind($post) === 'post';
                    ?>
                    <tr class="admin-post-row<?= $isPinned ? ' admin-post-row--pinned' : '' ?>">
                      <td><span class="content-kind content-kind--<?= h(content_kind($post)) ?>"><?= h(content_type_label($post)) ?></span></td>
                      <td>
                        <div class="table-title admin-post-title">
                          <strong><a href="<?= h(url_for('edit', ['id' => $post['id']])) ?>"><?= h($post['title']) ?></a></strong>
                          <?php if ($isPinned): ?><span class="admin-pinned-badge"><?= admin_icon('pin') ?><?= h(sblog_t('置顶')) ?></span><?php endif; ?>
                        </div>
                      </td>
                      <td><span class="status-badge status-badge--<?= h($state['class']) ?>"><?= h($state['label']) ?></span></td>
                      <td><time datetime="<?= h(date(DATE_ATOM, (int)$post['updated_at'])) ?>"><?= h(pretty_date((int)$post['updated_at'], true)) ?></time></td>
                      <td>
                        <div class="table-actions">
                          <a class="button button--ghost" href="<?= h(content_permalink($post)) ?>"><?= h(sblog_t('查看')) ?></a>
                          <a class="button button--ghost" href="<?= h(url_for('edit', ['id' => $post['id']])) ?>"><?= h(sblog_t('编辑')) ?></a>
                          <form method="post" action="<?= h(url_for('change_status')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= h($post['id']) ?>">
                            <input type="hidden" name="status" value="<?= h((string)$post['status'] === 'published' ? 'draft' : 'published') ?>">
                            <button class="button button--ghost" type="submit"><?= h((string)$post['status'] === 'published' ? sblog_t('转草稿') : sblog_t('发布')) ?></button>
                          </form>
                          <form method="post" action="<?= h(url_for('delete_post')) ?>" onsubmit="return confirm(<?= h(json_encode(sblog_t('确定删除这篇文章吗？'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= h($post['id']) ?>">
                            <button class="button button--danger" type="submit"><?= h(sblog_t('删除')) ?></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="empty-state empty-state--inside">
                <p><?= h(sblog_t('还没有文章。')) ?></p>
                <a class="button" href="<?= h(url_for('write')) ?>"><?= h(sblog_t('开始写作')) ?></a>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout(sblog_t('文章管理'), $content, [
        'active' => 'posts',
        'wide' => true,
        'description' => sblog_t('博客文章管理'),
    ]);
}

function render_admin_comments_page(): void
{
    require_admin();

    $filter = trim((string)($_GET['filter'] ?? 'all'));
    $search = str_sub_u(trim((string)($_GET['q'] ?? '')), 0, 100);
    $requestedPage = max(1, (int)($_GET['p'] ?? 1));
    [$comments, $total, $page, $totalPages, $filter] = fetch_admin_comments($filter, $search, $requestedPage);
    $counts = comment_admin_counts();
    $sidebar = render_admin_sidebar('comments');
    $filters = [
        'all' => ['label' => sblog_t('全部'), 'count' => $counts['all']],
        'unread' => ['label' => sblog_t('未读'), 'count' => $counts['unread']],
        'pending' => ['label' => sblog_t('待审核'), 'count' => $counts['pending']],
        'approved' => ['label' => sblog_t('已通过'), 'count' => $counts['approved']],
        'spam' => ['label' => sblog_t('垃圾'), 'count' => $counts['spam']],
    ];

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>
      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('评论管理')) ?>

        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header">
            <div class="admin-head">
              <div class="admin-head-left">
                <h2><?= h(sblog_t('评论管理')) ?></h2>
                <p class="panel__meta"><?= h(sblog_tn('当前筛选 {count} 条评论，审核状态与未读通知独立管理。', $total)) ?></p>
              </div>
              <?php if ($counts['unread'] > 0): ?>
                <form method="post" action="<?= h(url_for('mark_comments_read')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="filter" value="<?= h($filter) ?>">
                  <input type="hidden" name="q" value="<?= h($search) ?>">
                  <input type="hidden" name="p" value="<?= h((string)$page) ?>">
                  <button class="button button--secondary comment-mark-read" type="submit"><?= h(sblog_t('全部标为已读')) ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="admin-comment-toolbar">
            <nav class="admin-filter-tabs" aria-label="<?= h(sblog_t('评论筛选')) ?>">
              <?php foreach ($filters as $key => $item): ?>
                <a class="admin-filter-tab<?= $filter === $key ? ' is-active' : '' ?>" href="<?= h(admin_comments_url($key, $search)) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>>
                  <?= h((string)$item['label']) ?><span><?= h((string)$item['count']) ?></span>
                </a>
              <?php endforeach; ?>
            </nav>
            <form class="comment-search" method="get" action="<?= h(url_for('admin_comments')) ?>">
              <?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="admin_comments"><?php endif; ?>
              <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= h($filter) ?>"><?php endif; ?>
              <label class="sr-only" for="comment-search"><?= h(sblog_t('搜索评论')) ?></label>
              <input id="comment-search" name="q" type="search" value="<?= h($search) ?>" placeholder="<?= h(sblog_t('作者、邮箱、正文或文章')) ?>">
              <button class="button button--secondary" type="submit"><?= h(sblog_t('搜索')) ?></button>
            </form>
          </div>

          <div class="panel__body panel__body--flush">
            <?php if ($comments): ?>
              <div class="table-wrap">
                <table class="admin-table comment-table">
                  <thead>
                    <tr>
                      <th><label class="table-check"><input type="checkbox" data-check-all="comment_ids[]" aria-label="<?= h(sblog_t('全选评论')) ?>"><span class="sr-only"><?= h(sblog_t('全选')) ?></span></label></th>
                      <th><?= h(sblog_t('评论者')) ?></th>
                      <th><?= h(sblog_t('内容与文章')) ?></th>
                      <th><?= h(sblog_t('状态')) ?></th>
                      <th><?= h(sblog_t('提交时间')) ?></th>
                      <th><?= h(sblog_t('操作')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($comments as $comment): ?>
                      <?php $state = comment_status_meta((string)$comment['status']); ?>
                      <?php $authorUrl = safe_link_url((string)$comment['author_url']); ?>
                      <tr class="comment-row<?= (int)$comment['is_read'] === 0 ? ' is-unread' : '' ?>">
                        <td><label class="table-check"><input type="checkbox" name="comment_ids[]" value="<?= h((string)$comment['id']) ?>" form="comments-bulk-form" aria-label="<?= h(sblog_t('选择 {author} 的评论', ['author' => (string)$comment['author_name']])) ?>"><span class="sr-only"><?= h(sblog_t('选择评论')) ?></span></label></td>
                        <td>
                          <div class="table-title">
                            <strong><?= h((string)$comment['author_name']) ?></strong>
                            <span><?= h((string)$comment['author_email']) ?></span>
                            <?php if ((string)$comment['ip_address'] !== ''): ?><span><?= h(sblog_t('IP：{address}', ['address' => (string)$comment['ip_address']])) ?></span><?php endif; ?>
                            <?php if ($authorUrl !== '#'): ?><a href="<?= h($authorUrl) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= h((string)parse_url($authorUrl, PHP_URL_HOST)) ?></a><?php endif; ?>
                          </div>
                        </td>
                        <td>
                          <div class="comment-summary">
                            <?php if (trim((string)$comment['reply_to_name']) !== ''): ?><span class="comment-summary__reply"><?= h(sblog_t('回复 @{name}', ['name' => (string)$comment['reply_to_name']])) ?></span><?php endif; ?>
                            <p><?= h(comment_excerpt((string)$comment['content'])) ?></p>
                            <a href="<?= h(content_permalink(['kind' => (string)$comment['post_kind'], 'slug' => (string)$comment['post_slug']])) ?><?= (string)$comment['status'] === 'approved' && (string)$comment['post_kind'] === 'post' ? '#comment-' . h((string)$comment['id']) : '' ?>"><?= h((string)$comment['post_title']) ?></a>
                          </div>
                        </td>
                        <td>
                          <span class="status-badge status-badge--<?= h((string)$state['class']) ?>"><?= h((string)$state['label']) ?></span>
                          <?php if ((int)$comment['is_read'] === 0): ?><span class="comment-unread-dot"><?= h(sblog_t('未读')) ?></span><?php endif; ?>
                        </td>
                        <td><time datetime="<?= h(date(DATE_ATOM, (int)$comment['created_at'])) ?>"><?= h(pretty_date((int)$comment['created_at'], true)) ?></time></td>
                        <td>
                          <form class="table-actions comment-actions" method="post" action="<?= h(url_for('moderate_comments')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="comment_id" value="<?= h((string)$comment['id']) ?>">
                            <input type="hidden" name="filter" value="<?= h($filter) ?>">
                            <input type="hidden" name="q" value="<?= h($search) ?>">
                            <input type="hidden" name="p" value="<?= h((string)$page) ?>">
                            <?php if ((string)$comment['status'] !== 'approved'): ?><button class="button button--ghost" name="action" value="approve" type="submit"><?= h(sblog_t('通过')) ?></button><?php endif; ?>
                            <?php if ((string)$comment['status'] === 'approved'): ?><button class="button button--ghost" name="action" value="pending" type="submit"><?= h(sblog_t('撤下')) ?></button><?php endif; ?>
                            <?php if ((string)$comment['status'] !== 'spam'): ?><button class="button button--ghost" name="action" value="spam" type="submit"><?= h(sblog_t('垃圾')) ?></button><?php endif; ?>
                            <?php if ((int)$comment['is_read'] === 0): ?><button class="button button--ghost" name="action" value="read" type="submit"><?= h(sblog_t('已读')) ?></button><?php endif; ?>
                            <button class="button button--danger" name="action" value="delete" type="submit" onclick="return confirm(<?= h(json_encode(sblog_t('确定永久删除这条评论吗？'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>);"><?= h(sblog_t('删除')) ?></button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="admin-table-footer">
                <form id="comments-bulk-form" class="comment-bulk-form" method="post" action="<?= h(url_for('moderate_comments')) ?>" onsubmit="return this.elements.action.value !== 'delete' || confirm(<?= h(json_encode(sblog_t('确定永久删除选中的评论吗？'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>);">
                  <?= csrf_field() ?>
                  <input type="hidden" name="filter" value="<?= h($filter) ?>">
                  <input type="hidden" name="q" value="<?= h($search) ?>">
                  <input type="hidden" name="p" value="<?= h((string)$page) ?>">
                  <label for="comment-bulk-action"><?= h(sblog_t('批量操作')) ?></label>
                  <select id="comment-bulk-action" name="action" required>
                    <option value=""><?= h(sblog_t('请选择')) ?></option>
                    <option value="approve"><?= h(sblog_t('通过')) ?></option>
                    <option value="pending"><?= h(sblog_t('转待审核')) ?></option>
                    <option value="spam"><?= h(sblog_t('标记垃圾')) ?></option>
                    <option value="read"><?= h(sblog_t('标为已读')) ?></option>
                    <option value="delete"><?= h(sblog_t('删除')) ?></option>
                  </select>
                  <button class="button button--secondary" type="submit"><?= h(sblog_t('应用')) ?></button>
                </form>

                <?php if ($totalPages > 1): ?>
                  <nav class="admin-pagination" aria-label="<?= h(sblog_t('评论分页')) ?>">
                    <?php if ($page > 1): ?><a class="button button--secondary" href="<?= h(admin_comments_url($filter, $search, $page - 1)) ?>"><?= h(sblog_t('上一页')) ?></a><?php endif; ?>
                    <span><?= h(sblog_t('第 {page} / {pages} 页', ['page' => $page, 'pages' => $totalPages])) ?></span>
                    <?php if ($page < $totalPages): ?><a class="button button--secondary" href="<?= h(admin_comments_url($filter, $search, $page + 1)) ?>"><?= h(sblog_t('下一页')) ?></a><?php endif; ?>
                  </nav>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="empty-state empty-state--inside"><p><?= h($search !== '' ? sblog_t('没有匹配的评论。') : sblog_t('当前筛选下没有评论。')) ?></p></div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>
    <?php
    render_layout(sblog_t('评论管理'), (string)ob_get_clean(), [
        'active' => 'comments',
        'wide' => true,
        'description' => sblog_t('博客评论管理'),
    ]);
}

function render_admin_categories_page(array $form = [], array $errors = []): void
{
    require_admin();

    $categories = fetch_categories();
    $editing = null;
    $editId = (int)($_GET['id'] ?? $form['id'] ?? 0);
    if ($editId > 0) {
        $editing = one('SELECT * FROM categories WHERE id = ?', [$editId]);
    }

    $values = array_merge([
        'id' => (string)($editing['id'] ?? ''),
        'name' => (string)($editing['name'] ?? ''),
        'slug' => (string)($editing['slug'] ?? ''),
        'description' => (string)($editing['description'] ?? ''),
        'sort_order' => (string)($editing['sort_order'] ?? '0'),
    ], $form);
    $sidebar = render_admin_sidebar('categories');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('分类管理')) ?>

        <div class="admin-grid admin-grid--split">
          <section class="panel admin-list-panel admin-animate admin-animate--2">
            <div class="panel__header">
              <h2><?= h(sblog_t('分类列表')) ?></h2>
              <p class="panel__meta"><?= h(sblog_t('分类用于组织文章，不影响独立页面。')) ?></p>
            </div>
            <div class="panel__body panel__body--flush">
              <?php if ($categories): ?>
                <div class="table-wrap">
                  <table class="admin-table">
                    <thead>
                    <tr>
                      <th><?= h(sblog_t('分类')) ?></th>
                      <th><?= h(sblog_t('Slug')) ?></th>
                      <th><?= h(sblog_t('文章数')) ?></th>
                      <th><?= h(sblog_t('排序')) ?></th>
                      <th><?= h(sblog_t('操作')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                      <tr>
                        <td>
                          <div class="table-title">
                            <strong><?= h((string)$category['name']) ?></strong>
                            <span><?= h((string)$category['description']) ?></span>
                          </div>
                        </td>
                        <td><?= h((string)$category['slug']) ?></td>
                        <td><?= h((string)$category['post_count']) ?></td>
                        <td><?= h((string)$category['sort_order']) ?></td>
                        <td>
                          <div class="table-actions">
                            <a class="button button--ghost" href="<?= h(url_with_query(url_for('admin_categories'), ['id' => (int)$category['id']])) ?>"><?= h(sblog_t('编辑')) ?></a>
                            <form method="post" action="<?= h(url_for('delete_category')) ?>" onsubmit="return confirm(<?= h(json_encode(sblog_t('确定删除这个空分类吗？'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>);">
                              <?= csrf_field() ?>
                              <input type="hidden" name="id" value="<?= h($category['id']) ?>">
                              <button class="button button--danger" type="submit"><?= h(sblog_t('删除')) ?></button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="empty-state empty-state--inside">
                  <p><?= h(sblog_t('还没有分类。')) ?></p>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="panel admin-list-panel admin-animate admin-animate--3">
            <div class="panel__header">
              <h2><?= h($editing ? sblog_t('编辑分类') : sblog_t('新建分类')) ?></h2>
              <p class="panel__meta"><?= h(sblog_t('名称、URL 标识和排序。')) ?></p>
            </div>
            <div class="panel__body">
              <?php if ($errors): ?>
                <div class="flash flash--error"><?= h(implode(' ', translated_admin_form_errors($errors))) ?></div>
              <?php endif; ?>

              <form class="form-stack" method="post" action="<?= h(url_for('save_category')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= h((string)$values['id']) ?>">
                <div class="field">
                  <label for="category_name"><?= h(sblog_t('分类名称')) ?></label>
                  <input id="category_name" name="name" type="text" value="<?= h((string)$values['name']) ?>" required>
                </div>
                <div class="field-grid">
                  <div class="field">
                    <label for="category_slug"><?= h(sblog_t('Slug')) ?></label>
                    <input id="category_slug" name="slug" type="text" value="<?= h((string)$values['slug']) ?>" placeholder="<?= h(sblog_t('留空自动生成')) ?>">
                  </div>
                  <div class="field">
                    <label for="category_sort"><?= h(sblog_t('排序权重')) ?></label>
                    <input id="category_sort" name="sort_order" type="number" value="<?= h((string)$values['sort_order']) ?>">
                  </div>
                </div>
                <div class="field">
                  <label for="category_description"><?= h(sblog_t('分类描述')) ?></label>
                  <textarea id="category_description" name="description" rows="4"><?= h((string)$values['description']) ?></textarea>
                </div>
                <div class="action-row">
                  <?php if ($editing): ?>
                    <a class="button button--secondary" href="<?= h(url_for('admin_categories')) ?>"><?= h(sblog_t('取消编辑')) ?></a>
                  <?php endif; ?>
                  <button class="button" type="submit"><?= h($editing ? sblog_t('保存分类') : sblog_t('创建分类')) ?></button>
                </div>
              </form>
            </div>
          </section>
        </div>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout(sblog_t('分类管理'), $content, [
        'active' => 'categories',
        'wide' => true,
        'description' => sblog_t('博客分类管理'),
    ]);
}

function render_admin_links_page(array $form = [], array $errors = []): void
{
    require_admin();
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    $id = (int)($_GET['id'] ?? $form['id'] ?? 0);
    $editing = $id > 0 ? one('SELECT * FROM links WHERE id = ?', [$id]) : null;
    $values = array_merge([
        'id' => (string)($editing['id'] ?? ''), 'name' => (string)($editing['name'] ?? ''),
        'url' => (string)($editing['url'] ?? ''), 'icon_url' => (string)($editing['icon_url'] ?? ''), 'description' => (string)($editing['description'] ?? ''),
        'sort_order' => (string)($editing['sort_order'] ?? '0'),
    ], $form);
    $sidebar = render_admin_sidebar('links');
    ob_start();
    ?>
    <div class="admin-shell"><?= $sidebar ?><div class="admin-main">
      <?= render_admin_topbar(sblog_t('友情链接')) ?>
      <div class="admin-grid admin-grid--split">
        <section class="panel admin-list-panel"><div class="panel__header"><h2><?= h(sblog_t('链接列表')) ?></h2><p class="panel__meta"><?= h(sblog_t('排序数字越小越靠前。')) ?></p></div><div class="panel__body panel__body--flush">
          <?php if ($links): ?><div class="table-wrap"><table class="admin-table"><thead><tr><th><?= h(sblog_t('名称')) ?></th><th><?= h(sblog_t('网址')) ?></th><th><?= h(sblog_t('排序')) ?></th><th><?= h(sblog_t('操作')) ?></th></tr></thead><tbody>
          <?php foreach ($links as $link): ?><tr><td><div class="table-title"><strong><?= h((string)$link['name']) ?></strong><span><?= h((string)$link['description']) ?></span></div></td><td><a href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$link['url']) ?></a></td><td><?= h((string)$link['sort_order']) ?></td><td><div class="table-actions"><a class="button button--ghost" href="<?= h(url_with_query(url_for('admin_links'), ['id' => (int)$link['id']])) ?>"><?= h(sblog_t('编辑')) ?></a><form method="post" action="<?= h(url_for('delete_link')) ?>" onsubmit="return confirm(<?= h(json_encode(sblog_t('确定删除这个链接吗？'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>);"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($link['id']) ?>"><button class="button button--danger" type="submit"><?= h(sblog_t('删除')) ?></button></form></div></td></tr><?php endforeach; ?>
          </tbody></table></div><?php else: ?><div class="empty-state empty-state--inside"><p><?= h(sblog_t('还没有友情链接。')) ?></p></div><?php endif; ?>
        </div></section>
        <section class="panel admin-list-panel"><div class="panel__header"><h2><?= h($editing ? sblog_t('编辑链接') : sblog_t('添加链接')) ?></h2></div><div class="panel__body">
          <?php if ($errors): ?><div class="flash flash--error"><?= h(implode(' ', translated_admin_form_errors($errors))) ?></div><?php endif; ?>
          <form class="form-stack" method="post" action="<?= h(url_for('save_link')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h((string)$values['id']) ?>">
            <div class="field"><label for="link_name"><?= h(sblog_t('网站名称')) ?></label><input id="link_name" name="name" value="<?= h((string)$values['name']) ?>" required></div>
            <div class="field"><label for="link_url"><?= h(sblog_t('网站地址')) ?></label><input id="link_url" name="url" type="url" value="<?= h((string)$values['url']) ?>" placeholder="https://example.com" required></div>
            <div class="field"><label for="link_icon_url"><?= h(sblog_t('网站图标地址')) ?></label><input id="link_icon_url" name="icon_url" type="url" value="<?= h((string)$values['icon_url']) ?>" placeholder="https://example.com/favicon.ico"></div>
            <div class="field"><label for="link_description"><?= h(sblog_t('简短描述')) ?></label><textarea id="link_description" name="description" rows="3"><?= h((string)$values['description']) ?></textarea></div>
            <div class="field"><label for="link_sort"><?= h(sblog_t('排序')) ?></label><input id="link_sort" name="sort_order" type="number" value="<?= h((string)$values['sort_order']) ?>"></div>
            <div class="action-row"><?php if ($editing): ?><a class="button button--secondary" href="<?= h(url_for('admin_links')) ?>"><?= h(sblog_t('取消编辑')) ?></a><?php endif; ?><button class="button" type="submit"><?= h($editing ? sblog_t('保存修改') : sblog_t('添加链接')) ?></button></div>
          </form>
        </div></section>
      </div>
    </div></div>
    <?php
    render_layout(sblog_t('友情链接'), (string)ob_get_clean(), ['active' => 'links', 'wide' => true, 'description' => sblog_t('友情链接管理')]);
}

function replace_tag_everywhere(string $old, ?string $new): void
{
    foreach (all_rows('SELECT id, tags FROM posts') as $post) {
        $tags = post_tags($post);
        $changed = false;
        $result = [];
        foreach ($tags as $tag) {
            if (str_lower_u($tag) === str_lower_u($old)) {
                $changed = true;
                if ($new !== null && $new !== '') { $result[] = $new; }
            } else { $result[] = $tag; }
        }
        if ($changed) { q('UPDATE posts SET tags = ?, updated_at = ? WHERE id = ?', [encode_tags(parse_tags_input(implode(',', $result))), time(), (int)$post['id']]); }
    }
}

function render_admin_tags_page(array $form = [], array $errors = []): void
{
    require_admin();
    $tags = tag_index_data(false);
    $old = trim((string)($_GET['tag'] ?? $form['old_tag'] ?? ''));
    $currentSlug = $old !== '' ? tag_slug_for_label($old) : '';
    $sidebar = render_admin_sidebar('tags');
    ob_start(); ?>
    <div class="admin-shell"><?= $sidebar ?><div class="admin-main"><?= render_admin_topbar(sblog_t('标签管理')) ?><div class="admin-grid admin-grid--split">
      <section class="panel admin-list-panel"><div class="panel__header"><h2><?= h(sblog_t('标签列表')) ?></h2><p class="panel__meta"><?= h(sblog_tn('标签来自文章内容，共 {count} 个标签。', count($tags))) ?></p></div><div class="panel__body panel__body--flush">
      <?php if ($tags): ?><form method="post" action="<?= h(url_for('delete_tag')) ?>" onsubmit="return confirm(<?= h(json_encode(sblog_t('确定删除选中的标签吗？文章本身不会被删除。'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>);"><?= csrf_field() ?><div class="table-wrap"><table class="admin-table"><thead><tr><th><input type="checkbox" aria-label="<?= h(sblog_t('全选')) ?>" data-check-all="tag_ids[]"></th><th><?= h(sblog_t('标签')) ?></th><th><?= h(sblog_t('Slug')) ?></th><th><?= h(sblog_t('文章数')) ?></th><th><?= h(sblog_t('操作')) ?></th></tr></thead><tbody><?php foreach ($tags as $tag): ?><tr><td><input type="checkbox" name="tag_ids[]" value="<?= h((string)$tag['label']) ?>" aria-label="<?= h(sblog_t('选择标签 {tag}', ['tag' => (string)$tag['label']])) ?>"></td><td><strong>#<?= h((string)$tag['label']) ?></strong></td><td><?= h((string)$tag['slug']) ?></td><td><?= h((string)$tag['count']) ?></td><td><a class="button button--ghost" href="<?= h(url_with_query(url_for('admin_tags'), ['tag' => (string)$tag['label']])) ?>"><?= h(sblog_t('修改')) ?></a></td></tr><?php endforeach; ?></tbody></table></div><div class="panel__body"><button class="button button--danger" type="submit"><?= h(sblog_t('批量删除')) ?></button></div></form><?php else: ?><div class="empty-state empty-state--inside"><p><?= h(sblog_t('还没有标签。')) ?></p></div><?php endif; ?>
      </div></section>
      <section class="panel admin-list-panel"><div class="panel__header"><h2><?= h(sblog_t('修改标签')) ?></h2></div><div class="panel__body"><?php if ($errors): ?><div class="flash flash--error"><?= h(implode(' ', translated_admin_form_errors($errors))) ?></div><?php endif; ?><form class="form-stack" method="post" action="<?= h(url_for('save_tag')) ?>"><?= csrf_field() ?><div class="field"><label><?= h(sblog_t('原标签')) ?></label><input name="old_tag" value="<?= h($old) ?>" readonly required></div><div class="field"><label><?= h(sblog_t('标签名称')) ?></label><input name="new_tag" value="<?= h((string)($form['new_tag'] ?? $old)) ?>" required></div><div class="field"><label><?= h(sblog_t('Slug')) ?></label><input name="tag_slug" value="<?= h((string)($form['tag_slug'] ?? $currentSlug)) ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required><p class="field-hint"><?= h(sblog_t('仅使用小写字母、数字和连字符。')) ?></p></div><div class="action-row"><button class="button"><?= h(sblog_t('保存修改')) ?></button></div></form></div></section>
    </div></div></div><?php
    render_layout(sblog_t('标签管理'), (string)ob_get_clean(), ['active' => 'tags', 'wide' => true, 'description' => sblog_t('标签管理')]);
}

function render_admin_users_page(array $form = [], array $errors = []): void
{
    require_admin();
    $adminId = (int)(current_admin()['id'] ?? 0);
    $account = one('SELECT * FROM users WHERE id = ?', [$adminId]) ?? [];
    $username = (string)($form['username'] ?? $account['username'] ?? '');
    $profileDefaults = ['nickname' => '', 'email' => '', 'avatar_url' => '', 'website_url' => '', 'signature' => ''];
    foreach (social_profile_definitions() as $definition) { $profileDefaults[$definition['column']] = ''; }
    $profile = array_merge($profileDefaults, $account, $form);
    $socialLabels = [
        'github' => sblog_t('GitHub'),
        'qq' => sblog_t('QQ'),
        'wechat' => sblog_t('微信'),
        'weibo' => sblog_t('微博'),
        'x' => sblog_t('X'),
        'telegram' => sblog_t('Telegram'),
        'mastodon' => sblog_t('Mastodon'),
        'bilibili' => sblog_t('哔哩哔哩'),
        'instagram' => sblog_t('Instagram'),
        'tiktok' => sblog_t('TikTok'),
    ];
    $sidebar = render_admin_sidebar('users');
    ob_start(); ?>
    <div class="admin-shell"><?= $sidebar ?><div class="admin-main"><?= render_admin_topbar(sblog_t('用户设置')) ?><div class="admin-grid admin-user-settings">
      <section class="admin-profile-settings" aria-labelledby="user-settings-title">
        <header class="admin-profile-settings__header">
          <h2 id="user-settings-title"><?= h(sblog_t('用户设置')) ?></h2>
          <p>@<?= h($username) ?></p>
        </header>
        <?php if ($errors): ?><div class="flash flash--error"><?= h(implode(' ', translated_admin_form_errors($errors))) ?></div><?php endif; ?>
        <form class="admin-profile-form" method="post" action="<?= h(url_for('save_user')) ?>">
          <?= csrf_field() ?>
          <section class="admin-profile-section" aria-labelledby="account-settings-title">
            <div class="admin-profile-section__title"><h3 id="account-settings-title"><?= h(sblog_t('账户信息')) ?></h3></div>
            <div class="admin-profile-section__fields">
              <div class="field-grid">
                <div class="field"><label for="user-username"><?= h(sblog_t('用户名')) ?></label><input id="user-username" name="username" value="<?= h($username) ?>" autocomplete="username" required></div>
                <div class="field"><label for="user-email"><?= h(sblog_t('邮箱地址')) ?></label><input id="user-email" name="email" type="email" value="<?= h((string)$profile['email']) ?>" autocomplete="email"></div>
              </div>
              <div class="field-grid field-grid--triple">
                <div class="field"><label for="user-current-password"><?= h(sblog_t('原密码')) ?></label><input id="user-current-password" name="current_password" type="password" autocomplete="current-password"></div>
                <div class="field"><label for="user-password"><?= h(sblog_t('新密码')) ?></label><input id="user-password" name="password" type="password" minlength="8" autocomplete="new-password"></div>
                <div class="field"><label for="user-password-confirm"><?= h(sblog_t('确认新密码')) ?></label><input id="user-password-confirm" name="password_confirm" type="password" minlength="8" autocomplete="new-password"></div>
              </div>
              <p class="field-hint"><?= h(sblog_t('留空则不修改。')) ?></p>
            </div>
          </section>
          <section class="admin-profile-section" aria-labelledby="public-profile-title">
            <div class="admin-profile-section__title"><h3 id="public-profile-title"><?= h(sblog_t('公开资料')) ?></h3></div>
            <div class="admin-profile-section__fields">
              <div class="field-grid">
                <div class="field"><label for="user-nickname"><?= h(sblog_t('昵称')) ?></label><input id="user-nickname" name="nickname" value="<?= h((string)$profile['nickname']) ?>" required></div>
                <div class="field"><label for="user-avatar"><?= h(sblog_t('头像地址')) ?></label><input id="user-avatar" name="avatar_url" type="url" maxlength="300" value="<?= h((string)$profile['avatar_url']) ?>" placeholder="https://example.com/avatar.jpg"></div>
              </div>
              <div class="field"><label for="user-signature"><?= h(sblog_t('个人签名档')) ?></label><textarea id="user-signature" name="signature" rows="3" placeholder="<?= h(sblog_t('一句话介绍自己')) ?>"><?= h((string)$profile['signature']) ?></textarea></div>
              <div class="field"><label for="user-website"><?= h(sblog_t('网站地址')) ?></label><input id="user-website" name="website_url" type="url" maxlength="300" value="<?= h((string)$profile['website_url']) ?>" placeholder="https://example.com"></div>
            </div>
          </section>
          <section class="admin-profile-section" aria-labelledby="social-profile-title">
            <div class="admin-profile-section__title"><h3 id="social-profile-title"><?= h(sblog_t('社交链接')) ?></h3></div>
            <div class="admin-profile-section__fields field-grid">
              <?php foreach (social_profile_definitions() as $key => $definition): ?>
                <div class="field">
                  <label for="social-<?= h($key) ?>"><?= h($socialLabels[$key] ?? (string)$definition['label']) ?></label>
                  <input id="social-<?= h($key) ?>" name="social_<?= h($key) ?>" type="url" maxlength="300" value="<?= h((string)$profile[$definition['column']]) ?>" placeholder="<?= h((string)$definition['placeholder']) ?>">
                </div>
              <?php endforeach; ?>
            </div>
          </section>
          <div class="admin-profile-actions"><button class="button" type="submit"><?= h(sblog_t('保存修改')) ?></button></div>
        </form>
      </section>
    </div></div></div><?php
    render_layout(sblog_t('用户设置'), (string)ob_get_clean(), ['active' => 'users', 'wide' => true, 'description' => sblog_t('用户设置')]);
}

function render_admin_media_page(): void
{
    require_admin();

    $search = trim((string)($_GET['q'] ?? ''));
    $type = (string)($_GET['type'] ?? 'all');
    if (!in_array($type, ['all', 'images', 'files'], true)) {
        $type = 'all';
    }
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(title LIKE ? OR original_name LIKE ? OR caption LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term);
    }
    if ($type === 'images') {
        $where[] = 'is_image = 1';
    } elseif ($type === 'files') {
        $where[] = 'is_image = 0';
    }
    $sql = 'SELECT * FROM media' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC, id DESC';
    $mediaItems = all_rows($sql, $params);
    $total = (int)val('SELECT COUNT(*) FROM media');
    $editId = max(0, (int)($_GET['id'] ?? 0));
    $editing = $editId > 0 ? one('SELECT * FROM media WHERE id = ?', [$editId]) : null;

    ob_start(); ?>
    <div class="admin-shell">
      <?= render_admin_sidebar('media') ?>
      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('媒体库')) ?>
        <div class="media-library admin-animate admin-animate--2">
          <section class="panel media-library-upload">
            <div class="panel__header"><h2><?= h(sblog_t('上传媒体')) ?></h2><p class="panel__meta"><?= h(sblog_t('图片、PDF、文本和 ZIP 文件，每个最大 30M。')) ?></p></div>
            <div class="panel__body">
              <div class="attachment-uploader" data-upload-url="<?= h(url_for('upload_attachment')) ?>" data-csrf="<?= h(csrf_token()) ?>" data-refresh-on-upload="1">
                <input id="mediaAttachmentInput" class="attachment-input" type="file" name="attachments[]" multiple>
                <label class="attachment-drop" for="mediaAttachmentInput">
                  <span class="attachment-drop__title"><?= h(sblog_t('选择或拖入媒体文件')) ?></span>
                  <span class="attachment-drop__hint"><?= h(sblog_t('上传完成后会自动加入媒体库。')) ?></span>
                </label>
                <div class="attachment-list" aria-live="polite"></div>
              </div>
            </div>
          </section>

          <form class="media-library-toolbar" method="get" action="<?= h(url_for('admin_media')) ?>">
            <?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="admin_media"><?php endif; ?>
            <strong><?= h(sblog_tn('媒体资料：{count} 项', $total)) ?></strong>
            <label class="media-library-search"><span class="sr-only"><?= h(sblog_t('搜索媒体')) ?></span><input name="q" type="search" value="<?= h($search) ?>" placeholder="<?= h(sblog_t('搜索媒体')) ?>"></label>
            <label class="media-library-filter"><span class="sr-only"><?= h(sblog_t('媒体类型')) ?></span><select name="type"><option value="all"<?= $type === 'all' ? ' selected' : '' ?>><?= h(sblog_t('全部媒体')) ?></option><option value="images"<?= $type === 'images' ? ' selected' : '' ?>><?= h(sblog_t('图片')) ?></option><option value="files"<?= $type === 'files' ? ' selected' : '' ?>><?= h(sblog_t('文件')) ?></option></select></label>
            <button class="button button--secondary" type="submit"><?= h(sblog_t('筛选')) ?></button>
          </form>

          <?php if ($editing): ?>
            <section class="panel media-editor" id="media-editor">
              <div class="panel__header"><h2><?= h(sblog_t('编辑媒体')) ?></h2><p class="panel__meta"><?= h((string)$editing['original_name']) ?></p></div>
              <div class="panel__body">
                <form class="form-stack" method="post" action="<?= h(url_for('save_media')) ?>">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= h((string)$editing['id']) ?>">
                  <div class="field"><label for="mediaTitle"><?= h(sblog_t('标题')) ?></label><input id="mediaTitle" name="title" value="<?= h((string)$editing['title']) ?>" maxlength="255" required></div>
                  <?php if (!empty($editing['is_image'])): ?><div class="field"><label for="mediaAlt"><?= h(sblog_t('替代文本')) ?></label><input id="mediaAlt" name="alt_text" value="<?= h((string)$editing['alt_text']) ?>" maxlength="500"></div><?php endif; ?>
                  <div class="field"><label for="mediaCaption"><?= h(sblog_t('说明文字')) ?></label><textarea id="mediaCaption" name="caption" rows="3" maxlength="2000"><?= h((string)$editing['caption']) ?></textarea></div>
                  <div class="action-row"><a class="button button--secondary" href="<?= h(url_for('admin_media')) ?>"><?= h(sblog_t('取消')) ?></a><button class="button" type="submit"><?= h(sblog_t('保存媒体')) ?></button></div>
                </form>
              </div>
            </section>
          <?php elseif ($editId > 0): ?>
            <div class="flash flash--error"><?= h(sblog_t('找不到媒体资料。')) ?></div>
          <?php endif; ?>

          <?php if ($mediaItems): ?>
            <div class="media-library-grid">
              <?php foreach ($mediaItems as $media): ?>
                <?php $extension = strtoupper(pathinfo((string)$media['original_name'], PATHINFO_EXTENSION)) ?: sblog_t('文件'); ?>
                <article class="media-library-item">
                  <a class="media-library-preview" href="<?= h((string)$media['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= h(sblog_t('打开文件')) ?>" title="<?= h((string)$media['original_name']) ?>">
                    <?php if (!empty($media['is_image'])): ?><img src="<?= h((string)$media['url']) ?>" alt="<?= h((string)($media['alt_text'] ?: $media['title'])) ?>" loading="lazy"><?php else: ?><span><?= h(str_sub_u($extension, 0, 8)) ?></span><?php endif; ?>
                  </a>
                  <div class="media-library-meta">
                    <strong title="<?= h((string)$media['original_name']) ?>"><?= h((string)($media['title'] ?: $media['original_name'])) ?></strong>
                    <span><?= h((string)$media['mime_type']) ?> · <?= h(media_file_size((int)$media['file_size'])) ?></span>
                    <span><?= h(pretty_date((int)$media['created_at'], true)) ?><?= (int)$media['width'] > 0 ? ' · ' . h((string)$media['width']) . '×' . h((string)$media['height']) : '' ?></span>
                  </div>
                  <div class="media-library-actions">
                    <a class="button button--ghost" href="<?= h((string)$media['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h(sblog_t('打开')) ?></a>
                    <a class="button button--ghost" href="<?= h(url_with_query(url_for('admin_media'), ['id' => (int)$media['id']])) ?>#media-editor"><?= h(sblog_t('编辑')) ?></a>
                    <form method="post" action="<?= h(url_for('delete_media')) ?>" onsubmit="return confirm(<?= h(json_encode(sblog_t('删除此媒体资料？文件将被永久删除。'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>);"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h((string)$media['id']) ?>"><button class="button button--danger" type="submit"><?= h(sblog_t('删除')) ?></button></form>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty-state media-library-empty"><p><?= h($search !== '' || $type !== 'all' ? sblog_t('没有匹配的媒体资料。') : sblog_t('暂无媒体资料。')) ?></p></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    render_layout(sblog_t('媒体库'), (string)ob_get_clean(), ['active' => 'media', 'wide' => true, 'description' => sblog_t('媒体资料管理')]);
}

function render_admin_plugins_page(): void
{
    require_admin();

    $plugins = available_plugins();
    $active = active_plugin_slugs(true);
    $errors = is_array($GLOBALS['sblog_plugin_errors'] ?? null) ? $GLOBALS['sblog_plugin_errors'] : [];
    $sidebar = render_admin_sidebar('plugins');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('插件管理')) ?>

        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header">
            <h2><?= h(sblog_t('插件管理')) ?></h2>
            <p class="panel__meta"><?= h(sblog_t('启用可信插件，为博客增加功能或语言支持。')) ?></p>
          </div>
          <div class="panel__body panel__body--flush">
            <?php if ($plugins): ?>
              <div class="table-wrap">
                <table class="admin-table">
                  <thead><tr><th><?= h(sblog_t('插件')) ?></th><th><?= h(sblog_t('作者')) ?></th><th><?= h(sblog_t('版本')) ?></th><th><?= h(sblog_t('状态')) ?></th><th><?= h(sblog_t('操作')) ?></th></tr></thead>
                  <tbody>
                  <?php foreach ($plugins as $slug => $plugin): ?>
                    <?php
                    $isActive = in_array($slug, $active, true);
                    $displayMetadata = plugin_display_metadata((string)$slug, $plugin);
                    ?>
                    <tr>
                      <td>
                        <div class="table-title">
                          <strong><?= h($displayMetadata['name']) ?></strong>
                          <span><?= h($displayMetadata['description']) ?></span>
                        </div>
                      </td>
                      <td><?php if ($plugin['author'] !== ''): ?><?php if ($plugin['url'] !== ''): ?><a href="<?= h((string)$plugin['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$plugin['author']) ?></a><?php else: ?><?= h((string)$plugin['author']) ?><?php endif; ?><?php else: ?>—<?php endif; ?></td>
                      <td><?= h((string)($plugin['version'] ?: '—')) ?></td>
                      <td>
                        <?php if (isset($errors[$slug])): ?>
                          <span class="status-badge status-badge--draft" title="<?= h((string)$errors[$slug]) ?>"><?= h(sblog_t('加载失败')) ?></span>
                        <?php elseif ($isActive): ?>
                          <span class="status-badge status-badge--published"><?= h(sblog_t('已启用')) ?></span>
                        <?php else: ?>
                          <span class="status-badge status-badge--draft"><?= h(sblog_t('未启用')) ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="table-actions">
                          <?php if ($isActive && (string)$plugin['settings_action'] !== ''): ?>
                            <a class="button button--secondary" href="<?= h(script_url() . '?a=' . rawurlencode((string)$plugin['settings_action'])) ?>"><?= h(sblog_t('设置')) ?></a>
                          <?php endif; ?>
                          <form method="post" action="<?= h(url_for('toggle_plugin')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="plugin" value="<?= h((string)$slug) ?>">
                            <input type="hidden" name="operation" value="<?= $isActive ? 'deactivate' : 'activate' ?>">
                            <button class="button <?= $isActive ? 'button--ghost' : '' ?>" type="submit"><?= h($isActive ? sblog_t('停用') : sblog_t('启用')) ?></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="empty-state empty-state--inside"><p><?= h(sblog_t('没有发现有效插件。请将插件放入 {path}。', ['path' => 'plugins/插件目录'])) ?></p></div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>
    <?php
    render_layout(sblog_t('插件管理'), (string)ob_get_clean(), [
        'active' => 'plugins',
        'wide' => true,
        'description' => sblog_t('博客插件管理'),
    ]);
}

function render_admin_themes_page(): void
{
    require_admin();

    $themes = available_themes();
    $configuredSlug = trim(setting('active_theme', 'default'));
    $activeSlug = isset($themes[$configuredSlug]) ? $configuredSlug : 'default';
    $sidebar = render_admin_sidebar('themes');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('主题管理')) ?>

        <section class="theme-manager admin-animate admin-animate--2" aria-labelledby="theme-manager-title" data-theme-manager>
          <header class="theme-manager__header">
            <div>
              <p class="admin-masthead__eyebrow"><?= h(sblog_t('外观')) ?></p>
              <h1 id="theme-manager-title"><?= h(sblog_t('主题管理')) ?></h1>
              <p><?= h(sblog_t('预览已安装主题，并为博客前台启用新的外观。')) ?></p>
            </div>
            <span class="theme-manager__count"><?= h(sblog_tn('{count} 个主题', count($themes))) ?></span>
          </header>

          <div class="theme-grid">
            <?php foreach ($themes as $slug => $theme): ?>
              <?php
              $isActive = $slug === $activeSlug;
              $previewUrl = url_with_query(url_for('home'), ['theme_preview' => (string)$slug]);
              $displayMetadata = theme_display_metadata((string)$slug, $theme);
              $themeName = $displayMetadata['name'];
              $themeDescription = $displayMetadata['description'];
              $previewLabel = sblog_t('预览主题 {theme}', ['theme' => $themeName]);
              ?>
              <article class="theme-card<?= $isActive ? ' is-active' : '' ?>" data-theme-card data-theme-slug="<?= h((string)$slug) ?>">
                <a class="theme-card__preview" href="<?= h($previewUrl) ?>" target="_blank" rel="noopener" aria-label="<?= h($previewLabel) ?>" title="<?= h($previewLabel) ?>">
                  <iframe src="<?= h($previewUrl) ?>" loading="lazy" tabindex="-1" aria-hidden="true" title="<?= h($previewLabel) ?>"></iframe>
                  <span><?= h(sblog_t('打开预览')) ?></span>
                </a>
                <div class="theme-card__body">
                  <div class="theme-card__heading">
                    <div>
                      <h2><?= h($themeName) ?></h2>
                      <p><?= h((string)$slug) ?><?= $theme['version'] !== '' ? ' · ' . h((string)$theme['version']) : '' ?></p>
                    </div>
                    <span class="status-badge status-badge--published" data-theme-current<?= $isActive ? '' : ' hidden' ?>><?= h(sblog_t('当前主题')) ?></span>
                  </div>
                  <p class="theme-card__description"><?= h($themeDescription !== '' ? $themeDescription : sblog_t('该主题没有提供说明。')) ?></p>
                  <div class="theme-card__footer">
                    <span class="theme-card__author">
                      <?php if ($theme['author'] !== ''): ?>
                        <?= h(sblog_t('作者：')) ?><?php if ($theme['url'] !== ''): ?><a href="<?= h((string)$theme['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$theme['author']) ?></a><?php else: ?><?= h((string)$theme['author']) ?><?php endif; ?>
                      <?php else: ?>
                        <?= h(sblog_t('作者未注明')) ?>
                      <?php endif; ?>
                    </span>
                    <form method="post" action="<?= h(url_for('activate_theme')) ?>" data-theme-activate<?= $isActive ? ' hidden' : '' ?>>
                      <?= csrf_field() ?>
                      <input type="hidden" name="theme" value="<?= h((string)$slug) ?>">
                      <button class="button" type="submit"><?= h(sblog_t('启用')) ?></button>
                    </form>
                    <span class="button button--ghost is-disabled" aria-disabled="true" data-theme-active<?= $isActive ? '' : ' hidden' ?>><?= h(sblog_t('已启用')) ?></span>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
    <?php
    render_layout(sblog_t('主题管理'), (string)ob_get_clean(), [
        'active' => 'themes',
        'wide' => true,
        'description' => sblog_t('博客前台主题管理'),
    ]);
}

function render_admin_settings_page(): void
{
    require_admin();

    $sidebar = render_admin_sidebar('settings');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('站点设置')) ?>

        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header">
            <h2><?= h(sblog_t('站点设置')) ?></h2>
            <p class="panel__meta"><?= h(sblog_t('名称、地址、首页展示与伪静态配置。')) ?></p>
          </div>
          <div class="panel__body">
            <form class="form-stack" method="post" action="<?= h(url_for('save_settings')) ?>">
              <?= csrf_field() ?>
              <div class="field"><label for="site_name"><?= h(sblog_t('站点名称')) ?></label><input id="site_name" name="site_name" type="text" value="<?= h(setting('site_name')) ?>" required></div>
              <div class="field"><label for="site_tagline"><?= h(sblog_t('首页副标题')) ?></label><input id="site_tagline" name="site_tagline" type="text" value="<?= h(setting('site_tagline')) ?>"></div>
              <div class="field"><label for="site_description"><?= h(sblog_t('站点描述')) ?></label><textarea id="site_description" name="site_description" rows="3"><?= h(setting('site_description')) ?></textarea></div>
              <div class="field"><label for="site_keywords"><?= h(sblog_t('站点关键字')) ?></label><input id="site_keywords" name="site_keywords" value="<?= h(setting('site_keywords')) ?>" placeholder="<?= h(sblog_t('PHP, SQLite, 博客')) ?>"><p class="field-hint"><?= h(sblog_t('使用英文逗号分隔，页面将输出为 SEO keywords 元信息。')) ?></p></div>
              <div class="field">
                <label for="site_url"><?= h(sblog_t('站点地址')) ?></label>
                <input id="site_url" name="site_url" type="url" value="<?= h(setting('site_url')) ?>" placeholder="https://example.com/blog">
                <p class="field-hint"><?= h(sblog_t('RSS 会优先使用这里的绝对地址，子目录部署时请带上完整路径。')) ?></p>
              </div>
              <div class="field"><label for="favicon_url"><?= h(sblog_t('Favicon 地址')) ?></label><input id="favicon_url" name="favicon_url" value="<?= h(setting('favicon_url', 'favicon.png')) ?>" placeholder="favicon.png"><p class="field-hint"><?= h(sblog_t('默认使用项目根目录的 {file}，也可以填写完整图片 URL 或站内绝对路径。', ['file' => 'favicon.png'])) ?></p></div>
              <div class="field">
                <label for="footer_beian"><?= h(sblog_t('备案号')) ?></label>
                <input id="footer_beian" name="footer_beian" type="text" value="<?= h(setting('footer_beian')) ?>" placeholder="<?= h(sblog_t('京 ICP 备 12345678 号')) ?>">
              </div>
              <div class="field">
                <label for="posts_per_page"><?= h(sblog_t('首页每页文章数')) ?></label>
                <input id="posts_per_page" name="posts_per_page" type="number" min="1" max="24" value="<?= h(setting('posts_per_page', '6')) ?>">
              </div>
              <fieldset class="field settings-field">
                <legend><?= h(sblog_t('评论设置')) ?></legend>
                <div class="settings-option-list">
                  <label class="setting-option"><input id="comments_enabled" name="comments_enabled" type="checkbox" value="1"<?= setting('comments_enabled', '1') === '1' ? ' checked' : '' ?>><span><?= h(sblog_t('允许访客提交评论')) ?></span></label>
                  <label class="setting-option"><input name="comments_require_approval" type="checkbox" value="1"<?= setting('comments_require_approval', '1') === '1' ? ' checked' : '' ?>><span><?= h(sblog_t('访客首次留言需审核后展示（按邮箱判断）')) ?></span></label>
                  <label class="setting-option"><input name="comments_notify" type="checkbox" value="1"<?= setting('comments_notify', '1') === '1' ? ' checked' : '' ?>><span><?= h(sblog_t('新评论显示后台提醒')) ?></span></label>
                </div>
              </fieldset>
              <div class="field">
                  <label for="pretty_url"><?= h(sblog_t('伪静态 URL')) ?></label>
                  <select id="pretty_url" name="pretty_url">
                    <option value="0"<?= setting('pretty_url', '0') === '0' ? ' selected' : '' ?>><?= h(sblog_t('关闭')) ?></option>
                    <option value="1"<?= setting('pretty_url', '0') === '1' ? ' selected' : '' ?>><?= h(sblog_t('开启')) ?></option>
                  </select>
                  <p class="field-hint"><?= h(sblog_t('开启后文章链接会变成 {path}，需要服务器 rewrite 支持。', ['path' => '/archive/slug'])) ?></p>
                  <div class="rewrite-help" data-rewrite-help<?= setting('pretty_url', '0') === '1' ? '' : ' hidden' ?>>
                    <strong><?= h(sblog_t('Apache')) ?></strong>
                    <p><?= h(sblog_t('启用 {module}，并为当前目录设置 {setting}。项目根目录已有可直接使用的 {file}。', ['module' => 'mod_rewrite', 'setting' => 'AllowOverride All', 'file' => '.htaccess'])) ?></p>
                    <strong><?= h(sblog_t('Nginx')) ?></strong>
                    <pre><code>location ^~ /data/ { deny all; }
location ^~ /cache/ { deny all; }

location / {
    try_files $uri $uri/ /index.php?$query_string;
}</code></pre>
                    <p><?= h(sblog_t('若博客安装在子目录，请把 {entry} 改为包含子目录的入口路径，例如 {example}。', ['entry' => '/index.php', 'example' => '/blog/index.php'])) ?></p>
                  </div>
              </div>
              <div class="field">
                <label for="site_footer"><?= h(sblog_t('页脚文案')) ?></label>
                <input id="site_footer" name="site_footer" type="text" value="<?= h(setting('site_footer')) ?>" placeholder="<?= h(sblog_t('支持 {year} 占位符')) ?>">
              </div>
              <div class="field">
                <label for="custom_head_code"><?= h(sblog_t('Head 自定义代码')) ?></label>
                <textarea id="custom_head_code" name="custom_head_code" rows="10" spellcheck="false" placeholder="&lt;script&gt;...&lt;/script&gt;"><?= h(setting('custom_head_code')) ?></textarea>
                <p class="field-hint"><?= h(sblog_t('原样插入前台页面的 {closing_tag} 前，可用于统计脚本、{meta} 或 {style}；请仅使用可信代码。', ['closing_tag' => '</head>', 'meta' => 'meta', 'style' => 'style'])) ?></p>
              </div>
              <div class="action-row">
                <button class="button" type="submit"><?= h(sblog_t('保存设置')) ?></button>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout(sblog_t('站点设置'), $content, [
        'active' => 'settings',
        'wide' => true,
        'description' => sblog_t('博客站点设置'),
    ]);
}

function render_editor_page(?array $existing = null, array $form = [], array $errors = []): void
{
    require_admin();

    $categories = category_options();
    $defaultCategoryId = $categories ? (string)$categories[0]['id'] : '';
    $defaults = [
        'kind' => (string)($existing['kind'] ?? 'post'),
        'category_id' => (string)($existing['category_id'] ?? $defaultCategoryId),
        'title' => (string)($existing['title'] ?? ''),
        'slug' => (string)($existing['slug'] ?? ''),
        'tags_input' => implode(', ', post_tags($existing ?? [])),
        'excerpt' => (string)($existing['excerpt'] ?? ''),
        'content' => (string)($existing['content'] ?? ''),
        'status' => (string)($existing['status'] ?? 'draft'),
        'published_at' => $existing ? datetime_local_value((int)($existing['published_at'] ?: time())) : datetime_local_value(time()),
        'is_pinned' => (string)(int)($existing['is_pinned'] ?? 0),
        'allow_comments' => (string)(int)($existing['allow_comments'] ?? 0),
    ];

    $values = array_merge($defaults, $form);
    $isEdit = $existing !== null;
    $siteName = setting('site_name', default_settings()['site_name']);
    $editorContext = ['is_edit' => $isEdit, 'post_id' => (int)($existing['id'] ?? 0)];
    $editorActions = [];
    foreach (['slug', 'excerpt', 'content'] as $field) {
        $actionHtml = plugin_filter('editor_field_actions_html', '', $editorContext + ['field' => $field]);
        $editorActions[$field] = is_string($actionHtml) ? $actionHtml : '';
    }
    $afterFormHtml = plugin_filter('editor_after_form_html', '', $editorContext);
    $afterFormHtml = is_string($afterFormHtml) ? $afterFormHtml : '';
    $sidebar = render_admin_sidebar($isEdit ? 'edit' : 'write');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar($isEdit ? sblog_t('编辑内容') : sblog_t('撰写文章')) ?>

        <section class="panel admin-masthead admin-masthead--compact admin-animate admin-animate--2">
          <div class="panel__body admin-masthead__body">
            <div class="admin-masthead__intro">
              <img class="admin-masthead__logo" src="<?= h(theme_logo_url()) ?>" width="72" height="72" alt="<?= h($siteName) ?>">
              <div class="admin-masthead__copy">
                <p class="admin-masthead__eyebrow"><?= h($isEdit ? sblog_t('编辑') : sblog_t('撰写')) ?></p>
                <h1 class="admin-masthead__title"><?= h($isEdit ? sblog_t('编辑内容') : sblog_t('撰写文章')) ?></h1>
                <p class="admin-masthead__lead"><?= h(sblog_t('支持基础 Markdown，可创建文章或独立页面。')) ?></p>
              </div>
            </div>
            <div class="admin-masthead__actions">
              <a class="button button--secondary" href="<?= h(url_for('admin')) ?>"><?= h(sblog_t('返回后台')) ?></a>
            </div>
          </div>
        </section>

        <section class="panel editor-panel admin-animate admin-animate--3">
          <div class="panel__body">
            <?php if ($errors): ?>
              <div class="flash flash--error">
                <?= h(implode(' ', translated_admin_form_errors($errors))) ?>
              </div>
            <?php endif; ?>

            <form class="form-stack" method="post" action="<?= h($isEdit ? url_for('edit', ['id' => $existing['id']]) : url_for('write')) ?>">
              <?= csrf_field() ?>
              <div class="field">
                <label for="title"><?= h(sblog_t('标题')) ?></label>
                <input id="title" name="title" type="text" value="<?= h((string)$values['title']) ?>" required>
              </div>

              <div class="field-grid field-grid--quad">
                <div class="field">
                  <label for="kind"><?= h(sblog_t('内容类型')) ?></label>
                  <select id="kind" name="kind">
                    <option value="post"<?= (string)$values['kind'] === 'post' ? ' selected' : '' ?>><?= h(sblog_t('文章')) ?></option>
                    <option value="page"<?= (string)$values['kind'] === 'page' ? ' selected' : '' ?>><?= h(sblog_t('独立页面')) ?></option>
                  </select>
                </div>
                <div class="field">
                  <div class="field-label-row"><label for="slug"><?= h(sblog_t('Slug')) ?></label><?= $editorActions['slug'] ?></div>
                  <input id="slug" name="slug" type="text" value="<?= h((string)$values['slug']) ?>" placeholder="<?= h(sblog_t('留空将自动生成')) ?>">
                </div>
                <div class="field">
                  <label for="category_id"><?= h(sblog_t('分类')) ?></label>
                  <select id="category_id" name="category_id" required>
                    <option value="" disabled><?= h(sblog_t('请选择分类')) ?></option>
                    <?php foreach ($categories as $category): ?>
                      <option value="<?= h($category['id']) ?>"<?= (string)$values['category_id'] === (string)$category['id'] ? ' selected' : '' ?>><?= h($category['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="published_at"><?= h(sblog_t('发布时间')) ?></label>
                  <input id="published_at" name="published_at" type="datetime-local" value="<?= h((string)$values['published_at']) ?>">
                </div>
              </div>

              <div class="field">
                <label for="tags_input"><?= h(sblog_t('标签')) ?></label>
                <input id="tags_input" name="tags_input" type="text" value="<?= h((string)$values['tags_input']) ?>" placeholder="<?= h(sblog_t('用逗号分隔，例如 PHP, SQLite, 随笔')) ?>">
                <p class="field-hint"><?= h(sblog_t('独立页面可以留空，文章会用这些标签生成聚合页。')) ?></p>
              </div>

              <div class="field">
                <div class="field-label-row"><label for="excerpt"><?= h(sblog_t('摘要')) ?></label><?= $editorActions['excerpt'] ?></div>
                <textarea id="excerpt" name="excerpt" rows="3" placeholder="<?= h(sblog_t('留空将自动从正文截取')) ?>"><?= h((string)$values['excerpt']) ?></textarea>
              </div>

              <div class="field">
                <label for="status"><?= h(sblog_t('状态')) ?></label>
                <select id="status" name="status">
                  <option value="draft"<?= (string)$values['status'] === 'draft' ? ' selected' : '' ?>><?= h(sblog_t('草稿')) ?></option>
                  <option value="published"<?= (string)$values['status'] === 'published' ? ' selected' : '' ?>><?= h(sblog_t('发布')) ?></option>
                </select>
                <p class="field-hint"><?= h(sblog_t('如果发布时间晚于当前时间，前台会按定时发布处理。')) ?></p>
              </div>

              <label class="pin-option" for="is_pinned">
                <input id="is_pinned" name="is_pinned" type="checkbox" value="1"<?= (string)$values['is_pinned'] === '1' ? ' checked' : '' ?>>
                <span><strong><?= h(sblog_t('置顶文章')) ?></strong><small><?= h(sblog_t('发布后优先显示在前端文章列表顶部，仅对文章生效。')) ?></small></span>
              </label>

              <label class="pin-option page-comments-option" for="allow_comments">
                <input id="allow_comments" name="allow_comments" type="checkbox" value="1"<?= (string)$values['allow_comments'] === '1' ? ' checked' : '' ?>>
                <span><strong><?= h(sblog_t('显示评论')) ?></strong><small><?= h(sblog_t('仅对独立页面生效。')) ?></small></span>
              </label>

              <div class="field">
                <div class="field-label-row"><label for="content"><?= h(sblog_t('正文')) ?></label><?= $editorActions['content'] ?></div>
                <div class="markdown-editor" data-markdown-editor>
                  <div class="markdown-toolbar" role="toolbar" aria-label="<?= h(sblog_t('Markdown 格式工具栏')) ?>">
                    <label class="sr-only" for="markdown-heading"><?= h(sblog_t('标题级别')) ?></label>
                    <select id="markdown-heading" class="markdown-toolbar__heading" data-markdown-heading title="<?= h(sblog_t('标题级别')) ?>" aria-label="<?= h(sblog_t('标题级别')) ?>">
                      <option value=""><?= h(sblog_t('标题')) ?></option>
                      <option value="1"><?= h(sblog_t('一级标题')) ?></option>
                      <option value="2"><?= h(sblog_t('二级标题')) ?></option>
                      <option value="3"><?= h(sblog_t('三级标题')) ?></option>
                    </select>
                    <span class="markdown-toolbar__separator" aria-hidden="true"></span>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="bold" aria-label="<?= h(sblog_t('加粗')) ?>" aria-keyshortcuts="Control+B Meta+B" title="<?= h(sblog_t('加粗 (Ctrl/Cmd+B)')) ?>"><strong aria-hidden="true">B</strong></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="italic" aria-label="<?= h(sblog_t('斜体')) ?>" aria-keyshortcuts="Control+I Meta+I" title="<?= h(sblog_t('斜体 (Ctrl/Cmd+I)')) ?>"><em aria-hidden="true">I</em></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="strike" aria-label="<?= h(sblog_t('删除线')) ?>" title="<?= h(sblog_t('删除线')) ?>"><span class="markdown-toolbar__strike" aria-hidden="true">S</span></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="inline-code" aria-label="<?= h(sblog_t('行内代码')) ?>" title="<?= h(sblog_t('行内代码')) ?>"><span class="markdown-toolbar__code" aria-hidden="true">&lt;/&gt;</span></button>
                    <span class="markdown-toolbar__separator" aria-hidden="true"></span>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="quote" aria-label="<?= h(sblog_t('引用')) ?>" title="<?= h(sblog_t('引用')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21c3 0 7-1 7-8V5c0-1.2-.8-2-2-2H4C2.8 3 2 3.8 2 5v6c0 1.2.8 2 2 2h3c0 3-1 5-4 6v2Zm11 0c3 0 7-1 7-8V5c0-1.2-.8-2-2-2h-4c-1.2 0-2 .8-2 2v6c0 1.2.8 2 2 2h3c0 3-1 5-4 6v2Z"/></svg></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="unordered-list" aria-label="<?= h(sblog_t('无序列表')) ?>" title="<?= h(sblog_t('无序列表')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="ordered-list" aria-label="<?= h(sblog_t('有序列表')) ?>" title="<?= h(sblog_t('有序列表')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 6h11M10 12h11M10 18h11M4 6h1V3L3 4M3 11h2l-2 3h2M3 17h2l-2 2h2"/></svg></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="task-list" aria-label="<?= h(sblog_t('任务列表')) ?>" title="<?= h(sblog_t('任务列表')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 6h10M11 12h10M11 18h10M3 6l1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/></svg></button>
                    <span class="markdown-toolbar__separator" aria-hidden="true"></span>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="link" aria-label="<?= h(sblog_t('插入链接')) ?>" aria-keyshortcuts="Control+K Meta+K" title="<?= h(sblog_t('链接 (Ctrl/Cmd+K)')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"/></svg></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="image" aria-label="<?= h(sblog_t('插入图片')) ?>" title="<?= h(sblog_t('图片')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="table" aria-label="<?= h(sblog_t('插入表格')) ?>" title="<?= h(sblog_t('表格')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M8 5v14M16 5v14"/></svg></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="code-block" aria-label="<?= h(sblog_t('代码块')) ?>" title="<?= h(sblog_t('代码块')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 9-3 3 3 3m8-6 3 3-3 3m-2-9-4 12"/></svg></button>
                    <button class="markdown-toolbar__button" type="button" data-markdown-action="horizontal-rule" aria-label="<?= h(sblog_t('分隔线')) ?>" title="<?= h(sblog_t('分隔线')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg></button>
                  </div>
                  <textarea id="content" class="editor-textarea" name="content" rows="18" spellcheck="true" required><?= h((string)$values['content']) ?></textarea>
                  <div class="markdown-editor__status"><span><?= h(sblog_t('Markdown')) ?></span><span data-markdown-count aria-live="polite"><?= h(sblog_tn('{count} 个字符', 0)) ?></span></div>
                </div>
                <p class="field-hint"><?= h(sblog_t('支持 Markdown；将网易云音乐、哔哩哔哩、YouTube 或豆瓣链接单独放在一段可自动解析。')) ?></p>
              </div>

              <div class="field">
                <label for="attachmentInput"><?= h(sblog_t('上传附件')) ?></label>
                <div class="attachment-uploader" data-upload-url="<?= h(url_for('upload_attachment')) ?>" data-csrf="<?= h(csrf_token()) ?>">
                  <input id="attachmentInput" class="attachment-input" type="file" name="attachments[]" multiple>
                  <label class="attachment-drop" for="attachmentInput">
                    <span class="attachment-drop__title"><?= h(sblog_t('选择或拖入附件')) ?></span>
                    <span class="attachment-drop__hint"><?= h(sblog_t('可同时上传多个附件，每个最大 30M；图片上传完成后显示缩略图并插入 Markdown。')) ?></span>
                  </label>
                  <div class="attachment-list" aria-live="polite"></div>
                </div>
              </div>

              <div class="action-row">
                <button class="button" type="submit"><?= h($isEdit ? sblog_t('保存修改') : sblog_t('创建文章')) ?></button>
              </div>
            </form>
            <?= $afterFormHtml ?>
          </div>
        </section>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout($isEdit ? sblog_t('编辑文章') : sblog_t('写新文章'), $content, [
        'active' => $isEdit ? 'edit' : 'write',
        'wide' => true,
        'description' => sblog_t('博客文章编辑器'),
    ]);
}

if (!is_installed()) {
    redirect_to(install_url());
}

load_active_plugins();
plugin_action('plugins_loaded', ['plugins' => $GLOBALS['sblog_loaded_plugins'] ?? []]);
ob_start('plugin_output_buffer');
apply_pretty_route();

if (($_GET['__route_not_found'] ?? '') === '1') {
    simple_error_page(sblog_t('页面不存在'), sblog_t('你访问的地址没有匹配到任何页面。'), 404);
}

$action = (string)plugin_filter('route_action', (string)($_GET['a'] ?? 'home'), ['request' => $_REQUEST]);
$GLOBALS['sblog_current_action'] = $action;
plugin_action('request', ['action' => $action, 'request' => $_REQUEST]);

switch ($action) {
    case 'douban_cover':
        render_douban_cover();
        break;

    case 'home':
        render_home((int)($_GET['p'] ?? 1));
        break;

    case 'rss':
        render_rss_feed();
        break;

    case 'sitemap':
        render_sitemap();
        break;

    case 'archives':
        render_archives();
        break;

    case 'tags':
        render_tags_index();
        break;

    case 'links':
        render_links_page();
        break;

    case 'tag':
        render_tag_page(trim((string)($_GET['slug'] ?? '')));
        break;

    case 'category':
        render_category_page(trim((string)($_GET['slug'] ?? '')));
        break;

    case 'submit_comment':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_to(url_for('home'));
        }
        verify_csrf();
        $postId = (int)($_POST['post_id'] ?? 0);
        $post = one(
            'SELECT * FROM posts WHERE id = ? AND status = ? AND published_at > 0 AND published_at <= ?',
            [$postId, 'published', time()]
        );
        if (!$post || !content_allows_comments($post)) {
            simple_error_page(sblog_t('文章不存在'), sblog_t('这篇文章当前无法接收评论。'), 404);
        }
        $returnUrl = content_permalink($post) . '#comments';
        if (setting('comments_enabled', '1') !== '1') {
            set_comment_notice($postId, 'error', sblog_t('评论功能当前已关闭。'));
            redirect_to($returnUrl);
        }
        $authenticatedIdentity = authenticated_comment_identity();
        $commentInput = $_POST;
        if ($authenticatedIdentity !== null) {
            $commentInput = array_merge($commentInput, $authenticatedIdentity);
        }
        $startedAt = (int)($_POST['comment_started_at'] ?? 0);
        if (!record_comment_attempt()) {
            [$comment] = validate_comment_input($commentInput, $authenticatedIdentity === null);
            forget_comment_form($postId, $startedAt);
            set_comment_feedback($postId, $comment, ['提交过于频繁，请稍后再试。']);
            redirect_to($returnUrl);
        }
        if (trim((string)($_POST['company'] ?? '')) !== '') {
            forget_comment_form($postId, $startedAt);
            set_comment_notice($postId, 'success', sblog_t('评论已提交，审核通过后会显示。'));
            redirect_to($returnUrl);
        }

        [$comment, $commentErrors] = validate_comment_input($commentInput, $authenticatedIdentity === null);
        $parentId = (int)$comment['parent_id'];
        $replyTarget = approved_reply_target($postId, $parentId);
        if ($parentId > 0 && $replyTarget === null) {
            $commentErrors[] = '回复目标不存在或当前不可用。';
        }
        $formExists = !empty($_SESSION['comment_forms'][$postId][(string)$startedAt]);
        $elapsed = time() - $startedAt;
        if ($startedAt < 1 || !$formExists || $elapsed < 2 || $elapsed > 7200) {
            $commentErrors[] = $elapsed < 2 ? '提交过快，请稍后再试。' : '评论表单已失效，请刷新文章后重试。';
        }
        forget_comment_form($postId, $startedAt);
        if ($commentErrors) {
            set_comment_feedback($postId, $comment, array_values(array_unique($commentErrors)));
            redirect_to($returnUrl);
        }

        $linkCount = preg_match_all('#https?://#i', $comment['content']);
        $isAuthenticatedAdmin = $authenticatedIdentity !== null;
        $hasApprovedVisitorEmail = !$isAuthenticatedAdmin && visitor_email_has_approved_comment($comment['author_email']);
        $needsApproval = !$isAuthenticatedAdmin
            && !$hasApprovedVisitorEmail
            && (setting('comments_require_approval', '1') === '1' || $linkCount > 2);
        $status = $needsApproval ? 'pending' : 'approved';
        $isRead = setting('comments_notify', '1') === '1' ? 0 : 1;
        $now = time();
        $userId = (int)($authenticatedIdentity['user_id'] ?? 0);
        $insertParams = [
            $postId,
            $userId > 0 ? $userId : null,
            $comment['author_name'],
            $comment['author_email'],
            $comment['author_url'],
            $comment['content'],
            $status,
            $isRead,
            client_ip_hash(),
            client_ip_address(),
            str_sub_u((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $now,
            $now,
        ];
        $database = db();
        $duplicateCutoff = time() - 86400;
        $duplicateIdentitySql = $userId > 0 ? 'duplicate.user_id = ?' : 'duplicate.user_id IS NULL AND duplicate.author_email = ?';
        $duplicateIdentityValue = $userId > 0 ? $userId : $comment['author_email'];
        $commentId = 0;
        try {
            $database->exec('BEGIN IMMEDIATE');
            $duplicateError = duplicate_comment_error($postId, $parentId, $userId, $comment['author_email'], $comment['content']);
            if ($duplicateError !== '') {
                $database->exec('ROLLBACK');
                set_comment_feedback($postId, $comment, [$duplicateError]);
                redirect_to($returnUrl);
            }

            if ($parentId > 0) {
                $inserted = q(
                    "INSERT INTO comments(post_id, user_id, parent_id, reply_to_name, author_name, author_email, author_url, content, status, is_read, ip_hash, ip_address, user_agent, created_at, updated_at)
                     SELECT ?, ?, parent.id, parent.author_name, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                     FROM comments parent
                     WHERE parent.id = ? AND parent.post_id = ? AND parent.status = 'approved'
                       AND NOT EXISTS (
                           SELECT 1 FROM comments duplicate
                           WHERE duplicate.post_id = ? AND COALESCE(duplicate.parent_id, 0) = parent.id
                             AND {$duplicateIdentitySql} AND duplicate.content = ? AND duplicate.created_at >= ?
                       )",
                    array_merge($insertParams, [$parentId, $postId, $postId, $duplicateIdentityValue, $comment['content'], $duplicateCutoff])
                )->rowCount();
                if ($inserted !== 1) {
                    $targetStillAvailable = approved_reply_target($postId, $parentId) !== null;
                    $database->exec('ROLLBACK');
                    if ($targetStillAvailable) {
                        $failureMessage = '这条评论已经提交过了。';
                    } else {
                        $comment['parent_id'] = '';
                        $failureMessage = '回复目标已不可用，请重新选择。';
                    }
                    set_comment_feedback($postId, $comment, [$failureMessage]);
                    redirect_to($returnUrl);
                }
                $commentId = (int)$database->lastInsertId();
            } else {
                $inserted = q(
                    "INSERT INTO comments(post_id, user_id, parent_id, reply_to_name, author_name, author_email, author_url, content, status, is_read, ip_hash, ip_address, user_agent, created_at, updated_at)
                     SELECT ?, ?, NULL, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                     WHERE NOT EXISTS (
                         SELECT 1 FROM comments duplicate
                         WHERE duplicate.post_id = ? AND COALESCE(duplicate.parent_id, 0) = 0
                           AND {$duplicateIdentitySql} AND duplicate.content = ? AND duplicate.created_at >= ?
                     )",
                    array_merge($insertParams, [$postId, $duplicateIdentityValue, $comment['content'], $duplicateCutoff])
                )->rowCount();
                if ($inserted !== 1) {
                    $database->exec('ROLLBACK');
                    set_comment_feedback($postId, $comment, ['这条评论已经提交过了。']);
                    redirect_to($returnUrl);
                }
                $commentId = (int)$database->lastInsertId();
            }
            $database->exec('COMMIT');
            if ($isRead === 0) {
                try {
                    send_comment_notification($post, $comment, $status);
                } catch (Throwable) {
                }
            }
        } catch (Throwable $exception) {
            try { $database->exec('ROLLBACK'); } catch (Throwable) {}
            throw $exception;
        }
        plugin_action('comment_created', [
            'comment_id' => $commentId,
            'post_id' => $postId,
            'status' => $status,
            'comment' => $comment,
        ]);
        if ($status === 'approved' && $parentId > 0) {
            try {
                send_comment_reply_notice($commentId);
            } catch (Throwable $exception) {
                error_log('Reply notification failed: ' . $exception->getMessage());
            }
        }
        if ($authenticatedIdentity === null) {
            $_SESSION['comment_identity'] = [
                'author_name' => $comment['author_name'],
                'author_email' => $comment['author_email'],
                'author_url' => $comment['author_url'],
            ];
        }
        set_comment_notice(
            $postId,
            'success',
            $status === 'approved' ? sblog_t('评论已发布。') : sblog_t('评论已提交，审核通过后会显示。')
        );
        redirect_to($returnUrl);
        break;

    case 'post':
        $slug = trim((string)($_GET['slug'] ?? $_GET['id'] ?? ''));
        $post = fetch_post_by_identifier($slug, is_admin());
        if (!$post) {
            simple_error_page(sblog_t('文章不存在'), sblog_t('可能还未发布，或者链接已经失效。'), 404);
        }
        render_post_page($post);
        break;

    case 'page':
        $slug = trim((string)($_GET['slug'] ?? $_GET['id'] ?? ''));
        $page = fetch_page_by_identifier($slug, is_admin());
        if (!$page) {
            simple_error_page(sblog_t('页面不存在'), sblog_t('可能还未发布，或者链接已经失效。'), 404);
        }
        render_page_view($page);
        break;

    case 'forgot_password':
        if (is_admin()) {
            redirect_to(url_for('admin'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $rate = password_reset_rate_state();
            if ((int)$rate['count'] >= 3) {
                render_forgot_password_page('', sblog_t('重置请求过于频繁，请 15 分钟后再试。'));
            }
            password_reset_rate_state(true);

            $account = trim((string)($_POST['account'] ?? ''));
            if ($account === '') {
                render_forgot_password_page('', sblog_t('请填写用户名或邮箱。'), ['account' => $account]);
            }

            $user = one(
                'SELECT * FROM users WHERE username = ? OR lower(email) = ? LIMIT 1',
                [$account, str_lower_u($account)]
            );

            if ($user) {
                [$token, $expiresAt] = create_password_reset($user);
                send_password_reset_notice($user, $token, $expiresAt);
            }

            render_forgot_password_page(sblog_t('如果账号存在，重置链接已经生成。请检查管理员邮箱；若服务器未配置发信，请查看 cache 目录中的 password-reset 文件。'));
        }

        render_forgot_password_page();
        break;

    case 'reset_password':
        if (is_admin()) {
            redirect_to(url_for('admin'));
        }

        $token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
        $reset = password_reset_by_token($token);
        if (!$reset) {
            render_forgot_password_page('', sblog_t('重置链接无效或已过期，请重新申请。'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['password_confirm'] ?? '');

            if (strlen($password) < 8) {
                render_reset_password_page($token, sblog_t('新密码至少需要 8 个字符。'));
            }
            if ($password !== $confirm) {
                render_reset_password_page($token, sblog_t('两次输入的密码不一致。'));
            }

            $now = time();
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
            q('UPDATE password_resets SET used_at = ? WHERE id = ?', [$now, (int)$reset['id']]);
            q('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at = 0', [$now, (int)$reset['user_id']]);
            set_flash('success', sblog_t('密码已更新，请使用新密码登录。'));
            redirect_to(url_for('login'));
        }

        render_reset_password_page($token);
        break;

    case 'login':
        if (is_admin()) {
            redirect_to(url_for('admin'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $rate = login_rate_state();
            if ((int)$rate['count'] >= 5) {
                render_login_page(sblog_t('登录尝试过多，请 15 分钟后再试。'));
            }
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $user = one('SELECT * FROM users WHERE username = ?', [$username]);

            if (!$user || !password_verify($password, (string)$user['password_hash'])) {
                login_rate_state(true);
                render_login_page(sblog_t('用户名或密码不正确。'), ['username' => $username]);
            }

            login_rate_state(false, true);
            session_regenerate_id(true);
            $now = time();
            unset($_SESSION['csrf_token']);
            $_SESSION['admin_id'] = (int)$user['id'];
            $_SESSION['admin_authenticated_at'] = $now;
            $_SESSION['admin_last_seen_at'] = $now;
            $_SESSION['admin_password_fingerprint'] = hash('sha256', (string)$user['password_hash']);
            update_admin_presence((int)$user['id']);
            set_flash('success', sblog_t('已登录后台。'));
            redirect_to(url_for('admin'));
        }

        render_login_page();
        break;

    case 'logout':
        require_admin_post(url_for('home'));
        destroy_current_session();
        redirect_to(url_for('home'));
        break;

    case 'admin':
        $justUpdated = is_admin() && !empty($_SESSION['sblog_release_updated']);
        if ($justUpdated) {
            unset($_SESSION['sblog_release_updated']);
        }
        if ($justUpdated && bundled_release_files_missing()) {
            try {
                $update = github_update_info(true);
                if (!empty($update['repair'])) {
                    $version = install_github_update($update);
                    set_flash('success', sblog_t('已更新到 {version}，并已同步内置主题和插件。', ['version' => $version]));
                } elseif ((string)($update['error'] ?? '') !== '') {
                    throw new RuntimeException((string)$update['error']);
                }
            } catch (Throwable $exception) {
                set_flash('error', sblog_t('程序已更新，但内置主题和插件同步失败：{error}', ['error' => $exception->getMessage()]));
            }
        }
        render_admin_page();
        break;

    case 'install_update':
        require_admin_post(url_for('admin'));
        try {
            $update = github_update_info(true);
            $isRepair = !empty($update['repair']);
            $version = install_github_update($update);
            if (!$isRepair) {
                $_SESSION['sblog_release_updated'] = true;
            }
            set_flash(
                'success',
                $isRepair
                    ? sblog_t('内置主题和插件已同步。')
                    : sblog_t('已更新到 {version}。如版本包含数据库变更，请继续访问 update.php。', ['version' => $version])
            );
        } catch (Throwable $exception) {
            set_flash('error', sblog_t('更新失败：{error}', ['error' => $exception->getMessage()]));
        }
        redirect_to(url_for('admin'));
        break;

    case 'check_update':
        require_admin_post(url_for('admin'));
        $update = github_update_info(true);
        $updateError = trim((string)($update['error'] ?? ''));
        if ($updateError !== '') {
            set_flash('error', sblog_t('检测更新失败：{error}', ['error' => $updateError]));
        } elseif (!empty($update['available'])) {
            set_flash('success', sblog_t('发现新版本 {version}，可点击“立即更新”完成升级。', ['version' => (string)$update['latest']]));
        } elseif (!empty($update['repair'])) {
            set_flash('success', sblog_t('当前版本已是最新，但内置主题或插件需要补全。'));
        } else {
            set_flash('success', sblog_t('暂无更新，当前已是最新版本 {version}。', ['version' => APP_VERSION]));
        }
        redirect_to(url_for('admin'));
        break;

    case 'admin_posts':
        render_admin_posts_page();
        break;

    case 'admin_comments':
        render_admin_comments_page();
        break;

    case 'admin_categories':
        render_admin_categories_page();
        break;

    case 'admin_tags':
        render_admin_tags_page();
        break;

    case 'admin_links':
        render_admin_links_page();
        break;

    case 'admin_users':
        render_admin_users_page();
        break;

    case 'admin_media':
        render_admin_media_page();
        break;

    case 'admin_themes':
        render_admin_themes_page();
        break;

    case 'admin_settings':
        render_admin_settings_page();
        break;

    case 'admin_plugins':
        render_admin_plugins_page();
        break;

    case 'toggle_plugin':
        require_admin_post(url_for('admin_plugins'));
        $slug = trim((string)($_POST['plugin'] ?? ''));
        $operation = (string)($_POST['operation'] ?? '');
        $plugins = available_plugins();
        if (!isset($plugins[$slug]) || !in_array($operation, ['activate', 'deactivate'], true)) {
            set_flash('error', sblog_t('插件不存在或操作无效。'));
            redirect_to(url_for('admin_plugins'));
        }
        $activePlugins = active_plugin_slugs(true);
        if ($operation === 'activate' && !in_array($slug, $activePlugins, true)) {
            $exclusiveGroup = (string)$plugins[$slug]['exclusive_group'];
            if ($exclusiveGroup !== '') {
                $activePlugins = array_values(array_filter($activePlugins, static fn(string $activeSlug): bool => (string)$plugins[$activeSlug]['exclusive_group'] !== $exclusiveGroup));
            }
            $activePlugins[] = $slug;
        } elseif ($operation === 'deactivate') {
            $activePlugins = array_values(array_filter($activePlugins, static fn(string $activeSlug): bool => $activeSlug !== $slug));
        }
        save_active_plugins($activePlugins);
        plugin_action('plugin_status_changed', ['plugin' => $slug, 'operation' => $operation]);
        set_flash('success', $operation === 'activate' ? sblog_t('插件已启用。') : sblog_t('插件已停用。'));
        redirect_to(url_with_query(url_for('admin_plugins'), ['changed' => bin2hex(random_bytes(4))]), 303);
        break;

    case 'save_media':
        require_admin_post(url_for('admin_media'));
        $mediaId = max(0, (int)($_POST['id'] ?? 0));
        $media = $mediaId > 0 ? one('SELECT * FROM media WHERE id = ?', [$mediaId]) : null;
        if (!$media) {
            set_flash('error', sblog_t('找不到媒体资料。'));
            redirect_to(url_for('admin_media'));
        }
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            set_flash('error', sblog_t('媒体标题不能为空。'));
            redirect_to(url_with_query(url_for('admin_media'), ['id' => $mediaId]) . '#media-editor');
        }
        q('UPDATE media SET title = ?, alt_text = ?, caption = ?, updated_at = ? WHERE id = ?', [
            str_sub_u($title, 0, 255),
            !empty($media['is_image']) ? str_sub_u(trim((string)($_POST['alt_text'] ?? '')), 0, 500) : '',
            str_sub_u(trim((string)($_POST['caption'] ?? '')), 0, 2000),
            time(), $mediaId,
        ]);
        set_flash('success', sblog_t('媒体资料已更新。'));
        redirect_to(url_for('admin_media'));
        break;

    case 'delete_media':
        require_admin_post(url_for('admin_media'));
        $mediaId = max(0, (int)($_POST['id'] ?? 0));
        $media = $mediaId > 0 ? one('SELECT * FROM media WHERE id = ?', [$mediaId]) : null;
        if (!$media) {
            set_flash('error', sblog_t('找不到媒体资料。'));
            redirect_to(url_for('admin_media'));
        }
        $deleted = delete_media_storage($media);
        if (empty($deleted['ok'])) {
            set_flash('error', (string)($deleted['error'] ?? sblog_t('删除媒体资料失败。')));
            redirect_to(url_for('admin_media'));
        }
        q('DELETE FROM media WHERE id = ?', [$mediaId]);
        set_flash('success', sblog_t('媒体资料已删除。'));
        redirect_to(url_for('admin_media'));
        break;

    case 'activate_theme':
        require_admin_post(url_for('admin_themes'));
        $themeSlug = trim((string)($_POST['theme'] ?? ''));
        $acceptsJson = str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        if (!array_key_exists($themeSlug, available_themes())) {
            if ($acceptsJson) {
                json_response(['ok' => false, 'error' => sblog_t('所选主题不存在或 theme.json 无效。')], 422);
            }
            set_flash('error', sblog_t('所选主题不存在或 theme.json 无效。'));
            redirect_to(url_for('admin_themes'));
        }
        save_settings(['active_theme' => $themeSlug]);
        if ($acceptsJson) {
            json_response(['ok' => true, 'active_theme' => $themeSlug]);
        }
        set_flash('success', sblog_t('主题已启用。'));
        redirect_to(url_with_query(url_for('admin_themes'), ['changed' => bin2hex(random_bytes(4))]), 303);
        break;

    case 'save_settings':
        require_admin_post(url_for('admin_settings'));
        $siteName = trim((string)($_POST['site_name'] ?? ''));
        $postsPerPage = max(1, min(24, (int)($_POST['posts_per_page'] ?? (int)default_settings()['posts_per_page'])));
        $prettyUrl = (string)($_POST['pretty_url'] ?? '0') === '1' ? '1' : '0';
        save_settings([
            'site_name' => $siteName !== '' ? $siteName : default_settings()['site_name'],
            'site_url' => trim((string)($_POST['site_url'] ?? '')),
            'favicon_url' => trim((string)($_POST['favicon_url'] ?? '')) ?: default_settings()['favicon_url'],
            'footer_beian' => trim((string)($_POST['footer_beian'] ?? '')),
            'posts_per_page' => (string)$postsPerPage,
            'pretty_url' => $prettyUrl,
            'comments_enabled' => isset($_POST['comments_enabled']) ? '1' : '0',
            'comments_require_approval' => isset($_POST['comments_require_approval']) ? '1' : '0',
            'comments_notify' => isset($_POST['comments_notify']) ? '1' : '0',
            'site_tagline' => trim((string)($_POST['site_tagline'] ?? '')),
            'site_description' => trim((string)($_POST['site_description'] ?? '')),
            'site_keywords' => trim((string)($_POST['site_keywords'] ?? '')),
            'site_footer' => trim((string)($_POST['site_footer'] ?? '')),
            'custom_head_code' => trim((string)($_POST['custom_head_code'] ?? '')),
        ]);
        set_flash('success', sblog_t('站点设置已更新。'));
        redirect_to(url_for('admin_settings'));
        break;

    case 'mark_comments_read':
        require_admin_post(url_for('admin_comments'));
        $filter = trim((string)($_POST['filter'] ?? 'all'));
        $search = trim((string)($_POST['q'] ?? ''));
        $page = max(1, (int)($_POST['p'] ?? 1));
        $updated = q('UPDATE comments SET is_read = 1, updated_at = ? WHERE is_read = 0', [time()])->rowCount();
        set_flash('success', $updated > 0 ? sblog_t('所有评论通知已标为已读。') : sblog_t('当前没有未读评论。'));
        redirect_to(admin_comments_url($filter, $search, $page));
        break;

    case 'moderate_comments':
        require_admin_post(url_for('admin_comments'));
        $filter = trim((string)($_POST['filter'] ?? 'all'));
        $search = trim((string)($_POST['q'] ?? ''));
        $page = max(1, (int)($_POST['p'] ?? 1));
        $returnUrl = admin_comments_url($filter, $search, $page);
        $action = trim((string)($_POST['action'] ?? ''));
        $ids = $_POST['comment_ids'] ?? [];
        $singleId = (int)($_POST['comment_id'] ?? 0);
        if ($singleId > 0) { $ids = [$singleId]; }
        $ids = positive_int_ids($ids);
        if (!in_array($action, ['approve', 'pending', 'spam', 'read', 'delete'], true) || $ids === []) {
            set_flash('error', $ids === [] ? sblog_t('请先选择评论。') : sblog_t('未知的评论操作。'));
            redirect_to($returnUrl);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'delete') {
            $affected = q("DELETE FROM comments WHERE id IN ({$placeholders})", $ids)->rowCount();
            $message = sblog_tn('已删除 {count} 条评论。', $affected);
        } elseif ($action === 'read') {
            $params = array_merge([time()], $ids);
            $affected = q("UPDATE comments SET is_read = 1, updated_at = ? WHERE id IN ({$placeholders})", $params)->rowCount();
            $message = sblog_tn('已将 {count} 条评论标为已读。', $affected);
        } else {
            $status = ['approve' => 'approved', 'pending' => 'pending', 'spam' => 'spam'][$action];
            $params = array_merge([$status, time()], $ids);
            $affected = q("UPDATE comments SET status = ?, is_read = 1, updated_at = ? WHERE id IN ({$placeholders})", $params)->rowCount();
            if ($status === 'approved') {
                send_approved_reply_notices($ids);
            }
            $message = match ($status) {
                'approved' => sblog_tn('已通过 {count} 条评论。', $affected),
                'spam' => sblog_tn('已将 {count} 条评论标记为垃圾。', $affected),
                default => sblog_tn('已将 {count} 条评论转为待审核。', $affected),
            };
        }
        set_flash('success', $message);
        redirect_to($returnUrl);
        break;

    case 'save_category':
        require_admin_post(url_for('admin_categories'));
        $id = (int)($_POST['id'] ?? 0);
        $existing = $id > 0 ? one('SELECT * FROM categories WHERE id = ?', [$id]) : null;
        [$data, $errors] = validate_category_input($_POST, $existing);
        if ($errors) {
            render_admin_categories_page([
                'id' => (string)$id,
                'name' => (string)($_POST['name'] ?? ''),
                'slug' => (string)($_POST['slug'] ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
                'sort_order' => (string)($_POST['sort_order'] ?? '0'),
            ], $errors);
        }
        if ($existing) {
            q(
                'UPDATE categories SET name = ?, slug = ?, description = ?, sort_order = ?, updated_at = ? WHERE id = ?',
                [$data['name'], $data['slug'], $data['description'], $data['sort_order'], time(), $id]
            );
            set_flash('success', sblog_t('分类已保存。'));
        } else {
            $now = time();
            q(
                'INSERT INTO categories(name, slug, description, sort_order, created_at, updated_at) VALUES(?,?,?,?,?,?)',
                [$data['name'], $data['slug'], $data['description'], $data['sort_order'], $now, $now]
            );
            set_flash('success', sblog_t('分类已创建。'));
        }
        redirect_to(url_for('admin_categories'));
        break;

    case 'delete_category':
        require_admin_post(url_for('admin_categories'));
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $postCount = (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND category_id = ?', ['post', $id]);
            if ($postCount > 0) {
                set_flash('error', sblog_t('该分类下仍有文章，请先将文章移动到其他分类。'));
                redirect_to(url_for('admin_categories'));
            }
            q('DELETE FROM categories WHERE id = ?', [$id]);
        }
        set_flash('success', sblog_t('分类已删除。'));
        redirect_to(url_for('admin_categories'));
        break;

    case 'save_link':
        require_admin_post(url_for('admin_links'));
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $iconUrl = trim((string)($_POST['icon_url'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $errors = [];
        if ($name === '') { $errors[] = '请填写网站名称。'; }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(str_lower_u((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) { $errors[] = '请填写有效的 HTTP 或 HTTPS 地址。'; }
        if ($iconUrl !== '' && !filter_var($iconUrl, FILTER_VALIDATE_URL)) { $errors[] = '网站图标地址格式不正确。'; }
        if ($errors) { render_admin_links_page(['id' => (string)$id, 'name' => $name, 'url' => $url, 'icon_url' => $iconUrl, 'description' => $description, 'sort_order' => (string)$sortOrder], $errors); }
        if ($id > 0 && one('SELECT id FROM links WHERE id = ?', [$id])) {
            q('UPDATE links SET name = ?, url = ?, icon_url = ?, description = ?, sort_order = ?, updated_at = ? WHERE id = ?', [$name, $url, $iconUrl, $description, $sortOrder, time(), $id]);
            set_flash('success', sblog_t('链接已更新。'));
        } else {
            $now = time();
            q('INSERT INTO links(name, url, icon_url, description, sort_order, created_at, updated_at) VALUES(?,?,?,?,?,?,?)', [$name, $url, $iconUrl, $description, $sortOrder, $now, $now]);
            set_flash('success', sblog_t('链接已添加。'));
        }
        redirect_to(url_for('admin_links'));
        break;

    case 'save_tag':
        require_admin_post(url_for('admin_tags'));
        $oldTag = trim((string)($_POST['old_tag'] ?? ''));
        $newTag = trim((string)($_POST['new_tag'] ?? ''));
        $tagSlug = trim((string)($_POST['tag_slug'] ?? ''));
        $errors = [];
        if ($oldTag === '' || $newTag === '') { $errors[] = '原标签和新标签不能为空。'; }
        if (count(parse_tags_input($newTag)) !== 1) { $errors[] = '新标签不能包含逗号。'; }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $tagSlug)) { $errors[] = 'Slug 格式不正确。'; }
        if (one('SELECT label FROM tag_meta WHERE slug = ? AND label != ?', [$tagSlug, $oldTag])) { $errors[] = 'Slug 已被其他标签使用。'; }
        if ($errors) { render_admin_tags_page(['old_tag' => $oldTag, 'new_tag' => $newTag, 'tag_slug' => $tagSlug], $errors); }
        if (str_lower_u($oldTag) !== str_lower_u($newTag)) { replace_tag_everywhere($oldTag, $newTag); }
        q('DELETE FROM tag_meta WHERE label = ?', [$oldTag]);
        q('INSERT OR REPLACE INTO tag_meta(label, slug, updated_at) VALUES(?,?,?)', [$newTag, $tagSlug, time()]);
        set_flash('success', sblog_t('标签名称和 Slug 已更新。'));
        redirect_to(url_for('admin_tags'));
        break;

    case 'delete_tag':
        require_admin_post(url_for('admin_tags'));
        $selected = $_POST['tag_ids'] ?? [];
        if (!is_array($selected)) { $selected = []; }
        $selected = array_values(array_unique(array_filter(array_map(static fn($tag): string => trim((string)$tag), $selected))));
        foreach ($selected as $tag) {
            replace_tag_everywhere($tag, null);
            q('DELETE FROM tag_meta WHERE label = ?', [$tag]);
        }
        set_flash('success', $selected ? sblog_t('所选标签已移除，文章内容保持不变。') : sblog_t('请先选择需要删除的标签。'));
        redirect_to(url_for('admin_tags'));
        break;

    case 'delete_link':
        require_admin_post(url_for('admin_links'));
        q('DELETE FROM links WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        set_flash('success', sblog_t('链接已删除。'));
        redirect_to(url_for('admin_links'));
        break;

    case 'save_user':
        require_admin_post(url_for('admin_users'));
        $id = (int)(current_admin()['id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
        $nickname = trim((string)($_POST['nickname'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $avatarUrl = trim((string)($_POST['avatar_url'] ?? ''));
        $websiteUrl = trim((string)($_POST['website_url'] ?? ''));
        $socialProfiles = [];
        foreach (social_profile_definitions() as $key => $definition) { $socialProfiles[$definition['column']] = trim((string)($_POST['social_' . $key] ?? '')); }
        $signature = trim((string)($_POST['signature'] ?? ''));
        $errors = [];
        if ($username === '') { $errors[] = '用户名不能为空。'; }
        if ($nickname === '') { $errors[] = '昵称不能为空。'; }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = '邮箱地址格式不正确。'; }
        foreach (['头像地址' => $avatarUrl, '网站地址' => $websiteUrl] as $label => $url) { if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) { $errors[] = $label . '格式不正确。'; } }
        foreach (social_profile_definitions() as $definition) {
            $url = $socialProfiles[$definition['column']];
            if ($url !== '' && (strlen($url) > 300 || !preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL))) {
                $errors[] = (string)($definition['source_label'] ?? $definition['label']) . '链接格式不正确。';
            }
        }
        if (one('SELECT id FROM users WHERE username = ? AND id != ?', [$username, $id])) { $errors[] = '用户名已存在。'; }
        $passwordChangeRequested = $currentPassword !== '' || $password !== '' || $passwordConfirm !== '';
        if ($passwordChangeRequested) {
            $passwordHash = (string)(val('SELECT password_hash FROM users WHERE id = ?', [$id]) ?? '');
            if ($currentPassword === '') { $errors[] = '请输入原密码。'; }
            elseif ($passwordHash === '' || !password_verify($currentPassword, $passwordHash)) { $errors[] = '原密码不正确。'; }
            if (strlen($password) < 8) { $errors[] = '新密码至少需要 8 个字符。'; }
            if ($password !== $passwordConfirm) { $errors[] = '两次输入的密码不一致。'; }
        }
        $profileForm = array_merge(['username' => $username, 'nickname' => $nickname, 'email' => $email, 'avatar_url' => $avatarUrl, 'website_url' => $websiteUrl, 'signature' => $signature], $socialProfiles);
        if ($errors) { render_admin_users_page($profileForm, $errors); }
        $userValues = array_merge(['username' => $username, 'nickname' => $nickname, 'email' => $email, 'avatar_url' => $avatarUrl, 'website_url' => $websiteUrl], $socialProfiles, ['signature' => $signature]);
        $newPasswordHash = '';
        if ($passwordChangeRequested) {
            $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);
            $userValues['password_hash'] = $newPasswordHash;
        }
        $assignments = implode(', ', array_map(static fn(string $column): string => $column . ' = ?', array_keys($userValues)));
        q('UPDATE users SET ' . $assignments . ' WHERE id = ?', [...array_values($userValues), $id]);
        if ($newPasswordHash !== '') {
            $_SESSION['admin_password_fingerprint'] = hash('sha256', $newPasswordHash);
        }
        set_flash('success', sblog_t('用户设置已保存。'));
        redirect_to(url_for('admin_users'));
        break;

    case 'upload_attachment':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['ok' => false, 'error' => sblog_t('仅支持 POST 上传。')], 405);
        }
        handle_attachment_upload();
        break;

    case 'write':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            [$data, $errors] = validate_post_input($_POST);
            if (!$errors) {
                $id = save_post($data);
                set_flash('success', sblog_t('文章已创建。'));
                redirect_to(url_for('edit', ['id' => $id]));
            }
            render_editor_page(null, post_form_from_request($_POST), $errors);
        }
        render_editor_page();
        break;

    case 'edit':
        require_admin();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $post = fetch_post_by_id($id);
        if (!$post) {
            simple_error_page(sblog_t('文章不存在'), sblog_t('找不到需要编辑的文章。'), 404);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            [$data, $errors] = validate_post_input($_POST, $post);
            if (!$errors) {
                save_post($data, $id);
                set_flash('success', sblog_t('文章已保存。'));
                redirect_to(url_for('edit', ['id' => $id]));
            }
            render_editor_page($post, post_form_from_request($_POST), $errors);
        }
        render_editor_page($post);
        break;

    case 'change_status':
        require_admin_post(url_for('admin_posts'));
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'draft');
        $post = fetch_post_by_id($id);
        if (!$post) {
            simple_error_page(sblog_t('文章不存在'), sblog_t('找不到需要变更状态的文章。'), 404);
        }
        $target = $status === 'published' ? 'published' : 'draft';
        $publishedAt = (int)$post['published_at'];
        if ($target === 'published' && $publishedAt < 1) {
            $publishedAt = time();
        }
        q('UPDATE posts SET status = ?, published_at = ?, updated_at = ? WHERE id = ?', [$target, $publishedAt, time(), $id]);
        set_flash('success', $target === 'published' ? sblog_t('文章已发布。') : sblog_t('文章已转为草稿。'));
        redirect_to(url_for('admin_posts'));
        break;

    case 'delete_post':
        require_admin_post(url_for('admin_posts'));
        $id = (int)($_POST['id'] ?? 0);
        q('DELETE FROM posts WHERE id = ?', [$id]);
        set_flash('success', sblog_t('文章已删除。'));
        redirect_to(url_for('admin_posts'));
        break;

    default:
        simple_error_page(sblog_t('页面不存在'), sblog_t('当前操作未定义。'), 404);
        break;
}
