<?php
declare(strict_types=1);

function sblog_s3_defaults(): array
{
    return [
        's3_enabled' => '0',
        's3_keep_local' => '1',
        's3_endpoint' => 'https://s3.amazonaws.com',
        's3_region' => 'us-east-1',
        's3_bucket' => '',
        's3_access_key' => '',
        's3_secret_key' => '',
        's3_path_prefix' => 'uploads',
        's3_public_url' => '',
        's3_path_style' => '0',
    ];
}

function sblog_s3_settings(): array
{
    $settings = sblog_s3_defaults();
    try {
        foreach (all_rows('SELECT name, value FROM s3_settings') as $row) {
            $settings[(string)$row['name']] = (string)$row['value'];
        }
    } catch (Throwable) {
    }
    return $settings;
}

function sblog_s3_save_settings(array $values): void
{
    $statement = db()->prepare('INSERT OR REPLACE INTO s3_settings(name, value) VALUES(?, ?)');
    foreach ($values as $name => $value) {
        $statement->execute([(string)$name, (string)$value]);
    }
}

function sblog_s3_endpoint_parts(string $endpoint): ?array
{
    if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
        return null;
    }
    $parts = parse_url($endpoint);
    if (!is_array($parts)) {
        return null;
    }
    $scheme = str_lower_u((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || trim((string)($parts['host'] ?? '')) === ''
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return null;
    }
    $parts['scheme'] = $scheme;
    return $parts;
}

function sblog_s3_encoded_path(array $segments): string
{
    $segments = array_values(array_filter($segments, static fn(string $segment): bool => $segment !== ''));
    return '/' . implode('/', array_map(static fn(string $segment): string => rawurlencode(rawurldecode($segment)), $segments));
}

function sblog_s3_request_target(array $settings, string $key): ?array
{
    $parts = sblog_s3_endpoint_parts(rtrim(trim((string)$settings['s3_endpoint']), '/'));
    $bucket = trim((string)$settings['s3_bucket']);
    if ($parts === null || $bucket === '') {
        return null;
    }
    $pathStyle = (string)$settings['s3_path_style'] === '1';
    $hostName = trim((string)$parts['host'], '[]');
    if (!$pathStyle) {
        $hostName = $bucket . '.' . $hostName;
    }
    $urlHost = str_contains($hostName, ':') ? '[' . $hostName . ']' : $hostName;
    $host = $urlHost . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    $segments = preg_split('#/+#', trim((string)($parts['path'] ?? ''), '/')) ?: [];
    if ($pathStyle) {
        $segments[] = $bucket;
    }
    $segments = array_merge($segments, preg_split('#/+#', trim($key, '/')) ?: []);
    $uri = sblog_s3_encoded_path($segments);
    $url = $parts['scheme'] . '://' . $host . $uri;
    $publicBase = rtrim(trim((string)$settings['s3_public_url']), '/');
    $publicUrl = $publicBase !== ''
        ? $publicBase . sblog_s3_encoded_path(preg_split('#/+#', trim($key, '/')) ?: [])
        : $url;
    return ['url' => $url, 'public_url' => $publicUrl, 'host' => $host, 'uri' => $uri];
}

function sblog_s3_upload(string $file, string $key, string $mime, array $settings): array
{
    $region = trim((string)$settings['s3_region']);
    $accessKey = trim((string)$settings['s3_access_key']);
    $secretKey = (string)$settings['s3_secret_key'];
    $target = sblog_s3_request_target($settings, $key);
    if ($target === null || $region === '' || $accessKey === '' || $secretKey === '') {
        return [false, '', 'S3 配置不完整。'];
    }
    if (!function_exists('curl_init')) {
        return [false, '', '服务器缺少 cURL 扩展，无法上传到 S3。'];
    }

    $payloadHash = hash_file('sha256', $file);
    $stream = fopen($file, 'rb');
    if ($payloadHash === false || $stream === false) {
        if (is_resource($stream)) { fclose($stream); }
        return [false, '', '无法读取待上传文件。'];
    }
    $amzDate = gmdate('Ymd\THis\Z');
    $dateStamp = gmdate('Ymd');
    $canonicalHeaders = 'content-type:' . $mime . "\n"
        . 'host:' . $target['host'] . "\n"
        . 'x-amz-content-sha256:' . $payloadHash . "\n"
        . 'x-amz-date:' . $amzDate . "\n";
    $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
    $canonicalRequest = "PUT\n" . $target['uri'] . "\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
    $scope = $dateStamp . '/' . $region . '/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
    $dateKey = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
    $regionKey = hash_hmac('sha256', $region, $dateKey, true);
    $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
    $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    $signature = hash_hmac('sha256', $stringToSign, $signingKey);
    $authorization = 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $scope
        . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

    $curl = curl_init((string)$target['url']);
    curl_setopt_array($curl, [
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => $stream,
        CURLOPT_INFILESIZE => filesize($file),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authorization,
            'Content-Type: ' . $mime,
            'Host: ' . $target['host'],
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $amzDate,
            'Expect:',
        ],
    ]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($stream);
    if ($body === false) {
        return [false, '', '连接 S3 失败：' . $error];
    }
    if ($status < 200 || $status >= 300) {
        $message = 'S3 返回异常（HTTP ' . $status . '）。';
        $xml = function_exists('simplexml_load_string') ? @simplexml_load_string((string)$body, SimpleXMLElement::class, LIBXML_NONET) : false;
        if ($xml instanceof SimpleXMLElement && trim((string)($xml->Message ?? '')) !== '') {
            $message .= ' ' . trim((string)$xml->Message);
        }
        return [false, '', $message];
    }
    return [true, (string)$target['public_url'], ''];
}

