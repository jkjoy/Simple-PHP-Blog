<?php

declare(strict_types=1);

if (!defined('PLUGINS_DIR')) {
    http_response_code(403);
    exit;
}

function stack_markdown_fence_line(string $line, ?array &$fence): bool
{
    if ($fence !== null) {
        if (preg_match('/^ {0,3}' . preg_quote($fence[0], '/') . '{' . $fence[1] . ',}[ \t]*$/', $line)) {
            $fence = null;
            return true;
        }
        return false;
    }
    if (preg_match('/^ {0,3}(`{3,}|~{3,})([^\r\n]*)$/', $line, $match)
        && !($match[1][0] === '`' && str_contains($match[2], '`'))) {
        $fence = [$match[1][0], strlen($match[1])];
        return true;
    }
    return false;
}

function stack_markdown_segments(string $markdown): array
{
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    if (!str_contains($markdown, '[reply]')) {
        return [['hidden' => false, 'markdown' => $markdown]];
    }
    $lines = explode("\n", $markdown);
    $segments = [];
    $buffer = [];
    $depth = 0;
    $fence = null;

    $flush = static function () use (&$segments, &$buffer, &$depth): void {
        if ($buffer === []) {
            return;
        }
        $segments[] = ['hidden' => $depth > 0, 'markdown' => implode("\n", $buffer)];
        $buffer = [];
    };

    foreach ($lines as $line) {
        if (stack_markdown_fence_line($line, $fence)) {
            $buffer[] = $line;
            continue;
        }
        $inCode = $fence !== null || preg_match('/^(?: {4}|\t)/', $line);
        if (!$inCode && trim($line) === '[reply]') {
            if ($depth === 0) {
                $flush();
            }
            $depth++;
            continue;
        }
        if (!$inCode && trim($line) === '[/reply]' && $depth > 0) {
            if ($depth === 1) {
                $flush();
            }
            $depth--;
            continue;
        }
        $buffer[] = $line;
    }
    $flush();

    return $segments;
}

function stack_markdown_public_source(string $markdown): string
{
    if (!str_contains($markdown, '[reply]')) {
        return trim(str_replace(["\r\n", "\r"], "\n", $markdown), "\n");
    }
    $public = [];
    foreach (stack_markdown_segments($markdown) as $segment) {
        if (empty($segment['hidden'])) {
            $public[] = (string)$segment['markdown'];
        }
    }
    return trim(implode("\n\n", $public), "\n");
}

function stack_markdown_safe_url(string $url): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) { return '#'; }
    $safe = safe_link_url($url);
    if ($safe !== '#') { return $safe; }
    if (preg_match('/^mailto:([^?]+)(?:\?[^\r\n]*)?$/i', $url, $email)
        && filter_var($email[1], FILTER_VALIDATE_EMAIL)) { return $url; }
    // Markdown also permits site-relative paths such as ../images/photo.jpg.
    return !preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $url) ? $url : '#';
}

