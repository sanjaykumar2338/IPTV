<?php

if (!function_exists('provider_is_valid_stream_url')) {
    function provider_is_valid_stream_url(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string)$parts['scheme']);
        return in_array($scheme, ['http', 'https'], true);
    }
}

if (!function_exists('provider_status_message')) {
    function provider_status_message(int $status): string
    {
        if ($status === 401) return 'Provider unauthorized';
        if ($status === 403) return 'Provider forbidden';
        if ($status === 404) return 'Provider stream missing';
        if ($status === 504) return 'Provider timeout';
        if ($status === 502) return 'Provider upstream error';
        if ($status >= 200 && $status < 300) return 'Provider reachable';
        return 'Provider upstream error';
    }
}

if (!function_exists('provider_validator_redact_context')) {
    function provider_validator_redact_context(array $context): array
    {
        if (function_exists('provider_redact_context')) {
            $safe = provider_redact_context($context);
            return is_array($safe) ? $safe : $context;
        }

        array_walk_recursive($context, static function (&$value, $key): void {
            if (!is_string($value)) {
                return;
            }
            $isSensitiveKey = is_string($key) && (bool)preg_match('/(pass|password|username|user|token|secret|provider_key|source_url|stream_url|url|effective_url)/i', $key);
            if ($isSensitiveKey || strpos($value, '://') !== false) {
                if (function_exists('redact_provider_secret')) {
                    $value = redact_provider_secret($value);
                } else {
                    $value = '****';
                }
            }
        });

        return $context;
    }
}

if (!function_exists('provider_mask_url')) {
    function provider_mask_url(string $url): string
    {
        if (!is_string($url) || trim($url) === '') {
            return '';
        }

        if (function_exists('redact_provider_secret')) {
            return redact_provider_secret($url);
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return '****';
        }
        $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
        $host = strtolower((string)$parts['host']);
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        return "{$scheme}://{$host}{$port}/****";
    }
}

if (!function_exists('provider_log_event')) {
    function provider_log_event(string $message, array $context = []): void
    {
        $safeContext = provider_validator_redact_context($context);

        if (function_exists('import_log')) {
            import_log($message, $safeContext);
            return;
        }

        error_log($message . ' ' . json_encode($safeContext, JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('provider_probe_request')) {
    function provider_probe_request(string $url, string $method = 'GET', array $options = []): array
    {
        $timeout = (int)($options['timeout'] ?? 15);
        $connectTimeout = (int)($options['connect_timeout'] ?? 8);
        $userAgent = (string)($options['user_agent'] ?? 'Mozilla/5.0 (IPTV Provider Validator)');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 6,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Connection: keep-alive'
            ],
        ]);

        if (strtoupper($method) === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        } else {
            // Pull a tiny body slice so we can detect provider JSON errors quickly.
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RANGE, '0-2047');
        }

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        $effectiveUrl = (string)(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'curl_errno' => $errno,
            'curl_error' => $error,
            'content_type' => $contentType,
            'effective_url' => $effectiveUrl
        ];
    }
}

if (!function_exists('provider_extract_payload_error')) {
    function provider_extract_payload_error(string $body): ?array
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return null;
        }

        $firstChar = $trimmed[0];
        if ($firstChar !== '{' && $firstChar !== '[') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (!isset($decoded['error']) && !isset($decoded['message']) && !isset($decoded['status'])) {
            return null;
        }

        return [
            'status' => isset($decoded['status']) ? (int)$decoded['status'] : 0,
            'error' => (string)($decoded['error'] ?? ''),
            'message' => (string)($decoded['message'] ?? '')
        ];
    }
}

if (!function_exists('provider_normalize_status')) {
    function provider_normalize_status(int $status): int
    {
        if ($status === 401 || $status === 403 || $status === 404) {
            return $status;
        }

        if ($status === 408) {
            return 504;
        }

        if ($status >= 500) {
            return 502;
        }

        if ($status >= 200 && $status < 300) {
            return $status;
        }

        return 502;
    }
}

if (!function_exists('provider_extract_sample')) {
    function provider_extract_sample(string $body): string
    {
        if ($body === '') {
            return '';
        }

        $trimmed = ltrim($body);
        if (str_starts_with($trimmed, '#EXTM3U')) {
            $firstLine = strtok($trimmed, "\r\n");
            return substr((string)$firstLine, 0, 160);
        }

        $ascii = preg_replace('/[^\x20-\x7E]+/', ' ', $trimmed);
        $ascii = trim((string)$ascii);
        return substr($ascii, 0, 160);
    }
}

