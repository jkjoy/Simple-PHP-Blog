<?php
declare(strict_types=1);

function sblog_ai_defaults(): array
{
    return [
        'ai_api_url' => 'https://api.deepseek.com',
        'ai_api_key' => '',
        'ai_model' => 'deepseek-v4-flash',
        'ai_slug_prompt' => 'Translate the title into a concise English URL slug. Output lowercase ASCII words separated only by hyphens. Output the slug only, without quotes or explanation.',
        'ai_summary_prompt' => '根据文章内容生成不超过100个汉字的中文摘要。只输出摘要正文，不要标题、引号、解释或 Markdown 标记。',
        'ai_polish_prompt' => '你是专业中文编辑。严格执行用户要求，保留有效 Markdown 结构。只输出处理后的完整正文，不要解释处理过程。',
    ];
}

function sblog_ai_settings(): array
{
    $settings = sblog_ai_defaults();
    try {
        $rows = all_rows('SELECT name, value FROM ai_settings');
        if (!$rows) {
            $rows = all_rows("SELECT name, value FROM settings WHERE name LIKE 'ai\\_%' ESCAPE '\\'");
            if ($rows) {
                $statement = db()->prepare('INSERT OR REPLACE INTO ai_settings(name, value) VALUES(?, ?)');
                foreach ($rows as $row) {
                    $statement->execute([(string)$row['name'], (string)$row['value']]);
                }
                q("DELETE FROM settings WHERE name LIKE 'ai\\_%' ESCAPE '\\'");
                settings_cache(true);
            }
        }
        foreach ($rows as $row) {
            $settings[(string)$row['name']] = (string)$row['value'];
        }
    } catch (Throwable) {
    }
    return $settings;
}

function sblog_ai_save_settings(array $values): void
{
    $statement = db()->prepare('INSERT OR REPLACE INTO ai_settings(name, value) VALUES(?, ?)');
    foreach ($values as $name => $value) {
        $statement->execute([(string)$name, (string)$value]);
    }
}

function sblog_ai_validated_endpoint(string $url): ?array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || str_lower_u((string)($parts['scheme'] ?? '')) !== 'https'
        || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    $host = trim((string)($parts['host'] ?? ''), '[]');
    if ($host === '') {
        return null;
    }
    $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_values(array_unique(array_merge(
        gethostbynamel($host) ?: [],
        array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
    )));
    foreach ($addresses as $address) {
        if (!is_string($address) || !public_ip_address($address)) {
            return null;
        }
    }
    if ($addresses === []) {
        return null;
    }
    return ['host' => $host, 'port' => (int)($parts['port'] ?? 443), 'ip' => $addresses[0]];
}

function sblog_ai_completion(string $instruction, string $content): array
{
    $settings = sblog_ai_settings();
    $baseUrl = rtrim(trim((string)$settings['ai_api_url']), '/');
    $apiKey = trim((string)$settings['ai_api_key']);
    $model = trim((string)$settings['ai_model']);
    if ($baseUrl === '' || $apiKey === '' || $model === '') {
        return [false, '请先完成 AI 设置。'];
    }
    if (!function_exists('curl_init')) {
        return [false, '服务器缺少 cURL 扩展，无法调用 AI 服务。'];
    }
    $url = str_ends_with($baseUrl, '/chat/completions') ? $baseUrl : $baseUrl . '/chat/completions';
    $endpoint = sblog_ai_validated_endpoint($url);
    if ($endpoint === null) {
        return [false, 'AI 地址必须使用 HTTPS 并解析到公网地址。'];
    }
    $payload = json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $instruction],
            ['role' => 'user', 'content' => $content],
        ],
        'temperature' => 0.3,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        return [false, '无法创建 AI 请求。'];
    }

    $resolvedIp = str_contains((string)$endpoint['ip'], ':') ? '[' . $endpoint['ip'] . ']' : (string)$endpoint['ip'];
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_RESOLVE => [$endpoint['host'] . ':' . $endpoint['port'] . ':' . $resolvedIp],
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false) {
        return [false, 'AI 服务连接失败：' . $error];
    }
    $data = json_decode((string)$body, true);
    $result = trim((string)($data['choices'][0]['message']['content'] ?? ''));
    if ($status < 200 || $status >= 300 || $result === '') {
        return [false, (string)($data['error']['message'] ?? 'AI 服务返回异常（HTTP ' . $status . '）。')];
    }
    return [true, $result];
}

