<?php

declare(strict_types=1);

require_once __DIR__ . '/MarkdownInterface.php';
require_once __DIR__ . '/Markdown.php';
require_once __DIR__ . '/MarkdownExtra.php';

/** SBlog extensions to PHP Markdown Extra 2.0.0 (see License.md). */
final class StackMarkdownParser extends \SBlogStackMarkdown\Vendor\Michelf\MarkdownExtra
{
    public function __construct()
    {
        $this->escape_chars .= '~';
        $this->document_gamut['protectMath'] = 6;
        $this->span_gamut['doStrikethrough'] = 45;
        parent::__construct();
        $this->empty_element_suffix = '>';
        $this->code_class_prefix = 'language-';
        $this->hard_wrap = true;
        $this->hashtag_protection = true;
    }

    protected function hashHTMLBlocks($text)
    {
        // Extra's HTML scanner knows a narrower fence-info grammar. Hash the
        // complete SBlog fences first so HTML inside them always remains code.
        return parent::hashHTMLBlocks($this->doFencedCodeBlocks($text));
    }

    protected function protectMath($text)
    {
        // Fenced code is already hashed; inline code must keep literal delimiters.
        return preg_replace_callback(
            '~(`+)[\s\S]*?\1|(\$\$[\s\S]+?\$\$|\\\\\[[\s\S]+?\\\\\]|\\\\\([\s\S]+?\\\\\)|(?<![\\\\$])\$(?![\s$])(?:\\\\.|[^$\n])+?(?<!\s)\$(?!\$))~',
            fn(array $match): string => isset($match[2]) ? $this->hashPart(htmlspecialchars($match[2], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')) : $match[0],
            $text
        ) ?? $text;
    }

    protected function doStrikethrough($text)
    {
        return preg_replace_callback('/~~(?=\S)(.+?)(?<=\S)~~/s', fn(array $match): string => $this->hashPart('<del>' . $this->runSpanGamut($match[1]) . '</del>'), $text) ?? $text;
    }

    protected function doFencedCodeBlocks($text)
    {
        $lines = explode("\n", $text);
        $output = [];
        for ($index = 0, $count = count($lines); $index < $count; $index++) {
            if (!preg_match('/^( {0,3})(`{3,}|~{3,})([^\r\n]*)$/', $lines[$index], $opening)
                || ($opening[2][0] === '`' && str_contains($opening[3], '`'))) {
                $output[] = $lines[$index];
                continue;
            }
            $indent = strlen($opening[1]);
            $marker = preg_quote($opening[2][0], '/');
            $closing = '/^ {0,3}' . $marker . '{' . strlen($opening[2]) . ',}[ \t]*$/';
            $code = [];
            while (++$index < $count && !preg_match($closing, $lines[$index])) {
                $code[] = preg_replace('/^ {0,' . $indent . '}/', '', $lines[$index]) ?? $lines[$index];
            }
            // The transform input has trailing padding newlines, which are not code.
            if ($index === $count) {
                while ($code !== [] && end($code) === '') { array_pop($code); }
            }
            $attributes = '';
            $info = trim($opening[3]);
            if (preg_match('/^\.?([a-zA-Z0-9_#+.-]+)/', $info, $language)) {
                $attributes .= ' class="language-' . htmlspecialchars($language[1], ENT_QUOTES, 'UTF-8') . '"';
            }
            if (preg_match('/\blinenos\s*=\s*["\']?(true|false|table|inline)\b/i', $info, $option)) {
                $attributes .= ' data-stack-linenos="' . (strtolower($option[1]) === 'false' ? 'false' : 'true') . '"';
            }
            if (preg_match('/\blinenostart\s*=\s*["\']?(\d+)/i', $info, $option)) {
                $attributes .= ' data-stack-linenostart="' . max(1, min(1000000, (int)$option[1])) . '"';
            }
            if (preg_match('/\bhl_lines\s*=\s*(\[[^\]]*\]|"[^"]*"|\'[^\']*\'|[^,}\s]+)/i', $info, $option)) {
                preg_match_all('/\b\d+(?:-\d+)?\b/', $option[1], $ranges);
                $attributes .= ' data-stack-hl-lines="' . implode(' ', array_slice($ranges[0], 0, 100)) . '"';
            }
            $output[] = "\n" . $this->hashBlock('<pre><code' . $attributes . '>' . htmlspecialchars(implode("\n", $code), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>') . "\n";
        }
        return implode("\n", $output);
    }
}
