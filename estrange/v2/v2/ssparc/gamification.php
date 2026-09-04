<?php
require_once(__DIR__ . '/_sso_bridge.php');

// Fetch courses and assessments from E-STRANGE
$coursesQuery = "SELECT course_id, name FROM course WHERE is_active = 1 ORDER BY name ASC";
$coursesRes = $db->query($coursesQuery);
$courses = [];
if ($coursesRes) {
    while ($row = $coursesRes->fetch_assoc()) {
        $courses[] = $row;
    }
}

$assessmentsQuery = "SELECT assessment_id, course_id, name, submission_file_extension FROM assessment ORDER BY assessment_id DESC LIMIT 50";
$assessmentsRes = $db->query($assessmentsQuery);
$assessments = [];
if ($assessmentsRes) {
    while ($row = $assessmentsRes->fetch_assoc()) {
        $assessments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gamification Dashboard - S-SPARC AI</title>
  <link rel="icon" href="../strange_html_layout_additional_files/icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- DataTables & Select2 -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <style>
    :root { color-scheme: light; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    .rank-medal {
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      border-radius: 999px;
      font-size: 0.75rem;
    }
    .medal-1 { background-color: #fef08a; color: #854d0e; border: 1px solid #facc15; }
    .medal-2 { background-color: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; }
    .medal-3 { background-color: #fed7aa; color: #9a3412; border: 1px solid #fb923c; }
    #leaderboardTable tr.current-user {
      background: linear-gradient(90deg, #eff6ff 0%, #dbeafe 100%) !important;
      font-weight: 600;
      border-left: 4px solid #0f172a;
    }
    .points-badge {
      display: inline-block;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-weight: 700;
      font-size: 0.75rem;
      box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
    }
    /* Select2 Tailwind Light Styling */
    .select2-container--default .select2-selection--single {
      height: 38px;
      border: 1px solid #cbd5e1;
      border-radius: 0.5rem;
      display: flex;
      align-items: center;
      background-color: #ffffff;
      padding-left: 0.4rem;
      font-size: 0.8rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      color: #0f172a;
      line-height: 36px;
      font-weight: 500;
      padding-left: 0.2rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 36px;
      right: 6px;
    }
    .select2-dropdown {
      border: 1px solid #cbd5e1;
      border-radius: 0.5rem;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      background-color: #ffffff;
      font-size: 0.8rem;
      z-index: 9999;
    }
    .select2-results__option--highlighted[aria-selected] {
      background-color: #0f172a !important;
      color: #ffffff !important;
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
  
  <?php renderSSOHeader('gamification', 'Gamification Dashboard'); ?>

  <main class="flex-1 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      
      <!-- Section 1: Weekly Token Usage & Threshold Chart -->
      <section class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 shadow-sm">
        <div class="flex flex-wrap justify-between items-start gap-3 mb-4">
          <div>
            <h1 class="text-lg font-bold text-slate-900">Weekly Token Usage & Dynamic Quota</h1>
            <p class="text-xs text-slate-600 mt-1 max-w-2xl">
              Overview of student token consumption against course assessment thresholds. High-similarity cache hits (&ge;90%) consume 0 tokens and preserve your quota.
            </p>
          </div>
          <div class="w-40">
            <select id="periodSelect" class="min-w-[200px] shrink-0 select2 w-full">
              <option value="week">Current Week</option>
              <option value="all">All Time</option>
            </select>
          </div>
        </div>

        <div class="grid gap-6 md:grid-cols-[2fr_1fr] items-center">
          <div class="h-64 relative">
            <canvas id="tokenChart"></canvas>
          </div>
          <div class="space-y-3 text-sm text-slate-700 bg-slate-50 border border-slate-200 rounded-xl p-4" id="tokenSummary">
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
              <span class="text-xs text-slate-500 font-medium">Dynamic Threshold</span>
              <span class="font-bold text-slate-900 font-mono"><span id="dash-token-threshold">2,500</span> tokens</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
              <span class="text-xs text-slate-500 font-medium">Tokens Used</span>
              <span class="font-bold text-rose-600 font-mono"><span id="dash-token-used">0</span> tokens</span>
            </div>
            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
              <span class="text-xs text-slate-500 font-medium">Remaining Quota</span>
              <span class="font-bold text-emerald-700 font-mono"><span id="dash-token-remaining">2,500</span> tokens</span>
            </div>
            <div class="pt-1">
              <label class="text-xs text-slate-500 font-medium block mb-1">Filter Chart by Course</label>
              <select id="chartCourseSelect" class="min-w-[200px] shrink-0 select2 w-full">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?= htmlspecialchars($c['course_id']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </section>

      <!-- Section 2: How Points Work -->
      <section class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm">
        <h2 class="text-base font-bold text-slate-900 mb-2">Efficiency Scoring System</h2>
        <div class="text-xs text-slate-600 space-y-2 leading-relaxed">
          <p>Gamification scoring is determined by backend policy models evaluating token efficiency per assessment:</p>
          <ul class="list-disc pl-5 space-y-1">
            <li><strong>Dynamic Quota</strong>: Automatically derived from cohort consumption metrics per assessment window.</li>
            <li><strong>Full Score (100 pts)</strong>: Maintained while your consumption remains within the optimal threshold.</li>
            <li><strong>0-Token Vector Cache Hits</strong>: Vector queries matching previous solutions with similarity &ge;90% consume 0 tokens.</li>
          </ul>
        </div>
      </section>

      <!-- Section 3: Course & Assessment Leaderboard -->
      <section class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 shadow-sm" id="leaderboard">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
          <div>
            <h2 class="text-base font-bold text-slate-900">Student Efficiency Leaderboard</h2>
            <p class="text-xs text-slate-500">Rankings based on token efficiency and academic engagement per course assessment:</p>
          </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
          <div>
            <label class="text-xs font-semibold text-slate-600 block mb-1">Course</label>
            <select id="leaderboardCourseSelect" class="min-w-[200px] shrink-0 select2 w-full">
              <option value="">-- Select Course --</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= htmlspecialchars($c['course_id']) ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-slate-600 block mb-1">Assessment Assignment</label>
            <select id="leaderboardSelect" class="min-w-[200px] shrink-0 select2 w-full">
              <option value="">-- All Assessments --</option>
              <?php foreach ($assessments as $a): ?>
                <option value="<?= htmlspecialchars($a['assessment_id']) ?>">#<?= htmlspecialchars($a['assessment_id']) ?>: <?= htmlspecialchars($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div id="leaderboardContainer" class="text-sm text-slate-700">
          <div id="leaderboardTableWrapper" class="overflow-x-auto">
            <table id="leaderboardTable" class="w-full text-left text-sm">
              <thead>
                <tr class="border-b border-slate-200 text-xs font-bold text-slate-500 uppercase">
                  <th class="py-2.5 px-3" style="width: 80px;">Rank</th>
                  <th class="py-2.5 px-3">Student</th>
                  <th class="py-2.5 px-3">Tokens Used</th>
                  <th class="py-2.5 px-3 text-right">Score</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <!-- Dynamic Content Loaded via SSOT API -->
              </tbody>
            </table>
          </div>
        </div>
      </section>

    </div>
  </main>

  <script>
    const FASTAPI_URL = "http://127.0.0.1:5000";
    const SSO_USER_ID = "<?= htmlspecialchars($sso_user_id) ?>";
    const CURRENT_USERNAME = "<?= htmlspecialchars($sso_username) ?>";

    let chartInstance = null;

    $(document).ready(function() {
      // Initialize Select2 on all dropdowns
      $('#periodSelect').select2({ width: '100%', minimumResultsForSearch: Infinity });
      $('#chartCourseSelect').select2({ width: '100%' });
      $('#leaderboardCourseSelect').select2({ width: '100%' });
      $('#leaderboardSelect').select2({ width: '100%' });

      $('#periodSelect').on('change', function() {
        renderTokenChart();
      });

      $('#chartCourseSelect').on('change', function() {
        renderTokenChart();
      });

      $('#leaderboardCourseSelect').on('change', function() {
        loadLeaderboardData($(this).val(), null);
      });

      $('#leaderboardSelect').on('change', function() {
        const cid = $('#leaderboardCourseSelect').val();
        loadLeaderboardData(cid, $(this).val());
      });

      // Init chart and leaderboard
      renderTokenChart();
      loadLeaderboardData();
    });

    async function renderTokenChart() {
      const thresholdEl = document.getElementById('dash-token-threshold');
      const usedEl = document.getElementById('dash-token-used');
      const remEl = document.getElementById('dash-token-remaining');

      const period = $('#periodSelect').val() || 'week';
      const courseId = $('#chartCourseSelect').val() || '';

      let url = `${FASTAPI_URL}/api/gamification?period=${period}`;
      if (courseId) url += `&course_id=${courseId}`;

      try {
        const res = await fetch(url, { headers: { 'X-User-ID': SSO_USER_ID } });
        if (!res.ok) return;
        const data = await res.json();

        const labels = data.labels || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        const usedData = data.used_tokens || [0, 0, 0, 0, 0, 0, 0];
        const remainingData = data.remaining_tokens || [2500, 2500, 2500, 2500, 2500, 2500, 2500];
        const thresholdVal = Number(data.threshold || 2500);
        const totalUsed = Number(data.total_used_tokens || usedData.reduce((a, b) => a + b, 0));
        const totalRem = Number(data.remaining_quota || data.remaining_tokens_total || Math.max(0, thresholdVal - totalUsed));

        thresholdEl.textContent = thresholdVal.toLocaleString();
        usedEl.textContent = totalUsed.toLocaleString();
        remEl.textContent = totalRem.toLocaleString();

        const ctx = document.getElementById('tokenChart').getContext('2d');
        if (chartInstance) {
          chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [
              {
                label: 'Tokens Used',
                data: usedData,
                backgroundColor: '#ef4444',
                borderRadius: 4
              },
              {
                label: 'Remaining Quota',
                data: remainingData,
                backgroundColor: '#06b6d4',
                borderRadius: 4
              },
              {
                type: 'line',
                label: 'Dynamic Threshold',
                data: labels.map(() => thresholdVal),
                borderColor: '#0f172a',
                borderWidth: 2,
                borderDash: [5, 5],
                pointRadius: 0,
                fill: false
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { stacked: true, grid: { display: false } },
              y: { beginAtZero: true, stacked: true, grid: { color: '#f1f5f9' } }
            },
            plugins: {
              legend: { position: 'top', labels: { boxWidth: 12, font: { family: 'Inter', size: 11 } } }
            }
          }
        });
      } catch (err) {
        console.error('Failed to load gamification token breakdown:', err);
      }
    }

    async function loadLeaderboardData(courseId = null, assessmentId = null) {
      try {
        let url = `${FASTAPI_URL}/api/gamification/leaderboard`;
        const params = [];
        if (assessmentId) params.push(`assessment_id=${encodeURIComponent(assessmentId)}`);
        if (courseId) params.push(`course_id=${encodeURIComponent(courseId)}`);
        if (params.length > 0) url += `?${params.join('&')}`;

        const res = await fetch(url, { headers: { 'X-User-ID': SSO_USER_ID } });
        const tbody = document.querySelector('#leaderboardTable tbody');
        tbody.innerHTML = '';

        if (!res.ok) {
          tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-sm font-medium text-slate-500">Select a course to view leaderboard rankings.</td></tr>';
          return;
        }

        const data = await res.json();
        const rows = data.leaderboard || [];

        if (rows.length === 0) {
          tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-sm font-medium text-slate-500">No scoring data available for this category yet.</td></tr>';
          return;
        }

        rows.forEach(r => {
          const isMe = (r.username === CURRENT_USERNAME || r.user_id === SSO_USER_ID);
          const tr = document.createElement('tr');
          if (isMe) tr.className = 'current-user';

          let rankDisplay = `<span class="font-bold text-slate-700">${r.rank}</span>`;
          if (r.rank === 1) rankDisplay = '<span class="rank-medal medal-1">1</span>';
          else if (r.rank === 2) rankDisplay = '<span class="rank-medal medal-2">2</span>';
          else if (r.rank === 3) rankDisplay = '<span class="rank-medal medal-3">3</span>';

          tr.innerHTML = `
            <td class="py-3 px-3">${rankDisplay}</td>
            <td class="py-3 px-3 font-medium ${isMe ? 'text-slate-900 font-bold' : 'text-slate-800'}">${r.username || r.user_id}</td>
            <td class="py-3 px-3 font-mono text-slate-600">${Number(r.tokens_used || r.total_tokens || 0).toLocaleString()}</td>
            <td class="py-3 px-3 text-right"><span class="points-badge">${Number(r.points || r.final_points || 100).toFixed(1)} pts</span></td>
          `;
          tbody.appendChild(tr);
        });
      } catch (e) {
        console.error('Failed to load leaderboard data:', e);
      }
    }
  </script>
</body>
</html>

