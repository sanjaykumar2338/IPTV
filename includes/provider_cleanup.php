<?php

if (!function_exists('provider_cleanup_log')) {
    function provider_cleanup_log(string $message, array $context = []): void
    {
        $safeContext = function_exists('provider_redact_context')
            ? provider_redact_context($context)
            : $context;

        if (function_exists('import_log')) {
            import_log($message, is_array($safeContext) ? $safeContext : []);
            return;
        }

        error_log($message . ' ' . json_encode($safeContext, JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('provider_key_fingerprint')) {
    function provider_key_fingerprint(string $providerKey): string
    {
        return substr(sha1($providerKey), 0, 16);
    }
}

if (!function_exists('provider_public_host')) {
    function provider_public_host(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $parts = parse_url($value);
            if (!$parts || empty($parts['host'])) {
                return '';
            }

            $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
            $host = strtolower((string)$parts['host']);
            $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
            return "{$scheme}://{$host}{$port}";
        }

        // Already host-like text.
        $trimmed = preg_replace('#^https?://#i', '', $value) ?? $value;
        $trimmed = trim((string)$trimmed, '/');
        return strtolower($trimmed);
    }
}

if (!function_exists('redact_provider_secret')) {
    function redact_provider_secret(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $public = provider_public_host($value);
            return $public !== '' ? ($public . '/****') : '****';
        }

        $value = preg_replace('/([?&](?:username|user|password|pass)=)[^&]*/i', '$1****', $value) ?? $value;
        $value = preg_replace('#/(?:[^/]{1,64})/(?:[^/]{1,64})(?=/|$)#', '/****/****', $value) ?? $value;
        return $value;
    }
}

if (!function_exists('provider_redact_context')) {
    function provider_redact_context($value, string $key = '')
    {
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $itemKey => $itemValue) {
                $safe[$itemKey] = provider_redact_context($itemValue, is_string($itemKey) ? $itemKey : '');
            }
            return $safe;
        }

        if (is_object($value)) {
            return provider_redact_context((array)$value, $key);
        }

        if (!is_string($value)) {
            return $value;
        }

        $sensitiveKey = (bool)preg_match('/(pass|password|username|user|token|secret|provider_key|source_url|stream_url|url|effective_url)/i', $key);
        if ($sensitiveKey) {
            return redact_provider_secret($value);
        }

        if (strpos($value, '://') !== false) {
            return redact_provider_secret($value);
        }

        return $value;
    }
}

if (!function_exists('provider_scope_from_id')) {
    function provider_scope_from_id(int $providerId): string
    {
        return 'provider:' . max(0, $providerId);
    }
}

if (!function_exists('provider_safe_summary')) {
    function provider_safe_summary(array $provider): array
    {
        $providerKey = (string)($provider['provider_key'] ?? '');
        return [
            'id' => (int)($provider['id'] ?? 0),
            'name' => (string)($provider['name'] ?? ''),
            'provider_type' => (string)($provider['provider_type'] ?? ''),
            'host' => provider_public_host((string)($provider['host'] ?? '')),
            'provider_ref' => $providerKey !== '' ? provider_key_fingerprint($providerKey) : '',
        ];
    }
}

if (!function_exists('provider_table_exists')) {
    function provider_table_exists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
            LIMIT 1
        ");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('provider_column_exists')) {
    function provider_column_exists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('provider_ensure_schema')) {
    function provider_ensure_schema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS providers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                provider_type VARCHAR(40) NOT NULL DEFAULT 'm3u',
                host VARCHAR(190) NULL,
                source_url TEXT NULL,
                provider_key VARCHAR(190) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_provider_key (provider_key),
                INDEX idx_provider_host (host)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $tables = ['channels', 'movies', 'series', 'episodes'];
        foreach ($tables as $table) {
            if (!provider_table_exists($pdo, $table)) {
                continue;
            }

            if (!provider_column_exists($pdo, $table, 'provider_id')) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN provider_id INT NULL");
            }

            if (!provider_has_index($pdo, $table, 'idx_' . $table . '_provider')) {
                $pdo->exec("ALTER TABLE {$table} ADD INDEX idx_{$table}_provider (provider_id)");
            }
        }
    }
}

