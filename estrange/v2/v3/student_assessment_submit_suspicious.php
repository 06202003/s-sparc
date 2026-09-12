<?php
	include("_sessionchecker.php");
	include("_config.php");
	include_once("_ai_quiz.php");


	if(isset($_POST['id']) == false){
		if(isset($_SESSION['student_assessment_submit_suspicious_id']) == false){
				// if no id is provided, move to dashboard
			header('Location: student_dashboard.php');
			exit;
		}else{
			// if there is a session var for this, set the id with that value
			$_POST['id'] = $_SESSION['student_assessment_submit_suspicious_id'];
			$_POST['mode'] = $_SESSION['student_assessment_submit_suspicious_mode'];
		}
	}else{
		// set the session value for id
		$_SESSION['student_assessment_submit_suspicious_id'] = 	$_POST['id'];
		$_SESSION['student_assessment_submit_suspicious_mode'] = $_POST['mode'];
	}

	// get the required data for this page
	$sql = "SELECT assessment.name AS assessment_name, assessment.assessment_id, assessment.description as assessment_description, 
			course.name AS course_name, assessment.submission_file_extension AS ext, 
			assessment.suspicion_response AS is_suspicion_response_needed, suspicion.public_suspicion_id AS public_suspicion_id FROM assessment
		 INNER JOIN course ON course.course_id = assessment.course_id
		 INNER JOIN submission ON submission.assessment_id = assessment.assessment_id
		 INNER JOIN suspicion ON suspicion.submission_id = submission.submission_id
		 WHERE suspicion.suspicion_id = '".$_POST['id']."'
		 AND (assessment.submission_close_time > CURRENT_TIMESTAMP OR assessment.allow_late_submission = '1')";
	$result = mysqli_query($db,$sql);
	$row = $result->fetch_assoc();
	$mypublicsuspicionid = $row['public_suspicion_id'];
	$myassessmentid = $row['assessment_id'];
	$myassessmentdesc = $row['assessment_description'];
	$myassessmentname = $row['assessment_name'];
	$mycoursename = $row['course_name'];
	$mysuspicionid = mysqli_real_escape_string($db,$_POST['id']);
	$mysuspicionresponse = $row['is_suspicion_response_needed'];

	// for dealing with 'zip_java' and 'zip_py'
	$row['ext'] = explode('_',$row['ext'])[0];
	// set the accepted extension
	$accepted_ext = $row['ext'];

	// file handling copied and modified from https://stackoverflow.com/questions/5593473/how-to-upload-and-parse-a-csv-file-in-php
	if($_SERVER["REQUEST_METHOD"] == "POST") {
		if ( isset($_FILES["code"])) {
			 // if there was an error uploading the file
      if ($_FILES["code"]["error"] > 0) {
        echo "Return Code: " . $_FILES["ufile"]["error"] . "<br />";
      }
      else {
				// set the error message
				$errorMessage = "";

				// get the data from form
				$mydesc = mysqli_real_escape_string($db,$_POST['desc']);
				$myreason = mysqli_real_escape_string($db,$_POST['reason']);

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

				// file extension check
				if(isset($accepted_ext) && $file_ext != $accepted_ext) {
					$errorMessage .= "The uploaded file's extension should be '".$accepted_ext."'! <br />";
				}

				// file size check
				if($file_size > 5000000){
					 $errorMessage .= 'The file size must be lower or equal to 5 MB';
				}

				if($errorMessage == ""){
					// add a path to upload folder and make a new name to avoid filename conflict
					$uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
					if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
						echo "The upload directory could not be created.";
						exit;
					}
					$new_file_name = "uploads/".microtime(true) . ".code";
					$new_file_path = __DIR__ . DIRECTORY_SEPARATOR . $new_file_name;
					// if the new name is still in conflict (unlikely though)
					$counter = 0;
					while (file_exists($new_file_path)) {
						$counter += 1;
						$new_file_name = "uploads/".microtime(true) . $counter . ".code";
						$new_file_path = __DIR__ . DIRECTORY_SEPARATOR . $new_file_name;
					}
					// no error, proceed to storing the data
					$sql = "INSERT INTO submission (description, filename, file_path, attempt, submitter_id, assessment_id)
					 VALUES ('".$mydesc."', '".$file_name."', '".$new_file_name."', '".$attempt."', '".$_SESSION['user_id']."', '".$myassessmentid."')";
					if ($db->query($sql) === TRUE) {
						$submissionId = $db->insert_id;

						// if updated well, move the file to uploads and redirect to dashboard
						move_uploaded_file($file_tmp,$new_file_path);
						create_submission_quiz($db, $submissionId, (int)$_SESSION['user_id']);

						// update the suspicion with the reason
						$sql = "UPDATE suspicion SET student_response = '$myreason'
 						WHERE suspicion_id='$mysuspicionid'";

						if ($db->query($sql) === TRUE) {
							// if updated well, send the student to the generated quiz
							header('Location: student_submission_quiz.php?submission_id=' . $submissionId);
							exit;
						} else {
							echo "Error adding record: " . $db->error;
						}
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
		<title> E-STRANGE: Resubmit assessment</title>
    <link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">


    <script>
    </script>
    <style>
			body { font-family: 'Inter', system-ui, -apple-system, sans-serif;
				font-size: 12px;
				background-color: rgba(250,250,250,1);
			}
			div{
				float:left;
			}

			/* copied and modified from https://www.w3schools.com/css/css3_buttons.asp */
			button {
				background-color: rgba(0,140,186,1);
				border: none;
				color: white;
				padding: 2px 4px;
				text-align: center;
				text-decoration: none;
				display: inline-block;
				cursor: pointer;
			}

			/* for tabbed view. copied and modified from https://www.w3schools.com/howto/howto_js_tabs.asp */
			.tab {
			  float:left;
				width:100%;
				background-color: rgba(0,140,186,1);
			}
			button.tablinks {
				border:none;
				outline: none;
			  float: left;
			  cursor: pointer;
			  padding: 6px 20px;
				height:30px;
			  transition: 0.3s;
			}
			.tab button:hover {
			  background-color: rgba(20,160,206,1);
			}
			.tab button.active {
			  background-color: rgba(40,180,226,1);
			}
			div.tabcontent {
				float:left;
				width:99%;
				height:80%;
				display: none;
				border-top: none;
			}

			/* for header */
			div.header{
				width:100%;
				height:8%;
				margin-bottom:10px;
			}
			img{
				float:left;
				height:100%;
				margin-right:10px;
			}
			div.headertitle{
				font-weight: bold;
				font-size: 22px;
				height:100%;
				padding-top:20px;
				color: rgba(0,65,111,1);
			}
			div.logintext{
				float:right;
			}

			button.actionbutton, a.actionbutton{
				float:left;
				margin-top:10px;
				margin-left:10px;
				padding: 6px 20px;
				height:30px;
			}
			a.actionbutton{
				height:18px;
				padding-top:8px;
				padding-bottom:4px;
				background-color: rgba(0,140,186,1);
				border: none;
				color: white;
				text-align: center;
				text-decoration: none;
				cursor: pointer;
			}
			#mainsubmit{
				margin-left:67%;
			}

			<!-- copied and modified from https://www.w3schools.com/howto/howto_css_login_form.asp -->
			form{
				float:left;
				width:100%;
			}
			div.formbody{
				width:58%;
				height:50%;
				padding: 1%;
				margin-top:30px;
				margin-left:20%;
				margin-right:20%;
				border: 1px solid #dddddd;
				overflow-y: auto;
			}
			div.formrow {
				float:left;
				width:100%;
			}
			label, div.infolabel{
				float:left;
				width:20%;
				font-size: 16px;
				text-align: left;
				padding: 12px 20px;
			  margin: 8px 0;
			}
			input, textarea, div.infovalue{
				float:right;
			  width: 70%;
			  border: 1px solid #ccc;
				box-sizing: border-box;
				padding: 12px 20px;
			  margin: 8px 0;
			}
			div.infovalue{
				padding-top:14px;
				padding-bottom:10px;
				padding-left:0px;
				padding-right:0px;
				border: 0px;
				font-family: inherit;
				font-size: inherit;
			}
			textarea{
				margin-top:15px;
				font-family: inherit;
				font-size: inherit;
				resize: none;
			}

			div.warning{
				float:left;
				width:95%;
				font-size: 16px;
				font-weight:bold;
				text-align:left;
				color:red;
				margin-left:2%;
				margin-top:2%;
				margin-bottom:0%;
			}
			
			#asmt_desc{
				float:right;
				width: 70%;
				min-height: 50px;
				padding-left: 6px;
				padding-right: 6px;
				border: 1px solid #ccc;
				box-sizing: border-box;
				margin-top:10px;
				margin-bottom:8px;
			}

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
		<div class="header">
			<img src="strange_html_layout_additional_files/logo.png" alt="logo" />
			<div class="headertitle">Resubmit assessment</div>
			<?php
			  echo '<div class="logintext">Hello '.$_SESSION['name'].' You logged in as '.$_SESSION['role'].'!</div>';
			?>
		</div>

		<div class="tab">
			<button class="tablinks" onclick="window.open('student_dashboard.php', '_self');">Upcoming assessment due</button>
			<button class="tablinks" onclick="window.open('student_enrollment.php', '_self');">Enrolment</button>
			<button class="tablinks active" onclick="window.open('student_submission.php', '_self');">Submissions</button>
			<button class="tablinks" onclick="window.open('student_suspicion_response.php', '_self');">Similarity responses</button>
			<button class="tablinks" onclick="window.open('student_game.php', '_self');">Game</button>
			<button class="tablinks" onclick="window.open('user_info_self_update.php', '_self');">Update personal information</button>
			<button class="tablinks" onclick="window.open('user_about.php', '_self');">About</button>
			<form action=" <?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
			  <input type="hidden" name="logout" value="logout">
			  <button class="tablinks" type="submit">Logout</button>
			</form>
		</div>

		<!-- copied and modified from https://www.w3schools.com/howto/howto_css_login_form.asp -->
		<form action="<?php echo htmlentities($_SERVER['PHP_SELF'])."?id=".$mysuspicionid; ?>" method="post" enctype="multipart/form-data">
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
				 <div class="formrow">
 					<div class="infolabel"><b>Course / assessment:</b></div>
 					<div class="infovalue"><?php echo $mycoursename." / ".$myassessmentname;?></div>
 				</div>
				<div class="formrow">
					<label><b>Assessment desc:</b></label>
					<div id="asmt_desc"><?php echo $myassessmentdesc; ?></div>
				</div>
 				<div class="formrow">
 					<label for="code"><b>Code:</b></label>
 					<input type="file" id="code" name="code" required>
 				</div>
 				<div class="formrow">
 					<label for="desc"><b>Submission desc:</b></label>
 					<textarea rows=5 placeholder="Enter submission description" name="desc" ><?php if(isset($mydesc) && $mydesc != ''){ echo $mydesc;}?></textarea>
 				</div>
				<?php
				if($mysuspicionresponse == 1){	
					echo '<div class="formrow">
							<label for="reason"><b>Reason(s) for similarity alert on previous submission:</b></label>
							<textarea rows=5 placeholder="Enter reason(s)" name="reason" required>';
					if(isset($myreason) && $myreason != ''){echo $myreason;}
					echo   '</textarea>
						  </div>
						  ';
				}else{
					if($human_language == 'en'){
						echo '<input type="hidden" name="reason" value="[Automatically generated] Similarity response is not required in the assessment">';
					}else{
						echo '<input type="hidden" name="reason" value="[Terisi otomatis oleh sistem] Respon terhadap kesamaan tidak dibutuhkan di tugas terkait">';
					}
				}
				?>
				<input type="hidden" name="id" value="<?php echo $_POST['id']; ?>">
				<input type="hidden" name="mode" value="<?php echo $_POST['mode']; ?>">
			</div>
			<button class="actionbutton" id="mainsubmit" type="submit">Submit</button>
		</form>
		<?php
			// mode 1 to 3
			echo "
				<form class=\"invisform\" action=\"student_suspicion_sub.php?id=".$mypublicsuspicionid."\" method=\"post\">
					";
			if(isset($_POST['mode'])){
				echo "<input type=\"hidden\" name=\"mode\" value=\"".$_POST['mode']."\">";
			}
			echo "
					<button class=\"actionbutton\" type=\"submit\">Camcel</button>
				</form>
				";
		?>


<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
