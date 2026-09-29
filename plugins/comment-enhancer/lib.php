<?php

declare(strict_types=1);

function sce_level_for_count(int $count): int
{
    return match (true) {
        $count >= 100 => 6,
        $count >= 50 => 5,
        $count >= 20 => 4,
        $count >= 10 => 3,
        $count >= 5 => 2,
        default => 1,
    };
}

function sce_parse_user_agent(string $userAgent): array
{
    $userAgent = trim($userAgent);
    if ($userAgent === '') {
        return ['browser' => '', 'os' => ''];
    }

    $browser = match (true) {
        preg_match('/(?:Googlebot|bingbot|Baiduspider|YandexBot|DuckDuckBot|Applebot|GPTBot|ClaudeBot|Bytespider|AhrefsBot|SemrushBot|PetalBot|Slurp|bingpreview|facebookexternalhit)(?:\/|\b)/i', $userAgent) === 1 => 'Bot',
        preg_match('/Android.*(?:; wv\)|\bwv\b).*Chrome\/|Android.*Version\/4\.0.*Chrome\/.*Mobile Safari\//i', $userAgent) === 1 => 'Android WebView',
        preg_match('/EdgA?\/|EdgiOS\/|Edge\//i', $userAgent) === 1 => 'Edge',
        preg_match('/OPR\/|Opera Mini|Opera Mobi/i', $userAgent) === 1 => 'Opera',
        preg_match('/SamsungBrowser\//i', $userAgent) === 1 => 'Samsung Internet',
        preg_match('/MicroMessenger\//i', $userAgent) === 1 => 'WeChat',
        preg_match('/QQBrowser\//i', $userAgent) === 1 => 'QQ Browser',
        preg_match('/UCBrowser\/|UCWEB/i', $userAgent) === 1 => 'UC Browser',
        preg_match('/FxiOS\//i', $userAgent) === 1 => 'Firefox',
        preg_match('/Firefox\//i', $userAgent) === 1 => 'Firefox',
        preg_match('/CriOS\//i', $userAgent) === 1 => 'Chrome',
        preg_match('/Chrome\/|Chromium\//i', $userAgent) === 1 => 'Chrome',
        preg_match('/Version\/[^\s]+.*Safari\//i', $userAgent) === 1 => 'Safari',
        preg_match('/MSIE\s|Trident\//i', $userAgent) === 1 => 'Internet Explorer',
        default => '',
    };

    $os = match (true) {
        preg_match('/HarmonyOS/i', $userAgent) === 1 => 'HarmonyOS',
        preg_match('/Windows Phone/i', $userAgent) === 1 => 'Windows Phone',
        preg_match('/Windows NT 10\.0/i', $userAgent) === 1 => 'Windows 10/11',
        preg_match('/Windows NT 6\.3/i', $userAgent) === 1 => 'Windows 8.1',
        preg_match('/Windows NT 6\.2/i', $userAgent) === 1 => 'Windows 8',
        preg_match('/Windows NT 6\.1/i', $userAgent) === 1 => 'Windows 7',
        preg_match('/Windows/i', $userAgent) === 1 => 'Windows',
        preg_match('/iPad|CPU OS|Macintosh.*Mobile\//i', $userAgent) === 1 => 'iPadOS',
        preg_match('/iPhone|iPod/i', $userAgent) === 1 => 'iOS',
        preg_match('/Android/i', $userAgent) === 1 => 'Android',
        preg_match('/CrOS/i', $userAgent) === 1 => 'ChromeOS',
        preg_match('/Macintosh|Mac OS X/i', $userAgent) === 1 => 'macOS',
        preg_match('/Ubuntu/i', $userAgent) === 1 => 'Ubuntu',
        preg_match('/Linux|X11/i', $userAgent) === 1 => 'Linux',
        default => '',
    };

    return ['browser' => $browser, 'os' => $os];
}

