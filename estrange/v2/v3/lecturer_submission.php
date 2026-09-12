<?php
	include("_sessionchecker.php");
	include("_config.php");

	if($_SERVER["REQUEST_METHOD"] == "POST") {
		// set current assessment to the session
		if(isset($_POST['id'])){
				// if landed from assessment page
				$_SESSION['assessment_id'] = mysqli_real_escape_string($db,$_POST['id']);
				$_SESSION['assessment_name'] = mysqli_real_escape_string($db,$_POST['name']);
		}
	}

	// redirect if the sessions are not set
	if(isset($_SESSION['assessment_id']) == false){
	  header('Location: lecturer_dashboard.php');
		exit;
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Assessment Submissions</title>
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
	<?php setHeaderLecturer("courses", "Assessment submissions"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Submissions
						</span>
						<span class="text-xs font-semibold text-slate-500">
							<?= htmlspecialchars($_SESSION['assessment_name'] ?? 'Assessment') ?>
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Student Submissions &amp; Code Artifacts</h1>
					<p class="text-xs text-slate-500 mt-1">Review student programs, inspect originality alerts, and download submission archives.</p>
				</div>
				<div class="flex items-center gap-2">
					<button id="return-assessment" onclick="window.open('lecturer_assessments.php', '_self');" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
						<span>Back to Assessments</span>
					</button>
				</div>
			</div>

			<script>
				document.getElementById('return-assessment').addEventListener('click', function() {
					localStorage.setItem('returning_from_submission', 'true');
				});
			</script>

			<!-- Missing Student Submissions Notification Banner -->
			<?php
				$sqlt = "SELECT user.username, user.name 
					FROM user
					INNER JOIN enrollment ON enrollment.student_id = user.user_id
					INNER JOIN course ON course.course_id = enrollment.course_id
					INNER JOIN assessment ON assessment.course_id = course.course_id
					WHERE assessment.assessment_id = '".$_SESSION['assessment_id']."'
					AND user.user_id NOT IN 
						(SELECT submitter_id 
						FROM submission WHERE assessment_id = '".$_SESSION['assessment_id']."')";
				$resultt = mysqli_query($db,$sqlt);
				if ($resultt && $resultt->num_rows > 0) {
					$unsubmitted = [];
					while($rowU = $resultt->fetch_assoc()) {
						$unsubmitted[] = htmlspecialchars($rowU['username']) . ($rowU['name'] ? ' (' . htmlspecialchars($rowU['name']) . ')' : '');
					}
					echo '<div class="bg-amber-50/80 border border-amber-200/80 rounded-2xl p-4 text-xs text-amber-900 flex items-start gap-3 shadow-2xs">
							<svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
							<div>
								<span class="font-bold block mb-0.5">Pending Student Submissions ('.count($unsubmitted).' students):</span>
								<span class="text-amber-800 leading-relaxed">'.implode(', ', $unsubmitted).'</span>
							</div>
						  </div>';
				}
			?>

			<!-- Bulk Actions Bar -->
			<?php
				$sqlCheck = "SELECT similarity_report_path FROM assessment WHERE assessment_id = '".$_SESSION['assessment_id']."'";
				$resCheck = mysqli_query($db, $sqlCheck);
				$rowCheck = $resCheck ? $resCheck->fetch_assoc() : null;
				$simPath = $rowCheck['similarity_report_path'] ?? '';
			?>
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
				<span class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Batch Artifact Downloads:</span>
				<div class="flex flex-wrap items-center gap-2">
					<?php
						if (!empty($simPath) && $simPath != 'null') {
							echo '<a href="colecturer_download_sim_report.php?id='.htmlspecialchars($_SESSION['assessment_id']).'" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-semibold rounded-xl transition">Download Similarity Report</a>';
						} else if ($simPath == 'null') {
							echo '<span class="px-3 py-1.5 bg-slate-100 text-slate-400 font-semibold rounded-xl">Too Few Submissions for Report</span>';
						} else {
							$sqlQ = "SELECT queue_id FROM similarity_report_generation_queue WHERE assessment_id = '".$_SESSION['assessment_id']."'";
							$resQ = mysqli_query($db, $sqlQ);
							if ($resQ && $resQ->num_rows > 0) {
								echo '<span class="px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 font-semibold rounded-xl">Report Generating...</span>';
							} else {
								echo '<span class="px-3 py-1.5 bg-slate-100 text-slate-500 font-semibold rounded-xl">Similarity Report Pending Deadline</span>';
							}
						}
					?>
					<a href="colecturer_download_metadata.php" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 font-semibold rounded-xl transition">Export Metadata</a>
					<a href="colecturer_download_all_last_code.php" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 font-semibold rounded-xl transition">Download Final Attempts</a>
					<a href="colecturer_download_all_code.php" class="px-3 py-1.5 bg-[#00A0A5] hover:bg-[#008488] text-white font-semibold rounded-xl shadow-xs transition">Download All Archives</a>
				</div>
			</div>

			<!-- Submissions Table Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
				<div class="overflow-x-auto">
					<table id="lecturerAssessmentTable" class="w-full text-left text-xs" style="width:100%">
						<thead>
							<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
								<th class="py-3 px-3" style="width: 180px;">Student Submitter</th>
								<th class="py-3 px-3" style="width: 180px;">File Name</th>
								<th class="py-3 px-3">Student Notes</th>
								<th class="py-3 px-3 text-center" style="width: 70px;">Attempt</th>
								<th class="py-3 px-3" style="width: 150px;">Timestamp</th>
								<th class="py-3 px-3 text-right" style="width: 200px;">Actions</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							<?php
								$sql = "SELECT submission.submission_id, user.username, user.name AS student_name, submission.filename,
									submission.description, submission.attempt, submission.submission_time,
									submission.submitter_id, assessment.name AS assessment_name, course.name AS course_name, assessment.submission_close_time
									FROM submission	INNER JOIN user ON user.user_id = submission.submitter_id
									INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id
									INNER JOIN course ON assessment.course_id = course.course_id
									WHERE submission.assessment_id = '".$_SESSION['assessment_id']."'
									ORDER BY submission.submission_time DESC";
								$result = mysqli_query($db,$sql);

								if ($result && $result->num_rows > 0) {
									while($row = $result->fetch_assoc()) {
										$isLate = ($row['submission_time'] > $row['submission_close_time']);

										echo '<tr class="hover:bg-slate-50/80 transition-colors">';
										
										// Submitter
										echo '<td class="py-3.5 px-3 font-bold text-slate-900">';
										echo htmlspecialchars($row['username']);
										if (!empty($row['student_name'])) {
											echo ' <span class="text-slate-500 font-normal text-[11px]">('.htmlspecialchars($row['student_name']).')</span>';
										}
										if ($isLate) {
											echo ' <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-rose-100 text-rose-800 ml-1">LATE</span>';
										}
										echo '</td>';

										// Filename
										echo '<td class="py-3.5 px-3 font-mono text-slate-600 text-[11px]">';
										echo htmlspecialchars($row['filename']);
										echo '</td>';

										// Description
										echo '<td class="py-3.5 px-3 text-slate-600 text-sm font-medium leading-relaxed">';
										echo nl2br(htmlspecialchars($row['description'] ?? ''));
										echo '</td>';

										// Attempt
										echo '<td class="py-3.5 px-3 text-center font-bold text-slate-700">';
										echo htmlspecialchars($row['attempt']);
										echo '</td>';

										// Timestamp
										echo '<td class="py-3.5 px-3 text-slate-600 font-mono text-[11px]">';
										echo htmlspecialchars($row['submission_time']);
										echo '</td>';

										// Actions
										echo '<td class="py-3.5 px-3 text-right">';
										echo '<div class="flex items-center justify-end gap-1.5">';
										
										// Download Code Form
										echo '<form action="user_download_code.php" method="post" class="inline">
												<input type="hidden" name="id" value="'.htmlspecialchars($row['submission_id']).'">
												<button type="submit" class="px-2.5 py-1 text-[11px] font-semibold bg-[#00A0A5] hover:bg-[#008488] text-white rounded-lg transition shadow-2xs">Download</button>
											  </form>';

										// Similarity alert if present
										$sqlt = "SELECT suspicion_id FROM suspicion WHERE submission_id = '".$row['submission_id']."' AND suspicion_type = 'real'";
										$resultt = mysqli_query($db,$sqlt);
										if ($resultt && $resultt->num_rows > 0) {
											$rowt = $resultt->fetch_assoc();
											echo '<form action="user_suspicion_report.php" method="post" class="inline">
													<input type="hidden" name="id" value="'.htmlspecialchars($rowt['suspicion_id']).'">
													<input type="hidden" name="course_name" value="'.htmlspecialchars($row['course_name']).'">
													<input type="hidden" name="assessment_name" value="'.htmlspecialchars($row['assessment_name']).'">
													<input type="hidden" name="submitter_id" value="'.htmlspecialchars($row['submitter_id']).'">
													<button type="submit" class="px-2.5 py-1 text-[11px] font-bold bg-amber-100 hover:bg-amber-200 text-amber-900 border border-amber-300 rounded-lg transition">Similarity Alert</button>
												  </form>';
										}

										echo '</div>';
										echo '</td>';

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
		document.addEventListener('DOMContentLoaded', function() {
			var table = new DataTable('#lecturerAssessmentTable', {
				responsive: true,
				order: [[4, 'desc']],
				pageLength: 10,
				lengthMenu: [10, 25, 50, 100],
				language: { search: "_INPUT_", searchPlaceholder: "Search submissions..." }
			});
		});
	</script>
</body>
</html>
