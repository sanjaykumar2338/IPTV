<?php
include '../includes/config.php';
include '../includes/functions.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

// Get site settings
$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');

// Get category from URL
$selected_category = $_GET['category'] ?? '';

// Get all categories with channel counts
$categories_query = $pdo->query("SELECT category, COUNT(*) as channel_count FROM channels WHERE is_active = true GROUP BY category ORDER BY channel_count DESC");
$categories = $categories_query->fetchAll();

// Get channels for selected category or all channels
if ($selected_category) {
    $channels_query = $pdo->prepare("SELECT * FROM channels WHERE is_active = true AND category = ? ORDER BY name");
    $channels_query->execute([$selected_category]);
    $channels = $channels_query->fetchAll();
    $page_title = $selected_category . " Channels";
} else {
    $channels = $pdo->query("SELECT * FROM channels WHERE is_active = true ORDER BY category, name")->fetchAll();
    $page_title = "All Categories";
}

// Group channels by category for the category view
$channels_by_category = [];
foreach ($channels as $channel) {
    $category = $channel['category'];
    if (!isset($channels_by_category[$category])) {
        $channels_by_category[$category] = [];
    }
    $channels_by_category[$category][] = $channel;
}

// Get ads
$header_ads = getAds($pdo, 'header');
$body_ads = getAds($pdo, 'body');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo $site_name; ?></title>
    <meta name="description" content="Browse TV channels by category on <?php echo $site_name; ?> - Sports, Movies, News, Entertainment and more.">
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="index.php">
                        <i class="fas fa-tv"></i>
                        <?php echo $site_name; ?>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="/index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="/pages/channels.php" class="nav-link">Channels</a></li>
                    <li class="nav-item"><a href="/pages/categories.php" class="nav-link active">Categories</a></li>
                </ul>
                <div class="nav-toggle">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>

    <!-- Header Ads -->
    <?php if (!empty($header_ads)): ?>
    <div class="ad-container header-ad">
        <?php foreach ($header_ads as $ad): ?>
            <?php if ($ad['ad_type'] === 'google'): ?>
                <?php echo $ad['ad_code']; ?>
            <?php else: ?>
                <a href="<?php echo $ad['link_url']; ?>" target="_blank">
                    <img src="<?php echo $ad['image_url']; ?>" alt="Advertisement" style="max-width: 100%; height: auto;">
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <main class="page-shell">
        <section class="section">
            <div class="section-title">
                <span><?php echo $selected_category ? $selected_category . ' Channels' : 'All Categories'; ?></span>
                <span class="badge"><?php echo count($channels); ?> channels</span>
            </div>
            <div class="card-grid" style="margin-bottom:20px;">
                <div class="category-card">
                    <h3 style="margin-top:0;">Browse by category</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">
                        <a href="categories.php" class="button-pill button-secondary">All Categories</a>
                        <?php foreach ($categories as $category): ?>
                            <a href="categories.php?category=<?php echo urlencode($category['category']); ?>" class="button-pill button-secondary" style="padding:8px 12px;">
                                <?php echo sanitize($category['category']); ?>
                                <span class="badge" style="margin-left:6px; padding:4px 8px;"><?php echo $category['channel_count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php if (!empty($body_ads)): ?>
        <section class="section">
            <div class="glass-card">
                <?php foreach ($body_ads as $ad): ?>
                    <?php if ($ad['ad_type'] === 'google'): ?>
                        <?php echo $ad['ad_code']; ?>
                    <?php else: ?>
                        <a href="<?php echo $ad['link_url']; ?>" target="_blank">
                            <img src="<?php echo $ad['image_url']; ?>" alt="Advertisement" style="max-width: 100%; height: auto;">
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="section">
            <?php if ($selected_category): ?>
                <div class="card-grid">
                    <?php foreach ($channels as $channel): ?>
                    <div class="channel-card" data-category="<?php echo sanitize($channel['category']); ?>">
                        <div class="channel-logo">
                            <?php if ($channel['logo_url']): ?>
                                <img src="<?php echo sanitize($channel['logo_url']); ?>" alt="<?php echo sanitize($channel['name']); ?>" 
                                     style="max-width: 90px; max-height: 70px; border-radius: 6px;">
                            <?php else: ?>
                                <i class="fas fa-tv"></i>
                            <?php endif; ?>
                        </div>
                        <div class="channel-info">
                            <h3 class="channel-title"><?php echo sanitize($channel['name']); ?></h3>
                            <p class="channel-category"><?php echo sanitize($channel['category']); ?></p>
                            <?php if ($channel['drm_type']): ?>
                                <span class="tag danger"><i class="fas fa-shield-alt"></i> <?php echo strtoupper($channel['drm_type']); ?> Protected</span>
                            <?php else: ?>
                                <span class="tag success"><i class="fas fa-lock-open"></i> No DRM</span>
                            <?php endif; ?>
                            <div class="channel-stats">
                                <span><i class="fas fa-eye"></i> <?php echo number_format($channel['views']); ?> views</span>
                            </div>
                            <button onclick="playChannel(
                                '<?php echo sanitize($channel['stream_url']); ?>', 
                                '<?php echo sanitize($channel['name']); ?>',
                                '<?php echo sanitize($channel['drm_type']); ?>',
                                '<?php echo sanitize($channel['license_key']); ?>',
                                '<?php echo sanitize($channel['license_url']); ?>'
                            )" class="button-pill" style="padding: 10px 16px; font-size: 0.9rem; margin-top: 10px;">
                                <i class="fas fa-play"></i> Watch Now
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($channels_by_category as $category => $category_channels): ?>
                    <div class="category-card">
                        <h3 style="margin:0;"><?php echo sanitize($category); ?></h3>
                        <p style="color: var(--muted); margin:6px 0 12px;"><?php echo count($category_channels); ?> channels</p>
                        <a href="categories.php?category=<?php echo urlencode($category); ?>" 
                           class="button-pill button-secondary" style="padding: 8px 12px;">
                            View Channels
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (empty($channels)): ?>
            <div class="glass-card" style="text-align: center; margin-top:20px;">
                <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 10px;"></i>
                <h3>No channels found</h3>
                <p style="color: var(--muted);"><?php echo $selected_category ? 'No channels available in this category.' : 'No channels available.'; ?></p>
                <a href="channels.php" class="button-pill" style="margin-top: 12px;">
                    <i class="fas fa-broadcast-tower"></i> Browse All Channels
                </a>
            </div>
        <?php endif; ?>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>
