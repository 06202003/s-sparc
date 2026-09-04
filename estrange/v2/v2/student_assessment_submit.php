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
		header('Location: student_dashboard.php');
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
	        header('Location: lecturer_dashboard.php');
	      } else if ($_SESSION['role'] == 'student'){
	        header('Location: student_dashboard.php');
	      }
	      exit;
	    }
	  }
	}

	include("_config.php");
	include_once("_ai_quiz.php");

	// get the real assessment_id
	$sql = "SELECT assessment_id FROM assessment
		WHERE public_assessment_id = '".$_GET['id']."'";
	$result = mysqli_query($db,$sql);
	$row = $result->fetch_assoc();
	if(is_null($row)){
		// if no such id exists, redirect to student dashboard
		header('Location: student_dashboard.php');
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
		header('Location: student_dashboard.php');
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
					$uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
					if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
						echo "The upload directory could not be created.";
						exit;
					}
					$new_file_name = "uploads/".microtime(true) . ".code";
					$new_file_path = __DIR__ . DIRECTORY_SEPARATOR . $new_file_name;
					// if the new name is still in conflict (unlikely though)
					while (file_exists($new_file_path)) {
						$counter = random_str(3);
					  $new_file_name = "uploads/".microtime(true) . $counter . ".code";
					  $new_file_path = __DIR__ . DIRECTORY_SEPARATOR . $new_file_name;
					}
					// no error, proceed to storing the data
					$sql = "INSERT INTO submission (description, filename, file_path, attempt, submitter_id, assessment_id)
					 VALUES ('".$mydesc."', '".$file_name."', '".$new_file_name."', '".$attempt."', '".$_SESSION['user_id']."', '".$myassessmentid."')";
					if ($db->query($sql) === TRUE) {
						$submissionId = $db->insert_id;
						// if updated well, move the file to uploads
						move_uploaded_file($file_tmp,$new_file_path);
						create_submission_quiz($db, $submissionId, (int)$_SESSION['user_id']);
						// Generate the quiz after the submission has been recorded.
						header('Location: student_instant_quiz.php?submission_id=' . $submissionId);
						exit;
					} else {
						echo "Error adding record: " . $db->error;
					}
				}
     	}
	 	}
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Submit Assessment</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>
	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
	<?php setHeaderStudent("submissions", "Submit assessment"); ?>

	<main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
		<div class="w-full max-w-2xl bg-white rounded-2xl border border-slate-200/80 shadow-xl p-8 sm:p-10 space-y-6">
			
			<div class="border-b border-slate-200 pb-4">
				<div class="flex items-center gap-2 mb-1">
					<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
						Assessment Submission
					</span>
					<span class="text-xs font-semibold text-slate-500">
						Attempt #<?= htmlspecialchars((string)$attempt) ?>
					</span>
				</div>
				<h1 class="text-xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($row['assessment_name']) ?></h1>
				<p class="text-xs font-semibold text-slate-500 mt-0.5"><?= htmlspecialchars($row['course_name']) ?></p>
			</div>

			<?php if (isset($errorMessage) && $errorMessage != ""): ?>
				<div class="rounded-xl border border-rose-200 bg-rose-50/80 p-4 text-xs text-rose-700 flex items-center gap-2">
					<svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
					<div><?= $errorMessage ?></div>
				</div>
				<?php $errorMessage = ""; ?>
			<?php endif; ?>

			<!-- Assessment Brief -->
			<?php if (!empty($row['assessment_description'])): ?>
				<div class="bg-slate-50 rounded-xl p-4 border border-slate-200/80">
					<span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Instructions &amp; Description</span>
					<div class="text-xs text-slate-700 leading-relaxed whitespace-pre-line max-h-48 overflow-y-auto">
						<?= $row['assessment_description'] ?>
					</div>
				</div>
			<?php endif; ?>

			<form action="<?= htmlentities($_SERVER['PHP_SELF']). "?id=".$publicAssessmentId; ?>" method="post" enctype="multipart/form-data" class="space-y-5">
				<div>
					<label for="code" class="block text-xs font-semibold text-slate-700 mb-1.5">
						Source Code Archive / File <span class="text-rose-500">*</span>
					</label>
					<div class="relative">
						<input 
							type="file" 
							id="code" 
							name="code" 
							required
							class="w-full text-sm font-semibold text-slate-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-900 file:text-white hover:file:bg-slate-800 file:cursor-pointer border border-slate-200 rounded-xl bg-slate-50 p-2 cursor-pointer transition"
						/>
					</div>
					<p class="text-[11px] text-slate-400 mt-1">Accepted formats: .zip, .java, .py, etc. Max file size: 5 MB.</p>
				</div>

				<div>
					<label for="desc" class="block text-xs font-semibold text-slate-700 mb-1.5">Submission Notes &amp; Comments (Optional)</label>
					<textarea 
						id="desc"
						name="desc" 
						rows="3" 
						placeholder="Add any context, runtime notes, or special instructions for review..."
						class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition leading-relaxed resize-none"
					><?php if(isset($mydesc) && $mydesc != ''){ echo htmlspecialchars($mydesc); } ?></textarea>
				</div>

				<div class="flex items-center gap-3 pt-2">
					<?php
						$cancelUrl = (isset($_GET['game']) && $_GET['game'] != '') 
							? 'student_incomplete_assessment_goals.php?id='.urlencode($_GET['game']) 
							: 'student_dashboard.php';
					?>
					<a 
						href="<?= $cancelUrl ?>" 
						class="w-1/3 py-2.5 px-4 bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition text-center shadow-2xs"
					>
						Cancel
					</a>
					<button 
						type="submit" 
						class="w-2/3 py-2.5 px-4 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-semibold rounded-xl shadow-xs transition duration-150 flex items-center justify-center gap-2"
					>
						<span>Upload &amp; Submit Solution</span>
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
					</button>
				</div>
			</form>

		</div>
	</main>
</body>
</html>
