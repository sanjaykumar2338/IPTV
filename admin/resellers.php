<?php
include '../includes/config.php';
include '../includes/auth.php';
include '../includes/functions.php';
requireAdminAuth();

$errors = [];
$success = '';

// Create reseller
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_reseller'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (!$errors) {
        // uniqueness checks
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM resellers WHERE username = ? OR email = ?");
        $dupStmt->execute([$username, $email]);
        if ($dupStmt->fetchColumn() > 0) {
            $errors[] = 'Username or email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO resellers (username, password_hash, email) VALUES (?, ?, ?)");
            $insert->execute([$username, $hash, $email]);
            $success = 'Reseller created successfully.';
        }
    }
}

// Delete reseller
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reseller'])) {
    $deleteId = (int) ($_POST['reseller_id'] ?? 0);
    if ($deleteId > 0) {
        $del = $pdo->prepare("DELETE FROM resellers WHERE id = ?");
        $del->execute([$deleteId]);
        $success = 'Reseller deleted.';
    }
}

// Fetch resellers
$resellers = $pdo->query("SELECT id, username, email, created_at FROM resellers ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Detail view
$detail = null;
if (isset($_GET['view'])) {
    $viewId = (int) $_GET['view'];
    $detailStmt = $pdo->prepare("SELECT id, username, email, created_at FROM resellers WHERE id = ? LIMIT 1");
    $detailStmt->execute([$viewId]);
    $detail = $detailStmt->fetch(PDO::FETCH_ASSOC);

    if ($detail) {
        $custStats = $pdo->prepare("SELECT 
                COUNT(*) total,
                SUM(subscription_status='active') active,
                SUM(subscription_status='inactive') inactive,
                SUM(subscription_status='expired') expired
            FROM customers WHERE reseller_id = ?");
        $custStats->execute([$detail['id']]);
        $detail['stats'] = $custStats->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Management - Premium IPTV Admin</title>
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
            <a href="resellers.php" class="nav-link active"><i class="fas fa-handshake"></i> Reseller Management</a>
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
            <h2 style="margin: 0; color: #2c3e50;">Reseller Management</h2>
            <span>Total: <?php echo count($resellers); ?></span>
        </div>

        <?php if ($errors): ?>
            <div style="background:#fdecea;color:#b71c1c;padding:12px 16px;border-radius:8px;margin-bottom:15px;">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo sanitize($err); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div style="background:#ecfdf3;color:#166534;padding:12px 16px;border-radius:8px;margin-bottom:15px;">
                <?php echo sanitize($success); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($resellers); ?></div>
                <div class="stat-label">Resellers</div>
            </div>
            <div class="stat-card">
                <?php
                    $customerTotal = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
                ?>
                <div class="stat-number"><?php echo (int) $customerTotal; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin:0;">Create Reseller</h4>
            </div>
            <div class="admin-card-body">
                <form method="POST" autocomplete="off" novalidate>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="create_reseller" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Create Reseller</button>
                </form>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin:0;">Resellers</h4>
            </div>
            <div class="admin-card-body">
                <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resellers as $reseller): ?>
                        <tr>
                            <td><?php echo (int)$reseller['id']; ?></td>
                            <td><?php echo sanitize($reseller['username']); ?></td>
                            <td><?php echo sanitize($reseller['email']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($reseller['created_at'])); ?></td>
                            <td style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a class="btn btn-success" href="?view=<?php echo (int)$reseller['id']; ?>" style="padding:8px 12px;">View</a>
                                <form method="POST" onsubmit="return confirm('Delete this reseller?');" style="margin:0;">
                                    <input type="hidden" name="reseller_id" value="<?php echo (int)$reseller['id']; ?>">
                                    <button type="submit" name="delete_reseller" class="btn btn-danger" style="padding:8px 12px;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <?php if ($detail): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin:0;">Reseller Details</h4>
            </div>
            <div class="admin-card-body">
                <p><strong>Username:</strong> <?php echo sanitize($detail['username']); ?></p>
                <p><strong>Email:</strong> <?php echo sanitize($detail['email']); ?></p>
                <p><strong>Created:</strong> <?php echo date('Y-m-d H:i', strtotime($detail['created_at'])); ?></p>
                <?php if (!empty($detail['stats'])): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo (int) $detail['stats']['total']; ?></div>
                            <div class="stat-label">Customers</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo (int) $detail['stats']['active']; ?></div>
                            <div class="stat-label">Active</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo (int) $detail['stats']['inactive']; ?></div>
                            <div class="stat-label">Inactive</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo (int) $detail['stats']['expired']; ?></div>
                            <div class="stat-label">Expired</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
