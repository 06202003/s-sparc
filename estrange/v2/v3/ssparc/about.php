<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$sso_user_id = $_SESSION['user_id'] ?? '';
$sso_username = $_SESSION['username'] ?? 'User';
$sso_name = $_SESSION['name'] ?? $sso_username;
$sso_role = $_SESSION['role'] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>About Us — S-SPARC Academic Research</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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
        background-color: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid rgba(226, 232, 240, 0.9);
      }
      .navbar-brand img {
        height: 56px;
        width: auto;
        object-fit: contain;
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
      .feature-card-2026 {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        padding: 1.75rem;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      }
      .feature-card-2026:hover {
        transform: translateY(-3px);
        border-color: rgba(0, 160, 165, 0.4);
        box-shadow: 0 14px 32px -10px rgba(15, 23, 42, 0.08);
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
              <a class="nav-link" href="sustainability.php">Sustainability</a>
            </li>
            <li class="nav-item">
              <a class="nav-link active" href="about.php">About Us</a>
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

    <!-- Main Container -->
    <main class="container py-5">
      
      <!-- Top Overview Section -->
      <div class="row align-items-center justify-content-between g-4 mb-5 pb-3">
        
        <!-- Left: About Narrative -->
        <div class="col-12 col-lg-7 mb-4 mb-lg-0">
          <div class="pe-lg-3">
            <div class="mb-3">
              <div class="hero-pill font-mono">
                <span class="w-2 h-2 rounded-circle" style="background-color: var(--brand-teal);"></span>
                Academic Research
              </div>
            </div>
            <h1 class="display-5 fw-extrabold text-slate-900 mb-3" style="letter-spacing: -0.03em; line-height: 1.25;">
              About S-SPARC <span class="text-nowrap">Academic Research</span>
            </h1>
            <p class="lead text-slate-600 mb-4" style="font-size: 1.05rem; line-height: 1.7;">
              An applied AI research initiative developed at the Faculty of Smart Technology &amp; Engineering, Maranatha Christian University. Focuses on eliminating compute redundancy through vector semantic code reuse.
            </p>
            <div class="d-flex flex-wrap gap-3">
              <a href="<?php echo $isLoggedIn ? 'courses.php' : '../index.php'; ?>" class="btn btn-teal">
                Open Assistant Workspace &rarr;
              </a>
              <a href="../student_dashboard.php" class="btn btn-outline-teal">
                E-STRANGE Portal
              </a>
            </div>
          </div>
        </div>

        <!-- Right: Institutional Context & Mission Card -->
        <div class="col-12 col-lg-5">
          <div class="bg-white border rounded-4 p-4 p-md-5 shadow-sm">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
              <span class="text-xs font-mono text-slate-500 uppercase tracking-wider font-semibold">Institutional Mission</span>
              <span class="badge bg-teal-50 text-teal-800 border font-mono" style="background-color: #f0fdfa; color: #0f766e; border-color: #ccfbf1;">
                Sustainable Computing
              </span>
            </div>
            <p class="text-slate-600 mb-3" style="font-size: 0.95rem; line-height: 1.75; text-align: justify;">
              Traditional AI models generate answers from scratch for every prompt, resulting in severe GPU energy overhead. S-SPARC integrates vector cosine similarity (&ge;90%) to retrieve verified solutions locally.
            </p>
            <p class="text-slate-600 mb-0" style="font-size: 0.95rem; line-height: 1.75; text-align: justify;">
              This architecture cuts compute energy and carbon emissions by over 60%, establishing a responsible, low-footprint foundation for computer science higher education.
            </p>
          </div>
        </div>

      </div>

      <!-- Project Governance Section -->
      <div class="pt-2 mb-4">
        <div class="text-center max-w-2xl mx-auto mb-5">
          <span class="text-xs font-mono font-bold uppercase tracking-wider text-teal-600" style="color: var(--brand-teal);">Project Governance</span>
          <h2 class="h2 fw-bold text-slate-900 mt-1 mb-2">Contributors &amp; Academic Leadership</h2>
          <p class="text-slate-500">The academic researchers, advisors, and engineers behind the S-SPARC architecture.</p>
        </div>

        <div class="row g-4">
          
          <!-- 1. Yehezkiel David Setiawan, S.Kom., M.Kom. (Lead) -->
          <div class="col-12 mb-2">
            <div class="feature-card-2026 border-2 p-4 p-md-5" style="border-color: rgba(0, 160, 165, 0.35); background: linear-gradient(135deg, #ffffff 0%, #f0fdfa 100%);">
              <div>
                <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                  <h3 class="h4 fw-bold text-slate-900 mb-0">Yehezkiel David Setiawan, S.Kom., M.Kom.</h3>
                  <span class="badge bg-teal-100 text-teal-800 border font-mono px-3 py-1.5 text-[11px]" style="background-color: #ccfbf1; color: #0f766e; border-color: #99f6e4;">
                    Primary Creator &amp; Project Lead
                  </span>
                </div>
                <p class="text-slate-600 text-sm mb-0 font-medium leading-relaxed">
                  Core Architecture, Semantic Vector Engine &amp; Full-Stack System Implementation
                </p>
              </div>
            </div>
          </div>

          <!-- 2. Oscar Karnalim, S.T., M.T., Ph.D. -->
          <div class="col-12 col-md-6 mb-2">
            <div class="feature-card-2026 h-100 p-4">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <h4 class="h5 fw-bold text-slate-900 mb-0">Oscar Karnalim, S.T., M.T., Ph.D.</h4>
                <span class="badge bg-blue-50 text-blue-700 border font-mono px-2.5 py-1 text-[11px]" style="background-color: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;">
                  Academic &amp; Research Advisory
                </span>
              </div>
              <p class="text-slate-600 text-xs mb-0 leading-relaxed" style="line-height: 1.65;">
                Research direction, pedagogic evaluation frameworks, and scientific methodology oversight.
              </p>
            </div>
          </div>

          <!-- 3. Andreas Widjaja, S.Si., M.Sc., Ph.D. -->
          <div class="col-12 col-md-6 mb-2">
            <div class="feature-card-2026 h-100 p-4">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <h4 class="h5 fw-bold text-slate-900 mb-0">Andreas Widjaja, S.Si., M.Sc., Ph.D.</h4>
                <span class="badge bg-blue-50 text-blue-700 border font-mono px-2.5 py-1 text-[11px]" style="background-color: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;">
                  Academic &amp; Research Advisory
                </span>
              </div>
              <p class="text-slate-600 text-xs mb-0 leading-relaxed" style="line-height: 1.65;">
                Curriculum alignment, assessment validation, and higher-education integration strategy.
              </p>
            </div>
          </div>

          <!-- 4. Johanes Mario Pranata Listianto, S.Kom. -->
          <div class="col-12 col-md-6 mb-2">
            <div class="feature-card-2026 h-100 p-4">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <h4 class="h5 fw-bold text-slate-900 mb-0">Johanes Mario Pranata Listianto, S.Kom.</h4>
                <span class="badge bg-green-50 text-green-700 border font-mono px-2.5 py-1 text-[11px]" style="background-color: #f0fdf4; color: #15803d; border-color: #bbf7d0;">
                  Infrastructure &amp; Deployment Operations
                </span>
              </div>
              <p class="text-slate-600 text-xs mb-0 leading-relaxed" style="line-height: 1.65;">
                Server infrastructure orchestration, daemon services, telemetry monitoring, and deployment pipelines.
              </p>
            </div>
          </div>

          <!-- 5. Archangela Sheilla Haryanto Sundjaya -->
          <div class="col-12 col-md-6 mb-2">
            <div class="feature-card-2026 h-100 p-4">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <h4 class="h5 fw-bold text-slate-900 mb-0">Archangela Sheilla Haryanto Sundjaya</h4>
                <span class="badge bg-purple-50 text-purple-700 border font-mono px-2.5 py-1 text-[11px]" style="background-color: #faf5ff; color: #7e22ce; border-color: #e9d5ff;">
                  Design &amp; Visual Identity
                </span>
              </div>
              <p class="text-slate-600 text-xs mb-0 leading-relaxed" style="line-height: 1.65;">
                Brand identity systems, visual design language, interface aesthetics, and user experience workflows.
              </p>
            </div>
          </div>

        </div>
      </div>

    </main>

    <!-- Footer -->
    <footer class="bg-white py-4 text-slate-500 text-xs border-top">
      <div class="container text-center">
        &copy; <?= date('Y') ?> <span class="fw-semibold text-slate-700">S-SPARC Team</span> &bull; Faculty of Smart Technology &amp; Engineering, Maranatha Christian University
      </div>
    </footer>

    <!-- Bootstrap 5 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
