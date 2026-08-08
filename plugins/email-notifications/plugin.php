<?php
declare(strict_types=1);

function sblog_mail_defaults(): array
{
    return [
        'smtp_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => '465',
        'smtp_encryption' => 'ssl',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => '',
        'smtp_notify_email' => '',
    ];
}

function sblog_mail_settings(): array
{
    $settings = sblog_mail_defaults();
    try {
        foreach (all_rows('SELECT name, value FROM mail_settings') as $row) {
            $settings[(string)$row['name']] = (string)$row['value'];
        }
    } catch (Throwable) {
    }
    return $settings;
}

function sblog_mail_save_settings(array $values): void
{
    $statement = db()->prepare('INSERT OR REPLACE INTO mail_settings(name, value) VALUES(?, ?)');
    foreach ($values as $name => $value) {
        $statement->execute([(string)$name, (string)$value]);
    }
}

function sblog_mail_header_value(string $value): string
{
    return trim((string)preg_replace('/[\r\n]+/', ' ', $value));
}

function sblog_mail_address_header(string $email, string $name = ''): string
{
    $email = sblog_mail_header_value($email);
    $name = sblog_mail_header_value($name);
    return $name === '' ? '<' . $email . '>' : '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
}

function sblog_mail_smtp_read_response($socket): int
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (preg_match('/^\d{3} /', $line)) {
            break;
        }
    }
    return (int)substr($response, 0, 3);
}

function sblog_mail_smtp_expect($socket, array $accepted, string $command = ''): bool
{
    if ($command !== '' && fwrite($socket, $command . "\r\n") === false) {
        return false;
    }
    return in_array(sblog_mail_smtp_read_response($socket), $accepted, true);
}

function sblog_mail_smtp_send(string $to, string $subject, string $body, array $settings): bool
{
    $host = trim((string)$settings['smtp_host']);
    $port = (int)$settings['smtp_port'];
    $encryption = (string)$settings['smtp_encryption'];
    $username = trim((string)$settings['smtp_username']);
    $password = (string)$settings['smtp_password'];
    $fromEmail = trim((string)$settings['smtp_from_email']);
    $fromName = trim((string)$settings['smtp_from_name']);
    if ($host === '' || $port < 1 || $port > 65535 || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
    $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) {
        return false;
    }
    stream_set_timeout($socket, 20);
    try {
        if (!sblog_mail_smtp_expect($socket, [220])) { return false; }
        $serverName = (string)(parse_url(site_root_url(), PHP_URL_HOST) ?: 'localhost');
        if (!sblog_mail_smtp_expect($socket, [250], 'EHLO ' . $serverName)) { return false; }
        if ($encryption === 'tls') {
            if (!sblog_mail_smtp_expect($socket, [220], 'STARTTLS')) { return false; }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { return false; }
            if (!sblog_mail_smtp_expect($socket, [250], 'EHLO ' . $serverName)) { return false; }
        }
        if ($username !== '') {
            if (!sblog_mail_smtp_expect($socket, [334], 'AUTH LOGIN')) { return false; }
            if (!sblog_mail_smtp_expect($socket, [334], base64_encode($username))) { return false; }
            if (!sblog_mail_smtp_expect($socket, [235], base64_encode($password))) { return false; }
        }
        if (!sblog_mail_smtp_expect($socket, [250], 'MAIL FROM:<' . $fromEmail . '>')) { return false; }
        if (!sblog_mail_smtp_expect($socket, [250, 251], 'RCPT TO:<' . $to . '>')) { return false; }
        if (!sblog_mail_smtp_expect($socket, [354], 'DATA')) { return false; }

        $message = implode("\n", [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . sblog_mail_address_header($fromEmail, $fromName !== '' ? $fromName : setting('site_name', default_settings()['site_name'])),
            'To: ' . sblog_mail_address_header($to),
            'Subject: =?UTF-8?B?' . base64_encode(sblog_mail_header_value($subject)) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            str_replace(["\r\n", "\r"], "\n", $body),
        ]);
        $message = preg_replace('/^\./m', '..', $message);
        if (fwrite($socket, str_replace("\n", "\r\n", (string)$message) . "\r\n.\r\n") === false) { return false; }
        $ok = sblog_mail_smtp_expect($socket, [250]);
        sblog_mail_smtp_expect($socket, [221], 'QUIT');
        return $ok;
    } finally {
        fclose($socket);
    }
}

