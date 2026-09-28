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
            'admin_username' => '管理员用户名',
            'admin_email' => '管理员邮箱',
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
            'env_curl' => 'cURL 扩展（AI、S3 与扩展商店）',
            'env_zip' => 'ZipArchive 扩展（主题与插件商店）',
            'env_json' => 'JSON 扩展',
            'env_fileinfo' => 'Fileinfo 扩展（安全识别上传文件）',
            'env_random' => '安全随机数支持',
            'env_data' => 'data 目录可写',
            'env_cache' => 'cache 目录可写',
            'env_uploads' => 'uploads 目录可写',
            'env_themes' => 'themes 目录可写',
            'env_plugins' => 'plugins 目录可写',
            'error_environment' => '当前服务器环境未满足安装要求。',
            'error_site_name' => '站点名称不能为空。',
            'error_admin_username' => '管理员用户名不能为空。',
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
            'admin_username' => 'Administrator username',
            'admin_email' => 'Administrator email',
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
            'env_curl' => 'cURL extension (AI, S3, and extension store)',
            'env_zip' => 'ZipArchive extension (theme and plugin store)',
            'env_json' => 'JSON extension',
            'env_fileinfo' => 'Fileinfo extension (secure upload detection)',
            'env_random' => 'Secure random number support',
            'env_data' => 'Writable data directory',
            'env_cache' => 'Writable cache directory',
            'env_uploads' => 'Writable uploads directory',
            'env_themes' => 'Writable themes directory',
            'env_plugins' => 'Writable plugins directory',
            'error_environment' => 'The server does not meet the installation requirements.',
            'error_site_name' => 'Site name is required.',
            'error_admin_username' => 'Administrator username is required.',
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
        'active_theme' => 'default',
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

    if (!is_dir(__DIR__ . '/themes')) {
        mkdir(__DIR__ . '/themes', 0755, true);
    }

    if (!is_dir(__DIR__ . '/plugins')) {
        mkdir(__DIR__ . '/plugins', 0755, true);
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
        ['label' => i_t('env_zip'), 'ok' => class_exists('ZipArchive')],
        ['label' => i_t('env_json'), 'ok' => extension_loaded('json')],
        ['label' => i_t('env_fileinfo'), 'ok' => extension_loaded('fileinfo')],
        ['label' => i_t('env_random'), 'ok' => function_exists('random_bytes')],
        ['label' => i_t('env_data'), 'ok' => is_dir(INSTALL_DATA_DIR) && is_writable(INSTALL_DATA_DIR)],
        ['label' => i_t('env_cache'), 'ok' => is_dir(INSTALL_CACHE_DIR) && is_writable(INSTALL_CACHE_DIR)],
        ['label' => i_t('env_uploads'), 'ok' => is_dir(__DIR__ . '/uploads') && is_writable(__DIR__ . '/uploads')],
        ['label' => i_t('env_themes'), 'ok' => is_dir(__DIR__ . '/themes') && is_writable(__DIR__ . '/themes')],
        ['label' => i_t('env_plugins'), 'ok' => is_dir(__DIR__ . '/plugins') && is_writable(__DIR__ . '/plugins')],
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
  <style>
    :root {
      color-scheme: light;
      --ink: #171717;
      --muted: #666;
      --line: #e4e4e4;
      --soft: #f5f5f5;
      --font: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
    }
    * { box-sizing: border-box; }
    body { min-width: 320px; margin: 0; background: #fff; color: var(--ink); font: 14px/1.6 var(--font); }
    button, input, select { font: inherit; }
    a { color: inherit; text-decoration: none; }
    .main-wrap { width: min(calc(100% - 40px), 680px); margin: 0 auto; padding: 28px 0 80px; }
    .install-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 48px; font-size: 13px; }
    .install-toolbar > strong { font-weight: 700; white-space: nowrap; }
    .install-language-switch { display: inline-flex; gap: 2px; padding: 3px; border: 1px solid var(--line); border-radius: 5px; }
    .install-language-switch a { display: grid; min-width: 68px; min-height: 32px; place-items: center; padding: 0 8px; border-radius: 3px; color: var(--muted); font-size: 12px; font-weight: 600; }
    .install-language-switch a:hover { color: var(--ink); background: var(--soft); }
    .install-language-switch a.is-active { color: #fff; background: var(--ink); }
    .install-language-switch a:focus-visible, .button:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }
    .hero { margin-bottom: 28px; padding-bottom: 28px; border-bottom: 1px solid var(--line); }
    .hero__eyebrow { margin: 0 0 8px; color: var(--muted); font-size: 12px; font-weight: 600; }
    .hero__title { margin: 0; font-size: 30px; line-height: 1.25; font-weight: 650; }
    .hero__lead { margin: 10px 0 0; color: var(--muted); }
    .panel { margin-bottom: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--line); }
    .install-environment, .admin-grid .panel:last-child { padding-bottom: 8px; }
    .panel__header { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 4px 16px; margin-bottom: 14px; }
    .panel__header h2 { margin: 0; font-size: 16px; line-height: 1.4; }
    .panel__meta { margin: 0; color: var(--muted); font-size: 12px; }
    .environment-checks, .archive-items, .metric-grid { display: grid; grid-template-columns: minmax(0, 1fr); }
    .environment-check { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--line); }
    .environment-check:last-child, .archive-item:last-child, .metric-card:last-child { border-bottom: 0; }
    .environment-check span { min-width: 0; overflow-wrap: anywhere; }
    .environment-check__status { flex: 0 0 22px; font-size: 17px; line-height: 1; text-align: right; }
    .form-stack, .field { display: grid; gap: 16px; }
    .field { gap: 6px; }
    .field label { font-size: 13px; font-weight: 600; }
    .field input, .field select { width: 100%; min-width: 0; min-height: 44px; padding: 9px 11px; border: 1px solid #cfcfcf; border-radius: 4px; background: #fff; color: var(--ink); outline: none; }
    .field input:focus, .field select:focus { border-color: var(--ink); box-shadow: 0 0 0 2px #e4e4e4; }
    .action-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
    .button { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 18px; border: 1px solid var(--ink); border-radius: 4px; background: var(--ink); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; }
    .button:hover { background: #333; }
    .button--secondary { background: #fff; color: var(--ink); }
    .button--secondary:hover { background: var(--soft); }
    .button:disabled { border-color: #d6d6d6; background: #ececec; color: #777; cursor: not-allowed; }
    .form-stack .button { width: 100%; }
    .archive-items { margin: 0; padding: 0; list-style: none; }
    .archive-item { padding: 8px 0; border-bottom: 1px solid var(--line); overflow-wrap: anywhere; }
    .metric-card { display: grid; grid-template-columns: minmax(110px, .35fr) minmax(0, 1fr); gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line); }
    .metric-card__label { color: var(--muted); }
    .metric-card__value { overflow-wrap: anywhere; font-size: 14px; font-weight: 600; }
    .empty-state { padding: 4px 0 28px; }
    .flash { margin-bottom: 18px; padding: 10px 12px; border-left: 3px solid var(--ink); background: var(--soft); }
    @media (max-width: 480px) {
      .main-wrap { width: calc(100% - 32px); padding-top: 20px; }
      .install-toolbar { margin-bottom: 36px; }
      .hero__title { font-size: 26px; }
      .metric-card { grid-template-columns: minmax(0, 1fr); gap: 2px; }
      .action-row .button { width: 100%; }
    }
  </style>
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
            <div class="environment-check<?= $check['ok'] ? ' is-ok' : ' is-error' ?>"><span><?= i_h((string)$check['label']) ?></span><span class="environment-check__status" role="img" aria-label="<?= i_h(i_t($check['ok'] ? 'check_passed' : 'check_failed')) ?>" title="<?= i_h(i_t($check['ok'] ? 'check_passed' : 'check_failed')) ?>"><?= $check['ok'] ? '✅' : '❌' ?></span></div>
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
            <div class="field">
              <label for="site_name"><?= i_h(i_t('site_name')) ?></label>
              <input id="site_name" name="site_name" type="text" value="<?= i_h((string)$form['site_name']) ?>" required>
            </div>

            <div class="field">
              <label for="admin_username"><?= i_h(i_t('admin_username')) ?></label>
              <input id="admin_username" name="admin_username" type="text" value="<?= i_h((string)$form['admin_username']) ?>" required>
            </div>

            <div class="field">
              <label for="admin_email"><?= i_h(i_t('admin_email')) ?></label>
              <input id="admin_email" name="admin_email" type="email" value="<?= i_h((string)$form['admin_email']) ?>" maxlength="160" autocomplete="email" required>
            </div>

            <div class="field">
              <label for="admin_password"><?= i_h(i_t('admin_password')) ?></label>
              <input id="admin_password" name="admin_password" type="password" autocomplete="new-password" required>
            </div>
            <div class="field">
              <label for="admin_password2"><?= i_h(i_t('confirm_password')) ?></label>
              <input id="admin_password2" name="admin_password2" type="password" autocomplete="new-password" required>
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
    'admin_username' => 'admin',
    'admin_email' => '',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    i_render_form($form);
}

$form = [
    'site_name' => trim((string)($_POST['site_name'] ?? 'Simple PHP Blog')),
    'admin_username' => trim((string)($_POST['admin_username'] ?? 'admin')),
    'admin_email' => strtolower(trim((string)($_POST['admin_email'] ?? ''))),
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

if ($form['admin_username'] === '') {
    $errors[] = i_t('error_admin_username');
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
        post_format TEXT NOT NULL DEFAULT \'text\',
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
$settings['site_description'] = $settings['site_tagline'];

$statement = $db->prepare('INSERT OR REPLACE INTO settings(name, value) VALUES(?, ?)');
foreach ($settings as $name => $value) {
    $statement->execute([$name, $value]);
}

$db->prepare('INSERT INTO users(username, password_hash, nickname, email, avatar_url, website_url, created_at) VALUES(?, ?, ?, ?, ?, ?, ?)')
    ->execute([$form['admin_username'], password_hash($password, PASSWORD_DEFAULT), 'Admin', $form['admin_email'], '', '', $now]);
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
