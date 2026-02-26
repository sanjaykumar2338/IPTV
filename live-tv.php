<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth/check_auth.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
$asset_version = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live TV - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="assets/css/epg.css?v=<?php echo $asset_version; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Modern Container */
        .page-shell {
            max-width: 1600px;
            margin: 0 auto;
            padding: 15px;
        }

        /* Responsive Header for EPG */
        .epg-header {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .epg-controls {
            display: flex;
            gap: 10px;
            overflow-x: auto; /* Allow buttons to scroll on tiny screens */
            padding-bottom: 5px;
            scrollbar-width: none;
        }
        
        .epg-controls::-webkit-scrollbar { display: none; }

        /* The EPG Grid Container */
        #epgGrid {
            background: var(--glass-card-bg, rgba(255, 255, 255, 0.05));
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden; /* Prevent bleed */
            display: flex;
            flex-direction: column;
        }

        /* Responsive Logic for the Grid (Handled in CSS & JS) */
        .epg-guide {
            display: grid;
            grid-template-columns: 100px 1fr; /* Default Channel Logo + Timeline */
            position: relative;
            overflow-x: auto; /* Horizontal scroll for the timeline */
        }

        /* Mobile specific adjustments */
        @media (max-width: 768px) {
            .epg-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .section-title span {
                font-size: 1.2rem;
            }

            /* On mobile, we might want the channel column smaller */
            .epg-guide {
                grid-template-columns: 70px 1fr;
            }

            .button-pill {
                padding: 8px 16px;
                font-size: 0.85rem;
            }
        }

        /* Touch-friendly scrolling */
        .epg-container-scroll {
            -webkit-overflow-scrolling: touch;
            cursor: grab;
        }

        .epg-container-scroll:active {
            cursor: grabbing;
        }
        
        /* Time bar highlight */
        .current-time-indicator {
            border-left: 2px solid #ff4757;
            box-shadow: 0 0 10px rgba(255, 71, 87, 0.5);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/pages/partials/header.php'; ?>

<main class="page-shell">
    <div class="epg-header">
        <div class="section-title" style="margin-bottom: 0;">
            <span><i class="fas fa-broadcast-tower" style="color:var(--primary-color);"></i> Live TV Guide</span>
        </div>
        
        <div class="epg-controls">
            <button class="button-pill" data-tab="all" id="tabAll">
                <i class="fas fa-list"></i> All Channels
            </button>
            <button class="button-pill button-secondary" data-tab="fav" id="tabFav">
                <i class="fas fa-heart"></i> Favorites
            </button>
            <button class="button-pill button-secondary" id="btnNow">
                Jump to Now
            </button>
        </div>
    </div>

    <div class="epg-container-scroll glass-card" style="padding: 0;">
        <div id="epgGrid" class="epg-grid epg-guide">
            <div style="padding: 40px; text-align: center; color: var(--muted); grid-column: 1/-1;">
                <i class="fas fa-circle-notch fa-spin fa-2x"></i>
                <p style="margin-top: 10px;">Loading Electronic Program Guide...</p>
            </div>
        </div>
    </div>
</main>

<script src="/assets/js/main.js?v=<?php echo $asset_version; ?>"></script>
<script src="/assets/js/epg.js?v=<?php echo $asset_version; ?>"></script>
<script src="/assets/js/search.js?v=<?php echo $asset_version; ?>"></script>

<script>
    // Simple UI interaction for Tabs
    const tabs = document.querySelectorAll('[data-tab]');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.add('button-secondary'));
            tab.classList.remove('button-secondary');
            // epg.js should handle the actual filtering based on click
        });
    });

    // Helper to scroll the EPG to current time on load
    document.getElementById('btnNow')?.addEventListener('click', () => {
        const nowMarker = document.querySelector('.current-time-indicator');
        if (nowMarker) {
            nowMarker.scrollIntoView({ behavior: 'smooth', inline: 'center' });
        }
    });
</script>

</body>
</html>