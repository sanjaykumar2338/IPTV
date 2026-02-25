<?php
require_once __DIR__ . '/../import_logger.php';

// Basic guard: require admin session if available
// If you have a stronger auth include, place it here.
// Example: include '../includes/auth.php'; requireAdminAuth();

$logFile = __DIR__ . '/../../storage/logs/import.log';

header('Content-Type: text/plain; charset=utf-8');

if (!file_exists($logFile)) {
    echo "No log file: $logFile";
    exit;
}

$lines = file($logFile);
$tail = array_slice($lines, -400);
echo implode('', $tail);
