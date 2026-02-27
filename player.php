<?php
include 'includes/config.php';
include 'includes/functions.php';
include 'includes/proxy.php'; // Add proxy support
require_once __DIR__ . '/includes/auth/check_auth.php';

// Get stream parameters
$stream_url = $_GET['stream'] ?? '';
$channel_name = $_GET['name'] ?? 'Unknown Channel';
$drm_type = $_GET['drm_type'] ?? '';
$license_key = $_GET['license_key'] ?? '';
$license_url = $_GET['license_url'] ?? '';

if (empty($stream_url)) {
    header('Location: channels.php');
    exit;
}

// Update view count
$stmt = $pdo->prepare("UPDATE channels SET views = views + 1 WHERE stream_url = ?");
$stmt->execute([$stream_url]);

// Get channel category for ad targeting
$channel_stmt = $pdo->prepare("SELECT category FROM channels WHERE stream_url = ?");
$channel_stmt->execute([$stream_url]);
$channel_category = $channel_stmt->fetchColumn();

// Get site settings
$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');

// Get ads
$header_ads = getAds($pdo, 'header');
$body_ads = getAds($pdo, 'body');

// Get ClearKey configuration
$clearkey_config = null;
if ($drm_type === 'clearkey' && !empty($license_key)) {
    $clearkey_config = getClearKeyConfig($license_key);
}

// Proxy non-HTTPS streams
function getProxiedStreamUrl($stream_url) {
    $parsed = parse_url($stream_url);
    
    // Always use proxy for HTTP URLs
    if (($parsed['scheme'] ?? '') === 'http') {
        return "/proxy.php?url=" . urlencode($stream_url);
    }
    
    return $stream_url;
}

$proxied_stream_url = getProxiedStreamUrl($stream_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($channel_name); ?> - <?php echo $site_name; ?></title>
    <meta name="description" content="Watch <?php echo sanitize($channel_name); ?> live on <?php echo $site_name; ?>">
    
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/app.css">
<link rel="stylesheet" href="../assets/css/player.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Shaka Player CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.7.5/controls.css">
    
    <!-- Video Ads CSS -->
    <style>
        .video-ad-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #000;
            z-index: 1000;
            display: none;
        }
        
        #ad-video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .ad-controls {
            position: absolute;
            bottom: 20px;
            left: 20px;
            color: white;
            background: rgba(0,0,0,0.7);
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        #skip-ad {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 15px;
            cursor: pointer;
            margin-left: 10px;
            display: none;
        }
        
        #skip-ad:hover {
            background: #c0392b;
        }
        
        .ad-overlay {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            background: rgba(0,0,0,0.7);
            padding: 5px 10px;
            border-radius: 10px;
            font-size: 12px;
        }
        
        .ad-clickable {
            cursor: pointer;
        }
        
        .ad-clickable:hover::after {
            content: ' - Click to visit advertiser';
            font-size: 12px;
            opacity: 0.8;
        }
    </style>
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

    <main class="main-content">
        <div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
            <h2 style="text-align: center; margin-bottom: 20px;"><?php echo sanitize($channel_name); ?></h2>
            
            <!-- Player Container -->
            <div class="player-container">
                <div id="video-container">
                    <video id="video-player"
                           poster="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1200' height='675' viewBox='0 0 1200 675'%3E%3Crect fill='%232c3e50' width='1200' height='675'/%3E%3Ctext fill='%23ffffff' font-family='Arial' font-size='24' x='50%25' y='50%25' text-anchor='middle' dominant-baseline='middle'%3E<?php echo urlencode($channel_name); ?>%3C/text%3E%3C/svg%3E"
                           controls autoplay>
                    </video>
                </div>
            </div>

            <!-- Body Ads -->
            <?php if (!empty($body_ads)): ?>
            <div class="ad-container body-ad">
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
            <?php endif; ?>

            <!-- Stream Information -->
            <div class="player-info">
                <h3><i class="fas fa-info-circle"></i> Stream Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Channel Name</span>
                        <span class="info-value"><?php echo sanitize($channel_name); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Category</span>
                        <span class="info-value"><?php echo sanitize($channel_category); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">DRM Protection</span>
                        <span class="info-value">
                            <?php if ($drm_type): ?>
                                <span style="color: #e74c3c;"><?php echo strtoupper($drm_type); ?></span>
                            <?php else: ?>
                                <span style="color: #2ecc71;">None</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Stream Type</span>
                        <span class="info-value">
                            <?php if (strpos($stream_url, '.m3u8') !== false): ?>
                                HLS Stream
                            <?php elseif (strpos($stream_url, '.mpd') !== false): ?>
                                MPEG-DASH
                            <?php else: ?>
                                Direct Stream
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Proxy</span>
                        <span class="info-value">
                            <?php if ($stream_url !== $proxied_stream_url): ?>
                                <span style="color: #3498db;">Enabled</span>
                            <?php else: ?>
                                <span style="color: #95a5a6;">Disabled</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="info-value" id="player-status">Loading...</span>
                    </div>
                </div>
            </div>

    </main>

    <!-- Shaka Player -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.7.5/shaka-player.compiled.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.7.5/shaka-player.ui.js"></script>
    
    <!-- Main JavaScript -->
    <script src="assets/js/shaka-player.js"></script>
    
    <!-- Video Ads JavaScript -->
    <script src="assets/js/video-ads.js"></script>

    <script src="assets/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const drmConfig = {};

            <?php if ($clearkey_config): ?>
            drmConfig.clearkey = {
                '<?php echo $clearkey_config['key_id']; ?>': '<?php echo $clearkey_config['key']; ?>'
            };
            <?php endif; ?>

            window.iptvPlayer = new IPTVPlayer('video-player', {
                debug: true,
                loadTimeoutMs: 45000,
                autoProxyFallback: true,
                streaming: {
                    bufferingGoal: 60,
                    rebufferingGoal: 8,
                    bufferBehind: 30
                },
                drm: {
                    widevine: {
                        licenseServer: '<?php echo $license_url ?: "https://widevine-proxy.appspot.com/proxy"; ?>'
                    },
                    playready: {
                        licenseServer: '<?php echo $license_url ?: "https://playready.directtaps.net/pr/svc/rightsmanager.asmx"; ?>'
                    }
                }
            });

            window.iptvPlayer.loadStream('<?php echo $proxied_stream_url; ?>', drmConfig);

            window.videoAdManager = new VideoAdManager(window.iptvPlayer.videoElement, {
                category: '<?php echo $channel_category; ?>'
            });
        });
    </script>
<script>
    (function(){
        const params = new URLSearchParams(window.location.search);
        const contentType = params.get('content_type');
        const contentId = params.get('content_id');
        const episodeId = params.get('episode_id');
        const attachListeners = () => {
            const video = document.getElementById('video-player');
            if (!video || !contentType || !contentId) return;
            let lastSent = 0;
            const sendProgress = (ended=false) => {
                if (!window.iptvPlayer || !video) return;
                const now = Math.floor(video.currentTime);
                if (!ended && now - lastSent < 15) return;
                lastSent = now;
                const body = new URLSearchParams({
                    content_type: contentType,
                    content_id: contentId,
                    position_seconds: ended ? 0 : now,
                    ended: ended ? '1' : '0'
                });
                if (episodeId) body.append('episode_id', episodeId);
                fetch('/api/watch_history.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body});
            };
            video.addEventListener('timeupdate', () => sendProgress(false));
            video.addEventListener('pause', () => sendProgress(false));
            video.addEventListener('ended', () => sendProgress(true));
        };
        document.addEventListener('DOMContentLoaded', attachListeners);
    })();
</script>

</body>
</html>
