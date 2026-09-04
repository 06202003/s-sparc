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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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
  <body style="font-family: 'Plus Jakarta Sans', sans-serif; background: radial-gradient(circle at top left, #e0f7f4, #f5faf8);">
    <div id="particles-js"></div>
    <style>
      #particles-js {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
      }
    </style>
    <nav class="navbar navbar-expand-lg sticky-top" style="top: 0; z-index: 1030; background-color: rgba(245, 250, 248, 0.95); box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);">
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
            style="border-color: #00A0A5; color: #00A0A5; background-color: transparent"
          >
            Login
          </a>
          <a
            href="register.php"
            class="btn btn-primary ms-2 px-4"
            style="background-color: #00A0A5 !important; border-color: #00A0A5"
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
          <h2 class="fw-bold mb-3" style="font-size: 2.1rem">Reduction Rate Analysis</h2>
          <p class="mb-4" style="color: #444; max-width: 900px">
            Analisis berikut merangkum dampak optimasi S-SPARC terhadap reduction rate, yaitu seberapa besar inferensi dapat diselesaikan melalui mekanisme lokal dibandingkan inferensi langsung ke GPT. Hasil ini menegaskan bahwa penghematan token bukan hanya terjadi sesaat, tetapi juga konsisten selama periode observasi.
          </p>

          <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
              <div class="simple-metric-card">
                <div class="metric-label">Token Reduction</div>
                <div class="metric-value">77%</div>
                <div class="metric-desc">Rata-rata eksperimen terkontrol</div>
              </div>
            </div>
            <div class="col-6 col-lg-3">
              <div class="simple-metric-card">
                <div class="metric-label">Token Reduction</div>
                <div class="metric-value">84%</div>
                <div class="metric-desc">Quasi-experiment</div>
              </div>
            </div>
            <div class="col-6 col-lg-3">
              <div class="simple-metric-card">
                <div class="metric-label">Significance</div>
                <div class="metric-value">p = 0.029</div>
                <div class="metric-desc">Statistically significant</div>
              </div>
            </div>
            <div class="col-6 col-lg-3">
              <div class="simple-metric-card">
                <div class="metric-label">Effect Size</div>
                <div class="metric-value">d = 0.53</div>
                <div class="metric-desc">Cohen's d, medium effect</div>
              </div>
            </div>
          </div>

          <div class="row g-4">
            <div class="col-lg-6">
              <div class="p-4 h-100" style="border: 1px solid #dde6e2; border-radius: 1.25rem; background: rgba(255,255,255,0.8); box-shadow: 0 6px 24px rgba(15, 23, 42, 0.05);">
                <h5 class="fw-bold mb-3">Hypothesis Test: Paired Sample T-Test</h5>
                <div class="mb-3" style="color: #445; font-size: 0.95rem;">
                  <div><strong>H0</strong> &rarr; Tidak terdapat perbedaan reduction rate antara fase awal dan fase akhir penggunaan sistem.</div>
                  <div><strong>H1</strong> &rarr; Terdapat perbedaan reduction rate antara fase awal dan fase akhir penggunaan sistem.</div>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-3">
                    <thead class="table-light">
                      <tr>
                        <th>Fase</th>
                        <th>Definisi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Early</td>
                        <td>Rata-rata reduction rate 3 minggu pertama</td>
                      </tr>
                      <tr>
                        <td>Late</td>
                        <td>Rata-rata reduction rate 3 minggu terakhir</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="mb-2"><strong>p-value (two-tail):</strong> 0.273</p>
                <p class="mb-2"><strong>Keputusan:</strong> H0 diterima dan H1 ditolak</p>
                <p class="mb-0" style="color: #4b5563; font-size: 0.95rem;">
                  Tidak terdapat perbedaan reduction rate yang signifikan antara fase awal dan fase akhir penggunaan SSPARC. SSPARC mampu mempertahankan reduction rate pada level tinggi secara konsisten, sehingga mayoritas inferensi tetap diselesaikan secara lokal dan kebutuhan inferensi langsung ke GPT tetap rendah.
                </p>
              </div>
            </div>

            <div class="col-lg-6">
              <div class="p-4 h-100" style="border: 1px solid #dde6e2; border-radius: 1.25rem; background: rgba(255,255,255,0.8); box-shadow: 0 6px 24px rgba(15, 23, 42, 0.05);">
                <h5 class="fw-bold mb-3">Repeated Measures ANOVA</h5>
                <div class="mb-3" style="color: #445; font-size: 0.95rem;">
                  <div><strong>H0</strong> &rarr; Tidak terdapat perbedaan reduction rate SSPARC antar minggu pengamatan.</div>
                  <div><strong>H1</strong> &rarr; Terdapat minimal satu minggu yang memiliki reduction rate berbeda signifikan.</div>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-3">
                    <thead class="table-light">
                      <tr>
                        <th>Parameter</th>
                        <th>Nilai</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>F-statistic</td>
                        <td>0.859</td>
                      </tr>
                      <tr>
                        <td>p-value</td>
                        <td>0.527</td>
                      </tr>
                      <tr>
                        <td>GG-corrected p-value</td>
                        <td>0.452</td>
                      </tr>
                      <tr>
                        <td>Effect Size (ng²)</td>
                        <td>0.0217</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="mb-2"><strong>Keputusan:</strong> H0 diterima dan H1 ditolak</p>
                <p class="mb-0" style="color: #4b5563; font-size: 0.95rem;">
                  Tidak terdapat perubahan reduction rate yang signifikan antar minggu pengamatan. Efisiensi reduksi inferensi GPT oleh SSPARC relatif stabil sepanjang penggunaan sistem, dengan effect size yang tergolong kecil.
                </p>
              </div>
            </div>
          </div>

        </div>

        <h2 class="fw-bold mb-3 mt-5" style="font-size: 2.1rem">Key Findings</h2>
        <div class="p-4" style="border: 1px solid #dde6e2; border-radius: 1.25rem; background: #ffffff; box-shadow: 0 6px 24px rgba(15, 23, 42, 0.05);">
          <p class="mb-4" style="color: #444; max-width: 900px">
            S-SPARC membuktikan bahwa analisis perilaku, gamifikasi, dan optimasi inferensi dapat secara simultan meningkatkan efisiensi, menjaga kualitas, dan mengurangi dampak lingkungan dalam pembelajaran pemrograman berbasis AI.
          </p>

          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="simple-metric-card" style="min-height: 150px;">
                <div class="metric-label">Behavior Shift</div>
                <div class="metric-value" style="font-size: 1.25rem">More efficient interaction</div>
                <div class="metric-desc">Prompt quality lebih berpengaruh dibanding jumlah token.</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="simple-metric-card" style="min-height: 150px;">
                <div class="metric-label">Gamification</div>
                <div class="metric-value" style="font-size: 1.25rem">Self-monitoring improved</div>
                <div class="metric-desc">Mendorong selektivitas prompt dan menekan interaksi tidak esensial.</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="simple-metric-card" style="min-height: 150px;">
                <div class="metric-label">Efficiency</div>
                <div class="metric-value" style="font-size: 1.25rem">Up to 84%</div>
                <div class="metric-desc">Pengurangan beban komputasi pada quasi-experiment.</div>
              </div>
            </div>
          </div>

          <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
              <div class="p-4 h-100" style="border: 1px solid #e0e6ed; border-radius: 1.1rem; background: #ffffff;">
                <h5 class="fw-bold mb-3">What changed</h5>
                <ul class="mb-0" style="color: #445; padding-left: 1.2rem;">
                  <li>Terjadi pergeseran perilaku interaksi menuju penggunaan yang lebih efisien.</li>
                  <li>Gamifikasi meningkatkan selektivitas saat pembuatan prompt dan memperkuat self-monitoring.</li>
                  <li>Interaksi yang tidak esensial menurun, sehingga penggunaan AI menjadi lebih terarah.</li>
                  <li>Kualitas tetap terjaga: 88.13% pada S-SPARC dibanding 87.72% pada baseline.</li>
                </ul>
              </div>
            </div>
            <div class="col-lg-5">
              <div class="p-4 h-100" style="border: 1px solid #e0e6ed; border-radius: 1.1rem; background: #ffffff; display: flex; flex-direction: column; justify-content: center;">
                <blockquote class="mb-0" style="font-size: 2.1rem; font-weight: 700; color: #0f766e; line-height: 1.55;">
                  The problem is not the AI, it’s how we use it. <br/> S-SPARC changes that.
                </blockquote>
              </div>
            </div>
          </div>
        </div>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
      particlesJS('particles-js', {
        particles: {
          number: { value: 60, density: { enable: true, value_area: 900 } },
          color: { value: ['#14b8a6', '#0f766e', '#a5f3fc'] },
          shape: { type: 'circle' },
          opacity: { value: 0.4, random: true },
          size: { value: 4, random: true },
          line_linked: {
            enable: true,
            distance: 150,
            color: '#0f766e',
            opacity: 0.25,
            width: 2,
          },
          move: {
            enable: true,
            speed: 2,
            direction: 'none',
            random: false,
            straight: false,
            out_mode: 'out',
            bounce: false,
          },
        },
        interactivity: {
          detect_on: 'window',
          events: {
            onhover: { enable: true, mode: 'grab' },
            onclick: { enable: false, mode: 'push' },
            resize: true,
          },
          modes: {
            grab: {
              distance: 160,
              line_linked: { opacity: 0.6 },
            },
            repulse: { distance: 100, duration: 0.4 },
            push: { particles_nb: 4 },
          },
        },
        retina_detect: true,
      });
    </script>
  </body>
</html>
