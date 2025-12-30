<?php
require __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$backendBase = backend_base();
$httpClient = new Client([
    'base_uri' => $backendBase . '/',
    'timeout'  => 15,
]);

$loggedIn = !empty($_SESSION['flask_cookie']);
$username = $_SESSION['username'] ?? 'Guest';

if (!$loggedIn) {
    header('Location: login.php');
    exit;
}

$days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
if ($days <= 0) {
  $days = 30;
}

$scope = isset($_GET['scope']) ? trim(strtolower($_GET['scope'])) : 'all';
if ($scope === '') {
  $scope = 'all';
}
$selectedCourseId = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$selectedAssessmentId = isset($_GET['assessment_id']) ? trim($_GET['assessment_id']) : '';

$impact = null;
$error  = null;
$courses = [];
$assessments = [];
$assessmentsByCourse = [];

try {
    // Ambil daftar courses & assessments untuk filter
    $optionsBase = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Cookie'       => $_SESSION['flask_cookie'],
      ],
    ];
    // Courses
    $respC = $httpClient->request('GET', 'courses', $optionsBase);
    $dataC = json_decode((string) $respC->getBody(), true);
    if (is_array($dataC) && !empty($dataC['courses']) && is_array($dataC['courses'])) {
      $courses = $dataC['courses'];
    }
    // Assessments
    $respA = $httpClient->request('GET', 'assessments', $optionsBase);
    $dataA = json_decode((string) $respA->getBody(), true);
    if (is_array($dataA) && !empty($dataA['assessments']) && is_array($dataA['assessments'])) {
      $assessments = $dataA['assessments'];
      foreach ($assessments as $a) {
        $cid = (string)($a['course_id'] ?? '');
        if ($cid === '') continue;
        if (!isset($assessmentsByCourse[$cid])) {
          $assessmentsByCourse[$cid] = [];
        }
        $assessmentsByCourse[$cid][] = $a;
      }
    }

    // Query ke impact-summary dengan filter
    $options = $optionsBase;
    $options['query'] = [
      'days'         => $days,
      'scope'        => $scope,
      'course_id'    => $selectedCourseId !== '' ? $selectedCourseId : null,
      'assessment_id'=> $selectedAssessmentId !== '' ? $selectedAssessmentId : null,
    ];

    $resp   = $httpClient->request('GET', 'impact-summary', $options);
    $status = $resp->getStatusCode();
    $body   = (string) $resp->getBody();
    if ($status >= 200 && $status < 300) {
        $data = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $impact = $data;
        } else {
            $error = 'Gagal membaca respons server.';
        }
    } else {
        $error = 'Server mengembalikan status ' . $status;
    }
} catch (RequestException $e) {
    $resp = $e->getResponse();
    $status = $resp ? $resp->getStatusCode() : null;
    if ($resp) {
        $body = (string) $resp->getBody();
        $data = json_decode($body, true);
        $error = $data['error'] ?? $body;
    } else {
        $error = $e->getMessage();
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function fmt_number($value, $decimals = 2) {
    if (!is_numeric($value)) {
        return '-';
    }
    return number_format((float) $value, $decimals, ',', '.');
}

$totals = $impact['totals'] ?? ['energy_wh' => 0, 'energy_kwh' => 0, 'carbon_kg' => 0, 'water_ml' => 0];
$daily  = $impact['daily'] ?? [];
$rangeDays = $impact['range_days'] ?? $days;

// Siapkan data untuk grafik (Chart.js)
$labels = [];
$energySeries = [];
$carbonSeries = [];
$waterSeries = [];
foreach ($daily as $row) {
  $labels[] = $row['day'];
  $energySeries[] = (float)($row['energy_kwh'] ?? 0);
  $carbonSeries[] = (float)($row['carbon_kg'] ?? 0);
  $waterSeries[] = (float)(($row['water_ml'] ?? 0) / 1000.0);
}

// Label filter aktif
$activeFilterLabel = 'Semua mata kuliah & assessment';
if ($scope === 'course' && $selectedCourseId !== '') {
  foreach ($courses as $c) {
    if ((string)($c['course_id'] ?? '') === $selectedCourseId) {
      $activeFilterLabel = 'Mata kuliah: ' . ($c['name'] ?? ($c['code'] ?? $selectedCourseId));
      break;
    }
  }
} elseif ($scope === 'assessment' && $selectedAssessmentId !== '') {
  foreach ($assessments as $a) {
    if ((string)($a['assessment_id'] ?? '') === $selectedAssessmentId) {
      $courseName = '';
      $cid = (string)($a['course_id'] ?? '');
      foreach ($courses as $c) {
        if ((string)($c['course_id'] ?? '') === $cid) {
          $courseName = $c['name'] ?? ($c['code'] ?? $cid);
          break;
        }
      }
      $activeFilterLabel = 'Assessment: ' . ($a['name'] ?? ($a['code'] ?? $selectedAssessmentId));
      if ($courseName !== '') {
        $activeFilterLabel .= ' (Mata kuliah: ' . $courseName . ')';
      }
      break;
    }
  }
}
?><!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Environmental Impact</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <style>
    .odd-row { background-color: #f8fafc; }
    .even-row { background-color: #ffffff; }
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
          <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white grid place-items-center font-semibold">CO₂</div>
          <div>
            <div class="text-lg font-semibold">Environmental Impact Dashboard</div>
            <div class="text-xs text-slate-500">Summary of environmental footprint for user: <strong><?= htmlspecialchars($username) ?></strong></div>
          </div>
        </div>
        <nav class="flex items-center gap-3 text-sm font-medium">
          <a class="text-slate-400 hover:text-slate-700" href="courses.php">Courses</a>
          <a class="text-slate-400 hover:text-slate-700" href="gamification_dashboard.php">Gamification</a>
          <a class="text-slate-900 active" href="dashboard.php">Environmental impact</a>
          <a href="logout.php" class="ml-2 inline-flex items-center gap-2 rounded-full bg-red-800 text-white px-3 py-1 hover:bg-red-600 shadow-sm">Logout</a>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
        <div class="flex flex-col gap-3">
          <div>
            <h1 class="text-xl font-semibold text-slate-900">Summary of last <?= $rangeDays ?> days</h1>
            <p class="text-sm text-slate-600">Data taken from GPT token usage calculations and environmental impact logs.</p>
            <p class="text-xs text-slate-500 mt-1">Active filter: <?= htmlspecialchars($activeFilterLabel) ?></p>
          </div>
          <form method="get" class="flex flex-col sm:flex-row sm:items-center gap-2 text-sm">
            <div class="flex items-center gap-2">
              <label for="days" class="text-slate-600">Range of days:</label>
              <select id="days" name="days" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm select2">
                <?php foreach ([7, 30, 90] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $opt === $rangeDays ? 'selected' : '' ?>><?= $opt ?> days</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label for="scope" class="text-slate-600">Filter:</label>
              <select id="scope" name="scope" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm select2">
                <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>All</option>
                <option value="course" <?= $scope === 'course' ? 'selected' : '' ?>>Per course</option>
                <option value="assessment" <?= $scope === 'assessment' ? 'selected' : '' ?>>Per assessment</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <select id="course_id" name="course_id" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm select2" <?= $scope === 'course' || $scope === 'assessment' ? '' : 'disabled' ?>>
                <option value="">Select course</option>
                <?php foreach ($courses as $c): ?>
                  <?php $cid = (string)($c['course_id'] ?? ''); ?>
                  <option value="<?= htmlspecialchars($cid) ?>" <?= $cid === $selectedCourseId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['code'] . ' - ' . ($c['name'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <select id="assessment_id" name="assessment_id" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-sm select2" <?= $scope === 'assessment' ? '' : 'disabled' ?>>
                <option value="">Select assessment</option>
                <?php foreach ($assessments as $a): ?>
                  <?php
                    $aid = (string)($a['assessment_id'] ?? '');
                    $cid = (string)($a['course_id'] ?? '');
                    $label = ($a['code'] ?? $aid) . ' - ' . ($a['name'] ?? '');
                  ?>
                  <option value="<?= htmlspecialchars($aid) ?>" data-course="<?= htmlspecialchars($cid) ?>" <?= $aid === $selectedAssessmentId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="rounded-lg bg-slate-900 text-white px-3 py-1 hover:bg-slate-800">Apply</button>
          </form>
        </div>

        <?php if ($error): ?>
          <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-900 px-4 py-3 text-sm">
            An error occurred while fetching data: <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Energy</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900"><?= fmt_number($totals['energy_kwh'] ?? 0, 3) ?> kWh</div>
            <p class="mt-1 text-xs text-slate-500">Estimated total electricity consumption (kWh) from model inference.</p>
          </div>
          <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Carbon footprint</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900"><?= fmt_number($totals['carbon_kg'] ?? 0, 3) ?> kg CO₂e</div>
            <p class="mt-1 text-xs text-slate-500">Estimated carbon dioxide equivalent emissions due to energy consumption.</p>
          </div>
          <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Water usage</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900"><?= fmt_number(($totals['water_ml'] ?? 0) / 1000, 3) ?> L</div>
            <p class="mt-1 text-xs text-slate-500">Estimated total water usage (liters) for data center cooling.</p>
          </div>
          <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Energy intensity</div>
            <?php
              $totalJobs = max(count($daily), 1);
              $avgEnergyPerDay = ($totals['energy_kwh'] ?? 0) / $totalJobs;
            ?>
            <div class="mt-2 text-2xl font-semibold text-slate-900"><?= fmt_number($avgEnergyPerDay, 3) ?> kWh/day</div>
            <p class="mt-1 text-xs text-slate-500">Average energy consumption per day during this period.</p>
          </div>
        </section>

        <section class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-slate-900">Daily details</h2>
            <p class="text-xs text-slate-500">Daily summary of environmental impact logs.</p>
          </div>
          <div class="mb-4">
            <canvas id="impactChart" height="120"></canvas>
          </div>
          <div class="overflow-x-auto">
            <table id="impactDailyTable" class="min-w-full text-sm display">
              <thead>
                <tr class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                  <th class="px-3 py-2 text-left">Date</th>
                  <th class="px-3 py-2 text-right">Energy (kWh)</th>
                  <th class="px-3 py-2 text-right">Carbon (kg CO₂e)</th>
                  <th class="px-3 py-2 text-right">Water (L)</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($daily)): ?>
                <tr>
                  <td colspan="4" class="px-3 py-4 text-center text-slate-500">No environmental impact data recorded for this period.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($daily as $row): ?>
                  <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-3 py-2 text-slate-800"><?= htmlspecialchars($row['day']) ?></td>
                    <td class="px-3 py-2 text-right text-slate-800"><?= fmt_number($row['energy_kwh'] ?? 0, 3) ?></td>
                    <td class="px-3 py-2 text-right text-slate-800"><?= fmt_number($row['carbon_kg'] ?? 0, 3) ?></td>
                    <td class="px-3 py-2 text-right text-slate-800"><?= fmt_number(($row['water_ml'] ?? 0) / 1000, 3) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

      </div>
    </main>
  </div>
  <script>
    // Aktif/nonaktifkan dropdown berdasarkan scope
    const scopeSelect = document.getElementById('scope');
    const courseSelect = document.getElementById('course_id');
    const assessmentSelect = document.getElementById('assessment_id');
    function updateFilterControls() {
      const scope = scopeSelect.value;
      if (scope === 'all') {
        courseSelect.disabled = true;
        assessmentSelect.disabled = true;
      } else if (scope === 'course') {
        courseSelect.disabled = false;
        assessmentSelect.disabled = true;
      } else if (scope === 'assessment') {
        courseSelect.disabled = false;
        assessmentSelect.disabled = false;
      }
    }
    scopeSelect.addEventListener('change', updateFilterControls);
    updateFilterControls();

    // Filter daftar assessment sesuai course yang dipilih (di sisi UI saja)
    courseSelect && courseSelect.addEventListener('change', () => {
      const selectedCourse = courseSelect.value;
      if (!assessmentSelect) return;
      Array.from(assessmentSelect.options).forEach(opt => {
        const cid = opt.dataset ? opt.dataset.course : null;
        if (!cid || opt.value === '') {
          opt.hidden = false;
          return;
        }
        opt.hidden = selectedCourse && cid !== selectedCourse;
      });
      if (window.jQuery) {
        jQuery(assessmentSelect).val('').trigger('change.select2');
      }
    });

    // Chart.js: grafik garis energi/karbon/air per hari
    const chartLabels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    const energyData = <?= json_encode($energySeries) ?>;
    const carbonData = <?= json_encode($carbonSeries) ?>;
    const waterData = <?= json_encode($waterSeries) ?>;
    if (chartLabels.length > 0) {
      const ctx = document.getElementById('impactChart').getContext('2d');
      // eslint-disable-next-line no-undef
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: chartLabels,
          datasets: [
            {
              label: 'Energy (kWh)',
              data: energyData,
              borderColor: 'rgb(37, 99, 235)',
              backgroundColor: 'rgba(37, 99, 235, 0.1)',
              tension: 0.25,
              fill: true,
            },
            {
              label: 'Carbon (kg CO₂e)',
              data: carbonData,
              borderColor: 'rgb(16, 185, 129)',
              backgroundColor: 'rgba(16, 185, 129, 0.1)',
              tension: 0.25,
              fill: true,
            },
            {
              label: 'Water (L)',
              data: waterData,
              borderColor: 'rgb(234, 179, 8)',
              backgroundColor: 'rgba(234, 179, 8, 0.1)',
              tension: 0.25,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          stacked: false,
          plugins: {
            legend: { position: 'bottom' },
          },
          scales: {
            y: { beginAtZero: true },
          },
        },
      });
    }

    // Inisialisasi Select2 pada semua select yang diberi kelas select2
    if (window.jQuery) {
      jQuery(function($) {
        $('.select2').select2({ width: 'resolve' });
          try {
            if ($.fn.dataTable) {
              $('#impactDailyTable').DataTable({ paging: true, searching: true, info: false, pageLength: 10, stripeClasses: ['odd-row','even-row'] });
            }
          } catch (e) { console.warn('DataTable init failed', e); }
      });
    }

  </script>
</body>
</html>
