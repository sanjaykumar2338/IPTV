<?php
include '../includes/config.php';
include '../includes/functions.php';

$site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="index.php">
                        <i class="fas fa-tv"></i>
                        <?php echo $site_name; ?>
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div style="text-align: center; padding: 100px 20px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #e74c3c; margin-bottom: 20px;"></i>
            <h1 style="font-size: 3rem; margin-bottom: 20px;">404 - Page Not Found</h1>
            <p style="font-size: 1.2rem; margin-bottom: 30px; color: #95a5a6;">
                The page you're looking for doesn't exist or has been moved.
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="index.php" class="cta-button">
                    <i class="fas fa-home"></i> Go Home
                </a>
                <a href="channels.php" class="cta-button secondary">
                    <i class="fas fa-broadcast-tower"></i> Browse Channels
                </a>
            </div>
        </div>
    </main>
</body>
</html>