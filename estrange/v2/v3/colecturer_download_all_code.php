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
$zipnamee = "all_".preg_replace('/[^A-Za-z0-9_-]/', '_', $name) . ".zip";

// Get the submissions from the database
$q = mysqli_query($db, "SELECT submission.filename, submission.file_path, submission.attempt, user.username, user.name 
         FROM submission 
         INNER JOIN user ON submission.submitter_id = user.user_id 
         WHERE submission.assessment_id = '$id'");
if (!$q || mysqli_num_rows($q) == 0) {
    exit("Tidak ada file untuk assessment ini.");
}

// Prepare temporary zip
$zip_path = __DIR__ . "/download_$id.zip";
if (file_exists($zip_path)) unlink($zip_path);
$filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $name) . ".zip";
$zip = new ZipArchive;
if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
    exit("Cannot create ZIP");
}

// Insert all submission files
while ($row = mysqli_fetch_assoc($q)) {
    $filename = $row['filename'];
    $stored_path = __DIR__ . "/" . $row['file_path'];

    if (file_exists($stored_path)) {
        // add to the zip
        $zip->addFile($stored_path, $filename);
    }
}

$zip->close();

// Sent zip
ob_end_clean();
header("Content-Type: application/zip");
header("Content-Disposition: attachment; filename=$zipnamee");
header("Content-Length: " . filesize($zip_path));
readfile($zip_path);
exit;