function sblog_mail_native_send(string $to, string $subject, string $body, array $settings): bool
{
    if (!function_exists('mail')) {
        return false;
    }
    $headers = ['MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8'];
    $fromEmail = trim((string)$settings['smtp_from_email']);
    if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromName = trim((string)$settings['smtp_from_name']);
        $headers[] = 'From: ' . sblog_mail_address_header($fromEmail, $fromName !== '' ? $fromName : setting('site_name', default_settings()['site_name']));
    }
    $encodedSubject = '=?UTF-8?B?' . base64_encode(sblog_mail_header_value($subject)) . '?=';
    return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
}

function sblog_mail_render_settings(): void
{
    require_admin();
    $settings = sblog_mail_settings();
    $encryption = (string)$settings['smtp_encryption'];
    ob_start(); ?>
    <div class="admin-shell"><?= render_admin_sidebar('plugins') ?><div class="admin-main"><?= render_admin_topbar('邮件通知') ?>
      <section class="panel admin-list-panel"><div class="panel__header"><h2>SMTP 设置</h2><p class="panel__meta">启用 SMTP 后优先通过 SMTP 发送；关闭时回退到服务器 PHP mail。</p></div><div class="panel__body">
        <form class="form-stack" method="post" action="<?= h(url_for('save_mail_settings')) ?>"><?= csrf_field() ?>
          <label class="setting-option"><input name="smtp_enabled" type="checkbox" value="1"<?= $settings['smtp_enabled'] === '1' ? ' checked' : '' ?>><span>启用 SMTP 邮件通知</span></label>
          <div class="field-grid"><div class="field"><label for="smtp_host">SMTP 主机</label><input id="smtp_host" name="smtp_host" value="<?= h((string)$settings['smtp_host']) ?>" placeholder="smtp.example.com" maxlength="255"></div><div class="field"><label for="smtp_port">端口</label><input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" value="<?= h((string)$settings['smtp_port']) ?>" placeholder="465"></div></div>
          <div class="field-grid"><div class="field"><label for="smtp_encryption">加密方式</label><select id="smtp_encryption" name="smtp_encryption"><option value="ssl"<?= $encryption === 'ssl' ? ' selected' : '' ?>>SSL</option><option value="tls"<?= $encryption === 'tls' ? ' selected' : '' ?>>TLS</option><option value="none"<?= $encryption === 'none' ? ' selected' : '' ?>>无</option></select></div><div class="field"><label for="smtp_username">SMTP 账号</label><input id="smtp_username" name="smtp_username" value="<?= h((string)$settings['smtp_username']) ?>" maxlength="255" autocomplete="username"></div></div>
          <div class="field"><label for="smtp_password">SMTP 密码</label><input id="smtp_password" name="smtp_password" type="password" value="" placeholder="<?= $settings['smtp_password'] !== '' ? '已保存，留空则不修改' : '授权码或密码' ?>" autocomplete="new-password"></div>
          <div class="field-grid"><div class="field"><label for="smtp_from_email">发件邮箱</label><input id="smtp_from_email" name="smtp_from_email" type="email" value="<?= h((string)$settings['smtp_from_email']) ?>" maxlength="160" placeholder="noreply@example.com"></div><div class="field"><label for="smtp_from_name">发件名称</label><input id="smtp_from_name" name="smtp_from_name" value="<?= h((string)$settings['smtp_from_name']) ?>" maxlength="120" placeholder="<?= h(setting('site_name', default_settings()['site_name'])) ?>"></div></div>
          <div class="field"><label for="smtp_notify_email">通知收件邮箱</label><input id="smtp_notify_email" name="smtp_notify_email" type="email" value="<?= h((string)$settings['smtp_notify_email']) ?>" maxlength="160" placeholder="admin@example.com"><p class="field-hint">留空时使用管理员账号邮箱作为评论通知收件人。</p></div>
          <div class="action-row"><button class="button">保存邮件设置</button></div>
        </form>
      </div></section>
    </div></div><?php
    render_layout('邮件通知', (string)ob_get_clean(), ['active' => 'plugins', 'wide' => true, 'description' => 'SMTP 邮件通知设置']);
}

