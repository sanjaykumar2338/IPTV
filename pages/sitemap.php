<?php
include '../includes/config.php';
include '../includes/functions.php';
require_once __DIR__ . '/../includes/auth/check_auth.php';

header('Content-Type: application/xml; charset=utf-8');

$base_url = getSetting($pdo, 'site_url', 'http://localhost');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Main pages
$pages = [
    '' => 'daily',
    'channels.php' => 'daily',
    'categories.php' => 'weekly'
];

foreach ($pages as $page => $changefreq) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base_url . '/' . $page) . "</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>{$changefreq}</changefreq>\n";
    echo "    <priority>1.0</priority>\n";
    echo "  </url>\n";
}

// Channel pages
$channels = $pdo->query("SELECT id, name, updated_at FROM channels WHERE is_active = true")->fetchAll();
foreach ($channels as $channel) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base_url . '/player.php?stream=' . urlencode($channel['id'])) . "</loc>\n";
    echo "    <lastmod>" . date('Y-m-d', strtotime($channel['updated_at'])) . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

// Category pages
$categories = $pdo->query("SELECT DISTINCT category FROM channels WHERE is_active = true")->fetchAll(PDO::FETCH_COLUMN);
foreach ($categories as $category) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base_url . '/categories.php?category=' . urlencode($category)) . "</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
?>
