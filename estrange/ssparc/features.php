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
    <title>Features &amp; Gamification — S-SPARC</title>
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
      .feature-card-2026 {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        padding: 1.75rem;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      }
      .feature-card-2026:hover {
        transform: translateY(-4px);
        border-color: rgba(0, 160, 165, 0.4);
        box-shadow: 0 16px 36px -10px rgba(15, 23, 42, 0.08);
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
              <a class="nav-link active" href="features.php">Features &amp; Gamification</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="sustainability.php">Sustainability</a>
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

    <!-- Header Intro Section -->
    <section class="py-5">
      <div class="container">
        <div class="row align-items-center justify-content-between g-5 py-3">
          <div class="col-lg-6">
            <div class="mb-3">
              <div class="hero-pill font-mono">
                <span class="w-2 h-2 rounded-circle" style="background-color: var(--brand-teal);"></span>
                Platform Capabilities
              </div>
            </div>
            <h1 class="display-5 fw-extrabold text-slate-900 mb-3" style="letter-spacing: -0.03em; line-height: 1.2;">
              Discover What S-SPARC Can Do
            </h1>
            <p class="lead text-slate-600 mb-4" style="font-size: 1.05rem; line-height: 1.7;">
              S-SPARC combines generative code synthesis with vector semantic search to repurpose verified solutions, minimize redundant GPU compute, and deliver sub-second assistance.
            </p>
            <div class="d-flex flex-wrap gap-3">
              <a href="<?php echo $isLoggedIn ? 'courses.php' : '../index.php'; ?>" class="btn btn-teal">
                Launch Workspace &rarr;
              </a>
              <a href="#gamification" class="btn btn-outline-teal">
                Gamification Rules
              </a>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="bg-white border rounded-4 p-4 p-md-5 shadow-sm">
              <span class="text-xs font-mono text-slate-500 uppercase tracking-wider font-semibold d-block mb-3">Adaptive Architecture</span>
              <p class="text-slate-600 mb-3" style="font-size: 0.95rem; line-height: 1.75; text-align: justify;">
                By integrating adaptive caching and semantic prompt filtering, S-SPARC ensures AI systems operate with unprecedented efficiency, combining high code generation accuracy with measurable energy reduction.
              </p>
              <p class="text-slate-600 mb-0" style="font-size: 0.95rem; line-height: 1.75; text-align: justify;">
                For developers, students, and educators, S-SPARC delivers maximal output with minimal ecological impact, ensuring every prompt contributes to a sustainable digital ecosystem.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 6 Core Feature Grid -->
    <section class="py-5 bg-white border-top border-bottom">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto mb-5">
          <span class="text-xs font-mono font-bold uppercase tracking-wider text-teal-600" style="color: var(--brand-teal);">Core Modules</span>
          <h2 class="h2 fw-bold text-slate-900 mt-1 mb-2">Engineered Feature Set</h2>
          <p class="text-slate-500">Comprehensive tools for efficient code development and sustainable computing.</p>
        </div>

        <div class="row g-4">
          
          <div class="col-md-4">
            <div class="feature-card-2026 h-100">
              <div class="d-inline-flex p-3 rounded-3 mb-3" style="background-color: #f0fdfa; color: #00A0A5;">
                <i class="fa-solid fa-code fa-xl"></i>
              </div>
              <h3 class="h5 fw-bold text-slate-900 mb-2">Code Generation</h3>
              <p class="text-slate-600 mb-0" style="font-size: 0.925rem; line-height: 1.65;">
                Generates robust, production-ready code snippets with integrated unit testing and error diagnosis.
              </p>
            </div>
          </div>

          <div class="col-md-4">
            <div class="feature-card-2026 h-100">
              <div class="d-inline-flex p-3 rounded-3 mb-3" style="background-color: #fff7ed; color: #ea580c;">
                <i class="fa-solid fa-recycle fa-xl"></i>
              </div>
              <h3 class="h5 fw-bold text-slate-900 mb-2">Semantic Code Reuse</h3>
              <p class="text-slate-600 mb-0" style="font-size: 0.925rem; line-height: 1.65;">
                Identifies mathematically equivalent code patterns to eliminate duplicate generation and maintenance overhead.
              </p>
            </div>
          </div>

          <div class="col-md-4">
            <div class="feature-card-2026 h-100">
              <div class="d-inline-flex p-3 rounded-3 mb-3" style="background-color: #eff6ff; color: #2563eb;">
                <i class="fa-solid fa-database fa-xl"></i>
              </div>
              <h3 class="h5 fw-bold text-slate-900 mb-2">Adaptive Vector Caching</h3>
              <p class="text-slate-600 mb-0" style="font-size: 0.925rem; line-height: 1.65;">
                Dynamically indexes and optimizes caching parameters for high-frequency queries and classroom workloads.
              </p>
            </div>
          </div>

          <div class="col-md-4">
            <div class="feature-card-2026 h-100">
              <div class="d-inline-flex p-3 rounded-3 mb-3" style="background-color: #f0fdf4; color: #16a34a;">
                <i class="fa-solid fa-leaf fa-xl"></i>
              </div>
              <h3 class="h5 fw-bold text-slate-900 mb-2">Eco-Sustainability</h3>
              <p class="text-slate-600 mb-0" style="font-size: 0.925rem; line-height: 1.65;">
                Tracks and mitigates energy consumption (Wh) and carbon emissions per inference with live telemetry.
              </p>
            </div>
          </div>

          <div class="col-md-4">
            <div class="feature-card-2026 h-100">
              <div class="d-inline-flex p-3 rounded-3 mb-3" style="background-color: #faf5ff; color: #9333ea;">
                <i class="fa-solid fa-language fa-xl"></i>
              </div>
              <h3 class="h5 fw-bold text-slate-900 mb-2">Multilingual Support</h3>
              <p class="text-slate-600 mb-0" style="font-size: 0.925rem; line-height: 1.65;">
                Supports Python, Java, C++, PHP, JavaScript, and natural language explanations in Indonesian and English.
              </p>
            </div>
          </div>

          <div class="col-md-4">
            <div class="feature-card-2026 h-100">
              <div class="d-inline-flex p-3 rounded-3 mb-3" style="background-color: #fff1f2; color: #e11d48;">
                <i class="fa-solid fa-gamepad fa-xl"></i>
              </div>
              <h3 class="h5 fw-bold text-slate-900 mb-2">Student Gamification</h3>
              <p class="text-slate-600 mb-0" style="font-size: 0.925rem; line-height: 1.65;">
                Incentivizes efficient prompt construction, clean coding habits, and token conservation via live standings.
              </p>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Gamification Deep Dive Section -->
    <section id="gamification" class="py-5 my-3">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto mb-5">
          <span class="text-xs font-mono font-bold uppercase tracking-wider text-teal-600" style="color: var(--brand-teal);">Incentive Framework</span>
          <h2 class="h2 fw-bold text-slate-900 mt-1 mb-2">Gamification in S-SPARC</h2>
          <p class="text-slate-600">Encouraging sustainable platform usage and rigorous programming practice.</p>
        </div>

        <div class="row g-4">
          
          <div class="col-md-4">
            <div class="bg-white border rounded-4 p-4 h-100 shadow-sm d-flex flex-column justify-content-between">
              <div>
                <span class="text-xs font-mono text-slate-400 font-bold uppercase">Dimension 01</span>
                <h3 class="h5 fw-bold text-slate-900 mt-2 mb-2">Points &amp; Efficiency Badges</h3>
                <p class="text-slate-600 text-sm leading-relaxed" style="line-height: 1.7;">
                  Points are awarded based on token conservation and code quality. The fewer tokens consumed per inference, the higher the efficiency multiplier awarded to the student.
                </p>
              </div>
              <div class="mt-3 pt-3 border-top text-xs font-mono text-teal-700 font-semibold">
                Dynamic Score Multiplier
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="bg-white border rounded-4 p-4 h-100 shadow-sm d-flex flex-column justify-content-between">
              <div>
                <span class="text-xs font-mono text-slate-400 font-bold uppercase">Dimension 02</span>
                <h3 class="h5 fw-bold text-slate-900 mt-2 mb-2">Course Leaderboards</h3>
                <p class="text-slate-600 text-sm leading-relaxed" style="line-height: 1.7;">
                  Rankings highlight students with the greatest impact in code quality, originality, and digital carbon reduction, fostering healthy academic competition.
                </p>
              </div>
              <div class="mt-3 pt-3 border-top text-xs font-mono text-teal-700 font-semibold">
                Class Standings &amp; Tiers
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="bg-white border rounded-4 p-4 h-100 shadow-sm d-flex flex-column justify-content-between">
              <div>
                <span class="text-xs font-mono text-slate-400 font-bold uppercase">Dimension 03</span>
                <h3 class="h5 fw-bold text-slate-900 mt-2 mb-2">Challenges &amp; Socratic Tasks</h3>
                <p class="text-slate-600 text-sm leading-relaxed" style="line-height: 1.7;">
                  Coursework milestones offer additional XP rewards for students who optimize Big-O complexity and resolve edge cases using Socratic guidance.
                </p>
              </div>
              <div class="mt-3 pt-3 border-top text-xs font-mono text-teal-700 font-semibold">
                Academic Milestones
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

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
