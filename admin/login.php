<?php
include '../includes/config.php';
include '../includes/auth.php';
include '../includes/functions.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (adminLogin($pdo, $username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Premium IPTV</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="admin-card" style="width: 400px; margin: 20px;">
            <div class="admin-card-header">
                <h3 style="margin: 0;"><i class="fas fa-tv"></i> IPTV Admin</h3>
            </div>
            <div class="admin-card-body">
                <h4 style="text-align: center; margin-bottom: 30px; color: #2c3e50;">Admin Login</h4>
                
                <?php if (isset($error)): ?>
                    <div style="background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="admin" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" value="admin" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
            </div>
        </div>
    </div>
</body>
</html>