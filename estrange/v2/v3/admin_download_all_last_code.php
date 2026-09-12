<?php
// Only take the latest submission per student

include("_sessionchecker.php");
include("_config.php");

// redirect if assessment_id not set
if(!isset($_POST['assessment_id'])){
    header('Location: admin_dashboard.php');
    exit;
}

// sanitize inputs
$id = mysqli_real_escape_string($db,$_POST['assessment_id']);
$assessment_name = isset($_POST['assessment_name']) ? mysqli_real_escape_string($db,$_POST['assessment_name']) : 'UnknownAssessment';
$name = "last_" . $assessment_name;

// get last attempt per student
$sqlt = "SELECT MAX(attempt) AS max_attempt, submitter_id
         FROM submission
         WHERE assessment_id = '$id'
         GROUP BY submitter_id";
$resultt = mysqli_query($db,$sqlt);
$lastAttemptMap = [];
while($row = $resultt->fetch_assoc()){
    $lastAttemptMap[$row['submitter_id']] = $row['max_attempt'];
}

// get submission files
$sqlt = "SELECT submission.filename, submission.file_path, submission.attempt, user.username, user.name, submission.submitter_id
         FROM submission
         INNER JOIN user ON submission.submitter_id = user.user_id
         WHERE submission.assessment_id = '$id'";
$resultt = mysqli_query($db,$sqlt);

// create writable temp folder for ZIPs
$tempFolder = __DIR__ . '/tmp_zips/';
if(!is_dir($tempFolder)) mkdir($tempFolder, 0755, true);

// zip file path
$zipFile = $tempFolder . preg_replace('/[^A-Za-z0-9_-]/', '_', $name) . ".zip";

$zip = new ZipArchive;
if($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE){
    $isEmpty = true;
    while($row = $resultt->fetch_assoc()){
        // only last attempt
        if($row['attempt'] == $lastAttemptMap[$row['submitter_id']]){
            $file_path = __DIR__ . '/' . $row['file_path']; // absolute path
            if(file_exists($file_path)){
                $zip_path = $row['username'] . "_" . $row['name'] . "/" . $row['filename'];
                $zip->addFile($file_path, $zip_path);
                $isEmpty = false;
            }
        }
    }

    if($isEmpty){
        $zip->addFromString('readme.txt', 'No submissions in this assessment');
    }

    $zip->close();

    // output to browser
    header("Content-type: application/zip");
    header("Content-Disposition: attachment; filename=" . basename($zipFile));
    header("Content-length: " . filesize($zipFile));
    header("Pragma: no-cache");
    header("Expires: 0");
    readfile($zipFile);

    // clean up
    unlink($zipFile);
    exit;
} else {
    die("Failed to create ZIP file. Check folder permissions.");
}
?>




