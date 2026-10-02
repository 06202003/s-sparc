<?php
/**
 * Direct Live Real-Data Diagnostic & Verification Runner
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/_wrapped_service.php';

$output = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => [
        'connected' => (bool)$db,
        'name' => $dbname ?? 'unknown'
    ],
    'real_data_checks' => []
];

if (!$db) {
    $output['status'] = 'error';
    $output['message'] = 'MySQL database connection failed: ' . mysqli_connect_error();
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Raw Submissions in Course 32 (Assessment 311)
$c32Subs = [];
$qSubs = $db->query("SELECT s.submission_id, s.assessment_id, s.submitter_id, s.attempt, s.submission_time, s.filename, u.username, u.name 
                     FROM submission s 
                     JOIN assessment a ON s.assessment_id = a.assessment_id 
                     JOIN user u ON s.submitter_id = u.user_id 
                     WHERE a.course_id = '32' 
                     ORDER BY s.submission_time ASC");
if ($qSubs) {
    while ($r = $qSubs->fetch_assoc()) {
        $c32Subs[] = $r;
    }
}
$output['real_data_checks']['course_32_submissions_count'] = count($c32Subs);
$output['real_data_checks']['course_32_submissions_sample'] = array_slice($c32Subs, 0, 10);

// 2. Resolved User Identifiers for Yehezkiel (218) and Joshua (123760)
$idSqlYehezkiel = ssparc_resolve_all_user_identifiers($db, '218');
$idSqlJoshua = ssparc_resolve_all_user_identifiers($db, '123760');
$idSqlDominic = ssparc_resolve_all_user_identifiers($db, '123484');

$output['real_data_checks']['resolved_ids'] = [
    'yehezkiel' => $idSqlYehezkiel,
    'joshua' => $idSqlJoshua,
    'dominic' => $idSqlDominic
];

// 3. Fetched Prompts from Database for Yehezkiel (Assessment 311 & All)
$promptsYehezkiel311 = ssparc_fetch_all_student_prompts($db, $idSqlYehezkiel, '311', '218');
$promptsYehezkielAll = ssparc_fetch_all_student_prompts($db, $idSqlYehezkiel, null, '218');

$output['real_data_checks']['yehezkiel_prompts_asmt_311'] = [
    'count' => count($promptsYehezkiel311),
    'prompts' => $promptsYehezkiel311
];
$output['real_data_checks']['yehezkiel_prompts_all'] = [
    'count' => count($promptsYehezkielAll)
];

// 4. Fetched Prompts for Joshua (123760)
$promptsJoshua311 = ssparc_fetch_all_student_prompts($db, $idSqlJoshua, '311', '123760');
$output['real_data_checks']['joshua_prompts_asmt_311'] = [
    'count' => count($promptsJoshua311),
    'prompts' => $promptsJoshua311
];

// 5. Aggregated Profiles
$output['real_data_checks']['profile_yehezkiel'] = ssparc_get_student_aggregated_profile($db, '218');
$output['real_data_checks']['profile_joshua'] = ssparc_get_student_aggregated_profile($db, '123760');
$output['real_data_checks']['profile_dominic_non_ai'] = ssparc_get_student_aggregated_profile($db, '123484');

// 6. 6-Slide Wrapped Story for Yehezkiel
$output['real_data_checks']['wrapped_story_yehezkiel'] = ssparc_get_wrapped_for_assessment($db, '218', 'all');

// 7. Full Cohort Analytics for Course 32
$output['real_data_checks']['cohort_analytics_course_32'] = ssparc_get_cohort_research_analytics($db, '32', null);

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
