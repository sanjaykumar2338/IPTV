<?php
include '../includes/config.php';
include '../includes/auth.php';
include '../includes/functions.php';
include '../includes/m3u-parser.php';
include '../includes/provider_validator.php';
include '../pages/import_logger.php';
requireAdminAuth();

// Handle export requests
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $category = $_GET['category'] ?? '';
    
    try {
        // Get channels
        $query = "SELECT * FROM channels WHERE 1=1";
        $params = [];
        
        if ($category) {
            $query .= " AND category = ?";
            $params[] = $category;
        }
        
        $query .= " ORDER BY category, name";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($format === 'csv') {
            // Export as CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=channels_export_' . date('Y-m-d') . '.csv');
            
            $output = fopen('php://output', 'w');
            
            // CSV header
            fputcsv($output, [
                'ID', 'Name', 'Category', 'Stream URL', 'Logo URL', 'TVG ID', 
                'DRM Type', 'License Key', 'License URL', 'Views', 'Active', 'Created At'
            ]);
            
            // CSV data
            foreach ($channels as $channel) {
                fputcsv($output, [
                    $channel['id'],
                    $channel['name'],
                    $channel['category'],
                    $channel['stream_url'],
                    $channel['logo_url'],
                    $channel['tvg_id'],
                    $channel['drm_type'],
                    $channel['license_key'],
                    $channel['license_url'],
                    $channel['views'],
                    $channel['is_active'] ? 'Yes' : 'No',
                    $channel['created_at']
                ]);
            }
            
            fclose($output);
            exit;
            
        } elseif ($format === 'json') {
            // Export as JSON
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=channels_export_' . date('Y-m-d') . '.json');
            echo json_encode($channels, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
            
        } else {
            throw new Exception('Unsupported export format');
        }
        
    } catch (Exception $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

// Handle M3U import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['m3u_file'])) {
    $uploadDir = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
    // normalise with trailing slash so file paths are valid
    $uploadDir = rtrim($uploadDir, "/\\") . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }
    $uploadWritable = is_writable($uploadDir);
    // avoid fatal: error_log expects string
    error_log("UPLOAD_DIR_CHECK channels.php dir={$uploadDir} writable=" . ($uploadWritable ? 'yes' : 'no'));
    
    $fileName = time() . '_' . basename($_FILES['m3u_file']['name']);
    $filePath = $uploadDir . $fileName;
    
    if ($uploadWritable && move_uploaded_file($_FILES['m3u_file']['tmp_name'], $filePath)) {
        try {
            import_log("IMPORT START (channels.php form)");

            import_log("PHP INFO", [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
            ]);
            import_log("FILES SNAPSHOT", [
                '_FILES_keys' => array_keys($_FILES ?? []),
                '_POST_keys' => array_keys($_POST ?? []),
            ]);

            @set_time_limit(0);
            @ini_set('memory_limit', '1024M');

            $upload = $_FILES['m3u_file'] ?? null;
            import_log("UPLOAD META", [
                'exists' => (bool)$upload,
                'name' => $upload['name'] ?? null,
                'type' => $upload['type'] ?? null,
                'size' => $upload['size'] ?? null,
                'tmp_name' => $upload['tmp_name'] ?? null,
                'error' => $upload['error'] ?? null,
            ]);

            $parser = new M3UParser($filePath);
            $channels = $parser->parse();
            $stats = $parser->getStats();
            import_log("PARSE DONE", ['channels' => count($channels), 'stats' => $stats]);

            $precheck = provider_precheck_entries($channels);
            import_log("PROVIDER PRECHECK (channels.php)", [
                'ok' => $precheck['ok'],
                'message' => $precheck['message'],
                'results' => $precheck['results']
            ]);

            if (!$precheck['ok']) {
                $error = $precheck['message'];
            } else {
                // VOD-aware import
                include_once '../includes/vod_import.php';
                $importStats = import_vod_from_m3u($pdo, $channels);
                import_log("IMPORT DONE (channels.php)", $importStats);

                $successParts = [];
                if ($importStats['live_imported'] > 0) $successParts[] = "{$importStats['live_imported']} live channels";
                if ($importStats['movies_inserted'] > 0) $successParts[] = "{$importStats['movies_inserted']} movies";
                if ($importStats['series_inserted'] > 0) $successParts[] = "{$importStats['series_inserted']} series";
                if ($importStats['episodes_inserted'] > 0) $successParts[] = "{$importStats['episodes_inserted']} episodes";
                $success = "Imported: " . implode(', ', $successParts ?: ['nothing new']) . ".";
                if ($importStats['live_skipped'] > 0) $success .= " Skipped {$importStats['live_skipped']} duplicate live streams.";
                if (!empty($importStats['errors'])) {
                    $success .= " With " . count($importStats['errors']) . " minor errors.";
                }
            }
            @unlink($filePath); // Clean up
        } catch (Exception $e) {
            $error = "Error parsing M3U file: " . $e->getMessage();
        }
    } else {
        $uploadCode = $_FILES['m3u_file']['error'] ?? 0;
        $uploadMessages = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds MAX_FILE_SIZE directive.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk (check permissions on uploads/).',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
            0                     => 'Unknown upload error (check permissions on uploads/).'
        ];
        $msg = $uploadMessages[$uploadCode] ?? 'Upload failed.';
        $error = "Error uploading file: {$msg} (code {$uploadCode}). Upload dir: {$uploadDir} writable: " . ($uploadWritable ? 'yes' : 'no');
    }
}

