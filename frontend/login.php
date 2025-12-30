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

function call_flask_login($path, $payload) {
  global $httpClient;
  try {
    $resp = $httpClient->post(ltrim($path, '/'), ['json' => $payload]);
    $status = $resp->getStatusCode();
    $body = (string) $resp->getBody();
    $data = json_decode($body, true);
    $cookieHeaders = $resp->getHeader('Set-Cookie');
    $cookie = $cookieHeaders ? $cookieHeaders[0] : null;
    return ['data' => $data, 'status' => $status, 'cookie' => $cookie];
  } catch (\Throwable $e) {
    if ($e instanceof RequestException) {
      $resp = $e->getResponse();
      $status = $resp ? $resp->getStatusCode() : null;
      $body = $resp ? (string) $resp->getBody() : null;
      $data = $body ? json_decode($body, true) : null;
      $message = $data['error'] ?? $e->getMessage();
      $cookieHeaders = $resp ? $resp->getHeader('Set-Cookie') : [];
      $cookie = $cookieHeaders ? $cookieHeaders[0] : null;
      return ['error' => $message, 'status' => $status, 'data' => $data, 'cookie' => $cookie];
    }
    return ['error' => $e->getMessage(), 'status' => null, 'data' => null, 'cookie' => null];
  }
}

$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $result = call_flask_login('/login', [
            'username' => $username,
            'password' => $password,
        ]);
        if (!empty($result['data']['error'])) {
            $error = $result['data']['error'];
        } elseif (($result['status'] ?? 500) >= 500) {
            $error = 'Server backend sedang bermasalah (status ' . ($result['status'] ?? 'unknown') . '). Coba lagi nanti.';
        } elseif (($result['status'] ?? 500) === 401 || ($result['status'] ?? 500) === 403) {
            $error = 'Kredensial salah atau sesi ditolak.';
        } elseif (($result['status'] ?? 500) >= 400) {
            $error = 'Gagal login (status ' . ($result['status'] ?? 'unknown') . ').';
        } elseif (!empty($result['error'])) {
            $error = 'Tidak dapat menghubungi backend: ' . $result['error'];
        } else {
            $_SESSION['flask_cookie'] = $result['cookie'] ?? null;
            $_SESSION['username'] = $username;
            // Reset pilihan mata kuliah/assessment saat login baru
            unset($_SESSION['current_course'], $_SESSION['current_assessment'], $_SESSION['assessment_id']);
            // Langsung arahkan ke pemilihan mata kuliah
            header('Location: courses.php');
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
  <title>Login - Chat Assistant</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: 'Manrope', system-ui, -apple-system, sans-serif; }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900">
  <div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-10 border-b border-slate-200/70 bg-white/80 backdrop-blur">
      <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-slate-900 text-white grid place-items-center font-semibold">AI</div>
          <div>
            <div class="text-lg font-semibold">Chat Assistant</div>
            <div class="text-xs text-slate-500">Please sign in to access courses, chat, and dashboards.</div>
          </div>
        </div>
        <!-- <nav class="flex items-center gap-3 text-sm font-medium">
          <a class="text-slate-700 hover:text-slate-900" href="register.php">Register</a>
          <a class="text-slate-900 font-semibold" href="login.php">Login</a>
        </nav> -->
      </div>
    </header>

    <main class="flex-1 flex items-center">
      <div class="max-w-6xl mx-auto px-4 py-10 w-full">
        <section class="glass rounded-3xl border border-white/60 shadow-lg bg-white/80 p-6 sm:p-8">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
            <!-- Left: logo + copy -->
            <div class="flex flex-col justify-center">
              <div class="flex justify-center mb-4">
                <div class="inline-flex items-center justify-center text-white text-xs font-semibold uppercase shadow-sm">
                  <img src="logo.png" alt="S-SPARC AI logo" class="h-20 md:h-40 w-auto object-contain">
                </div>
              </div>
              <h2 class="text-2xl font-semibold text-slate-900 mb-3">Sustainable Semantic Personalized Academic Recommendation Chatbot</h2>
              <p class="text-sm text-slate-600 mb-6 max-w-md">
                Monitor your AI usage, track environmental impact, and earn gamified points while learning more sustainably.
              </p>
              <ul class="space-y-2 text-sm text-slate-700 list-disc list-inside">
                <li>Personalized chat assistant integrated with E-STRANGE.</li>
                <li>Weekly token quota and progress tracking.</li>
                <li>Insights on energy, carbon, and water footprint of your AI usage.</li>
              </ul>
            </div>

            <!-- Right: login form -->
            <div class="max-w-md w-full border-t border-slate-400 mt-8 pt-8 md:border-t-0 md:mt-0 md:pt-0 md:border-l md:border-slate-400 md:pl-10 md:ml-0">
              <h1 class="text-xl font-semibold text-slate-900 mb-2">Login to your account</h1>
              <p class="text-sm text-slate-600 mb-4">Use the same credentials as E-STRANGE platform account.</p>

              <?php if ($error): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
              <?php endif; ?>
              <?php if ($message): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($message) ?></div>
              <?php endif; ?>

              <form method="post" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1" for="username">Username</label>
                  <input class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:ring focus:ring-slate-200 outline-none" type="text" id="username" name="username" required>
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1" for="password">Password</label>
                  <input class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:ring focus:ring-slate-200 outline-none" type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring focus:ring-slate-200">Masuk</button>
              </form>

              <p class="mt-4 text-xs text-slate-500">Don't have an account yet? <a href="register.php" class="text-blue-900 font-medium hover:underline">Register now</a>.</p>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>
</body>
</html>
