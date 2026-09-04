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
  <style>
/* Premium Teal Dropdown Styling for E-STRANGE & S-SPARC */
/* Ensure SweetAlert2 hidden select is never displayed */
.swal2-container select,
.swal2-popup select,
.swal2-select {
  display: none !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select), .form-select, .custom-select {
  appearance: none !important;
  -webkit-appearance: none !important;
  -moz-appearance: none !important;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2300A0A5' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
  background-repeat: no-repeat !important;
  background-position: right 0.85rem center !important;
  background-size: 1.15rem 1.15rem !important;
  padding-left: 1rem !important;
  padding-right: 2.5rem !important;
  padding-top: 0.5rem !important;
  padding-bottom: 0.5rem !important;
  min-width: 130px !important;
  min-height: 40px !important;
  border-radius: 0.75rem !important;
  border: 1.5px solid #cbd5e1 !important;
  background-color: #ffffff !important;
  color: #0f172a !important;
  font-weight: 600 !important;
  font-size: 0.875rem !important;
  line-height: 1.25rem !important;
  transition: all 0.2s ease-in-out !important;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
  cursor: pointer !important;
  flex-shrink: 0 !important;
  display: inline-block !important;
  box-sizing: border-box !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select):hover, .form-select:hover {
  border-color: #00A0A5 !important;
  background-color: #f8fafc !important;
  box-shadow: 0 4px 12px rgba(0, 160, 165, 0.08) !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select):focus, .form-select:focus {
  outline: none !important;
  border-color: #00A0A5 !important;
  box-shadow: 0 0 0 3px rgba(0, 160, 165, 0.2) !important;
  background-color: #ffffff !important;
}

/* Ensure Select2 Native Input Remains Completely Hidden */
select.select2-hidden-accessible {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
  padding: 0 !important;
  margin: 0 !important;
  border: 0 !important;
  opacity: 0 !important;
  position: absolute !important;
  pointer-events: none !important;
}

/* Select2 Plugin Custom Teal Enhancements */
.select2-container--default .select2-selection--single {
  border-radius: 0.75rem !important;
  border: 1.5px solid #cbd5e1 !important;
  height: 42px !important;
  min-width: 140px !important;
  padding: 6px 12px !important;
  font-weight: 600 !important;
  font-size: 0.875rem !important;
  transition: all 0.2s ease-in-out !important;
}

.select2-container--default .select2-selection--single:hover {
  border-color: #00A0A5 !important;
}

.select2-container--default.select2-container--open .select2-selection--single,
.select2-container--default.select2-container--focus .select2-selection--single {
  border-color: #00A0A5 !important;
  box-shadow: 0 0 0 3px rgba(0, 160, 165, 0.2) !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
  background-color: #00A0A5 !important;
  color: #ffffff !important;
}

</style>
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
