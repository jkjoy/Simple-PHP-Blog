<?php
declare(strict_types=1);

const SBI_REPOSITORY = 'jkjoy/Simple-PHP-Blog';
const SBI_RELEASE_API = 'https://api.github.com/repos/' . SBI_REPOSITORY . '/releases/latest';
const SBI_MAX_PACKAGE_BYTES = 67108864;
const SBI_MAX_EXTRACTED_BYTES = 268435456;

session_start();

function sbi_h(string|int $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sbi_locale(): string
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

function sbi_t(string $chinese, string $english): string
{
    return sbi_locale() === 'en' ? $english : $chinese;
}

function sbi_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/installer.php'));
    $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $directory === '' || $directory === '.' ? '' : $directory;
}

function sbi_url(string $file): string
{
    return (sbi_base_path() !== '' ? sbi_base_path() : '') . '/' . ltrim($file, '/');
}

function sbi_self_url(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/installer.php'));
    return sbi_url(basename($script));
}

function sbi_localized_url(string $file, array $query = []): string
{
    return sbi_url($file) . '?' . http_build_query(['lang' => sbi_locale()] + $query);
}

function sbi_csrf_token(): string
{
    if (empty($_SESSION['sbi_csrf_token'])) {
        $_SESSION['sbi_csrf_token'] = bin2hex(random_bytes(24));
    }
    return (string)$_SESSION['sbi_csrf_token'];
}

function sbi_app_state(): string
{
    if (is_file(__DIR__ . '/data/install.lock') && is_file(__DIR__ . '/index.php')) {
        return 'installed';
    }
    if (is_file(__DIR__ . '/index.php') || is_file(__DIR__ . '/install.php')) {
        return 'deployed';
    }
    return 'empty';
}

function sbi_environment_checks(): array
{
    $enabled = sbi_t('已启用', 'Enabled');
    $disabled = sbi_t('未启用', 'Disabled');
    return [
        ['label' => sbi_t('PHP 8.0 或更高版本', 'PHP 8.0 or newer'), 'detail' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.0.0', '>=')],
        ['label' => sbi_t('当前目录可写', 'Current directory is writable'), 'detail' => basename(__DIR__), 'ok' => is_writable(__DIR__)],
        ['label' => sbi_t('cURL 网络扩展', 'cURL network extension'), 'detail' => function_exists('curl_init') ? $enabled : $disabled, 'ok' => function_exists('curl_init')],
        ['label' => sbi_t('ZipArchive 解压扩展', 'ZipArchive extraction extension'), 'detail' => class_exists('ZipArchive') ? $enabled : $disabled, 'ok' => class_exists('ZipArchive')],
        ['label' => sbi_t('PDO SQLite 数据库扩展', 'PDO SQLite database extension'), 'detail' => extension_loaded('pdo_sqlite') ? $enabled : $disabled, 'ok' => extension_loaded('pdo_sqlite')],
        ['label' => sbi_t('Fileinfo 文件扩展', 'Fileinfo extension'), 'detail' => extension_loaded('fileinfo') ? $enabled : $disabled, 'ok' => extension_loaded('fileinfo')],
    ];
}

function sbi_environment_ready(array $checks): bool
{
    foreach ($checks as $check) {
        if (empty($check['ok'])) {
            return false;
        }
    }
    return true;
}

function sbi_curl_trust_options(): array
{
    $candidates = [
        (string)ini_get('curl.cainfo'),
        (string)ini_get('openssl.cafile'),
        (string)(getenv('CURL_CA_BUNDLE') ?: ''),
        (string)(getenv('SSL_CERT_FILE') ?: ''),
        rtrim((string)(getenv('ProgramFiles') ?: 'C:/Program Files'), '/\\') . '/Git/mingw64/etc/ssl/certs/ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
    ];
    foreach (array_unique($candidates) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
            return [CURLOPT_CAINFO => $candidate];
        }
    }
    return [];
}

