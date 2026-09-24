<?php
/**
 * E-STRANGE & S-SPARC Dynamic Asset & AI Proxy Rewriter
 * 
 * Automatically detects whether the client/server is in an offline lab environment.
 * When in an offline environment (or when ?offline=1 is passed), rewrites CDN links to local
 * assets. When online, preserves standard high-speed CDN assets.
 */

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli' && !headers_sent()) {
    @session_start();
}

/**
 * Determine the relative path to assets/vendor/ dynamically based on the current execution context
 */
if (!function_exists('get_offline_assets_base')) {
    function get_offline_assets_base() {
        $uris = [
            $_SERVER['SCRIPT_NAME'] ?? '',
            $_SERVER['PHP_SELF'] ?? '',
            $_SERVER['REQUEST_URI'] ?? ''
        ];

        foreach ($uris as $u) {
            if (empty($u)) continue;
            if (stripos($u, '/ssparc/') !== false || stripos($u, '/karina/') !== false) {
                return '../assets/vendor/';
            }
        }

        return 'assets/vendor/';
    }
}

/**
 * Core rewrite function that transforms CDN links into local vendor assets only when offline
 */
if (!function_exists('rewrite_offline_assets')) {
    function rewrite_offline_assets($html) {
        if (empty($html) || !is_string($html)) {
            return $html;
        }

        // Check if offline mode is explicitly requested or enabled
        $isOffline = false;
        if (isset($_GET['offline']) && ($_GET['offline'] === '1' || $_GET['offline'] === 'true')) {
            $isOffline = true;
        } elseif (getenv('OFFLINE_MODE') === '1' || getenv('OFFLINE_MODE') === 'true') {
            $isOffline = true;
        } elseif (!empty($_SESSION['is_lab_offline'])) {
            $isOffline = true;
        }

        // If online, do not rewrite CDN assets
        if (!$isOffline) {
            return $html;
        }

        $baseAssets = get_offline_assets_base();
        $proxyPath = ($baseAssets === '../assets/vendor/') ? 'api_proxy.php' : 'ssparc/api_proxy.php';

        // Mapping CDN URLs to Local Vendor Assets
        $replacements = [
            '#https?://fonts\.googleapis\.com/css2\?[^"\']+#i' => $baseAssets . 'fonts.css',
            '#https?://fonts\.googleapis\.com/css\?[^"\']+#i' => $baseAssets . 'fonts.css',
            '#https?://cdn\.tailwindcss\.com#i' => $baseAssets . 'tailwindcss.js',
            '#https?://code\.jquery\.com/jquery-[^"\']+\.js#i' => $baseAssets . 'jquery.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2@[^/]+/dist/sweetalert2\.min\.css#i' => $baseAssets . 'sweetalert2.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2@[^"\']+#i' => $baseAssets . 'sweetalert2.all.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2#i' => $baseAssets . 'sweetalert2.all.min.js',
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/font-awesome/[^/]+/css/all\.min\.css#i' => $baseAssets . 'fontawesome/all.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/bootstrap@[^/]+/dist/css/bootstrap\.min\.css#i' => $baseAssets . 'bootstrap.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/bootstrap@[^/]+/dist/js/bootstrap\.bundle\.min\.js#i' => $baseAssets . 'bootstrap.bundle.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/select2@[^/]+/dist/css/select2\.min\.css#i' => $baseAssets . 'select2.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/select2@[^/]+/dist/js/select2\.min\.js#i' => $baseAssets . 'select2.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/chart\.js(/dist/chart\.umd\.js)?#i' => $baseAssets . 'chart.umd.js',
            '#(https?:)?//cdn\.datatables\.net/[^/]+/css/jquery\.dataTables\.min\.css#i' => $baseAssets . 'jquery.dataTables.min.css',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/css/responsive\.dataTables\.min\.css#i' => $baseAssets . 'responsive.dataTables.min.css',
            '#(https?:)?//cdn\.datatables\.net/[^/]+/js/jquery\.dataTables\.min\.js#i' => $baseAssets . 'jquery.dataTables.min.js',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/js/dataTables\.responsive\.min\.js#i' => $baseAssets . 'dataTables.responsive.min.js',
            '#const FASTAPI_URL = ["\']https://estrangeinternal\.itmaranatha\.org["\'];#i' => 'const FASTAPI_URL = "' . $proxyPath . '";'
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $html);
    }
}

// Register buffer rewriter only when offline parameter or offline mode is requested
if ((isset($_GET['offline']) && ($_GET['offline'] === '1' || $_GET['offline'] === 'true')) || getenv('OFFLINE_MODE') === '1') {
    if (!defined('ESTRANGE_ASSET_REWRITER_ACTIVE')) {
        define('ESTRANGE_ASSET_REWRITER_ACTIVE', true);
        ob_start('rewrite_offline_assets');
    }
}