// Handle channel actions
if (isset($_GET['action'])) {
    $channelId = $_GET['id'] ?? 0;
    
    switch ($_GET['action']) {
        case 'toggle':
            $stmt = $pdo->prepare("UPDATE channels SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$channelId]);
            break;
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM channels WHERE id = ?");
            $stmt->execute([$channelId]);
            break;
        case 'delete_all':
            $stmt = $pdo->prepare("DELETE FROM channels");
            $stmt->execute();
            $success = "All channels have been deleted!";
            break;
        case 'delete_inactive':
            $stmt = $pdo->prepare("DELETE FROM channels WHERE is_active = false");
            $stmt->execute();
            $success = "All inactive channels have been deleted!";
            break;
    }
    header('Location: channels.php');
    exit;
}

// Pagination for channels list
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$totalChannelsStmt = $pdo->query("SELECT COUNT(*) FROM channels");
$totalChannels = (int)$totalChannelsStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalChannels / $perPage));

$stmt = $pdo->prepare("SELECT * FROM channels ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get channel stats
$activeChannels = $pdo->query("SELECT COUNT(*) FROM channels WHERE is_active = true")->fetchColumn();
$inactiveChannels = $pdo->query("SELECT COUNT(*) FROM channels WHERE is_active = false")->fetchColumn();
$totalViews = $pdo->query("SELECT SUM(views) FROM channels")->fetchColumn();

// Get categories for export filter
$categories = $pdo->query("SELECT DISTINCT category FROM channels WHERE category != '' ORDER BY category")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Channel Management - Premium IPTV Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-tv"></i> IPTV Admin</h3>
        </div>
        <nav style="padding: 20px 0;">
            <a href="index.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="channels.php" class="nav-link"><i class="fas fa-broadcast-tower"></i> Channels</a>
            <a href="resellers.php" class="nav-link"><i class="fas fa-handshake"></i> Reseller Management</a>
            <a href="ads.php" class="nav-link"><i class="fas fa-ad"></i> Ad Management</a>
            <a href="video-ads.php" class="nav-link"><i class="fas fa-video"></i> Video Ads</a>
            <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Settings</a>
            <a href="seo.php" class="nav-link"><i class="fas fa-search"></i> SEO</a>
            <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> Profile</a>
            <a href="../index.php" class="nav-link"><i class="fas fa-external-link-alt"></i> Visit Site</a>
            <a href="?logout=1" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="admin-main">
        <div class="admin-header">
            <h2 style="margin: 0; color: #2c3e50;">Channel Management</h2>
            <div style="display: flex; gap: 15px; align-items: center;">
                <span><?php echo $totalChannels; ?> total channels</span>
                <span style="color: #27ae60;"><?php echo $activeChannels; ?> active</span>
                <span style="color: #e74c3c;"><?php echo $inactiveChannels; ?> inactive</span>
                <span style="color: #3498db;"><?php echo number_format($totalViews); ?> total views</span>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0; color: #3498db;"><?php echo count($channels); ?></h3>
                <p style="margin: 5px 0 0 0; color: #7f8c8d;">Total Channels</p>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0; color: #27ae60;"><?php echo $activeChannels; ?></h3>
                <p style="margin: 5px 0 0 0; color: #7f8c8d;">Active Channels</p>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0; color: #e74c3c;"><?php echo $inactiveChannels; ?></h3>
                <p style="margin: 5px 0 0 0; color: #7f8c8d;">Inactive Channels</p>
            </div>
            <div style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin: 0; color: #9b59b6;"><?php echo number_format($totalViews); ?></h3>
                <p style="margin: 5px 0 0 0; color: #7f8c8d;">Total Views</p>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-upload"></i> Import M3U File</h4>
            </div>
            <div class="admin-card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Upload M3U File</label>
                        <div class="file-upload">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 15px;"></i>
                            <p>Drag & drop your M3U file here or click to browse</p>
                            <input type="file" id="m3uFileInput" name="m3u_file" accept=".m3u,.m3u8" required>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('m3uFileInput').click()">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Provider pre-check</label>
                        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                            <input
                                type="url"
                                id="providerTestUrl"
                                placeholder="Paste sample stream URL to test"
                                style="flex:1 1 420px; min-width:220px; padding:10px; border:1px solid #dcdcdc; border-radius:6px;"
                            >
                            <button type="button" id="testProviderBtn" class="btn btn-warning">
                                <i class="fas fa-vial"></i> Test Provider
                            </button>
                        </div>
                        <small style="color:#6c757d; display:block; margin-top:8px;">
                            Tip: after selecting a file, first sample URL is auto-filled for quick validation.
                        </small>
                        <div id="providerTestResult" style="display:none; margin-top:10px; padding:10px 12px; border-radius:6px;"></div>
                    </div>
                    <button type="submit" name="import_m3u" class="btn btn-success">
                        <i class="fas fa-upload"></i> Import Channels
                    </button>
                </form>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h4 style="margin: 0;"><i class="fas fa-list"></i> All Channels (<?php echo $totalChannels; ?>)</h4>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <!-- Export Category Filter -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="margin: 0; font-weight: bold;">Export Category:</label>
                        <select id="exportCategory" onchange="updateExportLinks()" style="padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Export Buttons -->
                    <a href="channels.php?export=csv" id="exportCsv" class="btn btn-primary">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                    <a href="channels.php?export=json" id="exportJson" class="btn btn-primary">
                        <i class="fas fa-download"></i> Export JSON
                    </a>
                    
                    <!-- Management Buttons -->
                    <a href="?action=delete_inactive" class="btn btn-warning" onclick="return confirm('Delete all inactive channels?')">
                        <i class="fas fa-trash"></i> Delete Inactive
                    </a>
                    <a href="?action=delete_all" class="btn btn-danger" onclick="return confirm('Delete ALL channels? This cannot be undone!')">
                        <i class="fas fa-trash-alt"></i> Delete All
                    </a>
                </div>
            </div>
            <div class="admin-card-body">
                <?php if (empty($channels)): ?>
                    <div style="text-align: center; padding: 40px; color: #95a5a6;">
                        <i class="fas fa-tv" style="font-size: 4rem; margin-bottom: 15px;"></i>
                        <h4>No channels found</h4>
                        <p>Import an M3U file to get started</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Channel Name</th>
                                <th>Category</th>
                                <th>Stream URL</th>
                                <th>DRM</th>
                                <th>Views</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($channels as $channel): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php if ($channel['logo_url']): ?>
                                            <img src="<?php echo sanitize($channel['logo_url']); ?>" 
                                                 alt="<?php echo sanitize($channel['name']); ?>" 
                                                 style="width: 40px; height: 30px; object-fit: cover; border-radius: 3px;">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo sanitize($channel['name']); ?></strong>
                                            <?php if ($channel['tvg_id']): ?>
                                                <br><small style="color: #7f8c8d;">ID: <?php echo sanitize($channel['tvg_id']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo sanitize($channel['category']); ?></td>
                                <td>
                                    <small style="font-family: monospace; color: #7f8c8d;">
                                        <?php echo substr(sanitize($channel['stream_url']), 0, 50); ?>...
                                    </small>
                                </td>
                                <td>
                                    <?php if ($channel['drm_type']): ?>
                                        <span style="color: #e74c3c; font-weight: bold;"><?php echo strtoupper($channel['drm_type']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #27ae60;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($channel['views']); ?></td>
                                <td>
                                    <?php if ($channel['is_active']): ?>
                                        <span style="color: #27ae60; font-weight: bold;">Active</span>
                                    <?php else: ?>
                                        <span style="color: #e74c3c;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?action=toggle&id=<?php echo $channel['id']; ?>" 
                                           class="btn <?php echo $channel['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                           style="padding: 5px 10px;"
                                           title="<?php echo $channel['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $channel['id']; ?>" 
                                           class="btn btn-danger" 
                                           style="padding: 5px 10px;" 
                                           onclick="return confirm('Delete channel: <?php echo addslashes($channel['name']); ?>?')"
                                           title="Delete Channel">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="display:flex; justify-content:center; gap:8px; margin-top:14px; align-items:center; flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-secondary" href="?page=<?php echo $page - 1; ?>">&laquo; Prev</a>
                        <?php endif; ?>
                        <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-secondary" href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('m3uFileInput');
        const providerUrlInput = document.getElementById('providerTestUrl');
        const providerBtn = document.getElementById('testProviderBtn');
        const providerResult = document.getElementById('providerTestResult');

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderProviderResult(payload) {
            const status = Number(payload.status || 0);
            const ok = Boolean(payload.ok);
            const color = ok ? '#155724' : '#721c24';
            const background = ok ? '#d4edda' : '#f8d7da';
            const border = ok ? '#c3e6cb' : '#f5c6cb';
            const icon = ok ? 'fa-check-circle' : 'fa-times-circle';
            const sample = payload.sample ? `<div style="margin-top:6px;"><strong>Sample:</strong> <code>${escapeHtml(payload.sample)}</code></div>` : '';
            const message = escapeHtml(payload.message || '');

            providerResult.style.display = 'block';
            providerResult.style.color = color;
            providerResult.style.background = background;
            providerResult.style.border = `1px solid ${border}`;
            providerResult.innerHTML = `
                <div><i class="fas ${icon}"></i> <strong>Status ${status}</strong> - ${message}</div>
                ${sample}
            `;
        }

        async function runProviderTest(url) {
            const streamUrl = (url || '').trim();
            if (!streamUrl) {
                renderProviderResult({ ok: false, status: 400, message: 'Please enter a stream URL first.' });
                return;
            }

            providerBtn.disabled = true;
            providerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';

            try {
                const params = new URLSearchParams();
                params.set('url', streamUrl);

                const response = await fetch('test_provider.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                });

                const payload = await response.json();
                renderProviderResult(payload);
            } catch (error) {
                renderProviderResult({
                    ok: false,
                    status: 502,
                    message: 'Could not run provider test from server.'
                });
            } finally {
                providerBtn.disabled = false;
                providerBtn.innerHTML = '<i class="fas fa-vial"></i> Test Provider';
            }
        }

        function extractFirstSampleUrl(file) {
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function() {
                const content = String(reader.result || '');
                const urls = content
                    .split(/\r?\n/)
                    .map((line) => line.trim())
                    .filter((line) => line && line[0] !== '#' && /^https?:\/\//i.test(line))
                    .slice(0, 5);

                if (urls.length > 0 && !providerUrlInput.value.trim()) {
                    providerUrlInput.value = urls[0];
                    runProviderTest(urls[0]);
                }
            };
            reader.readAsText(file.slice(0, 1024 * 1024));
        }

        // File upload preview + auto provider sample test
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                document.querySelector('.file-upload p').textContent = 'Selected: ' + this.files[0].name;
                extractFirstSampleUrl(this.files[0]);
            }
        });

        providerBtn.addEventListener('click', function() {
            runProviderTest(providerUrlInput.value);
        });

        // Export category filter functionality
        function updateExportLinks() {
            const category = document.getElementById('exportCategory').value;
            const csvLink = document.getElementById('exportCsv');
            const jsonLink = document.getElementById('exportJson');
            
            if (category) {
                csvLink.href = `channels.php?export=csv&category=${encodeURIComponent(category)}`;
                jsonLink.href = `channels.php?export=json&category=${encodeURIComponent(category)}`;
            } else {
                csvLink.href = 'channels.php?export=csv';
                jsonLink.href = 'channels.php?export=json';
            }
        }

        // Initialize export links
        document.addEventListener('DOMContentLoaded', function() {
            updateExportLinks();
        });
    </script>
</body>
</html>
