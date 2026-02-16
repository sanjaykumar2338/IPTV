<?php
include '../includes/config.php';
include '../includes/auth.php';
include '../includes/functions.php';
requireAdminAuth();

// Handle ad actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_ad'])) {
        $ad_type = $_POST['ad_type'];
        $ad_position = $_POST['ad_position'];
        $ad_code = $_POST['ad_code'];
        $image_url = $_POST['image_url'];
        $link_url = $_POST['link_url'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO ads (ad_type, ad_position, ad_code, image_url, link_url, is_active) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ad_type, $ad_position, $ad_code, $image_url, $link_url, $is_active]);
        $success = "Ad created successfully!";
    }
    elseif (isset($_POST['update_ad'])) {
        $ad_id = $_POST['ad_id'];
        $ad_type = $_POST['ad_type'];
        $ad_position = $_POST['ad_position'];
        $ad_code = $_POST['ad_code'];
        $image_url = $_POST['image_url'];
        $link_url = $_POST['link_url'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE ads SET ad_type=?, ad_position=?, ad_code=?, image_url=?, link_url=?, is_active=? WHERE id=?");
        $stmt->execute([$ad_type, $ad_position, $ad_code, $image_url, $link_url, $is_active, $ad_id]);
        $success = "Ad updated successfully!";
    }
}

// Handle ad deletion or toggle
if (isset($_GET['action'])) {
    $ad_id = $_GET['id'] ?? 0;
    
    switch ($_GET['action']) {
        case 'toggle':
            $stmt = $pdo->prepare("UPDATE ads SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$ad_id]);
            break;
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM ads WHERE id = ?");
            $stmt->execute([$ad_id]);
            break;
    }
    header('Location: ads.php');
    exit;
}

// Get all ads
$ads = $pdo->query("SELECT * FROM ads ORDER BY ad_position, ad_type")->fetchAll();

// Get ad positions and types
$positions = ['header', 'body', 'footer'];
$types = ['google', 'image'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Management - Premium IPTV Admin</title>
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
            <h2 style="margin: 0; color: #2c3e50;">Ad Management</h2>
            <span><?php echo count($ads); ?> ads configured</span>
        </div>

        <?php if (isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-plus"></i> Add New Ad</h4>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Ad Type</label>
                        <select name="ad_type" class="form-control" required onchange="toggleAdFields(this.value)">
                            <option value="">Select Ad Type</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo ucfirst($type); ?> Ad</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ad Position</label>
                        <select name="ad_position" class="form-control" required>
                            <option value="">Select Position</option>
                            <?php foreach ($positions as $position): ?>
                                <option value="<?php echo $position; ?>"><?php echo ucfirst($position); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="google-ad-fields" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">AdSense Code</label>
                            <textarea name="ad_code" class="form-control" rows="6" placeholder="Paste your Google AdSense code here..."></textarea>
                        </div>
                    </div>

                    <div id="image-ad-fields" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Image URL</label>
                            <input type="url" name="image_url" class="form-control" placeholder="https://example.com/ad-image.jpg">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Link URL</label>
                            <input type="url" name="link_url" class="form-control" placeholder="https://example.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="switch">
                            <input type="checkbox" name="is_active" checked>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left: 10px;">Active</span>
                    </div>

                    <button type="submit" name="add_ad" class="btn btn-success">
                        <i class="fas fa-save"></i> Create Ad
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-list"></i> Manage Ads</h4>
            </div>
            <div class="admin-card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Position</th>
                            <th>Preview</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ads as $ad): ?>
                        <tr>
                            <td>
                                <span style="text-transform: capitalize;"><?php echo $ad['ad_type']; ?></span>
                            </td>
                            <td>
                                <span style="text-transform: capitalize;"><?php echo $ad['ad_position']; ?></span>
                            </td>
                            <td>
                                <?php if ($ad['ad_type'] === 'image' && $ad['image_url']): ?>
                                    <img src="<?php echo $ad['image_url']; ?>" alt="Ad Preview" style="max-width: 100px; max-height: 50px;">
                                <?php else: ?>
                                    <code style="font-size: 0.8rem;">Ad Code</code>
                                <?php endif; ?>
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
                                    <a href="?action=delete&id=<?php echo $ad['id']; ?>" class="btn btn-danger" style="padding: 5px 10px;" onclick="return confirm('Delete this ad?')">
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

    <script>
        function toggleAdFields(adType) {
            document.getElementById('google-ad-fields').style.display = 'none';
            document.getElementById('image-ad-fields').style.display = 'none';
            
            if (adType === 'google') {
                document.getElementById('google-ad-fields').style.display = 'block';
            } else if (adType === 'image') {
                document.getElementById('image-ad-fields').style.display = 'block';
            }
        }
    </script>
</body>
</html>
