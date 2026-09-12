<?php
// Only take the latest submission per student

include("_sessionchecker.php");
include("_config.php");

// redirect if the sessions are not set
if(isset($_SESSION['assessment_id']) == false){
  header('Location: lecturer_dashboard.php');
  exit;
}

// get assessment id and name
$id = mysqli_real_escape_string($db,$_SESSION['assessment_id']);
$name = "last_".mysqli_real_escape_string($db,$_SESSION['assessment_name']);


// get the list of students submitted to this assessment with the latest attempt
$sqlt = "SELECT MAX(attempt) AS max_attempt, submitter_id
  FROM submission
  WHERE assessment_id = '".$id."'
  GROUP BY submitter_id";
$resultt = mysqli_query($db,$sqlt);
// create the initial list
$lastAttemptMap = []; // index is the student id while the value is the max attempt
// add only the last attempt ones
while($row  =   $resultt->fetch_assoc()){
  $lastAttemptMap[$row['submitter_id']] = $row['max_attempt'];
}

// get the download-to-be files
$sqlt = "SELECT submission.filename, submission.file_path,
  submission.attempt, user.username, user.name, submission.submitter_id FROM submission
  INNER JOIN user ON submission.submitter_id = user.user_id
  WHERE submission.assessment_id = '".$id."'";
$resultt = mysqli_query($db,$sqlt);

// set the targeted zip name
$filename = preg_replace('/\s/', '_',$name).".zip";
$zip = new ZipArchive;

if ($zip->open($filename,  ZipArchive::CREATE)){
    $isEmpty = true;
    while($row  =   $resultt->fetch_assoc()){
      // add only if the last attempt
      if($row['attempt'] == $lastAttemptMap[$row['submitter_id']]){
        $zip->addFile(getcwd().'/'.$row['file_path'], $row['username'] ."_". $row['name'] ."/" . $row['filename']);
        $isEmpty = false;
      }
    }
    if($isEmpty){
        // add a text file
        $zip->addFromString('readme.txt', 'No submissions in this assessment');
    }
    
    $zip->close();

    // metadata
    header("Content-type: application/zip");
    header("Content-Disposition: attachment; filename=$filename");
    header("Content-length: " . filesize($filename));
    header("Pragma: no-cache");
    header("Expires: 0");
    readfile("$filename");
    unlink($filename);
    exit;
}

?>
