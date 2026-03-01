<?php
// Shared config for frontend PHP.
// Hardens session cookie and provides backend base URL helper.

// Set cookie parameters before session_start
if (session_status() === PHP_SESSION_NONE) {
    // Create sessions directory in project if it doesn't exist
    $session_dir = __DIR__ . '/../sessions';
    if (!is_dir($session_dir)) {
        @mkdir($session_dir, 0777, true);
    }
    
    // Set session save path to project directory (requires write permissions)
    if (is_writable($session_dir)) {
        session_save_path($session_dir);
    }
    
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isSecure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Helper to get backend base URL with sane default
function backend_base(): string {
    $base = getenv('FLASK_BASE_URL');
    if (!$base) {
        $base = 'http://localhost:5000';
    }
    return rtrim($base, '/');
}
