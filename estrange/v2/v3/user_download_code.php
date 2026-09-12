<?php
// this page downloads a submission.

include("_sessionchecker.php");

if($_SERVER["REQUEST_METHOD"] == "POST") {
  // proceed only if it is landed from post form

  include("_config.php");

  // get submission id
  $id = mysqli_real_escape_string($db,$_POST['id']);

  // get filename and filepath
  $sqlt = "SELECT filename, file_path FROM submission
    WHERE submission_id = '".$id."'";
  $resultt = mysqli_query($db,$sqlt);
  $rowt = $resultt->fetch_assoc();

  // copied and adapted from https://www.php.net/manual/en/function.readfile.php
  $file = $rowt['file_path'];
  $filename = $rowt['filename'];
  if (file_exists($file)) {
      // set the metadata
      header('Content-Description: File Transfer');
      header('Content-Type: application/octet-stream');
      header('Content-Disposition: attachment; filename="'.$filename.'"');
      header('Expires: 0');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      header('Content-Length: ' . filesize($file));
      readfile($file);
      exit;
  }
  
}else{
  // redirect if accessed directly
  if ($_SESSION['role'] == 'admin'){
    header('Location: admin_dashboard.php');
  } else if ($_SESSION['role'] == 'lecturer'){
    header('Location: lecturer_dashboard.php');
  } else if ($_SESSION['role'] == 'student'){
    header('Location: student_dashboard.php');
  }
  exit;
}



?>
