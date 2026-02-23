<?php
include '../../includes/config.php';
include '../../includes/auth.php';
include '../../includes/m3u-parser.php';
include '../../includes/vod_import.php';

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

try {
    // Check if file was uploaded
    if (!isset($_FILES['m3u_file']) || $_FILES['m3u_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
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
    
    if (empty($channels)) {
        throw new Exception('No channels found in the M3U file');
    }
    
    $importStats = import_vod_from_m3u($pdo, $channels);
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
            'totalProcessed' => count($channels)
        ],
        'message' => "Imported live: {$importStats['live_imported']} (skipped {$importStats['live_skipped']}), movies: {$importStats['movies_inserted']} new / {$importStats['movies_updated']} updated, series: {$importStats['series_inserted']} new / {$importStats['series_updated']} updated"
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
