<?php
include("_sessionchecker.php");
include("_config.php");

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
				header('Location: lecturer_students.php');
				exit;
		 }
	 }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Enroll Students</title>
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
	<?php setHeaderLecturer("courses", "Student enrollment"); ?>

	<main class="flex-1 py-10 flex items-center justify-center">
		<div class="max-w-2xl w-full mx-auto px-4 sm:px-6">
			
			<div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
				
				<!-- Card Header -->
				<div class="px-8 pt-8 pb-6 border-b border-slate-100 bg-slate-50/50">
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Enrollment
						</span>
						<span class="text-xs font-semibold text-slate-500">
							<?= htmlspecialchars($_SESSION['course_name'] ?? 'Course') ?>
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Batch Enroll Students</h1>
					<p class="text-xs text-slate-500 mt-1">Add multiple registered students to this course by providing their usernames.</p>
				</div>

				<!-- Form Content -->
				<form action="<?= htmlentities($_SERVER['PHP_SELF']); ?>" method="post" class="p-8 space-y-6">
					
					<?php if(isset($errorMessage) && $errorMessage != ""): ?>
						<div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 space-y-1">
							<span class="font-bold block">Validation Errors:</span>
							<div><?= $errorMessage ?></div>
						</div>
					<?php endif; ?>

					<!-- Usernames Textarea -->
					<div class="space-y-2">
						<div class="flex items-center justify-between">
							<label for="students" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Student Usernames <span class="text-rose-500">*</span></label>
							<span class="text-[11px] text-slate-400 font-medium">One username per line</span>
						</div>
						<textarea id="students" name="students" rows="8" placeholder="e.g.&#10;student_john&#10;student_sarah&#10;student_alex" required
							class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono text-slate-900 placeholder-slate-400 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition"><?= isset($mystudents) ? htmlspecialchars(str_replace("\\r", "\r", str_replace("\\n", "\n", $mystudents))) : '' ?></textarea>
						<p class="text-[11px] text-slate-500 leading-relaxed">
							Note: Usernames must belong to registered student accounts and must not conflict with co-lecturer roles.
						</p>
					</div>

					<!-- Actions -->
					<div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
						<a href="lecturer_students.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
							Cancel
						</a>
						<button type="submit" class="px-6 py-2.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl shadow-xs transition duration-150">
							Enroll Students
						</button>
					</div>

				</form>
			</div>

		</div>
	</main>
</body>
</html>
