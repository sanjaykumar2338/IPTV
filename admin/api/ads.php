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
    // Get ads
    try {
        $position = $_GET['position'] ?? '';
        
        $query = "SELECT * FROM ads WHERE 1=1";
        $params = [];
        
        if ($position) {
            $query .= " AND ad_position = ?";
            $params[] = $position;
        }
        
        $query .= " ORDER BY ad_position, ad_type";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $ads]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create or update ad
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $required = ['ad_type', 'ad_position'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        if (isset($input['id'])) {
            // Update existing ad
            $stmt = $pdo->prepare("
                UPDATE ads SET 
                ad_type = ?, ad_position = ?, ad_code = ?, image_url = ?, link_url = ?, is_active = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $input['ad_type'],
                $input['ad_position'],
                $input['ad_code'] ?? '',
                $input['image_url'] ?? '',
                $input['link_url'] ?? '',
                $input['is_active'] ?? true,
                $input['id']
            ]);
            
            $message = 'Ad updated successfully';
        } else {
            // Create new ad
            $stmt = $pdo->prepare("
                INSERT INTO ads (ad_type, ad_position, ad_code, image_url, link_url, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $input['ad_type'],
                $input['ad_position'],
                $input['ad_code'] ?? '',
                $input['image_url'] ?? '',
                $input['link_url'] ?? '',
                $input['is_active'] ?? true
            ]);
            
            $message = 'Ad created successfully';
        }
        
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>