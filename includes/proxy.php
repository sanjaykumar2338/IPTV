<?php
class StreamProxy {
    private $allowed_domains = [];
    private $cache_dir = 'cache/';
    private $cache_time = 300; // 5 minutes
    
    public function __construct() {
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    public function proxyStream($url, $headers = []) {
        // Validate URL
        if (!$this->isValidUrl($url)) {
            throw new Exception('Invalid URL provided: ' . $url);
        }
        
        // Check cache first
        $cache_key = md5($url);
        $cache_file = $this->cache_dir . $cache_key;
        
        if (file_exists($cache_file) && time() - filemtime($cache_file) < $this->cache_time) {
            return file_get_contents($cache_file);
        }
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set cURL options for better compatibility
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADER => false,
            CURLOPT_FAILONERROR => true,
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Accept-Language: en-US,en;q=0.9',
                'Connection: keep-alive',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: cross-site'
            ]
        ]);
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception('cURL error: ' . $curl_error . ' for URL: ' . $url);
        }
        
        // Check HTTP status
        if ($http_code !== 200) {
            throw new Exception("HTTP error: {$http_code} for URL: {$url} (Effective URL: {$effective_url})");
        }
        
        if (empty($response)) {
            throw new Exception("Empty response from URL: {$url}");
        }
        
        // Cache the response
        file_put_contents($cache_file, $response);
        
        return $response;
    }
    
    public function proxyM3U8($url) {
        $content = $this->proxyStream($url);
        
        // Parse M3U8 and proxy individual segments
        if (strpos($content, '#EXTM3U') !== false) {
            $lines = explode("\n", $content);
            $proxied_lines = [];
            $base_url = dirname($url) . '/';
            
            foreach ($lines as $line) {
                $trimmed_line = trim($line);
                if (strpos($trimmed_line, 'http') === 0) {
                    // This is a segment URL, proxy it
                    $proxied_url = $this->getProxyUrl($trimmed_line);
                    $proxied_lines[] = $proxied_url;
                } else if (strpos($trimmed_line, '#') !== 0 && !empty($trimmed_line)) {
                    // This might be a relative URL, convert to absolute and proxy
                    if (strpos($trimmed_line, 'http') !== 0) {
                        // Convert relative URL to absolute
                        $absolute_url = $this->resolveRelativeUrl($base_url, $trimmed_line);
                        $proxied_url = $this->getProxyUrl($absolute_url);
                        $proxied_lines[] = $proxied_url;
                    } else {
                        $proxied_lines[] = $line;
                    }
                } else {
                    $proxied_lines[] = $line;
                }
            }
            
            return implode("\n", $proxied_lines);
        }
        
        return $content;
    }
    
    private function resolveRelativeUrl($base_url, $relative_url) {
        if (strpos($relative_url, 'http') === 0) {
            return $relative_url;
        }
        
        // Remove any leading ./
        $relative_url = ltrim($relative_url, './');
        
        // Combine base URL and relative URL
        return rtrim($base_url, '/') . '/' . $relative_url;
    }
    
    public function getProxyUrl($original_url) {
        return "/proxy.php?url=" . urlencode($original_url);
    }
    
    private function isValidUrl($url) {
        $parsed = parse_url($url);
        
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
            return false;
        }
        
        if (!isset($parsed['host'])) {
            return false;
        }
        
        // Add domain validation if needed
        if (!empty($this->allowed_domains) && !in_array($parsed['host'], $this->allowed_domains)) {
            return false;
        }
        
        return true;
    }
    
    public function setAllowedDomains($domains) {
        $this->allowed_domains = $domains;
    }
}
?>