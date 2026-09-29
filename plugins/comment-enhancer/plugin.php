<?php

declare(strict_types=1);

if (!defined('PLUGINS_DIR')) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/lib.php';

const SCE_VERSION = '1.2.0';
const SCE_LOCATION_CACHE_TTL = 15552000;
const SCE_LOCATION_FAILURE_TTL = 21600;

function sce_install(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->exec(
        "CREATE TABLE IF NOT EXISTS comment_enhancer_settings(
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )"
    );
    db()->exec(
        "CREATE TABLE IF NOT EXISTS comment_enhancer_ip_cache(
            ip_hash TEXT PRIMARY KEY,
            location TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'failed',
            checked_at INTEGER NOT NULL DEFAULT 0,
            attempted_at INTEGER NOT NULL DEFAULT 0
        )"
    );
    $schemaVersion = val('SELECT value FROM comment_enhancer_settings WHERE name = ?', ['schema_version']);
    if ($schemaVersion !== '2') {
        $columns = table_columns(db(), 'comment_enhancer_ip_cache');
        if (!isset($columns['attempted_at'])) {
            db()->exec('ALTER TABLE comment_enhancer_ip_cache ADD COLUMN attempted_at INTEGER NOT NULL DEFAULT 0');
        }

        $setting = db()->prepare('INSERT OR IGNORE INTO comment_enhancer_settings(name, value) VALUES(?, ?)');
        $setting->execute(['cache_secret', bin2hex(random_bytes(32))]);
        $keyVersion = val('SELECT value FROM comment_enhancer_settings WHERE name = ?', ['cache_key_version']);
        if ($keyVersion !== '1') {
            db()->exec('DELETE FROM comment_enhancer_ip_cache');
            db()->prepare('INSERT OR REPLACE INTO comment_enhancer_settings(name, value) VALUES(?, ?)')
                ->execute(['cache_key_version', '1']);
        }
        db()->prepare('INSERT OR REPLACE INTO comment_enhancer_settings(name, value) VALUES(?, ?)')
            ->execute(['schema_version', '2']);
    }
    $lastPrune = (int)val('SELECT value FROM comment_enhancer_settings WHERE name = ?', ['last_cache_prune']);
    if ($lastPrune < time() - 86400) {
        q(
            "DELETE FROM comment_enhancer_ip_cache
             WHERE (status = 'ok' AND checked_at < ?)
                OR (status <> 'ok' AND attempted_at < ?)",
            [time() - SCE_LOCATION_CACHE_TTL, time() - SCE_LOCATION_CACHE_TTL]
        );
        db()->prepare('INSERT OR REPLACE INTO comment_enhancer_settings(name, value) VALUES(?, ?)')
            ->execute(['last_cache_prune', (string)time()]);
    }
}

