<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live TV - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/epg.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/pages/partials/header.php'; ?>
<main class="page-shell">
    <div class="section-title" style="justify-content: space-between; align-items:center;">
        <span>Live TV Guide</span>
        <div>
            <button class="button-pill button-secondary" data-tab="all" id="tabAll">All</button>
            <button class="button-pill button-secondary" data-tab="fav" id="tabFav">Favorites</button>
        </div>
    </div>
    <div id="epgGrid" class="epg-grid epg-guide"></div>
</main>
<script src="/assets/js/epg.js"></script>
<script src="/assets/js/search.js"></script>
</body>
</html>
