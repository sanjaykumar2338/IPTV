<?php
// CLI-only M3U importer to avoid browser timeouts
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Run from CLI: php admin/import_cli.php /path/to/file.m3u\n");
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/m3u-parser.php';
require_once __DIR__ . '/../includes/vod_import.php';
require_once __DIR__ . '/../pages/import_logger.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php admin/import_cli.php /path/to/file.m3u\n");
    exit(1);
}

$filePath = $argv[1];
if (!file_exists($filePath)) {
    fwrite(STDERR, "File not found: {$filePath}\n");
    exit(1);
}

@set_time_limit(0);
@ini_set('memory_limit', '2048M');

import_log("CLI IMPORT START", ['file' => $filePath]);

$parser = new M3UParser($filePath);
$channels = $parser->parse();
import_log("CLI PARSE DONE", ['channels' => count($channels)]);

$importStats = import_vod_from_m3u($pdo, $channels);
import_log("CLI IMPORT DONE", $importStats);

echo "Import finished. See storage/logs/import.log for details\n";
