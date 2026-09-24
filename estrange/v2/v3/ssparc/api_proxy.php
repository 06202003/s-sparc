<?php
/**
 * S-SPARC Universal Backend API Proxy
 * Relays API requests to the Python AI backend (local or online https://estrangeinternal.itmaranatha.org)
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Allow CORS for local intranet & lab clients
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-User-ID, X-Student-ID, X-API-Key, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Target backend URL (can be overridden via environment or config)
$backendBaseUrl = getenv('FASTAPI_BACKEND_URL') ?: 'https://estrangeinternal.itmaranatha.org';

// Determine the path / endpoint
$path = '';
if (!empty($_GET['path'])) {
    $path = $_GET['path'];
} elseif (!empty($_GET['endpoint'])) {
    $path = $_GET['endpoint'];
} elseif (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} else {
    // Extract relative path from REQUEST_URI if accessed like api_proxy.php/api/...
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($requestUri, $scriptName) === 0) {
        $path = substr($requestUri, strlen($scriptName));
        $path = explode('?', $path)[0]; // remove query string
    }
}

$path = '/' . ltrim($path, '/');

// Intercept Wrapped endpoints to guarantee 100% accurate assessment metadata from live DB
if (preg_match('#^/api/(?:domain/)?assessments/([0-9a-zA-Z_\-]+)/wrapped#i', $path, $m)) {
    require_once __DIR__ . '/../_config.php';
    require_once __DIR__ . '/_wrapped_service.php';
    $assessmentId = $m[1];
    $userId = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? 'student_demo');
    $wrappedResult = ssparc_get_wrapped_for_assessment($db, $userId, $assessmentId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($wrappedResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Build query string if any (excluding 'path' or 'endpoint' param)
$queryParams = $_GET;
unset($queryParams['path'], $queryParams['endpoint']);
$queryString = http_build_query($queryParams);

$targetUrl = rtrim($backendBaseUrl, '/') . $path . ($queryString ? ('?' . $queryString) : '');

// Initialize cURL
$ch = curl_init($targetUrl);

$method = $_SERVER['REQUEST_METHOD'];
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

// Ignore SSL errors if internal certificate
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

// Forward Headers
$headers = [];
$incomingHeaders = function_exists('getallheaders') ? getallheaders() : [];
$hasUserId = false;

// Also check $_SERVER for headers if getallheaders is missing or FastCGI
if (empty($incomingHeaders)) {
    foreach ($_SERVER as $key => $val) {
        if (strpos($key, 'HTTP_') === 0) {
            $hName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $incomingHeaders[$hName] = $val;
        }
    }
}

foreach ($incomingHeaders as $name => $value) {
    $lower = strtolower($name);
    if (in_array($lower, ['authorization', 'content-type', 'accept', 'x-api-key', 'x-user-id', 'x-student-id'])) {
        $headers[] = "$name: $value";
        if ($lower === 'x-user-id') {
            $hasUserId = true;
        }
    }
}

// Fallback: If X-User-ID header is missing from client, pass it from active PHP session or GET param
if (!$hasUserId) {
    if (!empty($_GET['user_id'])) {
        $headers[] = "X-User-ID: " . $_GET['user_id'];
    } elseif (!empty($_SESSION['user_id'])) {
        $headers[] = "X-User-ID: " . $_SESSION['user_id'];
    }
}

if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

// Forward Request Body for POST/PUT/PATCH/DELETE
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    $body = file_get_contents('php://input');
    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
}

// Execute cURL request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Failed to connect to backend AI server: ' . $curlError,
        'target' => $targetUrl
    ]);
    exit;
}

http_response_code($httpCode ?: 200);
if ($contentType) {
    header("Content-Type: $contentType");
} else {
    header("Content-Type: application/json");
}

echo $response;
