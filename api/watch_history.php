<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content_type = $_POST['content_type'] ?? '';
    $content_id = (int) ($_POST['content_id'] ?? 0);
    $episode_id = isset($_POST['episode_id']) && $_POST['episode_id'] !== '' ? (int) $_POST['episode_id'] : null;
    $position = max(0, (int) ($_POST['position_seconds'] ?? 0));
    $ended = filter_var($_POST['ended'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!in_array($content_type, ['movie', 'series'], true) || $content_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    if ($ended) {
        $position = 0;
    }

    $stmt = $pdo->prepare("
        INSERT INTO watch_history (customer_id, content_type, content_id, episode_id, last_position_seconds)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE last_position_seconds = VALUES(last_position_seconds), updated_at = CURRENT_TIMESTAMP, episode_id = VALUES(episode_id)
    ");
    $stmt->execute([$_SESSION['customer_id'], $content_type, $content_id, $episode_id, $position]);

    echo json_encode(['status' => 'ok', 'position' => $position]);
    exit;
}

// GET: fetch last position for given content
$content_type = $_GET['content_type'] ?? '';
$content_id = (int) ($_GET['content_id'] ?? 0);

if (!in_array($content_type, ['movie', 'series'], true) || $content_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid query']);
    exit;
}

$stmt = $pdo->prepare("SELECT episode_id, last_position_seconds FROM watch_history WHERE customer_id = ? AND content_type = ? AND content_id = ? ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$_SESSION['customer_id'], $content_type, $content_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($row ?: ['episode_id' => null, 'last_position_seconds' => 0]);
