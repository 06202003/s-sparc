<?php
	include("_sessionchecker.php");
	include("_config.php");
	
	$courseID = null;
	$prizeText = null;
	
	// if it has a course id attached in the url, set the course id
	if(isset($_GET['id']) == true && $_GET['id'] != ''){
		$courseID = mysqli_real_escape_string($db,$_GET['id']);
		
		// check if the student is enrolled to that course and game feature is on for that course
		$sql = "SELECT enrollment.course_id FROM enrollment
			INNER JOIN game_course ON game_course.course_id = enrollment.course_id 
			WHERE game_course.is_active = 1 
			AND enrollment.student_id = '".$_SESSION['user_id']."' 
			AND enrollment.course_id = '".$courseID."'";
		$result = mysqli_query($db,$sql);
		if ($result->num_rows == 0) {
			// if the student is not enrolled to given course, redirect to dashboard
			header('Location: student_dashboard.php');
			exit;
		}
	}
	
	// check if the student is enrolled in at least one course with game feature
	$sql = "SELECT course.course_id, course.name, 
			game_course.prize_text FROM course 
			INNER JOIN game_course ON game_course.course_id = course.course_id 
			INNER JOIN game_student_course ON game_student_course.course_id = course.course_id
			WHERE game_course.is_active = 1 
			AND game_student_course.student_id = '".$_SESSION['user_id']."' ";
	$result = mysqli_query($db,$sql);
	if ($result->num_rows == 0) {
		// if the student is not enrolled to at least one gamified course, redirect to student_nogame
		header('Location: student_no_game.php');
		exit;
	}
	
	// for access statistics of game page
	$sql = "INSERT INTO game_access (student_id, type) VALUES ('".$_SESSION['user_id']."','main_page_visit')";
	$db->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Student Course Game</title>
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

	<!-- Chart.js -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
		.dataTables_wrapper .dataTables_filter input:focus {
			border-color: #00A0A5;
			box-shadow: 0 0 0 2px rgba(0, 160, 165, 0.2);
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
			background: #00A0A5 !important;
			color: #ffffff !important;
			border-radius: 0.5rem;
			border: 1px solid #00A0A5 !important;
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
  min-width: 140px !important;
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
	</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
	<?php setHeaderStudent("game", "Student game"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header Card with Course Select -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Student Hub
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Gamification &amp; Leaderboards
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Student Gamification Standings</h1>
					<p class="text-xs text-slate-500 mt-1">Track your level progression, points breakdown across timeliness, decisiveness, quality, and efficiency metrics.</p>
				</div>
				
				<div class="flex items-center gap-3">
					<label for="course" class="text-xs font-bold uppercase tracking-wider text-slate-700 whitespace-nowrap">Active Course:</label>
					<select name="course" id="course" onchange="updateDisplayedGameDataBasedOnCourse()" class="form-select">
						<?php 
							while($row = $result->fetch_assoc()) {
								if($courseID == null)
									$courseID = $row['course_id'];
								
								$selected = "";
								if($courseID == $row['course_id']){
									$selected = "selected";
									$prizeText = $row['prize_text'];
								}
								echo "<option value=\"".$row['course_id']."\" ".$selected.">";
								echo htmlspecialchars($row['name'])."</option>";
							}
						?>
					</select>
				</div>
			</div>

			<?php 
				$isParticipating = false;
				$gsID = -1;
				
				$sql = "SELECT gs_id, is_participating
						FROM game_student_course 
						WHERE student_id = '".$_SESSION['user_id']."' 
						AND course_id = '".$courseID."'";
				$result = mysqli_query($db,$sql);

				if ($result && $result->num_rows > 0) {
					$row = $result->fetch_assoc();
					if($row['is_participating'] == 1)
						$isParticipating = true;
					$gsID = $row['gs_id'];
				}

				// Global points variables
				$uTimelinessPoints = 0;
				$uQualityPoints = 0;
				$uEfficiencyPoints = 0;
				$uDecisivePoints = 0;
				$uAuthenticityPoints = 0;
				$uArrAssessmentNames = "";
				$uArrTimelinessPoints = "";
				$uArrQualityPoints = "";
				$uArrEfficiencyPoints = "";
				$uArrDecisivePoints = "";
				$uArrAuthenticityPoints = "";
				$allTimelinessPoints = 0;
				$allQualityPoints = 0;
				$allEfficiencyPoints = 0;
				$allDecisivePoints = 0;
				$allAuthenticityPoints = 0;

				$students = array();
				
				// Fetch participating students
				$sql = "SELECT user.username, user.name, game_student_course.gs_id, game_student_course.student_id 
						FROM game_student_course 
						INNER JOIN user ON user.user_id = game_student_course.student_id 
						WHERE game_student_course.course_id = '".$courseID."' 
						AND game_student_course.is_participating = 1";
				
				$result = mysqli_query($db, $sql);
				if ($result && $result->num_rows > 0) {
					$totalStudentsCount = $result->num_rows;
					while ($row = $result->fetch_assoc()) {
						$myTimelinessPoints = 0;
						$myEfficiencyPoints = 0;
						$myQualityPoints = 0;
						$myDecisivePoints = 0;
						$myAuthenticityPoints = 0;

						// Submission points query
						$sqlt = "SELECT user.user_id AS id, 
								MAX(submission.attempt) as maxattempt, 
								ROUND(MAX((assessment.submission_close_time - submission.submission_time)/(assessment.submission_close_time - assessment.submission_open_time)*100),0) as mintime,
								ROUND(AVG(suspicion.efficiency_point),0) as eff, 
								ROUND(AVG(code_clarity_suggestion.quality_point),0) as qual, 
								ROUND(AVG(CASE WHEN generated_quizzes.score_points IS NOT NULL THEN (generated_quizzes.score_points / 3 * 100) ELSE 0 END),0) as auth,
								assessment.assessment_id as asmt_id, assessment.name as asmt_name 
								FROM suspicion  
								INNER JOIN submission ON submission.submission_id = suspicion.submission_id 
								INNER JOIN user ON user.user_id = submission.submitter_id 
								INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id 
								INNER JOIN course ON course.course_id = assessment.course_id 
								LEFT JOIN code_clarity_suggestion ON code_clarity_suggestion.submission_id = submission.submission_id 
								LEFT JOIN generated_quizzes ON generated_quizzes.submission_id = submission.submission_id
								WHERE user.user_id = '".$row['student_id']."' 
								AND course.course_id = '".$courseID."' 
								GROUP BY assessment.assessment_id";

						$resultt = mysqli_query($db, $sqlt);
						if ($resultt && $resultt->num_rows > 0) {
							while ($rowt = $resultt->fetch_assoc()) {
								if($rowt['qual'] == NULL)
									$rowt['qual'] = 100;
								if($rowt['mintime'] < 0){
									$rowt['mintime'] = 0;
								}
								$decisivePoint = round(100 / $rowt['maxattempt']);
								$authPoint = (int)$rowt['auth'];

								$myTimelinessPoints += $rowt['mintime'];
								$myEfficiencyPoints += $rowt['eff'];
								$myQualityPoints += $rowt['qual'];
								$myDecisivePoints += $decisivePoint;
								$myAuthenticityPoints += $authPoint;
									
								$allTimelinessPoints += $rowt['mintime'];
								$allEfficiencyPoints += $rowt['eff'];
								$allQualityPoints += $rowt['qual'];
								$allDecisivePoints += $decisivePoint;
								$allAuthenticityPoints += $authPoint;

								if ($rowt['id'] == $_SESSION['user_id']) {
									$uTimelinessPoints += $rowt['mintime'];
									$uEfficiencyPoints += $rowt['eff'];
									$uQualityPoints += $rowt['qual'];
									$uDecisivePoints += $decisivePoint;
									$uAuthenticityPoints += $authPoint;
									$uArrAssessmentNames .= ",'".addslashes($rowt['asmt_name'])."'";
									$uArrTimelinessPoints .= ",".$rowt['mintime'];
									$uArrEfficiencyPoints .= ",".$rowt['eff'];
									$uArrQualityPoints .= ",".$rowt['qual'];
									$uArrDecisivePoints .= ",".$decisivePoint;
									$uArrAuthenticityPoints .= ",".$authPoint;
								}
							}
						}

						$totalPoints = $myTimelinessPoints + $myQualityPoints + $myEfficiencyPoints + $myDecisivePoints + $myAuthenticityPoints;

						$students[] = array(
							'student_id' => $row['student_id'],
							'username' => $row['username'],
							'name' => $row['name'],
							'totalPoints' => $totalPoints,
							'mySubmissionPoints' => $myTimelinessPoints,
							'myDecisivePoints' => $myDecisivePoints,
							'myQualityPoints' => $myQualityPoints,
							'myEfficiencyPoints' => $myEfficiencyPoints,
							'myAuthenticityPoints' => $myAuthenticityPoints
						);
					}

					usort($students, function ($a, $b) {
						return $b['totalPoints'] - $a['totalPoints'];
					});

					if ($totalStudentsCount > 0) {
						$allTimelinessPoints = round($allTimelinessPoints / $totalStudentsCount);
						$allEfficiencyPoints = round($allEfficiencyPoints / $totalStudentsCount);
						$allQualityPoints = round($allQualityPoints / $totalStudentsCount);
						$allDecisivePoints = round($allDecisivePoints / $totalStudentsCount);
						$allAuthenticityPoints = round($allAuthenticityPoints / $totalStudentsCount);
					}
				}

				$uTotalPoints = $uTimelinessPoints + $uQualityPoints + $uEfficiencyPoints + $uDecisivePoints + $uAuthenticityPoints;
				$userLevel = 1 + intval($uTotalPoints / 500);
				$pointsToNextLevel = 500 - intval($uTotalPoints % 500);
				$levelProgressPercent = intval(($uTotalPoints % 500) / 500 * 100);
			?>

			<?php if ($isParticipating): ?>
				<!-- Student Level & Progress Stats Card -->
				<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
					<!-- Level & Points Card -->
					<div class="bg-gradient-to-br from-teal-700 to-cyan-900 rounded-2xl p-6 text-white shadow-sm flex flex-col justify-between space-y-4">
						<div>
							<div class="flex items-center justify-between mb-2">
								<span class="px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-white/20 text-white backdrop-blur-xs">
									Level <?= $userLevel; ?> Rank
								</span>
								<span class="text-xs font-semibold text-teal-200">
									<?= $uTotalPoints; ?> Total Points
								</span>
							</div>
							<h2 class="text-2xl font-black tracking-tight">Student Progression</h2>
							<p class="text-xs text-teal-100 mt-1"><?= $pointsToNextLevel; ?> more points needed to reach Level <?= $userLevel + 1; ?>!</p>
						</div>

						<!-- Progress Bar -->
						<div class="space-y-1.5">
							<div class="flex justify-between text-[11px] font-bold text-teal-200">
								<span>Level <?= $userLevel; ?></span>
								<span><?= $levelProgressPercent; ?>%</span>
							</div>
							<div class="w-full h-3 bg-teal-950/40 rounded-full overflow-hidden p-0.5 border border-teal-500/30">
								<div class="h-full bg-gradient-to-r from-teal-300 to-emerald-400 rounded-full transition-all duration-500" style="width: <?= $levelProgressPercent; ?>%"></div>
							</div>
						</div>

						<button type="button" onclick="document.getElementById('statsModal').classList.remove('hidden')" class="w-full py-2.5 bg-white hover:bg-teal-50 text-teal-950 text-xs font-bold rounded-xl shadow-xs transition flex items-center justify-center gap-2">
							<svg class="w-4 h-4 text-teal-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
							<span>View Detailed Analytics</span>
						</button>
					</div>

					<!-- Metrics Breakdown Card -->
					<div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-col justify-between space-y-4">
						<div class="flex items-center justify-between border-b border-slate-100 pb-3">
							<h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
								<svg class="w-4 h-4 text-[#00A0A5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
								<span>Your Category Metrics</span>
							</h3>
							<span class="text-xs text-slate-500 font-medium">Accumulated Points</span>
						</div>

						<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
							<!-- Timeliness -->
							<div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
								<span class="text-[11px] font-semibold text-slate-500 block">Timeliness</span>
								<span class="text-base font-black text-slate-900 font-mono block"><?= $uTimelinessPoints; ?></span>
								<span class="text-[10px] text-slate-400 block">Submission speed</span>
							</div>

							<!-- Decisiveness -->
							<div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
								<span class="text-[11px] font-semibold text-slate-500 block">Decisiveness</span>
								<span class="text-base font-black text-slate-900 font-mono block"><?= $uDecisivePoints; ?></span>
								<span class="text-[10px] text-slate-400 block">Fewer attempts</span>
							</div>

							<!-- Quality -->
							<div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
								<span class="text-[11px] font-semibold text-slate-500 block">Quality</span>
								<span class="text-base font-black text-slate-900 font-mono block"><?= $uQualityPoints; ?></span>
								<span class="text-[10px] text-slate-400 block">Code clarity</span>
							</div>

							<!-- Efficiency -->
							<div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
								<span class="text-[11px] font-semibold text-slate-500 block">Efficiency</span>
								<span class="text-base font-black text-slate-900 font-mono block"><?= $uEfficiencyPoints; ?></span>
								<span class="text-[10px] text-slate-400 block">Resource usage</span>
							</div>

							<!-- Authenticity -->
							<div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl space-y-1">
								<span class="text-[11px] font-semibold text-slate-500 block">Authenticity</span>
								<span class="text-base font-black text-[#00A0A5] font-mono block"><?= $uAuthenticityPoints; ?></span>
								<span class="text-[10px] text-slate-400 block">AI Verification Quiz</span>
							</div>
						</div>
					</div>
				</div>

				<!-- Leaderboard Table Card -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
					<div class="flex items-center justify-between border-b border-slate-100 pb-3">
						<h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
							<svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
							<span>Top Leaderboard Rankings</span>
						</h2>
						<span class="text-xs text-slate-500 font-medium">Top 10 Participants</span>
					</div>
					
					<div class="overflow-x-auto">
						<table id="leaderboard" class="w-full text-left text-xs" style="width:100%">
							<thead>
								<tr class="border-b border-slate-200 text-slate-700 uppercase tracking-wider font-bold text-[11px]">
									<th class="py-3 px-3 text-center" style="width: 6%;">Rank</th>
									<th class="py-3 px-3" style="width: 26%;">Student Identity</th>
									<th class="py-3 px-3 text-center font-extrabold text-slate-900" style="width: 14%;">General Points</th>
									<th class="py-3 px-3 text-center" style="width: 10.8%;">Timeliness</th>
									<th class="py-3 px-3 text-center" style="width: 10.8%;">Decisiveness</th>
									<th class="py-3 px-3 text-center" style="width: 10.8%;">Quality</th>
									<th class="py-3 px-3 text-center" style="width: 10.8%;">Efficiency</th>
									<th class="py-3 px-3 text-center font-extrabold text-[#00A0A5]" style="width: 10.8%;">Authenticity</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-100">
								<?php
									$counter = 1;
									foreach (array_slice($students, 0, 10) as $student) {
										$rankBadge = $counter === 1 ? 'bg-amber-100 text-amber-800 font-extrabold' : ($counter === 2 ? 'bg-slate-200 text-slate-800 font-bold' : ($counter === 3 ? 'bg-orange-100 text-orange-800 font-bold' : 'bg-slate-100 text-slate-700 font-medium'));
										$isSelf = ($student['student_id'] == $_SESSION['user_id']);
										$rowClass = $isSelf ? 'bg-teal-50/60 font-medium border-l-4 border-l-[#00A0A5]' : 'hover:bg-slate-50/80 transition-colors';
										$studentLevel = 1 + intval($student['totalPoints'] / 500);
										$firstName = explode(" ", $student['name'])[0];
								?>
									<tr class="<?= $rowClass; ?>" id="<?= htmlspecialchars($student['student_id']); ?>">
										<td class="py-3 px-3 text-center">
											<span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs <?= $rankBadge; ?>">
												<?= $counter; ?>
											</span>
										</td>
										<td class="py-3 px-3">
											<div class="flex items-center gap-2">
												<span class="font-mono font-bold text-slate-900"><?= htmlspecialchars($student['username']); ?></span>
												<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-teal-100 text-teal-800">Lv. <?= $studentLevel; ?></span>
												<?php if ($isSelf): ?>
													<span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold bg-[#00A0A5] text-white">YOU</span>
												<?php endif; ?>
											</div>
											<span class="text-slate-500 text-[11px] block mt-0.5"><?= htmlspecialchars($firstName); ?></span>
										</td>
										<td class="py-3 px-3 text-center font-extrabold text-[#00A0A5] font-mono text-xs">
											<?= htmlspecialchars($student['totalPoints']); ?>
										</td>
										<td class="py-3 px-3 text-center text-slate-700 font-mono">
											<?= htmlspecialchars($student['mySubmissionPoints']); ?>
										</td>
										<td class="py-3 px-3 text-center text-slate-700 font-mono">
											<?= htmlspecialchars($student['myDecisivePoints']); ?>
										</td>
										<td class="py-3 px-3 text-center text-slate-700 font-mono">
											<?= htmlspecialchars($student['myQualityPoints']); ?>
										</td>
										<td class="py-3 px-3 text-center text-slate-700 font-mono">
											<?= htmlspecialchars($student['myEfficiencyPoints']); ?>
										</td>
										<td class="py-3 px-3 text-center font-bold text-[#00A0A5] font-mono text-xs">
											<?= htmlspecialchars($student['myAuthenticityPoints']); ?>
										</td>
									</tr>
								<?php 
										$counter++;
									} 
								?>
							</tbody>
						</table>
					</div>
				</div>

			<?php else: ?>
				<!-- Not Participating Card -->
				<div class="bg-white rounded-2xl border border-amber-200 shadow-xs p-8 text-center space-y-4">
					<div class="w-14 h-14 bg-amber-50 border border-amber-200 text-amber-600 rounded-2xl flex items-center justify-center mx-auto">
						<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
					</div>
					<div class="space-y-1 max-w-md mx-auto">
						<h2 class="text-base font-bold text-slate-900">Game Feature Currently Off</h2>
						<p class="text-xs text-slate-500 leading-relaxed">
							<?php if ($human_language == 'en'): ?>
								You are not currently participating in gamification for this course. Turn on the game feature to appear on leaderboards and track your level progression!
							<?php else: ?>
								Kamu belum berpartisipasi dalam game di mata kuliah ini. Aktifkan fitur permainan untuk tampil di papan peringkat dan melacak kenaikan level kamu!
							<?php endif; ?>
						</p>
					</div>
					<form action="student_game_toggle.php" method="post" class="pt-2">
						<input type="hidden" name="id" value="<?= htmlspecialchars($gsID); ?>">
						<input type="hidden" name="is_participating" value="0">
						<input type="hidden" name="course_id" value="<?= htmlspecialchars($courseID); ?>">
						<button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-xs font-bold rounded-xl shadow-xs transition">
							<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
							<span>Turn On Game Feature</span>
						</button>
					</form>
				</div>
			<?php endif; ?>

			<!-- Game Description & Prize Rules Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
				<div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-3">
					<div class="flex items-center gap-2">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-slate-100 text-slate-700">
							Rules &amp; Incentives
						</span>
						<h3 class="text-sm font-bold text-slate-900">Game Description &amp; Scoring Mechanics</h3>
					</div>

					<form action="student_game_toggle.php" method="post">
						<input type="hidden" name="id" value="<?= htmlspecialchars($gsID); ?>">
						<input type="hidden" name="is_participating" value="<?= $isParticipating ? 1 : 0; ?>">
						<input type="hidden" name="course_id" value="<?= htmlspecialchars($courseID); ?>">
						<?php if ($isParticipating): ?>
							<button type="submit" class="px-3.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
								<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
								<span>Turn Off Game Feature</span>
							</button>
						<?php else: ?>
							<button type="submit" class="px-3.5 py-1.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
								<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
								<span>Turn On Game Feature</span>
							</button>
						<?php endif; ?>
					</form>
				</div>
				
				<?php if (!empty($prizeText)): ?>
					<div class="p-4 bg-emerald-50/90 border border-emerald-200 rounded-xl text-xs text-emerald-900 flex items-start gap-2.5">
						<svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
						<div>
							<strong class="font-bold block text-sm text-emerald-950">Prize Award Announcement</strong>
							<span class="text-xs text-emerald-800 leading-relaxed block mt-0.5"><?= htmlspecialchars($prizeText); ?></span>
						</div>
					</div>
				<?php endif; ?>

				<div class="text-xs text-slate-600 leading-relaxed space-y-3 p-4 bg-slate-50 border border-slate-200/80 rounded-xl">
					<?php 
						if($human_language == 'en'){
							echo "<p>Students will obtain more game points by submitting high-quality and efficient programs as early as possible (timeliness) with fewer submission attempts (decisiveness) and high code comprehension scores on the 3-question AI verification quiz (authenticity). Submitting programs early means good time management. 
							Fewer submission attempts means students only submit their work when it is ready.
							Having high-quality programs means maintainable code. Having efficient programs means environment-friendly execution. High authenticity proves independent work and true code understanding. 
							The points will be averaged if students do multiple submissions for a particular assessment.</p>";
							echo '<p class="text-slate-500 font-medium pt-1 border-t border-slate-200">Students can turn off the game feature. Their points will be hidden from anyone (but still recorded so the students can rejoin at any time without losing any points).</p>';
						}else{
							echo "<p>Siswa akan mendapatkan poin permainan lebih dengan mengumpulkan program yang berkualitas tinggi dan efisien sedini mungkin (timeliness), pengumpulan sesedikit mungkin (decisiveness), serta nilai pemahaman kode yang tinggi pada Kuis Verifikasi AI 3 Soal (authenticity). Mengumpulkan program sedini mungkin berarti siswa memiliki manajemen waktu yang baik. 
							Pengumpulan sedikit berarti siswa hanya mengumpulkan tugas jika sudah siap.
							Kualitas tinggi berarti kode yang rapi dan mudah dipelihara. Efisiensi tinggi berarti kode yang ramah lingkungan. Orisinalitas (authenticity) tinggi membuktikan pengerjaan mandiri dan pemahaman penuh atas kode yang dibuat. 
							Poin-poin tersebut akan direrata jika siswanya memiliki beberapa program untuk sebuah tugas.</p>";
							echo '<p class="text-slate-500 font-medium pt-1 border-t border-slate-200">Siswa dapat mematikan fitur permainan. Poin nya akan disembunyikan dari siswa lain (namun tetap disimpan sehingga siswa dapat ikut kembali tanpa kehilangan poin).</p>';
						}
					?>
				</div>
			</div>

		</div>
	</main>

	<!-- Statistics Modal -->
	<div id="statsModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
		<div class="bg-white rounded-3xl max-w-4xl w-full shadow-2xl border border-slate-200 overflow-hidden space-y-6 p-6">
			<div class="flex items-center justify-between border-b border-slate-100 pb-4">
				<div>
					<h3 class="text-base font-bold text-slate-900">Personal Performance Analytics</h3>
					<p class="text-xs text-slate-500">Comparative radar analysis and assessment progress over time</p>
				</div>
				<button type="button" onclick="document.getElementById('statsModal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
					<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
				</button>
			</div>

			<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
				<!-- Radar Chart -->
				<div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 flex flex-col items-center">
					<h4 class="text-xs font-bold text-slate-700 mb-2">Category Comparison (You vs Class Avg)</h4>
					<div class="w-full h-64">
						<canvas id="radarChart"></canvas>
					</div>
				</div>

				<!-- Line Chart -->
				<div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 flex flex-col items-center">
					<h4 class="text-xs font-bold text-slate-700 mb-2">Progress Across Assessments</h4>
					<div class="w-full h-64">
						<canvas id="lineChart"></canvas>
					</div>
				</div>
			</div>

			<div class="flex justify-end pt-2">
				<button type="button" onclick="document.getElementById('statsModal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
					Close Modal
				</button>
			</div>
		</div>
	</div>

	<script>
		document.addEventListener("DOMContentLoaded", function () {
			new DataTable('#leaderboard', {
				responsive: true,
				pageLength: 10,
				searching: false,
				paging: false,
				info: false
			});

			<?php if ($isParticipating): ?>
				// Radar Chart
				let ctxRadar = document.getElementById('radarChart').getContext('2d');
				new Chart(ctxRadar, {
					type: 'radar',
					data: {
						labels: ['Timeliness', 'Decisiveness', 'Quality', 'Efficiency', 'Authenticity'],
						datasets: [{
							label: 'Yours',
							data: [<?= $uTimelinessPoints; ?>, <?= $uDecisivePoints; ?>, <?= $uQualityPoints; ?>, <?= $uEfficiencyPoints; ?>, <?= $uAuthenticityPoints; ?>],
							backgroundColor: 'rgba(0, 160, 165, 0.2)',
							borderColor: 'rgba(0, 160, 165, 1)',
							borderWidth: 2
						}, {
							label: 'Average users',
							data: [<?= $allTimelinessPoints; ?>, <?= $allDecisivePoints; ?>, <?= $allQualityPoints; ?>, <?= $allEfficiencyPoints; ?>, <?= $allAuthenticityPoints; ?>],
							backgroundColor: 'rgba(239, 68, 68, 0.2)',
							borderColor: 'rgba(239, 68, 68, 1)',
							borderWidth: 2
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: { legend: { position: 'bottom' } }
					}
				});

				// Line Chart
				let ctxLine = document.getElementById('lineChart').getContext('2d');
				new Chart(ctxLine, {
					type: 'line',
					data: {
						labels: [<?= strlen($uArrAssessmentNames) > 0 ? substr($uArrAssessmentNames, 1) : ''; ?>],
						datasets: [{ 
							label: 'Timeliness',
							data: [<?= strlen($uArrTimelinessPoints) > 0 ? substr($uArrTimelinessPoints, 1) : ''; ?>],
							borderColor: '#3b82f6',
							tension: 0.2
						}, { 
							label: 'Decisiveness',
							data: [<?= strlen($uArrDecisivePoints) > 0 ? substr($uArrDecisivePoints, 1) : ''; ?>],
							borderColor: '#8b5cf6',
							tension: 0.2
						}, { 
							label: 'Quality',
							data: [<?= strlen($uArrQualityPoints) > 0 ? substr($uArrQualityPoints, 1) : ''; ?>],
							borderColor: '#10b981',
							tension: 0.2
						}, { 
							label: 'Efficiency',
							data: [<?= strlen($uArrEfficiencyPoints) > 0 ? substr($uArrEfficiencyPoints, 1) : ''; ?>],
							borderColor: '#f43f5e',
							tension: 0.2
						}, { 
							label: 'Authenticity',
							data: [<?= strlen($uArrAuthenticityPoints) > 0 ? substr($uArrAuthenticityPoints, 1) : ''; ?>],
							borderColor: '#f59e0b',
							tension: 0.2
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: { legend: { position: 'bottom' } },
						scales: {
							y: { beginAtZero: true }
						}
					}
				});
			<?php endif; ?>
		});
	</script>
</body>
</html>