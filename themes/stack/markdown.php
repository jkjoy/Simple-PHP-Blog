<?php

declare(strict_types=1);

/** Fixed Hugo-compatible shortcodes; generated HTML is restored only after core sanitation. */
function stack_shortcode_arguments(string $source): array
{
    preg_match_all('/(?:([\w-]+)\s*=\s*)?("(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\s]+)/u', $source, $matches, PREG_SET_ORDER);
    $arguments = [];
    foreach ($matches as $match) {
        $value = $match[2];
        if (strlen($value) >= 2 && in_array($value[0], ['"', "'"], true) && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
            $value = preg_replace('/\\\\([\\\\"\'])/', '$1', $value) ?? $value;
        }
        if (($match[1] ?? '') !== '') { $arguments[strtolower($match[1])] = $value; }
        else { $arguments[] = $value; }
    }
    return $arguments;
}

function stack_shortcode_url(string $value): string
{
    if (str_starts_with($value, 'uploads/')) { $value = asset_url($value); }
    $url = safe_link_url($value);
    return $url === '#' ? '' : $url;
}

function stack_markdown_fragment(string $markdown): string
{
    return function_exists('stack_markdown_render') ? stack_markdown_render($markdown) : markdown_to_html($markdown);
}

function stack_media_iframe(string $title, string $source): string
{
    return '<figure class="media-embed media-embed--video"><iframe src="' . h($source) . '" title="' . h($title)
        . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></figure>';
}

