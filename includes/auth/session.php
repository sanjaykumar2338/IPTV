<?php
// Secure session bootstrap
if (!function_exists('session_bootstrap')) {
    function session_bootstrap(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $is_https,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();
        }
    }

    function regenerate_session_id_secure(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    function destroy_session_secure(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }

            session_destroy();
        }
    }

    // Initialize session immediately on include
    session_bootstrap();
}
