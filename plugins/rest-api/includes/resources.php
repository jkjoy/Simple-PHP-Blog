<?php
declare(strict_types=1);

function sblog_rest_api_dispatch(string $route, string $method): never
{
    if ($method === 'OPTIONS') {
        sblog_rest_api_send([
            'namespace' => str_starts_with($route, '/wp/v2') ? 'wp/v2' : '',
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        ], 200, ['Allow' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS']);
    }
    if ($route === '/') {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
        }
        sblog_rest_api_send(sblog_rest_api_index());
    }
    if ($route === '/wp/v2') {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
        }
        sblog_rest_api_read_access();
        sblog_rest_api_send(sblog_rest_api_namespace_index());
    }

    if (preg_match('#^/wp/v2/(posts|pages)(?:/(\d+))?$#', $route, $matches)) {
        sblog_rest_api_content_route($matches[1] === 'pages' ? 'page' : 'post', isset($matches[2]) ? (int)$matches[2] : null, $method);
    }
    if (preg_match('#^/wp/v2/(categories|tags)(?:/(\d+))?$#', $route, $matches)) {
        sblog_rest_api_term_route($matches[1], isset($matches[2]) ? (int)$matches[2] : null, $method);
    }
    if (preg_match('#^/wp/v2/comments(?:/(\d+))?$#', $route, $matches)) {
        sblog_rest_api_comment_route(isset($matches[1]) ? (int)$matches[1] : null, $method);
    }
    if (preg_match('#^/wp/v2/media(?:/(\d+))?$#', $route, $matches)) {
        sblog_rest_api_media_route(isset($matches[1]) ? (int)$matches[1] : null, $method);
    }
    if ($route === '/wp/v2/users/me') {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
        }
        sblog_rest_api_send(sblog_rest_api_prepare_user(sblog_rest_api_user(true) ?? [], true));
    }
    if (preg_match('#^/wp/v2/users(?:/(\d+))?$#', $route, $matches)) {
        sblog_rest_api_user_route(isset($matches[1]) ? (int)$matches[1] : null, $method);
    }
    if (preg_match('#^/wp/v2/types(?:/(post|page|attachment))?$#', $route, $matches)) {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
        }
        sblog_rest_api_read_access();
        $types = sblog_rest_api_types();
        if (isset($matches[1])) {
            sblog_rest_api_send($types[$matches[1]]);
        }
        sblog_rest_api_send($types);
    }
    if (preg_match('#^/wp/v2/statuses(?:/(publish|future|draft))?$#', $route, $matches)) {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
        }
        sblog_rest_api_read_access();
        $statuses = sblog_rest_api_statuses();
        if (isset($matches[1])) {
            sblog_rest_api_send($statuses[$matches[1]]);
        }
        sblog_rest_api_send($statuses);
    }
    if (preg_match('#^/wp/v2/taxonomies(?:/(category|post_tag))?$#', $route, $matches)) {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
        }
        sblog_rest_api_read_access();
        $taxonomies = sblog_rest_api_taxonomies();
        if (isset($matches[1])) {
            sblog_rest_api_send($taxonomies[$matches[1]]);
        }
        sblog_rest_api_send($taxonomies);
    }
    sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
}

function sblog_rest_api_read_access(): ?array
{
    $user = sblog_rest_api_user(false);
    if ($user === null && sblog_rest_api_setting('public_enabled', '1') !== '1') {
        return sblog_rest_api_user(true);
    }
    return $user;
}

function sblog_rest_api_route_definition(array $methods): array
{
    return ['namespace' => 'wp/v2', 'methods' => $methods, 'endpoints' => [['methods' => $methods, 'args' => new stdClass()]], '_links' => ['self' => [['href' => sblog_rest_api_url('/wp/v2')]]]];
}

function sblog_rest_api_index(): array
{
    return [
        'name' => setting('site_name'),
        'description' => setting('site_description', setting('site_tagline')),
        'url' => site_root_url(),
        'home' => site_root_url(),
        'gmt_offset' => (float)(date('Z') / 3600),
        'timezone_string' => date_default_timezone_get(),
        'namespaces' => ['wp/v2'],
        'authentication' => [
            'application-passwords' => [
                'endpoints' => ['authorization' => absolute_url(sblog_rest_api_admin_url())],
            ],
        ],
        'routes' => sblog_rest_api_routes(),
        'site_logo' => 0,
        'site_icon' => 0,
        '_links' => ['help' => [['href' => 'https://developer.wordpress.org/rest-api/']]],
    ];
}

function sblog_rest_api_namespace_index(): array
{
    return [
        'namespace' => 'wp/v2',
        'routes' => array_filter(sblog_rest_api_routes(), static fn(string $key): bool => str_starts_with($key, '/wp/v2'), ARRAY_FILTER_USE_KEY),
        '_links' => ['up' => [['href' => sblog_rest_api_url('/')]]],
    ];
}

