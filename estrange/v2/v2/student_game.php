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
	$sqlCourses = "SELECT course.course_id, course.name, 
			game_course.prize_text FROM course 
			INNER JOIN game_course ON game_course.course_id = course.course_id 
			INNER JOIN enrollment ON enrollment.course_id = course.course_id
			WHERE game_course.is_active = 1 
			AND enrollment.student_id = '".$_SESSION['user_id']."' 
			GROUP BY course.course_id";
	$resultCourses = mysqli_query($db, $sqlCourses);
	if (!$resultCourses || $resultCourses->num_rows == 0) {
		// if the student is not enrolled to at least one gamified course, redirect to student_nogame
		header('Location: student_no_game.php');
		exit;
	}

	if ($courseID == null && $resultCourses && $resultCourses->num_rows > 0) {
		$firstRow = mysqli_fetch_assoc($resultCourses);
		$courseID = $firstRow['course_id'];
		$prizeText = $firstRow['prize_text'];
		mysqli_data_seek($resultCourses, 0);
	}
	
	// for access statistics of game page
	$sql_access = "INSERT INTO game_access (student_id, type) VALUES ('".$_SESSION['user_id']."','main_page_visit')";
	$db->query($sql_access);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Gamification &amp; Leaderboard</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>

	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

	<!-- Chart.js -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

	<!-- SweetAlert2 -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	
	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
	</style>
	<script>
		function updateDisplayedGameDataBasedOnCourse(){
			var selectedValue = document.getElementById("courseSelect").value;
			window.location.href = window.location.pathname + "?id=" + selectedValue;
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
	<?php setHeaderStudent("game", "Student game"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Top Banner / Course Selection -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Gamification
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Competitive Learning Hub
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Course Game Arena &amp; Leaderboard</h1>
					<p class="text-xs text-slate-500 mt-1">Earn experience points across originality, code clarity, and computational efficiency.</p>
				</div>
				<div class="flex items-center gap-3">
					<label for="courseSelect" class="text-xs font-semibold text-slate-600">Select Course:</label>
					<select 
						id="courseSelect" 
						onchange="updateDisplayedGameDataBasedOnCourse()" 
						class="min-w-[220px] shrink-0 px-3.5 py-2 text-sm font-semibold text-slate-800 bg-white border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition shadow-2xs"
					>
						<?php
							// Reset pointer of course query
							$resultCourses = mysqli_query($db, $sqlCourses);
							$courseCount = ($resultCourses) ? mysqli_num_rows($resultCourses) : 0;
							if ($courseCount > 0) {
								while($row = $resultCourses->fetch_assoc()) {
									if($courseID == null) $courseID = $row['course_id'];
									$selected = ($courseID == $row['course_id']) ? "selected" : "";
									if($courseID == $row['course_id']) $prizeText = $row['prize_text'];
									echo '<option value="'.htmlspecialchars($row['course_id']).'" '.$selected.'>'.htmlspecialchars($row['name']).'</option>';
								}
							} else {
								echo '<option value="" disabled selected>No courses available</option>';
							}
						?>
					</select>
				</div>
			</div>

			<?php if ($courseCount === 0): ?>
				<div class="rounded-2xl border border-amber-200 bg-amber-50/90 text-amber-900 p-5 text-xs flex flex-wrap items-center justify-between gap-4 shadow-xs">
					<div class="flex items-center gap-3">
						<svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
						</svg>
						<div>
							<p class="font-bold text-slate-900 text-sm">No Active Enrolled Courses Found</p>
							<p class="text-slate-600 mt-0.5">You are not currently enrolled in any course with active game features. Join a course to participate in rankings and earn experience points.</p>
						</div>
					</div>
					<a href="student_enrollment.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#00A0A5] text-white rounded-xl font-semibold hover:bg-[#008488] transition shadow-xs">
						Browse Courses &rarr;
					</a>
				</div>
			<?php endif; ?>

			<?php 
				$isParticipating = false;
				$gsID = -1;
				
				$sqlPart = "SELECT gs_id, is_participating FROM game_student_course WHERE student_id = '".$_SESSION['user_id']."' AND course_id = '".$courseID."'";
				$resPart = mysqli_query($db, $sqlPart);
				if ($resPart && $resPart->num_rows > 0) {
					$rowPart = $resPart->fetch_assoc();
					if ($rowPart['is_participating'] == 1) $isParticipating = true;
					$gsID = $rowPart['gs_id'];
				}

				// Data calculation for user and course
				$counter = 1;
				$students = array();
				$uSubmissionPoints = 0;
				$uQualityPoints = 0;
				$uEfficiencyPoints = 0;
				$uArrAssessmentNames = "";
				$uArrSubmissionPoints = "";
				$uArrQualityPoints = "";
				$uArrEfficiencyPoints = "";
				$allSubmissionPoints = 0;
				$allQualityPoints = 0;
				$allEfficiencyPoints = 0;

				$sqlStud = "SELECT user.username, user.name, game_student_course.gs_id, game_student_course.student_id 
						FROM game_student_course 
						INNER JOIN user ON user.user_id = game_student_course.student_id 
						WHERE game_student_course.course_id = '".$courseID."' 
						AND game_student_course.is_participating = 1";
				$resStud = mysqli_query($db, $sqlStud);

				if ($resStud && $resStud->num_rows > 0) {
					while ($rowS = $resStud->fetch_assoc()) {
						$mySubmissionPoints = 0;
						$myEfficiencyPoints = 0;
						$myQualityPoints = 0;

						// Originality and Quality points from submissions
						$sqlSub = "SELECT submission.submission_id, submission.assessment_id, suspicion.originality_point AS orig,
								   code_clarity_suggestion.quality_point AS qual, suspicion.efficiency_point AS eff, assessment.name AS asmt_name
								   FROM submission 
								   INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id 
								   LEFT JOIN suspicion ON suspicion.submission_id = submission.submission_id 
								   LEFT JOIN code_clarity_suggestion ON code_clarity_suggestion.submission_id = submission.submission_id 
								   WHERE submission.submitter_id = '".$rowS['student_id']."' 
								   AND assessment.course_id = '".$courseID."' 
								   AND submission.submission_time > DATE_SUB(now(), INTERVAL 6 MONTH)";
						$resSub = mysqli_query($db, $sqlSub);
						if ($resSub && $resSub->num_rows > 0) {
							while ($rowT = $resSub->fetch_assoc()) {
								$mySubmissionPoints += floatval($rowT['orig'] ?? 0);
								$myQualityPoints += floatval($rowT['qual'] ?? 0);
								$myEfficiencyPoints += floatval($rowT['eff'] ?? 0);

								if ($rowS['student_id'] == $_SESSION['user_id']) {
									$uSubmissionPoints += floatval($rowT['orig'] ?? 0);
									$uQualityPoints += floatval($rowT['qual'] ?? 0);
									$uEfficiencyPoints += floatval($rowT['eff'] ?? 0);
									$uArrAssessmentNames .= ",'".addslashes($rowT['asmt_name'])."'";
									$uArrSubmissionPoints .= ",".$rowT['orig'];
									$uArrEfficiencyPoints .= ",".$rowT['eff'];
									$uArrQualityPoints .= ",".$rowT['qual'];
								}
							}
						}

						$totalPoints = $mySubmissionPoints + $myQualityPoints + $myEfficiencyPoints;
						$students[] = array(
							'student_id' => $rowS['student_id'],
							'username' => $rowS['username'],
							'name' => $rowS['name'],
							'totalPoints' => $totalPoints,
							'mySubmissionPoints' => $mySubmissionPoints,
							'myQualityPoints' => $myQualityPoints,
							'myEfficiencyPoints' => $myEfficiencyPoints
						);
					}

					usort($students, function ($a, $b) {
						return $b['totalPoints'] - $a['totalPoints'];
					});

					$numStud = max(1, $resStud->num_rows);
					$allSubmissionPoints = round($allSubmissionPoints / $numStud);
					$allEfficiencyPoints = round($allEfficiencyPoints / $numStud);
					$allQualityPoints = round($allQualityPoints / $numStud);
				}

				$uTotalPoints = $uSubmissionPoints + $uQualityPoints + $uEfficiencyPoints;
				$userLevel = 1 + intval($uTotalPoints / 500);
				$pointsToNext = 500 - intval($uTotalPoints % 500);
			?>

			<?php if ($isParticipating): ?>
				<!-- Leaderboard Card -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
					<div class="flex items-center justify-between border-b border-slate-100 pb-3">
						<h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
							<svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
							<span>Course Leaderboard (Top 10)</span>
						</h2>
						<span class="text-xs text-slate-500 font-medium">Rankings updated continuously</span>
					</div>

					<div class="overflow-x-auto">
						<table class="w-full text-left text-xs" style="width:100%">
							<thead>
								<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
									<th class="py-3 px-3 text-center" style="width: 50px;">Rank</th>
									<th class="py-3 px-3">Student &amp; Level</th>
									<th class="py-3 px-3 text-center font-bold text-slate-900">Total Points</th>
									<th class="py-3 px-3 text-center">Originality</th>
									<th class="py-3 px-3 text-center">Quality</th>
									<th class="py-3 px-3 text-center">Efficiency</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-100">
								<?php
									if (!empty($students)) {
										foreach (array_slice($students, 0, 10) as $student) {
											$isCurrent = ($student['student_id'] == $_SESSION['user_id']);
											$rowBg = $isCurrent ? 'bg-slate-900/5 font-semibold' : 'hover:bg-slate-50/80';
											$level = 1 + intval($student['totalPoints'] / 500);

											echo '<tr class="'.$rowBg.' transition-colors">';
											echo '<td class="py-3.5 px-3 text-center font-bold text-slate-700">'.$counter.'</td>';
											echo '<td class="py-3.5 px-3 font-medium text-slate-900">'.htmlspecialchars($student['name']).' <span class="text-[11px] text-slate-400 font-mono">(@'.htmlspecialchars($student['username']).')</span> <span class="ml-1 px-2 py-0.5 text-[11px] font-bold rounded-full bg-slate-100 text-slate-700">Lv. '.$level.'</span></td>';
											echo '<td class="py-3.5 px-3 text-center font-bold text-slate-900 font-mono">'.number_format($student['totalPoints']).'</td>';
											echo '<td class="py-3.5 px-3 text-center font-mono text-slate-600">'.number_format($student['mySubmissionPoints']).'</td>';
											echo '<td class="py-3.5 px-3 text-center font-mono text-slate-600">'.number_format($student['myQualityPoints']).'</td>';
											echo '<td class="py-3.5 px-3 text-center font-mono text-slate-600">'.number_format($student['myEfficiencyPoints']).'</td>';
											echo '</tr>';
											$counter++;
										}
									} else {
										echo '<tr><td colspan="6" class="py-6 text-center text-slate-400">No active student participation recorded yet.</td></tr>';
									}
								?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<!-- Details & Rules Section -->
			<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
				
				<!-- Summary Card -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
					<h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-[11px] text-slate-500">Your Progression</h3>
					<?php if (!$isParticipating): ?>
						<div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800 leading-relaxed">
							<b>You are not currently participating in the gamification feature for this course.</b> Click the toggle button to join the leaderboard and track your progress.
						</div>
					<?php else: ?>
						<div class="p-4 rounded-xl bg-[#00A0A5] text-white space-y-2">
							<div class="flex justify-between items-center text-xs">
								<span class="text-slate-300">Current Level</span>
								<span class="font-bold text-emerald-400 text-sm">Level <?= $userLevel ?></span>
							</div>
							<div class="text-xs text-slate-400"><?= $pointsToNext ?> XP needed for Level <?= $userLevel + 1 ?></div>
							<div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden mt-1">
								<div class="bg-emerald-400 h-full rounded-full" style="width: <?= min(100, max(0, intval(($uTotalPoints % 500) / 5))) ?>%"></div>
							</div>
						</div>

						<div class="space-y-2 text-xs divide-y divide-slate-100">
							<div class="flex justify-between py-1.5"><span class="text-slate-600">Total Points</span><span class="font-bold text-slate-900 font-mono"><?= number_format($uTotalPoints) ?></span></div>
							<div class="flex justify-between py-1.5"><span class="text-slate-600">Originality Score</span><span class="font-bold text-slate-700 font-mono"><?= number_format($uSubmissionPoints) ?></span></div>
							<div class="flex justify-between py-1.5"><span class="text-slate-600">Code Quality Score</span><span class="font-bold text-slate-700 font-mono"><?= number_format($uQualityPoints) ?></span></div>
							<div class="flex justify-between py-1.5"><span class="text-slate-600">Efficiency Score</span><span class="font-bold text-slate-700 font-mono"><?= number_format($uEfficiencyPoints) ?></span></div>
						</div>
					<?php endif; ?>

					<form action="student_game_toggle.php" method="post" class="pt-2">
						<input type="hidden" name="id" value="<?= htmlspecialchars((string)$gsID) ?>">
						<input type="hidden" name="is_participating" value="<?= htmlspecialchars((string)$isParticipating) ?>">
						<input type="hidden" name="course_id" value="<?= htmlspecialchars((string)$courseID) ?>">
						<?php if ($isParticipating): ?>
							<button type="submit" class="w-full py-2.5 px-4 bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 text-sm font-semibold rounded-xl transition shadow-2xs">
								Turn Off Gamification
							</button>
						<?php else: ?>
							<button type="submit" class="w-full py-2.5 px-4 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-semibold rounded-xl transition shadow-xs">
								Turn On Gamification &rarr;
							</button>
						<?php endif; ?>
					</form>
				</div>

				<!-- Game Explanation & Prize -->
				<div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
					<div>
						<span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Course Incentive</span>
						<div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs font-semibold text-amber-900 flex items-center gap-2">
							<svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
							<span><strong>Prize Details:</strong> <?= htmlspecialchars($prizeText ?: 'No specific prize designated by the instructor.') ?></span>
						</div>
					</div>

					<div class="space-y-3 text-xs text-slate-600 leading-relaxed">
						<h4 class="font-bold text-slate-900 text-xs">How Points Are Earned</h4>
						<p>Students earn gamification points by submitting original, high-quality, and computationally efficient programs. Original programs reduce plagiarism risks, high-quality programs demonstrate maintainability, and efficient code reflects low computational overhead and environmental awareness.</p>
						<p class="text-slate-500 italic">Note: You can opt out at any time. Your recorded points will be hidden from the leaderboard but securely preserved should you choose to rejoin.</p>
					</div>

					<?php if ($isParticipating): ?>
						<!-- Interactive Analytics Charts -->
						<div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-100">
							<div class="bg-slate-50 rounded-xl p-3 border border-slate-200/80">
								<h5 class="text-[11px] font-bold text-slate-700 text-center mb-2">Category Comparison (Radar)</h5>
								<div class="h-48">
									<canvas id="radarChart"></canvas>
								</div>
							</div>
							<div class="bg-slate-50 rounded-xl p-3 border border-slate-200/80">
								<h5 class="text-[11px] font-bold text-slate-700 text-center mb-2">Assessment Timeline (Line)</h5>
								<div class="h-48">
									<canvas id="lineChart"></canvas>
								</div>
							</div>
						</div>

						<script>
							const ctxRadar = document.getElementById('radarChart').getContext('2d');
							new Chart(ctxRadar, {
								type: 'radar',
								data: {
									labels: ['Originality', 'Quality', 'Efficiency'],
									datasets: [
										{
											label: 'Your Points',
											data: [<?= $uSubmissionPoints ?: 95 ?>, <?= $uQualityPoints ?: 98 ?>, <?= $uEfficiencyPoints ?: 96 ?>],
											backgroundColor: 'rgba(15, 23, 42, 0.15)',
											borderColor: '#0f172a',
											borderWidth: 2
										},
										{
											label: 'Class Average',
											data: [<?= $allSubmissionPoints ?: 90 ?>, <?= $allQualityPoints ?: 92 ?>, <?= $allEfficiencyPoints ?: 91 ?>],
											backgroundColor: 'rgba(16, 185, 129, 0.15)',
											borderColor: '#10b981',
											borderWidth: 2
										}
									]
								},
								options: {
									responsive: true,
									maintainAspectRatio: false,
									plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
									scales: { r: { ticks: { display: false } } }
								}
							});

							const ctxLine = document.getElementById('lineChart').getContext('2d');
							new Chart(ctxLine, {
								type: 'line',
								data: {
									labels: [<?= ($uArrAssessmentNames != "") ? substr($uArrAssessmentNames,1) : "'Lab 1', 'Lab 2', 'Assignment 1', 'Project 1'" ?>],
									datasets: [
										{ label: 'Originality', data: [<?= ($uArrSubmissionPoints != "") ? substr($uArrSubmissionPoints,1) : "95, 95, 95, 95" ?>], borderColor: '#3b82f6', tension: 0.2 },
										{ label: 'Quality', data: [<?= ($uArrQualityPoints != "") ? substr($uArrQualityPoints,1) : "98, 98, 98, 98" ?>], borderColor: '#10b981', tension: 0.2 },
										{ label: 'Efficiency', data: [<?= ($uArrEfficiencyPoints != "") ? substr($uArrEfficiencyPoints,1) : "96, 96, 96, 96" ?>], borderColor: '#f59e0b', tension: 0.2 }
									]
								},
								options: {
									responsive: true,
									maintainAspectRatio: false,
									plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
									scales: { x: { ticks: { font: { size: 9 } } }, y: { ticks: { font: { size: 9 } } } }
								}
							});
						</script>
					<?php endif; ?>
				</div>

			</div>

		</div>
	</main>
</body>
</html>
