<?php

error_reporting(0);
ini_set('display_errors', '0');
@set_time_limit(0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Origin, User-Agent, Accept, Referer, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rawUrl = $_GET['url'] ?? '';
if ($rawUrl === '') {
    failWith(400, 'Missing stream URL.');
}

$targetUrl = rawurldecode($rawUrl);
if (!isValidRemoteUrl($targetUrl)) {
    failWith(400, 'Invalid stream URL.');
}

if (isLikelyPlaylistUrl($targetUrl)) {
    proxyPlaylist($targetUrl);
    exit;
}

proxyBinary($targetUrl);
exit;

function proxyPlaylist(string $url): void
{
    $headers = buildUpstreamHeaders();
    $ch = buildCurl($url, $headers, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string) (curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
    curl_close($ch);

    if ($curlErrNo !== 0) {
        error_log('[proxy][playlist] upstream curl error host=' . (parse_url($url, PHP_URL_HOST) ?: 'unknown') . ' errno=' . $curlErrNo . ' err=' . $curlErr);
        $status = in_array($curlErrNo, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST], true) ? 504 : 502;
        failWith($status, proxyProviderStatusMessage($status));
    }

    if ($httpCode >= 400 || $response === false || $response === '') {
        error_log('[proxy][playlist] upstream bad response host=' . (parse_url($url, PHP_URL_HOST) ?: 'unknown') . ' status=' . $httpCode);
        $status = proxyNormalizeProviderStatus($httpCode);
        failWith($status, proxyProviderStatusMessage($status));
    }

    $providerError = extractUpstreamProviderError($response);
    if ($providerError !== null) {
        $status = proxyNormalizeProviderStatus((int)($providerError['status'] ?? 502));

        error_log('[proxy][playlist] provider error host=' . (parse_url($url, PHP_URL_HOST) ?: 'unknown') . ' status=' . $status . ' error=' . ($providerError['error'] ?? 'unknown'));
        failWith($status, proxyProviderStatusMessage($status));
    }

    if (!isM3U8Payload($response)) {
        $sample = substr(trim($response), 0, 120);
        error_log('[proxy][playlist] invalid payload host=' . (parse_url($url, PHP_URL_HOST) ?: 'unknown') . ' sample=' . sanitizeHeaderValue($sample));
        failWith(502, proxyProviderStatusMessage(502));
    }

    $response = rewriteM3U8Playlist($response, $effectiveUrl);
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $response;
}

function proxyBinary(string $url): void
{
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @ob_implicit_flush(1);

    $headers = buildUpstreamHeaders();
    $ch = buildCurl($url, $headers, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    $sentHeaders = false;
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($ch, $chunk) use (&$sentHeaders, $url) {
        if (!$sentHeaders) {
            header('Content-Type: ' . guessContentType($url));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            $sentHeaders = true;
        }
        echo $chunk;
        flush();
        return strlen($chunk);
    });

    $ok = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($ok === false || $curlErrNo !== 0) {
        error_log('[proxy][binary] upstream curl error host=' . (parse_url($url, PHP_URL_HOST) ?: 'unknown') . ' errno=' . $curlErrNo . ' err=' . $curlErr);
        if (!$sentHeaders && !headers_sent()) {
            $status = in_array($curlErrNo, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST], true) ? 504 : 502;
            failWith($status, proxyProviderStatusMessage($status));
        }
        exit;
    }

    if ($httpCode >= 400 && !headers_sent()) {
        error_log('[proxy][binary] upstream bad response host=' . (parse_url($url, PHP_URL_HOST) ?: 'unknown') . ' status=' . $httpCode);
        $status = proxyNormalizeProviderStatus($httpCode);
        failWith($status, proxyProviderStatusMessage($status));
    }
}

function buildCurl(string $url, array $headers, bool $isPlaylist)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 6,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $isPlaylist ? 35 : 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (IPTV Proxy)',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FAILONERROR => false
    ]);
    return $ch;
}

function buildUpstreamHeaders(): array
{
    $headers = [
        'Accept: */*',
        'Connection: keep-alive'
    ];

    if (!empty($_SERVER['HTTP_RANGE'])) {
        $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    }

    if (!empty($_SERVER['HTTP_REFERER'])) {
        $headers[] = 'Referer: ' . $_SERVER['HTTP_REFERER'];
    }

    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $headers[] = 'Origin: ' . $_SERVER['HTTP_ORIGIN'];
    }

    return $headers;
}

