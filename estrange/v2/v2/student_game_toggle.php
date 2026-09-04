<?php
// this page toggles student game feature (on to off or vice versa)

include("_sessionchecker.php");

if($_SERVER["REQUEST_METHOD"] == "POST") {
  // proceed only if it is landed from post form

  include("_config.php");

  // get gs_id
  $id = mysqli_real_escape_string($db,$_POST['id']);
  $isParticipating = mysqli_real_escape_string($db,$_POST['is_participating']);
  $actionType = '';
  // reverse the value of isParticipating  and actionType as it is toggle feature
  if($isParticipating == '1'){
	$isParticipating = 0;
	$actionType = 'game_off';
  }else{
    $isParticipating = 1;
	$actionType = 'game_on';
  }
  
  $course_id = mysqli_real_escape_string($db,$_POST['course_id']);
  
  $sql = "UPDATE game_student_course SET is_participating = '".$isParticipating."'
		WHERE gs_id='".$id."'";
  $db->query($sql);
  
  // for access statistics of game page
  $sql = "INSERT INTO game_access (student_id, type) VALUES ('".$_SESSION['user_id']."','".$actionType."')";
  $db->query($sql);
	
  header('Location: student_game.php?id='.$course_id);
  exit;
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
// echo 'data tidak masuk';
}



?>
