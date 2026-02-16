<?php
include '../includes/config.php';
include '../includes/auth.php';

requireAdminAuth();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            // Handle checkbox values
            if (is_string($value) && $value === 'on') {
                $value = '1';
            }
            
            // Use ON DUPLICATE KEY UPDATE to handle duplicates
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
        }
        
        // Handle unchecked checkboxes
        $checkboxes = ['clearkey_enabled', 'widevine_enabled', 'playready_enabled', 'maintenance_mode'];
        foreach ($checkboxes as $checkbox) {
            if (!isset($_POST[$checkbox])) {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, '0') 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$checkbox]);
            }
        }
        
        $pdo->commit();
        $success = "Settings updated successfully!";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - Premium IPTV Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <!-- Rest of your HTML remains the same -->
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
            <h2 style="margin: 0; color: #2c3e50;">Site Settings</h2>
            <span>Configure your IPTV website</span>
        </div>

        <?php if (isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h4 style="margin: 0;"><i class="fas fa-info-circle"></i> General Settings</h4>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">Site Name</label>
                        <input type="text" name="site_name" class="form-control" 
                               value="<?php echo $settings['site_name'] ?? 'Premium IPTV'; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Site Description</label>
                        <textarea name="site_description" class="form-control" rows="3" required><?php echo $settings['site_description'] ?? 'Watch thousands of HD channels with premium quality'; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" 
                               value="<?php echo $settings['contact_email'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h4 style="margin: 0;"><i class="fas fa-palette"></i> Theme Settings</h4>
                </div>
                <div class="admin-card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Primary Color</label>
                            <input type="color" name="primary_color" class="form-control" 
                                   value="<?php echo $settings['primary_color'] ?? '#3498db'; ?>" style="height: 50px;">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Secondary Color</label>
                            <input type="color" name="secondary_color" class="form-control" 
                                   value="<?php echo $settings['secondary_color'] ?? '#2c3e50'; ?>" style="height: 50px;">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Accent Color</label>
                            <input type="color" name="accent_color" class="form-control" 
                                   value="<?php echo $settings['accent_color'] ?? '#e74c3c'; ?>" style="height: 50px;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h4 style="margin: 0;"><i class="fas fa-shield-alt"></i> DRM Settings</h4>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="switch">
                            <input type="checkbox" name="clearkey_enabled" 
                                   <?php echo ($settings['clearkey_enabled'] ?? '1') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left: 10px;">Enable ClearKey DRM</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="switch">
                            <input type="checkbox" name="widevine_enabled" 
                                   <?php echo ($settings['widevine_enabled'] ?? '1') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left: 10px;">Enable Widevine DRM</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="switch">
                            <input type="checkbox" name="playready_enabled" 
                                   <?php echo ($settings['playready_enabled'] ?? '0') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left: 10px;">Enable PlayReady DRM</span>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h4 style="margin: 0;"><i class="fas fa-cogs"></i> Advanced Settings</h4>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">Default M3U URL</label>
                        <input type="url" name="default_m3u_url" class="form-control" 
                               value="<?php echo $settings['default_m3u_url'] ?? ''; ?>" 
                               placeholder="https://example.com/playlist.m3u">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">API Key (Optional)</label>
                        <input type="text" name="api_key" class="form-control" 
                               value="<?php echo $settings['api_key'] ?? ''; ?>" 
                               placeholder="Your API key for external services">
                    </div>
                    
                    <div class="form-group">
                        <label class="switch">
                            <input type="checkbox" name="maintenance_mode" 
                                   <?php echo ($settings['maintenance_mode'] ?? '0') ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left: 10px;">Maintenance Mode</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 1.1rem;">
                <i class="fas fa-save"></i> Save All Settings
            </button>
        </form>
    </div>
</body>
</html>
