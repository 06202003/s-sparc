<?php
/**
 * E-STRANGE & S-SPARC Universal AI Proxy Bridge
 * 
 * Proxies API requests to the Python FastAPI backend (http://127.0.0.1:5000)
 * or falls back directly to Google Gemini API when FastAPI is unavailable.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$rawInput = file_get_contents('php_input') ?: file_get_contents('php://input');
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$parsedUri = parse_url($requestUri, PHP_URL_PATH);
$queryString = $_SERVER['QUERY_STRING'] ?? '';

// Forward to local FastAPI backend on port 5000 if active
$fastApiBase = 'http://127.0.0.1:5000';
$targetUrl = $fastApiBase . $parsedUri;
if (!empty($queryString)) {
    $targetUrl .= '?' . $queryString;
}

// 1. Attempt cURL request to FastAPI backend (0.5s connection timeout)
$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

if (!empty($rawInput)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput);
}

$headers = [];
foreach (getallheaders() as $name => $value) {
    if (strtolower($name) !== 'host' && strtolower($name) !== 'content-length') {
        $headers[] = "$name: $value";
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($httpCode > 0 && empty($curlErr)) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo $response;
    exit;
}

// 2. Direct Gemini Fallback Handler if FastAPI is offline
function get_gemini_api_key() {
    $keys = [
        getenv('GEMINI_API_KEY'),
        getenv('GEMINI_API_KEY_1'),
        getenv('GOOGLE_API_KEY')
    ];
    
    // Check .env file if available
    $envPath = __DIR__ . '/../env';
    if (!file_exists($envPath)) {
        $envPath = __DIR__ . '/env';
    }
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), 'GEMINI_API_KEY') === 0 || strpos(trim($line), 'GOOGLE_API_KEY') === 0) {
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $keys[] = trim($parts[1]);
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
if (!$geminiKey) {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'message' => 'AI Service Unavailable: Neither FastAPI daemon nor Gemini API Key configured.'
    ]);
    exit;
}

// Call Gemini 1.5/2.0 Flash REST API directly
$data = json_decode($rawInput, true) ?: [];
$userMessage = $data['prompt'] ?? $data['message'] ?? $data['text'] ?? 'Hello';

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
$gResp = curl_exec($chG);
$gCode = curl_getinfo($chG, CURLINFO_HTTP_CODE);
curl_close($chG);

$gDec = json_decode($gResp, true);
$aiText = $gDec['candidates'][0]['content']['parts'][0]['text'] ?? 'AI service is temporarily busy. Please try again.';

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ok',
    'response' => $aiText,
    'message' => $aiText,
    'source' => 'direct-gemini-fallback'
]);
