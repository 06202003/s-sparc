<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

$backendBase = backend_base();
$httpClient = new Client([
  'base_uri' => $backendBase . '/',
  'timeout' => 20,
]);

function call_flask($path, $payload) {
  global $httpClient;
  try {
    $resp = $httpClient->post(ltrim($path, '/'), ['json' => $payload]);
    $status = $resp->getStatusCode();
    $body = (string) $resp->getBody();
    $data = json_decode($body, true);
    return ['data' => $data, 'status' => $status];
  } catch (\Throwable $e) {
    if ($e instanceof RequestException) {
      $resp = $e->getResponse();
      $status = $resp ? $resp->getStatusCode() : null;
      $body = $resp ? (string) $resp->getBody() : null;
      $data = $body ? json_decode($body, true) : null;
      $message = $data['error'] ?? $e->getMessage();
      return ['error' => $message, 'status' => $status, 'data' => $data];
    }
    return ['error' => $e->getMessage(), 'status' => null, 'data' => null];
  }
}

$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username === '' || $email === '' || $password === '') {
        $error = 'Semua field wajib diisi.';
    } else {
        $result = call_flask('/register', [
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ]);
    if (!empty($result['data']['error'])) {
      $error = $result['data']['error'];
    } elseif (($result['status'] ?? 500) >= 500) {
      $error = 'Server backend sedang bermasalah (status ' . ($result['status'] ?? 'unknown') . '). Coba lagi nanti.';
    } elseif (($result['status'] ?? 500) >= 400) {
      $error = 'Gagal registrasi (status ' . ($result['status'] ?? 'unknown') . ').';
    } elseif (!empty($result['error'])) {
      $error = 'Tidak dapat menghubungi backend: ' . $result['error'];
    } else {
      // Redirect ke login dengan pesan sukses
      header('Location: login.php?registered=1');
      exit;
    }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Chat Assistant</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: 'Manrope', system-ui, -apple-system, sans-serif; }
  </style>
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900">
  <div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-10 border-b border-slate-200/70 bg-white/80 backdrop-blur">
      <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-[#00A0A5] text-white grid place-items-center font-semibold">AI</div>
          <div>
            <div class="text-lg font-semibold">Chat Assistant</div>
            <div class="text-xs text-slate-500">Create an account to access courses, chat, and dashboards.</div>
          </div>
        </div>
        <!-- <nav class="flex items-center gap-3 text-sm font-medium">
          <a class="text-slate-900 font-semibold" href="register.php">Register</a>
          <a class="text-slate-700 hover:text-slate-900" href="login.php">Login</a>
        </nav> -->
      </div>
    </header>

    <main class="flex-1 flex items-center">
      <div class="max-w-md mx-auto px-4 py-10 w-full">
        <section class="glass rounded-2xl border border-white/60 shadow-lg p-6 sm:p-8 bg-white/80">
          <h1 class="text-xl font-semibold text-slate-900 mb-2">Create a new account</h1>
          <p class="text-sm text-slate-600 mb-4">Use your institutional email and a strong password.</p>

          <?php if ($error): ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($message): ?>
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($message) ?></div>
          <?php endif; ?>

          <form method="post" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1" for="username">Username</label>
              <input class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="text" id="username" name="username" required>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email</label>
              <input class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="email" id="email" name="email" required>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1" for="password">Password</label>
              <input class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-[#00A0A5] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#008488] focus:outline-none focus:ring focus:ring-[#00A0A5]/20">Register</button>
          </form>

          <p class="mt-4 text-xs text-slate-500">Do you already have an account? <a href="login.php" class="text-[#00A0A5] font-medium hover:underline">Login here</a>.</p>
        </section>
      </div>
    </main>
  </div>
</body>
</html>