function sblog_s3_render_settings(): void
{
    require_admin();
    $settings = sblog_s3_settings();
    ob_start(); ?>
    <div class="admin-shell"><?= render_admin_sidebar('plugins') ?><div class="admin-main"><?= render_admin_topbar('S3 存储') ?>
      <section class="panel admin-list-panel"><div class="panel__header"><h2>S3 上传设置</h2><p class="panel__meta">启用后，新上传的附件将由 S3 接管；密钥不会写入配置缓存。</p></div><div class="panel__body">
        <form class="form-stack" method="post" action="<?= h(url_for('save_s3_settings')) ?>"><?= csrf_field() ?>
          <div class="settings-option-list"><label class="setting-option"><input name="s3_enabled" type="checkbox" value="1"<?= $settings['s3_enabled'] === '1' ? ' checked' : '' ?>><span>启用 S3 上传</span></label><label class="setting-option"><input name="s3_keep_local" type="checkbox" value="1"<?= $settings['s3_keep_local'] === '1' ? ' checked' : '' ?>><span>在本地保留上传备份</span></label><label class="setting-option"><input name="s3_path_style" type="checkbox" value="1"<?= $settings['s3_path_style'] === '1' ? ' checked' : '' ?>><span>使用 Path-style 地址（MinIO 等兼容服务常用）</span></label></div>
          <div class="field"><label for="s3_endpoint">Endpoint</label><input id="s3_endpoint" name="s3_endpoint" type="url" value="<?= h((string)$settings['s3_endpoint']) ?>" placeholder="https://s3.amazonaws.com" maxlength="500"><p class="field-hint">填写服务地址，不要包含 Bucket、查询参数或具体对象路径；生产环境建议使用 HTTPS。</p></div>
          <div class="field-grid"><div class="field"><label for="s3_region">Region</label><input id="s3_region" name="s3_region" value="<?= h((string)$settings['s3_region']) ?>" placeholder="us-east-1" maxlength="100"></div><div class="field"><label for="s3_bucket">Bucket</label><input id="s3_bucket" name="s3_bucket" value="<?= h((string)$settings['s3_bucket']) ?>" maxlength="255" autocomplete="off"></div></div>
          <div class="field-grid"><div class="field"><label for="s3_access_key">Access Key</label><input id="s3_access_key" name="s3_access_key" value="<?= h((string)$settings['s3_access_key']) ?>" maxlength="255" autocomplete="username"></div><div class="field"><label for="s3_secret_key">Secret Key</label><input id="s3_secret_key" name="s3_secret_key" type="password" value="" placeholder="<?= $settings['s3_secret_key'] !== '' ? '已保存，留空则不修改' : 'Secret Access Key' ?>" autocomplete="new-password"></div></div>
          <div class="field-grid"><div class="field"><label for="s3_path_prefix">对象路径前缀</label><input id="s3_path_prefix" name="s3_path_prefix" value="<?= h((string)$settings['s3_path_prefix']) ?>" placeholder="uploads" maxlength="500"><p class="field-hint">实际对象键会追加年份和随机文件名；可留空。</p></div><div class="field"><label for="s3_public_url">CDN 域名</label><input id="s3_public_url" name="s3_public_url" type="url" value="<?= h((string)$settings['s3_public_url']) ?>" placeholder="https://cdn.example.com" maxlength="500"><p class="field-hint">附件 URL 将使用此地址拼接对象键，留空时使用 S3 Endpoint。</p></div></div>
          <div class="action-row"><button class="button">保存 S3 设置</button></div>
        </form>
      </div></section>
    </div></div><?php
    render_layout('S3 存储', (string)ob_get_clean(), ['active' => 'plugins', 'wide' => true, 'description' => 'S3 附件上传设置']);
}

