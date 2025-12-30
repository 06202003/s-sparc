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
    /* Banded row untuk DataTables (kontras lebih kuat) */
    table.dataTable tbody tr:nth-child(odd) {
      background-color: #e2e8f0; /* slate-200 */
    }
    table.dataTable tbody tr:nth-child(even) {
      background-color: #f9fafb; /* slate-50 */
    }
    table.dataTable tbody tr:hover {
      background-color: #dbeafe !important; /* blue-100 */
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
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900">
  <div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-10 border-b border-slate-200/70 bg-white/80 backdrop-blur">
      <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white grid place-items-center font-semibold">MK</div>
          <div>
            <div class="text-lg font-semibold">Select Course & Assessment</div>
            <div class="text-xs text-slate-500">Logged in as <span class="font-medium"><?php echo htmlspecialchars($username); ?></span></div>
          </div>
        </div>
        <nav class="flex items-center gap-3 text-sm font-medium">
          <a class="text-slate-900 font-semibold active" href="courses.php">Courses</a>
          <!-- <a class="text-slate-700 hover:text-slate-900" href="chat.php">Chat</a> -->
          <a class="text-slate-400 hover:text-slate-700" href="dashboard.php">Dashboard</a>
          <a href="logout.php" class="ml-2 inline-flex items-center gap-2 rounded-full bg-red-800 text-white px-3 py-1 hover:bg-red-600 shadow-sm">Logout</a>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <div class="max-w-6xl mx-auto px-4 py-6">

    <?php if ($error): ?>
      <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="glass rounded-2xl border border-white/60 bg-white/80 shadow-lg p-6">
      <h2 class="text-lg font-semibold mb-4 text-slate-900">Your Courses</h2>
      <?php if (empty($courses)): ?>
        <p class="text-sm text-slate-600">No courses connected to your account yet.</p>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table id="courses-table" class="min-w-full text-sm text-left border border-slate-200 rounded-lg overflow-hidden">
            <thead class="bg-slate-100 text-slate-700">
              <tr>
                <th class="px-3 py-2 border-b border-slate-200">Course Code</th>
                <th class="px-3 py-2 border-b border-slate-200">Course Name</th>
                <th class="px-3 py-2 border-b border-slate-200">Assessment</th>
                <th class="px-3 py-2 border-b border-slate-200 text-center">Action</th>
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
                <tr class="hover:bg-slate-50">
                  <td class="px-3 py-2 align-top whitespace-nowrap font-mono text-xs text-slate-700"><?php echo htmlspecialchars($code); ?></td>
                  <td class="px-3 py-2 align-top text-slate-800"><?php echo htmlspecialchars($name); ?></td>
                  <td class="px-3 py-2 align-top">
                    <?php if (empty($courseAssessments)): ?>
                      <span class="text-xs text-slate-500">No assessments yet.</span>
                    <?php else: ?>
                      <?php
                        $labels = [];
                        foreach ($courseAssessments as $a) {
                          $aid = (string)($a['assessment_id'] ?? '');
                          $labels[] = $a['code'] ?? ($a['name'] ?? $aid);
                        }
                        $display = implode(', ', $labels);
                      ?>
                      <span class="text-xs text-slate-700"><?php echo htmlspecialchars($display); ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="px-2 py-1 align-top text-center">
                    <?php if (!empty($courseAssessments)): ?>
                      <form method="post" class="inline-flex items-center justify-center gap-1.5 w-full">
                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($cid); ?>">
                        <select name="assessment_id" class="rounded border border-slate-200 text-xs focus:border-blue-500 focus:ring-0 select2 min-w-[250px]" required>
                          <option class="text-xs text-slate-700" value="">Select assessment</option>
                          <?php foreach ($courseAssessments as $a): ?>
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
                        <button type="submit" class="rounded bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700 whitespace-nowrap">Chat</button>
                      </form>
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
    jQuery(function($) {
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
    });
  </script>
</body>
</html>
