<?php
// check whether the page is accessed without session

session_start();

// if logged in, redirect to the dashboard page
if(isset($_SESSION['name']) == true && $_SESSION['sub_domain'] == "mcu"){
  if ($_SESSION['role'] == 'admin'){
    header('Location: admin_dashboard.php');
	exit;
  } else if ($_SESSION['role'] == 'lecturer'){
    header('Location: lecturer_dashboard.php');
	exit;
  } else if ($_SESSION['role'] == 'student'){
    header('Location: student_dashboard.php');
	exit;
  }
}
?>
