<?php
include("_sessionchecker_peer.php"); //"../_sessionchecker.php"
include("_config_peer_review.php");

// Memastikan course_id dikirim
if (!isset($_GET['course_id'])) {
    echo json_encode([]);
    exit();
}

$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);

// Mengambil semua assessment dari course yang dipilih
// Assessment yang tidak memperbolehkan pengumpulan terlambat dan belum memiliki tugas peer review
$sql = "SELECT 
            assessment_id, 
            name,
            submission_close_time 
        FROM 
            assessment 
        WHERE 
            course_id = ? 
        AND 
            allow_late_submission = 0
        AND
            assessment_id NOT IN (
                SELECT assessment_id FROM peer_review_assessment WHERE assessment_id IS NOT NULL
            )";

$stmt = $db->prepare($sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
$assessments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mengembalikan hasilnya dalam format JSON
header('Content-Type: application/json');
echo json_encode($assessments);
?>