function sblog_ai_render_settings(): void
{
    require_admin();
    $settings = sblog_ai_settings();
    ob_start(); ?>
    <div class="admin-shell"><?= render_admin_sidebar('plugins') ?><div class="admin-main"><?= render_admin_topbar('AI 设置') ?>
      <section class="panel admin-list-panel"><div class="panel__header"><h2>模型接口</h2><p class="panel__meta">兼容 OpenAI Chat Completions 格式的服务。</p></div><div class="panel__body">
        <form class="form-stack" method="post" action="<?= h(url_for('save_ai_settings')) ?>"><?= csrf_field() ?>
          <div class="field"><label for="ai_api_url">API 地址</label><input id="ai_api_url" name="ai_api_url" type="url" value="<?= h((string)$settings['ai_api_url']) ?>" placeholder="https://api.deepseek.com" required><p class="field-hint">可以填写服务根地址或完整的 /chat/completions 地址。</p></div>
          <div class="field"><label for="ai_api_key">API 密钥</label><input id="ai_api_key" name="ai_api_key" type="password" value="" placeholder="<?= $settings['ai_api_key'] !== '' ? '已保存，留空则不修改' : 'sk-...' ?>" autocomplete="new-password"><p class="field-hint">密钥仅保存在服务器 SQLite 中，不会发送到浏览器前端。</p></div>
          <div class="field"><label for="ai_model">模型名称</label><input id="ai_model" name="ai_model" value="<?= h((string)$settings['ai_model']) ?>" placeholder="deepseek-v4-flash" required></div>
          <div class="field"><label for="ai_slug_prompt">Slug 提示词</label><textarea id="ai_slug_prompt" name="ai_slug_prompt" rows="4" required><?= h((string)$settings['ai_slug_prompt']) ?></textarea></div>
          <div class="field"><label for="ai_summary_prompt">摘要提示词</label><textarea id="ai_summary_prompt" name="ai_summary_prompt" rows="4" required><?= h((string)$settings['ai_summary_prompt']) ?></textarea></div>
          <div class="field"><label for="ai_polish_prompt">润色提示词</label><textarea id="ai_polish_prompt" name="ai_polish_prompt" rows="4" required><?= h((string)$settings['ai_polish_prompt']) ?></textarea><p class="field-hint">弹窗中填写的具体要求会追加到这条系统提示词之后。</p></div>
          <div class="action-row"><button class="button">保存 AI 设置</button></div>
        </form>
      </div></section>
    </div></div><?php
    render_layout('AI 设置', (string)ob_get_clean(), ['active' => 'plugins', 'wide' => true, 'description' => 'AI 模型设置']);
}

