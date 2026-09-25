<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: student_dashboard.php');
    exit;
}
include '_config.php';
include '_ai_quiz.php';

$nowUnix = time();
$duration = 60; // 1 menit waktu pengerjaan quiz

$submissionId = filter_input(INPUT_GET, 'submission_id', FILTER_VALIDATE_INT);
if (!$submissionId) {
    header('Location: student_dashboard.php');
    exit;
}

$stmt = $db->prepare('SELECT quiz_id, status, penalty_points, score_points, answered_at, quiz_started_at, quiz_expires_at, error_message FROM generated_quizzes WHERE submission_id = ? AND student_id = ?');
$stmt->bind_param('ii', $submissionId, $_SESSION['user_id']);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$quiz) {
    header('Location: student_dashboard.php');
    exit;
}

if ($quiz['status'] === 'failed' && isset($_GET['retry']) && $_GET['retry'] === '1' && empty($quiz['answered_at'])) {
    $retryStmt = $db->prepare("UPDATE generated_quizzes SET status = 'pending', error_message = NULL, quiz_started_at = NULL, quiz_expires_at = NULL, answered_at = NULL, score_points = NULL WHERE quiz_id = ? AND student_id = ?");
    $retryStmt->bind_param('ii', $quiz['quiz_id'], $_SESSION['user_id']);
    $retryStmt->execute();
    $retryStmt->close();
    header('Location: student_submission_quiz.php?submission_id=' . (int)$submissionId);
    exit;
}

// Start quiz timer if ready and not started yet
if ($quiz['status'] === 'ready' && empty($quiz['answered_at']) && empty($quiz['quiz_started_at'])) {
    $startedAtStr = date('Y-m-d H:i:s', $nowUnix);
    $expiresAtStr = date('Y-m-d H:i:s', $nowUnix + $duration);
    
    $startStmt = $db->prepare('UPDATE generated_quizzes SET quiz_started_at = ?, quiz_expires_at = ? WHERE quiz_id = ? AND quiz_started_at IS NULL');
    $startStmt->bind_param('ssi', $startedAtStr, $expiresAtStr, $quiz['quiz_id']);
    $startStmt->execute();
    $startStmt->close();
    
    $quiz['quiz_started_at'] = $startedAtStr;
    $quiz['quiz_expires_at'] = $expiresAtStr;
}

$remainingSeconds = $duration;
if (!empty($quiz['quiz_expires_at'])) {
    $expiresAtUnix = strtotime($quiz['quiz_expires_at']);
    if ($expiresAtUnix !== false) {
        $remainingSeconds = max(0, $expiresAtUnix - $nowUnix);
    }
}

