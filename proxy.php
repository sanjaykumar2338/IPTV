<?php
// Simple Proxy that actually works
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the URL to proxy
$url = $_GET['url'] ?? '';
if (!$url) {
    header("HTTP/1.0 400 Bad Request");
    echo "No URL provided";
    exit;
}

// Decode URL
$url = urldecode($url);

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    header("HTTP/1.0 400 Bad Request");
    echo "Invalid URL";
    exit;
}

// Set appropriate headers
if (strpos($url, '.m3u8') !== false) {
    header('Content-Type: application/vnd.apple.mpegurl');
} elseif (strpos($url, '.ts') !== false) {
    header('Content-Type: video/mp2t');
} else {
    header('Content-Type: application/octet-stream');
}

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Use cURL to fetch the content
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HEADER => false,
    CURLOPT_FAILONERROR => true
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    header("HTTP/1.0 500 Internal Server Error");
    echo "Proxy Error: " . $error;
    exit;
}

if ($http_code !== 200) {
    header("HTTP/1.0 {$http_code}");
    echo "HTTP Error: {$http_code}";
    exit;
}

// If it's an M3U8 file, process it to proxy segment URLs
if (strpos($url, '.m3u8') !== false && strpos($response, '#EXTM3U') !== false) {
    $lines = explode("\n", $response);
    $base_url = dirname($url) . '/';
    
    foreach ($lines as &$line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // If it's a segment URL (not a comment and not starting with #)
        if ($line[0] !== '#' && !empty($line)) {
            // Convert to absolute URL if relative
            if (strpos($line, 'http') !== 0) {
                $line = $base_url . ltrim($line, './');
            }
            // Proxy the segment URL
            $line = "/proxy.php?url=" . urlencode($line);
        }
    }
    
    $response = implode("\n", $lines);
}

echo $response;
?>