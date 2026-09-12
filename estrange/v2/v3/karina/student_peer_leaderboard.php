<?php
    include("_sessionchecker_peer.php");
    include("_config_peer_review.php");

function generate_student_leaderboard_points($db, $course_id)
{
    $leaderboard = [];

    // Ambil PR yang sudah closed
    $sql = "
        SELECT pra.pr_assessment_id, pra.peer_review_close_time
        FROM peer_review_assessment pra
        JOIN assessment a ON pra.assessment_id = a.assessment_id
        WHERE a.course_id = ?
        AND pra.peer_review_close_time <= NOW()
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $rs = $stmt->get_result();

    $pr_list = [];
    while ($row = $rs->fetch_assoc()) {
        $pr_list[] = [
            'pr_id' => (int)$row['pr_assessment_id'],
            'close_time' => $row['peer_review_close_time']
        ];
    }
    $stmt->close();

    if (empty($pr_list)) {
        return [];
    }

    // Ambil seluruh student
    $sql_students = "
        SELECT e.student_id, u.username, u.name
        FROM enrollment e
        JOIN user u ON e.student_id = u.user_id
        WHERE e.course_id = ?
    ";
    $stmt = $db->prepare($sql_students);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $rs = $stmt->get_result();

    $students = [];
    $student_totals = [];
    while ($r = $rs->fetch_assoc()) {
        $sid = (int)$r['student_id'];
        $students[$sid] = [
            'username' => $r['username'],
            'name' => $r['name']
        ];
        $student_totals[$sid] = 0;
    }
    $stmt->close();


    // DETAIL PER ASSESSMENT
    $assessment_detail = [];

    foreach ($pr_list as $pr_info) {

        $pr_id = $pr_info['pr_id'];
        $is_closed = true; // sudah difilter hanya yang closed

        // Review Stats
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
        $stmt->bind_param("i", $pr_id);
        $stmt->execute();
        $rs = $stmt->get_result();

        $review_stats = [];
        while ($r = $rs->fetch_assoc()) {
            $review_stats[(int)$r['student_id']] = [
                'pr_assign' => (int)$r['tasks_assigned'],
                'pr_completed' => (int)$r['tasks_completed']
            ];
        }
        $stmt->close();

        // Score received
        $sql_received = "
            SELECT
                s.submitter_id AS student_id,
                AVG(pr.review_score) AS avg_score
            FROM peer_review pr
            JOIN peer_review_submission prs ON pr.pr_submission_id = prs.pr_submission_id
            JOIN submission s ON prs.submission_to_review = s.submission_id
            WHERE prs.pr_assessment_id = ?
            GROUP BY s.submitter_id
        ";
        $stmt = $db->prepare($sql_received);
        $stmt->bind_param("i", $pr_id);
        $stmt->execute();
        $rs = $stmt->get_result();

        $received_stats = [];
        while ($r = $rs->fetch_assoc()) {
            $sid = (int)$r['student_id'];
            $received_stats[$sid] =
                $r['avg_score'] !== null ? (float)$r['avg_score'] : null;
        }
        $stmt->close();


        // HITUNG SCORE (RULES SAMA SEPERTI game_peer_review_points)
        foreach ($students as $sid => $info) {

            $pr_assign = $review_stats[$sid]['pr_assign'] ?? 0;
            $pr_completed = $review_stats[$sid]['pr_completed'] ?? 0;

            // RAW score (boleh null)
            $avg_raw = $received_stats[$sid] ?? null;

            // === DEFAULT SCORE RULE ===
            if ($avg_raw === null) {

                if ($is_closed) {

                    if ($pr_completed == 0) {
                        $avg_final = 0;
                    } else {
                        $avg_final = 100;
                    }

                } else {
                    $avg_final = 0;
                }

            } else {
                // sudah ada reviewer score
                $avg_final = $avg_raw;
            }

            // Completion %
            $completion_percent = ($pr_assign > 0)
                ? ($pr_completed / $pr_assign)
                : 0;

            $extra_points = $completion_percent * 100;

            $final_score = $avg_final + $extra_points;

            // RULE KHUSUS: jika tidak mengerjakan PR, final = 0
            if ($pr_completed == 0) {
                $completion_percent = 0;
                $extra_points = 0;
                $final_score = 0;
            }

            // Simpan detail
            $assessment_detail[$sid][$pr_id] = [
                'avg_score' => round($avg_final),
                'extra_points' => round($extra_points),
                'final_score' => round($final_score)
            ];

            // Total leaderboard
            $student_totals[$sid] += round($final_score);
        }
    }

    // Format leaderboard
    foreach ($students as $sid => $info) {
        $leaderboard[] = [
            'student_id' => $sid,
            'username' => $info['username'],
            'name' => $info['name'],
            'total_points' => $student_totals[$sid],
            'assessments' => $assessment_detail[$sid] ?? []
        ];
    }

    // Sorting
    usort($leaderboard, fn($a, $b) => $b['total_points'] <=> $a['total_points']);

    return $leaderboard;
}
?>