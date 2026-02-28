<?php
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $token = htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function is_valid_csrf_token(?string $token): bool {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '' || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

function require_valid_csrf(?string $token = null): void {
    $candidate = $token;
    if ($candidate === null) {
        $candidate = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    if (!is_valid_csrf_token(is_string($candidate) ? $candidate : null)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
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