function sbi_latest_release(bool $refresh = false): array
{
    $cached = $_SESSION['sbi_release'] ?? null;
    if (!$refresh && is_array($cached) && time() - (int)($cached['checked_at'] ?? 0) < 300) {
        return $cached;
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException(sbi_t('服务器未启用 cURL，无法连接 GitHub。', 'cURL is not enabled, so the installer cannot connect to GitHub.'));
    }

    $curl = curl_init(SBI_RELEASE_API);
    curl_setopt_array($curl, array_replace([
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Simple-PHP-Blog-Installer/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'],
    ], sbi_curl_trust_options()));
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    $data = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($data)) {
        throw new RuntimeException(sbi_t('无法获取最新版本：', 'Unable to fetch the latest release: ') . ($error !== '' ? $error : 'GitHub HTTP ' . $status));
    }
    $version = trim((string)($data['tag_name'] ?? ''));
    $downloadUrl = trim((string)($data['zipball_url'] ?? ''));
    $host = strtolower((string)parse_url($downloadUrl, PHP_URL_HOST));
    if ($version === '' || !filter_var($downloadUrl, FILTER_VALIDATE_URL) || $host !== 'api.github.com') {
        throw new RuntimeException(sbi_t('GitHub Release 信息不完整或下载地址无效。', 'The GitHub Release metadata is incomplete or its download URL is invalid.'));
    }

    $release = [
        'version' => $version,
        'download_url' => $downloadUrl,
        'published_at' => trim((string)($data['published_at'] ?? '')),
        'checked_at' => time(),
    ];
    $_SESSION['sbi_release'] = $release;
    return $release;
}

function sbi_remove_tree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
}

function sbi_validate_zip(ZipArchive $zip): void
{
    $extractedBytes = 0;
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = str_replace('\\', '/', (string)$zip->getNameIndex($index));
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name)) {
            throw new RuntimeException(sbi_t('安装包包含不安全的文件路径。', 'The package contains an unsafe file path.'));
        }
        foreach (explode('/', trim($name, '/')) as $segment) {
            if ($segment === '..') {
                throw new RuntimeException(sbi_t('安装包包含目录穿越路径。', 'The package contains a directory traversal path.'));
            }
        }
        $attributes = 0;
        if ($zip->getExternalAttributesIndex($index, $system, $attributes)) {
            $type = ($attributes >> 16) & 0xF000;
            if ($type === 0xA000) {
                throw new RuntimeException(sbi_t('安装包不能包含符号链接。', 'The package must not contain symbolic links.'));
            }
        }
        $stat = $zip->statIndex($index);
        $extractedBytes += is_array($stat) ? max(0, (int)($stat['size'] ?? 0)) : 0;
        if ($extractedBytes > SBI_MAX_EXTRACTED_BYTES) {
            throw new RuntimeException(sbi_t('安装包解压后超过 256 MB 安全限制。', 'The extracted package exceeds the 256 MB safety limit.'));
        }
    }
}

function sbi_create_directory(string $directory, array &$createdDirectories): void
{
    if (is_dir($directory)) {
        return;
    }
    if (file_exists($directory)) {
        throw new RuntimeException(sbi_t('目标路径不是目录：', 'The target path is not a directory: ') . basename($directory));
    }
    $missing = [];
    $cursor = $directory;
    while (!is_dir($cursor)) {
        $missing[] = $cursor;
        $parent = dirname($cursor);
        if ($parent === $cursor) {
            break;
        }
        $cursor = $parent;
    }
    $createdSuccessfully = mkdir($directory, 0755, true);
    foreach (array_reverse($missing) as $createdDirectory) {
        if (is_dir($createdDirectory)) {
            $createdDirectories[] = $createdDirectory;
        }
    }
    if (!$createdSuccessfully && !is_dir($directory)) {
        throw new RuntimeException(sbi_t('无法创建目录：', 'Unable to create directory: ') . basename($directory));
    }
}

