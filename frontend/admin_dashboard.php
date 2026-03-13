<?php
// Admin dashboard page - shows aggregated stats. Requires admin login.
require 'vendor/autoload.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$httpClient = new \GuzzleHttp\Client(['base_uri' => getenv('API_BASE') ?: 'http://localhost:5000/']);
$flaskCookie = $_SESSION['flask_cookie'] ?? null;
if ($flaskCookie) {
    $cookieJar = \GuzzleHttp\Cookie\CookieJar::fromArray(['flask_cookie' => $flaskCookie], parse_url(getenv('API_BASE') ?: 'http://localhost:5000/', PHP_URL_HOST));
}

try {
    // Log token dan cookie untuk debugging
    $authToken = $_SESSION['auth_token'] ?? 'No Token';
    $cookies = json_encode($_COOKIE);

    $resp = $httpClient->request('GET', 'admin-dashboard', [
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $authToken,
        ],
        'cookies' => $cookieJar,
    ]);

    // Log respons untuk debugging
    $responseStatusCode = $resp->getStatusCode();
    $responseHeaders = json_encode($resp->getHeaders());
    $responseBody = $resp->getBody()->getContents();

    $data = json_decode($responseBody, true);
} catch (\GuzzleHttp\Exception\RequestException $e) {
    // Log error untuk debugging
    $errorMessage = $e->getMessage();
    $responseStatusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'No Response';
    $responseHeaders = $e->hasResponse() ? json_encode($e->getResponse()->getHeaders()) : 'No Headers';
    $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No Body';

    $data = ['error' => 'Could not fetch admin data: ' . $errorMessage];
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="/assets/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <h1>Admin Dashboard</h1>
    <?php if(isset($data['error'])): ?>
      <div class="alert alert-danger">
        <?= htmlspecialchars($data['error']) ?>
        <pre>
          <strong>Debug Info:</strong>
          Authorization Token: <?= htmlspecialchars($authToken) ?>
          Cookies: <?= htmlspecialchars($cookies) ?>
          Response Status Code: <?= htmlspecialchars($responseStatusCode) ?>
          Response Headers: <?= htmlspecialchars($responseHeaders) ?>
          Response Body: <?= htmlspecialchars($responseBody) ?>
        </pre>
      </div>
    <?php else: ?>
      <div class="grid">
        <div class="card">
          <h3>Total assessments</h3>
          <p><?= intval($data['total_assessments']) ?></p>
        </div>
        <div class="card">
          <h3>Assessments ended</h3>
          <p><?= intval($data['assessments_ended']) ?></p>
        </div>
        <div class="card">
          <h3>Total users</h3>
          <p><?= intval($data['total_users']) ?></p>
        </div>
        <div class="card">
          <h3>Total points awarded</h3>
          <p><?= number_format(floatval($data['total_points_awarded']),2) ?></p>
        </div>
        <div class="card">
          <h3>Total Energy (kWh)</h3>
          <p id="totalEnergy">Loading...</p>
        </div>
        <div class="card">
          <h3>Total Carbon (kg)</h3>
          <p id="totalCarbon">Loading...</p>
        </div>
        <div class="card">
          <h3>Total Water (ml)</h3>
          <p id="totalWater">Loading...</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
