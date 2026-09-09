<?php
declare(strict_types=1);

function sblog_rest_api_admin_url(): string
{
    return script_url() . '?a=admin_rest_api';
}

function sblog_rest_api_generate_password(): string
{
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $raw = '';
    for ($i = 0; $i < 24; $i++) {
        $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return implode(' ', str_split($raw, 4));
}

function sblog_rest_api_render_admin(): never
{
    require_admin();
    $admin = current_admin() ?? [];
    $tokens = all_rows(
        'SELECT id, name, uuid, created_at, last_used_at, last_ip FROM rest_api_tokens WHERE user_id = ? AND revoked_at = 0 ORDER BY id DESC',
        [(int)($admin['id'] ?? 0)]
    );
    $newPassword = $_SESSION['sblog_rest_api_new_password'] ?? null;
    unset($_SESSION['sblog_rest_api_new_password']);
    $publicEnabled = sblog_rest_api_setting('public_enabled', '1') === '1';
    $writeEnabled = sblog_rest_api_setting('write_enabled', '1') === '1';
    $allowHttp = sblog_rest_api_setting('allow_http_auth', '0') === '1';
    $corsAllowedOrigins = sblog_rest_api_setting('cors_allowed_origins', '*');

    ob_start(); ?>
    <div class="admin-shell">
      <?= render_admin_sidebar('plugins') ?>
      <div class="admin-main">
        <?= render_admin_topbar('WordPress REST API') ?>

        <?php if (is_array($newPassword)): ?>
          <section class="panel admin-list-panel admin-animate admin-animate--2">
            <div class="panel__header"><h2><?= h(sblog_t('新的 Application Password')) ?></h2><p class="panel__meta"><?= h(sblog_t('该密码只显示一次，请立即保存到需要连接的客户端。')) ?></p></div>
            <div class="panel__body form-stack">
              <div class="field"><label for="rest-api-password"><?= h((string)($newPassword['name'] ?? 'Application Password')) ?></label><input id="rest-api-password" type="text" readonly value="<?= h((string)($newPassword['password'] ?? '')) ?>" autocomplete="off" spellcheck="false"></div>
              <p class="field-hint"><?= h(sblog_t('用户名：{username}', ['username' => (string)($admin['username'] ?? '')])) ?></p>
            </div>
          </section>
        <?php endif; ?>

        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header"><h2><?= h(sblog_t('接口设置')) ?></h2><p class="panel__meta"><?= h(sblog_t('WordPress REST API v2 兼容入口')) ?></p></div>
          <div class="panel__body">
            <form class="form-stack" method="post" action="<?= h(script_url() . '?a=save_rest_api_settings') ?>">
              <?= csrf_field() ?>
              <div class="field"><label for="rest-api-endpoint"><?= h(sblog_t('API 地址')) ?></label><input id="rest-api-endpoint" type="url" readonly value="<?= h(sblog_rest_api_url('/wp/v2')) ?>"></div>
              <label class="setting-option"><input name="public_enabled" type="checkbox" value="1"<?= $publicEnabled ? ' checked' : '' ?>><span><strong><?= h(sblog_t('允许匿名读取公开内容')) ?></strong><small><?= h(sblog_t('关闭后，文章、页面和公开评论也需要 Application Password。')) ?></small></span></label>
              <label class="setting-option"><input name="write_enabled" type="checkbox" value="1"<?= $writeEnabled ? ' checked' : '' ?>><span><strong><?= h(sblog_t('允许 API 写操作')) ?></strong><small><?= h(sblog_t('写操作始终需要有效的 Application Password。')) ?></small></span></label>
              <label class="setting-option"><input name="allow_http_auth" type="checkbox" value="1"<?= $allowHttp ? ' checked' : '' ?>><span><strong><?= h(sblog_t('允许通过普通 HTTP 发送凭据')) ?></strong><small><?= h(sblog_t('仅用于无法配置 HTTPS 的可信内网；密码会以可还原形式经过网络。localhost 始终允许。')) ?></small></span></label>
              <div class="field"><label for="rest-api-cors-origins"><?= h(sblog_t('跨域允许来源')) ?></label><textarea id="rest-api-cors-origins" name="cors_allowed_origins" rows="4" maxlength="4000" placeholder="https://example.com"><?= h($corsAllowedOrigins) ?></textarea><small class="field-hint"><?= h(sblog_t('每行填写一个完整来源（协议、域名及可选端口）；填写 * 允许所有来源，留空则关闭跨域访问。')) ?></small></div>
              <div class="action-row"><button class="button" type="submit"><?= h(sblog_t('保存设置')) ?></button></div>
            </form>
          </div>
        </section>

        <section class="panel admin-list-panel admin-animate admin-animate--3">
          <div class="panel__header"><h2><?= h(sblog_t('Application Passwords')) ?></h2><p class="panel__meta"><?= h(sblog_t('为每个 WordPress 客户端或自动化单独创建，停用时可独立撤销。')) ?></p></div>
          <div class="panel__body">
            <form class="form-stack" method="post" action="<?= h(script_url() . '?a=create_rest_api_token') ?>">
              <?= csrf_field() ?>
              <div class="field"><label for="rest-api-token-name"><?= h(sblog_t('连接名称')) ?></label><input id="rest-api-token-name" name="name" type="text" maxlength="100" placeholder="WordPress Mobile" required></div>
              <div class="action-row"><button class="button" type="submit"><?= h(sblog_t('生成 Application Password')) ?></button></div>
            </form>
          </div>
          <?php if ($tokens): ?>
            <div class="table-wrap"><table><thead><tr><th><?= h(sblog_t('名称')) ?></th><th><?= h(sblog_t('创建时间')) ?></th><th><?= h(sblog_t('最近使用')) ?></th><th><?= h(sblog_t('操作')) ?></th></tr></thead><tbody>
            <?php foreach ($tokens as $token): ?><tr>
              <td><strong><?= h((string)$token['name']) ?></strong><br><small><?= h((string)$token['uuid']) ?></small></td>
              <td><?= h(date('Y-m-d H:i', (int)$token['created_at'])) ?></td>
              <td><?= (int)$token['last_used_at'] > 0 ? h(date('Y-m-d H:i', (int)$token['last_used_at']) . ((string)$token['last_ip'] !== '' ? ' · ' . (string)$token['last_ip'] : '')) : h(sblog_t('尚未使用')) ?></td>
              <td><form method="post" action="<?= h(script_url() . '?a=revoke_rest_api_token') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$token['id'] ?>"><button class="button button--secondary" type="submit"><?= h(sblog_t('撤销')) ?></button></form></td>
            </tr><?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </section>
      </div>
    </div>
    <?php
    render_layout('WordPress REST API', (string)ob_get_clean(), ['active' => 'plugins', 'wide' => true, 'description' => 'WordPress REST API']);
    exit;
}

function sblog_rest_api_handle_admin_request(string $action): bool
{
    if ($action === 'admin_rest_api') {
        sblog_rest_api_render_admin();
    }
    if (!in_array($action, ['save_rest_api_settings', 'create_rest_api_token', 'revoke_rest_api_token'], true)) {
        return false;
    }
    require_admin_post(sblog_rest_api_admin_url());
    $adminId = (int)(current_admin()['id'] ?? 0);
    if ($action === 'save_rest_api_settings') {
        $corsAllowedOrigins = sblog_rest_api_parse_allowed_origins(str_sub_u((string)($_POST['cors_allowed_origins'] ?? ''), 0, 4000));
        sblog_rest_api_save_settings([
            'public_enabled' => isset($_POST['public_enabled']) ? '1' : '0',
            'write_enabled' => isset($_POST['write_enabled']) ? '1' : '0',
            'allow_http_auth' => isset($_POST['allow_http_auth']) ? '1' : '0',
            'cors_allowed_origins' => implode("\n", $corsAllowedOrigins),
        ]);
        set_flash('success', sblog_t('REST API 设置已保存。'));
    } elseif ($action === 'create_rest_api_token') {
        $name = str_sub_u(trim((string)($_POST['name'] ?? '')), 0, 100);
        if ($name === '') {
            set_flash('error', sblog_t('请填写连接名称。'));
        } else {
            $password = sblog_rest_api_generate_password();
            $uuid = bin2hex(random_bytes(16));
            q(
                'INSERT INTO rest_api_tokens(user_id, name, token_hash, uuid, created_at, last_used_at, last_ip, revoked_at) VALUES(?,?,?,?,?,?,?,?)',
                [$adminId, $name, password_hash(str_replace(' ', '', $password), PASSWORD_DEFAULT), $uuid, time(), 0, '', 0]
            );
            $_SESSION['sblog_rest_api_new_password'] = ['name' => $name, 'password' => $password];
            set_flash('success', sblog_t('Application Password 已生成。'));
        }
    } else {
        $id = (int)($_POST['id'] ?? 0);
        q('UPDATE rest_api_tokens SET revoked_at = ? WHERE id = ? AND user_id = ? AND revoked_at = 0', [time(), $id, $adminId]);
        set_flash('success', sblog_t('Application Password 已撤销。'));
    }
    redirect_to(sblog_rest_api_admin_url());
}
