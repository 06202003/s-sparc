<?php
require __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$loggedIn = !empty($_SESSION['flask_cookie']);
$username = $_SESSION['username'] ?? 'Guest';

$courses = [];
if ($loggedIn) {
    $backendBase = backend_base();
    $httpClient = new Client([
        'base_uri' => $backendBase . '/',
        'timeout'  => 10,
    ]);
    try {
        $optionsBase = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Cookie'       => $_SESSION['flask_cookie'],
            ],
        ];
        $respC = $httpClient->request('GET', 'courses', $optionsBase);
        $dataC = json_decode((string) $respC->getBody(), true);
        if (is_array($dataC) && !empty($dataC['courses']) && is_array($dataC['courses'])) {
            $courses = $dataC['courses'];
        }
    } catch (\Throwable $e) {
        // ignore
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gamification Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
  <style>
    .odd-row { background-color: #f8fafc; }
    .even-row { background-color: #ffffff; }
    .select2-container--default .select2-selection--single { height: 38px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; }
    .dataTables_wrapper, #leaderboardTable {
      font-family: 'Manrope', system-ui, -apple-system, sans-serif;
      font-size: 0.85rem;
    }
    #leaderboardTable th, #leaderboardTable td {
      padding: 0.5rem 0.75rem;
      vertical-align: middle;
    }
    #leaderboardTable td {
      white-space: normal;
      word-break: break-word;
    }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
      font-size: 0.85rem;
    }
    .dataTables_wrapper .dataTables_info { font-size: 0.8rem; color: #6b7280; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { font-size: 0.85rem; }
    /* Simple rank display with medals for top 3 */
    .rank-cell {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      justify-content: flex-start;
    }
    .rank-medal {
      font-size: 1.5rem;
      line-height: 1;
      display: flex;
      align-items: center;
      width: 24px;
      height: 24px;
    }
    .rank-number {
      font-weight: 600;
      color: #475569;
      line-height: 1;
    }
    /* Current user highlight */
    #leaderboardTable tr.current-user {
      background: linear-gradient(90deg, #dbeafe 0%, #bfdbfe 100%) !important;
      font-weight: 600;
      border-left: 4px solid #3b82f6;
    }
    #leaderboardTable tr.current-user td {
      color: #1e40af;
    }
    #leaderboardTable tr.current-user td:first-child {
      padding-left: 0.5rem;
    }
    /* Points badge */
    .points-badge {
      display: inline-block;
      background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-weight: 700;
      font-size: 0.75rem;
      box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
    }
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
          <div class="h-10 w-10 rounded-xl bg-indigo-600 text-white grid place-items-center font-semibold">XP</div>
          <div>
            <div class="text-lg font-semibold">Gamification Dashboard</div>
            <div class="text-xs text-slate-500">Token usage & points for user: <strong><?= htmlspecialchars($username) ?></strong></div>
          </div>
        </div>
         <nav class="flex items-center gap-3 text-sm font-medium">
          <a class="text-slate-400 hover:text-slate-700" href="courses.php">Courses</a>
          <a class="text-slate-900 hover:text-slate-900 active" href="gamification_dashboard.php">Gamification</a>
          <a class="text-slate-400 hover:text-slate-700" href="dashboard.php">Environmental impact</a>
          <a href="logout.php" class="ml-2 inline-flex items-center gap-2 rounded-full bg-red-800 text-white px-3 py-1 hover:bg-red-600 shadow-sm">Logout</a>
        </nav>
      </div>
    </header>
    <main class="flex-1">
      <div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
        <section class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
          <h1 class="text-lg font-semibold text-slate-900 mb-1">Weekly token usage</h1>
          <div class="flex justify-between items-start mb-4">
            <p class="text-sm text-slate-600 max-w-2xl">Overview of your weekly token usage and active points. Retrieval-only answers from the database do not reduce your token.</p>
            <select id="periodSelect" class="min-w-[200px] shrink-0 rounded-md border-slate-200 text-sm py-1 shadow-sm" onchange="renderTokenChart()">
              <option value="week">Current Week</option>
              <option value="all">All Time</option>
            </select>
          </div>
          <div class="grid gap-4 md:grid-cols-[2fr_1fr] items-center">
            <div class="h-64">
              <canvas id="tokenChart"></canvas>
            </div>
            <div class="space-y-2 text-sm text-slate-700" id="tokenSummary">
              <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between">
                                  <span class="text-slate-500">Threshold</span>
                                  <span><span id="dash-token-threshold">-</span> tokens</span>
                                </div>
                  <div class="flex items-center justify-between">
                    <span class="text-slate-500">Used</span>
                    <span><span id="dash-token-used">-</span> tokens</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-slate-500">Remaining</span>
                    <span><span id="dash-token-remaining">-</span> tokens</span>
                  </div>
                <div class="pt-2">
                  <label class="text-xs text-slate-500">Filter chart by course</label>
                  <select id="chartCourseSelect" class="min-w-[200px] shrink-0 block mt-1 w-full rounded-md border-slate-200 text-sm max-w-md">
                    <option value="">All courses</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </section>
        <section class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
          <h1 class="text-lg font-semibold text-slate-900 mb-1">How points work</h1>
          <div class="text-xs text-slate-600 space-y-2">
            <p>Points are a 0–100 score based on how your usage compares to the threshold for each assessment.</p>
            <ul class="list-disc pl-4 space-y-1">
              <li><strong>Threshold</strong> = 1.10 × average token usage of all users in the same assessment (this week or until the assessment end date).</li>
              <li>If your usage ≤ threshold → score = 100.</li>
              <li>If your usage > threshold → score decreases linearly toward 0.</li>
              <li>Retrieval-only answers are free and do not affect your usage.</li>
            </ul>
          </div>
        </section>
        <section class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
          <h1 class="text-lg font-semibold text-slate-900 mb-1">Leaderboard</h1>
          <div class="flex items-center gap-3 mb-3">
            <label class="text-xs text-slate-500">Course</label>
            <select id="leaderboardCourseSelect" class="min-w-[200px] shrink-0 block mt-1 w-full rounded-md border-slate-200 text-sm max-w-md">
              <option value="">Select course</option>
            </select>
            <label class="text-xs text-slate-500">Assessment</label>
            <select id="leaderboardSelect" class="min-w-[200px] shrink-0 block mt-1 w-full rounded-md border-slate-200 text-sm max-w-md" disabled>
              <option value="">Select assessment</option>
            </select>
          </div>
          <div id="leaderboardContainer" class="text-sm text-slate-700">
            <div class="text-xs text-slate-500 mb-2">Select a course to see its leaderboard, or choose an assessment for per-assessment ranks.</div>
            <div id="leaderboardTableWrapper"></div>
          </div>
        </section>
      </div>
    </main>
  </div>
  <script>
    async function renderTokenChart() {
      const canvas = document.getElementById('tokenChart');
      if (!canvas) return;
      const totalEl = document.getElementById('dash-token-total');
        const thresholdEl = document.getElementById('dash-token-threshold');
        const usedEl = document.getElementById('dash-token-used');
        const remainingEl = document.getElementById('dash-token-remaining');
      function createChart(cfg) {
        const existingChart = Chart.getChart("tokenChart");
        if (existingChart) {
          existingChart.destroy();
        }
        const ctx = document.getElementById('tokenChart').getContext('2d');
        new Chart(ctx, cfg);
      }
      try {
        const period = document.getElementById('periodSelect') ? document.getElementById('periodSelect').value : 'week';
        const res = await fetch('token_usage_breakdown.php?period=' + period, { method: 'GET' });
        
        if (!res.ok) return;
        const data = await res.json();
        // console.log('Data received from backend:', data);
        if (!data) return;
        // Only use by_assessment for summary
        // Show overall totals by default (no course selected)
        const assessment = null;
        // compute overall threshold: prefer backend-provided total_threshold, else sum per-assessment thresholds
        const overall_threshold = (typeof data.total_threshold !== 'undefined') ? Number(data.total_threshold) : (Array.isArray(data.by_assessment) ? data.by_assessment.reduce((s,a)=>s+Number(a.threshold||0),0) : 0);
        const overall_used_from_total = (data.total && typeof data.total.total_used !== 'undefined') ? Number(data.total.total_used) : 0;
        const overall_remaining_from_total = (data.total && typeof data.total.remaining !== 'undefined') ? Number(data.total.remaining) : null;
        // compute displayed overall used: prefer sum from by_assessment when available
        let displayed_overall_used = 0;
        if (Array.isArray(data.by_assessment) && data.by_assessment.length) {
          displayed_overall_used = data.by_assessment.map(a=>Number(a.total_used||0)).reduce((s,v)=>s+v,0);
        } else {
          displayed_overall_used = overall_used_from_total;
        }
        let displayed_overall_remaining = overall_remaining_from_total !== null ? overall_remaining_from_total : Math.max(0, overall_threshold - displayed_overall_used);
        if (thresholdEl) thresholdEl.textContent = (overall_threshold !== null && overall_threshold !== undefined) ? String(overall_threshold) : '-';
        if (usedEl) usedEl.textContent = (displayed_overall_used !== null && displayed_overall_used !== undefined) ? String(displayed_overall_used) : '-';
        if (remainingEl) remainingEl.textContent = (displayed_overall_remaining !== null && displayed_overall_remaining !== undefined) ? String(displayed_overall_remaining) : '-';

        // Build overall chart data (used when no course filter selected)
        let overall_labels = [];
        let overall_used_sum = 0;
        let overall_used_array = [];
        let overall_remaining_array = [];
        let overall_thresholds_array = [];
        if (Array.isArray(data.by_assessment)) {
          overall_labels = data.by_assessment.map(a => a.assessment_name || a.assessment_id || 'Unassigned');
          overall_used_array = data.by_assessment.map(a => Number(a.total_used ?? 0));
          overall_remaining_array = data.by_assessment.map(a => Number(a.remaining ?? 0));
          overall_thresholds_array = data.by_assessment.map(a => Number(a.threshold ?? 0));
          overall_used_sum = overall_used_array.reduce((s, v) => s + (Number(v) || 0), 0);
        }
        // create initial overall chart
        let datasets = [];
        if (overall_labels.length && overall_used_array.length === overall_remaining_array.length) {
          datasets.push({ label: 'Used', data: overall_used_array, backgroundColor: '#ef4444', borderRadius: 6, stack: 'stack1' });
          datasets.push({ label: 'Remaining', data: overall_remaining_array, backgroundColor: '#06b6d4', borderRadius: 6, stack: 'stack1' });
        }
        if (overall_thresholds_array.some(t=>t>0)) {
          datasets.push({ label: 'Threshold', data: overall_thresholds_array, type: 'line', borderColor: '#000000', borderDash: [6,4], borderWidth: 2.5, pointRadius: 4.5, pointHoverRadius: 6, pointBackgroundColor: '#000000', pointBorderColor: '#000000', tension: 0, fill: false, order: 0 });
        }
        createChart({
          type: 'bar',
          data: { labels: overall_labels, datasets: datasets.map(d => d.type === 'line' ? d : { ...d, order: 1 }) },
          options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { beginAtZero: true, stacked: true } }, plugins: { legend: { position: 'top' } } }
        });
        // Populate leaderboard select from assessments
        const leaderboardSelect = document.getElementById('leaderboardSelect');
        const leaderboardWrapper = document.getElementById('leaderboardTableWrapper');
        const currentUser = '<?= htmlspecialchars($username) ?>';
        const chartCourseSelect = document.getElementById('chartCourseSelect');
        async function renderLeaderboard(data, titleText) {
          const leaderboardWrapper = document.getElementById('leaderboardTableWrapper');
          if (!data || !Array.isArray(data.leaderboard)) {
            leaderboardWrapper.innerHTML = '<div class="text-xs text-slate-500">No leaderboard data.</div>';
            return;
          }
          const rows = data.leaderboard || [];
          leaderboardWrapper.innerHTML = `<div class="mb-2 text-sm font-semibold text-slate-800">${titleText}</div><div class="w-full"><table id="leaderboardTable" class="w-full text-left text-sm" style="width:100%; table-layout:fixed;"></table></div>`;
          if (window.leaderboardTable && $.fn.DataTable && $.fn.DataTable.isDataTable('#leaderboardTable')) {
            try { window.leaderboardTable.destroy(); } catch (e) {}
          }
          const dtData = rows.map(r => ({
            rank: r.rank,
            username: r.username || r.user_id || 'Unknown',
            points: (r.points !== null && r.points !== undefined) ? Number(r.points) : null,
            assessments_count: r.assessments_count || null,
          }));
          // console.log(dtData);
          window.leaderboardTable = $('#leaderboardTable').DataTable({
            data: dtData,
            columns: [
              { 
                data: 'rank', 
                title: 'Rank', 
                className: 'py-2',
                render: function(data, type, row) {
                  const rank = parseInt(data);
                  let medal = '';
                  if (rank === 1) medal = '🥇';
                  else if (rank === 2) medal = '🥈';
                  else if (rank === 3) medal = '🥉';
                  if (medal) return `<span class="rank-medal" title="Rank ${rank}">${medal}</span>`;
                  return `<span class="rank-number">${data}</span>`;
                }
              },
              { 
                data: 'username', 
                title: 'User', 
                className: 'py-2',
                render: function(data, type, row) { 
                  const escaped = escapeHtml(data);
                  if (data === currentUser) {
                    return `<strong>${escaped}</strong> <span style="color:#3b82f6;font-size:0.75rem">(You)</span>`;
                  }
                  return escaped;
                }
              },
              { 
                data: 'points', 
                title: 'Points', 
                className: 'py-2 text-center',
                render: function(data, type, row) {
                  const val = Number(data);
                  if (!Number.isFinite(val)) return '-';
                  return `<span class="points-badge">${val.toFixed(2)} pts</span>`;
                }
              },
              { 
                data: 'assessments_count', 
                title: 'Assessments',
                className: 'py-2 text-center',
                visible: dtData.some(d => d.assessments_count !== null),
                render: function(data) { return data ? data : '-'; }
              }
            ],
            paging: true,
            pageLength: 10,
            lengthChange: true,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            searching: true,
            info: true,
            ordering: true,
            order: [[0, 'asc']],
            responsive: true,
            language: { 
              search: "Search:", 
              emptyTable: "No leaderboard data.",
              lengthMenu: "Show _MENU_ entries",
              info: "Showing _START_ to _END_ of _TOTAL_ entries",
              infoEmpty: "Showing 0 to 0 of 0 entries",
              paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
              }
            },
            createdRow: function(row, data, dataIndex) {
              // Highlight current user row
              if (data.username === currentUser) {
                $(row).addClass('current-user');
              } else {
                if (dataIndex % 2 === 0) $(row).addClass('even-row');
                else $(row).addClass('odd-row');
              }
            }
          });
          if (data.user_rank) {
            const extra = data.user_rank.assessments_count ? ` (${data.user_rank.assessments_count} assessments)` : '';
            const urPoints = Number(data.user_rank.points);
            const urPointsText = Number.isFinite(urPoints) ? `${urPoints.toFixed(2)} pts` : '-';
            const ur = `<div class="mt-2 text-xs text-slate-600">Your rank: <strong>${data.user_rank.rank}</strong> — <strong>${escapeHtml(urPointsText)}</strong>${extra}</div>`;
            leaderboardWrapper.insertAdjacentHTML('beforeend', ur);
          }
        }

        async function loadAssessmentLeaderboard(assessmentId) {
          const leaderboardWrapper = document.getElementById('leaderboardTableWrapper');
          if (!assessmentId) {
            const courseId = document.getElementById('leaderboardCourseSelect') ? document.getElementById('leaderboardCourseSelect').value : '';
            if (courseId) {
              loadCourseLeaderboard(courseId);
            } else {
              leaderboardWrapper.innerHTML = '<div class="text-xs text-slate-500">Choose a course to see the course leaderboard.</div>';
            }
            return;
          }
          try {
            const res = await fetch(`assessment_leaderboard.php?assessment_id=${encodeURIComponent(assessmentId)}`);
            if (!res.ok) throw new Error('Failed');
            const json = await res.json();
            // console.log("Assessment Leaderboard FULL JSON:", json);
            // console.log("Leaderboard array:", json.leaderboard);
            // console.table(json.leaderboard);
            await renderLeaderboard(json, 'Assessment Leaderboard');
          } catch (e) {
            leaderboardWrapper.innerHTML = '<div class="text-xs text-red-500">Error loading leaderboard.</div>';
          }
        }

        async function loadCourseLeaderboard(courseId) {
          const leaderboardWrapper = document.getElementById('leaderboardTableWrapper');
          if (!courseId) {
            leaderboardWrapper.innerHTML = '<div class="text-xs text-slate-500">Choose a course to see the course leaderboard.</div>';
            return;
          }
          try {
            const res = await fetch(`course_leaderboard.php?course_id=${encodeURIComponent(courseId)}`);
            if (!res.ok) throw new Error('Failed');
            const json = await res.json();
            await renderLeaderboard(json, 'Course Leaderboard (average across assessments)');
          } catch (e) {
            leaderboardWrapper.innerHTML = '<div class="text-xs text-red-500">Error loading course leaderboard.</div>';
          }
        }
        function escapeHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
        try {
          const courseSelect = document.getElementById('leaderboardCourseSelect');
          const assessmentSelect = document.getElementById('leaderboardSelect');
          
          let coursesList = [];
          const enrolledCourses = <?php echo json_encode($courses); ?>;
          
          if (Array.isArray(enrolledCourses) && enrolledCourses.length) {
            coursesList = enrolledCourses.map(c => ({ 
              course_id: c.course_id, 
              course_name: (c.code ? c.code + ' - ' : '') + (c.name || 'Unknown')
            }));
          } else {
            const courses = data.by_course || [];
            if (Array.isArray(courses) && courses.length) {
              coursesList = courses.map(c => ({ course_id: c.course_id, course_name: c.course_name }));
            } else if (Array.isArray(data.by_assessment) && data.by_assessment.length) {
              const seen = new Map();
              data.by_assessment.forEach(a => {
                if (a.course_id && !seen.has(String(a.course_id))) seen.set(String(a.course_id), { course_id: a.course_id, course_name: null });
              });
              coursesList = Array.from(seen.values());
            }
          }
          if (courseSelect && coursesList.length) {
            courseSelect.innerHTML = '<option value="">Select course</option>' + coursesList.map(c => `<option value="${c.course_id}">${escapeHtml(c.course_name || c.course_id)}</option>`).join('');
            courseSelect.addEventListener('change', async (e) => {
              const cid = e.target.value;
              assessmentSelect.innerHTML = '<option value="">Loading...</option>';
              assessmentSelect.disabled = true;
              if (!cid) {
                assessmentSelect.innerHTML = '<option value="">Select assessment</option>';
                assessmentSelect.disabled = true;
                const leaderboardWrapper = document.getElementById('leaderboardTableWrapper');
                leaderboardWrapper.innerHTML = '<div class="text-xs text-slate-500">Choose a course to see the course leaderboard.</div>';
                return;
              }
              // Load course-level leaderboard immediately on course change
              loadCourseLeaderboard(cid);
              try {
                // Fetch all assessments (expired + active). Try flag, then without, then fallback to local data.
                let items = [];
                let res = await fetch(`assessments.php?course_id=${encodeURIComponent(cid)}&include_expired=1`);
                if (res.ok) {
                  const j = await res.json();
                  items = Array.isArray(j.assessments) ? j.assessments : [];
                }
                if (!items.length) {
                  res = await fetch(`assessments.php?course_id=${encodeURIComponent(cid)}`);
                  if (res.ok) {
                    const j2 = await res.json();
                    items = Array.isArray(j2.assessments) ? j2.assessments : [];
                  }
                }
                if (!items.length && Array.isArray(data.by_assessment) && data.by_assessment.length) {
                  items = data.by_assessment.filter(a => String(a.course_id) === String(cid));
                }
                if (!items.length) {
                  assessmentSelect.innerHTML = '<option value="">No assessments</option>';
                  assessmentSelect.disabled = true;
                  return;
                }
                assessmentSelect.innerHTML = '<option value="">Select assessment</option>' + items.map(a => `<option value="${a.assessment_id}">${escapeHtml(a.assessment_name || a.name || a.code || a.assessment_id)}</option>`).join('');
                assessmentSelect.disabled = false;
                assessmentSelect.onchange = (ev) => loadAssessmentLeaderboard(ev.target.value);
              } catch (err) {
                assessmentSelect.innerHTML = '<option value="">Error</option>';
                assessmentSelect.disabled = true;
              }
            });
            // Do NOT auto-select the first course; wait for the user to choose
          }
          // Populate the chart course select independently so chart filtering is separate
          if (chartCourseSelect && coursesList.length) {
            chartCourseSelect.innerHTML = '<option value="">All courses</option>' + coursesList.map(c => `<option value="${c.course_id}">${escapeHtml(c.course_name || c.course_id)}</option>`).join('');
            chartCourseSelect.addEventListener('change', (e) => {
              const cid = e.target.value;
              if (!cid) {
                // restore overall chart and summary (use the displayed values computed above)
                if (thresholdEl) thresholdEl.textContent = (overall_threshold !== null && overall_threshold !== undefined) ? String(overall_threshold) : '-';
                if (usedEl) usedEl.textContent = (typeof displayed_overall_used !== 'undefined' ? String(displayed_overall_used) : '-');
                if (remainingEl) remainingEl.textContent = (typeof displayed_overall_remaining !== 'undefined' ? String(displayed_overall_remaining) : '-');
                createChart({
                  type: 'bar',
                  data: { labels: overall_labels, datasets: (function(){ let d=[]; if (overall_thresholds_array.some(t=>t>0)) d.push({ label: 'Threshold', data: overall_thresholds_array, type: 'line', borderColor: '#000000', borderDash: [6,4], borderWidth: 2.5, pointRadius: 4.5, pointHoverRadius: 6, pointBackgroundColor: '#000000', pointBorderColor: '#000000', tension: 0, fill: false, order: 0 }); if (overall_labels.length){ d.push({ label: 'Used', data: overall_used_array, backgroundColor: '#ef4444', borderRadius: 6, stack: 'stack1', order: 1 }); d.push({ label: 'Remaining', data: overall_remaining_array, backgroundColor: '#06b6d4', borderRadius: 6, stack: 'stack1', order: 1 }); } return d; })() },
                  options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { beginAtZero: true, stacked: true } }, plugins: { legend: { position: 'top' } } }
                });
                return;
              }
              updateCourseChart(cid);
            });
          }

          // helper: update chart for a selected course using data.by_assessment
          function updateCourseChart(courseId) {
            const leaderboardWrapper = document.getElementById('leaderboardTableWrapper');
            let assessments = Array.isArray(data.by_assessment) ? data.by_assessment.filter(a => String(a.course_id) === String(courseId)) : [];
            if (!assessments.length) {
              // Fallback: if by_assessment does not include course mapping, try using by_course totals
              const courseEntry = Array.isArray(data.by_course) ? data.by_course.find(c => String(c.course_id) === String(courseId)) : null;
              if (courseEntry) {
                const course_used = Number(courseEntry.total_used || 0);
                const course_remaining = Number((typeof courseEntry.remaining !== 'undefined') ? courseEntry.remaining : 0);
                const course_threshold = course_used + course_remaining;
                if (thresholdEl) thresholdEl.textContent = String(course_threshold);
                if (usedEl) usedEl.textContent = String(course_used);
                if (remainingEl) remainingEl.textContent = String(course_remaining);
                try {
                  createChart({
                    type: 'bar',
                    data: { labels: [courseEntry.course_name || courseEntry.course_id], datasets: [ { label: 'Used', data: [course_used], backgroundColor: '#ef4444', borderRadius: 6, stack: 'stack1', order: 1 }, { label: 'Remaining', data: [course_remaining], backgroundColor: '#06b6d4', borderRadius: 6, stack: 'stack1', order: 1 }, (course_threshold > 0 ? { label: 'Threshold', data: [course_threshold], type: 'line', borderColor: '#000000', borderDash: [6,4], borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#000000', pointBorderColor: '#000000', tension: 0, fill: false, order: 0 } : null) ].filter(Boolean) },
                    options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { beginAtZero: true, stacked: true } }, plugins: { legend: { position: 'top' } } }
                  });
                } catch (e) {}
                return;
              }
              leaderboardWrapper.innerHTML = '<div class="text-xs text-slate-500">No token usage for assessments in this course this week.</div>';
              if (thresholdEl) thresholdEl.textContent = '-';
              if (usedEl) usedEl.textContent = '-';
              if (remainingEl) remainingEl.textContent = '-';
              return;
            }
            // build chart data per assessment
            const labels = assessments.map(a => a.assessment_name || a.assessment_id || 'Unassigned');
            const used = assessments.map(a => Number(a.total_used || 0));
            const remaining = assessments.map(a => Number(a.remaining || 0));
            const thresholds = assessments.map(a => Number(a.threshold || 0));
            // compute course summary
            const course_threshold = thresholds.reduce((s, v) => s + (Number(v) || 0), 0);
            const course_used = used.reduce((s, v) => s + (Number(v) || 0), 0);
            const course_remaining = Math.max(0, course_threshold - course_used);
            if (thresholdEl) thresholdEl.textContent = String(course_threshold);
            if (usedEl) usedEl.textContent = String(course_used);
            if (remainingEl) remainingEl.textContent = String(course_remaining);

            // update top chart to show course-specific per-assessment bars
            try {
              createChart({
                type: 'bar',
                data: { labels: labels, datasets: [ { label: 'Used', data: used, backgroundColor: '#ef4444', borderRadius: 6, stack: 'stack1', order: 1 }, { label: 'Remaining', data: remaining, backgroundColor: '#06b6d4', borderRadius: 6, stack: 'stack1', order: 1 }, (thresholds.some(t=>t>0) ? { label: 'Threshold', data: thresholds, type: 'line', borderColor: '#000000', borderDash: [6,4], borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#000000', pointBorderColor: '#000000', tension: 0, fill: false, order: 0 } : null) ].filter(Boolean) },
                options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { beginAtZero: true, stacked: true } }, plugins: { legend: { position: 'top' } } }
              });
            } catch (e) {
              const leaderboardWrapper = document.getElementById('leaderboardTableWrapper');
              leaderboardWrapper.innerHTML = '<div class="text-xs text-red-500">Error rendering chart.</div>';
            }
          }
        } catch (e) {}
      } catch (e) { console.warn('Failed to load token usage breakdown', e); }
    }
    renderTokenChart();
  </script>
</body>
</html>
