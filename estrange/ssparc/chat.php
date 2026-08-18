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
      <div class="flex items-center gap-2">
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

        <!-- Chat Form Input (Gemini-Style Unified Box) -->
        <form id="chat-form" class="mt-5" onsubmit="sendMessage(event)">
          <div class="rounded-3xl border border-slate-300/90 bg-white p-4 shadow-sm focus-within:border-[#00A0A5] focus-within:ring-2 focus-within:ring-[#00A0A5]/20 transition flex flex-col justify-between">
            
            <!-- Textarea Area -->
            <label for="chat-input" class="sr-only">Write a message</label>
            <textarea id="chat-input" rows="3" class="w-full min-h-[5.5rem] max-h-56 resize-none overflow-y-auto bg-transparent px-3 pt-1.5 pb-2 text-sm text-slate-900 outline-none placeholder:text-slate-400 leading-relaxed font-sans" placeholder="Tuliskan pertanyaan pemrograman, analisis error, atau paste kode tugas Anda di sini..." required></textarea>

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

              <!-- Right Controls: Status & Circular Send Button (Gemini-style) -->
              <div class="flex items-center gap-3">
                <span class="text-[11px] font-mono text-slate-400 hidden sm:inline-block">
                  Shift+Enter for newline
                </span>
                <button id="send-btn" type="submit" class="w-10 h-10 flex items-center justify-center rounded-full bg-[#00A0A5] text-white hover:bg-[#008589] transition shadow-sm hover:scale-105 active:scale-95 disabled:opacity-50" title="Send Message">
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

        <!-- Token Usage & Threshold Card -->
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <div class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
              <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
              <span>S-SPARC Token Policy</span>
            </div>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200 uppercase">
              Live Quota
            </span>
          </div>

          <!-- Metric Rows -->
          <div class="text-xs text-slate-700 flex flex-col gap-2 bg-slate-50 p-3 rounded-xl border border-slate-100">
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Course Threshold</span>
              <span class="font-bold text-slate-900 font-mono"><span id="token-threshold">2,500</span> tokens</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Tokens Consumed</span>
              <span class="font-bold text-slate-900 font-mono"><span id="token-used">0</span> tokens</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-500 font-medium">Efficiency Score</span>
              <span class="font-bold text-emerald-600 font-mono"><span id="token-points">100.0</span> pts</span>
            </div>
          </div>

          <!-- Remaining Queries Estimate Box -->
          <div class="rounded-xl border border-teal-100 bg-gradient-to-br from-teal-50/90 to-emerald-50/70 p-3 text-xs space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-teal-900 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Estimated Remaining Queries
              </span>
              <span id="remaining-cloud-badge" class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-teal-600 text-white font-mono shadow-2xs">
                <span id="remaining-queries-count">15</span> Queries
              </span>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
              <div class="bg-white/80 rounded-lg p-2 border border-teal-100/80">
                <div class="text-slate-500 text-[10px]">Cloud Generative</div>
                <div class="font-bold text-slate-800 font-mono mt-0.5" id="cloud-queries-avail">~ 15x AI Gen</div>
              </div>
              <div class="bg-white/80 rounded-lg p-2 border border-teal-100/80">
                <div class="text-slate-500 text-[10px]">Vector Cache Hit</div>
                <div class="font-bold text-emerald-600 font-mono mt-0.5">&infin; Unlimited</div>
              </div>
            </div>
            
            <div class="text-[10.5px] text-slate-500 flex items-center justify-between pt-0.5 border-t border-teal-100/60">
              <span>Cost / Gen Query:</span>
              <span class="font-semibold text-slate-700">10 Pts or ~150 Tok</span>
            </div>
          </div>

          <p class="text-[11px] text-slate-500 leading-relaxed pt-0.5">
            Only generative inference consumes quota. <strong class="text-emerald-600">Vector Retrieval is 100% FREE</strong> (&ge;90% similarity) and preserves all your quota.
          </p>
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
    const tokenThresholdEl = document.getElementById('token-threshold');
    const tokenUsedEl = document.getElementById('token-used');
    const tokenPointsEl = document.getElementById('token-points');
    const viewPromptTipsBtn = document.getElementById('view-prompt-tips');
    const sendBtn = document.getElementById('send-btn');

    const STORAGE_KEY = 'ssparc_chat_' + SSO_USER_ID + '_' + CURRENT_ASSESSMENT_ID;
    const state = { messages: [] };

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

    function copyToClipboard(btn, text) {
      navigator.clipboard.writeText(text).then(() => {
        const originalText = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.add('bg-slate-100');
        setTimeout(() => {
          btn.textContent = originalText;
          btn.classList.remove('bg-slate-100');
        }, 2000);
      });
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
        textContent.innerHTML = formatMessageContent(msg.text);
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

    function formatMessageContent(rawText) {
      if (!rawText) return '';

      let textToParse = rawText.trim();
      
      // Auto-wrap raw code into markdown fence if not already wrapped
      if (!textToParse.includes('```') && (textToParse.includes('def ') || textToParse.includes('import ') || textToParse.includes('class ') || textToParse.includes('plt.') || textToParse.includes('print('))) {
        textToParse = '```python\n' + textToParse + '\n```';
      }

      let html = '';
      if (typeof marked !== 'undefined' && marked.parse) {
        try {
          const renderer = new marked.Renderer();
          renderer.code = function(arg1, arg2) {
            let codeText = '';
            let langName = 'python';

            if (typeof arg1 === 'object' && arg1 !== null) {
              codeText = arg1.text || '';
              langName = arg1.lang || 'python';
            } else {
              codeText = typeof arg1 === 'string' ? arg1 : '';
              langName = typeof arg2 === 'string' && arg2 ? arg2 : 'python';
            }

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
          console.warn('Marked parse error, falling back:', eMarked);
          html = escapeHtml(textToParse).replace(/\n/g, '<br/>');
        }
      } else {
        html = escapeHtml(textToParse)
          .replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>')
          .replace(/\*(.*?)\*/g, '<em class="italic">$1</em>')
          .replace(/`([^`]+)`/g, '<code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono border border-slate-200 text-teal-800">$1</code>')
          .replace(/\n/g, '<br/>');
      }

      if (typeof DOMPurify !== 'undefined' && DOMPurify.sanitize) {
        html = DOMPurify.sanitize(html, {
          ADD_TAGS: ['button', 'code', 'pre', 'svg', 'path', 'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'p', 'br', 'hr', 'div', 'span'],
          ADD_ATTR: ['class', 'style', 'viewbox', 'fill', 'stroke', 'd', 'stroke-width', 'stroke-linecap', 'stroke-linejoin']
        });
      }

      return html;
    }

    // Delegated Global Clipboard Copy Listener
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.copy-code-btn');
      if (!btn) return;

      const container = btn.closest('.code-block-container');
      if (!container) return;

      const codeEl = container.querySelector('code');
      if (!codeEl) return;

      const textToCopy = codeEl.innerText || codeEl.textContent || '';

      navigator.clipboard.writeText(textToCopy.trim()).then(() => {
        btn.classList.add('copied');
        btn.innerHTML = `
          <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          <span class="text-emerald-400 font-semibold">Copied!</span>
        `;
        setTimeout(() => {
          btn.classList.remove('copied');
          btn.innerHTML = `
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <span>Copy</span>
          `;
        }, 2000);
      }).catch(err => {
        console.error('Clipboard copy failed:', err);
      });
    });

    async function sendMessage(e) {
      if (e) e.preventDefault();
      const prompt = chatInput.value.trim();
      if (!prompt) return;

      state.messages.push({ sender: 'user', text: prompt });
      persistMessages();
      renderMessages();

      chatInput.value = '';
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

        if (!response.ok) {
          throw new Error(`HTTP Error ${response.status}`);
        }

        const data = await response.json();
        
        let replyText = data.code || data.text || data.message || 'Your request has been processed.';
        
        // Locked API contract disambiguation: request_tokens_used (0 on retrieval) vs session_cumulative_tokens
        const requestTokens = Number(data.request_tokens_used !== undefined ? data.request_tokens_used : (data.is_retrieval ? 0 : (data.total_tokens || 0)));
        const isRetrieval = data.is_retrieval || requestTokens === 0;
        
        let metaInfo = isRetrieval 
          ? 'Vector Semantic Cache Hit (0 Tokens / Preserved Quota)' 
          : `Generative GPT Model (${requestTokens.toLocaleString()} tokens used)`;

        if (data.session_cumulative_tokens !== undefined) {
          metaInfo += ` • Cumulative: ${Number(data.session_cumulative_tokens).toLocaleString()} tok`;
        }

        state.messages.push({ sender: 'bot', text: replyText, meta: metaInfo });
        persistMessages();

        if (data.gamification) {
          updateTokenPolicyMetrics(data.gamification);
        }
      } catch (err) {
        state.messages.push({
          sender: 'bot',
          text: `Failed to connect to AI assistant backend: ${err.message}`,
          meta: 'Error'
        });
        persistMessages();
      } finally {
        typing.classList.add('hidden');
        sendBtn.disabled = false;
        renderMessages();
      }
    }

    function updateTokenPolicyMetrics(gamification) {
      if (!gamification) return;

      const threshold = Number(gamification.token_threshold || 2500);
      const used = Number(gamification.gpt_tokens_used || gamification.used_tokens || 0);
      const points = Number(gamification.points || gamification.current_points || 100.0);
      const gamificationPoints = Number(gamification.gamification_points !== undefined ? gamification.gamification_points : 150.0);

      if (tokenThresholdEl) tokenThresholdEl.textContent = threshold.toLocaleString();
      if (tokenUsedEl) tokenUsedEl.textContent = used.toLocaleString();
      if (tokenPointsEl) tokenPointsEl.textContent = points.toFixed(1);

      // 1 Cloud Generative Query costs 10 gamification points
      const remainingCloudQueries = Math.max(0, Math.floor(gamificationPoints / 10));
      
      const remCountEl = document.getElementById('remaining-queries-count');
      const cloudAvailEl = document.getElementById('cloud-queries-avail');
      const remBadge = document.getElementById('remaining-cloud-badge');

      if (remCountEl) remCountEl.textContent = remainingCloudQueries;
      if (cloudAvailEl) cloudAvailEl.textContent = `~ ${remainingCloudQueries}x AI Gen`;

      if (remBadge) {
        if (remainingCloudQueries <= 2 && remainingCloudQueries > 0) {
          remBadge.className = "px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500 text-white font-mono shadow-2xs";
        } else if (remainingCloudQueries === 0) {
          remBadge.className = "px-2 py-0.5 rounded-md text-[11px] font-bold bg-rose-600 text-white font-mono shadow-2xs";
        } else {
          remBadge.className = "px-2 py-0.5 rounded-md text-[11px] font-bold bg-teal-600 text-white font-mono shadow-2xs";
        }
      }
    }

    async function fetchLiveTokenPolicy() {
      try {
        const res = await fetch(`${FASTAPI_URL}/api/gamification?assessment_id=${CURRENT_ASSESSMENT_ID}`, {
          headers: { 'X-User-ID': SSO_USER_ID }
        });
        if (res.ok) {
          const json = await res.json();
          if (json.gamification) {
            updateTokenPolicyMetrics(json.gamification);
          }
        }
      } catch (e) {
        console.debug('Token policy fetch fallback:', e);
      }
    }

    // Suggestions & Quick Prompt Templates handler
    document.addEventListener('click', function(e) {
      const suggestBtn = e.target.closest('[data-suggest]');
      if (suggestBtn) {
        chatInput.value = suggestBtn.getAttribute('data-suggest');
        chatInput.focus();
      }

      const templateBtn = e.target.closest('[data-fill]');
      if (templateBtn) {
        chatInput.value = templateBtn.getAttribute('data-fill');
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
            text: 'New chat session started for this assessment. Please ask your programming question.',
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
        title: 'S-SPARC AI Prompting Guide',
        html: `
          <div class="text-left text-sm font-bold space-y-3 text-slate-700">
            <p>To maximize code quality and preserve your token efficiency score, follow these best practices:</p>
            <ol class="list-decimal pl-4 space-y-1.5">
              <li><strong>Use Code (only) Mode:</strong> Outputs clean code directly without preamble, saving up to 60% tokens.</li>
              <li><strong>Be Explicit on Functions & Types:</strong> Specify parameter names, expected return types, and constraints.</li>
              <li><strong>Leverage Vector Retrieval (FREE):</strong> Frequently asked algorithmic questions match semantic cache at &ge;90% similarity for 0 tokens.</li>
            </ol>
          </div>
        `,
        confirmButtonText: 'Close Guide',
        confirmButtonColor: '#0f172a',
        width: '560px'
      });
    }

    if (viewPromptTipsBtn) {
      viewPromptTipsBtn.addEventListener('click', showPromptingTipsModal);
    }

    $(document).ready(function() {
      fetchLiveTokenPolicy();
    });

    // Load messages on init
    loadMessages();
  </script>
</body>
</html>

