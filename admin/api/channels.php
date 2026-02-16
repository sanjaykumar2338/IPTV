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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get channels with pagination and filtering
    try {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(max(1, intval($_GET['limit'])), 100) : 20;
        $offset = ($page - 1) * $limit;
        
        $category = $_GET['category'] ?? '';
        $search = $_GET['search'] ?? '';
        $drm_type = $_GET['drm_type'] ?? '';
        $active_only = isset($_GET['active']) ? $_GET['active'] === 'true' : true;
        
        // Build query
        $query = "SELECT * FROM channels WHERE 1=1";
        $count_query = "SELECT COUNT(*) FROM channels WHERE 1=1";
        $params = [];
        $count_params = [];
        
        if ($active_only) {
            $query .= " AND is_active = true";
            $count_query .= " AND is_active = true";
        }
        
        if ($category) {
            $query .= " AND category = ?";
            $count_query .= " AND category = ?";
            $params[] = $category;
            $count_params[] = $category;
        }
        
        if ($search) {
            $query .= " AND name LIKE ?";
            $count_query .= " AND name LIKE ?";
            $params[] = "%{$search}%";
            $count_params[] = "%{$search}%";
        }
        
        if ($drm_type) {
            if ($drm_type === 'none') {
                $query .= " AND (drm_type IS NULL OR drm_type = '')";
                $count_query .= " AND (drm_type IS NULL OR drm_type = '')";
            } else {
                $query .= " AND drm_type = ?";
                $count_query .= " AND drm_type = ?";
                $params[] = $drm_type;
                $count_params[] = $drm_type;
            }
        }
        
        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute($count_params);
        $total = $count_stmt->fetchColumn();
        
        $response = [
            'success' => true,
            'data' => [
                'channels' => $channels,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new channel
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $required = ['name', 'stream_url'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO channels (name, stream_url, category, logo_url, tvg_id, drm_type, license_key, license_url, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['name'],
            $input['stream_url'],
            $input['category'] ?? 'General',
            $input['logo_url'] ?? '',
            $input['tvg_id'] ?? '',
            $input['drm_type'] ?? null,
            $input['license_key'] ?? null,
            $input['license_url'] ?? null,
            $input['is_active'] ?? true
        ]);
        
        $channel_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Channel created successfully',
            'data' => ['id' => $channel_id]
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>