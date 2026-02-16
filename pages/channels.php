<?php
include '../includes/config.php';
include '../includes/functions.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

// Get site settings
$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');

// Get all active channels - ensure we're selecting the name field
$channels = $pdo->query("SELECT id, name, logo_url, category, stream_url, drm_type, license_key, license_url, views, is_active FROM channels WHERE is_active = true ORDER BY name")->fetchAll();

// Get unique categories
$categories = $pdo->query("SELECT DISTINCT category FROM channels WHERE is_active = true ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get ads
$header_ads = getAds($pdo, 'header');
$body_ads = getAds($pdo, 'body');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Channels - <?php echo $site_name; ?></title>
    <meta name="description" content="Browse all available TV channels on <?php echo $site_name; ?> - Sports, Movies, News, Entertainment and more.">
    
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
                    <li class="nav-item"><a href="/pages/channels.php" class="nav-link active">Channels</a></li>
                    <li class="nav-item"><a href="/pages/categories.php" class="nav-link">Categories</a></li>
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
                <span>All Channels (<?php echo count($channels); ?>)</span>
            </div>
            <div class="filter-bar">
                <input type="text" id="searchInput" placeholder="Search channels...">
                <select id="categoryFilter" onchange="filterChannels()">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo sanitize($category); ?>"><?php echo sanitize($category); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="drmFilter" onchange="filterChannels()">
                    <option value="all">All DRM Types</option>
                    <option value="drm">DRM Protected</option>
                    <option value="clearkey">ClearKey</option>
                    <option value="none">No DRM</option>
                </select>
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
            <div class="card-grid" id="channelsContainer">
                <?php foreach ($channels as $channel): ?>
                <div class="channel-card" 
                     data-category="<?php echo sanitize($channel['category']); ?>"
                     data-drm="<?php echo $channel['drm_type'] ?: 'none'; ?>">
                    
                    <div class="channel-logo">
                        <?php if ($channel['logo_url']): ?>
                            <img src="<?php echo sanitize($channel['logo_url']); ?>" 
                                 alt="<?php echo sanitize($channel['name']); ?>" 
                                 style="max-width: 90px; max-height: 70px; border-radius: 6px;">
                        <?php else: ?>
                            <i class="fas fa-tv"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="channel-info">
                        <h3 class="channel-title"><?php echo sanitize($channel['name']); ?></h3>
                        <p class="channel-category"><?php echo sanitize($channel['category']); ?></p>
                        
                        <?php if ($channel['drm_type']): ?>
                            <span class="tag danger"><i class="fas fa-shield-alt"></i> <?php echo strtoupper($channel['drm_type']); ?></span>
                        <?php else: ?>
                            <span class="tag success"><i class="fas fa-lock-open"></i> No DRM</span>
                        <?php endif; ?>
                        
                        <div class="channel-stats">
                            <span><i class="fas fa-eye"></i> <?php echo number_format($channel['views']); ?> views</span>
                            <button onclick="toggleFavorite(<?php echo $channel['id']; ?>)" 
                                    class="favorite-btn" data-channel-id="<?php echo $channel['id']; ?>" 
                                    style="background: none; border: none; color: #666; cursor: pointer;">
                                <i class="far fa-heart"></i>
                            </button>
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
                    
            <?php if (empty($channels)): ?>
                <div class="glass-card" style="text-align: center;">
                    <i class="fas fa-tv" style="font-size: 3rem; margin-bottom: 10px;"></i>
                    <h3>No channels available</h3>
                    <p style="color: var(--muted);">Check back later for updated channel listings.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        // Initialize search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const channels = document.querySelectorAll('.channel-card');
            
            channels.forEach(channel => {
                const channelName = channel.querySelector('.channel-title').textContent.toLowerCase();
                if (channelName.includes(searchTerm)) {
                    channel.style.display = 'block';
                } else {
                    channel.style.display = 'none';
                }
            });
        });
        
        // Initialize filters
        filterChannels();
    </script>
</body>
</html>
