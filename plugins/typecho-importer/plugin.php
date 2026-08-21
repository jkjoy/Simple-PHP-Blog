<?php
declare(strict_types=1);

const SBLOG_TYPECHO_IMPORT_HEADER = '%TYPECHO_BACKUP_0001%';
const SBLOG_TYPECHO_IMPORT_MAX_BYTES = 67108864;
const SBLOG_TYPECHO_IMPORT_TOKEN_TTL = 3600;

function sblog_typecho_import_read_exact($stream, int $length): string
{
    $buffer = '';
    while (strlen($buffer) < $length && !feof($stream)) {
        $chunk = fread($stream, $length - strlen($buffer));
        if ($chunk === false || $chunk === '') {
            break;
        }
        $buffer .= $chunk;
    }
    if (strlen($buffer) !== $length) {
        throw new RuntimeException('Typecho 备份文件被截断。');
    }
    return $buffer;
}

function sblog_typecho_import_parse(string $path): array
{
    $size = is_file($path) ? (int)filesize($path) : 0;
    $headerLength = strlen(SBLOG_TYPECHO_IMPORT_HEADER);
    if ($size < $headerLength * 2 || $size > SBLOG_TYPECHO_IMPORT_MAX_BYTES) {
        throw new RuntimeException('备份文件大小无效，最大支持 64 MB。');
    }

    $stream = fopen($path, 'rb');
    if ($stream === false) {
        throw new RuntimeException('无法读取备份文件。');
    }

    $tables = [1 => 'contents', 2 => 'comments', 3 => 'metas', 4 => 'relationships', 5 => 'users', 6 => 'fields'];
    $records = array_fill_keys(array_values($tables), []);
    $unknownBlocks = 0;
    $blockCount = 0;
    $footerOffset = $size - $headerLength;

    try {
        if (sblog_typecho_import_read_exact($stream, $headerLength) !== SBLOG_TYPECHO_IMPORT_HEADER) {
            throw new RuntimeException('不是受支持的 Typecho 0001 备份文件。');
        }
        if (fseek($stream, $footerOffset) !== 0
            || sblog_typecho_import_read_exact($stream, $headerLength) !== SBLOG_TYPECHO_IMPORT_HEADER) {
            throw new RuntimeException('Typecho 备份文件尾无效，文件可能不完整。');
        }
        fseek($stream, $headerLength);
        $offset = $headerLength;

        while ($offset < $footerOffset) {
            if (++$blockCount > 200000 || $footerOffset - $offset < 40) {
                throw new RuntimeException('Typecho 备份数据块数量或长度无效。');
            }
            $meta = sblog_typecho_import_read_exact($stream, 8);
            $offset += 8;
            $parts = unpack('vtype/vheader_length/Vbody_length', $meta);
            $type = (int)($parts['type'] ?? 0);
            $schemaLength = (int)($parts['header_length'] ?? 0);
            $bodyLength = (int)($parts['body_length'] ?? 0);
            if ($schemaLength < 2 || $schemaLength > 1048576 || $bodyLength < 0
                || $offset + $schemaLength + $bodyLength + 32 > $footerOffset) {
                throw new RuntimeException('Typecho 备份数据块长度无效。');
            }

            $schemaJson = sblog_typecho_import_read_exact($stream, $schemaLength);
            $body = sblog_typecho_import_read_exact($stream, $bodyLength);
            $checksum = sblog_typecho_import_read_exact($stream, 32);
            $offset += $schemaLength + $bodyLength + 32;
            if (!hash_equals(md5($meta . $schemaJson . $body), strtolower($checksum))) {
                throw new RuntimeException('Typecho 备份数据块校验失败。');
            }

            try {
                $schema = json_decode($schemaJson, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('Typecho 备份字段描述无效。');
            }
            if (!is_array($schema)) {
                throw new RuntimeException('Typecho 备份字段描述无效。');
            }

            $row = [];
            $bodyOffset = 0;
            foreach ($schema as $field => $length) {
                if (!is_string($field) || ($length !== null && !is_int($length)) || (is_int($length) && $length < 0)) {
                    throw new RuntimeException('Typecho 备份字段长度无效。');
                }
                if ($length === null) {
                    $row[$field] = null;
                    continue;
                }
                if ($bodyOffset + $length > $bodyLength) {
                    throw new RuntimeException('Typecho 备份字段超出数据块边界。');
                }
                $row[$field] = substr($body, $bodyOffset, $length);
                $bodyOffset += $length;
            }
            if ($bodyOffset !== $bodyLength) {
                throw new RuntimeException('Typecho 备份数据块包含未描述内容。');
            }

            if (isset($tables[$type])) {
                $records[$tables[$type]][] = $row;
            } else {
                $unknownBlocks++;
            }
        }

        if ($offset !== $footerOffset) {
            throw new RuntimeException('Typecho 备份文件边界无效。');
        }
    } finally {
        fclose($stream);
    }

    $contentTypes = [];
    $contentStatuses = [];
    $passwordProtected = 0;
    foreach ($records['contents'] as $content) {
        $contentTypes[(string)($content['type'] ?? '')] = 1 + ($contentTypes[(string)($content['type'] ?? '')] ?? 0);
        $contentStatuses[(string)($content['status'] ?? '')] = 1 + ($contentStatuses[(string)($content['status'] ?? '')] ?? 0);
        if ((string)($content['password'] ?? '') !== '') {
            $passwordProtected++;
        }
    }
    $metaTypes = [];
    foreach ($records['metas'] as $metaRow) {
        $metaTypes[(string)($metaRow['type'] ?? '')] = 1 + ($metaTypes[(string)($metaRow['type'] ?? '')] ?? 0);
    }

    $pageCount = 0;
    foreach ($contentTypes as $type => $count) {
        if ($type !== 'post' && $type !== 'attachment') {
            $pageCount += $count;
        }
    }

    return [
        'hash' => (string)hash_file('sha256', $path),
        'records' => $records,
        'stats' => [
            'blocks' => $blockCount,
            'posts' => (int)($contentTypes['post'] ?? 0),
            'pages' => $pageCount,
            'attachments' => (int)($contentTypes['attachment'] ?? 0),
            'comments' => count($records['comments']),
            'categories' => (int)($metaTypes['category'] ?? 0),
            'tags' => (int)($metaTypes['tag'] ?? 0),
            'users' => count($records['users']),
            'fields' => count($records['fields']),
            'relationships' => count($records['relationships']),
            'unknown_blocks' => $unknownBlocks,
            'password_protected' => $passwordProtected,
            'content_types' => $contentTypes,
            'content_statuses' => $contentStatuses,
        ],
    ];
}

function sblog_typecho_import_page_url(array $params = []): string
{
    return url_with_query(script_url(), array_merge(['a' => 'admin_typecho_import'], $params));
}

function sblog_typecho_import_infer_source_url(string $filename): string
{
    if (preg_match('/^\d{8}_(.+)_[a-f0-9]+\.dat$/i', basename($filename), $match)) {
        $host = trim((string)$match[1]);
        if ($host !== '' && filter_var('https://' . $host, FILTER_VALIDATE_URL)) {
            return 'https://' . $host;
        }
    }
    return '';
}

function sblog_typecho_import_normalize_source_url(string $url): string
{
    $url = rtrim(trim($url), '/');
    if ($url === '') {
        return '';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('原 Typecho 站点地址必须是有效的 HTTP 或 HTTPS 地址。');
    }
    return $url;
}

function sblog_typecho_import_cleanup_sessions(): void
{
    $entries = is_array($_SESSION['sblog_typecho_imports'] ?? null) ? $_SESSION['sblog_typecho_imports'] : [];
    foreach ($entries as $token => $entry) {
        if (!is_array($entry) || (int)($entry['created_at'] ?? 0) < time() - SBLOG_TYPECHO_IMPORT_TOKEN_TTL) {
            $path = is_array($entry) ? (string)($entry['path'] ?? '') : '';
            if ($path !== '' && preg_match('/^typecho-import-\d+-[a-f0-9]{32}\.dat$/', basename($path))) {
                @unlink($path);
            }
            unset($entries[$token]);
        }
    }
    $_SESSION['sblog_typecho_imports'] = $entries;
}

function sblog_typecho_import_entry(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    sblog_typecho_import_cleanup_sessions();
    $entry = $_SESSION['sblog_typecho_imports'][$token] ?? null;
    $adminId = (int)(current_admin()['id'] ?? 0);
    if (!is_array($entry) || (int)($entry['admin_id'] ?? 0) !== $adminId || !is_file((string)($entry['path'] ?? ''))) {
        return null;
    }
    return $entry;
}

function sblog_typecho_import_forget(string $token): void
{
    $entry = $_SESSION['sblog_typecho_imports'][$token] ?? null;
    if (is_array($entry)) {
        $path = (string)($entry['path'] ?? '');
        if ($path !== '' && preg_match('/^typecho-import-\d+-[a-f0-9]{32}\.dat$/', basename($path))) {
            @unlink($path);
        }
    }
    unset($_SESSION['sblog_typecho_imports'][$token]);
}

function sblog_typecho_import_store_upload(array $file, string $sourceUrl): string
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            ? '备份文件超过服务器上传限制。' : '备份文件上传失败。');
    }
    $size = (int)($file['size'] ?? 0);
    $temporary = (string)($file['tmp_name'] ?? '');
    if ($size < 1 || $size > SBLOG_TYPECHO_IMPORT_MAX_BYTES || !is_uploaded_file($temporary)) {
        throw new RuntimeException('备份文件无效，最大支持 64 MB。');
    }

    ensure_runtime_dirs();
    $adminId = (int)(current_admin()['id'] ?? 0);
    $token = bin2hex(random_bytes(16));
    $path = CACHE_DIR . '/typecho-import-' . $adminId . '-' . $token . '.dat';
    if (!move_uploaded_file($temporary, $path)) {
        throw new RuntimeException('无法保存待导入的备份文件。');
    }

    try {
        $parsed = sblog_typecho_import_parse($path);
        $normalizedSourceUrl = sblog_typecho_import_normalize_source_url($sourceUrl);
        $_SESSION['sblog_typecho_imports'][$token] = [
            'admin_id' => $adminId,
            'path' => $path,
            'hash' => $parsed['hash'],
            'filename' => str_sub_u(basename((string)($file['name'] ?? 'typecho.dat')), 0, 255),
            'source_url' => $normalizedSourceUrl,
            'created_at' => time(),
        ];
        return $token;
    } catch (Throwable $exception) {
        @unlink($path);
        throw $exception;
    }
}

