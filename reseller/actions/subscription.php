<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth/reseller.php';
require_once __DIR__ . '/../../includes/functions.php';

requireResellerAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /reseller/index.php');
    exit;
}

$customerId = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
$action = $_POST['action'] ?? '';
$allowed = ['add_1m', 'add_3m', 'add_1y', 'deactivate'];

if ($customerId <= 0 || !in_array($action, $allowed, true)) {
    setFlash('error', 'Invalid request.');
    redirectBack();
}

// Ensure customer belongs to this reseller
$customerStmt = $pdo->prepare("SELECT id, subscription_expiry_date FROM customers WHERE id = ? AND reseller_id = ? LIMIT 1");
$customerStmt->execute([$customerId, $_SESSION['reseller_id']]);
$customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    setFlash('error', 'Customer not found.');
    redirectBack();
}

try {
    if ($action === 'deactivate') {
        $update = $pdo->prepare("UPDATE customers SET subscription_status = 'expired', subscription_expiry_date = NOW() WHERE id = ?");
        $update->execute([$customerId]);
        setFlash('success', 'Subscription deactivated.');
        redirectBack();
    }

    $now = new DateTime();
    $currentExpiry = $customer['subscription_expiry_date'] ? new DateTime($customer['subscription_expiry_date']) : null;

    $start = ($currentExpiry && $currentExpiry > $now) ? $currentExpiry : $now;

    switch ($action) {
        case 'add_1m':
            $start->add(new DateInterval('P1M'));
            break;
        case 'add_3m':
            $start->add(new DateInterval('P3M'));
            break;
        case 'add_1y':
            $start->add(new DateInterval('P1Y'));
            break;
    }

    $newExpiry = $start->format('Y-m-d H:i:s');

    $update = $pdo->prepare("UPDATE customers SET subscription_expiry_date = ?, subscription_status = 'active' WHERE id = ?");
    $update->execute([$newExpiry, $customerId]);

    setFlash('success', 'Subscription updated. New expiry: ' . $newExpiry);
    redirectBack();
} catch (Exception $e) {
    setFlash('error', 'Failed to update subscription.');
    redirectBack();
}

// Helpers
function setFlash(string $type, string $message): void
{
    $_SESSION['reseller_flash'] = [
        'type' => $type,
        'message' => $message,
        'link' => ''
    ];
}

function redirectBack(): void
{
    header('Location: /reseller/index.php');
    exit;
}
