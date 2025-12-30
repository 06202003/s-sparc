<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>S-SPARC.AI - Features</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
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
              <a class="nav-link active" aria-current="page" href="features.php">Features &amp; Gamification</a>
            </li>
            <li class="nav-item mx-3">
              <a class="nav-link" aria-current="page" href="sustainability.php">Sustainability</a>
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
      <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 70vh">
          <div class="col-lg-12">
            <h1 class="mb-2 fw-bolder" style="color: teal">Discover What S-SPARC Can Do</h1>
            <h3 class="mb-4 fw-bold">Pioneering Intelligent, Sustainable AI</h3>
            <p class="lead mb-4" style="font-size: 1rem">
              S-SPARC redefines the boundaries of generative AI by intelligently reusing code through semantic equivalence, transforming routine computation into an exercise in efficiency and sustainability. Leveraging the power of GPT-4
              and embedding-based semantic search, it identifies and repurposes previously generated code, eliminating redundancy while preserving the highest standards of output quality.
            </p>
            <p class="mb-4" style="font-size: 1rem">
              This groundbreaking system delivers faster inference, optimized token utilization, and substantial reductions in energy consumption, translating directly into a significant decrease in carbon footprint. By integrating adaptive
              caching and semantic prompt filtering, S-SPARC ensures AI systems operate with unprecedented efficiency, combining performance, environmental responsibility, and operational cost-effectiveness.
            </p>
            <p class="mb-0" style="font-size: 1rem">
              For developers, researchers, and enterprises, S-SPARC is not merely a tool—it is a strategic innovation that empowers AI to think smarter, act faster, and tread lightly on the planet. Experience the future of generative AI
              where maximal output meets minimal ecological impact, and every line of code contributes to a sustainable digital ecosystem.
            </p>
          </div>
          <div class="col-lg-12 mt-5">
            <div class="row row-cols-1 row-cols-md-3 g-4">
              <div class="col">
                <div class="card h-100 px-2 border-0 shadow-sm">
                  <div class="card-body text-center d-flex flex-column justify-content-center" style="min-height: 200px">
                    <i class="fas fa-code fa-2x mb-3 text-primary"></i>
                    <h5 class="card-title fw-bold">Code Generation</h5>
                    <p class="card-text">
                      Automatically generates production-ready code using advanced AI.
                    </p>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card h-100 px-2 border-0 shadow-sm">
                  <div class="card-body text-center d-flex flex-column justify-content-center" style="min-height: 200px">
                    <i class="fas fa-recycle fa-2x mb-3 text-warning"></i>
                    <h5 class="card-title fw-bold">Semantic Code Reuse</h5>
                    <p class="card-text">
                      Identifies and reuses equivalent code to reduce duplication and maintenance effort.
                  </p>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card h-100 px-2 border-0 shadow-sm">
                  <div class="card-body text-center d-flex flex-column justify-content-center" style="min-height: 200px">
                    <i class="fas fa-database fa-2x mb-3 text-info"></i>
                    <h5 class="card-title fw-bold">Adaptive Caching</h5>
                    <p class="card-text">
                      Dynamically optimises caching to improve performance under varying workloads.
                    </p>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card h-100 px-2 border-0 shadow-sm">
                  <div class="card-body text-center d-flex flex-column justify-content-center" style="min-height: 200px">
                    <i class="fas fa-leaf fa-2x mb-3" style="color: teal"></i>
                    <h5 class="card-title fw-bold">Sustainability</h5>
                    <p class="card-text">
                      Reduces energy consumption by optimising computational processes.
                    </p>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card h-100 px-2 border-0 shadow-sm">
                  <div class="card-body text-center d-flex flex-column justify-content-center" style="min-height: 200px">
                    <i class="fas fa-language fa-2x mb-3 text-success"></i>
                    <h5 class="card-title fw-bold">Multilingual</h5>
                    <p class="card-text">
                      Supports multiple programming and natural languages for global collaboration.
                    </p>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card h-100 px-2 border-0 shadow-sm">
                  <div class="card-body text-center d-flex flex-column justify-content-center" style="min-height: 200px">
                    <i class="fas fa-gamepad fa-2x mb-3 text-danger"></i>
                    <h5 class="card-title fw-bold">Gamification</h5>
                    <p class="card-text">
                      Encourages engagement through rewarding and motivating user interactions.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="section mt-5">
      <div class="container">
        <div class="row">
          <div class="col-lg-12">
            <h1 class="fw-bold mb-3" style="color: teal">Gamification in S-SPARC</h1>
            <p class="mb-3" style="font-size: 1rem">
              Gamification in S-SPARC.AI is designed to boost user motivation and engagement for sustainable platform usage. By implementing game elements such as points, badges, leaderboards, and daily challenges, users are encouraged to
              actively contribute, share solutions, and adopt environmentally friendly coding practices.
            </p>
            <ul class="mb-3" style="font-size: 1rem">
              <li>
                <strong>Points &amp; Badges:</strong> Users earn points each time they utilize efficiency features, perform code reuse, or participate in community activities. Points are calculated based on token usage— the fewer tokens
                consumed per GPT inference, the more points are awarded. Accumulated points unlock special badges as a form of recognition.
              </li>
              <li><strong>Leaderboard:</strong> The ranking system highlights the most active users and those who have the greatest impact in reducing digital carbon footprint, fostering healthy competition.</li>
              <li><strong>Challenges &amp; Missions:</strong> Daily or weekly challenges, such as optimizing code or minimizing token usage, offer additional rewards for users who successfully complete them.</li>
            </ul>
            <p style="font-size: 1rem">Through this approach, S-SPARC not only promotes technical efficiency but also builds a community that values sustainability and innovation through a fun and interactive experience.</p>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
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
