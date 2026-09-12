<?php
// this php is focused on donwloading all code files for a particular assessment
// all files will be merged to one zip file

include("_sessionchecker.php");
include("_config.php");

// redirect if the sessions are not set
if(isset($_SESSION['assessment_id']) == false){
  header('Location: lecturer_dashboard.php');
  exit;
}

// get assessment id and name
$id = mysqli_real_escape_string($db,$_SESSION['assessment_id']);
$name = "all_".mysqli_real_escape_string($db,$_SESSION['assessment_name']);

// get all the submissions
$sqlt = "SELECT submission.filename, submission.file_path,
  submission.attempt, user.username, user.name FROM submission
  INNER JOIN user ON submission.submitter_id = user.user_id
  WHERE submission.assessment_id = '".$id."'";
$resultt = mysqli_query($db,$sqlt);

// target filename
$filename = preg_replace('/\s/', '_',$name).".zip";

$zip = new ZipArchive;
if ($zip->open($filename,  ZipArchive::CREATE)){
    $isEmpty = true;
    // merge each submission to the zip
    while($row  =   $resultt->fetch_assoc()){
        $zip->addFile(getcwd().'/'.$row['file_path'], $row['username'] ."_". $row['name'] ."/" . $row['attempt'] . "/". $row['filename']);
        $isEmpty = false;
    }
    
    if($isEmpty){
        // add a text file
        $zip->addFromString('readme.txt', 'No submissions in this assessment');
    }
    
    $zip->close();

    // metadata set
    header("Content-type: application/zip");
    header("Content-Disposition: attachment; filename=$filename");
    header("Content-length: " . filesize($filename));
    header("Pragma: no-cache");
    header("Expires: 0");
    readfile("$filename");
    unlink($filename);

    // return
    exit;
}

?>
