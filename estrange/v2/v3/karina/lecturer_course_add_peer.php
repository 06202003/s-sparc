<?php
	include("_sessionchecker_peer.php");
	include("_config_peer_review.php");
	include("_header_peer_review.php");

	// copied and modified from https://www.tutorialspoint.com/php/php_mysql_login.htm
	if($_SERVER["REQUEST_METHOD"] == "POST") {
		// add new course from the added data given in the form
		if(isset($_POST['cname']) == true){
			// data sent from form
			$myname= mysqli_real_escape_string($db,$_POST['cname']);
			$mydesc = mysqli_real_escape_string($db,$_POST['desc']);
			$myenrollmenttype = mysqli_real_escape_string($db,$_POST['enrollment_type']);
			$mycpassword = mysqli_real_escape_string($db,$_POST['cpassword']);
			$mygamefeature = mysqli_real_escape_string($db,$_POST['game_feature']);
			$myprizetext = mysqli_real_escape_string($db,$_POST['prize_text']);
			$errorMessage = "";

			 if(strlen($myname) >= 50){
					$errorMessage .= "The course name should be shorter or equal to 50 characters. <br />";
			 }
			 
			 if(strlen($mycpassword) >= 50){
					$errorMessage .= "The course password should be shorter or equal to 50 characters. <br />";
			 }

			 // if no error message
			 if($errorMessage == ""){
				// add the entry
				$sql = "INSERT INTO course (name, course_password, description, enrollment_mode, creator_id)
						 VALUES ('".$myname."', '".$mycpassword."', '".$mydesc."', '".$myenrollmenttype."', '".$_SESSION['user_id']."')";
				if ($db->query($sql) === TRUE) {
					// get the newly-added course id
					$courseid = mysqli_insert_id($db);
					
					$sql = "INSERT INTO game_course (course_id, prize_text, is_active)
						 VALUES ('".$courseid."', '".$myprizetext."', '".$mygamefeature."')";
					if($db->query($sql) == true){
						// if updated well, redirect to dashboard
						header('Location: lecturer_peer_dashboard.php');
						exit;
					}else{
						echo "Error adding record: " . $db->error;
					}
				} else {
					echo "Error adding record: " . $db->error;
				}
			}
		 }
	}
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title> E-STRANGE: Add course</title>
    <link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
	<!-- ASLI <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet"> -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	

    <script>
    </script>
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
		  setHeaderLecturer("courses", "Add course");
		?>

		<div class="container mt-3">
			<div class="row d-flex justify-content-center align-items-center" style="min-height: 60vh">
				<div class="col-md-6">
					<div class="card mx-auto w-100 border-0">
						<!-- copied and modified from https://www.w3schools.com/howto/howto_css_login_form.asp -->
						<form class="w-100" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
							<div class="formbody">
								<?php
									// if error message exist, show it
									if(isset($errorMessage) && $errorMessage != ""){
										// show the message
										echo "<div class='alert alert-warning'>Error(s):<br />".$errorMessage."</div>";
									}
								?>
								<div class="form-group mt-3">
									<label for="cname"><b>Course name:</b></label>
									<input class="form-control" type="text" placeholder="Enter course name" name="cname" 
										<?php if(isset($myname) == true){echo "value = \"".$myname."\"";} ?>
									required />
								</div>
								<div class="form-group mt-3">
									<label for="desc"><b>Description:</b></label>
									<textarea class="form-control"  placeholder="Enter description" name="desc" ><?php if(isset($mydesc) == true){echo $mydesc; } ?></textarea>
								</div>
								<div class="form-group mt-3">
									<label for="enrollment_type"><b>Enrolment type:</b></label>
									<select class="min-w-[200px] shrink-0 form-control"  id="enrollment_type" name="enrollment_type">
										<?php
											if(isset($myenrollmenttype)){
												// set with the existing value

												if($myenrollmenttype == 0){
													echo '<option value="0" selected>Manual: Lecturer enrols students to the course</option>';
												}else{
													echo '<option value="0">Manual: Lecturer enrols students to the course</option>';
												}

												if($myenrollmenttype == 1){
													echo '<option value="1" selected>Public: students enrol themselves to the course</option>';
												}else{
													echo '<option value="1">Public: students enrol themselves to the course</option>';
												}
											}else{
												echo '<option value="0" selected>Manual: admin sets student accounts and lecturer enrols them to the course</option>
												<option value="1">Public: students create their own accounts and enrol themselves to the course</option>';
											}
										?>
									</select>
								</div>
								<div class="form-group mt-3">
									<label for="cpassword" ><b>Course password:</b><br /><i style="font-size:0.8em">If enrolment is public</i></label>
									<input class="form-control" type="text" placeholder="Enter course password" name="cpassword" 
										<?php if(isset($mycpassword) == true){echo "value = \"".$mycpassword."\"";}?> />
								</div>
								<div class="form-group mt-3">
									<label for="game_feature"><b>Game feature:</b></label>
									<select class="min-w-[200px] shrink-0 form-control" id="game_feature" name="game_feature">
											<?php
												if(isset($mygamefeature)){
													// set with the existing value

													if($mygamefeature == 1){
														echo '<option value="1" selected>On</option>';
													}else{
														echo '<option value="1">On</option>';
													}

													if($mygamefeature == 0){
														echo '<option value="0" selected>Off</option>';
													}else{
														echo '<option value="0">Off</option>';
													}
												}else{
													echo '<option value="1">On</option>
													<option value="0" selected>Off</option>';
												}
											?>
										</select>
								</div>
								<div class="form-group mt-3">
									<label for="prize_text"  ><b>Game prize text:</b><br /><i style="font-size:0.8em">If game feature is on</i></label>
									<textarea class="form-control"  placeholder="Enter explanation about game prize" name="prize_text" ><?php if(isset($myprizetext) == true){echo $myprizetext; } ?></textarea>
								</div>				
							</div>
							<div class="mt-3">
								<a href="lecturer_peer_dashboard.php" class="btn btn-danger">Cancel</a>
								<button class="btn btn-primary" type="submit">Add</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>

		<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
