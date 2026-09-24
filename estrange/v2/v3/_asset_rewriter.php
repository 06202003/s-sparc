<?php
/**
 * E-STRANGE & S-SPARC Dynamic Asset & AI Proxy Rewriter
 * 
 * Automatically detects whether local offline assets in assets/vendor/ are available.
 * If running offline / in local lab without internet, it rewrites CDN links to local vendor assets.
 * If running online on production (e.g. cPanel) with CDN access, it preserves CDN links.
 */

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli' && !headers_sent()) {
    @session_start();
}

/**
 * Check whether offline rewriting should be active
 */
if (!function_exists('should_use_offline_assets')) {
    function should_use_offline_assets() {
        // Explicit force via URL parameter
        if (isset($_GET['offline'])) {
            return ($_GET['offline'] === '1' || $_GET['offline'] === 'true');
        }
        if (isset($_GET['online']) || isset($_GET['online_cdn'])) {
            return false;
        }

        // Check if assets/vendor directory actually exists
        $vendorDir = __DIR__ . '/assets/vendor/';
        if (!is_dir($vendorDir)) {
            return false;
        }

        // Check environment variable
        $envOffline = getenv('OFFLINE_MODE') ?: getenv('LAB_OFFLINE');
        if ($envOffline !== false) {
            return ($envOffline === '1' || strtolower($envOffline) === 'true');
        }

        // Default: If assets/vendor exists on local environment (localhost / 127.0.0.1), enable local assets
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return true;
        }

        return false;
    }
}

/**
 * Determine relative path to assets/vendor/
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
 * Core rewrite function that transforms CDN links into local vendor assets
 */
