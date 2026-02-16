<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

header('Content-Type: application/json');

$customerId = $_SESSION['customer_id'];

// Featured (movies + series ordered by is_featured/popularity)
$featured = $pdo->query("
    (SELECT id, title, poster_url, banner_url, 'movie' AS type, genre, year FROM movies WHERE is_featured = 1 ORDER BY popularity DESC, created_at DESC LIMIT 10)
    UNION ALL
    (SELECT id, title, poster_url, banner_url, 'series' AS type, genre, year FROM series WHERE is_featured = 1 ORDER BY popularity DESC, created_at DESC LIMIT 10)
")->fetchAll(PDO::FETCH_ASSOC);

// Continue watching
$cwStmt = $pdo->prepare("
    SELECT wh.content_type, wh.content_id, wh.episode_id, wh.last_position_seconds, wh.updated_at,
           COALESCE(m.title, s.title) AS title,
           COALESCE(m.poster_url, s.poster_url) AS poster_url
    FROM watch_history wh
    LEFT JOIN movies m ON wh.content_type='movie' AND wh.content_id = m.id
    LEFT JOIN series s ON wh.content_type='series' AND wh.content_id = s.id
    WHERE wh.customer_id = ?
    ORDER BY wh.updated_at DESC
    LIMIT 15
");
$cwStmt->execute([$customerId]);
$continue = $cwStmt->fetchAll(PDO::FETCH_ASSOC);

// My list
$listStmt = $pdo->prepare("
    SELECT ml.content_type, ml.content_id,
           COALESCE(m.title, s.title) AS title,
           COALESCE(m.poster_url, s.poster_url) AS poster_url
    FROM my_list ml
    LEFT JOIN movies m ON ml.content_type='movie' AND ml.content_id = m.id
    LEFT JOIN series s ON ml.content_type='series' AND ml.content_id = s.id
    WHERE ml.customer_id = ?
    ORDER BY ml.created_at DESC
    LIMIT 15
");
$listStmt->execute([$customerId]);
$myList = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Genres rows (simple fixed set)
$genres = ['Action','Drama','Comedy','Thriller','Kids','Documentary'];
$genreRows = [];
foreach ($genres as $g) {
    $stmt = $pdo->prepare("
        (SELECT id, title, poster_url, 'movie' AS type FROM movies WHERE genre LIKE ? ORDER BY popularity DESC, created_at DESC LIMIT 12)
        UNION ALL
        (SELECT id, title, poster_url, 'series' AS type FROM series WHERE genre LIKE ? ORDER BY popularity DESC, created_at DESC LIMIT 12)
    ");
    $like = '%' . $g . '%';
    $stmt->execute([$like, $like]);
    $genreRows[] = ['genre' => $g, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

echo json_encode([
    'featured' => $featured,
    'continue_watching' => $continue,
    'my_list' => $myList,
    'genres' => $genreRows
]);
