<?php
/**
 * E-STRANGE & S-SPARC Dynamic Asset & AI Proxy Rewriter
 * 
<<<<<<< HEAD
 * Automatically detects whether the client/server is in an offline lab environment.
 * If offline, transparently intercepts the HTML output and rewrites CDN links to local
 * assets in assets/vendor/ and routes AI prompt requests to api_proxy.php.
 * 
 * Works across all pages without modifying existing PHP/HTML source code.
 */

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli' && !headers_sent()) {
=======
 * Automatically intercepts HTML output and rewrites CDN links to local
 * assets in assets/vendor/ and routes AI prompt requests to api_proxy.php.
 * 
 * Works across all pages and subfolders (root, ssparc, karina, etc.)
 */

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
>>>>>>> db592a8 (feat: implement S-SPARC Prompt Wrapped story player, BYOK telemetry, and research CSV export)
    @session_start();
}

/**
<<<<<<< HEAD
 * Core rewrite function that transforms CDN links into local vendor assets
 */
if (!function_exists('rewrite_offline_assets')) {
    function rewrite_offline_assets($html, $requestUri = null) {
=======
 * Determine the relative path to assets/vendor/ dynamically based on the current execution context
 */
if (!function_exists('get_offline_assets_base')) {
    function get_offline_assets_base() {
        // Method 1: Check running script directory vs root directory
        $baseDir = str_replace('\\', '/', realpath(__DIR__));
        $currentDir = str_replace('\\', '/', realpath(getcwd()));

        if (!empty($baseDir) && !empty($currentDir) && strpos($currentDir, $baseDir) === 0) {
            $sub = trim(substr($currentDir, strlen($baseDir)), '/');
            if (!empty($sub)) {
                $depth = count(explode('/', $sub));
                return str_repeat('../', $depth) . 'assets/vendor/';
            }
            return 'assets/vendor/';
        }

        // Method 2: Check SCRIPT_NAME / PHP_SELF / REQUEST_URI
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
>>>>>>> db592a8 (feat: implement S-SPARC Prompt Wrapped story player, BYOK telemetry, and research CSV export)
        if (empty($html) || !is_string($html)) {
            return $html;
        }

<<<<<<< HEAD
        if ($requestUri === null) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        }

        // Determine relative path to assets/vendor based on the current URI
        $baseAssets = 'assets/vendor/';
        if (stristr($requestUri, 'ssparc') !== false || stristr($requestUri, 'karina') !== false) {
            $baseAssets = '../assets/vendor/';
        }
=======
        $baseAssets = get_offline_assets_base();

        // Determine correct path to api_proxy.php
        $proxyPath = ($baseAssets === '../assets/vendor/') ? 'api_proxy.php' : 'ssparc/api_proxy.php';
>>>>>>> db592a8 (feat: implement S-SPARC Prompt Wrapped story player, BYOK telemetry, and research CSV export)

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

<<<<<<< HEAD
            // SweetAlert2 -> Local sweetalert2.all.min.js
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2@[^"\']+#i' => $baseAssets . 'sweetalert2.all.min.js',
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2#i' => $baseAssets . 'sweetalert2.all.min.js',
=======
            // SweetAlert2 -> Local sweetalert2.all.min.js & sweetalert2.min.css
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2@[^/]+/dist/sweetalert2\.min\.css#i' => $baseAssets . 'sweetalert2.min.css',
            '#https?://cdn\.jsdelivr\.net/npm/sweetalert2@[^"\']+#i' => $baseAssets . 'sweetalert2.all.min.js',
>>>>>>> db592a8 (feat: implement S-SPARC Prompt Wrapped story player, BYOK telemetry, and research CSV export)

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
<<<<<<< HEAD
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/select2/[^/]+/css/select2\.min\.css#i' => $baseAssets . 'select2.min.css',
            '#https?://cdnjs\.cloudflare\.com/ajax/libs/select2/[^/]+/js/select2\.min\.js#i' => $baseAssets . 'select2.min.js',
=======
>>>>>>> db592a8 (feat: implement S-SPARC Prompt Wrapped story player, BYOK telemetry, and research CSV export)

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

<<<<<<< HEAD
            // DataTables -> Local DataTables CSS & JS
            '#(https?:)?//cdn\.datatables\.net/v/bs5/dt-[^/]+/datatables\.min\.css#i' => $baseAssets . 'datatables.min.css',
            '#(https?:)?//cdn\.datatables\.net/v/bs5/dt-[^/]+/datatables\.min\.js#i' => $baseAssets . 'datatables.min.js',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/css/responsive\.bootstrap5\.min\.css#i' => $baseAssets . 'responsive.bootstrap5.min.css',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/js/responsive\.bootstrap5\.min\.js#i' => $baseAssets . 'responsive.bootstrap5.min.js',
=======
            // DataTables & Bootstrap 5 Extensions -> Local DataTables CSS & JS
            '#(https?:)?//cdn\.datatables\.net/v/bs5/[^/]+/datatables\.min\.js#i' => $baseAssets . 'datatables.min.js',
            '#(https?:)?//cdn\.datatables\.net/v/bs5/[^/]+/datatables\.min\.css#i' => $baseAssets . 'datatables.min.css',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/js/responsive\.bootstrap5\.min\.js#i' => $baseAssets . 'responsive.bootstrap5.min.js',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/css/responsive\.bootstrap5\.min\.css#i' => $baseAssets . 'responsive.bootstrap5.min.css',
>>>>>>> db592a8 (feat: implement S-SPARC Prompt Wrapped story player, BYOK telemetry, and research CSV export)
            '#(https?:)?//cdn\.datatables\.net/[^/]+/css/jquery\.dataTables\.min\.css#i' => $baseAssets . 'jquery.dataTables.min.css',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/css/responsive\.dataTables\.min\.css#i' => $baseAssets . 'responsive.dataTables.min.css',
            '#(https?:)?//cdn\.datatables\.net/[^/]+/js/jquery\.dataTables\.min\.js#i' => $baseAssets . 'jquery.dataTables.min.js',
            '#(https?:)?//cdn\.datatables\.net/responsive/[^/]+/js/dataTables\.responsive\.min\.js#i' => $baseAssets . 'dataTables.responsive.min.js',

<<<<<<< HEAD
            // Prettify Code Highlighting -> Local run_prettify.js
            '#https?://cdn\.jsdelivr\.net/gh/google/code-prettify[^"\']+#i' => $baseAssets . 'run_prettify.js',

            // Offline HTML5 Background Video -> Local bg_video.mp4
            '#https?://cdn\.jsdelivr\.net/gh/06202003/MainPortfolio/data/[^"\']+\.mp4#i' => $baseAssets . 'bg_video.mp4',

            // AI Backend FASTAPI_URL in S-SPARC -> Local api_proxy.php
            '#const FASTAPI_URL = ["\']https://estrangeinternal\.itmaranatha\.org["\'];#i' => 'const FASTAPI_URL = "api_proxy.php";'
=======
            // Google Code Prettify -> Local run_prettify.js
            '#https?://cdn\.jsdelivr\.net/gh/google/code-prettify[^"\']+/run_prettify\.js#i' => $baseAssets . 'run_prettify.js',
            '#https?://cdn\.rawgit\.com/google/code-prettify[^"\']+/run_prettify\.js#i' => $baseAssets . 'run_prettify.js',

            // AI Backend FASTAPI_URL in S-SPARC -> Local api_proxy.php
            '#const FASTAPI_URL = ["\']https://estrangeinternal\.itmaranatha\.org["\'];#i' => 'const FASTAPI_URL = "' . $proxyPath . '";'
>>>>>>> db592a8 (feat: implement S-SPARC Prompt Wrapped story player, BYOK telemetry, and research CSV export)
        ];

        $rewritten = preg_replace(array_keys($replacements), array_values($replacements), $html);

        // Clean integrity & crossorigin attributes on rewritten local assets
        $rewritten = preg_replace('#(src|href)=([\'"][^\'"]*assets/vendor/[^\'"]*[\'"])\s+integrity=[\'"][^\'"]*[\'"]\s+crossorigin=[\'"][^\'"]*[\'"]#i', '$1=$2', $rewritten);
        $rewritten = preg_replace('#(src|href)=([\'"][^\'"]*assets/vendor/[^\'"]*[\'"])\s+integrity=[\'"][^\'"]*[\'"]#i', '$1=$2', $rewritten);
        $rewritten = preg_replace('#(src|href)=([\'"][^\'"]*assets/vendor/[^\'"]*[\'"])\s+crossorigin=[\'"][^\'"]*[\'"]#i', '$1=$2', $rewritten);

        return $rewritten;
    }
}

<<<<<<< HEAD
// 1. Check for manual override via URL parameter if needed: ?offline=1 or ?offline=0
if (isset($_GET['offline'])) {
    $_SESSION['is_lab_offline'] = ($_GET['offline'] === '1' || $_GET['offline'] === 'true');
}

// 2. Automatic Connectivity Check (cached in session to avoid repeating on every request)
if (!isset($_SESSION['is_lab_offline'])) {
    $isOffline = false;
    // Fast socket check to public DNS / CDN (0.2s timeout)
    $fp = @fsockopen('1.1.1.1', 53, $errno, $errstr, 0.2);
    if (!$fp) {
        $fp2 = @fsockopen('8.8.8.8', 53, $errno, $errstr, 0.2);
        if (!$fp2) {
            $isOffline = true;
        } else {
            fclose($fp2);
        }
    } else {
        fclose($fp);
    }
    $_SESSION['is_lab_offline'] = $isOffline;
}

// 3. Register Output Buffer Rewriter if Offline Mode is Active
if (!empty($_SESSION['is_lab_offline']) || (isset($force_offline_assets) && $force_offline_assets === true)) {
=======
// Activate Output Buffer Rewriter by default (unless explicitly disabled via ?online_cdn=1)
$disableLocalAssets = (isset($_GET['online_cdn']) && ($_GET['online_cdn'] === '1' || $_GET['online_cdn'] === 'true'));

if (!$disableLocalAssets) {
>>>>>>> db592a8 (feat: implement S-SPARC Prompt Wrapped story player, BYOK telemetry, and research CSV export)
    if (!defined('ESTRANGE_ASSET_REWRITER_ACTIVE')) {
        define('ESTRANGE_ASSET_REWRITER_ACTIVE', true);
        ob_start('rewrite_offline_assets');
    }
}
