<?php
	include("_sessionchecker.php");
	include("_config.php");
	
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

	$error_message = '';
	$pr_assessment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
	$existing_data = null;

	if (!$pr_assessment_id) {
		die("Peer Review Task ID not valid");
	}

	// Mengambil semua data yang ada di database untuk ditampilkan di form
	$sql_fetch = "SELECT 
					pra.assessment_id, 
					pra.reviews_per_submission, 
					pra.peer_review_close_time,
					pra.peer_review_start_time,
					pra.is_pr_assessment_generated,
					a.course_id,
					a.name AS assessment_name,
					a.submission_close_time,
					c.name AS course_name
				FROM peer_review_assessment pra
				JOIN assessment a ON pra.assessment_id = a.assessment_id
				JOIN course c ON a.course_id = c.course_id
				WHERE pra.pr_assessment_id = ?";
				
	$stmt_fetch = $db->prepare($sql_fetch);
	$stmt_fetch->bind_param("i", $pr_assessment_id);
	$stmt_fetch->execute();
	$result_fetch = $stmt_fetch->get_result();

	if ($result_fetch->num_rows !== 1) {
		die("Peer Review Task not found");
	}

	$existing_data = $result_fetch->fetch_assoc();
	$stmt_fetch->close();

	// Logika Proses Form Update (POST Request)
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		// Ambil data hanya dari field yang bisa diedit
		$reviews_per_submission = filter_input(INPUT_POST, 'reviews_per_submission', FILTER_VALIDATE_INT);
		$peer_review_start_time = $_POST['peer_review_start_time'] ?? '';
		$peer_review_close_time = $_POST['peer_review_close_time'] ?? '';
		$pr_assessment_id_post = filter_input(INPUT_POST, 'pr_assessment_id', FILTER_VALIDATE_INT); 

		// Ambil submission_close_time dari data yang sudah dipilih
		$submission_close_time_dt = new DateTime($existing_data['submission_close_time']);
		$new_start_time_dt = new DateTime($peer_review_start_time);
		$new_close_time_dt = new DateTime($peer_review_close_time);

		// start time peer review harus setelah submission close time
		if ($new_start_time_dt < $submission_close_time_dt) {
			$error_message = "Error: Peer review start time must be after the submission close time (" . $submission_close_time_dt->format('d M Y, H:i') . ").";
		} 
		//close time tidak boleh lebih kecil dari start time
		else if ($new_close_time_dt <= $new_start_time_dt) {
			$error_message = "Error: Peer review close time must be after the start time (" . $new_start_time_dt->format('d M Y, H:i') . ").";
		}
		// Validasi input dasar
		else if (!$reviews_per_submission || !$peer_review_close_time || !$peer_review_start_time || !$pr_assessment_id_post) {
			$error_message = "All fields need to be filled";
		} 
		// Jika semua validasi lolos
		else {
			$db->begin_transaction();
			try {
				$sql_update = "UPDATE peer_review_assessment SET 
									peer_review_start_time = ?, 
									reviews_per_submission = ?, 
									peer_review_close_time = ? 
							WHERE 
									pr_assessment_id = ?";
				
				$stmt_update = $db->prepare($sql_update);
				$stmt_update->bind_param("sisi", $peer_review_start_time, $reviews_per_submission, $peer_review_close_time, $pr_assessment_id_post);
				$stmt_update->execute();

				if ($db->error) {
					throw new Exception("Failed to update: " . $db->error);
				}
				$stmt_update->close();

				$db->commit();
				header("Location: colecturer_peer_review.php?status=edit_success");
				exit();

			} catch (Exception $e) {
				$db->rollback();
				$error_message = "Error Found: " . $e->getMessage();
				// Muat ulang data yang ada jika terjadi error, agar form menampilkan data lama
				$existing_data = $result_fetch->fetch_assoc();
			}
		}
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Edit Peer Review</title>
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
	<?php
		setHeaderStudent("peer_review", "Edit Peer Review");
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
							Task Editor
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Edit Peer Review Task</h1>
					<p class="text-xs text-slate-500 mt-1">Update peer review schedule parameters and review deadline windows.</p>
				</div>

				<!-- Form -->
				<form action="colecturer_peer_review_edit.php?id=<?= $pr_assessment_id; ?>" method="POST" class="p-8 space-y-6">
					<input type="hidden" name="pr_assessment_id" value="<?= $pr_assessment_id; ?>">

					<?php if (!empty($error_message)): ?>
						<div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 space-y-1">
							<span class="font-bold block">Validation Errors:</span>
							<div><?= htmlspecialchars($error_message); ?></div>
						</div>
					<?php endif; ?>

					<!-- Readonly Context Info -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div class="space-y-1.5">
							<label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Course</label>
							<input type="text" value="<?= htmlspecialchars($existing_data['course_name']); ?>" disabled
								class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 cursor-not-allowed">
						</div>
						<div class="space-y-1.5">
							<label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Assessment</label>
							<input type="text" value="<?= htmlspecialchars($existing_data['assessment_name']); ?>" disabled
								class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 cursor-not-allowed">
						</div>
					</div>
					<p class="text-[11px] text-slate-500">
						Submission deadline for this assignment: <span class="font-mono font-bold text-slate-700"><?= (new DateTime($existing_data['submission_close_time']))->format('d M Y, H:i'); ?></span>
					</p>

					<!-- Review Opening Window -->
					<div class="space-y-1.5">
						<label for="peer_review_start_time" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Review Opening Window <span class="text-rose-500">*</span></label>
						<?php
							$start_time_formatted = (new DateTime($existing_data['peer_review_start_time']))->format('Y-m-d\TH:i');
							$isGenerated = ($existing_data['is_pr_assessment_generated'] == 1);
						?>
						<input type="datetime-local" id="peer_review_start_time" name="peer_review_start_time"
							value="<?= $start_time_formatted; ?>" <?= $isGenerated ? 'readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs text-slate-600 font-mono cursor-not-allowed"' : 'class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono"'; ?>>
						<?php if ($isGenerated): ?>
							<p class="text-[11px] text-amber-600">Review task allocations have already been generated. Start time is locked.</p>
						<?php else: ?>
							<p class="text-[11px] text-rose-500">Start time must be scheduled after the assignment submission close time.</p>
						<?php endif; ?>
					</div>

					<!-- Reviews per Submission & Close Time -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div class="space-y-1.5">
							<label for="reviews_per_submission" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Reviews Per Submission <span class="text-rose-500">*</span></label>
							<input type="number" id="reviews_per_submission" name="reviews_per_submission" min="1" required
								value="<?= htmlspecialchars($existing_data['reviews_per_submission']); ?>"
								<?= $isGenerated ? 'readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 font-mono cursor-not-allowed"' : 'class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono"'; ?>>
						</div>
						<div class="space-y-1.5">
							<label for="peer_review_close_time" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Review Deadline Window <span class="text-rose-500">*</span></label>
							<?php
								$close_time_formatted = (new DateTime($existing_data['peer_review_close_time']))->format('Y-m-d\TH:i');
							?>
							<input type="datetime-local" id="peer_review_close_time" name="peer_review_close_time" required
								value="<?= $close_time_formatted; ?>"
								class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono">
						</div>
					</div>

					<!-- Actions -->
					<div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
						<a href="colecturer_peer_review.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
							Cancel
						</a>
						<button type="submit" class="px-6 py-2.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl shadow-xs transition duration-150">
							Save Changes
						</button>
					</div>

				</form>
			</div>

		</div>
	</main>
</body>
</html>