function sce_browser_icon_name(string $browser): string
{
    return match ($browser) {
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
        default => 'generic-browser',
    };
}

function sce_os_icon_name(string $os): string
{
    return match ($os) {
        'HarmonyOS' => 'brand-harmonyos',
        'Windows Phone' => 'brand-windows-phone',
        'Windows 10/11', 'Windows 8.1', 'Windows 8', 'Windows 7', 'Windows' => 'brand-windows',
        'iPadOS', 'iOS', 'macOS' => 'brand-apple',
        'Android' => 'brand-android',
        'ChromeOS' => 'brand-chromeos',
        'Ubuntu' => 'brand-ubuntu',
        'Linux' => 'brand-linux',
        default => 'generic-os',
    };
}

function sce_icon_svg(string $name): string
{
    static $icons = [
        'generic-browser' => '<g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.5"></circle><path d="M2.5 12h19M12 2.5c2.5 2.6 3.8 5.8 3.8 9.5s-1.3 6.9-3.8 9.5c-2.5-2.6-3.8-5.8-3.8-9.5S9.5 5.1 12 2.5Z"></path></g>',
        'generic-os' => '<g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="19" height="14" rx="2"></rect><path d="M8 21h8M12 17.5V21"></path></g>',
        'generic-bot' => '<g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7V3H8"></path><rect x="4" y="7" width="16" height="13" rx="3"></rect><path d="M2 13h2M20 13h2M9 12v2M15 12v2"></path></g>',
        'product-android-webview' => '<g fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3" width="19" height="18" rx="2.5"></rect><path d="M2.5 8h19M8.5 16.5v-2a3.5 3.5 0 0 1 7 0v2M9.5 11.5 8.4 10M14.5 11.5l1.1-1.5M10.5 14h.01M13.5 14h.01"></path></g>',
        'brand-edge' => '<path fill="currentColor" transform="scale(.046875)" d="M120.1 37.44C161.1 12.23 207.7-.7753 255 .0016C423 .0016 512 123.8 512 219.5C511.9 252.2 499 283.4 476.1 306.7C453.2 329.9 422.1 343.2 389.4 343.7C314.2 343.7 297.9 320.6 297.9 311.7C297.9 307.9 299.1 305.5 302.7 302.3L303.7 301.1L304.1 299.5C314.6 288 320 273.3 320 257.9C320 179.2 237.8 115.2 136 115.2C98.46 114.9 61.46 124.1 28.48 142.1C55.48 84.58 111.2 44.5 119.8 38.28C120.6 37.73 120.1 37.44 120.1 37.44ZM135.7 355.5C134.3 385.5 140.3 415.5 152.1 442.7C165.7 469.1 184.8 493.7 208.6 512C149.1 500.5 97.11 468.1 59.2 422.7C21.12 376.3 0 318.4 0 257.9C0 206.7 62.4 163.5 136 163.5C172.6 162.9 208.4 174.4 237.8 196.2L234.2 197.4C182.7 215 135.7 288.1 135.7 355.5ZM469.8 400L469.1 400.1C457.3 418.9 443.2 435.2 426.9 449.6C396.1 477.6 358.8 495.1 318.1 499.5C299.5 499.8 281.3 496.3 264.3 488.1C238.7 477.8 217.2 458.1 202.7 435.1C188.3 411.2 181.6 383.4 183.7 355.5C183.1 335.4 189.1 315.2 198.7 297.3C212.6 330.4 236.2 358.6 266.3 378.1C296.4 397.6 331.8 407.6 367.7 406.7C398.7 407 429.8 400 457.9 386.2L459.8 385.3C463.7 383 467.5 381.4 471.4 385.3C475.9 390.2 473.2 394.5 470.2 399.3C470 399.5 469.9 399.8 469.8 400Z"></path>',
        'brand-opera' => '<path fill="currentColor" d="M8.051 5.238C6.723 6.804 5.865 9.121 5.805 11.718v.564c.061 2.598.918 4.912 2.246 6.479c1.721 2.236 4.279 3.654 7.139 3.654c1.756 0 3.4-.537 4.807-1.471C17.879 22.846 15.074 24 12 24c-.192 0-.383-.004-.57-.014C5.064 23.689 0 18.436 0 12C0 5.371 5.373 0 12 0h.045c3.055.012 5.84 1.166 7.953 3.055c-1.408-.93-3.051-1.471-4.81-1.471c-2.858 0-5.417 1.42-7.14 3.654h.003ZM24 12c0 3.556-1.545 6.748-4.002 8.945c-3.078 1.5-5.946.451-6.896-.205c3.023-.664 5.307-4.32 5.307-8.74c0-4.422-2.283-8.075-5.307-8.74c.949-.654 3.818-1.703 6.896-.205C22.455 5.25 24 8.445 24 12Z"></path>',
        'brand-samsung-internet' => '<g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="6"></rect><circle cx="12" cy="12" r="5.4"></circle><ellipse cx="12" cy="12" rx="8.4" ry="3.7" transform="rotate(20 12 12)"></ellipse></g>',
        'brand-wechat' => '<path fill="currentColor" d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213c0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098a10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05c-.857-2.578.157-4.972 1.932-6.446c1.703-1.415 3.882-1.98 5.853-1.838c-.576-3.583-4.196-6.348-8.596-6.348ZM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18Zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178a1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18Zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786c-1.72 1.428-2.687 3.72-1.78 6.22c.942 2.453 3.666 4.229 6.884 4.229c.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.111.24-.247c0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156a.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088V8.89c-.135-.01-.27-.027-.407-.03Zm-2.53 3.274c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983a.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982Zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983a.976.976 0 0 1-.969-.983c0-.542.434-.982.969-.982Z"></path>',
        'brand-qq-browser' => '<g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11.5" cy="11.5" r="8.5"></circle><path d="m15.7 15.7 5.3 5.3M8.2 14.8l6.6-6.6-2.1 4.5-4.5 2.1Z"></path></g>',
        'brand-uc-browser' => '<g fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5v8c0 3.8 1.8 6 4.5 6s4.5-2.2 4.5-6V5"></path><path d="M21 8.2A5.5 5.5 0 1 0 21 16"></path></g>',
        'brand-firefox' => '<path fill="currentColor" d="M22.778 8.048c-.505-1.215-1.53-2.528-2.333-2.943c.654 1.283 1.033 2.57 1.177 3.53l.002.02c-1.314-3.278-3.544-4.6-5.366-7.477c-.091-.147-.184-.292-.273-.446a3.545 3.545 0 0 1-.302-.7a.03.03 0 0 0-.027-.03a.038.038 0 0 0-.027.001a.037.037 0 0 0-.015.005L15.624 0c-2.585 1.515-3.657 4.168-3.932 5.856a6.197 6.197 0 0 0-2.305.587a.297.297 0 0 0-.147.37c.057.162.24.24.396.17a5.622 5.622 0 0 1 2.008-.523a5.847 5.847 0 0 1 2.019.217a5.816 5.816 0 0 1 .716.258a5.835 5.835 0 0 1 .713.373a5.953 5.953 0 0 1 2.034 2.104c-.62-.437-1.733-.868-2.803-.681c4.183 2.09 3.06 9.292-2.737 9.02a5.164 5.164 0 0 1-1.513-.292a4.42 4.42 0 0 1-.538-.232c-1.42-.735-2.593-2.121-2.74-3.806c0 0 .537-2 3.845-2c.357 0 1.38-.998 1.398-1.287c-.005-.095-2.029-.9-2.817-1.677c-.422-.416-.622-.616-.8-.767a3.47 3.47 0 0 0-.301-.227a5.388 5.388 0 0 1-.032-2.842c-1.195.544-2.124 1.403-2.8 2.163h-.006c-.46-.584-.428-2.51-.402-2.913c-.006-.025-.343.176-.389.206c-.406.29-.787.616-1.136.974c-.397.403-.76.839-1.085 1.303a9.816 9.816 0 0 0-1.562 3.52c-.003.013-.11.487-.19 1.073c-.013.09-.026.181-.037.272a7.8 7.8 0 0 0-.069.667l-.025.421l-.001.06C.386 18.795 5.593 24 12.016 24c5.752 0 10.527-4.176 11.463-9.661c.02-.149.035-.298.052-.448c.232-1.994-.025-4.09-.753-5.843Z"></path>',
        'brand-chrome' => '<path fill="currentColor" fill-rule="evenodd" d="M12 0C8.21 0 4.831 1.757 2.632 4.501l3.953 6.848A5.454 5.454 0 0 1 12 6.545h10.691A12 12 0 0 0 12 0ZM1.931 5.47A11.943 11.943 0 0 0 0 12c0 6.012 4.42 10.991 10.189 11.864l3.953-6.847a5.45 5.45 0 0 1-6.865-2.29L1.931 5.47Zm13.342 2.166a5.446 5.446 0 0 1 1.45 7.09l.002.001l-5.346 9.257c.206.01.413.016.621.016c6.627 0 12-5.373 12-12c0-1.54-.29-3.011-.818-4.364h-7.909ZM12 16.364a4.364 4.364 0 1 1 0-8.728a4.364 4.364 0 0 1 0 8.728Z"></path>',
        'brand-safari' => '<g stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" fill="none" stroke-width="1.8"></circle><path fill="currentColor" stroke="none" d="m15.9 8.1-2.4 5.4-5.4 2.4 2.4-5.4 5.4-2.4Zm-4.8 3a1.27 1.27 0 1 0 1.8 1.8a1.27 1.27 0 0 0-1.8-1.8Z"></path><path d="M12 2v2M12 20v2M2 12h2M20 12h2" fill="none" stroke-width="1.5"></path></g>',
        'brand-internet-explorer' => '<path fill="currentColor" transform="scale(.046875)" d="M483.049 159.706c10.855-24.575 21.424-60.438 21.424-87.871c0-72.722-79.641-98.371-209.673-38.577c-107.632-7.181-211.221 73.67-237.098 186.457c30.852-34.862 78.271-82.298 121.977-101.158C125.404 166.85 79.128 228.002 43.992 291.725C23.246 329.651 0 390.94 0 436.747c0 98.575 92.854 86.5 180.251 42.006c31.423 15.43 66.559 15.573 101.695 15.573c97.124 0 184.249-54.294 216.814-146.022H377.927c-52.509 88.593-196.819 52.996-196.819-47.436H509.9c6.407-43.581-1.655-95.715-26.851-141.162ZM64.559 346.877c17.711 51.15 53.703 95.871 100.266 123.304c-88.741 48.94-173.267 29.096-100.266-123.304Zm115.977-108.873c2-55.151 50.276-94.871 103.98-94.871c53.418 0 101.981 39.72 103.981 94.871H180.536Zm184.536-187.6c21.425-10.287 48.563-22.003 72.558-22.003c31.422 0 54.274 21.717 54.274 53.722c0 20.003-7.427 49.007-14.569 67.867c-26.28-42.292-65.986-81.584-112.263-99.586Z"></path>',
        'brand-harmonyos' => '<path fill="currentColor" d="M3.5 3h3v7.5h11V3h3v18h-3v-7.5h-11V21h-3V3Z"></path>',
        'brand-windows-phone' => '<g stroke="currentColor" stroke-linejoin="round"><rect x="5" y="1.5" width="14" height="21" rx="2.5" fill="none" stroke-width="1.7"></rect><path fill="currentColor" stroke="none" d="M8 6h3.2v3.2H8V6Zm4.1 0H16v3.2h-3.9V6ZM8 10.1h3.2v3.8H8v-3.8Zm4.1 0H16v3.8h-3.9v-3.8Z"></path><path d="M11 19h2" fill="none" stroke-width="1.5" stroke-linecap="round"></path></g>',
        'brand-windows' => '<path fill="currentColor" d="M1.5 3.3h9.4v8.1H1.5V3.3Zm10.6-.2 10.4-1.4v9.7H12.1V3.1ZM1.5 12.6h9.4v8.1l-9.4-1.3v-6.8Zm10.6 0h10.4v9.7l-10.4-1.4v-8.3Z"></path>',
        'brand-apple' => '<path fill="currentColor" d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04c-2.04.027-3.91 1.183-4.961 3.014c-2.117 3.675-.546 9.103 1.519 12.09c1.013 1.454 2.208 3.09 3.792 3.039c1.52-.065 2.09-.987 3.935-.987c1.831 0 2.35.987 3.96.948c1.637-.026 2.676-1.48 3.676-2.948c1.156-1.688 1.636-3.325 1.662-3.415c-.039-.013-3.182-1.221-3.22-4.857c-.026-3.04 2.48-4.494 2.597-4.559c-1.429-2.09-3.623-2.324-4.39-2.376c-2-.156-3.675 1.09-4.61 1.09Zm3.378-3.065C16.373 2.819 16.93 1.404 16.775 0c-1.207.052-2.662.805-3.532 1.818c-.78.896-1.454 2.338-1.273 3.714c1.338.104 2.715-.688 3.56-1.701Z"></path>',
        'brand-android' => '<path fill="currentColor" d="M18.44 5.559c-.675 1.166-1.352 2.332-2.027 3.498c-.037-.016-.075-.029-.112-.043c-1.825-.696-3.484-.8-4.42-.787c-1.855.019-3.354.464-4.26.82c-.084-.149-1.752-3.021-2.021-3.486a1.145 1.145 0 0 0-.141-.192c-.331-.364-.905-.486-1.379-.203c-.475.282-.714.936-.389 1.502c1.947 3.37-.096-.216 1.947 3.359c.018.031-.494.264-1.392 1.018C2.899 12.176.452 14.772 0 18.99h24c-.119-1.111-.369-2.099-.746-3.068c-.744-1.912-1.844-3.293-2.74-4.184a12.105 12.105 0 0 0-2.131-1.687c.66-1.122 1.312-2.256 1.965-3.385c.207-.362.188-.796-.008-1.119a1.1 1.1 0 0 0-.852-.533c-.522-.054-.939.312-1.048.545Zm-.04 8.46c.394.594.324 1.332-.156 1.651c-.48.32-1.188.099-1.582-.494c-.394-.593-.324-1.331.156-1.65c.473-.315 1.182-.109 1.582.494ZM7.207 13.528c.48.32.551 1.058.156 1.65c-.394.593-1.104.814-1.584.494c-.48-.32-.55-1.058-.156-1.65c.401-.603 1.109-.811 1.584-.494Z"></path>',
        'brand-chromeos' => '<g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="16" rx="2.5"></rect><path d="M8 22h8M12 19v3"></path><circle cx="12" cy="11" r="4.5"></circle><circle cx="12" cy="11" r="1.6" fill="currentColor" stroke="none"></circle><path d="M8.1 8.8h7.8M10 15l2-2.4M14 15l-2-2.4"></path></g>',
        'brand-ubuntu' => '<path fill="currentColor" d="M17.61.455a3.41 3.41 0 1 0 0 6.82a3.41 3.41 0 0 0 0-6.82ZM12.92.8C8.923.777 5.137 2.941 3.148 6.451a4.5 4.5 0 0 1 .26-.007a4.92 4.92 0 0 1 2.585.737A8.316 8.316 0 0 1 12.688 3.6A4.944 4.944 0 0 1 13.723.834A11.008 11.008 0 0 0 12.92.8Zm9.226 4.994a4.915 4.915 0 0 1-1.918 2.246a8.36 8.36 0 0 1-.273 8.303a4.89 4.89 0 0 1 1.632 2.54a11.156 11.156 0 0 0 .559-13.089ZM3.41 7.932a3.41 3.41 0 1 0 0 6.819a3.41 3.41 0 0 0 0-6.82Zm2.027 7.866a4.908 4.908 0 0 1-2.915.358a11.1 11.1 0 0 0 7.991 6.698a11.234 11.234 0 0 0 2.422.249a4.879 4.879 0 0 1-.999-2.85a8.484 8.484 0 0 1-.836-.136a8.304 8.304 0 0 1-5.663-4.32Zm11.405.928a3.41 3.41 0 1 0 0 6.82a3.41 3.41 0 0 0 0-6.82Z"></path>',
        'brand-linux' => '<g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.2c-2.9 0-4.4 2.7-4.2 6.5c.1 1.6-.8 2.9-1.7 4.4c-1.4 2.2-1.7 4.7-.5 6.7c1.3 2.2 3.8 1.5 6.4.7c2.7.8 5.1 1.5 6.4-.7c1.2-2 .9-4.5-.5-6.7c-.9-1.5-1.8-2.8-1.7-4.4c.2-3.8-1.3-6.5-4.2-6.5Z"></path><path d="M9.6 7.7c.2-1.1.7-1.7 1.3-1.7s1 .7 1.1 1.8c.1-1.1.5-1.8 1.1-1.8s1.1.6 1.3 1.7M9.4 10.1 12 12l2.6-1.9M8.2 17.2c2.5 1.2 5.1 1.2 7.6 0"></path></g>',
    ];

    $body = $icons[$name] ?? $icons['generic-browser'];
    return '<svg class="comment-enhancer-meta__brand" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . $body
        . '</svg>';
}

