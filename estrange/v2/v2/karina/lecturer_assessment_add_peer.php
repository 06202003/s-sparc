<?php
	ob_start();
	include("_sessionchecker_peer.php");
	include("_config_peer_review.php");
	include("_header_peer_review.php");

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

	// copied and modified from https://www.tutorialspoint.com/php/php_mysql_login.htm
	if($_SERVER["REQUEST_METHOD"] == "POST") {
		// process the added data
		if(isset($_POST['cname']) == true){
		   // data sent from form
		   $myname= mysqli_real_escape_string($db,$_POST['cname']);
		   $mydesc = mysqli_real_escape_string($db,$_POST['desc']);
		   echo $mydesc;
			 $myopentime = new DateTime(mysqli_real_escape_string($db,$_POST['open_time']));
			 $myclosetime = new DateTime(mysqli_real_escape_string($db,$_POST['close_time']));
			 $myacceptedfileext = mysqli_real_escape_string($db,$_POST['file_ext']);
			 $myallowlatesubmission = mysqli_real_escape_string($db,$_POST['late_submission']);
			 $mycourseid = $_SESSION['course_id'];

			 // to store the error message
			 $errorMessage = "";

			 // check whether closing time is later than opening time
			 if($myclosetime < $myopentime){
				 $errorMessage .= "The closing submission time should be later than the opening one. <br />";
			 }

			 if(strlen($myname) >= 50){
					$errorMessage .= "The assessment name should be shorter or equal to 50 characters. <br />";
			 }

			 // if no error message
			 if($errorMessage == ""){
				 // generate public_assessment_id
				 $public_assessment_id = '';
				 while(true){
				   // generate the key
				   $public_assessment_id = random_str(5).microtime(true).random_str(5);

				   // if such key is nonexistent, escape the loop
				   $sql = "SELECT assessment_id FROM assessment WHERE public_assessment_id = '$public_assessment_id'";
				   $result = mysqli_query($db,$sql);
				   $count = mysqli_num_rows($result);
				   if($count == 0){
					 break;
				   }
				 }

				// add the entry
				$sql = "INSERT INTO assessment (name, description, submission_open_time, submission_close_time, course_id, submission_file_extension, public_assessment_id, allow_late_submission)
						 VALUES ('".$myname."', '".$mydesc."', '".$myopentime->format('Y-m-d\TH:i')."', '".$myclosetime->format('Y-m-d\TH:i')."', '".$mycourseid."','".$myacceptedfileext."','".$public_assessment_id."','".$myallowlatesubmission."')";
				if ($db->query($sql) === TRUE) {
					// if updated well, redirect to dashboard
					header('Location: lecturer_assessments_peer.php');
					exit;
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
		<title> E-STRANGE: Add assessment</title>
    <link rel="icon" href="../strange_html_layout_additional_files/icon.png">
	<link href="../strange_html_layout_additional_files/quill/quill.snow.css" rel="stylesheet">
	<!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	
	<script src="../strange_html_layout_additional_files/quill/quill.min.js"></script>
    <script>
		var descricheditor;
		function initialise(){
		  descricheditor = new Quill('#desc_rich_editor', {
			theme: 'snow',
			placeholder: 'Enter description'
		  });
		}
		function prepareform(){
			var dst = document.getElementById("desc");
			dst.value  = descricheditor.root.innerHTML;
		}
    </script>
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
  <body onload="initialise();" >
		<?php
		  setHeaderLecturer("courses", "Add assessment");
		?>

		<div class="container mt-3">
			<div class="row d-flex justify-content-center align-items-center" style="min-height: 60vh">
				<div class="col-md-6">
					<div class="card mx-auto w-100 border-0">
						<!-- copied and modified from https://www.w3schools.com/howto/howto_css_login_form.asp -->
						<form class="w-100" onsubmit="prepareform();" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
							<div class="formbody">
								<?php
									// if error message exist, show it
									if(isset($errorMessage) && $errorMessage != ""){
										// show the message
										echo "<div class='alert alert-warning'>Error(s):<br />".$errorMessage."</div>";
									}
								?>
								<div class="form-group mt-3">
									<label for="cname"><b>Assessment name:</b></label>
									<input class="form-control" type="text" placeholder="Enter assessment name" name="cname"
										<?php if(isset($myname) == true){echo "value = \"".$myname."\"";} ?>
									required />
								</div>
								<div class="form-group mt-3">
									<label for="desc"><b>Description:</b></label>
									<div id="desc_rich_editor" contenteditable="true"><?php if(isset($mydesc) == true){echo $mydesc; } ?></div>
									<input class="form-control" type="hidden" id="desc" name="desc" />
								</div>
								<div class="form-group mt-3">
									<label for="open_time"><b>Submission opening time:</b></label>
									<input class="form-control" type="datetime-local" id="open_time" name="open_time"
										<?php
											if(isset($myopentime) == true && $myopentime != ""){
												echo "value = \"".$myopentime->format('Y-m-d\TH:i')."\"";
											} else{
												echo "value = \"".date('Y-m-d\TH:i')."\"";
											}
										?>
									required />
								</div>
								<div class="form-group mt-3">
									<label for="close_time"><b>Submission closing time:</b></label>
									<input class="form-control" type="datetime-local" id="close_time" name="close_time"
										<?php
											if(isset($myclosetime) == true && $myclosetime != ""){
												echo "value = \"".$myclosetime->format('Y-m-d\TH:i')."\"";
											}else{
												echo "value = \"".date('Y-m-d\TH:i',strtotime("+1 week"))."\"";
											}
										?>
									required />
								</div>
								<div class="form-group mt-3">
									<label for="file_ext"><b>Submission file type:</b></label>
									<select class="min-w-[200px] shrink-0 form-control" id="file_ext" name="file_ext">
										<?php
											if(isset($myacceptedfileext)){
												// set with the existing value

												if($myacceptedfileext == 'java'){
													echo '<option value="java" selected>Java file</option>';
												}else{
													echo '<option value="java">Java file</option>';
												}

												if($myacceptedfileext == 'py'){
													echo '<option value="py" selected>Python file</option>';
												}else{
													echo '<option value="py">Python file</option>';
												}

												if($myacceptedfileext == 'zip_java'){
													echo '<option value="zip_java" selected>Zip file (Java)</option>';
												}else{
													echo '<option value="zip_java">Zip file (Java)</option>';
												}

												if($myacceptedfileext == 'zip_py'){
													echo '<option value="zip_py" selected>Zip file (Python)</option>';
												}else{
													echo '<option value="zip_py">Zip file (Python)</option>';
												}

											}else{
												echo '<option value="java" selected>Java file</option>
												<option value="py">Python file</option>
												<option value="zip_java">Zip file (Java)</option>
												<option value="zip_py">Zip file (Python)</option>';
											}
										?>
									</select>
								</div>
								<div class="form-group mt-3">
									<label for="late_submission"><b>Late submission:</b></label>
									<select class="min-w-[200px] shrink-0 form-control" id="late_submission" name="late_submission">
										<?php
											if(isset($myallowlatesubmission)){
												// set with the existing value

												if($myallowlatesubmission == 1){
													echo '<option value="1" selected>Allow</option>';
												}else{
													echo '<option value="1">Allow</option>';
												}

												if($myallowlatesubmission == 0){
													echo '<option value="0" selected>Disallow</option>';
												}else{
													echo '<option value="0">Disallow</option>';
												}
											}else{
												echo '<option value="1">Allow</option>
												<option value="0" selected>Disallow</option>';
											}
										?>
									</select>
								</div>
							</div>
							<div class="mt-3">
								<a href="lecturer_assessments_peer.php" class="btn btn-danger">Cancel</a>
								<button class="btn btn-primary" type="submit">Add</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>

		<script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
