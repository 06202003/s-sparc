<?php
	include("_sessionchecker.php");
	include("_config.php");

	// copied and modified from https://www.tutorialspoint.com/php/php_mysql_login.htm
	if($_SERVER["REQUEST_METHOD"] == "POST") {
		// process the updated data
		if(isset($_POST['cname']) == true){
		   // data sent from form
			 $myname= mysqli_real_escape_string($db,$_POST['cname']);
		     $mydesc = mysqli_real_escape_string($db,$_POST['desc']);
			 $myopentime = new DateTime(mysqli_real_escape_string($db,$_POST['open_time']));
			 $myclosetime = new DateTime(mysqli_real_escape_string($db,$_POST['close_time']));
			 $myoldclosetime = new DateTime(mysqli_real_escape_string($db,$_POST['old_close_time']));
			 $myacceptedfileext = mysqli_real_escape_string($db,$_POST['file_ext']);
			 $myallowlatesubmission = mysqli_real_escape_string($db,$_POST['late_submission']);
			 $mycourseid = $_SESSION['course_id'];
			 $assessment_id = mysqli_real_escape_string($db,$_POST['id']);


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
		       // update the entry
		       $sql = "UPDATE assessment SET name = '".$myname."', description = '".$mydesc."',
					 	submission_open_time = '".$myopentime->format('Y-m-d\TH:i')."',
						submission_close_time = '".$myclosetime->format('Y-m-d\TH:i')."',
						course_id = '".$mycourseid."', submission_file_extension = '".$myacceptedfileext."', allow_late_submission = '".$myallowlatesubmission."'";
				
			   // if the new close time is later than the old one, reset similarity report path			
			   if($myclosetime > $myoldclosetime){
				  $sql .= " ,similarity_report_path = '' ";
			   }
						
			  $sql .= "WHERE assessment_id='".$assessment_id."'";
		      if ($db->query($sql) === TRUE) {
		        // if updated well, redirect to dashboard
		        header('Location: colecturer_assessments.php');
				exit;
		      } else {
		        echo "Error updating record: " . $db->error;
				exit;
		      }
		   }
		 }
	}

	// if the post values do not exist
	if(isset($_POST['id']) == false){
		header('Location: colecturer_courses.php');
		exit;
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Update Assessment</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<link href="strange_html_layout_additional_files/quill/quill.snow.css" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>
	<script src="strange_html_layout_additional_files/quill/quill.min.js"></script>

	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
		.ql-toolbar.ql-snow {
			border-top-left-radius: 0.75rem;
			border-top-right-radius: 0.75rem;
			border-color: #e2e8f0;
			background: #f8fafc;
		}
		.ql-container.ql-snow {
			border-bottom-left-radius: 0.75rem;
			border-bottom-right-radius: 0.75rem;
			border-color: #e2e8f0;
			min-height: 120px;
			font-family: inherit;
		}
	</style>

	<script>
		var descricheditor;
		function initialise() {
			descricheditor = new Quill('#desc_rich_editor', {
				theme: 'snow',
				placeholder: 'Enter assessment description...'
			});
		}
		function prepareform() {
			var dst = document.getElementById("desc");
			dst.value = descricheditor.root.innerHTML;
		}
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
<body onload="initialise();" class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
	<?php
		if ($_SESSION['role'] == 'lecturer') {
			setHeaderLecturer("colecturer courses", "Update assessments for co-lecturer course");
		} else {
			setHeaderStudent("colecturer courses", "Update assessments for co-lecturer course");
		}
	?>

	<main class="flex-1 py-10 flex items-center justify-center">
		<div class="max-w-2xl w-full mx-auto px-4 sm:px-6">
			
			<div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
				
				<!-- Header -->
				<div class="px-8 pt-8 pb-6 border-b border-slate-100 bg-slate-50/50">
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Co-Lecturer Access
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Assessment Configuration
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Update Assessment</h1>
					<p class="text-xs text-slate-500 mt-1">Modify delivery deadlines, submission extensions, and assignment descriptions.</p>
				</div>

				<?php
					$sql = "SELECT name, description, submission_open_time, submission_close_time, submission_file_extension, allow_late_submission
							FROM assessment WHERE assessment_id = '".$_POST['id']."'";
					$result = mysqli_query($db, $sql);
					$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
					
					$myOldCloseTime = (new DateTime($row['submission_close_time']))->format('Y-m-d\TH:i');

					if (isset($errorMessage) && $errorMessage != "") {
						$row['name'] = $myname;
						$row['description'] = $mydesc;
						$row['submission_open_time'] = $myopentime->format('Y-m-d\TH:i');
						$row['submission_close_time'] = $myclosetime->format('Y-m-d\TH:i');
						$row['submission_file_extension'] = $myacceptedfileext;
						$row['allow_late_submission'] = $myallowlatesubmission;
					}
				?>

				<!-- Form -->
				<form onsubmit="prepareform();" action="<?= htmlentities($_SERVER['PHP_SELF']); ?>" method="POST" class="p-8 space-y-6">
					
					<?php if (isset($errorMessage) && $errorMessage != ""): ?>
						<div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 space-y-1">
							<span class="font-bold block">Validation Errors:</span>
							<div><?= $errorMessage ?></div>
						</div>
					<?php endif; ?>

					<input type="hidden" name="id" value="<?= htmlspecialchars($_POST['id']); ?>">
					<input type="hidden" name="old_close_time" value="<?= htmlspecialchars($myOldCloseTime); ?>">

					<!-- Assessment Name -->
					<div class="space-y-1.5">
						<label for="cname" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Assessment Title <span class="text-rose-500">*</span></label>
						<input type="text" id="cname" name="cname" required maxlength="50"
							value="<?= htmlspecialchars($row['name']); ?>"
							class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
					</div>

					<!-- Description (Quill) -->
					<div class="space-y-1.5">
						<label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Instructions &amp; Description</label>
						<div id="desc_rich_editor"><?= $row['description']; ?></div>
						<input type="hidden" id="desc" name="desc">
					</div>

					<!-- Submission Time Windows -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div class="space-y-1.5">
							<label for="open_time" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Opening Window <span class="text-rose-500">*</span></label>
							<input type="datetime-local" id="open_time" name="open_time" required
								value="<?= (new DateTime($row['submission_open_time']))->format('Y-m-d\TH:i'); ?>"
								class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono">
						</div>
						<div class="space-y-1.5">
							<label for="close_time" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Deadline Window <span class="text-rose-500">*</span></label>
							<input type="datetime-local" id="close_time" name="close_time" required
								value="<?= (new DateTime($row['submission_close_time']))->format('Y-m-d\TH:i'); ?>"
								class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono">
						</div>
					</div>

					<!-- Submission Settings -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div class="space-y-1.5">
							<label for="file_ext" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Accepted Artifact Format</label>
							<select id="file_ext" name="file_ext"
								class="min-w-[200px] shrink-0 w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
								<option value="java" <?= ($row['submission_file_extension'] == 'java') ? 'selected' : ''; ?>>Single Java File (.java)</option>
								<option value="py" <?= ($row['submission_file_extension'] == 'py') ? 'selected' : ''; ?>>Single Python File (.py)</option>
								<option value="zip_java" <?= ($row['submission_file_extension'] == 'zip_java') ? 'selected' : ''; ?>>Compressed ZIP Archive (Java)</option>
								<option value="zip_py" <?= ($row['submission_file_extension'] == 'zip_py') ? 'selected' : ''; ?>>Compressed ZIP Archive (Python)</option>
							</select>
						</div>
						<div class="space-y-1.5">
							<label for="late_submission" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Late Submission Policy</label>
							<select id="late_submission" name="late_submission"
								class="min-w-[200px] shrink-0 w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
								<option value="0" <?= ($row['allow_late_submission'] == 0) ? 'selected' : ''; ?>>Disallow: Strict deadline cutoff</option>
								<option value="1" <?= ($row['allow_late_submission'] == 1) ? 'selected' : ''; ?>>Allow: Flagged as late submission</option>
							</select>
						</div>
					</div>

					<!-- Actions -->
					<div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
						<a href="colecturer_assessments.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
							Cancel
						</a>
						<button type="submit" class="px-6 py-2.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl shadow-xs transition duration-150">
							Save Assessment
						</button>
					</div>

				</form>
			</div>

		</div>
	</main>
</body>
</html>
