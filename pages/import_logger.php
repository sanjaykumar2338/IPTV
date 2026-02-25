<?php

function import_log(string $msg, array $ctx = []): void
{
    $logFile = __DIR__ . '/../storage/logs/import.log';

    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $uri = $_SERVER['REQUEST_URI'] ?? 'cli';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'cli';

    $line = "[$ts] [$ip] [$method $uri] $msg";
    if (!empty($ctx)) {
        $line .= " | " . json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $line .= PHP_EOL;

    @file_put_contents($logFile, $line, FILE_APPEND);
}
