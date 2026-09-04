<?php
include("_sessionchecker_peer.php");
include("_config_peer_review.php");
include("_header_peer_review.php");

// copied and modified from https://www.tutorialspoint.com/php/php_mysql_login.htm
if($_SERVER["REQUEST_METHOD"] == "POST") {
	// start to enroll the students based on the text given in the form
	if(isset($_POST['students']) == true && $_POST['students'] != ""){
		 // data sent from form
		 $mystudents= mysqli_real_escape_string($db,$_POST['students']);
		 $mycourseid = $_SESSION['course_id'];

		 // to store the student IDs
		 $studentIDs = array();

		 // to store the error message
		 $errorMessage = "";

		 // split based on newline
		 $arr = explode ("\\n",$mystudents);
		 $arrLength = count($arr);
		 for($i=0;$i<$arrLength;$i++){
			 // get the username without escape characters
			 $arr[$i] = str_replace("\\r", "", $arr[$i]);

			 // if empty, skip the line but show no error message
			 if($arr[$i] == ""){
				 continue;
			 }

			 // checking the validity of username
		     $sql = "SELECT user_id, role FROM user
			 				 WHERE username = '".$arr[$i]."'";
		     $result = mysqli_query($db,$sql);
		     $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
		     $count = mysqli_num_rows($result);
		     if($count == 0) {
		        // if the username does not exist
		        $errorMessage .= "Line ".($i+1).": The username does not exist. <br />";
		     }else if($row['role'] != 'student'){
				// if not student
				$errorMessage .= "Line ".($i+1).": The username is not registered as a student. <br />";
			 }else{
				 // check whether the account is associated with co-lecturers for this course
				 $sql = "SELECT user_id FROM colecturer
			 				 WHERE user_id = '".$row['user_id']."'
							 AND course_id = '".$mycourseid."' ";
				 $result = mysqli_query($db,$sql);
				 $count = mysqli_num_rows($result);
				 if($count > 0){
					 // has been assigned to co-lecturer
					 $errorMessage .= "Line ".($i+1).": The username has been registered as a co-lecturer for this course. <br />";
				 }else{
					// get the id to an array
					$studentIDs[] = $row['user_id'];
				 }
			 }
		 }

		 // if no error message
		 if($errorMessage == ""){
			  $IDLength = count($studentIDs);
				$mycourseid = $_SESSION['course_id'];
				// for each student id given in the text
				for($i=0;$i<$IDLength;$i++){
				   // check whether the account has been enrolled for given course
				   $sql = "SELECT student_id FROM enrollment
						 WHERE student_id = '".$studentIDs[$i] ."' AND course_id = '".$mycourseid."'";
		 		   $result = mysqli_query($db,$sql);
		 		   $count = mysqli_num_rows($result);
					 // if it has been enrolled, skip the process
					 if($count > 0)
					 	continue;

						// add the entry
						$sql = "INSERT INTO enrollment (student_id, course_id)
					 			VALUES ('".$studentIDs[$i]."', '".$mycourseid."')";

						// if error, print the message and exit
						if ($db->query($sql) != TRUE) {							
							echo "Error adding record: " . $db->error;
							exit;
						}else{
						    // check whether such game_student_course exists
						    $sqlt = "SELECT student_id FROM game_student_course
        						 WHERE student_id = '".$studentIDs[$i] ."' AND course_id = '".$mycourseid."'";
        		 		   $resultt = mysqli_query($db,$sqlt);
        		 		   $count = mysqli_num_rows($resultt);
						    if($count == 0){
    							// add entry for game_student_course if it has no such id
    							$sql = "INSERT INTO game_student_course (student_id, course_id)
    					 			VALUES ('".$studentIDs[$i]."', '".$mycourseid."')";
    
    							// if error, print the message and exit
    							if ($db->query($sql) != TRUE) {						
    								echo "Error adding record: " . $db->error;
    								exit;
    							}
						    }
						}
				}
				// if updated well, redirect to dashboard
				header('Location: lecturer_students_peer.php');
				exit;
		 }
	 }
}
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
		<title> E-STRANGE: Enroll student(s)</title>
    <link rel="icon" href="../strange_html_layout_additional_files/icon.png">
	<!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	
	<link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">

	 <style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300&display=swap');
    body {
      /* font-family: "Times New Roman", Times, serif; */
      font-family: 'Montserrat', sans-serif;
    }
	.btn-primary{
			background: #00A0A5 !important ;
			color: white !important ;
		}
	.btn-danger{
			background: #f56976 !important ;
		}
	.form-control {
			border: 2px solid #000;	
			border-radius: 8px;
		}
	</style>
    <script>
    </script>
   
    <style>
