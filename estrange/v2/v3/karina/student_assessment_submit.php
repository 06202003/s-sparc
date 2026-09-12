<?php
	session_start();

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
	// if the assessment id does not exist
	if(isset($_GET['id']) == false || $_GET['id'] == ''){
		header('Location: student_peer_dashboard.php'); //student_dashboard
		exit;
	}

	// part of sessionchecker pasted here due to unique behaviour of this page
	// redirect if it is not logged in
	if(isset($_SESSION['name']) == false){
	  header('Location: student_assessment_submit_without_login.php?id='.$_GET['id']);
	  exit;
	}else{
	  // check whether the role is similar to the opened pages

	  // get the page role
	  $pagerole = htmlentities($_SERVER['PHP_SELF']);
	  $pagerole = substr($pagerole, strrpos($pagerole,'/')+1);
	  $pagerole = substr($pagerole, 0, strpos($pagerole,'_'));

	  // check whether the page is user specific
	  if($pagerole != 'user'){
	    // if it is in different role
	    if($pagerole != $_SESSION['role']){
	      // redirect to its dashboard
	      if ($_SESSION['role'] == 'admin'){
	        header('Location: admin_dashboard.php');
	      } else if ($_SESSION['role'] == 'lecturer'){
	        header('Location: lecturer_peer_dashboard.php'); //lecturer_dashboard.php
	      } else if ($_SESSION['role'] == 'student'){
	        header('Location: student_peer_dashboard.php'); //student_dashboard.php
	      }
	      exit;
	    }
	  }
	}

	//tambahan
	include("_config_peer_review.php");
	include("_header_peer_review.php");

	// get the real assessment_id
	$sql = "SELECT assessment_id FROM assessment
		WHERE public_assessment_id = '".$_GET['id']."'";
	$result = mysqli_query($db,$sql);
	$row = $result->fetch_assoc();
	if(is_null($row)){
		// if no such id exists, redirect to student dashboard
		header('Location: student_peer_dashboard.php'); //student_dashboard.php
		exit;
	}
	// store the public assessment id as variable
	$publicAssessmentId = 	$_GET['id'];
	// set the id with the 'real' one
	$_GET['id'] = $row['assessment_id'];

	$errorMessage = "";

	// check whether the assessment id is listed to a course and the submission is still open
	$sql = "SELECT assessment.name AS assessment_name, course.name AS course_name, assessment.submission_file_extension AS ext, assessment.description as assessment_description 
		 FROM assessment INNER JOIN course ON course.course_id = assessment.course_id
		 WHERE assessment.assessment_id = '".$_GET['id']."'
		 AND (assessment.submission_close_time > CURRENT_TIMESTAMP OR assessment.allow_late_submission = '1')
		 AND assessment.submission_open_time < CURRENT_TIMESTAMP";
	$result = mysqli_query($db,$sql);
	$row = $result->fetch_assoc();

	// if the given assessment id is not listed, redirect to login
	if(is_null($row)){
		header('Location: student_peer_dashboard.php'); //student_dashboard.php
		exit;
	}
	
	// this code block aims to show how many submission attempts have been made
	$myassessmentid = mysqli_real_escape_string($db,$_GET['id']);
	// get the highest attempt
	$sqlt = "SELECT MAX(attempt) as max_att FROM submission
		WHERE submitter_id = '".$_SESSION['user_id']."' AND assessment_id = '".$myassessmentid."'";
	$resultt = mysqli_query($db,$sqlt);
	$rowt = $resultt->fetch_assoc();
	// set the attempt
	if($rowt['max_att'] == ''){
		$rowt['max_att'] = 0;
	}
	$attempt = ((int) $rowt['max_att'] + 1);
	

	// for generating random string
	function random_str(
	    $length,
	    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
	    $str = '';
	    $max = mb_strlen($keyspace, '8bit') - 1;
	    for ($i = 0; $i < $length; ++$i) {
	        $str .= $keyspace[rand(0, $max)];
	    }
	    return $str;
	}

	// file handling copied and modified from https://stackoverflow.com/questions/5593473/how-to-upload-and-parse-a-csv-file-in-php
	if($_SERVER["REQUEST_METHOD"] == "POST") {
		if ( isset($_FILES["code"])) {
			  // if there was an error uploading the file
			  if ($_FILES["code"]["error"] > 0) {
				echo "Return Code: " . $_FILES["ufile"]["error"] . "<br />";
			  }
			  else {
				// get the data from form
				$mydesc = mysqli_real_escape_string($db,$_POST['desc']);
				$myassessmentid = mysqli_real_escape_string($db,$_GET['id']);

				// get the highest attempt
				$sqlt = "SELECT MAX(attempt) as max_att FROM submission
					WHERE submitter_id = '".$_SESSION['user_id']."' AND assessment_id = '".$myassessmentid."'";
				$resultt = mysqli_query($db,$sqlt);
				$rowt = $resultt->fetch_assoc();

				// set the attempt
				if($rowt['max_att'] == ''){
					$rowt['max_att'] = 0;
				}
				$attempt = ((int) $rowt['max_att'] + 1);

				// get the metadata of the uploaded code
				$file_name = $_FILES['code']['name'];
				$file_size =$_FILES['code']['size'];
				$file_tmp =$_FILES['code']['tmp_name'];
				$file_type=$_FILES['code']['type'];
				$tmp = explode('.',$_FILES['code']['name']);
				$file_ext=strtolower(end($tmp));

				// check file name size
				if(strlen($file_name) >= 100){
					 $errorMessage .= "The file name should be shorter or equal to 100 characters. <br />";
				}

				// for dealing with 'zip_java' and 'zip_py'
				$row['ext'] = explode('_',$row['ext'])[0];
				if($file_ext != $row['ext']) {
					$errorMessage .= "The uploaded file's extension should be '".$row['ext']."'! <br />";
				}

				if($file_size > 5000000){
					$errorMessage .= 'The file size must be lower or equal to 5 MB';
				}

				if($errorMessage == ""){
					// add a path to upload folder and make a new name to avoid filename conflict
					$new_file_name = "uploads/".microtime(true) . ".code";
					// if the new name is still in conflict (unlikely though)
					while (file_exists($new_file_name)) {
						$counter = random_str(3);
					  $new_file_name = "uploads/".microtime(true) . $counter . ".code";
					}
					// no error, proceed to storing the data
					$sql = "INSERT INTO submission (description, filename, file_path, attempt, submitter_id, assessment_id)
					 VALUES ('".$mydesc."', '".$file_name."', '".$new_file_name."', '".$attempt."', '".$_SESSION['user_id']."', '".$myassessmentid."')";
					if ($db->query($sql) === TRUE) {
						// if updated well, move the file to uploads
						move_uploaded_file($file_tmp,$new_file_name);
						// redirect to dashboard
						header('Location: student_peer_dashboard.php?submit=true');
						exit;
					} else {
						echo "Error adding record: " . $db->error;
					}
				}
     	}
	 	}
	}
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
		<title> E-STRANGE: Submit assessment</title>
		<link rel="icon" href="strange_html_layout_additional_files/icon.png">

		<link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet"> 
		<!--TTAMBAHAN <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">-->

	  <!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	
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
		  setHeaderStudent("submissions", "Submit assessment");
		?>
		<div class="container mt-3">
			<div class="row d-flex justify-content-center align-items-center" style="min-height: 60vh">
				<div class="col-md-6">
					<div class="card mx-auto w-100 border-0">
						<!-- copied and modified from https://www.w3schools.com/howto/howto_css_login_form.asp -->
						<form class="w-100" action="<?php echo htmlentities($_SERVER['PHP_SELF']). "?id=".$publicAssessmentId; ?>" method="post" enctype="multipart/form-data">
							<div class="formbody">
								<?php
									// if error message exist, show it
									if(isset($errorMessage) && $errorMessage != ""){
										// show the message
										echo "<div class='warning'>Error(s):<br />".$errorMessage."</div>";
										// unset afterward
										$errorMessage = "";
									}
								?>
								<div class="form-group mt-3">
									<div class="infolabel"><b>Course / assessment:</b></div>
									<div class="infovalue"><?php echo $row['course_name']." / ".$row['assessment_name'];?></div>
								</div>
								<div class="form-group mt-3">
									<label><b>Assessment desc:</b></label>
									<div id="asmt_desc"><?php echo $row['assessment_description']; ?></div>
								</div>
								<div class="form-group mt-3">
									<div class="infolabel"><b>Attempt:</b></div>
									<div class="infovalue"><?php echo $attempt;?></div>
								</div>
								<div class="form-group mt-3">
									<label for="code"><b>Code:</b></label>
									<input class="form-control" type="file" id="code" name="code" required>
								</div>
								<div class="form-group mt-3">
									<label for="desc"><b>Submission desc:</b></label>
									<textarea class="form-control" placeholder="Enter submission description" name="desc" ><?php if(isset($mydesc) && $mydesc != ''){ echo $mydesc;}?></textarea>
								</div>
							</div>
							<div class="mt-3">
								<?php
									if(isset($_GET['game']) && $_GET['game'] != ''){
										echo '<a href="student_incomplete_assessment_goals.php?id='.$_GET['game'].'" class="btn btn-danger">Cancel</a>';
									}else{
										// student_dashboard.php
										echo '<a href="student_peer_dashboard.php" class="btn btn-danger">Cancel</a>';
									}
								?>
								<button class="btn btn-primary" type="submit">Submit</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>			
		
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
