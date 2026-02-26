<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
$asset_version = time();

// Basic Filters & Logic
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

$stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM movies $whereSql ORDER BY $order LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
$pages = max(1, ceil($total / $limit));

$genresAll = $pdo->query("SELECT DISTINCT genre FROM movies WHERE genre IS NOT NULL AND genre <> '' ORDER BY genre")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Responsive Layout Base */
        .page-container {
            display: grid;
            grid-template-columns: 1fr; /* Mobile: Single column */
            gap: 20px;
            padding: 15px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Sidebar Styles */
        .filter-sidebar {
            background: var(--glass-card-bg, rgba(255, 255, 255, 0.05));
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            height: fit-content;
        }

        /* Grid for Movie Posters */
        .movie-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
        }

        .movie-card {
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s;
            display: flex;
            flex-direction: column;
        }

        .movie-card:hover {
            transform: translateY(-5px);
        }

        .movie-poster {
            aspect-ratio: 2/3;
            background: #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            margin-bottom: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .movie-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .movie-info h3 {
            font-size: 0.9rem;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .movie-info p {
            font-size: 0.8rem;
            color: var(--muted);
            margin: 4px 0;
        }

        /* Desktop Adjustments */
        @media (min-width: 1024px) {
            .page-container {
                grid-template-columns: 260px 1fr; /* Sidebar returns on Desktop */
                padding: 30px;
            }
            .filter-sidebar {
                position: sticky;
                top: 100px;
            }
            .movie-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
        }

        /* Form Styling */
        .filter-group { margin-bottom: 15px; }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.85rem; }
        .input-style {
            width: 100%;
            padding: 10px;
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: white;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/pages/partials/header.php'; ?>

<main class="page-container">
    <aside class="filter-sidebar glass-card">
        <h3 style="margin-top:0;"><i class="fas fa-filter"></i> Filters</h3>
        <form method="GET">
            <div class="filter-group">
                <label>Genres</label>
                <div style="max-height:160px; overflow-y:auto; border:1px solid var(--border); padding:8px; border-radius:10px; background: rgba(0,0,0,0.1);">
                    <?php foreach ($genresAll as $g): ?>
                        <label style="display:block; color:var(--muted); font-size: 0.85rem; margin-bottom: 4px;">
                            <input type="checkbox" name="genre[]" value="<?php echo sanitize($g); ?>" <?php echo in_array($g, $genreFilters) ? 'checked' : ''; ?>> 
                            <?php echo sanitize($g); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="filter-group">
                <label>Year Range</label>
                <div style="display:flex; gap:8px;">
                    <input type="number" name="year_min" placeholder="Min" value="<?php echo $yearMin; ?>" class="input-style">
                    <input type="number" name="year_max" placeholder="Max" value="<?php echo $yearMax; ?>" class="input-style">
                </div>
            </div>

            <div class="filter-group">
                <label>Rating &ge;</label>
                <input type="number" step="0.1" name="rating_min" placeholder="e.g. 7.0" value="<?php echo $ratingMin; ?>" class="input-style">
            </div>

            <div class="filter-group">
                <label>Sort By</label>
                <select name="sort" class="input-style">
                    <option value="recent" <?php echo $sort==='recent'?'selected':''; ?>>Most Recent</option>
                    <option value="popular" <?php echo $sort==='popular'?'selected':''; ?>>Most Popular</option>
                    <option value="az" <?php echo $sort==='az'?'selected':''; ?>>A–Z</option>
                </select>
            </div>

            <button class="button-pill" type="submit" style="width:100%; background: var(--primary-color); border:none; padding:12px; font-weight:bold;">Apply Filters</button>
        </form>
    </aside>

    <section>
        <div class="section-title" style="display:flex; justify-content: space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0;">Movies</h2>
            <span class="badge" style="background: var(--primary-color);"><?php echo $total; ?> Titles</span>
        </div>

        <div class="movie-grid">
            <?php if (empty($movies)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--muted);">
                    <i class="fas fa-search fa-3x" style="margin-bottom: 10px;"></i>
                    <p>No movies found matching your criteria.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($movies as $movie): ?>
                <a class="movie-card" href="/movie.php?id=<?php echo $movie['id']; ?>">
                    <div class="movie-poster">
                        <?php if (!empty($movie['poster_url'])): ?>
                            <img src="<?php echo sanitize($movie['poster_url']); ?>" alt="<?php echo sanitize($movie['title']); ?>" loading="lazy">
                        <?php else: ?>
                            <div style="height: 100%; display:flex; align-items:center; justify-content:center; color: #333;">
                                <i class="fas fa-film fa-2x"></i>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($movie['rating']): ?>
                            <div style="position:absolute; top:8px; right:8px; background: rgba(0,0,0,0.7); color: #ffc107; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">
                                <i class="fas fa-star"></i> <?php echo $movie['rating']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="movie-info">
                        <h3><?php echo sanitize($movie['title']); ?></h3>
                        <p><?php echo (int)$movie['year']; ?> • <?php echo explode(',', sanitize($movie['genre']))[0]; ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
        <div style="margin-top:30px; display:flex; justify-content:center; gap:10px; align-items:center;">
            <?php if ($page > 1): ?>
                <a class="button-pill button-secondary" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            
            <span style="font-size: 0.9rem;">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
            
            <?php if ($page < $pages): ?>
                <a class="button-pill button-secondary" href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
</main>

<script src="/assets/js/main.js?v=<?php echo $asset_version; ?>"></script>
<script src="/assets/js/search.js?v=<?php echo $asset_version; ?>"></script>
</body>
</html>