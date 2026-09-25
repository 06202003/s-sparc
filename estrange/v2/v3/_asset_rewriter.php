<?php
/**
 * E-STRANGE & S-SPARC Dynamic Asset & AI Proxy Rewriter
 * 
 * Intercepts HTML output and rewrites external CDN dependencies to local vendor assets,
 * removing blocking external preconnects so the entire application loads instantly
 * without 4-5 minute network timeouts when offline.
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
 * Core rewrite function that transforms CDN links into local vendor assets
 */
if (!function_exists('rewrite_offline_assets')) {
    function rewrite_offline_assets($html) {
        if (empty($html) || !is_string($html)) {
            return $html;
        }

        // Only skip rewrite if user explicitly requested online CDN mode
        if (isset($_GET['online']) && ($_GET['online'] === '1' || $_GET['online'] === 'true')) {
            return $html;
        }

        $baseAssets = get_offline_assets_base();
        $proxyPath = ($baseAssets === '../assets/vendor/') ? 'api_proxy.php' : 'ssparc/api_proxy.php';

        // 1. Remove blocking preconnect & dns-prefetch tags for external CDNs
        $preconnectPattern = '#<link\s+[^>]*rel=["\'](?:preconnect|dns-prefetch)["\'][^>]*>#i';
        $html = preg_replace($preconnectPattern, '', $html);

        // 2. Mapping CDN URLs to Local Vendor Assets
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
            '#https?://cdn\.jsdelivr\.net/npm/particles\.js@[^/]+/particles\.min\.js#i' => $baseAssets . 'particles.min.js',
            '#(https?:)?//cdn\.datatables\.net/[^/]+/css/jquery\.dataTables\.min\.css#i' => $baseAssets . 'jquery.dataTables.min.css',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/css/responsive\.dataTables\.min\.css#i' => $baseAssets . 'responsive.dataTables.min.css',
            '#(https?:)?//cdn\.datatables\.net/[^/]+/js/jquery\.dataTables\.min\.js#i' => $baseAssets . 'jquery.dataTables.min.js',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/js/dataTables\.responsive\.min\.js#i' => $baseAssets . 'dataTables.responsive.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/katex@[^/]+/dist/katex\.min\.css#i' => $baseAssets . 'katex.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/katex@[^/]+/dist/katex\.min\.js#i' => $baseAssets . 'katex.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/katex@[^/]+/dist/contrib/auto-render\.min\.js#i' => $baseAssets . 'auto-render.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/marked@[^/]+/marked\.min\.js#i' => $baseAssets . 'marked.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/marked/marked\.min\.js#i' => $baseAssets . 'marked.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/dompurify@[^/]+/dist/purify\.min\.js#i' => $baseAssets . 'purify.min.js',
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/highlight\.js/[^/]+/highlight\.min\.js#i' => $baseAssets . 'highlight.min.js',
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/highlight\.js/[^/]+/styles/atom-one-dark\.min\.css#i' => $baseAssets . 'atom-one-dark.min.css',
            '#const FASTAPI_URL = ["\']https://estrangeinternal\.itmaranatha\.org["\'];#i' => 'const FASTAPI_URL = "' . $proxyPath . '";'
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $html);
    }
}

// Automatically start output buffering to rewrite all output by default
if (!defined('ESTRANGE_ASSET_REWRITER_ACTIVE')) {
    define('ESTRANGE_ASSET_REWRITER_ACTIVE', true);
    ob_start('rewrite_offline_assets');
}
