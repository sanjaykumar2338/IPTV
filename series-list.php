<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
$asset_version = time();

// Filter Logic
$genreFilters = $_GET['genre'] ?? [];
if (!is_array($genreFilters)) $genreFilters = [$genreFilters];
$yearMin = isset($_GET['year_min']) && $_GET['year_min'] !== '' ? (int) $_GET['year_min'] : null;
$yearMax = isset($_GET['year_max']) && $_GET['year_max'] !== '' ? (int) $_GET['year_max'] : null;
$ratingMin = isset($_GET['rating_min']) && $_GET['rating_min'] !== '' ? (float) $_GET['rating_min'] : null;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Series Catalog - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --card-bg: rgba(255, 255, 255, 0.05);
            --input-bg: rgba(0, 0, 0, 0.2);
            --primary: #007bff;
            --text-muted: #a0a0a0;
        }

        /* Responsive Layout Container */
        .series-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Sidebar Styling */
        .filter-sidebar {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 20px;
            height: fit-content;
        }

        .filter-group { margin-bottom: 20px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem; }
        
        .genre-list {
            max-height: 180px;
            overflow-y: auto;
            background: var(--input-bg);
            padding: 10px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .genre-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.85rem;
        }

        /* Grid System */
        .series-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
        }

        /* Media Card */
        .series-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: white;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .series-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            border-color: var(--primary);
        }

        .poster-wrapper {
            aspect-ratio: 2/3;
            position: relative;
            background: #1a1a1a;
            overflow: hidden;
        }

        .poster-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-meta {
            padding: 12px;
            flex-grow: 1;
        }

        .series-title {
            font-size: 0.95rem;
            margin: 0 0 5px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .series-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .rating-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            color: #ffc107;
            backdrop-filter: blur(5px);
        }

        /* Responsive Breakpoints */
        @media (min-width: 768px) {
            .series-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
        }

        @media (min-width: 1024px) {
            .series-container { grid-template-columns: 280px 1fr; }
            .filter-sidebar { position: sticky; top: 100px; }
        }

        @media (max-width: 1023px) {
            .filter-sidebar {
                margin-bottom: 20px;
            }
            /* Optional: make filters a horizontal flex on tablet */
            .filter-form {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .filter-form { grid-template-columns: 1fr; }
            .series-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .series-title { font-size: 0.85rem; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/pages/partials/header.php'; ?>

<main class="series-container">
    <aside class="filter-sidebar">
        <h3 style="margin-top:0; display:flex; align-items:center; gap:10px;">
            <i class="fas fa-sliders-h"></i> Filters
        </h3>
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Genres</label>
                <div class="genre-list">
                    <?php foreach ($genresAll as $g): ?>
                        <label class="genre-item">
                            <input type="checkbox" name="genre[]" value="<?php echo sanitize($g); ?>" 
                                <?php echo in_array($g, $genreFilters) ? 'checked' : ''; ?>>
                            <?php echo sanitize($g); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="filter-group">
                <label>Release Year</label>
                <div style="display:flex; gap:10px;">
                    <input type="number" name="year_min" placeholder="From" value="<?php echo $yearMin; ?>" 
                           style="width:100%; padding:8px; border-radius:6px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:white;">
                    <input type="number" name="year_max" placeholder="To" value="<?php echo $yearMax; ?>" 
                           style="width:100%; padding:8px; border-radius:6px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:white;">
                </div>
            </div>

            <div class="filter-group">
                <label>Minimum Rating</label>
                <input type="number" step="0.1" name="rating_min" placeholder="e.g. 7.5" value="<?php echo $ratingMin; ?>" 
                       style="width:100%; padding:8px; border-radius:6px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:white;">
            </div>

            <div class="filter-group">
                <label>Sort By</label>
                <select name="sort" style="width:100%; padding:8px; border-radius:6px; background:var(--input-bg); border:1px solid rgba(255,255,255,0.1); color:white;">
                    <option value="recent" <?php echo $sort==='recent'?'selected':''; ?>>Recently Added</option>
                    <option value="popular" <?php echo $sort==='popular'?'selected':''; ?>>Popularity</option>
                    <option value="az" <?php echo $sort==='az'?'selected':''; ?>>Name (A-Z)</option>
                </select>
            </div>

            <button type="submit" class="button-pill" style="width:100%; background:var(--primary); color:white; border:none; padding:12px; cursor:pointer; font-weight:bold;">
                Apply Filters
            </button>
        </form>
    </aside>

    <section>
        <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 25px;">
            <h1 style="margin:0; font-size: 1.5rem;">Series Library</h1>
            <span style="background:rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 20px; font-size: 0.8rem;">
                <?php echo number_format($total); ?> Titles
            </span>
        </div>

        <div class="series-grid">
            <?php if (empty($series)): ?>
                <div style="grid-column: 1/-1; text-align:center; padding: 50px; color: var(--text-muted);">
                    <i class="fas fa-search fa-3x" style="margin-bottom:15px; opacity:0.5;"></i>
                    <p>No series found matching your filters.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($series as $s): ?>
                <a class="series-card" href="series_details.php?id=<?php echo $s['id']; ?>">
                    <div class="poster-wrapper">
                        <?php if ($s['rating']): ?>
                            <div class="rating-badge"><i class="fas fa-star"></i> <?php echo $s['rating']; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($s['poster_url'])): ?>
                            <img src="<?php echo sanitize($s['poster_url']); ?>" alt="<?php echo sanitize($s['title']); ?>" loading="lazy">
                        <?php else: ?>
                            <div style="display:flex; height:100%; align-items:center; justify-content:center; color:#444;">
                                <i class="fas fa-clapperboard fa-3x"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-meta">
                        <h3 class="series-title"><?php echo sanitize($s['title']); ?></h3>
                        <div class="series-sub">
                            <?php echo (int)$s['year']; ?> • <?php echo explode(',', sanitize($s['genre']))[0]; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
        <div style="margin-top:40px; display:flex; justify-content: center; gap:10px; align-items:center;">
            <?php if ($page > 1): ?>
                <a class="button-pill button-secondary" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>">
                    <i class="fas fa-arrow-left"></i>
                </a>
            <?php endif; ?>

            <span style="font-size: 0.9rem; color: var(--text-muted);">
                Page <strong><?php echo $page; ?></strong> of <?php echo $pages; ?>
            </span>

            <?php if ($page < $pages): ?>
                <a class="button-pill button-secondary" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">
                    <i class="fas fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
</main>

<script src="/assets/js/main.js?v=<?php echo $asset_version; ?>"></script>
</body>
</html>