if (!function_exists('rewrite_offline_assets')) {
    function rewrite_offline_assets($html) {
        if (empty($html) || !is_string($html)) {
            return $html;
        }

        $baseAssets = get_offline_assets_base();
        $proxyPath = ($baseAssets === '../assets/vendor/') ? 'api_proxy.php' : 'ssparc/api_proxy.php';

        // Clean preconnect links
        $html = preg_replace('#<link rel="preconnect"[^>]*>\s*#i', '', $html);

        // Mapping CDN URLs to Local Vendor Assets
        $replacements = [
            // Google Fonts -> Local fonts.css
            '#https?://fonts\.googleapis\.com/css2\?[^"\']+#i' => $baseAssets . 'fonts.css',
            '#https?://fonts\.googleapis\.com/css\?[^"\']+#i' => $baseAssets . 'fonts.css',

            // Tailwind CSS Runtime -> Local tailwindcss.js
            '#https?://cdn\.tailwindcss\.com#i' => $baseAssets . 'tailwindcss.js',

            // jQuery -> Local jquery.min.js
            '#https?://code\.jquery\.com/jquery-[^"\']+\.js#i' => $baseAssets . 'jquery.min.js',

            // SweetAlert2 -> Local sweetalert2.all.min.js & sweetalert2.min.css
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2@[^/]+/dist/sweetalert2\.min\.css#i' => $baseAssets . 'sweetalert2.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2@[^"\']+#i' => $baseAssets . 'sweetalert2.all.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2#i' => $baseAssets . 'sweetalert2.all.min.js',

            // FontAwesome -> Local fontawesome/all.min.css
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/font-awesome/[^/]+/css/all\.min\.css#i' => $baseAssets . 'fontawesome/all.min.css',

            // Bootstrap 5 -> Local bootstrap.min.css & bundle
            '#https?://cdn\.jsdelivr\.net/npm/bootstrap@[^/]+/dist/css/bootstrap\.min\.css#i' => $baseAssets . 'bootstrap.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/bootstrap@[^/]+/dist/js/bootstrap\.bundle\.min\.js#i' => $baseAssets . 'bootstrap.bundle.min.js',

            // Particles.js -> Local particles.min.js
            '#https?://cdn\.jsdelivr\.net/(npm/)?particles\.js@[^/]+/particles\.min\.js#i' => $baseAssets . 'particles.min.js',
            '#https?://cdn\.jsdelivr\.net/particles\.js/[^/]+/particles\.min\.js#i' => $baseAssets . 'particles.min.js',

            // Select2 -> Local select2.min.css & select2.min.js
            '#https?://cdn\.jsdelivr\.net/npm/select2@[^/]+/dist/css/select2\.min\.css#i' => $baseAssets . 'select2.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/select2@[^/]+/dist/js/select2\.min\.js#i' => $baseAssets . 'select2.min.js',
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/select2/[^/]+/css/select2\.min\.css#i' => $baseAssets . 'select2.min.css',
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/select2/[^/]+/js/select2\.min\.js#i' => $baseAssets . 'select2.min.js',

            // Marked & DOMPurify -> Local marked.min.js & purify.min.js
            '#https?://cdn\.jsdelivr\.net/npm/marked(/marked)?\.min\.js#i' => $baseAssets . 'marked.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/dompurify@[^/]+/dist/purify\.min\.js#i' => $baseAssets . 'purify.min.js',

            // Highlight.js -> Local highlight.min.js & atom-one-dark.min.css
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/highlight\.js/[^/]+/styles/atom-one-dark\.min\.css#i' => $baseAssets . 'atom-one-dark.min.css',
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/highlight\.js/[^/]+/highlight\.min\.js#i' => $baseAssets . 'highlight.min.js',

            // KaTeX -> Local katex.min.css, katex.min.js, auto-render.min.js
            '#https?://cdn\.jsdelivr\.net/npm/katex@[^/]+/dist/katex\.min\.css#i' => $baseAssets . 'katex.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/katex@[^/]+/dist/katex\.min\.js#i' => $baseAssets . 'katex.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/katex@[^/]+/dist/contrib/auto-render\.min\.js#i' => $baseAssets . 'auto-render.min.js',

            // Chart.js -> Local chart.umd.js
            '#https?://cdn\.jsdelivr\.net/npm/chart\.js(/dist/chart\.umd\.js)?#i' => $baseAssets . 'chart.umd.js',

            // DataTables & Bootstrap 5 Extensions -> Local DataTables CSS & JS
            '#(https?:)?//cdn\.datatables\.net/v/bs5/[^/]+/datatables\.min\.js#i' => $baseAssets . 'datatables.min.js',
            '#(https?:)?//cdn\.datatables\.net/v/bs5/[^/]+/datatables\.min\.css#i' => $baseAssets . 'datatables.min.css',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/js/responsive\.bootstrap5\.min\.js#i' => $baseAssets . 'responsive.bootstrap5.min.js',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/css/responsive\.bootstrap5\.min\.css#i' => $baseAssets . 'responsive.bootstrap5.min.css',
            '#(https?:)?//cdn\.datatables\.net/[^/]+/css/jquery\.dataTables\.min\.css#i' => $baseAssets . 'jquery.dataTables.min.css',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/css/responsive\.dataTables\.min\.css#i' => $baseAssets . 'responsive.dataTables.min.css',
            '#(https?:)?//cdn\.datatables\.net/[^/]+/js/jquery\.dataTables\.min\.js#i' => $baseAssets . 'jquery.dataTables.min.js',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/js/dataTables\.responsive\.min\.js#i' => $baseAssets . 'dataTables.responsive.min.js',

            // Google Code Prettify -> Local run_prettify.js
            '#https?://cdn\.jsdelivr\.net/gh/google/code-prettify[^"\']+/run_prettify\.js#i' => $baseAssets . 'run_prettify.js',
            '#https?://cdn\.rawgit\.com/google/code-prettify[^"\']+/run_prettify\.js#i' => $baseAssets . 'run_prettify.js',

            // AI Backend FASTAPI_URL in S-SPARC -> Local api_proxy.php
            '#const FASTAPI_URL = ["\']https://estrangeinternal\.itmaranatha\.org["\'];#i' => 'const FASTAPI_URL = "' . $proxyPath . '";'
        ];

        $rewritten = preg_replace(array_keys($replacements), array_values($replacements), $html);

        // Clean integrity & crossorigin attributes on rewritten local assets
        $rewritten = preg_replace('#(src|href)=([\'"][^\'"]*assets/vendor/[^\'"]*[\'"])\s+integrity=[\'"][^\'"]*[\'"]\s+crossorigin=[\'"][^\'"]*[\'"]#i', '$1=$2', $rewritten);
        $rewritten = preg_replace('#(src|href)=([\'"][^\'"]*assets/vendor/[^\'"]*[\'"])\s+integrity=[\'"][^\'"]*[\'"]#i', '$1=$2', $rewritten);
        $rewritten = preg_replace('#(src|href)=([\'"][^\'"]*assets/vendor/[^\'"]*[\'"])\s+crossorigin=[\'"][^\'"]*[\'"]#i', '$1=$2', $rewritten);

        return $rewritten;
    }
}

// Only activate Output Buffer Rewriter if offline mode is explicitly detected and local assets exist
if (should_use_offline_assets()) {
    if (!defined('ESTRANGE_ASSET_REWRITER_ACTIVE')) {
        define('ESTRANGE_ASSET_REWRITER_ACTIVE', true);
        ob_start('rewrite_offline_assets');
    }
}
