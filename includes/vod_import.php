<?php
/**
 * VOD-aware M3U import helpers.
 * Shared by admin imports (API + UI).
 */

function normalize_group($group = ''): string {
    return strtolower(trim((string)$group));
}

function build_source_id(?string $tvgId, string $title, string $url, string $providerScope = ''): string {
    $tvgId = trim($tvgId ?? '');
    $prefix = $providerScope !== '' ? ($providerScope . '|') : '';
    if ($tvgId !== '') return hash('sha1', $prefix . $tvgId);
    return hash('sha1', $prefix . $title . '|' . $url);
}

function parse_series_meta(string $rawTitle): array {
    $seriesTitle = trim($rawTitle);
    $episodeTitle = $seriesTitle;
    $season = 1;
    $episode = 1;

    $patterns = [
        '/^(.*?)\\s+[Ss](\\d{1,2})\\s*[Ee](\\d{1,2})\\s*(.*)$/',
        '/^(.*?)\\s+(\\d{1,2})x(\\d{1,2})\\s*(.*)$/',
        '/^(.*?)\\s+Season\\s+(\\d{1,2})\\s+Episode\\s+(\\d{1,2})\\s*(.*)$/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $rawTitle, $m)) {
            $seriesTitle = trim($m[1]);
            $season = (int) $m[2];
            $episode = (int) $m[3];
            $episodeTitle = trim($m[4] ?? '') ?: $rawTitle;
            break;
        }
    }

    if ($seriesTitle === '') $seriesTitle = $rawTitle;
    if ($episodeTitle === '') $episodeTitle = $rawTitle;

    return [
        'series_title' => $seriesTitle,
        'season_number' => $season ?: 1,
        'episode_number' => $episode ?: 1,
        'episode_title' => $episodeTitle,
    ];
}

