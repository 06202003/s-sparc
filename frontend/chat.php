<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['chat_user_id'])) {
  $_SESSION['chat_user_id'] = bin2hex(random_bytes(6));
}
$loggedIn = !empty($_SESSION['flask_cookie']);
$username = $_SESSION['username'] ?? 'Guest';
$currentCourse = $_SESSION['current_course'] ?? null;
$currentAssessment = $_SESSION['current_assessment'] ?? null;
$assessmentId = $_SESSION['assessment_id'] ?? '';

// Wajib login dan memilih mata kuliah + assessment terlebih dahulu
if (!$loggedIn) {
  header('Location: login.php');
  exit;
}
if (!$assessmentId) {
  header('Location: courses.php');
  exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chatbot - BotMan + Flask</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root { color-scheme: light; }
    body { font-family: 'Manrope', system-ui, -apple-system, sans-serif; }
    .glass { backdrop-filter: blur(10px); background: rgba(255,255,255,0.7); }
    .typing-dot { width: 8px; height: 8px; border-radius: 999px; background: #475569; animation: blink 1.2s infinite; }
    .typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .typing-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes blink { 0%, 80%, 100% { opacity: 0.2; } 40% { opacity: 1; } }
    .code-block { position: relative; }
    .copy-btn { position: absolute; top: 8px; right: 8px; font-size: 12px; padding: 4px 8px; border-radius: 12px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; cursor: pointer; }
    .copy-btn:hover { background: #e2e8f0; }
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
        <div class="flex min-w-0 items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-[#00A0A5] text-white grid place-items-center font-semibold">AI</div>
          <div class="min-w-0">
            <div class="text-lg font-semibold">Chat Assistant</div>
            <div class="text-xs text-slate-500 truncate max-w-[36rem]">courses: <strong><?= htmlspecialchars($currentCourse ?? '-') ?></strong> &mdash; Assessment: <strong><?= htmlspecialchars($currentAssessment ?? '-') ?></strong></div>
            <div class="text-xs text-slate-600 font-medium" id="assessment-end-info"></div>
            <div class="text-xs font-semibold" id="assessment-end-countdown"></div>
          </div>
        </div>
        <nav class="flex shrink-0 items-center gap-2 text-sm font-medium">
          <button id="btn-api-key" onclick="openApiKeyModal()" type="button" class="inline-flex h-10 items-center gap-1.5 rounded-full border border-teal-300 bg-teal-50 px-4 text-teal-800 font-semibold hover:bg-teal-100 whitespace-nowrap shadow-2xs">
            <span>🔑</span>
            <span id="api-key-btn-text">API Key</span>
          </button>
          <a class="inline-flex h-10 items-center rounded-full px-3 text-slate-500 hover:bg-slate-100 hover:text-slate-700 whitespace-nowrap" href="dashboard.php">Dashboard</a>
          <a class="inline-flex h-10 items-center rounded-full px-3 text-slate-500 hover:bg-slate-100 hover:text-slate-700 whitespace-nowrap" href="courses.php">Change courses</a>
          <button id="view-prompt-tips" type="button" class="inline-flex h-10 items-center rounded-full border border-slate-200 px-4 text-slate-700 hover:border-slate-400 whitespace-nowrap">Lihat Tips Prompting</button>
          <button id="new-chat" type="button" class="inline-flex h-10 items-center rounded-full bg-[#00A0A5] text-white px-4 hover:bg-[#008488] whitespace-nowrap">New chat</button>
          <button id="clear-chat" type="button" class="inline-flex h-10 items-center rounded-full border border-slate-200 px-4 text-slate-700 hover:border-slate-400 whitespace-nowrap">Clear history</button>
          <?php if ($loggedIn): ?>
            <a href="logout.php" class="inline-flex h-10 items-center rounded-full bg-red-500 text-white px-4 hover:bg-red-600 shadow-sm whitespace-nowrap">Logout</a>
          <?php else: ?>
            <a href="login.php" class="inline-flex h-10 items-center rounded-full border border-slate-300 px-4 text-slate-700 hover:border-slate-500 hover:text-slate-900 whitespace-nowrap">Login</a>
          <?php endif; ?>
        </nav>
      </div>
    </header>

    <?php if (!$loggedIn): ?>
      <div class="max-w-6xl mx-auto w-full px-4 pt-4">
        <div class="rounded-lg border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 text-sm">
          You are not logged in. Log in on the login page so that the Flask session cookie is saved. If you get a job_id, check the status anytime by typing: <span class="font-mono">status &lt;job_id&gt;</span>.
        </div>
      </div>
    <?php endif; ?>

    <main class="flex-1">
      <div class="max-w-6xl mx-auto px-4 py-6 grid gap-4 lg:grid-cols-[1fr_320px] h-full">
        <section class="glass rounded-2xl border border-white/60 shadow-lg p-4 sm:p-6 flex flex-col min-h-[60vh] lg:max-h-[calc(100vh-120px)]">
          <div id="chat-window" class="flex-1 overflow-y-auto space-y-4 pr-1" aria-live="polite"></div>
          <div id="typing" class="hidden mt-2 flex items-center gap-2 text-sm text-slate-600">
            <span class="inline-flex items-center gap-1">
              <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
            </span>
            <span>Assistant is typing…</span>
          </div>
          <form id="chat-form" class="mt-4 flex items-stretch gap-3" onsubmit="sendMessage(event)">
            <div class="flex-1 flex flex-col gap-2">
              <div class="flex gap-2 items-center">
                <label for="language-select" class="text-xs text-slate-600">Language</label>
                <select id="language-select" class="min-w-[200px] shrink-0 text-sm rounded-md border border-slate-200 px-2 py-1">
                  <option value="">Auto-detect</option>
                  <option value="Python">Python</option>
                  <option value="JavaScript">JavaScript</option>
                  <option value="Java">Java</option>
                  <option value="C">C</option>
                  <option value="C++">C++</option>
                  <option value="Go">Go</option>
                  <option value="PHP">PHP</option>
                </select>
                <label for="response-mode" class="text-xs text-slate-600 ml-3">Mode</label>
                <select id="response-mode" class="min-w-[200px] shrink-0 text-sm rounded-md border border-slate-200 px-2 py-1">
                  <option value="code">Code (only)</option>
                  <option value="summary">Summary (short)</option>
                  <option value="summary_code_explanation">Summary + Code + Explanation</option>
                </select>
              </div>
              <div class="flex items-center gap-3">
                <label for="chat-input" class="sr-only">Write a message</label>
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm focus-within:border-[#00A0A5] flex-1 flex flex-col p-2">
                  <textarea id="chat-input" rows="3" class="w-full min-h-[4.5rem] resize-none overflow-y-auto bg-transparent px-2 py-1 outline-none text-sm" placeholder="Write your code question here (min. 10, max. 2000 chars)…" required></textarea>
                  <div class="flex flex-wrap justify-between items-center px-2 pt-1 border-t border-slate-100 gap-2">
                    <span id="char-counter" class="text-[11px] font-mono text-slate-400">0 / 2000 chars (min. 10)</span>
                    <div class="flex items-center gap-3">
                      <div id="query-quota-badge" onclick="showTermsModal()" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-teal-50/90 border border-teal-200/80 text-[11px] font-semibold text-teal-800 shadow-2xs cursor-pointer hover:bg-teal-100 transition" title="Klik untuk rincian kuota & syarat ketentuan">
                        <span class="text-teal-600">⚡</span>
                        <span>Sisa Query: <strong id="query-remaining-count" class="font-mono font-bold text-teal-900">1,500</strong> / <span id="query-limit-count" class="font-mono text-slate-500">1,500</span></span>
                      </div>
                      <span class="text-[10px] text-slate-400 hidden sm:inline">Shift+Enter for newline</span>
                    </div>
                  </div>
                </div>
                <button id="send-btn" type="submit" class="h-11 self-center flex items-center justify-center rounded-xl bg-[#00A0A5] text-white px-4 font-semibold hover:bg-[#008488] focus:ring focus:ring-[#00A0A5]/20 disabled:opacity-50">Send</button>
              </div>
            </div>
          </form>
          <div id="rate-limit-notice" class="hidden mt-2 p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-xs font-semibold text-amber-900 flex items-center gap-2 animate-pulse"></div>
          <div class="mt-3 flex flex-wrap gap-2 text-sm" id="suggestions">
            <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-700 hover:border-slate-400" data-suggest="Create a Python function named calculate_factorial that takes a single integer argument and returns its factorial. The function should include type annotations, a detailed docstring explaining the algorithm, and handle invalid input such as negative numbers or non-integer values by raising appropriate exceptions. Please also add inline comments explaining each logical step, and provide an example usage in the docstring. The function should be efficient and avoid recursion for very large numbers, using an iterative approach instead. Assume the input can be very large, so optimize for performance and memory usage. The code should be clear and easy to understand, following PEP8 style guidelines.">Factorial Python</button>
            <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-700 hover:border-slate-400" data-suggest="Write an SQL query that selects the top 10 most recently registered users from a users table, joining with a profiles table to retrieve each user's full name and email address. The query should filter out users who have not verified their email, sort the results by the created_at column in descending order, and include comments explaining each part of the query. Please ensure the query is well-formatted, readable, and uses table aliases for clarity.">SQL Query top 10 </button>
            <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-700 hover:border-slate-400" data-suggest="Create a Flask POST endpoint at /predict that accepts a JSON body with fields for age, gender, and symptoms (as a list of strings). Validate the input using Marshmallow or Pydantic, and return a JSON response with a prediction and a confidence score. If validation fails, return a detailed error message. Include type annotations, a docstring, and example request/response in the comments. The code should be modular, with input validation separated from the prediction logic.">Flask POST Endpoint</button>
            <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-700 hover:border-slate-400" data-suggest="Write a Python script that demonstrates how to read a CSV file containing user data, process the data to filter out users who have not verified their email, and then write the filtered data to a new CSV file. The script should use the csv module, include detailed comments explaining each step, handle possible exceptions such as file not found or invalid data, and print a summary of how many users were processed and how many were filtered.">CSV Processing Python</button>
          </div>
        </section>

        <aside class="hidden lg:block space-y-3">
          <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-semibold text-slate-800 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Template Prompt Cepat
              </span>
              <span class="text-[11px] font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Klik untuk Isi</span>
            </div>
            <p class="text-xs text-slate-500 mb-3">Pilih format prompt siap pakai untuk hasil AI yang lebih cepat, presisi, dan hemat token:</p>
            <div class="space-y-2 text-xs" id="quick-prompt-templates">
              <button type="button" class="w-full text-left p-2.5 rounded-xl border border-slate-100 bg-slate-50 hover:bg-indigo-50/70 hover:border-indigo-200 transition text-slate-700 flex flex-col gap-0.5" data-fill="Buatkan fungsi Python bernama [nama_fungsi] yang menerima input [parameter] dan menghasilkan output [hasil]. Tambahkan validasi error handling, type hints, dan contoh pengujian sederhana.">
                <span class="font-medium text-slate-800 flex items-center gap-1">Implementasi Fungsi / Algoritma</span>
                <span class="text-[11px] text-slate-500">Format standar pembuatan logika kode baru</span>
              </button>

              <button type="button" class="w-full text-left p-2.5 rounded-xl border border-slate-100 bg-slate-50 hover:bg-rose-50/70 hover:border-rose-200 transition text-slate-700 flex flex-col gap-0.5" data-fill="Saya mendapatkan error [pesan_error_lengkap] pada potongan kode berikut:&#10;&#10;[paste_kode_anda_di_sini]&#10;&#10;Tolong jelaskan akar penyebab masalahnya dan berikan kode perbaikannya.">
                <span class="font-medium text-slate-800 flex items-center gap-1">Debugging & Perbaikan Error</span>
                <span class="text-[11px] text-slate-500">Analisis akar masalah dan solusi perbaikan</span>
              </button>

              <button type="button" class="w-full text-left p-2.5 rounded-xl border border-slate-100 bg-slate-50 hover:bg-emerald-50/70 hover:border-emerald-200 transition text-slate-700 flex flex-col gap-0.5" data-fill="Tolong refactor dan optimasi kode berikut agar lebih efisien dalam penggunaan memori dan kecepatan eksekusi tanpa mengubah fungsionalitas aslinya:&#10;&#10;[paste_kode_di_sini]">
                <span class="font-medium text-slate-800 flex items-center gap-1">Refactor & Optimasi Kode</span>
                <span class="text-[11px] text-slate-500">Meningkatkan efisiensi & keterbacaan kode</span>
              </button>

              <button type="button" class="w-full text-left p-2.5 rounded-xl border border-slate-100 bg-slate-50 hover:bg-amber-50/70 hover:border-amber-200 transition text-slate-700 flex flex-col gap-0.5" data-fill="Buatkan unit test menggunakan pytest untuk menguji fungsi/kelas berikut, mencakup skenario normal (happy path), edge cases (nilai batas), dan penanganan input tidak valid (exception handling):&#10;&#10;[paste_kode_di_sini]">
                <span class="font-medium text-slate-800 flex items-center gap-1">Pembuatan Unit Test (Pytest)</span>
                <span class="text-[11px] text-slate-500">Uji otomatis kasus sukses dan kasus gagal</span>
              </button>
            </div>
            
            <div class="mt-3 pt-2.5 border-t border-slate-100 text-[11px] text-slate-500 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-slate-600 font-medium">Tips Hemat Token:</span>
                <span class="text-emerald-700 font-semibold bg-emerald-50 px-1.5 py-0.5 rounded">Mode: Code (only)</span>
              </div>
              <p class="leading-relaxed">Gunakan mode <strong>Code (only)</strong> untuk menghemat kuota token hingga 60% dan mempercepat respons AI.</p>
              <button type="button" onclick="showPromptingTipsModal()" class="w-full py-1.5 px-3 rounded-xl border border-indigo-200 bg-indigo-50/80 hover:bg-indigo-100/80 text-indigo-800 text-xs font-semibold flex items-center justify-center gap-1.5 transition shadow-sm">
                Buka Panduan Lengkap Prompting
              </button>
            </div>
          </div>
          
          <!-- Access & Policy Card -->
          <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
              <div class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>S-SPARC Access &amp; Policy</span>
              </div>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase">Free Access</span>
            </div>
            
            <div class="text-xs text-slate-700 flex flex-col gap-2 bg-slate-50 p-3 rounded-xl border border-slate-100">
              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Access Tier:</span>
                <span class="font-bold text-teal-700">Personal Gemini Key</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Sisa Kuota Hari Ini:</span>
                <span id="sidebar-query-remaining" class="font-bold text-teal-800 font-mono">1,500 req</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Rate Limit:</span>
                <span class="font-bold text-slate-900 font-mono">1 request / minute</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Prompt Limits:</span>
                <span class="font-bold text-slate-900 font-mono">10 &ndash; 2,000 chars</span>
              </div>
            </div>

            <div class="pt-1 space-y-2">
              <button type="button" onclick="openApiKeyModal()" class="w-full py-2 px-3 rounded-xl border border-teal-300 bg-white hover:bg-teal-50 text-teal-800 text-xs font-bold flex items-center justify-center gap-1.5 transition shadow-2xs">
                <span>🔑</span>
                <span>Kelola Google Gemini API Key</span>
              </button>
              <button type="button" onclick="showTermsModal()" class="w-full py-1.5 px-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 hover:text-slate-900 text-[11px] font-semibold flex items-center justify-center gap-1.5 transition">
                <span>📜</span>
                <span>Syarat &amp; Ketentuan API Pribadi</span>
              </button>
            </div>
          </div>
        </aside>
      </div>
    </main>
  </div>

  <script>
    const chatWindow = document.getElementById('chat-window');
    const chatInput = document.getElementById('chat-input');
    const typing = document.getElementById('typing');
    const languageSelect = document.getElementById('language-select');
    const responseModeSelect = document.getElementById('response-mode');
    const newChatBtn = document.getElementById('new-chat');
    const clearChatBtn = document.getElementById('clear-chat');
    const suggestions = document.getElementById('suggestions');
    const tokenTotalEl = document.getElementById('token-total');
    const tokenUsedEl = document.getElementById('token-used');
    // Remaining element removed
    const tokenPointsEl = document.getElementById('token-points');
    const viewPromptTipsBtn = document.getElementById('view-prompt-tips');
    let scrollBtn = null;
    const userId = '<?= htmlspecialchars($_SESSION['user_id'] ?? $_SESSION['chat_user_id']) ?>';
    const assessmentId = '<?= htmlspecialchars($assessmentId, ENT_QUOTES, 'UTF-8') ?>';
    const sendBtn = document.getElementById('send-btn');

    const STORAGE_KEY = 'chat_messages_v1_' + (assessmentId || 'default');
    const state = { messages: [] };
    const tokenState = { total: null, points: null };

    function loadMessages() {
      try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) return;
        const parsed = JSON.parse(saved);
        if (Array.isArray(parsed)) {
          state.messages = parsed.slice(-200); // keep last 200
          renderMessages();
        }
      } catch (e) {
        console.warn('Failed to load messages', e);
      }
    }

    function persistMessages() {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state.messages.slice(-200)));
      } catch (e) {
        console.warn('Failed to persist messages', e);
      }
    }

    function renderMessages() {
      chatWindow.innerHTML = '';
      state.messages.forEach(msg => {
        const row = document.createElement('div');
        row.className = msg.sender === 'user' ? 'flex justify-end items-start gap-2' : 'flex justify-start items-start gap-2';

        const avatar = document.createElement('div');
        avatar.className = 'h-9 w-9 rounded-full flex-shrink-0 grid place-items-center text-xs font-semibold shadow-sm ' + (msg.sender === 'user' ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 border border-slate-200');
        avatar.textContent = msg.sender === 'user' ? 'You' : 'AI';

        const bubble = document.createElement('div');
        bubble.className = msg.sender === 'user'
          ? 'max-w-3xl rounded-2xl bg-slate-900 text-white px-4 py-3 shadow'
          : 'max-w-3xl rounded-2xl bg-white text-slate-900 px-4 py-3 shadow border border-slate-100';

        if (msg.sender === 'bot' && msg.meta) {
          const meta = document.createElement('div');
          meta.className = 'text-xs text-slate-500 mb-1 flex items-center gap-2';
          meta.textContent = msg.meta;
          bubble.appendChild(meta);
        }

        let isCode = msg.sender === 'bot' && (
          msg.text.includes('\n') ||
          msg.text.includes(';') ||
          msg.text.includes('{') ||
          msg.text.includes('def ') ||
          msg.text.includes('class ') ||
          msg.text.includes('function ') ||
          msg.text.includes('import ') ||
          msg.text.includes('#include')
        );

        // Special case: guardrail text like "Here is the code result... Sorry, I can only help..."
        // should be shown as normal chat text, not as a code block.
        if (
          msg.sender === 'bot' &&
          msg.text.startsWith('Here is the code result:') &&
          msg.text.includes('Sorry, I can only help with programming/code questions.')
        ) {
          isCode = false;
        }

        if (isCode) {
          // If message contains fenced code blocks, split into text/code/text
          if (msg.text.includes('```')) {
            // Regex to capture parts: text before, each fenced block, and after
            const parts = [];
            const fenceRe = /```([a-zA-Z0-9+\-]*)\n([\s\S]*?)\n```/g;
            let lastIndex = 0;
            let m;
            while ((m = fenceRe.exec(msg.text)) !== null) {
              const start = m.index;
              const lang = m[1] || '';
              const codeContent = m[2] || '';
              if (start > lastIndex) {
                parts.push({ type: 'text', content: msg.text.slice(lastIndex, start) });
              }
              parts.push({ type: 'code', content: codeContent, lang });
              lastIndex = fenceRe.lastIndex;
            }
            if (lastIndex < msg.text.length) {
              parts.push({ type: 'text', content: msg.text.slice(lastIndex) });
            }
            parts.forEach(p => {
              if (p.type === 'text') {
                const textNode = document.createElement('div');
                textNode.className = 'mb-2';
                textNode.textContent = p.content.trim();
                bubble.appendChild(textNode);
              } else if (p.type === 'code') {
                const wrapper = document.createElement('div');
                wrapper.className = 'code-block rounded-xl border border-slate-200 bg-slate-50 text-slate-900 relative overflow-x-auto my-2';
                const code = document.createElement('pre');
                code.className = 'text-sm leading-relaxed p-3 whitespace-pre-wrap break-words';
                code.textContent = p.content.trim();
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'copy-btn';
                btn.textContent = 'Copy';
                btn.dataset.copy = p.content.trim();
                wrapper.appendChild(btn);
                wrapper.appendChild(code);
                bubble.appendChild(wrapper);
              }
            });
          } else {
            const wrapper = document.createElement('div');
            wrapper.className = 'code-block rounded-xl border border-slate-200 bg-slate-50 text-slate-900 relative overflow-x-auto';
            const code = document.createElement('pre');
            code.className = 'text-sm leading-relaxed p-3 whitespace-pre-wrap break-words';
            code.textContent = msg.text;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'copy-btn';
            btn.textContent = 'Copy';
            btn.dataset.copy = msg.text;
            wrapper.appendChild(btn);
            wrapper.appendChild(code);
            bubble.appendChild(wrapper);
          }
        } else {
          const text = document.createElement('div');
          text.textContent = msg.text;
          bubble.appendChild(text);
        }

        if (msg.sender === 'bot' && msg.source === 'db' && msg.originalPrompt) {
          const footer = document.createElement('div');
          footer.className = 'mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500';
          const info = document.createElement('span');
          info.textContent = "The code above is taken from a database (free). If it's not suitable, you can request a new version from ChatGPT.";
          const gptBtn = document.createElement('button');
          gptBtn.type = 'button';
          gptBtn.className = 'gpt-generate inline-flex items-center gap-1 rounded-full bg-[#00A0A5] text-white px-3 py-1 text-xs hover:bg-[#008488]';
          gptBtn.dataset.prompt = msg.originalPrompt;
          gptBtn.textContent = 'Generate with ChatGPT';
          footer.appendChild(info);
          footer.appendChild(gptBtn);
          bubble.appendChild(footer);
        }

        if (msg.sender === 'user') {
          row.appendChild(bubble);
          row.appendChild(avatar);
        } else {
          row.appendChild(avatar);
          row.appendChild(bubble);
        }
        chatWindow.appendChild(row);
      });
      chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    function addMessage(sender, text, meta = '', extra = {}) {
      state.messages.push({ sender, text, meta, ...extra });
      renderMessages();
      persistMessages();
    }

    function showTyping(show) {
      typing.classList.toggle('hidden', !show);
    }

    async function refreshGamification() {
      try {
        const url = assessmentId ? `gamification.php?assessment_id=${encodeURIComponent(assessmentId)}` : 'gamification.php';
        const res = await fetch(url, { method: 'GET' });
        if (!res.ok) return;
        const data = await res.json();
        const g = data.gamification;
        if (!g) return;

        const thresholdEl = document.getElementById('token-threshold');
        if (thresholdEl) thresholdEl.textContent = Number(g.token_threshold || 0).toLocaleString();
        if (tokenTotalEl) tokenTotalEl.textContent = Number(g.token_threshold || 0).toLocaleString();
        if (tokenUsedEl) tokenUsedEl.textContent = Number(g.gpt_tokens_used || 0).toLocaleString();
        if (tokenPointsEl) tokenPointsEl.textContent = Number(g.current_points || 0).toLocaleString();

        const countdownEl = document.getElementById('assessment-end-countdown');
        const infoEl = document.getElementById('assessment-end-info');
        if (g.assessment_end_date) {
          const endDate = new Date(g.assessment_end_date);
          function updateCountdown() {
            const now = new Date();
            const diff = endDate.getTime() - now.getTime();
            if (diff > 0) {
              const hours = Math.floor(diff / 1000 / 60 / 60);
              const minutes = Math.floor((diff / 1000 / 60) % 60);
              const seconds = Math.floor((diff / 1000) % 60);
              if (countdownEl) countdownEl.textContent = `Assessment ends in ${hours}h ${minutes}m ${seconds}s`;
              if (infoEl) infoEl.textContent = `Deadline: ${endDate.toLocaleString()}`;
            } else {
              if (countdownEl) countdownEl.textContent = 'Assessment expired';
              if (infoEl) infoEl.textContent = `Expired: ${endDate.toLocaleString()}`;
            }
          }
          updateCountdown();
          if (window._assessmentCountdownTimer) clearInterval(window._assessmentCountdownTimer);
          window._assessmentCountdownTimer = setInterval(updateCountdown, 1000);
        } else if (countdownEl) {
          countdownEl.textContent = '';
          if (infoEl) infoEl.textContent = '';
        }
      } catch (e) {
        console.warn('Failed to refresh gamification info', e);
      }
    }

    async function sendMessage(e) {
      e.preventDefault();
      const text = chatInput.value.trim();
      if (!text) return;
      
      if (text.length < 10) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'warning',
            title: 'Prompt Terlalu Pendek',
            text: 'Harap masukkan pertanyaan pemrograman minimal 10 karakter.',
            confirmButtonColor: '#00A0A5'
          });
        } else {
          alert('Prompt terlalu pendek. Minimal 10 karakter.');
        }
        return;
      }
      if (text.length > 2000) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'warning',
            title: 'Prompt Terlalu Panjang',
            text: `Panjang prompt saat ini ${text.length} karakter. Maksimal yang diizinkan adalah 2000 karakter.`,
            confirmButtonColor: '#00A0A5'
          });
        } else {
          alert('Prompt terlalu panjang. Maksimal 2000 karakter.');
        }
        return;
      }

      chatInput.value = '';
      autoResizeTextarea();
      // Jalankan pengiriman di background
      sendMessageCore(text, text);
    }

    async function sendMessageCore(messageText, displayText) {
      if (!messageText) return;
      addMessage('user', displayText);

      const params = new URLSearchParams();
      params.append('driver', 'web');
      // Always send userId and assessmentId explicitly
      if (userId) params.append('user_id', userId);
      if (assessmentId) params.append('assessment_id', assessmentId);
      params.append('userId', userId); // legacy/fallback
      params.append('message', messageText);
      // send optional hints to backend
      if (languageSelect && languageSelect.value) params.append('language', languageSelect.value);
      if (responseModeSelect && responseModeSelect.value) params.append('response_mode', responseModeSelect.value);

      showTyping(true);
      try {
        const res = await fetch('botman.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: params.toString(),
        });
        const raw = await res.text();
        let data = null;
        try {
          data = raw ? JSON.parse(raw) : null;
        } catch (parseErr) {
          // Jika backend mengirim HTML/PHP error, tampilkan cuplikan agar lebih jelas
          const snippet = raw && raw.length > 300 ? raw.slice(0, 300) + '…' : raw;
          addMessage('bot', 'Failed to process server response. Response snippet: ' + (snippet || '[empty]'));
          return;
        }
        // Detect backend rate-limit messages (from BotMan) and temporarily disable send button
        try {
          if (data && Array.isArray(data.messages)) {
            const rl = data.messages.some(m => (m.text || '').toLowerCase().includes('rate limit'));
            const isRetrieval = data.messages.some(m => {
              const t = (m.text || '').toLowerCase();
              return t.startsWith('answers are retrieved from the database') || t.startsWith('found similar code in the database') || t.startsWith('found similar code in the database') || t.startsWith('answers taken from database') || t.includes('similarity');
            });
            // Only start rate-limit countdown when the response is NOT a DB retrieval/suggestion
            if (rl && !isRetrieval && sendBtn) {
              startRateLimitCountdown(61);
            }
          }
        } catch (e) { /* ignore */ }

        if (data && Array.isArray(data.messages) && data.messages.length > 0) {
          const msgs = data.messages;
          // Khusus pola antrian GPT: BotMan mengirim dua pesan sekaligus:
          // 1) "Permintaan Anda sedang diproses (antrian)... job_id: ..."
          // 2) "Berikut hasil kodenya:\n<code>"
          // Untuk UX yang lebih rapi, kita gabungkan menjadi satu bubble saja
          if (
            msgs.length >= 2 &&
            typeof msgs[0].text === 'string' &&
            typeof msgs[1].text === 'string' &&
            // Pesan pertama adalah notifikasi antrian dari BotMan (tidak perlu ditampilkan ke user)
            msgs[0].text.startsWith('Your request is being processed (queued).') &&
            msgs[1].text.startsWith('Here is the code result:')
          ) {
            const queueText = msgs[0].text;
            const finalText = msgs[1].text;
            let jobId = '';
            const match = queueText.match(/job_id:\s*([a-f0-9\-]+)/i);
            if (match) jobId = match[1];

            // Take only the code part from the second message (after the first line)
            const lines = finalText.split('\n');
            const codeOnly = lines.slice(1).join('\n') || finalText;
            
            // Detect source: check if message starts with "Answers are retrieved from the database"
            const isRetrieval = finalText.includes('Answers are retrieved from the database');
            let meta = '';
            if (isRetrieval) {
              // Extract similarity info if present
              const simMatch = finalText.match(/Similarity\s+([0-9.]+)%/);
              meta = simMatch
                ? `Retrieved from database (FREE). Similarity ${simMatch[1]}%`
                : 'Retrieved from database (FREE)';
            } else {
              meta = jobId
                ? `Result from ChatGPT (queued earlier, job_id: ${jobId}).`
                : 'Result from ChatGPT (queued earlier).';
            }

            // Hanya tampilkan hasil akhirnya, bukan pesan antrian mentah
            addMessage('bot', codeOnly, meta);
          } else {
            // Render response messages cleanly
            msgs.forEach(msg => {
              const body = msg.text || '[empty message]';
              if (body.startsWith('Your request is being processed (queued)')) {
                return; // Suppress queue notification banner
              }
              // Answers from the database (retrieval/suggestion): baris pertama berisi keterangan sumber
              const isDbRetrieval =
                body.startsWith('Answers taken from database') ||
                body.startsWith('Answers are retrieved from the database') ||
                body.startsWith('Found similar code in database') ||
                body.startsWith('Found similar code in the database');

              if (isDbRetrieval) {
                const lines = body.split('\n');
                const metaLine = lines[0];
                const codeOnly = lines.slice(1).join('\n');
                addMessage('bot', codeOnly || metaLine, metaLine, {
                  source: 'db',
                  originalPrompt: displayText,
                });
              } else {
                const meta = body.startsWith('Result') ? 'Retrieval / similarity info' : '';
                addMessage('bot', body, meta);
              }
            });
          }
        } else {
          addMessage('bot', 'Unknown response from BotMan.');
        }
      } catch (err) {
        addMessage('bot', 'Failed to send message: ' + err);
      } finally {
        showTyping(false);
        // Update token card after each response
        refreshGamification();
      }
    }

    function clearChat() {
      state.messages = [];
      persistMessages();
      renderMessages();
      chatInput.focus();
    }

    function newChat() {
      clearChat();
      addMessage('bot', 'New chat started. Ask me anything about coding.');
    }

    const MAX_TEXTAREA_HEIGHT = 128; // 8rem assuming 16px base font size

    function autoResizeTextarea() {
      if (!chatInput) return;
      chatInput.style.height = 'auto';
      const newHeight = Math.min(chatInput.scrollHeight, MAX_TEXTAREA_HEIGHT);
      chatInput.style.height = newHeight + 'px';
    }

    chatInput.addEventListener('input', autoResizeTextarea);

    // Real-Time Prompt Length & Character Validation
    function validateInputState(){
      if(!sendBtn || !chatInput) return;
      const v = chatInput.value || '';
      const len = v.trim().length;
      const charCounter = document.getElementById('char-counter');
      if (charCounter) {
        charCounter.textContent = `${len} / 2000 chars (min. 10)`;
        if (len < 10 && len > 0) {
          charCounter.className = "text-[11px] font-mono text-amber-600 font-semibold";
        } else if (len > 2000) {
          charCounter.className = "text-[11px] font-mono text-rose-600 font-bold";
        } else {
          charCounter.className = "text-[11px] font-mono text-slate-400";
        }
      }

      let ok = (len >= 10 && len <= 2000);
      if (!_rateLimitTimer) {
        sendBtn.disabled = !ok;
        sendBtn.classList.toggle('opacity-50', !ok);
      }
    }
    chatInput.addEventListener('input', validateInputState);
    chatInput.addEventListener('keyup', validateInputState);
    // initial validation
    validateInputState();

    // Terms and Conditions Modal Dialog
    function showTermsModal() {
      Swal.fire({
        title: '📜 Syarat & Ketentuan Penggunaan API Key Pribadi',
        html: `
          <div class="text-left text-xs leading-relaxed space-y-3.5 text-slate-700 max-h-[60vh] overflow-y-auto pr-1">
            <div class="p-3 bg-teal-50/90 border border-teal-200 rounded-xl text-[11px] text-teal-900 font-medium">
              S-SPARC AI mengadopsi model <em>Bring Your Own Key (BYOK)</em> Google Gemini Flash untuk menjamin kebebasan eksplorasi coding mahasiswa secara mandiri tanpa pemotongan poin gamifikasi.
            </div>

            <div class="space-y-3">
              <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">1.</span> Kerahasiaan & Keamanan Data (Data Privacy)
                </div>
                <p class="text-[11px] text-slate-600">
                  Google Gemini API Key Anda disimpan secara terenkripsi dan terisolasi di database. Kunci ini semata-mata digunakan untuk memproses permintaan inferensi asisten coding pada akun Anda dan tidak pernah dibagikan kepada pihak ketiga manapun.
                </p>
              </div>

              <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">2.</span> Tanggung Jawab Kepemilikan Kunci
                </div>
                <p class="text-[11px] text-slate-600">
                  Pengguna bertanggung jawab penuh atas API key pribadi yang didaftarkan melalui Google AI Studio. Dilarang memasukkan kunci milik orang lain, menyalahgunakan kuota untuk aktivitas non-akademik, atau mengeksekusi prompt yang melanggar hukum/kebijakan Google Cloud.
                </p>
              </div>

              <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">3.</span> Alokasi Kuota & Batasan Bebas Biaya (Free Tier Limits)
                </div>
                <p class="text-[11px] text-slate-600">
                  Paket gratis Google Gemini 2.0/2.5 Flash Free Tier mengalokasikan hingga <strong>1.500 requests per hari (RPD)</strong> dan <strong>15 requests per menit (RPM)</strong>. S-SPARC memberlakukan aturan jeda rate limit 1 menit (60 detik) per prompt untuk menjaga stabilitas akun dan melatih kebiasaan berpikir komputasional mandiri.
                </p>
              </div>

              <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">4.</span> Jaminan Multi-Tier Failover
                </div>
                <p class="text-[11px] text-slate-600">
                  Jika kuota API key pribadi Anda mengalami limit atau gangguan koneksi cloud, sistem S-SPARC secara transparan mengalihkan eksekusi ke <em>System Pool Key</em> cadangan atau <em>Local LLM Ollama</em> agar proses belajar Anda tidak terputus.
                </p>
              </div>

              <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">5.</span> Integritas Akademik & Etika Penggunaan AI
                </div>
                <p class="text-[11px] text-slate-600">
                  Asisten AI ini difungsikan sebagai tutor interaktif (membantu diagnosis pesan error, memahami alur algoritma, dan mengoptimalkan efisiensi kode). Mahasiswa tetap wajib memahami dan mampu mempertanggungjawabkan setiap baris kode solusi yang diserahkan dalam tugas E-STRANGE.
                </p>
              </div>
            </div>
          </div>
        `,
        confirmButtonText: 'Saya Mengerti & Setuju',
        confirmButtonColor: '#00A0A5',
        width: '580px'
      });
    }

    // Fetch and Update Real-time Query Quota
    async function fetchQueryQuota() {
      try {
        const res = await fetch(`http://127.0.0.1:5000/api/user/query-quota`, {
          headers: { 'X-User-ID': userId }
        });
        if (res.ok) {
          const quota = await res.json();
          updateQueryQuotaUI(quota);
        }
      } catch (e) {
        console.debug('Failed to fetch query quota:', e);
      }
    }

    function updateQueryQuotaUI(quota) {
      if (!quota) return;
      const remainingEl = document.getElementById('query-remaining-count');
      const limitEl = document.getElementById('query-limit-count');
      const sidebarRemainingEl = document.getElementById('sidebar-query-remaining');
      const badgeEl = document.getElementById('query-quota-badge');

      const remaining = Number(quota.daily_remaining !== undefined ? quota.daily_remaining : 1500);
      const limit = Number(quota.daily_limit || 1500);

      if (remainingEl) remainingEl.textContent = remaining.toLocaleString();
      if (limitEl) limitEl.textContent = limit.toLocaleString();
      if (sidebarRemainingEl) sidebarRemainingEl.textContent = `${remaining.toLocaleString()} req`;

      if (badgeEl) {
        if (!quota.has_key) {
          badgeEl.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-rose-50 border border-rose-200 text-[11px] font-semibold text-rose-800 shadow-2xs cursor-pointer hover:bg-rose-100 transition";
          badgeEl.innerHTML = `<span class="text-rose-600">⚠️</span><span>Set Gemini API Key</span>`;
          badgeEl.onclick = () => openApiKeyModal(true);
        } else if (remaining < 50) {
          badgeEl.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-rose-50 border border-rose-200 text-[11px] font-semibold text-rose-800 shadow-2xs cursor-pointer hover:bg-rose-100 transition";
          badgeEl.onclick = showTermsModal;
        } else if (remaining < 300) {
          badgeEl.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-amber-50 border border-amber-200 text-[11px] font-semibold text-amber-800 shadow-2xs cursor-pointer hover:bg-amber-100 transition";
          badgeEl.onclick = showTermsModal;
        } else {
          badgeEl.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-teal-50/90 border border-teal-200/80 text-[11px] font-semibold text-teal-800 shadow-2xs cursor-pointer hover:bg-teal-100 transition";
          badgeEl.onclick = showTermsModal;
        }
      }
    }

    // API Key Management Modal (SweetAlert2)
    async function openApiKeyModal(isFirstTime = false) {
      let currentMasked = '';
      try {
        const res = await fetch(`http://127.0.0.1:5000/api/user/api-key`, {
          headers: { 'X-User-ID': userId }
        });
        if (res.ok) {
          const info = await res.json();
          if (info.has_key && info.masked_key) {
            currentMasked = info.masked_key;
          }
        }
      } catch (e) {
        console.debug('Failed to fetch API key info:', e);
      }

      const titleText = isFirstTime ? 'Masukkan Google Gemini API Key Anda' : 'Kelola Google Gemini API Key';
      const introText = isFirstTime 
        ? 'Untuk menggunakan asisten coding <strong>S-SPARC AI</strong>, Anda wajib memasukkan Google Gemini API Key pribadi Anda. Kunci ini tersimpan aman dan digunakan untuk setiap pertanyaan pemrograman Anda.'
        : 'Google Gemini API Key pribadi Anda saat ini: <strong class="font-mono text-teal-700">' + (currentMasked || 'Belum diatur') + '</strong>.';

      const { value: formValues } = await Swal.fire({
        title: titleText,
        html: `
          <div class="text-left text-xs text-slate-600 space-y-3">
            <p>${introText}</p>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
              <label class="block font-bold text-slate-800 mb-1">Google Gemini API Key:</label>
              <input id="swal-api-key-input" type="password" placeholder="AIzaSy..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#00A0A5] bg-white text-slate-900" autocomplete="off">
              <div class="flex items-center justify-between mt-1.5 text-[11px] text-slate-500">
                <span>Panjang minimal 10 karakter</span>
                <button type="button" onclick="const inp = document.getElementById('swal-api-key-input'); inp.type = (inp.type === 'password' ? 'text' : 'password');" class="text-teal-600 hover:underline font-semibold">Tampilkan / Sembunyikan</button>
              </div>
            </div>
            
            <div class="p-2.5 bg-slate-100/90 rounded-xl border border-slate-200 text-left">
              <label class="flex items-start gap-2 cursor-pointer text-[11px] text-slate-700 select-none">
                <input type="checkbox" id="swal-terms-checkbox" checked class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                <span>Saya menyetujui <a href="javascript:void(0)" onclick="showTermsModal()" class="font-bold text-teal-700 underline hover:text-teal-900">Syarat & Ketentuan Penggunaan API Key Pribadi</a> di S-SPARC / E-STRANGE.</span>
              </label>
            </div>

            <div class="p-2.5 bg-teal-50 border border-teal-200 rounded-xl text-[11px] text-teal-900 flex items-center gap-2">
              <svg class="w-4 h-4 text-teal-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>Belum punya key? Dapatkan gratis di <a href="https://aistudio.google.com/app/apikey" target="_blank" class="font-bold underline text-teal-800">Google AI Studio</a>.</span>
            </div>
          </div>
        `,
        focusConfirm: false,
        showCancelButton: !isFirstTime,
        confirmButtonText: 'Simpan API Key',
        confirmButtonColor: '#00A0A5',
        cancelButtonText: 'Batal',
        preConfirm: () => {
          const keyVal = document.getElementById('swal-api-key-input')?.value.trim();
          const termsChecked = document.getElementById('swal-terms-checkbox')?.checked;
          if (!keyVal || keyVal.length < 10) {
            Swal.showValidationMessage('Silakan masukkan API key yang valid (minimal 10 karakter)');
            return false;
          }
          if (!termsChecked) {
            Swal.showValidationMessage('Anda wajib menyetujui Syarat & Ketentuan Penggunaan API Key Pribadi.');
            return false;
          }
          return { apiKey: keyVal, termsAccepted: termsChecked };
        }
      });

      if (formValues && formValues.apiKey) {
        try {
          Swal.fire({
            title: 'Menyimpan API Key...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
          });

          const postRes = await fetch(`http://127.0.0.1:5000/api/user/api-key`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-User-ID': userId
            },
            body: JSON.stringify({ 
              api_key: formValues.apiKey, 
              provider: 'gemini',
              terms_accepted: formValues.termsAccepted
            })
          });

          if (!postRes.ok) {
            const errData = await postRes.json().catch(() => ({}));
            throw new Error(errData.detail || 'Gagal menyimpan API key.');
          }

          const saveRes = await postRes.json();
          const apiKeyBtnText = document.getElementById('api-key-btn-text');
          if (apiKeyBtnText) apiKeyBtnText.textContent = 'API Key (Aktif)';

          // Refresh query quota UI
          fetchQueryQuota();

          Swal.fire({
            icon: 'success',
            title: 'API Key Berhasil Disimpan!',
            text: `Kunci aktif: ${saveRes.masked_key || 'Tersimpan'}. Anda sekarang siap menggunakan S-SPARC AI.`,
            confirmButtonColor: '#00A0A5'
          });
        } catch (saveErr) {
          Swal.fire({
            icon: 'error',
            title: 'Gagal Menyimpan API Key',
            text: saveErr.message,
            confirmButtonColor: '#0f172a'
          });
        }
      }
    }

    async function checkUserApiKey() {
      try {
        const res = await fetch(`http://127.0.0.1:5000/api/user/api-key`, {
          headers: { 'X-User-ID': userId }
        });
        if (res.ok) {
          const data = await res.json();
          const apiKeyBtnText = document.getElementById('api-key-btn-text');
          if (data.has_key) {
            if (apiKeyBtnText) apiKeyBtnText.textContent = 'API Key (Aktif)';
          } else {
            if (apiKeyBtnText) apiKeyBtnText.textContent = 'Set API Key';
            setTimeout(() => { openApiKeyModal(true); }, 500);
          }
        }
      } catch (e) {
        console.debug('Error checking user API key status:', e);
      }
    }

    // Rate-limit countdown UI
    let _rateLimitTimer = null;
    const rateLimitEl = document.getElementById('rate-limit-notice');
    function startRateLimitCountdown(seconds){
      if (!sendBtn) return;
      if (_rateLimitTimer) {
        clearInterval(_rateLimitTimer);
        _rateLimitTimer = null;
      }
      let remaining = Math.max(1, Math.floor(Number(seconds) || 60));
      sendBtn.disabled = true;
      sendBtn.classList.add('opacity-50');
      if (rateLimitEl) {
        rateLimitEl.classList.remove('hidden');
        rateLimitEl.textContent = `⏱ Cooldown aktif. Mohon tunggu ${remaining} detik sebelum mengirim prompt berikutnya.`;
      }
      _rateLimitTimer = setInterval(() => {
        remaining -= 1;
        if (rateLimitEl) rateLimitEl.textContent = `⏱ Cooldown aktif. Mohon tunggu ${remaining} detik sebelum mengirim prompt berikutnya.`;
        if (remaining <= 0) {
          clearInterval(_rateLimitTimer);
          _rateLimitTimer = null;
          if (rateLimitEl) {
            rateLimitEl.classList.add('hidden');
            rateLimitEl.textContent = '';
          }
          validateInputState();
        }
      }, 1000);
    }

    chatInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(e);
      }
    });

    newChatBtn.addEventListener('click', newChat);
    clearChatBtn.addEventListener('click', clearChat);

    // Delegate copy buttons
    chatWindow.addEventListener('click', (e) => {
      const copyBtn = e.target.closest('.copy-btn');
      if (copyBtn) {
        const text = copyBtn.dataset.copy || '';
        navigator.clipboard.writeText(text).then(() => {
          copyBtn.textContent = 'Copied';
          setTimeout(() => { copyBtn.textContent = 'Copy'; }, 1500);
        });
        return;
      }

      const gptBtn = e.target.closest('.gpt-generate');
      if (gptBtn) {
        const original = gptBtn.dataset.prompt || '';
        if (original) {
          // Send with __force_gpt__ prefix to trigger /enqueue-gpt endpoint (no rate limit)
          const FORCE_PREFIX = '__force_gpt__ ';
          sendMessageCore(FORCE_PREFIX + original, original + ' (generate with ChatGPT)');
        }
      }
    });

    // Suggestions chip click
    suggestions.addEventListener('click', (e) => {
      const target = e.target.closest('button[data-suggest]');
      if (!target) return;
      chatInput.value = target.dataset.suggest;
      autoResizeTextarea();
      validateInputState();
      chatInput.focus();
    });

    // Quick prompt templates click handler
    const promptTemplatesContainer = document.getElementById('quick-prompt-templates');
    if (promptTemplatesContainer) {
      promptTemplatesContainer.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-fill]');
        if (!btn) return;
        chatInput.value = btn.dataset.fill;
        autoResizeTextarea();
        validateInputState();
        chatInput.focus();
      });
    }

    // Scroll-to-bottom helper
    function ensureScrollBtn() {
      if (scrollBtn) return scrollBtn;
      scrollBtn = document.createElement('button');
      scrollBtn.type = 'button';
      scrollBtn.textContent = '↓ Ke bawah';
      scrollBtn.className = 'hidden fixed right-6 bottom-6 rounded-full bg-[#00A0A5] text-white px-4 py-2 shadow-lg hover:bg-[#008488]';
      scrollBtn.addEventListener('click', () => {
        chatWindow.scrollTop = chatWindow.scrollHeight;
        scrollBtn.classList.add('hidden');
      });
      document.body.appendChild(scrollBtn);
      return scrollBtn;
    }

    chatWindow.addEventListener('scroll', () => {
      const btn = ensureScrollBtn();
      const nearBottom = chatWindow.scrollHeight - chatWindow.scrollTop - chatWindow.clientHeight < 120;
      if (nearBottom) {
        btn.classList.add('hidden');
      } else {
        btn.classList.remove('hidden');
      }
    });

    // Hydrate from localStorage on load and seed welcome message if empty
    loadMessages();

    function showPromptingTipsModal() {
      if (typeof Swal === 'undefined') return;

      const templates = [
        {
          id: 'tpl-algo',
          tag: 'Algoritma & Logika',
          color: 'indigo',
          title: 'Implementasi Algoritma & Struktur Data',
          desc: 'Gunakan template ini untuk membuat fungsi logika, pemrosesan data, atau algoritma komputasi baru.',
          template: `Buatkan fungsi Python bernama [nama_fungsi] yang menerima parameter [tipe_parameter: nama_param] dan mengembalikan [tipe_output: deskripsi_hasil].\n\nKetentuan Tambahan:\n1. Tambahkan validasi error handling untuk input negatif atau tipe data tidak valid.\n2. Sertakan type annotations dan docstring penjelasan algoritma.\n3. Optimalkan efisiensi waktu eksekusi dan penggunaan memori.\n4. Berikan contoh pengujian pemanggilan fungsi.`
        },
        {
          id: 'tpl-debug',
          tag: 'Debugging & Error',
          color: 'rose',
          title: 'Analisis Akar Masalah & Solusi Error',
          desc: 'Gunakan template ini saat kode Anda menghasilkan traceback atau pesan error.',
          template: `Saya mendapatkan error berikut saat menjalankan program:\n\n[PASTE_PESAN_ERROR_LENGKAP_DI_SINI]\n\nBerikut potongan kode terkait yang saya gunakan:\n\`\`\`python\n[PASTE_KODE_ANDA_DI_SINI]\n\`\`\`\n\nMohon bantuannya untuk:\n1. Jelaskan secara ringkas akar penyebab masalah (root cause).\n2. Berikan kode perbaikan yang sudah diperbaiki.\n3. Jelaskan langkah pencegahan agar error serupa tidak terulang.`
        },
        {
          id: 'tpl-refactor',
          tag: 'Refactor & Clean Code',
          color: 'emerald',
          title: 'Optimasi Memori, Kecepatan & Keterbacaan',
          desc: 'Gunakan template ini untuk merapikan kode yang sudah jalan agar lebih modular dan efisien.',
          template: `Tolong refactor dan optimasi kode berikut agar lebih modular, bersih (clean code PEP8), dan efisien dalam konsumsi memori tanpa mengubah fungsionalitas aslinya:\n\n\`\`\`python\n[PASTE_KODE_YANG_INGIN_DIREFACTOR]\n\`\`\`\n\nMohon sertakan ringkasan poin-poin penting perbaikan yang dilakukan.`
        },
        {
          id: 'tpl-pytest',
          tag: 'Unit Testing (Pytest)',
          color: 'amber',
          title: 'Pembuatan Kasus Uji Otomatis Lengkap',
          desc: 'Gunakan template ini untuk membuat pengujian otomatis dengan skenario normal, edge cases, dan exception.',
          template: `Buatkan suite unit test menggunakan pytest untuk menguji fungsi/kelas berikut:\n\n\`\`\`python\n[PASTE_FUNGSI_ATAU_KELAS_DI_SINI]\n\`\`\`\n\nCakupan pengujian yang diminta:\n1. Test case skenario normal (happy path).\n2. Test case nilai batas dan edge cases (misal: data kosong, nilai 0, nilai ekstrem).\n3. Test case penanganan exception/error handling saat input tidak valid.`
        },
        {
          id: 'tpl-oop',
          tag: 'OOP & Validasi Model',
          color: 'teal',
          title: 'Pembuatan Kelas Model dengan Enkapsulasi & Validasi',
          desc: 'Cocok untuk membuat class entity (seperti Product, User, Account) dengan setter validation.',
          template: `Buatkan kelas Python bernama [NamaKelas] dengan atribut [id, name, price, stock].\n\nKetentuan:\n1. Gunakan method setter/property untuk memvalidasi bahwa harga (price) dan stok (stock) tidak boleh negatif.\n2. Tambahkan method [nama_method_tambahan] untuk memproses [tujuan_method].\n3. Tambahkan method __str__ yang mudah dibaca.\n4. Sertakan contoh instansiasi objek dan penanganan ValueError jika input tidak valid.`
        },
        {
          id: 'tpl-sql',
          tag: 'SQL & Query Database',
          color: 'sky',
          title: 'Query Relasional Terstruktur & Efisien',
          desc: 'Cocok untuk query JOIN, agregasi, subquery, dan indexing database.',
          template: `Saya butuh query SQL yang efisien untuk [tujuan query].\n\nStruktur Tabel:\n- Tabel [tabel_1] (kolom: id, user_id, status, created_at)\n- Tabel [tabel_2] (kolom: id, name, category)\n\nKetentuan:\n1. Lakukan INNER/LEFT JOIN yang tepat antar tabel.\n2. Filter data dengan syarat [kondisi_where].\n3. Urutkan berdasarkan [kolom_order] secara descending dengan LIMIT [jumlah].\n4. Berikan penjelasan singkat mengenai logika query.`
        }
      ];

      Swal.fire({
        title: '<div class="text-left"><span class="text-xl font-bold text-slate-900">Panduan Prompting Dinamis: Hasil Tepat & Minim Error</span><p class="text-xs font-normal text-slate-500 mt-1">Kiat praktis menyusun prompt cerdas pemrograman untuk mendapatkan jawaban akurat, siap pakai, dan hemat kuota token.</p></div>',
        width: 900,
        showConfirmButton: true,
        confirmButtonText: 'Tutup & Mulai Chat',
        confirmButtonColor: '#00A0A5',
        showCloseButton: true,
        customClass: {
          popup: 'rounded-2xl shadow-2xl border border-slate-200 text-left p-6',
        },
        html: `
          <div class="text-left text-sm text-slate-700 space-y-4">
            <!-- Navigation Tabs -->
            <div class="flex flex-wrap gap-1 border-b border-slate-200 pb-2" id="modal-tab-nav">
              <button type="button" class="tab-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-[#00A0A5] text-white transition" data-tab="tab-gold">Kaidah Utama</button>
              <button type="button" class="tab-btn px-3.5 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100 transition" data-tab="tab-framework">Framework 4L</button>
              <button type="button" class="tab-btn px-3.5 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100 transition" data-tab="tab-compare">Sebelum vs Sesudah</button>
              <button type="button" class="tab-btn px-3.5 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100 transition" data-tab="tab-templates">Koleksi Template Siap Pakai</button>
            </div>

            <!-- TAB 1: Kaidah Utama -->
            <div id="tab-gold" class="tab-pane space-y-3">
              <div class="grid sm:grid-cols-2 gap-3">
                <div class="p-3.5 rounded-xl border border-indigo-100 bg-indigo-50/50">
                  <div class="font-semibold text-indigo-950 flex items-center gap-1.5 mb-1">
                    <span class="w-5 h-5 rounded-full bg-indigo-600 text-white text-[11px] grid place-items-center font-bold">1</span>
                    Spesifik & Eksplisitkan Parameter
                  </div>
                  <p class="text-xs text-indigo-900/80 leading-relaxed">
                    Sebutkan dengan jelas nama fungsi/kelas, tipe input (misal: <code class="bg-indigo-100 px-1 py-0.5 rounded text-[11px]">list[int]</code>), dan tipe nilai kembalian agar AI tidak mengarang asumsi sendiri.
                  </p>
                </div>

                <div class="p-3.5 rounded-xl border border-rose-100 bg-rose-50/50">
                  <div class="font-semibold text-rose-950 flex items-center gap-1.5 mb-1">
                    <span class="w-5 h-5 rounded-full bg-rose-600 text-white text-[11px] grid place-items-center font-bold">2</span>
                    Sertakan Validasi & Edge Cases
                  </div>
                  <p class="text-xs text-rose-900/80 leading-relaxed">
                    Instruksikan batasan nyata seperti <code class="bg-rose-100 px-1 py-0.5 rounded text-[11px]">nilai tidak boleh negatif</code>, <code class="bg-rose-100 px-1 py-0.5 rounded text-[11px]">input kosong</code>, atau exception handling agar kode siap lolos unit test.
                  </p>
                </div>

                <div class="p-3.5 rounded-xl border border-emerald-100 bg-emerald-50/50">
                  <div class="font-semibold text-emerald-950 flex items-center gap-1.5 mb-1">
                    <span class="w-5 h-5 rounded-full bg-emerald-600 text-white text-[11px] grid place-items-center font-bold">3</span>
                    Pilih Mode Respons yang Tepat
                  </div>
                  <p class="text-xs text-emerald-900/80 leading-relaxed">
                    Pilih mode <strong class="text-emerald-800">Code (only)</strong> saat mengerjakan tugas koding untuk <strong>menghemat token hingga 60%</strong> dan mendapatkan kode bersih tanpa teks narasi.
                  </p>
                </div>

                <div class="p-3.5 rounded-xl border border-amber-100 bg-amber-50/50">
                  <div class="font-semibold text-amber-950 flex items-center gap-1.5 mb-1">
                    <span class="w-5 h-5 rounded-full bg-amber-600 text-white text-[11px] grid place-items-center font-bold">4</span>
                    Manfaatkan Konteks Multi-Turn
                  </div>
                  <p class="text-xs text-amber-900/80 leading-relaxed">
                    Jangan ketik ulang seluruh kode; cukup katakan: <em class="text-amber-800">"Sekarang tambahkan method apply_discount pada kelas di atas"</em>, AI akan otomatis mengingat kode sebelumnya.
                  </p>
                </div>
              </div>

              <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
                <span><strong>Pencarian Semantik Otomatis (FREE):</strong> Jika pertanyaan serupa sudah ada di basis data semantik (&gt;= 90%), sistem menjawab instan dengan <strong>0 Token (Gratis)</strong>.</span>
              </div>
            </div>

            <!-- TAB 2: Framework 4L -->
            <div id="tab-framework" class="tab-pane hidden space-y-3">
              <p class="text-xs text-slate-500">Framework <strong>4L (Logika, Larangan, Luaran, Latihan)</strong> adalah standar industri agar AI menghasilkan solusi tepat sasaran pada percobaan pertama:</p>
              
              <div class="space-y-2 text-xs">
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-2.5">
                  <span class="font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded uppercase text-[11px] shrink-0">1. Logika</span>
                  <div><strong>Tujuan Utama:</strong> Jelaskan secara lugas apa tugas atau algoritma yang ingin dibangun (misal: <em>"Algoritma Dijkstra untuk graf berbobot positif"</em>).</div>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-2.5">
                  <span class="font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded uppercase text-[11px] shrink-0">2. Larangan</span>
                  <div><strong>Batasan & Validasi:</strong> Sebutkan aturan ketat (misal: <em>"Gunakan modul heapq bawaan, jangan gunakan recursion untuk menghindari stack overflow"</em>).</div>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-2.5">
                  <span class="font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded uppercase text-[11px] shrink-0">3. Luaran</span>
                  <div><strong>Format Kode:</strong> Tentukan format yang diharapkan (misal: <em>"Kembalikan pure code dengan type hints dan dictionary jarak terpendek"</em>).</div>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-2.5">
                  <span class="font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded uppercase text-[11px] shrink-0">4. Latihan</span>
                  <div><strong>Kasus Uji:</strong> Minta contoh pemanggilan fungsi dan penanganan input salah (misal: <em>"Sertakan blok try-except untuk menguji graf terputus"</em>).</div>
                </div>
              </div>
            </div>

            <!-- TAB 3: Sebelum vs Sesudah -->
            <div id="tab-compare" class="tab-pane hidden space-y-3">
              <div class="space-y-3 text-xs">
                <!-- Case 1 -->
                <div class="p-3 rounded-xl border border-slate-200 bg-white shadow-sm space-y-2">
                  <div class="font-semibold text-slate-800 text-[13px]">Kasus 1: Pembuatan Kelas OOP & Validasi</div>
                  <div class="grid sm:grid-cols-2 gap-2">
                    <div class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-900">
                      <div class="font-bold text-rose-700 mb-0.5">Format Kurang Efektif (Terlalu Ambigu)</div>
                      <p class="italic font-mono text-[11px]">"Bikin class product buat e-commerce python"</p>
                      <div class="text-[10px] text-rose-600 mt-1">Dampak: Tidak ada validasi harga negatif, atribut tidak konsisten, boros token karena penjelasan terlalu panjang.</div>
                    </div>
                    <div class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900">
                      <div class="font-bold text-emerald-700 mb-0.5">Format Tepat & Terstruktur</div>
                      <p class="font-mono text-[11px]">"Buatkan kelas Python Product dengan atribut id, name, price, stock. Tambahkan setter validation agar price dan stock tidak boleh negatif."</p>
                      <div class="text-[10px] text-emerald-700 mt-1">Hasil: Kode 100% tepat, ada ValueError exception, langsung lolos unit test.</div>
                    </div>
                  </div>
                </div>

                <!-- Case 2 -->
                <div class="p-3 rounded-xl border border-slate-200 bg-white shadow-sm space-y-2">
                  <div class="font-semibold text-slate-800 text-[13px]">Kasus 2: Debugging Error Message</div>
                  <div class="grid sm:grid-cols-2 gap-2">
                    <div class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-900">
                      <div class="font-bold text-rose-700 mb-0.5">Format Kurang Efektif</div>
                      <p class="italic font-mono text-[11px]">"Kode saya error tolong benerin dong"</p>
                      <div class="text-[10px] text-rose-600 mt-1">Dampak: AI tidak mengetahui letak baris error dan harus menebak konteks.</div>
                    </div>
                    <div class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900">
                      <div class="font-bold text-emerald-700 mb-0.5">Format Tepat & Lengkap</div>
                      <p class="font-mono text-[11px]">"Saya mendapat TypeError: unsupported operand '+' int and str pada baris 'total = harga + pajak'. Berikut kodenya: [...] Tolong berikan solusinya."</p>
                      <div class="text-[10px] text-emerald-700 mt-1">Hasil: AI langsung mengidentifikasi type mismatch dan memberikan konversi float() yang benar.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 4: Koleksi Template Siap Pakai -->
            <div id="tab-templates" class="tab-pane hidden space-y-3">
              <p class="text-xs text-slate-500">Pilih salah satu template di bawah, lalu klik <strong>"Gunakan di Chat"</strong> untuk langsung menggunakannya:</p>
              
              <div class="grid sm:grid-cols-2 gap-2.5 max-h-[320px] overflow-y-auto pr-1" id="template-cards-list">
                ${templates.map(t => `
                  <div class="p-3 rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-white hover:border-slate-300 transition flex flex-col justify-between gap-2">
                    <div>
                      <div class="flex items-center justify-between gap-1 mb-1">
                        <span class="font-semibold text-xs text-slate-900 truncate">${t.title}</span>
                        <span class="text-[10px] font-bold text-${t.color}-700 bg-${t.color}-50 px-1.5 py-0.5 rounded shrink-0">${t.tag}</span>
                      </div>
                      <p class="text-[11px] text-slate-500 line-clamp-2">${t.desc}</p>
                    </div>
                    <div class="flex items-center gap-2 pt-1 border-t border-slate-100">
                      <button type="button" class="btn-insert-tpl flex-1 py-1 px-2 rounded-lg bg-[#00A0A5] hover:bg-[#008488] text-white text-[11px] font-medium transition text-center" data-template="${encodeURIComponent(t.template)}">
                        Gunakan di Chat
                      </button>
                      <button type="button" class="btn-copy-tpl py-1 px-2 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 text-[11px] transition" data-template="${encodeURIComponent(t.template)}">
                        Salin
                      </button>
                    </div>
                  </div>
                `).join('')}
              </div>
            </div>
          </div>
        `,
        didOpen: () => {
          // Tab switching logic
          const tabBtns = document.querySelectorAll('#modal-tab-nav .tab-btn');
          const tabPanes = document.querySelectorAll('.tab-pane');

          tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
              const targetTab = btn.getAttribute('data-tab');
              
              tabBtns.forEach(b => {
                b.classList.remove('bg-[#00A0A5]', 'text-white');
                b.classList.add('text-slate-600', 'hover:bg-slate-100');
              });
              btn.classList.add('bg-[#00A0A5]', 'text-white');
              btn.classList.remove('text-slate-600', 'hover:bg-slate-100');

              tabPanes.forEach(pane => {
                if (pane.id === targetTab) {
                  pane.classList.remove('hidden');
                } else {
                  pane.classList.add('hidden');
                }
              });
            });
          });

          // Insert template button handler
          document.querySelectorAll('.btn-insert-tpl').forEach(btn => {
            btn.addEventListener('click', () => {
              const rawTpl = decodeURIComponent(btn.getAttribute('data-template') || '');
              if (chatInput && rawTpl) {
                chatInput.value = rawTpl;
                autoResizeTextarea();
                validateInputState();
                chatInput.focus();
                try { Swal.close(); } catch (e) {}
              }
            });
          });

          // Copy template button handler
          document.querySelectorAll('.btn-copy-tpl').forEach(btn => {
            btn.addEventListener('click', () => {
              const rawTpl = decodeURIComponent(btn.getAttribute('data-template') || '');
              if (rawTpl) {
                navigator.clipboard.writeText(rawTpl).then(() => {
                  btn.textContent = 'Tersalin';
                  setTimeout(() => { btn.textContent = 'Salin'; }, 1500);
                });
              }
            });
          });
        }
      });
    }

    if (viewPromptTipsBtn) {
      viewPromptTipsBtn.addEventListener('click', () => {
        showPromptingTipsModal();
      });
    }

    // Ambil status API key dan informasi awal
    checkUserApiKey();
    fetchQueryQuota();
    refreshGamification();
    if (state.messages.length === 0) {
      addMessage('bot', 'Hello! I\'m ready to help with any questions about programming. Just ask me anything related to coding.');
    }
    chatInput.focus();
    autoResizeTextarea();
  </script>
</body>
</html>
