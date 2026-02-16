<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth/reseller.php';
require_once __DIR__ . '/../includes/functions.php';

if (isResellerLoggedIn()) {
    header('Location: /reseller/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usernameOrEmail === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        if (resellerLogin($pdo, $usernameOrEmail, $password)) {
            header('Location: /reseller/index.php');
            exit;
        } else {
            $error = 'Invalid credentials. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Login - Premium IPTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {font-family: Arial, sans-serif; background: linear-gradient(135deg, #1c1f2b, #2a3350); margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #ecf0f1;}
        .card {background: #161925; padding: 32px; border-radius: 12px; width: 100%; max-width: 420px; box-shadow: 0 12px 40px rgba(0,0,0,0.35);} 
        h1 {margin: 0 0 10px; font-size: 26px; text-align: center;} 
        p.subtitle {margin: 0 0 24px; text-align: center; color: #9ca3af;} 
        label {display: block; margin-bottom: 6px; color: #e5e7eb; font-size: 14px;} 
        input {width: 100%; padding: 12px 14px; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: #e5e7eb; font-size: 15px;}
        input:focus {outline: 2px solid #6366f1; border-color: transparent;}
        .field {margin-bottom: 16px;}
        .btn {width: 100%; padding: 12px; border: none; border-radius: 8px; background: #6366f1; color: #fff; font-weight: 600; cursor: pointer; font-size: 15px;}
        .btn:hover {background: #4f46e5;}
        .error {background: #fee2e2; color: #991b1b; padding: 10px 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;}
        .brand {display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 10px; color: #f8fafc;}
        .brand i {color: #22c55e;}
    </style>
</head>
<body>
    <div class="card">
        <div class="brand"><i class="fas fa-satellite-dish"></i><strong>Premium IPTV</strong></div>
        <h1>Reseller Login</h1>
        <p class="subtitle">Access your dashboard to manage customers.</p>
        <?php if ($error): ?>
            <div class="error"><?php echo sanitize($error); ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off" novalidate>
            <div class="field">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" placeholder="reseller@domain.com" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <button class="btn" type="submit">Sign In</button>
        </form>
    </div>
</body>
</html>
