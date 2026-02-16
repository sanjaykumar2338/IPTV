<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$id = (int) ($_GET['id'] ?? 0);
$movieStmt = $pdo->prepare("SELECT * FROM movies WHERE id = ? LIMIT 1");
$movieStmt->execute([$id]);
$movie = $movieStmt->fetch(PDO::FETCH_ASSOC);

if (!$movie) {
    http_response_code(404);
    echo "Movie not found";
    exit;
}

// Watch history fetch for resume position
$resume = 0;
$historyStmt = $pdo->prepare("SELECT last_position_seconds FROM watch_history WHERE customer_id = ? AND content_type = 'movie' AND content_id = ? LIMIT 1");
$historyStmt->execute([$_SESSION['customer_id'], $movie['id']]);
if ($row = $historyStmt->fetch(PDO::FETCH_ASSOC)) {
    $resume = (int) $row['last_position_seconds'];
}

$inListStmt = $pdo->prepare("SELECT 1 FROM my_list WHERE customer_id = ? AND content_type='movie' AND content_id = ? LIMIT 1");
$inListStmt->execute([$_SESSION['customer_id'], $movie['id']]);
$inList = (bool)$inListStmt->fetchColumn();

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($movie['title']); ?> - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/pages/partials/header.php'; ?>
<main class="page-shell">
    <div class="glass-card" style="display:grid; grid-template-columns: 320px 1fr; gap:20px; align-items:start;">
        <div>
            <img src="<?php echo sanitize($movie['poster_url']); ?>" alt="<?php echo sanitize($movie['title']); ?>" style="width:100%; border-radius:16px;">
        </div>
        <div>
            <div class="badge">Movie</div>
            <h1 style="margin:8px 0 6px; font-size:2.2rem;"><?php echo sanitize($movie['title']); ?></h1>
            <p style="color:var(--muted); margin:6px 0 14px;">Year: <?php echo (int)$movie['year']; ?> • Genre: <?php echo sanitize($movie['genre']); ?> • Rating: <?php echo $movie['rating']; ?> • Duration: <?php echo (int)$movie['duration_minutes']; ?>m</p>
            <p style="line-height:1.6; margin-bottom:16px; color:var(--text); opacity:0.92;"><?php echo nl2br(sanitize($movie['synopsis'])); ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a class="button-pill" href="/player.php?stream=<?php echo urlencode($movie['stream_url']); ?>&name=<?php echo urlencode($movie['title']); ?>&content_type=movie&content_id=<?php echo $movie['id']; ?>">
                    <i class="fas fa-play"></i> Watch Now
                </a>
                <button class="button-pill button-secondary" id="toggleMyList" data-type="movie" data-id="<?php echo $movie['id']; ?>" data-inlist="<?php echo $inList ? '1' : '0'; ?>">
                    <i class="fas <?php echo $inList ? 'fa-check' : 'fa-plus'; ?>"></i> <?php echo $inList ? 'In My List' : 'My List'; ?>
                </button>
                <?php if ($resume > 0): ?>
                    <span class="badge">Resume at <?php echo gmdate('H:i:s', $resume); ?></span>
                <?php endif; ?>
                <?php if (!empty($movie['trailer_url'])): ?>
                    <a class="button-pill button-secondary" href="<?php echo sanitize($movie['trailer_url']); ?>" target="_blank"><i class="fas fa-film"></i> Trailer</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<script src="/assets/js/my-list.js"></script>
</body>
</html>