function stack_markdown_sanitize_html(string $html): string
{
    if (!class_exists('DOMDocument')) {
        // The parser disables raw markup in this mode and filters every generated URL.
        return $html;
    }
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><div id="sblog-markdown-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $document->getElementById('sblog-markdown-root');
    if (!$root) { return ''; }
    $allowed = array_fill_keys(explode(' ', 'a abbr address article aside b bdi bdo blockquote br caption cite code col colgroup dd del details dfn div dl dt em figcaption figure h1 h2 h3 h4 h5 h6 hr i img ins kbd li mark ol p pre q rp rt ruby s samp section small span strong sub summary sup table tbody td th thead time tr u ul var wbr'), true);
    $remove = array_fill_keys(explode(' ', 'script style iframe frame frameset object embed applet svg math template form input button textarea select option link meta base noscript'), true);
    $clean = static function (DOMNode $parent) use (&$clean, $document, $allowed, $remove): void {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMComment || $node instanceof DOMProcessingInstruction) { $parent->removeChild($node); continue; }
            if (!$node instanceof DOMElement) { continue; }
            $tag = strtolower($node->tagName);
            if (isset($remove[$tag])) { $parent->removeChild($node); continue; }
            $clean($node);
            if (!isset($allowed[$tag])) {
                while ($node->firstChild) { $parent->insertBefore($node->firstChild, $node); }
                $parent->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = $attribute->value;
                $keep = in_array($name, ['title', 'lang', 'aria-label'], true);
                if ($name === 'id') { $keep = strlen($value) <= 200 && !preg_match('/[\x00-\x20"\'<>]/', $value); }
                if ($name === 'class') { $keep = preg_match('/^[a-zA-Z0-9_+.: -]{0,300}$/D', $value) === 1; }
                if ($name === 'dir') { $keep = in_array($value, ['ltr', 'rtl', 'auto'], true); }
                if ($name === 'role') { $keep = in_array($value, ['doc-endnotes', 'doc-noteref', 'doc-backlink', 'note'], true); }
                if ($tag === 'a' && $name === 'href') { $node->setAttribute('href', stack_markdown_safe_url($value)); $keep = true; }
                if ($tag === 'img' && $name === 'src') { $node->setAttribute('src', stack_markdown_safe_url($value)); $keep = true; }
                if ($tag === 'img' && $name === 'alt') { $keep = true; }
                if ($tag === 'img' && in_array($name, ['width', 'height'], true)) { $keep = preg_match('/^[1-9][0-9]{0,4}$/D', $value) === 1; }
                if ($tag === 'ol' && $name === 'start') { $keep = preg_match('/^-?[0-9]{1,9}$/D', $value) === 1; }
                if ($tag === 'details' && $name === 'open') { $keep = true; }
                if ($tag === 'time' && $name === 'datetime') { $keep = true; }
                if (in_array($tag, ['td', 'th', 'col', 'colgroup'], true) && in_array($name, ['colspan', 'rowspan', 'span'], true)) { $keep = preg_match('/^[1-9][0-9]{0,2}$/D', $value) === 1; }
                if (in_array($tag, ['td', 'th'], true) && $name === 'align') { $keep = in_array($value, ['left', 'right', 'center'], true); }
                if (in_array($tag, ['td', 'th'], true) && $name === 'style') { $keep = preg_match('/^text-align:\s*(left|right|center);?$/D', $value) === 1; }
                if ($tag === 'code' && $name === 'data-stack-linenos') { $keep = in_array($value, ['true', 'false'], true); }
                if ($tag === 'code' && $name === 'data-stack-linenostart') { $keep = preg_match('/^[1-9][0-9]{0,6}$/D', $value) === 1; }
                if ($tag === 'code' && $name === 'data-stack-hl-lines') { $keep = preg_match('/^[0-9 -]{0,1500}$/D', $value) === 1; }
                if (in_array($name, ['data-no-gallery', 'data-no-zoom', 'data-no-resize', 'data-no-toc'], true)) { $keep = true; }
                if (!$keep) { $node->removeAttribute($attribute->name); }
            }
            if ($tag === 'a' && preg_match('#^https?://#i', $node->getAttribute('href'))) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer');
            }
            if ($tag === 'img') { $node->setAttribute('loading', 'lazy'); }
        }
    };
    $clean($root);
    $xpath = new DOMXPath($document);
    // Task markers become read-only controls; arbitrary input HTML is never allowed.
    foreach ($xpath->query('.//li', $root) as $item) {
        $container = $item->firstChild;
        if ($container instanceof DOMElement && $container->tagName === 'p') { $container = $container->firstChild; }
        if (!$container instanceof DOMText || !preg_match('/^\[([ xX])\][ \t]+/', $container->nodeValue, $task)) { continue; }
        $container->nodeValue = substr($container->nodeValue, strlen($task[0]));
        $checkbox = $document->createElement('input');
        $checkbox->setAttribute('type', 'checkbox');
        $checkbox->setAttribute('disabled', '');
        $checkbox->setAttribute('aria-label', strtolower($task[1]) === 'x' ? '已完成' : '未完成');
        if (strtolower($task[1]) === 'x') { $checkbox->setAttribute('checked', ''); }
        $container->parentNode->insertBefore($checkbox, $container);
        $container->parentNode->insertBefore($document->createTextNode(' '), $container);
        $item->setAttribute('class', trim($item->getAttribute('class') . ' task-list-item'));
        $classes = preg_split('/\s+/', trim($item->parentNode->getAttribute('class') . ' task-list'));
        $item->parentNode->setAttribute('class', implode(' ', array_unique($classes)));
    }
    // Keep SBlog's bare-link convenience, without linking code or existing links twice.
    foreach (iterator_to_array($xpath->query('.//text()[not(ancestor::a or ancestor::code or ancestor::pre or ancestor::kbd or ancestor::samp)]', $root)) as $text) {
        if (!preg_match_all('~(?<![A-Z0-9_])https?://[A-Z0-9._:/?#\[\]@!$&\'()*+,;=%\~-]+~iu', $text->nodeValue, $matches, PREG_OFFSET_CAPTURE)) { continue; }
        $fragment = $document->createDocumentFragment();
        $offset = 0;
        foreach ($matches[0] as [$matched, $position]) {
            $fragment->appendChild($document->createTextNode(substr($text->nodeValue, $offset, $position - $offset)));
            [$url, $suffix] = split_bare_url_suffix($matched);
            $link = $document->createElement('a');
            $link->setAttribute('href', stack_markdown_safe_url($url));
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer');
            $link->appendChild($document->createTextNode($url));
            $fragment->appendChild($link);
            $fragment->appendChild($document->createTextNode($suffix));
            $offset = $position + strlen($matched);
        }
        $fragment->appendChild($document->createTextNode(substr($text->nodeValue, $offset)));
        $text->parentNode->replaceChild($fragment, $text);
    }
    $result = '';
    foreach ($root->childNodes as $child) { $result .= $document->saveHTML($child); }
    // Only trusted core/provider helpers can create embeds, after sanitizing author HTML.
    return preg_replace_callback('~<p>([^<]*(?:<a\b[^>]*>[^<]*</a>)?[^<]*)</p>~i', static function (array $match): string {
        $text = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = media_url_from_paragraph($text);
        $embed = $url !== '' ? media_embed_html($url) : '';
        return $embed !== '' ? $embed : $match[0];
    }, $result) ?? $result;
}

