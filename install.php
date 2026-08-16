<?php

declare(strict_types=1);

const INSTALL_DATA_DIR = __DIR__ . '/data';
const INSTALL_CACHE_DIR = __DIR__ . '/cache';
const INSTALL_DB_CONFIG_FILE = INSTALL_DATA_DIR . '/config.php';
const INSTALL_LOCK_FILE = INSTALL_DATA_DIR . '/install.lock';
const INSTALL_SETTINGS_CACHE_FILE = INSTALL_CACHE_DIR . '/settings.php';

function i_h(string|int|float|bool|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function i_locale(): string
{
    static $locale;
    if (is_string($locale)) {
        return $locale;
    }

    $requested = trim((string)($_POST['lang'] ?? $_GET['lang'] ?? ''));
    if (in_array($requested, ['zh-CN', 'en'], true)) {
        return $locale = $requested;
    }

    $accepted = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    return $locale = str_starts_with($accepted, 'en') ? 'en' : 'zh-CN';
}

function i_t(string $key, array $replace = []): string
{
    static $translations = [
        'zh-CN' => [
            'page_title' => '安装博客',
            'language' => '安装语言',
            'install_eyebrow' => 'Install',
            'install_title' => '安装博客',
            'install_lead' => '一次性初始化 SQLite、管理员账号和默认内容。',
            'environment_title' => '安装环境检测',
            'environment_ready' => '当前环境满足安装要求。',
            'environment_not_ready' => '请修复未通过项目后再安装。',
            'check_passed' => '通过',
            'check_failed' => '未通过',
            'details_title' => '安装信息',
            'site_name' => '站点名称',
            'site_tagline' => '首页副标题',
            'admin_username' => '管理员用户名',
            'admin_nickname' => '管理员昵称',
            'admin_email' => '管理员邮箱',
            'pretty_url' => '伪静态 URL',
            'disabled' => '关闭',
            'enabled' => '开启',
            'admin_password' => '管理员密码',
            'confirm_password' => '确认密码',
            'start_install' => '开始安装',
            'creates_title' => '将会创建',
            'creates_database' => '随机文件名 SQLite 数据库',
            'creates_tables' => '站点设置、用户、内容、分类与评论数据表',
            'creates_content' => '默认分类、Hello World 文章与默认关于页',
            'creates_lock' => '`data/install.lock` 安装锁',
            'creates_cache' => '`cache/settings.php` 站点配置缓存',
            'locked_title' => '安装已锁定',
            'locked_lead' => '如果你要重新安装，请先删除 `data/install.lock`。',
            'home' => '进入首页',
            'installed_eyebrow' => 'Installed',
            'installed_title' => '安装完成',
            'installed_lead' => '博客已经可以直接使用了。',
            'result_title' => '安装结果',
            'result_site' => '站点名称',
            'result_admin' => '管理员',
            'result_database' => '数据库',
            'login_admin' => '登录后台',
            'env_php' => 'PHP 8.0 或更高版本',
            'env_pdo' => 'PDO 扩展',
            'env_sqlite' => 'PDO SQLite 驱动',
            'env_curl' => 'cURL 扩展（AI 与 S3 接口）',
            'env_json' => 'JSON 扩展',
            'env_fileinfo' => 'Fileinfo 扩展（安全识别上传文件）',
            'env_random' => '安全随机数支持',
            'env_data' => 'data 目录可写',
            'env_cache' => 'cache 目录可写',
            'env_uploads' => 'uploads 目录可写',
            'error_environment' => '当前服务器环境未满足安装要求。',
            'error_site_name' => '站点名称不能为空。',
            'error_site_tagline' => '首页副标题不能为空。',
            'error_admin_username' => '管理员用户名不能为空。',
            'error_author_name' => '作者显示名不能为空。',
            'error_admin_email' => '请填写有效的管理员邮箱地址。',
            'error_password' => '管理员密码不能为空。',
            'error_password_match' => '两次输入的密码不一致。',
        ],
        'en' => [
            'page_title' => 'Install Blog',
            'language' => 'Installer language',
            'install_eyebrow' => 'Install',
            'install_title' => 'Install your blog',
            'install_lead' => 'Set up SQLite, your administrator account, and the default content in one step.',
            'environment_title' => 'Environment checks',
            'environment_ready' => 'This server meets the installation requirements.',
            'environment_not_ready' => 'Resolve the failed checks before installing.',
            'check_passed' => 'Passed',
            'check_failed' => 'Failed',
            'details_title' => 'Installation details',
            'site_name' => 'Site name',
            'site_tagline' => 'Homepage tagline',
            'admin_username' => 'Administrator username',
            'admin_nickname' => 'Administrator display name',
            'admin_email' => 'Administrator email',
            'pretty_url' => 'Pretty URLs',
            'disabled' => 'Off',
            'enabled' => 'On',
            'admin_password' => 'Administrator password',
            'confirm_password' => 'Confirm password',
            'start_install' => 'Install now',
            'creates_title' => 'The installer will create',
            'creates_database' => 'A SQLite database with a randomized filename',
            'creates_tables' => 'Tables for settings, users, content, categories, and comments',
            'creates_content' => 'A default category, Hello World post, and About page',
            'creates_lock' => 'The `data/install.lock` installation lock',
            'creates_cache' => 'The `cache/settings.php` settings cache',
            'locked_title' => 'Installation locked',
            'locked_lead' => 'To reinstall, delete `data/install.lock` first.',
            'home' => 'Open homepage',
            'installed_eyebrow' => 'Installed',
            'installed_title' => 'Installation complete',
            'installed_lead' => 'Your blog is ready to use.',
            'result_title' => 'Installation result',
            'result_site' => 'Site name',
            'result_admin' => 'Administrator',
            'result_database' => 'Database',
            'login_admin' => 'Sign in to admin',
            'env_php' => 'PHP 8.0 or newer',
            'env_pdo' => 'PDO extension',
            'env_sqlite' => 'PDO SQLite driver',
            'env_curl' => 'cURL extension (AI and S3 integrations)',
            'env_json' => 'JSON extension',
            'env_fileinfo' => 'Fileinfo extension (secure upload detection)',
            'env_random' => 'Secure random number support',
            'env_data' => 'Writable data directory',
            'env_cache' => 'Writable cache directory',
            'env_uploads' => 'Writable uploads directory',
            'error_environment' => 'The server does not meet the installation requirements.',
            'error_site_name' => 'Site name is required.',
            'error_site_tagline' => 'Homepage tagline is required.',
            'error_admin_username' => 'Administrator username is required.',
            'error_author_name' => 'Administrator display name is required.',
            'error_admin_email' => 'Enter a valid administrator email address.',
            'error_password' => 'Administrator password is required.',
            'error_password_match' => 'The passwords do not match.',
        ],
    ];

    $text = $translations[i_locale()][$key] ?? $translations['zh-CN'][$key] ?? $key;
    return $replace ? strtr($text, $replace) : $text;
}

function i_locale_url(string $locale): string
{
    return i_asset_url('install.php') . '?lang=' . rawurlencode($locale);
}

function i_default_settings(): array
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
        'active_plugins' => '[]',
        'favicon_url' => 'favicon.png',
        'footer_beian' => '',
        'comments_enabled' => '1',
        'comments_require_approval' => '1',
        'comments_notify' => '1',
        'posts_per_page' => '6',
        'pretty_url' => '0',
    ];
}

