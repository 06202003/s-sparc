<?php
include("_sessionchecker.php");
include("_config.php");

ob_end_clean();
ob_start();
error_reporting(E_ERROR | E_PARSE);

if (!isset($_SESSION['assessment_id'])) {
    exit("Assessment tidak ada");
}

$id = mysqli_real_escape_string($db, $_SESSION['assessment_id']);
$name = mysqli_real_escape_string($db,$_SESSION['assessment_name']);
$zipnamee = "last_".preg_replace('/[^A-Za-z0-9_-]/', '_', $name) . ".zip";

// Get max attempt per students
$sql = "SELECT submitter_id, MAX(attempt) AS max_attempt
        FROM submission
        WHERE assessment_id = '$id'
        GROUP BY submitter_id";
$res = mysqli_query($db, $sql);
$lastAttemptMap = [];
while ($row = $res->fetch_assoc()) {
    $lastAttemptMap[$row['submitter_id']] = $row['max_attempt'];
}

// Obtain all submissions from this assessment
$sql = "SELECT filename, file_path, attempt, submitter_id
        FROM submission
        WHERE assessment_id = '$id'";
$res = mysqli_query($db, $sql);

if (!$res || mysqli_num_rows($res) == 0) {
    exit("No Files for this assessment");
}

// ZIP temporary
$zip_path = __DIR__ . "/last_attempt_$id.zip";
if (file_exists($zip_path)) unlink($zip_path);

$zip = new ZipArchive;
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    exit("Cannot create ZIP");
}

$isEmpty = true;

while ($row = $res->fetch_assoc()) {
    // Obtain all last submitted programs
    if (isset($lastAttemptMap[$row['submitter_id']]) && $row['attempt'] == $lastAttemptMap[$row['submitter_id']]) {
        $stored_path = __DIR__ . "/" . $row['file_path'];
        if (file_exists($stored_path)) {
            $zip->addFile($stored_path, $row['filename']);
            $isEmpty = false;
        }
    }
}

// If empty, add readme
if ($isEmpty) {
    $zip->addFromString("readme.txt", "No submissions in this assessment.");
}

$zip->close();

// Kirim ZIP ke browser
ob_end_clean();
header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=$zipnamee");
header("Content-Length: " . filesize($zip_path));
readfile($zip_path);
unlink($zip_path);
exit;
?>
