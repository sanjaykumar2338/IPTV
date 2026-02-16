<?php
include '../includes/config.php';
include '../includes/auth.php';
include '../includes/functions.php';
requireAdminAuth();

// Handle SEO settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        $key = trim($key);
        if ($key === '') continue;

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }
    
    generateSitemap($pdo);
    $success = "SEO settings updated and sitemap generated successfully!";
}

// Get current SEO settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'seo_%'")->fetchAll(PDO::FETCH_KEY_PAIR);

// Function to generate sitemap
function generateSitemap($pdo) {
    $baseUrl = getSetting($pdo, 'site_url', 'http://localhost');
    $sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $sitemapContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // Add main pages
    $pages = ['', 'channels.php', 'categories.php'];
    foreach ($pages as $page) {
        $sitemapContent .= "  <url>\n";
        $sitemapContent .= "    <loc>{$baseUrl}/{$page}</loc>\n";
        $sitemapContent .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        $sitemapContent .= "    <changefreq>daily</changefreq>\n";
        $sitemapContent .= "    <priority>1.0</priority>\n";
        $sitemapContent .= "  </url>\n";
    }
    
    // Add channels
    $channels = $pdo->query("SELECT id, name FROM channels WHERE is_active = true")->fetchAll();
    foreach ($channels as $channel) {
        $sitemapContent .= "  <url>\n";
        $sitemapContent .= "    <loc>{$baseUrl}/player.php?id={$channel['id']}</loc>\n";
        $sitemapContent .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        $sitemapContent .= "    <changefreq>weekly</changefreq>\n";
        $sitemapContent .= "    <priority>0.8</priority>\n";
        $sitemapContent .= "  </url>\n";
    }
    
    $sitemapContent .= '</urlset>';
    
    file_put_contents('../sitemap.xml', $sitemapContent);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Settings - Premium IPTV Admin</title>
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
            <h2 style="margin: 0; color: #2c3e50;">SEO Settings</h2>
            <span>Optimize your site for search engines</span>
        </div>

        <?php if (isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h4 style="margin: 0;"><i class="fas fa-tags"></i> Meta Tags</h4>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="seo_meta_title" class="form-control" 
                               value="<?php echo $settings['seo_meta_title'] ?? 'Premium IPTV - Watch HD Channels Online'; ?>" 
                               placeholder="Keep it under 60 characters">
                        <small style="color: #7f8c8d;">Recommended: 50-60 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Meta Description</label>
                        <textarea name="seo_meta_description" class="form-control" rows="3" 
                                  placeholder="Brief description of your site"><?php echo $settings['seo_meta_description'] ?? 'Watch thousands of HD TV channels online with premium IPTV service. Live sports, movies, news and entertainment.'; ?></textarea>
                        <small style="color: #7f8c8d;">Recommended: 150-160 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Meta Keywords</label>
                        <input type="text" name="seo_meta_keywords" class="form-control" 
                               value="<?php echo $settings['seo_meta_keywords'] ?? 'iptv, live tv, streaming, hd channels, sports, movies'; ?>" 
                               placeholder="Comma-separated keywords">
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h4 style="margin: 0;"><i class="fas fa-globe"></i> Site Information</h4>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">Site URL</label>
                        <input type="url" name="site_url" class="form-control" 
                               value="<?php echo getSetting($pdo, 'site_url', 'http://localhost'); ?>" 
                               placeholder="https://your-iptv-site.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Canonical URL</label>
                        <input type="url" name="seo_canonical_url" class="form-control" 
                               value="<?php echo $settings['seo_canonical_url'] ?? ''; ?>" 
                               placeholder="https://your-iptv-site.com">
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h4 style="margin: 0;"><i class="fas fa-sitemap"></i> Sitemap & Robots</h4>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">Sitemap URL</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" class="form-control" 
                                   value="<?php echo getSetting($pdo, 'site_url', 'http://localhost'); ?>/sitemap.xml" 
                                   readonly style="flex: 1;">
                            <a href="../sitemap.xml" target="_blank" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Robots.txt</label>
                        <textarea name="seo_robots_txt" class="form-control" rows="4" 
                                  placeholder="User-agent: *&#10;Allow: /&#10;Disallow: /admin/"><?php echo $settings['seo_robots_txt'] ?? "User-agent: *\nAllow: /\nDisallow: /admin/"; ?></textarea>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h4 style="margin: 0;"><i class="fas fa-chart-line"></i> Analytics</h4>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">Google Analytics ID</label>
                        <input type="text" name="seo_ga_id" class="form-control" 
                               value="<?php echo $settings['seo_ga_id'] ?? ''; ?>" 
                               placeholder="G-XXXXXXXXXX">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Google Search Console Verification</label>
                        <input type="text" name="seo_google_verification" class="form-control" 
                               value="<?php echo $settings['seo_google_verification'] ?? ''; ?>" 
                               placeholder="Google site verification code">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Bing Webmaster Verification</label>
                        <input type="text" name="seo_bing_verification" class="form-control" 
                               value="<?php echo $settings['seo_bing_verification'] ?? ''; ?>" 
                               placeholder="Bing site verification code">
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-header">
                    <h4 style="margin: 0;"><i class="fas fa-share-alt"></i> Social Media</h4>
                </div>
                <div class="admin-card-body">
                    <div class="form-group">
                        <label class="form-label">Open Graph Title</label>
                        <input type="text" name="seo_og_title" class="form-control" 
                               value="<?php echo $settings['seo_og_title'] ?? 'Premium IPTV - Watch HD Channels Online'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Open Graph Description</label>
                        <textarea name="seo_og_description" class="form-control" rows="2"><?php echo $settings['seo_og_description'] ?? 'Watch thousands of HD TV channels online with premium IPTV service.'; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Open Graph Image URL</label>
                        <input type="url" name="seo_og_image" class="form-control" 
                               value="<?php echo $settings['seo_og_image'] ?? ''; ?>" 
                               placeholder="https://example.com/og-image.jpg">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 1.1rem;">
                <i class="fas fa-save"></i> Save SEO Settings & Generate Sitemap
            </button>
        </form>
    </div>
</body>
</html>
