<?php
// this php is focused on donwloading all submission metadata for a particular assessment

include("_sessionchecker.php");
include("_config.php");

// redirect if the sessions are not set
if(isset($_POST['assessment_id']) == false){
  header('Location: admin_dashboard.php');
  exit;
}

// get assessment id and name
$id = mysqli_real_escape_string($db,$_POST['assessment_id']);
$name = mysqli_real_escape_string($db,$_POST['course_name'])."-".mysqli_real_escape_string($db,$_POST['assessment_name']);;

// get all the submissions
$sqlt = "SELECT submission.submission_time, submission.description,
  submission.attempt, user.username, user.name, assessment.submission_close_time FROM submission
  INNER JOIN user ON submission.submitter_id = user.user_id
  INNER JOIN suspicion ON submission.submission_id = suspicion.submission_id
  INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id
  WHERE submission.assessment_id = '".$id."'";
$resultt = mysqli_query($db,$sqlt);

// target filename
$filename = preg_replace('/\s/', '_',$name).".csv";

// below code is copied and modified from https://stackoverflow.com/questions/16251625/how-to-create-and-download-a-csv-file-from-php-script

$delimiter=",";
// open raw memory as file so no temp files needed, you might run out of memory though
$f = fopen('php://memory', 'w'); 

// header
$csvrow = array('Username', 'Name', 'Attempt','Submission time', 'Late submission?','Description', 'Student response if suspected');
// generate csv rows from the inner arrays
fputcsv($f, $csvrow, $delimiter); 

while($row  = $resultt->fetch_assoc()){
	$isLate = "";
	if($row['submission_time'] > $row['submission_close_time'])
		$isLate = "LATE";
	$csvrow = array($row['username'], $row['name'], $row['attempt'],$row['submission_time'], $isLate, $row['description'], $row['student_response']);
	// generate csv rows from the inner arrays
	fputcsv($f, $csvrow, $delimiter); 
}
// reset the file pointer to the start of the file
fseek($f, 0);
// tell the browser it's going to be a csv file
header('Content-Type: application/csv');
// tell the browser we want to save it instead of displaying it
header('Content-Disposition: attachment; filename="'.$filename.'";');
// make php send the generated csv lines to the browser
fpassthru($f);


?>
