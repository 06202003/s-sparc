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
          <a class="text-slate-700 hover:text-slate-900" href="courses.php">Courses</a>
          <a class="text-slate-700 hover:text-slate-900" href="dashboard.php">Environmental impact</a>
          <a class="text-slate-700 hover:text-slate-900" href="chat.php">Chat</a>
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
              <p><span class="inline-block w-32 text-slate-500">Active points</span> <span id="dash-token-points">-</span></p>
            </div>
          </div>
        </section>

        <section class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-900 mb-1">How points work</h2>
          <p class="text-xs text-slate-500">Points are equivalent to your remaining tokens in the current week. Every GPT call consumes tokens and reduces your remaining quota, while retrieval-only answers from the database are free.</p>
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

      try {
        const res = await fetch('gamification.php', { method: 'GET' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !data.gamification) return;
        const g = data.gamification;
        const total = Number(g.total_tokens ?? 0) || 0;
        const remaining = Number(g.remaining_tokens ?? 0) || 0;
        const points = Number(g.points ?? 0) || 0;
        const used = g.used_tokens != null ? (Number(g.used_tokens) || 0) : (total - remaining);

        if (totalEl) totalEl.textContent = total.toString();
        if (usedEl) usedEl.textContent = used.toString();
        if (remainingEl) remainingEl.textContent = remaining.toString();
        if (pointsEl) pointsEl.textContent = points.toString();

        const ctx = canvas.getContext('2d');
        // eslint-disable-next-line no-undef
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: ['Used', 'Remaining', 'Active points'],
            datasets: [
              {
                label: 'Tokens / points',
                data: [used, remaining, points],
                backgroundColor: [
                  'rgba(239, 68, 68, 0.85)',
                  'rgba(34, 197, 94, 0.85)',
                  'rgba(59, 130, 246, 0.85)',
                ],
                borderRadius: 8,
                borderSkipped: false,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label(context) {
                    const value = context.parsed.y || 0;
                    return value.toLocaleString('en-US') + ' tokens';
                  },
                },
              },
            },
            scales: {
              x: { grid: { display: false } },
              y: {
                beginAtZero: true,
                ticks: {
                  callback(value) {
                    return value.toLocaleString('en-US');
                  },
                },
              },
            },
          },
        });
      } catch (e) {
        console.warn('Failed to load gamification data', e);
      }
    }

    renderTokenChart();
  </script>
</body>
</html>
