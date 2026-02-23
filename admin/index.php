<?php
include '../includes/config.php';
include '../includes/auth.php';
include '../includes/functions.php';
requireAdminAuth();

// Get stats
$channels_count = $pdo->query("SELECT COUNT(*) FROM channels")->fetchColumn();
$active_channels = $pdo->query("SELECT COUNT(*) FROM channels WHERE is_active = true")->fetchColumn();
$categories_count = $pdo->query("SELECT COUNT(DISTINCT category) FROM channels")->fetchColumn();
$total_views = $pdo->query("SELECT SUM(views) FROM channels")->fetchColumn();
$movies_count = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
$series_count = $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();
$episodes_count = $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn();

// Recent channels
$recent_channels = $pdo->query("SELECT * FROM channels ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Premium IPTV Admin</title>
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
            <h2 style="margin: 0; color: #2c3e50;">Dashboard</h2>
            <span>Welcome, <?php echo $_SESSION['admin_username']; ?></span>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $channels_count; ?></div>
                <div class="stat-label">Total Channels</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_channels; ?></div>
                <div class="stat-label">Active Channels</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $categories_count; ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_views); ?></div>
                <div class="stat-label">Total Views</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $movies_count; ?></div>
                <div class="stat-label">Movies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $series_count; ?></div>
                <div class="stat-label">Series</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $episodes_count; ?></div>
                <div class="stat-label">Episodes</div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-broadcast-tower"></i> Recent Channels</h4>
                <a href="channels.php" class="btn btn-primary">Manage Channels</a>
            </div>
            <div class="admin-card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Channel Name</th>
                            <th>Category</th>
                            <th>DRM</th>
                            <th>Views</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_channels as $channel): ?>
                        <tr>
                            <td><?php echo sanitize($channel['name']); ?></td>
                            <td><?php echo sanitize($channel['category']); ?></td>
                            <td>
                                <?php if ($channel['drm_type']): ?>
                                    <span style="color: #e74c3c;"><?php echo strtoupper($channel['drm_type']); ?></span>
                                <?php else: ?>
                                    <span style="color: #27ae60;">None</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($channel['views']); ?></td>
                            <td>
                                <?php if ($channel['is_active']): ?>
                                    <span style="color: #27ae60;">Active</span>
                                <?php else: ?>
                                    <span style="color: #e74c3c;">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-upload"></i> Quick M3U Import</h4>
            </div>
            <div class="admin-card-body">
                <form action="channels.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Upload M3U File</label>
                        <div class="file-upload">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 15px;"></i>
                            <p>Drag & drop your M3U file here or click to browse</p>
                            <input type="file" name="m3u_file" accept=".m3u,.m3u8" required>
                            <button type="button" class="btn btn-primary" onclick="document.querySelector('input[type=file]').click()">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="import_m3u" class="btn btn-success">
                        <i class="fas fa-upload"></i> Import Channels
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // File upload display
        document.querySelector('input[type=file]').addEventListener('change', function(e) {
            if (this.files.length > 0) {
                document.querySelector('.file-upload p').textContent = 'Selected: ' + this.files[0].name;
            }
        });
    </script>
</body>
</html>
