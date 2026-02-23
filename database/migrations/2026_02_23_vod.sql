-- VOD support schema
-- Safe to run multiple times (uses IF NOT EXISTS where supported).

CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    genre VARCHAR(255) NULL,
    poster_url TEXT NULL,
    stream_url TEXT NOT NULL,
    source_id VARCHAR(191) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_movies_source (source_id),
    INDEX idx_movies_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    genre VARCHAR(255) NULL,
    poster_url TEXT NULL,
    source_id VARCHAR(191) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_series_source (source_id),
    INDEX idx_series_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    series_id INT NOT NULL,
    season_number INT NOT NULL DEFAULT 1,
    episode_number INT NOT NULL DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    stream_url TEXT NOT NULL,
    thumbnail_url TEXT NULL,
    source_id VARCHAR(191) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_episode_source (source_id),
    INDEX idx_episode_series (series_id, season_number, episode_number),
    CONSTRAINT fk_episode_series FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add source_id/indexes to existing tables (MySQL 8+ supports IF NOT EXISTS)
ALTER TABLE movies 
    ADD COLUMN IF NOT EXISTS source_id VARCHAR(191) NULL,
    ADD UNIQUE KEY IF NOT EXISTS uniq_movies_source (source_id),
    ADD INDEX IF NOT EXISTS idx_movies_title (title);

ALTER TABLE series 
    ADD COLUMN IF NOT EXISTS source_id VARCHAR(191) NULL,
    ADD UNIQUE KEY IF NOT EXISTS uniq_series_source (source_id),
    ADD INDEX IF NOT EXISTS idx_series_title (title);

ALTER TABLE episodes 
    ADD COLUMN IF NOT EXISTS source_id VARCHAR(191) NULL,
    ADD UNIQUE KEY IF NOT EXISTS uniq_episode_source (source_id),
    ADD INDEX IF NOT EXISTS idx_episode_series (series_id, season_number, episode_number);
