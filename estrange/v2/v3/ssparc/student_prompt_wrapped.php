<?php
require_once(__DIR__ . '/_sso_bridge.php');

$userId = $_SESSION['user_id'] ?? 'student_demo';
$assessmentId = $_GET['assessment_id'] ?? $_GET['id'] ?? '1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>S-SPARC Prompt Wrapped - Kilas Balik AI Literacy</title>
  <link rel="icon" href="../strange_html_layout_additional_files/icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
  <style>
    body {
      font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
      background-color: #0b0f17;
      color: #f8fafc;
      overflow-x: hidden;
      user-select: none;
    }
    .font-mono-code {
      font-family: 'JetBrains Mono', monospace;
    }
    .story-bg {
      background: radial-gradient(circle at 50% 20%, rgba(16, 185, 129, 0.15) 0%, rgba(11, 15, 23, 0.95) 75%),
                  radial-gradient(circle at 80% 80%, rgba(59, 130, 246, 0.12) 0%, rgba(11, 15, 23, 1) 70%);
    }
    .glass-card {
      background: rgba(17, 24, 39, 0.75);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    }
    .progress-bar-segment {
      height: 3.5px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 9999px;
      overflow: hidden;
      flex: 1;
    }
    .progress-bar-fill {
      height: 100%;
      background: #ffffff;
      width: 0%;
      border-radius: 9999px;
      transition: width 0.05s linear;
    }
    .progress-bar-fill.completed {
      width: 100% !important;
    }
    .slide-content {
      animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(12px) scale(0.98); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .nav-btn {
      cursor: pointer;
      z-index: 20;
    }
  </style>
</head>
<body class="min-h-screen story-bg flex flex-col items-center justify-center p-3 sm:p-6">

  <!-- Container Spotify-Wrapped Story -->
  <div id="wrapped-container" class="relative w-full max-w-lg aspect-[9/16] sm:aspect-[9/15] max-h-[860px] glass-card rounded-3xl flex flex-col justify-between overflow-hidden shadow-2xl p-6 sm:p-8">
    
    <!-- Top Progress Bar & Header Controls -->
    <div class="relative z-30 space-y-3">
      <!-- 6-Segment Progress Bars -->
      <div class="flex items-center gap-1.5 w-full">
        <div class="progress-bar-segment"><div id="fill-0" class="progress-bar-fill"></div></div>
        <div class="progress-bar-segment"><div id="fill-1" class="progress-bar-fill"></div></div>
        <div class="progress-bar-segment"><div id="fill-2" class="progress-bar-fill"></div></div>
        <div class="progress-bar-segment"><div id="fill-3" class="progress-bar-fill"></div></div>
        <div class="progress-bar-segment"><div id="fill-4" class="progress-bar-fill"></div></div>
        <div class="progress-bar-segment"><div id="fill-5" class="progress-bar-fill"></div></div>
      </div>

      <!-- Story Subheader (Assessment Title & Exit) -->
      <div class="flex items-center justify-between text-xs text-slate-400">
        <div class="flex items-center gap-2">
          <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/30">
            S-SPARC WRAPPED
          </span>
          <span id="header-assessment-title" class="truncate max-w-[200px] font-medium text-slate-200">Assessment #<?= htmlspecialchars($assessmentId) ?></span>
        </div>
        <a href="student_analytics.php" class="hover:text-white transition p-1 rounded-full bg-white/5 hover:bg-white/10" title="Keluar">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </a>
      </div>
    </div>

    <!-- Main Dynamic Slide Area -->
    <div id="slide-viewport" class="relative z-20 flex-1 flex flex-col justify-center my-4 overflow-y-auto">
      <!-- Loading State -->
      <div id="loading-spinner" class="text-center space-y-4 my-auto">
        <div class="w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
        <p class="text-slate-400 text-sm font-medium">Menyusun kilas balik AI Literacy Anda...</p>
      </div>
    </div>

    <!-- Bottom Navigation / Instructions -->
    <div class="relative z-30 flex items-center justify-between text-[11px] text-slate-400 border-t border-white/10 pt-3">
      <span class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        <span id="slide-indicator">Slide 1 dari 6</span>
      </span>
      <span class="hidden sm:inline text-slate-400">Tap kiri/kanan untuk navigasi</span>
      <div class="flex items-center gap-2">
        <button id="btn-prev" class="p-1.5 rounded-lg bg-white/5 hover:bg-white/15 text-white transition" title="Sebelumnya">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <button id="btn-next" class="p-1.5 rounded-lg bg-white/5 hover:bg-white/15 text-white transition" title="Berikutnya">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>
    </div>

    <!-- Invisible Touch / Click Overlay for Tap Navigation -->
    <div class="absolute inset-0 z-10 flex">
      <div id="tap-left" class="w-1/2 h-full cursor-pointer"></div>
      <div id="tap-right" class="w-1/2 h-full cursor-pointer"></div>
    </div>

  </div>

  <script>
    const ASSESSMENT_ID = "<?= htmlspecialchars($assessmentId) ?>";
    const USER_ID = "<?= htmlspecialchars($userId) ?>";
    
    let wrappedData = null;
    let currentSlide = 0;
    const TOTAL_SLIDES = 6;
    const SLIDE_DURATION = 6000; // 6 detik per slide
    let slideTimer = null;
    let progressInterval = null;
    let progressStartTime = 0;
    let elapsedTimePaused = 0;
    let isPaused = false;

    // Fetch Wrapped Data from API
    async function loadWrappedData() {
      try {
        const url = `api_proxy.php?endpoint=/api/domain/assessments/${ASSESSMENT_ID}/wrapped&user_id=${USER_ID}`;
        const res = await fetch(url);
        const data = await res.json();

        if (data.status === 'locked') {
          renderLockedState(data);
          return;
        }

        if (data.status === 'success') {
          wrappedData = data;
          document.getElementById('header-assessment-title').innerText = data.assessment_title || `Assessment #${ASSESSMENT_ID}`;
          startStory();
        } else {
          showError(data.message || 'Gagal memuat data kilas balik.');
        }
      } catch (err) {
        console.error("Fetch wrapped error:", err);
        showError('Koneksi ke backend API terputus. Silakan coba kembali.');
      }
    }

    function renderLockedState(data) {
      document.getElementById('loading-spinner').remove();
      document.getElementById('slide-viewport').innerHTML = `
        <div class="text-center space-y-5 my-auto px-4 slide-content">
          <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center mx-auto text-amber-400 text-2xl font-bold">
            🔒
          </div>
          <div class="space-y-2">
            <h2 class="text-xl font-bold text-white tracking-tight">S-SPARC Wrapped Belum Terbuka</h2>
            <p class="text-xs text-slate-300 leading-relaxed max-w-sm mx-auto">
              ${data.message || 'Wrapped otomatis dapat diakses setelah waktu pengerjaan tugas resmi berakhir (expired) untuk menjaga integritas kelas.'}
            </p>
          </div>
          <div class="p-3 rounded-xl bg-white/5 border border-white/10 text-xs font-mono text-slate-400 inline-block">
            Batas Waktu: ${data.due_date || 'Sedang Berlangsung'}
          </div>
          <div>
            <a href="student_analytics.php" class="inline-block mt-2 px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white transition">
              Kembali ke Analitik
            </a>
          </div>
        </div>
      `;
    }

    function showError(msg) {
      document.getElementById('loading-spinner').remove();
      document.getElementById('slide-viewport').innerHTML = `
        <div class="text-center space-y-4 my-auto px-4">
          <p class="text-rose-400 text-sm font-semibold">${msg}</p>
          <a href="student_analytics.php" class="inline-block px-4 py-2 rounded-xl bg-slate-800 text-xs text-white">Kembali</a>
        </div>
      `;
    }

    function startStory() {
      renderSlide(0);
    }

    function renderSlide(index) {
      currentSlide = index;
      document.getElementById('slide-indicator').innerText = `Slide ${index + 1} dari ${TOTAL_SLIDES}`;
      
      // Update Progress Bar Fills
      for (let i = 0; i < TOTAL_SLIDES; i++) {
        const fill = document.getElementById(`fill-${i}`);
        if (i < index) {
          fill.className = 'progress-bar-fill completed';
        } else if (i === index) {
          fill.className = 'progress-bar-fill';
          fill.style.width = '0%';
        } else {
          fill.className = 'progress-bar-fill';
          fill.style.width = '0%';
        }
      }

      const viewport = document.getElementById('slide-viewport');
      viewport.innerHTML = '';

      switch (index) {
        case 0:
          renderSlide1(viewport);
          break;
        case 1:
          renderSlide2(viewport);
          break;
        case 2:
          renderSlide3(viewport);
          break;
        case 3:
          renderSlide4(viewport);
          break;
        case 4:
          renderSlide5(viewport);
          break;
        case 5:
          renderSlide6(viewport);
          break;
      }

      resetTimer();
    }

    // Slide 1: The Assessment Snapshot
    function renderSlide1(container) {
      const s = wrappedData.summary;
      container.innerHTML = `
        <div class="space-y-6 my-auto text-center slide-content">
          <div class="space-y-1">
            <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Assessment Milestone</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">${wrappedData.assessment_title}</h1>
            <p class="text-xs text-slate-400">${wrappedData.course_name}</p>
          </div>

          <div class="p-6 rounded-2xl bg-gradient-to-br from-emerald-500/10 to-teal-500/5 border border-emerald-500/30 text-center space-y-3">
            <span class="text-xs font-semibold uppercase text-emerald-300 block">Predikat AI Literacy</span>
            <div class="text-3xl font-black text-white tracking-tight">${s.literacy_tier}</div>
            <div class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-emerald-500 text-slate-950">
              Skor Mutu: ${s.overall_score}%
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 text-left">
            <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
              <span class="text-[11px] text-slate-400 block">Total Interaksi Prompt</span>
              <span class="text-lg font-bold text-white mt-0.5 block">${s.total_prompts} Pertanyaan</span>
            </div>
            <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
              <span class="text-[11px] text-slate-400 block">0-Token Semantic Hits</span>
              <span class="text-lg font-bold text-teal-300 mt-0.5 block">${s.fast_path_hits}x Cache Hit</span>
            </div>
          </div>
        </div>
      `;
    }

    // Slide 2: Prompt Persona Card
    function renderSlide2(container) {
      const p = wrappedData.persona;
      container.innerHTML = `
        <div class="space-y-6 my-auto text-center slide-content">
          <div class="space-y-1">
            <span class="text-xs font-semibold text-teal-400 uppercase tracking-wider">Profil Gaya Berpikir</span>
            <h2 class="text-xl font-bold text-white">Persona Prompting Anda</h2>
          </div>

          <div class="p-6 rounded-2xl bg-gradient-to-b from-slate-800/80 to-slate-900 border border-teal-500/40 text-center space-y-4 shadow-xl">
            <div class="w-14 h-14 rounded-2xl bg-teal-500/20 border border-teal-400/40 flex items-center justify-center mx-auto text-teal-300 text-lg font-bold">
              AI
            </div>
            <div>
              <h3 class="text-2xl font-black text-white tracking-tight">${p.title}</h3>
              <p class="text-xs font-medium text-teal-300 mt-1">${p.tagline}</p>
            </div>
            <p class="text-xs text-slate-300 leading-relaxed">
              ${p.description}
            </p>
            <div class="p-2.5 rounded-xl bg-white/5 border border-white/10 text-xs font-mono text-emerald-300 font-semibold">
              ${p.power_stat}
            </div>
          </div>
        </div>
      `;
    }

    // Slide 3: Radar C-I-O-E & Entropy
    function renderSlide3(container) {
      const d = wrappedData.dimensions;
      container.innerHTML = `
        <div class="space-y-4 my-auto text-center slide-content">
          <div class="space-y-1">
            <span class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">Metrik Dekomposisi</span>
            <h2 class="text-xl font-bold text-white">Radar Penguasaan C-I-O-E</h2>
          </div>

          <div class="relative w-full max-w-[280px] aspect-square mx-auto">
            <canvas id="radarCanvas"></canvas>
          </div>

          <div class="grid grid-cols-2 gap-2 text-left text-xs">
            <div class="p-2.5 rounded-xl bg-white/5 border border-white/10">
              <span class="text-slate-400 block text-[10px]">C-I-O-E Mastery</span>
              <span class="font-bold text-white">${d.cioe_completeness}%</span>
            </div>
            <div class="p-2.5 rounded-xl bg-white/5 border border-white/10">
              <span class="text-slate-400 block text-[10px]">Shannon Entropy H(X)</span>
              <span class="font-bold text-emerald-400">${d.shannon_entropy} / 1.0</span>
            </div>
          </div>
        </div>
      `;

      // Render Radar Chart with Chart.js
      setTimeout(() => {
        const ctx = document.getElementById('radarCanvas')?.getContext('2d');
        if (!ctx) return;
        new Chart(ctx, {
          type: 'radar',
          data: {
            labels: ['Context', 'Input', 'Output', 'Error', 'Vocabulary'],
            datasets: [{
              label: 'Mastery (%)',
              data: [
                d.radar.Context || 0,
                d.radar.Input || 0,
                d.radar.Output || 0,
                d.radar.Error || 0,
                d.radar.Vocabulary || 0
              ],
              backgroundColor: 'rgba(16, 185, 129, 0.25)',
              borderColor: '#10B981',
              pointBackgroundColor: '#34D399',
              borderWidth: 2
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              r: {
                angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
                grid: { color: 'rgba(255, 255, 255, 0.1)' },
                pointLabels: { color: '#94a3b8', font: { size: 10 } },
                ticks: { display: false, max: 100, min: 0 }
              }
            },
            plugins: { legend: { display: false } }
          }
        });
      }, 50);
    }

    // Slide 4: AI Critic Room (Best vs Polish)
    function renderSlide4(container) {
      const cr = wrappedData.critic_room;
      container.innerHTML = `
        <div class="space-y-4 my-auto slide-content text-left">
          <div class="text-center space-y-1">
            <span class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Ruang Evaluasi</span>
            <h2 class="text-xl font-bold text-white">Ulasan Kritikus AI</h2>
          </div>

          <!-- Best Prompt -->
          <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-emerald-400">Prompt Paling Efektif</span>
              <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded-full font-mono">${cr.best_prompt.score}% Mutu</span>
            </div>
            <p class="text-xs text-slate-200 line-clamp-2 font-mono-code bg-black/30 p-2 rounded-lg">
              "${cr.best_prompt.text}"
            </p>
            <p class="text-[11px] text-emerald-300/90">${cr.best_prompt.why_stellar}</p>
          </div>

          <!-- Needs Polish Prompt -->
          <div class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-rose-400">Rekomendasi Peningkatan</span>
              <span class="text-[10px] bg-rose-500/20 text-rose-300 px-2 py-0.5 rounded-full font-mono">${cr.needs_polish_prompt.score}% Mutu</span>
            </div>
            <p class="text-[11px] text-slate-300 leading-relaxed">
              ${cr.needs_polish_prompt.ai_critic_comment}
            </p>
            <div class="p-2 rounded-lg bg-black/40 border border-white/5 space-y-1">
              <span class="text-[10px] font-bold text-teal-300 block uppercase">Contoh Penulisan C-I-O-E Ideal:</span>
              <pre class="text-[10px] font-mono-code text-slate-200 whitespace-pre-wrap">${cr.needs_polish_prompt.suggested_rewrite}</pre>
            </div>
          </div>
        </div>
      `;
    }

    // Slide 5: BYOK Environmental Impact
    function renderSlide5(container) {
      const byok = wrappedData.byok_sustainability;
      container.innerHTML = `
        <div class="space-y-6 my-auto text-center slide-content">
          <div class="space-y-1">
            <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Jejak Komputasi Mandiri</span>
            <h2 class="text-xl font-bold text-white">Dampak Lingkungan BYOK</h2>
          </div>

          <div class="p-6 rounded-2xl bg-gradient-to-b from-teal-900/40 to-slate-900 border border-teal-500/30 text-center space-y-4 shadow-xl">
            <div class="space-y-1">
              <span class="text-3xl font-black text-emerald-300 tracking-tight">${byok.energy_wh} Wh</span>
              <span class="text-xs text-slate-400 block">Total Energi Listrik Terpakai</span>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2">
              <div class="p-3 rounded-xl bg-white/5 border border-white/10">
                <span class="text-[10px] text-slate-400 block">Estimasi Emisi Karbon</span>
                <span class="text-sm font-bold text-white mt-0.5 block">${byok.carbon_g} g CO2e</span>
              </div>
              <div class="p-3 rounded-xl bg-white/5 border border-white/10">
                <span class="text-[10px] text-slate-400 block">Jejak Air Pendingin</span>
                <span class="text-sm font-bold text-teal-300 mt-0.5 block">${byok.water_ml} mL</span>
              </div>
            </div>

            <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300 font-medium">
              Status: ${byok.rating}
            </div>
          </div>

          <p class="text-[11px] text-slate-400 leading-relaxed px-2">
            Dengan menggunakan API Key pribadi dan protokol dekomposisi terstruktur, Anda membantu mendistribusikan beban komputasi secara bertanggung jawab.
          </p>
        </div>
      `;
    }

    // Slide 6: Level-Up Checklist & Share
    function renderSlide6(container) {
      const actions = wrappedData.action_items || [];
      container.innerHTML = `
        <div class="space-y-5 my-auto text-center slide-content">
          <div class="space-y-1">
            <span class="text-xs font-semibold text-teal-400 uppercase tracking-wider">Langkah Lanjutan</span>
            <h2 class="text-xl font-bold text-white">Level-Up AI Literacy</h2>
          </div>

          <div class="space-y-2 text-left">
            ${actions.map(act => `
              <div class="p-3 rounded-xl bg-white/5 border border-white/10 flex items-start gap-2.5">
                <div class="w-1.5 h-1.5 rounded-full bg-emerald-400 mt-1.5 shrink-0"></div>
                <p class="text-xs text-slate-200 leading-relaxed">${act}</p>
              </div>
            `).join('')}
          </div>

          <div class="pt-2 space-y-2">
            <button id="btn-export-card" class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 font-bold text-xs text-slate-950 transition shadow-lg flex items-center justify-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              <span>Download Kartu Summary Wrapped</span>
            </button>
            <a href="student_analytics.php" class="block w-full py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-semibold text-slate-300 transition">
              Selesai & Lihat Semua Analitik
            </a>
          </div>
        </div>
      `;

      document.getElementById('btn-export-card')?.addEventListener('click', exportSummaryCard);
    }

    // Download/Share Card Generator via HTML5 Canvas
    function exportSummaryCard() {
      const card = document.getElementById('wrapped-container');
      const btn = document.getElementById('btn-export-card');
      if (btn) btn.innerText = "Mengunduh kartu...";

      html2canvas(card, {
        scale: 2,
        backgroundColor: '#0b0f17',
        useCORS: true
      }).then(canvas => {
        const link = document.createElement('a');
        link.download = `ssparc_wrapped_assessment_${ASSESSMENT_ID}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
        if (btn) btn.innerText = "Download Kartu Summary Wrapped";
      }).catch(err => {
        console.error(err);
        if (btn) btn.innerText = "Download Kartu Summary Wrapped";
      });
    }

    // Story Timer & Pause Controls
    function resetTimer() {
      clearInterval(progressInterval);
      clearTimeout(slideTimer);
      
      const fill = document.getElementById(`fill-${currentSlide}`);
      progressStartTime = Date.now();
      elapsedTimePaused = 0;

      progressInterval = setInterval(() => {
        if (isPaused) return;
        const elapsed = Date.now() - progressStartTime + elapsedTimePaused;
        const pct = Math.min(100, (elapsed / SLIDE_DURATION) * 100);
        if (fill) fill.style.width = `${pct}%`;

        if (elapsed >= SLIDE_DURATION) {
          clearInterval(progressInterval);
          if (currentSlide < TOTAL_SLIDES - 1) {
            renderSlide(currentSlide + 1);
          }
        }
      }, 50);
    }

    function nextSlide() {
      if (currentSlide < TOTAL_SLIDES - 1) {
        renderSlide(currentSlide + 1);
      }
    }

    function prevSlide() {
      if (currentSlide > 0) {
        renderSlide(currentSlide - 1);
      }
    }

    // Tap Navigation Event Listeners
    document.getElementById('tap-left')?.addEventListener('click', (e) => {
      e.stopPropagation();
      prevSlide();
    });
    document.getElementById('tap-right')?.addEventListener('click', (e) => {
      e.stopPropagation();
      nextSlide();
    });
    document.getElementById('btn-prev')?.addEventListener('click', prevSlide);
    document.getElementById('btn-next')?.addEventListener('click', nextSlide);

    // Pause on Hold
    const container = document.getElementById('wrapped-container');
    container.addEventListener('mousedown', () => { isPaused = true; });
    container.addEventListener('mouseup', () => { isPaused = false; });
    container.addEventListener('touchstart', () => { isPaused = true; }, { passive: true });
    container.addEventListener('touchend', () => { isPaused = false; });

    // Keyboard Arrow Keys Navigation
    window.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') nextSlide();
      if (e.key === 'ArrowLeft') prevSlide();
      if (e.key === ' ') isPaused = !isPaused;
    });

    // Start loading on page ready
    loadWrappedData();
  </script>
</body>
</html>
