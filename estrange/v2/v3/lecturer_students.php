<?php
include("_sessionchecker.php");
include("_config.php");

if($_SERVER["REQUEST_METHOD"] == "POST") {
	if(isset($_POST['id']) == true){ // this is course id
		// if landed from course page
		// set the course data in the session
		$_SESSION['course_id'] = mysqli_real_escape_string($db,$_POST['id']);
		$_SESSION['course_name'] = mysqli_real_escape_string($db,$_POST['name']);
	}else if(isset($_POST['did']) == true){
		// unenroll student from a course
		$id = mysqli_real_escape_string($db,$_POST['did']);
		
		
		$sql = "SELECT submission.submission_id FROM submission
				INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id
				INNER JOIN course ON assessment.course_id = course.course_id
				INNER JOIN enrollment ON course.course_id = enrollment.course_id
				WHERE enrollment.enrollment_id = '".$id."' AND enrollment.student_id = submission.submitter_id ";
		$result = mysqli_query($db,$sql);
		if(mysqli_num_rows($result) > 0){
			// if the student has submitted anything in that course, cannot delete
			echo "<script>alert('The enrollment cannot be deleted since the student has submitted at least a program to the course\'s assessments.');</script>";
		}else{
			// otherwise, remove game_student_course entry for that students
			$sql = "DELETE g FROM game_student_course g
					INNER JOIN enrollment ON enrollment.course_id = g.course_id 
					AND enrollment.student_id = g.student_id
					WHERE enrollment.enrollment_id = '$id'";
			if ($db->query($sql) === TRUE) {
				$sql = "DELETE FROM enrollment WHERE enrollment_id = '$id'";
				if ($db->query($sql) === TRUE) {
					// if removed well, redirect to dashboard
					header('Location: lecturer_students.php');
					exit;
				}else{
					echo "Error removing record: " . $db->error;
				}
			} else {
				echo "<script>alert('The enrollment cannot be deleted since the student has participated in game feature of one or more courses.');</script>";
				header('Location: lecturer_students.php');
				exit;
			}
		}
	}
}

// if the session values do not exist
if(isset($_SESSION['course_id']) == false){
	header('Location: lecturer_dashboard.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Course Students</title>
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
		function triggerStudentUnenroll(enrollmentId, username) {
			Swal.fire({
				title: 'Unenroll Student?',
				text: `Are you sure you want to remove "${username}" from this course? Students with submitted programs cannot be unenrolled.`,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#e11d48',
				cancelButtonColor: '#64748b',
				confirmButtonText: 'Yes, Unenroll Student'
			}).then((result) => {
				if (result.isConfirmed) {
					document.getElementById('unenroll-form-' + enrollmentId).submit();
				}
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
	<?php setHeaderLecturer("courses", "Course students"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Roster
						</span>
						<span class="text-xs font-semibold text-slate-500">
							<?= htmlspecialchars($_SESSION['course_name'] ?? 'Course') ?>
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Enrolled Student Cohort</h1>
					<p class="text-xs text-slate-500 mt-1">Manage student enrollments, inspect cohort roster, and add new students.</p>
				</div>
				<div class="flex items-center gap-2">
					<button id="return-student" onclick="window.open('lecturer_dashboard.php', '_self');" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
						<span>Back to Courses</span>
					</button>
					<a href="lecturer_enrollment_student.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#00A0A5] hover:bg-[#008488] text-white text-xs font-semibold rounded-xl shadow-xs transition duration-150">
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
						<span>Enroll Students</span>
					</a>
				</div>
			</div>

			<script>
				document.getElementById('return-student').addEventListener('click', function() {
					localStorage.setItem('returning_from_student', 'true');
				});
			</script>

			<!-- Student Roster Table Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
				<div class="overflow-x-auto">
					<table id="lecturerStudentTable" class="w-full text-left text-xs" style="width:100%">
						<thead>
							<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
								<th class="py-3 px-3" style="width: 20%;">Student ID / Username</th>
								<th class="py-3 px-3" style="width: 25%;">Full Name</th>
								<th class="py-3 px-3" style="width: 25%;">Email Address</th>
								<th class="py-3 px-3" style="width: 150px;">Enrollment Date</th>
								<th class="py-3 px-3 text-right" style="width: 100px;">Actions</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							<?php
								$sql = "SELECT enrollment.enrollment_id, user.username, user.name, user.email, enrollment.enrollment_time
									FROM enrollment	INNER JOIN user ON user.user_id = enrollment.student_id
									WHERE enrollment.course_id = '".$_SESSION['course_id']."' ORDER BY enrollment.enrollment_time DESC";
								$result = mysqli_query($db,$sql);

								if ($result && $result->num_rows > 0) {
									while($row = $result->fetch_assoc()) {
										$usernameEscaped = addslashes(htmlspecialchars($row['username']));

										echo '<tr class="hover:bg-slate-50/80 transition-colors">';
										
										// Username
										echo '<td class="py-3.5 px-3 font-bold font-mono text-slate-900">';
										echo htmlspecialchars($row['username']);
										echo '</td>';

										// Full Name
										echo '<td class="py-3.5 px-3 font-semibold text-slate-800">';
										echo htmlspecialchars($row['name'] ?? 'N/A');
										echo '</td>';

										// Email
										echo '<td class="py-3.5 px-3 text-slate-600">';
										echo htmlspecialchars($row['email'] ?? 'N/A');
										echo '</td>';

										// Enrollment Date
										echo '<td class="py-3.5 px-3 text-slate-600 font-mono text-[11px]">';
										echo htmlspecialchars($row['enrollment_time']);
										echo '</td>';

										// Action Button
										echo '<td class="py-3.5 px-3 text-right">';
										echo '<button type="button" onclick="triggerStudentUnenroll(\''.htmlspecialchars($row['enrollment_id']).'\', \''.$usernameEscaped.'\')" class="px-2.5 py-1 text-[11px] font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg transition">Remove</button>';

										echo '<form id="unenroll-form-'.htmlspecialchars($row['enrollment_id']).'" action="'.htmlentities($_SERVER['PHP_SELF']).'" method="post" class="hidden">
												<input type="hidden" name="did" value="'.htmlspecialchars($row['enrollment_id']).'">
											  </form>';

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
			var table = new DataTable('#lecturerStudentTable', {
				responsive: true,
				pageLength: 10,
				lengthMenu: [10, 25, 50, 100],
				language: { search: "_INPUT_", searchPlaceholder: "Search students..." }
			});
		});
	</script>
</body>
</html>