function sblog_typecho_import_ensure_map_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS typecho_import_map(
            source_hash TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            source_id TEXT NOT NULL,
            target_key TEXT NOT NULL,
            imported_at INTEGER NOT NULL,
            PRIMARY KEY(source_hash, entity_type, source_id)
        )"
    );
}

function sblog_typecho_import_mapped_target(string $hash, string $entityType, string $sourceId): ?string
{
    $row = one(
        'SELECT target_key FROM typecho_import_map WHERE source_hash = ? AND entity_type = ? AND source_id = ?',
        [$hash, $entityType, $sourceId]
    );
    if (!$row) {
        return null;
    }
    $target = (string)$row['target_key'];
    $exists = match ($entityType) {
        'category' => one('SELECT id FROM categories WHERE id = ?', [(int)$target]) !== null,
        'post' => one('SELECT id FROM posts WHERE id = ?', [(int)$target]) !== null,
        'comment' => one('SELECT id FROM comments WHERE id = ?', [(int)$target]) !== null,
        'media' => one('SELECT id FROM media WHERE id = ?', [(int)$target]) !== null,
        'user' => one('SELECT id FROM users WHERE id = ?', [(int)$target]) !== null,
        'tag' => one('SELECT label FROM tag_meta WHERE label = ?', [$target]) !== null,
        default => false,
    };
    if ($exists) {
        return $target;
    }
    q('DELETE FROM typecho_import_map WHERE source_hash = ? AND entity_type = ? AND source_id = ?', [$hash, $entityType, $sourceId]);
    return null;
}

