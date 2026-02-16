<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config.php';

function customer_gatekeep(PDO $pdo): void
{
    if (!isset($_SESSION['customer_id'])) {
        redirectToLogin();
    }

    $stmt = $pdo->prepare("SELECT id, uuid, subscription_status, subscription_expiry_date FROM customers WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        destroy_session_secure();
        redirectToLogin();
    }

    // Expiry validation
    if ($customer['subscription_expiry_date'] !== null) {
        $expiry_ts = strtotime($customer['subscription_expiry_date']);
        if ($expiry_ts !== false && $expiry_ts < time()) {
            updateSubscriptionStatus($pdo, (int) $customer['id'], 'expired');
            destroy_session_secure();
            redirectToLogin($customer['uuid']);
        }
    }

    if ($customer['subscription_status'] === 'expired') {
        destroy_session_secure();
        redirectToLogin($customer['uuid']);
    }
}

function updateSubscriptionStatus(PDO $pdo, int $customerId, string $status): void
{
    $allowed = ['active', 'inactive', 'expired'];
    if (!in_array($status, $allowed, true)) {
        return;
    }
    $stmt = $pdo->prepare("UPDATE customers SET subscription_status = ? WHERE id = ?");
    $stmt->execute([$status, $customerId]);
}

function redirectToLogin(?string $uuid = null): void
{
    $target = '/watch';
    if ($uuid) {
        $target .= '?uuid=' . urlencode($uuid);
    }
    header('Location: ' . $target);
    exit;
}

customer_gatekeep($pdo);
