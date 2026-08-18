<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$backendBase = backend_base();
$httpClient = new Client([
    'base_uri' => $backendBase . '/',
    'timeout' => 10,
]);

if (!empty($_SESSION['flask_cookie'])) {
    try {
        $httpClient->post('logout', [
            'headers' => ['Cookie' => $_SESSION['flask_cookie']],
        ]);
    } catch (RequestException $e) {
        // swallow errors; logout is best-effort
    } catch (\Throwable $e) {
        // ignore
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: login.php');
exit;