function sce_ip_in_cidr(string $ip, string $cidr): bool
{
    [$network, $prefixText] = array_pad(explode('/', $cidr, 2), 2, '');
    $addressBytes = @inet_pton($ip);
    $networkBytes = @inet_pton($network);
    if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
        return false;
    }

    $bitLength = strlen($addressBytes) * 8;
    $prefix = filter_var($prefixText, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => $bitLength],
    ]);
    if ($prefix === false) {
        return false;
    }

    $wholeBytes = intdiv((int)$prefix, 8);
    if ($wholeBytes > 0 && substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
        return false;
    }

    $remainingBits = (int)$prefix % 8;
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xff << (8 - $remainingBits)) & 0xff;
    return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
}

function sce_ip_scope(string $ip): string
{
    $ip = trim($ip);
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'invalid';
    }

    $packed = @inet_pton($ip);
    if (is_string($packed) && strlen($packed) === 16
        && substr($packed, 0, 10) === str_repeat("\0", 10)
        && substr($packed, 10, 2) === "\xff\xff") {
        $mapped = @inet_ntop(substr($packed, 12, 4));
        return is_string($mapped) ? sce_ip_scope($mapped) : 'invalid';
    }

    $localNetworks = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '255.255.255.255/32',
        '::/128',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ];
    foreach ($localNetworks as $network) {
        if (sce_ip_in_cidr($ip, $network)) {
            return 'local';
        }
    }

    $reservedNetworks = [
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/32',
        '2001:2::/48',
        '2001:10::/28',
        '2001:20::/28',
        '2001:db8::/32',
        '2002::/16',
        '3fff::/20',
        '5f00::/16',
        'ff00::/8',
    ];
    foreach ($reservedNetworks as $network) {
        if (sce_ip_in_cidr($ip, $network)) {
            return 'reserved';
        }
    }

    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        ? 'public'
        : 'reserved';
}

