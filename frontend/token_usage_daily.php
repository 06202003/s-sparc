<?php
require __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$backendBase = backend_base();
$httpClient = new Client([
    'base_uri' => $backendBase . '/',
    'timeout'  => 10,
]);

header('Content-Type: application/json');

if (empty($_SESSION['flask_cookie'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Silakan login.']);
    exit;
}

$options = [
    'headers' => [
        'Content-Type' => 'application/json',
        'Cookie'       => $_SESSION['flask_cookie'],
    ],
];

try {
    $resp = $httpClient->request('GET', 'token-usage-daily', $options);
    $status = $resp->getStatusCode();
    $body   = (string) $resp->getBody();
    http_response_code($status);
    echo $body !== '' ? $body : json_encode(['error' => 'Empty response from backend']);
} catch (RequestException $e) {
    $resp = $e->getResponse();
    $status = $resp ? $resp->getStatusCode() : 500;
    $body   = $resp ? (string) $resp->getBody() : null;
    http_response_code($status);
    if ($body) {
        echo $body;
    } else {
        echo json_encode(['error' => $e->getMessage()]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
