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
    <title>Maintenance - <?php echo $site_name; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);">
        <div style="background: white; padding: 50px; border-radius: 15px; text-align: center; max-width: 500px; margin: 20px;">
            <i class="fas fa-tools" style="font-size: 4rem; color: #3498db; margin-bottom: 20px;"></i>
            <h1 style="color: #2c3e50; margin-bottom: 15px;">Site Under Maintenance</h1>
            <p style="color: #7f8c8d; margin-bottom: 30px; line-height: 1.6;">
                We're currently performing some maintenance on our website. 
                We'll be back online shortly. Thank you for your patience.
            </p>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-top: 20px;">
                <p style="margin: 0; color: #495057; font-size: 0.9rem;">
                    <i class="fas fa-clock"></i> Expected completion: <?php echo date('F j, Y g:i A', strtotime('+1 hour')); ?>
                </p>
            </div>
        </div>
    </div>
</body>
</html>