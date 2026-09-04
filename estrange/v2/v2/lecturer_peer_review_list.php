<?php
	include("_sessionchecker.php");
	include("_config.php");

	// Mengambil ID Peer Review Assessment
	if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
		die("Error: Peer Review Assessment ID not valid.");
	}

	$pr_assessment_id = $_GET['id'];
	$lecturer_id = $_SESSION['user_id'];

	// Query untuk mendapatkan nama assessment dan course
	$sql_info = "SELECT a.name AS assessment_name, 
						c.name AS course_name
				FROM peer_review_assessment pra
				JOIN assessment a ON pra.assessment_id = a.assessment_id
				JOIN course c ON a.course_id = c.course_id
				LEFT JOIN colecturer co ON c.course_id = co.course_id
				WHERE pra.pr_assessment_id = ? AND (c.creator_id = ? OR co.user_id = ?)";

	$stmt_info = $db->prepare($sql_info);
	$stmt_info->bind_param("iii", $pr_assessment_id, $lecturer_id, $lecturer_id);
	$stmt_info->execute();
	$result_info = $stmt_info->get_result();

	if ($result_info->num_rows == 0) {
		die("No data available in table");
	}
	$assessment_info = $result_info->fetch_assoc();
	$stmt_info->close();

	// Ambil hasil game point dari backend
	$game_points_all = generate_peer_review_game_points($db, [
		'pr_assessment_id' => $pr_assessment_id,
		'ignore_deadline' => true,
		'use_cache' => false
	]);

	$game_points = $game_points_all[$pr_assessment_id] ?? [];

	// Query hanya ambil data student & submission, tanpa hitung nilai lagi
	$sql_reviewed_students = "
		SELECT 
			MAX(s.submission_id) AS submission_id,
			u.user_id AS student_id,
			u.username AS reviewed_username,
			u.name AS reviewed_name
		FROM peer_review_submission prs
		JOIN submission s ON prs.submission_to_review = s.submission_id
		JOIN user u ON s.submitter_id = u.user_id
		WHERE prs.pr_assessment_id = ?
		GROUP BY u.user_id, u.username, u.name
		ORDER BY u.username
	";

	$stmt_reviewed = $db->prepare($sql_reviewed_students);
	$stmt_reviewed->bind_param("i", $pr_assessment_id);
	$stmt_reviewed->execute();
	$result_reviewed = $stmt_reviewed->get_result();
	
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
	<title>E-STRANGE: Lecturer Peer Review List</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>

	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

	<!-- DataTables -->
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
	<script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
		.dataTables_wrapper .dataTables_length select,
		.dataTables_wrapper .dataTables_filter input {
			border: 1px solid #cbd5e1;
			border-radius: 0.5rem;
			padding: 0.35rem 0.6rem;
			font-size: 0.8rem;
			outline: none;
		}
		
		@media (max-width: 640px) {
			.dataTables_wrapper .dataTables_length,
			.dataTables_wrapper .dataTables_filter {
				float: none !important;
				text-align: left !important;
				margin-bottom: 0.75rem;
				width: 100%;
			}
			.dataTables_wrapper .dataTables_filter input {
				width: 100% !important;
				margin-left: 0 !important;
				margin-top: 0.25rem;
			}
			.dataTables_wrapper .dataTables_info,
			.dataTables_wrapper .dataTables_paginate {
				float: none !important;
				text-align: center !important;
				margin-top: 0.5rem;
				width: 100%;
			}
			.dataTables_wrapper .dataTables_paginate .paginate_button {
				padding: 0.25rem 0.5rem !important;
				font-size: 0.75rem !important;
			}
		}
		.dataTables_wrapper .dataTables_paginate .paginate_button.current {
			background: #0f172a !important;
			color: #ffffff !important;
			border-radius: 0.5rem;
			border: 1px solid #0f172a !important;
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
	<?php setHeaderLecturer("peer_review", "Peer Review List"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Task Roster
						</span>
						<span class="text-xs font-semibold text-slate-500">
							<?= htmlspecialchars($assessment_info['course_name'] ?? 'Course') ?>
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($assessment_info['assessment_name'] ?? 'Assessment') ?> &mdash; Peer Review Tasks</h1>
					<p class="text-xs text-slate-500 mt-1">Review student evaluation progress, peer review points, and individual feedback submissions.</p>
				</div>
				<div class="flex items-center gap-2">
					<a href="lecturer_peer_review.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
						<span>Back to Peer Reviews</span>
					</a>
				</div>
			</div>

			<!-- Submissions Table Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
				<div class="overflow-x-auto">
					<table id="reviewedSubmissionsTable" class="w-full text-left text-xs" style="width:100%">
						<thead>
							<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
								<th class="py-3 px-3" style="width: 25%;">Reviewed Student</th>
								<th class="py-3 px-3 text-center" style="width: 15%;">Score from Reviews</th>
								<th class="py-3 px-3 text-center" style="width: 15%;">Completed / Assigned</th>
								<th class="py-3 px-3 text-center" style="width: 15%;">Score from Reviewing</th>
								<th class="py-3 px-3 text-center" style="width: 15%;">Total Score</th>
								<th class="py-3 px-3 text-right" style="width: 15%;">Actions</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							<?php if ($result_reviewed && $result_reviewed->num_rows > 0): ?>
								<?php while ($row = $result_reviewed->fetch_assoc()): ?>
									<?php
										$gp = $game_points[$row['student_id']] ?? null;

										$avg = $gp['avg_score'] ?? null;
										$assign = $gp['pr_assign'] ?? 0;
										$comp = $gp['pr_completed'] ?? 0;
										$extra = $gp['extra_points'] ?? null;
										$total = $gp['final_score'] ?? null;

										$is_default_score = ($avg == null);
									?>
									<tr class="hover:bg-slate-50/80 transition-colors">
										
										<!-- Student Name -->
										<td class="py-3.5 px-3">
											<div class="font-bold text-slate-900 font-mono"><?= htmlspecialchars($row['reviewed_username']) ?></div>
											<div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($row['reviewed_name']) ?></div>
										</td>

										<!-- Average Score -->
										<td class="py-3.5 px-3 text-center font-bold text-slate-800">
											<?php if ($is_default_score): ?>
												<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">100*</span>
											<?php else: ?>
												<span class="text-sm"><?= number_format($avg); ?></span>
											<?php endif; ?>
										</td>

										<!-- Assigned / Completed -->
										<td class="py-3.5 px-3 text-center font-mono text-slate-700 text-sm font-medium">
											<span class="font-bold"><?= $comp ?></span> / <?= $assign ?>
										</td>

										<!-- Extra Point -->
										<td class="py-3.5 px-3 text-center font-semibold text-slate-700">
											<?= ($extra !== null ? number_format($extra) : '<span class="text-slate-400">N/A</span>') ?>
										</td>

										<!-- Total Point -->
										<td class="py-3.5 px-3 text-center font-bold">
											<?php if ($comp == 0 && $avg !== null): ?>
												<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">0*</span>
											<?php else: ?>
												<span class="text-sm text-slate-900"><?= ($total !== null ? number_format($total) : '<span class="text-slate-400 font-normal">N/A</span>') ?></span>
											<?php endif; ?>
										</td>

										<!-- Actions -->
										<td class="py-3.5 px-3 text-right">
											<a href="lecturer_view_review_details.php?submission_id=<?= htmlspecialchars($row['submission_id']) ?>"
												class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold bg-[#00A0A5] hover:bg-[#008488] text-white rounded-lg transition shadow-2xs">
												View Reviews
											</a>
										</td>

									</tr>
								<?php endwhile; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<!-- Scoring Legend -->
				<div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-0.5">
					<p><span class="font-bold text-slate-700">100*</span> = Default score applied when no peer reviews were received for this submission.</p>
					<p><span class="font-bold text-slate-700">0*</span> = Zero points awarded because the student did not complete their assigned review tasks.</p>
				</div>
			</div>

		</div>
	</main>

	<script>
		$(document).ready(function() {
			new DataTable('#reviewedSubmissionsTable', {
				responsive: true,
				pageLength: 10,
				lengthMenu: [5, 10, 25, 50],
				language: { search: "_INPUT_", searchPlaceholder: "Search submissions..." }
			});
		});
	</script>
</body>
</html>
<?php
if (isset($stmt_reviewed)) $stmt_reviewed->close();
?>
