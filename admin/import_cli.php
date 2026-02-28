<?php
// CLI-only M3U importer to avoid browser timeouts
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Run from CLI: php admin/import_cli.php /path/to/file.m3u\n");
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/m3u-parser.php';
require_once __DIR__ . '/../includes/vod_import.php';
require_once __DIR__ . '/../includes/provider_cleanup.php';
require_once __DIR__ . '/../includes/provider_validator.php';
require_once __DIR__ . '/../pages/import_logger.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php admin/import_cli.php /path/to/file.m3u [--no-replace] [--confirm-legacy-wipe]\n");
    exit(1);
}

$filePath = $argv[1];
$replaceProviderData = !in_array('--no-replace', $argv, true);
$confirmLegacyWipe = in_array('--confirm-legacy-wipe', $argv, true);
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

provider_ensure_schema($pdo);

$precheck = provider_precheck_entries($channels);
if (!$precheck['ok']) {
    import_log("CLI PROVIDER PRECHECK FAILED", $precheck);
    fwrite(STDERR, $precheck['message'] . PHP_EOL);
    exit(2);
}

$providerMeta = provider_detect_from_entries($channels, basename($filePath));
$provider = provider_get_or_create($pdo, $providerMeta);
$providerId = (int)$provider['id'];
$providerSafe = provider_safe_summary($provider);

if (ENABLE_LEGACY_UNASSIGNED_WIPE && $confirmLegacyWipe) {
    $legacyCounts = legacyCatalogCounts($pdo);
    $legacyDeleted = wipeLegacyUnassignedCatalog($pdo);
    import_log("CLI LEGACY UNASSIGNED WIPE", [
        'before' => $legacyCounts,
        'deleted' => $legacyDeleted
    ]);
}

if ($replaceProviderData) {
    $replaceSummary = replaceProviderCatalogAtomically($pdo, $providerId, $channels, [
        'provider_scope' => provider_scope_from_id($providerId),
        'commit_every' => 100
    ]);
    $importStats = $replaceSummary['import'];
} else {
    $importStats = import_vod_from_m3u($pdo, $channels, 100, [
        'provider_id' => $providerId,
        'provider_scope' => provider_scope_from_id($providerId)
    ]);
}
import_log("CLI IMPORT DONE", $importStats);

echo "Import finished for provider {$providerSafe['name']} ({$providerSafe['provider_ref']}). See storage/logs/import.log for details\n";
