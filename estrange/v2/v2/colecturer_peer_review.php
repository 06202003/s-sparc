<?php
    date_default_timezone_set('Asia/Jakarta');
	include("_sessionchecker.php");
	include("_config.php");

	generate_peer_review_assessments($db);

	// Mengambil Daftar Course untuk Filter
    $colecturer_id = $_SESSION['user_id'];
    $courses_list = []; // Array untuk menyimpan course
    
    // Query ini mengambil course-course unik yang dimiliki dosen yang memiliki peer review assessment
    $sql_courses = "SELECT DISTINCT c.course_id, c.name 
                    FROM course c
                    JOIN colecturer co ON c.course_id = co.course_id
                    JOIN assessment a ON c.course_id = a.course_id
                    JOIN peer_review_assessment pra ON a.assessment_id = pra.assessment_id
                    WHERE co.user_id = ?
                    ORDER BY c.name";
                    
    $stmt_courses = $db->prepare($sql_courses);
    $stmt_courses->bind_param("i", $colecturer_id);
    $stmt_courses->execute();
    $result_courses = $stmt_courses->get_result();
    while ($course = $result_courses->fetch_assoc()) {
        $courses_list[] = $course; // Simpan di array
    }
    $stmt_courses->close();
	
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
	<title>E-STRANGE: Co-Lecturer Peer Review</title>
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
	<?php
		setHeaderStudent("peer_review", "Co-Lecturer Peer Review");
	?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Co-Lecturer Evaluation
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Collaborative Assessment
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Peer Review Management</h1>
					<p class="text-xs text-slate-500 mt-1">Configure peer review timeframes, assignment distributions, and review status tracking.</p>
				</div>
				<div class="flex items-center gap-2.5">
					<a href="student_peer_review.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
						<span>Student Peer Review</span>
					</a>
					<a href="colecturer_peer_review_add.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#00A0A5] hover:bg-[#008488] text-white text-xs font-bold rounded-xl transition shadow-2xs">
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
						<span>Add Peer Review Task</span>
					</a>
				</div>
			</div>

			<!-- Filter Bar Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-4 flex flex-wrap items-center justify-between gap-4">
				<div class="flex items-center gap-2.5">
					<label for="courseFilterDropdown" class="text-xs font-bold uppercase tracking-wider text-slate-600">Filter Course:</label>
					<select id="courseFilterDropdown" class="min-w-[200px] shrink-0 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
						<option value="">All Courses</option>
						<?php foreach ($courses_list as $course): ?>
							<option value="<?= htmlspecialchars($course['name']); ?>"><?= htmlspecialchars($course['name']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<!-- Tasks Table Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
				<div class="overflow-x-auto">
					<table id="colecturerPeerReviewTable" class="w-full text-left text-xs" style="width:100%">
						<thead>
							<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
								<th class="py-3 px-3" style="width: 28%;">Assessment Name</th>
								<th class="py-3 px-3" style="width: 24%;">Course</th>
								<th class="py-3 px-3" style="width: 17%;">Start Time</th>
								<th class="py-3 px-3" style="width: 17%;">Deadline</th>
								<th class="py-3 px-3 text-right" style="width: 14%;">Actions</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							<?php
								$colecturer_id = $_SESSION['user_id'];
								$sql = "SELECT 
											pra.pr_assessment_id,
											a.name AS assessment_name,
											c.name AS course_name,
											pra.peer_review_start_time,
											pra.peer_review_close_time,
											pra.is_pr_assessment_generated
										FROM 
											peer_review_assessment pra
										JOIN 
											assessment a ON pra.assessment_id = a.assessment_id
										JOIN 
											course c ON a.course_id = c.course_id
										JOIN 
											colecturer co ON c.course_id = co.course_id
										WHERE 
											co.user_id = ?"; 

								$stmt = $db->prepare($sql);
								$stmt->bind_param("i", $colecturer_id);
								$stmt->execute();
								$result = $stmt->get_result();

								if ($result && $result->num_rows > 0) {
									while ($row = $result->fetch_assoc()) {
										$now = new DateTime();
										$start_time = new DateTime($row['peer_review_start_time']);
										$close_time = new DateTime($row['peer_review_close_time']);
							?>
								<tr class="hover:bg-slate-50/80 transition-colors">
									<td class="py-3 px-3 font-bold text-slate-900">
										<?= htmlspecialchars($row['assessment_name']); ?>
									</td>
									<td class="py-3 px-3 text-slate-700 font-medium">
										<?= htmlspecialchars($row['course_name']); ?>
									</td>
									<td class="py-3 px-3 text-slate-500 font-mono text-[11px]">
										<?= htmlspecialchars($row['peer_review_start_time']); ?>
									</td>
									<td class="py-3 px-3 text-slate-500 font-mono text-[11px]">
										<?= htmlspecialchars($row['peer_review_close_time']); ?>
									</td>
									<td class="py-3 px-3 text-right">
										<div class="flex items-center justify-end gap-1.5">
											<a href="colecturer_peer_review_edit.php?id=<?= htmlspecialchars($row['pr_assessment_id']); ?>"
												class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold rounded-lg transition">
												Edit
											</a>
											<?php if ($now >= $start_time): ?>
												<a href="colecturer_peer_review_list.php?id=<?= htmlspecialchars($row['pr_assessment_id']); ?>"
													class="px-2.5 py-1 bg-[#00A0A5] hover:bg-[#008488] text-white text-[11px] font-bold rounded-lg transition">
													View
												</a>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php
									}
								}
								$stmt->close();
							?>
						</tbody>
					</table>
				</div>
			</div>

		</div>
	</main>

	<script>
		$(document).ready(function() {
			var table = new DataTable('#colecturerPeerReviewTable', {
				responsive: true,
				pageLength: 10,
				lengthMenu: [5, 10, 25, 50],
				order: [[3, 'desc']],
				language: { search: "_INPUT_", searchPlaceholder: "Search peer reviews..." }
			});

			$('#courseFilterDropdown').on('change', function() {
				var selectedCourseName = $(this).val();
				table.column(1).search(selectedCourseName).draw();
			});
		});
	</script>
</body>
</html>
