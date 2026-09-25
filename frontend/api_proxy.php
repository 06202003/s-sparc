<?php
/**
 * Standalone Frontend API Proxy & Universal AI Bridge
 * 
 * Proxies API requests to the Python FastAPI backend (http://127.0.0.1:5000)
 * or falls back directly to Google Gemini API when FastAPI is unavailable.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-User-ID, X-Student-ID, X-API-Key, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$backendBaseUrl = getenv('FASTAPI_BACKEND_URL') ?: (getenv('FLASK_BASE_URL') ?: 'http://127.0.0.1:5000');

$path = '';
if (!empty($_GET['path'])) {
    $path = $_GET['path'];
} elseif (!empty($_GET['endpoint'])) {
    $path = $_GET['endpoint'];
} elseif (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} else {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($requestUri, $scriptName) === 0) {
        $path = substr($requestUri, strlen($scriptName));
        $path = explode('?', $path)[0];
    }
}

$path = '/' . ltrim($path, '/');

$queryParams = $_GET;
unset($queryParams['path'], $queryParams['endpoint']);
$queryString = http_build_query($queryParams);

$targetUrl = rtrim($backendBaseUrl, '/') . $path . ($queryString ? ('?' . $queryString) : '');

$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');

// 1. Attempt cURL request to FastAPI backend (fast 1s connect timeout)
$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1500);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

$headers = [];
$incomingHeaders = function_exists('getallheaders') ? getallheaders() : [];
foreach ($incomingHeaders as $name => $value) {
    $lower = strtolower($name);
    if (in_array($lower, ['authorization', 'content-type', 'accept', 'x-api-key', 'x-user-id', 'x-student-id'])) {
        $headers[] = "$name: $value";
    }
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']) && !empty($body)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response !== false && $httpCode > 0) {
    http_response_code($httpCode);
    if ($contentType) {
        header("Content-Type: $contentType");
    } else {
        header("Content-Type: application/json; charset=utf-8");
    }
    echo $response;
    exit;
}

// 2. Direct Gemini Fallback Handler if FastAPI is offline and it's an AI chat request
function get_gemini_api_key() {
    $keys = [
        getenv('GEMINI_API_KEY'),
        getenv('GEMINI_API_KEY_1'),
        getenv('GOOGLE_API_KEY')
    ];
    
    // Check .env file if available
    $envPaths = [__DIR__ . '/../.env', __DIR__ . '/.env', __DIR__ . '/../env', __DIR__ . '/env'];
    foreach ($envPaths as $envPath) {
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), 'GEMINI_API_KEY') === 0 || strpos(trim($line), 'GOOGLE_API_KEY') === 0) {
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $keys[] = trim(trim($parts[1]), '"\'');
                    }
                }
            }
        }
    }
    
    foreach ($keys as $k) {
        if (!empty($k)) return $k;
    }
    return null;
}

$geminiKey = get_gemini_api_key();
if ($geminiKey && !empty($body)) {
    $data = json_decode($body, true) ?: [];
    $userMessage = $data['prompt'] ?? $data['message'] ?? $data['text'] ?? '';
    
    if (!empty($userMessage)) {
        $geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . urlencode($geminiKey);
        $geminiPayload = json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $userMessage]
                    ]
                ]
            ]
        ]);
        
        $chG = curl_init($geminiUrl);
        curl_setopt($chG, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chG, CURLOPT_POST, true);
        curl_setopt($chG, CURLOPT_POSTFIELDS, $geminiPayload);
        curl_setopt($chG, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($chG, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($chG, CURLOPT_TIMEOUT, 15);
        $gResp = curl_exec($chG);
        $gCode = curl_getinfo($chG, CURLINFO_HTTP_CODE);
        curl_close($chG);
        
        if ($gResp !== false && $gCode === 200) {
            $gDec = json_decode($gResp, true);
            $aiText = $gDec['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (!empty($aiText)) {
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 'ok',
                    'response' => $aiText,
                    'message' => $aiText,
                    'source' => 'direct-gemini-fallback'
                ]);
                exit;
            }
        }
    }
}

// 3. Fallback error response
http_response_code(502);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'error' => true,
    'status' => 'error',
    'message' => 'Backend AI service unavailable: ' . ($curlError ?: 'Connection failed'),
    'target' => $targetUrl
]);
