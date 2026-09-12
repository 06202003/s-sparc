<?php
	include("_sessionchecker.php");
	include("_config.php");
	
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
				  header('Location: admin_dashboard.php');
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
	<title>E-STRANGE: Admin Course Management</title>
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
	<?php setHeaderAdmin("courses", "Admin Course"); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header & Action Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Admin Governance
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Course Administration
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Admin Managed Courses</h1>
					<p class="text-xs text-slate-500 mt-1">Configure class enrollment rules, manage assignments, and oversee course participants.</p>
				</div>
				<div>
					<a href="admin_course_add.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#00A0A5] hover:bg-[#008488] text-white text-xs font-bold rounded-xl transition shadow-2xs">
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
						<span>Add New Course</span>
					</a>
				</div>
			</div>

			<!-- Courses Table Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
				<div class="overflow-x-auto">
					<table id="adminDashboardTable" class="w-full text-left text-xs" style="width:100%">
						<thead>
							<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
								<th class="py-3 px-3" style="width: 25%;">Course Name</th>
								<th class="py-3 px-3" style="width: 25%;">Description</th>
								<th class="py-3 px-3 text-center" style="width: 12%;">Enrollment</th>
								<th class="py-3 px-3 text-center" style="width: 10%;">Gamification</th>
								<th class="py-3 px-3 text-right" style="width: 28%;">Actions</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							<?php
								$sql = "SELECT course.course_id, course.name, course.description, course.enrollment_mode, game_course.is_active FROM course 
								INNER JOIN game_course ON game_course.course_id = course.course_id 
								WHERE creator_id = '".$_SESSION['user_id']."' ORDER BY time_created DESC";
								$result = mysqli_query($db, $sql);
								
								if ($result && $result->num_rows > 0) {
									while ($row = $result->fetch_assoc()) {
							?>
								<tr class="hover:bg-slate-50/80 transition-colors">
									<td class="py-3 px-3 font-bold text-slate-900">
										<?= htmlspecialchars($row['name']); ?>
									</td>
									<td class="py-3 px-3 text-slate-600 text-[11px] leading-relaxed">
										<?= htmlspecialchars($row['description']); ?>
									</td>
									<td class="py-3 px-3 text-center">
										<?php if ($row['enrollment_mode'] == 0): ?>
											<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">Manual</span>
										<?php else: ?>
											<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Public</span>
										<?php endif; ?>
									</td>
									<td class="py-3 px-3 text-center">
										<?php if ($row['is_active'] == 1): ?>
											<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Active</span>
										<?php else: ?>
											<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-500">Off</span>
										<?php endif; ?>
									</td>
									<td class="py-3 px-3 text-right">
										<div class="flex flex-wrap items-center justify-end gap-1.5">
											<form action="admin_assessments.php" method="POST" class="inline">
												<input type="hidden" name="id" value="<?= htmlspecialchars($row['course_id']); ?>">
												<input type="hidden" name="name" value="<?= htmlspecialchars($row['name']); ?>">
												<button type="submit" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold rounded-lg transition">
													Tasks
												</button>
											</form>
											<form action="admin_students.php" method="POST" class="inline">
												<input type="hidden" name="id" value="<?= htmlspecialchars($row['course_id']); ?>">
												<input type="hidden" name="name" value="<?= htmlspecialchars($row['name']); ?>">
												<button type="submit" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold rounded-lg transition">
													Students
												</button>
											</form>
											<form action="admin_colecturer.php" method="POST" class="inline">
												<input type="hidden" name="id" value="<?= htmlspecialchars($row['course_id']); ?>">
												<input type="hidden" name="name" value="<?= htmlspecialchars($row['name']); ?>">
												<button type="submit" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold rounded-lg transition">
													Co-Lecturers
												</button>
											</form>
											<?php if ($row['is_active'] == 1): ?>
												<a href="admin_game.php?id=<?= htmlspecialchars($row['course_id']); ?>" class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 text-[11px] font-bold rounded-lg border border-amber-200 transition">
													Game
												</a>
											<?php endif; ?>
											<form action="admin_course_update.php" method="POST" class="inline">
												<input type="hidden" name="id" value="<?= htmlspecialchars($row['course_id']); ?>">
												<button type="submit" class="px-2 py-1 bg-[#00A0A5] hover:bg-[#008488] text-white text-[11px] font-semibold rounded-lg transition">
													Edit
												</button>
											</form>
											<button type="button" onclick="confirmDelete('<?= htmlspecialchars($row['course_id']); ?>', '<?= htmlspecialchars($row['name'], ENT_QUOTES); ?>')"
												class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-bold rounded-lg border border-rose-200 transition">
												Delete
											</button>
										</div>
									</td>
								</tr>
							<?php
									}
								}
							?>
						</tbody>
					</table>
				</div>
			</div>

		</div>
	</main>

	<!-- Hidden Delete Form -->
	<form id="deleteCourseForm" action="<?= htmlentities($_SERVER['PHP_SELF']); ?>" method="POST" class="hidden">
		<input type="hidden" name="id" id="deleteCourseId">
	</form>

	<script>
		$(document).ready(function() {
			new DataTable('#adminDashboardTable', {
				responsive: true,
				pageLength: 10,
				lengthMenu: [5, 10, 25, 50],
				language: { search: "_INPUT_", searchPlaceholder: "Search course..." }
			});
		});

		function confirmDelete(courseId, courseName) {
			Swal.fire({
				title: 'Delete Course?',
				text: `Are you sure you want to delete ${courseName}? Courses with enrolled students or assessments cannot be deleted.`,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#e11d48',
				cancelButtonColor: '#64748b',
				confirmButtonText: 'Yes, delete',
				cancelButtonText: 'Cancel',
				customClass: {
					popup: 'rounded-2xl',
					confirmButton: 'rounded-xl text-xs px-4 py-2 font-bold',
					cancelButton: 'rounded-xl text-xs px-4 py-2 font-bold'
				}
			}).then((result) => {
				if (result.isConfirmed) {
					$('#deleteCourseId').val(courseId);
					$('#deleteCourseForm').submit();
				}
			});
		}
	</script>
</body>
</html>