function i_db_name(): string
{
    if (is_file(INSTALL_LOCK_FILE) && is_file(INSTALL_DB_CONFIG_FILE)) {
        $config = include INSTALL_DB_CONFIG_FILE;
        $name = is_array($config) ? basename((string)($config['db_file'] ?? '')) : '';
        if ($name !== '' && $name !== 'blog.sqlite' && preg_match('/^blog-[a-f0-9]{16}\.sqlite$/', $name)) {
            return $name;
        }
    }

    return 'blog-' . bin2hex(random_bytes(8)) . '.sqlite';
}

function i_db_file(): string
{
    return INSTALL_DATA_DIR . '/' . i_db_name();
}

function i_is_installed(): bool
{
    if (!is_file(INSTALL_LOCK_FILE) || !is_file(INSTALL_DB_CONFIG_FILE)) { return false; }
    $config = include INSTALL_DB_CONFIG_FILE;
    $name = is_array($config) ? basename((string)($config['db_file'] ?? '')) : '';
    return preg_match('/^blog-[a-f0-9]{16}\.sqlite$/', $name) === 1 && is_file(INSTALL_DATA_DIR . '/' . $name);
}

function i_ensure_dirs(): void
{
    if (!is_dir(INSTALL_DATA_DIR)) {
        mkdir(INSTALL_DATA_DIR, 0755, true);
    }

    if (!is_dir(INSTALL_CACHE_DIR)) {
        mkdir(INSTALL_CACHE_DIR, 0755, true);
    }

    if (!is_dir(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0755, true);
    }
}

