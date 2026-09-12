<?php
// check whether the user has been logged in

// start the session if it has not been started
if (session_id() == "")
  session_start();

// redirect if it is not logged in
if(isset($_SESSION['name']) == false || $_SESSION['sub_domain'] != "mcu"){
  header('Location: index.php');
  exit;
}else{
  // check whether the role is similar to the opened pages

  // get the page role
  $pagerole = htmlentities($_SERVER['PHP_SELF']);
  $pagerole = substr($pagerole, strrpos($pagerole,'/')+1);
  $pagerole = substr($pagerole, 0, strpos($pagerole,'_'));
  
  // if the pages are for colecturer
  if($pagerole == 'colecturer')
	  if($_SESSION['role'] == 'lecturer' || $_SESSION['role'] == 'student')
		$pagerole = $_SESSION['role'];

  // check whether the page is user specific
  if($pagerole != 'user'){
    // if it is in different role
    if($pagerole != $_SESSION['role']){		
      // redirect to its dashboard
      if ($_SESSION['role'] == 'admin'){
        header('Location: admin_dashboard.php');
      } else if ($_SESSION['role'] == 'lecturer'){
        header('Location: lecturer_dashboard.php');
      } else if ($_SESSION['role'] == 'student'){
        header('Location: student_dashboard.php');
      }
      exit;
    }
  }
}

// for logout in any pages
if($_SERVER["REQUEST_METHOD"] == "POST") {
  if(isset($_POST['logout']) == true){
    // remove all session variables
    session_unset();

    // destroy the session
    session_destroy();

    // redirect to home
    header('Location: index.php');
    exit;
  }
}
?>
