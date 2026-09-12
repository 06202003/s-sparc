<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: student_dashboard.php');
    exit;
}
include '_config.php';
include '_ai_quiz.php';

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
    $retryStmt = $db->prepare("UPDATE generated_quizzes SET status = 'pending', error_message = NULL WHERE quiz_id = ? AND student_id = ? AND answered_at IS NULL");
    $retryStmt->bind_param('ii', $quiz['quiz_id'], $_SESSION['user_id']);
    $retryStmt->execute();
    $retryStmt->close();
    header('Location: student_submission_quiz.php?submission_id=' . (int)$submissionId);
    exit;
}

if ($quiz['status'] === 'ready' && empty($quiz['answered_at']) && empty($quiz['quiz_started_at'])) {
    $startStmt = $db->prepare('UPDATE generated_quizzes SET quiz_started_at = NOW(), quiz_expires_at = DATE_ADD(NOW(), INTERVAL 1 MINUTE) WHERE quiz_id = ? AND quiz_started_at IS NULL');
    $startStmt->bind_param('i', $quiz['quiz_id']);
    $startStmt->execute();
    $startStmt->close();
    $quiz['quiz_started_at'] = date('Y-m-d H:i:s');
    $quiz['quiz_expires_at'] = date('Y-m-d H:i:s', time() + 60);
}

