<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['q' => '', 'movies' => [], 'series' => []]);
    exit;
}
$like = '%' . $q . '%';

$movieStmt = $pdo->prepare("SELECT id, title, poster_url as poster, year FROM movies WHERE title LIKE ? ORDER BY created_at DESC LIMIT 5");
$movieStmt->execute([$like]);

$seriesStmt = $pdo->prepare("SELECT id, title, poster_url as poster, year FROM series WHERE title LIKE ? ORDER BY created_at DESC LIMIT 5");
$seriesStmt->execute([$like]);

echo json_encode([
    'q' => $q,
    'movies' => $movieStmt->fetchAll(PDO::FETCH_ASSOC),
    'series' => $seriesStmt->fetchAll(PDO::FETCH_ASSOC)
]);
