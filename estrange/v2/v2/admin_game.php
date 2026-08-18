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
			AND course.course_id = '".$courseID."'";
		$result = mysqli_query($db,$sql);
		if ($result->num_rows == 0) {
			// if he gamification is off, redirect to dashboard
			header('Location: admin_dashboard.php');
			exit;
		}
	}
	
	// check if there is at least one course with game feature on
	$sql = "SELECT course.course_id, course.name, 
			game_course.prize_text, user.username FROM course 
			INNER JOIN game_course ON game_course.course_id = course.course_id 
			INNER JOIN user ON user.user_id = course.creator_id 
			WHERE game_course.is_active = 1 ";
	$result = mysqli_query($db,$sql);
	if ($result->num_rows == 0) {
		// if no gamified course, redirect to admin_nogame
		header('Location: admin_no_game.php');
		exit;
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Course Gamification</title>
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
	
	<script>
		function updateDisplayedGameDataBasedOnCourse() {
			var selectedValue = document.getElementById("course").value;
			var currentUrl = window.location.href;
			var baseUrl = currentUrl.indexOf("?") !== -1 ? currentUrl.substring(0, currentUrl.indexOf("?")) : currentUrl;
			window.location.href = baseUrl + "?id=" + selectedValue;
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
	<?php setHeaderAdmin("game", "Course game"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header Card with Course Select -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Admin Governance
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Gamification Analytics
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Course Game Leaderboard</h1>
					<p class="text-xs text-slate-500 mt-1">Cross-sectional standings based on code originality, quality, and efficiency.</p>
				</div>
				
				<div class="flex items-center gap-3">
					<label for="course" class="text-xs font-bold uppercase tracking-wider text-slate-700 whitespace-nowrap">Active Course:</label>
					<select name="course" id="course" onchange="updateDisplayedGameDataBasedOnCourse()"
						class="min-w-[200px] shrink-0 px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold font-bold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
						<?php
							$res = mysqli_query($db, $sql);
							if ($res && $res->num_rows > 0) {
								while ($row = $res->fetch_assoc()) {
									if ($courseID == null) $courseID = $row['course_id'];
									$selected = ($courseID == $row['course_id']) ? "selected" : "";
									if ($courseID == $row['course_id']) $prizeText = $row['prize_text'];
									echo '<option value="' . htmlspecialchars($row['course_id']) . '" ' . $selected . '>' . htmlspecialchars($row['name']) . '</option>';
								}
							} else {
								echo '<option value="" disabled selected>No active courses available</option>';
							}
						?>
					</select>
				</div>
			</div>

			<!-- Leaderboard Table Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
				<div class="flex items-center justify-between border-b border-slate-100 pb-3">
					<h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
						<svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
						<span>Gamification Standings</span>
					</h2>
					<span class="text-xs text-slate-500 font-medium">Rankings updated continuously</span>
				</div>
				
				<div class="overflow-x-auto">
					<table id="leaderboard" class="w-full text-left text-xs" style="width:100%">
						<thead>
							<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
								<th class="py-3 px-3 text-center" style="width: 8%;">Rank</th>
								<th class="py-3 px-3" style="width: 35%;">Student Identity</th>
								<th class="py-3 px-3 text-center font-bold text-slate-900" style="width: 15%;">Total Score</th>
								<th class="py-3 px-3 text-center" style="width: 14%;">Originality</th>
								<th class="py-3 px-3 text-center" style="width: 14%;">Quality</th>
								<th class="py-3 px-3 text-center" style="width: 14%;">Efficiency</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							<?php
								$arr = [];
								$sql = "SELECT user.username, user.name, game_student_course.gs_id, game_student_course.student_id 
										FROM game_student_course 
										INNER JOIN user ON user.user_id = game_student_course.student_id 
										WHERE game_student_course.course_id = '".$courseID."' 
										AND game_student_course.is_participating = 1";
								$result = mysqli_query($db, $sql);

								if ($result && $result->num_rows > 0) {
									while ($row = $result->fetch_assoc()) {
										if (in_array($row['username'], array_column($arr, 'username'))) {
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
												 INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id 
												 INNER JOIN course ON course.course_id = assessment.course_id 
												 LEFT JOIN code_clarity_suggestion ON code_clarity_suggestion.submission_id = submission.submission_id 
												 WHERE submission.submitter_id = '".$row['student_id']."' 
												 AND course.course_id = '".$courseID."'
												 GROUP BY assessment.assessment_id";

										$resultt = mysqli_query($db, $sqlt);
										if ($resultt && $resultt->num_rows > 0) {
											while ($rowt = $resultt->fetch_assoc()) {
												$mySubmissionPoints += $rowt['orig'];
												$myEfficiencyPoints += $rowt['eff'];
												$myQualityPoints += $rowt['qual'];
											}
										}

										$totalPoints = $mySubmissionPoints + $myEfficiencyPoints + $myQualityPoints;

										if ($totalPoints != 0) {
											$arr[] = [
												'student_id' => $row['student_id'],
												'username' => $row['username'],
												'name' => $row['name'],
												'totalPoints' => $totalPoints,
												'submissionPoints' => $mySubmissionPoints,
												'qualityPoints' => $myQualityPoints,
												'efficiencyPoints' => $myEfficiencyPoints
											];
										}
									}
								}

								usort($arr, function ($a, $b) {
									return $b['totalPoints'] <=> $a['totalPoints'];
								});

								foreach ($arr as $key => $student) {
									$rank = $key + 1;
									$rankBadge = $rank === 1 ? 'bg-amber-100 text-amber-800 font-extrabold' : ($rank === 2 ? 'bg-slate-200 text-slate-800 font-bold' : ($rank === 3 ? 'bg-orange-100 text-orange-800 font-bold' : 'bg-slate-100 text-slate-700'));
							?>
								<tr class="hover:bg-slate-50/80 transition-colors">
									<td class="py-3 px-3 text-center">
										<span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs <?= $rankBadge; ?>">
											<?= $rank; ?>
										</span>
									</td>
									<td class="py-3 px-3">
										<span class="font-mono font-bold text-slate-900"><?= htmlspecialchars($student['username']); ?></span>
										<span class="text-slate-500 text-[11px] block"><?= htmlspecialchars($student['name']); ?></span>
									</td>
									<td class="py-3 px-3 text-center font-extrabold text-slate-900 font-mono">
										<?= htmlspecialchars($student['totalPoints']); ?>
									</td>
									<td class="py-3 px-3 text-center text-slate-600 font-mono">
										<?= htmlspecialchars($student['submissionPoints']); ?>
									</td>
									<td class="py-3 px-3 text-center text-slate-600 font-mono">
										<?= htmlspecialchars($student['qualityPoints']); ?>
									</td>
									<td class="py-3 px-3 text-center text-slate-600 font-mono">
										<?= htmlspecialchars($student['efficiencyPoints']); ?>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Game Description & Prize Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
				<div class="flex items-center gap-2">
					<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-slate-100 text-slate-700">
						Rules &amp; Incentives
					</span>
					<h3 class="text-sm font-bold text-slate-900">Gamification Framework</h3>
				</div>
				
				<?php if (!empty($prizeText)): ?>
					<div class="p-4 bg-emerald-50/80 border border-emerald-200 rounded-xl text-xs text-emerald-900">
						<span class="font-bold block mb-0.5">Top Performer Prize Award:</span>
						<div><?= htmlspecialchars($prizeText); ?></div>
					</div>
				<?php endif; ?>

				<div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-600 leading-relaxed">
					<div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
						<div class="font-bold text-slate-900">Originality &amp; Integrity</div>
						<p>Points awarded for submitting authentic code, reducing plagiarism risk and unauthorized collaboration.</p>
					</div>
					<div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
						<div class="font-bold text-slate-900">Quality &amp; Structure</div>
						<p>Evaluates modularity, documentation, and maintainability to cultivate professional engineering standards.</p>
					</div>
					<div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
						<div class="font-bold text-slate-900">Algorithmic Efficiency</div>
						<p>Incentivizes optimal computational complexity and reduced carbon/energy footprints during execution.</p>
					</div>
				</div>
			</div>

		</div>
	</main>

	<script>
		$(document).ready(function() {
			new DataTable('#leaderboard', {
				responsive: true,
				pageLength: 10,
				lengthMenu: [5, 10, 25, 50],
				language: { search: "_INPUT_", searchPlaceholder: "Search leaderboard..." }
			});
		});
	</script>
</body>
</html>
