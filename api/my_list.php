<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

header('Content-Type: application/json');

$customerId = $_SESSION['customer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content_type = $_POST['content_type'] ?? '';
    $content_id = (int) ($_POST['content_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (!in_array($content_type, ['movie', 'series'], true) || $content_id <= 0 || !in_array($action, ['add', 'remove'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO my_list (customer_id, content_type, content_id) VALUES (?, ?, ?)");
        $stmt->execute([$customerId, $content_type, $content_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM my_list WHERE customer_id = ? AND content_type = ? AND content_id = ?");
        $stmt->execute([$customerId, $content_type, $content_id]);
    }

    echo json_encode(['status' => 'ok']);
    exit;
}

// GET list
$movies = $pdo->prepare("SELECT m.id, m.title, m.poster_url, 'movie' AS type FROM my_list ml JOIN movies m ON ml.content_id = m.id WHERE ml.customer_id = ? AND ml.content_type = 'movie'");
$movies->execute([$customerId]);
$series = $pdo->prepare("SELECT s.id, s.title, s.poster_url, 'series' AS type FROM my_list ml JOIN series s ON ml.content_id = s.id WHERE ml.customer_id = ? AND ml.content_type = 'series'");
$series->execute([$customerId]);

echo json_encode([
    'movies' => $movies->fetchAll(PDO::FETCH_ASSOC),
    'series' => $series->fetchAll(PDO::FETCH_ASSOC)
]);