function stack_shortcode_html(string $name, array $args, ?string $inner): ?string
{
    $first = (string)($args[0] ?? '');
    if ($name === 'quote' && $inner !== null) {
        $cite = '';
        $author = trim((string)($args['author'] ?? ''));
        $source = trim((string)($args['source'] ?? ''));
        $url = stack_shortcode_url((string)($args['url'] ?? ''));
        if ($author !== '' || $source !== '') {
            $cite = '<span class="cite">― ' . h($author) . ($author !== '' && $source !== '' ? ', ' : '')
                . ($url !== '' ? '<a href="' . h($url) . '" rel="noopener noreferrer">' : '')
                . ($source !== '' ? '<cite>' . h($source) . '</cite>' : '') . ($url !== '' ? '</a>' : '') . '</span>';
        }
        return '<blockquote class="stack-quote">' . stack_markdown_fragment($inner) . $cite . '</blockquote>';
    }
    if ($name === 'highlight' && $inner !== null) {
        $language = preg_replace('/[^\w+-]/', '', (string)($args['lang'] ?? $first)) ?? '';
        $options = implode(' ', array_map('strval', array_filter($args, 'is_int', ARRAY_FILTER_USE_KEY)));
        $options .= ' ' . implode(' ', array_map(static fn($key, $value): string => $key . '=' . $value, array_keys(array_filter($args, 'is_string', ARRAY_FILTER_USE_KEY)), array_values(array_filter($args, 'is_string', ARRAY_FILTER_USE_KEY))));
        $attributes = $language !== '' ? ' class="language-' . h($language) . '"' : '';
        if (preg_match('/\blinenos\s*=\s*(true|false|0|1|table|inline)\b/i', $options, $match)) {
            $attributes .= ' data-stack-linenos="' . (in_array(strtolower($match[1]), ['false', '0'], true) ? 'false' : 'true') . '"';
        }
        if (preg_match('/\blinenostart\s*=\s*(\d+)/i', $options, $match)) {
            $attributes .= ' data-stack-linenostart="' . max(1, min(1000000000, (int)$match[1])) . '"';
        }
        if (preg_match('/\bhl_lines\s*=\s*(\[[^\]]*\]|"[^"]*"|\'[^\']*\'|[\d\s,-]+)/i', $options, $match)) {
            preg_match_all('/\b\d+(?:-\d+)?\b/', $match[1], $ranges);
            $attributes .= ' data-stack-hl-lines="' . implode(' ', array_slice($ranges[0], 0, 100)) . '"';
        }
        $code = preg_replace('/\A\r?\n|\r?\n\z/', '', $inner) ?? $inner;
        return '<pre><code' . $attributes . '>' . h($code) . '</code></pre>';
    }
    if ($name === 'gallery' && $inner !== null) {
        return '<div class="stack-shortcode-gallery">' . stack_markdown_fragment($inner) . '</div>';
    }
    if ($name === 'figure') {
        $src = stack_shortcode_url((string)($args['src'] ?? $first));
        if ($src === '') { return null; }
        $alt = (string)($args['alt'] ?? '');
        $title = (string)($args['title'] ?? '');
        $caption = (string)($args['caption'] ?? $title);
        $link = stack_shortcode_url((string)($args['link'] ?? ''));
        $image = '<img src="' . h($src) . '" alt="' . h($alt) . '"' . ($title !== '' ? ' title="' . h($title) . '"' : '') . ' loading="lazy" decoding="async">';
        if ($link !== '') { $image = '<a href="' . h($link) . '">' . $image . '</a>'; }
        return '<figure class="stack-figure">' . $image . ($caption !== '' ? '<figcaption>' . h($caption) . '</figcaption>' : '') . '</figure>';
    }
    if ($name === 'video') {
        $src = stack_shortcode_url((string)($args['src'] ?? $first));
        if ($src === '') { return null; }
        $poster = stack_shortcode_url((string)($args['poster'] ?? ''));
        $attributes = '';
        foreach (['autoplay', 'muted', 'loop'] as $attribute) {
            if (in_array(strtolower((string)($args[$attribute] ?? 'false')), ['true', '1'], true)) { $attributes .= ' ' . $attribute; }
        }
        return '<div class="video-wrapper"><video controls playsinline preload="metadata" src="' . h($src) . '"'
            . ($poster !== '' ? ' poster="' . h($poster) . '"' : '') . $attributes . '></video></div>';
    }
    if ($name === 'youtube') {
        $id = (string)($args['id'] ?? $first);
        if (!preg_match('/^[\w-]{6,15}$/D', $id)) { return null; }
        $start = max(0, min(604800, (int)($args['start'] ?? 0)));
        return stack_media_iframe('YouTube 视频', 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . ($start ? '?start=' . $start : ''));
    }
    if ($name === 'bilibili') {
        $id = (string)($args['id'] ?? $first);
        if (!preg_match('/^(?:av\d+|BV[0-9A-Za-z]+)$/iD', $id)) { return null; }
        $page = max(1, min(10000, (int)($args['page'] ?? $args[1] ?? 1)));
        $parameter = strncasecmp($id, 'av', 2) === 0 ? 'aid=' . substr($id, 2) : 'bvid=' . rawurlencode($id);
        return stack_media_iframe('哔哩哔哩视频', 'https://player.bilibili.com/player.html?' . $parameter . '&page=' . $page . '&high_quality=1&danmaku=0&as_wide=1');
    }
    if ($name === 'tencent') {
        $id = (string)($args['id'] ?? $first);
        if (!preg_match('/^[A-Za-z0-9]{6,32}$/D', $id)) { return null; }
        return stack_media_iframe('腾讯视频', 'https://v.qq.com/txp/iframe/player.html?vid=' . rawurlencode($id));
    }
    if ($name === 'gitlab') {
        $id = (string)($args['id'] ?? $first);
        if (!preg_match('/^\d{1,12}$/D', $id)) { return null; }
        $url = 'https://gitlab.com/-/snippets/' . $id;
        // Upstream's remote snippet script runs in an isolated frame, never in the blog document.
        $srcdoc = '<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;font:14px sans-serif}pre{white-space:pre-wrap;overflow-wrap:anywhere}</style></head><body><script src="' . $url . '.js"></script></body></html>';
        return '<div class="stack-snippet"><iframe title="GitLab snippet ' . h($id) . '" sandbox="allow-scripts" referrerpolicy="no-referrer" loading="lazy" srcdoc="' . h($srcdoc) . '"></iframe><a href="' . h($url) . '" target="_blank" rel="noopener noreferrer">GitLab #' . h($id) . '</a></div>';
    }
    return null;
}

