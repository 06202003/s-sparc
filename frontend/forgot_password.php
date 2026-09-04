<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$backendBase = backend_base();
$httpClient = new Client([
    'base_uri' => $backendBase . '/',
    'timeout'  => 20,
]);

$error = null;
$success = null;
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$tokenParam = isset($_GET['token']) ? trim($_GET['token']) : '';
$prefillIdentifier = '';

if ($tokenParam !== '') {
    $step = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'request';

    if ($action === 'request') {
        $identifier = trim($_POST['identifier'] ?? '');
        $prefillIdentifier = $identifier;
        if ($identifier === '') {
            $error = 'Username atau email wajib diisi.';
        } else {
            try {
                $resp = $httpClient->post('forgot-password/request', [
                    'json' => ['identifier' => $identifier]
                ]);
                $data = json_decode((string)$resp->getBody(), true);
                if (!empty($data['reset_token'])) {
                    $tokenParam = $data['reset_token'];
                    $step = 2;
                    $success = 'Akun ditemukan (' . htmlspecialchars($data['username']) . ' - ' . htmlspecialchars($data['masked_email']) . '). Silakan masukkan kode token atau langsung tetapkan password baru.';
                } else {
                    $error = 'Gagal memproses permintaan reset.';
                }
            } catch (RequestException $e) {
                $resp = $e->getResponse();
                $body = $resp ? json_decode((string)$resp->getBody(), true) : null;
                $error = $body['detail'] ?? ($body['error'] ?? 'Akun dengan username atau email tersebut tidak ditemukan.');
            } catch (\Throwable $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'reset_token') {
        $token = trim($_POST['token'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($token === '' || $new_password === '') {
            $error = 'Token dan password baru wajib diisi.';
            $step = 2;
        } elseif (strlen($new_password) < 4) {
            $error = 'Password baru minimal 4 karakter.';
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = 'Konfirmasi password baru tidak cocok.';
            $step = 2;
        } else {
            try {
                $resp = $httpClient->post('forgot-password/reset', [
                    'json' => [
                        'token' => $token,
                        'new_password' => $new_password
                    ]
                ]);
                $data = json_decode((string)$resp->getBody(), true);
                $step = 3;
                $success = $data['message'] ?? 'Password berhasil diperbarui. Silakan login kembali dengan password baru.';
            } catch (RequestException $e) {
                $resp = $e->getResponse();
                $body = $resp ? json_decode((string)$resp->getBody(), true) : null;
                $error = $body['detail'] ?? ($body['error'] ?? 'Token tidak valid atau sudah kedaluwarsa.');
                $step = 2;
            } catch (\Throwable $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
                $step = 2;
            }
        }
    } elseif ($action === 'direct_reset') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($username === '' || $email === '' || $new_password === '') {
            $error = 'Semua kolom wajib diisi.';
        } elseif (strlen($new_password) < 4) {
            $error = 'Password baru minimal 4 karakter.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Konfirmasi password baru tidak cocok.';
        } else {
            try {
                $resp = $httpClient->post('forgot-password/direct-reset', [
                    'json' => [
                        'username' => $username,
                        'email' => $email,
                        'new_password' => $new_password
                    ]
                ]);
                $data = json_decode((string)$resp->getBody(), true);
                $step = 3;
                $success = $data['message'] ?? 'Password berhasil diperbarui. Silakan login kembali dengan password baru.';
            } catch (RequestException $e) {
                $resp = $e->getResponse();
                $body = $resp ? json_decode((string)$resp->getBody(), true) : null;
                $error = $body['detail'] ?? ($body['error'] ?? 'Kombinasi username dan email tidak cocok.');
            } catch (\Throwable $e) {
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lupa Password - S-SPARC Assistant</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: 'Manrope', system-ui, -apple-system, sans-serif; }
    .glass { backdrop-filter: blur(10px); background: rgba(255,255,255,0.85); }
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col justify-between">
  <!-- Header -->
  <header class="border-b border-slate-200/70 bg-white/80 backdrop-blur">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-[#00A0A5] text-white grid place-items-center font-semibold">AI</div>
        <div>
          <div class="text-lg font-semibold text-slate-900">S-SPARC Assistant</div>
          <div class="text-xs text-slate-500">Layanan Pemulihan &amp; Reset Kata Sandi</div>
        </div>
      </div>
      <nav class="flex items-center gap-3 text-sm font-medium">
        <a class="inline-flex h-10 items-center rounded-full border border-slate-200 px-4 text-slate-700 hover:border-slate-400 hover:text-slate-900" href="login.php">Kembali ke Login</a>
      </nav>
    </div>
  </header>

  <!-- Main Container -->
  <main class="flex-1 flex items-center py-10 px-4">
    <div class="max-w-xl mx-auto w-full">
      <div class="glass rounded-3xl border border-white/60 shadow-xl p-6 sm:p-8">
        
        <!-- Brand Header -->
        <div class="text-center mb-6">
          <div class="inline-flex items-center justify-center mb-3">
            <img src="logo.png" alt="S-SPARC AI Logo" class="h-16 w-auto object-contain">
          </div>
          <h1 class="text-2xl font-bold text-slate-900">Pemulihan Kata Sandi</h1>
          <p class="text-xs text-slate-500 mt-1">Atur ulang kata sandi akun S-SPARC Anda dengan cepat dan aman.</p>
        </div>

        <!-- Alert Error / Success -->
        <?php if ($error): ?>
          <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-3.5 text-xs text-red-800 flex items-start gap-2">
            <span class="font-bold shrink-0">[Gagal]</span>
            <div><?= htmlspecialchars($error) ?></div>
          </div>
        <?php endif; ?>

        <?php if ($success && $step !== 3): ?>
          <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs text-emerald-800 flex items-start gap-2">
            <span class="font-bold shrink-0">[Berhasil]</span>
            <div><?= htmlspecialchars($success) ?></div>
          </div>
        <?php endif; ?>

        <!-- STEP 3: Sukses Penuh -->
        <?php if ($step === 3): ?>
          <div class="text-center py-6 space-y-4">
            <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-800 grid place-items-center mx-auto text-xl font-bold border border-emerald-300">
              OK
            </div>
            <h2 class="text-lg font-bold text-slate-900">Kata Sandi Berhasil Diperbarui</h2>
            <p class="text-xs text-slate-600 max-w-md mx-auto leading-relaxed">
              Kata sandi baru Anda telah tersimpan dengan aman di sistem. Anda sekarang dapat masuk kembali ke portal S-SPARC menggunakan kredensial yang baru.
            </p>
            <div class="pt-2">
              <a href="login.php" class="inline-flex items-center justify-center rounded-xl bg-[#00A0A5] px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#008488] transition">
                Masuk Sekarang
              </a>
            </div>
          </div>

        <!-- STEP 2: Masukkan Password Baru dengan Token -->
        <?php elseif ($step === 2): ?>
          <form method="post" class="space-y-4">
            <input type="hidden" name="action" value="reset_token">
            
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
              <div class="font-semibold text-slate-800 mb-1">Langkah 2 dari 2: Buat Password Baru</div>
              <p>Masukkan token verifikasi dan tentukan kata sandi baru Anda (minimal 4 karakter).</p>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1" for="token">Token / Kode Verifikasi</label>
              <input class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-mono text-slate-800 focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="text" id="token" name="token" value="<?= htmlspecialchars($tokenParam) ?>" required placeholder="Masukkan token reset...">
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1" for="new_password">Kata Sandi Baru</label>
              <input class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="password" id="new_password" name="new_password" required placeholder="Minimal 4 karakter">
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1" for="confirm_password">Konfirmasi Kata Sandi Baru</label>
              <input class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="password" id="confirm_password" name="confirm_password" required placeholder="Ulangi kata sandi baru">
            </div>

            <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-[#00A0A5] px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#008488] transition">
              Simpan Kata Sandi Baru
            </button>

            <div class="text-center pt-2">
              <a href="forgot_password.php" class="text-xs text-slate-500 hover:text-slate-800 underline">Gunakan username/email lain</a>
            </div>
          </form>

        <!-- STEP 1: Masukkan Username / Email -->
        <?php else: ?>
          <!-- Tab Navigasi Metode Reset -->
          <div class="flex border-b border-slate-200 mb-5 text-xs font-semibold">
            <button type="button" id="tab-btn-token" class="flex-1 py-2.5 border-b-2 border-slate-900 text-slate-900 text-center transition" onclick="switchMethod('token')">
              Opsi 1: Verifikasi Akun
            </button>
            <button type="button" id="tab-btn-direct" class="flex-1 py-2.5 border-b-2 border-transparent text-slate-500 hover:text-slate-800 text-center transition" onclick="switchMethod('direct')">
              Opsi 2: Reset Langsung (Username + Email)
            </button>
          </div>

          <!-- Method 1: Token / OTP Request -->
          <div id="panel-token">
            <form method="post" class="space-y-4">
              <input type="hidden" name="action" value="request">
              
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1" for="identifier">Username atau Email Terdaftar</label>
                <input class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="text" id="identifier" name="identifier" value="<?= htmlspecialchars($prefillIdentifier) ?>" required placeholder="Contoh: 2172003 atau email@domain.com">
              </div>

              <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-[#00A0A5] px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#008488] transition">
                Cari Akun &amp; Buat Token Reset
              </button>
            </form>
          </div>

          <!-- Method 2: Direct Reset by Username + Email -->
          <div id="panel-direct" class="hidden">
            <form method="post" class="space-y-4">
              <input type="hidden" name="action" value="direct_reset">
              
              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1" for="dir_username">Username</label>
                <input class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="text" id="dir_username" name="username" required placeholder="Contoh: 2172003">
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1" for="dir_email">Email Terdaftar</label>
                <input class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="email" id="dir_email" name="email" required placeholder="Contoh: user@domain.com">
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1" for="dir_new_password">Kata Sandi Baru</label>
                <input class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="password" id="dir_new_password" name="new_password" required placeholder="Minimal 4 karakter">
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1" for="dir_confirm_password">Konfirmasi Kata Sandi Baru</label>
                <input class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs focus:border-[#00A0A5] focus:ring focus:ring-[#00A0A5]/20 outline-none" type="password" id="dir_confirm_password" name="confirm_password" required placeholder="Ulangi kata sandi baru">
              </div>

              <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-[#00A0A5] px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-[#008488] transition">
                Reset Kata Sandi
              </button>
            </form>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-200/80 text-center text-xs text-slate-500">
            Ingat kata sandi Anda? <a href="login.php" class="text-slate-900 font-semibold hover:underline">Masuk ke akun</a>.
          </div>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="text-center py-4 text-xs text-slate-400">
    S-SPARC - Sustainable Smart Personal Assistant for Responsible Consumption
  </footer>

  <script>
    function switchMethod(method) {
      const pToken = document.getElementById('panel-token');
      const pDirect = document.getElementById('panel-direct');
      const bToken = document.getElementById('tab-btn-token');
      const bDirect = document.getElementById('tab-btn-direct');

      if (method === 'direct') {
        pToken.classList.add('hidden');
        pDirect.classList.remove('hidden');
        bDirect.classList.add('border-slate-900', 'text-slate-900');
        bDirect.classList.remove('border-transparent', 'text-slate-500');
        bToken.classList.remove('border-slate-900', 'text-slate-900');
        bToken.classList.add('border-transparent', 'text-slate-500');
      } else {
        pDirect.classList.add('hidden');
        pToken.classList.remove('hidden');
        bToken.classList.add('border-slate-900', 'text-slate-900');
        bToken.classList.remove('border-transparent', 'text-slate-500');
        bDirect.classList.remove('border-slate-900', 'text-slate-900');
        bDirect.classList.add('border-transparent', 'text-slate-500');
      }
    }
  </script>
</body>
</html>
