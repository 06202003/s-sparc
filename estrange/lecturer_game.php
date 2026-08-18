<?php
	include("_sessionchecker.php");
	include("_config.php");
	
	$courseID = null;
	$prizeText = null;
	
	// if it has a course id attached in the url, set the course id
	if(isset($_GET['id']) == true && $_GET['id'] != ''){
		$courseID = mysqli_real_escape_string($db,$_GET['id']);
		
		// check if the lecturer is the creator of the course and game feature is on for that course
		$sql = "SELECT course.creator_id FROM course
			INNER JOIN game_course ON game_course.course_id = course.course_id 
			WHERE game_course.is_active = 1 
			AND course.creator_id = '".$_SESSION['user_id']."' 
			AND course.course_id = '".$courseID."'";
		$result = mysqli_query($db,$sql);
		if ($result->num_rows == 0) {
			// if the lecturer is not in charge for given course or the gamification is off, redirect to dashboard
			header('Location: lecturer_dashboard.php');
			exit;
		}
	}
	
	// check if the lecturer is in charge in at least one course with game feature
	$sql = "SELECT course.course_id, course.name, 
			game_course.prize_text FROM course 
			INNER JOIN game_course ON game_course.course_id = course.course_id 
			WHERE game_course.is_active = 1 
			AND course.creator_id = '".$_SESSION['user_id']."' ";
	$result = mysqli_query($db,$sql);
	if ($result->num_rows == 0) {
		// if the lecturer is not in charge in at least one gamified course, redirect to lecturer_nogame
		header('Location: lecturer_no_game.php');
		exit;
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Course Gamification Leaderboard</title>
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
	<script>
		function updateDisplayedGameDataBasedOnCourse(){
			var selectedValue = document.getElementById("course").value;
			var url = window.location.href.split('?')[0];
			window.location.href = url + "?id=" + selectedValue;
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
	<?php setHeaderLecturer("courses", "Course game"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header & Course Switcher Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-500 text-white">
							Gamification Hub
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Lecturer Leaderboard
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Course Game &amp; Achievement Standings</h1>
					<p class="text-xs text-slate-500 mt-1">Live ranking tracking student originality, code clarity, green efficiency, and quiz participation.</p>
				</div>
				<div class="flex items-center gap-3">
					<label for="course" class="text-xs font-bold text-slate-700 whitespace-nowrap">Active Course:</label>
					<select name="course" id="course" onchange="updateDisplayedGameDataBasedOnCourse()"
						class="min-w-[200px] shrink-0 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold font-bold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
						<?php
							// Re-query or seek result
							$result->data_seek(0);
							while($row = $result->fetch_assoc()) {
								if($courseID == null) $courseID = $row['course_id'];
								$isSelected = ($courseID == $row['course_id']);
								if($isSelected) $prizeText = $row['prize_text'];
								echo '<option value="' . $row['course_id'] . '" ' . ($isSelected ? 'selected' : '') . '>' . htmlspecialchars($row['name']) . '</option>';
							}
						?>
					</select>
					<a href="lecturer_assessment_goals.php?id=<?= htmlspecialchars($courseID ?? '') ?>" 
						class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#00A0A5] hover:bg-[#008488] text-white text-xs font-bold rounded-xl transition shadow-2xs">
						<span>Collaborative Goals</span>
						<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
					</a>
				</div>
			</div>

			<!-- Leaderboard Table Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
				<div class="flex items-center justify-between">
					<h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Course Leaderboard Roster</h2>
					<?php if (!empty($prizeText)): ?>
						<div class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 border border-amber-200 rounded-full text-xs font-semibold text-amber-800">
							<span class="font-bold">Course Prize:</span> <?= htmlspecialchars($prizeText); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="overflow-x-auto">
					<table id="leaderboard" class="w-full text-left text-xs" style="width:100%">
						<thead>
							<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
								<th class="py-3 px-3 text-center" style="width: 8%;">Rank</th>
								<th class="py-3 px-3" style="width: 28%;">Student</th>
								<th class="py-3 px-3 text-center" style="width: 14%;">Total Points</th>
								<th class="py-3 px-3 text-center" style="width: 12%;">Originality</th>
								<th class="py-3 px-3 text-center" style="width: 12%;">Quality</th>
								<th class="py-3 px-3 text-center" style="width: 12%;">Efficiency</th>
								<th class="py-3 px-3 text-center" style="width: 14%;">Quiz Score</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							<?php
								$students = array();
								
								$sql = "SELECT user.username, user.name, game_student_course.gs_id, game_student_course.student_id 
										FROM game_student_course 
										INNER JOIN user ON user.user_id = game_student_course.student_id 
										WHERE game_student_course.course_id = '".$courseID."' 
										AND game_student_course.is_participating = 1";
								
								$resStudents = mysqli_query($db, $sql);
								if ($resStudents && $resStudents->num_rows > 0) {
									while ($row = $resStudents->fetch_assoc()) {
										if (in_array($row['username'], array_column($students, 'username'))) {
											continue;
										}
								
										$mySubmissionPoints = 0;
										$myEfficiencyPoints = 0;
										$myQualityPoints = 0;
								
										$sqlt = "SELECT ROUND(AVG(suspicion.originality_point),0) as orig, 
														ROUND(AVG(suspicion.efficiency_point),0) as eff, 
														ROUND(AVG(code_clarity_suggestion.quality_point),0) as qual
												 FROM suspicion  
												 INNER JOIN submission ON submission.submission_id = suspicion.submission_id 
												 INNER JOIN user ON user.user_id = submission.submitter_id 
												 INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id 
												 INNER JOIN course ON course.course_id = assessment.course_id 
												 LEFT JOIN code_clarity_suggestion ON code_clarity_suggestion.submission_id = submission.submission_id 
												 WHERE submission.submitter_id = '".$row['student_id']."' 
												 AND course.course_id = '".$courseID."'
												 GROUP BY assessment.assessment_id";
								
										$resultt = mysqli_query($db, $sqlt);
										if ($resultt && $resultt->num_rows > 0) {
											while ($rowt = $resultt->fetch_assoc()) {
												if($rowt['qual'] == NULL) $rowt['qual'] = 100;
												$mySubmissionPoints += $rowt['orig'];
												$myEfficiencyPoints += $rowt['eff'];
												$myQualityPoints += $rowt['qual'];
											}
										}
								
										$sqlt = "SELECT COUNT(question_id) AS tot 
												 FROM instant_quiz_response_history
												 WHERE student_id = '".$row['student_id']."' 
												 AND is_correct = 1 
												 AND response_time > DATE_SUB(now(), INTERVAL 6 MONTH)";
								
										$resultt = mysqli_query($db, $sqlt);
										$rowt = $resultt->fetch_assoc();
										$myQuizPoints = ($rowt['tot'] ?? 0) * 100;
								
										$totalPoints = $mySubmissionPoints + $myEfficiencyPoints + $myQualityPoints + $myQuizPoints;
								
										if ($totalPoints != 0) {
											$students[] = [
												'student_id' => $row['student_id'],
												'username' => $row['username'],
												'name' => $row['name'],
												'totalPoints' => $totalPoints,
												'submissionPoints' => $mySubmissionPoints,
												'qualityPoints' => $myQualityPoints,
												'efficiencyPoints' => $myEfficiencyPoints,
												'quizPoints' => $myQuizPoints
											];
										}
									}
								}
								
								usort($students, function ($a, $b) {
									return $b['totalPoints'] <=> $a['totalPoints'];
								});
								
								$counter = 1;
								foreach ($students as $student):
									$isTop3 = ($counter <= 3);
									$badgeColor = match($counter) {
										1 => 'bg-amber-100 text-amber-800 border-amber-300 font-extrabold',
										2 => 'bg-slate-200 text-slate-800 border-slate-300 font-extrabold',
										3 => 'bg-amber-50 text-amber-700 border-amber-200 font-bold',
										default => 'bg-slate-100 text-slate-600 border-slate-200'
									};
							?>
								<tr class="hover:bg-slate-50/80 transition-colors">
									<td class="py-3 px-3 text-center">
										<span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs border <?= $badgeColor ?>">
											<?= $counter ?>
										</span>
									</td>
									<td class="py-3 px-3">
										<div class="font-bold text-slate-900 font-mono"><?= htmlspecialchars($student['username']) ?></div>
										<div class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars($student['name']) ?></div>
									</td>
									<td class="py-3 px-3 text-center font-extrabold text-sm text-slate-900 font-mono">
										<?= number_format($student['totalPoints']) ?>
									</td>
									<td class="py-3 px-3 text-center font-semibold text-slate-700">
										<?= number_format($student['submissionPoints']) ?>
									</td>
									<td class="py-3 px-3 text-center font-semibold text-slate-700">
										<?= number_format($student['qualityPoints']) ?>
									</td>
									<td class="py-3 px-3 text-center font-semibold text-slate-700">
										<?= number_format($student['efficiencyPoints']) ?>
									</td>
									<td class="py-3 px-3 text-center font-semibold text-slate-700">
										<?= number_format($student['quizPoints']) ?>
									</td>
								</tr>
							<?php 
								$counter++;
								endforeach; 
							?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Game Description Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-3">
				<h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Gamification Rules &amp; Point Allocation</h2>
				<div class="text-xs text-slate-600 leading-relaxed space-y-2">
					<p>Students earn game points by submitting original, high-quality, and efficient programs. Originality indicates low collusion/plagiarism risk, code quality reflects maintainability, and efficiency measures algorithmic resource optimization. Scores across multiple submissions for a single assessment are averaged.</p>
					<p>Students also earn <span class="font-bold text-slate-800">100 bonus points</span> for each correct response to instant quizzes relevant to originality, quality, and efficiency within the past 6 months.</p>
					<p class="text-slate-400">Students who disable gamification have their points hidden from peers while their records remain preserved in the system.</p>
				</div>
			</div>

		</div>
	</main>

	<script>
		$(document).ready(function() {
			$('#course').select2({ placeholder: "Select course...", width: '220px' });
			new DataTable('#leaderboard', {
				responsive: true,
				pageLength: 10,
				lengthMenu: [5, 10, 25, 50],
				language: { search: "_INPUT_", searchPlaceholder: "Search student..." }
			});
		});
	</script>
</body>
</html>
