<?php
	include("_sessionchecker.php");
	include("_config.php");

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
						header('Location: lecturer_dashboard.php');
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
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Add Course</title>
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
	<?php setHeaderLecturer("courses", "Add course"); ?>

	<main class="flex-1 py-10 flex items-center justify-center">
		<div class="max-w-2xl w-full mx-auto px-4 sm:px-6">
			
			<div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
				
				<!-- Card Header -->
				<div class="px-8 pt-8 pb-6 border-b border-slate-100 bg-slate-50/50">
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Curriculum
						</span>
						<span class="text-xs font-semibold text-slate-500">
							New Offering Setup
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Create Course Offering</h1>
					<p class="text-xs text-slate-500 mt-1">Specify enrolment security, course description, and optional gamification dynamics.</p>
				</div>

				<!-- Form Content -->
				<form action="<?= htmlentities($_SERVER['PHP_SELF']); ?>" method="post" class="p-8 space-y-6">
					
					<?php if(isset($errorMessage) && $errorMessage != ""): ?>
						<div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 space-y-1">
							<span class="font-bold block">Please resolve the following issues:</span>
							<div><?= $errorMessage ?></div>
						</div>
					<?php endif; ?>

					<!-- Course Name -->
					<div class="space-y-1.5">
						<label for="cname" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Course Name <span class="text-rose-500">*</span></label>
						<input type="text" id="cname" name="cname" placeholder="e.g., Object-Oriented Programming (CS201)" 
							value="<?= isset($myname) ? htmlspecialchars($myname) : '' ?>" required
							class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
					</div>

					<!-- Description -->
					<div class="space-y-1.5">
						<label for="desc" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Course Description</label>
						<textarea id="desc" name="desc" rows="3" placeholder="Brief outline or syllabus overview..."
							class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition"><?= isset($mydesc) ? htmlspecialchars($mydesc) : '' ?></textarea>
					</div>

					<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
						<!-- Enrollment Mode -->
						<div class="space-y-1.5">
							<label for="enrollment_type" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Enrolment Mode</label>
							<select id="enrollment_type" name="enrollment_type"
								class="min-w-[200px] shrink-0 w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
								<option value="0" <?= (isset($myenrollmenttype) && $myenrollmenttype == 0) ? 'selected' : '' ?>>Manual: Lecturer enrols students</option>
								<option value="1" <?= (isset($myenrollmenttype) && $myenrollmenttype == 1) ? 'selected' : '' ?>>Public: Students self-enrol</option>
							</select>
						</div>

						<!-- Course Password -->
						<div class="space-y-1.5">
							<label for="cpassword" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Enrolment Password</label>
							<input type="text" id="cpassword" name="cpassword" placeholder="Passcode (if public self-enrolment)"
								value="<?= isset($mycpassword) ? htmlspecialchars($mycpassword) : '' ?>"
								class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
						</div>
					</div>

					<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
						<!-- Gamification Feature -->
						<div class="space-y-1.5">
							<label for="game_feature" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Gamification Engine</label>
							<select id="game_feature" name="game_feature"
								class="min-w-[200px] shrink-0 w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
								<option value="0" <?= (isset($mygamefeature) && $mygamefeature == 0) ? 'selected' : (!isset($mygamefeature) ? 'selected' : '') ?>>Disabled (Standard Mode)</option>
								<option value="1" <?= (isset($mygamefeature) && $mygamefeature == 1) ? 'selected' : '' ?>>Active (Leaderboards &amp; Badges)</option>
							</select>
						</div>

						<!-- Game Prize Text -->
						<div class="space-y-1.5">
							<label for="prize_text" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Gamification Incentive / Prize</label>
							<input type="text" id="prize_text" name="prize_text" placeholder="e.g., Certificate of Excellence"
								value="<?= isset($myprizetext) ? htmlspecialchars($myprizetext) : '' ?>"
								class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
						</div>
					</div>

					<!-- Form Actions -->
					<div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
						<a href="lecturer_dashboard.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
							Cancel
						</a>
						<button type="submit" class="px-6 py-2.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl shadow-xs transition duration-150">
							Create Course
						</button>
					</div>

				</form>
			</div>

		</div>
	</main>
</body>
</html>
