<?php
require_once(__DIR__ . '/_sso_bridge.php');

// Chatbot Assistant is reserved for student learning inquiries
if ($sso_role === 'lecturer' || $sso_role === 'admin') {
    header("Location: environmental_impact.php");
    exit;
}

// Must select Course & Assessment context first
if (empty($_SESSION['assessment_id']) || empty($_SESSION['current_course_id'])) {
    header("Location: courses.php");
    exit;
}

$currentCourse = $_SESSION['current_course'] ?? 'General Programming';
$currentCourseId = $_SESSION['current_course_id'] ?? '';
$currentAssessment = $_SESSION['current_assessment'] ?? 'Active Assessment';
$assessmentId = $_SESSION['assessment_id'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Programming Assistant Chatbot - S-SPARC AI</title>
  <link rel="icon" href="../strange_html_layout_additional_files/icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Select2 CSS & JS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <!-- Markdown & HTML Sanitizer & Highlight.js -->
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
  <style>
    :root { color-scheme: light; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    .glass { backdrop-filter: blur(10px); background: rgba(255,255,255,0.9); }
    .typing-dot { width: 8px; height: 8px; border-radius: 999px; background: #475569; animation: blink 1.2s infinite; }
    .typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .typing-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes blink { 0%, 80%, 100% { opacity: 0.2; } 40% { opacity: 1; } }
    
    /* Sleek Code Block Aesthetics */
    .code-block-container {
      margin: 0.75rem 0;
      border-radius: 0.75rem;
      overflow: hidden;
      border: 1px solid #1e293b;
      background: #0f172a;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
    }
    .code-block-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.45rem 0.85rem;
      background: #1e293b;
      border-bottom: 1px solid #334155;
      user-select: none;
    }
    .code-block-body {
      padding: 1rem;
      font-family: 'JetBrains Mono', monospace !important;
      font-size: 0.825rem;
      line-height: 1.6;
      background: #0b0f19 !important;
      overflow-x: auto;
      color: #e2e8f0;
      margin: 0 !important;
    }
    .code-block-body code {
      font-family: 'JetBrains Mono', monospace !important;
      background: transparent !important;
      padding: 0 !important;
    }
    .copy-code-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.25rem 0.65rem;
      border-radius: 0.375rem;
      font-size: 0.725rem;
      font-weight: 600;
      background: #334155;
      color: #cbd5e1;
      border: 1px solid #475569;
      cursor: pointer;
      transition: all 0.15s ease-in-out;
    }
    .copy-code-btn:hover {
      background: #475569;
      color: #ffffff;
      border-color: #64748b;
    }
    .copy-code-btn.copied {
      background: #064e3b !important;
      color: #34d399 !important;
      border-color: #059669 !important;
    }

    /* Select2 Compact Styling */
    .select2-container--default .select2-selection--single {
      height: 32px;
      border: 1px solid #cbd5e1;
      border-radius: 0.5rem;
      display: flex;
      align-items: center;
      background-color: #ffffff;
      padding-left: 0.35rem;
      font-size: 0.75rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      color: #0f172a;
      line-height: 30px;
      font-weight: 500;
      padding-left: 0.2rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 30px;
      right: 6px;
    }
    .select2-dropdown {
      border: 1px solid #cbd5e1;
      border-radius: 0.5rem;
      font-size: 0.75rem;
      z-index: 9999;
    }
    .select2-results__option--highlighted[aria-selected] {
      background-color: #0f172a !important;
      color: #ffffff !important;
    }

    /* Clean Markdown Typography Styling inside Chat Bubbles */
    .chat-bubble-content p {
      margin-bottom: 0.5rem;
      line-height: 1.55;
    }
    .chat-bubble-content p:last-child {
      margin-bottom: 0;
    }
    .chat-bubble-content ul {
      list-style-type: disc;
      padding-left: 1.25rem;
      margin-top: 0.35rem;
      margin-bottom: 0.5rem;
    }
    .chat-bubble-content ol {
      list-style-type: decimal;
      padding-left: 1.25rem;
      margin-top: 0.35rem;
      margin-bottom: 0.5rem;
    }
    .chat-bubble-content li {
      margin-bottom: 0.25rem;
    }
    .chat-bubble-content strong {
      font-weight: 700;
      color: inherit;
    }
    .chat-bubble-content em {
      font-style: italic;
    }
    .chat-bubble-content h1, 
    .chat-bubble-content h2, 
    .chat-bubble-content h3, 
    .chat-bubble-content h4 {
      font-weight: 700;
      margin-top: 0.75rem;
      margin-bottom: 0.35rem;
      color: inherit;
    }
    .chat-bubble-content h1 { font-size: 1.15rem; }
    .chat-bubble-content h2 { font-size: 1.05rem; }
    .chat-bubble-content h3 { font-size: 0.95rem; }
    .chat-bubble-content blockquote {
      border-left: 3px solid #cbd5e1;
      padding-left: 0.75rem;
      margin: 0.5rem 0;
      color: #64748b;
    }
    .chat-bubble-content code:not(pre code) {
      background-color: #f1f5f9;
      color: #0f172a;
      padding: 0.15rem 0.4rem;
      border-radius: 0.35rem;
      font-size: 0.8rem;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      border: 1px solid #e2e8f0;
      font-weight: 600;
    }
    .user-bubble .chat-bubble-content code:not(pre code) {
      background-color: rgba(255, 255, 255, 0.2);
      color: #fef08a;
      border-color: rgba(255, 255, 255, 0.3);
    }
  </style>
  <style>
/* Premium Teal Dropdown Styling for E-STRANGE & S-SPARC */
/* Ensure SweetAlert2 hidden select is never displayed */
.swal2-container select,
.swal2-popup select,
.swal2-select {
  display: none !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select):not(.gemini-pill-select), .form-select, .custom-select {
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

select:not(.select2-hidden-accessible):not(.swal2-select):not(.gemini-pill-select):hover, .form-select:hover {
  border-color: #00A0A5 !important;
  background-color: #f8fafc !important;
  box-shadow: 0 4px 12px rgba(0, 160, 165, 0.08) !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select):not(.gemini-pill-select):focus, .form-select:focus {
  outline: none !important;
  border-color: #00A0A5 !important;
  box-shadow: 0 0 0 3px rgba(0, 160, 165, 0.2) !important;
  background-color: #ffffff !important;
}

/* Gemini-Style Subtle Pill Dropdown Controls */
.gemini-pill-select {
  appearance: none !important;
  -webkit-appearance: none !important;
  -moz-appearance: none !important;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
  background-repeat: no-repeat !important;
  background-position: right 0.75rem center !important;
  background-size: 0.75rem 0.75rem !important;
  padding: 0.45rem 2rem 0.45rem 0.95rem !important;
  margin-right: 0.5rem !important;
  background-color: #f1f5f9 !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 9999px !important;
  color: #334155 !important;
  font-size: 0.775rem !important;
  font-weight: 600 !important;
  cursor: pointer !important;
  transition: all 0.15s ease !important;
  outline: none !important;
  min-width: auto !important;
  min-height: auto !important;
  display: inline-flex !important;
  align-items: center !important;
  box-shadow: none !important;
}

.gemini-pill-select:last-child {
  margin-right: 0 !important;
}

.gemini-pill-select:hover {
  background-color: #e2e8f0 !important;
  border-color: #cbd5e1 !important;
  color: #0f172a !important;
}

.gemini-pill-select:focus {
  border-color: #00A0A5 !important;
  background-color: #ffffff !important;
  box-shadow: 0 0 0 2px rgba(0, 160, 165, 0.25) !important;
  color: #0f172a !important;
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
  
  <?php renderSSOHeader('chat', 'Chat Assistant'); ?>

  <!-- Academic Context Sub-Header (Verified from E-STRANGE) -->
  <div class="border-b border-slate-200/80 bg-white/70 backdrop-blur">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex flex-wrap items-center justify-between gap-3 text-xs">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-[#00A0A5] text-white">
          Verified Context
        </span>
        <span class="text-slate-600">
          Course: <strong class="text-slate-900"><?= htmlspecialchars($currentCourse) ?></strong>
        </span>
        <span class="text-slate-300">|</span>
        <span class="text-slate-600">
          Assessment: <strong class="text-slate-900"><?= htmlspecialchars($currentAssessment) ?></strong>
        </span>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <button id="manage-api-key-btn" type="button" onclick="openApiKeyModal()" class="inline-flex h-7 items-center gap-1 rounded-md border border-teal-300 bg-teal-50 px-2.5 text-[11px] font-semibold text-teal-800 hover:bg-teal-100 transition shadow-2xs">
          <span>🔑</span>
          <span id="api-key-btn-text">API Key</span>
        </button>
        <a href="courses.php" class="inline-flex h-7 items-center rounded-md border border-slate-300 bg-white px-2.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50 hover:border-slate-400 no-underline transition">
          Change Context
        </a>
        <button id="new-chat" type="button" class="inline-flex h-7 items-center rounded-md bg-[#00A0A5] text-white px-2.5 text-[11px] font-medium hover:bg-slate-800 transition">
          New Chat
        </button>
        <button id="clear-chat" type="button" class="inline-flex h-7 items-center rounded-md border border-slate-300 bg-white px-2.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50 transition">
          Clear History
        </button>
      </div>
    </div>
  </div>

  <main class="flex-1 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-5 lg:grid-cols-[1fr_350px] items-stretch">
      
      <!-- Chat Interface Main Area -->
      <section class="glass rounded-2xl border border-slate-200/80 shadow-sm p-4 sm:p-6 flex flex-col h-full justify-between">
        
        <!-- Chat Window -->
        <div id="chat-window" class="flex-1 overflow-y-auto space-y-4 pr-1 min-h-[480px] max-h-[680px]" aria-live="polite"></div>
        
        <!-- Typing Indicator -->
        <div id="typing" class="hidden mt-3 flex items-center gap-2 text-xs text-slate-600">
          <span class="inline-flex items-center gap-1">
            <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
          </span>
          <span>Assistant is processing…</span>
        </div>

        <!-- Rate Limit Cooldown Notice -->
        <div id="rate-limit-notice" class="hidden mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex items-center justify-between gap-2 animate-pulse">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span id="rate-limit-text" class="font-medium">Cooldown aktif. Mohon tunggu sebelum mengirim pesan berikutnya.</span>
          </div>
          <span id="rate-limit-timer-badge" class="px-2 py-0.5 rounded-md bg-amber-600 text-white font-mono font-bold text-[11px]">60s</span>
        </div>

        <!-- Chat Form Input (Gemini-Style Unified Box) -->
        <form id="chat-form" class="mt-4" onsubmit="sendMessage(event)">
          <div class="rounded-3xl border border-slate-300/90 bg-white p-4 shadow-sm focus-within:border-[#00A0A5] focus-within:ring-2 focus-within:ring-[#00A0A5]/20 transition flex flex-col justify-between">
            
            <!-- Textarea Area -->
            <label for="chat-input" class="sr-only">Write a message</label>
            <textarea id="chat-input" rows="3" class="w-full min-h-[5.5rem] max-h-56 resize-none overflow-y-auto bg-transparent px-3 pt-1.5 pb-2 text-sm text-slate-900 outline-none placeholder:text-slate-400 leading-relaxed font-sans" placeholder="Tuliskan pertanyaan pemrograman, analisis error, atau paste kode tugas Anda di sini (min. 10 karakter, maks. 2000)..." required></textarea>

            <!-- Bottom Toolbar inside Gemini Box -->
            <div class="mt-3 pt-3 px-1 border-t border-slate-100 flex items-center justify-between gap-3">
              
              <!-- Left Controls: Language & Mode Selector -->
              <div class="flex items-center gap-2 flex-wrap">
                <select id="language-select" class="gemini-pill-select" title="Pilih Bahasa Pemrograman">
                  <option value="">Auto-detect</option>
                  <option value="Python" selected>Python</option>
                  <option value="JavaScript">JavaScript</option>
                  <option value="Java">Java</option>
                  <option value="C">C</option>
                  <option value="C++">C++</option>
                  <option value="Go">Go</option>
                  <option value="PHP">PHP</option>
                  <option value="SQL">SQL</option>
                </select>

                <select id="response-mode" class="gemini-pill-select" title="Pilih Format Respon">
                  <option value="code" selected>Code (only)</option>
                  <option value="summary">Explanation (short)</option>
                  <option value="summary_code_explanation">Summary + Code + Explanation</option>
                </select>
              </div>

              <!-- Right Controls: Dynamic Query Badge, Character Counter & Circular Send Button (Gemini-style) -->
              <div class="flex items-center gap-2 sm:gap-3 flex-wrap justify-end">
                <div id="query-quota-badge" onclick="showTermsModal()" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-teal-50/90 border border-teal-200/80 text-[11px] font-semibold text-teal-800 shadow-2xs cursor-pointer hover:bg-teal-100 transition" title="Klik untuk rincian kuota & syarat ketentuan">
                  <span class="text-teal-600">⚡</span>
                  <span>Sisa Query: <strong id="query-remaining-count" class="font-mono font-bold text-teal-900">1,500</strong> / <span id="query-limit-count" class="font-mono text-slate-500">1,500</span></span>
                </div>
                <div id="char-counter" class="text-[11px] font-mono text-slate-400">
                  0 / 2000 chars (min. 10)
                </div>
                <button id="send-btn" type="submit" class="w-10 h-10 flex items-center justify-center rounded-full bg-[#00A0A5] text-white hover:bg-[#008589] transition shadow-sm hover:scale-105 active:scale-95 disabled:opacity-50" title="Send Message (min. 10 chars)">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                  </svg>
                </button>
              </div>

            </div>

          </div>
        </form>
      </section>

      <!-- Sidebar Prompt Templates & Token Status -->
      <aside class="flex flex-col justify-between space-y-4 h-full">
        
        <!-- Quick Prompt Templates Card -->
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
              <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
              Quick Prompt Templates
            </span>
            <span class="text-[10px] font-bold text-teal-700 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded-full uppercase">Contextual</span>
          </div>
          <p class="text-xs text-slate-500 mb-3">Format prompt terstruktur sesuai konteks tugas <strong class="text-slate-700 font-semibold"><?= htmlspecialchars($currentAssessment) ?></strong> untuk hasil presisi & hemat kuota token:</p>
          <div class="space-y-2 text-xs" id="quick-prompt-templates">
            
            <button type="button" class="w-full text-left p-2.5 rounded-xl border border-slate-100 bg-slate-50 hover:bg-slate-100/80 hover:border-slate-300 transition text-slate-700 flex flex-col gap-0.5 group" data-fill="Saya sedang mengerjakan tugas <?= htmlspecialchars($currentAssessment) ?> pada mata kuliah <?= htmlspecialchars($currentCourse) ?>. Saya mengalami error berikut:&#10;&#10;[Tuliskan pesan error / masalah di sini]&#10;&#10;Berikut potongan kode saya:&#10;```python&#10;# Paste kode Anda di sini&#10;```&#10;&#10;Tolong jelaskan letak kesalahan dan berikan solusi perbaikannya.">
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-800 group-hover:text-teal-700">Debug & Fix Error Tugas</span>
                <span class="text-[10px] font-mono text-slate-400 bg-slate-200/60 px-1.5 py-0.2 rounded">Diagnosis</span>
              </div>
              <span class="text-[11px] text-slate-500">Lacak bug, root-cause error, dan perbaikan sintaksis</span>
            </button>

            <button type="button" class="w-full text-left p-2.5 rounded-xl border border-slate-100 bg-slate-50 hover:bg-slate-100/80 hover:border-slate-300 transition text-slate-700 flex flex-col gap-0.5 group" data-fill="Tolong analisis efisiensi komputasi dari kode saya untuk tugas <?= htmlspecialchars($currentAssessment) ?> agar lebih hemat waktu eksekusi dan memori:&#10;&#10;```python&#10;# Paste kode Anda di sini&#10;```&#10;&#10;Jelaskan analisis Time & Space Complexity (Big-O) serta berikan versi kode yang lebih optimal.">
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-800 group-hover:text-teal-700">Optimasi Efisiensi & Big-O</span>
                <span class="text-[10px] font-mono text-emerald-700 bg-emerald-50 border border-emerald-200/60 px-1.5 py-0.2 rounded">Green Code</span>
              </div>
              <span class="text-[11px] text-slate-500">Optimalkan runtime, kompleksitas waktu, dan konsumsi memori</span>
            </button>

            <button type="button" class="w-full text-left p-2.5 rounded-xl border border-slate-100 bg-slate-50 hover:bg-slate-100/80 hover:border-slate-300 transition text-slate-700 flex flex-col gap-0.5 group" data-fill="Buatkan skenario pengujian (test cases) lengkap untuk memvalidasi solusi tugas <?= htmlspecialchars($currentAssessment) ?> (Mata Kuliah: <?= htmlspecialchars($currentCourse) ?>).&#10;&#10;Sertakan contoh kasus normal, boundary/edge cases (nilai 0, negatif, data kosong), dan penanganan error.">
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-800 group-hover:text-teal-700">Uji Edge Cases & Validasi</span>
                <span class="text-[10px] font-mono text-teal-700 bg-teal-50 border border-teal-200/60 px-1.5 py-0.2 rounded">Testing</span>
              </div>
              <span class="text-[11px] text-slate-500">Generate automated test case, batas nilai, dan edge cases</span>
            </button>

            <button type="button" class="w-full text-left p-2.5 rounded-xl border border-slate-100 bg-slate-50 hover:bg-slate-100/80 hover:border-slate-300 transition text-slate-700 flex flex-col gap-0.5 group" data-fill="Saya butuh panduan alur logika / pseudocode langkah demi langkah untuk menyelesaikan tugas <?= htmlspecialchars($currentAssessment) ?> pada mata kuliah <?= htmlspecialchars($currentCourse) ?>. Tolong jelaskan konsep algoritmanya tanpa memberikan full source code secara langsung agar saya bisa belajar mengimplementasikannya sendiri.">
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-800 group-hover:text-teal-700">Petunjuk Algoritma (Socratic)</span>
                <span class="text-[10px] font-mono text-amber-700 bg-amber-50 border border-amber-200/60 px-1.5 py-0.2 rounded">Guidance</span>
              </div>
              <span class="text-[11px] text-slate-500">Bimbingan konsep pseudocode terstruktur tanpa spoiler kode</span>
            </button>

          </div>
          
          <div class="mt-3 pt-2.5 border-t border-slate-100 text-[11px] text-slate-500 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-slate-600 font-medium">Token Saving Tip:</span>
              <span class="text-emerald-700 font-semibold bg-emerald-50 px-1.5 py-0.5 rounded">Mode: Code (only)</span>
            </div>
            <p class="leading-relaxed">Gunakan <strong>Code (only)</strong> untuk memotong konsumsi token hingga 60% dan mempercepat respon.</p>
            <button type="button" onclick="showPromptingTipsModal()" class="w-full py-1.5 px-3 rounded-xl border border-teal-200 bg-teal-50/80 hover:bg-teal-100/80 text-teal-800 text-sm font-semibold flex items-center justify-center gap-1.5 transition shadow-xs">
              Buka Panduan Prompting
            </button>
          </div>
        </div>

        <!-- Token Usage & System Policy Card -->
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <div class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
              <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
              <span>S-SPARC Access &amp; Policy</span>
            </div>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase">
              Free Access
            </span>
          </div>

          <!-- Policy Specifications -->
          <div class="text-xs text-slate-700 flex flex-col gap-2 bg-slate-50 p-3 rounded-xl border border-slate-100">
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Access Tier</span>
              <span class="font-bold text-teal-700">Personal Gemini Key</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Sisa Kuota Hari Ini</span>
              <span id="sidebar-query-remaining" class="font-bold text-teal-800 font-mono">1,500 req</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Rate Limit Cooldown</span>
              <span class="font-bold text-slate-900 font-mono">1 request / minute</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Prompt Limits</span>
              <span class="font-bold text-slate-900 font-mono">10 &ndash; 2,000 chars</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Gamification Impact</span>
              <span class="font-bold text-emerald-600 font-mono">0 Pts (Free)</span>
            </div>
          </div>

          <!-- Multi-tier Fallback Guarantee Box -->
          <div class="rounded-xl border border-teal-100 bg-gradient-to-br from-teal-50/90 to-emerald-50/70 p-3 text-xs space-y-1.5">
            <div class="font-semibold text-teal-900 flex items-center gap-1.5 text-xs">
              <svg class="w-3.5 h-3.5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
              Multi-Tier Failover Guarantee
            </div>
            <p class="text-[11px] text-slate-600 leading-relaxed">
              Jika kuota API key pribadi Anda habis atau rate limit, sistem otomatis mengalihkan permintaan ke <strong>System Key Pool</strong> atau <strong>Local Ollama (Qwen2.5-Coder 14B)</strong> tanpa henti.
            </p>
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
              <span>🔑</span>
              <span>Kelola Google Gemini API Key</span>
            </button>
          </div>
        </div>
      </aside>
    </div>
  </main>

  <script>
    const FASTAPI_URL = "http://127.0.0.1:5000";
    const SSO_USER_ID = "<?= htmlspecialchars($sso_user_id) ?>";
    const CURRENT_COURSE_ID = "<?= htmlspecialchars($currentCourseId) ?>";
    const CURRENT_ASSESSMENT_ID = "<?= htmlspecialchars($assessmentId) ?>";

    const chatWindow = document.getElementById('chat-window');
    const chatInput = document.getElementById('chat-input');
    const typing = document.getElementById('typing');
    const languageSelect = document.getElementById('language-select');
    const responseModeSelect = document.getElementById('response-mode');
    const newChatBtn = document.getElementById('new-chat');
    const clearChatBtn = document.getElementById('clear-chat');
    const viewPromptTipsBtn = document.getElementById('view-prompt-tips');
    const sendBtn = document.getElementById('send-btn');
    const charCounter = document.getElementById('char-counter');
    const rateLimitNotice = document.getElementById('rate-limit-notice');
    const rateLimitTimerBadge = document.getElementById('rate-limit-timer-badge');
    const apiKeyBtnText = document.getElementById('api-key-btn-text');

    const STORAGE_KEY = 'ssparc_chat_' + SSO_USER_ID + '_' + CURRENT_ASSESSMENT_ID;
    const state = { messages: [], inCooldown: false, hasApiKey: false };
    let cooldownInterval = null;

    // Configure marked options
    if (typeof marked !== 'undefined') {
      marked.setOptions({
        gfm: true,
        breaks: true
      });
    }

    function loadMessages() {
      try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) {
          state.messages = [
            {
              sender: 'bot',
              text: 'Hello **<?= htmlspecialchars($sso_name) ?>**! You are working on assessment: **<?= htmlspecialchars($currentAssessment) ?>** in course **<?= htmlspecialchars($currentCourse) ?>**.\n\nFeel free to ask programming questions, request error debugging, or get algorithmic guidance.',
              meta: 'S-SPARC AI context verified'
            }
          ];
        } else {
          state.messages = JSON.parse(saved).slice(-200);
        }
        renderMessages();
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

    function escapeHtml(string) {
      return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderMessages() {
      chatWindow.innerHTML = '';
      state.messages.forEach(msg => {
        const isUser = msg.sender === 'user';
        const row = document.createElement('div');
        row.className = isUser ? 'flex justify-end items-start gap-2 user-bubble' : 'flex justify-start items-start gap-2';

        const avatar = document.createElement('div');
        avatar.className = 'h-8 w-8 rounded-full flex-shrink-0 grid place-items-center text-xs font-semibold shadow-xs ' + 
          (isUser ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 border border-slate-200');
        avatar.textContent = isUser ? 'You' : 'AI';

        const bubble = document.createElement('div');
        bubble.className = isUser
          ? 'max-w-2xl rounded-2xl bg-[#00A0A5] text-white px-4 py-3 shadow-xs text-sm leading-relaxed'
          : 'max-w-2xl rounded-2xl bg-white text-slate-900 px-4 py-3 shadow-xs border border-slate-200 text-sm leading-relaxed';

        if (!isUser && msg.meta) {
          const meta = document.createElement('div');
          meta.className = 'text-[11px] text-slate-500 mb-1.5 flex items-center gap-2 font-medium';
          meta.textContent = msg.meta;
          bubble.appendChild(meta);
        }

        const textContent = document.createElement('div');
        textContent.className = 'chat-bubble-content';
        textContent.innerHTML = formatMessageContent(msg.text, isUser);
        bubble.appendChild(textContent);

        if (isUser) {
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

    function formatMessageContent(rawText, isUser = false) {
      if (!rawText) return '';
      let textToParse = rawText.trim();
      
      if (isUser) {
        if (!textToParse.includes('```')) {
          return escapeHtml(textToParse)
            .replace(/\n/g, '<br/>')
            .replace(/`([^`]+)`/g, '<code class="bg-black/20 px-1.5 py-0.5 rounded text-xs font-mono text-white">$1</code>');
        }
      }

      if (!isUser && !textToParse.includes('```')) {
        const isPureCode = (textToParse.startsWith('def ') || textToParse.startsWith('import ') || textToParse.startsWith('#include ') || textToParse.startsWith('public class ') || textToParse.startsWith('class ')) && !textToParse.includes('?') && !textToParse.toLowerCase().startsWith('buatkan') && !textToParse.toLowerCase().startsWith('jelaskan');
        if (isPureCode) {
          textToParse = '```python\n' + textToParse + '\n```';
        }
      }

      let html = '';
      if (typeof marked !== 'undefined' && marked.parse) {
        try {
          const renderer = new marked.Renderer();
          renderer.code = function(arg1, arg2) {
            let codeText = (typeof arg1 === 'object' && arg1 !== null) ? (arg1.text || '') : (typeof arg1 === 'string' ? arg1 : '');
            let langName = (typeof arg1 === 'object' && arg1 !== null) ? (arg1.lang || 'python') : (typeof arg2 === 'string' && arg2 ? arg2 : 'python');

            let highlightedCode = '';
            if (typeof hljs !== 'undefined') {
              try {
                if (langName && hljs.getLanguage(langName)) {
                  highlightedCode = hljs.highlight(codeText.trim(), { language: langName }).value;
                } else {
                  highlightedCode = hljs.highlightAuto(codeText.trim()).value;
                }
              } catch (eHljs) {
                highlightedCode = escapeHtml(codeText.trim());
              }
            } else {
              highlightedCode = escapeHtml(codeText.trim());
            }

            return `
              <div class="code-block-container not-prose">
                <div class="code-block-header">
                  <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1.5">
                      <span class="w-2.5 h-2.5 rounded-full bg-rose-500/80 inline-block"></span>
                      <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80 inline-block"></span>
                      <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80 inline-block"></span>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-wider bg-teal-500/10 text-teal-400 border border-teal-500/20 uppercase font-mono">${escapeHtml(langName)}</span>
                  </div>
                  <button type="button" class="copy-code-btn">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <span>Copy</span>
                  </button>
                </div>
                <pre class="code-block-body"><code class="hljs language-${escapeHtml(langName)}">${highlightedCode}</code></pre>
              </div>
            `;
          };
          html = marked.parse(textToParse, { renderer: renderer, breaks: true, gfm: true });
        } catch (eMarked) {
          html = escapeHtml(textToParse).replace(/\n/g, '<br/>');
        }
      } else {
        html = escapeHtml(textToParse).replace(/\n/g, '<br/>');
      }

      if (typeof DOMPurify !== 'undefined' && DOMPurify.sanitize) {
        html = DOMPurify.sanitize(html, {
          ADD_TAGS: ['button', 'code', 'pre', 'svg', 'path', 'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'p', 'br', 'hr', 'div', 'span'],
          ADD_ATTR: ['class', 'style', 'viewbox', 'fill', 'stroke', 'd', 'stroke-width', 'stroke-linecap', 'stroke-linejoin']
        });
      }

      return html;
    }

    // Real-Time Prompt Length & Character Validation
    function validatePromptInput() {
      if (!chatInput || !sendBtn) return;
      const len = chatInput.value.trim().length;

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

      const isValid = (len >= 10 && len <= 2000);
      if (!state.inCooldown) {
        sendBtn.disabled = !isValid;
        sendBtn.classList.toggle('opacity-50', !isValid);
      }
    }

    if (chatInput) {
      chatInput.addEventListener('input', validatePromptInput);
      chatInput.addEventListener('keyup', validatePromptInput);
    }

    // Cooldown Rate-Limit Timer (60s)
    function startCooldown(seconds = 60) {
      state.inCooldown = true;
      let remaining = Math.max(1, Math.floor(seconds));

      if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.classList.add('opacity-50');
      }
      if (chatInput) {
        chatInput.disabled = true;
      }

      if (rateLimitNotice) rateLimitNotice.classList.remove('hidden');
      if (rateLimitTimerBadge) rateLimitTimerBadge.textContent = `${remaining}s`;

      if (cooldownInterval) clearInterval(cooldownInterval);

      cooldownInterval = setInterval(() => {
        remaining -= 1;
        if (rateLimitTimerBadge) rateLimitTimerBadge.textContent = `${remaining}s`;

        if (remaining <= 0) {
          clearInterval(cooldownInterval);
          cooldownInterval = null;
          state.inCooldown = false;
          if (rateLimitNotice) rateLimitNotice.classList.add('hidden');
          if (chatInput) {
            chatInput.disabled = false;
            chatInput.focus();
          }
          validatePromptInput();
        }
      }, 1000);
    }

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
        const res = await fetch(`${FASTAPI_URL}/api/user/query-quota`, {
          headers: { 'X-User-ID': SSO_USER_ID }
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
          badgeEl.className = "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 border border-rose-200 text-[11px] font-semibold text-rose-800 shadow-2xs cursor-pointer hover:bg-rose-100 transition";
          badgeEl.innerHTML = `<span class="text-rose-600">⚠️</span><span>Set Gemini API Key</span>`;
          badgeEl.onclick = () => openApiKeyModal(true);
        } else if (remaining < 50) {
          badgeEl.className = "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 border border-rose-200 text-[11px] font-semibold text-rose-800 shadow-2xs cursor-pointer hover:bg-rose-100 transition";
          badgeEl.onclick = showTermsModal;
        } else if (remaining < 300) {
          badgeEl.className = "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-[11px] font-semibold text-amber-800 shadow-2xs cursor-pointer hover:bg-amber-100 transition";
          badgeEl.onclick = showTermsModal;
        } else {
          badgeEl.className = "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-teal-50/90 border border-teal-200/80 text-[11px] font-semibold text-teal-800 shadow-2xs cursor-pointer hover:bg-teal-100 transition";
          badgeEl.onclick = showTermsModal;
        }
      }
    }

    // API Key Management Modal (SweetAlert2)
    async function openApiKeyModal(isFirstTime = false) {
      let currentMasked = '';
      try {
        const res = await fetch(`${FASTAPI_URL}/api/user/api-key`, {
          headers: { 'X-User-ID': SSO_USER_ID }
        });
        if (res.ok) {
          const info = await res.json();
          if (info.has_key && info.masked_key) {
            currentMasked = info.masked_key;
            state.hasApiKey = true;
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

          const postRes = await fetch(`${FASTAPI_URL}/api/user/api-key`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-User-ID': SSO_USER_ID
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
          state.hasApiKey = true;
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
        const res = await fetch(`${FASTAPI_URL}/api/user/api-key`, {
          headers: { 'X-User-ID': SSO_USER_ID }
        });
        if (res.ok) {
          const data = await res.json();
          state.hasApiKey = data.has_key;
          if (data.has_key) {
            if (apiKeyBtnText) apiKeyBtnText.textContent = 'API Key (Aktif)';
          } else {
            if (apiKeyBtnText) apiKeyBtnText.textContent = 'Set API Key';
            // Prompt user on first use
            setTimeout(() => { openApiKeyModal(true); }, 500);
          }
        }
      } catch (e) {
        console.debug('Error checking user API key status:', e);
      }
    }

    async function sendMessage(e) {
      if (e) e.preventDefault();
      const prompt = chatInput.value.trim();
      
      if (!prompt || prompt.length < 10) {
        Swal.fire({
          icon: 'warning',
          title: 'Prompt Terlalu Pendek',
          text: 'Harap masukkan pertanyaan pemrograman minimal 10 karakter.',
          confirmButtonColor: '#00A0A5'
        });
        return;
      }
      if (prompt.length > 2000) {
        Swal.fire({
          icon: 'warning',
          title: 'Prompt Terlalu Panjang',
          text: `Panjang prompt saat ini ${prompt.length} karakter. Maksimal yang diizinkan adalah 2000 karakter.`,
          confirmButtonColor: '#00A0A5'
        });
        return;
      }

      if (state.inCooldown) {
        return;
      }

      state.messages.push({ sender: 'user', text: prompt });
      persistMessages();
      renderMessages();

      chatInput.value = '';
      validatePromptInput();
      sendBtn.disabled = true;
      typing.classList.remove('hidden');

      const lang = languageSelect.value;
      const mode = responseModeSelect.value;

      try {
        const response = await fetch(`${FASTAPI_URL}/api/generate-code`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-User-ID': SSO_USER_ID
          },
          body: JSON.stringify({
            prompt: prompt,
            course_id: CURRENT_COURSE_ID || null,
            assessment_id: CURRENT_ASSESSMENT_ID || null,
            response_mode: mode === 'code' ? 'Code (only)' : (mode === 'summary' ? 'Explanation (only)' : 'Standard'),
            language: lang || null
          })
        });

        if (response.status === 429) {
          const errJson = await response.json().catch(() => ({}));
          const retryAfter = Number(response.headers.get('Retry-After') || 60);
          startCooldown(retryAfter);
          throw new Error(errJson.detail || `Rate limit tercapai. Silakan tunggu ${retryAfter} detik.`);
        }

        if (response.status === 400) {
          const errJson = await response.json().catch(() => ({}));
          if (errJson.detail && errJson.detail.includes('API Key')) {
            openApiKeyModal(true);
          }
          throw new Error(errJson.detail || 'Permintaan tidak valid.');
        }

        if (!response.ok) {
          throw new Error(`HTTP Error ${response.status}`);
        }

        const data = await response.json();
        let replyText = data.code || data.text || data.message || 'Solusi berhasil diproses.';
        
        const isRetrieval = data.is_retrieval || Number(data.request_tokens_used || 0) === 0;
        let metaInfo = isRetrieval 
          ? 'Vector Semantic Cache Hit (0 Tokens / Free Tier)' 
          : `Adaptive Router Gemini (Personal Key)`;

        state.messages.push({ sender: 'bot', text: replyText, meta: metaInfo });
        persistMessages();

        // Dynamically update query quota badge if returned
        if (data.query_quota) {
          updateQueryQuotaUI(data.query_quota);
        } else {
          fetchQueryQuota();
        }

        // Start 60-second cooldown rate limit after successful generation
        startCooldown(data.cooldown_seconds || 60);

      } catch (err) {
        state.messages.push({
          sender: 'bot',
          text: `Gagal memproses respon: ${err.message}`,
          meta: 'Error'
        });
        persistMessages();
      } finally {
        typing.classList.add('hidden');
        renderMessages();
      }
    }

    // Suggestions & Quick Prompt Templates handler
    document.addEventListener('click', function(e) {
      const suggestBtn = e.target.closest('[data-suggest]');
      if (suggestBtn) {
        chatInput.value = suggestBtn.getAttribute('data-suggest');
        validatePromptInput();
        chatInput.focus();
      }

      const templateBtn = e.target.closest('[data-fill]');
      if (templateBtn) {
        chatInput.value = templateBtn.getAttribute('data-fill');
        validatePromptInput();
        chatInput.focus();
      }
    });

    // Enter to Send, Shift+Enter for newline (Gemini style)
    if (chatInput) {
      chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendMessage(e);
        }
      });
    }

    // Action Buttons
    if (newChatBtn) {
      newChatBtn.addEventListener('click', function() {
        state.messages = [
          {
            sender: 'bot',
            text: 'Sesi chat baru telah dimulai. Silakan ajukan pertanyaan pemrograman atau diskusikan algoritma Anda.',
            meta: 'New session'
          }
        ];
        persistMessages();
        renderMessages();
      });
    }

    if (clearChatBtn) {
      clearChatBtn.addEventListener('click', function() {
        localStorage.removeItem(STORAGE_KEY);
        state.messages = [];
        renderMessages();
      });
    }

    function showPromptingTipsModal() {
      Swal.fire({
        title: 'Panduan Prompting S-SPARC AI',
        html: `
          <div class="text-left text-xs leading-relaxed space-y-3 text-slate-700">
            <p>Ikuti panduan berikut agar asisten memberikan kode yang presisi, efisien, dan hemat waktu:</p>
            <ol class="list-decimal pl-4 space-y-2">
              <li><strong>Gunakan Mode Code (only):</strong> Output langsung berupa kode tanpa teks pembuka, menghemat token hingga 60%.</li>
              <li><strong>Sertakan Detail Parameter & Tipe Data:</strong> Tuliskan nama fungsi, tipe input/output, dan batas waktu eksekusi yang diharapkan.</li>
              <li><strong>Patuhi Batas Karakter:</strong> Minimal 10 karakter dan maksimal 2000 karakter per prompt.</li>
              <li><strong>Rate Limit 1 Menit:</strong> Terdapat jeda 60 detik antar pengiriman pesan untuk menjaga kestabilan sistem dan mendorong pembelajaran mandiri.</li>
            </ol>
          </div>
        `,
        confirmButtonText: 'Tutup Panduan',
        confirmButtonColor: '#00A0A5',
        width: '520px'
      });
    }

    if (viewPromptTipsBtn) {
      viewPromptTipsBtn.addEventListener('click', showPromptingTipsModal);
    }

    $(document).ready(function() {
      checkUserApiKey();
      fetchQueryQuota();
      validatePromptInput();
    });

    // Load messages on init
    loadMessages();
  </script>
</body>
</html>