function sblog_rest_api_routes(): array
{
    $routes = ['/wp/v2' => sblog_rest_api_route_definition(['GET'])];
    foreach (['posts', 'pages', 'categories', 'tags', 'comments', 'media', 'users'] as $resource) {
        $methods = in_array($resource, ['comments', 'users'], true) ? ['GET'] : ['GET', 'POST'];
        $routes['/wp/v2/' . $resource] = sblog_rest_api_route_definition($methods);
        $routes['/wp/v2/' . $resource . '/(?P<id>[\d]+)'] = sblog_rest_api_route_definition(in_array($resource, ['comments', 'users'], true) ? ['GET'] : ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
    }
    $routes['/wp/v2/users/me'] = sblog_rest_api_route_definition(['GET']);
    foreach (['types', 'statuses', 'taxonomies'] as $resource) {
        $routes['/wp/v2/' . $resource] = sblog_rest_api_route_definition(['GET']);
    }
    $routes['/wp/v2/types/(?P<type>[a-z_]+)'] = sblog_rest_api_route_definition(['GET']);
    $routes['/wp/v2/statuses/(?P<status>[a-z_]+)'] = sblog_rest_api_route_definition(['GET']);
    $routes['/wp/v2/taxonomies/(?P<taxonomy>[a-z_]+)'] = sblog_rest_api_route_definition(['GET']);
    return $routes;
}

function sblog_rest_api_types(): array
{
    return [
        'post' => ['description' => 'Posts', 'hierarchical' => false, 'rest_base' => 'posts', 'rest_namespace' => 'wp/v2', 'slug' => 'post', 'name' => 'Posts', 'taxonomies' => ['category', 'post_tag'], '_links' => ['collection' => [['href' => sblog_rest_api_url('/wp/v2/types')]], 'wp:items' => [['href' => sblog_rest_api_url('/wp/v2/posts')]]]],
        'page' => ['description' => 'Pages', 'hierarchical' => false, 'rest_base' => 'pages', 'rest_namespace' => 'wp/v2', 'slug' => 'page', 'name' => 'Pages', 'taxonomies' => [], '_links' => ['collection' => [['href' => sblog_rest_api_url('/wp/v2/types')]], 'wp:items' => [['href' => sblog_rest_api_url('/wp/v2/pages')]]]],
        'attachment' => ['description' => 'Media', 'hierarchical' => false, 'rest_base' => 'media', 'rest_namespace' => 'wp/v2', 'slug' => 'attachment', 'name' => 'Media', 'taxonomies' => [], '_links' => ['collection' => [['href' => sblog_rest_api_url('/wp/v2/types')]], 'wp:items' => [['href' => sblog_rest_api_url('/wp/v2/media')]]]],
    ];
}

function sblog_rest_api_statuses(): array
{
    return [
        'publish' => ['name' => 'Published', 'public' => true, 'queryable' => true, 'slug' => 'publish', '_links' => ['archives' => [['href' => sblog_rest_api_url('/wp/v2/posts')]]]],
        'future' => ['name' => 'Scheduled', 'public' => false, 'queryable' => false, 'slug' => 'future', '_links' => ['archives' => [['href' => sblog_rest_api_url('/wp/v2/posts')]]]],
        'draft' => ['name' => 'Draft', 'public' => false, 'queryable' => false, 'slug' => 'draft', '_links' => ['archives' => [['href' => sblog_rest_api_url('/wp/v2/posts')]]]],
    ];
}

function sblog_rest_api_taxonomies(): array
{
    return [
        'category' => ['name' => 'Categories', 'slug' => 'category', 'rest_base' => 'categories', 'rest_namespace' => 'wp/v2', 'types' => ['post'], 'hierarchical' => true, '_links' => ['collection' => [['href' => sblog_rest_api_url('/wp/v2/taxonomies')]], 'wp:items' => [['href' => sblog_rest_api_url('/wp/v2/categories')]]]],
        'post_tag' => ['name' => 'Tags', 'slug' => 'post_tag', 'rest_base' => 'tags', 'rest_namespace' => 'wp/v2', 'types' => ['post'], 'hierarchical' => false, '_links' => ['collection' => [['href' => sblog_rest_api_url('/wp/v2/taxonomies')]], 'wp:items' => [['href' => sblog_rest_api_url('/wp/v2/tags')]]]],
    ];
}

function sblog_rest_api_wp_status(array $row): string
{
    if ((string)$row['status'] !== 'published') {
        return 'draft';
    }
    return (int)$row['published_at'] > time() ? 'future' : 'publish';
}

function sblog_rest_api_date(int $timestamp, bool $gmt = false): ?string
{
    if ($timestamp < 1) {
        return null;
    }
    return $gmt ? gmdate('Y-m-d\TH:i:s', $timestamp) : date('Y-m-d\TH:i:s', $timestamp);
}

function sblog_rest_api_rendered_excerpt(string $excerpt): string
{
    $plain = markdown_to_plain($excerpt);
    return $plain === '' ? '' : '<p>' . h($plain) . "</p>\n";
}

function sblog_rest_api_prepare_content(array $row, bool $editContext = false): array
{
    $kind = content_kind($row);
    $restBase = $kind === 'page' ? 'pages' : 'posts';
    $tagIds = $kind === 'post' ? sblog_rest_api_tag_ids_for_post($row) : [];
    $authorId = (int)($row['author_id'] ?? 0);
    $result = [
        'id' => (int)$row['id'],
        'date' => sblog_rest_api_date((int)$row['published_at']),
        'date_gmt' => sblog_rest_api_date((int)$row['published_at'], true),
        'guid' => ['rendered' => absolute_url(content_permalink($row))],
        'modified' => sblog_rest_api_date((int)$row['updated_at']),
        'modified_gmt' => sblog_rest_api_date((int)$row['updated_at'], true),
        'slug' => (string)$row['slug'],
        'status' => sblog_rest_api_wp_status($row),
        'type' => $kind,
        'link' => absolute_url(content_permalink($row)),
        'title' => ['rendered' => h((string)$row['title'])],
        'content' => ['rendered' => markdown_to_html((string)$row['content']), 'protected' => false],
        'excerpt' => ['rendered' => sblog_rest_api_rendered_excerpt((string)$row['excerpt']), 'protected' => false],
        'author' => $authorId,
        'featured_media' => 0,
        'comment_status' => content_allows_comments($row) ? 'open' : 'closed',
        'ping_status' => 'closed',
        'sticky' => $kind === 'post' && (int)$row['is_pinned'] === 1,
        'template' => '',
        'format' => (string)($row['post_format'] ?? 'text') === 'image' ? 'image' : 'standard',
        'meta' => [],
        'categories' => $kind === 'post' && (int)($row['category_id'] ?? 0) > 0 ? [(int)$row['category_id']] : [],
        'tags' => $tagIds,
        '_links' => [
            'self' => [['href' => sblog_rest_api_url('/wp/v2/' . $restBase . '/' . (int)$row['id'])]],
            'collection' => [['href' => sblog_rest_api_url('/wp/v2/' . $restBase)]],
            'about' => [['href' => sblog_rest_api_url('/wp/v2/types/' . $kind)]],
            'author' => [['embeddable' => true, 'href' => sblog_rest_api_url('/wp/v2/users/' . $authorId)]],
            'replies' => [['embeddable' => true, 'href' => sblog_rest_api_url('/wp/v2/comments?post=' . (int)$row['id'])]],
        ],
    ];
    if ($kind === 'post') {
        $result['_links']['wp:term'] = [
            ['taxonomy' => 'category', 'embeddable' => true, 'href' => sblog_rest_api_url('/wp/v2/categories?post=' . (int)$row['id'])],
            ['taxonomy' => 'post_tag', 'embeddable' => true, 'href' => sblog_rest_api_url('/wp/v2/tags?post=' . (int)$row['id'])],
        ];
    }
    if ($editContext) {
        $result['title']['raw'] = (string)$row['title'];
        $result['content']['raw'] = (string)$row['content'];
        $result['excerpt']['raw'] = (string)$row['excerpt'];
    }
    if (sblog_rest_api_bool($_GET['_embed'] ?? false)) {
        $author = $authorId > 0 ? one('SELECT * FROM users WHERE id = ?', [$authorId]) : null;
        $result['_embedded'] = [];
        if ($author !== null) {
            $result['_embedded']['author'] = [sblog_rest_api_prepare_user($author, false)];
        }
    }
    return $result;
}

function sblog_rest_api_content_route(string $kind, ?int $id, string $method): never
{
    $restBase = $kind === 'page' ? 'pages' : 'posts';
    if (in_array($method, ['GET', 'HEAD'], true)) {
        $user = sblog_rest_api_read_access();
        $editContext = (string)sblog_rest_api_param('context', 'view') === 'edit';
        if ($editContext && $user === null) {
            sblog_rest_api_user(true);
        }
        if ($id !== null) {
            $row = one('SELECT * FROM posts WHERE id = ? AND kind = ?', [$id, $kind]);
            if ($row === null || (!$editContext && !is_live_content($row))) {
                sblog_rest_api_error('rest_post_invalid_id', 'Invalid post ID.', 404);
            }
            sblog_rest_api_send(sblog_rest_api_prepare_content($row, $editContext));
        }
        sblog_rest_api_content_collection($kind, $editContext);
    }
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
    }
    $user = sblog_rest_api_require_write();
    if ($method === 'DELETE') {
        if ($id === null) {
            sblog_rest_api_error('rest_no_route', 'A post ID is required.', 404);
        }
        $row = one('SELECT * FROM posts WHERE id = ? AND kind = ?', [$id, $kind]);
        if ($row === null) {
            sblog_rest_api_error('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        $previous = sblog_rest_api_prepare_content($row, true);
        if (sblog_rest_api_bool(sblog_rest_api_param('force', false))) {
            q('DELETE FROM posts WHERE id = ?', [$id]);
            sblog_rest_api_send(['deleted' => true, 'previous' => $previous]);
        }
        q('UPDATE posts SET status = ?, updated_at = ? WHERE id = ?', ['draft', time(), $id]);
        $row['status'] = 'draft';
        $row['updated_at'] = time();
        sblog_rest_api_send(sblog_rest_api_prepare_content($row, true));
    }
    $existing = $id !== null ? one('SELECT * FROM posts WHERE id = ? AND kind = ?', [$id, $kind]) : null;
    if ($id !== null && $existing === null) {
        sblog_rest_api_error('rest_post_invalid_id', 'Invalid post ID.', 404);
    }
    $savedId = sblog_rest_api_save_content($kind, sblog_rest_api_input(), $existing, $user);
    $saved = one('SELECT * FROM posts WHERE id = ?', [$savedId]);
    if ($saved === null) {
        sblog_rest_api_error('rest_cannot_create', 'The post could not be read after saving.', 500);
    }
    sblog_rest_api_send(sblog_rest_api_prepare_content($saved, true), $existing === null ? 201 : 200, ['Location' => sblog_rest_api_url('/wp/v2/' . $restBase . '/' . $savedId)]);
}

function sblog_rest_api_content_collection(string $kind, bool $editContext): never
{
    [$page, $perPage, $offset] = sblog_rest_api_pagination();
    $where = ['p.kind = ?'];
    $params = [$kind];
    $authenticated = sblog_rest_api_user(false) !== null;
    $requestedStatuses = array_filter(array_map('trim', explode(',', (string)sblog_rest_api_param('status', 'publish'))));
    if (!$authenticated || !$editContext) {
        $where[] = "p.status = 'published'";
        $where[] = 'p.published_at > 0';
        $where[] = 'p.published_at <= ?';
        $params[] = time();
    } elseif (!in_array('any', $requestedStatuses, true)) {
        $statusParts = [];
        foreach ($requestedStatuses as $status) {
            if ($status === 'publish') {
                $statusParts[] = "(p.status = 'published' AND p.published_at <= " . time() . ')';
            } elseif ($status === 'future') {
                $statusParts[] = "(p.status = 'published' AND p.published_at > " . time() . ')';
            } elseif ($status === 'draft') {
                $statusParts[] = "p.status = 'draft'";
            }
        }
        if ($statusParts !== []) {
            $where[] = '(' . implode(' OR ', $statusParts) . ')';
        } elseif ($requestedStatuses !== []) {
            sblog_rest_api_error('rest_invalid_param', 'One or more statuses are invalid.', 400, ['params' => ['status' => 'Invalid status.']]);
        }
    }

    $search = trim((string)sblog_rest_api_param('search', ''));
    if ($search !== '') {
        $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)';
        $needle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        array_push($params, $needle, $needle, $needle);
    }
    $include = sblog_rest_api_int_list(sblog_rest_api_param('include', []));
    if ($include !== []) {
        $where[] = 'p.id IN (' . implode(',', array_fill(0, count($include), '?')) . ')';
        array_push($params, ...$include);
    }
    $exclude = sblog_rest_api_int_list(sblog_rest_api_param('exclude', []));
    if ($exclude !== []) {
        $where[] = 'p.id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
        array_push($params, ...$exclude);
    }
    $slugs = array_filter(array_map('trim', explode(',', (string)sblog_rest_api_param('slug', ''))));
    if ($slugs !== []) {
        $where[] = 'p.slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')';
        array_push($params, ...$slugs);
    }
    $authors = sblog_rest_api_int_list(sblog_rest_api_param('author', []));
    if ($authors !== []) {
        $where[] = 'p.author_id IN (' . implode(',', array_fill(0, count($authors), '?')) . ')';
        array_push($params, ...$authors);
    }
    if ($kind === 'post') {
        $categories = sblog_rest_api_int_list(sblog_rest_api_param('categories', []));
        if ($categories !== []) {
            $where[] = 'p.category_id IN (' . implode(',', array_fill(0, count($categories), '?')) . ')';
            array_push($params, ...$categories);
        }
        $tagIds = sblog_rest_api_int_list(sblog_rest_api_param('tags', []));
        if ($tagIds !== []) {
            $labels = sblog_rest_api_tag_labels($tagIds);
            if ($labels === []) {
                $where[] = '1 = 0';
            } else {
                $conditions = [];
                foreach ($labels as $label) {
                    $conditions[] = "EXISTS (SELECT 1 FROM json_each(p.tags) tag WHERE lower(CAST(tag.value AS TEXT)) = lower(?))";
                    $params[] = $label;
                }
                $operator = strtolower((string)sblog_rest_api_param('tags_relation', 'or')) === 'and' ? ' AND ' : ' OR ';
                $where[] = '(' . implode($operator, $conditions) . ')';
            }
        }
    }
    foreach (['after' => '>=', 'before' => '<='] as $parameter => $operator) {
        $value = trim((string)sblog_rest_api_param($parameter, ''));
        if ($value !== '') {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                sblog_rest_api_error('rest_invalid_param', $parameter . ' is not a valid date.', 400, ['params' => [$parameter => 'Invalid date.']]);
            }
            $where[] = 'p.published_at ' . $operator . ' ?';
            $params[] = $timestamp;
        }
    }
    $order = strtoupper((string)sblog_rest_api_param('order', 'desc')) === 'ASC' ? 'ASC' : 'DESC';
    $orderColumns = ['date' => 'p.published_at', 'id' => 'p.id', 'modified' => 'p.updated_at', 'title' => 'p.title', 'slug' => 'p.slug'];
    $orderBy = $orderColumns[(string)sblog_rest_api_param('orderby', 'date')] ?? 'p.published_at';
    $whereSql = implode(' AND ', $where);
    $total = (int)val('SELECT COUNT(*) FROM posts p WHERE ' . $whereSql, $params);
    $rows = all_rows('SELECT p.* FROM posts p WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' ' . $order . ', p.id ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    $items = array_map(static fn(array $row): array => sblog_rest_api_prepare_content($row, $editContext), $rows);
    sblog_rest_api_collection($items, $total, $page, $perPage, '/wp/v2/' . ($kind === 'page' ? 'pages' : 'posts'));
}