$message = '';
// Check if quiz has genuinely expired (with 10s grace tolerance to avoid false triggers on page load)
if ($quiz['status'] === 'ready' && empty($quiz['answered_at']) && !empty($quiz['quiz_expires_at'])) {
    $expiresAtUnix = strtotime($quiz['quiz_expires_at']);
    if ($expiresAtUnix !== false && $expiresAtUnix < ($nowUnix - 10)) {
        $message = 'Waktu menjawab sudah habis. Nilai quiz: 0/3.';
        $nowStr = date('Y-m-d H:i:s', $nowUnix);
        $expiredStmt = $db->prepare('UPDATE generated_quizzes SET answered_at = ?, score_points = 0 WHERE quiz_id = ? AND answered_at IS NULL');
        $expiredStmt->bind_param('si', $nowStr, $quiz['quiz_id']);
        $expiredStmt->execute();
        $expiredStmt->close();
        $quiz['answered_at'] = $nowStr;
        $quiz['score_points'] = 0;
        $remainingSeconds = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz['status'] === 'ready' && empty($quiz['answered_at'])) {
    if (isset($_POST['abort_quiz']) && $_POST['abort_quiz'] === '1') {
        abort_submission_quiz($db, (int)$quiz['quiz_id']);
        $quiz['answered_at'] = date('Y-m-d H:i:s', $nowUnix);
        $quiz['score_points'] = 0;
        $message = 'Sesi quiz dibatalkan karena Anda berpindah tab. Nilai: 0/3.';
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    } elseif (isset($_POST['expire_quiz']) && $_POST['expire_quiz'] === '1') {
        $nowStr = date('Y-m-d H:i:s', $nowUnix);
        $expiredStmt = $db->prepare('UPDATE generated_quizzes SET answered_at = ?, score_points = 0 WHERE quiz_id = ? AND answered_at IS NULL');
        $expiredStmt->bind_param('si', $nowStr, $quiz['quiz_id']);
        $expiredStmt->execute();
        $expiredStmt->close();
        $quiz['answered_at'] = $nowStr;
        $quiz['score_points'] = 0;
        $message = 'Waktu menjawab sudah habis. Jawaban tidak dapat dikirim. Nilai: 0/3.';
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    } elseif (empty($quiz['quiz_expires_at']) || (strtotime($quiz['quiz_expires_at']) < ($nowUnix - 10))) {
        $message = 'Waktu menjawab sudah habis. Jawaban tidak dapat dikirim. Nilai: 0/3.';
        $nowStr = date('Y-m-d H:i:s', $nowUnix);
        $expiredStmt = $db->prepare('UPDATE generated_quizzes SET answered_at = ?, score_points = 0 WHERE quiz_id = ? AND answered_at IS NULL');
        $expiredStmt->bind_param('si', $nowStr, $quiz['quiz_id']);
        $expiredStmt->execute();
        $expiredStmt->close();
        $quiz['answered_at'] = $nowStr;
        $quiz['score_points'] = 0;
    } else {
        $answers = $_POST['answers'] ?? [];
        $questionStmt = $db->prepare('SELECT question_id, correct_option FROM generated_quiz_questions WHERE quiz_id = ? ORDER BY question_id');
        $questionStmt->bind_param('i', $quiz['quiz_id']);
        $questionStmt->execute();
        $questions = $questionStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $questionStmt->close();
        if (count($questions) === 3 && count($answers) === 3) {
            $correctCount = 0;
            $updateQuestion = $db->prepare('UPDATE generated_quiz_questions SET selected_option = ?, is_correct = ? WHERE question_id = ? AND quiz_id = ?');
            foreach ($questions as $question) {
                $selected = strtoupper((string)($answers[$question['question_id']] ?? ''));
                $isCorrect = in_array($selected, ['A', 'B', 'C', 'D'], true) && $selected === $question['correct_option'] ? 1 : 0;
                $correctCount += $isCorrect;
                $updateQuestion->bind_param('siii', $selected, $isCorrect, $question['question_id'], $quiz['quiz_id']);
                $updateQuestion->execute();
            }
            $updateQuestion->close();
            $score = (float)$correctCount;
            $nowStr = date('Y-m-d H:i:s', $nowUnix);
            $updateQuiz = $db->prepare('UPDATE generated_quizzes SET score_points = ?, penalty_points = CASE WHEN ? < 3 THEN penalty_points ELSE 0 END, answered_at = ? WHERE quiz_id = ? AND answered_at IS NULL');
            $updateQuiz->bind_param('disi', $score, $correctCount, $nowStr, $quiz['quiz_id']);
            $updateQuiz->execute();
            $updateQuiz->close();
            $quiz['answered_at'] = $nowStr;
            $quiz['score_points'] = $score;
            $message = 'Quiz selesai. Jawaban benar: ' . $correctCount . ' dari 3.';
        } else {
            $message = 'Jawab semua pertanyaan terlebih dahulu.';
        }
    }
}

$questions = [];
if ($quiz['status'] === 'ready') {
    $stmt = $db->prepare('SELECT question_id, question_text, option_a, option_b, option_c, option_d, selected_option, is_correct FROM generated_quiz_questions WHERE quiz_id = ? ORDER BY question_id');
    $stmt->bind_param('i', $quiz['quiz_id']);
    $stmt->execute();
    $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$expiresAtMs = 0;
if (!empty($quiz['quiz_expires_at'])) {
    $expiresAtUnix = strtotime($quiz['quiz_expires_at']);
    if ($expiresAtUnix !== false) {
        $expiresAtMs = (int)($expiresAtUnix * 1000);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>E-STRANGE: Quiz Submission</title>
<link rel="icon" href="strange_html_layout_additional_files/icon.png">
<script src="https://cdn.tailwindcss.com"></script>
<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>body { font-family: Inter, system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900">
<main class="min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
<h1 class="text-xl font-bold">Verifikasi Kode Submission</h1>
<p class="mt-2 text-sm text-slate-500">Jawab tiga pertanyaan singkat berdasarkan kode yang baru Anda kirim.</p>
<?php if ($quiz['status'] === 'pending'): ?>
<div class="mt-8 rounded-xl border border-teal-200 bg-teal-50 p-6 text-center">
<div class="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-4 border-teal-200 border-t-teal-600"></div>
<p class="font-semibold text-teal-900">Soal sedang dibuat...</p>
<p class="mt-2 text-sm text-teal-700">Mohon tetap berada di halaman ini sampai soal muncul.</p>
</div>
<script>
setInterval(function () {
  fetch('generate_submission_quiz.php?submission_id=<?= (int)$submissionId ?>', { cache: 'no-store' })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      if (data.status === 'ready' || data.status === 'failed') {
        window.location.reload();
      }
    }).catch(function() {});
}, 2000);
</script>
<?php elseif ($quiz['status'] === 'failed'): ?>
<div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">Soal tidak dapat dibuat: <?= htmlspecialchars($quiz['error_message'] ?: 'Kesalahan tidak diketahui.') ?></div>
<a href="student_submission_quiz.php?submission_id=<?= (int)$submissionId ?>&retry=1" class="mt-6 block rounded-xl bg-teal-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-teal-700 transition">Coba Generate Lagi</a>
<a href="student_submission.php" class="mt-6 block rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-slate-800 transition">Kembali ke Submission</a>
<?php elseif (!empty($quiz['answered_at'])): ?>
<div class="mt-6 rounded-xl border <?= (float)$quiz['score_points'] >= 2 ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' ?> p-4 text-sm font-medium">
<div class="font-bold text-base mb-1"><?= (float)$quiz['score_points'] >= 2 ? '🎉 Quiz Berhasil Diselesaikan!' : '⚠️ Quiz Selesai' ?></div>
<?= htmlspecialchars($message ?: 'Sesi quiz telah selesai.') ?> Nilai quiz Anda: <span class="font-bold text-lg"><?= htmlspecialchars((string)$quiz['score_points']) ?>/3</span>.
</div>
<a href="student_submission.php" class="mt-6 block rounded-xl bg-teal-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-teal-700 transition">Kembali ke Submission</a>
<?php if (!empty($message)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: '<?= (float)$quiz['score_points'] >= 2 ? 'success' : 'info' ?>',
            title: 'Hasil Quiz',
            text: <?= json_encode($message . ' (Nilai: ' . $quiz['score_points'] . '/3)') ?>,
            confirmButtonColor: '#0d9488',
            confirmButtonText: 'OK'
        });
    }
});
</script>
<?php endif; ?>
<?php else: ?>
<form method="post" id="quiz-form" class="mt-6 space-y-6">
<div class="sticky top-3 z-10 flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-bold text-amber-900 shadow-sm">
  <span class="flex items-center gap-2">
    <svg class="w-4 h-4 text-amber-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    Waktu Tersisa
  </span>
  <span id="quiz-timer" class="font-mono text-base font-extrabold text-amber-800">01:00</span>
