<?php
/**
 * VOD-aware M3U import helpers.
 * Shared by admin imports (API + UI).
 */

function normalize_group($group = ''): string {
    return strtolower(trim((string)$group));
}

function build_source_id(?string $tvgId, string $title, string $url): string {
    $tvgId = trim($tvgId ?? '');
    if ($tvgId !== '') return $tvgId;
    return hash('sha1', $title . '|' . $url);
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
        INSERT INTO movies (title, genre, poster_url, stream_url, source_id, created_at, updated_at)
        VALUES (:title, :genre, :poster, :stream, :source_id, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            genre = VALUES(genre),
            poster_url = VALUES(poster_url),
            stream_url = VALUES(stream_url),
            updated_at = VALUES(updated_at)
    ");
    $stmt->execute([
        ':title' => $entry['title'],
        ':genre' => $entry['genre'],
        ':poster' => $entry['poster_url'],
        ':stream' => $entry['stream_url'],
        ':source_id' => $entry['source_id'],
    ]);

    $inserted = $stmt->rowCount() === 1;
    $id = $inserted ? (int)$pdo->lastInsertId() : get_id_by_source($pdo, 'movies', $entry['source_id']);
    return ['inserted' => $inserted, 'id' => $id];
}

function upsert_series(PDO $pdo, array $entry): array {
    $stmt = $pdo->prepare("
        INSERT INTO series (title, genre, poster_url, source_id, created_at, updated_at)
        VALUES (:title, :genre, :poster, :source_id, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            genre = VALUES(genre),
            poster_url = VALUES(poster_url),
            updated_at = VALUES(updated_at)
    ");
    $stmt->execute([
        ':title' => $entry['title'],
        ':genre' => $entry['genre'],
        ':poster' => $entry['poster_url'],
        ':source_id' => $entry['source_id'],
    ]);

    $inserted = $stmt->rowCount() === 1;
    $id = $inserted ? (int)$pdo->lastInsertId() : get_id_by_source($pdo, 'series', $entry['source_id']);
    return ['inserted' => $inserted, 'id' => $id];
}

function upsert_episode(PDO $pdo, array $entry): array {
    $stmt = $pdo->prepare("
        INSERT INTO episodes (series_id, season_number, episode_number, title, stream_url, thumbnail_url, source_id, created_at, updated_at)
        VALUES (:series_id, :season, :episode, :title, :stream, :thumb, :source_id, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            stream_url = VALUES(stream_url),
            thumbnail_url = VALUES(thumbnail_url),
            updated_at = VALUES(updated_at)
    ");
    $stmt->execute([
        ':series_id' => $entry['series_id'],
        ':season' => $entry['season_number'],
        ':episode' => $entry['episode_number'],
        ':title' => $entry['title'],
        ':stream' => $entry['stream_url'],
        ':thumb' => $entry['thumbnail_url'],
        ':source_id' => $entry['source_id'],
    ]);

    $inserted = $stmt->rowCount() === 1;
    return ['inserted' => $inserted];
}

function import_vod_from_m3u(PDO $pdo, array $entries, int $commitEvery = 100): array {
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

    // Chunked transaction to reduce lock time for huge imports
    $pdo->beginTransaction();
    $processedSinceCommit = 0;

    // Prepared statements reused in loops
    $liveInsert = $pdo->prepare("
        INSERT INTO channels (name, stream_url, category, logo_url, tvg_id, drm_type, license_key, license_url, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $liveExists = $pdo->prepare("SELECT id FROM channels WHERE stream_url = ? LIMIT 1");

    foreach ($entries as $index => $channel) {
        $group = normalize_group($channel['category'] ?? '');
        $title = $channel['name'] ?? 'Untitled';
        $logo = $channel['logo'] ?? '';
        $stream = $channel['stream_url'] ?? '';
        $tvgId = $channel['tvg_id'] ?? '';

        try {
            if (strpos($group, 'movie') !== false) {
                $sourceId = build_source_id($tvgId, $title, $stream);
                $result = upsert_movie($pdo, [
                    'title' => $title,
                    'genre' => $channel['category'] ?? null,
                    'poster_url' => $logo,
                    'stream_url' => $stream,
                    'source_id' => $sourceId
                ]);
                $stats[$result['inserted'] ? 'movies_inserted' : 'movies_updated']++;
                continue;
            }

            if (strpos($group, 'series') !== false) {
                $meta = parse_series_meta($title);
                $seriesSource = hash('sha1', $meta['series_title']);
                $seriesResult = upsert_series($pdo, [
                    'title' => $meta['series_title'],
                    'genre' => $channel['category'] ?? null,
                    'poster_url' => $logo,
                    'source_id' => $seriesSource
                ]);
                $stats[$seriesResult['inserted'] ? 'series_inserted' : 'series_updated']++;

                $episodeSource = hash('sha1', $seriesResult['id'] . '|' . $meta['season_number'] . '|' . $meta['episode_number'] . '|' . $stream);
                $episodeResult = upsert_episode($pdo, [
                    'series_id' => $seriesResult['id'],
                    'season_number' => $meta['season_number'],
                    'episode_number' => $meta['episode_number'],
                    'title' => $meta['episode_title'],
                    'stream_url' => $stream,
                    'thumbnail_url' => $logo,
                    'source_id' => $episodeSource
                ]);
                $stats[$episodeResult['inserted'] ? 'episodes_inserted' : 'episodes_updated']++;
                continue;
            }

            // Live channel fallback with duplicate guard
            $liveExists->execute([$stream]);
            if ($liveExists->fetch()) {
                $stats['live_skipped']++;
                continue;
            }

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
            $stats['live_imported']++;

            // Log occasional progress to error_log for visibility
            if (($stats['live_imported'] + $stats['movies_inserted'] + $stats['series_inserted']) % 1000 === 0) {
                error_log("M3U import progress: " . ($stats['live_imported'] + $stats['movies_inserted'] + $stats['series_inserted']) . " items processed");
            }

            $processedSinceCommit++;
            if ($processedSinceCommit >= $commitEvery) {
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

    $pdo->commit();

    return $stats;
}
