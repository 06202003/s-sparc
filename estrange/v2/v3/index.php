<?php
include("_nosessionchecker.php");
include("_config.php");

// Handle authentication POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['logout'])) {
    session_start();
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
  }

  $myusername = mysqli_real_escape_string($db, $_POST['uname'] ?? '');
  $mypassword = mysqli_real_escape_string($db, $_POST['pwd'] ?? '');

  $sql = "SELECT user_id, username, name, email, password, role FROM user WHERE username = '$myusername'";
  $result = mysqli_query($db, $sql);
  $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $count = mysqli_num_rows($result);

  if ($count == 1 && password_verify($mypassword, $row['password'])) {
      if (session_status() === PHP_SESSION_NONE) {
          session_start();
      }

      $_SESSION['user_id'] = $row['user_id'];
      $_SESSION['username'] = $myusername;
      $_SESSION['name'] = $row['name'];
      $_SESSION['role'] = $row['role'];
      $_SESSION['sub_domain'] = "mcu";

      // Redirect according to user role
      if ($row['role'] == 'admin') {
          header('Location: admin_dashboard.php');
          exit;
      } elseif ($row['role'] == 'lecturer') {
          header('Location: lecturer_dashboard.php');
          exit;
      } elseif ($row['role'] == 'student') {
          header('Location: student_dashboard.php');
          exit;
      }
  } else {
      $error = true;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - E-STRANGE Academic Code Intelligence</title>
  <link rel="icon" href="strange_html_layout_additional_files/icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="strange_html_layout_additional_files/notyf.min.css">
  <script src="strange_html_layout_additional_files/notyf.min.js"></script>
  <style>
    :root { color-scheme: light; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    .glass-canvas {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(20px);
    }
  </style>
  <script type="text/javascript">
    function loadGameNotif(){
      var notyf = new Notyf({
        duration: 5000,
        position: { x: 'center', y: 'top' },
        dismissible: true
      });
      
      <?php
      if(isset($_GET['err'])){
        echo "notyf.error('Incorrect username and/or password!');";
      } else if(isset($_GET['errregis'])){
        echo "notyf.error('The registration link is invalid! Please re-register your email.');";
      } else if(isset($_GET['update'])){
        echo "notyf.success('A password reset link has been sent to your email!');";
      } else if(isset($_GET['update2'])){
        echo "notyf.success('Your password has been successfully changed!');";
      } else if(isset($_GET['update3'])){
        echo "notyf.success('An account verification link has been sent to your email!');";
      } else if(isset($_GET['update4'])){
        echo "notyf.success('Your account has been created successfully!');";
      } else if(isset($_GET['submit'])){
        echo "notyf.success('Your code submission has been uploaded successfully!');";
      } else if(isset($_GET['nocoursesinvitee'])){
        echo "notyf.error('There are no active courses available for public registration.');";
      } else if(isset($_GET['invalidreport'])){
        echo "notyf.error('Invalid link! Please log in to view the report in your submission history.');";
      }
      ?>
    }

    function togglePasswordVisibility() {
      const pwdInput = document.getElementById('passwordInput');
      const eyeIcon = document.getElementById('eyeIcon');
      if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.03 10.03 0 013.025-.563c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m-3.328 3.328A3.992 3.992 0 0112 15c-1.105 0-2.105-.448-2.828-1.172M3 3l18 18"/>';
      } else {
        pwdInput.type = 'password';
        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
      }
    }
  </script>
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
<body onload="loadGameNotif()" class="min-h-screen bg-gradient-to-br from-slate-100 via-sky-50/50 to-indigo-50/60 text-slate-900 flex items-center justify-center p-4 relative overflow-x-hidden">
  
  <!-- Vibrant ambient background glow light elements -->
  <div class="fixed top-[-10%] left-[-10%] w-[550px] h-[550px] rounded-full bg-sky-400/20 blur-[130px] pointer-events-none"></div>
  <div class="fixed bottom-[-10%] right-[-10%] w-[550px] h-[550px] rounded-full bg-indigo-400/20 blur-[130px] pointer-events-none"></div>
  <div class="fixed top-[40%] right-[30%] w-[350px] h-[350px] rounded-full bg-emerald-400/15 blur-[120px] pointer-events-none"></div>

  <!-- Subtle Tech Grid Background Pattern -->
  <div class="fixed inset-0 bg-[linear-gradient(to_right,#e2e8f0_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f0_1px,transparent_1px)] bg-[size:3.5rem_3.5rem] opacity-40 pointer-events-none"></div>
  <div class="w-full max-w-5xl glass-canvas rounded-3xl border border-white/80 shadow-2xl shadow-slate-900/10 overflow-hidden grid grid-cols-1 md:grid-cols-12 relative z-10">
    
    <!-- Left Hero Brand Panel (Light Theme) -->
    <div class="md:col-span-5 bg-gradient-to-b from-white/95 via-slate-50/90 to-indigo-50/40 p-6 sm:p-7 border-r border-slate-200/80 flex flex-col justify-between relative overflow-hidden">
      <!-- Decorative background accent -->
      <div class="absolute -top-12 -left-12 w-40 h-40 bg-sky-100/60 rounded-full blur-2xl pointer-events-none"></div>
      
      <div class="relative z-10">
        <div class="mb-5">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-[11px] font-bold mb-3 shadow-2xs">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Student Learning Portal</span>
          </div>
          <div>
            <img src="strange_html_layout_additional_files/logo.png" alt="E-STRANGE Logo" class="h-14 sm:h-16 w-auto object-contain drop-shadow-xs">
          </div>
          <p class="text-xs font-semibold text-slate-600 mt-2 tracking-wide">Academic Code Intelligence &amp; Learning Hub</p>
        </div>

        <div class="space-y-2.5 text-xs">
          <!-- Feature Card 1 -->
          <div class="bg-gradient-to-r from-emerald-50/90 via-white to-white border border-emerald-200/90 shadow-xs hover:shadow-md hover:border-emerald-300 transition-all duration-200 rounded-2xl p-3 flex items-start gap-3">
            <div class="p-2 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-xs shrink-0 mt-0.5">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            </div>
            <div>
              <div class="font-bold text-slate-900 text-sm mb-0.5">Smart Code Submissions</div>
              <p class="text-xs text-slate-600 leading-snug font-medium">Secure assignment uploads &amp; instant code quality analysis.</p>
            </div>
          </div>

          <!-- Feature Card 2 -->
          <div class="bg-gradient-to-r from-sky-50/90 via-white to-white border border-sky-200/90 shadow-xs hover:shadow-md hover:border-sky-300 transition-all duration-200 rounded-2xl p-3 flex items-start gap-3">
            <div class="p-2 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-xs shrink-0 mt-0.5">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div>
              <div class="font-bold text-slate-900 text-sm mb-0.5">S-SPARC AI Learning Copilot</div>
              <p class="text-xs text-slate-600 leading-snug font-medium">24/7 AI tutor for coding logic &amp; syntax debugging.</p>
            </div>
          </div>

          <!-- Feature Card 3 -->
          <div class="bg-gradient-to-r from-indigo-50/90 via-white to-white border border-indigo-200/90 shadow-xs hover:shadow-md hover:border-indigo-300 transition-all duration-200 rounded-2xl p-3 flex items-start gap-3">
            <div class="p-2 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-xs shrink-0 mt-0.5">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            </div>
            <div>
              <div class="font-bold text-slate-900 text-sm mb-0.5">Peer Reviews &amp; Leaderboards</div>
              <p class="text-xs text-slate-600 leading-snug font-medium">Anonymous peer evaluation &amp; course ranking badges.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="pt-4 border-t border-slate-200/80 text-[11px] text-slate-500 font-semibold relative z-10 flex items-center justify-between">
        <span class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
          <span>Maranatha Christian University</span>
        </span>
        <span class="font-mono text-slate-400">v2.0</span>
      </div>
    </div>

    <!-- Right Login Form (Light Theme) -->
    <div class="md:col-span-7 p-6 sm:p-10 flex flex-col justify-center bg-white/95 my-auto">
      <div>
        <div class="mb-5 flex items-start justify-between gap-4">
          <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Welcome Back</h1>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Enter your academic credentials to access your dashboard.</p>
          </div>
        </div>

        <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" class="space-y-3.5 font-sans">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5" for="usernameInput">Username</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </div>
              <input 
                id="usernameInput" 
                name="uname" 
                type="text" 
                required 
                placeholder="Enter your username"
                class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition shadow-2xs"
              />
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="passwordInput">Password</label>
              <a href="forgot_password.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">Forgot password?</a>
            </div>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </div>
              <input 
                id="passwordInput" 
                name="pwd" 
                type="password" 
                required 
                placeholder="••••••••"
                class="w-full pl-10 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition shadow-2xs"
              />
              <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition">
                <svg id="eyeIcon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
          </div>

          <button 
            type="submit" 
            class="w-full py-3 px-4 bg-[#00A0A5] hover:bg-[#008488] hover:-translate-y-0.5 active:translate-y-0 text-white text-sm font-bold uppercase tracking-wider rounded-xl shadow-md hover:shadow-xl transition-all duration-200 flex items-center justify-center gap-2 mt-1"
          >
            <span>Sign In</span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </button>
        </form>
      </div>

      <div class="mt-6 pt-4 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <span class="text-slate-500 font-medium">Need a student account?</span>
        <a href="student_registration.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-indigo-50 to-sky-50 hover:from-indigo-100 hover:to-sky-100 border border-indigo-200/70 text-indigo-950 font-bold transition shadow-2xs">
          <span>Register as Student</span>
          <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </a>
      </div>
    </div>
  </div>

  <!-- Bottom Right Minimal Info Pop-up Widget -->
  <div 
    id="floatingInfoPopup" 
    onmouseenter="hideOnHover()" 
    onmouseleave="showOnLeave()"
    class="fixed bottom-6 right-6 z-50 w-72 sm:w-80 transition-all duration-300 ease-out transform scale-100 opacity-100"
  >
    <div class="bg-white/95 border border-slate-200/90 shadow-xl shadow-slate-900/10 rounded-2xl p-3.5 backdrop-blur-md">
      <!-- Title -->
      <div class="flex items-center gap-2 mb-1.5">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span class="text-xs font-bold text-slate-900 tracking-tight">Student Info</span>
      </div>

      <!-- Info Content Carousel (Title + Content Only) -->
      <div class="relative">
        <div class="info-slide transition-opacity duration-300" id="slide0">
          <p class="text-xs text-slate-600 leading-relaxed font-medium">
            Double-check your code submission archive before uploading to ensure proper folder structure for automated evaluation.
          </p>
        </div>

        <div class="info-slide hidden transition-opacity duration-300" id="slide1">
          <p class="text-xs text-slate-600 leading-relaxed font-medium">
            Ask S-SPARC AI for coding logic guidance and syntax debugging to boost your programming comprehension 24/7.
          </p>
        </div>

        <div class="info-slide hidden transition-opacity duration-300" id="slide2">
          <p class="text-xs text-slate-600 leading-relaxed font-medium">
            Provide constructive feedback in peer evaluation tasks to earn experience points and climb course rankings.
          </p>
        </div>

        <div class="info-slide hidden transition-opacity duration-300" id="slide3">
          <p class="text-xs text-slate-600 leading-relaxed font-medium">
            Write original code—E-STRANGE performs pairwise AST structural similarity analysis to protect academic integrity.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Interactive Neural Network Graph Canvas -->
  <canvas id="neuralNetworkCanvas" class="fixed inset-0 pointer-events-none z-0"></canvas>

  <script>
    <?php if (isset($error) && $error == true) : ?>
      Swal.fire({
        icon: 'error',
        title: 'Authentication Failed',
        text: 'Incorrect username and/or password!',
        confirmButtonColor: '#0f172a'
      });
    <?php endif; ?>

    // Minimal Floating Info Toast Script (10-second interval, Hover to hide)
    let currentSlide = 0;
    const totalSlides = 4;
    const intervalMs = 10000;
    let autoSlideInterval = setInterval(nextSlide, intervalMs);

    function showSlide(index) {
      currentSlide = (index + totalSlides) % totalSlides;
      for (let i = 0; i < totalSlides; i++) {
        const slide = document.getElementById('slide' + i);
        if (slide) {
          if (i === currentSlide) {
            slide.classList.remove('hidden');
          } else {
            slide.classList.add('hidden');
          }
        }
      }
    }

    function nextSlide() {
      showSlide(currentSlide + 1);
    }

    function hideOnHover() {
      const popup = document.getElementById('floatingInfoPopup');
      if (popup) {
        popup.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
      }
    }

    function showOnLeave() {
      const popup = document.getElementById('floatingInfoPopup');
      if (popup) {
        popup.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
      }
    }

    // Interactive Neural Network Graph Background Animation
    (function initNeuralNetwork() {
      const canvas = document.getElementById('neuralNetworkCanvas');
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      
      let width = canvas.width = window.innerWidth;
      let height = canvas.height = window.innerHeight;
      
      window.addEventListener('resize', () => {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
      });

      const nodeCount = Math.min(Math.floor(width * height / 16000), 70);
      const nodes = [];
      const colors = ['#0ea5e9', '#6366f1', '#10b981', '#3b82f6'];

      for (let i = 0; i < nodeCount; i++) {
        nodes.push({
          x: Math.random() * width,
          y: Math.random() * height,
          vx: (Math.random() - 0.5) * 0.6,
          vy: (Math.random() - 0.5) * 0.6,
          radius: Math.random() * 2 + 2,
          color: colors[Math.floor(Math.random() * colors.length)]
        });
      }

      function animate() {
        ctx.clearRect(0, 0, width, height);

        // Draw connecting neural network lines
        for (let i = 0; i < nodes.length; i++) {
          for (let j = i + 1; j < nodes.length; j++) {
            const dx = nodes[i].x - nodes[j].x;
            const dy = nodes[i].y - nodes[j].y;
            const dist = Math.sqrt(dx * dx + dy * dy);

            if (dist < 140) {
              ctx.beginPath();
              ctx.moveTo(nodes[i].x, nodes[i].y);
              ctx.lineTo(nodes[j].x, nodes[j].y);
              const alpha = (1 - dist / 140) * 0.22;
              ctx.strokeStyle = `rgba(99, 102, 241, ${alpha})`;
              ctx.lineWidth = 1;
              ctx.stroke();
            }
          }
        }

        // Draw & update nodes
        for (let i = 0; i < nodes.length; i++) {
          const n = nodes[i];
          n.x += n.vx;
          n.y += n.vy;

          if (n.x < 0 || n.x > width) n.vx *= -1;
          if (n.y < 0 || n.y > height) n.vy *= -1;

          ctx.beginPath();
          ctx.arc(n.x, n.y, n.radius, 0, Math.PI * 2);
          ctx.fillStyle = n.color;
          ctx.globalAlpha = 0.55;
          ctx.fill();
          ctx.globalAlpha = 1.0;
        }

        requestAnimationFrame(animate);
      }

      animate();
    })();
  </script>
</body>
</html>
