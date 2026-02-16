<?php
ini_set('log_errors', 'On');
ini_set('error_log', '/dev/null');
include '../includes/config.php';
include '../includes/functions.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> - Home</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=20250216">
    <link rel="stylesheet" href="../assets/css/app.css?v=20250216">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>

    <div class="layout page-shell">
        <aside class="sidebar glass-card">
            <h3 style="margin-top:0;">Browse</h3>
            <ul id="categoriesList" class="category-list"></ul>
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

    <script src="../assets/js/main.js?v=20250216"></script>
    <script src="../assets/js/home.js?v=20250216"></script>
</body>
</html>
