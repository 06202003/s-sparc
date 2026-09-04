<?php
require_once(__DIR__ . '/_sso_bridge.php');

// Context verification & AI chat assistant are reserved for students
if ($sso_role === 'lecturer' || $sso_role === 'admin') {
    header("Location: environmental_impact.php");
    exit;
}

$error = null;
$courses = [];
$assessmentsByCourse = [];

// 1. Fetch courses from E-STRANGE for current user
$userIdSafe = mysqli_real_escape_string($db, $sso_user_id);

// Check if student is enrolled
$coursesQuery = "SELECT DISTINCT c.course_id, c.name, COALESCE(c.description, '') AS description
                 FROM course c
                 INNER JOIN enrollment e ON e.course_id = c.course_id
                 WHERE e.student_id = '$userIdSafe' AND c.is_active = 1
                 ORDER BY c.name ASC";
$coursesRes = $db->query($coursesQuery);

if ($coursesRes && $coursesRes->num_rows > 0) {
    while ($row = $coursesRes->fetch_assoc()) {
        $courses[] = $row;
    }
}

// If lecturer or admin, load corresponding courses
if ($sso_role === 'lecturer') {
    $lecturerCoursesQuery = "SELECT course_id, name, COALESCE(description, '') AS description 
                             FROM course 
                             WHERE creator_id = '$userIdSafe' AND is_active = 1 
                             ORDER BY name ASC";
    $lecRes = $db->query($lecturerCoursesQuery);
    if ($lecRes && $lecRes->num_rows > 0) {
        $courses = [];
        while ($row = $lecRes->fetch_assoc()) {
            $courses[] = $row;
        }
    }
} else if ($sso_role === 'admin') {
    $allCoursesQuery = "SELECT course_id, name, COALESCE(description, '') AS description 
                        FROM course 
                        WHERE is_active = 1 
                        ORDER BY name ASC";
    $allRes = $db->query($allCoursesQuery);
    if ($allRes) {
        $courses = [];
        while ($row = $allRes->fetch_assoc()) {
            $courses[] = $row;
        }
    }
}

// 2. Fetch assessments for courses
if ($sso_role === 'lecturer' || $sso_role === 'admin') {
    $assessmentsQuery = "SELECT a.assessment_id, a.course_id, a.name, a.description, a.submission_close_time, a.submission_file_extension
                         FROM assessment a
                         INNER JOIN course c ON c.course_id = a.course_id
                         WHERE c.is_active = 1
                         ORDER BY a.course_id ASC, a.submission_close_time DESC, a.assessment_id ASC";
} else {
    $assessmentsQuery = "SELECT a.assessment_id, a.course_id, a.name, a.description, a.submission_close_time, a.submission_file_extension
                         FROM assessment a
                         INNER JOIN course c ON c.course_id = a.course_id
                         WHERE (a.submission_close_time > CURRENT_TIMESTAMP OR a.allow_late_submission = 1)
                           AND a.submission_open_time < CURRENT_TIMESTAMP
                           AND c.is_active = 1
                         ORDER BY a.course_id ASC, a.submission_close_time ASC, a.assessment_id ASC";
}
$assessmentsRes = $db->query($assessmentsQuery);

if ($assessmentsRes) {
    while ($a = $assessmentsRes->fetch_assoc()) {
        $cid = (string)$a['course_id'];
        if (!isset($assessmentsByCourse[$cid])) {
            $assessmentsByCourse[$cid] = [];
        }
        $assessmentsByCourse[$cid][] = $a;
    }
}