function sbi_deploy_release(array $release): int
{
    if (sbi_app_state() !== 'empty') {
        throw new RuntimeException(sbi_t('当前目录已经存在 SBlog 程序文件，安装器不会覆盖它们。', 'SBlog files already exist in this directory. The installer will not overwrite them.'));
    }
    $workDirectory = __DIR__ . '/.sblog-install-' . bin2hex(random_bytes(6));
    $zipFile = $workDirectory . '/release.zip';
    $extractDirectory = $workDirectory . '/source';
    $createdFiles = [];
    $createdDirectories = [];

    if (!mkdir($workDirectory, 0700, true)) {
        throw new RuntimeException(sbi_t('无法创建安装临时目录。', 'Unable to create the temporary installation directory.'));
    }

    try {
        $handle = fopen($zipFile, 'wb');
        if ($handle === false) {
            throw new RuntimeException(sbi_t('无法创建安装包文件。', 'Unable to create the package file.'));
        }
        $downloaded = 0;
        $tooLarge = false;
        $curl = curl_init((string)$release['download_url']);
        curl_setopt_array($curl, array_replace([
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Simple-PHP-Blog-Installer/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
            CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use ($handle, &$downloaded, &$tooLarge): int {
                $length = strlen($chunk);
                $downloaded += $length;
                if ($downloaded > SBI_MAX_PACKAGE_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $written = fwrite($handle, $chunk);
                return $written === false ? 0 : $written;
            },
        ], sbi_curl_trust_options()));
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);
        if ($tooLarge) {
            throw new RuntimeException(sbi_t('安装包超过 64 MB 安全限制。', 'The package exceeds the 64 MB safety limit.'));
        }
        if (!$ok || $status !== 200 || $downloaded === 0) {
            throw new RuntimeException(sbi_t('安装包下载失败：', 'Package download failed: ') . ($error !== '' ? $error : 'HTTP ' . $status));
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zipFile);
        if ($opened !== true) {
            throw new RuntimeException(sbi_t('下载内容不是有效的 ZIP 安装包。', 'The downloaded file is not a valid ZIP package.'));
        }
        sbi_validate_zip($zip);
        if (!mkdir($extractDirectory, 0700, true) || !$zip->extractTo($extractDirectory)) {
            $zip->close();
            throw new RuntimeException(sbi_t('安装包解压失败。', 'Unable to extract the package.'));
        }
        $zip->close();

        $roots = glob($extractDirectory . '/*', GLOB_ONLYDIR) ?: [];
        if (count($roots) !== 1) {
            throw new RuntimeException(sbi_t('安装包目录结构无效。', 'The package directory structure is invalid.'));
        }
        $source = $roots[0];
        foreach (['index.php', 'install.php', 'assets/index.css', 'assets/index.js', 'assets/admin.css', 'assets/admin.js'] as $required) {
            if (!is_file($source . '/' . $required)) {
                throw new RuntimeException(sbi_t('安装包缺少必要文件：', 'The package is missing a required file: ') . $required);
            }
        }
        $indexCode = (string)file_get_contents($source . '/index.php');
        if (!preg_match("/const APP_VERSION = '([^']+)'/", $indexCode, $match)) {
            throw new RuntimeException(sbi_t('无法识别安装包版本。', 'Unable to identify the package version.'));
        }
        $packageVersion = ltrim((string)$match[1], 'vV');
        $releaseVersion = ltrim((string)$release['version'], 'vV');
        if ($packageVersion !== $releaseVersion) {
            throw new RuntimeException(sbi_t('安装包版本与 Release 信息不一致。', 'The package version does not match the Release metadata.'));
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
            if ($relative === basename(__FILE__)) {
                continue;
            }
            $files[$relative] = $item->getPathname();
        }
        foreach ($files as $relative => $_sourceFile) {
            $target = __DIR__ . '/' . $relative;
            if (file_exists($target)) {
                throw new RuntimeException(sbi_t('目标目录存在同名文件，已停止以避免覆盖：', 'A file with the same name already exists. Deployment stopped to avoid overwriting it: ') . $relative);
            }
        }

        foreach ($files as $relative => $sourceFile) {
            $target = __DIR__ . '/' . $relative;
            sbi_create_directory(dirname($target), $createdDirectories);
            $createdFiles[] = $target;
            if (!copy($sourceFile, $target)) {
                throw new RuntimeException(sbi_t('写入文件失败：', 'Unable to write file: ') . $relative);
            }
        }
        foreach (['data', 'cache', 'uploads'] as $runtimeDirectory) {
            sbi_create_directory(__DIR__ . '/' . $runtimeDirectory, $createdDirectories);
        }
        return count($createdFiles);
    } catch (Throwable $exception) {
        foreach (array_reverse($createdFiles) as $file) {
            @unlink($file);
        }
        foreach (array_reverse(array_unique($createdDirectories)) as $directory) {
            @rmdir($directory);
        }
        throw $exception;
    } finally {
        sbi_remove_tree($workDirectory);
    }
}