function sblog_s3_handle_request(array $context): void
{
    $action = (string)($context['action'] ?? '');
    if ($action === 'admin_s3') {
        sblog_s3_render_settings();
        exit;
    }
    if ($action !== 'save_s3_settings') {
        return;
    }
    require_admin_post(url_for('admin_s3'));
    $current = sblog_s3_settings();
    $enabled = isset($_POST['s3_enabled']) ? '1' : '0';
    $endpoint = rtrim(trim((string)($_POST['s3_endpoint'] ?? '')), '/');
    $region = trim((string)($_POST['s3_region'] ?? ''));
    $bucket = trim((string)($_POST['s3_bucket'] ?? ''));
    $accessKey = trim((string)($_POST['s3_access_key'] ?? ''));
    $secretKey = trim((string)($_POST['s3_secret_key'] ?? ''));
    $pathPrefix = trim(str_replace('\\', '/', (string)($_POST['s3_path_prefix'] ?? '')), '/');
    $publicUrl = rtrim(trim((string)($_POST['s3_public_url'] ?? '')), '/');
    $effectiveSecret = $secretKey !== '' ? $secretKey : (string)$current['s3_secret_key'];
    $endpointValid = $endpoint !== '' && sblog_s3_endpoint_parts($endpoint) !== null;
    $publicUrlValid = $publicUrl === '' || sblog_s3_endpoint_parts($publicUrl) !== null;
    $prefixValid = !preg_match('/[\x00-\x1F\x7F]/', $pathPrefix) && !preg_match('#(?:^|/)\.\.?(?:/|$)#', $pathPrefix);
    $credentialsValid = !preg_match('/[\x00-\x1F\x7F]/', $region . $accessKey);
    if ($enabled === '1' && (!$endpointValid || $region === '' || $bucket === '' || $accessKey === '' || $effectiveSecret === '' || !$credentialsValid || !function_exists('curl_init'))) {
        set_flash('error', '启用 S3 时，请填写有效的 Endpoint、Region、Bucket 和访问密钥，并确认服务器已启用 cURL。');
        redirect_to(url_for('admin_s3'));
    }
    if (($bucket !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,254}$/', $bucket)) || !$publicUrlValid || !$prefixValid) {
        set_flash('error', 'Bucket、CDN 域名或对象路径前缀格式不正确。');
        redirect_to(url_for('admin_s3'));
    }
    $values = [
        's3_enabled' => $enabled,
        's3_keep_local' => isset($_POST['s3_keep_local']) ? '1' : '0',
        's3_endpoint' => str_sub_u($endpoint, 0, 500),
        's3_region' => str_sub_u($region, 0, 100),
        's3_bucket' => str_sub_u($bucket, 0, 255),
        's3_access_key' => str_sub_u($accessKey, 0, 255),
        's3_path_prefix' => str_sub_u($pathPrefix, 0, 500),
        's3_public_url' => str_sub_u($publicUrl, 0, 500),
        's3_path_style' => isset($_POST['s3_path_style']) ? '1' : '0',
    ];
    if ($secretKey !== '') {
        $values['s3_secret_key'] = $secretKey;
    }
    sblog_s3_save_settings($values);
    set_flash('success', 'S3 上传设置已保存。');
    redirect_to(url_for('admin_s3'));
}

add_plugin_filter('attachment_storage', static function (array $storage, array $context): array {
    $settings = sblog_s3_settings();
    if ($settings['s3_enabled'] !== '1') {
        return $storage;
    }
    $prefix = trim((string)$settings['s3_path_prefix'], '/');
    $key = implode('/', array_filter([$prefix, (string)($context['year'] ?? date('Y')), (string)($context['filename'] ?? '')]));
    [$ok, $url, $error] = sblog_s3_upload((string)($context['file'] ?? ''), $key, (string)($context['mime'] ?? 'application/octet-stream'), $settings);
    if (!$ok) {
        return ['ok' => false, 'url' => '', 'error' => $error, 'remove_local' => false];
    }
    return ['ok' => true, 'url' => $url, 'error' => '', 'remove_local' => $settings['s3_keep_local'] !== '1'];
});

add_plugin_action('request', 'sblog_s3_handle_request');
