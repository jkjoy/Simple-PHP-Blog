<?php

declare(strict_types=1);

$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
if ($requestPath === '' || str_contains($requestPath, "\0")) {
    http_response_code(400);
    exit;
}

$requestPath = '/' . ltrim(str_replace('\\', '/', $requestPath), '/');
$segments = explode('/', trim($requestPath, '/'));
if (in_array('..', $segments, true)) {
    http_response_code(400);
    exit;
}

$publicExtensions = [
        'css', 'js', 'mjs',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico',
        'eot', 'ttf', 'otf', 'woff', 'woff2',
        'xml', 'webmanifest',
        'mp3', 'ogg', 'wav', 'mp4', 'webm',
];
$pathIsPrivate = static function (string $path) use ($publicExtensions): bool {
    $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
    foreach (explode('/', trim($path, '/')) as $segment) {
        if ($segment !== '' && str_starts_with($segment, '.')) {
            return true;
        }
    }
    if (preg_match('#^/(?:data|cache)(?:/|$)#i', $path)) {
        return true;
    }
    return preg_match('#^/(?:themes|plugins)(?:/|$)#i', $path) === 1
        && !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $publicExtensions, true);
};

if ($pathIsPrivate($requestPath)) {
    http_response_code(404);
    exit;
}

$candidate = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $requestPath);
$realCandidate = is_file($candidate) ? realpath($candidate) : false;
$realRoot = realpath(__DIR__);
if (is_string($realCandidate)
    && is_string($realRoot)
    && ($realCandidate === $realRoot || str_starts_with($realCandidate, $realRoot . DIRECTORY_SEPARATOR))) {
    $resolvedPath = '/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($realCandidate, strlen($realRoot) + 1));
    if ($pathIsPrivate($resolvedPath)) {
        http_response_code(404);
        exit;
    }
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require __DIR__ . '/index.php';