function stack_shortcode_source(string $markdown, array $context): string
{
    $renderId = (string)($context['render_id'] ?? '');
    if ($renderId === '' || !str_contains($markdown, '{{')) { return $markdown; }
    $prefix = 'STACK' . bin2hex(random_bytes(12));
    $protected = [];
    $lines = preg_split('/(?<=\n)/', $markdown) ?: [];
    $fence = null;
    $buffer = '';
    $safe = '';
    $protect = static function (string $text) use (&$protected, $prefix): string {
        $token = $prefix . 'CODE' . count($protected) . 'END';
        $hasNewline = str_ends_with($text, "\n");
        $protected[$token] = $hasNewline ? substr($text, 0, -1) : $text;
        return $token . ($hasNewline ? "\n" : '');
    };
    foreach ($lines as $line) {
        $logicalLine = preg_replace('/^(?: {0,3}>[ \t]?)+/', '', rtrim($line, "\r\n")) ?? $line;
        $logicalLine = preg_replace('/^ {0,3}(?:[-+*]|\d+[.)])[ \t]+/', '', $logicalLine) ?? $logicalLine;
        if ($fence !== null) {
            $buffer .= $line;
            if (preg_match('/^ {0,3}' . preg_quote($fence[0], '/') . '{' . $fence[1] . ',}[ \t]*$/', $logicalLine)) {
                $safe .= $protect($buffer); $fence = null; $buffer = '';
            }
        } elseif (preg_match('/^ {0,3}(`{3,}|~{3,})/', $logicalLine, $match)) {
            $fence = [$match[1][0], strlen($match[1])]; $buffer = $line;
        } elseif (preg_match('/^(?: {4}|\t)/', $line)) {
            $safe .= $protect($line);
        } else { $safe .= $line; }
    }
    if ($buffer !== '') { $safe .= $protect($buffer); }
    $safe = preg_replace_callback('/(?<!`)(`+)(?!`)[\s\S]*?\1(?!`)/', static function (array $match) use (&$protected, $prefix): string {
        $token = $prefix . 'CODE' . count($protected) . 'END';
        $protected[$token] = $match[0];
        return $token;
    }, $safe) ?? $safe;
    preg_match_all('/\{\{([<%])\s*(\/?)([a-zA-Z][\w-]*)\b([\s\S]*?)\s*([>%])\}\}/', $safe, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $result = ''; $offset = 0; $outputs = [];
    for ($index = 0; $index < count($matches); $index++) {
        $match = $matches[$index];
        if ($match[0][1] < $offset || $match[2][0] !== '' || ($match[1][0] === '<' ? '>' : '%') !== $match[5][0]) { continue; }
        $name = strtolower($match[3][0]);
        $end = $match[0][1] + strlen($match[0][0]);
        $inner = null;
        if (in_array($name, ['quote', 'highlight', 'gallery'], true)) {
            $level = 1; $closing = null;
            for ($next = $index + 1; $next < count($matches); $next++) {
                if (strtolower($matches[$next][3][0]) !== $name || $matches[$next][1][0] !== $match[1][0]) { continue; }
                $level += $matches[$next][2][0] === '/' ? -1 : 1;
                if ($level === 0) { $closing = $matches[$next]; break; }
            }
            if ($closing === null) { continue; }
            $inner = strtr(substr($safe, $end, $closing[0][1] - $end), $protected);
            $end = $closing[0][1] + strlen($closing[0][0]);
        }
        $output = stack_shortcode_html($name, stack_shortcode_arguments($match[4][0]), $inner);
        if ($output === null) { continue; }
        $token = $prefix . 'HTML' . count($outputs) . 'END';
        $outputs[$token] = $output;
        $result .= substr($safe, $offset, $match[0][1] - $offset) . "\n\n" . $token . "\n\n";
        $offset = $end;
    }
    $result .= substr($safe, $offset);
    $GLOBALS['stack_shortcode_outputs'][$renderId] = $outputs;
    return strtr($result, $protected);
}

function stack_shortcode_restore(string $html, array $context): string
{
    $renderId = (string)($context['render_id'] ?? '');
    $outputs = $GLOBALS['stack_shortcode_outputs'][$renderId] ?? [];
    unset($GLOBALS['stack_shortcode_outputs'][$renderId]);
    foreach ($outputs as $token => $output) {
        $html = str_replace('<p>' . $token . '</p>', $output, $html);
        $html = str_replace($token, $output, $html);
    }
    return $html;
}

/** Only local upload rasters are read; remote URLs and files outside uploads stay untouched. */
function stack_local_image(string $url): ?array
{
    $urlParts = parse_url($url);
    $uploadUrl = absolute_url(asset_url('uploads/'));
    $base = parse_url($uploadUrl);
    if ($urlParts === false || $base === false || isset($urlParts['user']) || isset($urlParts['pass'])) { return null; }
    if (isset($urlParts['host']) && (strtolower($urlParts['host']) !== strtolower((string)($base['host'] ?? '')) || ($urlParts['port'] ?? null) !== ($base['port'] ?? null))) { return null; }
    $path = rawurldecode((string)($urlParts['path'] ?? ''));
    $basePath = (string)($base['path'] ?? '/uploads/');
    if (!str_starts_with($path, $basePath) || str_contains($path, "\0") || str_contains($path, '\\')) { return null; }
    $root = realpath(UPLOAD_DIR);
    $file = realpath(UPLOAD_DIR . '/' . substr($path, strlen($basePath)));
    if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file) || filesize($file) > 20971520) { return null; }
    $info = @getimagesize($file);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 32000000) { return null; }
    return ['file' => $file, 'width' => $info[0], 'height' => $info[1], 'type' => $info[2]];
}