function get_id_by_source(PDO $pdo, string $table, string $sourceId): ?int {
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE source_id = ? LIMIT 1");
    $stmt->execute([$sourceId]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function upsert_movie(PDO $pdo, array $entry): array {
    $stmt = $pdo->prepare("
        INSERT INTO movies (title, genre, poster_url, stream_url, source_id, provider_id)
        VALUES (:title, :genre, :poster, :stream, :source_id, :provider_id)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            genre = VALUES(genre),
            poster_url = VALUES(poster_url),
            stream_url = VALUES(stream_url),
            provider_id = VALUES(provider_id)
    ");
    $stmt->execute([
        ':title' => $entry['title'],
        ':genre' => $entry['genre'],
        ':poster' => $entry['poster_url'],
        ':stream' => $entry['stream_url'],
        ':source_id' => $entry['source_id'],
        ':provider_id' => $entry['provider_id'] ?? null,
    ]);

    $inserted = $stmt->rowCount() === 1;
    $id = $inserted ? (int)$pdo->lastInsertId() : get_id_by_source($pdo, 'movies', $entry['source_id']);
    return ['inserted' => $inserted, 'id' => $id];
}

function upsert_series(PDO $pdo, array $entry): array {
    $stmt = $pdo->prepare("
        INSERT INTO series (title, genre, poster_url, source_id, provider_id)
        VALUES (:title, :genre, :poster, :source_id, :provider_id)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            genre = VALUES(genre),
            poster_url = VALUES(poster_url),
            provider_id = VALUES(provider_id)
    ");
    $stmt->execute([
        ':title' => $entry['title'],
        ':genre' => $entry['genre'],
        ':poster' => $entry['poster_url'],
        ':source_id' => $entry['source_id'],
        ':provider_id' => $entry['provider_id'] ?? null,
    ]);

    $inserted = $stmt->rowCount() === 1;
    $id = $inserted ? (int)$pdo->lastInsertId() : get_id_by_source($pdo, 'series', $entry['source_id']);
    return ['inserted' => $inserted, 'id' => $id];
}

function upsert_episode(PDO $pdo, array $entry): array {
    $stmt = $pdo->prepare("
        INSERT INTO episodes (series_id, season_number, episode_number, title, stream_url, thumbnail_url, source_id, provider_id)
        VALUES (:series_id, :season, :episode, :title, :stream, :thumb, :source_id, :provider_id)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            stream_url = VALUES(stream_url),
            thumbnail_url = VALUES(thumbnail_url),
            provider_id = VALUES(provider_id)
    ");
    $stmt->execute([
        ':series_id' => $entry['series_id'],
        ':season' => $entry['season_number'],
        ':episode' => $entry['episode_number'],
        ':title' => $entry['title'],
        ':stream' => $entry['stream_url'],
        ':thumb' => $entry['thumbnail_url'],
        ':source_id' => $entry['source_id'],
        ':provider_id' => $entry['provider_id'] ?? null,
    ]);

    $inserted = $stmt->rowCount() === 1;
    return ['inserted' => $inserted];
}

function import_vod_from_m3u(PDO $pdo, array $entries, int $commitEvery = 100, array $options = []): array {
    // Keep long imports alive
    @set_time_limit(0);
    @ignore_user_abort(true);

    // Reduce buffering issues if any output happens later
    while (ob_get_level()) { @ob_end_clean(); }

    // Table existence guard to avoid flooding errors when schema is missing
    $tablesList = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    import_log("DB TABLES", ['tables' => $tablesList]);
    $required = ['movies', 'series', 'episodes'];
    $missing = array_values(array_diff($required, $tablesList));
    if ($missing) {
        import_log("FATAL: Missing VOD tables", ['missing' => $missing]);
        return [
            'live_imported' => 0,
            'live_skipped' => 0,
            'movies_inserted' => 0,
            'movies_updated' => 0,
            'series_inserted' => 0,
            'series_updated' => 0,
            'episodes_inserted' => 0,
            'episodes_updated' => 0,
            'errors' => ['Missing tables: ' . implode(', ', $missing)]
        ];
    }

    $stats = [
        'live_imported' => 0,
        'live_skipped' => 0,
        'movies_inserted' => 0,
        'movies_updated' => 0,
        'series_inserted' => 0,
        'series_updated' => 0,
        'episodes_inserted' => 0,
        'episodes_updated' => 0,
        'errors' => []
    ];

    $providerId = isset($options['provider_id']) ? (int)$options['provider_id'] : null;
    if ($providerId !== null && $providerId <= 0) {
        $providerId = null;
    }
    $providerScope = trim((string)($options['provider_scope'] ?? ''));
    if ($providerScope === '' && $providerId !== null) {
        $providerScope = 'provider:' . $providerId;
    }

    $manageTransaction = !isset($options['manage_transaction']) || $options['manage_transaction'] !== false;
    $startedTx = false;

    // Chunked transaction to reduce lock time for huge imports.
    if ($manageTransaction && !$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTx = true;
    }

    $processedSinceCommit = 0;

    // Prepared statements reused in loops
    if ($providerId !== null) {
        $liveInsert = $pdo->prepare("
            INSERT INTO channels (name, stream_url, category, logo_url, tvg_id, drm_type, license_key, license_url, provider_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $liveExists = $pdo->prepare("SELECT id FROM channels WHERE stream_url = ? AND provider_id = ? LIMIT 1");
    } else {
        $liveInsert = $pdo->prepare("
            INSERT INTO channels (name, stream_url, category, logo_url, tvg_id, drm_type, license_key, license_url, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $liveExists = $pdo->prepare("SELECT id FROM channels WHERE stream_url = ? LIMIT 1");
    }

    try {
        foreach ($entries as $index => $channel) {
            $group = normalize_group($channel['category'] ?? '');
            $title = $channel['name'] ?? 'Untitled';
            $logo = $channel['logo'] ?? '';
            $stream = $channel['stream_url'] ?? '';
            $tvgId = $channel['tvg_id'] ?? '';
            // Heuristic flags so VOD still routes even if group-title is missing/incorrect
            // Avoid misclassifying 24x7/live-style channel names as series; rely on explicit season/episode tokens
            $isSeriesByName = (bool) preg_match('/\\bS\\d{1,2}E\\d{1,2}\\b/i', $title)
                || (bool) preg_match('/\\bSeason\\s*\\d+\\s*Episode\\s*\\d+/i', $title);
            $isSeriesGroup = strpos($group, 'series') !== false;
            $isMovieGroup = strpos($group, 'movie') !== false;
            $isSeries = $isSeriesGroup || $isSeriesByName;

            try {
                if ($isMovieGroup && !$isSeries) {
                    $sourceId = build_source_id($tvgId, $title, $stream, $providerScope);
                    $result = upsert_movie($pdo, [
                        'title' => $title,
                        'genre' => $channel['category'] ?? null,
                        'poster_url' => $logo,
                        'stream_url' => $stream,
                        'source_id' => $sourceId,
                        'provider_id' => $providerId
                    ]);
                    $stats[$result['inserted'] ? 'movies_inserted' : 'movies_updated']++;
                    continue;
                }

                if ($isSeries) {
                    $meta = parse_series_meta($title);
                    $seriesSource = hash('sha1', $providerScope . '|' . $meta['series_title']);
                    $seriesResult = upsert_series($pdo, [
                        'title' => $meta['series_title'],
                        'genre' => $channel['category'] ?? null,
                        'poster_url' => $logo,
                        'source_id' => $seriesSource,
                        'provider_id' => $providerId
                    ]);
                    $stats[$seriesResult['inserted'] ? 'series_inserted' : 'series_updated']++;

                    $episodeSource = hash('sha1', $providerScope . '|' . $seriesResult['id'] . '|' . $meta['season_number'] . '|' . $meta['episode_number'] . '|' . $stream);
                    $episodeResult = upsert_episode($pdo, [
                        'series_id' => $seriesResult['id'],
                        'season_number' => $meta['season_number'],
                        'episode_number' => $meta['episode_number'],
                        'title' => $meta['episode_title'],
                        'stream_url' => $stream,
                        'thumbnail_url' => $logo,
                        'source_id' => $episodeSource,
                        'provider_id' => $providerId
                    ]);
                    $stats[$episodeResult['inserted'] ? 'episodes_inserted' : 'episodes_updated']++;
                    continue;
                }

                // Live channel fallback with duplicate guard
                if ($providerId !== null) {
                    $liveExists->execute([$stream, $providerId]);
                } else {
                    $liveExists->execute([$stream]);
                }
                if ($liveExists->fetch()) {
                    $stats['live_skipped']++;
                    continue;
                }

                if ($providerId !== null) {
                    $liveInsert->execute([
                        $title,
                        $stream,
                        $channel['category'] ?? 'General',
                        $logo,
                        $tvgId,
                        $channel['drm']['type'] ?? null,
                        $channel['drm']['license_key'] ?? null,
                        $channel['drm']['license_url'] ?? null,
                        $providerId
                    ]);
                } else {
                    $liveInsert->execute([
                        $title,
                        $stream,
                        $channel['category'] ?? 'General',
                        $logo,
                        $tvgId,
                        $channel['drm']['type'] ?? null,
                        $channel['drm']['license_key'] ?? null,
                        $channel['drm']['license_url'] ?? null
                    ]);
                }
                $stats['live_imported']++;

                // Log occasional progress to error_log for visibility
                if (($stats['live_imported'] + $stats['movies_inserted'] + $stats['series_inserted']) % 1000 === 0) {
                    error_log("M3U import progress: " . ($stats['live_imported'] + $stats['movies_inserted'] + $stats['series_inserted']) . " items processed");
                }

                $processedSinceCommit++;
                if ($startedTx && $processedSinceCommit >= $commitEvery) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                    $processedSinceCommit = 0;
                    import_log("IMPORT PARTIAL COMMIT", [
                        'processed' => $stats['live_imported'] + $stats['movies_inserted'] + $stats['series_inserted'],
                        'mem_mb' => round(memory_get_usage(true)/1024/1024, 1)
                    ]);
                }
            } catch (Exception $e) {
                $stats['errors'][] = "Row {$index}: " . $e->getMessage();
                import_log("ROW FAILED", [
                    'index' => $index,
                    'type' => $group,
                    'title' => $title,
                    'source_id' => $channel['tvg_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($startedTx) {
            $pdo->commit();
        }

        return $stats;
    } catch (Throwable $e) {
        if ($startedTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