$message = '';
if ($quiz['status'] === 'ready' && empty($quiz['answered_at']) && !empty($quiz['quiz_expires_at'])) {
    $expiresAtUnix = strtotime($quiz['quiz_expires_at']);
    if ($expiresAtUnix !== false && $expiresAtUnix <= time()) {
        $message = 'Waktu menjawab sudah habis. Jawaban tidak dapat dikirim.';
        $expiredStmt = $db->prepare('UPDATE generated_quizzes SET answered_at = NOW(), score_points = 0 WHERE quiz_id = ? AND answered_at IS NULL');
        $expiredStmt->bind_param('i', $quiz['quiz_id']);
        $expiredStmt->execute();
        $expiredStmt->close();
        $quiz['answered_at'] = date('Y-m-d H:i:s');
        $quiz['score_points'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz['status'] === 'ready' && empty($quiz['answered_at'])) {
    if (isset($_POST['abort_quiz']) && $_POST['abort_quiz'] === '1') {
        abort_submission_quiz($db, (int)$quiz['quiz_id']);
        $quiz['answered_at'] = date('Y-m-d H:i:s');
        $quiz['score_points'] = 0;
        $message = 'Sesi quiz dibatalkan karena Anda berpindah tab atau memindahkan fokus dari browser. Nilai: 0/3.';
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    } elseif (empty($quiz['quiz_expires_at']) || strtotime($quiz['quiz_expires_at']) < time()) {
        $message = 'Waktu menjawab sudah habis. Jawaban tidak dapat dikirim.';
        $expiredStmt = $db->prepare('UPDATE generated_quizzes SET answered_at = NOW(), score_points = 0 WHERE quiz_id = ? AND answered_at IS NULL');
        $expiredStmt->bind_param('i', $quiz['quiz_id']);
        $expiredStmt->execute();
        $expiredStmt->close();
        $quiz['answered_at'] = date('Y-m-d H:i:s');
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
            $updateQuiz = $db->prepare('UPDATE generated_quizzes SET score_points = ?, penalty_points = CASE WHEN ? < 3 THEN penalty_points ELSE 0 END, answered_at = NOW() WHERE quiz_id = ? AND answered_at IS NULL');
            $updateQuiz->bind_param('dii', $score, $correctCount, $quiz['quiz_id']);
            $updateQuiz->execute();
            $updateQuiz->close();
            $quiz['answered_at'] = date('Y-m-d H:i:s');
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
if ($expiresAtMs <= 0) {
    $expiresAtMs = time() * 1000;
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
      if (data.status === 'ready') window.location.reload();
      if (data.status === 'failed') window.location.reload();
    });
}, 2000);
</script>
<?php elseif ($quiz['status'] === 'failed'): ?>
<div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">Soal tidak dapat dibuat: <?= htmlspecialchars($quiz['error_message'] ?: 'Kesalahan tidak diketahui.') ?></div>
<a href="student_submission_quiz.php?submission_id=<?= (int)$submissionId ?>&retry=1" class="mt-6 block rounded-xl bg-teal-600 px-4 py-3 text-center text-sm font-semibold text-white">Coba Generate Lagi</a>
<a href="student_submission.php" class="mt-6 block rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-semibold text-white">Kembali ke Submission</a>
<?php elseif (!empty($quiz['answered_at'])): ?>
<div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"><?= htmlspecialchars($message ?: 'Quiz sudah diselesaikan.') ?> Nilai quiz: <?= htmlspecialchars((string)$quiz['score_points']) ?>/3.</div>
<a href="student_submission.php" class="mt-6 block rounded-xl bg-teal-600 px-4 py-3 text-center text-sm font-semibold text-white">Kembali ke Submission</a>
<?php else: ?>
<form method="post" id="quiz-form" class="mt-6 space-y-6">
<div class="sticky top-3 z-10 flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-bold text-amber-900"><span>Waktu tersisa</span><span id="quiz-timer">01:00</span></div>
<?php foreach ($questions as $index => $question): ?>
<fieldset class="space-y-3">
<legend class="font-semibold"><?= $index + 1 ?>. <?= htmlspecialchars($question['question_text']) ?></legend>
<?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $option => $column): ?>
<label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm hover:bg-slate-100"><input required type="radio" name="answers[<?= (int)$question['question_id'] ?>]" value="<?= $option ?>"><span><strong><?= $option ?>.</strong> <?= htmlspecialchars($question[$column]) ?></span></label>
<?php endforeach; ?>
</fieldset>
<?php endforeach; ?>
<?php if ($message): ?><p class="text-sm font-semibold text-rose-700"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<button class="w-full rounded-xl bg-teal-600 px-4 py-3 text-sm font-bold text-white hover:bg-teal-700" type="submit">Kirim Jawaban</button>
</form>
<script>
(function () {
    var expiresAt = <?= (int)$expiresAtMs ?>;
    var timer = document.getElementById('quiz-timer');
    var interval = setInterval(function () {
        var remaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
        var minutes = Math.floor(remaining / 60);
        var seconds = remaining % 60;
        timer.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

        if (remaining <= 0) {
            clearInterval(interval);
            timer.textContent = '00:00';
            alert('Waktu menjawab sudah habis.');
            window.location.reload();
        }
    }, 250);

    // Anti-cheating: Otomatis hentikan quiz jika pindah tab atau keluar fokus browser
    var quizSubmitted = false;
    var isReady = false;
    setTimeout(function () { isReady = true; }, 1000);

    var quizForm = document.getElementById('quiz-form');
    if (quizForm) {
        quizForm.addEventListener('submit', function () {
            quizSubmitted = true;
        });
    }

    function handleQuizAbort() {
        if (!isReady || quizSubmitted) return;
        quizSubmitted = true;

        var formData = new FormData();
        formData.append('abort_quiz', '1');
        formData.append('ajax', '1');

        if (navigator.sendBeacon) {
            navigator.sendBeacon(window.location.href, formData);
        } else {
            fetch(window.location.href, { method: 'POST', body: formData, keepalive: true });
        }

        alert('Anda berpindah tab atau keluar dari fokus browser! Sesi quiz dihentikan dan nilai Anda 0/3.');
        window.location.reload();
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            handleQuizAbort();
        }
    });

    window.addEventListener('blur', function () {
        handleQuizAbort();
    });
})();
</script>
<?php endif; ?>
</div>
</main>
</body>
</html>