$checks = sbi_environment_checks();
$environmentReady = sbi_environment_ready($checks);
$appState = sbi_app_state();
$release = null;
$error = '';
$completed = isset($_GET['done']) && is_array($_SESSION['sbi_completed'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(sbi_csrf_token(), (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException(sbi_t('请求已失效，请刷新页面后重试。', 'This request has expired. Refresh the page and try again.'));
        }
        if (!$environmentReady) {
            throw new RuntimeException(sbi_t('服务器环境尚未满足安装要求。', 'The server does not meet the installation requirements yet.'));
        }
        $release = sbi_latest_release();
        $fileCount = sbi_deploy_release($release);
        $_SESSION['sbi_completed'] = ['version' => $release['version'], 'files' => $fileCount];
        header('Location: ' . sbi_localized_url(basename(sbi_self_url()), ['done' => '1']), true, 303);
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $appState = sbi_app_state();
    }
}

if ($appState === 'empty' && $environmentReady && !$completed && $release === null) {
    try {
        $release = sbi_latest_release();
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$completedData = is_array($_SESSION['sbi_completed'] ?? null) ? $_SESSION['sbi_completed'] : [];
$readyToDownload = $appState === 'empty' && $environmentReady && is_array($release) && $error === '';
$statusTitle = sbi_t('准备部署', 'Ready to deploy');
$statusText = sbi_t('检查通过后，安装器会下载最新稳定版并写入当前目录。', 'After the checks pass, the installer will download the latest stable release into this directory.');
if ($completed) {
    $statusTitle = sbi_t('程序文件已部署', 'Application files deployed');
    $statusText = sbi_t('下一步将初始化站点、管理员账号和 SQLite 数据库。', 'Next, set up the site, administrator account, and SQLite database.');
} elseif ($appState === 'installed') {
    $statusTitle = sbi_t('站点已经安装', 'Site already installed');
    $statusText = sbi_t('检测到安装锁和有效程序入口，无需重复部署。', 'An installation lock and valid application entry point were found. No redeployment is needed.');
} elseif ($appState === 'deployed') {
    $statusTitle = sbi_t('程序文件已就绪', 'Application files ready');
    $statusText = sbi_t('当前目录已经有 SBlog，请继续完成站点初始化。', 'SBlog is already deployed in this directory. Continue with site setup.');
} elseif (!$environmentReady) {
    $statusTitle = sbi_t('环境需要调整', 'Environment needs attention');
    $statusText = sbi_t('修复下方未通过项目并刷新页面后即可继续。', 'Resolve the failed checks below, then refresh the page to continue.');
} elseif ($error !== '') {
    $statusTitle = sbi_t('暂时无法部署', 'Unable to deploy');
    $statusText = sbi_t('没有写入站点文件，可以修复问题后安全重试。', 'No application files were written. Resolve the issue and try again safely.');
}
?>
<!doctype html>
<html lang="<?= sbi_h(sbi_locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= sbi_h(sbi_t('SBlog 安装器', 'SBlog Installer')) ?></title>
  <style>
    :root {
      --bg: #f4f5f3;
      --surface: #ffffff;
      --surface-soft: #f8f9f7;
      --text: #18201d;
      --muted: #65716b;
      --line: #dce1de;
      --line-strong: #c7cfca;
      --accent: #176b4d;
      --accent-hover: #105b40;
      --accent-soft: #e7f3ed;
      --danger: #b42318;
      --danger-soft: #fff1ef;
      --shadow: 0 14px 38px rgba(26, 39, 33, .08);
      --radius: 8px;
      --font: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-width: 320px;
      min-height: 100vh;
      background: var(--bg);
      color: var(--text);
      font: 15px/1.6 var(--font);
      letter-spacing: 0;
      -webkit-font-smoothing: antialiased;
    }
    button, a { -webkit-tap-highlight-color: transparent; }
    .page { width: min(100% - 32px, 980px); margin: 0 auto; padding: 42px 0; }
    .masthead { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 30px; }
    .brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .brand-mark {
      display: grid;
      width: 38px;
      height: 38px;
      place-items: center;
      border-radius: var(--radius);
      background: var(--text);
      color: #fff;
      font-weight: 750;
      font-size: 18px;
    }
    .brand-copy { display: grid; line-height: 1.25; }
    .brand-copy strong { font-size: 15px; }
    .brand-copy span { margin-top: 3px; color: var(--muted); font-size: 12px; }
    .masthead-actions { display: flex; align-items: center; gap: 16px; }
    .secure-label { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 13px; white-space: nowrap; }
    .secure-label::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
    .language-switch { display: inline-grid; grid-template-columns: repeat(2, minmax(64px, 1fr)); padding: 3px; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); }
    .language-switch a { display: grid; min-height: 40px; place-items: center; padding: 0 10px; border-radius: 5px; color: var(--muted); font-size: 12px; font-weight: 700; text-decoration: none; transition: background-color .16s ease, color .16s ease, box-shadow .16s ease, transform .16s ease; }
    .language-switch a:hover { color: var(--text); }
    .language-switch a:active { transform: scale(.96); }
    .language-switch a:focus-visible { outline: 3px solid rgba(23, 107, 77, .24); outline-offset: 1px; }
    .language-switch a.is-active { background: var(--surface-soft); color: var(--text); box-shadow: 0 1px 3px rgba(26, 39, 33, .1); }
    .installer-shell { overflow: hidden; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow); }
    .intro { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 32px; align-items: end; padding: 38px 40px 32px; border-bottom: 1px solid var(--line); }
    .eyebrow { margin: 0 0 11px; color: var(--accent); font-size: 12px; font-weight: 750; text-transform: uppercase; }
    h1 { margin: 0; font-size: 40px; line-height: 1.15; font-weight: 720; letter-spacing: 0; text-wrap: balance; }
    .lead { max-width: 610px; margin: 14px 0 0; color: var(--muted); font-size: 16px; }
    .version-box { min-width: 126px; padding-left: 22px; border-left: 1px solid var(--line); }
    .version-box span { display: block; color: var(--muted); font-size: 12px; }
    .version-box strong { display: block; margin-top: 4px; font-size: 21px; font-variant-numeric: tabular-nums; }
    .content { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(260px, .65fr); }
    .checks { padding: 30px 40px 34px; border-right: 1px solid var(--line); }
    .section-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; margin-bottom: 17px; }
    h2 { margin: 0; font-size: 16px; line-height: 1.35; text-wrap: balance; }
    .section-heading span { color: var(--muted); font-size: 12px; }
    .check-list { margin: 0; padding: 0; list-style: none; border-top: 1px solid var(--line); }
    .check-item { display: grid; grid-template-columns: 22px minmax(0, 1fr) auto; gap: 11px; align-items: center; min-height: 50px; border-bottom: 1px solid var(--line); }
    .check-icon { display: grid; width: 18px; height: 18px; place-items: center; border-radius: 50%; background: var(--accent-soft); color: var(--accent); font-size: 11px; font-weight: 800; }
    .check-item.is-error .check-icon { background: var(--danger-soft); color: var(--danger); }
    .check-label { min-width: 0; font-weight: 600; }
    .check-detail { max-width: 145px; overflow: hidden; color: var(--muted); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
    .action-panel { display: flex; min-width: 0; flex-direction: column; justify-content: space-between; gap: 28px; padding: 30px; background: var(--surface-soft); }
    .status-mark { display: grid; width: 34px; height: 34px; margin-bottom: 17px; place-items: center; border: 1px solid #b8d6c8; border-radius: var(--radius); background: var(--accent-soft); color: var(--accent); font-size: 16px; font-weight: 800; }
    .status-mark.is-error { border-color: #efc4bf; background: var(--danger-soft); color: var(--danger); }
    .status-copy h2 { font-size: 19px; }
    .status-copy p { margin: 9px 0 0; color: var(--muted); font-size: 13px; }
    .alert { margin-top: 17px; padding: 11px 12px; border: 1px solid #efc4bf; border-radius: var(--radius); background: var(--danger-soft); color: var(--danger); font-size: 12px; overflow-wrap: anywhere; }
    .button {
      display: inline-flex;
      width: 100%;
      min-height: 44px;
      align-items: center;
      justify-content: center;
      gap: 9px;
      border: 1px solid var(--accent);
      border-radius: var(--radius);
      background: var(--accent);
      color: #fff;
      cursor: pointer;
      font: 700 14px/1 var(--font);
      text-decoration: none;
      transition: background .16s ease, border-color .16s ease, transform .16s ease;
    }
    .button:hover { border-color: var(--accent-hover); background: var(--accent-hover); }
    .button:active { transform: scale(.96); }
    .button:focus-visible { outline: 3px solid rgba(23, 107, 77, .24); outline-offset: 2px; }
    .button:disabled { border-color: var(--line-strong); background: #d5dad7; color: #77817c; cursor: not-allowed; }
    .button.is-loading { cursor: wait; }
    .spinner { display: none; width: 15px; height: 15px; border: 2px solid rgba(255,255,255,.42); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
    .button.is-loading .spinner { display: inline-block; }
    .action-note { margin: 11px 0 0; color: var(--muted); font-size: 11px; text-align: center; }
    .footer { display: flex; justify-content: space-between; gap: 20px; margin-top: 17px; color: var(--muted); font-size: 12px; }
    .footer code { color: var(--text); font: inherit; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; } }
    @media (max-width: 720px) {
      .page { width: min(100% - 24px, 560px); padding: 22px 0; }
      .masthead { margin-bottom: 20px; }
      .secure-label { display: none; }
      .intro { grid-template-columns: 1fr; gap: 22px; padding: 28px 24px 24px; }
      h1 { font-size: 30px; }
      .version-box { padding: 0; border: 0; }
      .version-box span, .version-box strong { display: inline; }
      .version-box strong { margin-left: 7px; font-size: 16px; }
      .content { grid-template-columns: 1fr; }
      .checks { padding: 24px; border-right: 0; border-bottom: 1px solid var(--line); }
      .action-panel { padding: 24px; }
      .footer { display: block; }
      .footer span { display: block; margin-top: 4px; }
    }
    @media (max-width: 420px) {
      .masthead { align-items: flex-start; flex-direction: column; }
      .masthead-actions, .language-switch { width: 100%; }
      .check-item { grid-template-columns: 22px minmax(0, 1fr); padding: 10px 0; }
      .check-detail { grid-column: 2; max-width: 100%; white-space: normal; }
    }
  </style>
</head>
<body>
  <main class="page">
    <header class="masthead">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true">S</span>
        <span class="brand-copy"><strong>Simple PHP Blog</strong><span><?= sbi_h(sbi_t('单文件部署工具', 'Single-file deployment tool')) ?></span></span>
      </div>
      <div class="masthead-actions">
        <span class="secure-label"><?= sbi_h(sbi_t('仅从官方 GitHub Release 获取', 'Official GitHub Releases only')) ?></span>
        <nav class="language-switch" aria-label="<?= sbi_h(sbi_t('安装语言', 'Installer language')) ?>">
          <a href="<?= sbi_h(sbi_url(basename(sbi_self_url())) . '?' . http_build_query(array_filter(['lang' => 'zh-CN', 'done' => $completed ? '1' : null]))) ?>" hreflang="zh-CN"<?= sbi_locale() === 'zh-CN' ? ' class="is-active" aria-current="page"' : '' ?>>中文</a>
          <a href="<?= sbi_h(sbi_url(basename(sbi_self_url())) . '?' . http_build_query(array_filter(['lang' => 'en', 'done' => $completed ? '1' : null]))) ?>" hreflang="en"<?= sbi_locale() === 'en' ? ' class="is-active" aria-current="page"' : '' ?>>English</a>
        </nav>
      </div>
    </header>

    <section class="installer-shell" aria-labelledby="installer-title">
      <div class="intro">
        <div>
          <p class="eyebrow">SBlog Installer</p>
          <h1 id="installer-title"><?= sbi_h($statusTitle) ?></h1>
          <p class="lead"><?= sbi_h($statusText) ?></p>
        </div>
        <div class="version-box">
          <span><?= sbi_h($completed || $appState !== 'empty' ? sbi_t('部署状态', 'Deployment status') : sbi_t('最新稳定版', 'Latest stable release')) ?></span>
          <strong><?= sbi_h((string)($completedData['version'] ?? $release['version'] ?? ($appState === 'empty' ? sbi_t('待获取', 'Pending') : sbi_t('已就绪', 'Ready')))) ?></strong>
        </div>
      </div>

      <div class="content">
        <section class="checks" aria-labelledby="checks-title">
          <div class="section-heading">
            <h2 id="checks-title"><?= sbi_h(sbi_t('服务器检查', 'Server checks')) ?></h2>
            <span><?= sbi_h($environmentReady ? sbi_t('全部通过', 'All checks passed') : sbi_t('存在未通过项目', 'Some checks failed')) ?></span>
          </div>
          <ul class="check-list">
            <?php foreach ($checks as $check): ?>
              <li class="check-item<?= $check['ok'] ? '' : ' is-error' ?>">
                <span class="check-icon" aria-hidden="true"><?= $check['ok'] ? '✓' : '!' ?></span>
                <span class="check-label"><?= sbi_h((string)$check['label']) ?></span>
                <span class="check-detail" title="<?= sbi_h((string)$check['detail']) ?>"><?= sbi_h((string)$check['detail']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <aside class="action-panel" aria-live="polite">
          <div class="status-copy">
            <span class="status-mark<?= $error !== '' || !$environmentReady ? ' is-error' : '' ?>" aria-hidden="true"><?= $error !== '' || !$environmentReady ? '!' : '✓' ?></span>
            <h2><?= sbi_h($statusTitle) ?></h2>
            <p><?= sbi_h($statusText) ?></p>
            <?php if ($error !== ''): ?><div class="alert" role="alert"><?= sbi_h($error) ?></div><?php endif; ?>
          </div>

          <div>
            <?php if ($completed || $appState === 'deployed'): ?>
              <a class="button" href="<?= sbi_h(sbi_localized_url('install.php')) ?>"><?= sbi_h(sbi_t('继续初始化站点', 'Continue site setup')) ?></a>
              <p class="action-note"><?= sbi_h(sbi_t('部署器完成使命后请从服务器删除', 'Delete this deployer from the server after setup')) ?></p>
            <?php elseif ($appState === 'installed'): ?>
              <a class="button" href="<?= sbi_h(sbi_url('index.php')) ?>"><?= sbi_h(sbi_t('进入博客', 'Open blog')) ?></a>
              <p class="action-note"><?= sbi_h(sbi_t('当前站点数据不会被修改', 'Existing site data will not be changed')) ?></p>
            <?php elseif ($error !== '' && $environmentReady): ?>
              <a class="button" href="<?= sbi_h(sbi_localized_url(basename(sbi_self_url()))) ?>"><?= sbi_h(sbi_t('重新检测', 'Check again')) ?></a>
              <p class="action-note"><?= sbi_h(sbi_t('修复提示的问题后可以安全重试', 'Resolve the issue, then retry safely')) ?></p>
            <?php else: ?>
              <form id="install-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= sbi_h(sbi_csrf_token()) ?>">
                <input type="hidden" name="lang" value="<?= sbi_h(sbi_locale()) ?>">
                <button id="install-button" class="button" type="submit"<?= $readyToDownload ? '' : ' disabled' ?>>
                  <span class="spinner" aria-hidden="true"></span>
                  <span class="button-label"><?= sbi_h($environmentReady ? sbi_t('下载并部署', 'Download and deploy') : sbi_t('环境未通过', 'Checks failed')) ?></span>
                </button>
              </form>
              <p class="action-note"><?= sbi_h(sbi_t('不会覆盖同名文件，失败将自动回滚', 'Existing files are never overwritten; failed deployments roll back')) ?></p>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </section>

    <footer class="footer">
      <span><?= sbi_h(sbi_t('目标目录', 'Target directory')) ?> <code><?= sbi_h(__DIR__) ?></code></span>
      <span><?= sbi_h(sbi_t('发布源', 'Release source')) ?> <?= sbi_h(SBI_REPOSITORY) ?></span>
    </footer>
  </main>
  <script>
    (() => {
      const form = document.getElementById('install-form');
      const button = document.getElementById('install-button');
      if (!form || !button) return;
      form.addEventListener('submit', () => {
        button.disabled = true;
        button.classList.add('is-loading');
        button.querySelector('.button-label').textContent = <?= json_encode(sbi_t('正在下载并部署…', 'Downloading and deploying…'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      });
    })();
  </script>
</body>
</html>
