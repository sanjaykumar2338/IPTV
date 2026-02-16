<?php
include '../includes/config.php';

header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

try {
    $channel_id = $_GET['channel_id'] ?? $_POST['channel_id'] ?? 0;
    
    if (!$channel_id) {
        throw new Exception('Channel ID is required');
    }

    // Update view count
    $stmt = $pdo->prepare("UPDATE channels SET views = views + 1 WHERE id = ?");
    $stmt->execute([$channel_id]);

    // Get updated view count
    $stmt = $pdo->prepare("SELECT views FROM channels WHERE id = ?");
    $stmt->execute([$channel_id]);
    $views = $stmt->fetchColumn();

    $response = [
        'success' => true,
        'views' => $views
    ];

} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
?>