if (!function_exists('provider_validate_url')) {
    function provider_validate_url(string $url, array $options = []): array
    {
        $url = trim($url);
        $maskedUrl = provider_mask_url($url);

        if (!provider_is_valid_stream_url($url)) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Invalid URL. Use a full http/https stream URL.',
                'url' => $maskedUrl,
                'sample' => '',
                'content_type' => '',
                'effective_url' => ''
            ];
        }

        $head = provider_probe_request($url, 'HEAD', $options);
        $get = provider_probe_request($url, 'GET', $options);

        $curlErrNo = $get['curl_errno'] ?: $head['curl_errno'];
        $curlErr = $get['curl_error'] ?: $head['curl_error'];

        if ($curlErrNo !== 0) {
            $status = in_array($curlErrNo, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST], true) ? 504 : 502;
            return [
                'ok' => false,
                'status' => $status,
                'message' => provider_status_message($status),
                'url' => $maskedUrl,
                'sample' => '',
                'content_type' => '',
                'effective_url' => provider_mask_url((string)($get['effective_url'] ?: $head['effective_url'])),
                'curl_error' => $curlErr
            ];
        }

        $status = $get['status'] > 0 ? (int)$get['status'] : (int)$head['status'];
        $status = provider_normalize_status($status);

        $payloadError = provider_extract_payload_error($get['body']);
        if ($payloadError !== null) {
            $payloadStatus = provider_normalize_status((int)($payloadError['status'] ?: 502));
            $status = $payloadStatus;
        }

        $ok = $status >= 200 && $status < 300 && $payloadError === null;
        $message = $ok ? 'Provider reachable (200 OK)' : provider_status_message($status);
        if (!$ok && $status === 401) {
            $message = 'Unauthorized - provider rejected credentials';
        }

        $sample = provider_extract_sample($get['body']);
        if (function_exists('redact_provider_secret')) {
            $sample = redact_provider_secret($sample);
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'message' => $message,
            'url' => $maskedUrl,
            'sample' => $sample,
            'content_type' => (string)$get['content_type'],
            'effective_url' => provider_mask_url((string)($get['effective_url'] ?: $head['effective_url'])),
            'payload_error' => $payloadError
        ];
    }
}

if (!function_exists('provider_guess_entry_type')) {
    function provider_guess_entry_type(array $entry): string
    {
        $group = strtolower(trim((string)($entry['category'] ?? '')));
        $title = (string)($entry['name'] ?? '');

        $isSeriesByName = (bool)preg_match('/\bS\d{1,2}E\d{1,2}\b/i', $title)
            || (bool)preg_match('/\bSeason\s*\d+\s*Episode\s*\d+/i', $title);

        if (strpos($group, 'series') !== false || $isSeriesByName) {
            return 'series';
        }

        if (strpos($group, 'movie') !== false || strpos($group, 'vod') !== false || strpos($group, 'film') !== false) {
            return 'movie';
        }

        return 'live';
    }
}

if (!function_exists('provider_pick_sample_streams')) {
    function provider_pick_sample_streams(array $entries, int $firstWindow = 5, int $scanLimit = 120): array
    {
        $wanted = ['live', 'movie', 'series'];
        $picked = [];
        $seenUrls = [];

        $pickFrom = static function (array $slice) use (&$picked, &$seenUrls, $wanted): void {
            foreach ($slice as $entry) {
                $url = trim((string)($entry['stream_url'] ?? ''));
                if ($url === '' || !provider_is_valid_stream_url($url)) {
                    continue;
                }

                if (isset($seenUrls[$url])) {
                    continue;
                }

                $type = provider_guess_entry_type($entry);
                if (!in_array($type, $wanted, true)) {
                    $type = 'live';
                }

                $seenUrls[$url] = true;
                if (!isset($picked[$type])) {
                    $picked[$type] = [
                        'type' => $type,
                        'name' => (string)($entry['name'] ?? ''),
                        'url' => $url
                    ];
                }

                if (count($picked) === count($wanted)) {
                    break;
                }
            }
        };

        $pickFrom(array_slice($entries, 0, $firstWindow));

        if (count($picked) < count($wanted)) {
            $pickFrom(array_slice($entries, $firstWindow, max(0, $scanLimit - $firstWindow)));
        }

        if (empty($picked)) {
            return [];
        }

        return array_values($picked);
    }
}

if (!function_exists('provider_precheck_entries')) {
    function provider_precheck_entries(array $entries): array
    {
        $targets = provider_pick_sample_streams($entries, 5, 120);
        if (empty($targets)) {
            return [
                'ok' => false,
                'message' => 'Import blocked: no valid stream URL found in M3U samples.',
                'results' => []
            ];
        }

        $results = [];
        $okCount = 0;
        $firstFailure = null;

        foreach ($targets as $target) {
            $probe = provider_validate_url((string)$target['url']);
            $probe['type'] = $target['type'];
            $probe['name'] = $target['name'];
            $results[] = $probe;

            if (!empty($probe['ok'])) {
                $okCount++;
                continue;
            }

            if ($firstFailure === null) {
                $firstFailure = $probe;
            }
        }

        provider_log_event('PROVIDER PRECHECK RESULTS', [
            'targets' => count($targets),
            'ok_count' => $okCount,
            'results' => $results
        ]);

        if ($okCount === 0 && $firstFailure !== null) {
            $status = (int)($firstFailure['status'] ?? 502);
            $statusText = provider_status_message($status);
            return [
                'ok' => false,
                'message' => "Import blocked: Provider returned {$status} {$statusText}. Upload a valid M3U.",
                'results' => $results
            ];
        }

        return [
            'ok' => true,
            'message' => 'Provider pre-check passed.',
            'results' => $results
        ];
    }
}
