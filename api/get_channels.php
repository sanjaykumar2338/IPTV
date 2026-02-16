<?php
include '../includes/config.php';
include '../includes/functions.php';

header('Content-Type: application/json');

try {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Build query
    $query = "SELECT * FROM channels WHERE is_active = true";
    $params = [];

    if ($category) {
        $query .= " AND category = ?";
        $params[] = $category;
    }

    if ($search) {
        $query .= " AND name LIKE ?";
        $params[] = '%' . $search . '%';
    }

    $query .= " ORDER BY name LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM channels WHERE is_active = true";
    $count_params = [];

    if ($category) {
        $count_query .= " AND category = ?";
        $count_params[] = $category;
    }

    if ($search) {
        $count_query .= " AND name LIKE ?";
        $count_params[] = '%' . $search . '%';
    }

    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_channels = $count_stmt->fetchColumn();

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'channels' => $channels,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total_channels,
                'pages' => ceil($total_channels / $limit)
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

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>