<?php
/**
 * S-SPARC Universal Backend API Proxy
 * Relays API requests to the Python AI backend (local or online https://estrangeinternal.itmaranatha.org)
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../_config.php';

// Allow CORS for local intranet & lab clients
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-User-ID, X-Student-ID, X-API-Key, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Target backend URL (can be overridden via environment or config)
$backendBaseUrl = getenv('FASTAPI_BACKEND_URL') ?: 'https://estrangeinternal.itmaranatha.org';

// Determine the path / endpoint
$path = '';
if (!empty($_GET['path'])) {
    $path = $_GET['path'];
} elseif (!empty($_GET['endpoint'])) {
    $path = $_GET['endpoint'];
} elseif (!empty($_SERVER['PATH_INFO'])) {
    $path = $_SERVER['PATH_INFO'];
} else {
    // Extract relative path from REQUEST_URI if accessed like api_proxy.php/api/...
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($requestUri, $scriptName) === 0) {
        $path = substr($requestUri, strlen($scriptName));
        $path = explode('?', $path)[0]; // remove query string
    }
}

// 1. Direct High-Performance Cohort Telemetry Handler
if ($path === '/api/admin/wrapped/analytics' || $path === '/admin/wrapped/analytics') {
    require_once __DIR__ . '/_wrapped_service.php';
    header('Content-Type: application/json; charset=utf-8');
    
    $courseId = $_GET['course_id'] ?? null;
    $assessmentId = $_GET['assessment_id'] ?? null;
    $data = ssparc_get_cohort_research_analytics($db, $courseId, $assessmentId);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Direct Research Telemetry CSV Exporter
if ($path === '/api/admin/wrapped/export-csv' || $path === '/admin/wrapped/export-csv') {
    require_once __DIR__ . '/_wrapped_service.php';
    
    $courseId = $_GET['course_id'] ?? null;
    $assessmentId = $_GET['assessment_id'] ?? null;
    $data = ssparc_get_cohort_research_analytics($db, $courseId, $assessmentId);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ssparc_cohort_telemetry_' . date('Ymd_His') . '.csv"');
    
    $outStream = fopen('php://output', 'w');
    fputcsv($outStream, ['Assessment ID', 'Anonymous NIM', 'Student Name', 'Total Prompts', 'C-I-O-E (%)', 'Shannon Entropy', 'Archetype', 'Literacy Tier', 'Energy (Wh)', 'CO2 (g)']);
    
    foreach ($data['student_telemetry'] ?? [] as $r) {
        fputcsv($outStream, [
            $assessmentId ?: 'all',
            'STU_' . substr(md5($r['nim']), 0, 6),
            $r['name'],
            $r['total_prompts'],
            $r['cioe_score'],
            $r['shannon_entropy'],
            $r['archetype'],
            $r['literacy_tier'],
            $r['energy_wh'],
            $r['carbon_g']
        ]);
    }
    fclose($outStream);
    exit;
}

// 3. Direct Student AI Literacy Profile Handler
if (preg_match('#^/api/educational/student-profile/(.+)$#', $path, $matches)) {
    require_once __DIR__ . '/_wrapped_service.php';
    header('Content-Type: application/json; charset=utf-8');
    
    $targetUser = trim($matches[1]);
    $prof = ssparc_get_student_aggregated_profile($db, $targetUser);
    echo json_encode($prof, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Direct Environmental Footprint Telemetry Handler
if ($path === '/api/environmental/footprint') {
    require_once __DIR__ . '/_wrapped_service.php';
    header('Content-Type: application/json; charset=utf-8');
    
    $cohort = ssparc_get_cohort_research_analytics($db, $_GET['course_id'] ?? null, $_GET['assessment_id'] ?? null);
    $totWh = (float)($cohort['total_class_wh'] ?? 0.0);
    $totCarbonG = (float)($cohort['total_class_carbon_g'] ?? 0.0);
    $totCarbonKg = round($totCarbonG / 1000.0, 5);
    $totKwh = round($totWh / 1000.0, 5);
    $totWaterL = round(($totWh * 4.65) / 1000.0, 4);

    $daysCount = max(1, min(90, (int)($_GET['days'] ?? 30)));
    $avgDailyKwh = round($totKwh / $daysCount, 5);

    $footprintData = [
        'status' => 'success',
        'scope' => $_GET['scope'] ?? 'all',
        'days' => $daysCount,
        'totals' => [
            'energy_kwh' => $totKwh,
            'energy_wh' => $totWh,
            'carbon_kg' => $totCarbonKg,
            'carbon_g' => $totCarbonG,
            'water_l' => $totWaterL,
            'water_ml' => $totWaterL * 1000.0,
            'total_prompts' => $cohort['total_class_prompts'] ?? 0
        ],
        'avg_daily_kwh' => $avgDailyKwh,
        'equivalents' => [
            'phone_charges' => round($totWh / 12.0, 1),
            'led_hours' => round($totWh / 9.0, 1),
            'kettle_boils' => round($totWh / 1500.0, 2),
            'car_km' => round($totCarbonKg / 0.192, 2),
            'tree_days' => round($totCarbonKg / (21.0 / 365.0), 1),
            'shower_minutes' => round($totWaterL / 9.0, 2)
        ],
        'daily' => [
            [
                'day' => date('Y-m-d'),
                'energy_kwh' => $totKwh,
                'carbon_kg' => $totCarbonKg,
                'water_l' => $totWaterL
            ]
        ]
    ];
    echo json_encode($footprintData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['debug']) || (isset($_GET['action']) && $_GET['action'] === 'debug_user')) {
    require_once __DIR__ . '/../_config.php';
    require_once __DIR__ . '/_wrapped_service.php';
    header('Content-Type: application/json; charset=utf-8');
    
    $sessUserId = $_SESSION['user_id'] ?? ($_GET['user_id'] ?? '');
    $sessUname = $_SESSION['username'] ?? '';
    $sessName = $_SESSION['name'] ?? '';
    
    $out = [
        'session' => [
            'user_id' => $sessUserId,
            'username' => $sessUname,
            'name' => $sessName,
            'all' => $_SESSION ?? []
        ],
        'user_table_matches' => [],
        'users_table_matches' => [],
        'resolved_identifiers_sql' => ssparc_resolve_all_user_identifiers($db, $sessUserId),
        'chat_history_latest' => [],
        'gpt_jobs_latest' => [],
        'fetched_prompts' => []
    ];
    
    // 1. Database name and list of databases on server
    $dbNameRes = $db->query("SELECT DATABASE() AS cur_db");
    $out['current_database'] = $dbNameRes ? $dbNameRes->fetch_assoc()['cur_db'] : 'unknown';
    
    $dbsRes = $db->query("SHOW DATABASES");
    $out['server_databases'] = [];
    if ($dbsRes) {
        while ($dbr = $dbsRes->fetch_assoc()) {
            $out['server_databases'][] = reset($dbr);
        }
    }

    // 2. Table row counts across all databases on server
    $out['table_row_counts'] = [];
    $candidateTables = ['chat_history', 'gpt_jobs', 'educational_learning_logs', 'code_embeddings', 'session_tokens', 'submission', 'suspicion', 'code_clarity_suggestion', 'user', 'users', 'game_student_course', 'enrollment', 'assessment', 'course'];
    foreach ($candidateTables as $tbl) {
        $chk = $db->query("SHOW TABLES LIKE '$tbl'");
        if ($chk && $chk->num_rows > 0) {
            $cntRes = $db->query("SELECT COUNT(*) AS total FROM $tbl");
            $out['table_row_counts'][$tbl] = $cntRes ? (int)$cntRes->fetch_assoc()['total'] : 0;
        } else {
            $out['table_row_counts'][$tbl] = 'TABLE_NOT_FOUND';
        }
    }

    // 2b. Check if S-SPARC tables exist in other databases on server (e.g. estrange_v7, estrange_v6)
    $out['ssparc_tables_in_other_dbs'] = [];
    foreach ($out['server_databases'] as $otherDb) {
        if ($otherDb === 'information_schema' || $otherDb === 'estrange_ssparc') continue;
        $qT = $db->query("SHOW TABLES FROM `$otherDb` LIKE 'chat_history'");
        if ($qT && $qT->num_rows > 0) {
            $cntChat = $db->query("SELECT COUNT(*) AS total FROM `$otherDb`.`chat_history`");
            $cntVal = $cntChat ? (int)$cntChat->fetch_assoc()['total'] : 0;
            $out['ssparc_tables_in_other_dbs'][$otherDb] = [
                'has_chat_history' => true,
                'chat_history_rows' => $cntVal
            ];
            
            // Sample latest rows from this DB
            $sampleQ = $db->query("SELECT id, user_id, session_id, assessment_id, role, LEFT(content, 60) as preview, created_at FROM `$otherDb`.`chat_history` ORDER BY created_at DESC LIMIT 5");
            if ($sampleQ) {
                while ($sq = $sampleQ->fetch_assoc()) {
                    $out['ssparc_tables_in_other_dbs'][$otherDb]['sample_rows'][] = $sq;
                }
            }
        }
    }

    // 3. User submissions history for account 218
    $out['user_submissions'] = [];
    $subRes = $db->query("SELECT s.submission_id, s.assessment_id, a.name AS assessment_name, s.attempt, s.submitted_time, s.filename 
                          FROM submission s 
                          LEFT JOIN assessment a ON s.assessment_id = a.assessment_id 
                          WHERE s.submitter_id = '$sessUserId' 
                          ORDER BY s.submitted_time DESC LIMIT 10");
    if ($subRes) {
        while ($sr = $subRes->fetch_assoc()) $out['user_submissions'][] = $sr;
    }

    // 4. Matches in user
    $qUser = $db->query("SELECT user_id, username, name, email, role FROM user WHERE name LIKE '%YEHEZKIEL%' OR username LIKE '%yehezkiel%' OR name LIKE '%SETIAWAN%' OR user_id = '$sessUserId' OR username = '$sessUname' LIMIT 10");
    if ($qUser) {
        while ($r = $qUser->fetch_assoc()) $out['user_table_matches'][] = $r;
    }
    
    // 5. Matches in users
    $hasUsers = $db->query("SHOW TABLES LIKE 'users'");
    if ($hasUsers && $hasUsers->num_rows > 0) {
        $qUsers = $db->query("SELECT * FROM users WHERE username LIKE '%yehezkiel%' OR email LIKE '%yehezkiel%' OR username LIKE '%setiawan%' OR user_id = '$sessUserId' LIMIT 10");
        if ($qUsers) {
            while ($r = $qUsers->fetch_assoc()) $out['users_table_matches'][] = $r;
        }
    }
    
    // 6. chat_history latest 20 rows
    $hasChat = $db->query("SHOW TABLES LIKE 'chat_history'");
    if ($hasChat && $hasChat->num_rows > 0) {
        $qChat = $db->query("SELECT id, user_id, session_id, assessment_id, role, LEFT(content, 80) AS content_preview, created_at FROM chat_history ORDER BY created_at DESC LIMIT 20");
        if ($qChat) {
            while ($r = $qChat->fetch_assoc()) $out['chat_history_latest'][] = $r;
        }
    }

    // 7. gpt_jobs latest 20 rows
    $hasJobs = $db->query("SHOW TABLES LIKE 'gpt_jobs'");
    if ($hasJobs && $hasJobs->num_rows > 0) {
        $qJobs = $db->query("SELECT id, user_id, LEFT(prompt, 80) AS prompt_preview, status, created_at FROM gpt_jobs ORDER BY created_at DESC LIMIT 20");
        if ($qJobs) {
            while ($r = $qJobs->fetch_assoc()) $out['gpt_jobs_latest'][] = $r;
        }
    }

    // 8. Fetched prompts via resolver
    $resolvedSql = ssparc_resolve_all_user_identifiers($db, $sessUserId);
    $out['fetched_prompts'] = ssparc_fetch_all_student_prompts($db, $resolvedSql, null);
    
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Clean debug parameter if present
$queryParams = $_GET;
unset($queryParams['path'], $queryParams['endpoint'], $queryParams['debug'], $queryParams['action']);
$queryString = http_build_query($queryParams);

$targetUrl = rtrim($backendBaseUrl, '/') . $path . ($queryString ? ('?' . $queryString) : '');

// Initialize cURL
$ch = curl_init($targetUrl);

$method = $_SERVER['REQUEST_METHOD'];
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

// Ignore SSL errors if internal certificate
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

// Forward Headers
$headers = [];
$incomingHeaders = function_exists('getallheaders') ? getallheaders() : [];
$hasUserId = false;

// Also check $_SERVER for headers if getallheaders is missing or FastCGI
if (empty($incomingHeaders)) {
    foreach ($_SERVER as $key => $val) {
        if (strpos($key, 'HTTP_') === 0) {
            $hName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $incomingHeaders[$hName] = $val;
        }
    }
}

foreach ($incomingHeaders as $name => $value) {
    $lower = strtolower($name);
    if (in_array($lower, ['authorization', 'content-type', 'accept', 'x-api-key', 'x-user-id', 'x-student-id'])) {
        $headers[] = "$name: $value";
        if ($lower === 'x-user-id') {
            $hasUserId = true;
        }
    }
}

// Fallback: If X-User-ID header is missing from client, pass it from active PHP session or GET param
if (!$hasUserId) {
    if (!empty($_GET['user_id'])) {
        $headers[] = "X-User-ID: " . $_GET['user_id'];
    } elseif (!empty($_SESSION['user_id'])) {
        $headers[] = "X-User-ID: " . $_SESSION['user_id'];
    }
}

if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

// Forward Request Body for POST/PUT/PATCH/DELETE
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    $body = file_get_contents('php://input');
    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
}

// Execute cURL request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Failed to connect to backend AI server: ' . $curlError,
        'target' => $targetUrl
    ]);
    exit;
}

http_response_code($httpCode ?: 200);
if ($contentType) {
    header("Content-Type: $contentType");
} else {
    header("Content-Type: application/json");
}

echo $response;
