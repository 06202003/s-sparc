<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$loggedIn = !empty($_SESSION['flask_cookie']);
$username = $_SESSION['username'] ?? 'Guest';

if (!$loggedIn) {
  header('Location: login.php');
  exit;
}

$backendBase = backend_base();
$httpClient = new Client([
  'base_uri' => $backendBase . '/',
  'timeout'  => 20,
]);

$error = null;
$courses = [];
$assessmentsByCourse = [];

// Ambil daftar mata kuliah yang terhubung dengan user (via IAM/user_courses)
try {
  $options = [];
  if (!empty($_SESSION['flask_cookie'])) {
    $options['headers']['Cookie'] = $_SESSION['flask_cookie'];
  }

  // Ambil courses
  $resp = $httpClient->get('courses', $options);
  $data = json_decode((string) $resp->getBody(), true);
  if (is_array($data) && !empty($data['courses']) && is_array($data['courses'])) {
    $courses = $data['courses'];
  }

  // Ambil semua assessments, lalu kelompokkan per course_id
  $respA = $httpClient->get('assessments', $options);
  $dataA = json_decode((string) $respA->getBody(), true);
  if (is_array($dataA) && !empty($dataA['assessments']) && is_array($dataA['assessments'])) {
    foreach ($dataA['assessments'] as $a) {
      $cid = (string)($a['course_id'] ?? '');
      if ($cid === '') continue;
      if (!isset($assessmentsByCourse[$cid])) {
        $assessmentsByCourse[$cid] = [];
      }
      $assessmentsByCourse[$cid][] = $a;
    }
  }
} catch (RequestException $e) {
  $error = 'Gagal memuat daftar mata kuliah/assessment dari backend.';
} catch (\Throwable $e) {
  $error = 'Terjadi kesalahan saat memuat data dari backend.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $selectedCourseId = trim($_POST['course_id'] ?? '');
  $selectedAssessmentId = trim($_POST['assessment_id'] ?? '');

  if ($selectedCourseId === '' || $selectedAssessmentId === '') {
    $error = 'Mata kuliah dan assessment wajib dipilih.';
  } else {
    $courseName = null;
    foreach ($courses as $c) {
      if ((string)($c['course_id'] ?? '') === $selectedCourseId) {
        $courseName = $c['name'] ?? ($c['code'] ?? $selectedCourseId);
        break;
      }
    }

    $assessmentName = null;
    $foundAssessment = false;
    if (isset($assessmentsByCourse[$selectedCourseId])) {
      foreach ($assessmentsByCourse[$selectedCourseId] as $a) {
        if ((string)($a['assessment_id'] ?? '') === $selectedAssessmentId) {
          $assessmentName = $a['name'] ?? ($a['code'] ?? $selectedAssessmentId);
          $foundAssessment = true;
          break;
        }
      }
    }

    if (!$courseName || !$foundAssessment) {
      $error = 'Pilihan mata kuliah atau assessment tidak valid.';
    } else {
      $_SESSION['current_course'] = $courseName;
      $_SESSION['current_assessment'] = $assessmentName;
      $_SESSION['assessment_id'] = $selectedAssessmentId;
      $_SESSION['current_course_id'] = $selectedCourseId;
      // Set chat_user_id dari session user_id backend jika ada
      if (!empty($_SESSION['user_id'])) {
        $_SESSION['chat_user_id'] = $_SESSION['user_id'];
      }
      header('Location: chat.php');
      exit;
    }
  }
}
?><!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Select Course & Assessment</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css" />
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body { font-family: 'Manrope', system-ui, -apple-system, sans-serif; }
    /* Perkecil tampilan pagination DataTables */
    div.dataTables_paginate {
      font-size: 0.75rem;
    }
    .dataTables_paginate .paginate_button {
      padding: 0.15rem 0.45rem !important;
      margin: 0 2px !important;
      border-radius: 9999px !important;
    }
    /* Kecilkan tampilan search "Cari" DataTables */
    div.dataTables_filter {
      margin-bottom: 0.5rem; /* mirip Tailwind mb-2 */
    }
    div.dataTables_filter label {
      font-size: 0.75rem;
    }
    div.dataTables_filter input[type="search"] {
      font-size: 0.75rem;
      height: 1.6rem;
      padding: 0.1rem 0.4rem;
      border-radius: 0.375rem;
    }
    /* DataTables modern row styling */
    table.dataTable tbody tr {
      background-color: #ffffff;
      border-bottom: 1px solid #f1f5f9;
      transition: background-color 0.2s ease;
    }
    table.dataTable tbody tr:nth-child(even) {
      background-color: #f8fafc; /* very light slate */
    }
    table.dataTable tbody tr:hover {
      background-color: #f1f5f9 !important; /* light slate hover */
    }
    /* Perkecil tinggi dan padding Select2 di kolom Aksi */
    .select2-container .select2-selection--single {
      height: 1.7rem !important;
      padding: 0 0.25rem !important;
      display: flex;
      align-items: center;
      border-radius: 0.375rem;
      font-size: 0.75rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      padding-left: 0.25rem !important;
      padding-right: 1.25rem !important;
      line-height: 1.1rem !important;
      font-size: 0.75rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 100% !important;
    }
    /* Perkecil font di daftar dropdown Select2 */
    .select2-container .select2-results__option {
      font-size: 0.75rem;
      padding-top: 0.15rem;
      padding-bottom: 0.15rem;
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900">
  <div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-10 border-b border-slate-200/70 bg-white/80 backdrop-blur">
      <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-[#00A0A5] text-white grid place-items-center font-semibold">MK</div>
          <div>
            <div class="text-lg font-semibold">Select Course & Assessment</div>
            <div class="text-xs text-slate-500">Logged in as <span class="font-medium"><?php echo htmlspecialchars($username); ?></span></div>
          </div>
        </div>
        <nav class="flex items-center gap-3 text-sm font-medium">
          <a class="text-slate-900 font-semibold active" href="courses.php">Courses</a>
          <!-- <a class="text-slate-700 hover:text-slate-900" href="chat.php">Chat</a> -->
          <a class="text-slate-400 hover:text-slate-700" href="dashboard.php">Dashboard</a>
          <a class="ml-2 inline-flex items-center gap-2 rounded-full bg-[#00A0A5] text-white px-3 py-1 hover:bg-[#008488] shadow-sm" href="change_password.php">Change Password</a>
          <a href="logout.php" class="ml-2 inline-flex items-center gap-2 rounded-full bg-red-800 text-white px-3 py-1 hover:bg-red-600 shadow-sm">Logout</a>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <div class="max-w-6xl mx-auto px-4 py-6">

    <?php if ($error): ?>
      <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- API Key Status Notice Banner -->
    <div id="apiKeyNotice" class="hidden mb-4 rounded-xl border p-4 text-xs space-y-2 shadow-xs transition-all bg-white/80 backdrop-blur">
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

    <div class="glass rounded-2xl border border-white/60 bg-white/80 shadow-lg p-6">
      <h2 class="text-lg font-semibold mb-4 text-slate-900">Your Courses</h2>
      <?php if (empty($courses)): ?>
        <p class="text-sm text-slate-600">No courses connected to your account yet.</p>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table id="courses-table" class="min-w-full text-sm text-left border border-slate-200 rounded-lg overflow-hidden">
            <thead class="bg-slate-50 text-slate-600 font-semibold uppercase text-xs tracking-wider border-b border-slate-200">
              <tr>
                <th class="px-4 py-3 text-left">Course Code</th>
                <th class="px-4 py-3 text-left">Course Name</th>
                <th class="px-4 py-3 text-left">Assessment & Status</th>
                <th class="px-4 py-3 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($courses as $c): ?>
                <?php
                  $cid = (string)($c['course_id'] ?? '');
                  $code = $c['code'] ?? '-';
                  $name = $c['name'] ?? $code;
                  $courseAssessments = $assessmentsByCourse[$cid] ?? [];
                ?>
                <tr class="transition-colors">
                  <td class="px-4 py-3 align-middle whitespace-nowrap font-mono text-[13px] font-semibold text-slate-700"><?php echo htmlspecialchars($code); ?></td>
                  <td class="px-4 py-3 align-middle text-[13px] font-medium text-slate-800"><?php echo htmlspecialchars($name); ?></td>
                  <td class="px-4 py-3 align-middle">
                    <?php if (empty($courseAssessments)): ?>
                      <span class="text-xs text-slate-500">No assessments yet.</span>
                    <?php else: ?>
                      <div class="flex flex-col gap-2 max-h-32 overflow-y-auto pr-2 scrollbar-thin scrollbar-thumb-slate-300 scrollbar-track-slate-100">
                        <?php foreach ($courseAssessments as $a): ?>
                          <?php
                            $end = $a['end_date'] ?? '';
                            $assessmentLabel = htmlspecialchars($a['code'] ?? ($a['name'] ?? $a['assessment_id'] ?? '-'));
                            

                            if ($end) {
                              $endCleaned = str_replace(' GMT', '', $end);
                              $dt = new DateTime($endCleaned, new DateTimeZone('Asia/Jakarta'));
                              $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                              $isEnded = $dt < $now;
                              
                              if ($isEnded) {
                                $badgeColor = 'bg-rose-50 border-rose-200 text-rose-700';
                                $icon = '<svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                              } else {
                                $badgeColor = 'bg-emerald-50 border-emerald-200 text-emerald-700';
                                $icon = '<svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                              }
                              $dateStr = $dt->format('d M Y, H:i') . ' WIB';
                            } else {
                              $badgeColor = 'bg-slate-50 border-slate-200 text-slate-600';
                              $icon = '<svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                              $dateStr = 'No end date';
                            }
                          ?>
                          <div class="inline-flex items-center justify-between gap-3 px-3 py-1.5 rounded-full border <?php echo $badgeColor; ?> text-[11px] font-semibold tracking-wide shadow-sm">
                            <span><?php echo $assessmentLabel; ?></span>
                            <span class="flex items-center gap-1.5 opacity-90">
                              <?php echo $icon; ?>
                              <span><?php echo $dateStr; ?></span>
                            </span>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="px-2 py-1 align-top text-center">
                    <?php if (!empty($courseAssessments)): ?>
                      <?php
                        // Set timezone untuk consistency
                        date_default_timezone_set('Asia/Jakarta');
                        
                        // Filter only active (non-expired) assessments for dropdown
                        $activeAssessments = [];
                        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                        
                        // Debug: tampilkan waktu sekarang
                        $debugInfo = "<!-- Current time: " . $now->format('Y-m-d H:i:s') . " WIB -->\n";
                        
                        foreach ($courseAssessments as $a) {
                          $end = $a['end_date'] ?? '';
                          $isActive = false;
                          
                          // Debug: tampilkan data assessment
                          $debugInfo .= "<!-- Assessment: " . ($a['code'] ?? $a['name'] ?? 'Unknown') . " | end_date raw: " . var_export($end, true) . " -->\n";
                          
                          if (empty($end) || $end === null || $end === '' || $end === '0000-00-00 00:00:00') {
                            // No end date = always active
                            $isActive = true;
                            $debugInfo .= "<!-- -> No end_date, marked as ACTIVE -->\n";
                          } else {
                            try {
                              // Strip 'GMT' label if present (backend sends WIB time with GMT label)
                              $endCleaned = str_replace(' GMT', '', $end);
                              
                              // Parse as WIB time directly
                              $dt = new DateTime($endCleaned, new DateTimeZone('Asia/Jakarta'));
                              
                              $debugInfo .= "<!-- -> Parsed end_date (WIB): " . $dt->format('Y-m-d H:i:s') . " | Comparison: " . ($dt > $now ? "ACTIVE (future)" : "EXPIRED (past)") . " -->\n";
                              
                              // Assessment is active if end_date is in the future
                              if ($dt > $now) {
                                $isActive = true;
                              }
                            } catch (Exception $e) {
                              // If date parsing fails, treat as no end date (active)
                              $isActive = true;
                              $debugInfo .= "<!-- -> Parse error: " . $e->getMessage() . ", marked as ACTIVE -->\n";
                            }
                          }
                          
                          if ($isActive) {
                            $activeAssessments[] = $a;
                          }
                        }
                        
                        // Output debug info
                        echo $debugInfo;
                        echo "<!-- Total assessments: " . count($courseAssessments) . " | Active: " . count($activeAssessments) . " -->\n";
                      ?>
                      <?php if (!empty($activeAssessments)): ?>
                        <form method="post" class="inline-flex items-center justify-center gap-2 w-full mt-1">
                          <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($cid); ?>">
                          <select name="assessment_id" class="rounded-lg border border-slate-200 text-xs focus:border-[#00A0A5] focus:ring-1 focus:ring-[#00A0A5]/20 select2 min-w-[220px]" required>
                            <option class="text-xs text-slate-700" value="">Select active assessment (<?php echo count($activeAssessments); ?>/<?php echo count($courseAssessments); ?>)</option>
                            <?php foreach ($activeAssessments as $a): ?>
                              <?php
                                $aid = (string)($a['assessment_id'] ?? '');
                                $acode = $a['code'] ?? '';
                                $aname = $a['name'] ?? '';

                                if ($acode !== '' && $aname !== '') {
                                  $alabel = $acode . ' - ' . $aname;
                                } elseif ($acode !== '') {
                                  $alabel = $acode;
                                } elseif ($aname !== '') {
                                  $alabel = $aname;
                                } else {
                                  $alabel = $aid; // fallback terakhir kalau data kurang lengkap
                                }
                              ?>
                              <option value="<?php echo htmlspecialchars($aid); ?>"><?php echo htmlspecialchars($alabel); ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="rounded-lg bg-[#00A0A5] px-3 py-1.5 text-xs font-bold text-white hover:bg-[#008488] shadow-md hover:shadow-lg transition-all duration-200 whitespace-nowrap flex items-center gap-1">
                            Chat
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
                          </button>
                        </form>
                      <?php else: ?>
                        <span class="text-xs text-slate-400">All assessments expired</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-xs text-slate-400">Tidak ada assessment</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="mt-3 text-xs text-slate-500">Select the assessment for the desired course, then click the <strong>Chat</strong> button to go directly to the chatbot page.</p>
      <?php endif; ?>
    </div>
      </div>
    </main>
  </div>
  <script>
    const FASTAPI_URL = "http://127.0.0.1:5000";
    const SSO_USER_ID = "<?= htmlspecialchars($_SESSION['user_id'] ?? '') ?>";
    let userHasApiKey = false;

    async function checkUserApiKey() {
      if (!SSO_USER_ID) return;
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

    jQuery(function($) {
      checkUserApiKey();

      $('.select2').select2({ width: 'resolve' });

      $('#courses-table').DataTable({
        pageLength: 5,
        lengthChange: false,
        ordering: true,
        language: {
          search: 'Search:',
          zeroRecords: 'No matching courses found',
          info: 'Showing _START_–_END_ of _TOTAL_ courses',
          infoEmpty: 'Showing 0 of 0 courses',
          paginate: {
            first: 'First',
            last: 'Last',
            next: 'Next',
            previous: 'Previous'
          }
        }
      });

      $(document).on('submit', 'form', function(e) {
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
    });
  </script>
  <?php if (isset($_GET['password']) && $_GET['password'] === 'updated'): ?>
    <script>
      Swal.fire({
          icon: 'success',
          title: 'Password Updated',
          text: 'Your password has been updated successfully.',
          showConfirmButton: false,
          timer: 2000
      });
    </script>
  <?php endif; ?>
</body>
</html>