// 3. Handle Form Submit (Context Verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCourseId = trim($_POST['course_id'] ?? '');
    $selectedAssessmentId = trim($_POST['assessment_id'] ?? '');

    if ($selectedCourseId === '' || $selectedAssessmentId === '') {
        $error = 'Both course and assessment must be selected before launching the AI assistant.';
    } else {
        $courseName = null;
        foreach ($courses as $c) {
            if ((string)$c['course_id'] === $selectedCourseId) {
                $courseName = $c['name'];
                break;
            }
        }

        $assessmentName = null;
        $foundAssessment = false;
        if (isset($assessmentsByCourse[$selectedCourseId])) {
            foreach ($assessmentsByCourse[$selectedCourseId] as $a) {
                if ((string)$a['assessment_id'] === $selectedAssessmentId) {
                    $assessmentName = $a['name'];
                    $foundAssessment = true;
                    break;
                }
            }
        }

        if (!$courseName || !$foundAssessment) {
            $error = 'Invalid course or assessment selection.';
        } else {
            $_SESSION['current_course'] = $courseName;
            $_SESSION['current_course_id'] = $selectedCourseId;
            $_SESSION['current_assessment'] = $assessmentName;
            $_SESSION['assessment_id'] = $selectedAssessmentId;
            $_SESSION['chat_user_id'] = $sso_user_id;

            header('Location: chat.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Academic Context Verification - S-SPARC AI</title>
  <link rel="icon" href="../strange_html_layout_additional_files/icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Select2 CSS & JS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root { color-scheme: light; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    .glass { backdrop-filter: blur(10px); background: rgba(255,255,255,0.95); }
    /* Select2 Tailwind Light Styling */
    .select2-container--default .select2-selection--single {
      height: 44px;
      border: 1px solid #cbd5e1;
      border-radius: 0.75rem;
      display: flex;
      align-items: center;
      background-color: #ffffff;
      padding-left: 0.5rem;
      outline: none;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      color: #0f172a;
      font-size: 0.875rem;
      line-height: 42px;
      padding-left: 0.25rem;
      font-weight: 500;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 42px;
      right: 10px;
    }
    .select2-dropdown {
      border: 1px solid #cbd5e1;
      border-radius: 0.75rem;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      background-color: #ffffff;
      font-size: 0.875rem;
      z-index: 9999;
    }
    .select2-results__option--highlighted[aria-selected] {
      background-color: #0f172a !important;
      color: #ffffff !important;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
      border: 1px solid #cbd5e1;
      border-radius: 0.5rem;
      padding: 0.4rem 0.6rem;
      font-size: 0.85rem;
      outline: none;
    }
  </style>
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
  
  <?php renderSSOHeader('courses', 'Verify Academic Context'); ?>

  <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
    <div class="max-w-2xl w-full">
      
      <!-- Verification Card -->
      <div class="glass rounded-2xl border border-slate-200/80 shadow-xl p-6 sm:p-8 space-y-6">
        
        <!-- Header & Instructions -->
        <div class="border-b border-slate-200/80 pb-4">
          <div class="flex items-center space-x-2">
            <span class="px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
              Step 1 of 1
            </span>
            <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
              Parent: E-STRANGE
            </span>
          </div>
          <h1 class="text-xl font-bold text-slate-900 mt-2">Select S-SPARC Academic Context</h1>
          <p class="text-xs text-slate-600 mt-1 leading-relaxed">
            Before launching the AI Assistant, please select your enrolled course and active assessment in E-STRANGE. This scopes token usage, dynamic quota calculations, and 0-token vector retrieval caching.
          </p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="rounded-xl border border-rose-200 bg-rose-50/80 text-rose-800 p-3.5 text-xs flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <!-- API Key Status Notice Banner -->
        <div id="apiKeyNotice" class="hidden rounded-xl border p-4 text-xs space-y-2 shadow-xs transition-all">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2.5">
              <span id="apiKeyNoticeIcon" class="text-xl shrink-0 mt-0.5">⚠️</span>
              <div>
                <strong id="apiKeyNoticeTitle" class="font-bold block text-sm">Google Gemini API Key Required</strong>
                <span id="apiKeyNoticeDesc" class="block text-xs mt-0.5 text-slate-700">You have not registered your personal API Key. Please accept the Terms &amp; Conditions and register your API key before launching the S-SPARC AI Assistant.</span>
              </div>
            </div>
            <button type="button" id="apiKeyActionBtn" onclick="openApiKeyFlow(true)" class="shrink-0 bg-[#00A0A5] hover:bg-[#008488] text-white font-bold px-3 py-1.5 rounded-lg transition text-xs shadow-xs">
              Set API Key Now
            </button>
          </div>
        </div>

        <?php if (empty($courses)): ?>
          <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 p-4 text-xs space-y-2">
            <p class="font-bold">You are not enrolled in any active courses in E-STRANGE.</p>
            <p>Please contact your lecturer or complete course enrollment via the Course Catalog menu first.</p>
          </div>
        <?php else: ?>
          <!-- Form Selection -->
          <form method="POST" action="courses.php" class="space-y-5">
            
            <!-- Step 1: Course Select with Select2 -->
            <div>
              <label for="course_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                1. Select Course / Class
              </label>
              <select id="course_id" name="course_id" class="min-w-[200px] shrink-0 select2 w-full" required>
                <option value="">-- Choose Course from E-STRANGE --</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?= htmlspecialchars($c['course_id']) ?>" <?= (isset($_SESSION['current_course_id']) && $_SESSION['current_course_id'] == $c['course_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Step 2: Assessment Select with Select2 -->
            <div>
              <label for="assessment_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                2. Select Active Assessment Assignment
              </label>
              <select id="assessment_id" name="assessment_id" class="min-w-[200px] shrink-0 select2 w-full" required>
                <option value="">-- Select Course First --</option>
              </select>
            </div>

            <!-- Assessment Detail Preview Box -->
            <div id="assessmentPreview" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs space-y-2">
              <div class="font-bold text-slate-800 text-sm" id="prevAsmtName">-</div>
              <div class="text-slate-600" id="prevAsmtDesc">-</div>
              <div class="flex items-center gap-4 text-[11px] text-slate-500 pt-2 border-t border-slate-200">
                <span>Submission Deadline: <strong id="prevAsmtDue" class="text-slate-700">-</strong></span>
                <span>Allowed Extension: <strong id="prevAsmtExt" class="text-slate-700 font-mono">-</strong></span>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-3 flex items-center justify-between gap-3">
              <a href="../student_dashboard.php" class="inline-flex items-center justify-center px-4 py-2.5 text-xs font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition no-underline">
                Cancel &amp; Return to Dashboard
              </a>
              <button type="submit" id="submitBtn" class="inline-flex items-center justify-center px-6 py-2.5 text-sm font-bold text-white bg-[#00A0A5] hover:bg-[#008488] rounded-xl shadow-xs transition focus:ring-2 focus:ring-[#00A0A5] focus:ring-offset-2">
                Launch S-SPARC AI Assistant &rarr;
              </button>
            </div>
          </form>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <script>
    const assessmentsByCourse = <?= json_encode($assessmentsByCourse) ?>;
    const defaultAssessmentId = "<?= htmlspecialchars($_SESSION['assessment_id'] ?? '') ?>";
    const FASTAPI_URL = "http://127.0.0.1:5000";
    const SSO_USER_ID = "<?= htmlspecialchars($sso_user_id) ?>";
    let userHasApiKey = false;

    async function checkUserApiKey() {
      try {
        const res = await fetch(`${FASTAPI_URL}/api/user/api-key`, {
          headers: { 'X-User-ID': SSO_USER_ID }
        });
        if (res.ok) {
          const data = await res.json();
          userHasApiKey = !!data.has_key;
          updateApiKeyBanner(data);
        }
      } catch (e) {
        console.debug('Error checking user API key:', e);
      }
    }

    function updateApiKeyBanner(data) {
      const banner = $('#apiKeyNotice');
      const icon = $('#apiKeyNoticeIcon');
      const title = $('#apiKeyNoticeTitle');
      const desc = $('#apiKeyNoticeDesc');
      const btn = $('#apiKeyActionBtn');

      banner.removeClass('hidden border-amber-200 bg-amber-50 border-emerald-200 bg-emerald-50/80 text-amber-900 text-emerald-900');

      if (data && data.has_key) {
        banner.addClass('border-emerald-200 bg-emerald-50/80 text-emerald-900');
        icon.text('✅');
        title.text('Google Gemini API Key Active');
        desc.html(`Active Key: <strong class="font-mono text-teal-800">${data.masked_key || 'Saved'}</strong>. You are ready to launch S-SPARC AI Assistant.`);
        btn.text('Manage API Key').removeClass('hidden bg-amber-600 hover:bg-amber-700').addClass('bg-teal-700 hover:bg-teal-800 text-white');
        btn.attr('onclick', 'openApiKeyFlow(false)');
      } else {
        banner.addClass('border-amber-200 bg-amber-50 text-amber-900');
        icon.text('⚠️');
        title.text('Google Gemini API Key Required');
        desc.text('You have not registered your personal API Key. Please accept the Terms & Conditions and register your API key before launching the S-SPARC AI Assistant.');
        btn.text('Set API Key Now').removeClass('hidden bg-teal-700 hover:bg-teal-800').addClass('bg-amber-600 hover:bg-amber-700 text-white');
        btn.attr('onclick', 'openApiKeyFlow(true)');
      }
    }

    function showTermsAndConditionsModal(onAcceptCallback) {
      Swal.fire({
        title: '📜 Terms & Conditions — Personal API Key Usage',
        html: `
          <div class="text-left text-xs leading-relaxed space-y-3.5 text-slate-700 max-h-[220px] overflow-y-auto pr-2 border border-slate-200 rounded-xl p-3.5 bg-slate-50/50" id="termsScrollBox">
            <div class="p-3 bg-teal-50/90 border border-teal-200 rounded-xl text-[11px] text-teal-900 font-medium">
              S-SPARC AI operates on a <em>Bring Your Own Key (BYOK)</em> model using Google Gemini Flash Lite to grant full coding exploration freedom with 0 gamification point deductions.
            </div>

            <div class="space-y-3">
              <div class="p-2.5 rounded-xl border border-slate-200 bg-white">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">1.</span> Data Privacy & Confidentiality
                </div>
                <p class="text-[11px] text-slate-600">
                  Your Google Gemini API Key is stored securely with encryption in our database. It is exclusively used to process AI coding assistance requests for your account and is never shared with third parties.
                </p>
              </div>

              <div class="p-2.5 rounded-xl border border-slate-200 bg-white">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">2.</span> Ownership & Responsibility
                </div>
                <p class="text-[11px] text-slate-600">
                  You are solely responsible for the personal API key registered from Google AI Studio. Misuse of API keys, sharing keys, or executing prompts violating Google Cloud policies is strictly prohibited.
                </p>
              </div>

              <div class="p-2.5 rounded-xl border border-slate-200 bg-white">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">3.</span> Free Tier Allocation & Rate Limits
                </div>
                <p class="text-[11px] text-slate-600">
                  Google Gemini 3.5 Flash Lite Free Tier provides up to <strong>1,500 Requests Per Day (RPD)</strong> and <strong>15 Requests Per Minute (RPM)</strong>. S-SPARC enforces a 1-minute (60s) cooldown per prompt to maintain system stability and foster independent problem-solving skills.
                </p>
              </div>

              <div class="p-2.5 rounded-xl border border-slate-200 bg-white">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">4.</span> Multi-Tier Failover Guarantee
                </div>
                <p class="text-[11px] text-slate-600">
                  If your personal key reaches rate limits or encounters connectivity issues, S-SPARC transparently routes execution to System Backup Pool Keys or Local LLM Ollama to ensure uninterrupted learning.
                </p>
              </div>

              <div class="p-2.5 rounded-xl border border-slate-200 bg-white">
                <div class="font-bold text-slate-900 flex items-center gap-1.5 mb-1 text-xs">
                  <span class="text-teal-600">5.</span> Academic Integrity & AI Ethics
                </div>
                <p class="text-[11px] text-slate-600">
                  S-SPARC AI acts as an interactive tutor (helping diagnose error tracebacks, understand algorithm logic, and optimize code). Students remain fully responsible for understanding and explaining every line of code submitted in E-STRANGE assessments.
                </p>
              </div>
            </div>
          </div>

          <div id="readingProgressNotice" class="mt-3 p-2.5 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-900 flex items-center gap-2 font-medium">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>📜 <strong>Scroll Required:</strong> Please scroll down to the bottom of the Terms box to read all terms and enable agreement.</span>
          </div>

          <div class="mt-3 p-3 bg-slate-50 rounded-xl border border-slate-200 text-left">
            <label class="flex items-start gap-2 cursor-pointer text-[11px] text-slate-800 select-none font-medium">
              <input type="checkbox" id="swal-terms-read-checkbox" disabled class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 shrink-0 disabled:opacity-40 disabled:cursor-not-allowed">
              <span>I have read, understood, and <strong>agree to all Terms & Conditions</strong> for using a personal Google Gemini API key in S-SPARC.</span>
            </label>
          </div>
        `,
        width: '580px',
        showCancelButton: true,
        confirmButtonText: 'I Agree & Proceed to API Key &rarr;',
        confirmButtonColor: '#00A0A5',
        cancelButtonText: 'Cancel',
        focusConfirm: false,
        didOpen: (modal) => {
          const scrollBox = modal.querySelector('#termsScrollBox');
          const checkbox = modal.querySelector('#swal-terms-read-checkbox');
          const notice = modal.querySelector('#readingProgressNotice');
          const confirmBtn = Swal.getConfirmButton();

          if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
          }

          let hasScrolledBottom = false;

          function evaluateScroll() {
            if (!scrollBox) return;
            if (scrollBox.scrollTop + scrollBox.clientHeight >= scrollBox.scrollHeight - 25) {
              if (!hasScrolledBottom) {
                hasScrolledBottom = true;
                if (checkbox) {
                  checkbox.disabled = false;
                  checkbox.classList.remove('disabled:opacity-40', 'disabled:cursor-not-allowed');
                }
                if (notice) {
                  notice.className = 'mt-3 p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-[11px] text-emerald-900 flex items-center gap-2 font-medium';
                  notice.innerHTML = '<svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>✅ Terms & Conditions read! Check the box below to accept.</span>';
                }
              }
            }
          }

          if (scrollBox) {
            scrollBox.addEventListener('scroll', evaluateScroll);
            if (scrollBox.scrollHeight <= scrollBox.clientHeight + 25) {
              evaluateScroll();
            }
          }

          if (checkbox) {
            checkbox.addEventListener('change', function() {
              if (confirmBtn) {
                if (this.checked && hasScrolledBottom) {
                  confirmBtn.disabled = false;
                  confirmBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                  confirmBtn.disabled = true;
                  confirmBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
              }
            });
          }
        },
        preConfirm: () => {
          const scrollBox = document.getElementById('termsScrollBox');
          const hasScrolled = scrollBox ? (scrollBox.scrollTop + scrollBox.clientHeight >= scrollBox.scrollHeight - 25) : true;
          const checked = document.getElementById('swal-terms-read-checkbox')?.checked;

          if (!hasScrolled) {
            Swal.showValidationMessage('📜 Please scroll down to the bottom of the Terms & Conditions before agreeing.');
            return false;
          }
          if (!checked) {
            Swal.showValidationMessage('⚠️ You must check the agreement box to accept the Terms & Conditions before proceeding.');
            return false;
          }
          return true;
        }
      }).then((result) => {
        if (result.isConfirmed && typeof onAcceptCallback === 'function') {
          onAcceptCallback();
        }
      });
    }

    async function openApiKeyInputModal(isFirstTime = true) {
      let currentMasked = '';
      try {
        const res = await fetch(`${FASTAPI_URL}/api/user/api-key`, {
          headers: { 'X-User-ID': SSO_USER_ID }
        });
        if (res.ok) {
          const info = await res.json();
          if (info.has_key && info.masked_key) {
            currentMasked = info.masked_key;
            userHasApiKey = true;
          }
        }
      } catch (e) {}

      const titleText = isFirstTime ? '🔑 Register Google Gemini API Key' : '⚙️ Manage Google Gemini API Key';
      const introText = isFirstTime 
        ? 'Please enter your personal Google Gemini API Key below. This key will be securely saved for all your coding sessions in S-SPARC AI.'
        : 'Your active Google Gemini API Key: <strong class="font-mono text-teal-700">' + (currentMasked || 'Not set') + '</strong>.';

      const { value: formValues } = await Swal.fire({
        title: titleText,
        html: `
          <div class="text-left text-xs text-slate-600 space-y-3">
            <p>${introText}</p>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
              <label class="block font-bold text-slate-800 mb-1">Google Gemini API Key:</label>
              <input id="swal-api-key-input" type="password" placeholder="AIzaSy..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#00A0A5] bg-white text-slate-900" autocomplete="off">
              <div class="flex items-center justify-between mt-1.5 text-[11px] text-slate-500">
                <span>Minimum 10 characters</span>
                <button type="button" onclick="const inp = document.getElementById('swal-api-key-input'); inp.type = (inp.type === 'password' ? 'text' : 'password');" class="text-teal-600 hover:underline font-semibold">Show / Hide Key</button>
              </div>
            </div>

            <div class="p-2.5 bg-teal-50 border border-teal-200 rounded-xl text-[11px] text-teal-900 flex items-center gap-2">
              <svg class="w-4 h-4 text-teal-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>Don't have an API key yet? Get one for free at <a href="https://aistudio.google.com/app/apikey" target="_blank" class="font-bold underline text-teal-800">Google AI Studio</a>.</span>
            </div>
          </div>
        `,
        focusConfirm: false,
        showCancelButton: !isFirstTime,
        confirmButtonText: 'Save & Activate API Key',
        confirmButtonColor: '#00A0A5',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
          const keyVal = document.getElementById('swal-api-key-input')?.value.trim();
          if (!keyVal || keyVal.length < 10) {
            Swal.showValidationMessage('Please enter a valid API key (minimum 10 characters)');
            return false;
          }
          return { apiKey: keyVal };
        }
      });

      if (formValues && formValues.apiKey) {
        try {
          Swal.fire({
            title: 'Saving API Key...',
            text: 'Encrypting and activating key in S-SPARC database...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
          });

          const postRes = await fetch(`${FASTAPI_URL}/api/user/api-key`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-User-ID': SSO_USER_ID
            },
            body: JSON.stringify({ 
              api_key: formValues.apiKey, 
              provider: 'gemini',
              terms_accepted: true
            })
          });

          if (!postRes.ok) {
            const errData = await postRes.json().catch(() => ({}));
            throw new Error(errData.detail || 'Failed to save API key.');
          }

          const saveRes = await postRes.json();
          userHasApiKey = true;
          checkUserApiKey();

          Swal.fire({
            icon: 'success',
            title: 'API Key Saved & Activated!',
            text: `Active key: ${saveRes.masked_key || 'Saved'}. You are now ready to launch S-SPARC AI Assistant.`,
            confirmButtonColor: '#00A0A5'
          });
        } catch (saveErr) {
          Swal.fire({
            icon: 'error',
            title: 'Failed to Save API Key',
            text: saveErr.message,
            confirmButtonColor: '#0f172a'
          });
        }
      }
    }

    function openApiKeyFlow(isFirstTime = true) {
      showTermsAndConditionsModal(() => {
        openApiKeyInputModal(isFirstTime);
      });
    }

    $(document).ready(function() {
      // Check API Key status
      checkUserApiKey();

      // Initialize Select2 on both dropdowns
      $('#course_id').select2({
        width: '100%',
        placeholder: '-- Choose Course from E-STRANGE --'
      });

      $('#assessment_id').select2({
        width: '100%',
        placeholder: '-- Select Assessment --'
      });

      function updateAssessments() {
        const selectedCourseId = $('#course_id').val();
        const $asmtSelect = $('#assessment_id');
        $asmtSelect.empty();

        if (!selectedCourseId || !assessmentsByCourse[selectedCourseId] || assessmentsByCourse[selectedCourseId].length === 0) {
          $asmtSelect.append('<option value="">-- No active assessments found for this course --</option>');
          $('#assessmentPreview').addClass('hidden');
          $asmtSelect.trigger('change.select2');
          return;
        }

        $asmtSelect.append('<option value="">-- Select Active Assessment --</option>');
        const list = assessmentsByCourse[selectedCourseId];
        list.forEach(a => {
          const isSelected = (defaultAssessmentId && String(a.assessment_id) === String(defaultAssessmentId)) ? 'selected' : '';
          $asmtSelect.append(`<option value="${a.assessment_id}" ${isSelected}>#${a.assessment_id}: ${a.name} (${a.submission_file_extension || 'file'})</option>`);
        });

        $asmtSelect.trigger('change.select2');
        updatePreview();
      }

      function updatePreview() {
        const selectedCourseId = $('#course_id').val();
        const selectedAsmtId = $('#assessment_id').val();

        if (!selectedCourseId || !selectedAsmtId || !assessmentsByCourse[selectedCourseId]) {
          $('#assessmentPreview').addClass('hidden');
          return;
        }

        const found = assessmentsByCourse[selectedCourseId].find(a => String(a.assessment_id) === String(selectedAsmtId));
        if (found) {
          $('#prevAsmtName').text(found.name);
          $('#prevAsmtDesc').text(found.description ? found.description.replace(/<[^>]*>?/gm, '') : 'No description provided.');
          $('#prevAsmtDue').text(found.submission_close_time || 'Unspecified');
          $('#prevAsmtExt').text(found.submission_file_extension || '*');
          $('#assessmentPreview').removeClass('hidden');
        } else {
          $('#assessmentPreview').addClass('hidden');
        }
      }

      $('#course_id').on('change', function() {
        updateAssessments();
      });

      $('#assessment_id').on('change', function() {
        updatePreview();
      });

      // Intercept form submit if API key is not configured
      $('form').on('submit', function(e) {
        if (!userHasApiKey) {
          e.preventDefault();
          Swal.fire({
            icon: 'warning',
            title: 'Google Gemini API Key Diperlukan',
            text: 'Anda belum memasukkan Google Gemini API Key pribadi. Anda tidak dapat meluncurkan S-SPARC AI Assistant sebelum memasukkan API Key yang valid.',
            confirmButtonText: 'Atur API Key Sekarang',
            confirmButtonColor: '#00A0A5',
            showCancelButton: true,
            cancelButtonText: 'Batal'
          }).then((result) => {
            if (result.isConfirmed) {
              openApiKeyModal(true);
            }
          });
          return false;
        }
      });

      // Trigger initial load if course is selected
      if ($('#course_id').val()) {
        updateAssessments();
      }
    });
  </script>
</body>
</html>