if (!function_exists('provider_has_index')) {
    function provider_has_index(PDO $pdo, string $table, string $indexName): bool
    {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $indexName]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('provider_get_by_id')) {
    function provider_get_by_id(PDO $pdo, int $providerId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM providers WHERE id = ? LIMIT 1");
        $stmt->execute([$providerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('provider_build_key_from_url')) {
    function provider_build_key_from_url(string $url): string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
        $host = strtolower((string)$parts['host']);
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = trim((string)($parts['path'] ?? ''), '/');
        $segments = $path === '' ? [] : array_values(array_filter(explode('/', $path), 'strlen'));

        // For Xtream-like URLs we keep account scope to avoid mixing credentials on same host.
        $scopeParts = array_slice($segments, 0, 2);
        $scope = empty($scopeParts) ? '' : '/' . implode('/', $scopeParts);

        return "{$scheme}://{$host}{$port}{$scope}";
    }
}

if (!function_exists('provider_normalize_key')) {
    function provider_normalize_key(string $providerKey): string
    {
        $providerKey = trim($providerKey);
        if ($providerKey === '') {
            return 'key:' . sha1((string)microtime(true));
        }

        if (strlen($providerKey) <= 190) {
            return $providerKey;
        }

        return 'hash:' . sha1($providerKey);
    }
}

if (!function_exists('provider_detect_type_from_url')) {
    function provider_detect_type_from_url(string $url): string
    {
        $urlLower = strtolower($url);
        if (strpos($urlLower, '.m3u8') !== false || strpos($urlLower, '.m3u') !== false) {
            return 'm3u';
        }
        if (strpos($urlLower, '/live/') !== false || strpos($urlLower, '/movie/') !== false || strpos($urlLower, '/series/') !== false) {
            return 'xtream';
        }
        return 'm3u';
    }
}

if (!function_exists('provider_detect_from_entries')) {
    function provider_detect_from_entries(array $entries, string $fallbackName = 'Imported Provider'): array
    {
        $firstUrl = '';
        foreach ($entries as $entry) {
            $candidate = trim((string)($entry['stream_url'] ?? ''));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_URL)) {
                $firstUrl = $candidate;
                break;
            }
        }

        if ($firstUrl === '') {
            return [
            'name' => $fallbackName,
            'provider_type' => 'm3u',
            'host' => '',
            'source_url' => '',
            'provider_key' => provider_normalize_key('file:' . sha1($fallbackName)),
        ];
    }

        $parts = parse_url($firstUrl);
        $host = strtolower((string)($parts['host'] ?? ''));
        $providerType = provider_detect_type_from_url($firstUrl);
        $providerKey = provider_build_key_from_url($firstUrl);

        return [
            'name' => $host !== '' ? $host : $fallbackName,
            'provider_type' => $providerType,
            'host' => $host,
            'source_url' => $firstUrl,
            'provider_key' => provider_normalize_key($providerKey !== '' ? $providerKey : ('url:' . sha1($firstUrl))),
        ];
    }
}

