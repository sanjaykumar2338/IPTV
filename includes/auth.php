<?php
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function adminLogin($pdo, $username, $password) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin['password'])) {
        if (function_exists('regenerate_session_id_secure')) {
            regenerate_session_id_secure();
        } else {
            session_regenerate_id(true);
        }
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_id'] = $admin['id'];
        return true;
    }
    return false;
}

function adminLogout() {
    session_destroy();
    header('Location: /admin/login.php');
    exit;
}

// Auto-handle logout query param on admin pages
if (isset($_GET['logout']) && isAdminLoggedIn()) {
    adminLogout();
}
?>
