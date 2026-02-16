<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

header('Content-Type: application/json');
$customerId = $_SESSION['customer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $channel_id = (int) ($_POST['channel_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($channel_id <= 0 || !in_array($action, ['add','remove'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO channel_favorites (customer_id, channel_id) VALUES (?, ?)");
        $stmt->execute([$customerId, $channel_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM channel_favorites WHERE customer_id = ? AND channel_id = ?");
        $stmt->execute([$customerId, $channel_id]);
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

$stmt = $pdo->prepare("SELECT channel_id FROM channel_favorites WHERE customer_id = ?");
$stmt->execute([$customerId]);
$favs = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode(['favorites' => array_map('intval', $favs)]);
