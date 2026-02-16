<?php
require_once __DIR__ . '/session.php';

function isResellerLoggedIn(): bool
{
    return isset($_SESSION['reseller_logged_in']) && $_SESSION['reseller_logged_in'] === true && isset($_SESSION['reseller_id']);
}

function requireResellerAuth(): void
{
    if (!isResellerLoggedIn()) {
        header('Location: /reseller/login.php');
        exit;
    }
}

function resellerLogin(PDO $pdo, string $usernameOrEmail, string $password): bool
{
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM resellers WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
    $reseller = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reseller && password_verify($password, $reseller['password_hash'])) {
        if (function_exists('regenerate_session_id_secure')) {
            regenerate_session_id_secure();
        } else {
            session_regenerate_id(true);
        }

        $_SESSION['reseller_logged_in'] = true;
        $_SESSION['reseller_id'] = (int) $reseller['id'];
        $_SESSION['reseller_username'] = $reseller['username'];
        $_SESSION['reseller_email'] = $reseller['email'];
        return true;
    }

    return false;
}

function resellerLogout(): void
{
    $_SESSION['reseller_logged_in'] = false;
    unset($_SESSION['reseller_id'], $_SESSION['reseller_username'], $_SESSION['reseller_email']);
    if (function_exists('destroy_session_secure')) {
        destroy_session_secure();
    } else {
        session_destroy();
    }
    header('Location: /reseller/login.php');
    exit;
}