function sce_cache_key_for_ip(string $ip): string
{
    $secret = (string)(sce_settings()['cache_secret'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
        $secret = bin2hex(random_bytes(32));
        db()->prepare('INSERT OR REPLACE INTO comment_enhancer_settings(name, value) VALUES(?, ?)')
            ->execute(['cache_secret', $secret]);
        db()->exec('DELETE FROM comment_enhancer_ip_cache');
        $GLOBALS['sce_settings_cache']['cache_secret'] = $secret;
    }
    $packed = @inet_pton(trim($ip));
    return is_string($packed) ? hash_hmac('sha256', $packed, $secret) : '';
}

function sce_settings(): array
{
    if (is_array($GLOBALS['sce_settings_cache'] ?? null)) {
        return $GLOBALS['sce_settings_cache'];
    }
    $settings = ['online_lookup' => '0'];
    try {
        foreach (all_rows('SELECT name, value FROM comment_enhancer_settings') as $row) {
            $settings[(string)$row['name']] = (string)$row['value'];
        }
    } catch (Throwable) {
    }
    return $GLOBALS['sce_settings_cache'] = $settings;
}

function sce_save_settings(array $values): void
{
    sce_install();
    $statement = db()->prepare('INSERT OR REPLACE INTO comment_enhancer_settings(name, value) VALUES(?, ?)');
    foreach ($values as $name => $value) {
        $statement->execute([(string)$name, (string)$value]);
    }
    $GLOBALS['sce_settings_cache'] = array_replace(sce_settings(), array_map('strval', $values));
}

function sce_text(string $key, array $parameters = []): string
{
    $english = str_starts_with(strtolower(sblog_i18n_locale()), 'en');
    $messages = $english ? [
        'group' => 'Commenter details',
        'level' => 'Comment activity level {level}',
        'owner' => 'Owner',
        'owner_title' => 'Authenticated site owner',
        'location' => 'IP location: {value}',
        'browser' => 'Browser: {value}',
        'os' => 'Operating system: {value}',
        'local' => 'Local network',
        'unknown_location' => 'Unknown location',
        'settings_title' => 'Comment Enhancer',
        'settings_heading' => 'Comment metadata',
        'settings_description' => 'Show activity level, coarse IP location, browser, and operating system without exposing email, IP, or the full user agent.',
        'online_label' => 'Enable online IP location lookup',
        'online_hint' => 'New comment public IPs are sent over HTTPS to ipwho.is after submission. Public page views never trigger a lookup. Results are cached for 180 days.',
        'save' => 'Save settings',
        'saved' => 'Comment enhancer settings saved.',
        'cache_title' => 'Location cache',
        'cache_summary' => '{success} locations cached; {failed} failed lookups cached.',
        'backfill' => 'Backfill history',
        'backfill_hint' => 'Looks up at most five uncached historical public IPs per click. No email, name, comment text, or user agent is sent.',
        'backfill_done' => 'Historical location lookup processed {count} IPs.',
        'online_required' => 'Enable online IP lookup before backfilling history.',
        'clear_cache' => 'Clear location cache',
        'cache_cleared' => 'Location cache cleared.',
    ] : [
        'group' => '评论者信息',
        'level' => '评论活跃等级 {level}',
        'owner' => '博主',
        'owner_title' => '已登录的站点管理员',
        'location' => 'IP 归属地：{value}',
        'browser' => '浏览器：{value}',
        'os' => '操作系统：{value}',
        'local' => '本地网络',
        'unknown_location' => '未知地区',
        'settings_title' => '评论增强',
        'settings_heading' => '评论元信息',
        'settings_description' => '显示活跃等级、粗粒度 IP 归属地、浏览器和操作系统，不公开邮箱、完整 IP 或完整 User-Agent。',
        'online_label' => '启用在线 IP 归属地查询',
        'online_hint' => '新评论提交后，其公网 IP 会通过 HTTPS 发送给 ipwho.is。访客浏览公开页面不会触发查询，结果缓存 180 天。',
        'save' => '保存设置',
        'saved' => '评论增强设置已保存。',
        'cache_title' => '归属地缓存',
        'cache_summary' => '已缓存 {success} 个归属地，另有 {failed} 个失败记录。',
        'backfill' => '补全历史归属地',
        'backfill_hint' => '每次最多查询 5 个尚未缓存的历史公网 IP；不会发送邮箱、昵称、评论内容或 User-Agent。',
        'backfill_done' => '已处理 {count} 个历史 IP。',
        'online_required' => '请先启用在线 IP 归属地查询。',
        'clear_cache' => '清空归属地缓存',
        'cache_cleared' => '归属地缓存已清空。',
    ];

    $message = $messages[$key] ?? $key;
    $replacements = [];
    foreach ($parameters as $name => $value) {
        $replacements['{' . $name . '}'] = (string)$value;
    }
    return strtr($message, $replacements);
}

function sce_comment_email_counts(array $comments): array
{
    $emails = [];
    foreach ($comments as $comment) {
        if ((int)($comment['user_id'] ?? 0) > 0) {
            continue;
        }
        $email = str_lower_u(trim((string)($comment['author_email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[$email] = true;
        }
    }
    if ($emails === []) {
        return [];
    }

    $emailList = array_keys($emails);
    $placeholders = implode(',', array_fill(0, count($emailList), '?'));
    $rows = all_rows(
        "SELECT author_email AS email_key, COUNT(*) AS total
         FROM comments
         WHERE user_id IS NULL AND status = 'approved' AND author_email COLLATE NOCASE IN ({$placeholders})
         GROUP BY author_email COLLATE NOCASE",
        $emailList
    );

    $counts = [];
    foreach ($rows as $row) {
        $counts[str_lower_u(trim((string)$row['email_key']))] = (int)$row['total'];
    }
    return $counts;
}

function sce_lookup_public_ip(string $ip): string
{
    if (sce_ip_scope($ip) !== 'public' || !function_exists('curl_init')) {
        return '';
    }

    $handle = curl_init('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country_code,country,region');
    if ($handle === false) {
        return '';
    }

    $body = '';
    $tooLarge = false;
    $options = [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT_MS => 800,
        CURLOPT_TIMEOUT_MS => 1800,
        CURLOPT_NOSIGNAL => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'SBlog-Comment-Enhancer/' . SCE_VERSION,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 8192) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    curl_setopt_array($handle, array_replace($options, curl_trust_options()));
    $ok = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    if ($ok === false || $tooLarge || $status !== 200 || $body === '') {
        return '';
    }

    try {
        $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return '';
    }
    return is_array($payload) ? sce_geo_cache_value($payload) : '';
}

function sce_location_label(string $cachedValue): string
{
    $cachedValue = trim($cachedValue);
    if ($cachedValue === '') {
        return '';
    }
    try {
        $payload = json_decode($cachedValue, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return sce_clean_geo_text($cachedValue);
    }
    return is_array($payload) ? sce_format_geo_location($payload, sblog_i18n_locale()) : '';
}

function sce_store_location(string $ipHash, string $location, string $status, int $checkedAt, int $attemptedAt): void
{
    q(
        "INSERT OR REPLACE INTO comment_enhancer_ip_cache(ip_hash, location, status, checked_at, attempted_at)
         VALUES(?, ?, ?, ?, ?)",
        [$ipHash, $location, $status, $checkedAt, $attemptedAt]
    );
}

function sce_prime_location_cache(array $comments): void
{
    $keys = [];
    foreach ($comments as $comment) {
        $ip = trim((string)($comment['ip_address'] ?? ''));
        if (sce_ip_scope($ip) === 'public') {
            $key = sce_cache_key_for_ip($ip);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
    }
    if ($keys === []) {
        return;
    }

    $memory = is_array($GLOBALS['sce_location_memory'] ?? null) ? $GLOBALS['sce_location_memory'] : [];
    $missing = array_values(array_filter(
        array_keys($keys),
        static fn(string $key): bool => !array_key_exists($key, $memory)
    ));
    if ($missing === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($missing), '?'));
    foreach (all_rows(
        "SELECT ip_hash, location, status, checked_at
         FROM comment_enhancer_ip_cache
         WHERE ip_hash IN ({$placeholders})",
        $missing
    ) as $row) {
        $fresh = (string)($row['status'] ?? '') === 'ok'
            && (int)($row['checked_at'] ?? 0) >= time() - SCE_LOCATION_CACHE_TTL;
        $location = $fresh ? sce_location_label((string)($row['location'] ?? '')) : '';
        $memory[(string)$row['ip_hash']] = $location !== '' ? $location : sce_text('unknown_location');
    }
    foreach ($missing as $key) {
        if (!array_key_exists($key, $memory)) {
            $memory[$key] = sce_text('unknown_location');
        }
    }
    $GLOBALS['sce_location_memory'] = $memory;
}

function sce_location_for_ip(string $ip, bool $allowRemote = false): ?string
{
    $memory = is_array($GLOBALS['sce_location_memory'] ?? null) ? $GLOBALS['sce_location_memory'] : [];

    $scope = sce_ip_scope($ip);
    if ($scope === 'local') {
        return sce_text('local');
    }
    if ($scope !== 'public') {
        return null;
    }

    $cacheKey = sce_cache_key_for_ip($ip);
    if ($cacheKey === '') {
        return null;
    }
    if (array_key_exists($cacheKey, $memory)) {
        return $memory[$cacheKey];
    }

    $cached = null;
    try {
        $cached = one(
            'SELECT location, status, checked_at, attempted_at FROM comment_enhancer_ip_cache WHERE ip_hash = ?',
            [$cacheKey]
        );
    } catch (Throwable) {
    }

    $now = time();
    $staleValue = is_array($cached) ? trim((string)($cached['location'] ?? '')) : '';
    $staleIsFresh = is_array($cached)
        && (string)($cached['status'] ?? '') === 'ok'
        && (int)($cached['checked_at'] ?? 0) >= $now - SCE_LOCATION_CACHE_TTL;
    $staleLocation = $staleIsFresh ? sce_location_label($staleValue) : '';
    if (is_array($cached)) {
        if ((string)($cached['status'] ?? '') === 'ok'
            && (int)($cached['checked_at'] ?? 0) >= $now - SCE_LOCATION_CACHE_TTL) {
            $memory[$cacheKey] = $staleLocation !== '' ? $staleLocation : sce_text('unknown_location');
            $GLOBALS['sce_location_memory'] = $memory;
            return $memory[$cacheKey];
        }
        if ((int)($cached['attempted_at'] ?? 0) >= $now - SCE_LOCATION_FAILURE_TTL) {
            $memory[$cacheKey] = $staleLocation !== '' ? $staleLocation : sce_text('unknown_location');
            $GLOBALS['sce_location_memory'] = $memory;
            return $memory[$cacheKey];
        }
    }

    if (!$allowRemote) {
        $memory[$cacheKey] = $staleLocation !== '' ? $staleLocation : sce_text('unknown_location');
        $GLOBALS['sce_location_memory'] = $memory;
        return $memory[$cacheKey];
    }

    $cacheValue = sce_lookup_public_ip($ip);
    try {
        if ($cacheValue !== '') {
            sce_store_location($cacheKey, $cacheValue, 'ok', $now, $now);
        } elseif ($staleIsFresh) {
            q('UPDATE comment_enhancer_ip_cache SET attempted_at = ? WHERE ip_hash = ?', [$now, $cacheKey]);
        } else {
            sce_store_location($cacheKey, '', 'failed', 0, $now);
        }
    } catch (Throwable) {
    }

    $location = $cacheValue !== '' ? sce_location_label($cacheValue) : $staleLocation;
    $memory[$cacheKey] = $location !== '' ? $location : sce_text('unknown_location');
    $GLOBALS['sce_location_memory'] = $memory;
    return $memory[$cacheKey];
}

function sce_cache_new_comment_location(array $context): void
{
    if ((string)(sce_settings()['online_lookup'] ?? '0') !== '1') {
        return;
    }
    $commentId = (int)($context['comment_id'] ?? 0);
    if ($commentId < 1) {
        return;
    }

    $comment = one('SELECT ip_address FROM comments WHERE id = ?', [$commentId]);
    if (is_array($comment)) {
        sce_location_for_ip((string)$comment['ip_address'], true);
    }
}

function sce_handle_comment_status_changed(array $context): void
{
    $comments = is_array($context['comments'] ?? null) ? $context['comments'] : [];
    $operation = (string)($context['operation'] ?? '');
    if ($operation === 'delete') {
        foreach ($comments as $comment) {
            $ip = trim((string)($comment['ip_address'] ?? ''));
            if ($ip === '' || val('SELECT 1 FROM comments WHERE ip_address = ? LIMIT 1', [$ip]) !== false) {
                continue;
            }
            $cacheKey = sce_cache_key_for_ip($ip);
            if ($cacheKey !== '') {
                q('DELETE FROM comment_enhancer_ip_cache WHERE ip_hash = ?', [$cacheKey]);
            }
        }
        return;
    }

    if ((string)($context['status'] ?? '') !== 'approved'
        || (string)(sce_settings()['online_lookup'] ?? '0') !== '1') {
        return;
    }
    $lookedUp = 0;
    foreach ($comments as $comment) {
        $ip = trim((string)($comment['ip_address'] ?? ''));
        if (sce_ip_scope($ip) !== 'public') {
            continue;
        }
        sce_location_for_ip($ip, true);
        $lookedUp++;
        if ($lookedUp >= 5) {
            break;
        }
    }
}

function sce_handle_plugin_status_changed(array $context): void
{
    if ((string)($context['plugin'] ?? '') === 'comment-enhancer'
        && (string)($context['operation'] ?? '') === 'deactivate') {
        q('DELETE FROM comment_enhancer_ip_cache');
        sce_save_settings([
            'online_lookup' => '0',
            'backfill_cursor' => (string)PHP_INT_MAX,
        ]);
    }
}

function sce_client_icon_html(string $kind, string $value): string
{
    $icon = $kind === 'browser' ? sce_browser_icon_name($value) : sce_os_icon_name($value);
    $label = sce_text($kind, ['value' => $value]);
    return '<span class="comment-enhancer-meta__icon comment-enhancer-meta__icon--' . h($kind) . ' comment-enhancer-meta__icon--' . h($icon) . '" role="listitem" tabindex="0" aria-label="' . h($label) . '">'
        . sce_icon_svg($icon)
        . '<span class="comment-enhancer-meta__tooltip" aria-hidden="true">' . h($value) . '</span>'
        . '</span>';
}

function sce_render_comment_identity(mixed $html, array $context): string
{
    $existing = is_string($html) ? $html : '';
    $comment = is_array($context['comment'] ?? null) ? $context['comment'] : [];
    if ($comment === []) {
        return $existing;
    }

    static $countsByEmail = [];
    $visibleComments = is_array($context['comments'] ?? null) ? $context['comments'] : [$comment];
    $emailsToLoad = [];
    foreach ($visibleComments as $visibleComment) {
        if ((int)($visibleComment['user_id'] ?? 0) > 0) {
            continue;
        }
        $visibleEmail = str_lower_u(trim((string)($visibleComment['author_email'] ?? '')));
        if (filter_var($visibleEmail, FILTER_VALIDATE_EMAIL) && !array_key_exists($visibleEmail, $countsByEmail)) {
            $emailsToLoad[$visibleEmail] = true;
        }
    }
    if ($emailsToLoad !== []) {
        $batch = sce_comment_email_counts(array_map(
            static fn(string $email): array => ['author_email' => $email, 'user_id' => null],
            array_keys($emailsToLoad)
        ));
        foreach (array_keys($emailsToLoad) as $loadedEmail) {
            $countsByEmail[$loadedEmail] = (int)($batch[$loadedEmail] ?? 0);
        }
    }

    $email = str_lower_u(trim((string)($comment['author_email'] ?? '')));
    $authenticated = (int)($comment['user_id'] ?? 0) > 0;
    $commentCount = !$authenticated && filter_var($email, FILTER_VALIDATE_EMAIL)
        ? (int)($countsByEmail[$email] ?? 0)
        : 0;
    if ($authenticated) {
        $title = sce_text('owner_title');
        return $existing . '<span class="comment-enhancer-badge comment-enhancer-badge--owner" aria-label="' . h($title) . '" title="' . h($title) . '">' . h(sce_text('owner')) . '</span>';
    } elseif ($commentCount > 0) {
        $level = sce_level_for_count($commentCount);
        $levelLabel = 'LV' . $level;
        $levelTitle = sce_text('level', ['level' => $levelLabel]);
        return $existing . '<span class="comment-enhancer-badge comment-enhancer-badge--lv' . $level . '" aria-label="' . h($levelTitle) . '" title="' . h($levelTitle) . '">' . h($levelLabel) . '</span>';
    }

    return $existing;
}

function sce_render_comment_meta(mixed $html, array $context): string
{
    $existing = is_string($html) ? $html : '';
    $comment = is_array($context['comment'] ?? null) ? $context['comment'] : [];
    if ($comment === []) {
        return $existing;
    }

    $visibleComments = is_array($context['comments'] ?? null) ? $context['comments'] : [$comment];
    static $primedPosts = [];
    $postId = (int)((is_array($context['post'] ?? null) ? $context['post'] : [])['id'] ?? 0);
    if ($postId < 1 || !isset($primedPosts[$postId])) {
        sce_prime_location_cache($visibleComments);
        if ($postId > 0 && array_key_exists('comments', $context)) {
            $primedPosts[$postId] = true;
        }
    }

    $items = [];
    $client = sce_parse_user_agent((string)($comment['user_agent'] ?? ''));
    if ($client['browser'] !== '') {
        $items[] = sce_client_icon_html('browser', $client['browser']);
    }
    if ($client['os'] !== '') {
        $items[] = sce_client_icon_html('os', $client['os']);
    }

    $location = sce_location_for_ip(
        (string)($comment['ip_address'] ?? ''),
        false
    );
    if ($location !== null && $location !== '') {
        $title = sce_text('location', ['value' => $location]);
        $items[] = '<span class="comment-enhancer-meta__item" role="listitem" data-kind="location" aria-label="' . h($title) . '" title="' . h($title) . '">' . h($location) . '</span>';
    }

    if ($items === []) {
        return $existing;
    }

    return $existing
        . '<span class="comment-enhancer-meta" role="list" aria-label="' . h(sce_text('group')) . '">'
        . implode('', $items)
        . '</span>';
}

function sce_backfill_locations(int $limit = 5): int
{
    $limit = max(1, min(10, $limit));
    $now = time();
    $processed = 0;
    $scanned = 0;
    $cursor = (int)(sce_settings()['backfill_cursor'] ?? PHP_INT_MAX);
    $cursor = $cursor > 0 ? $cursor : PHP_INT_MAX;
    while ($processed < $limit && $scanned < 1000) {
        $rows = all_rows(
            "SELECT id, ip_address
             FROM comments
             WHERE status = 'approved' AND ip_address <> '' AND id < ?
             ORDER BY id DESC
             LIMIT 200",
            [$cursor]
        );
        if ($rows === []) {
            $cursor = PHP_INT_MAX;
            break;
        }
        foreach ($rows as $row) {
            $cursor = (int)$row['id'];
            $scanned++;
            $ip = trim((string)($row['ip_address'] ?? ''));
            if (sce_ip_scope($ip) !== 'public') {
                continue;
            }
            $cacheKey = sce_cache_key_for_ip($ip);
            $cached = one(
                'SELECT status, checked_at, attempted_at FROM comment_enhancer_ip_cache WHERE ip_hash = ?',
                [$cacheKey]
            );
            if (is_array($cached)) {
                $fresh = (string)($cached['status'] ?? '') === 'ok'
                    && (int)($cached['checked_at'] ?? 0) >= $now - SCE_LOCATION_CACHE_TTL;
                $recentAttempt = (int)($cached['attempted_at'] ?? 0) >= $now - SCE_LOCATION_FAILURE_TTL;
                if ($fresh || $recentAttempt) {
                    continue;
                }
            }
            sce_location_for_ip($ip, true);
            $processed++;
            if ($processed >= $limit) {
                break;
            }
            if ($scanned >= 1000) {
                break;
            }
        }
    }
    sce_save_settings(['backfill_cursor' => (string)$cursor]);
    return $processed;
}

function sce_render_settings_page(): void
{
    require_admin();
    $settings = sce_settings();
    $stats = one(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'ok' THEN 1 ELSE 0 END), 0) AS success_count,
            COALESCE(SUM(CASE WHEN status <> 'ok' THEN 1 ELSE 0 END), 0) AS failed_count
         FROM comment_enhancer_ip_cache"
    ) ?? [];
    $returnUrl = script_url() . '?a=admin_comment_enhancer';

    ob_start();
    ?>
    <div class="admin-shell">
      <?= render_admin_sidebar('plugins') ?>
      <div class="admin-main">
        <?= render_admin_topbar(sce_text('settings_title')) ?>
        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header"><h2><?= h(sce_text('settings_heading')) ?></h2><p class="panel__meta"><?= h(sce_text('settings_description')) ?></p></div>
          <div class="panel__body">
            <form class="form-stack" method="post" action="<?= h(script_url() . '?a=save_comment_enhancer') ?>">
              <?= csrf_field() ?>
              <label class="setting-option"><input name="online_lookup" type="checkbox" value="1"<?= (string)$settings['online_lookup'] === '1' ? ' checked' : '' ?>><span><strong><?= h(sce_text('online_label')) ?></strong><small><?= h(sce_text('online_hint')) ?></small></span></label>
              <div class="action-row"><button class="button" type="submit"><?= h(sce_text('save')) ?></button></div>
            </form>
          </div>
        </section>
        <section class="panel admin-list-panel admin-animate admin-animate--3">
          <div class="panel__header"><h2><?= h(sce_text('cache_title')) ?></h2><p class="panel__meta"><?= h(sce_text('cache_summary', [
              'success' => (int)($stats['success_count'] ?? 0),
              'failed' => (int)($stats['failed_count'] ?? 0),
          ])) ?></p></div>
          <div class="panel__body">
            <p class="field-hint"><?= h(sce_text('backfill_hint')) ?></p>
            <div class="action-row">
              <form method="post" action="<?= h(script_url() . '?a=backfill_comment_enhancer') ?>"><?= csrf_field() ?><button class="button" type="submit"><?= h(sce_text('backfill')) ?></button></form>
              <form method="post" action="<?= h(script_url() . '?a=clear_comment_enhancer_cache') ?>"><?= csrf_field() ?><button class="button button--ghost" type="submit"><?= h(sce_text('clear_cache')) ?></button></form>
            </div>
          </div>
        </section>
      </div>
    </div>
    <?php
    render_layout(sce_text('settings_title'), (string)ob_get_clean(), [
        'active' => 'plugins',
        'wide' => true,
        'description' => sce_text('settings_description'),
    ]);
}

function sce_handle_request(array $context): void
{
    $action = (string)($context['action'] ?? '');
    $returnUrl = script_url() . '?a=admin_comment_enhancer';
    if ($action === 'admin_comment_enhancer') {
        sce_render_settings_page();
        exit;
    }
    if ($action === 'save_comment_enhancer') {
        require_admin_post($returnUrl);
        sce_save_settings(['online_lookup' => isset($_POST['online_lookup']) ? '1' : '0']);
        set_flash('success', sce_text('saved'));
        redirect_to($returnUrl, 303);
    }
    if ($action === 'backfill_comment_enhancer') {
        require_admin_post($returnUrl);
        if ((string)(sce_settings()['online_lookup'] ?? '0') !== '1') {
            set_flash('error', sce_text('online_required'));
        } else {
            $processed = sce_backfill_locations();
            set_flash('success', sce_text('backfill_done', ['count' => $processed]));
        }
        redirect_to($returnUrl, 303);
    }
    if ($action === 'clear_comment_enhancer_cache') {
        require_admin_post($returnUrl);
        q('DELETE FROM comment_enhancer_ip_cache');
        sce_save_settings(['backfill_cursor' => (string)PHP_INT_MAX]);
        set_flash('success', sce_text('cache_cleared'));
        redirect_to($returnUrl, 303);
    }
}

function sce_stylesheet_link(array $context): string
{
    $file = __DIR__ . '/assets/comment-enhancer.css';
    $url = plugin_asset_url('comment-enhancer', 'assets/comment-enhancer.css');
    if ($url === '' || !is_file($file)) {
        return '';
    }

    return '<link rel="stylesheet" href="' . h($url . '?v=' . rawurlencode((string)filemtime($file))) . '">';
}

function sce_script_tag(array $context): string
{
    $file = __DIR__ . '/assets/comment-enhancer.js';
    $url = plugin_asset_url('comment-enhancer', 'assets/comment-enhancer.js');
    if ($url === '' || !is_file($file)) {
        return '';
    }

    return '<script src="' . h($url . '?v=' . rawurlencode((string)filemtime($file))) . '"></script>';
}

add_plugin_action('plugins_loaded', 'sce_install');
add_plugin_action('request', 'sce_handle_request');
add_plugin_action('comment_created', 'sce_cache_new_comment_location', 20);
add_plugin_action('comment_status_changed', 'sce_handle_comment_status_changed', 20);
add_plugin_action('plugin_status_changed', 'sce_handle_plugin_status_changed', 20);
add_plugin_filter('comment_identity_html', 'sce_render_comment_identity', 10);
add_plugin_filter('comment_meta_html', 'sce_render_comment_meta', 10);
add_theme_action('head', 'sce_stylesheet_link', 30);
add_theme_action('body_close', 'sce_script_tag', 30);
