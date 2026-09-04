<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/* =========================
   START SESSION DULU
========================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   CEK LOGIN
========================= */
if (empty($_SESSION['user_id']) || empty($_SESSION['flask_cookie'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'Guest';

$backendBase = backend_base();

$httpClient = new Client([
    'base_uri' => $backendBase . '/',
    'timeout'  => 20,
]);

/* =========================
   CALL FLASK API (COOKIE BASED)
========================= */
function call_flask_api($path, $payload) {
    global $httpClient;

    try {
        $options = [
            'json' => $payload,
            'headers' => [
                // kirim cookie session flask
                'Cookie' => $_SESSION['flask_cookie']
            ]
        ];

        $resp = $httpClient->post(ltrim($path, '/'), $options);

        return [
            'status' => $resp->getStatusCode(),
            'data'   => json_decode((string)$resp->getBody(), true)
        ];

    } catch (RequestException $e) {

        $resp = $e->getResponse();
        $status = $resp ? $resp->getStatusCode() : null;
        $body = $resp ? json_decode((string)$resp->getBody(), true) : null;

        return [
            'status' => $status,
            'error'  => $body['error'] ?? $e->getMessage(),
            'data'   => $body
        ];

    } catch (\Throwable $e) {
        return [
            'status' => null,
            'error'  => $e->getMessage(),
            'data'   => null
        ];
    }
}

/* =========================
   HANDLE FORM
========================= */
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old_password = trim($_POST['old_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');

    if (!$old_password || !$new_password) {
        $error = "Semua field wajib diisi.";
    } else {

        $result = call_flask_api('/change-password', [
            'old_password' => $old_password,
            'new_password' => $new_password
        ]);

        if (!empty($result['error'])) {

            // kalau unauthorized → paksa login ulang
            if (($result['status'] ?? 0) == 401) {
                session_destroy();
                header("Location: login.php");
                exit();
            }

            $error = $result['error'];

        } elseif (($result['status'] ?? 500) >= 400) {

            $error = $result['data']['error'] ?? 'Gagal mengubah password.';

        } else {

            $success = $result['data']['message'] ?? 'Password berhasil diganti.';
            header("Location: courses.php?password=updated");
            exit();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password</title>
<script src="https://cdn.tailwindcss.com"></script>
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

<!-- <body class="bg-gray-100 min-h-screen flex"> -->
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900">
  <div class="min-h-screen flex flex-col">
<!-- SIDEBAR -->
<!-- <aside class="w-64 bg-gray-900 text-white flex flex-col p-6">
    <h2 class="text-2xl font-bold mb-8">Dashboard</h2>

    <nav class="flex flex-col gap-4 text-sm">
        <a href="courses.php" class="hover:text-gray-300">Courses</a>
        <a href="chat.php" class="hover:text-gray-300">Chat</a>
        <a href="change_password.php" class="text-yellow-400 font-semibold">Change Password</a>
        <a href="logout.php" class="hover:text-red-400 mt-6">Logout</a>
    </nav>
</aside> -->

    <header class="sticky top-0 z-10 border-b border-slate-200/70 bg-white/80 backdrop-blur">
      <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-[#00A0A5] text-white grid place-items-center font-semibold">AI</div>
          <div>
            <div class="text-lg font-semibold">Change Password</div>
            <div class="text-xs text-slate-500">Logged in as <span class="font-medium"><?php echo htmlspecialchars($username); ?></span></div>
          </div>
        </div>
        <nav class="flex items-center gap-3 text-sm font-medium">
          <a class="text-slate-900 font-semibold active" href="courses.php">Courses</a>
          <!-- <a class="text-slate-700 hover:text-slate-900" href="chat.php">Chat</a> -->
          <a class="text-slate-400 hover:text-slate-700" href="dashboard.php">Dashboard</a>
          <a href="logout.php" class="ml-2 inline-flex items-center gap-2 rounded-full bg-red-800 text-white px-3 py-1 hover:bg-red-600 shadow-sm">Logout</a>
        </nav>
      </div>
    </header>

<!-- MAIN CONTENT -->
<main class="flex-1 p-10">

    <div class="max-w-xl mx-auto bg-white shadow-lg rounded-2xl p-8">

        <h1 class="text-2xl font-bold mb-6">Change Password</h1>

        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg text-sm">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">

            <div>
                <label class="block text-sm font-medium mb-1">Old Password</label>
                <input type="password" name="old_password" required
                    class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-gray-200 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">New Password</label>
                <input type="password" name="new_password" required
                    class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-gray-200 focus:outline-none">
            </div>

            <button type="submit"
                class="w-full bg-gray-900 text-white py-2 rounded-lg font-semibold hover:bg-gray-800">
                Update Password
            </button>

        </form>

    </div>

</main>

</div>

</body>
</html>
