<?php
// Admin dashboard page - shows aggregated stats. Requires admin login.
require 'vendor/autoload.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$httpClient = new \GuzzleHttp\Client(['base_uri' => getenv('API_BASE') ?: 'http://localhost:5000/']);
$cookieJar = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : null;

try {
    $resp = $httpClient->request('GET', 'admin-dashboard', [
        'headers' => [
            'Accept' => 'application/json',
        ],
        'cookies' => true,
    ]);
    $data = json_decode($resp->getBody()->getContents(), true);
} catch (\GuzzleHttp\Exception\RequestException $e) {
    $data = ['error' => 'Could not fetch admin data: ' . $e->getMessage()];
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="/assets/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
  /* simple modal */
  .modal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background:rgba(0,0,0,0.4);} 
  .modal-content { background:#fff; margin:10% auto; padding:20px; width:80%; max-width:800px; }
  .btn-chart, .btn-csv { margin-right:8px; padding:6px 10px; background:#067; color:#fff; border:none; border-radius:4px; text-decoration:none }
  </style>
</head>
<body>
  <div class="container">
    <h1>Admin Dashboard</h1>
    <?php if(isset($data['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($data['error']) ?></div>
    <?php else: ?>
      <div class="grid">
        <div class="card">
          <h3>Total assessments</h3>
          <p><?= intval($data['total_assessments']) ?></p>
        </div>
        <div class="card">
          <h3>Assessments ended</h3>
          <p><?= intval($data['assessments_ended']) ?></p>
        </div>
        <div class="card">
          <h3>Total users</h3>
          <p><?= intval($data['total_users']) ?></p>
        </div>
        <div class="card">
          <h3>Total points awarded</h3>
          <p><?= number_format(floatval($data['total_points_awarded']),2) ?></p>
        </div>
        <div class="card">
          <h3>Total Energy (kWh)</h3>
          <p id="totalEnergy">Loading...</p>
        </div>
        <div class="card">
          <h3>Total Carbon (kg)</h3>
          <p id="totalCarbon">Loading...</p>
        </div>
        <div class="card">
          <h3>Total Water (ml)</h3>
          <p id="totalWater">Loading...</p>
        </div>
      </div>

      <h2>Recent Assessments</h2>
      <table class="table">
        <thead><tr><th>Name</th><th>End Date</th><th>Avg Usage</th><th>Threshold</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($data['recent_assessments'] as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['assessment_name']) ?></td>
            <td><?= htmlspecialchars($r['end_date']) ?></td>
            <td><?= number_format($r['avg_usage'],2) ?></td>
            <td><?= number_format($r['threshold'],2) ?></td>
            <td>
              <button class="btn-chart" data-aid="<?= htmlspecialchars($r['assessment_id']) ?>">Chart</button>
              <a class="btn-csv" href="/admin-assessment-csv?assessment_id=<?= urlencode($r['assessment_id']) ?>">Download CSV</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <script>
  async function fetchEnvStats(){
    const res = await fetch('/admin-environmental-stats?days=30', {credentials:'include'});
    if(!res.ok) return null;
    return await res.json();
  }

  async function initEnv(){
    const s = await fetchEnvStats();
    if(!s) return;
    document.getElementById('totalEnergy').innerText = Number(s.total_energy_kwh).toFixed(2);
    document.getElementById('totalCarbon').innerText = Number(s.total_carbon_kg).toFixed(2);
    document.getElementById('totalWater').innerText = Number(s.total_water_ml).toFixed(2);
  }
  document.addEventListener('DOMContentLoaded', ()=>{ initEnv(); });
  </script>

  <div id="histModal" class="modal">
    <div class="modal-content">
      <button id="modalClose">Close</button>
      <h3 id="modalTitle">Histogram</h3>
      <canvas id="histChart" width="600" height="300"></canvas>
    </div>
  </div>

  <script>
  async function fetchHistogram(aid){
    const res = await fetch(`/admin-assessment-histogram?assessment_id=${encodeURIComponent(aid)}&buckets=10`, {credentials:'include'});
    if(!res.ok){ alert('Failed to fetch histogram'); return null; }
    return await res.json();
  }

  function showModal(){ document.getElementById('histModal').style.display='block'; }
  function hideModal(){ document.getElementById('histModal').style.display='none'; }
  document.addEventListener('DOMContentLoaded', ()=>{
    document.getElementById('modalClose').addEventListener('click', hideModal);
    document.querySelectorAll('.btn-chart').forEach(btn=>{
      btn.addEventListener('click', async (e)=>{
        const aid = btn.getAttribute('data-aid');
        const data = await fetchHistogram(aid);
        if(!data) return;
        showModal();
        document.getElementById('modalTitle').innerText = `Assessment ${aid} Histogram`;
        const ctx = document.getElementById('histChart').getContext('2d');
        if(window._histChart) window._histChart.destroy();
        window._histChart = new Chart(ctx, {
          type: 'bar',
          data: { labels: data.labels, datasets: [{ label: 'Users', data: data.counts, backgroundColor: 'rgba(54,162,235,0.5)'}] },
          options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
        });
      });
    });
  });
  </script>
</body>
</html>
