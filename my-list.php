<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
$asset_version = time();

// Fetch list
$listMovies = $pdo->prepare("SELECT m.id, m.title, m.poster_url FROM my_list ml JOIN movies m ON ml.content_id = m.id WHERE ml.customer_id = ? AND ml.content_type = 'movie'");
$listMovies->execute([$_SESSION['customer_id']]);
$movies = $listMovies->fetchAll(PDO::FETCH_ASSOC);

$listSeries = $pdo->prepare("SELECT s.id, s.title, s.poster_url FROM my_list ml JOIN series s ON ml.content_id = s.id WHERE ml.customer_id = ? AND ml.content_type = 'series'");
$listSeries->execute([$_SESSION['customer_id']]);
$series = $listSeries->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My List - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/pages/partials/header.php'; ?>
<main class="page-shell">
    <div class="section-title"><span>My List</span></div>
    <div class="section">
        <div class="card-grid">
            <?php foreach ($movies as $item): ?>
                <div class="channel-card">
                    <div class="channel-logo">
                        <?php if (!empty($item['poster_url'])): ?>
                            <img src="<?php echo sanitize($item['poster_url']); ?>" alt="<?php echo sanitize($item['title']); ?>" style="max-width:90px; max-height:70px; border-radius:6px;">
                        <?php else: ?>
                            <i class="fas fa-film"></i>
                        <?php endif; ?>
                    </div>
                    <div class="channel-info">
                        <h3 class="channel-title"><?php echo sanitize($item['title']); ?> <span class="badge">Movie</span></h3>
                        <div style="display:flex; gap:6px; margin-top:8px;">
                            <a class="button-pill" style="padding:8px 12px; font-size:0.9rem;" href="/movie.php?id=<?php echo $item['id']; ?>">Open</a>
                            <button class="button-pill button-secondary remove-list" data-type="movie" data-id="<?php echo $item['id']; ?>" style="padding:8px 12px; font-size:0.9rem;">Remove</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($series as $item): ?>
                <div class="channel-card">
                    <div class="channel-logo">
                        <?php if (!empty($item['poster_url'])): ?>
                            <img src="<?php echo sanitize($item['poster_url']); ?>" alt="<?php echo sanitize($item['title']); ?>" style="max-width:90px; max-height:70px; border-radius:6px;">
                        <?php else: ?>
                            <i class="fas fa-clapperboard"></i>
                        <?php endif; ?>
                    </div>
                    <div class="channel-info">
                        <h3 class="channel-title"><?php echo sanitize($item['title']); ?> <span class="badge">Series</span></h3>
                        <div style="display:flex; gap:6px; margin-top:8px;">
                            <a class="button-pill" style="padding:8px 12px; font-size:0.9rem;" href="/series.php?id=<?php echo $item['id']; ?>">Open</a>
                            <button class="button-pill button-secondary remove-list" data-type="series" data-id="<?php echo $item['id']; ?>" style="padding:8px 12px; font-size:0.9rem;">Remove</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (empty($movies) && empty($series)): ?>
            <div class="glass-card" style="text-align:center; margin-top:20px;">
                <p style="color:var(--muted);">Your list is empty. Add movies or series to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
<script src="/assets/js/main.js?v=<?php echo $asset_version; ?>"></script>
<script src="/assets/js/my-list.js?v=<?php echo $asset_version; ?>"></script>
<script>
document.querySelectorAll('.remove-list').forEach(btn => {
    btn.addEventListener('click', async () => {
        const content_type = btn.dataset.type;
        const content_id = btn.dataset.id;
        await fetch('/api/my_list.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`action=remove&content_type=${content_type}&content_id=${content_id}`});
        location.reload();
    });
});
</script>
</body>
</html>