/* Premium Teal Dropdown Styling for E-STRANGE & S-SPARC */
/* Ensure SweetAlert2 hidden select is never displayed */
.swal2-container select,
.swal2-popup select,
.swal2-select {
  display: none !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select), .form-select, .custom-select {
  appearance: none !important;
  -webkit-appearance: none !important;
  -moz-appearance: none !important;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2300A0A5' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
  background-repeat: no-repeat !important;
  background-position: right 0.85rem center !important;
  background-size: 1.15rem 1.15rem !important;
  padding-left: 1rem !important;
  padding-right: 2.5rem !important;
  padding-top: 0.5rem !important;
  padding-bottom: 0.5rem !important;
  min-width: 130px !important;
  min-height: 40px !important;
  border-radius: 0.75rem !important;
  border: 1.5px solid #cbd5e1 !important;
  background-color: #ffffff !important;
  color: #0f172a !important;
  font-weight: 600 !important;
  font-size: 0.875rem !important;
  line-height: 1.25rem !important;
  transition: all 0.2s ease-in-out !important;
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
  cursor: pointer !important;
  flex-shrink: 0 !important;
  display: inline-block !important;
  box-sizing: border-box !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select):hover, .form-select:hover {
  border-color: #00A0A5 !important;
  background-color: #f8fafc !important;
  box-shadow: 0 4px 12px rgba(0, 160, 165, 0.08) !important;
}

select:not(.select2-hidden-accessible):not(.swal2-select):focus, .form-select:focus {
  outline: none !important;
  border-color: #00A0A5 !important;
  box-shadow: 0 0 0 3px rgba(0, 160, 165, 0.2) !important;
  background-color: #ffffff !important;
}

/* Ensure Select2 Native Input Remains Completely Hidden */
select.select2-hidden-accessible {
  display: none !important;
  width: 0 !important;
  height: 0 !important;
  padding: 0 !important;
  margin: 0 !important;
  border: 0 !important;
  opacity: 0 !important;
  position: absolute !important;
  pointer-events: none !important;
}

/* Select2 Plugin Custom Teal Enhancements */
.select2-container--default .select2-selection--single {
  border-radius: 0.75rem !important;
  border: 1.5px solid #cbd5e1 !important;
  height: 42px !important;
  min-width: 140px !important;
  padding: 6px 12px !important;
  font-weight: 600 !important;
  font-size: 0.875rem !important;
  transition: all 0.2s ease-in-out !important;
}

.select2-container--default .select2-selection--single:hover {
  border-color: #00A0A5 !important;
}

.select2-container--default.select2-container--open .select2-selection--single,
.select2-container--default.select2-container--focus .select2-selection--single {
  border-color: #00A0A5 !important;
  box-shadow: 0 0 0 3px rgba(0, 160, 165, 0.2) !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
  background-color: #00A0A5 !important;
  color: #ffffff !important;
}

</style>
</head>
  <body>
		<?php
		  setHeaderLecturer("courses", "Student enrollment");
		?>
		<div class="container mt-4">
			<div class="row d-flex justify-content-center align-items-center" style="min-height:50vh">
				<div class="col-md-6">
					<!-- copied and modified from https://www.w3schools.com/howto/howto_css_login_form.asp -->
					<form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
						<div class="formbody">
							<?php
								// if error message exist, show it
								if(isset($errorMessage) && $errorMessage != ""){
									// show the message
									echo "<div class='alert alert-warning'>Error(s):<br />".$errorMessage."</div>";
								}
							?>
							<div class="form-group mt-3">
								<label for="students"><b>Student usernames:</b></label>
								<textarea rows=10 placeholder="Enter student usernames separated by line"
								class="form-control" name="students"  required><?php if(isset($mystudents) == true){echo str_replace("\\r", "\r", str_replace("\\n", "\n", $mystudents)); } ?></textarea>
							</div>
						</div>
						
						<div class="form-group mt-3">
							<a href="lecturer_students_peer.php" class="btn btn-danger">Cancel</a>
							<button class="btn btn-primary " type="submit">Enroll</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
