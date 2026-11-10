<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$project = dirname(__DIR__, 3);
$source = file_get_contents($project . '/index.php');
$wanted = array_fill_keys(['h','str_lower_u','is_ascii_digits','valid_http_url','safe_link_url','split_bare_url_suffix','media_host_matches','media_iframe_html','media_url_from_paragraph','media_embed_html'], true);
$tokens = token_get_all($source);
for ($i = 0; $i < count($tokens); $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) { continue; }
    $j = $i + 1;
    while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { $j++; }
    if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || !isset($wanted[$tokens[$j][1]])) { continue; }
    $body = ''; $depth = 0; $started = false;
    do {
        $token = $tokens[$i++];
        if ($token === '{') { $depth++; $started = true; }
        if ($token === '}') { $depth--; }
        $body .= is_array($token) ? $token[1] : $token;
    } while (!$started || $depth !== 0);
    $i--;
    eval($body);
}
function sblog_t(string $text): string { return $text; }
function plugin_filter(string $hook, mixed $value, array $context = []): mixed { return $value; }
function theme_filter(string $hook, mixed $value, array $context = []): mixed {
    $GLOBALS['hooks'][] = [$hook, $value, $context];
    $priorities = $GLOBALS['testThemeFilters'][$hook] ?? [];
    ksort($priorities);
    foreach ($priorities as $callbacks) { foreach ($callbacks as $callback) { $value = $callback($value, $context); } }
    return $value;
}
define('PLUGINS_DIR', $project . '/plugins');
function add_theme_filter(string $hook, callable $callback, int $priority = 10): void { $GLOBALS['testThemeFilters'][$hook][$priority][] = $callback; }
function asset_url(string $path): string { return '/' . ltrim($path, '/'); }
require $project . '/plugins/stack-markdown/plugin.php';
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks; $checks++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}
function render(string $input): string { return stack_markdown_render($input); }
function content_requires_password(array $post): bool { return !empty($post['content_password_hash']); }
function content_password_is_unlocked(array $post): bool { return !empty($GLOBALS['passwordUnlocked']); }
function can_view_reply_hidden_content(array $post): bool { return !empty($GLOBALS['replyUnlocked']); }
function render_password_content_gate(array $post): string { return '<aside class="content-gate--password">PASSWORD_GATE</aside>'; }
function render_reply_content_gate(array $post): string { return '<aside class="content-gate--reply">REPLY_GATE</aside>'; }
$twoSpaces = render("- Parent\n  - Child\n    - Grandchild\n\n1. Parent\n   1. Child");
$headings = render("# H1\n\n## H2\n\n### H3\n\n#### H4\n\n##### H5\n\n###### H6\n\nSetext one\n===\n\nSetext two\n---");
foreach (range(1,6) as $level) { check(str_contains($headings, '<h' . $level . '>'), 'H' . $level); }
check(substr_count($headings, '<h1>') === 2 && substr_count($headings, '<h2>') === 2, 'Setext');
$lists = render("3. Start\n4. Next\n    - Child\n        1. Deep\n\n- [ ] Open\n- [x] Done\n    - [X] Nested done");
check(str_contains($lists, '<ol start="3">'), 'Ordered start');
check(substr_count($lists, '<ul') >= 3 && substr_count($lists, '<ol') >= 2, 'Nested lists');
check(substr_count($lists, 'type="checkbox"') === 3 && substr_count($lists, ' checked') === 2 && substr_count($lists, ' disabled') === 3, 'Tasks');
$inline = render('_italic_ __bold__ ~~deleted~~ `not _italic_` [ref][r] ![photo][p]' . "\n\n[r]: /go \"Link title\"\n[p]: /uploads/photo.jpg \"Image title\"");
check(str_contains($inline, '<em>italic</em>') && str_contains($inline, '<strong>bold</strong>') && str_contains($inline, '<del>deleted</del>'), 'Emphasis/strike');
check(str_contains($inline, '<code>not _italic_</code>'), 'Inline code untouched');
check(str_contains($inline, 'href="/go" title="Link title"') && str_contains($inline, 'title="Image title"'), 'Reference title links/images');
$foot1 = render("Text[^a] and again[^a].\n\n[^a]: First **bold** footnote.\n\n    Second paragraph.");
$foot2 = render("Other[^a].\n\n[^a]: Other footnote.");
check(str_contains($foot1, 'class="footnotes"') && str_contains($foot1, '<strong>bold</strong>'), 'Footnotes');
preg_match('/id="fn:([^"]+)"/', $foot1, $first); preg_match('/id="fn:([^"]+)"/', $foot2, $second);
check($first[1] !== $second[1], 'Distinct footnote ids across segments');
$code = render("~~~php {linenos=true,linenostart=8,hl_lines=[\"1-2\",\"4\"]}\n<?php echo '<script>';\n~~~\n\n````md\n```literal\n[reply]\ninside code\n[/reply]\n```\n````\n\n    indented <unsafe>\n\n---");
check(substr_count($code, '<pre>') === 3 && str_contains($code, '&lt;?php') && str_contains($code, '&lt;unsafe&gt;'), 'Fences/indented escaping');
check(str_contains($code, 'data-stack-linenos="true"') && str_contains($code, 'data-stack-linenostart="8"') && str_contains($code, 'data-stack-hl-lines="1-2 4"'), 'Fence metadata');
check(str_contains($code, '[reply]') && str_contains($code, 'inside code') && str_contains($code, '<hr>'), 'Code marker literal/HR');
check(str_contains(render('~~~js' . "\nempty eof"), '<pre><code class="language-js">empty eof</code></pre>'), 'Unclosed fence');
check(str_contains(render("~~~js\n~~~"), '<pre><code class="language-js"></code></pre>'), 'Empty fenced code');
check(str_contains(render("````js\na\n```\nb\n`````"), "a\n```\nb"), 'Fence length closure');
$htmlCode = render("~~~html {linenos=true}\n<script>alert(1)</script>\n<div>literal</div>\n~~~~");
check(str_contains($htmlCode, '&lt;script&gt;alert(1)&lt;/script&gt;') && str_contains($htmlCode, '&lt;div&gt;literal&lt;/div&gt;'), 'HTML inside extended fence preserved literally');
$escape = render('Escaped \\*literal\\* and \\_literal\\_ and ``code `ticks` _safe_``');
check(!str_contains($escape, '<em>') && str_contains($escape, '<code>code `ticks` _safe_</code>'), 'Escapes/multi-backtick spans');
$gates = "Public\n\n~~~md\n[reply]\nliteral\n[/reply]\n~~~\n\n[reply]\nSECRET_SENTINEL\n[/reply]\n\nEnd";
check(str_contains(render($gates), 'literal') && !str_contains(render($gates), 'SECRET_SENTINEL'), 'Reply code/private isolation');
check(count(stack_markdown_segments($gates)) === 3, 'Reply syntax new fences');
check(str_contains(render("    [reply]\n    literal\n    [/reply]"), '[reply]'), 'Indented marker preserved');
$html = render('<kbd>Ctrl</kbd> <mark>mark</mark> H<sub>2</sub> x<sup>2</sup> <abbr title="Long name">AB</abbr>' . "\n\n<details open><summary>More</summary><p>Body</p></details>");
foreach (['kbd','mark','sub','sup','abbr','details','summary'] as $tag) { check(str_contains($html, '<' . $tag), 'Safe semantic HTML ' . $tag); }
$xss = render('<img src="javascript:alert(1)" onerror="alert(1)"><a href="&#106;avascript:alert(2)" onclick="evil()">bad</a><svg><a xlink:href="javascript:evil()">bad svg</a></svg><script>EVIL_SCRIPT</script><iframe src="https://evil.test"></iframe><form><input name="x"></form><p style="background:url(javascript:evil())">safe</p>' . "\n\n[bad](javascript:alert) ![bad](data:image/svg+xml,bad) [relative](../ok) [mail](mailto:test@example.com)");
foreach (['onerror','onclick','javascript:','<svg','EVIL_SCRIPT','<iframe','<form','<input','background:','data:image'] as $bad) { check(!str_contains($xss, $bad), 'Reject ' . $bad); }
check(str_contains($xss, 'href="../ok"') && str_contains($xss, 'href="mailto:test@example.com"'), 'Safe relative/mail URLs');
$math = render('Inline $a_b + c_d$ and \(x_i^2\), plus `\(literal\)`.' . "\n\n$$\nx_i+y_j\n$$\n\n\\[x_k\\]");
check(str_contains($math, '$a_b + c_d$') && str_contains($math, '\(x_i^2\)') && str_contains($math, '\[x_k\]') && !str_contains($math, '<em>'), 'Math delimiters/content');
$urls = render('Visit https://example.com/a(foo)?x=1&b=2. [link](https://example.com) `https://example.com/code`');
check(substr_count($urls, '<a ') === 2 && str_contains($urls, '<code>https://example.com/code</code>'), 'Bare URL tokenization');
$media = render('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
$mediaProvider = media_embed_html('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
check($mediaProvider !== '' ? trim($media) === trim($mediaProvider) : str_contains($media, 'href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"'), 'Standalone media URL follows the existing provider interface');
check(str_contains(render("text\n\n***\n\n___\n\n- - -"), '<hr>'), 'All standard thematic breaks');
$table = render("| A | B | C |\n| :--- | :---: | ---: |\n| **bold** | `code` | x |\n");
check(str_contains($table, 'align="left"') && str_contains($table, 'align="center"') && str_contains($table, 'align="right"'), 'Table alignment');
$alert = render("> [!TIP] Custom **bold** title\n> Content");
check(str_contains($alert, 'Custom <strong>bold</strong> title<br>'), 'Alert title compatibility');
check(!array_filter($GLOBALS['hooks'], static fn(array $hook): bool => is_string($hook[1]) && str_contains($hook[1], 'SECRET_SENTINEL')), 'Hooks cannot see private source');
$post = ['id' => 1, 'content' => "# Public\n\n[reply]\n#### PRIVATE_AUTHORIZED\n[/reply]\n\nEnd"];
$GLOBALS['replyUnlocked'] = false;
$restricted = stack_markdown_render_post($post);
check(str_contains($restricted, 'REPLY_GATE') && !str_contains($restricted, 'PRIVATE_AUTHORIZED'), 'Post gate hides reply-only source');
$GLOBALS['replyUnlocked'] = true;
$authorized = stack_markdown_render_post($post);
check(str_contains($authorized, '<h4>PRIVATE_AUTHORIZED</h4>') && !str_contains($authorized, 'REPLY_GATE'), 'Authorized reply-only source parses fully');
$post['content_password_hash'] = 'password-hash';
$GLOBALS['passwordUnlocked'] = false;
$beforeHooks = count($GLOBALS['hooks']);
check(str_contains(stack_markdown_render_post($post), 'PASSWORD_GATE') && count($GLOBALS['hooks']) === $beforeHooks, 'Locked password never calls source hooks');
$GLOBALS['passwordUnlocked'] = true;
check(str_contains(stack_markdown_render_post($post), '<h4>PRIVATE_AUTHORIZED</h4>'), 'Unlocked password and reply source parses fully');
echo "PASS: $checks Markdown engine assertions\n";

$theme = $project . '/themes/stack/markdown.php';
if (is_file($theme)) {
    require $theme;
    $standalone = $checks;
    $figureSource = '{{< figure src="/uploads/test.jpg" caption="<unsafe>" >}}';
    $figure = render($figureSource);
    check(str_contains($figure, '<figure class="stack-figure">') && str_contains($figure, '<figcaption>&lt;unsafe&gt;</figcaption>'), 'Figure shortcode with escaped caption');
    $video = render('{{< video src="/uploads/test.mp4" poster="/uploads/poster.jpg" muted=true >}}');
    check(str_contains($video, '<video controls playsinline') && str_contains($video, ' muted'), 'Safe video shortcode');
    $badVideo = render('{{< video src="javascript:alert(1)" >}}');
    check(!str_contains($badVideo, '<video') && str_contains($badVideo, '{{'), 'Dangerous video URL stays literal');
    $quote = render('{{< quote author="A <script>" source="Source" url="https://example.com" >}}' . "\n#### Inner heading\n\n_inner italic_\n" . '{{< /quote >}}');
    check(str_contains($quote, '<blockquote class="stack-quote">') && str_contains($quote, '<h4>Inner heading</h4>') && str_contains($quote, '<em>inner italic</em>') && str_contains($quote, 'A &lt;script&gt;'), 'Quote nested Markdown and safe attribution');
    $highlight = render('{{< highlight php "linenos=true,linenostart=8,hl_lines=[1-2,4]" >}}' . "\necho '<script>';\n" . '{{< /highlight >}}');
    check(str_contains($highlight, 'data-stack-linenos="true"') && str_contains($highlight, 'data-stack-linenostart="8"') && str_contains($highlight, 'data-stack-hl-lines="1-2 4"') && str_contains($highlight, '&lt;script&gt;'), 'Highlight metadata arrays and escaped code');
    foreach ([
        "```md\n$figureSource\n```",
        "````md\n$figureSource\n````",
        "~~~md\n$figureSource\n~~~",
        "    $figureSource",
        '`' . $figureSource . '`',
        '``' . $figureSource . '``',
        "> ~~~md\n> $figureSource\n> ~~~",
        "- ~~~md\n  $figureSource\n  ~~~",
    ] as $index => $literal) {
        $output = render($literal);
        check(!str_contains($output, '<figure') && str_contains($output, '{{'), 'Shortcode remains literal in code form ' . $index);
    }
    $unknown = render('{{< unknown name="value" >}}');
    check(str_contains($unknown, '{{') && str_contains($unknown, 'unknown'), 'Unknown shortcode preserved');
    $withNotes = render("Outer[^same].\n\n[^same]: Outer footnote.\n\n" . '{{< quote >}}' . "\nInner[^same].\n\n[^same]: Inner footnote.\n" . '{{< /quote >}}');
    preg_match_all('/\bid="([^"]+)"/', $withNotes, $ids);
    check(count($ids[1]) >= 4 && count($ids[1]) === count(array_unique($ids[1])), 'Recursive shortcode and outer footnotes use unique IDs');
    check(empty($GLOBALS['stack_shortcode_outputs']), 'Every shortcode render clears its tokens');
    echo 'PASS: ' . ($checks - $standalone) . " Stack theme integration assertions\n";
}
