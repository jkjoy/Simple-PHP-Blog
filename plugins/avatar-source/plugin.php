<?php

declare(strict_types=1);

function sblog_avatar_source_defaults(): array
{
    return [
        'provider' => 'gravatar',
        'custom_template' => '',
        'default_image' => 'identicon',
        'rating' => 'g',
    ];
}

function sblog_avatar_source_providers(): array
{
    return [
        'gravatar' => 'Gravatar',
        'cravatar' => 'Cravatar',
        'libravatar' => 'Libravatar',
        'custom' => sblog_t('自定义 URL 模板'),
    ];
}

function sblog_avatar_source_default_images(): array
{
    return [
        'identicon' => 'Identicon',
        'retro' => 'Retro',
        'monsterid' => 'MonsterID',
        'wavatar' => 'Wavatar',
        'robohash' => 'RoboHash',
        'mp' => sblog_t('神秘人'),
        'blank' => sblog_t('空白'),
    ];
}

function sblog_avatar_source_ensure_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    db()->exec(
        "CREATE TABLE IF NOT EXISTS avatar_source_settings(
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )"
    );
    $done = true;
}

function sblog_avatar_source_settings(): array
{
    static $settings = null;
    if (is_array($settings)) {
        return $settings;
    }

    $settings = sblog_avatar_source_defaults();
    try {
        sblog_avatar_source_ensure_table();
        foreach (all_rows('SELECT name, value FROM avatar_source_settings') as $row) {
            $settings[(string)$row['name']] = (string)$row['value'];
        }
    } catch (Throwable $exception) {
        error_log('Avatar source settings load failed: ' . $exception->getMessage());
    }
    return $settings;
}

function sblog_avatar_source_save_settings(array $values): void
{
    sblog_avatar_source_ensure_table();
    $statement = db()->prepare('INSERT OR REPLACE INTO avatar_source_settings(name, value) VALUES(?, ?)');
    foreach ($values as $name => $value) {
        $statement->execute([(string)$name, (string)$value]);
    }
}