function sblog_typecho_import_map_target(string $hash, string $entityType, string $sourceId, string|int $target): void
{
    q(
        'INSERT OR REPLACE INTO typecho_import_map(source_hash, entity_type, source_id, target_key, imported_at) VALUES(?,?,?,?,?)',
        [$hash, $entityType, $sourceId, (string)$target, time()]
    );
}

function sblog_typecho_import_unique_category_slug(string $seed): string
{
    $base = slugify($seed);
    $slug = $base;
    $index = 2;
    while (one('SELECT id FROM categories WHERE slug = ?', [$slug]) !== null) {
        $slug = $base . '-' . $index++;
    }
    return $slug;
}

function sblog_typecho_import_unique_tag_slug(string $seed, string $label): string
{
    $base = slugify($seed !== '' ? $seed : $label);
    $slug = $base;
    $index = 2;
    while (($row = one('SELECT label FROM tag_meta WHERE slug = ?', [$slug])) !== null && (string)$row['label'] !== $label) {
        $slug = $base . '-' . $index++;
    }
    return $slug;
}

function sblog_typecho_import_field_values(array $rows): array
{
    $values = [];
    foreach ($rows as $row) {
        $cid = (string)($row['cid'] ?? '');
        $name = trim((string)($row['name'] ?? ''));
        if ($cid === '' || $name === '') {
            continue;
        }
        $type = (string)($row['type'] ?? 'str');
        $value = match ($type) {
            'int' => $row['int_value'] ?? '',
            'float' => $row['float_value'] ?? '',
            default => $row['str_value'] ?? '',
        };
        if (!isset($values[$cid][$name]) && $value !== null) {
            $values[$cid][$name] = trim((string)$value);
        }
    }
    return $values;
}

function sblog_typecho_import_excerpt(array $fields, string $content): string
{
    foreach (['excerpt', 'summary', 'abstract', 'description', 'customSummary', 'desc'] as $name) {
        $value = trim((string)($fields[$name] ?? ''));
        if ($value !== '') {
            return str_sub_u(markdown_to_plain($value), 0, 500);
        }
    }
    return derive_excerpt($content);
}

function sblog_typecho_import_content(string $content, string $sourceUrl): string
{
    $content = preg_replace('/^\s*<!--markdown-->\s*/u', '', $content, 1) ?? $content;
    if ($sourceUrl !== '') {
        $content = preg_replace_callback(
            '#(?<![A-Za-z0-9:/])(/usr/uploads/[^\s"\'<>\)\]]+)#u',
            static fn(array $match): string => $sourceUrl . $match[1],
            $content
        ) ?? $content;
    }
    return trim($content);
}

function sblog_typecho_import_attachment_data(string $serialized): ?array
{
    if ($serialized === '' || strlen($serialized) > 1048576) {
        return null;
    }
    try {
        $value = @unserialize($serialized, ['allowed_classes' => false]);
    } catch (Throwable) {
        $value = false;
    }
    if (is_array($value)) {
        return $value;
    }

    if (!preg_match('/^a:\d+:\{.*\}$/s', $serialized)) {
        return null;
    }
    $result = [];
    foreach (['name', 'path', 'type', 'mime'] as $field) {
        if (preg_match('/s:\d+:"' . preg_quote($field, '/') . '";s:\d+:"([^"\r\n]*)";/s', $serialized, $match)) {
            $result[$field] = $match[1];
        }
    }
    if (preg_match('/s:\d+:"size";i:(\d+);/', $serialized, $match)) {
        $result['size'] = (int)$match[1];
    }
    $path = trim((string)($result['path'] ?? ''));
    if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path)
        || (!str_starts_with($path, '/') && !preg_match('#^https?://#i', $path))) {
        return null;
    }
    return $result;
}

function sblog_typecho_import_attachment_mime(array $attachment, string $name, string $path): string
{
    $mime = strtolower(trim((string)($attachment['mime'] ?? '')));
    if ($mime !== '' && $mime !== 'application/octet-stream'
        && preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#', $mime)) {
        return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
    }

    $type = strtolower(ltrim(trim((string)($attachment['type'] ?? '')), '.'));
    if (str_contains($type, '/') && preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#', $type)) {
        return $type === 'image/jpg' ? 'image/jpeg' : $type;
    }
    $pathPart = (string)(parse_url($path, PHP_URL_PATH) ?? $path);
    $extensions = [$type, strtolower(pathinfo($pathPart, PATHINFO_EXTENSION)), strtolower(pathinfo($name, PATHINFO_EXTENSION))];
    $mimeByExtension = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'jpe' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'avif' => 'image/avif', 'bmp' => 'image/bmp', 'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon', 'pdf' => 'application/pdf', 'zip' => 'application/zip',
        'txt' => 'text/plain', 'md' => 'text/plain',
    ];
    foreach ($extensions as $extension) {
        if (isset($mimeByExtension[$extension])) {
            return $mimeByExtension[$extension];
        }
    }
    return 'application/octet-stream';
}

function sblog_typecho_import_timestamp(mixed $value, int $fallback): int
{
    $timestamp = (int)$value;
    return $timestamp > 0 ? $timestamp : $fallback;
}

