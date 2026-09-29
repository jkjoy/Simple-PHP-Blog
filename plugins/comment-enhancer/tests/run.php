<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib.php';

$failures = [];

$expect = static function (mixed $actual, mixed $expected, string $label) use (&$failures): void {
    if ($actual !== $expected) {
        $failures[] = $label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
};

foreach ([
    0 => 1, 1 => 1, 4 => 1, 5 => 2, 9 => 2, 10 => 3, 19 => 3,
    20 => 4, 49 => 4, 50 => 5, 99 => 5, 100 => 6,
] as $count => $level) {
    $expect(sce_level_for_count($count), $level, 'level boundary ' . $count);
}

$edge = sce_parse_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0 Safari/537.36 Edg/140.0');
$expect($edge, ['browser' => 'Edge', 'os' => 'Windows 10/11'], 'Edge before Chrome');

$iosChrome = sce_parse_user_agent('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 CriOS/140.0 Mobile/15E148 Safari/604.1');
$expect($iosChrome, ['browser' => 'Chrome', 'os' => 'iOS'], 'Chrome on iOS');

$webView = sce_parse_user_agent('Mozilla/5.0 (Linux; Android 14; Pixel 8 Build/AP1A; wv) AppleWebKit/537.36 Version/4.0 Chrome/140.0 Mobile Safari/537.36');
$expect($webView, ['browser' => 'Android WebView', 'os' => 'Android'], 'Android WebView before Chrome');

$legacyEdge = sce_parse_user_agent('Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/42.0 Safari/537.36 Edge/12.246');
$expect($legacyEdge, ['browser' => 'Edge', 'os' => 'Windows 10/11'], 'legacy Edge before Chrome');

$phone = sce_parse_user_agent('Mozilla/5.0 (Linux; Android 13; CUBOT X20 Pro) AppleWebKit/537.36 Chrome/112.0 Mobile Safari/537.36');
$expect($phone, ['browser' => 'Chrome', 'os' => 'Android'], 'phone model is not a bot');

$crawler = sce_parse_user_agent('Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)');
$expect($crawler, ['browser' => 'Bot', 'os' => ''], 'known crawler');

$ipadDesktop = sce_parse_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1');
$expect($ipadDesktop, ['browser' => 'Safari', 'os' => 'iPadOS'], 'iPad desktop mode before macOS');

$browserIcons = [
    'Bot' => 'generic-bot',
    'Android WebView' => 'product-android-webview',
    'Edge' => 'brand-edge',
    'Opera' => 'brand-opera',
    'Samsung Internet' => 'brand-samsung-internet',
    'WeChat' => 'brand-wechat',
    'QQ Browser' => 'brand-qq-browser',
    'UC Browser' => 'brand-uc-browser',
    'Firefox' => 'brand-firefox',
    'Chrome' => 'brand-chrome',
    'Safari' => 'brand-safari',
    'Internet Explorer' => 'brand-internet-explorer',
    'Unknown Browser' => 'generic-browser',
];
foreach ($browserIcons as $browser => $icon) {
    $expect(sce_browser_icon_name($browser), $icon, $browser . ' icon');
}

$osIcons = [
    'HarmonyOS' => 'brand-harmonyos',
    'Windows Phone' => 'brand-windows-phone',
    'Windows 10/11' => 'brand-windows',
    'Windows 8.1' => 'brand-windows',
    'Windows 8' => 'brand-windows',
    'Windows 7' => 'brand-windows',
    'Windows' => 'brand-windows',
    'iPadOS' => 'brand-apple',
    'iOS' => 'brand-apple',
    'macOS' => 'brand-apple',
    'Android' => 'brand-android',
    'ChromeOS' => 'brand-chromeos',
    'Ubuntu' => 'brand-ubuntu',
    'Linux' => 'brand-linux',
    'Unknown OS' => 'generic-os',
];
foreach ($osIcons as $os => $icon) {
    $expect(sce_os_icon_name($os), $icon, $os . ' icon');
}

$iconIds = array_unique(array_merge(array_values($browserIcons), array_values($osIcons)));
foreach ($iconIds as $iconId) {
    $svg = sce_icon_svg($iconId);
    $expect(str_starts_with($svg, '<svg '), true, $iconId . ' SVG starts with root element');
    $expect(str_ends_with($svg, '</svg>'), true, $iconId . ' SVG ends with root element');
    $expect(substr_count($svg, '<svg'), 1, $iconId . ' SVG has one opening root');
    $expect(substr_count($svg, '</svg>'), 1, $iconId . ' SVG has one closing root');
    $expect(str_contains($svg, 'viewBox="0 0 24 24"'), true, $iconId . ' SVG viewBox');
    $expect(str_contains($svg, 'aria-hidden="true"'), true, $iconId . ' SVG hidden from accessibility tree');
    $expect(str_contains($svg, 'focusable="false"'), true, $iconId . ' SVG is not focusable');
    $expect(preg_match('/^<svg\b[^>]*>\s*.+\s*<\/svg>$/s', $svg), 1, $iconId . ' SVG has nonempty content');
    $expect(preg_match('/<(?:script|foreignObject)\b/i', $svg), 0, $iconId . ' SVG has no active elements');
    $expect(preg_match('/\son[a-z0-9_-]*\s*=/i', $svg), 0, $iconId . ' SVG has no event handlers');
    $expect(preg_match('/\s(?:xlink:)?href\s*=/i', $svg), 0, $iconId . ' SVG has no links');
}

$expect(sce_parse_user_agent(''), ['browser' => '', 'os' => ''], 'empty user agent');
$expect(sce_parse_user_agent('wx-miniprogram'), ['browser' => '', 'os' => ''], 'unknown user agent');
$expect(sce_ip_scope('8.8.8.8'), 'public', 'public IPv4');
$expect(sce_ip_scope('2001:4860:4860::8888'), 'public', 'public IPv6');
$expect(sce_ip_scope('10.4.3.2'), 'local', 'private IPv4');
$expect(sce_ip_scope('127.0.0.1'), 'local', 'loopback IPv4');
$expect(sce_ip_scope('100.64.0.1'), 'local', 'shared IPv4');
$expect(sce_ip_scope('fd12::1'), 'local', 'private IPv6');
$expect(sce_ip_scope('::ffff:10.0.0.1'), 'local', 'mapped private IPv4');
$expect(sce_ip_scope('::ffff:192.168.1.1'), 'local', 'mapped private IPv4 second range');
$expect(sce_ip_scope('2001:db8::1'), 'reserved', 'documentation IPv6');
$expect(sce_ip_scope('3fff::1'), 'reserved', 'documentation IPv6 second range start');
$expect(sce_ip_scope('3fff:0fff:ffff:ffff:ffff:ffff:ffff:ffff'), 'reserved', 'documentation IPv6 second range end');
$expect(sce_ip_scope('5f00::1'), 'reserved', 'SRv6 SID IPv6 start');
$expect(sce_ip_scope('5f00:ffff:ffff:ffff:ffff:ffff:ffff:ffff'), 'reserved', 'SRv6 SID IPv6 end');
$expect(sce_ip_scope('192.0.2.1'), 'reserved', 'documentation IPv4');
$expect(sce_ip_scope('198.18.0.1'), 'reserved', 'benchmark IPv4');
$expect(sce_ip_scope('224.0.0.1'), 'reserved', 'multicast IPv4');
$expect(sce_ip_scope('ff02::1'), 'reserved', 'multicast IPv6');
$expect(sce_ip_scope('not-an-ip'), 'invalid', 'invalid IP');

$chinaPayload = [
    'success' => true,
    'country_code' => 'CN',
    'country' => 'China',
    'region' => 'Jiangsu Sheng',
];
$expect(sce_format_geo_location($chinaPayload), '中国 · 江苏', 'Chinese province mapping');
$expect(sce_format_geo_location($chinaPayload, 'en-US'), 'China · Jiangsu Sheng', 'English location formatting');
$expect(json_decode(sce_geo_cache_value($chinaPayload), true), [
    'country_code' => 'CN',
    'country' => 'China',
    'region' => 'Jiangsu Sheng',
], 'locale-neutral location cache');
$expect(sce_format_geo_location([
    'country_code' => 'US',
    'country' => 'United States',
    'region' => 'California',
]), '美国', 'foreign country mapping');
$expect(sce_geo_cache_value(['success' => false]), '', 'failed lookup');
$expect(sce_clean_geo_text("<script>\0test</script>"), 'script test /script', 'geo text sanitizing');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "comment-enhancer tests passed" . PHP_EOL;