function rewriteM3U8Playlist(string $playlist, string $baseUrl): string
{
    $lines = preg_split("/\r\n|\n|\r/", $playlist) ?: [];
    $rewritten = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $rewritten[] = '';
            continue;
        }

        if ($trimmed[0] === '#') {
            // Rewrite URI attributes in tags like EXT-X-KEY and EXT-X-MAP.
            $trimmed = preg_replace_callback('/URI="([^"]+)"/', static function (array $match) use ($baseUrl): string {
                $resolved = resolveUrl($baseUrl, $match[1]);
                return 'URI="' . buildProxyUrl($resolved) . '"';
            }, $trimmed) ?? $trimmed;

            $rewritten[] = $trimmed;
            continue;
        }

        $absolute = resolveUrl($baseUrl, $trimmed);
        $rewritten[] = buildProxyUrl($absolute);
    }

    return implode("\n", $rewritten);
}

function resolveUrl(string $baseUrl, string $relative): string
{
    if ($relative === '') {
        return $baseUrl;
    }

    if (preg_match('#^https?://#i', $relative)) {
        return $relative;
    }

    $base = parse_url($baseUrl);
    if (!$base || empty($base['scheme']) || empty($base['host'])) {
        return $relative;
    }

    $scheme = $base['scheme'];
    $host = $base['host'];
    $port = isset($base['port']) ? ':' . $base['port'] : '';
    $basePath = $base['path'] ?? '/';

    if (strpos($relative, '//') === 0) {
        return $scheme . ':' . $relative;
    }

    if ($relative[0] === '#') {
        return $baseUrl;
    }

    if ($relative[0] === '?') {
        return $scheme . '://' . $host . $port . $basePath . $relative;
    }

    if ($relative[0] === '/') {
        return $scheme . '://' . $host . $port . normalizePath($relative);
    }

    $dir = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';
    $path = normalizePath($dir . $relative);
    return $scheme . '://' . $host . $port . $path;
}

function normalizePath(string $path): string
{
    $segments = explode('/', $path);
    $output = [];

    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($output);
            continue;
        }
        $output[] = $segment;
    }

    return '/' . implode('/', $output);
}

function buildProxyUrl(string $absoluteUrl): string
{
    return '/proxy.php?url=' . rawurlencode($absoluteUrl);
}

function proxyNormalizeProviderStatus(int $status): int
{
    if (in_array($status, [401, 403, 404], true)) {
        return $status;
    }

    if ($status === 408) {
        return 504;
    }

    if ($status >= 500 || $status <= 0) {
        return 502;
    }

    return 502;
}

function proxyProviderStatusMessage(int $status): string
{
    if ($status === 401) return 'Provider unauthorized';
    if ($status === 403) return 'Provider forbidden';
    if ($status === 404) return 'Provider stream missing';
    if ($status === 504) return 'Provider timeout';
    return 'Provider upstream error';
}

function isLikelyPlaylistUrl(string $url): bool
{
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    return (bool) preg_match('/\.(m3u8|m3u)$/i', $path);
}

function isM3U8Payload(string $content): bool
{
    $trimmed = ltrim($content);
    return $trimmed !== '' && str_starts_with($trimmed, '#EXTM3U');
}

function extractUpstreamProviderError(string $content): ?array
{
    $trimmed = trim($content);
    if ($trimmed === '') {
        return null;
    }

    $firstChar = $trimmed[0];
    if ($firstChar !== '{' && $firstChar !== '[') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (!is_array($decoded)) {
        return null;
    }

    if (!isset($decoded['error']) && !isset($decoded['message']) && !isset($decoded['status'])) {
        return null;
    }

    return [
        'status' => isset($decoded['status']) ? (int)$decoded['status'] : null,
        'error' => isset($decoded['error']) ? (string)$decoded['error'] : '',
        'message' => isset($decoded['message']) ? (string)$decoded['message'] : '',
    ];
}

function guessContentType(string $url): string
{
    $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));

    if (str_ends_with($path, '.m3u8') || str_ends_with($path, '.m3u')) {
        return 'application/vnd.apple.mpegurl';
    }
    if (str_ends_with($path, '.ts')) {
        return 'video/mp2t';
    }
    if (str_ends_with($path, '.mpd')) {
        return 'application/dash+xml';
    }
    if (str_ends_with($path, '.mp4')) {
        return 'video/mp4';
    }
    return 'application/octet-stream';
}

function isValidRemoteUrl(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }

    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return false;
    }

    return true;
}

function sanitizeHeaderValue(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function failWith(int $status, string $message): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo $message;
    exit;
}
