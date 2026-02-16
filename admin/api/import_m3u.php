<?php
include '../../includes/config.php';
include '../../includes/auth.php';
include '../../includes/m3u-parser.php';

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
    
    // Import channels
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($channels as $index => $channel) {
        try {
            // Check if channel already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM channels WHERE stream_url = ?");
            $stmt->execute([$channel['stream_url']]);
            $exists = $stmt->fetchColumn();
            
            if ($exists) {
                $skipped++;
                continue;
            }
            
            // Insert new channel
            $stmt = $pdo->prepare("
                INSERT INTO channels (name, stream_url, category, logo_url, tvg_id, drm_type, license_key, license_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $channel['name'] ?? 'Unknown Channel',
                $channel['stream_url'] ?? '',
                $channel['category'] ?? 'General',
                $channel['logo'] ?? '',
                $channel['tvg_id'] ?? '',
                $channel['drm']['type'] ?? null,
                $channel['drm']['license_key'] ?? null,
                $channel['drm']['license_url'] ?? null
            ]);
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Channel {$index}: " . $e->getMessage();
        }
    }
    
    // Clean up uploaded file
    unlink($filePath);
    
    $response = [
        'success' => true,
        'data' => [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'stats' => $stats,
            'totalProcessed' => count($channels)
        ],
        'message' => "Successfully imported {$imported} channels"
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