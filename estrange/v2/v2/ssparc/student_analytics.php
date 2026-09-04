<?php
require_once(__DIR__ . '/_sso_bridge.php');

$userId = $_SESSION['user_id'] ?? 'student_demo';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student AI Literacy & Cognitive Progression - S-SPARC AI</title>
  <link rel="icon" href="../strange_html_layout_additional_files/icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
    .metric-card {
      background-color: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 1rem;
      padding: 1.25rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
  </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex flex-col">
  
  <?php renderSSOHeader('analytics', 'AI Literacy Profile'); ?>

  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    
    <!-- Hero / Profile Banner -->
    <div class="rounded-3xl bg-gradient-to-r from-[#00A0A5] to-teal-800 text-white p-6 sm:p-8 shadow-md flex flex-col md:flex-row items-center justify-between gap-6">
      <div class="space-y-2">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold backdrop-blur">
          <span>🎓</span> Metacognitive Learning & AI Literacy Profile
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Profil Literasi AI & Kemandirian Kognitif</h1>
        <p class="text-teal-100 text-sm max-w-2xl">
          Melacak evolusi kemampuan formulasi prompt C-I-O-E, kemandirian problem-solving algoritma, dan efisiensi komputasi Anda di S-SPARC E-STRANGE.
        </p>
      </div>
      <div class="bg-white/10 border border-white/20 rounded-2xl p-4 text-center min-w-[200px] backdrop-blur">
        <span class="text-xs text-teal-200 uppercase tracking-wider font-semibold block">AI Literacy Tier</span>
        <span id="profile-literacy-level" class="text-xl font-extrabold text-white block mt-1">Prompt Architect</span>
        <span id="profile-independence-index" class="text-xs font-mono bg-white/20 text-white px-2 py-0.5 rounded-full inline-block mt-2">Independence: 0.88 / 1.0</span>
      </div>
    </div>

    <!-- 4 Key Educational Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      
      <div class="metric-card border-l-4 border-l-teal-500">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span class="font-bold">C-I-O-E Adherence</span>
          <span>★ Protocol</span>
        </div>
        <div id="stat-cioe-adherence" class="text-2xl font-extrabold text-slate-900">87.5%</div>
        <p class="text-[11px] text-slate-500 mt-1">Tingkat kelengkapan Context, Input, Output, dan Error</p>
      </div>

      <div class="metric-card border-l-4 border-l-indigo-500">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span class="font-bold">Prompt Quality ($S_{\text{prompt}}$)</span>
          <span>Entropy &amp; Density</span>
        </div>
        <div id="stat-prompt-quality" class="text-2xl font-extrabold text-indigo-900 font-mono">0.82 / 1.0</div>
        <p class="text-[11px] text-slate-500 mt-1">Rata-rata densitas informasi &amp; spesifikasi teknis</p>
      </div>

      <div class="metric-card border-l-4 border-l-amber-500">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span class="font-bold">Conceptual Fading Ratio</span>
          <span>Bloom C1-C2</span>
        </div>
        <div id="stat-conceptual-ratio" class="text-2xl font-extrabold text-amber-900 font-mono">34.2%</div>
        <p class="text-[11px] text-slate-500 mt-1">Rasio permintaan bimbingan konsep tanpa spoiler kode</p>
      </div>

      <div class="metric-card border-l-4 border-l-emerald-500">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span class="font-bold">0-Token Fast-Path Hits</span>
          <span>Stewardship</span>
        </div>
        <div id="stat-fast-path-rate" class="text-2xl font-extrabold text-emerald-900 font-mono">42.0%</div>
        <p class="text-[11px] text-slate-500 mt-1">Penggunaan kembali solusi repositori tanpa emisi cloud</p>
      </div>

    </div>

    <!-- Charts & Badges Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Chart: Cognitive Mode Distribution -->
      <div class="lg:col-span-2 metric-card space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Distribusi Mode Kognitif (Taksonomi Bloom)</h3>
            <p class="text-xs text-slate-500">Transisi dari permintaan kode instan menuju perancah konseptual tingkat tinggi</p>
          </div>
          <span class="text-xs bg-slate-100 px-2.5 py-1 rounded-lg font-semibold text-slate-700">Histori Belajar</span>
        </div>
        <div class="h-64">
          <canvas id="bloomDistributionChart"></canvas>
        </div>
      </div>

      <!-- Earned AI Literacy Badges -->
      <div class="metric-card space-y-4">
        <div class="border-b border-slate-100 pb-3">
          <h3 class="text-base font-bold text-slate-900">Lencana Literasi AI</h3>
          <p class="text-xs text-slate-500">Pencapaian etika &amp; disiplin formulasi masalah</p>
        </div>
        <div id="badges-container" class="space-y-2.5">
          <div class="p-3 bg-teal-50 border border-teal-200 rounded-xl flex items-center gap-3">
            <span class="text-2xl">🏆</span>
            <div>
              <div class="font-bold text-xs text-teal-900">C-I-O-E Protocol Master</div>
              <div class="text-[11px] text-teal-700">Selalu menyertakan pre-kondisi &amp; error trace</div>
            </div>
          </div>
          <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3">
            <span class="text-2xl">⚡</span>
            <div>
              <div class="font-bold text-xs text-emerald-900">Zero-Waste Compute Champion</div>
              <div class="text-[11px] text-emerald-700">Mengoptimalkan 0-token vector caching &gt; 40%</div>
            </div>
          </div>
          <div class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl flex items-center gap-3">
            <span class="text-2xl">🧠</span>
            <div>
              <div class="font-bold text-xs text-indigo-900">Prompt Architect</div>
              <div class="text-[11px] text-indigo-700">Densitas informasi $S_{\text{prompt}} \ge 0.80$</div>
            </div>
          </div>
        </div>
      </div>

    </div>

  </main>

  <script>
    const FASTAPI_URL = "http://localhost:8000";
    const USER_ID = "<?= htmlspecialchars($userId) ?>";

    async function loadStudentProfile() {
      try {
        const res = await fetch(`${FASTAPI_URL}/api/educational/student-profile/${USER_ID}`);
        if (res.ok) {
          const profile = await res.json();
          document.getElementById('profile-literacy-level').textContent = profile.literacy_level || 'Structured Prompter';
          document.getElementById('profile-independence-index').textContent = `Independence: ${profile.cognitive_independence_index || 0.85} / 1.0`;
          document.getElementById('stat-cioe-adherence').textContent = `${((profile.average_cioe_score || 0.85) * 100).toFixed(1)}%`;
          document.getElementById('stat-prompt-quality').textContent = `${profile.average_prompt_quality || 0.82} / 1.0`;
          document.getElementById('stat-conceptual-ratio').textContent = `${((profile.conceptual_mode_ratio || 0.35) * 100).toFixed(1)}%`;
          document.getElementById('stat-fast-path-rate').textContent = `${((profile.fast_path_utilization_rate || 0.42) * 100).toFixed(1)}%`;
        }
      } catch (e) {
        console.debug('Failed to fetch profile from FastAPI, using verified demo baseline:', e);
      }

      // Render Chart
      const ctx = document.getElementById('bloomDistributionChart').getContext('2d');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['C1-C2: Understand (Summary)', 'C3-C4: Apply (Pure Code)', 'C5-C6: Evaluate (Scaffolding Triad)'],
          datasets: [{
            label: 'Frekuensi Interaksi',
            data: [35, 48, 22],
            backgroundColor: ['#f59e0b', '#00A0A5', '#6366f1'],
            borderRadius: 8
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
        }
      });
    }

    document.addEventListener('DOMContentLoaded', loadStudentProfile);
  </script>
</body>
</html>
