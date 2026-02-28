<?php
include '../../includes/config.php';
include '../../includes/auth.php';
include '../../includes/m3u-parser.php';
include '../../includes/vod_import.php';
include '../../includes/provider_validator.php';
include '../../includes/provider_cleanup.php';
include '../../pages/import_logger.php';

import_log("IMPORT START (api/import_m3u.php)");

import_log("PHP INFO", [
  'php_version' => PHP_VERSION,
  'memory_limit' => ini_get('memory_limit'),
  'max_execution_time' => ini_get('max_execution_time'),
  'upload_max_filesize' => ini_get('upload_max_filesize'),
  'post_max_size' => ini_get('post_max_size'),
  'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
]);

import_log("FILES SNAPSHOT", [
  '_FILES_keys' => array_keys($_FILES ?? []),
  '_POST_keys' => array_keys($_POST ?? []),
]);

@set_time_limit(0);
@ini_set('memory_limit', '1024M');

// Require admin authentication for API access
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_valid_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

try {
    // Check if file was uploaded
    if (!isset($_FILES['m3u_file']) || $_FILES['m3u_file']['error'] !== UPLOAD_ERR_OK) {
        import_log("UPLOAD FAILED", ['error' => $_FILES['m3u_file']['error'] ?? 'NO_FILE']);
        throw new Exception('No file uploaded or upload error');
    }
    
    import_log("UPLOAD META", [
        'name' => $_FILES['m3u_file']['name'] ?? null,
        'type' => $_FILES['m3u_file']['type'] ?? null,
        'size' => $_FILES['m3u_file']['size'] ?? null,
        'tmp_name' => $_FILES['m3u_file']['tmp_name'] ?? null,
        'error' => $_FILES['m3u_file']['error'] ?? null,
    ]);
    
    $uploadDir = '../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = time() . '_' . basename($_FILES['m3u_file']['name']);
    $filePath = $uploadDir . $fileName;
    
    // Move uploaded file
    if (!move_uploaded_file($_FILES['m3u_file']['tmp_name'], $filePath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Parse M3U file
    $parser = new M3UParser($filePath);
    $channels = $parser->parse();
    $stats = $parser->getStats();
    import_log("PARSE DONE", ['channels' => count($channels), 'stats' => $stats]);
    
    if (empty($channels)) {
        throw new Exception('No channels found in the M3U file');
    }

    provider_ensure_schema($pdo);

    $precheck = provider_precheck_entries($channels);
    import_log("PROVIDER PRECHECK (api/import_m3u.php)", [
        'ok' => $precheck['ok'],
        'message' => $precheck['message'],
        'results' => $precheck['results']
    ]);
    if (!$precheck['ok']) {
        @unlink($filePath);
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => $precheck['message'],
            'provider_checks' => $precheck['results']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $replaceProviderData = !isset($_POST['replace_provider_data']) || $_POST['replace_provider_data'] === '1';
    $confirmLegacyWipe = isset($_POST['confirm_legacy_wipe']) && $_POST['confirm_legacy_wipe'] === '1';
    $allowLegacyWipe = ENABLE_LEGACY_UNASSIGNED_WIPE && $confirmLegacyWipe;
    $providerMeta = provider_detect_from_entries($channels, (string)($_FILES['m3u_file']['name'] ?? 'Imported Provider'));
    $provider = provider_get_or_create($pdo, $providerMeta);
    $providerId = (int)$provider['id'];
    $providerSafe = provider_safe_summary($provider);

    if ($allowLegacyWipe) {
        $legacyCounts = legacyCatalogCounts($pdo);
        $legacyDeleted = wipeLegacyUnassignedCatalog($pdo);
        import_log("LEGACY UNASSIGNED WIPE (api/import_m3u.php)", [
            'before' => $legacyCounts,
            'deleted' => $legacyDeleted
        ]);
    }

    if ($replaceProviderData) {
        $replaceSummary = replaceProviderCatalogAtomically($pdo, $providerId, $channels, [
            'provider_scope' => provider_scope_from_id($providerId),
            'commit_every' => 100
        ]);
        $importStats = $replaceSummary['import'];
    } else {
        $importStats = import_vod_from_m3u($pdo, $channels, 100, [
            'provider_id' => $providerId,
            'provider_scope' => provider_scope_from_id($providerId)
        ]);
    }
    import_log("IMPORT DONE (API)", $importStats);
    unlink($filePath); // clean up
    
    $response = [
        'success' => true,
        'data' => [
            'live_imported' => $importStats['live_imported'],
            'live_skipped' => $importStats['live_skipped'],
            'movies_inserted' => $importStats['movies_inserted'],
            'movies_updated' => $importStats['movies_updated'],
            'series_inserted' => $importStats['series_inserted'],
            'series_updated' => $importStats['series_updated'],
            'episodes_inserted' => $importStats['episodes_inserted'],
            'episodes_updated' => $importStats['episodes_updated'],
            'errors' => $importStats['errors'],
            'stats' => $stats,
            'totalProcessed' => count($channels),
            'provider' => $providerSafe
        ],
        'message' => "Provider import complete. Live: {$importStats['live_imported']} (skipped {$importStats['live_skipped']}), movies: {$importStats['movies_inserted']} new / {$importStats['movies_updated']} updated, series: {$importStats['series_inserted']} new / {$importStats['series_updated']} updated"
    ];
    
} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
