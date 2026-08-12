<?php
declare(strict_types=1);

function sblog_akismet_defaults(): array
{
    return [
        'enabled' => '0',
        'api_key' => '',
        'checked_count' => '0',
        'blocked_count' => '0',
        'error_count' => '0',
        'last_result' => '',
        'last_checked_at' => '0',
    ];
}

function sblog_akismet_ensure_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    db()->exec(
        "CREATE TABLE IF NOT EXISTS akismet_settings(
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )"
    );
    $done = true;
}

function sblog_akismet_settings(): array
{
    $settings = sblog_akismet_defaults();
    try {
        sblog_akismet_ensure_table();
        foreach (all_rows('SELECT name, value FROM akismet_settings') as $row) {
            $settings[(string)$row['name']] = (string)$row['value'];
        }
    } catch (Throwable $exception) {
        error_log('Akismet settings load failed: ' . $exception->getMessage());
    }
    return $settings;
}

function sblog_akismet_save_settings(array $values): void
{
    sblog_akismet_ensure_table();
    $statement = db()->prepare('INSERT OR REPLACE INTO akismet_settings(name, value) VALUES(?, ?)');
    foreach ($values as $name => $value) {
        $statement->execute([(string)$name, (string)$value]);
    }
}

function sblog_akismet_record_result(string $result): void
{
    sblog_akismet_ensure_table();
    $database = db();
    $database->prepare('INSERT OR REPLACE INTO akismet_settings(name, value) VALUES(?, ?)')
        ->execute(['last_result', $result]);
    $database->prepare('INSERT OR REPLACE INTO akismet_settings(name, value) VALUES(?, ?)')
        ->execute(['last_checked_at', (string)time()]);
    $counter = match ($result) {
        'spam' => ['checked_count', 'blocked_count'],
        'ham' => ['checked_count'],
        'error' => ['error_count'],
        default => [],
    };
    $statement = $database->prepare(
        "INSERT INTO akismet_settings(name, value) VALUES(?, '1')
         ON CONFLICT(name) DO UPDATE SET value = CAST(CAST(value AS INTEGER) + 1 AS TEXT)"
    );
    foreach ($counter as $name) {
        $statement->execute([$name]);
    }
}

function sblog_akismet_request(string $url, array $payload): array
{
    if (!function_exists('curl_init')) {
        return [false, '', sblog_t('服务器缺少 cURL 扩展。')];
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent: SimplePHPBlog/1.0 | Akismet/1.0.0',
        ],
        CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
    ]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false) {
        return [false, '', $error !== '' ? $error : '连接失败。'];
    }
    if ($status < 200 || $status >= 300) {
        return [false, '', 'HTTP ' . $status];
    }
    return [true, trim((string)$body), ''];
}

function sblog_akismet_verify_key(string $apiKey): array
{
    [$ok, $result, $error] = sblog_akismet_request('https://rest.akismet.com/1.1/verify-key', [
        'key' => $apiKey,
        'blog' => site_root_url(),
    ]);
    if (!$ok) {
        return [false, sblog_t('无法连接 Akismet：{error}', ['error' => $error])];
    }
    if ($result !== 'valid') {
        return [false, $result === 'invalid' ? sblog_t('Akismet API Key 无效。') : sblog_t('Akismet 返回了无法识别的验证结果。')];
    }
    return [true, ''];
}

