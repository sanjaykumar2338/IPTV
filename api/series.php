<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM series WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$series = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$series) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

$seasonsStmt = $pdo->prepare("SELECT DISTINCT season_number FROM episodes WHERE series_id = ? ORDER BY season_number");
$seasonsStmt->execute([$id]);
$series['seasons_list'] = $seasonsStmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($series);