function stack_responsive_image(DOMElement $image): void
{
    static $variants = [];
    $local = stack_local_image($image->getAttribute('src'));
    if (!$local) { return; }
    if (!$image->hasAttribute('width') && !$image->hasAttribute('height')) {
        $image->setAttribute('width', (string)$local['width']);
        $image->setAttribute('height', (string)$local['height']);
    }
    $supportedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if (defined('IMAGETYPE_AVIF')) { $supportedTypes[] = constant('IMAGETYPE_AVIF'); }
    if (!stack_enabled('image_processing_enabled') || $image->hasAttribute('srcset') || $image->hasAttribute('data-no-resize')
        || !function_exists('imagecreatetruecolor') || !in_array($local['type'], $supportedTypes, true)) { return; }
    $widths = array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,]+/', theme_setting('image_processing_widths', '480,800,1024,1600')) ?: []), static fn(int $width): bool => $width >= 160 && $width <= 3200 && $width < $local['width'])));
    sort($widths);
    $widths = array_slice($widths, 0, 6);
    if (!$widths) { return; }
    $key = $local['file'] . ':' . implode(',', $widths);
    if (!isset($variants[$key])) {
        $variants[$key] = [];
        $directory = UPLOAD_DIR . '/stack-images';
        $root = realpath(UPLOAD_DIR);
        if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0755, true)) || !is_writable($directory)
            || !$root || !str_starts_with((string)realpath($directory), $root . DIRECTORY_SEPARATOR)) { return; }
        $digest = substr((string)hash_file('sha256', $local['file']), 0, 32);
        $useWebp = function_exists('imagewebp');
        $extension = $useWebp ? 'webp' : ($local['type'] === IMAGETYPE_JPEG ? 'jpg' : 'png');
        $source = null;
        foreach ($widths as $width) {
            $name = $digest . '-' . $width . '.' . $extension;
            $target = $directory . '/' . $name;
            if (!is_file($target)) {
                if ($source === null) {
                    // Reserve headroom for decoded source + resampling buffer under PHP's memory limit.
                    $limit = ini_get('memory_limit');
                    $bytes = (int)$limit;
                    if (preg_match('/([KMG])$/i', $limit, $unit)) { $bytes *= 1024 ** (strpos('KMG', strtoupper($unit[1])) + 1); }
                    if ($bytes > 0 && memory_get_usage(true) + $local['width'] * $local['height'] * 8 + 16777216 > $bytes) { break; }
                    $contents = (string)file_get_contents($local['file']);
                    // Animated WebP/PNG must keep their original frames just like GIF.
                    if (($local['type'] === IMAGETYPE_WEBP && str_contains($contents, 'ANIM'))
                        || ($local['type'] === IMAGETYPE_PNG && str_contains($contents, 'acTL'))) { break; }
                    $source = @imagecreatefromstring($contents);
                    unset($contents);
                    if (!$source) { break; }
                }
                $height = max(1, (int)round($local['height'] * $width / $local['width']));
                $resized = imagecreatetruecolor($width, $height);
                imagealphablending($resized, false); imagesavealpha($resized, true);
                imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, $local['width'], $local['height']);
                $temporary = tempnam($directory, '.stack-');
                if ($temporary === false) { imagedestroy($resized); break; }
                $saved = $useWebp ? imagewebp($resized, $temporary, 82) : ($extension === 'jpg' ? imagejpeg($resized, $temporary, 85) : imagepng($resized, $temporary));
                imagedestroy($resized);
                if (!$saved || !@rename($temporary, $target)) { @unlink($temporary); continue; }
            }
            $variants[$key][] = asset_url('uploads/stack-images/' . $name) . ' ' . $width . 'w';
        }
        if ($source) { imagedestroy($source); }
    }
    if (!$variants[$key]) { return; }
    $image->setAttribute('srcset', implode(', ', $variants[$key]) . ', ' . $image->getAttribute('src') . ' ' . $local['width'] . 'w');
    $image->setAttribute('sizes', '(max-width: 767px) calc(100vw - 45px), (max-width: 1023px) 700px, (max-width: 1279px) 950px, 1232px');
    $image->setAttribute('loading', 'lazy');
    $image->setAttribute('decoding', 'async');
}

function stack_image_html(string $source, string $alt, string $loading = 'lazy'): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $image = $document->createElement('img');
    $image->setAttribute('src', $source);
    $image->setAttribute('alt', $alt);
    $image->setAttribute('loading', $loading);
    $image->setAttribute('decoding', 'async');
    stack_responsive_image($image);
    $image->setAttribute('loading', $loading);
    return $document->saveHTML($image);
}

add_theme_filter('markdown_source', 'stack_shortcode_source');
add_theme_filter('markdown_html', 'stack_shortcode_restore');
