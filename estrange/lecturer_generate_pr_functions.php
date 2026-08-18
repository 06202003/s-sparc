<?php
// 	include("_sessionchecker.php");
// 	include("_config.php");
	
function generate_peer_review_assessments($db) {

    // Cari assessment yang siap diproses
    $sql_find_pending = "SELECT 
                            pra.pr_assessment_id, 
                            pra.assessment_id, 
                            pra.reviews_per_submission,
                            a.course_id 
                        FROM 
                            peer_review_assessment pra
                        JOIN 
                            assessment a ON pra.assessment_id = a.assessment_id
                        WHERE 
                            pra.peer_review_start_time <= NOW()
                        AND 
                            pra.is_pr_assessment_generated = 0";

    $result_pending = $db->query($sql_find_pending);
    if ($result_pending === false) {
        return false;
    }

    if ($result_pending->num_rows === 0) {
        return true; // tidak ada yang perlu dikerjakan
    }

    // Loop setiap assessment
    while ($assessment = $result_pending->fetch_assoc()) {

        $pr_assessment_id = $assessment['pr_assessment_id'];
        $assessment_id = $assessment['assessment_id'];
        $reviews_per_submission = $assessment['reviews_per_submission'];
        $course_id = $assessment['course_id'];

        // mulai transaksi
        $db->begin_transaction();

        try {
            // ambil semua student di course
            $sql_students = "SELECT student_id FROM enrollment WHERE course_id = ?";
            $stmt_students = $db->prepare($sql_students);
            $stmt_students->bind_param("i", $course_id);
            $stmt_students->execute();
            $all_students = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_students->close();

            // ambil submission terakhir tiap student
            $sql_submissions = "SELECT
                                    s.submission_id,
                                    s.submitter_id
                                FROM
                                    submission s
                                WHERE
                                    s.assessment_id = ?
                                AND s.attempt = (
                                    SELECT MAX(s2.attempt)
                                    FROM submission s2
                                    WHERE s2.submitter_id = s.submitter_id
                                    AND s2.assessment_id = s.assessment_id
                                )";

            $stmt_submissions = $db->prepare($sql_submissions);
            $stmt_submissions->bind_param("i", $assessment_id);
            $stmt_submissions->execute();
            $all_submissions = $stmt_submissions->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_submissions->close();

            $submission_count = count($all_submissions);

            // tidak ada submission
            if ($submission_count == 0) {
                $sql_mark = "UPDATE peer_review_assessment SET is_pr_assessment_generated = 1 WHERE pr_assessment_id = ?";
                $stmt = $db->prepare($sql_mark);
                $stmt->bind_param("i", $pr_assessment_id);
                $stmt->execute();
                $stmt->close();
                $db->commit();
                continue;
            }

            // submission kurang
            if ($submission_count <= $reviews_per_submission) {
                $sql_mark = "UPDATE peer_review_assessment SET is_pr_assessment_generated = 1 WHERE pr_assessment_id = ?";
                $stmt = $db->prepare($sql_mark);
                $stmt->bind_param("i", $pr_assessment_id);
                $stmt->execute();
                $stmt->close();
                $db->commit();
                continue;
            }

            // Insert assignments
            $sql_assign = "INSERT INTO peer_review_submission 
                           (pr_assessment_id, submission_to_review, reviewer_id, review_status) 
                           VALUES (?, ?, ?, 0)";

            $stmt_assign = $db->prepare($sql_assign);

            shuffle($all_submissions);

            $S = $submission_count;
            $N = $reviews_per_submission;

            for ($i = 0; $i < $S; $i++) {
                $reviewer_id = $all_submissions[$i]['submitter_id'];

                for ($j = 1; $j <= $N; $j++) {
                    $idx = ($i + $j) % $S;
                    $submission_id_to_review = $all_submissions[$idx]['submission_id'];

                    $stmt_assign->bind_param("iii", 
                        $pr_assessment_id, 
                        $submission_id_to_review, 
                        $reviewer_id
                    );

                    $stmt_assign->execute();
                    if ($stmt_assign->affected_rows !== 1) {
                        throw new Exception("failed insert");
                    }
                }
            }

            $stmt_assign->close();

            // tandai selesai
            $sql_mark_done = "UPDATE peer_review_assessment SET is_pr_assessment_generated = 1 WHERE pr_assessment_id = ?";
            $stmt_mark = $db->prepare($sql_mark_done);
            $stmt_mark->bind_param("i", $pr_assessment_id);
            $stmt_mark->execute();
            $stmt_mark->close();

            $db->commit();

        } catch (Exception $e) {
            $db->rollback();
            // skip & teruskan ke assessment berikutnya
            continue;
        }
    }

    return true;
}