</div>
<?php foreach ($questions as $index => $question): ?>
<fieldset class="space-y-3">
<legend class="font-semibold text-slate-800"><?= $index + 1 ?>. <?= htmlspecialchars($question['question_text']) ?></legend>
<?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $option => $column): ?>
<label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm hover:bg-teal-50 hover:border-teal-300 transition"><input required type="radio" name="answers[<?= (int)$question['question_id'] ?>]" value="<?= $option ?>" class="text-teal-600 focus:ring-teal-500"><span><strong><?= $option ?>.</strong> <?= htmlspecialchars($question[$column]) ?></span></label>
<?php endforeach; ?>
</fieldset>
<?php endforeach; ?>
<?php if ($message): ?><p class="text-sm font-semibold text-rose-700"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<button class="w-full rounded-xl bg-teal-600 px-4 py-3 text-sm font-bold text-white hover:bg-teal-700 shadow-md transition" type="submit">Kirim Jawaban</button>
</form>
<script>
(function () {
    var remainingSeconds = <?= (int)$remainingSeconds ?>;
    var startTime = performance.now();
    var timer = document.getElementById('quiz-timer');
    var isExpiredHandled = false;

    function handleTimeExpired() {
        if (isExpiredHandled || quizSubmitted) return;
        isExpiredHandled = true;
        quizSubmitted = true;
        if (timer) timer.textContent = '00:00';

        var formData = new FormData();
        formData.append('expire_quiz', '1');
        formData.append('ajax', '1');

        if (navigator.sendBeacon) {
            navigator.sendBeacon(window.location.href, formData);
        } else {
            fetch(window.location.href, { method: 'POST', body: formData, keepalive: true }).catch(function(){});
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Waktu Habis!',
                text: 'Waktu menjawab sudah habis. Quiz otomatis diselesaikan (Nilai: 0/3).',
                confirmButtonColor: '#0d9488',
                confirmButtonText: 'Lihat Hasil',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(function () {
                window.location.reload();
            });
        } else {
            alert('Waktu menjawab sudah habis. Nilai quiz: 0/3.');
            window.location.reload();
        }
    }

    function updateTimer() {
        var elapsed = Math.floor((performance.now() - startTime) / 1000);
        var remaining = Math.max(0, remainingSeconds - elapsed);
        var minutes = Math.floor(remaining / 60);
        var seconds = remaining % 60;
        if (timer) {
            timer.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }

        if (remaining <= 0) {
            handleTimeExpired();
        }
    }

    updateTimer();
    var interval = setInterval(updateTimer, 500);

    // Anti-cheating: Hentikan quiz jika berpindah tab browser
    var quizSubmitted = false;
    var isReady = false;
    setTimeout(function () { isReady = true; }, 1500);

    var quizForm = document.getElementById('quiz-form');
    if (quizForm) {
        quizForm.addEventListener('submit', function (e) {
            if (quizSubmitted) return;
            e.preventDefault();

            // Validasi kelengkapan jawaban
            var totalQuestions = <?= count($questions) ?>;
            var checkedRadios = quizForm.querySelectorAll('input[type="radio"]:checked');
            if (checkedRadios.length < totalQuestions) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Jawaban Belum Lengkap',
                        text: 'Harap jawab semua ' + totalQuestions + ' pertanyaan sebelum mengirim!',
                        confirmButtonColor: '#0d9488',
                        confirmButtonText: 'Mengerti'
                    });
                } else {
                    alert('Harap jawab semua pertanyaan terlebih dahulu.');
                }
                return;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Kirim Jawaban?',
                    text: 'Apakah Anda yakin ingin menyelesaikan quiz ini sekarang?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d9488',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Ya, Kirim Sekarang',
                    cancelButtonText: 'Periksa Lagi'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        quizSubmitted = true;
                        Swal.fire({
                            title: 'Memproses Jawaban...',
                            text: 'Mohon tunggu sebentar',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: function () {
                                Swal.showLoading();
                            }
                        });
                        quizForm.submit();
                    }
                });
            } else {
                quizSubmitted = true;
                quizForm.submit();
            }
        });
    }

    function handleQuizAbort() {
        if (!isReady || quizSubmitted || isExpiredHandled) return;
        quizSubmitted = true;
        isExpiredHandled = true;

        var formData = new FormData();
        formData.append('abort_quiz', '1');
        formData.append('ajax', '1');

        if (navigator.sendBeacon) {
            navigator.sendBeacon(window.location.href, formData);
        } else {
            fetch(window.location.href, { method: 'POST', body: formData, keepalive: true }).catch(function(){});
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Sesi Dibatalkan!',
                text: 'Anda berpindah tab browser. Sesi quiz dihentikan dan nilai Anda 0/3.',
                confirmButtonColor: '#e11d48',
                confirmButtonText: 'Tutup',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(function () {
                window.location.reload();
            });
        } else {
            alert('Anda berpindah tab browser! Sesi quiz dihentikan dan nilai Anda 0/3.');
            window.location.reload();
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            handleQuizAbort();
        }
    });
})();
</script>
<?php endif; ?>
</div>
</main>
</body>
</html>
