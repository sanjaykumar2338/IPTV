<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth/reseller.php';
require_once __DIR__ . '/../../includes/auth/uuid.php';
require_once __DIR__ . '/../../includes/functions.php';

requireResellerAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /reseller/index.php');
    exit;
}

$phone = trim($_POST['phone_number'] ?? '');

if ($phone === '') {
    setFlash('error', 'Phone number is required.');
    redirectBack();
}

// Basic phone validation: digits with optional leading +, length 7-15
if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
    setFlash('error', 'Please enter a valid phone number.');
    redirectBack();
}

// Check duplicate phone
$existingStmt = $pdo->prepare("SELECT id FROM customers WHERE phone_number = ? LIMIT 1");
$existingStmt->execute([$phone]);
if ($existingStmt->fetch()) {
    setFlash('error', 'That phone number already exists.');
    redirectBack();
}

$uuid = generate_uuid_v4();
$pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
$pin_hash = password_hash($pin, PASSWORD_DEFAULT);

$insert = $pdo->prepare("INSERT INTO customers (phone_number, uuid, pin_hash, subscription_status, subscription_expiry_date, reseller_id) VALUES (?, ?, ?, 'inactive', NULL, ?)");
$insert->execute([$phone, $uuid, $pin_hash, $_SESSION['reseller_id']]);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$link = rtrim($scheme . $host, '/') . '/watch?uuid=' . urlencode($uuid);

setFlash('success', 'Customer created. Share the watch link and PIN: ' . $pin, $link);
redirectBack();

// Helpers
function setFlash(string $type, string $message, string $link = ''): void
{
    $_SESSION['reseller_flash'] = [
        'type' => $type,
        'message' => $message,
        'link' => $link,
    ];
}

function redirectBack(): void
{
    header('Location: /reseller/index.php');
    exit;
}
