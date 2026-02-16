<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth/session.php';

$uuid = trim($_GET['uuid'] ?? ($_POST['uuid'] ?? ''));
$error = '';
$customer = null;

// Validate UUID format
if ($uuid !== '' && preg_match('/^[a-f0-9-]{36}$/i', $uuid)) {
    $stmt = $pdo->prepare("SELECT id, uuid, pin_hash FROM customers WHERE uuid = ? LIMIT 1");
    $stmt->execute([$uuid]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        $error = 'Link invalid. Please request a fresh link from your reseller.';
    }
} else {
    $error = 'Link invalid. Please request a fresh link from your reseller.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $customer) {
    $pin = $_POST['pin'] ?? '';
    if (!preg_match('/^[0-9]{4}$/', $pin)) {
        $error = 'PIN must be 4 digits.';
    } else {
        if (password_verify($pin, $customer['pin_hash'])) {
            if (function_exists('regenerate_session_id_secure')) {
                regenerate_session_id_secure();
            } else {
                session_regenerate_id(true);
            }
            $_SESSION['customer_id'] = (int) $customer['id'];
            $_SESSION['customer_uuid'] = $customer['uuid'];
            header('Location: /index.php');
            exit;
        } else {
            $error = 'Incorrect PIN. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter PIN - Premium IPTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {font-family: Arial, sans-serif; background: radial-gradient(circle at 10% 20%, #111827, #0b1021 45%, #060814 100%); color: #e5e7eb; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; padding: 20px;}
        .card {background: #0f172a; border: 1px solid #1f2937; border-radius: 14px; padding: 28px; width: 100%; max-width: 420px; box-shadow: 0 14px 40px rgba(0,0,0,0.45);} 
        h1 {margin: 0 0 8px; font-size: 26px; text-align: center;}
        p.subtitle {margin: 0 0 24px; text-align: center; color: #9ca3af;}
        label {display:block; margin-bottom: 8px; color: #cbd5e1; font-size: 14px;}
        input[type="text"], input[type="password"] {width: 100%; padding: 12px 14px; border-radius: 10px; border: 1px solid #1f2937; background: #0b1224; color: #e5e7eb; font-size: 16px; letter-spacing: 4px; text-align: center;}
        input:focus {outline: 2px solid #6366f1;}
        .btn {width: 100%; padding: 12px; border: none; border-radius: 10px; background: #22c55e; color: #0b1021; font-weight: 700; cursor: pointer; font-size: 15px;}
        .btn:hover {background: #16a34a;}
        .error {background: #fef2f2; color: #991b1b; padding: 12px; border-radius: 10px; margin-bottom: 16px; font-size: 14px;}
        .brand {display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom: 10px; color: #f8fafc;}
        .brand i {color:#22c55e;}
        .input-hint {color:#94a3b8; font-size: 13px; margin-top: 6px; text-align:center;}
    </style>
</head>
<body>
    <div class="card">
        <div class="brand"><i class="fas fa-tv"></i><strong>Premium IPTV</strong></div>
        <h1>Enter Your PIN</h1>
        <p class="subtitle">Access your channels with the 4-digit PIN provided.</p>
        <?php if ($error): ?>
            <div class="error"><?php echo sanitize($error); ?></div>
        <?php endif; ?>
        <?php if ($customer): ?>
        <form method="POST" autocomplete="off" novalidate>
            <input type="hidden" name="uuid" value="<?php echo sanitize($uuid); ?>">
            <label for="pin">4-digit PIN</label>
            <input type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" minlength="4" placeholder="0000" required>
            <div class="input-hint">PIN is numeric only.</div>
            <div style="margin-top:16px;">
                <button class="btn" type="submit">Continue</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