function sblog_rest_api_field_text(mixed $value): string
{
    if (is_array($value)) {
        return (string)($value['raw'] ?? $value['rendered'] ?? '');
    }
    return (string)$value;
}

function sblog_rest_api_save_content(string $kind, array $input, ?array $existing, array $user): int
{
    $isNew = $existing === null;
    $title = array_key_exists('title', $input) ? sblog_rest_api_field_text($input['title']) : (string)($existing['title'] ?? '');
    $content = array_key_exists('content', $input) ? sblog_rest_api_field_text($input['content']) : (string)($existing['content'] ?? '');
    $excerpt = array_key_exists('excerpt', $input) ? sblog_rest_api_field_text($input['excerpt']) : (string)($existing['excerpt'] ?? '');
    $status = (string)($input['status'] ?? ($existing ? sblog_rest_api_wp_status($existing) : 'draft'));
    if (!in_array($status, ['publish', 'future', 'draft'], true)) {
        sblog_rest_api_error('rest_invalid_param', 'status must be publish, future, or draft.', 400, ['params' => ['status' => 'Unsupported status.']]);
    }
    $publishedAt = (int)($existing['published_at'] ?? 0);
    $dateValue = trim((string)($input['date_gmt'] ?? $input['date'] ?? ''));
    if ($dateValue !== '') {
        $publishedAt = strtotime($dateValue . (isset($input['date_gmt']) && !preg_match('/(?:Z|[+-]\d\d:\d\d)$/', $dateValue) ? ' UTC' : '')) ?: 0;
        if ($publishedAt < 1) {
            sblog_rest_api_error('rest_invalid_param', 'date is not valid.', 400, ['params' => ['date' => 'Invalid date.']]);
        }
    }
    if ($status === 'future' && $publishedAt <= time()) {
        sblog_rest_api_error('rest_invalid_param', 'A future post requires a future date.', 400, ['params' => ['date' => 'Date must be in the future.']]);
    }
    if ($status === 'publish' && $publishedAt < 1) {
        $publishedAt = time();
    }
    $categoryId = (int)($existing['category_id'] ?? 0);
    if ($kind === 'post' && array_key_exists('categories', $input)) {
        $categoryId = (int)(sblog_rest_api_int_list($input['categories'])[0] ?? 0);
    }
    if ($kind === 'post' && ($categoryId < 1 || !one('SELECT id FROM categories WHERE id = ?', [$categoryId]))) {
        $categoryId = (int)(val('SELECT id FROM categories ORDER BY sort_order ASC, id DESC LIMIT 1') ?: 0);
    }
    $tags = post_tags($existing ?? []);
    if ($kind === 'post' && array_key_exists('tags', $input)) {
        $tags = sblog_rest_api_tag_labels(sblog_rest_api_int_list($input['tags']));
    }
    $format = (string)($input['format'] ?? ((string)($existing['post_format'] ?? 'text') === 'image' ? 'image' : 'standard'));
    $validationInput = [
        'kind' => $kind,
        'post_format' => $kind === 'post' && $format === 'image' ? 'image' : 'text',
        'category_id' => (string)$categoryId,
        'title' => $title,
        'slug' => (string)($input['slug'] ?? $existing['slug'] ?? ''),
        'tags_input' => implode(', ', $tags),
        'excerpt' => $excerpt,
        'content' => $content,
        'status' => $status === 'draft' ? 'draft' : 'published',
        'published_at' => $publishedAt > 0 ? date('Y-m-d\TH:i', $publishedAt) : '',
        'is_pinned' => $kind === 'post' && sblog_rest_api_bool($input['sticky'] ?? ($existing['is_pinned'] ?? false)) ? '1' : '0',
        'allow_comments' => ($input['comment_status'] ?? (content_allows_comments($existing ?? ['kind' => $kind]) ? 'open' : 'closed')) === 'open' ? '1' : '0',
    ];
    [$data, $errors] = validate_post_input($validationInput, $existing);
    if ($errors !== []) {
        sblog_rest_api_error('rest_invalid_param', implode(' ', $errors), 400, ['params' => ['post' => $errors]]);
    }
    $id = save_post($data, $existing !== null ? (int)$existing['id'] : null);
    if ($isNew) {
        q('UPDATE posts SET author_id = ? WHERE id = ?', [(int)$user['id'], $id]);
    }
    return $id;
}

