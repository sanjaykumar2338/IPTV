<?php
include '../includes/config.php';
include '../includes/auth.php';
include '../pages/import_logger.php';
include '../includes/provider_validator.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'status' => 401,
        'message' => 'Unauthorized'
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'status' => 405,
        'message' => 'Method not allowed'
    ]);
    exit;
}

require_valid_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

$url = trim((string)($_POST['url'] ?? ''));
if ($url === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'status' => 400,
        'message' => 'Missing URL'
    ]);
    exit;
}

$result = provider_validate_url($url, [
    'timeout' => 15,
    'connect_timeout' => 8
]);

provider_log_event('PROVIDER TEST (manual)', [
    'url' => provider_mask_url($url),
    'result' => $result
]);

$responseStatus = !empty($result['ok']) ? 200 : (int)($result['status'] ?? 502);
if ($responseStatus < 100 || $responseStatus > 599) {
    $responseStatus = 502;
}
http_response_code($responseStatus);

echo json_encode([
    'ok' => (bool)($result['ok'] ?? false),
    'status' => (int)($result['status'] ?? 0),
    'message' => (string)($result['message'] ?? ''),
    'content_type' => (string)($result['content_type'] ?? ''),
    'sample' => (string)($result['sample'] ?? ''),
    'effective_url' => (string)($result['effective_url'] ?? ''),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
