<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>S-SPARC.AI - About</title>
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
              <a class="nav-link" aria-current="page" href="features.php">Features &amp; Gamification</a>
            </li>
            <li class="nav-item mx-3">
              <a class="nav-link" aria-current="page" href="sustainability.php">Sustainability</a>
            </li>
            <li class="nav-item mx-3">
              <a class="nav-link active" aria-current="page" href="about.php">About Us</a>
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
      <div class="container" style="min-height: 60vh">
        <div class="row justify-content-center">
          <div class="col-lg-8">
            <h1 class="fw-bold mb-3" style="color: teal">About S-SPARC</h1>
            <p class="mb-3" style="font-size: 1rem; text-align: justify">
              S-SPARC (Semantic Sustainable Programming and Resource-Conscious AI) is a research-driven initiative that explores how intelligent code generation systems can be made more efficient, transparent, and environmentally aware.
              Built in an academic context, the platform combines semantic search, code reuse, and environmental impact tracking to help developers write better software while understanding the hidden costs of computation.
            </p>
            <p class="mb-3" style="font-size: 1rem; text-align: justify">
              This project is part of an ongoing effort to align modern AI tooling with principles of sustainability. By surfacing metrics such as estimated energy usage, water consumption, and carbon footprint alongside generated code,
              S-SPARC invites users to reflect on the trade-offs behind every request and to discover patterns that reduce waste over time.
            </p>
            <p class="mb-3" style="font-size: 1rem; text-align: justify">
              The system is designed for students, educators, and practitioners who are curious not only about what AI can build, but also about how responsibly it can be operated. Through dashboards, visualizations, and playful gamification,
              S-SPARC encourages more thoughtful interaction with AI assistants—turning everyday coding tasks into an opportunity for learning and impact.
            </p>
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
