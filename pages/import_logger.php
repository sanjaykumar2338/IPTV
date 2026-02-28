<?php

if (!function_exists('import_log_sanitize_context')) {
    function import_log_sanitize_context(array $ctx): array
    {
        if (function_exists('provider_redact_context')) {
            $safe = provider_redact_context($ctx);
            return is_array($safe) ? $safe : $ctx;
        }

        array_walk_recursive($ctx, static function (&$value, $key): void {
            if (!is_string($value)) {
                return;
            }

            $sensitiveKey = is_string($key) && (bool)preg_match('/(pass|password|username|user|token|secret|provider_key|source_url|stream_url|url|effective_url)/i', $key);
            if ($sensitiveKey || strpos($value, '://') !== false) {
                if (function_exists('redact_provider_secret')) {
                    $value = redact_provider_secret($value);
                } else {
                    $value = '****';
                }
            }
        });

        return $ctx;
    }
}

function import_log(string $msg, array $ctx = []): void
{
    $logFile = __DIR__ . '/../storage/logs/import.log';

    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $uri = $_SERVER['REQUEST_URI'] ?? 'cli';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'cli';

    $line = "[$ts] [$ip] [$method $uri] $msg";
    if (!empty($ctx)) {
        $line .= " | " . json_encode(import_log_sanitize_context($ctx), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $line .= PHP_EOL;

    @file_put_contents($logFile, $line, FILE_APPEND);
}
