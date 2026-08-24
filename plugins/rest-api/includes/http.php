<?php
declare(strict_types=1);

function sblog_rest_api_claim_request(): void
{
    $route = trim((string)($_GET['rest_route'] ?? ''));
    if ($route === '') {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return;
        }
        $path = '/' . ltrim(str_replace('\\', '/', rawurldecode($path)), '/');
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($base !== '' && $base !== '/' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        if (!preg_match('#^/wp-json(?:/(.*))?/?$#i', $path, $matches)) {
            return;
        }
        $route = '/' . trim((string)($matches[1] ?? ''), '/');
    }

    if ($route !== '/' && !preg_match('#^/wp/v2(?:/|$)#', $route)) {
        return;
    }

    $GLOBALS['sblog_rest_api_route'] = $route === '' ? '/' : '/' . trim($route, '/');
    $_GET['a'] = SBLOG_REST_API_ACTION;
    $_REQUEST['a'] = SBLOG_REST_API_ACTION;

    $query = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    $_SERVER['REQUEST_URI'] = script_url() . (is_string($query) && $query !== '' ? '?' . $query : '');
}

function sblog_rest_api_install(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS rest_api_settings(
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )"
    );
    db()->exec(
        "CREATE TABLE IF NOT EXISTS rest_api_tokens(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            token_hash TEXT NOT NULL,
            uuid TEXT NOT NULL UNIQUE,
            created_at INTEGER NOT NULL,
            last_used_at INTEGER NOT NULL DEFAULT 0,
            last_ip TEXT NOT NULL DEFAULT '',
            revoked_at INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
    db()->exec(
        "CREATE TABLE IF NOT EXISTS rest_api_tags(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label TEXT NOT NULL UNIQUE COLLATE NOCASE,
            slug TEXT NOT NULL UNIQUE,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )"
    );
    db()->exec('CREATE INDEX IF NOT EXISTS idx_rest_api_tokens_user ON rest_api_tokens(user_id, revoked_at, id)');
    if (sblog_rest_api_setting('tag_map_initialized', '0') !== '1') {
        sblog_rest_api_sync_tags();
        sblog_rest_api_save_settings(['tag_map_initialized' => '1']);
    }
}

function sblog_rest_api_setting(string $name, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }
    $value = val('SELECT value FROM rest_api_settings WHERE name = ?', [$name]);
    return $cache[$name] = is_string($value) ? $value : $default;
}

function sblog_rest_api_save_settings(array $values): void
{
    $statement = db()->prepare('INSERT OR REPLACE INTO rest_api_settings(name, value) VALUES(?, ?)');
    foreach ($values as $name => $value) {
        $statement->execute([(string)$name, (string)$value]);
    }
}

function sblog_rest_api_base_url(): string
{
    return rtrim(site_root_url(), '/') . '/wp-json';
}

function sblog_rest_api_url(string $route = '/'): string
{
    return sblog_rest_api_base_url() . ($route === '/' ? '/' : '/' . ltrim($route, '/'));
}

function sblog_rest_api_method(): string
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $override = strtoupper(trim((string)($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_REQUEST['_method'] ?? '')));
    return in_array($override, ['PUT', 'PATCH', 'DELETE'], true) ? $override : $method;
}

function sblog_rest_api_input(): array
{
    static $input = null;
    if (is_array($input)) {
        return $input;
    }
    $input = [];
    $type = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($type === 'application/json' || str_ends_with($type, '+json')) {
        $raw = (string)file_get_contents('php://input');
        if ($raw !== '') {
            try {
                $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    sblog_rest_api_error('rest_invalid_json', 'JSON body must be an object.', 400);
                }
                $input = $decoded;
            } catch (JsonException $exception) {
                sblog_rest_api_error('rest_invalid_json', 'Invalid JSON body: ' . $exception->getMessage(), 400);
            }
        }
    } elseif ($_POST !== []) {
        $input = $_POST;
    }
    return $input;
}

function sblog_rest_api_param(string $name, mixed $default = null): mixed
{
    $input = sblog_rest_api_input();
    return array_key_exists($name, $input) ? $input[$name] : ($_GET[$name] ?? $default);
}

function sblog_rest_api_int_list(mixed $value): array
{
    $parts = is_array($value) ? $value : preg_split('/\s*,\s*/', trim((string)$value));
    if (!is_array($parts)) {
        return [];
    }
    return array_values(array_unique(array_filter(array_map('intval', $parts), static fn(int $id): bool => $id > 0)));
}

function sblog_rest_api_bool(mixed $value, bool $default = false): bool
{
    if ($value === null || $value === '') {
        return $default;
    }
    $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $filtered ?? $default;
}

function sblog_rest_api_is_secure(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if (($https !== '' && $https !== 'off') || (string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || sblog_rest_api_setting('allow_http_auth', '0') === '1';
}

function sblog_rest_api_authorization_header(): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (trim((string)($_SERVER[$key] ?? '')) !== '') {
            return trim((string)$_SERVER[$key]);
        }
    }
    if (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
        return 'Basic ' . base64_encode((string)$_SERVER['PHP_AUTH_USER'] . ':' . (string)$_SERVER['PHP_AUTH_PW']);
    }
    return '';
}

