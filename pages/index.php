<?php
ini_set('log_errors', 'On');
ini_set('error_log', '/dev/null');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
$asset_version = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> - Home</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="../assets/css/app.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="home-page">
<?php include __DIR__ . '/partials/header.php'; ?>
    <div id="mobileSidebarOverlay" class="sidebar-overlay" hidden></div>

    <div class="layout page-shell">
        <aside id="mobileSidebar" class="sidebar glass-card">
            <button class="sidebar-close" id="sidebarClose" aria-label="Close menu">
                <i class="fa-solid fa-xmark"></i> Close
            </button>
            <h3 style="margin-top:0; padding-left: 10px;">Browse</h3>
            <ul id="categoriesList" class="category-list">
                <li><a href="#genre-action" class="nav-link">Action</a></li>
                <li><a href="#genre-drama" class="nav-link">Drama</a></li>
                <li><a href="#genre-comedy" class="nav-link">Comedy</a></li>
                <li><a href="#genre-thriller" class="nav-link">Thriller</a></li>
                <li><a href="#genre-kids" class="nav-link">Kids</a></li>
                <li><a href="#genre-documentary" class="nav-link">Documentary</a></li>
            </ul>
        </aside>

        <main class="content">
            <section class="section">
                <div id="featuredCarousel" class="carousel"></div>
            </section>

            <section class="section">
                <div class="section-title"><span>Continue Watching</span></div>
                <div id="continueRow"></div>
            </section>

            <section class="section">
                <div class="section-title"><span>My List</span></div>
                <div id="myListRow"></div>
            </section>

            <section class="section" id="genreRows"></section>
        </main>
    </div>

    <script src="../assets/js/main.js?v=<?php echo $asset_version; ?>"></script>
    <script src="../assets/js/home.js?v=<?php echo $asset_version; ?>"></script>
</body>
</html>
