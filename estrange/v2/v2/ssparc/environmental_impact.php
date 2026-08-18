<?php
require_once(__DIR__ . '/_sso_bridge.php');

// Fetch courses and assessments for filter
$coursesQuery = "SELECT course_id, name FROM course WHERE is_active = 1 ORDER BY name ASC";
$coursesRes = $db->query($coursesQuery);
$courses = [];
if ($coursesRes) {
    while ($row = $coursesRes->fetch_assoc()) {
        $courses[] = $row;
    }
}

$assessmentsQuery = "SELECT assessment_id, course_id, name FROM assessment ORDER BY assessment_id DESC LIMIT 50";
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
  <title>Environmental Impact & Sustainability - S-SPARC AI</title>
  <link rel="icon" href="../strange_html_layout_additional_files/icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Select2 CSS & JS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <style>
    :root { color-scheme: light; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    .glass { backdrop-filter: blur(10px); background: rgba(255,255,255,0.9); }
    .metric-card {
      background-color: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 1rem;
      padding: 1.25rem;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }
    /* Select2 Tailwind Light Styling */
    .select2-container--default .select2-selection--single {
      height: 36px;
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
      line-height: 34px;
      font-weight: 500;
      padding-left: 0.2rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 34px;
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
  
  <?php renderSSOHeader('environmental_impact', 'Eco-Metrics & Carbon Tracking'); ?>

  <main class="flex-1 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      
      <!-- Filter Bar -->
      <section class="glass rounded-2xl border border-slate-200/80 shadow-sm p-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 text-xs">
          <div class="w-36">
            <label class="block text-slate-500 font-medium mb-1">Time Range</label>
            <select id="filterDays" class="min-w-[200px] shrink-0 select2 w-full">
              <option value="7">Last 7 Days</option>
              <option value="30" selected>Last 30 Days</option>
              <option value="90">Last 90 Days</option>
              <option value="365">1 Year</option>
            </select>
          </div>
          <div class="w-44">
            <label class="block text-slate-500 font-medium mb-1">Data Scope</label>
            <select id="filterScope" class="min-w-[200px] shrink-0 select2 w-full">
              <option value="all" selected><?= ($sso_role === 'student') ? 'All My Activity' : 'All Students Activity' ?></option>
              <option value="course">By Course</option>
              <option value="assessment">By Assessment</option>
            </select>
          </div>
          <div id="courseFilterWrapper" class="hidden w-56">
            <label class="block text-slate-500 font-medium mb-1">Course</label>
            <select id="filterCourse" class="min-w-[200px] shrink-0 select2 w-full">
              <option value="">All Courses</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= htmlspecialchars($c['course_id']) ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="asmtFilterWrapper" class="hidden w-56">
            <label class="block text-slate-500 font-medium mb-1">Assessment</label>
            <select id="filterAsmt" class="min-w-[200px] shrink-0 select2 w-full">
              <option value="">All Assessments</option>
              <?php foreach ($assessments as $a): ?>
                <option value="<?= htmlspecialchars($a['assessment_id']) ?>">#<?= htmlspecialchars($a['assessment_id']) ?>: <?= htmlspecialchars($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <button type="button" onclick="loadImpactData()" class="inline-flex h-9 items-center rounded-lg bg-[#00A0A5] text-white text-sm font-semibold px-4 hover:bg-slate-800 transition shadow-xs">
          Refresh Metrics
        </button>
      </section>

      <!-- 4 Primary KPI Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <div class="metric-card">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Energy</span>
            <span class="p-2 rounded-lg bg-amber-50 text-amber-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </span>
          </div>
          <div class="text-2xl font-bold text-slate-900 mt-2" id="kpiEnergy">0.0000 kWh</div>
          <span class="text-xs text-slate-500 mt-1 block" id="kpiEnergyWh">0.00 Wh compute consumed</span>
        </div>

        <div class="metric-card">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Carbon Footprint</span>
            <span class="p-2 rounded-lg bg-rose-50 text-rose-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"></path></svg>
            </span>
          </div>
          <div class="text-2xl font-bold text-slate-900 mt-2" id="kpiCarbon">0.0000 kg</div>
          <span class="text-xs text-slate-500 mt-1 block">Estimated server CO₂e emissions</span>
        </div>

        <div class="metric-card">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Water Consumption</span>
            <span class="p-2 rounded-lg bg-blue-50 text-blue-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
            </span>
          </div>
          <div class="text-2xl font-bold text-slate-900 mt-2" id="kpiWater">0.000 L</div>
          <span class="text-xs text-slate-500 mt-1 block">Data center cooling (WUE)</span>
        </div>

        <div class="metric-card">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Daily Average</span>
            <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </span>
          </div>
          <div class="text-2xl font-bold text-slate-900 mt-2" id="kpiAvg">0.000 kWh/day</div>
          <span class="text-xs text-slate-500 mt-1 block">Average over active period</span>
        </div>

      </div>

      <!-- Real-World Equivalents Card Grid -->
      <section class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 shadow-sm">
        <h2 class="text-base font-bold text-slate-900 mb-1">Visual Real-World Equivalents</h2>
        <p class="text-xs text-slate-500 mb-4">Concrete real-world comparisons of energy and emissions generated by your AI inference:</p>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-center">
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-xs text-slate-500 block font-medium">Smartphone Charge</span>
            <div class="text-lg font-bold text-slate-900 mt-1" id="eqPhone">0.0</div>
            <span class="text-[11px] text-slate-400">full charges (12Wh)</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-xs text-slate-500 block font-medium">LED Bulb (9W)</span>
            <div class="text-lg font-bold text-slate-900 mt-1" id="eqLed">0.0</div>
            <span class="text-[11px] text-slate-400">hours powered</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-xs text-slate-500 block font-medium">Electric Kettle</span>
            <div class="text-lg font-bold text-slate-900 mt-1" id="eqKettle">0.0</div>
            <span class="text-[11px] text-slate-400">boils completed</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-xs text-slate-500 block font-medium">Gasoline Car</span>
            <div class="text-lg font-bold text-slate-900 mt-1" id="eqCar">0.0</div>
            <span class="text-[11px] text-slate-400">km traveled</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-xs text-slate-500 block font-medium">Tree Absorption</span>
            <div class="text-lg font-bold text-slate-900 mt-1" id="eqTree">0.0</div>
            <span class="text-[11px] text-slate-400">days of CO₂ uptake</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
            <span class="text-xs text-slate-500 block font-medium">Shower Duration</span>
            <div class="text-lg font-bold text-slate-900 mt-1" id="eqShower">0.0</div>
            <span class="text-[11px] text-slate-400">minutes (9L/min)</span>
          </div>
        </div>
      </section>

      <!-- Timeseries Impact Chart -->
      <section class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 shadow-sm">
        <h2 class="text-base font-bold text-slate-900 mb-1">Daily Environmental Footprint Timeseries</h2>
        <p class="text-xs text-slate-500 mb-4">Daily consumption trend of energy (kWh), carbon emissions (kg CO₂e), and server cooling water (L):</p>
        <div class="h-72 relative">
          <canvas id="impactChart"></canvas>
        </div>
      </section>

      <!-- Scientific Cloud API Performance Metrics -->
      <section class="rounded-2xl bg-white border border-slate-200 p-5 sm:p-6 shadow-sm">
        <h2 class="text-base font-bold text-slate-900 mb-1">Cloud API Environmental Parameters</h2>
        <p class="text-xs text-slate-500 mb-4">Underlying environmental calculation parameters based on scientific literature and data center standards:</p>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-left">
            <span class="text-xs font-semibold text-slate-600 block">Carbon Intensity</span>
            <div class="text-sm font-bold text-slate-900 mt-1">0.384 <span class="text-xs font-normal text-slate-500">g CO₂e/Wh</span></div>
            <span class="text-[11px] text-slate-400">Grid Emission Factor</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-left">
            <span class="text-xs font-semibold text-slate-600 block">PUE</span>
            <div class="text-sm font-bold text-slate-900 mt-1">1.12</div>
            <span class="text-[11px] text-slate-400">Power Usage Effectiveness</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-left">
            <span class="text-xs font-semibold text-slate-600 block">WUE Site</span>
            <div class="text-sm font-bold text-slate-900 mt-1">0.30 <span class="text-xs font-normal text-slate-500">mL/Wh</span></div>
            <span class="text-[11px] text-slate-400">Water Usage Effectiveness</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-left">
            <span class="text-xs font-semibold text-slate-600 block">WUE Source</span>
            <div class="text-sm font-bold text-slate-900 mt-1">4.35 <span class="text-xs font-normal text-slate-500">mL/Wh</span></div>
            <span class="text-[11px] text-slate-400">Grid Water Factor</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-left">
            <span class="text-xs font-semibold text-slate-600 block">Energy (&le;400)</span>
            <div class="text-sm font-bold text-slate-900 mt-1">0.00217 <span class="text-xs font-normal text-slate-500">Wh/tok</span></div>
            <span class="text-[11px] text-slate-400">Token Tier 1</span>
          </div>

          <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-left">
            <span class="text-xs font-semibold text-slate-600 block">Energy (&gt;2000)</span>
            <div class="text-sm font-bold text-slate-900 mt-1">0.00042 <span class="text-xs font-normal text-slate-500">Wh/tok</span></div>
            <span class="text-[11px] text-slate-400">Token Tier 3</span>
          </div>
        </div>
      </section>

    </div>
  </main>

  <script>
    const FASTAPI_URL = "http://127.0.0.1:5000";
    const SSO_USER_ID = "<?= htmlspecialchars($sso_user_id) ?>";

    let impactChartInstance = null;

    $(document).ready(function() {
      // Initialize Select2 on all filter dropdowns
      $('#filterDays').select2({ width: '100%', minimumResultsForSearch: Infinity });
      $('#filterScope').select2({ width: '100%', minimumResultsForSearch: Infinity });
      $('#filterCourse').select2({ width: '100%' });
      $('#filterAsmt').select2({ width: '100%' });

      $('#filterScope').on('change', function() {
        const val = $(this).val();
        $('#courseFilterWrapper').toggleClass('hidden', val !== 'course');
        $('#asmtFilterWrapper').toggleClass('hidden', val !== 'assessment');
        loadImpactData();
      });

      $('#filterDays, #filterCourse, #filterAsmt').on('change', function() {
        loadImpactData();
      });

      loadImpactData();
    });

    async function loadImpactData() {
      const days = $('#filterDays').val() || '30';
      const scope = $('#filterScope').val() || 'all';
      const courseId = $('#filterCourse').val() || '';
      const asmtId = $('#filterAsmt').val() || '';

      let url = `${FASTAPI_URL}/api/environmental/footprint?days=${days}&scope=${scope}`;
      if (scope === 'course' && courseId) url += `&course_id=${courseId}`;
      if (scope === 'assessment' && asmtId) url += `&assessment_id=${asmtId}`;

      try {
        const res = await fetch(url, { headers: { 'X-User-ID': SSO_USER_ID } });
        if (!res.ok) return;
        const data = await res.json();

        const totals = data.totals || {};
        const energyKwh = Number(totals.energy_kwh || 0);
        const energyWh = Number(totals.energy_wh || 0);
        const carbonKg = Number(totals.carbon_kg || 0);
        const waterL = Number(totals.water_l !== undefined ? totals.water_l : ((totals.water_ml || 0) / 1000.0));
        const avgDailyKwh = Number(data.avg_daily_kwh !== undefined ? data.avg_daily_kwh : (data.daily && data.daily.length > 0 ? energyKwh / data.daily.length : energyKwh));

        document.getElementById('kpiEnergy').textContent = `${energyKwh.toFixed(4)} kWh`;
        document.getElementById('kpiEnergyWh').textContent = `${energyWh.toFixed(2)} Wh compute consumed`;
        document.getElementById('kpiCarbon').textContent = `${carbonKg.toFixed(4)} kg`;
        document.getElementById('kpiWater').textContent = `${waterL.toFixed(3)} L`;
        document.getElementById('kpiAvg').textContent = `${avgDailyKwh.toFixed(4)} kWh/day`;

        // Direct presentation of SSOT equivalents returned from backend
        const eq = data.equivalents || {};
        document.getElementById('eqPhone').textContent = Number(eq.phone_charges || (energyWh / 12.0)).toFixed(1);
        document.getElementById('eqLed').textContent = Number(eq.led_hours || (energyWh / 9.0)).toFixed(1);
        document.getElementById('eqKettle').textContent = Number(eq.kettle_boils || (energyWh / 1500.0)).toFixed(2);
        document.getElementById('eqCar').textContent = Number(eq.car_km || (carbonKg / 0.192)).toFixed(2);
        document.getElementById('eqTree').textContent = Number(eq.tree_days || (carbonKg / (21.0 / 365.0))).toFixed(1);
        document.getElementById('eqShower').textContent = Number(eq.shower_minutes || (waterL / 9.0)).toFixed(2);

        // Render Chart
        const daily = data.daily || [];
        const labels = daily.map(d => d.day);
        const energySeries = daily.map(d => Number(d.energy_kwh || 0));
        const carbonSeries = daily.map(d => Number(d.carbon_kg || 0));
        const waterSeries = daily.map(d => Number(d.water_l !== undefined ? d.water_l : ((d.water_ml || 0) / 1000.0)));

        const ctx = document.getElementById('impactChart').getContext('2d');
        if (impactChartInstance) {
          impactChartInstance.destroy();
        }

        impactChartInstance = new Chart(ctx, {
          type: 'line',
          data: {
            labels: labels.length > 0 ? labels : ['Today'],
            datasets: [
              {
                label: 'Energy (kWh)',
                data: energySeries.length > 0 ? energySeries : [energyKwh],
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
              },
              {
                label: 'Carbon (kg CO2e)',
                data: carbonSeries.length > 0 ? carbonSeries : [carbonKg],
                borderColor: '#f43f5e',
                backgroundColor: 'rgba(244, 63, 94, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
              },
              {
                label: 'Water (L)',
                data: waterSeries.length > 0 ? waterSeries : [waterL],
                borderColor: '#06b6d4',
                backgroundColor: 'rgba(6, 182, 212, 0.1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { grid: { display: false } },
              y: { beginAtZero: true, grid: { color: '#f1f5f9' } }
            },
            plugins: {
              legend: { position: 'top', labels: { boxWidth: 12, font: { family: 'Inter', size: 11 } } }
            }
          }
        });

      } catch (e) {
        console.error('Failed to load impact data:', e);
      }
    }
  </script>
</body>
</html>