if (!function_exists('provider_get_or_create')) {
    function provider_get_or_create(PDO $pdo, array $meta): array
    {
        $providerKey = provider_normalize_key((string)($meta['provider_key'] ?? ''));
        $stmt = $pdo->prepare("SELECT * FROM providers WHERE provider_key = ? LIMIT 1");
        $stmt->execute([$providerKey]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $update = $pdo->prepare("
                UPDATE providers
                SET name = ?, provider_type = ?, host = ?, source_url = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update->execute([
                $meta['name'],
                $meta['provider_type'],
                $meta['host'],
                $meta['source_url'],
                $existing['id']
            ]);
            return provider_get_by_id($pdo, (int)$existing['id']) ?? $existing;
        }

        $insert = $pdo->prepare("
            INSERT INTO providers (name, provider_type, host, source_url, provider_key, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $insert->execute([
            $meta['name'],
            $meta['provider_type'],
            $meta['host'],
            $meta['source_url'],
            $providerKey
        ]);

        $providerId = (int)$pdo->lastInsertId();
        $created = provider_get_by_id($pdo, $providerId);
        if (!$created) {
            throw new RuntimeException('Failed to create provider record.');
        }
        return $created;
    }
}

if (!function_exists('provider_get_counts')) {
    function provider_get_counts(PDO $pdo, int $providerId): array
    {
        $counts = [
            'channels' => 0,
            'movies' => 0,
            'series' => 0,
            'episodes' => 0,
            'watch_history' => 0,
            'my_list' => 0,
            'channel_favorites' => 0,
            'epg_programs' => 0,
        ];

        if (provider_table_exists($pdo, 'channels') && provider_column_exists($pdo, 'channels', 'provider_id')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM channels WHERE provider_id = ?");
            $stmt->execute([$providerId]);
            $counts['channels'] = (int)$stmt->fetchColumn();
        }

        if (provider_table_exists($pdo, 'movies') && provider_column_exists($pdo, 'movies', 'provider_id')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM movies WHERE provider_id = ?");
            $stmt->execute([$providerId]);
            $counts['movies'] = (int)$stmt->fetchColumn();
        }

        if (provider_table_exists($pdo, 'series') && provider_column_exists($pdo, 'series', 'provider_id')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM series WHERE provider_id = ?");
            $stmt->execute([$providerId]);
            $counts['series'] = (int)$stmt->fetchColumn();
        }

        if (provider_table_exists($pdo, 'episodes')) {
            if (provider_column_exists($pdo, 'episodes', 'provider_id') && provider_table_exists($pdo, 'series')) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM episodes e
                    LEFT JOIN series s ON s.id = e.series_id
                    WHERE e.provider_id = ? OR s.provider_id = ?
                ");
                $stmt->execute([$providerId, $providerId]);
            } elseif (provider_table_exists($pdo, 'series') && provider_column_exists($pdo, 'series', 'provider_id')) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM episodes e
                    INNER JOIN series s ON s.id = e.series_id
                    WHERE s.provider_id = ?
                ");
                $stmt->execute([$providerId]);
            } else {
                $stmt = null;
            }

            if ($stmt) {
                $counts['episodes'] = (int)$stmt->fetchColumn();
            }
        }

        if (provider_table_exists($pdo, 'watch_history') && provider_table_exists($pdo, 'movies')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM watch_history wh
                INNER JOIN movies m ON wh.content_type = 'movie' AND wh.content_id = m.id
                WHERE m.provider_id = ?
            ");
            $stmt->execute([$providerId]);
            $counts['watch_history'] += (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM watch_history wh
                INNER JOIN series s ON wh.content_type = 'series' AND wh.content_id = s.id
                WHERE s.provider_id = ?
            ");
            $stmt->execute([$providerId]);
            $counts['watch_history'] += (int)$stmt->fetchColumn();
        }

        if (provider_table_exists($pdo, 'my_list') && provider_table_exists($pdo, 'movies')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM my_list ml
                INNER JOIN movies m ON ml.content_type = 'movie' AND ml.content_id = m.id
                WHERE m.provider_id = ?
            ");
            $stmt->execute([$providerId]);
            $counts['my_list'] += (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM my_list ml
                INNER JOIN series s ON ml.content_type = 'series' AND ml.content_id = s.id
                WHERE s.provider_id = ?
            ");
            $stmt->execute([$providerId]);
            $counts['my_list'] += (int)$stmt->fetchColumn();
        }

        if (provider_table_exists($pdo, 'channel_favorites') && provider_table_exists($pdo, 'channels')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM channel_favorites cf
                INNER JOIN channels c ON c.id = cf.channel_id
                WHERE c.provider_id = ?
            ");
            $stmt->execute([$providerId]);
            $counts['channel_favorites'] = (int)$stmt->fetchColumn();
        }

        if (provider_table_exists($pdo, 'epg_programs') && provider_table_exists($pdo, 'channels')) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM epg_programs ep
                INNER JOIN channels c ON c.id = ep.channel_id
                WHERE c.provider_id = ?
            ");
            $stmt->execute([$providerId]);
            $counts['epg_programs'] = (int)$stmt->fetchColumn();
        }

        return $counts;
    }
}

if (!function_exists('wipeProviderData')) {
    function wipeProviderData(PDO $pdo, int $providerId, bool $manageTransaction = true): array
    {
        if ($providerId <= 0) {
            throw new InvalidArgumentException('Invalid provider id.');
        }

        $provider = provider_get_by_id($pdo, $providerId);
        if (!$provider) {
            throw new RuntimeException('Provider not found.');
        }

        $startedTx = false;
        if ($manageTransaction && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTx = true;
        }

        try {
            $before = provider_get_counts($pdo, $providerId);

            if (provider_table_exists($pdo, 'watch_history')) {
                $stmt = $pdo->prepare("
                    DELETE wh FROM watch_history wh
                    INNER JOIN series s ON wh.content_type = 'series' AND wh.content_id = s.id
                    WHERE s.provider_id = ?
                ");
                $stmt->execute([$providerId]);

                $stmt = $pdo->prepare("
                    DELETE wh FROM watch_history wh
                    INNER JOIN movies m ON wh.content_type = 'movie' AND wh.content_id = m.id
                    WHERE m.provider_id = ?
                ");
                $stmt->execute([$providerId]);
            }

            if (provider_table_exists($pdo, 'my_list')) {
                $stmt = $pdo->prepare("
                    DELETE ml FROM my_list ml
                    INNER JOIN series s ON ml.content_type = 'series' AND ml.content_id = s.id
                    WHERE s.provider_id = ?
                ");
                $stmt->execute([$providerId]);

                $stmt = $pdo->prepare("
                    DELETE ml FROM my_list ml
                    INNER JOIN movies m ON ml.content_type = 'movie' AND ml.content_id = m.id
                    WHERE m.provider_id = ?
                ");
                $stmt->execute([$providerId]);
            }

            if (provider_table_exists($pdo, 'channel_favorites')) {
                $stmt = $pdo->prepare("
                    DELETE cf FROM channel_favorites cf
                    INNER JOIN channels c ON c.id = cf.channel_id
                    WHERE c.provider_id = ?
                ");
                $stmt->execute([$providerId]);
            }

            if (provider_table_exists($pdo, 'epg_programs')) {
                $stmt = $pdo->prepare("
                    DELETE ep FROM epg_programs ep
                    INNER JOIN channels c ON c.id = ep.channel_id
                    WHERE c.provider_id = ?
                ");
                $stmt->execute([$providerId]);
            }

            if (provider_table_exists($pdo, 'episodes') && provider_column_exists($pdo, 'episodes', 'provider_id')) {
                $stmt = $pdo->prepare("DELETE FROM episodes WHERE provider_id = ?");
                $stmt->execute([$providerId]);
            }

            if (provider_table_exists($pdo, 'episodes') && provider_table_exists($pdo, 'series')) {
                $stmt = $pdo->prepare("
                    DELETE e FROM episodes e
                    INNER JOIN series s ON s.id = e.series_id
                    WHERE s.provider_id = ?
                ");
                $stmt->execute([$providerId]);
            }

            if (provider_table_exists($pdo, 'series')) {
                $stmt = $pdo->prepare("DELETE FROM series WHERE provider_id = ?");
                $stmt->execute([$providerId]);
            }

            if (provider_table_exists($pdo, 'movies')) {
                $stmt = $pdo->prepare("DELETE FROM movies WHERE provider_id = ?");
                $stmt->execute([$providerId]);
            }

            if (provider_table_exists($pdo, 'channels')) {
                $stmt = $pdo->prepare("DELETE FROM channels WHERE provider_id = ?");
                $stmt->execute([$providerId]);
            }

            $after = provider_get_counts($pdo, $providerId);
            $deleted = [];
            foreach ($before as $key => $value) {
                $deleted[$key] = max(0, (int)$value - (int)($after[$key] ?? 0));
            }

            provider_cleanup_log('PROVIDER DATA WIPE', [
                'provider_id' => $providerId,
                'provider' => provider_safe_summary($provider),
                'deleted' => $deleted
            ]);

            if ($startedTx) {
                $pdo->commit();
            }

            return [
                'provider' => $provider,
                'deleted' => $deleted
            ];
        } catch (Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('deleteProviderFully')) {
    function deleteProviderFully(PDO $pdo, int $providerId, array $guard = []): array
    {
        if ($providerId <= 0) {
            throw new InvalidArgumentException('Invalid provider id.');
        }

        $provider = provider_get_by_id($pdo, $providerId);
        if (!$provider) {
            throw new RuntimeException('Provider not found.');
        }

        $expectedHost = trim((string)($guard['expected_host'] ?? ''));
        if ($expectedHost !== '' && strcasecmp((string)$provider['host'], $expectedHost) !== 0) {
            throw new RuntimeException('Safety check failed: host mismatch for provider delete.');
        }

        $expectedRef = trim((string)($guard['expected_ref'] ?? ''));
        if ($expectedRef === '') {
            throw new RuntimeException('Safety check failed: provider ref is required.');
        }

        $actualRef = provider_key_fingerprint((string)$provider['provider_key']);
        if (!hash_equals($actualRef, $expectedRef)) {
            throw new RuntimeException('Safety check failed: provider ref mismatch.');
        }

        $startedTx = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTx = true;
        }

        try {
            $wipeSummary = wipeProviderData($pdo, $providerId, false);
            $stmt = $pdo->prepare("DELETE FROM providers WHERE id = ?");
            $stmt->execute([$providerId]);
            $providerDeleted = (int)$stmt->rowCount();

            if ($startedTx) {
                $pdo->commit();
            }

            provider_cleanup_log('PROVIDER FULL DELETE', [
                'provider_id' => $providerId,
                'provider' => provider_safe_summary($provider),
                'provider_deleted' => $providerDeleted,
                'deleted' => $wipeSummary['deleted'] ?? []
            ]);

            return [
                'provider' => $provider,
                'provider_deleted' => $providerDeleted,
                'deleted' => $wipeSummary['deleted'] ?? []
            ];
        } catch (Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('provider_list_with_counts')) {
    function provider_list_with_counts(PDO $pdo): array
    {
        if (!provider_table_exists($pdo, 'providers')) {
            return [];
        }

        $sql = "
            SELECT
                p.*,
                (SELECT COUNT(*) FROM channels c WHERE c.provider_id = p.id) AS channels_count,
                (SELECT COUNT(*) FROM movies m WHERE m.provider_id = p.id) AS movies_count,
                (SELECT COUNT(*) FROM series s WHERE s.provider_id = p.id) AS series_count,
                (
                    SELECT COUNT(*)
                    FROM episodes e
                    LEFT JOIN series s2 ON s2.id = e.series_id
                    WHERE e.provider_id = p.id OR s2.provider_id = p.id
                ) AS episodes_count
            FROM providers p
            ORDER BY p.updated_at DESC, p.id DESC
        ";

        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('wipeAllCatalogData')) {
    function wipeAllCatalogData(PDO $pdo): array
    {
        $startedTx = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTx = true;
        }

        try {
            $summary = [
                'channels' => 0,
                'movies' => 0,
                'series' => 0,
                'episodes' => 0,
                'providers' => 0
            ];

            if (provider_table_exists($pdo, 'watch_history')) {
                $stmt = $pdo->prepare("DELETE FROM watch_history WHERE content_type IN ('movie', 'series')");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'my_list')) {
                $stmt = $pdo->prepare("DELETE FROM my_list WHERE content_type IN ('movie', 'series')");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'episodes')) {
                $stmt = $pdo->prepare("DELETE FROM episodes");
                $stmt->execute();
                $summary['episodes'] = (int)$stmt->rowCount();
            }

            if (provider_table_exists($pdo, 'series')) {
                $stmt = $pdo->prepare("DELETE FROM series");
                $stmt->execute();
                $summary['series'] = (int)$stmt->rowCount();
            }

            if (provider_table_exists($pdo, 'movies')) {
                $stmt = $pdo->prepare("DELETE FROM movies");
                $stmt->execute();
                $summary['movies'] = (int)$stmt->rowCount();
            }

            if (provider_table_exists($pdo, 'channels')) {
                $stmt = $pdo->prepare("DELETE FROM channels");
                $stmt->execute();
                $summary['channels'] = (int)$stmt->rowCount();
            }

            if (provider_table_exists($pdo, 'providers')) {
                $stmt = $pdo->prepare("DELETE FROM providers");
                $stmt->execute();
                $summary['providers'] = (int)$stmt->rowCount();
            }

            if ($startedTx) {
                $pdo->commit();
            }

            provider_cleanup_log('CATALOG FULL WIPE', $summary);
            return $summary;
        } catch (Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('legacyCatalogCounts')) {
    function legacyCatalogCounts(PDO $pdo): array
    {
        $counts = [
            'channels' => 0,
            'movies' => 0,
            'series' => 0,
            'episodes' => 0
        ];

        foreach (array_keys($counts) as $table) {
            if (!provider_table_exists($pdo, $table) || !provider_column_exists($pdo, $table, 'provider_id')) {
                continue;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE provider_id IS NULL");
            $stmt->execute();
            $counts[$table] = (int)$stmt->fetchColumn();
        }

        return $counts;
    }
}

if (!function_exists('wipeLegacyUnassignedCatalog')) {
    function wipeLegacyUnassignedCatalog(PDO $pdo, bool $manageTransaction = true): array
    {
        $startedTx = false;
        if ($manageTransaction && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTx = true;
        }

        try {
            $before = legacyCatalogCounts($pdo);

            if (provider_table_exists($pdo, 'watch_history')) {
                $stmt = $pdo->prepare("
                    DELETE wh FROM watch_history wh
                    INNER JOIN series s ON wh.content_type = 'series' AND wh.content_id = s.id
                    WHERE s.provider_id IS NULL
                ");
                $stmt->execute();

                $stmt = $pdo->prepare("
                    DELETE wh FROM watch_history wh
                    INNER JOIN movies m ON wh.content_type = 'movie' AND wh.content_id = m.id
                    WHERE m.provider_id IS NULL
                ");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'my_list')) {
                $stmt = $pdo->prepare("
                    DELETE ml FROM my_list ml
                    INNER JOIN series s ON ml.content_type = 'series' AND ml.content_id = s.id
                    WHERE s.provider_id IS NULL
                ");
                $stmt->execute();

                $stmt = $pdo->prepare("
                    DELETE ml FROM my_list ml
                    INNER JOIN movies m ON ml.content_type = 'movie' AND ml.content_id = m.id
                    WHERE m.provider_id IS NULL
                ");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'channel_favorites')) {
                $stmt = $pdo->prepare("
                    DELETE cf FROM channel_favorites cf
                    INNER JOIN channels c ON c.id = cf.channel_id
                    WHERE c.provider_id IS NULL
                ");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'epg_programs')) {
                $stmt = $pdo->prepare("
                    DELETE ep FROM epg_programs ep
                    INNER JOIN channels c ON c.id = ep.channel_id
                    WHERE c.provider_id IS NULL
                ");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'episodes') && provider_column_exists($pdo, 'episodes', 'provider_id')) {
                $stmt = $pdo->prepare("DELETE FROM episodes WHERE provider_id IS NULL");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'episodes') && provider_table_exists($pdo, 'series') && provider_column_exists($pdo, 'series', 'provider_id')) {
                $stmt = $pdo->prepare("
                    DELETE e FROM episodes e
                    INNER JOIN series s ON s.id = e.series_id
                    WHERE s.provider_id IS NULL
                ");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'series') && provider_column_exists($pdo, 'series', 'provider_id')) {
                $stmt = $pdo->prepare("DELETE FROM series WHERE provider_id IS NULL");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'movies') && provider_column_exists($pdo, 'movies', 'provider_id')) {
                $stmt = $pdo->prepare("DELETE FROM movies WHERE provider_id IS NULL");
                $stmt->execute();
            }

            if (provider_table_exists($pdo, 'channels') && provider_column_exists($pdo, 'channels', 'provider_id')) {
                $stmt = $pdo->prepare("DELETE FROM channels WHERE provider_id IS NULL");
                $stmt->execute();
            }

            $after = legacyCatalogCounts($pdo);
            $deleted = [];
            foreach ($before as $key => $value) {
                $deleted[$key] = max(0, (int)$value - (int)($after[$key] ?? 0));
            }

            provider_cleanup_log('LEGACY UNASSIGNED WIPE', $deleted);

            if ($startedTx) {
                $pdo->commit();
            }

            return $deleted;
        } catch (Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('replaceProviderCatalogAtomically')) {
    function replaceProviderCatalogAtomically(PDO $pdo, int $providerId, array $entries, array $options = []): array
    {
        if ($providerId <= 0) {
            throw new InvalidArgumentException('Invalid provider id.');
        }

        if (!function_exists('import_vod_from_m3u')) {
            throw new RuntimeException('Import helper is missing.');
        }

        $provider = provider_get_by_id($pdo, $providerId);
        if (!$provider) {
            throw new RuntimeException('Provider not found.');
        }

        $providerScope = trim((string)($options['provider_scope'] ?? provider_scope_from_id($providerId)));
        $commitEvery = (int)($options['commit_every'] ?? 100);
        if ($commitEvery <= 0) {
            $commitEvery = 100;
        }

        $oldCounts = provider_get_counts($pdo, $providerId);

        $startedTx = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTx = true;
        }
        try {
            $wipeSummary = wipeProviderData($pdo, $providerId, false);

            $importStats = import_vod_from_m3u($pdo, $entries, $commitEvery, [
                'provider_id' => $providerId,
                'provider_scope' => $providerScope,
                'manage_transaction' => false
            ]);

            $loadedRows =
                (int)($importStats['live_imported'] ?? 0) +
                (int)($importStats['movies_inserted'] ?? 0) +
                (int)($importStats['movies_updated'] ?? 0) +
                (int)($importStats['series_inserted'] ?? 0) +
                (int)($importStats['series_updated'] ?? 0) +
                (int)($importStats['episodes_inserted'] ?? 0) +
                (int)($importStats['episodes_updated'] ?? 0);

            if ($loadedRows <= 0) {
                throw new RuntimeException('Atomic replace failed: no catalog rows imported.');
            }

            $newCounts = provider_get_counts($pdo, $providerId);
            if ($startedTx) {
                $pdo->commit();
            }

            provider_cleanup_log('PROVIDER ATOMIC REPLACE', [
                'provider' => provider_safe_summary($provider),
                'old_counts' => $oldCounts,
                'wipe_deleted' => $wipeSummary['deleted'] ?? [],
                'import_stats' => $importStats,
                'new_counts' => $newCounts
            ]);

            return [
                'provider' => $provider,
                'old_counts' => $oldCounts,
                'wipe' => $wipeSummary,
                'import' => $importStats,
                'new_counts' => $newCounts
            ];
        } catch (Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            provider_cleanup_log('PROVIDER ATOMIC REPLACE FAILED', [
                'provider' => provider_safe_summary($provider),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