function sblog_typecho_import_data(array $parsed, string $sourceUrl, array $selected): array
{
    $sourceUrl = sblog_typecho_import_normalize_source_url($sourceUrl);
    $hash = (string)($parsed['hash'] ?? '');
    $records = is_array($parsed['records'] ?? null) ? $parsed['records'] : [];
    if ($hash === '' || !isset($records['contents'], $records['metas'], $records['relationships'])) {
        throw new RuntimeException('待导入数据无效。');
    }

    $selection = [];
    foreach (['posts', 'pages', 'users', 'categories', 'tags', 'comments', 'media'] as $type) {
        $selection[$type] = !empty($selected[$type]);
    }
    if (!in_array(true, $selection, true)) {
        throw new RuntimeException('请至少选择一项需要导入的数据。');
    }

    sblog_typecho_import_ensure_map_table();
    $database = db();
    $result = [
        'users_created' => 0, 'users_reused' => 0,
        'categories_created' => 0, 'categories_reused' => 0,
        'tags_created' => 0, 'tags_reused' => 0,
        'posts_created' => 0, 'pages_created' => 0, 'contents_skipped' => 0,
        'comments_created' => 0, 'comments_skipped' => 0,
        'media_created' => 0, 'media_updated' => 0, 'media_skipped' => 0,
    ];

    $metaById = [];
    foreach ($records['metas'] as $meta) {
        $mid = (string)($meta['mid'] ?? '');
        if ($mid !== '') {
            $metaById[$mid] = $meta;
        }
    }
    $relationships = [];
    foreach ($records['relationships'] as $relationship) {
        $cid = (string)($relationship['cid'] ?? '');
        $mid = (string)($relationship['mid'] ?? '');
        if ($cid !== '' && $mid !== '') {
            $relationships[$cid][] = $mid;
        }
    }
    $fields = sblog_typecho_import_field_values(is_array($records['fields'] ?? null) ? $records['fields'] : []);

    try {
        $database->exec('BEGIN IMMEDIATE');
        $categoryMap = [];
        $tagMap = [];
        $now = time();

        foreach ($records['metas'] as $meta) {
            $mid = (string)($meta['mid'] ?? '');
            $type = (string)($meta['type'] ?? '');
            $name = trim((string)($meta['name'] ?? ''));
            if ($mid === '' || $name === '' || !in_array($type, ['category', 'tag'], true)) {
                continue;
            }
            if ($type === 'category') {
                $mapped = sblog_typecho_import_mapped_target($hash, 'category', $mid);
                if ($mapped !== null) {
                    $categoryMap[$mid] = (int)$mapped;
                    if ($selection['categories']) {
                        $result['categories_reused']++;
                    }
                    continue;
                }
                if (!$selection['categories']) {
                    continue;
                }
                $existing = one('SELECT id FROM categories WHERE name = ? ORDER BY id LIMIT 1', [$name]);
                if ($existing) {
                    $targetId = (int)$existing['id'];
                    $result['categories_reused']++;
                } else {
                    $slug = sblog_typecho_import_unique_category_slug((string)($meta['slug'] ?? $name));
                    q(
                        'INSERT INTO categories(name, slug, description, sort_order, created_at, updated_at) VALUES(?,?,?,?,?,?)',
                        [$name, $slug, trim((string)($meta['description'] ?? '')), (int)($meta['order'] ?? 0), $now, $now]
                    );
                    $targetId = (int)$database->lastInsertId();
                    $result['categories_created']++;
                }
                $categoryMap[$mid] = $targetId;
                sblog_typecho_import_map_target($hash, 'category', $mid, $targetId);
                continue;
            }

            $mapped = sblog_typecho_import_mapped_target($hash, 'tag', $mid);
            if ($mapped !== null) {
                $tagMap[$mid] = $mapped;
                if ($selection['tags']) {
                    $result['tags_reused']++;
                }
                continue;
            }
            if (!$selection['tags']) {
                continue;
            }
            $existing = one('SELECT label FROM tag_meta WHERE label = ?', [$name]);
            if ($existing) {
                $label = (string)$existing['label'];
                $result['tags_reused']++;
            } else {
                $label = $name;
                $slug = sblog_typecho_import_unique_tag_slug((string)($meta['slug'] ?? ''), $label);
                q('INSERT INTO tag_meta(label, slug, updated_at) VALUES(?,?,?)', [$label, $slug, $now]);
                $result['tags_created']++;
            }
            $tagMap[$mid] = $label;
            sblog_typecho_import_map_target($hash, 'tag', $mid, $label);
        }

        $userMap = [];
        foreach (is_array($records['users'] ?? null) ? $records['users'] : [] as $userRow) {
            $uid = (string)($userRow['uid'] ?? '');
            if ($uid === '') {
                continue;
            }
            $mapped = sblog_typecho_import_mapped_target($hash, 'user', $uid);
            if ($mapped !== null) {
                $userMap[$uid] = (int)$mapped;
                if ($selection['users']) {
                    $result['users_reused']++;
                }
                continue;
            }
            if (!$selection['users']) {
                continue;
            }

            $username = trim((string)($userRow['name'] ?? ''));
            $username = str_sub_u($username !== '' ? $username : 'typecho-' . $uid, 0, 100);
            $existing = one('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
            if ($existing) {
                $userId = (int)$existing['id'];
                $result['users_reused']++;
            } else {
                $email = trim((string)($userRow['mail'] ?? ''));
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = '';
                }
                $website = trim((string)($userRow['url'] ?? ''));
                if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $website))) {
                    $website = '';
                }
                q(
                    'INSERT INTO users(username, password_hash, nickname, email, website_url, created_at) VALUES(?,?,?,?,?,?)',
                    [
                        $username,
                        password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                        str_sub_u(trim((string)($userRow['screenName'] ?? '')), 0, 100),
                        str_sub_u($email, 0, 255),
                        str_sub_u($website, 0, 500),
                        sblog_typecho_import_timestamp($userRow['created'] ?? 0, $now),
                    ]
                );
                $userId = (int)$database->lastInsertId();
                $result['users_created']++;
            }
            $userMap[$uid] = $userId;
            sblog_typecho_import_map_target($hash, 'user', $uid, $userId);
        }

        $postMap = [];
        $fallbackCategoryId = 0;
        foreach ($records['contents'] as $contentRow) {
            $cid = (string)($contentRow['cid'] ?? '');
            $sourceType = (string)($contentRow['type'] ?? '');
            if ($cid === '' || $sourceType === 'attachment') {
                continue;
            }
            $kind = $sourceType === 'post' ? 'post' : 'page';
            $isSelected = $kind === 'post' ? $selection['posts'] : $selection['pages'];
            $mapped = sblog_typecho_import_mapped_target($hash, 'post', $cid);
            if ($mapped !== null) {
                $postMap[$cid] = (int)$mapped;
                if ($isSelected) {
                    $result['contents_skipped']++;
                }
                continue;
            }
            if (!$isSelected) {
                continue;
            }

            $categoryId = null;
            $tags = [];
            foreach ($relationships[$cid] ?? [] as $mid) {
                $meta = $metaById[$mid] ?? null;
                if (!is_array($meta)) {
                    continue;
                }
                if ($kind === 'post' && (string)($meta['type'] ?? '') === 'category' && isset($categoryMap[$mid]) && $categoryId === null) {
                    $categoryId = $categoryMap[$mid];
                } elseif ((string)($meta['type'] ?? '') === 'tag' && isset($tagMap[$mid])) {
                    $tags[] = $tagMap[$mid];
                }
            }
            if ($kind === 'post' && $categoryId === null) {
                if ($fallbackCategoryId < 1) {
                    $fallback = one('SELECT id FROM categories WHERE name = ? ORDER BY id LIMIT 1', ['未分类']);
                    if ($fallback) {
                        $fallbackCategoryId = (int)$fallback['id'];
                    } else {
                        q(
                            'INSERT INTO categories(name, slug, description, sort_order, created_at, updated_at) VALUES(?,?,?,?,?,?)',
                            ['未分类', sblog_typecho_import_unique_category_slug('uncategorized'), 'Typecho 导入时未关联分类的文章。', 999, $now, $now]
                        );
                        $fallbackCategoryId = (int)$database->lastInsertId();
                        $result['categories_created']++;
                    }
                }
                $categoryId = $fallbackCategoryId;
            }

            $title = trim((string)($contentRow['title'] ?? '')) ?: 'Typecho #' . $cid;
            $body = sblog_typecho_import_content((string)($contentRow['text'] ?? ''), $sourceUrl);
            if ($body === '') {
                $body = $title;
            }
            $createdAt = sblog_typecho_import_timestamp($contentRow['created'] ?? 0, $now);
            $updatedAt = sblog_typecho_import_timestamp($contentRow['modified'] ?? 0, $createdAt);
            $status = (string)($contentRow['status'] ?? '') === 'publish' && (string)($contentRow['password'] ?? '') === ''
                ? 'published' : 'draft';
            $slugSeed = trim((string)($contentRow['slug'] ?? '')) ?: $title;
            $slug = unique_slug($slugSeed);
            $excerpt = sblog_typecho_import_excerpt($fields[$cid] ?? [], $body);
            $tagJson = json_encode(array_values(array_unique($tags)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
            q(
                'INSERT INTO posts(author_id, kind, category_id, slug, title, tags, excerpt, content, status, published_at, is_pinned, allow_comments, views, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $userMap[(string)($contentRow['authorId'] ?? '')] ?? (int)(current_admin()['id'] ?? 0),
                    $kind, $categoryId, $slug, $title, $tagJson, $excerpt, $body,
                    $status, $createdAt, 0, (string)($contentRow['allowComment'] ?? '0') === '1' ? 1 : 0,
                    0, $createdAt, $updatedAt,
                ]
            );
            $postId = (int)$database->lastInsertId();
            $postMap[$cid] = $postId;
            sblog_typecho_import_map_target($hash, 'post', $cid, $postId);
            $result[$kind === 'post' ? 'posts_created' : 'pages_created']++;
        }

        if ($selection['comments']) {
            $comments = is_array($records['comments'] ?? null) ? $records['comments'] : [];
            usort($comments, static fn(array $a, array $b): int => [(int)($a['created'] ?? 0), (int)($a['coid'] ?? 0)] <=> [(int)($b['created'] ?? 0), (int)($b['coid'] ?? 0)]);
            $commentMap = [];
            $commentRows = [];
            foreach ($comments as $comment) {
                $coid = (string)($comment['coid'] ?? '');
                $cid = (string)($comment['cid'] ?? '');
                if ($coid === '' || !isset($postMap[$cid]) || (string)($comment['type'] ?? 'comment') !== 'comment') {
                    $result['comments_skipped']++;
                    continue;
                }
                $mapped = sblog_typecho_import_mapped_target($hash, 'comment', $coid);
                if ($mapped !== null) {
                    $commentMap[$coid] = (int)$mapped;
                    $commentRows[$coid] = $comment;
                    $result['comments_skipped']++;
                    continue;
                }
                $createdAt = sblog_typecho_import_timestamp($comment['created'] ?? 0, $now);
                $ip = filter_var((string)($comment['ip'] ?? ''), FILTER_VALIDATE_IP) ? (string)$comment['ip'] : '';
                $url = trim((string)($comment['url'] ?? ''));
                if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url))) {
                    $url = '';
                }
                $commentStatus = match ((string)($comment['status'] ?? '')) {
                    'approved' => 'approved',
                    'spam' => 'spam',
                    default => 'pending',
                };
                q(
                    'INSERT INTO comments(post_id, user_id, parent_id, reply_to_name, author_name, author_email, author_url, content, status, is_read, ip_hash, ip_address, user_agent, reply_notified_at, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $postMap[$cid], null, null, '', trim((string)($comment['author'] ?? '')) ?: '匿名',
                        trim((string)($comment['mail'] ?? '')), $url, (string)($comment['text'] ?? ''), $commentStatus, 1,
                        $ip !== '' ? hash('sha256', 'typecho:' . $ip) : '', $ip,
                        str_sub_u((string)($comment['agent'] ?? ''), 0, 255), 0, $createdAt, $createdAt,
                    ]
                );
                $commentId = (int)$database->lastInsertId();
                $commentMap[$coid] = $commentId;
                $commentRows[$coid] = $comment;
                sblog_typecho_import_map_target($hash, 'comment', $coid, $commentId);
                $result['comments_created']++;
            }
            foreach ($commentRows as $coid => $comment) {
                $parentSourceId = (string)($comment['parent'] ?? '0');
                if ($parentSourceId === '' || $parentSourceId === '0' || !isset($commentMap[$parentSourceId], $commentMap[$coid])) {
                    continue;
                }
                $parentRow = $commentRows[$parentSourceId] ?? null;
                q(
                    'UPDATE comments SET parent_id = ?, reply_to_name = ? WHERE id = ?',
                    [$commentMap[$parentSourceId], is_array($parentRow) ? trim((string)($parentRow['author'] ?? '')) : '', $commentMap[$coid]]
                );
            }
        }

        if ($selection['media']) {
            foreach ($records['contents'] as $contentRow) {
                if ((string)($contentRow['type'] ?? '') !== 'attachment') {
                    continue;
                }
                $cid = (string)($contentRow['cid'] ?? '');
                if ($cid === '') {
                    $result['media_skipped']++;
                    continue;
                }
                $attachment = sblog_typecho_import_attachment_data((string)($contentRow['text'] ?? ''));
                $path = is_array($attachment) ? trim((string)($attachment['path'] ?? '')) : '';
                if ($path === '') {
                    $result['media_skipped']++;
                    continue;
                }
                $url = preg_match('#^https?://#i', $path) ? $path : ($sourceUrl !== '' ? $sourceUrl . '/' . ltrim($path, '/') : $path);
                $name = trim((string)($attachment['name'] ?? $contentRow['title'] ?? 'attachment')) ?: 'attachment';
                $mime = sblog_typecho_import_attachment_mime($attachment, $name, $path);
                $isImage = str_starts_with($mime, 'image/') ? 1 : 0;
                $mapped = sblog_typecho_import_mapped_target($hash, 'media', $cid);
                if ($mapped !== null) {
                    $existingMedia = one('SELECT mime_type FROM media WHERE id = ?', [(int)$mapped]);
                    $existingMime = strtolower(trim((string)($existingMedia['mime_type'] ?? '')));
                    if (($existingMime === '' || $existingMime === 'application/octet-stream') && $mime !== 'application/octet-stream') {
                        q('UPDATE media SET mime_type = ?, is_image = ?, updated_at = ? WHERE id = ?', [$mime, $isImage, time(), (int)$mapped]);
                        $result['media_updated']++;
                    }
                    $result['media_skipped']++;
                    continue;
                }
                $createdAt = sblog_typecho_import_timestamp($contentRow['created'] ?? 0, $now);
                q(
                    'INSERT INTO media(original_name, title, alt_text, caption, url, storage_driver, storage_key, local_path, mime_type, file_size, is_image, width, height, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        str_sub_u($name, 0, 255), str_sub_u(pathinfo($name, PATHINFO_FILENAME), 0, 255), '', '', $url,
                        'local', '', '', str_sub_u($mime, 0, 255), max(0, (int)($attachment['size'] ?? 0)),
                        $isImage, 0, 0, $createdAt, $createdAt,
                    ]
                );
                $mediaId = (int)$database->lastInsertId();
                sblog_typecho_import_map_target($hash, 'media', $cid, $mediaId);
                $result['media_created']++;
            }
        }

        $database->exec('COMMIT');
        return $result;
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->exec('ROLLBACK');
        }
        throw $exception;
    }
}

