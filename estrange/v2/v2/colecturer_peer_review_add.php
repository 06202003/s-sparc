<?php
	include("_sessionchecker.php");
	include("_config.php");
	
	if (isset($_GET['course_id'])) {
        $course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
        $data = getAssessmentsForPeerReview($db, $course_id);
    
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
	$error_message = '';
	$success_message = ''; 

	// Logika Proses Form Submission -> POST
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		// Ambil dan validasi data
		$assessment_id = filter_input(INPUT_POST, 'assessment_id', FILTER_VALIDATE_INT);
		$reviews_per_submission = filter_input(INPUT_POST, 'reviews_per_submission', FILTER_VALIDATE_INT);
		$peer_review_start_time = $_POST['peer_review_start_time'] ?? '';
		$peer_review_close_time = $_POST['peer_review_close_time'] ?? '';

		// Pengecekan / validasi
		if (!$assessment_id || !$reviews_per_submission || !$peer_review_close_time || !$peer_review_start_time) {
			$error_message = "All fields need to be filled";
		} else {
			$db->begin_transaction();
			try {
				// Mengambil submission_close_time dari database
				$sql_get_close_time = "SELECT submission_close_time FROM assessment WHERE assessment_id = ?";
				$stmt_time = $db->prepare($sql_get_close_time);
				$stmt_time->bind_param("i", $assessment_id);
				$stmt_time->execute();
				$result_time = $stmt_time->get_result();
				if ($result_time->num_rows !== 1) {
					throw new Exception("Assessment not found");
				}
				$submission_close_time = $result_time->fetch_assoc()['submission_close_time'];
				$stmt_time->close();

				// Melakukan Validasi Waktu
				$submission_close_time_dt = new DateTime($submission_close_time);
				$new_start_time_dt = new DateTime($peer_review_start_time);
				$new_close_time_dt = new DateTime($peer_review_close_time);
				//start time tidak boleh lebih kecil dari submission close time
				if ($new_start_time_dt < $submission_close_time_dt) {
					throw new Exception("Error: Peer review start time must be after the submission close time (" . $submission_close_time_dt->format('d M Y, H:i') . ").");
				}
				//close time tidak boleh lebih kecil dari start time
				if ($new_close_time_dt <= $new_start_time_dt) {
					throw new Exception("Error: Peer review close time must be after the start time (" . $new_start_time_dt->format('d M Y, H:i') . ").");
				}

				// INSERT ke peer_review_assessment
				$sql_insert_assessment = "INSERT INTO peer_review_assessment(assessment_id, peer_review_start_time, reviews_per_submission, peer_review_close_time, is_pr_assessment_generated) VALUES (?, ?, ?, ?, 0)";
				$stmt_insert = $db->prepare($sql_insert_assessment);
				$stmt_insert->bind_param("isis", $assessment_id, $peer_review_start_time, $reviews_per_submission, $peer_review_close_time);
				$stmt_insert->execute();

				if ($stmt_insert->affected_rows !== 1) {
					throw new Exception("Failed to add new Peer Review Assessment");
				}
				$stmt_insert->close();

				// Commit transaksi
				$db->commit();
				header("Location: colecturer_peer_review.php?status=add_success"); 
				exit();

			} catch (Exception $e) {
				$db->rollback();
				$error_message = "Error Found: " . $e->getMessage();
			}
		}
	}
	
	// remove all old notifications (longer than three days)
	$sql = "DELETE FROM game_unobserved_notif WHERE  DATEDIFF(CURRENT_TIMESTAMP,time_created) > 3;";
	$db->query($sql);

	if($_SERVER["REQUEST_METHOD"] == "POST") {
		// for deleting a course
		if(isset($_POST['id']) == true){
			// course id
			$id = mysqli_real_escape_string($db,$_POST['id']);
			// delete that course data from game_course
			$sql = "DELETE FROM game_course WHERE course_id = '$id'";
			if ($db->query($sql) === TRUE) {
			  $sql = "DELETE FROM game_course WHERE course_id = '$id'";
			  if ($db->query($sql) === TRUE) {
				  // if removed well, redirect to dashboard
				  header('Location: lecturer_dashboard.php');
				  exit;
			  }else{
				echo "<script>alert('The course cannot be deleted since it either has been assigned with some assessments or has been enrolled by some students');</script>";
			  }
			} else {
			  echo "<script>alert('The course cannot be deleted since it either has been assigned with some assessments or has been enrolled by some students');</script>";
			}
		}
	}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Add Peer Review</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>

	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

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
	<?php
		setHeaderStudent("peer_review", "Add Peer Review");
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
							Peer Review Configuration
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Create Peer Review Task</h1>
					<p class="text-xs text-slate-500 mt-1">Configure peer evaluation periods, review quotas per submission, and deadline schedules.</p>
				</div>

				<!-- Form -->
				<form action="colecturer_peer_review_add.php" method="POST" class="p-8 space-y-6">
					
					<?php if (!empty($error_message)): ?>
						<div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 space-y-1">
							<span class="font-bold block">Validation Errors:</span>
							<div><?= htmlspecialchars($error_message); ?></div>
						</div>
					<?php endif; ?>

					<!-- Course Selection -->
					<div class="space-y-1.5">
						<label for="course_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Select Course <span class="text-rose-500">*</span></label>
						<select id="course_id" name="course_id" required
							class="min-w-[200px] shrink-0 w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
							<option value="" disabled selected>Choose a course...</option>
							<?php
								$colecturer_id = $_SESSION['user_id'];
								$sql_courses = "SELECT DISTINCT c.course_id, c.name 
												FROM course c 
												JOIN assessment a ON c.course_id = a.course_id 
												JOIN colecturer co ON c.course_id = co.course_id
												WHERE co.user_id = ? AND a.allow_late_submission = 0"; 
								$stmt_courses = $db->prepare($sql_courses);
								$stmt_courses->bind_param("i", $colecturer_id);
								$stmt_courses->execute();
								$result_courses = $stmt_courses->get_result();
								while ($course = $result_courses->fetch_assoc()) {
									echo '<option value="' . $course['course_id'] . '">' . htmlspecialchars($course['name']) . '</option>';
								}
								$stmt_courses->close();
							?>
						</select>
					</div>

					<!-- Assessment Selection -->
					<div class="space-y-1.5">
						<label for="assessment_id" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Target Assessment <span class="text-rose-500">*</span></label>
						<select id="assessment_id" name="assessment_id" required disabled
							class="min-w-[200px] shrink-0 w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition disabled:opacity-50 disabled:cursor-not-allowed">
							<option value="" selected>Choose course first...</option>
						</select>
						<p class="text-[11px] text-slate-400">Only lists assessments without existing peer review tasks and strict submission deadlines.</p>
					</div>

					<!-- Start Time Window -->
					<div class="space-y-1.5">
						<label for="peer_review_start_time" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Review Opening Window <span class="text-rose-500">*</span></label>
						<input type="datetime-local" id="peer_review_start_time" name="peer_review_start_time" required
							class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono">
						<p class="text-[11px] text-rose-500">Start time must be scheduled after the assessment deadline.</p>
					</div>

					<!-- Reviews per Submission & Close Time -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div class="space-y-1.5">
							<label for="reviews_per_submission" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Reviews Per Submission <span class="text-rose-500">*</span></label>
							<input type="number" id="reviews_per_submission" name="reviews_per_submission" min="1" required placeholder="e.g. 3"
								class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono">
						</div>
						<div class="space-y-1.5">
							<label for="peer_review_close_time" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Review Deadline Window <span class="text-rose-500">*</span></label>
							<input type="datetime-local" id="peer_review_close_time" name="peer_review_close_time" required
								class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono">
						</div>
					</div>

					<!-- Actions -->
					<div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
						<a href="colecturer_peer_review.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
							Cancel
						</a>
						<button type="submit" class="px-6 py-2.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl shadow-xs transition duration-150">
							Create Peer Review
						</button>
					</div>

				</form>
			</div>

		</div>
	</main>

	<script>
		$(document).ready(function() {
			$('#course_id').on('change', function() {
				var courseId = $(this).val();
				var assessmentDropdown = $('#assessment_id');
				
				assessmentDropdown.html('<option>Loading assessments...</option>').prop('disabled', true);
				$('#peer_review_start_time').val('');

				if (courseId) {
					$.ajax({
						url: 'colecturer_peer_review_add.php',
						type: 'GET',
						data: { course_id: courseId },
						dataType: 'json',
						success: function(assessments) {
							assessmentDropdown.empty().prop('disabled', false);
							assessmentDropdown.append('<option value="" disabled selected>Choose Assessment...</option>');
							if (assessments.length > 0) {
								$.each(assessments, function(key, assessment) {
									assessmentDropdown.append($('<option>', {
										value: assessment.assessment_id,
										text: assessment.name,
										'data-closetime': assessment.submission_close_time
									}));
								});
							} else {
								assessmentDropdown.append('<option disabled>No valid assessments found</option>').prop('disabled', true);
							}
						},
						error: function() {
							assessmentDropdown.html('<option>Failed to load assessments</option>').prop('disabled', true);
						}
					});
				} else {
					assessmentDropdown.html('<option>Choose course first...</option>').prop('disabled', true);
				}
			});

			$('#assessment_id').on('change', function() {
				const selectedOption = $(this).find('option:selected');
				const closeTime = selectedOption.data('closetime');

				if (closeTime) {
					const formattedCloseTime = closeTime.slice(0, 16).replace(' ', 'T');
					$('#peer_review_start_time').val(formattedCloseTime);
				} else {
					$('#peer_review_start_time').val('');
				}
			});
		});
	</script>
</body>
</html>