function sblog_ai_handle_request(array $context): void
{
    $action = (string)($context['action'] ?? '');
    if ($action === 'admin_ai') {
        sblog_ai_render_settings();
        exit;
    }
    if ($action === 'save_ai_settings') {
        require_admin_post(url_for('admin_ai'));
        $apiUrl = rtrim(trim((string)($_POST['ai_api_url'] ?? '')), '/');
        $apiKey = trim((string)($_POST['ai_api_key'] ?? ''));
        $model = trim((string)($_POST['ai_model'] ?? ''));
        $slugPrompt = trim((string)($_POST['ai_slug_prompt'] ?? ''));
        $summaryPrompt = trim((string)($_POST['ai_summary_prompt'] ?? ''));
        $polishPrompt = trim((string)($_POST['ai_polish_prompt'] ?? ''));
        if (sblog_ai_validated_endpoint($apiUrl) === null || $model === '' || $slugPrompt === '' || $summaryPrompt === '' || $polishPrompt === '') {
            set_flash('error', 'API 地址必须使用 HTTPS 并解析到公网地址，同时请填写模型名称和提示词。');
            redirect_to(url_for('admin_ai'));
        }
        $values = ['ai_api_url' => $apiUrl, 'ai_model' => $model, 'ai_slug_prompt' => $slugPrompt, 'ai_summary_prompt' => $summaryPrompt, 'ai_polish_prompt' => $polishPrompt];
        if ($apiKey !== '') {
            $values['ai_api_key'] = $apiKey;
        }
        sblog_ai_save_settings($values);
        set_flash('success', 'AI 设置已保存。');
        redirect_to(url_for('admin_ai'));
    }
    if ($action !== 'ai_generate') {
        return;
    }

    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => '仅支持 POST 请求。'], 405);
    }
    verify_csrf();
    $type = (string)($_POST['type'] ?? '');
    $content = trim((string)($_POST['content'] ?? ''));
    $instruction = trim((string)($_POST['instruction'] ?? ''));
    if (str_len_u($content) > 50000) {
        json_response(['ok' => false, 'error' => '内容过长，请控制在 50000 字以内。'], 422);
    }
    $settings = sblog_ai_settings();
    if ($type === 'slug') {
        if ($content === '') {
            json_response(['ok' => false, 'error' => '请先填写文章标题。'], 422);
        }
        [$ok, $result] = sblog_ai_completion((string)$settings['ai_slug_prompt'], $content);
        if ($ok) {
            $result = substr(trim((string)preg_replace('/[^a-z0-9]+/', '-', str_lower_u($result)), '-'), 0, 100);
        }
    } elseif ($type === 'summary') {
        if ($content === '') {
            json_response(['ok' => false, 'error' => '请先填写文章正文。'], 422);
        }
        [$ok, $result] = sblog_ai_completion((string)$settings['ai_summary_prompt'], $content);
        if ($ok) {
            $result = str_sub_u($result, 0, 100);
        }
    } elseif ($type === 'polish') {
        if ($instruction === '') {
            json_response(['ok' => false, 'error' => '请填写润色或生成要求。'], 422);
        }
        [$ok, $result] = sblog_ai_completion((string)$settings['ai_polish_prompt'] . ' 用户要求：' . $instruction, $content !== '' ? $content : '请根据要求生成正文。');
    } else {
        json_response(['ok' => false, 'error' => '未知的 AI 操作。'], 422);
    }
    if (!$ok) {
        json_response(['ok' => false, 'error' => $result], 502);
    }
    json_response(['ok' => true, 'result' => $result]);
}

add_plugin_filter('editor_field_actions_html', static function (string $html, array $context): string {
    return $html . match ((string)($context['field'] ?? '')) {
        'slug' => '<button class="button button--ghost button--compact" type="button" data-ai-action="slug">AI 生成</button>',
        'excerpt' => '<button class="button button--ghost button--compact" type="button" data-ai-action="summary">AI 摘要</button>',
        'content' => '<button class="button button--ghost button--compact" type="button" data-ai-action="polish">AI 润色</button>',
        default => '',
    };
});

add_plugin_filter('editor_after_form_html', static function (string $html): string {
    ob_start(); ?>
    <div class="ai-editor" data-ai-editor data-url="<?= h(url_for('ai_generate')) ?>" data-csrf="<?= h(csrf_token()) ?>">
      <div class="ai-modal" data-ai-modal hidden role="dialog" aria-modal="true" aria-labelledby="ai-modal-title">
        <div class="ai-modal__backdrop" data-ai-close></div>
        <div class="ai-modal__panel">
          <div class="ai-modal__header"><h2 id="ai-modal-title">AI 润色正文</h2><button class="button button--ghost button--compact" type="button" data-ai-close aria-label="关闭窗口">关闭窗口</button></div>
          <div class="field"><label for="ai_instruction">润色或生成要求</label><textarea id="ai_instruction" rows="5" placeholder="例如：修正语病，保持 Markdown 格式；补充一段实际使用示例；将内容改得更简洁。"></textarea></div>
          <p class="field-hint" data-ai-status></p>
          <div class="action-row"><button class="button button--secondary" type="button" data-ai-close>取消</button><button class="button" type="button" data-ai-confirm>确定并填入正文</button></div>
        </div>
      </div>
    </div>
    <?php return $html . (string)ob_get_clean();
});

add_plugin_action('request', 'sblog_ai_handle_request');