function i_environment_checks(): array
{
    i_ensure_dirs();
    return [
        ['label' => i_t('env_php'), 'ok' => version_compare(PHP_VERSION, '8.0.0', '>=')],
        ['label' => i_t('env_pdo'), 'ok' => extension_loaded('pdo')],
        ['label' => i_t('env_sqlite'), 'ok' => extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true)],
        ['label' => i_t('env_curl'), 'ok' => extension_loaded('curl')],
        ['label' => i_t('env_json'), 'ok' => extension_loaded('json')],
        ['label' => i_t('env_fileinfo'), 'ok' => extension_loaded('fileinfo')],
        ['label' => i_t('env_random'), 'ok' => function_exists('random_bytes')],
        ['label' => i_t('env_data'), 'ok' => is_dir(INSTALL_DATA_DIR) && is_writable(INSTALL_DATA_DIR)],
        ['label' => i_t('env_cache'), 'ok' => is_dir(INSTALL_CACHE_DIR) && is_writable(INSTALL_CACHE_DIR)],
        ['label' => i_t('env_uploads'), 'ok' => is_dir(__DIR__ . '/uploads') && is_writable(__DIR__ . '/uploads')],
    ];
}

function i_environment_ready(array $checks): bool
{
    foreach ($checks as $check) { if (empty($check['ok'])) { return false; } }
    return true;
}

function i_save_db_config(string $dbName): void
{
    i_ensure_dirs();
    file_put_contents(INSTALL_DB_CONFIG_FILE, "<?php\nreturn ['db_file' => " . var_export($dbName, true) . "];\n", LOCK_EX);
}

function i_db(): PDO
{
    static $db;

    if ($db instanceof PDO) {
        return $db;
    }

    $file = i_db_file();
    i_ensure_dirs();
    i_save_db_config(basename($file));

    $db = new PDO('sqlite:' . $file, null, null, [
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

    return $db;
}

function i_plain_excerpt(string $content, int $length = 140): string
{
    $text = preg_replace('/\s+/u', ' ', strip_tags($content)) ?? $content;
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($len <= $length) {
        return $text;
    }

    return rtrim(function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length)) . '…';
}

function i_write_settings_cache(PDO $db): void
{
    i_ensure_dirs();
    $settings = i_default_settings();
    foreach ($db->query('SELECT name, value FROM settings') as $row) {
        $settings[(string)$row['name']] = (string)$row['value'];
    }
    file_put_contents(INSTALL_SETTINGS_CACHE_FILE, "<?php\nreturn " . var_export($settings, true) . ";\n", LOCK_EX);
}

function i_hello_world_body(): string
{
    return <<<MD
# Hello World

Welcome to your new blog. This is your first post. Edit it from the admin dashboard, or delete it and start writing.
MD;
}

function i_about_body(string $siteName): string
{
    if (i_locale() === 'en') {
        return <<<MD
# About {$siteName}

This page was created during installation. Use it to introduce yourself, describe the blog, or share contact details.
MD;
    }

    return <<<MD
# 关于 {$siteName}

这是安装时自动生成的独立页面，你可以把这里改成博客简介、作者介绍，或者放联系方式。

## 建议内容

- 你是谁
- 这个博客主要写什么
- 如何联系你

> 这个页面会出现在顶部导航里。
MD;
}

function i_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/install.php'));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $dir === '' || $dir === '.' ? '' : $dir;
}

function i_asset_url(string $path): string
{
    return (i_base_path() !== '' ? i_base_path() : '') . '/' . ltrim($path, '/');
}

