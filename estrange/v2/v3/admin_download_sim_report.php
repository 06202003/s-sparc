<?php
// this php is focused on donwloading all code files for a particular assessment
// all files will be merged to one zip file

include("_sessionchecker.php");
include("_config.php");

// redirect if the sessions are not set
if(isset($_POST['assessment_id']) == false){
  header('Location: admin_dashboard.php');
  exit;
}

// get assessment id and name
$id = mysqli_real_escape_string($db,$_POST['assessment_id']);
$name =mysqli_real_escape_string($db,$_POST['course_name'])."-".mysqli_real_escape_string($db,$_POST['assessment_name']);

// get all the submissions
$sqlt = "SELECT similarity_report_path, name FROM assessment WHERE assessment_id = '".$id."' AND similarity_report_path != ''";
$resultt = mysqli_query($db,$sqlt);

if ($resultt->num_rows > 0) {
	$row = $resultt->fetch_assoc();
	// source path
	$path = $baseDomainLink . '/'. $row['similarity_report_path'];
	// target filename
	$file_name = basename($name . ".zip");
	
	// metadata
	header('Content-Type: application/octet-stream');  
    header("Content-Disposition: attachment; filename=".$file_name);
	header("Content-Transfer-Encoding: Binary");   
    readfile($path);
    exit;
}

?>
