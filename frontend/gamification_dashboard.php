<?php
require __DIR__ . '/config.php';

$loggedIn = !empty($_SESSION['flask_cookie']);
$username = $_SESSION['username'] ?? 'Guest';

if (!$loggedIn) {
  header('Location: login.php');
  exit;
}
?>
<!doctype html>
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
  <!-- jQuery, DataTables, Select2 -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
  <style>
    /* Banded rows for DataTables using Tailwind-like colors */
    .odd-row { background-color: #f8fafc; }
    .even-row { background-color: #ffffff; }
    .select2-container--default .select2-selection--single { height: 38px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; }
    /* DataTables sizing to match page font (smaller) */
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
  </style>
  <style>
    body { font-family: 'Manrope', system-ui, -apple-system, sans-serif; }
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
            <div class="text-xs text-slate-500">Token quota & points for user: <strong><?= htmlspecialchars($username) ?></strong></div>
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
          <h1 class="text-lg font-semibold text-slate-900 mb-1">Weekly token quota</h1>
          <p class="text-sm text-slate-600 mb-4">Overview of your weekly token usage and active points. Retrieval-only answers from the database do not reduce your quota.</p>
          <div class="grid gap-4 md:grid-cols-[2fr_1fr] items-center">
            <div class="h-48">
              <canvas id="tokenChart"></canvas>
            </div>
            <div class="space-y-2 text-sm text-slate-700" id="tokenSummary">
              <p><span class="inline-block w-32 text-slate-500">Total quota</span> <span id="dash-token-total">-</span> tokens</p>
              <p><span class="inline-block w-32 text-slate-500">Used</span> <span id="dash-token-used">-</span> tokens</p>
              <p><span class="inline-block w-32 text-slate-500">Remaining</span> <span id="dash-token-remaining">-</span> tokens</p>
              <p><span class="inline-block w-32 text-slate-500">Active points</span> <span id="dash-token-points">-</span> points</p>
              <div class="pt-2">
                <label class="text-xs text-slate-500">View</label>
                <select id="viewSelect" class="block mt-1 w-full rounded-md border-slate-200 text-sm">
                  <option value="accumulated">Accumulated (all)</option>
                  <option value="by_course">By Course</option>
                  <option value="by_assessment">By Assessment</option>
                </select>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
          <!-- <h2 class="text-sm font-semibold text-slate-900 mb-1">How points work</h2> -->
          <h1 class="text-lg font-semibold text-slate-900 mb-1">How points work</h1>
          <p class="text-xs text-slate-500">Points are equivalent to your remaining tokens in the current week. Every GPT call consumes tokens and reduces your remaining quota, while retrieval-only answers from the database are free.</p>
        </section>

        <section class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
          <!-- <h2 class="text-sm font-semibold text-slate-900 mb-3">Leaderboard</h2> -->
          <h1 class="text-lg font-semibold text-slate-900 mb-1">Leaderboard</h1>
          <div class="flex items-center gap-3 mb-3">
            <label class="text-xs text-slate-500">Course</label>
            <select id="leaderboardCourseSelect" class="block mt-1 w-full rounded-md border-slate-200 text-sm max-w-md">
              <option value="">Select course</option>
            </select>
            <label class="text-xs text-slate-500">Assessment</label>
            <select id="leaderboardSelect" class="block mt-1 w-full rounded-md border-slate-200 text-sm max-w-md" disabled>
              <option value="">Select assessment</option>
            </select>
          </div>
          <div id="leaderboardContainer" class="text-sm text-slate-700">
            <div class="text-xs text-slate-500 mb-2">Top participants for selected assessment will appear here.</div>
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
      const usedEl = document.getElementById('dash-token-used');
      const remainingEl = document.getElementById('dash-token-remaining');
      const pointsEl = document.getElementById('dash-token-points');

      let chart = null;
      function createChart(cfg) {
        if (chart) {
          try { chart.destroy(); } catch (e) {}
          chart = null;
        }
        const ctx = document.getElementById('tokenChart').getContext('2d');
        chart = new Chart(ctx, cfg);
      }

      try {
        const res = await fetch('token_usage_breakdown.php', { method: 'GET' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data) return;

        // Compute totals depending on view. For "accumulated" prefer summing per-assessment quotas
        function computeTotalsForView(view) {
          const PER_ITEM_QUOTA = 2000;
          let totalQuota = PER_ITEM_QUOTA;
          let totalUsed = Number((data.total && data.total.total_used) || 0) || 0;

          if (view === 'accumulated') {
            if (Array.isArray(data.by_assessment) && data.by_assessment.length > 0) {
              totalQuota = data.by_assessment.length * PER_ITEM_QUOTA;
              totalUsed = data.by_assessment.reduce((s, a) => s + Number(a.total_used || 0), 0);
            } else if (Array.isArray(data.by_course) && data.by_course.length > 0) {
              // sum assessments_count across courses
              const count = data.by_course.reduce((s, c) => s + (Number(c.assessments_count || 0) || 0), 0) || data.by_course.length;
              totalQuota = count * PER_ITEM_QUOTA;
              totalUsed = data.by_course.reduce((s, c) => s + Number(c.total_used || 0), 0);
            } else {
              totalQuota = PER_ITEM_QUOTA;
              totalUsed = Number((data.total && data.total.total_used) || 0) || 0;
            }
          } else if (view === 'by_course') {
            if (Array.isArray(data.by_course) && data.by_course.length > 0) {
              const count = data.by_course.reduce((s, c) => s + (Number(c.assessments_count || 0) || 0), 0) || data.by_course.length;
              totalQuota = count * PER_ITEM_QUOTA;
              totalUsed = data.by_course.reduce((s, c) => s + Number(c.total_used || 0), 0);
            } else {
              totalQuota = PER_ITEM_QUOTA;
              totalUsed = Number((data.total && data.total.total_used) || 0) || 0;
            }
          } else if (view === 'by_assessment') {
            totalQuota = (Array.isArray(data.by_assessment) && data.by_assessment.length > 0) ? data.by_assessment.length * PER_ITEM_QUOTA : PER_ITEM_QUOTA;
            totalUsed = (Array.isArray(data.by_assessment) && data.by_assessment.length > 0) ? data.by_assessment.reduce((s, a) => s + Number(a.total_used || 0), 0) : (Number((data.total && data.total.total_used) || 0) || 0);
          }

          const remaining = Math.max(0, totalQuota - totalUsed);
          return { totalQuota, totalUsed, remaining };
        }

        // Update summary panel
        function updateSummary(view) {
          const t = computeTotalsForView(view);
          if (totalEl) totalEl.textContent = String(t.totalQuota);
          if (usedEl) usedEl.textContent = String(t.totalUsed);
          if (remainingEl) remainingEl.textContent = String(t.remaining);
          if (pointsEl) pointsEl.textContent = String(t.remaining);
        }

        function renderAccumulated() {
          const t = computeTotalsForView('accumulated');
          createChart({
            type: 'bar',
            data: { labels: ['Used', 'Remaining'], datasets: [{ label: 'Tokens', data: [t.totalUsed, t.remaining], backgroundColor: ['#ef4444', '#10b981'], borderRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
          });
          updateSummary('accumulated');
        }

        function renderByCourse() {
          const items = (data.by_course || []).map(c => ({ name: c.course_name || c.course_id || 'Unassigned', used: Number(c.total_used || 0), count: Number(c.assessments_count || 0), remaining: Math.max(0, (Number(c.assessments_count || 0) * 2000) - Number(c.total_used || 0)) }));
          const labels = items.map(i => i.name);
          const used = items.map(i => i.used);
          const remaining = items.map(i => i.remaining);
          createChart({
            type: 'bar',
            data: { labels, datasets: [
              { label: 'Used', data: used, backgroundColor: '#ef4444', borderRadius: 6, stack: 'stack1' },
              { label: 'Remaining', data: remaining, backgroundColor: '#06b6d4', borderRadius: 6, stack: 'stack1' }
            ] },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { beginAtZero: true, stacked: true } } }
          });
          updateSummary('by_course');
        }

        function renderByAssessment() {
          const items = (data.by_assessment || []).map(a => ({ name: a.assessment_name || a.assessment_id || 'Unassigned', used: Number(a.total_used || 0), remaining: Math.max(0, 2000 - Number(a.total_used || 0)) }));
          const labels = items.map(i => i.name);
          const used = items.map(i => i.used);
          const remaining = items.map(i => i.remaining);
          createChart({
            type: 'bar',
            data: { labels, datasets: [
              { label: 'Used', data: used, backgroundColor: '#ef4444', borderRadius: 6, stack: 'stack1' },
              { label: 'Remaining', data: remaining, backgroundColor: '#06b6d4', borderRadius: 6, stack: 'stack1' }
            ] },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { beginAtZero: true, stacked: true } }, plugins: { legend: { position: 'top' } } }
          });
          updateSummary('by_assessment');
        }

        const viewSelect = document.getElementById('viewSelect');
        function applyCurrentView() {
          const v = viewSelect ? viewSelect.value : 'accumulated';
          if (v === 'by_course') renderByCourse();
          else if (v === 'by_assessment') renderByAssessment();
          else renderAccumulated();
        }

        if (viewSelect) viewSelect.addEventListener('change', () => applyCurrentView());
        applyCurrentView();

        // Populate leaderboard select from assessments
        const leaderboardSelect = document.getElementById('leaderboardSelect');
        const leaderboardWrapper = document.getElementById('leaderboardTableWrapper');
        const currentUser = '<?= htmlspecialchars($username) ?>';
        async function loadLeaderboard(assessmentId) {
          if (!assessmentId) {
            leaderboardWrapper.innerHTML = '<div class="text-xs text-slate-500">Choose an assessment to see the leaderboard.</div>';
            return;
          }
          try {
            const res = await fetch(`assessment_leaderboard.php?assessment_id=${encodeURIComponent(assessmentId)}`);
            if (!res.ok) {
              leaderboardWrapper.innerHTML = '<div class="text-xs text-red-500">Failed to load leaderboard.</div>';
              return;
            }
            const json = await res.json();
            if (!json || !Array.isArray(json.leaderboard)) {
              leaderboardWrapper.innerHTML = '<div class="text-xs text-slate-500">No leaderboard data.</div>';
              return;
            }
            const rows = json.leaderboard || [];
            // render DataTable container (no horizontal scroll wrapper)
            leaderboardWrapper.innerHTML = '<div class="w-full"><table id="leaderboardTable" class="w-full text-left text-sm" style="width:100%; table-layout:fixed;"></table></div>';

            // destroy previous instance if exists
            if (window.leaderboardTable && $.fn.DataTable && $.fn.DataTable.isDataTable('#leaderboardTable')) {
              try { window.leaderboardTable.destroy(); } catch (e) {}
            }

            // prepare data for DataTables
            const dtData = rows.map(r => ({ rank: r.rank, username: r.username || r.user_id || 'Unknown', points: r.points }));

            // initialize DataTable with search, pagination and banded rows
            window.leaderboardTable = $('#leaderboardTable').DataTable({
              data: dtData,
              columns: [
                { data: 'rank', title: 'Rank', className: 'py-2' },
                { data: 'username', title: 'User', className: 'py-2', render: function(data, type, row) { return escapeHtml(data); } },
                { data: 'points', title: 'Points', className: 'py-2' }
              ],
              paging: true,
              pageLength: 10,
              lengthChange: true,
              searching: true,
              info: true,
              ordering: false,
              responsive: true,
              language: { search: "Search:", emptyTable: "No leaderboard data." },
              createdRow: function(row, data, dataIndex) {
                // apply banded row classes (odd/even)
                if (dataIndex % 2 === 0) $(row).addClass('even-row'); else $(row).addClass('odd-row');
              },
              dom: '<"top"f>rt<"bottom"lip>'
            });

            if (json.user_rank) {
              const ur = `<div class="mt-2 text-xs text-slate-600">Your rank: <strong>${json.user_rank.rank}</strong> — <strong>${escapeHtml(json.user_rank.points + ' pts')}</strong></div>`;
              leaderboardWrapper.insertAdjacentHTML('beforeend', ur);
            }
          } catch (e) {
            leaderboardWrapper.innerHTML = '<div class="text-xs text-red-500">Error loading leaderboard.</div>';
          }
        }

        function escapeHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

        // populate courses select from by_course
        try {
          const courses = data.by_course || [];
          const courseSelect = document.getElementById('leaderboardCourseSelect');
          const assessmentSelect = document.getElementById('leaderboardSelect');
          if (courseSelect && courses.length) {
            courseSelect.innerHTML = '<option value="">Select course</option>' + courses.map(c => `<option value="${c.course_id}">${escapeHtml(c.course_name || c.course_id)}</option>`).join('');
            courseSelect.addEventListener('change', async (e) => {
              const cid = e.target.value;
              assessmentSelect.innerHTML = '<option value="">Loading...</option>';
              assessmentSelect.disabled = true;
              if (!cid) {
                assessmentSelect.innerHTML = '<option value="">Select assessment</option>';
                assessmentSelect.disabled = true;
                loadLeaderboard('');
                return;
              }
              try {
                // fetch assessments for course via proxy
                const res = await fetch(`assessments.php?course_id=${encodeURIComponent(cid)}`);
                if (!res.ok) {
                  assessmentSelect.innerHTML = '<option value="">Failed to load</option>';
                  assessmentSelect.disabled = true;
                  return;
                }
                const j = await res.json();
                const items = j.assessments || [];
                if (!items.length) {
                  assessmentSelect.innerHTML = '<option value="">No assessments</option>';
                  assessmentSelect.disabled = true;
                  return;
                }
                assessmentSelect.innerHTML = '<option value="">Select assessment</option>' + items.map(a => `<option value="${a.assessment_id}">${escapeHtml(a.name || a.code || a.assessment_id)}</option>`).join('');
                assessmentSelect.disabled = false;
                assessmentSelect.onchange = (ev) => loadLeaderboard(ev.target.value);
                // auto-select first
                loadLeaderboard(items[0].assessment_id);
              } catch (err) {
                assessmentSelect.innerHTML = '<option value="">Error</option>';
                assessmentSelect.disabled = true;
              }
            });
            // auto-select first course and trigger change to load assessments
            courseSelect.value = courses[0].course_id;
            courseSelect.dispatchEvent(new Event('change'));
          }
        } catch (e) {
          // ignore
        }
      } catch (e) {
        console.warn('Failed to load token usage breakdown', e);
      }
    }

    renderTokenChart();
  </script>
</body>
</html>
