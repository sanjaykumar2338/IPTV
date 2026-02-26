<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
$asset_version = time();

// Fetch list - Movies
$listMovies = $pdo->prepare("SELECT m.id, m.title, m.poster_url FROM my_list ml JOIN movies m ON ml.content_id = m.id WHERE ml.customer_id = ? AND ml.content_type = 'movie'");
$listMovies->execute([$_SESSION['customer_id']]);
$movies = $listMovies->fetchAll(PDO::FETCH_ASSOC);

// Fetch list - Series
$listSeries = $pdo->prepare("SELECT s.id, s.title, s.poster_url FROM my_list ml JOIN series s ON ml.content_id = s.id WHERE ml.customer_id = ? AND ml.content_type = 'series'");
$listSeries->execute([$_SESSION['customer_id']]);
$series = $listSeries->fetchAll(PDO::FETCH_ASSOC);

$isEmpty = empty($movies) && empty($series);
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
    <style>
        .page-shell {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Consistent Grid System */
        .list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
        }

        /* Card Styling */
        .content-card {
            background: var(--glass-card-bg, rgba(255, 255, 255, 0.05));
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease;
        }

        .content-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
        }

        .poster-box {
            aspect-ratio: 2/3;
            position: relative;
            background: #1a1a1a;
        }

        .poster-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .type-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: bold;
        }

        .content-details {
            padding: 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .content-title {
            font-size: 0.95rem;
            margin: 0 0 10px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .action-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255,255,255,0.03);
            border-radius: 15px;
            border: 1px dashed rgba(255,255,255,0.1);
        }

        /* Responsive Breakpoints */
        @media (max-width: 480px) {
            .list-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
            }
            .action-btns {
                grid-template-columns: 1fr; /* Stack buttons on very small screens */
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/pages/partials/header.php'; ?>

<main class="page-shell">
    <div class="section-title" style="margin-bottom: 25px;">
        <h1 style="margin:0; font-size: 1.6rem;"><i class="fas fa-bookmark" style="color:var(--primary-color);"></i> My List</h1>
    </div>

    <?php if ($isEmpty): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open fa-4x" style="color: rgba(255,255,255,0.1); margin-bottom: 15px;"></i>
            <h2 style="margin:0; color: #fff;">Your list is empty</h2>
            <p style="color: var(--muted); margin-top: 10px;">Browse movies and series to add them to your collection.</p>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
                <a href="/movies.php" class="button-pill">Browse Movies</a>
                <a href="/series-list.php" class="button-pill button-secondary">Browse Series</a>
            </div>
        </div>
    <?php else: ?>
        <div class="list-grid">
            <?php 
            $all_items = array_merge(
                array_map(fn($m) => array_merge($m, ['type' => 'movie']), $movies),
                array_map(fn($s) => array_merge($s, ['type' => 'series']), $series)
            );
            
            foreach ($all_items as $item): 
                $link = $item['type'] === 'movie' ? "/movie.php?id=" . $item['id'] : "/series.php?id=" . $item['id'];
            ?>
                <div class="content-card">
                    <div class="poster-box">
                        <span class="type-badge" style="color: <?php echo $item['type'] === 'movie' ? '#00d1b2' : '#3273dc'; ?>;">
                            <?php echo $item['type']; ?>
                        </span>
                        <?php if (!empty($item['poster_url'])): ?>
                            <img src="<?php echo sanitize($item['poster_url']); ?>" alt="<?php echo sanitize($item['title']); ?>" loading="lazy">
                        <?php else: ?>
                            <div style="height:100%; display:flex; align-items:center; justify-content:center; background:#222;">
                                <i class="fas <?php echo $item['type'] === 'movie' ? 'fa-film' : 'fa-clapperboard'; ?> fa-2x"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="content-details">
                        <h3 class="content-title" title="<?php echo sanitize($item['title']); ?>">
                            <?php echo sanitize($item['title']); ?>
                        </h3>
                        <div class="action-btns">
                            <a class="button-pill" style="text-align:center; padding: 6px 0;" href="<?php echo $link; ?>">Open</a>
                            <button class="button-pill button-secondary remove-list" 
                                    data-type="<?php echo $item['type']; ?>" 
                                    data-id="<?php echo $item['id']; ?>" 
                                    style="padding: 6px 0;">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script src="/assets/js/main.js?v=<?php echo $asset_version; ?>"></script>
<script>
document.querySelectorAll('.remove-list').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const content_type = btn.dataset.type;
        const content_id = btn.dataset.id;
        
        // Visual feedback immediately
        const card = btn.closest('.content-card');
        card.style.opacity = '0.5';
        card.style.pointerEvents = 'none';

        try {
            const response = await fetch('/api/my_list.php', {
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: `action=remove&content_type=${content_type}&content_id=${content_id}`
            });
            
            if(response.ok) {
                card.style.transform = 'scale(0.8)';
                setTimeout(() => location.reload(), 200);
            }
        } catch (err) {
            card.style.opacity = '1';
            card.style.pointerEvents = 'all';
            alert('Failed to remove item.');
        }
    });
});
</script>
</body>
</html>