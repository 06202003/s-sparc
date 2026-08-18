<?php
	// default template
	include("_sessionchecker.php");
	include("_config.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Student Dashboard</title>
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

		function showDescriptionModal(title, content) {
			Swal.fire({
				title: title,
				html: `<div class="text-left text-xs text-slate-700 leading-relaxed max-h-96 overflow-y-auto whitespace-pre-line p-2">${content}</div>`,
				confirmButtonText: 'Close',
				confirmButtonColor: '#0f172a',
				width: '550px'
			});
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
	<?php setHeaderStudent("dashboard", "Student home"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Page Header Banner -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Student Portal
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Active Academic Term
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Active Assessments & Tasks</h1>
					<p class="text-xs text-slate-500 mt-1">Overview of upcoming course assessments, submission deadlines, and grading reports.</p>
				</div>
				<div class="flex items-center gap-2">
					<a href="student_enroll.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl transition shadow-2xs">
						<svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
						<span>Enroll Course</span>
					</a>
					<a href="ssparc/courses.php" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-[#00A0A5] hover:bg-[#008488] rounded-xl transition shadow-xs">
						<svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
						<span>Open S-SPARC AI</span>
					</a>
				</div>
			</div>

			<!-- Priority Due Assessments Section -->
			<?php
				$sqlDue = "SELECT assessment.assessment_id, assessment.name AS assessment_name, assessment.submission_close_time, course.name AS course_name,
							TIMESTAMPDIFF(HOUR, CURRENT_TIMESTAMP, assessment.submission_close_time) AS hours_left
							FROM assessment 
							INNER JOIN enrollment ON enrollment.course_id = assessment.course_id
							INNER JOIN course ON course.course_id = enrollment.course_id
							WHERE enrollment.student_id = '".$_SESSION['user_id']."'
							AND assessment.submission_close_time > CURRENT_TIMESTAMP
							AND assessment.submission_open_time <= CURRENT_TIMESTAMP
							AND course.is_active = 1
							ORDER BY assessment.submission_close_time ASC LIMIT 3";
				$resDue = mysqli_query($db, $sqlDue);

				if ($resDue && $resDue->num_rows > 0):
			?>
				<div class="bg-gradient-to-r from-amber-500/10 via-amber-50 to-orange-50 border border-amber-200/80 rounded-2xl p-5 shadow-2xs space-y-3">
					<div class="flex items-center justify-between gap-4">
						<div class="flex items-center gap-2 text-amber-900">
							<span class="p-1.5 bg-amber-500 text-white rounded-lg font-bold text-xs">
								<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
							</span>
							<div>
								<h2 class="text-sm font-bold tracking-tight">Priority Assessment Deadlines</h2>
								<p class="text-[11px] text-amber-700">Upcoming coursework tasks requiring immediate submission attention.</p>
							</div>
						</div>
						<span class="text-[11px] font-bold uppercase tracking-wider bg-amber-200/60 text-amber-900 px-2.5 py-1 rounded-full border border-amber-300/50">
							Urgent Queue
						</span>
					</div>

					<div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-1">
						<?php while ($rowDue = $resDue->fetch_assoc()): 
							$hours = (int)$rowDue['hours_left'];
							$timeLabel = ($hours <= 24) ? ($hours . 'h left') : (ceil($hours / 24) . ' days left');
							$badgeColor = ($hours <= 24) ? 'bg-rose-100 text-rose-800 border-rose-200 font-bold' : 'bg-amber-100 text-amber-800 border-amber-200';
						?>
							<div class="bg-white rounded-xl border border-amber-200/70 p-3.5 flex flex-col justify-between space-y-2 shadow-2xs">
								<div>
									<div class="flex items-center justify-between gap-2 mb-1">
										<span class="text-[11px] font-bold text-slate-500 uppercase truncate"><?= htmlspecialchars($rowDue['course_name']); ?></span>
										<span class="px-2 py-0.5 rounded-md text-[11px] border <?= $badgeColor; ?>"><?= $timeLabel; ?></span>
									</div>
									<h3 class="text-xs font-bold text-slate-900 line-clamp-1"><?= htmlspecialchars($rowDue['assessment_name']); ?></h3>
									<p class="text-[11px] font-mono text-slate-500 mt-1">Due: <?= htmlspecialchars($rowDue['submission_close_time']); ?></p>
								</div>
								<form action="student_assessment_submit.php" method="POST" class="pt-1">
									<input type="hidden" name="id" value="<?= htmlspecialchars($rowDue['assessment_id']); ?>">
									<input type="hidden" name="name" value="<?= htmlspecialchars($rowDue['assessment_name']); ?>">
									<button type="submit" class="w-full py-1.5 px-3 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-semibold rounded-lg shadow-2xs transition flex items-center justify-center gap-1.5">
										<span>Submit Task</span>
										<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
									</button>
								</form>
							</div>
						<?php endwhile; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Assessments Table Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
				<div class="overflow-x-auto">
					<table id="studentDashboard" class="w-full text-left text-xs" style="width:100%">
						<thead>
							<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
								<th class="py-3 px-3">Assessment Name</th>
								<th class="py-3 px-3 text-center" style="width: 100px;">Details</th>
								<th class="py-3 px-3" style="width: 20%;">Course</th>
								<th class="py-3 px-3">Deadline</th>
								<th class="py-3 px-3 text-center" style="width: 110px;">Status</th>
								<th class="py-3 px-3 text-right" style="min-width: 220px;">Actions</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							<?php
								$sql = "SELECT assessment.assessment_id, assessment.public_assessment_id, assessment.name AS assessment_name, assessment.description, assessment.submission_close_time,
									 assessment.course_id, course.name AS course_name
									 FROM assessment INNER JOIN enrollment ON enrollment.course_id = assessment.course_id
									 INNER JOIN course ON course.course_id = enrollment.course_id
									 WHERE enrollment.student_id = '".$_SESSION['user_id']."'
									 AND (assessment.submission_close_time > CURRENT_TIMESTAMP OR assessment.allow_late_submission = '1')
									 AND assessment.submission_open_time < CURRENT_TIMESTAMP
									 AND course.is_active = 1 
									 ORDER BY assessment.submission_close_time ASC";
								$result = mysqli_query($db,$sql);

								if ($result && $result->num_rows > 0) {
									while($row = $result->fetch_assoc()) {
										$sqlt = "SELECT MAX(submission_id) AS sub_id FROM submission
											WHERE submitter_id = '".$_SESSION['user_id']."' AND assessment_id = '".$row['assessment_id']."'";
										$resultt = mysqli_query($db,$sqlt);
										$rowt = ($resultt) ? $resultt->fetch_assoc() : ['sub_id' => ''];

										$descClean = trim($row['description']);
										$descClean = preg_replace('/<p>\s*<br>\s*<\/p>/', '', $descClean);
										$descCleanEscaped = addslashes(htmlspecialchars(strip_tags($descClean)));
										$titleEscaped = addslashes(htmlspecialchars($row['assessment_name']));

										echo '<tr class="hover:bg-slate-50/80 transition-colors">';
										echo '<td class="py-3.5 px-3 font-bold text-slate-900">'.htmlspecialchars($row['assessment_name']).'</td>';
										
										echo '<td class="py-3.5 px-3 text-center">';
										if (!empty($descClean)) {
											echo '<button type="button" onclick="showDescriptionModal(\''.$titleEscaped.'\', \''.$descCleanEscaped.'\')" class="px-2.5 py-1 text-[11px] font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition">View</button>';
										} else {
											echo '<span class="text-slate-400 text-[11px]">-</span>';
										}
										echo '</td>';

										echo '<td class="py-3.5 px-3 font-medium text-slate-700">'.htmlspecialchars($row['course_name']).'</td>';
										echo '<td class="py-3.5 px-3 font-mono text-slate-600">'.htmlspecialchars($row['submission_close_time']).'</td>';

										if (!empty($rowt['sub_id'])) {
											echo '<td class="py-3.5 px-3 text-center"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Submitted</span></td>';
											echo '<td class="py-3.5 px-3 text-right">';
											echo '<div class="flex items-center justify-end gap-1.5 flex-wrap">';
											
											// Download submission
											echo '<form class="inline" action="user_download_code.php" method="post">
													<input type="hidden" name="id" value="'.htmlspecialchars($rowt['sub_id']).'">
													<button type="submit" class="px-2.5 py-1 text-[11px] font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-lg transition">Download</button>
												  </form>';

											// Resubmit
											echo '<form class="inline" action="student_assessment_submit.php?id='.htmlspecialchars($row['public_assessment_id']).'" method="post">
													<button type="submit" class="px-2.5 py-1 text-[11px] font-semibold bg-[#00A0A5] text-white hover:bg-slate-800 rounded-lg transition shadow-2xs">Resubmit</button>
												  </form>';

											// Suspicion report
											$sqlSusp = "SELECT suspicion_id, suspicion_type FROM suspicion WHERE submission_id = '".$rowt['sub_id']."'";
											$resSusp = mysqli_query($db, $sqlSusp);
											if ($resSusp && $resSusp->num_rows > 0) {
												$rowSusp = $resSusp->fetch_assoc();
												$btnLabel = ($rowSusp['suspicion_type'] == "real") ? "Originality Report" : "Originality Simulation";
												$btnClass = ($rowSusp['suspicion_type'] == "real") ? "bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100" : "bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100";
												echo '<form class="inline" action="user_suspicion_report.php" method="post">
														<input type="hidden" name="id" value="'.htmlspecialchars($rowSusp['suspicion_id']).'">
														<input type="hidden" name="course_name" value="'.htmlspecialchars($row['course_name']).'">
														<input type="hidden" name="assessment_name" value="'.htmlspecialchars($row['assessment_name']).'">
														<input type="hidden" name="mode" value="1">
														<button type="submit" class="px-2.5 py-1 text-[11px] font-semibold rounded-lg transition '.$btnClass.'">'.$btnLabel.'</button>
													  </form>';
											}

											// Code quality suggestion
											$sqlQual = "SELECT public_suggestion_id FROM code_clarity_suggestion WHERE submission_id = '".$rowt['sub_id']."'";
											$resQual = mysqli_query($db, $sqlQual);
											if ($resQual && $resQual->num_rows > 0) {
												$rowQual = $resQual->fetch_assoc();
												echo '<form class="inline" action="student_code_clarity.php?id='.htmlspecialchars($rowQual['public_suggestion_id']).'" method="post">
														<input type="hidden" name="mode" value="1">
														<button type="submit" class="px-2.5 py-1 text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 rounded-lg transition">Code Clarity</button>
													  </form>';
											}

											echo '</div></td>';
										} else {
											echo '<td class="py-3.5 px-3 text-center"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span></td>';
											echo '<td class="py-3.5 px-3 text-right">
													<a href="student_assessment_submit.php?id='.htmlspecialchars($row['public_assessment_id']).'" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-[#00A0A5] text-white hover:bg-slate-800 rounded-lg transition shadow-2xs">
														<span>Submit Solution</span>
														<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
													</a>
												  </td>';
										}

										echo '</tr>';
									}
								}
							?>
						</tbody>
					</table>
				</div>
			</div>

		</div>
	</main>

	<script>
		$(document).ready(function() {
			new DataTable('#studentDashboard', {
				responsive: true,
				order: [[3, 'asc']],
				pageLength: 10,
				language: {
					search: "_INPUT_",
					searchPlaceholder: "Search assessments..."
				}
			});
		});
	</script>
</body>
</html>
