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
    // Get all settings
    try {
        $settings = $pdo->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        echo json_encode(['success' => true, 'data' => $settings]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update settings
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $pdo->beginTransaction();
        
        foreach ($input as $key => $value) {
            // Check if setting exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            }
            
            $stmt->execute([$value, $key]);
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>