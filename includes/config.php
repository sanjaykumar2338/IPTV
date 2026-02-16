<?php
require_once __DIR__ . '/auth/session.php';

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'learningscriptin_stream');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site Configuration
$site_config = [
    'site_name' => 'Premium IPTV',
    'site_description' => 'Watch thousands of HD channels with premium quality',
    'version' => '1.0.0',
    'debug' => true
];

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create tables if they don't exist
function createTables($pdo) {

    // Force UTF8MB4 on connection
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $queries = [

        "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS channels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            stream_url TEXT NOT NULL,
            category VARCHAR(100),
            logo_url VARCHAR(500),
            tvg_id VARCHAR(100),
            drm_type VARCHAR(50),
            license_key TEXT,
            license_url TEXT,
            is_active BOOLEAN DEFAULT true,
            views INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS video_ads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ad_type ENUM('pre-roll','mid-roll','post-roll','random') NOT NULL,
            ad_name VARCHAR(255) NOT NULL,
            video_url VARCHAR(500) NOT NULL,
            duration INT NOT NULL,
            skip_after INT DEFAULT 5,
            target_url VARCHAR(500),
            categories TEXT,
            is_active BOOLEAN DEFAULT true,
            impressions INT DEFAULT 0,
            clicks INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT true
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS ads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ad_type ENUM('google', 'image') NOT NULL,
            ad_position ENUM('header','body','footer') NOT NULL,
            ad_code TEXT,
            image_url VARCHAR(500),
            link_url VARCHAR(500),
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS resellers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone_number VARCHAR(50) NOT NULL UNIQUE,
            uuid VARCHAR(64) NOT NULL UNIQUE,
            pin_hash VARCHAR(255) NOT NULL,
            subscription_status ENUM('active','inactive','expired') NOT NULL DEFAULT 'inactive',
            subscription_expiry_date DATETIME NULL,
            reseller_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX reseller_id_idx (reseller_id),
            CONSTRAINT fk_customers_reseller FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE SET NULL
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS movies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            poster_url VARCHAR(500),
            banner_url VARCHAR(500),
            synopsis TEXT,
            year INT,
            genre VARCHAR(255),
            rating DECIMAL(3,1),
            duration_minutes INT,
            trailer_url VARCHAR(500),
            stream_url TEXT,
            is_featured TINYINT(1) DEFAULT 0,
            popularity INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS series (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            poster_url VARCHAR(500),
            banner_url VARCHAR(500),
            synopsis TEXT,
            year INT,
            genre VARCHAR(255),
            rating DECIMAL(3,1),
            seasons INT DEFAULT 1,
            trailer_url VARCHAR(500),
            is_featured TINYINT(1) DEFAULT 0,
            popularity INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS episodes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            series_id INT NOT NULL,
            season_number INT NOT NULL,
            episode_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            synopsis TEXT,
            duration_minutes INT,
            stream_url TEXT,
            thumbnail_url VARCHAR(500),
            air_date DATE NULL,
            INDEX series_season_idx (series_id, season_number),
            CONSTRAINT fk_episode_series FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS watch_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            content_type ENUM('movie','series') NOT NULL,
            content_id INT NOT NULL,
            episode_id INT NULL,
            last_position_seconds INT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_history (customer_id, content_type, content_id, episode_id),
            INDEX history_customer_idx (customer_id, content_type, content_id),
            CONSTRAINT fk_history_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS my_list (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            content_type ENUM('movie','series') NOT NULL,
            content_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_list (customer_id, content_type, content_id),
            CONSTRAINT fk_list_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS channel_favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            channel_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_channel_fav (customer_id, channel_id),
            CONSTRAINT fk_fav_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            CONSTRAINT fk_fav_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS epg_programs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            channel_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            INDEX epg_channel_time_idx (channel_id, start_time, end_time),
            CONSTRAINT fk_epg_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        /* FIX: Avoid key-too-long by using VARCHAR(190) */
        "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(190) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_type VARCHAR(50)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    // Create default admin user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
    $stmt->execute();

    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('admin', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admins (username, password, email) VALUES (?, ?, ?)")
            ->execute(['admin', $hashed_password, 'admin@iptv.com']);
    }
}

createTables($pdo);
?>