function sce_clean_geo_text(mixed $value): string
{
    if (!is_string($value) && !is_numeric($value)) {
        return '';
    }

    $text = trim((string)$value);
    if ($text === '' || preg_match('//u', $text) !== 1) {
        return '';
    }

    $text = preg_replace('/[\p{C}<>]+/u', ' ', $text);
    $text = is_string($text) ? preg_replace('/\s+/u', ' ', $text) : null;
    if (!is_string($text)) {
        return '';
    }

    return function_exists('mb_substr') ? mb_substr(trim($text), 0, 80, 'UTF-8') : substr(trim($text), 0, 80);
}

function sce_geo_cache_value(array $payload): string
{
    if (($payload['success'] ?? false) !== true) {
        return '';
    }

    $countryCode = strtoupper(sce_clean_geo_text($payload['country_code'] ?? ''));
    $country = sce_clean_geo_text($payload['country'] ?? '');
    $region = sce_clean_geo_text($payload['region'] ?? '');
    if ($countryCode === '' && $country === '') {
        return '';
    }

    $encoded = json_encode([
        'country_code' => $countryCode,
        'country' => $country,
        'region' => $region,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($encoded) ? $encoded : '';
}

function sce_format_geo_location(array $payload, string $locale = 'zh-CN'): string
{
    $countryCode = strtoupper(sce_clean_geo_text($payload['country_code'] ?? ''));
    $country = sce_clean_geo_text($payload['country'] ?? '');
    $region = sce_clean_geo_text($payload['region'] ?? '');
    if (!str_starts_with(strtolower($locale), 'zh')) {
        return $country !== '' && $region !== '' ? $country . ' · ' . $region : $country;
    }

    $countryNames = [
        'CN' => '中国', 'HK' => '中国香港', 'MO' => '中国澳门', 'TW' => '中国台湾',
        'US' => '美国', 'JP' => '日本', 'KR' => '韩国', 'SG' => '新加坡',
        'GB' => '英国', 'DE' => '德国', 'FR' => '法国', 'CA' => '加拿大',
        'AU' => '澳大利亚', 'NZ' => '新西兰', 'RU' => '俄罗斯', 'IN' => '印度',
        'TH' => '泰国', 'MY' => '马来西亚', 'ID' => '印度尼西亚', 'VN' => '越南',
        'PH' => '菲律宾', 'IT' => '意大利', 'ES' => '西班牙', 'NL' => '荷兰',
        'CH' => '瑞士', 'SE' => '瑞典', 'BR' => '巴西', 'ZA' => '南非',
    ];
    $countryLabel = $countryNames[$countryCode] ?? $country;

    if ($countryCode !== 'CN') {
        return $countryLabel;
    }

    $regionKey = strtolower((string)preg_replace('/[^a-z]/i', '', $region));
    $regionNames = [
        'anhui' => '安徽', 'beijing' => '北京', 'chongqing' => '重庆', 'fujian' => '福建',
        'gansu' => '甘肃', 'guangdong' => '广东', 'guangxi' => '广西', 'guizhou' => '贵州',
        'hainan' => '海南', 'hebei' => '河北', 'heilongjiang' => '黑龙江', 'henan' => '河南',
        'hubei' => '湖北', 'hunan' => '湖南', 'innermongolia' => '内蒙古', 'jiangsu' => '江苏',
        'jiangxi' => '江西', 'jilin' => '吉林', 'liaoning' => '辽宁', 'ningxia' => '宁夏',
        'qinghai' => '青海', 'shaanxi' => '陕西', 'shandong' => '山东', 'shanghai' => '上海',
        'shanxi' => '山西', 'sichuan' => '四川', 'tianjin' => '天津', 'tibet' => '西藏',
        'xinjiang' => '新疆', 'yunnan' => '云南', 'zhejiang' => '浙江',
    ];
    foreach ($regionNames as $needle => $label) {
        if (str_contains($regionKey, $needle)) {
            return $countryLabel . ' · ' . $label;
        }
    }

    return $countryLabel !== '' && $region !== '' ? $countryLabel . ' · ' . $region : $countryLabel;
}
