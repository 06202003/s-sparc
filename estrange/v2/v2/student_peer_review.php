<?php
    date_default_timezone_set('Asia/Jakarta');
	// default template
	include("_sessionchecker.php");
	include("_config.php");

    $is_colecturer = false;

	if ($_SESSION['role'] == 'student') {

		$user_id = $_SESSION['user_id'];

		$sql = "SELECT colecturer_id FROM colecturer WHERE user_id = $user_id LIMIT 1";
		$result = mysqli_query($db, $sql);

		if (mysqli_num_rows($result) > 0) {
			$is_colecturer = true;
		}
	}

	$student_id = $_SESSION['user_id'];

	$sql = "SELECT course_id FROM enrollment WHERE student_id = ? ORDER BY course_id ASC";
	$stmt = $db->prepare($sql);
	$stmt->bind_param("i", $student_id);
	$stmt->execute();
	$result = $stmt->get_result();

	if($result->num_rows == 0){
    // Student nggak punya course sama sekali
    $has_course = false;
    $selected_course_id = null;
    $assessments = [];
    $game_points = [];
	} else {
		// Mengambil course paling kecil untuk default
		$sql = "SELECT c.course_id 
				FROM enrollment e
				JOIN course c ON c.course_id = e.course_id
				WHERE e.student_id = ?
				ORDER BY c.course_id ASC
				LIMIT 1";

		$stmt = $db->prepare($sql);
		$stmt->bind_param("i", $student_id);
		$stmt->execute();
		$rs = $stmt->get_result();

		$default_course_id = 0;
		if ($row = $rs->fetch_assoc()) {
			$default_course_id = $row['course_id'];
		}
		$stmt->close();

		// Mengambil GET course_id
		$selected_course_id = $_GET['course_id'] ?? null;

		// Jika NULL â†’ pakai default_course_id
		if ($selected_course_id === null || $selected_course_id === "") {
			$selected_course_id = $default_course_id;
		}

		// Mengambil GET active_tab atau default 'reviewAssessment'
		$active_tab = $_GET['active_tab'] ?? 'reviewAssessment';


		// Generate leaderboard statis
		$leaderboard_data = generate_student_leaderboard_points($db, $selected_course_id);

		// Mengambil list assessment PR untuk tabs
		$sql = "
			SELECT pra.pr_assessment_id, a.name
			FROM peer_review_assessment pra
			JOIN assessment a ON pra.assessment_id = a.assessment_id
			WHERE a.course_id = ?
			AND pra.peer_review_close_time <= NOW()
		";
		$stmt = $db->prepare($sql);
		$stmt->bind_param("i", $selected_course_id);
		$stmt->execute();
		$rs = $stmt->get_result();

		$assessments = [];
		while ($row = $rs->fetch_assoc()) {
			$assessments[$row['pr_assessment_id']] = $row['name'];
		}
		$stmt->close();	
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Student Peer Review</title>
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

	<!-- SweetAlert2 -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<!-- Notyf library -->
	<link rel="stylesheet" href="strange_html_layout_additional_files/notyf.min.css">
	<script src="strange_html_layout_additional_files/notyf.min.js"></script>
	
	<!-- Select2 -->
	<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
	<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
	
	<script type="text/javascript">
		function loadGameNotif(){
			var notyf = new Notyf({
				duration: 0,
				position: { x: 'right', y: 'top' },
				dismissible: true
			});
			
			<?php
				$sqlt = "SELECT game_unobserved_notif.notification_id, game_unobserved_notif.message, game_student_course.course_id, course.name AS course_name  
						FROM game_unobserved_notif 
						INNER JOIN game_student_course ON game_student_course.gs_id = game_unobserved_notif.gs_id 
						INNER JOIN course ON course.course_id = game_student_course.course_id 
						INNER JOIN game_course ON game_course.course_id = game_student_course.course_id 
						WHERE game_student_course.student_id = '".$_SESSION['user_id']."' 
						AND game_course.is_active = '1' 
						AND game_student_course.is_participating = '1' 
						ORDER BY game_unobserved_notif.time_created ASC
						LIMIT 3";
				$rt = mysqli_query($db,$sqlt);
				$i = 0;
				if ($rt) {
					while($row = $rt->fetch_assoc()) {
						echo "const notification".$i." = notyf.success(\"[".addslashes($row['course_name'])."] ".addslashes($row['message'])."<br />Click here for details!\");
							  notification".$i.".on('click', ({target, event}) => {window.location.href = 'student_game.php?id=".$row['course_id']."';});";
						$sql = "DELETE FROM game_unobserved_notif WHERE notification_id = '".$row['notification_id']."'";
						$db->query($sql);
						$i++;
					}
				}
			?>
		}

		function showReviewModal(title, content) {
			Swal.fire({
				title: title,
				html: `<div class="text-left text-xs text-slate-700 leading-relaxed max-h-96 overflow-y-auto whitespace-pre-line p-2">${content}</div>`,
				confirmButtonText: 'Close',
				confirmButtonColor: '#0f172a',
				width: '550px'
			});
		}

		function switchTab(tabId) {
			document.querySelectorAll('.tab-content-panel').forEach(el => el.classList.add('hidden'));
			document.querySelectorAll('.tab-btn').forEach(btn => {
				btn.classList.remove('bg-slate-900', 'text-white', 'shadow-xs');
				btn.classList.add('text-slate-600', 'hover:bg-slate-100');
			});
			
			const targetPanel = document.getElementById(tabId);
			if (targetPanel) targetPanel.classList.remove('hidden');

			const activeBtn = document.getElementById('btn-' + tabId);
			if (activeBtn) {
				activeBtn.classList.add('bg-slate-900', 'text-white', 'shadow-xs');
				activeBtn.classList.remove('text-slate-600', 'hover:bg-slate-100');
			}
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col" onload="loadGameNotif()">
	<?php setHeaderStudent("peer_review", "Student Peer Review"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Peer Review
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Collaborative Evaluation
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Student Peer Review Workspace</h1>
					<p class="text-xs text-slate-500 mt-1">Review assigned peer solutions, examine received evaluations, and monitor evaluation leaderboards.</p>
				</div>
				<?php if ($is_colecturer): ?>
					<div>
						<a href="colecturer_peer_review.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl transition shadow-2xs">
							<span>Co-Lecturer Peer Review Portal</span>
							<svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
						</a>
					</div>
				<?php endif; ?>
			</div>

			<!-- Tab Navigation Bar -->
			<div class="flex items-center gap-2 bg-white/60 backdrop-blur-md p-1.5 rounded-2xl border border-slate-200/80 shadow-2xs max-w-xl">
				<button 
					id="btn-reviewAssessment" 
					onclick="switchTab('reviewAssessment')" 
					class="tab-btn flex-1 py-2 px-3 text-sm font-bold rounded-xl transition <?= ($active_tab == 'reviewAssessment') ? 'bg-[#00A0A5] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100' ?>"
				>
					Review Tasks
				</button>
				<button 
					id="btn-assessmentReviewed" 
					onclick="switchTab('assessmentReviewed')" 
					class="tab-btn flex-1 py-2 px-3 text-sm font-bold rounded-xl transition <?= ($active_tab == 'assessmentReviewed') ? 'bg-[#00A0A5] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100' ?>"
				>
					Reviews Received
				</button>
				<button 
					id="btn-leaderboard" 
					onclick="switchTab('leaderboard')" 
					class="tab-btn flex-1 py-2 px-3 text-sm font-bold rounded-xl transition <?= ($active_tab == 'leaderboard') ? 'bg-[#00A0A5] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100' ?>"
				>
					Leaderboard
				</button>
			</div>

			<!-- Tab 1: Review Tasks -->
			<div id="reviewAssessment" class="tab-content-panel <?= ($active_tab == 'reviewAssessment') ? '' : 'hidden' ?> space-y-4">
				<?php
					$sql_count_pending = "SELECT COUNT(*) AS pending_count
						FROM peer_review_submission prs
						JOIN peer_review_assessment pra ON prs.pr_assessment_id = pra.pr_assessment_id
						WHERE reviewer_id = ? 
						AND review_status = 0
						AND pra.peer_review_close_time > NOW()";
					$stmt_pending = $db->prepare($sql_count_pending);
					$stmt_pending->bind_param("i", $_SESSION['user_id']);
					$stmt_pending->execute();
					$pending_result = $stmt_pending->get_result();
					$pending = $pending_result->fetch_assoc()['pending_count'] ?? 0;
					$stmt_pending->close();

					if ($pending > 0) {
						echo '<div class="rounded-xl border border-indigo-200 bg-indigo-50/80 p-4 text-xs text-indigo-900 flex items-center justify-between gap-4">
								<div class="flex items-center gap-2">
									<svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
									<span><strong>Pending Reviews:</strong> You have <b>'.$pending.'</b> peer review task(s) awaiting completion. Finish all tasks to maximize your bonus game points.</span>
								</div>
							  </div>';
					}
				?>

				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
					<div class="overflow-x-auto">
						<table id="studentReviewAssessment" class="w-full text-left text-xs" style="width:100%">
							<thead>
								<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
									<th class="py-3 px-3" style="width: 50%;">Course &amp; Assessment</th>
									<th class="py-3 px-3">Deadline</th>
									<th class="py-3 px-3 text-center" style="width: 100px;">Given Score</th>
									<th class="py-3 px-3 text-right" style="width: 140px;">Status / Action</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-100">
								<?php
									$sql_review = "SELECT a.name AS assessment_name, c.name AS course_name, pra.peer_review_close_time,
										prs.review_status, prs.pr_submission_id, pr.review_score, pr.review_description
										FROM peer_review_submission prs
										JOIN peer_review_assessment pra ON prs.pr_assessment_id = pra.pr_assessment_id
										JOIN assessment a ON pra.assessment_id = a.assessment_id
										JOIN course c ON a.course_id = c.course_id
										LEFT JOIN peer_review pr ON prs.pr_submission_id = pr.pr_submission_id
										WHERE prs.reviewer_id = ? 
										ORDER BY pra.peer_review_close_time ASC";
									$stmt_r = $db->prepare($sql_review);
									$stmt_r->bind_param("i", $_SESSION['user_id']);
									$stmt_r->execute();
									$res_r = $stmt_r->get_result();

									if ($res_r && $res_r->num_rows > 0) {
										while ($row = $res_r->fetch_assoc()) {
											$descClean = trim($row['review_description'] ?? '');
											$descClean = preg_replace('/<p>\s*<br>\s*<\/p>/', '', $descClean);
											$descCleanEscaped = addslashes(htmlspecialchars(strip_tags($descClean)));
											$titleEscaped = addslashes(htmlspecialchars($row['course_name'] . ' - ' . $row['assessment_name']));

											echo '<tr class="hover:bg-slate-50/80 transition-colors">';
											echo '<td class="py-3.5 px-3 font-bold text-slate-900">'.htmlspecialchars($row['course_name'].' - '.$row['assessment_name']).'</td>';
											echo '<td class="py-3.5 px-3 font-mono text-slate-600">'.htmlspecialchars($row['peer_review_close_time']).'</td>';
											echo '<td class="py-3.5 px-3 text-center font-bold font-mono text-slate-800">'.(isset($row['review_score']) ? htmlspecialchars($row['review_score']) : '<span class="text-slate-400 font-normal">-</span>').'</td>';
											
											echo '<td class="py-3.5 px-3 text-right">';
											$now = new DateTime();
											$close_time = new DateTime($row['peer_review_close_time']);

											if ($row['review_status'] == 1) {
												if (!empty($descClean)) {
													echo '<button type="button" onclick="showReviewModal(\''.$titleEscaped.'\', \''.$descCleanEscaped.'\')" class="px-2.5 py-1 text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 rounded-lg transition">View Review</button>';
												} else {
													echo '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700">Submitted</span>';
												}
											} else {
												if ($now > $close_time) {
													echo '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-400">Closed</span>';
												} else {
													echo '<a href="student_review_submit.php?id='.htmlspecialchars($row['pr_submission_id']).'" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold bg-[#00A0A5] text-white hover:bg-slate-800 rounded-lg transition shadow-2xs">Submit Review</a>';
												}
											}
											echo '</td>';
											echo '</tr>';
										}
									}
									$stmt_r->close();
								?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Tab 2: Reviews Received -->
			<div id="assessmentReviewed" class="tab-content-panel <?= ($active_tab == 'assessmentReviewed') ? '' : 'hidden' ?>">
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
					<div class="overflow-x-auto">
						<table id="studentReviewedSubmission" class="w-full text-left text-xs" style="width:100%">
							<thead>
								<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
									<th class="py-3 px-3" style="width: 50%;">Course &amp; Assessment</th>
									<th class="py-3 px-3 text-center" style="width: 140px;">Reviews Received</th>
									<th class="py-3 px-3 text-center" style="width: 140px;">Average Score</th>
									<th class="py-3 px-3 text-right" style="width: 140px;">Actions</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-100">
								<?php
									$sql_feedback = "SELECT a.name as assessment_name, c.name as course_name, s.submission_id,
										pra.peer_review_close_time, COUNT(pr.peer_review_id) as total_reviews, AVG(pr.review_score) as avg_score
										FROM peer_review pr
										JOIN peer_review_submission prs ON pr.pr_submission_id = prs.pr_submission_id
										JOIN peer_review_assessment pra ON prs.pr_assessment_id = pra.pr_assessment_id
										JOIN submission s ON prs.submission_to_review = s.submission_id
										JOIN assessment a ON s.assessment_id = a.assessment_id
										JOIN course c ON a.course_id = c.course_id
										WHERE s.submitter_id = ?
										GROUP BY s.submission_id, a.name, c.name, pra.peer_review_close_time
										ORDER BY c.name, a.name";
									$stmt_f = $db->prepare($sql_feedback);
									$stmt_f->bind_param("i", $_SESSION['user_id']);
									$stmt_f->execute();
									$res_f = $stmt_f->get_result();

									if ($res_f && $res_f->num_rows > 0) {
										while ($row = $res_f->fetch_assoc()) {
											$close_time = new DateTime($row['peer_review_close_time']);
											$now = new DateTime();
											$isOver = ($now > $close_time);

											echo '<tr class="hover:bg-slate-50/80 transition-colors">';
											echo '<td class="py-3.5 px-3 font-bold text-slate-900">'.htmlspecialchars($row['course_name'].' - '.$row['assessment_name']).'</td>';
											echo '<td class="py-3.5 px-3 text-center">'.($isOver ? (int)$row['total_reviews'] : '<span class="text-slate-400 italic">Pending Close</span>').'</td>';
											echo '<td class="py-3.5 px-3 text-center font-mono font-bold text-slate-800">'.($isOver ? ($row['avg_score'] ? number_format($row['avg_score'], 1) : 'N/A') : '<span class="text-slate-400 font-normal italic">-</span>').'</td>';
											echo '<td class="py-3.5 px-3 text-right">';
											if ($isOver) {
												echo '<a href="student_view_review_details.php?submission_id='.htmlspecialchars($row['submission_id']).'" class="px-2.5 py-1 text-[11px] font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-lg transition">View Details</a>';
											} else {
												echo '<button disabled class="px-2.5 py-1 text-[11px] font-semibold bg-slate-50 text-slate-300 rounded-lg cursor-not-allowed">Pending</button>';
											}
											echo '</td>';
											echo '</tr>';
										}
									}
									$stmt_f->close();
								?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Tab 3: Leaderboard -->
			<div id="leaderboard" class="tab-content-panel <?= ($active_tab == 'leaderboard') ? '' : 'hidden' ?> space-y-6">
				
				<!-- Filter Dropdown Card -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
					<div>
						<h3 class="text-sm font-bold text-slate-900">Course Ranking Selector</h3>
						<p class="text-xs text-slate-500">Filter peer review performance metrics by course section.</p>
					</div>
					<div>
						<select id="courseSelect" class="min-w-[200px] shrink-0 px-3.5 py-2 text-sm font-semibold font-semibold text-slate-800 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition">
							<?php 
								$student_id = $_SESSION['user_id'];
								$sql = "SELECT c.course_id, c.name FROM enrollment e JOIN course c ON c.course_id = e.course_id WHERE e.student_id = ? ORDER BY c.course_id ASC";
								$stmt_c = $db->prepare($sql);
								$stmt_c->bind_param("i", $student_id);
								$stmt_c->execute();
								$rs_c = $stmt_c->get_result();
								if ($rs_c && $rs_c->num_rows > 0) {
									while ($c = $rs_c->fetch_assoc()) {
										echo '<option value="'. $c['course_id'] .'" '. ($selected_course_id == $c['course_id'] ? 'selected' : '') .'>'. htmlspecialchars($c['name']) .'</option>';
									}
								} else {
									echo '<option value="" disabled selected>No courses available</option>';
								}
								$stmt_c->close();
							?>
						</select>
					</div>
				</div>

				<!-- Main Leaderboard -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
					<h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-[11px] text-slate-500">Overall Course Leaderboard</h3>
					<div class="overflow-x-auto">
						<table id="mainLeaderboardTable" class="w-full text-left text-xs" style="width: 100%;">
							<thead>
								<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
									<th class="py-3 px-3 text-center" style="width: 60px;">Rank</th>
									<th class="py-3 px-3">Student Name</th>
									<th class="py-3 px-3 text-center" style="width: 140px;">Peer Review Score</th>
								</tr>
							</thead>
							<tbody id="mainLeaderboardBody" class="divide-y divide-slate-100">
								<tr>
									<td class="py-4 text-center text-slate-400">&mdash;</td>
									<td class="py-4 text-center text-slate-400">Loading ranking data...</td>
									<td class="py-4 text-center text-slate-400">&mdash;</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Task-Wise Leaderboard -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
					<h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-[11px] text-slate-500">Task-Wise Performance Breakdown</h3>
					<div id="assessmentTabs" class="flex flex-wrap gap-2 border-b border-slate-100 pb-3"></div>
					<div id="assessmentTabsContent"></div>
				</div>

				<!-- Scoring Guide Card -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-3">
					<h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider text-slate-500">Scoring Methodology</h4>
					<div class="text-xs text-slate-600 leading-relaxed space-y-1.5">
						<p><strong>Score From Reviews:</strong> Average rating score received from peer evaluations.</p>
						<p><strong>Score From Reviewing:</strong> Percentage of assigned reviews successfully completed, scaled to 100.</p>
						<p><strong>Peer Review Score:</strong> Combined sum of reviews received score and completion score.</p>
						<p class="text-slate-500 italic">Note: If you do not complete assigned reviews, your overall peer review score for that task defaults to 0.</p>
					</div>
				</div>

			</div>

		</div>
	</main>

	<script>
		$(document).ready(function() {
			new DataTable('#studentReviewAssessment', {
				responsive: true,
				order: [[1, 'desc']],
				pageLength: 10,
				language: { search: "_INPUT_", searchPlaceholder: "Search review tasks..." }
			});

			new DataTable('#studentReviewedSubmission', {
				responsive: true,
				order: [[0, 'asc']],
				pageLength: 10,
				language: { search: "_INPUT_", searchPlaceholder: "Search submissions..." }
			});
		});

		document.addEventListener("DOMContentLoaded", function () {
			let selectedCourse = <?= json_encode($selected_course_id); ?>;
			let defaultCourse  = <?= json_encode($default_course_id); ?>;

			if (!selectedCourse || selectedCourse == 0) {
				window.location.href = "student_peer_review.php?course_id=" + defaultCourse;
				return;
			}

			$('#courseSelect').select2({ placeholder: "Select course...", width: '220px' }).on('change', function() {
				let cid = $(this).val();
				window.location.href = "student_peer_review.php?course_id=" + cid + "&active_tab=leaderboard";
			});

			let response = <?= json_encode($leaderboard_data ?? ['total' => [], 'per_assessment' => []]); ?>;
			let assessmentList = <?= json_encode($assessments ?? []); ?>;

			renderMainLeaderboard(response.total || []);
			renderAssessmentTabs(response.per_assessment || {}, assessmentList);
		});

		function renderMainLeaderboard(data) {
			let tbody = "";
			let rank = 1;

			if (data.length > 0) {
				data.forEach(row => {
					tbody += `
						<tr class="hover:bg-slate-50/80 transition-colors">
							<td class="py-3 px-3 text-center font-bold text-slate-700">${rank++}</td>
							<td class="py-3 px-3 font-medium text-slate-900">${row.username} - ${row.name}</td>
							<td class="py-3 px-3 text-center font-mono font-bold text-slate-800">${row.total_points}</td>
						</tr>
					`;
				});
			} else {
				tbody = `<tr><td class="py-4 text-center text-slate-400">&mdash;</td><td class="py-4 text-center text-slate-400">No student rankings recorded yet.</td><td class="py-4 text-center text-slate-400">&mdash;</td></tr>`;
			}

			document.getElementById("mainLeaderboardBody").innerHTML = tbody;
			if ($.fn.DataTable.isDataTable('#mainLeaderboardTable')) {
				$('#mainLeaderboardTable').DataTable().destroy();
			}
			new DataTable('#mainLeaderboardTable', {
				responsive: true,
				order: [[0, 'asc']],
				pageLength: 5,
				lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]]
			});
		}

		function renderAssessmentTabs(data, assessments) {
			let tabHeader = "";
			let tabContent = "";
			let first = true;

			const keys = Object.keys(assessments);
			if (keys.length === 0) {
				document.getElementById("assessmentTabs").innerHTML = `<span class="text-xs text-slate-400">No closed peer review assessments available yet.</span>`;
				return;
			}

			keys.forEach(pr_id => {
				const activeBtnClass = first ? 'bg-[#00A0A5] text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200';
				tabHeader += `
					<button onclick="switchAssessmentSubTab('ass_${pr_id}')" id="btn_ass_${pr_id}" class="sub-tab-btn px-3 py-1.5 text-sm font-semibold rounded-lg transition ${activeBtnClass}">
						${assessments[pr_id]}
					</button>
				`;

				let rows = "";
				let rank = 1;
				(data[pr_id] ?? []).forEach(student => {
					rows += `
						<tr class="hover:bg-slate-50/80 transition-colors">
							<td class="py-2.5 px-3 text-center font-bold text-slate-700">${rank++}</td>
							<td class="py-2.5 px-3 font-medium text-slate-900">${student.username} - ${student.name}</td>
							<td class="py-2.5 px-3 text-center font-mono">${student.avg_score}</td>
							<td class="py-2.5 px-3 text-center font-mono">${student.extra_points}</td>
							<td class="py-2.5 px-3 text-center font-mono font-bold text-slate-900">${student.final_score}</td>
						</tr>
					`;
				});

				if (rows === "") {
					rows = `<tr><td colspan="5" class="py-4 text-center text-slate-400">No assessment data available.</td></tr>`;
				}

				tabContent += `
					<div id="ass_${pr_id}" class="sub-tab-content ${first ? '' : 'hidden'} overflow-x-auto pt-2">
						<table class="w-full text-left text-xs divide-y divide-slate-100">
							<thead>
								<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
									<th class="py-2.5 px-3 text-center" style="width: 50px;">Rank</th>
									<th class="py-2.5 px-3">Student Name</th>
									<th class="py-2.5 px-3 text-center">Score From Reviews</th>
									<th class="py-2.5 px-3 text-center">Score From Reviewing</th>
									<th class="py-2.5 px-3 text-center font-bold text-slate-900">Final Score</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-100">${rows}</tbody>
						</table>
					</div>
				`;

				first = false;
			});

			document.getElementById("assessmentTabs").innerHTML = tabHeader;
			document.getElementById("assessmentTabsContent").innerHTML = tabContent;
		}

		function switchAssessmentSubTab(targetId) {
			document.querySelectorAll('.sub-tab-content').forEach(el => el.classList.add('hidden'));
			document.querySelectorAll('.sub-tab-btn').forEach(btn => {
				btn.classList.remove('bg-slate-900', 'text-white');
				btn.classList.add('bg-slate-100', 'text-slate-700');
			});

			const target = document.getElementById(targetId);
			if (target) target.classList.remove('hidden');

			const btn = document.getElementById('btn_' + targetId);
			if (btn) {
				btn.classList.add('bg-slate-900', 'text-white');
				btn.classList.remove('bg-slate-100', 'text-slate-700');
			}
		}
	</script>
</body>
</html></html>
