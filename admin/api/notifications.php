<?php
include '../../includes/config.php';
include '../../includes/auth.php';

// Require admin authentication for API access
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $notifications = [];
    
    // Check for channels without logos
    $channels_without_logos = $pdo->query("
        SELECT COUNT(*) as count 
        FROM channels 
        WHERE (logo_url IS NULL OR logo_url = '') AND is_active = true
    ")->fetchColumn();
    
    if ($channels_without_logos > 0) {
        $notifications[] = [
            'type' => 'warning',
            'title' => 'Channels without logos',
            'message' => "{$channels_without_logos} channels are missing logos",
            'action' => 'admin/channels.php',
            'icon' => 'fa-image',
            'timestamp' => time()
        ];
    }
    
    // Check for inactive channels
    $inactive_channels = $pdo->query("
        SELECT COUNT(*) as count 
        FROM channels 
        WHERE is_active = false
    ")->fetchColumn();
    
    if ($inactive_channels > 0) {
        $notifications[] = [
            'type' => 'info',
            'title' => 'Inactive channels',
            'message' => "{$inactive_channels} channels are currently inactive",
            'action' => 'admin/channels.php',
            'icon' => 'fa-power-off',
            'timestamp' => time()
        ];
    }
    
    // Check for ads configuration
    $active_ads = $pdo->query("
        SELECT COUNT(*) as count 
        FROM ads 
        WHERE is_active = true
    ")->fetchColumn();
    
    if ($active_ads == 0) {
        $notifications[] = [
            'type' => 'warning',
            'title' => 'No active ads',
            'message' => 'No advertisements are currently active',
            'action' => 'admin/ads.php',
            'icon' => 'fa-ad',
            'timestamp' => time()
        ];
    }
    
    // Check for recent imports (last 24 hours)
    $recent_imports = $pdo->query("
        SELECT COUNT(*) as count 
        FROM channels 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ")->fetchColumn();
    
    if ($recent_imports > 0) {
        $notifications[] = [
            'type' => 'success',
            'title' => 'Recent imports',
            'message' => "{$recent_imports} channels imported in the last 24 hours",
            'action' => 'admin/channels.php',
            'icon' => 'fa-upload',
            'timestamp' => time()
        ];
    }
    
    // System check notifications
    $upload_dir = '../../uploads/';
    if (!is_writable($upload_dir)) {
        $notifications[] = [
            'type' => 'error',
            'title' => 'Upload directory not writable',
            'message' => 'The uploads directory needs write permissions',
            'action' => 'admin/settings.php',
            'icon' => 'fa-folder',
            'timestamp' => time()
        ];
    }
    
    // Check for PHP version
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $notifications[] = [
            'type' => 'warning',
            'title' => 'PHP version outdated',
            'message' => 'Consider upgrading to PHP 7.4 or higher for better performance',
            'action' => 'admin/settings.php',
            'icon' => 'fa-exclamation-triangle',
            'timestamp' => time()
        ];
    }
    
    $response = [
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'unreadCount' => count($notifications),
            'lastChecked' => date('Y-m-d H:i:s')
        ]
    ];
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>