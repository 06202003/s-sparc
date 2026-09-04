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
    <title>S-SPARC — Semantic Programming Assistant & Code Reuse System</title>
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
      .video-frame-2026 {
        position: relative;
        height: 420px;
        overflow: hidden;
        border-radius: 1.5rem;
        border: 1px solid #334155;
        box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.25);
      }
      .faq-item-2026 .faq-question {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.875rem;
        padding: 1.1rem 1.35rem;
        font-weight: 600;
        color: #0f172a;
        transition: all 0.2s ease;
      }
      .faq-item-2026 .faq-question:not(.collapsed) {
        background: #f8fafc;
        border-color: var(--brand-teal);
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
      }
      .faq-item-2026 .faq-icon {
        transition: transform 0.25s ease;
      }
      .faq-item-2026 .faq-question:not(.collapsed) .faq-icon {
        transform: rotate(180deg);
        color: var(--brand-teal);
      }
      .faq-item-2026 .faq-answer {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-top: 0;
        border-bottom-left-radius: 0.875rem;
        border-bottom-right-radius: 0.875rem;
        padding: 1.25rem 1.35rem;
        font-size: 0.925rem;
        line-height: 1.7;
        color: #475569;
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
              <a class="nav-link active" href="index.php">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="features.php">Features &amp; Gamification</a>
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

    <!-- Hero Section -->
    <section class="py-5">
      <div class="container">
        <div class="flex flex-wrap align-items-center justify-content-between g-5 py-4">
          
          <!-- Left Hero Column -->
          <div class="w-full lg:w-1/2">
            <div class="mb-3">
              <div class="hero-pill font-mono">
                <span class="w-2 h-2 rounded-circle bg-teal-500" style="background-color: var(--brand-teal);"></span>
                Academic Research Platform
              </div>
            </div>

            <h1 class="display-4 fw-extrabold text-slate-900 mb-3" style="letter-spacing: -0.03em; line-height: 1.15;">
              Intelligent Code Reuse &amp; Sustainable AI
            </h1>

            <p class="lead text-slate-600 mb-4" style="font-size: 1.1rem; line-height: 1.65;">
              Accelerate programming learning with vector semantic similarity and sub-50ms cache turnaround. Built specifically for Maranatha Coders to eliminate redundant cloud GPU compute.
            </p>

            <div class="d-flex flex-wrap gap-3">
              <a href="<?php echo $isLoggedIn ? 'courses.php' : '../index.php'; ?>" class="btn btn-teal btn-lg px-4">
                Launch Workspace &rarr;
              </a>
              <a href="#why-ssparc" class="btn btn-outline-teal btn-lg px-4">
                System Overview
              </a>
            </div>
          </div>

          <!-- Right Research Narrative Card -->
          <div class="w-full lg:w-1/2">
            <div class="bg-white border rounded-4 p-4 p-md-5 shadow-sm">
              <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                <span class="text-xs font-mono text-slate-500 uppercase tracking-wider font-semibold">Semantic Architecture</span>
                <span class="badge bg-teal-50 text-teal-800 border font-mono" style="background-color: #f0fdfa; color: #0f766e; border-color: #ccfbf1;">
                  Cosine Distance &ge; 0.90
                </span>
              </div>
              <p class="text-slate-600 mb-3" style="font-size: 0.95rem; line-height: 1.75; text-align: justify;">
                Traditional AI assistants perform full generative inference for every prompt, resulting in massive GPU energy consumption. S-SPARC addresses this by comparing new coding prompts against verified solution embeddings.
              </p>
              <p class="text-slate-600 mb-0" style="font-size: 0.95rem; line-height: 1.75; text-align: justify;">
                High-confidence matches trigger zero-token, instant local vector retrieval. When novel solutions are required, cloud inference is invoked and the output is automatically indexed into the knowledge base for future reuse.
              </p>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Video Showcase Section -->
    <section class="py-4">
      <div class="container">
        <div class="video-frame-2026 position-relative text-center">
          <video autoplay loop muted playsinline style="width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 0;">
            <source src="https://cdn.jsdelivr.net/gh/06202003/MainPortfolio/data/No%20Copyright%2C%20free%20to%20use.mp4" type="video/mp4" />
          </video>
          
          <div class="position-absolute top-50 start-50 translate-middle w-100 px-3" style="z-index: 2">
            <div class="hero-pill mb-3 bg-black/40 text-white border-white/20 font-mono" style="background: rgba(0,0,0,0.5); color: #ffffff; border: 1px solid rgba(255,255,255,0.2);">
              Platform Demonstration
            </div>
            <h2 id="video-message" class="display-6 fw-bold text-white mb-0" style="text-shadow: 0 4px 20px rgba(0, 0, 0, 0.85); transition: opacity 0.6s ease;">
              Efficiency Through Intelligence
            </h2>
          </div>
          <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(180deg, rgba(15, 23, 42, 0.55) 0%, rgba(15, 23, 42, 0.85) 100%); z-index: 1;"></div>
        </div>
      </div>
    </section>

    <!-- Why S-SPARC 3-Pillar Section -->
    <section id="why-ssparc" class="py-5 my-3">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto mb-5">
          <span class="text-xs font-mono font-bold uppercase tracking-wider text-teal-600" style="color: var(--brand-teal);">Core Value Pillars</span>
          <h2 class="h2 fw-bold text-slate-900 mt-1 mb-3">Engineered for Resource Efficiency</h2>
          <p class="text-slate-600 mx-auto" style="max-width: 680px; font-size: 0.975rem;">
            A hybrid computational framework combining semantic vector retrieval, automated knowledge expansion, and student gamification.
          </p>
        </div>

        <div class="flex flex-wrap g-4">
          
          <!-- Pillar 1 -->
          <div class="w-full md:w-1/3">
            <div class="feature-card-2026 h-100 d-flex flex-column justify-content-between">
              <div>
                <div class="d-inline-flex p-3 rounded-3 mb-3" style="background-color: #f0fdfa; color: #00A0A5;">
                  <i class="fa-solid fa-bolt fa-xl"></i>
                </div>
                <h3 class="h5 fw-bold text-slate-900 mb-2">Fast-Path Vector Cache</h3>
                <p class="text-slate-600 mb-0" style="font-size: 0.925rem; line-height: 1.65;">
                  Matches student questions against verified solutions at &ge;90% cosine similarity, serving responses in milliseconds with 0 tokens consumed.
                </p>
              </div>
              <div class="mt-4 pt-3 border-top text-xs font-mono text-slate-500">
                Turnaround: &lt; 50ms
              </div>
            </div>
          </div>

          <!-- Pillar 2 -->
          <div class="w-full md:w-1/3">
            <div class="feature-card-2026 h-100 d-flex flex-column justify-content-between">
              <div>
                <div class="d-inline-flex p-3 rounded-3 mb-3" style="background-color: #fff7ed; color: #ea580c;">
                  <i class="fa-solid fa-code-branch fa-xl"></i>
                </div>
                <h3 class="h5 fw-bold text-slate-900 mb-2">Self-Growing Knowledge Base</h3>
                <p class="text-slate-600 mb-0" style="font-size: 0.925rem; line-height: 1.65;">
                  When novel problems are synthesized by Cloud LLMs, S-SPARC automatically indexes the solution into local vector embeddings for future free access.
                </p>
              </div>
              <div class="mt-4 pt-3 border-top text-xs font-mono text-slate-500">
                Automated Indexing
              </div>
            </div>
          </div>

          <!-- Pillar 3 -->
          <div class="w-full md:w-1/3">
            <div class="feature-card-2026 h-100 d-flex flex-column justify-content-between">
              <div>
                <div class="d-inline-flex p-3 rounded-3 mb-3" style="background-color: #f0fdf4; color: #16a34a;">
                  <i class="fa-solid fa-leaf fa-xl"></i>
                </div>
                <h3 class="h5 fw-bold text-slate-900 mb-2">Eco-Aware Gamification</h3>
                <p class="text-slate-600 mb-0" style="font-size: 0.925rem; line-height: 1.65;">
                  Students earn points and leaderboard rank by writing clean, efficient code and conserving computational token quotas through precise prompting.
                </p>
              </div>
              <div class="mt-4 pt-3 border-top text-xs font-mono text-slate-500">
                Green Computing
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-5 bg-white border-top border-bottom">
      <div class="container">
        <div class="text-center max-w-2xl mx-auto mb-5">
          <span class="text-xs font-mono font-bold uppercase tracking-wider text-teal-600" style="color: var(--brand-teal);">FAQ</span>
          <h2 class="h2 fw-bold text-slate-900 mt-1 mb-2">Frequently Asked Questions</h2>
          <p class="text-slate-500">Pertanyaan umum seputar arsitektur semantik, kebijakan kuota token, dan integrasi LMS.</p>
        </div>

        <div class="flex flex-wrap justify-content-center">
          <div class="w-full lg:w-5/6">
            <div class="accordion accordion-flush" id="faqAccordion">

              <!-- FAQ 1 -->
              <div class="faq-item-2026 mb-3">
                <button class="faq-question d-flex align-items-center w-100 border-0" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true">
                  <span class="me-3 text-slate-400 font-mono text-xs">01</span>
                  <span class="flex-grow-1 text-start">Apa itu S-SPARC dan tujuannya?</span>
                  <i class="fa-solid fa-chevron-down faq-icon text-slate-400 text-xs ms-2"></i>
                </button>
                <div id="faq1" class="collapse show" data-bs-parent="#faqAccordion">
                  <div class="faq-answer">
                    S-SPARC adalah asisten pemrograman cerdas yang dirancang untuk mengoptimalkan proses bantuan koding mahasiswa. Dengan membandingkan pertanyaan terhadap bank solusi terverifikasi menggunakan algoritma <strong>semantic similarity</strong>, sistem memberikan jawaban instan sekaligus menghemat energi komputasi data center.
                  </div>
                </div>
              </div>

              <!-- FAQ 2 -->
              <div class="faq-item-2026 mb-3">
                <button class="faq-question d-flex align-items-center w-100 border-0 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false">
                  <span class="me-3 text-slate-400 font-mono text-xs">02</span>
                  <span class="flex-grow-1 text-start">Bagaimana cara S-SPARC menghemat kuota token?</span>
                  <i class="fa-solid fa-chevron-down faq-icon text-slate-400 text-xs ms-2"></i>
                </button>
                <div id="faq2" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer">
                    Ketika pertanyaan memiliki kemiripan &ge;90% dengan solusi di database, S-SPARC langsung mengambil jawaban dari vector cache lokal. Pengambilan ini berstatus <strong>100% GRATIS (0 Token)</strong> dan tidak memotong poin gamifikasi mahasiswa.
                  </div>
                </div>
              </div>

              <!-- FAQ 3 -->
              <div class="faq-item-2026 mb-3">
                <button class="faq-question d-flex align-items-center w-100 border-0 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false">
                  <span class="me-3 text-slate-400 font-mono text-xs">03</span>
                  <span class="flex-grow-1 text-start">Kapan inferensi Cloud AI dijalankan?</span>
                  <i class="fa-solid fa-chevron-down faq-icon text-slate-400 text-xs ms-2"></i>
                </button>
                <div id="faq3" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer">
                    Jika pertanyaan mahasiswa bersifat baru (kemiripan di bawah threshold), router otomatis memanggil model Google Gemini Flash Lite. Setelah respon selesai dibuat, modul <em>Self-Growing</em> otomatis mengindeksnya agar query serupa di masa depan menjadi gratis.
                  </div>
                </div>
              </div>

              <!-- FAQ 4 -->
              <div class="faq-item-2026 mb-3">
                <button class="faq-question d-flex align-items-center w-100 border-0 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false">
                  <span class="me-3 text-slate-400 font-mono text-xs">04</span>
                  <span class="flex-grow-1 text-start">Bagaimana integrasi dengan E-STRANGE?</span>
                  <i class="fa-solid fa-chevron-down faq-icon text-slate-400 text-xs ms-2"></i>
                </button>
                <div id="faq4" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer">
                    S-SPARC terhubung langsung secara Single Sign-On (SSO) dengan database E-STRANGE. Asisten membaca kelas aktif, tugas praktikum yang sedang dikerjakan, dan tenggat waktu pengerjaan untuk memastikan bantuan koding tetap relevan dengan konteks akademik.
                  </div>
                </div>
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
            number: { value: 30, density: { enable: true, value_area: 900 } },
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
          interactivity: {
            detect_on: 'canvas',
            events: {
              onhover: { enable: true, mode: 'grab' },
              onclick: { enable: true, mode: 'push' },
              resize: true
            },
            modes: {
              grab: { distance: 130, line_linked: { opacity: 0.25 } }
            }
          },
          retina_detect: true
        });
      }

      // Video Message Rotator
      const messages = ['Efficiency Through Intelligence', 'Sustainable Code Assistance', 'Faster Turnaround, Lower Footprint'];
      let idx = 0;
      const msgEl = document.getElementById('video-message');
      if (msgEl) {
        setInterval(() => {
          msgEl.style.opacity = 0;
          setTimeout(() => {
            idx = (idx + 1) % messages.length;
            msgEl.textContent = messages[idx];
            msgEl.style.opacity = 1;
          }, 600);
        }, 3600);
      }
    </script>
  </body>
</html>
