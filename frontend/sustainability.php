<?php
  // Load a small preview (header + first rows) from emissions.csv
  $emissionsPreview = [];
  $csvPath = __DIR__ . '/../emissions.csv';

  if (file_exists($csvPath) && is_readable($csvPath)) {
    if (($handle = fopen($csvPath, 'r')) !== false) {
      $maxRows = 6; // header + 5 data rows
      $rowCount = 0;
      while (($data = fgetcsv($handle)) !== false && $rowCount < $maxRows) {
        $emissionsPreview[] = $data;
        $rowCount++;
      }
      fclose($handle);
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>S-SPARC.AI - Sustainability</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
  </head>
  <body style="font-family: 'Plus Jakarta Sans', sans-serif; background-color: #fffff0">
    <nav class="navbar navbar-expand-lg sticky-top" style="top: 0; z-index: 1030; background-color: #fffff0">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
          <img src="logo.png" alt="S-SPARC" width="150" height="75" />
        </a>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
            <li class="nav-item mx-3">
              <a class="nav-link" aria-current="page" href="index.php">Home</a>
            </li>
            <li class="nav-item mx-3">
              <a class="nav-link" aria-current="page" href="features.php">Features &amp; Gamification</a>
            </li>
            <li class="nav-item mx-3">
              <a class="nav-link active" aria-current="page" href="sustainability.php">Sustainability</a>
            </li>
            <li class="nav-item mx-3">
              <a class="nav-link" aria-current="page" href="about.php">About Us</a>
            </li>
          </ul>
          <a
            href="login.php"
            class="btn btn-outline-primary me-2 px-4"
            style="border-color: teal; color: teal; background-color: transparent"
          >
            Login
          </a>
          <a
            href="register.php"
            class="btn btn-primary ms-2 px-4"
            style="background-color: teal !important; border-color: teal"
          >
            Create Account
          </a>
        </div>
      </div>
    </nav>

    <div class="section py-5">
      <div class="container py-5">
        <h2 class="fw-bold mb-3" style="font-size: 2.1rem">Cloud API Performance Metrics</h2>
        <p class="mb-4" style="color: #333; max-width: 800px">Methodology for calculating the environmental impact of AI: energy, carbon, water, and other parameters. Data and formulas based on scientific references and industry standards.</p>
        <div class="row g-3 justify-content-center align-items-center">
          <div class="col-6 col-md-4 col-lg-3">
            <div class="simple-metric-card">
              <div class="metric-label">Carbon Footprint</div>
              <div class="metric-value">0.384 <span class="metric-unit">g CO₂e/Wh</span></div>
              <div class="metric-desc">Grid Emission Factors</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="simple-metric-card">
              <div class="metric-label">PUE</div>
              <div class="metric-value">1.12</div>
              <div class="metric-desc">Power Usage Effectiveness</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="simple-metric-card">
              <div class="metric-label">WUE Site</div>
              <div class="metric-value">0.30 <span class="metric-unit">mL/Wh</span></div>
              <div class="metric-desc">Water Usage Effectiveness </div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="simple-metric-card">
              <div class="metric-label">WUE Source</div>
              <div class="metric-value">4.35 <span class="metric-unit">mL/Wh</span></div>
              <div class="metric-desc">Water Usage Effectiveness</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="simple-metric-card">
              <div class="metric-label">Energy</div>
              <div class="metric-value">0.0021775 <span class="metric-unit">Wh/token</span></div>
              <div class="metric-desc">≤400 token</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="simple-metric-card">
              <div class="metric-label">Energy</div>
              <div class="metric-value">0.0015805 <span class="metric-unit">Wh/token</span></div>
              <div class="metric-desc">≤2000 token</div>
            </div>
          </div>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="simple-metric-card">
              <div class="metric-label">Energy</div>
              <div class="metric-value">0.00042026 <span class="metric-unit">Wh/token</span></div>
              <div class="metric-desc">&gt;2000 token</div>
            </div>
          </div>
        </div>

        

        <?php if (!empty($emissionsPreview)): ?>
        <div class="mt-5">
          <h2 class="fw-bold mb-3" style="font-size: 2.1rem">Local Performance Metrics</h2>
          <p class="mt-4" style="color: #444; font-size: 0.95rem; max-width: 800px">
          In addition to estimates based on grid factors and data center efficiency, we also calculate the carbon footprint
          generated by the local server (development machine) using the open-source library
            <a href="https://github.com/mlco2/codecarbon" target="_blank">CodeCarbon</a>. Each time an experiment or
          specific script is run, CodeCarbon records energy consumption and CO₂e emissions into the
            <span style="font-family: monospace">emissions.csv</span> file, a sample of which is shown in the table below.
          </p>
          <h5 class="fw-bold mb-2" style="font-size: 1.1rem">Sample CodeCarbon Log</h5>
          <p class="mb-2" style="color: #555; font-size: 0.95rem">The table below shows the first few rows of the <span style="font-family: monospace">emissions.csv</span> file recorded by CodeCarbon.</p>
          <div class="table-responsive" style="max-height: 260px; border-radius: 0.75rem; border: 1px solid #e0e6ed; background: #ffffff">
            <table class="table table-sm mb-0 align-middle">
              <thead class="table-light" style="font-size: 0.8rem; position: sticky; top: 0; z-index: 1;">
                <tr>
                  <?php foreach ($emissionsPreview[0] as $col): ?>
                    <th scope="col"><?php echo htmlspecialchars($col); ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody style="font-size: 0.78rem">
                <?php for ($i = 1; $i < count($emissionsPreview); $i++): ?>
                  <tr>
                    <?php foreach ($emissionsPreview[$i] as $cell): ?>
                      <td><?php echo htmlspecialchars($cell); ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <div class="mt-5">
          <h5 class="fw-bold mb-2" style="font-size: 1.1rem">References</h5>
          <ul style="font-size: 1em">
            <li>Jegham et al., 2025. <a href="https://arxiv.org/pdf/2505.09598" target="_blank">The Carbon Footprint of ChatGPT</a>.</li>
            <li>Local server measurements in this project are estimated using <a href="https://github.com/mlco2/codecarbon" target="_blank">CodeCarbon</a>, with raw logs stored in <code>emissions.csv</code>.</li>
          </ul>
        </div>
      </div>
      <style>
        .simple-metric-card {
          background: #f8fafb;
          border: 1.5px solid #e0e6ed;
          border-radius: 1.25rem;
          padding: 1.5rem 1.1rem 1.1rem 1.1rem;
          margin-bottom: 0.7rem;
          box-shadow: 0 4px 18px 0 #b4c5d833, 0 1.5px 8px #e0e6ed33;
          text-align: center;
          min-height: 130px;
          transition: box-shadow 0.18s, transform 0.18s;
        }
        .simple-metric-card:hover {
          box-shadow: 0 8px 32px #b4ec5133, 0 1.5px 8px #e0e6ed33;
          transform: translateY(-4px) scale(1.025);
        }
        .metric-label {
          font-size: 1.13rem;
          color: #1b3c2e;
          font-weight: 700;
          margin-bottom: 0.3rem;
          letter-spacing: 0.01em;
        }
        .metric-value {
          font-size: 1.7rem;
          font-weight: 800;
          color: #217a53;
          margin-bottom: 0.1rem;
        }
        .metric-unit {
          font-size: 1.05rem;
          color: #7a8a99;
          font-weight: 500;
        }
        .metric-desc {
          font-size: 1.01rem;
          color: #7a8a99;
          margin-top: 0.2rem;
        }
        @media (max-width: 768px) {
          .simple-metric-card {
            padding: 0.9rem 0.5rem;
            min-height: 90px;
          }
          .metric-value {
            font-size: 1.15rem;
          }
        }
      </style>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  </body>
</html>