function sblog_typecho_import_result_message(array $result): string
{
    return sprintf(
        '导入完成：文章 %d，页面 %d，用户 %d，分类 %d，标签 %d，评论 %d，媒体 %d，修正媒体类型 %d；已有或无效记录 %d。',
        (int)($result['posts_created'] ?? 0),
        (int)($result['pages_created'] ?? 0),
        (int)($result['users_created'] ?? 0),
        (int)($result['categories_created'] ?? 0),
        (int)($result['tags_created'] ?? 0),
        (int)($result['comments_created'] ?? 0),
        (int)($result['media_created'] ?? 0),
        (int)($result['media_updated'] ?? 0),
        (int)($result['users_reused'] ?? 0) + (int)($result['categories_reused'] ?? 0)
            + (int)($result['tags_reused'] ?? 0) + (int)($result['contents_skipped'] ?? 0)
            + (int)($result['comments_skipped'] ?? 0) + (int)($result['media_skipped'] ?? 0)
    );
}

function sblog_typecho_import_render(): void
{
    require_admin();
    sblog_typecho_import_cleanup_sessions();
    $token = trim((string)($_GET['token'] ?? ''));
    $entry = $token !== '' ? sblog_typecho_import_entry($token) : null;
    $parsed = null;
    if ($entry !== null) {
        try {
            $parsed = sblog_typecho_import_parse((string)$entry['path']);
            if (!hash_equals((string)$entry['hash'], (string)$parsed['hash'])) {
                throw new RuntimeException('待导入文件已发生变化。');
            }
        } catch (Throwable $exception) {
            sblog_typecho_import_forget($token);
            set_flash('error', $exception->getMessage());
            redirect_to(sblog_typecho_import_page_url());
        }
    }

    $lastResult = $_SESSION['sblog_typecho_import_result'] ?? null;
    unset($_SESSION['sblog_typecho_import_result']);
    $stats = is_array($parsed['stats'] ?? null) ? $parsed['stats'] : [];
    $defaultSourceUrl = '';

    ob_start(); ?>
    <div class="admin-shell">
      <?= render_admin_sidebar('plugins') ?>
      <div class="admin-main">
        <?= render_admin_topbar(sblog_t('Typecho 数据导入')) ?>

        <?php if (is_array($lastResult)): ?>
          <section class="panel admin-list-panel admin-animate admin-animate--2">
            <div class="panel__header"><h2><?= h(sblog_t('最近一次导入结果')) ?></h2><p class="panel__meta"><?= h(sblog_typecho_import_result_message($lastResult)) ?></p></div>
            <div class="panel__body"><div class="metric-grid">
              <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('新增文章')) ?></span><strong class="metric-card__value"><?= (int)($lastResult['posts_created'] ?? 0) ?></strong></div>
              <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('新增页面')) ?></span><strong class="metric-card__value"><?= (int)($lastResult['pages_created'] ?? 0) ?></strong></div>
              <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('新增用户')) ?></span><strong class="metric-card__value"><?= (int)($lastResult['users_created'] ?? 0) ?></strong></div>
              <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('新增分类')) ?></span><strong class="metric-card__value"><?= (int)($lastResult['categories_created'] ?? 0) ?></strong></div>
              <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('新增标签')) ?></span><strong class="metric-card__value"><?= (int)($lastResult['tags_created'] ?? 0) ?></strong></div>
              <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('新增评论')) ?></span><strong class="metric-card__value"><?= (int)($lastResult['comments_created'] ?? 0) ?></strong></div>
              <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('新增媒体')) ?></span><strong class="metric-card__value"><?= (int)($lastResult['media_created'] ?? 0) ?></strong></div>
              <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('修正媒体类型')) ?></span><strong class="metric-card__value"><?= (int)($lastResult['media_updated'] ?? 0) ?></strong></div>
            </div></div>
          </section>
        <?php endif; ?>

        <?php if ($entry !== null && is_array($parsed)): ?>
          <section class="panel admin-list-panel admin-animate admin-animate--2">
            <div class="panel__header"><h2><?= h(sblog_t('备份预检')) ?></h2><p class="panel__meta"><?= h((string)$entry['filename']) ?> · SHA-256 <?= h(substr((string)$entry['hash'], 0, 16)) ?>...</p></div>
            <div class="panel__body">
              <div class="metric-grid">
                <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('文章')) ?></span><strong class="metric-card__value"><?= (int)($stats['posts'] ?? 0) ?></strong></div>
                <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('页面')) ?></span><strong class="metric-card__value"><?= (int)($stats['pages'] ?? 0) ?></strong></div>
                <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('评论')) ?></span><strong class="metric-card__value"><?= (int)($stats['comments'] ?? 0) ?></strong></div>
                <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('附件元数据')) ?></span><strong class="metric-card__value"><?= (int)($stats['attachments'] ?? 0) ?></strong></div>
                <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('分类')) ?></span><strong class="metric-card__value"><?= (int)($stats['categories'] ?? 0) ?></strong></div>
                <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('标签')) ?></span><strong class="metric-card__value"><?= (int)($stats['tags'] ?? 0) ?></strong></div>
                <div class="metric-card"><span class="metric-card__label"><?= h(sblog_t('用户')) ?></span><strong class="metric-card__value"><?= (int)($stats['users'] ?? 0) ?></strong></div>
              </div>
            </div>
          </section>

          <section class="panel admin-list-panel admin-animate admin-animate--3">
            <div class="panel__header"><h2><?= h(sblog_t('确认导入')) ?></h2><p class="panel__meta"><?= h(sblog_t('导入会新增数据，不会覆盖现有内容；再次导入同一备份会跳过已迁移记录。')) ?></p></div>
            <div class="panel__body">
              <div class="flash flash--error"><strong><?= h(sblog_t('兼容性说明')) ?></strong><br><?= h(sblog_t('Typecho 主题短代码会原样保留，导入后可能需要手动调整。')) ?><?php if ((int)($stats['password_protected'] ?? 0) > 0): ?><br><?= h(sblog_t('带访问密码的内容将导入为草稿，避免失去保护后直接公开。')) ?><?php endif; ?></div>
              <form class="form-stack" method="post" action="<?= h(script_url() . '?a=run_typecho_import') ?>">
                <?= csrf_field() ?><input type="hidden" name="token" value="<?= h($token) ?>">
                <div class="field"><label for="typecho-source-url"><?= h(sblog_t('原 Typecho 站点地址')) ?></label><input id="typecho-source-url" name="source_url" type="url" value="<?= h((string)$entry['source_url']) ?>" placeholder="https://old.example.com"><p class="field-hint"><?= h(sblog_t('用于把 /usr/uploads/... 附件路径改写为原站完整地址；留空则保留原路径。')) ?></p></div>
                <div class="field"><label><?= h(sblog_t('选择需要导入的数据')) ?></label><p class="field-hint"><?= h(sblog_t('各项可以分批导入；重复选择同一备份时会自动跳过已经迁移的数据。')) ?></p></div>
                <label class="setting-option"><input name="import_posts" type="checkbox" value="1"><span><strong><?= h(sblog_t('文章')) ?> · <?= (int)($stats['posts'] ?? 0) ?></strong><small><?= h(sblog_t('导入 Typecho 文章；未选择或尚未导入分类时归入“未分类”。')) ?></small></span></label>
                <label class="setting-option"><input name="import_pages" type="checkbox" value="1"><span><strong><?= h(sblog_t('独立页面')) ?> · <?= (int)($stats['pages'] ?? 0) ?></strong><small><?= h(sblog_t('包括 Typecho 标准页面和自定义页面类型。')) ?></small></span></label>
                <label class="setting-option"><input name="import_categories" type="checkbox" value="1"><span><strong><?= h(sblog_t('分类')) ?> · <?= (int)($stats['categories'] ?? 0) ?></strong><small><?= h(sblog_t('可以先导入分类，再分批导入文章。')) ?></small></span></label>
                <label class="setting-option"><input name="import_tags" type="checkbox" value="1"><span><strong><?= h(sblog_t('标签')) ?> · <?= (int)($stats['tags'] ?? 0) ?></strong><small><?= h(sblog_t('文章只会关联已经导入的标签。')) ?></small></span></label>
                <label class="setting-option"><input name="import_comments" type="checkbox" value="1"><span><strong><?= h(sblog_t('评论')) ?> · <?= (int)($stats['comments'] ?? 0) ?></strong><small><?= h(sblog_t('只导入已迁移文章或页面下的评论，并保留层级与审核状态。')) ?></small></span></label>
                <label class="setting-option"><input name="import_media" type="checkbox" value="1"><span><strong><?= h(sblog_t('附件元数据')) ?> · <?= (int)($stats['attachments'] ?? 0) ?></strong><small><?= h(sblog_t('备份不包含附件文件，只会建立指向原附件地址的媒体记录。')) ?></small></span></label>
                <label class="setting-option"><input name="import_users" type="checkbox" value="1"><span><strong><?= h(sblog_t('用户')) ?> · <?= (int)($stats['users'] ?? 0) ?></strong><small><?= h(sblog_t('导入用户资料并映射文章作者；旧密码不会导入，账号需通过“忘记密码”重设密码。')) ?></small></span></label>
                <div class="flash flash--error"><strong><?= h(sblog_t('用户权限提醒')) ?></strong><br><?= h(sblog_t('SBlog 暂无角色分级，导入的 Typecho 用户重设密码后都能进入后台。请只导入可信用户。')) ?></div>
                <label class="setting-option"><input name="confirmed" type="checkbox" value="1" required><span><strong><?= h(sblog_t('我已确认开始导入')) ?></strong><small><?= h(sblog_t('仅导入上方勾选的数据；未导入用户资料时，新增内容归属当前管理员。')) ?></small></span></label>
                <div class="action-row"><button class="button" type="submit"><?= h(sblog_t('开始导入')) ?></button></div>
              </form>
              <form method="post" action="<?= h(script_url() . '?a=cancel_typecho_import') ?>"><?= csrf_field() ?><input type="hidden" name="token" value="<?= h($token) ?>"><div class="action-row"><button class="button button--secondary" type="submit"><?= h(sblog_t('取消并删除临时文件')) ?></button></div></form>
            </div>
          </section>
        <?php else: ?>
          <section class="panel admin-list-panel admin-animate admin-animate--2">
            <div class="panel__header"><h2><?= h(sblog_t('上传 Typecho 备份')) ?></h2><p class="panel__meta"><?= h(sblog_t('支持 Typecho 官方 %TYPECHO_BACKUP_0001% 二进制 .dat 文件，最大 64 MB。')) ?></p></div>
            <div class="panel__body">
              <form class="form-stack" method="post" enctype="multipart/form-data" action="<?= h(script_url() . '?a=preview_typecho_import') ?>">
                <?= csrf_field() ?>
                <div class="field"><label for="typecho-backup"><?= h(sblog_t('备份文件')) ?></label><input id="typecho-backup" name="backup" type="file" accept=".dat,application/octet-stream" required></div>
                <div class="field"><label for="typecho-source-url"><?= h(sblog_t('原 Typecho 站点地址')) ?></label><input id="typecho-source-url" name="source_url" type="url" value="<?= h($defaultSourceUrl) ?>" placeholder="https://old.example.com"><p class="field-hint"><?= h(sblog_t('可选。文件名符合 Typecho 默认规则时会尝试推断，也可以手动修改。')) ?></p></div>
                <div class="flash flash--error"><strong><?= h(sblog_t('附件说明')) ?></strong><br><?= h(sblog_t('Typecho 数据备份只包含附件路径和元数据，不包含图片、视频等文件本体。请确保原站附件仍可访问，或另行迁移 usr/uploads 目录。')) ?></div>
                <div class="action-row"><button class="button" type="submit"><?= h(sblog_t('上传并预检')) ?></button></div>
              </form>
            </div>
          </section>
        <?php endif; ?>
      </div>
    </div>
    <?php
    render_layout(sblog_t('Typecho 数据导入'), (string)ob_get_clean(), ['active' => 'plugins', 'wide' => true, 'description' => sblog_t('导入 Typecho 备份数据')]);
}

