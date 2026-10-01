<?php
require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/_wrapped_service.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$sessUid = $_SESSION['user_id'] ?? ($_GET['user_id'] ?? '');
$sessUname = $_SESSION['username'] ?? '';
$sessName = $_SESSION['name'] ?? '';

$out = [
    'session' => [
        'user_id' => $sessUid,
        'username' => $sessUname,
        'name' => $sessName,
        'session_keys' => array_keys($_SESSION ?? [])
    ],
    'matched_users' => [],
    'resolved_ids' => ssparc_resolve_all_user_identifiers($db, $sessUid),
    'chat_history_sample' => [],
    'chat_history_users' => [],
    'prompts_for_user' => []
];

// 1. Find user in table user
$q = $db->query("SELECT user_id, username, name, email FROM user WHERE name LIKE '%YEHEZKIEL%' OR username LIKE '%yehezkiel%' OR name LIKE '%SETIAWAN%' OR user_id = '$sessUid' OR username = '$sessUname' LIMIT 10");
if ($q) {
    while ($r = $q->fetch_assoc()) $out['matched_users'][] = $r;
}

// 2. Distinct users in chat_history
$hasChat = $db->query("SHOW TABLES LIKE 'chat_history'");
if ($hasChat && $hasChat->num_rows > 0) {
    $qc = $db->query("SELECT DISTINCT user_id, COUNT(*) as cnt FROM chat_history GROUP BY user_id LIMIT 50");
    if ($qc) {
        while ($rc = $qc->fetch_assoc()) $out['chat_history_users'][] = $rc;
    }
    
    // Sample latest 10 chat_history rows
    $qs = $db->query("SELECT id, user_id, session_id, assessment_id, role, LEFT(content, 100) AS preview, created_at FROM chat_history ORDER BY created_at DESC LIMIT 10");
    if ($qs) {
        while ($rs = $qs->fetch_assoc()) $out['chat_history_sample'][] = $rs;
    }
}

// 3. Fetched prompts
$out['prompts_for_user'] = ssparc_fetch_all_student_prompts($db, $out['resolved_ids'], null);

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
