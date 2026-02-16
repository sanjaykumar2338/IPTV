<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$movie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$movie) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

echo json_encode($movie);