function sblog_typecho_import_handle_request(array $context): void
{
    $action = (string)($context['action'] ?? '');
    if ($action === 'admin_typecho_import') {
        sblog_typecho_import_render();
        exit;
    }
    if (!in_array($action, ['preview_typecho_import', 'run_typecho_import', 'cancel_typecho_import'], true)) {
        return;
    }

    require_admin_post(sblog_typecho_import_page_url());
    if ($action === 'preview_typecho_import') {
        try {
            $file = is_array($_FILES['backup'] ?? null) ? $_FILES['backup'] : [];
            $sourceUrl = trim((string)($_POST['source_url'] ?? ''));
            if ($sourceUrl === '') {
                $sourceUrl = sblog_typecho_import_infer_source_url((string)($file['name'] ?? ''));
            }
            $token = sblog_typecho_import_store_upload($file, $sourceUrl);
            redirect_to(sblog_typecho_import_page_url(['token' => $token]));
        } catch (Throwable $exception) {
            set_flash('error', 'Typecho 备份预检失败：' . $exception->getMessage());
            redirect_to(sblog_typecho_import_page_url());
        }
    }

    $token = trim((string)($_POST['token'] ?? ''));
    $entry = sblog_typecho_import_entry($token);
    if ($entry === null) {
        set_flash('error', '待导入文件不存在或已过期，请重新上传。');
        redirect_to(sblog_typecho_import_page_url());
    }
    if ($action === 'cancel_typecho_import') {
        sblog_typecho_import_forget($token);
        set_flash('success', '临时备份文件已删除。');
        redirect_to(sblog_typecho_import_page_url());
    }
    if ((string)($_POST['confirmed'] ?? '') !== '1') {
        set_flash('error', '请先确认导入操作。');
        redirect_to(sblog_typecho_import_page_url(['token' => $token]));
    }

    try {
        $parsed = sblog_typecho_import_parse((string)$entry['path']);
        if (!hash_equals((string)$entry['hash'], (string)$parsed['hash'])) {
            throw new RuntimeException('待导入文件已发生变化。');
        }
        $sourceUrl = sblog_typecho_import_normalize_source_url((string)($_POST['source_url'] ?? ''));
        $selected = [
            'posts' => isset($_POST['import_posts']),
            'pages' => isset($_POST['import_pages']),
            'users' => isset($_POST['import_users']),
            'categories' => isset($_POST['import_categories']),
            'tags' => isset($_POST['import_tags']),
            'comments' => isset($_POST['import_comments']),
            'media' => isset($_POST['import_media']),
        ];
        $result = sblog_typecho_import_data($parsed, $sourceUrl, $selected);
        sblog_typecho_import_forget($token);
        $_SESSION['sblog_typecho_import_result'] = $result;
        set_flash('success', sblog_typecho_import_result_message($result));
        redirect_to(sblog_typecho_import_page_url());
    } catch (Throwable $exception) {
        set_flash('error', 'Typecho 数据导入失败，所有写入已回滚：' . $exception->getMessage());
        redirect_to(sblog_typecho_import_page_url(['token' => $token]));
    }
}

add_plugin_action('request', 'sblog_typecho_import_handle_request');