function sblog_rest_api_user(bool $required = false): ?array
{
    static $resolved = false;
    static $user = null;
    static $error = null;
    if (!$resolved) {
        $resolved = true;
        $header = sblog_rest_api_authorization_header();
        if ($header !== '') {
            if (!sblog_rest_api_is_secure()) {
                $error = ['rest_application_password_requires_https', 'Application Passwords require HTTPS.', 401];
            } elseif (!preg_match('/^Basic\s+(.+)$/i', $header, $matches)) {
                $error = ['rest_cannot_use_authentication', 'Only HTTP Basic Authentication is supported.', 401];
            } else {
                $decoded = base64_decode(trim($matches[1]), true);
                if (!is_string($decoded) || !str_contains($decoded, ':')) {
                    $error = ['rest_application_password_malformed', 'The Authorization header is malformed.', 401];
                } else {
                    [$username, $password] = explode(':', $decoded, 2);
                    $password = preg_replace('/\s+/', '', $password) ?? '';
                    $account = one(
                        'SELECT id, username, nickname, email, avatar_url, website_url, signature, created_at FROM users WHERE username = ? OR lower(email) = lower(?) LIMIT 1',
                        [trim($username), trim($username)]
                    );
                    if ($account !== null && $password !== '') {
                        foreach (all_rows('SELECT id, token_hash FROM rest_api_tokens WHERE user_id = ? AND revoked_at = 0 ORDER BY id DESC', [(int)$account['id']]) as $token) {
                            if (password_verify($password, (string)$token['token_hash'])) {
                                q('UPDATE rest_api_tokens SET last_used_at = ?, last_ip = ? WHERE id = ?', [time(), client_ip_address(), (int)$token['id']]);
                                $account['application_password_id'] = (int)$token['id'];
                                $user = $account;
                                break;
                            }
                        }
                    }
                    if ($user === null) {
                        $error = ['rest_application_password_invalid', 'Invalid username or Application Password.', 401];
                    }
                }
            }
        }
    }
    if (is_array($error) && ($required || sblog_rest_api_authorization_header() !== '')) {
        sblog_rest_api_error($error[0], $error[1], $error[2], [], ['WWW-Authenticate' => 'Basic realm="SBlog REST API"']);
    }
    if ($required && $user === null) {
        sblog_rest_api_error('rest_not_logged_in', 'Authentication is required.', 401, [], ['WWW-Authenticate' => 'Basic realm="SBlog REST API"']);
    }
    return $user;
}

function sblog_rest_api_require_write(): array
{
    if (sblog_rest_api_setting('write_enabled', '1') !== '1') {
        sblog_rest_api_error('rest_cannot_create', 'Write access is disabled by the site administrator.', 403);
    }
    return sblog_rest_api_user(true) ?? [];
}

function sblog_rest_api_apply_fields(mixed $data): mixed
{
    $raw = trim((string)($_GET['_fields'] ?? ''));
    if ($raw === '') {
        return $data;
    }
    $fields = array_fill_keys(array_filter(array_map('trim', explode(',', $raw))), true);
    $filter = static function (array $item) use ($fields): array {
        return array_intersect_key($item, $fields);
    };
    if (is_array($data) && array_keys($data) === range(0, count($data) - 1)) {
        return array_map(static fn($item) => is_array($item) ? $filter($item) : $item, $data);
    }
    return is_array($data) ? $filter($data) : $data;
}

function sblog_rest_api_send(mixed $data, int $status = 200, array $headers = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    header('X-Content-Type-Options: nosniff');
    header('Vary: Authorization');
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    $payload = $status < 400 ? sblog_rest_api_apply_fields($data) : $data;
    if (sblog_rest_api_method() !== 'HEAD') {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

function sblog_rest_api_error(string $code, string $message, int $status = 400, array $details = [], array $headers = []): never
{
    sblog_rest_api_send([
        'code' => $code,
        'message' => $message,
        'data' => ['status' => $status] + $details,
    ], $status, $headers);
}

function sblog_rest_api_collection(mixed $items, int $total, int $page, int $perPage, string $route): never
{
    $totalPages = $total > 0 ? (int)ceil($total / max(1, $perPage)) : 0;
    if ($page > 1 && $totalPages > 0 && $page > $totalPages) {
        sblog_rest_api_error('rest_post_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
    }
    $headers = [
        'X-WP-Total' => (string)$total,
        'X-WP-TotalPages' => (string)$totalPages,
    ];
    $links = [];
    $query = $_GET;
    unset($query['a'], $query['rest_route']);
    if ($page > 1) {
        $query['page'] = $page - 1;
        $links[] = '<' . sblog_rest_api_url($route) . '?' . http_build_query($query) . '>; rel="prev"';
    }
    if ($page < $totalPages) {
        $query['page'] = $page + 1;
        $links[] = '<' . sblog_rest_api_url($route) . '?' . http_build_query($query) . '>; rel="next"';
    }
    if ($links !== []) {
        $headers['Link'] = implode(', ', $links);
    }
    sblog_rest_api_send($items, 200, $headers);
}

function sblog_rest_api_pagination(): array
{
    $page = max(1, (int)sblog_rest_api_param('page', 1));
    $perPage = (int)sblog_rest_api_param('per_page', 10);
    if ($perPage < 1 || $perPage > 100) {
        sblog_rest_api_error('rest_invalid_param', 'per_page must be between 1 and 100.', 400, ['params' => ['per_page' => 'Invalid value.']]);
    }
    return [$page, $perPage, ($page - 1) * $perPage];
}

function sblog_rest_api_handle_request(array $context): void
{
    $action = (string)($context['action'] ?? '');
    if (sblog_rest_api_handle_admin_request($action)) {
        return;
    }
    if ($action !== SBLOG_REST_API_ACTION) {
        return;
    }

    $route = (string)($GLOBALS['sblog_rest_api_route'] ?? $_GET['rest_route'] ?? '/');
    $route = '/' . trim($route, '/');
    if ($route === '//') {
        $route = '/';
    }
    try {
        sblog_rest_api_dispatch($route, sblog_rest_api_method());
    } catch (Throwable $exception) {
        error_log('REST API request failed: ' . $exception->getMessage());
        sblog_rest_api_error('rest_unknown_error', 'An unexpected error occurred while processing the REST API request.', 500);
    }
}