function sblog_avatar_source_template_error(string $template): string
{
    if ($template === '') {
        return sblog_t('请填写自定义头像 URL 模板。');
    }
    if (str_len_u($template) > 2000 || preg_match('/[\x00-\x1F\x7F]/', $template)) {
        return sblog_t('自定义头像 URL 模板过长或包含无效字符。');
    }
    if (!str_contains($template, '{hash}') && !str_contains($template, '{email}')) {
        return sblog_t('自定义模板必须包含 {hash} 或 {email}。');
    }

    preg_match_all('/\{([a-z_]+)\}/i', $template, $matches);
    $unknown = array_diff(array_unique($matches[1] ?? []), ['hash', 'email', 'size', 'default', 'rating']);
    if ($unknown !== []) {
        return sblog_t('自定义模板包含不支持的占位符：{name}', ['name' => '{' . reset($unknown) . '}']);
    }

    $sample = strtr($template, [
        '{hash}' => md5('preview@example.com'),
        '{email}' => 'preview%40example.com',
        '{size}' => '72',
        '{default}' => 'identicon',
        '{rating}' => 'g',
    ]);
    $parts = parse_url($sample);
    if (!filter_var($sample, FILTER_VALIDATE_URL) || !is_array($parts)
        || !in_array(str_lower_u((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        || trim((string)($parts['host'] ?? '')) === '' || isset($parts['user']) || isset($parts['pass'])) {
        return sblog_t('自定义头像 URL 模板必须生成有效的 HTTP 或 HTTPS 地址。');
    }
    return '';
}

function sblog_avatar_source_build_url(string $fallbackUrl, string $email, int $size, array $settings): string
{
    $provider = (string)($settings['provider'] ?? 'gravatar');
    $providers = sblog_avatar_source_providers();
    if (!isset($providers[$provider])) {
        return $fallbackUrl;
    }

    $email = strtolower(trim($email));
    $hash = md5($email);
    $size = max(16, min(512, $size));
    $defaultImage = (string)($settings['default_image'] ?? 'identicon');
    if (!isset(sblog_avatar_source_default_images()[$defaultImage])) {
        $defaultImage = 'identicon';
    }
    $rating = (string)($settings['rating'] ?? 'g');
    if (!in_array($rating, ['g', 'pg', 'r', 'x'], true)) {
        $rating = 'g';
    }

    if ($provider === 'custom') {
        $template = trim((string)($settings['custom_template'] ?? ''));
        if (sblog_avatar_source_template_error($template) !== '') {
            return $fallbackUrl;
        }
        $url = strtr($template, [
            '{hash}' => $hash,
            '{email}' => rawurlencode($email),
            '{size}' => (string)$size,
            '{default}' => rawurlencode($defaultImage),
            '{rating}' => rawurlencode($rating),
        ]);
        return safe_link_url($url) !== '#' ? $url : $fallbackUrl;
    }

    $base = match ($provider) {
        'cravatar' => 'https://cravatar.cn/avatar/',
        'libravatar' => 'https://seccdn.libravatar.org/avatar/',
        default => 'https://www.gravatar.com/avatar/',
    };
    $query = ['s' => $size, 'd' => $defaultImage];
    if ($provider !== 'libravatar') {
        $query['r'] = $rating;
    }
    return $base . $hash . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function sblog_avatar_source_render_settings(): void
{
    require_admin();
    $settings = sblog_avatar_source_settings();
    $providers = sblog_avatar_source_providers();
    $defaultImages = sblog_avatar_source_default_images();
    $previewEmail = 'preview@example.com';
    $previewFallback = 'https://www.gravatar.com/avatar/' . md5($previewEmail) . '?s=96&d=identicon&r=g';
    $previewUrl = sblog_avatar_source_build_url($previewFallback, $previewEmail, 96, $settings);

    ob_start();
    ?>
    <div class="admin-shell">
      <?= render_admin_sidebar('plugins') ?>
      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('自定义头像源')) ?>
        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header"><h2><?= h(sblog_t('评论头像设置')) ?></h2><p class="panel__meta"><?= h(sblog_t('站点级配置，对所有主题统一生效；停用插件后会自动恢复 Gravatar。')) ?></p></div>
          <div class="panel__body">
            <form class="form-stack" method="post" action="<?= h(script_url() . '?a=save_avatar_source_settings') ?>">
              <?= csrf_field() ?>
              <div class="field-grid">
                <div class="field"><label for="avatar_provider"><?= h(sblog_t('头像服务')) ?></label><select id="avatar_provider" name="provider"><?php foreach ($providers as $value => $label): ?><option value="<?= h($value) ?>"<?= $settings['provider'] === $value ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="avatar_default_image"><?= h(sblog_t('无头像时的图案')) ?></label><select id="avatar_default_image" name="default_image"><?php foreach ($defaultImages as $value => $label): ?><option value="<?= h($value) ?>"<?= $settings['default_image'] === $value ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="field"><label for="avatar_custom_template"><?= h(sblog_t('自定义 URL 模板')) ?></label><input id="avatar_custom_template" name="custom_template" value="<?= h((string)$settings['custom_template']) ?>" placeholder="https://avatar.example.com/{hash}?s={size}&amp;d={default}&amp;r={rating}" maxlength="2000" autocomplete="off"><p class="field-hint"><?= h(sblog_t('可用占位符：{hash}、{email}、{size}、{default}、{rating}。必须包含 {hash} 或 {email}；为保护评论者隐私，建议优先使用 {hash}。')) ?></p></div>
              <div class="field"><label for="avatar_rating"><?= h(sblog_t('内容等级')) ?></label><select id="avatar_rating" name="rating"><?php foreach (['g', 'pg', 'r', 'x'] as $rating): ?><option value="<?= h($rating) ?>"<?= $settings['rating'] === $rating ? ' selected' : '' ?>><?= h(strtoupper($rating)) ?></option><?php endforeach; ?></select></div>
              <div class="field"><label for="avatar_preview_url"><?= h(sblog_t('头像预览')) ?></label><img src="<?= h($previewUrl) ?>" width="96" height="96" alt="<?= h(sblog_t('头像预览')) ?>" loading="eager" decoding="async" referrerpolicy="no-referrer"><input id="avatar_preview_url" value="<?= h($previewUrl) ?>" readonly aria-label="<?= h(sblog_t('头像预览地址')) ?>"></div>
              <div class="action-row"><button class="button" type="submit"><?= h(sblog_t('保存头像设置')) ?></button></div>
            </form>
          </div>
        </section>
      </div>
    </div>
    <?php
    render_layout(sblog_t('自定义头像源'), (string)ob_get_clean(), [
        'active' => 'plugins',
        'wide' => true,
        'description' => sblog_t('评论头像服务设置'),
    ]);
}

function sblog_avatar_source_handle_request(array $context): void
{
    $action = (string)($context['action'] ?? '');
    if ($action === 'admin_avatar_source') {
        sblog_avatar_source_render_settings();
        exit;
    }
    if ($action !== 'save_avatar_source_settings') {
        return;
    }

    require_admin_post(script_url() . '?a=admin_avatar_source');
    $providers = sblog_avatar_source_providers();
    $defaultImages = sblog_avatar_source_default_images();
    $provider = trim((string)($_POST['provider'] ?? 'gravatar'));
    $customTemplate = trim((string)($_POST['custom_template'] ?? ''));
    $defaultImage = trim((string)($_POST['default_image'] ?? 'identicon'));
    $rating = trim((string)($_POST['rating'] ?? 'g'));

    if (!isset($providers[$provider]) || !isset($defaultImages[$defaultImage]) || !in_array($rating, ['g', 'pg', 'r', 'x'], true)) {
        set_flash('error', sblog_t('头像设置包含无效选项。'));
        redirect_to(script_url() . '?a=admin_avatar_source');
    }
    if ($customTemplate !== '' || $provider === 'custom') {
        $error = sblog_avatar_source_template_error($customTemplate);
        if ($error !== '') {
            set_flash('error', $error);
            redirect_to(script_url() . '?a=admin_avatar_source');
        }
    }

    sblog_avatar_source_save_settings([
        'provider' => $provider,
        'custom_template' => str_sub_u($customTemplate, 0, 2000),
        'default_image' => $defaultImage,
        'rating' => $rating,
    ]);
    set_flash('success', sblog_t('头像源设置已保存。'));
    redirect_to(script_url() . '?a=admin_avatar_source');
}

add_plugin_filter('avatar_url', static function (string $url, array $context): string {
    return sblog_avatar_source_build_url(
        $url,
        (string)($context['email'] ?? ''),
        (int)($context['size'] ?? 72),
        sblog_avatar_source_settings()
    );
});

add_plugin_action('request', 'sblog_avatar_source_handle_request');
