<?php
class M3UParser {
    private $filePath;
    private $channels = [];
    
    public function __construct($filePath) {
        $this->filePath = $filePath;
    }
    
    public function parse() {
        if (!file_exists($this->filePath)) {
            throw new Exception("M3U file not found: " . $this->filePath);
        }
        
        $content = file_get_contents($this->filePath);
        $lines = explode("\n", $content);
        $currentChannel = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || strpos($line, '#EXTM3U') === 0) {
                continue;
            }
            
            if (strpos($line, '#EXTINF:') === 0) {
                $currentChannel = $this->parseExtInf($line);
            } 
            elseif (strpos($line, '#KODIPROP:') === 0) {
                if ($currentChannel) {
                    $this->parseKodiProp($line, $currentChannel);
                }
            }
            elseif ($currentChannel && !empty($line) && $line[0] !== '#') {
                $currentChannel['stream_url'] = $line;
                $this->channels[] = $currentChannel;
                $currentChannel = null;
            }
        }
        
        return $this->channels;
    }
    
private function parseExtInf($line) {
    $channel = [
        'name' => 'Unknown Channel',
        'logo' => '',
        'category' => 'General',
        'tvg_id' => '',
        'duration' => -1,
        'drm' => [
            'type' => null,
            'license_key' => null,
            'license_url' => null
        ]
    ];
    
    // Extract duration
    $duration = -1;
    if (preg_match('/#EXTINF:(-?\d+)/', $line, $durationMatches)) {
        $channel['duration'] = intval($durationMatches[1]);
    }
    
    // Extract attributes and channel name
    $attributes = [
        'tvg-id' => 'tvg_id',
        'tvg-logo' => 'logo',
        'group-title' => 'category',
        'group-logo' => 'group_logo'
    ];
    
    // Extract all attributes
    foreach ($attributes as $attr => $field) {
        if (preg_match('/' . $attr . '="([^"]*)"/', $line, $matches)) {
            $channel[$field] = $matches[1];
        }
    }
    
    // Extract the actual channel name (after the last comma)
    if (preg_match('/,([^,]+)$/', $line, $nameMatches)) {
        $channel['name'] = trim($nameMatches[1]);
    } else {
        // Fallback: try to extract name after attributes
        $parts = explode(',', $line);
        if (count($parts) > 1) {
            $channel['name'] = trim(end($parts));
        }
    }
    
    // Clean up the name - remove any remaining quotes or special characters
    $channel['name'] = preg_replace('/^["\']|["\']$/', '', $channel['name']);
    
    return $channel;
}
    
    private function parseKodiProp($line, &$channel) {
        if (strpos($line, 'inputstream.adaptive.license_type=') !== false) {
            $channel['drm']['type'] = trim(substr($line, strpos($line, '=') + 1));
        }
        elseif (strpos($line, 'inputstream.adaptive.license_key=') !== false) {
            $channel['drm']['license_key'] = trim(substr($line, strpos($line, '=') + 1));
        }
        elseif (strpos($line, 'inputstream.adaptive.license_url=') !== false) {
            $channel['drm']['license_url'] = trim(substr($line, strpos($line, '=') + 1));
        }
    }
    
    public function getStats() {
        $stats = [
            'total_channels' => count($this->channels),
            'drm_channels' => 0,
            'clearkey_channels' => 0,
            'categories' => []
        ];
        
        foreach ($this->channels as $channel) {
            if (!empty($channel['drm']['type'])) {
                $stats['drm_channels']++;
                if ($channel['drm']['type'] === 'clearkey') {
                    $stats['clearkey_channels']++;
                }
            }
            
            $category = $channel['category'];
            if (!isset($stats['categories'][$category])) {
                $stats['categories'][$category] = 0;
            }
            $stats['categories'][$category]++;
        }
        
        return $stats;
    }
}
?>