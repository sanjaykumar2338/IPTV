<?php
include '../includes/config.php';
include '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Get video ads based on current channel category
    $category = $_GET['category'] ?? '';
    
    $query = "SELECT * FROM video_ads WHERE is_active = true";
    $params = [];
    
    if ($category) {
        $query .= " AND (categories = '' OR categories LIKE ? OR categories IS NULL)";
        $params[] = "%{$category}%";
    }
    
    $query .= " ORDER BY RAND()";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'data' => [
            'ads' => $ads
        ]
    ];
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>