function stack_markdown_render(string $markdown): string
{
    $markdown = trim(str_replace(["\r\n", "\r"], "\n", stack_markdown_public_source($markdown)), "\n");
    if (trim($markdown) === '') { return '<p>' . h(sblog_t('暂无内容。')) . '</p>'; }
    static $renderIndex = 0;
    $context = ['render_id' => ++$renderIndex];
    // Source hooks only receive content after password/reply visibility checks.
    $source = theme_filter('markdown_source', $markdown, $context);
    if (is_string($source)) { $markdown = $source; }
    require_once __DIR__ . '/vendor/SBlogMarkdown.php';
    $parser = new StackMarkdownParser();
    $parser->fn_id_prefix = 'md' . $context['render_id'] . '-';
    $parser->fn_backlink_label = '返回引用 ^^（%%）';
    $parser->no_markup = !class_exists('DOMDocument');
    $parser->url_filter_func = 'stack_markdown_safe_url';
    $html = stack_markdown_sanitize_html($parser->transform($markdown));
    $filtered = theme_filter('markdown_html', $html, $context);
    return is_string($filtered) ? $filtered : $html;
}

function stack_markdown_render_post(array $post): string
{
    if (content_requires_password($post) && !content_password_is_unlocked($post)) {
        return render_password_content_gate($post);
    }
    $segments = stack_markdown_segments((string)($post['content'] ?? ''));
    $canViewHidden = in_array(true, array_column($segments, 'hidden'), true) && can_view_reply_hidden_content($post);
    $html = [];
    foreach ($segments as $segment) {
        if (!empty($segment['hidden']) && !$canViewHidden) {
            $html[] = render_reply_content_gate($post);
            continue;
        }
        $markdown = trim((string)$segment['markdown'], "\r\n");
        if (trim($markdown) !== '') { $html[] = stack_markdown_render($markdown); }
    }
    return $html !== [] ? implode("\n", $html) : '<p>' . h(sblog_t('暂无内容。')) . '</p>';
}

function stack_markdown_filter_content(string $html, array $context): string
{
    if (!class_exists('DOMDocument') || ($context['options']['status'] ?? 200) !== 200 || ($context['options']['mode'] ?? 'public') !== 'public') { return $html; }
    $action = (string)($GLOBALS['sblog_current_action'] ?? $_GET['a'] ?? 'home');
    if (!in_array($action, ['post', 'page'], true) || !str_contains($html, 'post-content')) { return $html; }
    $slug = trim((string)($_GET['slug'] ?? $_GET['id'] ?? ''));
    $post = $action === 'post' ? fetch_post_by_identifier($slug, is_admin()) : fetch_page_by_identifier($slug, is_admin());
    if ($post === null) { return $html; }
    // Keep the core's already-rendered password form, including one-time notices.
    if (content_requires_password($post) && !content_password_is_unlocked($post)) { return $html; }
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><div id="stack-markdown-source">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    $xpath = new DOMXPath($document);
    $body = $xpath->query('//*[@id="stack-markdown-source"]/article[1]//*[contains(concat(" ", normalize-space(@class), " "), " post-content ")]')->item(0);
    if (!$body) { libxml_clear_errors(); libxml_use_internal_errors($previous); return $html; }
    $rendered = stack_markdown_render_post($post);
    $replacement = new DOMDocument('1.0', 'UTF-8');
    $replacement->loadHTML('<?xml encoding="utf-8" ?><div id="stack-markdown-replacement">' . $rendered . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    $replacementRoot = $replacement->getElementById('stack-markdown-replacement');
    if (!$replacementRoot) { libxml_clear_errors(); libxml_use_internal_errors($previous); return $html; }
    while ($body->firstChild) { $body->removeChild($body->firstChild); }
    foreach (iterator_to_array($replacementRoot->childNodes) as $child) { $body->appendChild($document->importNode($child, true)); }
    $result = '';
    foreach ($document->getElementById('stack-markdown-source')->childNodes as $child) { $result .= $document->saveHTML($child); }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $result;
}

// Run before theme presentation filters. The original core stays untouched.
add_theme_filter('content', 'stack_markdown_filter_content', 5);
