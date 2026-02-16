<?php
// M3U Parser with DRM support
function parseM3UFile($filePath) {
    $channels = [];
    if (!file_exists($filePath)) {
        return ['error' => 'File not found'];
    }
    
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $currentChannel = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line) || strpos($line, '#EXTM3U') === 0) {
            continue;
        }
        
        if (strpos($line, '#EXTINF:') === 0) {
            $currentChannel = [
                'name' => 'Unknown Channel',
                'logo' => '',
                'category' => 'General',
                'tvg_id' => '',
                'drm' => [
                    'type' => null,
                    'license_key' => null,
                    'license_url' => null
                ]
            ];
            
            // Extract channel name
            if (strpos($line, ',') !== false) {
                $currentChannel['name'] = trim(substr($line, strrpos($line, ',') + 1));
            }
            
            // Extract attributes
            preg_match('/tvg-id="([^"]*)"/', $line, $matches) && $currentChannel['tvg_id'] = $matches[1];
            preg_match('/tvg-logo="([^"]*)"/', $line, $matches) && $currentChannel['logo'] = $matches[1];
            preg_match('/group-title="([^"]*)"/', $line, $matches) && $currentChannel['category'] = $matches[1];
            
        } elseif (strpos($line, '#KODIPROP:inputstream.adaptive.license_type=') === 0) {
            $currentChannel['drm']['type'] = trim(substr($line, 43));
        } elseif (strpos($line, '#KODIPROP:inputstream.adaptive.license_key=') === 0) {
            $currentChannel['drm']['license_key'] = trim(substr($line, 42));
        } elseif (strpos($line, '#KODIPROP:inputstream.adaptive.license_url=') === 0) {
            $currentChannel['drm']['license_url'] = trim(substr($line, 42));
        } elseif ($currentChannel && !empty($line) && $line[0] !== '#') {
            $currentChannel['stream_url'] = $line;
            $channels[] = $currentChannel;
            $currentChannel = null;
        }
    }
    
    return $channels;
}

// Get ClearKey configuration
function getClearKeyConfig($license_key) {
    if (strpos($license_key, ':') !== false) {
        list($key_id, $key) = explode(':', $license_key, 2);
        return ['key_id' => $key_id, 'key' => $key];
    }
    return null;
}

// Get setting value
function getSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Get active ads
function getAds($pdo, $position) {
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE ad_position = ? AND is_active = true");
    $stmt->execute([$position]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Sanitize output
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>