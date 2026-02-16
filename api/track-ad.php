<?php
include '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['ad_id'])) {
            throw new Exception('Invalid input');
        }
        
        $ad_id = $input['ad_id'];
        $type = $input['type'] ?? 'impression';
        
        // Update ad statistics
        switch ($type) {
            case 'impression':
                $stmt = $pdo->prepare("UPDATE video_ads SET impressions = impressions + 1 WHERE id = ?");
                break;
            case 'click':
                $stmt = $pdo->prepare("UPDATE video_ads SET clicks = clicks + 1 WHERE id = ?");
                break;
            case 'skip':
                // You might want to track skips separately
                $stmt = $pdo->prepare("UPDATE video_ads SET impressions = impressions + 1 WHERE id = ?");
                break;
            default:
                throw new Exception('Invalid tracking type');
        }
        
        $stmt->execute([$ad_id]);
        
        $response = ['success' => true];
        
    } catch (Exception $e) {
        http_response_code(400);
        $response = ['success' => false, 'error' => $e->getMessage()];
    }
} else {
    http_response_code(405);
    $response = ['error' => 'Method not allowed'];
}

echo json_encode($response);
?>