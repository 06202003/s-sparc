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
            $error = 'Failed to read server response.';
        }
    } else {
        $error = 'Server returned status ' . $status;
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

// Prepare data for the chart (Chart.js)
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

$totalDays = max(count($daily), 1);
$avgEnergyPerDay = (float)($totals['energy_kwh'] ?? 0) / $totalDays;

$energyKwh = (float)($totals['energy_kwh'] ?? 0);
$carbonKg = (float)($totals['carbon_kg'] ?? 0);
$waterL = (float)(($totals['water_ml'] ?? 0) / 1000.0);

$phoneChargeWh = 12.0;
$ledBulbW = 9.0;
$kettleW = 1500.0;
$carKgPerKm = 0.192;
$treeKgPerYear = 21.0;
$showerLPerMin = 9.0;
$bottleL = 0.6;

$charges = $phoneChargeWh > 0 ? ($energyKwh * 1000.0) / $phoneChargeWh : 0;
$chargePercent = max(0.0, min(100.0, $charges * 100.0));
$ledHours = $ledBulbW > 0 ? ($energyKwh * 1000.0) / $ledBulbW : 0;
$kettleMinutes = $kettleW > 0 ? (($energyKwh * 1000.0) / $kettleW) * 60.0 : 0;
$carKm = $carKgPerKm > 0 ? $carbonKg / $carKgPerKm : 0;
$treeDays = $treeKgPerYear > 0 ? $carbonKg / ($treeKgPerYear / 365.0) : 0;
$showerMinutes = $showerLPerMin > 0 ? $waterL / $showerLPerMin : 0;
$bottles = $bottleL > 0 ? $waterL / $bottleL : 0;

$maxEnergyDay = null;
$maxEnergyKwh = 0.0;
foreach ($daily as $row) {
  $val = (float)($row['energy_kwh'] ?? 0);
  if ($val > $maxEnergyKwh) {
    $maxEnergyKwh = $val;
    $maxEnergyDay = $row['day'] ?? null;
  }
}

$maxEnergyDayLabel = $maxEnergyDay;
if ($maxEnergyDay) {
  try {
    $dt = new DateTime($maxEnergyDay);
    $maxEnergyDayLabel = $dt->format('D, d M Y');
  } catch (Throwable $e) {
    $maxEnergyDayLabel = $maxEnergyDay;
  }
}
$maxChargePercent = 0.0;
if ($phoneChargeWh > 0) {
  $maxChargePercent = max(0.0, min(100.0, (($maxEnergyKwh * 1000.0) / $phoneChargeWh) * 100.0));
}

$factCards = [
  [
    'key' => 'energy',
    'title' => 'Energy equivalence',
    'value' => fmt_number($energyKwh, 3) . ' kWh',
    'detail' => 'Equivalent to a ' . fmt_number($chargePercent, 0) . '% phone charge (12 Wh) or ' . fmt_number($ledHours, 1) . ' hours of a 9W LED lamp.',
    'bar' => min(100, ($energyKwh / 1.0) * 100.0),
    'color' => '#38bdf8'
  ],
  [
    'key' => 'carbon',
    'title' => 'Carbon travel',
    'value' => fmt_number($carbonKg, 3) . ' kg CO₂e',
    'detail' => 'Equivalent to about ' . fmt_number($carKm, 2) . ' km of gasoline car travel (0.192 kg CO₂/km).',
    'bar' => min(100, ($carbonKg / 1.0) * 100.0),
    'color' => '#10b981'
  ],
  [
    'key' => 'water',
    'title' => 'Water usage',
    'value' => fmt_number($waterL, 3) . ' L',
    'detail' => 'Equivalent to ' . fmt_number($showerMinutes, 1) . ' minutes of showering (9 L/min) or ' . fmt_number($bottles, 1) . ' bottles of 600 ml.',
    'bar' => min(100, ($waterL / 100.0) * 100.0),
    'color' => '#f59e0b'
  ],
  [
    'key' => 'intensity',
    'title' => 'Energy intensity',
    'value' => fmt_number($avgEnergyPerDay, 3) . ' kWh/day',
    'detail' => 'Equivalent to ' . fmt_number($kettleMinutes, 1) . ' minutes of running a 1500W kettle across the period.',
    'bar' => min(100, ($avgEnergyPerDay / 1.0) * 100.0),
    'color' => '#6366f1'
  ],
];

if ($maxEnergyDay !== null && $maxEnergyKwh > 0) {
  $factCards[] = [
    'key' => 'peak',
    'title' => 'Peak day',
    'value' => fmt_number($maxEnergyKwh, 3) . ' kWh',
    'detail' => 'Highest-usage day: ' . $maxEnergyDayLabel . '. Equivalent to charging a phone up to ' . fmt_number($maxChargePercent, 0) . '%.',
    'bar' => min(100, ($maxEnergyKwh / 1.0) * 100.0),
    'color' => '#f43f5e'
  ];
}

// Active filter label
$activeFilterLabel = 'All courses & assessments';
if ($scope === 'course' && $selectedCourseId !== '') {
  foreach ($courses as $c) {
    if ((string)($c['course_id'] ?? '') === $selectedCourseId) {
      $activeFilterLabel = 'Course: ' . ($c['name'] ?? ($c['code'] ?? $selectedCourseId));
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
        $activeFilterLabel .= ' (Course: ' . $courseName . ')';
      }
      break;
    }
  }
}
?><!doctype html>
<html lang="en">
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
            <div class="mt-2 text-2xl font-semibold text-slate-900"><?= fmt_number($avgEnergyPerDay, 3) ?> kWh/day</div>
            <p class="mt-1 text-xs text-slate-500">Average energy consumption per day during this period.</p>
          </div>
        </section>

        <section class="rounded-3xl bg-white border border-slate-200 p-6 shadow-sm">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Fun facts from your numbers</h2>
              <p class="text-sm text-slate-600">Switch between facts to see more relatable comparisons.</p>
            </div>
            <div class="flex flex-wrap gap-2" id="factTabs">
              <?php foreach ($factCards as $idx => $card): ?>
                <button
                  type="button"
                  class="fact-tab rounded-full border border-slate-300 px-3 py-1 text-xs uppercase tracking-wide text-slate-600 hover:bg-slate-50"
                  data-index="<?= $idx ?>"
                >
                  <?= htmlspecialchars($card['title']) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mt-4 grid gap-4 md:grid-cols-5">
            <div class="md:col-span-3 rounded-2xl bg-slate-50 border border-slate-200 p-5">
              <div class="text-xs uppercase tracking-widest text-slate-500" id="factTitle"></div>
              <div class="mt-2 text-3xl font-semibold text-slate-900" id="factValue"></div>
              <p class="mt-2 text-sm text-slate-600" id="factDetail"></p>
            </div>
            <div class="md:col-span-2 rounded-2xl bg-slate-50 border border-slate-200 p-5">
              <div class="text-xs uppercase tracking-widest text-slate-500">Quick highlights</div>
              <ul class="mt-3 space-y-2 text-sm text-slate-700">
                <li>Energy: <?= fmt_number($energyKwh, 3) ?> kWh</li>
                <li>Carbon: <?= fmt_number($carbonKg, 3) ?> kg CO₂e</li>
                <li>Water: <?= fmt_number($waterL, 3) ?> L</li>
                <?php if ($maxEnergyDay): ?>
                  <li>Peak day: <?= htmlspecialchars($maxEnergyDayLabel) ?></li>
                <?php endif; ?>
              </ul>
              <button type="button" id="factNext" class="mt-4 w-full rounded-xl bg-slate-900 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800">Next fact</button>
            </div>
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
                  <td class="px-3 py-4 text-center text-slate-500">No environmental impact data recorded for this period.</td>
                  <td></td>
                  <td></td>
                  <td></td>
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
    // Enable/disable dropdowns based on scope
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

    // Filter assessments based on selected course (client-side only)
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

    // Chart.js: daily energy/carbon/water line chart
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

    const facts = <?= json_encode($factCards, JSON_UNESCAPED_UNICODE) ?>;
    let currentFact = 0;
    const titleEl = document.getElementById('factTitle');
    const valueEl = document.getElementById('factValue');
    const detailEl = document.getElementById('factDetail');
    const tabEls = document.querySelectorAll('.fact-tab');
    const nextBtn = document.getElementById('factNext');

    function renderFact(index) {
      if (!facts.length) return;
      const fact = facts[index];
      titleEl.textContent = fact.title;
      valueEl.textContent = fact.value;
      detailEl.textContent = fact.detail;
      tabEls.forEach((btn, i) => {
        const accent = facts[i] && facts[i].color ? facts[i].color : '#94a3b8';
        btn.style.borderColor = accent;
        if (i === index) {
          btn.style.backgroundColor = accent;
          btn.style.color = '#0f172a';
        } else {
          btn.style.backgroundColor = 'transparent';
          btn.style.color = '#475569';
        }
      });
    }

    tabEls.forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.index, 10);
        if (!Number.isNaN(idx)) {
          currentFact = idx;
          renderFact(currentFact);
        }
      });
    });

    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        currentFact = (currentFact + 1) % facts.length;
        renderFact(currentFact);
      });
    }

    renderFact(currentFact);

    // Initialize Select2 on all selects with the select2 class
    if (window.jQuery) {
      jQuery(function($) {
        $('.select2').select2({ width: 'resolve' });
          try {
            if ($.fn.dataTable) {
              // Destroy existing DataTable instance if exists
              if ($.fn.DataTable.isDataTable('#impactDailyTable')) {
                $('#impactDailyTable').DataTable().destroy();
              }
              // Initialize DataTable with proper column configuration
              $('#impactDailyTable').DataTable({ 
                paging: true, 
                searching: true, 
                info: false, 
                pageLength: 10, 
                stripeClasses: ['odd-row','even-row'],
                columns: [
                  { orderable: true },  // Date
                  { orderable: true },  // Energy
                  { orderable: true },  // Carbon
                  { orderable: true }   // Water
                ]
              });
            }
          } catch (e) { console.warn('DataTable init failed', e); }
      });
    }

  </script>
</body>
</html>
