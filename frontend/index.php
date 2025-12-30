<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>S-SPARC.AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
  </head>
  <body style="font-family: 'Plus Jakarta Sans', sans-serif; background: radial-gradient(circle at top left, #e0f7f4, #f5faf8);">
    <!-- Animated background layer -->
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
              <a class="nav-link active" aria-current="page" href="index.php">Home</a>
            </li>
            <li class="nav-item mx-3">
              <a class="nav-link" aria-current="page" href="features.php">Features &amp; Gamification</a>
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
          <div class="col-lg-6 col-md-10 mb-5 mb-lg-0 d-flex flex-column justify-content-center align-items-start">
            <h1 class="fw-bold mb-3" style="font-size: 3.5rem; line-height: 1.1">
              <span style="color: teal">S-SPARC</span>
            </h1>
            <h2 class="fw-semibold mb-4" style="font-size: 2rem; color: #212529">Efficient Code Generation, Optimized by Advanced Semantic Intelligence.</h2>
            <p class="mb-4" style="font-size: 1rem; opacity: 0.92; line-height: 1.6">
              Accelerate your development with AI-driven code reuse and semantic similarity.<br />
              Designed for Maranatha Coders, built for the future.
            </p>
            <div>
              <a href="login.php" class="btn btn-primary btn-lg px-5 me-3 mb-2 mb-sm-0" style="background-color: teal !important">Get Started</a>
              <a href="#faqAccordion" class="btn btn-outline-primary btn-lg px-5 learn-more-btn" style="border-color: teal; color: teal">Learn More</a>
              <style>
                .learn-more-btn:hover,
                .learn-more-btn:focus {
                  background-color: teal !important;
                  color: #fff !important;
                  border-color: teal !important;
                }
              </style>
            </div>
          </div>
          <div class="col-lg-6 col-md-10 d-flex flex-column justify-content-center align-items-start">
            <p style="font-size: 1.05rem; line-height: 1.7; padding: 2rem; border-radius: 0.5rem; margin: 0; text-align: justify">
              We believe code generation can be faster, cleaner, and more resource efficient. S-SPARC is a semantic driven code assistant built to reduce redundant computation, accelerate development, and promote sustainable AI practices.
              <br /><br />
              Powered by a reuse optimized architecture and developed within an academic research environment, S-SPARC ensures every response is context aware, token efficient, and transparently produced. With semantic similarity analysis
              at its core, S-SPARC helps you generate better code with lower computational cost, minimal energy waste, and a commitment to responsible AI innovation.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="section mt-5 pb-5">
      <div class="container">
        <div class="row">
          <div class="col-12 text-center mb-4 position-relative" style="height: 400px; overflow: hidden; border-radius: 1rem">
            <video autoplay loop muted playsinline style="width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 0; border-radius: 1rem">
              <source src="https://cdn.jsdelivr.net/gh/06202003/MainPortfolio/data/No%20Copyright%2C%20free%20to%20use.mp4" type="video/mp4" />
              Your browser does not support the video tag.
            </video>
            <div class="position-absolute top-50 start-50 translate-middle w-100" style="z-index: 2">
              <h2 id="video-message" class="fw-bold text-white" style="text-shadow: 0 2px 16px rgba(0, 0, 0, 0.7); transition: opacity 0.7s; font-size: 3rem">Efficiency Through Intelligence</h2>
            </div>
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(180deg, rgba(0, 0, 0, 0.55) 0%, rgba(0, 0, 0, 0.7) 100%); z-index: 1; border-radius: 1rem"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="section py-5">
      <div class="container">
        <div class="row justify-content-center mb-4">
          <div class="col-lg-10">
            <h1 class="fw-bold mb-3 text-center">Why S-SPARC</h1>
            <p class="lead text-center" style="text-align: justify">
              S-SPARC is designed to accelerate your coding process by reusing and optimizing code with advanced semantic intelligence. Instead of generating code from scratch every time, S-SPARC identifies similarities, retrieves relevant
              solutions, and delivers efficient, high-quality results—saving time, energy, and resources.
            </p>
          </div>
        </div>

        <div class="row justify-content-center">
          <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-0">
              <div class="card-body text-start">
                <div class="mb-3">
                  <span class="fa-stack fa-2x" style="vertical-align: middle">
                    <i class="fa fa-circle fa-stack-2x" style="color: #e0f7fa"></i>
                    <i class="fa-solid fa-brain fa-stack-1x" style="color: #16161d"></i>
                  </span>
                </div>
                <h5 class="card-title fw-semibold mb-2">Semantic Intelligence</h5>
                <p class="card-text mb-0">Understands your intent and context to deliver code that fits your needs, not just generic snippets.</p>
              </div>
            </div>
          </div>
          <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-0">
              <div class="card-body text-start">
                <div class="mb-3">
                  <span class="fa-stack fa-2x" style="vertical-align: middle">
                    <i class="fa fa-circle fa-stack-2x" style="color: #fff3e0"></i>
                    <i class="fa-solid fa-code-branch fa-stack-1x" style="color: #ff9800"></i>
                  </span>
                </div>
                <h5 class="card-title fw-semibold mb-2">Efficient Code Reuse</h5>
                <p class="card-text mb-0">Reduces redundant computation by reusing and adapting previous solutions, making development faster and more sustainable.</p>
              </div>
            </div>
          </div>
          <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-0">
              <div class="card-body text-start">
                <div class="mb-3">
                  <span class="fa-stack fa-2x" style="vertical-align: middle">
                    <i class="fa fa-circle fa-stack-2x" style="color: #e8f5e9"></i>
                    <i class="fa-solid fa-leaf fa-stack-1x" style="color: #43a047"></i>
                  </span>
                </div>
                <h5 class="card-title fw-semibold mb-2">Sustainable AI</h5>
                <p class="card-text mb-0">Minimizes energy consumption and token usage, supporting responsible and eco-friendly AI practices.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="section py-5">
      <div class="container">
        <div class="row justify-content-center mb-4">
          <div class="col-lg-12">
            <h1 class="fw-bold mb-3 text-center">FAQ</h1>
          </div>
        </div>
        <div class="row justify-content-center">
          <div class="col-lg-12">
            <div id="faqAccordion" class="faq-modern">
              <!-- FAQ Item 1 -->
              <!-- <div class="faq-item mb-3">
                <button class="faq-question d-flex align-items-center w-100 px-4 py-3 border-0 rounded-3 shadow-sm bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true" aria-controls="faq1">
                  <span class="me-3 flex-shrink-0">
                    <i class="fa-solid fa-lightbulb" style="color: #ffd600"></i>
                  </span>
                  <span class="fw-semibold flex-grow-1 text-start">What is S-SPARC?</span>
                  <span class="faq-icon ms-3">
                    <i class="fa-solid fa-chevron-down" style="color: #ffd600"></i>
                  </span>
                </button>
                <div id="faq1" class="collapse show" data-bs-parent="#faqAccordion">
                  <div class="faq-answer px-4 py-3 bg-light rounded-bottom-3 border-top">
                    S-SPARC is an AI assistant designed to support efficient, intelligent programming workflows. It uses <b>semantic similarity</b> to analyze user requests, compare them against historical data, and surface the most relevant
                    solutions. This approach not only speeds up coding, but also reduces energy use and computational resources. S-SPARC can be used by anyone who wants to increase productivity in software development, from academic projects
                    to professional work and self-learning.
                  </div>
                </div>
              </div> -->
              <!-- FAQ Item 2 -->
              <div class="faq-item mb-3">
                <button
                  class="faq-question d-flex align-items-center w-100 px-4 py-3 border-0 rounded-3 shadow-sm bg-white collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#faq2"
                  aria-expanded="false"
                  aria-controls="faq2"
                >
                  <span class="me-3 flex-shrink-0">
                    <i class="fa-solid fa-layer-group" style="color: #7c4dff"></i>
                  </span>
                  <span class="fw-semibold flex-grow-1 text-start">What makes S-SPARC different from other AI chatbots?</span>
                  <span class="faq-icon ms-3">
                    <i class="fa-solid fa-chevron-down" style="color: #7c4dff"></i>
                  </span>
                </button>
                <div id="faq2" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer px-4 py-3 bg-light rounded-bottom-3 border-top">
                    Unlike many generative chatbots that always create answers from scratch, S-SPARC identifies patterns, context, and similarity between new requests and previously solved cases. It then <b>reuses</b> proven solutions and
                    adapts them to the current need. This makes responses faster, reduces computational load, and improves consistency and quality. S-SPARC also emphasizes transparency, so users can understand how each answer is produced.
                  </div>
                </div>
              </div>
              <!-- FAQ Item 3 -->
              <div class="faq-item mb-3">
                <button
                  class="faq-question d-flex align-items-center w-100 px-4 py-3 border-0 rounded-3 shadow-sm bg-white collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#faq3"
                  aria-expanded="false"
                  aria-controls="faq3"
                >
                  <span class="me-3 flex-shrink-0">
                    <i class="fa-solid fa-user-graduate" style="color: #00bfae"></i>
                  </span>
                  <span class="fw-semibold flex-grow-1 text-start">Is S-SPARC only for IT students?</span>
                  <span class="faq-icon ms-3">
                    <i class="fa-solid fa-chevron-down" style="color: #00bfae"></i>
                  </span>
                </button>
                <div id="faq3" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer px-4 py-3 bg-light rounded-bottom-3 border-top">
                    No. S-SPARC is built for a wide range of users: students, lecturers, researchers, and anyone who wants to learn or build code-based solutions. You do not need a deep technical background to benefit from S-SPARC. A friendly
                    interface and detailed explanations allow users to ask questions or request code and receive solutions that are easy to understand and ready to use. S-SPARC can also provide additional references to help users grasp the
                    concepts behind each solution.
                  </div>
                </div>
              </div>
              <!-- FAQ Item 4 -->
              <div class="faq-item mb-3">
                <button
                  class="faq-question d-flex align-items-center w-100 px-4 py-3 border-0 rounded-3 shadow-sm bg-white collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#faq4"
                  aria-expanded="false"
                  aria-controls="faq4"
                >
                  <span class="me-3 flex-shrink-0">
                    <i class="fa-solid fa-bolt" style="color: #ff5252"></i>
                  </span>
                  <span class="fw-semibold flex-grow-1 text-start">How does S-SPARC improve computational efficiency?</span>
                  <span class="faq-icon ms-3">
                    <i class="fa-solid fa-chevron-down" style="color: #ff5252"></i>
                  </span>
                </button>
                <div id="faq4" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer px-4 py-3 bg-light rounded-bottom-3 border-top">
                    S-SPARC improves efficiency by applying <b>semantic similarity</b> to compare new requests with historical data. When a relevant solution already exists, the system adjusts and optimizes it instead of running a full
                    inference every time. This significantly reduces processing time, energy consumption, and token usage. S-SPARC also uses smart internal caching to speed up frequent queries, delivering more responsive and resource-efficient
                    experiences.
                  </div>
                </div>
              </div>
              <!-- FAQ Item 5 -->
              <div class="faq-item mb-3">
                <button
                  class="faq-question d-flex align-items-center w-100 px-4 py-3 border-0 rounded-3 shadow-sm bg-white collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#faq5"
                  aria-expanded="false"
                  aria-controls="faq5"
                >
                  <span class="me-3 flex-shrink-0">
                    <i class="fa-solid fa-leaf" style="color: #43a047"></i>
                  </span>
                  <span class="fw-semibold flex-grow-1 text-start">Is S-SPARC environmentally friendly?</span>
                  <span class="faq-icon ms-3">
                    <i class="fa-solid fa-chevron-down" style="color: #43a047"></i>
                  </span>
                </button>
                <div id="faq5" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer px-4 py-3 bg-light rounded-bottom-3 border-top">
                    Yes. S-SPARC is developed with sustainability as a core principle. By reducing repeated code generation and lowering inference load on AI models, it directly cuts energy consumption and associated carbon emissions. This
                    aligns with <b>sustainable AI</b> practices—maximizing the benefits of AI while minimizing environmental impact. S-SPARC also promotes reuse of existing solutions so that compute resources are used more efficiently.
                  </div>
                </div>
              </div>
              <!-- FAQ Item 6 -->
              <div class="faq-item mb-3">
                <button
                  class="faq-question d-flex align-items-center w-100 px-4 py-3 border-0 rounded-3 shadow-sm bg-white collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#faq6"
                  aria-expanded="false"
                  aria-controls="faq6"
                >
                  <span class="me-3 flex-shrink-0">
                    <i class="fa-solid fa-shield-halved" style="color: #1976d2"></i>
                  </span>
                  <span class="fw-semibold flex-grow-1 text-start">Is my data safe with S-SPARC?</span>
                  <span class="faq-icon ms-3">
                    <i class="fa-solid fa-chevron-down" style="color: #1976d2"></i>
                  </span>
                </button>
                <div id="faq6" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer px-4 py-3 bg-light rounded-bottom-3 border-top">
                    User privacy and data security are top priorities for S-SPARC. The system does not permanently store conversations or personal data, except for limited, anonymized caching needed for optimization. Any data used for
                    improving the system is anonymized and cannot be traced back to individuals. S-SPARC only uses the minimal metadata required to enhance efficiency and quality, without compromising privacy.
                  </div>
                </div>
              </div>
              <!-- FAQ Item 7 -->
              <div class="faq-item mb-3">
                <button
                  class="faq-question d-flex align-items-center w-100 px-4 py-3 border-0 rounded-3 shadow-sm bg-white collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#faq7"
                  aria-expanded="false"
                  aria-controls="faq7"
                >
                  <span class="me-3 flex-shrink-0">
                    <i class="fa-solid fa-code" style="color: #ff9800"></i>
                  </span>
                  <span class="fw-semibold flex-grow-1 text-start">What kind of code can S-SPARC generate?</span>
                  <span class="faq-icon ms-3">
                    <i class="fa-solid fa-chevron-down" style="color: #ff9800"></i>
                  </span>
                </button>
                <div id="faq7" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer px-4 py-3 bg-light rounded-bottom-3 border-top">
                    S-SPARC can assist with a wide range of programming languages, including Python, JavaScript, PHP, Java, C++, and more. Beyond simple snippets, it can support full algorithm implementations, data structures, web
                    development, automation scripts, and API integrations. Every solution is tailored to the user's context and includes explanations or references so it is easier to understand and apply. S-SPARC can also help with
                    debugging, refactoring, and optimizing existing code.
                  </div>
                </div>
              </div>
              <!-- FAQ Item 8 -->
              <div class="faq-item mb-3">
                <button
                  class="faq-question d-flex align-items-center w-100 px-4 py-3 border-0 rounded-3 shadow-sm bg-white collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#faq8"
                  aria-expanded="false"
                  aria-controls="faq8"
                >
                  <span class="me-3 flex-shrink-0">
                    <i class="fa-solid fa-book-open" style="color: #d84315"></i>
                  </span>
                  <span class="fw-semibold flex-grow-1 text-start">Can I rely on S-SPARC for academic projects?</span>
                  <span class="faq-icon ms-3">
                    <i class="fa-solid fa-chevron-down" style="color: #d84315"></i>
                  </span>
                </button>
                <div id="faq8" class="collapse" data-bs-parent="#faqAccordion">
                  <div class="faq-answer px-4 py-3 bg-light rounded-bottom-3 border-top">
                    Yes. S-SPARC is developed in an academic research environment and has been tested to support coursework, research projects, and final theses. It provides solutions that are not only fast and efficient but also
                    scientifically grounded. S-SPARC helps students and researchers explore concepts, design algorithms, write code, and produce clear, structured documentation—turning day-to-day coding tasks into opportunities for deeper
                    learning.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <style>
        .faq-modern .faq-question {
          transition: box-shadow 0.2s, background 0.2s;
          cursor: pointer;
        }
        .faq-modern .faq-question:not(.collapsed) {
          background: linear-gradient(90deg, #f0f4f8 0%, #ffffff 100%);
          box-shadow: 0 4px 24px 0 rgba(0, 0, 0, 0.06);
        }
        .faq-modern .faq-question .faq-icon {
          transition: transform 0.3s;
        }
        .faq-modern .faq-question:not(.collapsed) .faq-icon {
          transform: rotate(180deg);
        }
        .faq-modern .faq-answer {
          animation: fadeInFaq 0.4s;
          text-align: justify;
        }
        @keyframes fadeInFaq {
          from {
            opacity: 0;
            transform: translateY(-10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
      </style>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script>
      const messages = ['Efficiency Through Intelligence', 'Sustainable Code Assistance', 'Faster Coding Made Simple', 'Intelligence In Motion'];
      let idx = 0;
      const msgEl = document.getElementById('video-message');
      setInterval(() => {
        msgEl.style.opacity = 0;
        setTimeout(() => {
          idx = (idx + 1) % messages.length;
          msgEl.textContent = messages[idx];
          msgEl.style.opacity = 1;
        }, 1000);
      }, 3000);
    </script>

    <!-- Particles.js animated background -->
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
