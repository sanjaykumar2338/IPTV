<?php
/**
 * IPTV Website Main Router
 * Handles all incoming requests and routes to appropriate pages
 */

include 'includes/config.php';
include 'includes/functions.php';

// Check for maintenance mode
if (getSetting($pdo, 'maintenance_mode', '0') === '1' && !strpos($_SERVER['REQUEST_URI'], '/admin')) {
    header('HTTP/1.1 503 Service Unavailable');
    include 'pages/maintenance.php';
    exit;
}

// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'];
$path = trim(parse_url($request_uri, PHP_URL_PATH), '/');

// Handle proxy.php requests FIRST
if (strpos($request_uri, '/proxy.php') !== false) {
    include 'proxy.php';
    exit;
}

// Remove .php extension and route clean URLs
$clean_path = preg_replace('/\.php$/', '', $path);

// Route the request
switch ($clean_path) {
    case '':
    case 'index':
        include 'pages/index.php';
        break;
        
    case 'channels':
        include 'pages/channels.php';
        break;
        
    case 'categories':
        include 'pages/categories.php';
        break;
        
    case 'player':
        include 'pages/player.php';
        break;
        
    case 'watch':
        include 'watch/index.php';
        break;
        
    case 'movies':
        include 'movies.php';
        break;
        
    case 'series-list':
        include 'series-list.php';
        break;
        
    case 'live-tv':
        include 'live-tv.php';
        break;
        
    case 'movie':
        include 'movie.php';
        break;
        
    case 'series':
        include 'series.php';
        break;
        
    case 'my-list':
        include 'my-list.php';
        break;
        
    case 'sitemap.xml':
        include 'pages/sitemap.php';
        break;
    
    // API routes
    case 'api/get_channels':
        include 'api/get_channels.php';
        break;

    case 'api/track-ad':
        include 'api/track-ad.php';
        break;
        
    case 'api/update_views':
        include 'api/update_views.php';
        break;

    case 'api/video-ads':
        include 'api/video-ads.php';
        break;
        
    case 'api/watch_history':
        include 'api/watch_history.php';
        break;
        
    case 'api/my_list':
        include 'api/my_list.php';
        break;
        
    case 'api/movie':
        include 'api/movie.php';
        break;
        
    case 'api/series':
        include 'api/series.php';
        break;
        
    // Admin routes
    case 'admin':
        include 'admin/index.php';
        break;
        
    case 'admin/login':
        include 'admin/login.php';
        break;
        
    case 'admin/channels':
        include 'admin/channels.php';
        break;
        
    case 'admin/ads':
        include 'admin/ads.php';
        break;

    case 'admin/video-ads':  
        include 'admin/video-ads.php';
        break;
        
    case 'admin/settings':
        include 'admin/settings.php';
        break;
        
    case 'admin/seo':
        include 'admin/seo.php';
        break;
        
    case 'admin/logout':
        include 'includes/auth.php';
        adminLogout();
        break;
        
    // Static files - allow direct access to assets
    default:
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|txt)$/i', $path)) {
            // Serve static files directly
            $file_path = __DIR__ . '/' . $path;
            if (file_exists($file_path) && is_file($file_path)) {
                $mime_types = [
                    'css' => 'text/css',
                    'js' => 'application/javascript',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'ico' => 'image/x-icon',
                    'txt' => 'text/plain'
                ];
                
                $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                if (isset($mime_types[$extension])) {
                    header('Content-Type: ' . $mime_types[$extension]);
                }
                
                readfile($file_path);
                exit;
            }
        }
        
        // 404 for everything else
        http_response_code(404);
        include 'pages/404.php';
        break;
}