function sblog_mail_handle_request(array $context): void
{
    $action = (string)($context['action'] ?? '');
    if ($action === 'admin_mail') {
        sblog_mail_render_settings();
        exit;
    }
    if ($action !== 'save_mail_settings') {
        return;
    }
    require_admin_post(url_for('admin_mail'));
    $enabled = isset($_POST['smtp_enabled']) ? '1' : '0';
    $host = trim((string)($_POST['smtp_host'] ?? ''));
    $port = max(1, min(65535, (int)($_POST['smtp_port'] ?? 465)));
    $encryption = (string)($_POST['smtp_encryption'] ?? 'ssl');
    if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) {
        $encryption = 'ssl';
    }
    $username = trim((string)($_POST['smtp_username'] ?? ''));
    $password = trim((string)($_POST['smtp_password'] ?? ''));
    $fromEmail = str_lower_u(trim((string)($_POST['smtp_from_email'] ?? '')));
    $fromName = trim((string)($_POST['smtp_from_name'] ?? ''));
    $notifyEmail = str_lower_u(trim((string)($_POST['smtp_notify_email'] ?? '')));
    if ($enabled === '1' && ($host === '' || $fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL))) {
        set_flash('error', '启用 SMTP 时，请填写 SMTP 主机和有效的发件邮箱。');
        redirect_to(url_for('admin_mail'));
    }
    if ($notifyEmail !== '' && !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', '通知收件邮箱格式不正确。');
        redirect_to(url_for('admin_mail'));
    }
    $values = [
        'smtp_enabled' => $enabled,
        'smtp_host' => str_sub_u($host, 0, 255),
        'smtp_port' => (string)$port,
        'smtp_encryption' => $encryption,
        'smtp_username' => str_sub_u($username, 0, 255),
        'smtp_from_email' => str_sub_u($fromEmail, 0, 160),
        'smtp_from_name' => str_sub_u($fromName, 0, 120),
        'smtp_notify_email' => str_sub_u($notifyEmail, 0, 160),
    ];
    if ($password !== '') {
        $values['smtp_password'] = $password;
    }
    sblog_mail_save_settings($values);
    set_flash('success', '邮件通知设置已保存。');
    redirect_to(url_for('admin_mail'));
}

add_plugin_filter('site_mail_send', static function (bool $sent, array $context): bool {
    if ($sent) {
        return true;
    }
    $to = trim((string)($context['recipient'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $settings = sblog_mail_settings();
    $subject = (string)($context['subject'] ?? '');
    $body = (string)($context['body'] ?? '');
    return $settings['smtp_enabled'] === '1'
        ? sblog_mail_smtp_send($to, $subject, $body, $settings)
        : sblog_mail_native_send($to, $subject, $body, $settings);
});

add_plugin_filter('notification_recipient', static function (string $recipient): string {
    $configured = str_lower_u(trim((string)sblog_mail_settings()['smtp_notify_email']));
    return filter_var($configured, FILTER_VALIDATE_EMAIL) ? $configured : $recipient;
});

add_plugin_action('request', 'sblog_mail_handle_request');