function i_render_page(string $title, string $body): void
{
    ?>
<!doctype html>
<html lang="<?= i_h(i_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= i_h($title) ?></title>
  <link rel="stylesheet" href="<?= i_h(i_asset_url('index.css')) ?>?v=v1.6.0">
</head>
<body>
  <div class="site-frame">
    <main class="main-wrap main-wrap--wide">
      <div class="install-toolbar">
        <strong>SBlog Setup</strong>
        <nav class="install-language-switch" aria-label="<?= i_h(i_t('language')) ?>">
          <a href="<?= i_h(i_locale_url('zh-CN')) ?>" hreflang="zh-CN"<?= i_locale() === 'zh-CN' ? ' class="is-active" aria-current="page"' : '' ?>>中文</a>
          <a href="<?= i_h(i_locale_url('en')) ?>" hreflang="en"<?= i_locale() === 'en' ? ' class="is-active" aria-current="page"' : '' ?>>English</a>
        </nav>
      </div>
      <?= $body ?>
    </main>
  </div>
</body>
</html>
<?php
    exit;
}

function i_render_form(array $form, array $errors = []): void
{
    $environmentChecks = i_environment_checks();
    $environmentReady = i_environment_ready($environmentChecks);
    ob_start();
    ?>
    <section class="hero hero--compact">
      <p class="hero__eyebrow"><?= i_h(i_t('install_eyebrow')) ?></p>
      <h1 class="hero__title"><?= i_h(i_t('install_title')) ?></h1>
      <p class="hero__lead"><?= i_h(i_t('install_lead')) ?></p>
    </section>

    <section class="panel install-environment">
      <div class="panel__header"><h2><?= i_h(i_t('environment_title')) ?></h2><p class="panel__meta"><?= i_h(i_t($environmentReady ? 'environment_ready' : 'environment_not_ready')) ?></p></div>
      <div class="panel__body">
        <div class="environment-checks">
          <?php foreach ($environmentChecks as $check): ?>
            <div class="environment-check<?= $check['ok'] ? ' is-ok' : ' is-error' ?>"><strong><?= i_h(i_t($check['ok'] ? 'check_passed' : 'check_failed')) ?></strong><span><?= i_h((string)$check['label']) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <div class="admin-grid">
      <section class="panel">
        <div class="panel__header">
          <h2><?= i_h(i_t('details_title')) ?></h2>
        </div>
        <div class="panel__body">
          <?php if ($errors): ?>
            <div class="flash flash--error"><?= i_h(implode(' ', $errors)) ?></div>
          <?php endif; ?>

          <form class="form-stack" method="post">
            <input type="hidden" name="lang" value="<?= i_h(i_locale()) ?>">
            <div class="field-grid">
              <div class="field">
                <label for="site_name"><?= i_h(i_t('site_name')) ?></label>
                <input id="site_name" name="site_name" type="text" value="<?= i_h((string)$form['site_name']) ?>" required>
              </div>
              <div class="field">
                <label for="site_tagline"><?= i_h(i_t('site_tagline')) ?></label>
                <input id="site_tagline" name="site_tagline" type="text" value="<?= i_h((string)$form['site_tagline']) ?>" required>
              </div>
            </div>

            <div class="field-grid">
              <div class="field">
                <label for="admin_username"><?= i_h(i_t('admin_username')) ?></label>
                <input id="admin_username" name="admin_username" type="text" value="<?= i_h((string)$form['admin_username']) ?>" required>
              </div>
              <div class="field">
                <label for="author_name"><?= i_h(i_t('admin_nickname')) ?></label>
                <input id="author_name" name="author_name" type="text" value="<?= i_h((string)$form['author_name']) ?>" required>
              </div>
            </div>

            <div class="field">
              <label for="admin_email"><?= i_h(i_t('admin_email')) ?></label>
              <input id="admin_email" name="admin_email" type="email" value="<?= i_h((string)$form['admin_email']) ?>" maxlength="160" autocomplete="email" required>
            </div>

            <div class="field">
              <label for="pretty_url"><?= i_h(i_t('pretty_url')) ?></label>
              <select id="pretty_url" name="pretty_url">
                <option value="0"<?= (string)($form['pretty_url'] ?? '0') === '0' ? ' selected' : '' ?>><?= i_h(i_t('disabled')) ?></option>
                <option value="1"<?= (string)($form['pretty_url'] ?? '0') === '1' ? ' selected' : '' ?>><?= i_h(i_t('enabled')) ?></option>
              </select>
            </div>

            <div class="field-grid">
              <div class="field">
                <label for="admin_password"><?= i_h(i_t('admin_password')) ?></label>
                <input id="admin_password" name="admin_password" type="password" autocomplete="new-password" required>
              </div>
              <div class="field">
                <label for="admin_password2"><?= i_h(i_t('confirm_password')) ?></label>
                <input id="admin_password2" name="admin_password2" type="password" autocomplete="new-password" required>
              </div>
            </div>

            <div class="action-row">
              <button class="button" type="submit"<?= $environmentReady ? '' : ' disabled' ?>><?= i_h(i_t('start_install')) ?></button>
            </div>
          </form>
        </div>
      </section>

      <section class="panel">
        <div class="panel__header">
          <h2><?= i_h(i_t('creates_title')) ?></h2>
        </div>
        <div class="panel__body">
          <ul class="archive-items archive-items--plain">
            <li class="archive-item"><span><?= i_h(i_t('creates_database')) ?></span></li>
            <li class="archive-item"><span><?= i_h(i_t('creates_tables')) ?></span></li>
            <li class="archive-item"><span><?= i_h(i_t('creates_content')) ?></span></li>
            <li class="archive-item"><span><?= i_h(i_t('creates_lock')) ?></span></li>
            <li class="archive-item"><span><?= i_h(i_t('creates_cache')) ?></span></li>
          </ul>
        </div>
      </section>
    </div>
    <?php
    i_render_page(i_t('page_title'), (string)ob_get_clean());
}

