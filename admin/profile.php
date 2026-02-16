<?php
include '../includes/config.php';
include '../includes/auth.php';
requireAdminAuth();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long!";
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($current_password, $admin['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['admin_id']]);
            
            $success = "Password changed successfully!";
        } else {
            $error = "Current password is incorrect!";
        }
    }
}

// Get admin info
$stmt = $pdo->prepare("SELECT username, email, created_at FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Premium IPTV Admin</title>
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
            <a href="profile.php" class="nav-link active"><i class="fas fa-user"></i> Profile</a>
            <a href="../index.php" class="nav-link"><i class="fas fa-external-link-alt"></i> Visit Site</a>
            <a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="admin-main">
        <div class="admin-header">
            <h2 style="margin: 0; color: #2c3e50;">Admin Profile</h2>
            <span>Manage your account settings</span>
        </div>

        <?php if (isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-user-circle"></i> Profile Information</h4>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div style="text-align: center;">
                        <div style="width: 100px; height: 100px; background: linear-gradient(45deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: white; font-size: 2rem;">
                            <i class="fas fa-user"></i>
                        </div>
                        <h4 style="margin: 0;"><?php echo htmlspecialchars($admin_info['username']); ?></h4>
                        <p style="color: #7f8c8d; margin: 5px 0;">Administrator</p>
                    </div>
                    
                    <div>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <strong>Username:</strong> <?php echo htmlspecialchars($admin_info['username']); ?>
                        </div>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <strong>Email:</strong> <?php echo htmlspecialchars($admin_info['email'] ?? 'Not set'); ?>
                        </div>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($admin_info['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-key"></i> Change Password</h4>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small style="color: #7f8c8d;">Must be at least 6 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-success">
                        <i class="fas fa-save"></i> Change Password
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-shield-alt"></i> Security Tips</h4>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="background: #e8f4fd; padding: 15px; border-radius: 8px; border-left: 4px solid #3498db;">
                        <h5 style="margin: 0 0 10px 0; color: #2c3e50;"><i class="fas fa-lock"></i> Strong Password</h5>
                        <p style="margin: 0; color: #7f8c8d; font-size: 0.9rem;">Use a combination of letters, numbers, and symbols</p>
                    </div>
                    
                    <div style="background: #e8f6f3; padding: 15px; border-radius: 8px; border-left: 4px solid #27ae60;">
                        <h5 style="margin: 0 0 10px 0; color: #2c3e50;"><i class="fas fa-sync-alt"></i> Regular Updates</h5>
                        <p style="margin: 0; color: #7f8c8d; font-size: 0.9rem;">Change your password regularly for better security</p>
                    </div>
                    
                    <div style="background: #fef9e7; padding: 15px; border-radius: 8px; border-left: 4px solid #f39c12;">
                        <h5 style="margin: 0 0 10px 0; color: #2c3e50;"><i class="fas fa-user-secret"></i> Secure Session</h5>
                        <p style="margin: 0; color: #7f8c8d; font-size: 0.9rem;">Always logout when using public computers</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
[file content end]
