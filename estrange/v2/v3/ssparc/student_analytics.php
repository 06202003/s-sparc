<?php
require_once(__DIR__ . '/_sso_bridge.php');

$userId = $_SESSION['user_id'] ?? 'student_demo';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student AI Literacy &amp; Cognitive Progression - S-SPARC AI</title>
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
  
  <?php renderSSOHeader('student_analytics', 'AI Literacy Profile'); ?>

  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    
    <!-- Hero / Profile Banner -->
    <div class="rounded-3xl bg-gradient-to-r from-[#00A0A5] to-teal-800 text-white p-6 sm:p-8 shadow-md flex flex-col md:flex-row items-center justify-between gap-6">
      <div class="space-y-2">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold backdrop-blur">
          Metacognitive Learning &amp; AI Literacy Profile
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">AI Literacy &amp; Cognitive Progression</h1>
        <p class="text-teal-100 text-sm max-w-2xl">
          Tracking the evolution of your C-I-O-E prompt formulation, algorithmic problem-solving independence, and computational efficiency in S-SPARC E-STRANGE.
        </p>
      </div>
      <div class="bg-white/10 border border-white/20 rounded-2xl p-4 text-center min-w-[200px] backdrop-blur">
        <span class="text-xs text-teal-200 uppercase tracking-wider font-semibold block">Cognitive Persona</span>
        <span id="profile-literacy-level" class="text-xl font-extrabold text-white block mt-1">The Algorithmic Synthesizer</span>
        <span id="profile-independence-index" class="text-xs font-mono bg-white/20 text-white px-2 py-0.5 rounded-full inline-block mt-2">Independence: 0.88 / 1.0</span>
      </div>
    </div>

    <!-- 4 Key Educational Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      
      <div class="metric-card border-l-4 border-l-teal-500">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span class="font-bold">C-I-O-E Adherence</span>
          <span>Protocol</span>
        </div>
        <div id="stat-cioe-adherence" class="text-2xl font-extrabold text-slate-900">58.3%</div>
        <p class="text-[11px] text-slate-500 mt-1">Completeness rate of Context, Input, Output, and Error trace</p>
      </div>

      <div class="metric-card border-l-4 border-l-indigo-500">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span class="font-bold">Prompt Information Density</span>
          <span>Shannon Entropy H(X)</span>
        </div>
        <div id="stat-prompt-quality" class="text-2xl font-extrabold text-indigo-900 font-mono">0.61 / 1.0</div>
        <p class="text-[11px] text-slate-500 mt-1">Average semantic density and technical specification depth</p>
      </div>

      <div class="metric-card border-l-4 border-l-amber-500">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span class="font-bold">Conceptual Fading Ratio</span>
          <span>Bloom C1-C2</span>
        </div>
        <div id="stat-conceptual-ratio" class="text-2xl font-extrabold text-amber-900 font-mono">33.3%</div>
        <p class="text-[11px] text-slate-500 mt-1">Ratio of conceptual guidance requests without code spoilers</p>
      </div>

      <div class="metric-card border-l-4 border-l-emerald-500">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span class="font-bold">0-Token Fast-Path Hits</span>
          <span>Stewardship</span>
        </div>
        <div id="stat-fast-path-rate" class="text-2xl font-extrabold text-emerald-900 font-mono">35.0%</div>
        <p class="text-[11px] text-slate-500 mt-1">Repository solution reuse avoiding redundant cloud compute</p>
      </div>

    </div>

    <!-- Dual Visual Analytics Row: Bloom's Taxonomy & 5-Axis C-I-O-E Metacognitive Radar -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- Chart 1: Cognitive Mode Distribution (Bloom's Taxonomy) -->
      <div class="metric-card space-y-4 flex flex-col justify-between">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
              <span>Cognitive Mode Distribution</span>
              <span class="text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full">Bloom's Taxonomy</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Transition from raw code extraction to higher-order conceptual scaffolding</p>
          </div>
        </div>
        
        <div class="h-64 relative">
          <canvas id="bloomDistributionChart"></canvas>
        </div>

        <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 text-center text-[11px]">
          <div class="p-2 rounded-xl bg-amber-50/70 border border-amber-200/60">
            <span class="text-amber-800 font-bold block">C1–C2 Understand</span>
            <span class="text-[10px] text-amber-600">Conceptual Inquiries</span>
          </div>
          <div class="p-2 rounded-xl bg-teal-50/70 border border-teal-200/60">
            <span class="text-teal-800 font-bold block">C3–C4 Apply</span>
            <span class="text-[10px] text-teal-600">Code Synthesis</span>
          </div>
          <div class="p-2 rounded-xl bg-indigo-50/70 border border-indigo-200/60">
            <span class="text-indigo-800 font-bold block">C5–C6 Evaluate</span>
            <span class="text-[10px] text-indigo-600">Refactoring &amp; Scaffolding</span>
          </div>
        </div>
      </div>

      <!-- Chart 2: 5-Axis C-I-O-E Protocol & Technical Mastery Radar -->
      <div class="metric-card space-y-4 flex flex-col justify-between">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
              <span>Metacognitive &amp; C-I-O-E Radar</span>
              <span class="text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full">5 Dimensions</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Decomposition proficiency across Context, Input, Output, Debugging &amp; Entropy</p>
          </div>
        </div>

        <div class="h-64 relative flex items-center justify-center">
          <canvas id="metacognitiveRadarChart"></canvas>
        </div>

        <!-- 5 Dimension Live Status Badges -->
        <div class="grid grid-cols-5 gap-1.5 pt-2 border-t border-slate-100 text-center">
          <div class="p-1.5 rounded-xl bg-slate-50 border border-slate-200/80">
            <span class="text-[10px] text-slate-500 block">Context</span>
            <span id="radar-val-context" class="text-xs font-bold text-teal-700 mt-0.5 block">0%</span>
          </div>
          <div class="p-1.5 rounded-xl bg-slate-50 border border-slate-200/80">
            <span class="text-[10px] text-slate-500 block">Input</span>
            <span id="radar-val-input" class="text-xs font-bold text-teal-700 mt-0.5 block">0%</span>
          </div>
          <div class="p-1.5 rounded-xl bg-slate-50 border border-slate-200/80">
            <span class="text-[10px] text-slate-500 block">Output</span>
            <span id="radar-val-output" class="text-xs font-bold text-teal-700 mt-0.5 block">0%</span>
          </div>
          <div class="p-1.5 rounded-xl bg-slate-50 border border-slate-200/80">
            <span class="text-[10px] text-slate-500 block">Debugging</span>
            <span id="radar-val-error" class="text-xs font-bold text-teal-700 mt-0.5 block">0%</span>
          </div>
          <div class="p-1.5 rounded-xl bg-slate-50 border border-slate-200/80">
            <span class="text-[10px] text-slate-500 block">Vocabulary</span>
            <span id="radar-val-vocab" class="text-xs font-bold text-teal-700 mt-0.5 block">0%</span>
          </div>
        </div>
      </div>

    </div>

    <!-- S-SPARC Assessment Wrapped Archive -->
    <div class="metric-card space-y-5">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
        <div>
          <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <span>S-SPARC Prompt Wrapped Archive</span>
            <span class="text-[11px] font-semibold bg-teal-50 text-teal-700 border border-teal-200 px-2 py-0.5 rounded-full">Enrolled Courses</span>
          </h3>
          <p class="text-xs text-slate-500 mt-0.5">Interactive Spotify-Wrapped review unlocked once an assessment deadline has expired.</p>
        </div>

        <!-- Filter & Search Controls -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
          <!-- Course Filter Dropdown -->
          <div class="relative min-w-[180px]">
            <select id="course-filter-select" onchange="onFilterChange()" class="w-full text-xs font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition cursor-pointer">
              <option value="all">Semua Mata Kuliah</option>
              <?php
              $userIdSafe = mysqli_real_escape_string($db, $sso_user_id);
              // Fetch distinct enrolled courses for filter dropdown
              $coursesListQuery = "
                  SELECT DISTINCT c.course_id, c.name AS course_name
                  FROM course c
                  LEFT JOIN enrollment e ON e.course_id = c.course_id AND e.student_id = '$userIdSafe'
                  LEFT JOIN game_student_course gsc ON gsc.course_id = c.course_id AND gsc.student_id = '$userIdSafe'
                  WHERE (e.student_id IS NOT NULL OR gsc.student_id IS NOT NULL)
                  ORDER BY c.name ASC
              ";
              $coursesListRes = $db->query($coursesListQuery);
              if ($coursesListRes && $coursesListRes->num_rows > 0) {
                  while ($cRow = $coursesListRes->fetch_assoc()) {
                      echo '<option value="' . htmlspecialchars($cRow['course_id']) . '">' . htmlspecialchars($cRow['course_name']) . '</option>';
                  }
              }
              ?>
            </select>
          </div>

          <!-- Search Input -->
          <div class="relative">
            <input type="text" id="assessment-search-input" oninput="onFilterChange()" placeholder="Cari assessment..." class="w-full sm:w-48 text-xs bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition">
            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
          </div>
        </div>
      </div>

      <!-- PHP Data Collector for Client-Side Pagination & Filtering -->
      <?php
      $expiredAssessmentsList = [];
      $expiredAssessmentsQuery = "
          SELECT DISTINCT a.assessment_id, a.name AS assessment_name, a.course_id, c.name AS course_name, a.submission_close_time
          FROM assessment a
          INNER JOIN course c ON c.course_id = a.course_id
          LEFT JOIN enrollment e ON e.course_id = a.course_id AND e.student_id = '$userIdSafe'
          LEFT JOIN game_student_course gsc ON gsc.course_id = a.course_id AND gsc.student_id = '$userIdSafe'
          WHERE (e.student_id IS NOT NULL OR gsc.student_id IS NOT NULL)
            AND a.submission_close_time < NOW()
          ORDER BY a.submission_close_time DESC
      ";
      $expiredRes = $db->query($expiredAssessmentsQuery);
      if ($expiredRes && $expiredRes->num_rows > 0) {
          while ($row = $expiredRes->fetch_assoc()) {
              $expiredAssessmentsList[] = [
                  'assessment_id' => (string)$row['assessment_id'],
                  'assessment_name' => (string)$row['assessment_name'],
                  'course_id' => (string)$row['course_id'],
                  'course_name' => (string)($row['course_name'] ?: 'Course'),
                  'submission_close_time' => (string)$row['submission_close_time'],
                  'formatted_date' => date('d M Y, H:i', strtotime($row['submission_close_time']))
              ];
          }
      }
      ?>

      <!-- Grid Cards Container -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="wrapped-assessments-grid">
        <!-- Rendered dynamically by JavaScript -->
      </div>

      <!-- Pagination Controls Bar -->
      <div id="pagination-controls" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-slate-100 text-xs text-slate-500">
        <div id="pagination-info" class="font-medium">Showing 0 of 0 assessments</div>
        <div class="flex items-center gap-1.5" id="pagination-buttons">
          <!-- Page Buttons -->
        </div>
      </div>
    </div>

  </main>

  <script>
    const USER_ID = "<?= htmlspecialchars($userId) ?>";
    const ALL_EXPIRED_ASSESSMENTS = <?= json_encode($expiredAssessmentsList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    
    let filteredAssessments = [...ALL_EXPIRED_ASSESSMENTS];
    let currentPage = 1;
    const ITEMS_PER_PAGE = 6;

    function renderAssessmentCards() {
      const grid = document.getElementById('wrapped-assessments-grid');
      const totalItems = filteredAssessments.length;
      const totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE) || 1;

      if (currentPage > totalPages) currentPage = totalPages;
      if (currentPage < 1) currentPage = 1;

      const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
      const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, totalItems);
      const pageItems = filteredAssessments.slice(startIndex, endIndex);

      if (totalItems === 0) {
        grid.innerHTML = `
          <div class="col-span-full p-8 text-center bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2">
            <div class="w-10 h-10 rounded-xl bg-slate-200/70 text-slate-500 flex items-center justify-center mx-auto">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <p class="text-xs font-bold text-slate-700">Tidak ada assessment yang cocok dengan filter.</p>
            <p class="text-[11px] text-slate-500">Coba ubah kata kunci pencarian atau pilih mata kuliah lain.</p>
          </div>
        `;
        document.getElementById('pagination-info').innerText = 'Showing 0 assessments';
        document.getElementById('pagination-buttons').innerHTML = '';
        return;
      }

      grid.innerHTML = pageItems.map(item => `
        <div class="p-4 rounded-2xl bg-slate-900 text-white flex flex-col justify-between space-y-3 shadow-md border border-slate-800 transition hover:border-slate-700 hover:shadow-lg">
          <div class="space-y-1">
            <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider block truncate">${escapeHtml(item.course_name)}</span>
            <h4 class="font-bold text-sm text-white line-clamp-1" title="${escapeHtml(item.assessment_name)}">${escapeHtml(item.assessment_name)}</h4>
            <span class="text-[11px] text-slate-400 block font-mono">Closed: ${item.formatted_date}</span>
          </div>
          <a href="student_prompt_wrapped.php?assessment_id=${encodeURIComponent(item.assessment_id)}" class="w-full py-2 px-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-slate-950 font-bold text-xs text-center transition flex items-center justify-center gap-1.5 shadow">
            <span>Open S-SPARC Wrapped</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
          </a>
        </div>
      `).join('');

      // Update Pagination Info
      document.getElementById('pagination-info').innerText = `Showing ${startIndex + 1}–${endIndex} of ${totalItems} assessments`;

      // Render Pagination Buttons
      renderPaginationControls(totalPages);
    }

    function renderPaginationControls(totalPages) {
      const btnContainer = document.getElementById('pagination-buttons');
      if (totalPages <= 1) {
        btnContainer.innerHTML = '';
        return;
      }

      let buttonsHtml = `
        <button onclick="goToPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled class="p-1.5 rounded-lg border border-slate-200 text-slate-300 cursor-not-allowed"' : 'class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 transition"'} title="Previous">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
      `;

      for (let p = 1; p <= totalPages; p++) {
        if (p === currentPage) {
          buttonsHtml += `<button class="h-7 min-w-[28px] px-2 rounded-lg bg-teal-600 text-white font-bold text-xs shadow-xs">${p}</button>`;
        } else if (p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1) {
          buttonsHtml += `<button onclick="goToPage(${p})" class="h-7 min-w-[28px] px-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 font-semibold text-xs transition">${p}</button>`;
        } else if (p === currentPage - 2 || p === currentPage + 2) {
          buttonsHtml += `<span class="px-1 text-slate-400">...</span>`;
        }
      }

      buttonsHtml += `
        <button onclick="goToPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled class="p-1.5 rounded-lg border border-slate-200 text-slate-300 cursor-not-allowed"' : 'class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 transition"'} title="Next">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
      `;

      btnContainer.innerHTML = buttonsHtml;
    }

    function goToPage(page) {
      currentPage = page;
      renderAssessmentCards();
    }

    function onFilterChange() {
      const selectedCourse = document.getElementById('course-filter-select').value;
      const searchQuery = document.getElementById('assessment-search-input').value.toLowerCase().trim();

      filteredAssessments = ALL_EXPIRED_ASSESSMENTS.filter(item => {
        const matchCourse = (selectedCourse === 'all') || (item.course_id === selectedCourse);
        const matchSearch = (item.assessment_name.toLowerCase().includes(searchQuery)) ||
                            (item.course_name.toLowerCase().includes(searchQuery));
        return matchCourse && matchSearch;
      });

      currentPage = 1;
      renderAssessmentCards();
    }

    function escapeHtml(text) {
      if (!text) return '';
      return text.replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
      });
    }

    async function loadStudentProfile() {
      let bloomData = [0, 0, 0];
      let radarData = [0, 0, 0, 0, 0];

      try {
        let res = await fetch(`api_proxy.php?endpoint=/api/educational/student-profile/${USER_ID}`);
        if (!res.ok) {
          res = await fetch(`https://estrangeinternal.itmaranatha.org/api/educational/student-profile/${USER_ID}`);
        }
        if (res.ok) {
          const profile = await res.json();
          document.getElementById('profile-literacy-level').textContent = profile.persona_title || profile.literacy_level || 'The Algorithmic Synthesizer';
          document.getElementById('profile-independence-index').textContent = `Independence: ${(profile.cognitive_independence_index ?? 1.0).toFixed(2)} / 1.0`;
          document.getElementById('stat-cioe-adherence').textContent = `${(((profile.average_cioe_score ?? 0) * 100)).toFixed(1)}%`;
          document.getElementById('stat-prompt-quality').textContent = `${(profile.average_entropy ?? 0).toFixed(2)} / 1.0`;
          document.getElementById('stat-conceptual-ratio').textContent = `${(((profile.conceptual_mode_ratio ?? 0) * 100)).toFixed(1)}%`;
          document.getElementById('stat-fast-path-rate').textContent = `${(((profile.fast_path_utilization_rate ?? 0) * 100)).toFixed(1)}%`;
          
          if (profile.bloom_distribution && Array.isArray(profile.bloom_distribution)) {
            bloomData = profile.bloom_distribution;
          }

          if (profile.radar_dimensions) {
            const rd = profile.radar_dimensions;
            radarData = [
              rd.Context ?? 0,
              rd.Input ?? 0,
              rd.Output ?? 0,
              rd.Error ?? 0,
              rd.Vocabulary ?? 0
            ];
            document.getElementById('radar-val-context').innerText = `${rd.Context ?? 0}%`;
            document.getElementById('radar-val-input').innerText = `${rd.Input ?? 0}%`;
            document.getElementById('radar-val-output').innerText = `${rd.Output ?? 0}%`;
            document.getElementById('radar-val-error').innerText = `${rd.Error ?? 0}%`;
            document.getElementById('radar-val-vocab').innerText = `${rd.Vocabulary ?? 0}%`;
          }
        }
      } catch (e) {
        console.debug('Failed to fetch profile:', e);
      }

      // 1. Render Bloom Cognitive Mode Bar Chart
      const ctxBloom = document.getElementById('bloomDistributionChart')?.getContext('2d');
      if (ctxBloom) {
        new Chart(ctxBloom, {
          type: 'bar',
          data: {
            labels: ['C1–C2 (Understand)', 'C3–C4 (Apply/Code)', 'C5–C6 (Evaluate/Design)'],
            datasets: [{
              label: 'Interaction Count',
              data: bloomData,
              backgroundColor: ['#f59e0b', '#00A0A5', '#6366f1'],
              borderRadius: 8
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1, precision: 0 } },
              x: { grid: { display: false }, ticks: { font: { size: 11, weight: 'bold' } } }
            }
          }
        });
      }

      // 2. Render 5-Axis Metacognitive & C-I-O-E Radar Chart
      const ctxRadar = document.getElementById('metacognitiveRadarChart')?.getContext('2d');
      if (ctxRadar) {
        new Chart(ctxRadar, {
          type: 'radar',
          data: {
            labels: ['Context [C]', 'Input [I]', 'Output [O]', 'Debugging [E]', 'Vocabulary H(X)'],
            datasets: [{
              label: 'Mastery Score (%)',
              data: radarData,
              backgroundColor: 'rgba(0, 160, 165, 0.25)',
              borderColor: '#00A0A5',
              pointBackgroundColor: '#0f766e',
              pointBorderColor: '#ffffff',
              pointHoverBackgroundColor: '#ffffff',
              pointHoverBorderColor: '#00A0A5',
              borderWidth: 2,
              pointRadius: 4
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              r: {
                angleLines: { color: '#e2e8f0' },
                grid: { color: '#f1f5f9' },
                pointLabels: {
                  color: '#334155',
                  font: { size: 10, weight: '600' }
                },
                ticks: {
                  display: false,
                  min: 0,
                  max: 100,
                  stepSize: 20
                }
              }
            },
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return `${context.label}: ${context.raw}%`;
                  }
                }
              }
            }
          }
        });
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      renderAssessmentCards();
      loadStudentProfile();
    });
  </script>
</body>
</html>