function i_render_locked(): void
{
    ob_start();
    ?>
    <section class="hero hero--compact">
      <p class="hero__eyebrow"><?= i_h(i_t('install_eyebrow')) ?></p>
      <h1 class="hero__title"><?= i_h(i_t('locked_title')) ?></h1>
      <p class="hero__lead"><?= i_h(i_t('locked_lead')) ?></p>
    </section>
    <div class="empty-state">
      <a class="button" href="index.php"><?= i_h(i_t('home')) ?></a>
    </div>
    <?php
    i_render_page(i_t('locked_title'), (string)ob_get_clean());
}

function i_render_success(string $siteName, string $adminUsername, string $dbName): void
{
    ob_start();
    ?>
    <section class="hero hero--compact">
      <p class="hero__eyebrow"><?= i_h(i_t('installed_eyebrow')) ?></p>
      <h1 class="hero__title"><?= i_h(i_t('installed_title')) ?></h1>
      <p class="hero__lead"><?= i_h(i_t('installed_lead')) ?></p>
    </section>

    <div class="admin-grid">
      <section class="panel">
        <div class="panel__header">
          <h2><?= i_h(i_t('result_title')) ?></h2>
        </div>
        <div class="panel__body">
          <div class="metric-grid">
            <div class="metric-card">
              <span class="metric-card__label"><?= i_h(i_t('result_site')) ?></span>
              <strong class="metric-card__value metric-card__value--small"><?= i_h($siteName) ?></strong>
            </div>
            <div class="metric-card">
              <span class="metric-card__label"><?= i_h(i_t('result_admin')) ?></span>
              <strong class="metric-card__value metric-card__value--small"><?= i_h($adminUsername) ?></strong>
            </div>
            <div class="metric-card">
              <span class="metric-card__label"><?= i_h(i_t('result_database')) ?></span>
              <strong class="metric-card__value metric-card__value--small"><?= i_h($dbName) ?></strong>
            </div>
          </div>
          <div class="action-row action-row--start">
            <a class="button" href="index.php"><?= i_h(i_t('home')) ?></a>
            <a class="button button--secondary" href="index.php?a=login"><?= i_h(i_t('login_admin')) ?></a>
          </div>
        </div>
      </section>
    </div>
    <?php
    i_render_page(i_t('installed_title'), (string)ob_get_clean());
}

if (i_is_installed()) {
    i_render_locked();
}

$form = [
    'site_name' => 'Simple PHP Blog',
    'site_tagline' => 'A small PHP blog running on one main entry file.',
    'admin_username' => 'admin',
    'author_name' => 'Admin',
    'admin_email' => '',
    'pretty_url' => '0',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    i_render_form($form);
}

