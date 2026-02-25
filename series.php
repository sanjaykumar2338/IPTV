<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$id = (int) ($_GET['id'] ?? 0);
$seriesStmt = $pdo->prepare("SELECT * FROM series WHERE id = ? LIMIT 1");
$seriesStmt->execute([$id]);
$series = $seriesStmt->fetch(PDO::FETCH_ASSOC);

if (!$series) {
    http_response_code(404);
    echo "Series not found";
    exit;
}

// Seasons
$seasonStmt = $pdo->prepare("SELECT DISTINCT season_number FROM episodes WHERE series_id = ? ORDER BY season_number");
$seasonStmt->execute([$series['id']]);
$seasons = $seasonStmt->fetchAll(PDO::FETCH_COLUMN);
$seasonCount = count($seasons);
$currentSeason = (int) ($_GET['season'] ?? ($seasons[0] ?? 1));

$episodesStmt = $pdo->prepare("SELECT * FROM episodes WHERE series_id = ? AND season_number = ? ORDER BY episode_number");
$episodesStmt->execute([$series['id'], $currentSeason]);
$episodes = $episodesStmt->fetchAll(PDO::FETCH_ASSOC);

// Continue watching: last episode & position
$resumeEpisodeId = null;
$resumePos = 0;
$histStmt = $pdo->prepare("SELECT episode_id, last_position_seconds FROM watch_history WHERE customer_id = ? AND content_type='series' AND content_id = ? ORDER BY updated_at DESC LIMIT 1");
$histStmt->execute([$_SESSION['customer_id'], $series['id']]);
if ($row = $histStmt->fetch(PDO::FETCH_ASSOC)) {
    $resumeEpisodeId = $row['episode_id'];
    $resumePos = (int) $row['last_position_seconds'];
}

$inListStmt = $pdo->prepare("SELECT 1 FROM my_list WHERE customer_id = ? AND content_type='series' AND content_id = ? LIMIT 1");
$inListStmt->execute([$_SESSION['customer_id'], $series['id']]);
$inList = (bool)$inListStmt->fetchColumn();

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
$asset_version = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($series['title']); ?> - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/pages/partials/header.php'; ?>
<main class="page-shell">
    <?php $hasBanner = !empty($series['banner_url']); ?>
    <div class="glass-card" style="overflow:hidden;">
        <?php if ($hasBanner): ?>
            <div style="background:url('<?php echo sanitize($series['banner_url']); ?>') center/cover; height:220px; border-radius:12px; filter:brightness(0.7);"></div>
        <?php endif; ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:18px; margin-top:<?php echo $hasBanner ? '-140px' : '0'; ?>; align-items:start;">
            <div style="max-width:320px;">
                <img src="<?php echo sanitize($series['poster_url']); ?>" alt="<?php echo sanitize($series['title']); ?>" style="width:100%; border-radius:16px; box-shadow:var(--shadow);">
            </div>
            <div>
                <div class="badge">Series</div>
                <h1 style="margin:8px 0 6px; font-size:2.2rem;"><?php echo sanitize($series['title']); ?></h1>
                <?php
                    $year    = $series['year'] ?: '—';
                    $genre   = $series['genre'] ? sanitize($series['genre']) : 'Unspecified';
                    $seasonsLabel = $seasonCount ?: ($series['seasons'] ?: '—');
                    $rating  = $series['rating'] ?: '—';
                ?>
                <p style="color:var(--muted); margin:6px 0 14px;">Year: <?php echo $year; ?> • Genre: <?php echo $genre; ?> • Seasons: <?php echo $seasonsLabel; ?> • Rating: <?php echo $rating; ?></p>
                <p style="line-height:1.6; margin-bottom:16px; color:var(--text); opacity:0.92;"><?php echo nl2br(sanitize($series['synopsis'])); ?></p>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <?php if (!empty($series['trailer_url'])): ?>
                        <a class="button-pill button-secondary" href="<?php echo sanitize($series['trailer_url']); ?>" target="_blank"><i class="fas fa-film"></i> Trailer</a>
                    <?php endif; ?>
                    <button class="button-pill button-secondary" id="toggleMyList" data-type="series" data-id="<?php echo $series['id']; ?>" data-inlist="<?php echo $inList ? '1' : '0'; ?>">
                        <i class="fas <?php echo $inList ? 'fa-check' : 'fa-plus'; ?>"></i> <?php echo $inList ? 'In My List' : 'My List'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="section" style="margin-top:20px;">
        <div class="section-title">
            <span>Episodes</span>
            <div>
                <select onchange="location.href='series.php?id=<?php echo $series['id']; ?>&season=' + this.value" style="padding:10px; border-radius:10px;">
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?php echo $season; ?>" <?php echo (int)$season === $currentSeason ? 'selected' : ''; ?>>Season <?php echo $season; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-grid">
            <?php foreach ($episodes as $ep): ?>
            <div class="channel-card" style="cursor:pointer;" onclick="window.location='/player.php?stream=<?php echo urlencode($ep['stream_url']); ?>&name=<?php echo urlencode($series['title'].' S'.str_pad($ep['season_number'],2,'0',STR_PAD_LEFT).'E'.str_pad($ep['episode_number'],2,'0',STR_PAD_LEFT)); ?>&content_type=series&content_id=<?php echo $series['id']; ?>&episode_id=<?php echo $ep['id']; ?>'">
            <div class="channel-logo">
                <?php if (!empty($ep['thumbnail_url'])): ?>
                    <img src="<?php echo sanitize($ep['thumbnail_url']); ?>" alt="<?php echo sanitize($ep['title']); ?>" style="max-width: 90px; max-height: 70px; border-radius: 6px;">
                <?php else: ?>
                    <i class="fas fa-clapperboard"></i>
                <?php endif; ?>
            </div>
            <div class="channel-info">
                <h3 class="channel-title">S<?php echo str_pad($ep['season_number'],2,'0',STR_PAD_LEFT); ?>E<?php echo str_pad($ep['episode_number'],2,'0',STR_PAD_LEFT); ?> · <?php echo sanitize($ep['title']); ?></h3>
                <p class="channel-category" style="color:var(--muted);">Duration: <?php echo (int)$ep['duration_minutes']; ?>m</p>
                <?php if ($resumeEpisodeId && $resumeEpisodeId == $ep['id'] && $resumePos > 0): ?>
                    <span class="badge">Resume at <?php echo gmdate('H:i:s', $resumePos); ?></span>
                <?php endif; ?>
            </div>
        </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<script src="/assets/js/main.js?v=<?php echo $asset_version; ?>"></script>
<script src="/assets/js/my-list.js?v=<?php echo $asset_version; ?>"></script>
</body>
</html>
