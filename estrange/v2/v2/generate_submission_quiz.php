<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    echo json_encode(['status' => 'failed', 'error' => 'Unauthorized.']);
    exit;
}

$submissionId = filter_input(INPUT_GET, 'submission_id', FILTER_VALIDATE_INT);
if (!$submissionId) {
    http_response_code(400);
    echo json_encode(['status' => 'failed', 'error' => 'Invalid submission.']);
    exit;
}

include '_config.php';
include '_ai_quiz.php';

$stmt = $db->prepare('SELECT quiz_id, status, error_message FROM generated_quizzes WHERE submission_id = ? AND student_id = ?');
$stmt->bind_param('ii', $submissionId, $_SESSION['user_id']);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    http_response_code(404);
    echo json_encode(['status' => 'failed', 'error' => 'Quiz not found.']);
    exit;
}

if ($quiz['status'] === 'failed' && isset($_GET['retry']) && $_GET['retry'] === '1') {
    $retryStmt = $db->prepare("UPDATE generated_quizzes SET status = 'pending', error_message = NULL WHERE submission_id = ? AND student_id = ? AND answered_at IS NULL");
    $retryStmt->bind_param('ii', $submissionId, $_SESSION['user_id']);
    $retryStmt->execute();
    $retryStmt->close();
    $quiz['status'] = 'pending';
}

if ($quiz['status'] === 'pending') {
    generate_submission_quiz($db, $submissionId, (int)$_SESSION['user_id']);
    $stmt = $db->prepare('SELECT status, error_message FROM generated_quizzes WHERE submission_id = ? AND student_id = ?');
    $stmt->bind_param('ii', $submissionId, $_SESSION['user_id']);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

echo json_encode([
    'status' => $quiz['status'],
    'error' => $quiz['error_message'] ?? null
], JSON_UNESCAPED_UNICODE);