$form = [
    'site_name' => trim((string)($_POST['site_name'] ?? 'Simple PHP Blog')),
    'site_tagline' => trim((string)($_POST['site_tagline'] ?? '')),
    'admin_username' => trim((string)($_POST['admin_username'] ?? 'admin')),
    'author_name' => trim((string)($_POST['author_name'] ?? 'Admin')),
    'admin_email' => strtolower(trim((string)($_POST['admin_email'] ?? ''))),
    'pretty_url' => (string)($_POST['pretty_url'] ?? '0') === '1' ? '1' : '0',
];

$password = (string)($_POST['admin_password'] ?? '');
$password2 = (string)($_POST['admin_password2'] ?? '');
$errors = [];
$environmentChecks = i_environment_checks();
if (!i_environment_ready($environmentChecks)) {
    $errors[] = i_t('error_environment');
}

if ($form['site_name'] === '') {
    $errors[] = i_t('error_site_name');
}

if ($form['site_tagline'] === '') {
    $errors[] = i_t('error_site_tagline');
}

if ($form['admin_username'] === '') {
    $errors[] = i_t('error_admin_username');
}

if ($form['author_name'] === '') {
    $errors[] = i_t('error_author_name');
}

if ($form['admin_email'] === '' || strlen($form['admin_email']) > 160 || !filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = i_t('error_admin_email');
}

if ($password === '') {
    $errors[] = i_t('error_password');
}

if ($password !== $password2) {
    $errors[] = i_t('error_password_match');
}

if ($errors) {
    i_render_form($form, $errors);
}