function sblog_akismet_comment_is_spam(array $context, string $apiKey): array
{
    $comment = is_array($context['comment'] ?? null) ? $context['comment'] : [];
    $post = is_array($context['post'] ?? null) ? $context['post'] : [];
    $permalink = isset($post['slug']) ? content_permalink($post) : site_root_url();
    $payload = [
        'blog' => site_root_url(),
        'user_ip' => client_ip_address(),
        'user_agent' => str_sub_u((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'referrer' => str_sub_u((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 1000),
        'permalink' => $permalink,
        'comment_type' => 'comment',
        'comment_author' => (string)($comment['author_name'] ?? ''),
        'comment_author_email' => (string)($comment['author_email'] ?? ''),
        'comment_author_url' => (string)($comment['author_url'] ?? ''),
        'comment_content' => (string)($comment['content'] ?? ''),
        'blog_lang' => sblog_i18n_locale(),
    ];
    [$ok, $result, $error] = sblog_akismet_request(
        'https://' . rawurlencode($apiKey) . '.rest.akismet.com/1.1/comment-check',
        $payload
    );
    if (!$ok) {
        return [false, false, $error];
    }
    if ($result !== 'true' && $result !== 'false') {
        return [false, false, sblog_t('Akismet 返回了无法识别的检测结果。')];
    }
    return [true, $result === 'true', ''];
}

function sblog_akismet_result_label(array $settings): string
{
    return match ((string)$settings['last_result']) {
        'spam' => sblog_t('最近一次：已拦截垃圾评论'),
        'ham' => sblog_t('最近一次：评论正常'),
        'error' => sblog_t('最近一次：服务异常，评论已放行'),
        default => sblog_t('尚未检查评论'),
    };
}

function sblog_akismet_render_settings(): void
{
    require_admin();
    $settings = sblog_akismet_settings();
    $lastCheckedAt = (int)$settings['last_checked_at'];
    ob_start(); ?>
    <div class="admin-shell"><?= render_admin_sidebar('plugins') ?><div class="admin-main"><?= render_admin_topbar(sblog_t('Akismet 垃圾评论拦截')) ?>
      <section class="panel admin-list-panel"><div class="panel__header"><h2><?= h(sblog_t('Akismet 设置')) ?></h2><p class="panel__meta"><?= h(sblog_t('垃圾评论会在写入数据库前被拦截；服务不可用时自动放行，避免误伤正常评论。')) ?></p></div><div class="panel__body">
        <form class="form-stack" method="post" action="<?= h(script_url() . '?a=save_akismet_settings') ?>"><?= csrf_field() ?>
          <label class="setting-option"><input name="enabled" type="checkbox" value="1"<?= $settings['enabled'] === '1' ? ' checked' : '' ?>><span><strong><?= h(sblog_t('启用 Akismet 检测')) ?></strong><small><?= h(sblog_t('登录管理员发布的评论不会送检。')) ?></small></span></label>
          <div class="field"><label for="akismet_api_key"><?= h(sblog_t('API Key')) ?></label><input id="akismet_api_key" name="api_key" type="password" value="" placeholder="<?= h($settings['api_key'] !== '' ? sblog_t('已保存，留空则不修改') : sblog_t('输入 Akismet API Key')) ?>" autocomplete="new-password"><p class="field-hint"><?= h(sblog_t('启用时会先在线验证密钥；密钥仅保存在服务器 SQLite 数据库中。')) ?></p></div>
          <div class="action-row"><button class="button"><?= h(sblog_t('保存并验证')) ?></button></div>
        </form>
      </div></section>
      <section class="panel admin-list-panel"><div class="panel__header"><h2><?= h(sblog_t('拦截统计')) ?></h2><p class="panel__meta"><?= h(sblog_akismet_result_label($settings)) ?><?= $lastCheckedAt > 0 ? ' · ' . h(pretty_date($lastCheckedAt, true)) : '' ?></p></div><div class="panel__body">
        <div class="field-grid"><div><strong><?= (int)$settings['checked_count'] ?></strong><p class="field-hint"><?= h(sblog_t('已检查')) ?></p></div><div><strong><?= (int)$settings['blocked_count'] ?></strong><p class="field-hint"><?= h(sblog_t('已拦截')) ?></p></div><div><strong><?= (int)$settings['error_count'] ?></strong><p class="field-hint"><?= h(sblog_t('服务异常')) ?></p></div></div>
      </div></section>
    </div></div><?php
    render_layout(sblog_t('Akismet 垃圾评论拦截'), (string)ob_get_clean(), ['active' => 'plugins', 'wide' => true, 'description' => sblog_t('Akismet 垃圾评论拦截设置')]);
}

function sblog_akismet_handle_request(array $context): void
{
    $action = (string)($context['action'] ?? '');
    if ($action === 'admin_akismet') {
        sblog_akismet_render_settings();
        exit;
    }
    if ($action !== 'save_akismet_settings') {
        return;
    }
    require_admin_post(script_url() . '?a=admin_akismet');
    $current = sblog_akismet_settings();
    $enabled = isset($_POST['enabled']) ? '1' : '0';
    $newApiKey = trim((string)($_POST['api_key'] ?? ''));
    $apiKey = $newApiKey !== '' ? $newApiKey : (string)$current['api_key'];
    if ($newApiKey !== '' && !preg_match('/^[A-Za-z0-9]{6,64}$/', $newApiKey)) {
        set_flash('error', sblog_t('API Key 格式不正确。'));
        redirect_to(script_url() . '?a=admin_akismet');
    }
    if ($enabled === '1') {
        if ($apiKey === '') {
            set_flash('error', sblog_t('启用 Akismet 前请填写 API Key。'));
            redirect_to(script_url() . '?a=admin_akismet');
        }
        [$valid, $error] = sblog_akismet_verify_key($apiKey);
        if (!$valid) {
            set_flash('error', $error);
            redirect_to(script_url() . '?a=admin_akismet');
        }
    }
    $values = ['enabled' => $enabled];
    if ($newApiKey !== '') {
        $values['api_key'] = $newApiKey;
    }
    sblog_akismet_save_settings($values);
    set_flash('success', $enabled === '1' ? sblog_t('Akismet 已启用，API Key 验证成功。') : sblog_t('Akismet 设置已保存。'));
    redirect_to(script_url() . '?a=admin_akismet');
}

add_plugin_filter('comment_submission_allowed', static function (bool $allowed, array $context): bool {
    if (!$allowed || ($context['authenticated'] ?? false) === true) {
        return $allowed;
    }
    $settings = sblog_akismet_settings();
    $apiKey = trim((string)$settings['api_key']);
    if ($settings['enabled'] !== '1' || $apiKey === '') {
        return true;
    }
    [$ok, $isSpam, $error] = sblog_akismet_comment_is_spam($context, $apiKey);
    if (!$ok) {
        sblog_akismet_record_result('error');
        error_log('Akismet comment check failed: ' . $error);
        return true;
    }
    sblog_akismet_record_result($isSpam ? 'spam' : 'ham');
    return !$isSpam;
});

add_plugin_action('request', 'sblog_akismet_handle_request');
