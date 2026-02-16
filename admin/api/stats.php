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
    // Get basic stats
    $total_channels = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
    $active_channels = $pdo->query("SELECT COUNT(*) FROM channels WHERE is_active = true")->fetchColumn();
    $total_views = $pdo->query("SELECT SUM(views) FROM channels")->fetchColumn();
    $total_categories = $pdo->query("SELECT COUNT(DISTINCT category) FROM channels")->fetchColumn();
    
    // Get DRM stats
    $drm_stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN drm_type IS NOT NULL AND drm_type != '' THEN 1 ELSE 0 END) as drm_protected,
            SUM(CASE WHEN drm_type = 'clearkey' THEN 1 ELSE 0 END) as clearkey,
            SUM(CASE WHEN drm_type = 'widevine' THEN 1 ELSE 0 END) as widevine,
            SUM(CASE WHEN drm_type = 'playready' THEN 1 ELSE 0 END) as playready
        FROM channels
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activity (last 7 days)
    $recent_activity = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as channels_added,
            SUM(views) as daily_views
        FROM channels 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get top channels by views
    $top_channels = $pdo->query("
        SELECT name, views, category 
        FROM channels 
        ORDER BY views DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get category distribution
    $category_distribution = $pdo->query("
        SELECT category, COUNT(*) as channel_count
        FROM channels 
        WHERE is_active = true
        GROUP BY category 
        ORDER BY channel_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'overview' => [
                'totalChannels' => (int)$total_channels,
                'activeChannels' => (int)$active_channels,
                'totalViews' => (int)$total_views,
                'totalCategories' => (int)$total_categories
            ],
            'drmStats' => $drm_stats,
            'recentActivity' => $recent_activity,
            'topChannels' => $top_channels,
            'categoryDistribution' => $category_distribution,
            'serverInfo' => [
                'phpVersion' => PHP_VERSION,
                'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'lastUpdate' => date('Y-m-d H:i:s')
            ]
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