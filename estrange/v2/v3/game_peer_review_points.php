<?php
// 	include("_sessionchecker.php");
// 	include("_config.php");
	
function generate_peer_review_game_points($db, $options = [])
{
    $pr_assessment_id = $options['pr_assessment_id'] ?? null;
    $cache_ttl        = $options['cache_ttl'] ?? 3600;
    $use_cache        = array_key_exists('use_cache', $options) ? (bool)$options['use_cache'] : true;

    // Cache key
    $cache_key = $pr_assessment_id
        ? "peer_review_game_points_{$pr_assessment_id}"
        : "peer_review_game_points_all";

    // File cache path
    $file_cache_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cache_key . '.cache';

    // Try load cache first
    if ($use_cache && file_exists($file_cache_path)) {
        $meta = @filemtime($file_cache_path);
        if ($meta !== false && (time() - $meta) < $cache_ttl) {
            $data = @file_get_contents($file_cache_path);
            if ($data !== false) {
                $unser = @unserialize($data);
                if ($unser !== false) {
                    return $unser;
                }
            }
        }
    }

    $results = [];

    // 1) GET ASSESSMENTS (TANPA FILTER CLOSE TIME)
    $sql_find = "
        SELECT pra.pr_assessment_id, a.course_id, pra.peer_review_close_time
        FROM peer_review_assessment pra
        JOIN assessment a ON pra.assessment_id = a.assessment_id
        WHERE pra.pr_assessment_id = ?
    ";

    $stmt_find = $db->prepare($sql_find);
    if ($stmt_find === false) {
        return $results;
    }

    $stmt_find->bind_param("i", $pr_assessment_id);
    $stmt_find->execute();
    $pending_rs = $stmt_find->get_result();
    $stmt_find->close();

    if ($pending_rs === false) {
        return $results;
    }

    // 2) LOOP ASSESSMENT
    while ($assessment = $pending_rs->fetch_assoc()) {

        $pr_id      = (int)$assessment['pr_assessment_id'];
        $course_id  = (int)$assessment['course_id'];
        $close_time = $assessment['peer_review_close_time'];
        $is_closed  = strtotime($close_time) <= time();

        // 2.1 Get all students in course
        $sql_students = "SELECT student_id FROM enrollment WHERE course_id = ?";
        $stmt = $db->prepare($sql_students);
        if ($stmt === false) {
            continue;
        }

        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $rs = $stmt->get_result();

        $all_students = [];
        while ($r = $rs->fetch_assoc()) {
            $all_students[] = (int)$r['student_id'];
        }
        $stmt->close();

        if (empty($all_students)) {
            $results[$pr_id] = [];
            continue;
        }

        // 2.2 Review stats (assigned vs completed)
        $sql_review_stats = "
            SELECT
                reviewer_id AS student_id,
                COUNT(pr_submission_id) AS tasks_assigned,
                SUM(CASE WHEN review_status = 1 THEN 1 ELSE 0 END) AS tasks_completed
            FROM peer_review_submission
            WHERE pr_assessment_id = ?
            GROUP BY reviewer_id
        ";

        $stmt = $db->prepare($sql_review_stats);
        if ($stmt === false) {
            continue;
        }

        $stmt->bind_param("i", $pr_id);
        $stmt->execute();
        $rs = $stmt->get_result();

        $review_stats = [];
        while ($r = $rs->fetch_assoc()) {
            $sid = (int)$r['student_id'];
            $review_stats[$sid] = [
                'pr_assign'    => (int)$r['tasks_assigned'],
                'pr_completed' => (int)$r['tasks_completed'],
            ];
        }
        $stmt->close();

        // 2.3 Average score received by submitter
        $sql_received_stats = "
            SELECT
                s.submitter_id AS student_id,
                AVG(pr.review_score) AS avg_score_received
            FROM peer_review pr
            JOIN peer_review_submission prs ON pr.pr_submission_id = prs.pr_submission_id
            JOIN submission s ON prs.submission_to_review = s.submission_id
            WHERE prs.pr_assessment_id = ?
            GROUP BY s.submitter_id
        ";

        $stmt = $db->prepare($sql_received_stats);
        if ($stmt === false) {
            continue;
        }

        $stmt->bind_param("i", $pr_id);
        $stmt->execute();
        $rs = $stmt->get_result();

        $received_stats = [];
        while ($r = $rs->fetch_assoc()) {
            $sid = (int)$r['student_id'];
            // penting: biarkan NULL jika tidak ada score
            $received_stats[$sid] = $r['avg_score_received'] !== null
                ? (float)$r['avg_score_received']
                : null;
        }
        $stmt->close();

        // 2.4 Final Calculation
        $results[$pr_id] = [];

        foreach ($all_students as $student_id) {

            $pr_assign    = $review_stats[$student_id]['pr_assign']    ?? 0;
            $pr_completed = $review_stats[$student_id]['pr_completed'] ?? 0;

            // raw score (bisa NULL)
            $avg_score_raw = $received_stats[$student_id] ?? null;

            // default score rule
            if ($avg_score_raw === null) {

                if ($is_closed) {

                    if ($pr_completed == 0) {
                        // Tidak mengerjakan review + Tidak ada reviewer menilai
                        $avg_score_final = 0;
                    } else {
                        // Sudah mengerjakan review tapi tidak dapat review
                        $avg_score_final = 100;
                    }

                } else {
                    // Belum close → tetap 0
                    $avg_score_final = 0;
                }

            } else {
                // Sudah ada skor reviewer
                $avg_score_final = $avg_score_raw;
            }

            // completion %
            $completion_percent = ($pr_assign > 0)
                ? ($pr_completed / $pr_assign)
                : 0;

            // extra points
            $extra_points = $completion_percent * 100;

            // final score -> mengerjakan tugas peer review
            $final_score = $avg_score_final + $extra_points;

            // final score -> tidak mengerjakan tugas peer review sama sekali
            if ($pr_completed == 0) {
                $extra_points = 0;
                $final_score = 0;
                $completion_percent = 0; 
            }

            // save
            $results[$pr_id][$student_id] = [
                'student_id'          => $student_id,
                'pr_assessment_id'    => $pr_id,
                'avg_score'           => round($avg_score_final),
                'pr_assign'           => $pr_assign,
                'pr_completed'        => $pr_completed,
                'completion_percent'  => round($completion_percent, 4),
                'extra_points'        => round($extra_points),
                'final_score'         => round($final_score),
            ];
        }
    }

    // save cache
    if ($use_cache) {
        @file_put_contents($file_cache_path, serialize($results), LOCK_EX);
    }

    return $results;
}

?>