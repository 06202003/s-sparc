<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$sso_user_id = $_SESSION['user_id'] ?? '';
$sso_username = $_SESSION['username'] ?? 'User';
$sso_name = $_SESSION['name'] ?? $sso_username;
$sso_role = $_SESSION['role'] ?? 'student';

// Load emissions preview from emissions.csv
$emissionsPreview = [];
$csvPath = __DIR__ . '/../../../../emissions.csv';
if (!file_exists($csvPath)) {
    $csvPath = __DIR__ . '/../emissions.csv';
}
if (file_exists($csvPath) && is_readable($csvPath)) {
    if (($handle = fopen($csvPath, 'r')) !== false) {
        $maxRows = 6;
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
    <title>Sustainability &amp; Eco-AI — S-SPARC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
    <style>
      :root {
        --brand-teal: #00A0A5;
        --brand-dark: #0f172a;
        --brand-slate: #1e293b;
        --brand-teal-glow: rgba(0, 160, 165, 0.18);
      }
      body {
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        background: #f8fafc;
        color: #0f172a;
        min-height: 100vh;
        overflow-x: hidden;
      }
      .font-mono {
        font-family: 'JetBrains Mono', monospace;
      }
      #particles-js {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        pointer-events: none;
      }
      .navbar {
        background-color: rgba(255, 255, 255, 0.92) !important;
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid rgba(226, 232, 240, 0.9);
      }
      .navbar-brand img {
        height: 58px;
        width: auto;
        object-fit: contain;
        transition: transform 0.2s ease;
      }
      .navbar-brand img:hover {
        transform: scale(1.02);
      }
      .nav-link {
        font-weight: 600;
        color: #475569 !important;
        font-size: 0.9rem;
        padding: 0.5rem 1rem !important;
        border-radius: 0.5rem;
        transition: all 0.2s ease;
      }
      .nav-link:hover, .nav-link.active {
        color: var(--brand-teal) !important;
        background-color: rgba(0, 160, 165, 0.06);
      }
      .btn-teal {
        background-color: var(--brand-teal) !important;
        border-color: var(--brand-teal) !important;
        color: #ffffff !important;
        font-weight: 600;
        border-radius: 0.75rem;
        padding: 0.65rem 1.4rem;
        transition: all 0.2s ease-in-out;
      }
      .btn-teal:hover {
        background-color: #008589 !important;
        border-color: #008589 !important;
        box-shadow: 0 4px 16px var(--brand-teal-glow);
        transform: translateY(-1px);
      }
      .btn-outline-teal {
        border-color: #cbd5e1 !important;
        color: #1e293b !important;
        background-color: #ffffff !important;
        font-weight: 600;
        border-radius: 0.75rem;
        padding: 0.65rem 1.4rem;
        transition: all 0.2s ease-in-out;
      }
      .btn-outline-teal:hover {
        border-color: var(--brand-teal) !important;
        color: var(--brand-teal) !important;
        background-color: #f0fdfa !important;
        transform: translateY(-1px);
      }
      .hero-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0.9rem;
        border-radius: 9999px;
        background: #f0fdfa;
        border: 1px solid #ccfbf1;
        color: #0f766e;
        font-size: 0.775rem;
        font-weight: 700;
        letter-spacing: 0.025em;
        text-transform: uppercase;
      }
      .metric-card-2026 {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        padding: 1.5rem;
        transition: all 0.25s ease;
      }
      .metric-card-2026:hover {
        transform: translateY(-3px);
        border-color: rgba(0, 160, 165, 0.4);
        box-shadow: 0 12px 28px -8px rgba(15, 23, 42, 0.08);
      }
    </style>
  </head>
  <body>
    <!-- Background Particle Canvas -->
    <div id="particles-js"></div>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg sticky-top">
      <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
          <img src="logo.png" alt="S-SPARC" />
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
          <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarContent">
          <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link" href="index.php">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="features.php">Features &amp; Gamification</a>
            </li>
            <li class="nav-item">
              <a class="nav-link active" href="sustainability.php">Sustainability</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="about.php">About Us</a>
            </li>
          </ul>
          
          <div class="d-flex align-items-center gap-2">
            <?php if ($isLoggedIn): ?>
              <span class="d-none d-lg-inline-block px-3 py-1.5 rounded-3 border font-mono" style="background-color: #f1f5f9; border-color: #cbd5e1; color: #0f172a !important; font-size: 0.825rem; font-weight: 700;"><?= htmlspecialchars($sso_name) ?></span>
              <a href="courses.php" class="btn btn-teal">
                Open AI Assistant
              </a>
              <a href="../student_dashboard.php" class="btn btn-outline-teal">
                E-STRANGE LMS
              </a>
            <?php else: ?>
              <a href="../index.php" class="btn btn-outline-teal">
                Login
              </a>
              <a href="../index.php" class="btn btn-teal">
                Get Started
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </nav>

    <!-- Header Section -->
    <section class="py-5">
      <div class="container">
        <div class="flex flex-wrap align-items-center justify-content-between g-5 py-3">
          <div class="w-full lg:w-1/2">
            <div class="mb-3">
              <div class="hero-pill font-mono">
                <span class="w-2 h-2 rounded-circle" style="background-color: var(--brand-teal);"></span>
                Green Computing Metrics
              </div>
            </div>
            <h1 class="display-5 fw-extrabold text-slate-900 mb-3" style="letter-spacing: -0.03em; line-height: 1.2;">
              Computational Sustainability &amp; Carbon Telemetry
            </h1>
            <p class="lead text-slate-600 mb-4" style="font-size: 1.05rem; line-height: 1.7;">
              Methodology for calculating the real-world environmental impact of generative AI: electrical energy, carbon equivalent (gCO2e), and data center water consumption.
            </p>
            <div class="d-flex flex-wrap gap-3">
              <a href="environmental_impact.php" class="btn btn-teal">
                View Live Telemetry &rarr;
              </a>
              <a href="#scientific-metrics" class="btn btn-outline-teal">
                Formulas &amp; Standards
              </a>
            </div>
          </div>

          <div class="w-full lg:w-1/2">
            <div class="bg-white border rounded-4 p-4 p-md-5 shadow-sm">
              <span class="text-xs font-mono text-slate-500 uppercase tracking-wider font-semibold d-block mb-3">Eco-Routing Philosophy</span>
              <p class="text-slate-600 mb-3" style="font-size: 0.95rem; line-height: 1.75; text-align: justify;">
                Standard generative AI architectures waste immense kilowatt-hours regenerating identical or near-identical solutions. S-SPARC establishes a fast-path vector cache that eliminates 100% of LLM energy consumption for repeated queries.
              </p>
              <p class="text-slate-600 mb-0" style="font-size: 0.95rem; line-height: 1.75; text-align: justify;">
                Through peer-reviewed emission factors and Power Usage Effectiveness (PUE) metrics, S-SPARC transparently quantifies the carbon preserved by student code reuse.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 6 Scientific Metric Cards -->
    <section id="scientific-metrics" class="py-5 bg-white border-top border-bottom">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto mb-5">
          <span class="text-xs font-mono font-bold uppercase tracking-wider text-teal-600" style="color: var(--brand-teal);">Scientific Constants</span>
          <h2 class="h2 fw-bold text-slate-900 mt-1 mb-2">Cloud API Performance Standards</h2>
          <p class="text-slate-500">Benchmark constants calibrated for academic datacenter modeling.</p>
        </div>

        <div class="flex flex-wrap g-4">
          
          <div class="w-full md:w-1/3">
            <div class="metric-card-2026 h-100">
              <span class="text-xs font-mono text-slate-400 font-bold uppercase">Emission Factor</span>
              <div class="h3 fw-extrabold text-slate-900 mt-2 mb-1 font-mono">0.384 <span class="text-xs font-normal text-slate-500">gCO₂e/Wh</span></div>
              <p class="text-slate-600 text-xs mt-2 mb-0">Regional Grid Carbon Intensity applied to total server kilowatt-hours.</p>
            </div>
          </div>

          <div class="w-full md:w-1/3">
            <div class="metric-card-2026 h-100">
              <span class="text-xs font-mono text-slate-400 font-bold uppercase">PUE Efficiency</span>
              <div class="h3 fw-extrabold text-teal-700 mt-2 mb-1 font-mono">1.12 <span class="text-xs font-normal text-slate-500">ratio</span></div>
              <p class="text-slate-600 text-xs mt-2 mb-0">Power Usage Effectiveness representing modern hyper-efficient cloud infrastructure.</p>
            </div>
          </div>

          <div class="w-full md:w-1/3">
            <div class="metric-card-2026 h-100">
              <span class="text-xs font-mono text-slate-400 font-bold uppercase">WUE Site Water</span>
              <div class="h3 fw-extrabold text-blue-700 mt-2 mb-1 font-mono">0.30 <span class="text-xs font-normal text-slate-500">mL/Wh</span></div>
              <p class="text-slate-600 text-xs mt-2 mb-0">Direct on-site evaporative cooling consumption per watt-hour.</p>
            </div>
          </div>

          <div class="w-full md:w-1/3">
            <div class="metric-card-2026 h-100">
              <span class="text-xs font-mono text-slate-400 font-bold uppercase">WUE Source Water</span>
              <div class="h3 fw-extrabold text-indigo-700 mt-2 mb-1 font-mono">4.35 <span class="text-xs font-normal text-slate-500">mL/Wh</span></div>
              <p class="text-slate-600 text-xs mt-2 mb-0">Indirect utility-side water consumption during regional power generation.</p>
            </div>
          </div>

          <div class="w-full md:w-1/3">
            <div class="metric-card-2026 h-100">
              <span class="text-xs font-mono text-slate-400 font-bold uppercase">Energy Intensity (&le;400 Tokens)</span>
              <div class="h3 fw-extrabold text-emerald-700 mt-2 mb-1 font-mono">0.00218 <span class="text-xs font-normal text-slate-500">Wh/tok</span></div>
              <p class="text-slate-600 text-xs mt-2 mb-0">Baseline energy draw for short context generative completions.</p>
            </div>
          </div>

          <div class="w-full md:w-1/3">
            <div class="metric-card-2026 h-100">
              <span class="text-xs font-mono text-slate-400 font-bold uppercase">Energy Intensity (&le;2000 Tokens)</span>
              <div class="h3 fw-extrabold text-amber-700 mt-2 mb-1 font-mono">0.00158 <span class="text-xs font-normal text-slate-500">Wh/tok</span></div>
              <p class="text-slate-600 text-xs mt-2 mb-0">Amortized energy draw for deep multi-turn code synthesis workloads.</p>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Raw Data Preview Table -->
    <?php if (!empty($emissionsPreview)): ?>
    <section class="py-5 my-2">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto mb-4">
          <span class="text-xs font-mono font-bold uppercase tracking-wider text-teal-600" style="color: var(--brand-teal);">Empirical Dataset</span>
          <h2 class="h3 fw-bold text-slate-900 mt-1 mb-2">Emissions Log Sample</h2>
          <p class="text-slate-500 text-sm">Real-time telemetry records logged during benchmark executions.</p>
        </div>

        <div class="bg-white border rounded-4 shadow-sm overflow-hidden">
          <div class="table-responsive">
            <table class="table table-hover mb-0 text-xs font-mono">
              <thead class="table-light border-bottom">
                <tr>
                  <?php foreach ($emissionsPreview[0] as $colHeader): ?>
                    <th class="py-3 px-3 text-slate-700"><?= htmlspecialchars($colHeader) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php for ($i = 1; $i < count($emissionsPreview); $i++): ?>
                  <tr>
                    <?php foreach ($emissionsPreview[$i] as $val): ?>
                      <td class="py-2.5 px-3 text-slate-600"><?= htmlspecialchars($val) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

        <!-- Footer -->
    <footer class="bg-white py-4 text-slate-500 text-xs border-top">
      <div class="container text-center">
        &copy; <?= date('Y') ?> <span class="fw-semibold text-slate-700">S-SPARC Team</span> &bull; Faculty of Smart Technology &amp; Engineering, Maranatha Christian University
      </div>
    </footer>

    <!-- Bootstrap 5 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <!-- Particles.js -->
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script>
      if (typeof particlesJS !== 'undefined') {
        particlesJS('particles-js', {
          particles: {
            number: { value: 25, density: { enable: true, value_area: 900 } },
            color: { value: '#00A0A5' },
            shape: { type: 'circle' },
            opacity: { value: 0.2, random: true },
            size: { value: 2.5, random: true },
            line_linked: {
              enable: true,
              distance: 150,
              color: '#00A0A5',
              opacity: 0.12,
              width: 1
            },
            move: {
              enable: true,
              speed: 1.0,
              direction: 'none',
              random: false,
              straight: false,
              out_mode: 'out',
              bounce: false
            }
          },
          retina_detect: true
        });
      }
    </script>
  </body>
</html>