$db = i_db();
$db->exec(
    'CREATE TABLE IF NOT EXISTS settings(
        name TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT \'\'
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS ai_settings(
        name TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT \'\'
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS mail_settings(
        name TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT \'\'
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS s3_settings(
        name TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT \'\'
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        nickname TEXT NOT NULL DEFAULT \'\',
        email TEXT NOT NULL DEFAULT \'\',
        avatar_url TEXT NOT NULL DEFAULT \'\',
        website_url TEXT NOT NULL DEFAULT \'\',
        github_url TEXT NOT NULL DEFAULT \'\',
        qq_url TEXT NOT NULL DEFAULT \'\',
        wechat_url TEXT NOT NULL DEFAULT \'\',
        weibo_url TEXT NOT NULL DEFAULT \'\',
        x_url TEXT NOT NULL DEFAULT \'\',
        telegram_url TEXT NOT NULL DEFAULT \'\',
        mastodon_url TEXT NOT NULL DEFAULT \'\',
        bilibili_url TEXT NOT NULL DEFAULT \'\',
        instagram_url TEXT NOT NULL DEFAULT \'\',
        tiktok_url TEXT NOT NULL DEFAULT \'\',
        signature TEXT NOT NULL DEFAULT \'\',
        created_at INTEGER NOT NULL
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS posts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        author_id INTEGER,
        category_id INTEGER,
        slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        excerpt TEXT NOT NULL DEFAULT \'\',
        content TEXT NOT NULL,
        kind TEXT NOT NULL DEFAULT \'post\',
        tags TEXT NOT NULL DEFAULT \'[]\',
        views INTEGER NOT NULL DEFAULT 0,
        is_pinned INTEGER NOT NULL DEFAULT 0,
        allow_comments INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT \'draft\',
        published_at INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS categories(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        description TEXT NOT NULL DEFAULT \'\',
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS comments(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        user_id INTEGER,
        parent_id INTEGER,
        reply_to_name TEXT NOT NULL DEFAULT \'\',
        author_name TEXT NOT NULL,
        author_email TEXT NOT NULL,
        author_url TEXT NOT NULL DEFAULT \'\',
        content TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'pending\',
        is_read INTEGER NOT NULL DEFAULT 0,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        ip_address TEXT NOT NULL DEFAULT \'\',
        user_agent TEXT NOT NULL DEFAULT \'\',
        reply_notified_at INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL,
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY(parent_id) REFERENCES comments(id) ON DELETE SET NULL
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS post_views(
        post_id INTEGER NOT NULL,
        ip_hash TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        PRIMARY KEY(post_id, ip_hash),
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) WITHOUT ROWID'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS post_likes(
        post_id INTEGER NOT NULL,
        ip_hash TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        PRIMARY KEY(post_id, ip_hash),
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) WITHOUT ROWID'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS media(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        original_name TEXT NOT NULL,
        title TEXT NOT NULL DEFAULT \'\',
        alt_text TEXT NOT NULL DEFAULT \'\',
        caption TEXT NOT NULL DEFAULT \'\',
        url TEXT NOT NULL,
        storage_driver TEXT NOT NULL DEFAULT \'local\',
        storage_key TEXT NOT NULL DEFAULT \'\',
        local_path TEXT NOT NULL DEFAULT \'\',
        mime_type TEXT NOT NULL,
        file_size INTEGER NOT NULL DEFAULT 0,
        is_image INTEGER NOT NULL DEFAULT 0,
        width INTEGER NOT NULL DEFAULT 0,
        height INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )'
);
$db->exec('CREATE INDEX IF NOT EXISTS idx_posts_published_pinned ON posts(kind, status, is_pinned DESC, published_at DESC, id DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category_id, kind, status, published_at DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_categories_sort ON categories(sort_order ASC, id DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_post_public ON comments(post_id, status, created_at, id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_moderation ON comments(status, created_at DESC, id DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_unread ON comments(is_read, created_at DESC, id DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_ip_recent ON comments(ip_hash, created_at DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_parent ON comments(parent_id, created_at, id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_user_recent ON comments(user_id, created_at DESC)');
$db->exec("CREATE INDEX IF NOT EXISTS idx_comments_visitor_email_approval ON comments(author_email COLLATE NOCASE, status) WHERE user_id IS NULL");
$db->exec('CREATE INDEX IF NOT EXISTS idx_media_created ON media(created_at DESC, id DESC)');
$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_media_local_path ON media(local_path) WHERE local_path <> ''");

$now = time();
$settings = i_default_settings();
$settings['site_name'] = $form['site_name'];
$settings['site_tagline'] = $form['site_tagline'];
$settings['site_description'] = $form['site_tagline'];
$settings['pretty_url'] = $form['pretty_url'];

$statement = $db->prepare('INSERT OR REPLACE INTO settings(name, value) VALUES(?, ?)');
foreach ($settings as $name => $value) {
    $statement->execute([$name, $value]);
}

$db->prepare('INSERT INTO users(username, password_hash, nickname, email, avatar_url, website_url, created_at) VALUES(?, ?, ?, ?, ?, ?, ?)')
    ->execute([$form['admin_username'], password_hash($password, PASSWORD_DEFAULT), $form['author_name'], $form['admin_email'], '', '', $now]);
$defaultAuthorId = (int)$db->lastInsertId();

$db->prepare('INSERT INTO categories(name, slug, description, sort_order, created_at, updated_at) VALUES(?, ?, ?, ?, ?, ?)')
    ->execute(i_locale() === 'en'
        ? ['General', 'default', 'The default post category created during installation.', 0, $now, $now]
        : ['默认分类', 'default', '安装时自动创建的默认文章分类。', 0, $now, $now]);
$defaultCategoryId = (int)$db->lastInsertId();

$helloWorldBody = i_hello_world_body();

$db->prepare(
    'INSERT INTO posts(author_id, kind, category_id, slug, title, tags, excerpt, content, status, published_at, created_at, updated_at)
     VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $defaultAuthorId,
    'post',
    $defaultCategoryId,
    'hello-world',
    'Hello World',
    '[]',
    i_plain_excerpt($helloWorldBody),
    $helloWorldBody,
    'published',
    $now,
    $now,
    $now,
]);

$db->prepare(
    'INSERT INTO posts(author_id, kind, category_id, slug, title, tags, excerpt, content, status, published_at, created_at, updated_at)
     VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $defaultAuthorId,
    'page',
    null,
    'about',
    i_locale() === 'en' ? 'About' : '关于',
    '[]',
    i_locale() === 'en' ? 'About page' : '关于页面',
    i_about_body($form['site_name']),
    'published',
    $now,
    $now,
    $now,
]);

i_write_settings_cache($db);
file_put_contents(INSTALL_LOCK_FILE, (string)$now, LOCK_EX);

i_render_success($form['site_name'], $form['admin_username'], basename(i_db_file()));
