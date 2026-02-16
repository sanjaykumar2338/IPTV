<?php
include '../includes/config.php';
include '../includes/auth.php';
requireAdminAuth();

// Handle video ad actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_video_ad'])) {
        $ad_type = $_POST['ad_type']; // pre-roll, mid-roll, post-roll, random
        $ad_name = $_POST['ad_name'];
        $video_url = $_POST['video_url'];
        $duration = $_POST['duration'];
        $skip_after = $_POST['skip_after'];
        $target_url = $_POST['target_url'];
        $categories = implode(',', $_POST['categories'] ?? []);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO video_ads (ad_type, ad_name, video_url, duration, skip_after, target_url, categories, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ad_type, $ad_name, $video_url, $duration, $skip_after, $target_url, $categories, $is_active]);
        $success = "Video ad created successfully!";
    }
    elseif (isset($_POST['update_video_ad'])) {
        $ad_id = $_POST['ad_id'];
        $ad_type = $_POST['ad_type'];
        $ad_name = $_POST['ad_name'];
        $video_url = $_POST['video_url'];
        $duration = $_POST['duration'];
        $skip_after = $_POST['skip_after'];
        $target_url = $_POST['target_url'];
        $categories = implode(',', $_POST['categories'] ?? []);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE video_ads SET 
            ad_type=?, ad_name=?, video_url=?, duration=?, skip_after=?, target_url=?, categories=?, is_active=?
            WHERE id=?
        ");
        $stmt->execute([$ad_type, $ad_name, $video_url, $duration, $skip_after, $target_url, $categories, $is_active, $ad_id]);
        $success = "Video ad updated successfully!";
    }
}

// Handle ad actions
if (isset($_GET['action'])) {
    $ad_id = $_GET['id'] ?? 0;
    
    switch ($_GET['action']) {
        case 'toggle':
            $stmt = $pdo->prepare("UPDATE video_ads SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$ad_id]);
            break;
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM video_ads WHERE id = ?");
            $stmt->execute([$ad_id]);
            break;
    }
    header('Location: video-ads.php');
    exit;
}

// Get all video ads
$video_ads = $pdo->query("SELECT * FROM video_ads ORDER BY ad_type, created_at DESC")->fetchAll();

// Get categories for filtering
$categories = $pdo->query("SELECT DISTINCT category FROM channels WHERE is_active = true ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Create video_ads table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS video_ads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ad_type ENUM('pre-roll', 'mid-roll', 'post-roll', 'random') NOT NULL,
        ad_name VARCHAR(255) NOT NULL,
        video_url VARCHAR(500) NOT NULL,
        duration INT NOT NULL,
        skip_after INT DEFAULT 5,
        target_url VARCHAR(500),
        categories TEXT,
        is_active BOOLEAN DEFAULT true,
        impressions INT DEFAULT 0,
        clicks INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Ads Management - Premium IPTV Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-tv"></i> IPTV Admin</h3>
        </div>
        <nav style="padding: 20px 0;">
            <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="channels.php" class="nav-link"><i class="fas fa-broadcast-tower"></i> Channels</a>
            <a href="resellers.php" class="nav-link"><i class="fas fa-handshake"></i> Reseller Management</a>
            <a href="ads.php" class="nav-link"><i class="fas fa-ad"></i> Ad Management</a>
            <a href="video-ads.php" class="nav-link"><i class="fas fa-video"></i> Video Ads</a>
            <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
            <a href="seo.php" class="nav-link"><i class="fas fa-search"></i> SEO</a>
            <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> Profile</a>
            <a href="../index.php" class="nav-link"><i class="fas fa-external-link-alt"></i> Visit Site</a>
            <a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="admin-main">
        <div class="admin-header">
            <h2 style="margin: 0; color: #2c3e50;">Video Ads Management</h2>
            <span><?php echo count($video_ads); ?> video ads configured</span>
        </div>

        <?php if (isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-plus"></i> Add New Video Ad</h4>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Ad Type</label>
                        <select name="ad_type" class="form-control" required>
                            <option value="">Select Ad Type</option>
                            <option value="pre-roll">Pre-roll (before content)</option>
                            <option value="mid-roll">Mid-roll (during content)</option>
                            <option value="post-roll">Post-roll (after content)</option>
                            <option value="random">Random (random timing)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ad Name</label>
                        <input type="text" name="ad_name" class="form-control" required placeholder="Enter ad name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Video URL</label>
                        <input type="url" name="video_url" class="form-control" required placeholder="https://example.com/ad.mp4">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Duration (seconds)</label>
                            <input type="number" name="duration" class="form-control" required min="5" max="300" value="30">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Skip After (seconds)</label>
                            <input type="number" name="skip_after" class="form-control" min="0" max="300" value="5">
                            <small style="color: #7f8c8d;">0 = cannot skip</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Target URL (optional)</label>
                        <input type="url" name="target_url" class="form-control" placeholder="https://example.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Target Categories (optional)</label>
                        <select name="categories[]" class="form-control" multiple style="height: 120px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #7f8c8d;">Hold Ctrl to select multiple categories</small>
                    </div>

                    <div class="form-group">
                        <label class="switch">
                            <input type="checkbox" name="is_active" checked>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left: 10px;">Active</span>
                    </div>

                    <button type="submit" name="add_video_ad" class="btn btn-success">
                        <i class="fas fa-save"></i> Create Video Ad
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-list"></i> Manage Video Ads</h4>
            </div>
            <div class="admin-card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Name</th>
                            <th>Duration</th>
                            <th>Categories</th>
                            <th>Stats</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($video_ads as $ad): ?>
                        <tr>
                            <td>
                                <span style="text-transform: capitalize;"><?php echo $ad['ad_type']; ?></span>
                            </td>
                            <td>
                                <strong><?php echo $ad['ad_name']; ?></strong>
                                <?php if ($ad['target_url']): ?>
                                    <br><small><a href="<?php echo $ad['target_url']; ?>" target="_blank">Click URL</a></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $ad['duration']; ?>s
                                <?php if ($ad['skip_after'] > 0): ?>
                                    <br><small>Skip after <?php echo $ad['skip_after']; ?>s</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $ad['categories'] ?: 'All Categories'; ?>
                            </td>
                            <td>
                                <small>Impressions: <?php echo $ad['impressions']; ?></small><br>
                                <small>Clicks: <?php echo $ad['clicks']; ?></small>
                            </td>
                            <td>
                                <?php if ($ad['is_active']): ?>
                                    <span style="color: #27ae60;">Active</span>
                                <?php else: ?>
                                    <span style="color: #e74c3c;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="?action=toggle&id=<?php echo $ad['id']; ?>" class="btn btn-primary" style="padding: 5px 10px;">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $ad['id']; ?>" class="btn btn-danger" style="padding: 5px 10px;" onclick="return confirm('Delete this video ad?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
