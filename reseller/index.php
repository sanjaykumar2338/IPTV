<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth/reseller.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth/uuid.php';

requireResellerAuth();

// Flash messaging
$flash = $_SESSION['reseller_flash'] ?? null;
unset($_SESSION['reseller_flash']);

// Fetch customers for this reseller
$customerStmt = $pdo->prepare("SELECT id, full_name, phone, phone_number, subscription_status, subscription_expiry_date, uuid FROM customers WHERE reseller_id = ? ORDER BY created_at DESC");
$customerStmt->execute([$_SESSION['reseller_id']]);
$customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = rtrim($scheme . $host, '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Dashboard - Premium IPTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {font-family: Arial, sans-serif; background: #0b1021; color: #e5e7eb; margin: 0;}
        header {background: #111827; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 12px rgba(0,0,0,0.4);} 
        header .brand {display: flex; align-items: center; gap: 10px; font-weight: 700; letter-spacing: .3px;}
        header .brand i {color: #22c55e;}
        header .user {color: #9ca3af; font-size: 14px;}
        main {max-width: 1100px; margin: 30px auto; padding: 0 20px 60px;}
        h2 {margin: 0 0 12px;}
        .card {background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 8px 30px rgba(0,0,0,0.28);} 
        label {display:block; margin-bottom: 8px; font-size: 14px; color: #cbd5e1;}
        input[type="tel"], input[type="text"] {width: 100%; padding: 12px 14px; border-radius: 10px; border: 1px solid #1f2937; background: #0b1224; color: #e5e7eb; font-size: 15px;} 
        input[type="tel"]:focus, input[type="text"]:focus {outline: 2px solid #6366f1;}
        .btn {display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border: none; border-radius: 10px; cursor: pointer; font-weight: 600;}
        .btn-primary {background: #6366f1; color: #fff;} .btn-primary:hover {background: #4f46e5;}
        .btn-ghost {background: #111827; border: 1px solid #1f2937; color: #e5e7eb;}
        .grid {display: grid; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); gap: 16px;}
        .flash {padding: 12px 14px; border-radius: 10px; margin-bottom: 16px;}
        .flash.success {background: #ecfdf3; color: #166534;}
        .flash.error {background: #fef2f2; color: #991b1b;}
        table {width: 100%; border-collapse: collapse; margin-top: 12px;}
        th, td {padding: 12px 10px; border-bottom: 1px solid #1f2937; text-align: left;}
        th {color: #cbd5e1; font-weight: 600;}
        td {color: #e5e7eb; font-size: 14px;}
        .status-pill {padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;}
        .status-active {background: #ecfdf3; color: #15803d;}
        .status-inactive {background: #f1f5f9; color: #475569;}
        .status-expired {background: #fef2f2; color: #b91c1c;}
        .actions {display: flex; flex-wrap: wrap; gap: 6px;}
        .small-btn {padding: 6px 10px; font-size: 12px; border-radius: 8px; border: 1px solid #1f2937; background: #1f2937; color: #e5e7eb; cursor: pointer;}
        .small-btn:hover {background: #273449;}
        .link-box {background: #0b1224; border: 1px solid #1f2937; padding: 10px; border-radius: 10px; margin-top: 10px; display: flex; align-items: center; justify-content: space-between; gap: 10px; color: #cbd5e1; font-size: 14px;}
        .copy-btn {border: none; background: #111827; color: #9ca3af; padding: 6px 10px; border-radius: 8px; cursor: pointer;}
        .copy-btn:hover {color: #fff;}
        @media (max-width: 640px) {th, td {font-size: 12px;} .actions {flex-direction: column;}}
    </style>
</head>
<body>
<header>
    <div class="brand"><i class="fas fa-satellite-dish"></i> <span>Premium IPTV — Reseller</span></div>
    <div class="user" style="display:flex; gap:12px; align-items:center;">
        <span><i class="fas fa-user"></i> <?php echo sanitize($_SESSION['reseller_username'] ?? ''); ?></span>
        <a href="/reseller/logout.php" style="color:#f87171; text-decoration:none; font-weight:600;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</header>
<main>
    <?php if ($flash): ?>
        <div class="flash <?php echo $flash['type'] === 'error' ? 'error' : 'success'; ?>">
            <?php echo sanitize($flash['message']); ?>
            <?php if (!empty($flash['link'])): ?>
                <div class="link-box">
                    <span id="watchLink"><?php echo sanitize($flash['link']); ?></span>
                    <button class="copy-btn" type="button" onclick="copyLink()"><i class="fas fa-copy"></i> Copy</button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2>Create Customer</h2>
            <p style="color:#9ca3af; margin-top:0;">Add customer details; we’ll generate a unique watch link.</p>
            <form method="POST" action="/reseller/actions/create_customer.php" autocomplete="off" novalidate>
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" placeholder="John Doe" required>
                <label for="phone" style="margin-top:12px;">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="+12025550123" required>
                <div style="margin-top:14px;">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-plus-circle"></i> Create Customer</button>
                </div>
            </form>
        </div>
        <div class="card">
            <h2>Watch Link Format</h2>
            <p style="color:#9ca3af;">Generated links use customer UUID. Example:</p>
            <div class="link-box" style="margin-top:10px;">
                <span><?php echo sanitize($baseUrl . '/watch?uuid={uuid}'); ?></span>
            </div>
            <p style="color:#94a3b8; margin-top:10px; font-size:13px;">Share the specific link shown after creating a customer.</p>
        </div>
    </div>

    <div class="card">
        <h2>Customers</h2>
        <?php if (empty($customers)): ?>
            <p style="color:#9ca3af;">No customers yet. Create one to get started.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Expiry</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?php echo sanitize($customer['full_name']); ?></td>
                        <td><?php echo sanitize($customer['phone'] ?: $customer['phone_number']); ?></td>
                        <td>
                            <?php
                                $statusClass = 'status-' . $customer['subscription_status'];
                                $expiryText = $customer['subscription_expiry_date'] ? date('Y-m-d H:i', strtotime($customer['subscription_expiry_date'])) : '—';
                            ?>
                            <span class="status-pill <?php echo sanitize($statusClass); ?>"><?php echo strtoupper(sanitize($customer['subscription_status'])); ?></span>
                        </td>
                        <td><?php echo $customer['subscription_expiry_date'] ? sanitize($expiryText) : 'Not set'; ?></td>
                        <td>
                            <div class="actions">
                                <form method="POST" action="/reseller/actions/subscription.php" style="margin:0;">
                                    <input type="hidden" name="customer_id" value="<?php echo (int) $customer['id']; ?>">
                                    <button class="small-btn" name="action" value="add_1m" type="submit">+1 Month</button>
                                    <button class="small-btn" name="action" value="add_3m" type="submit">+3 Months</button>
                                    <button class="small-btn" name="action" value="add_1y" type="submit">+1 Year</button>
                                    <button class="small-btn" name="action" value="deactivate" type="submit" style="background:#7f1d1d; color:#fff;">Deactivate</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>
<script>
    function copyLink() {
        const el = document.getElementById('watchLink');
        if (!el) return;
        const text = el.textContent.trim();
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.querySelector('.copy-btn');
            if (btn) { btn.textContent = 'Copied!'; setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i> Copy', 1200); }
        });
    }
</script>
</body>
</html>
