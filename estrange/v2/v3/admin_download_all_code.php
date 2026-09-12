<?php
// This PHP script is focused on downloading all code files for a particular assessment
// All files will be merged into one ZIP file

include("_sessionchecker.php");
include("_config.php");

// Redirect if assessment_id is not set
if (!isset($_POST['assessment_id']) || empty($_POST['assessment_id'])) {
    header('Location: admin_dashboard.php?error=missing_assessment_id');
    exit;
}

// Get and sanitize assessment ID and name
$id = mysqli_real_escape_string($db, $_POST['assessment_id']);
$assessment_name = isset($_POST['assessment_name']) ? mysqli_real_escape_string($db, $_POST['assessment_name']) : 'UnknownAssessment';
$name = "all_" . $assessment_name;

// Validate assessment_id exists in the database
$check_assessment = mysqli_query($db, "SELECT * FROM assessment WHERE assessment_id = '$id'");
if (!$check_assessment || mysqli_num_rows($check_assessment) == 0) {
    header('Location: admin_dashboard.php?error=invalid_assessment_id');
    exit;
}

// Get all submissions
$sqlt = "SELECT submission.filename, submission.file_path, submission.attempt, user.username, user.name 
         FROM submission 
         INNER JOIN user ON submission.submitter_id = user.user_id 
         WHERE submission.assessment_id = '$id'";
$resultt = mysqli_query($db, $sqlt);

// Check for query errors
if (!$resultt) {
    error_log("Query failed: " . mysqli_error($db));
    header('Location: admin_dashboard.php?error=query_failed');
    exit;
}

// Create target filename (sanitize spaces and special characters)
$filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $name) . ".zip";
$zip = new ZipArchive;

// Open ZIP file for creation
if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $isEmpty = true;

    // Merge each submission into the ZIP
    while ($row = $resultt->fetch_assoc()) {
        $file_path = getcwd() . '/' . $row['file_path'];
        
        // Validate file existence and accessibility
        if (file_exists($file_path) && is_readable($file_path)) {
            $zip_path = $row['username'] . "_" . $row['name'] . "/" . $row['attempt'] . "/" . $row['filename'];
            $zip->addFile($file_path, $zip_path);
            $isEmpty = false;
            error_log("Added file to ZIP: $file_path");
        } else {
            error_log("File not found or inaccessible: $file_path");
        }
    }

    // If no files were added, include a readme
    if ($isEmpty) {
        $zip->addFromString('readme.txt', 'No submissions found for this assessment.');
        error_log("No submissions found for assessment_id: $id");
    }

    $zip->close();

    // Check if ZIP file was created successfully
    if (!file_exists($filename) || filesize($filename) == 0) {
        error_log("ZIP file creation failed or empty: $filename");
        header('Location: admin_dashboard.php?error=zip_creation_failed');
        exit;
    }

    // Set headers for download
    header("Content-type: application/zip");
    header("Content-Disposition: attachment; filename=$filename");
    header("Content-length: " . filesize($filename));
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Cache-Control: no-store, no-cache, must-revalidate");

    // Output the file and clean up
    readfile($filename);
    unlink($filename);
    exit;
} else {
    error_log("Failed to create ZIP file: $filename");
    header('Location: admin_dashboard.php?error=zip_open_failed');
    exit;
}
?>