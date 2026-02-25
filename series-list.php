<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
$asset_version = time();

$genreFilters = $_GET['genre'] ?? [];
if (!is_array($genreFilters)) $genreFilters = [$genreFilters];
$yearMin = isset($_GET['year_min']) ? (int) $_GET['year_min'] : null;
$yearMax = isset($_GET['year_max']) ? (int) $_GET['year_max'] : null;
$ratingMin = isset($_GET['rating_min']) ? (float) $_GET['rating_min'] : null;
$sort = $_GET['sort'] ?? 'recent';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 24;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
if ($genreFilters) {
    $or = [];
    foreach ($genreFilters as $g) {
        $or[] = "genre LIKE ?";
        $params[] = '%' . $g . '%';
    }
    $where[] = '(' . implode(' OR ', $or) . ')';
}
if ($yearMin) { $where[] = "year >= ?"; $params[] = $yearMin; }
if ($yearMax) { $where[] = "year <= ?"; $params[] = $yearMax; }
if ($ratingMin) { $where[] = "rating >= ?"; $params[] = $ratingMin; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

switch ($sort) {
    case 'popular': $order = 'popularity DESC, created_at DESC'; break;
    case 'az': $order = 'title ASC'; break;
    default: $order = 'created_at DESC';
}

$stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM series $whereSql ORDER BY $order LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$series = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$pages = max(1, ceil($total / $limit));

$genresAll = $pdo->query("SELECT DISTINCT genre FROM series WHERE genre IS NOT NULL AND genre <> '' ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);

// Temporary debug print to verify data on live
$dbgDb  = $pdo->query("SELECT DATABASE()")->fetchColumn();
$dbgCnt = (int)$pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();
echo "<pre style=\"padding:10px; background:#111; color:#0f0;\">DEBUG: DB={$dbgDb} series_count={$dbgCnt}</pre>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Series - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/pages/partials/header.php'; ?>
<main class="page-shell" style="display:grid; grid-template-columns: 260px 1fr; gap:18px;">
    <aside class="glass-card" style="position:sticky; top:90px; align-self:start;">
        <h3 style="margin-top:0;">Filters</h3>
        <form method="GET">
            <div style="margin-bottom:12px;">
                <label>Genres</label>
                <div style="max-height:160px; overflow:auto; border:1px solid var(--border); padding:8px; border-radius:10px;">
                    <?php foreach ($genresAll as $g): ?>
                        <label style="display:block; color:var(--muted);"><input type="checkbox" name="genre[]" value="<?php echo sanitize($g); ?>" <?php echo in_array($g, $genreFilters) ? 'checked' : ''; ?>> <?php echo sanitize($g); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display:flex; gap:8px; margin-bottom:12px;">
                <div style="flex:1;">
                    <label>Year min</label>
                    <input type="number" name="year_min" value="<?php echo $yearMin ?: ''; ?>" style="width:100%; padding:8px; border-radius:8px; border:1px solid var(--border);">
                </div>
                <div style="flex:1;">
                    <label>Year max</label>
                    <input type="number" name="year_max" value="<?php echo $yearMax ?: ''; ?>" style="width:100%; padding:8px; border-radius:8px; border:1px solid var(--border);">
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label>Rating ≥</label>
                <input type="number" step="0.1" name="rating_min" value="<?php echo $ratingMin ?: ''; ?>" style="width:100%; padding:8px; border-radius:8px; border:1px solid var(--border);">
            </div>
            <div style="margin-bottom:12px;">
                <label>Sort</label>
                <select name="sort" style="width:100%; padding:8px; border-radius:8px; border:1px solid var(--border);">
                    <option value="recent" <?php echo $sort==='recent'?'selected':''; ?>>Most Recent</option>
                    <option value="popular" <?php echo $sort==='popular'?'selected':''; ?>>Most Popular</option>
                    <option value="az" <?php echo $sort==='az'?'selected':''; ?>>A–Z</option>
                </select>
            </div>
            <button class="button-pill" type="submit" style="width:100%;">Apply</button>
        </form>
    </aside>

    <section>
        <div class="section-title" style="justify-content: space-between; align-items:center;">
            <span>Series</span>
            <span class="badge"><?php echo $total; ?> results</span>
        </div>
        <div class="card-grid">
            <?php foreach ($series as $s): ?>
                <a class="channel-card" href="/series.php?id=<?php echo $s['id']; ?>">
                    <div class="channel-logo">
                        <?php if (!empty($s['poster_url'])): ?>
                            <img src="<?php echo sanitize($s['poster_url']); ?>" alt="<?php echo sanitize($s['title']); ?>" style="max-width:90px; max-height:70px; border-radius:6px;">
                        <?php else: ?>
                            <i class="fas fa-clapperboard"></i>
                        <?php endif; ?>
                    </div>
                    <div class="channel-info">
                        <h3 class="channel-title"><?php echo sanitize($s['title']); ?></h3>
                        <p class="channel-category" style="color:var(--muted);"><?php echo (int)$s['year']; ?> • <?php echo sanitize($s['genre']); ?></p>
                        <?php if ($s['rating']): ?><span class="badge">Rating <?php echo $s['rating']; ?></span><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:16px; display:flex; gap:8px;">
            <?php if ($page > 1): ?>
                <a class="button-pill button-secondary" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>">Prev</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a class="button-pill button-secondary" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">Next</a>
            <?php endif; ?>
        </div>
    </section>
</main>
<script src="/assets/js/main.js?v=<?php echo $asset_version; ?>"></script>
<script src="/assets/js/search.js?v=<?php echo $asset_version; ?>"></script>
</body>
</html>