function sblog_rest_api_sync_tags(): void
{
    $labels = [];
    $existingLabels = [];
    $existingSlugs = [];
    foreach (all_rows('SELECT label, slug FROM rest_api_tags') as $existing) {
        $existingLabels[str_lower_u((string)$existing['label'])] = true;
        $existingSlugs[(string)$existing['slug']] = true;
    }
    foreach (all_rows('SELECT label, slug FROM tag_meta ORDER BY rowid') as $tag) {
        $label = trim((string)$tag['label']);
        if ($label !== '') {
            $labels[str_lower_u($label)] = ['label' => $label, 'slug' => (string)$tag['slug']];
        }
    }
    foreach (all_rows('SELECT tags FROM posts') as $post) {
        foreach (post_tags($post) as $label) {
            $labels[str_lower_u($label)] = ['label' => $label, 'slug' => tag_slug_for_label($label)];
        }
    }
    $now = time();
    $insert = db()->prepare('INSERT INTO rest_api_tags(label, slug, created_at, updated_at) VALUES(?,?,?,?)');
    foreach ($labels as $tag) {
        if (isset($existingLabels[str_lower_u((string)$tag['label'])]) || isset($existingSlugs[(string)$tag['slug']])) {
            continue;
        }
        $insert->execute([$tag['label'], $tag['slug'], $now, $now]);
        $existingLabels[str_lower_u((string)$tag['label'])] = true;
        $existingSlugs[(string)$tag['slug']] = true;
    }
}

function sblog_rest_api_tag_ids_for_post(array $post): array
{
    $ids = [];
    foreach (post_tags($post) as $label) {
        $row = one('SELECT id FROM rest_api_tags WHERE label = ? COLLATE NOCASE', [$label]);
        if ($row === null) {
            $now = time();
            q('INSERT OR IGNORE INTO rest_api_tags(label, slug, created_at, updated_at) VALUES(?,?,?,?)', [$label, tag_slug_for_label($label), $now, $now]);
            $row = one('SELECT id FROM rest_api_tags WHERE label = ? COLLATE NOCASE', [$label]);
        }
        if ($row !== null) {
            $ids[] = (int)$row['id'];
        }
    }
    return $ids;
}

function sblog_rest_api_tag_labels(array $ids): array
{
    if ($ids === []) {
        return [];
    }
    $rows = all_rows('SELECT id, label FROM rest_api_tags WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
    $map = [];
    foreach ($rows as $row) {
        $map[(int)$row['id']] = (string)$row['label'];
    }
    $labels = [];
    foreach ($ids as $id) {
        if (isset($map[$id])) {
            $labels[] = $map[$id];
        }
    }
    return $labels;
}

function sblog_rest_api_term_count(string $taxonomy, array $row): int
{
    if ($taxonomy === 'categories') {
        return (int)val(
            'SELECT COUNT(*) FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at > 0 AND published_at <= ?',
            ['post', (int)$row['id'], 'published', time()]
        );
    }
    $count = 0;
    foreach (all_rows('SELECT tags FROM posts WHERE kind = ? AND status = ? AND published_at > 0 AND published_at <= ?', ['post', 'published', time()]) as $post) {
        foreach (post_tags($post) as $label) {
            if (str_lower_u($label) === str_lower_u((string)$row['label'])) {
                $count++;
                break;
            }
        }
    }
    return $count;
}

function sblog_rest_api_prepare_term(string $taxonomy, array $row): array
{
    $isCategory = $taxonomy === 'categories';
    $id = (int)$row['id'];
    $name = (string)($isCategory ? $row['name'] : $row['label']);
    $result = [
        'id' => $id,
        'count' => sblog_rest_api_term_count($taxonomy, $row),
        'description' => $isCategory ? (string)$row['description'] : '',
        'link' => absolute_url(url_for($isCategory ? 'category' : 'tag', ['slug' => (string)$row['slug']])),
        'name' => $name,
        'slug' => (string)$row['slug'],
        'taxonomy' => $isCategory ? 'category' : 'post_tag',
        'meta' => [],
        '_links' => [
            'self' => [['href' => sblog_rest_api_url('/wp/v2/' . $taxonomy . '/' . $id)]],
            'collection' => [['href' => sblog_rest_api_url('/wp/v2/' . $taxonomy)]],
            'about' => [['href' => sblog_rest_api_url('/wp/v2/taxonomies/' . ($isCategory ? 'category' : 'post_tag'))]],
            'wp:post_type' => [['href' => sblog_rest_api_url('/wp/v2/posts?' . ($isCategory ? 'categories' : 'tags') . '=' . $id)]],
        ],
    ];
    if ($isCategory) {
        $result['parent'] = 0;
    }
    return $result;
}

function sblog_rest_api_term_route(string $taxonomy, ?int $id, string $method): never
{
    if ($taxonomy === 'tags') {
        sblog_rest_api_sync_tags();
    }
    if (in_array($method, ['GET', 'HEAD'], true)) {
        sblog_rest_api_read_access();
        if ($id !== null) {
            $row = $taxonomy === 'categories'
                ? one('SELECT * FROM categories WHERE id = ?', [$id])
                : one('SELECT * FROM rest_api_tags WHERE id = ?', [$id]);
            if ($row === null) {
                sblog_rest_api_error('rest_term_invalid', 'Term does not exist.', 404);
            }
            sblog_rest_api_send(sblog_rest_api_prepare_term($taxonomy, $row));
        }
        sblog_rest_api_term_collection($taxonomy);
    }
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
    }
    sblog_rest_api_require_write();
    if ($method === 'DELETE') {
        if ($id === null) {
            sblog_rest_api_error('rest_no_route', 'A term ID is required.', 404);
        }
        sblog_rest_api_delete_term($taxonomy, $id);
    }
    $input = sblog_rest_api_input();
    $existing = $id === null ? null : ($taxonomy === 'categories'
        ? one('SELECT * FROM categories WHERE id = ?', [$id])
        : one('SELECT * FROM rest_api_tags WHERE id = ?', [$id]));
    if ($id !== null && $existing === null) {
        sblog_rest_api_error('rest_term_invalid', 'Term does not exist.', 404);
    }
    $saved = sblog_rest_api_save_term($taxonomy, $input, $existing);
    sblog_rest_api_send(sblog_rest_api_prepare_term($taxonomy, $saved), $existing === null ? 201 : 200, ['Location' => sblog_rest_api_url('/wp/v2/' . $taxonomy . '/' . (int)$saved['id'])]);
}

function sblog_rest_api_term_collection(string $taxonomy): never
{
    [$page, $perPage, $offset] = sblog_rest_api_pagination();
    $table = $taxonomy === 'categories' ? 'categories' : 'rest_api_tags';
    $nameColumn = $taxonomy === 'categories' ? 'name' : 'label';
    $where = ['1 = 1'];
    $params = [];
    $search = trim((string)sblog_rest_api_param('search', ''));
    if ($search !== '') {
        $where[] = '(' . $nameColumn . ' LIKE ? OR slug LIKE ?)';
        array_push($params, '%' . $search . '%', '%' . $search . '%');
    }
    $include = sblog_rest_api_int_list(sblog_rest_api_param('include', []));
    if ($include !== []) {
        $where[] = 'id IN (' . implode(',', array_fill(0, count($include), '?')) . ')';
        array_push($params, ...$include);
    }
    $exclude = sblog_rest_api_int_list(sblog_rest_api_param('exclude', []));
    if ($exclude !== []) {
        $where[] = 'id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
        array_push($params, ...$exclude);
    }
    $postId = (int)sblog_rest_api_param('post', 0);
    if ($postId > 0) {
        $post = one('SELECT category_id, tags FROM posts WHERE id = ?', [$postId]);
        if ($post === null) {
            $where[] = '1 = 0';
        } elseif ($taxonomy === 'categories') {
            $where[] = 'id = ?';
            $params[] = (int)$post['category_id'];
        } else {
            $ids = sblog_rest_api_tag_ids_for_post($post);
            if ($ids === []) {
                $where[] = '1 = 0';
            } else {
                $where[] = 'id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                array_push($params, ...$ids);
            }
        }
    }
    $slugs = array_filter(array_map('trim', explode(',', (string)sblog_rest_api_param('slug', ''))));
    if ($slugs !== []) {
        $where[] = 'slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')';
        array_push($params, ...$slugs);
    }
    $whereSql = implode(' AND ', $where);
    if (sblog_rest_api_bool(sblog_rest_api_param('hide_empty', false))) {
        $allRows = all_rows('SELECT * FROM ' . $table . ' WHERE ' . $whereSql . ' ORDER BY ' . $nameColumn . ' COLLATE NOCASE ASC, id ASC', $params);
        $allItems = array_values(array_filter(
            array_map(static fn(array $row): array => sblog_rest_api_prepare_term($taxonomy, $row), $allRows),
            static fn(array $item): bool => (int)$item['count'] > 0
        ));
        $total = count($allItems);
        $items = array_slice($allItems, $offset, $perPage);
    } else {
        $total = (int)val('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $whereSql, $params);
        $rows = all_rows('SELECT * FROM ' . $table . ' WHERE ' . $whereSql . ' ORDER BY ' . $nameColumn . ' COLLATE NOCASE ASC, id ASC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
        $items = array_map(static fn(array $row): array => sblog_rest_api_prepare_term($taxonomy, $row), $rows);
    }
    sblog_rest_api_collection($items, $total, $page, $perPage, '/wp/v2/' . $taxonomy);
}

function sblog_rest_api_save_term(string $taxonomy, array $input, ?array $existing): array
{
    $name = trim((string)($input['name'] ?? ($taxonomy === 'categories' ? ($existing['name'] ?? '') : ($existing['label'] ?? ''))));
    if ($name === '') {
        sblog_rest_api_error('rest_missing_callback_param', 'Missing parameter(s): name', 400, ['params' => ['name']]);
    }
    if ($taxonomy === 'categories') {
        [$data, $errors] = validate_category_input([
            'name' => $name,
            'slug' => (string)($input['slug'] ?? $existing['slug'] ?? ''),
            'description' => (string)($input['description'] ?? $existing['description'] ?? ''),
            'sort_order' => (string)($existing['sort_order'] ?? 0),
        ], $existing);
        if ($errors !== []) {
            sblog_rest_api_error('rest_invalid_param', implode(' ', $errors), 400);
        }
        $now = time();
        if ($existing === null) {
            q('INSERT INTO categories(name, slug, description, sort_order, created_at, updated_at) VALUES(?,?,?,?,?,?)', [$data['name'], $data['slug'], $data['description'], $data['sort_order'], $now, $now]);
            return one('SELECT * FROM categories WHERE id = ?', [(int)db()->lastInsertId()]) ?? [];
        }
        q('UPDATE categories SET name = ?, slug = ?, description = ?, sort_order = ?, updated_at = ? WHERE id = ?', [$data['name'], $data['slug'], $data['description'], $data['sort_order'], $now, (int)$existing['id']]);
        return one('SELECT * FROM categories WHERE id = ?', [(int)$existing['id']]) ?? [];
    }
    if (count(parse_tags_input($name)) !== 1) {
        sblog_rest_api_error('rest_invalid_param', 'A tag name cannot contain commas.', 400, ['params' => ['name' => 'Invalid tag name.']]);
    }
    $slug = slugify(trim((string)($input['slug'] ?? '')) ?: $name);
    $duplicate = $existing === null
        ? one('SELECT id FROM rest_api_tags WHERE label = ? COLLATE NOCASE OR slug = ?', [$name, $slug])
        : one('SELECT id FROM rest_api_tags WHERE (label = ? COLLATE NOCASE OR slug = ?) AND id <> ?', [$name, $slug, (int)$existing['id']]);
    if ($duplicate !== null) {
        sblog_rest_api_error('term_exists', 'A term with the name or slug already exists.', 400, ['term_id' => (int)$duplicate['id']]);
    }
    $now = time();
    if ($existing === null) {
        q('INSERT INTO rest_api_tags(label, slug, created_at, updated_at) VALUES(?,?,?,?)', [$name, $slug, $now, $now]);
        $tagId = (int)db()->lastInsertId();
        q('INSERT OR REPLACE INTO tag_meta(label, slug, updated_at) VALUES(?,?,?)', [$name, $slug, $now]);
        return one('SELECT * FROM rest_api_tags WHERE id = ?', [$tagId]) ?? [];
    }
    $oldName = (string)$existing['label'];
    $database = db();
    $database->exec('BEGIN IMMEDIATE');
    try {
        if (str_lower_u($oldName) !== str_lower_u($name)) {
            replace_tag_everywhere($oldName, $name);
        }
        q('DELETE FROM tag_meta WHERE label = ?', [$oldName]);
        q('INSERT OR REPLACE INTO tag_meta(label, slug, updated_at) VALUES(?,?,?)', [$name, $slug, $now]);
        q('UPDATE rest_api_tags SET label = ?, slug = ?, updated_at = ? WHERE id = ?', [$name, $slug, $now, (int)$existing['id']]);
        $database->exec('COMMIT');
    } catch (Throwable $exception) {
        try { $database->exec('ROLLBACK'); } catch (Throwable) {}
        sblog_rest_api_error('rest_cannot_update', 'The tag could not be updated.', 500);
    }
    return one('SELECT * FROM rest_api_tags WHERE id = ?', [(int)$existing['id']]) ?? [];
}

function sblog_rest_api_delete_term(string $taxonomy, int $id): never
{
    $row = $taxonomy === 'categories'
        ? one('SELECT * FROM categories WHERE id = ?', [$id])
        : one('SELECT * FROM rest_api_tags WHERE id = ?', [$id]);
    if ($row === null) {
        sblog_rest_api_error('rest_term_invalid', 'Term does not exist.', 404);
    }
    $previous = sblog_rest_api_prepare_term($taxonomy, $row);
    if ($taxonomy === 'categories') {
        $replacement = (int)(val('SELECT id FROM categories WHERE id <> ? ORDER BY sort_order ASC, id DESC LIMIT 1', [$id]) ?: 0);
        if ($replacement < 1) {
            sblog_rest_api_error('rest_cannot_delete', 'The final category cannot be deleted.', 400);
        }
        q('UPDATE posts SET category_id = ?, updated_at = ? WHERE category_id = ?', [$replacement, time(), $id]);
        q('DELETE FROM categories WHERE id = ?', [$id]);
    } else {
        replace_tag_everywhere((string)$row['label'], null);
        q('DELETE FROM tag_meta WHERE label = ?', [(string)$row['label']]);
        q('DELETE FROM rest_api_tags WHERE id = ?', [$id]);
    }
    sblog_rest_api_send(['deleted' => true, 'previous' => $previous]);
}

function sblog_rest_api_prepare_comment(array $row, bool $editContext): array
{
    $post = one('SELECT id, slug, kind FROM posts WHERE id = ?', [(int)$row['post_id']]);
    $result = [
        'id' => (int)$row['id'],
        'post' => (int)$row['post_id'],
        'parent' => (int)($row['parent_id'] ?? 0),
        'author' => (int)($row['user_id'] ?? 0),
        'author_name' => (string)$row['author_name'],
        'author_url' => (string)$row['author_url'],
        'date' => sblog_rest_api_date((int)$row['created_at']),
        'date_gmt' => sblog_rest_api_date((int)$row['created_at'], true),
        'content' => ['rendered' => nl2br(h((string)$row['content'])), 'raw' => $editContext ? (string)$row['content'] : ''],
        'link' => $post !== null ? absolute_url(content_permalink($post)) . '#comment-' . (int)$row['id'] : '',
        'status' => match ((string)$row['status']) { 'approved' => 'approved', 'spam' => 'spam', default => 'hold' },
        'type' => 'comment',
        'author_avatar_urls' => [
            '24' => gravatar_url((string)$row['author_email'], 24),
            '48' => gravatar_url((string)$row['author_email'], 48),
            '96' => gravatar_url((string)$row['author_email'], 96),
        ],
        'meta' => [],
        '_links' => [
            'self' => [['href' => sblog_rest_api_url('/wp/v2/comments/' . (int)$row['id'])]],
            'collection' => [['href' => sblog_rest_api_url('/wp/v2/comments')]],
            'up' => [['embeddable' => true, 'post_type' => (string)($post['kind'] ?? 'post'), 'href' => sblog_rest_api_url('/wp/v2/' . ((string)($post['kind'] ?? 'post') === 'page' ? 'pages' : 'posts') . '/' . (int)$row['post_id'])]],
        ],
    ];
    if ($editContext) {
        $result['author_email'] = (string)$row['author_email'];
        $result['author_ip'] = (string)$row['ip_address'];
        $result['author_user_agent'] = (string)$row['user_agent'];
    } else {
        unset($result['content']['raw']);
    }
    return $result;
}

function sblog_rest_api_comment_route(?int $id, string $method): never
{
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        sblog_rest_api_error('rest_no_route', 'Comments are read-only through this API.', 404);
    }
    $user = sblog_rest_api_read_access();
    $editContext = (string)sblog_rest_api_param('context', 'view') === 'edit';
    if ($editContext && $user === null) {
        sblog_rest_api_user(true);
    }
    if ($id !== null) {
        $row = one('SELECT * FROM comments WHERE id = ?', [$id]);
        if ($row === null || (!$editContext && (string)$row['status'] !== 'approved')) {
            sblog_rest_api_error('rest_comment_invalid_id', 'Invalid comment ID.', 404);
        }
        sblog_rest_api_send(sblog_rest_api_prepare_comment($row, $editContext));
    }
    [$page, $perPage, $offset] = sblog_rest_api_pagination();
    $where = [];
    $params = [];
    if (!$editContext) {
        $where[] = "c.status = 'approved'";
        $where[] = "p.status = 'published'";
        $where[] = 'p.published_at > 0 AND p.published_at <= ?';
        $params[] = time();
    } else {
        $status = (string)sblog_rest_api_param('status', 'approved');
        $mapped = ['approved' => 'approved', 'hold' => 'pending', 'spam' => 'spam'];
        if ($status !== 'any' && isset($mapped[$status])) {
            $where[] = 'c.status = ?';
            $params[] = $mapped[$status];
        }
    }
    $postIds = sblog_rest_api_int_list(sblog_rest_api_param('post', []));
    if ($postIds !== []) {
        $where[] = 'c.post_id IN (' . implode(',', array_fill(0, count($postIds), '?')) . ')';
        array_push($params, ...$postIds);
    }
    if (sblog_rest_api_param('parent', null) !== null) {
        $parentIds = sblog_rest_api_int_list(sblog_rest_api_param('parent', []));
        if ($parentIds === [] && (int)sblog_rest_api_param('parent', -1) === 0) {
            $where[] = 'c.parent_id IS NULL';
        } elseif ($parentIds !== []) {
            $where[] = 'c.parent_id IN (' . implode(',', array_fill(0, count($parentIds), '?')) . ')';
            array_push($params, ...$parentIds);
        }
    }
    $search = trim((string)sblog_rest_api_param('search', ''));
    if ($search !== '') {
        $where[] = '(c.author_name LIKE ? OR c.content LIKE ?)';
        array_push($params, '%' . $search . '%', '%' . $search . '%');
    }
    $whereSql = $where !== [] ? implode(' AND ', $where) : '1 = 1';
    $total = (int)val('SELECT COUNT(*) FROM comments c INNER JOIN posts p ON p.id = c.post_id WHERE ' . $whereSql, $params);
    $order = strtoupper((string)sblog_rest_api_param('order', 'desc')) === 'ASC' ? 'ASC' : 'DESC';
    $rows = all_rows('SELECT c.* FROM comments c INNER JOIN posts p ON p.id = c.post_id WHERE ' . $whereSql . ' ORDER BY c.created_at ' . $order . ', c.id ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    $items = array_map(static fn(array $row): array => sblog_rest_api_prepare_comment($row, $editContext), $rows);
    sblog_rest_api_collection($items, $total, $page, $perPage, '/wp/v2/comments');
}

function sblog_rest_api_prepare_user(array $row, bool $editContext): array
{
    $id = (int)$row['id'];
    $name = trim((string)($row['nickname'] ?? '')) ?: (string)$row['username'];
    $result = [
        'id' => $id,
        'name' => $name,
        'url' => (string)($row['website_url'] ?? ''),
        'description' => (string)($row['signature'] ?? ''),
        'link' => (string)($row['website_url'] ?? '') ?: site_root_url(),
        'slug' => slugify((string)$row['username']),
        'avatar_urls' => [
            '24' => (string)($row['avatar_url'] ?? '') ?: gravatar_url((string)($row['email'] ?? ''), 24),
            '48' => (string)($row['avatar_url'] ?? '') ?: gravatar_url((string)($row['email'] ?? ''), 48),
            '96' => (string)($row['avatar_url'] ?? '') ?: gravatar_url((string)($row['email'] ?? ''), 96),
        ],
        'meta' => [],
        '_links' => [
            'self' => [['href' => sblog_rest_api_url('/wp/v2/users/' . $id)]],
            'collection' => [['href' => sblog_rest_api_url('/wp/v2/users')]],
        ],
    ];
    if ($editContext) {
        $result['username'] = (string)$row['username'];
        $result['email'] = (string)($row['email'] ?? '');
        $result['registered_date'] = sblog_rest_api_date((int)($row['created_at'] ?? 0), true);
        $result['roles'] = ['administrator'];
        $result['capabilities'] = ['administrator' => true];
        $result['extra_capabilities'] = ['administrator' => true];
    }
    return $result;
}

function sblog_rest_api_user_route(?int $id, string $method): never
{
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        sblog_rest_api_error('rest_no_route', 'Users are read-only through this API.', 404);
    }
    $user = sblog_rest_api_read_access();
    $editContext = (string)sblog_rest_api_param('context', 'view') === 'edit';
    if ($editContext && $user === null) {
        sblog_rest_api_user(true);
    }
    if ($id !== null) {
        $row = one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($row === null || (!$editContext && !val('SELECT 1 FROM posts WHERE author_id = ? AND status = ? AND published_at > 0 AND published_at <= ? LIMIT 1', [$id, 'published', time()]))) {
            sblog_rest_api_error('rest_user_invalid_id', 'Invalid user ID.', 404);
        }
        sblog_rest_api_send(sblog_rest_api_prepare_user($row, $editContext));
    }
    [$page, $perPage, $offset] = sblog_rest_api_pagination();
    $where = [];
    $params = [];
    if (!$editContext) {
        $where[] = 'EXISTS (SELECT 1 FROM posts p WHERE p.author_id = u.id AND p.status = ? AND p.published_at > 0 AND p.published_at <= ?)';
        array_push($params, 'published', time());
    }
    $search = trim((string)sblog_rest_api_param('search', ''));
    if ($search !== '') {
        $where[] = '(u.username LIKE ? OR u.nickname LIKE ?)';
        array_push($params, '%' . $search . '%', '%' . $search . '%');
    }
    $include = sblog_rest_api_int_list(sblog_rest_api_param('include', []));
    if ($include !== []) {
        $where[] = 'u.id IN (' . implode(',', array_fill(0, count($include), '?')) . ')';
        array_push($params, ...$include);
    }
    $whereSql = $where !== [] ? implode(' AND ', $where) : '1 = 1';
    $total = (int)val('SELECT COUNT(*) FROM users u WHERE ' . $whereSql, $params);
    $rows = all_rows('SELECT u.* FROM users u WHERE ' . $whereSql . ' ORDER BY u.nickname COLLATE NOCASE ASC, u.id ASC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    $items = array_map(static fn(array $row): array => sblog_rest_api_prepare_user($row, $editContext), $rows);
    sblog_rest_api_collection($items, $total, $page, $perPage, '/wp/v2/users');
}

function sblog_rest_api_prepare_media(array $row, bool $editContext = false): array
{
    $id = (int)$row['id'];
    $url = absolute_url((string)$row['url']);
    $isImage = (int)$row['is_image'] === 1;
    $details = [
        'filesize' => (int)$row['file_size'],
        'sizes' => [],
        'image_meta' => [],
    ];
    if ($isImage) {
        $details['width'] = (int)$row['width'];
        $details['height'] = (int)$row['height'];
        $details['file'] = (string)$row['local_path'];
        $details['sizes']['full'] = [
            'file' => basename((string)$row['local_path']),
            'width' => (int)$row['width'],
            'height' => (int)$row['height'],
            'mime_type' => (string)$row['mime_type'],
            'source_url' => $url,
        ];
    }
    $result = [
        'id' => $id,
        'date' => sblog_rest_api_date((int)$row['created_at']),
        'date_gmt' => sblog_rest_api_date((int)$row['created_at'], true),
        'guid' => ['rendered' => $url],
        'modified' => sblog_rest_api_date((int)$row['updated_at']),
        'modified_gmt' => sblog_rest_api_date((int)$row['updated_at'], true),
        'slug' => slugify((string)$row['title']),
        'status' => 'inherit',
        'type' => 'attachment',
        'link' => $url,
        'title' => ['rendered' => h((string)$row['title'])],
        'author' => 0,
        'featured_media' => 0,
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'template' => '',
        'meta' => [],
        'description' => ['rendered' => (string)$row['caption']],
        'caption' => ['rendered' => (string)$row['caption']],
        'alt_text' => (string)$row['alt_text'],
        'media_type' => $isImage ? 'image' : 'file',
        'mime_type' => (string)$row['mime_type'],
        'media_details' => $details,
        'post' => null,
        'source_url' => $url,
        '_links' => [
            'self' => [['href' => sblog_rest_api_url('/wp/v2/media/' . $id)]],
            'collection' => [['href' => sblog_rest_api_url('/wp/v2/media')]],
            'about' => [['href' => sblog_rest_api_url('/wp/v2/types/attachment')]],
        ],
    ];
    if ($editContext) {
        $result['title']['raw'] = (string)$row['title'];
        $result['description']['raw'] = (string)$row['caption'];
        $result['caption']['raw'] = (string)$row['caption'];
    }
    return $result;
}

function sblog_rest_api_media_route(?int $id, string $method): never
{
    if (in_array($method, ['GET', 'HEAD'], true)) {
        $user = sblog_rest_api_read_access();
        $editContext = (string)sblog_rest_api_param('context', 'view') === 'edit';
        if ($editContext && $user === null) {
            sblog_rest_api_user(true);
        }
        if ($id !== null) {
            $row = one('SELECT * FROM media WHERE id = ?', [$id]);
            if ($row === null) {
                sblog_rest_api_error('rest_post_invalid_id', 'Invalid media ID.', 404);
            }
            sblog_rest_api_send(sblog_rest_api_prepare_media($row, $editContext));
        }
        [$page, $perPage, $offset] = sblog_rest_api_pagination();
        $where = [];
        $params = [];
        $search = trim((string)sblog_rest_api_param('search', ''));
        if ($search !== '') {
            $where[] = '(title LIKE ? OR original_name LIKE ? OR caption LIKE ?)';
            array_push($params, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
        }
        $mediaType = (string)sblog_rest_api_param('media_type', '');
        if ($mediaType === 'image') {
            $where[] = 'is_image = 1';
        }
        $mimeType = trim((string)sblog_rest_api_param('mime_type', ''));
        if ($mimeType !== '') {
            $where[] = 'mime_type LIKE ?';
            $params[] = str_ends_with($mimeType, '/*') ? substr($mimeType, 0, -1) . '%' : $mimeType;
        }
        $whereSql = $where !== [] ? implode(' AND ', $where) : '1 = 1';
        $total = (int)val('SELECT COUNT(*) FROM media WHERE ' . $whereSql, $params);
        $rows = all_rows('SELECT * FROM media WHERE ' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
        $items = array_map(static fn(array $row): array => sblog_rest_api_prepare_media($row, $editContext), $rows);
        sblog_rest_api_collection($items, $total, $page, $perPage, '/wp/v2/media');
    }
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        sblog_rest_api_error('rest_no_route', 'No route was found matching the URL and request method.', 404);
    }
    sblog_rest_api_require_write();
    if ($method === 'DELETE') {
        if ($id === null) {
            sblog_rest_api_error('rest_no_route', 'A media ID is required.', 404);
        }
        $row = one('SELECT * FROM media WHERE id = ?', [$id]);
        if ($row === null) {
            sblog_rest_api_error('rest_post_invalid_id', 'Invalid media ID.', 404);
        }
        $previous = sblog_rest_api_prepare_media($row, true);
        $deleted = delete_media_storage($row);
        if (empty($deleted['ok'])) {
            sblog_rest_api_error('rest_cannot_delete', (string)($deleted['error'] ?? 'The media file could not be deleted.'), 500);
        }
        q('DELETE FROM media WHERE id = ?', [$id]);
        sblog_rest_api_send(['deleted' => true, 'previous' => $previous]);
    }
    if ($id === null) {
        $created = sblog_rest_api_upload_media();
        sblog_rest_api_send(sblog_rest_api_prepare_media($created, true), 201, ['Location' => sblog_rest_api_url('/wp/v2/media/' . (int)$created['id'])]);
    }
    $row = one('SELECT * FROM media WHERE id = ?', [$id]);
    if ($row === null) {
        sblog_rest_api_error('rest_post_invalid_id', 'Invalid media ID.', 404);
    }
    $input = sblog_rest_api_input();
    $title = array_key_exists('title', $input) ? sblog_rest_api_field_text($input['title']) : (string)$row['title'];
    $caption = array_key_exists('caption', $input) ? sblog_rest_api_field_text($input['caption']) : (string)$row['caption'];
    $description = array_key_exists('description', $input) ? sblog_rest_api_field_text($input['description']) : $caption;
    $alt = (string)($input['alt_text'] ?? $row['alt_text']);
    q('UPDATE media SET title = ?, caption = ?, alt_text = ?, updated_at = ? WHERE id = ?', [str_sub_u(trim($title), 0, 255), str_sub_u(trim($description !== '' ? $description : $caption), 0, 2000), str_sub_u(trim($alt), 0, 1000), time(), $id]);
    $saved = one('SELECT * FROM media WHERE id = ?', [$id]) ?? $row;
    sblog_rest_api_send(sblog_rest_api_prepare_media($saved, true));
}

function sblog_rest_api_upload_media(): array
{
    $maxSize = 30 * 1024 * 1024;
    $tmpName = '';
    $originalName = '';
    $removeTemp = false;
    $file = $_FILES['file'] ?? null;
    if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmpName = (string)($file['tmp_name'] ?? '');
        $originalName = basename(str_replace('\\', '/', (string)($file['name'] ?? '')));
        $size = (int)($file['size'] ?? 0);
        if (!is_uploaded_file($tmpName)) {
            sblog_rest_api_error('rest_upload_unknown_error', 'The uploaded file is invalid.', 500);
        }
    } else {
        $disposition = (string)($_SERVER['HTTP_CONTENT_DISPOSITION'] ?? '');
        if (preg_match('/filename\*=(?:UTF-8\'\')?([^;]+)/i', $disposition, $matches)) {
            $originalName = rawurldecode(trim($matches[1], " \t\n\r\0\x0B\"'"));
        } elseif (preg_match('/filename="([^"]+)"/i', $disposition, $matches)) {
            $originalName = $matches[1];
        }
        $originalName = basename(str_replace('\\', '/', $originalName));
        if ($originalName === '') {
            sblog_rest_api_error('rest_upload_no_content_disposition', 'No Content-Disposition header was supplied.', 400);
        }
        $raw = file_get_contents('php://input', false, null, 0, $maxSize + 1);
        if (!is_string($raw) || $raw === '') {
            sblog_rest_api_error('rest_upload_no_data', 'No data supplied.', 400);
        }
        $size = strlen($raw);
        $tmpName = tempnam(sys_get_temp_dir(), 'sblog-rest-') ?: '';
        if ($tmpName === '' || file_put_contents($tmpName, $raw, LOCK_EX) !== $size) {
            if ($tmpName !== '') { @unlink($tmpName); }
            sblog_rest_api_error('rest_upload_file_error', 'Could not write the uploaded data.', 500);
        }
        $removeTemp = true;
    }
    if ($size < 1 || $size > $maxSize) {
        if ($removeTemp) { @unlink($tmpName); }
        sblog_rest_api_error('rest_upload_file_too_big', 'Each attachment may be at most 30 MB.', 400);
    }
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedTypes = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'gif' => ['image/gif'],
        'webp' => ['image/webp'], 'pdf' => ['application/pdf'], 'txt' => ['text/plain'], 'md' => ['text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName) ?: 'application/octet-stream';
    if (!isset($allowedTypes[$extension]) || !in_array($mime, $allowedTypes[$extension], true)) {
        if ($removeTemp) { @unlink($tmpName); }
        sblog_rest_api_error('rest_upload_sideload_error', 'This file type is not permitted.', 400);
    }
    [$year, $directory] = ensure_upload_year_dir();
    $filename = str_replace('.', '', sprintf('%.6F', microtime(true))) . '-' . bin2hex(random_bytes(3)) . '.' . preg_replace('/[^a-z0-9]/', '', $extension);
    $target = $directory . '/' . $filename;
    $moved = $removeTemp ? @copy($tmpName, $target) : move_uploaded_file($tmpName, $target);
    if ($removeTemp) {
        @unlink($tmpName);
    }
    if (!$moved) {
        sblog_rest_api_error('rest_upload_file_error', 'Could not save the uploaded file.', 500);
    }
    $imageInfo = @getimagesize($target);
    $isImage = is_array($imageInfo);
    $localUrl = asset_url('uploads/' . $year . '/' . $filename);
    $storage = plugin_filter('attachment_storage', [
        'ok' => true, 'url' => $localUrl, 'error' => '', 'remove_local' => false,
        'storage_driver' => 'local', 'storage_key' => '',
    ], [
        'file' => $target, 'year' => $year, 'filename' => $filename, 'mime' => $mime,
        'size' => $size, 'original_name' => $originalName, 'local_url' => $localUrl,
    ]);
    if (!is_array($storage) || empty($storage['ok']) || trim((string)($storage['url'] ?? '')) === '') {
        @unlink($target);
        sblog_rest_api_error('rest_upload_sideload_error', trim((string)($storage['error'] ?? '')) ?: 'Attachment storage failed.', 500);
    }
    $removeLocal = !empty($storage['remove_local']);
    if ($removeLocal && is_file($target)) {
        @unlink($target);
    }
    $input = sblog_rest_api_input();
    $defaultTitle = trim(pathinfo($originalName, PATHINFO_FILENAME)) ?: $filename;
    $title = str_sub_u(trim(sblog_rest_api_field_text($input['title'] ?? $defaultTitle)), 0, 255);
    $caption = str_sub_u(trim(sblog_rest_api_field_text($input['caption'] ?? '')), 0, 2000);
    $alt = str_sub_u(trim((string)($input['alt_text'] ?? '')), 0, 1000);
    $now = time();
    try {
        q(
            'INSERT INTO media(original_name, title, alt_text, caption, url, storage_driver, storage_key, local_path, mime_type, file_size, is_image, width, height, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                str_sub_u($originalName, 0, 255), $title, $alt, $caption, trim((string)$storage['url']),
                str_sub_u(trim((string)($storage['storage_driver'] ?? 'local')), 0, 80) ?: 'local',
                str_sub_u(trim((string)($storage['storage_key'] ?? '')), 0, 1000), $removeLocal ? '' : $year . '/' . $filename,
                $mime, $size, $isImage ? 1 : 0, $isImage ? (int)$imageInfo[0] : 0, $isImage ? (int)$imageInfo[1] : 0, $now, $now,
            ]
        );
    } catch (Throwable $exception) {
        $storageDriver = trim((string)($storage['storage_driver'] ?? 'local')) ?: 'local';
        if ($storageDriver !== 'local') {
            plugin_filter('attachment_delete', ['ok' => false, 'error' => ''], [
                'storage_driver' => $storageDriver,
                'storage_key' => (string)($storage['storage_key'] ?? ''),
                'media' => ['storage_driver' => $storageDriver, 'storage_key' => (string)($storage['storage_key'] ?? '')],
            ]);
        }
        if (is_file($target)) { @unlink($target); }
        sblog_rest_api_error('rest_upload_file_error', 'The media record could not be created.', 500);
    }
    return one('SELECT * FROM media WHERE id = ?', [(int)db()->lastInsertId()]) ?? [];
}
