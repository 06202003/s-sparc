<?php
	include("_sessionchecker_peer.php"); //"../_sessionchecker.php"
	include("_config_peer_review.php");
	include("_header_peer_review.php");
	include("student_peer_leaderboard.php");

	// Mengambil course paling kecil untuk default
	$student_id = $_SESSION['user_id'];
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

	// Jika NULL → pakai default_course_id
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
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title>E-STRANGE: Student Peer Review</title>
	<!-- ASLI <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet"> --> 
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="icon" href="../strange_html_layout_additional_files/icon.png">
	
	<!-- DataTables CSS -->
	<!-- ASLI <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css"> -->
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.css"/>
	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>

	<!-- DataTables JS -->
	<!-- ASLI <link rel="stylesheet" type="text/css" href="../datatables/jquery.dataTables.min.css">
	<script type="text/javascript" src="../datatables/jquery.dataTables.min.js"></script>
	<link rel="stylesheet" type="text/css" href="../datatables/responsive.bootstrap5.min.css">
	<script type="text/javascript" src="../datatables/dataTables.responsive.min.js"></script>
	<script type="text/javascript" src="../datatables/responsive.bootstrap5.min.js"></script> -->

	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/3.0.2/css/responsive.bootstrap5.min.css"/>
	<script type="text/javascript" src="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/responsive/3.0.2/js/dataTables.responsive.min.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/responsive/3.0.2/js/responsive.bootstrap5.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

	<!-- The use of Notyf library https://github.com/caroso1222/notyf -->
	<link rel="stylesheet" href="../strange_html_layout_additional_files/notyf.min.css">
	<script src="../strange_html_layout_additional_files/notyf.min.js"></script>
	<script type="text/javascript">
			function loadGameNotif(){
				// Create an instance of Notyf
				var notyf = new Notyf({
				  duration: 0,
				  position: {
					x: 'right',
					y: 'top',
				  },
				  dismissible: true
				});
				
			}

			function construct(){
				loadGameNotif();
			}
			
			// sort table content. Copied and modified from https://www.w3schools.com/howto/howto_js_sort_table.asp
			function sortTable(n, tableId, isNumber, tableContainerId) {
				var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
				table = document.getElementById(tableId);
				switching = true;
				// Set the sorting direction to ascending:
				dir = "asc";
				/* Make a loop that will continue until
				no switching has been done: */
				while (switching) {
					// Start by saying: no switching is done:
					switching = false;
					rows = table.rows;
					/* Loop through all table rows */
					for (i = 0; i < (rows.length - 1); i++) {
						// Start by saying there should be no switching:
						shouldSwitch = false;
						/* Get the two elements you want to compare,
						one from current row and one from the next: */
						x = rows[i].getElementsByTagName("TD")[n];
						y = rows[i + 1].getElementsByTagName("TD")[n];
						if(n==0){
							/*
							* the column content is encapsulated with a link and can provide confusing result
							* as the <A> tag is considered in comparison
							*/
							x = x.getElementsByTagName("A")[0];
							y = y.getElementsByTagName("A")[0];
						}
						/* Check if the two rows should switch place,
						based on the direction, asc or desc: */
						if (dir == "asc") {
							if(isNumber == true){
								numx = Number(x.innerHTML.split(" ")[0]);
								numy = Number(y.innerHTML.split(" ")[0]);
								if (numx > numy ){
									// If so, mark as a switch and break the loop:
									shouldSwitch = true;
									break;
								}
							}else{
								if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
									// If so, mark as a switch and break the loop:
									shouldSwitch = true;
									break;
								}
							}
						} else if (dir == "desc") {
							if(isNumber == true){
								numx = Number(x.innerHTML.split(" ")[0]);
								numy = Number(y.innerHTML.split(" ")[0]);
								if (numx < numy ){
									// If so, mark as a switch and break the loop:
									shouldSwitch = true;
									break;
								}
							}else{
								if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
									// If so, mark as a switch and break the loop:
									shouldSwitch = true;
									break;
								}
							}
						}
					}
					if (shouldSwitch) {
						/* If a switch has been marked, make the switch
						and mark that a switch has been done: */
						rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
						switching = true;
						// Each time a switch is done, increase this count by 1:
						switchcount ++;
					} else {
						/* If no switching has been done AND the direction is "asc",
						set the direction to "desc" and run the while loop again. */
						if (switchcount == 0 && dir == "asc") {
							dir = "desc";
							switching = true;
						}
					}
				}
				recolorTableContent(tableId);
				recolorCodeFragment(previousRowId,"rgba(60,200,246,1)");
			}

			function recolorTableContent(tableId){
				table = document.getElementById(tableId);
				rows = table.rows;
				/* Loop through all table rows */
				for (i = 0; i < rows.length; i++) {
					if(i%2 == 0){
						rows[i].style.backgroundColor = "rgba(255,255,255,1)";
					}else {
						rows[i].style.backgroundColor = "#eeeeee";
					}
				}
			}

			var previousRowId = null;
			function selectRow(id, tableId){
				if(previousRowId != null){
					// for header table, recolor the contents
					recolorTableContent(tableId);
				}
				// for header table, recolor the row
				recolorCodeFragment(id,"rgba(60,200,246,1)");
				previousRowId= id;
			}

			// recolor a code fragment with its following rows
			function recolorCodeFragment(id, defaultColour){
				document.getElementById(id).style.backgroundColor = defaultColour;
			}
    </script>
    <style>
	    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300&display=swap');
		body {
		/* font-family: "Times New Roman", Times, serif; */
		font-family: 'Montserrat', sans-serif;
		}
		.btn-primary{
			background: #00A0A5 !important ;
			color: white !important ;
		}
		th{
			text-align: left !important;
		}
		td {
            white-space: normal !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
			text-align: left !important;
        }
		.dataTables_length, .dataTables_filter {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }
		@media (max-width: 425px) {
			tr td{
				font-size: 0.9em;
			}
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
  <body onload="construct()">
	<?php
		setHeaderStudent("dashboard", "Student Peer Review"); // seHeaderStudent("dashboard", "Student Peer Review");
	?>
	<div class="container my-3">
			
		<div class="bodycontent">
			<div class="row d-flex justify-content-center align-items-center">
				<div class="col-md-12">
					<div class="infotitle fs-1">Student Peer Review:</div>
				</div>
			</div>

			<ul class="nav nav-tabs mt-3" id="myTab" role="tablist">
				<li class="nav-item" role="presentation" >
					<button class="nav-link active" id="review-assessment-tab" data-bs-toggle="tab" data-bs-target="#reviewAssessment" type="button" role="tab" aria-controls="reviewAssessment" aria-selected="true">Peer Review Assessment</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" id="assessment-reviewed-tab" data-bs-toggle="tab" data-bs-target="#assessmentReviewed" type="button" role="tab" aria-controls="assessmentReviewed" aria-selected="false">My Reviewed Submission</button>
				</li>
				<li class="nav-item" role="presentation" >
					<button class="nav-link" id="leaderboard-tab" data-bs-toggle="tab" data-bs-target="#leaderboard" type="button" role="tab" aria-controls="leaderboard" aria-selected="false">Peer Review Leaderboard</button>
				</li>
			</ul>

			<div class="tab-content" id="myTabContent">
				<div class="tab-pane fade show active" id="reviewAssessment" role="tabpanel" aria-labelledby="review-assessment-tab">
					<div class="tablecontainer">
						<?php
						// Hitung review yang belum dikerjakan
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
						$pending = $pending_result->fetch_assoc()['pending_count'];
						$stmt_pending->close();

						// Jika ada yang belum dikerjakan → tampilkan ajakan
						if ($pending > 0) {
							if ($human_language == 'id') {
								echo "<div class='alert alert-info mb-3 mt-3' style='font-size:16px;'>
										<strong>Yuk lanjut me-review!</strong> Kamu masih punya <b>$pending</b> peer review yang belum kamu kerjakan.
										Selesaikan semuanya biar bisa dapat bonus poin game penuh! 💪🔥
									</div>";
							} else {
								echo "<div class='alert alert-info mb-3 mt-3' style='font-size:16px;'>
										<strong>Keep reviewing!</strong> You still have <b>$pending</b> pending peer reviews.
										Complete them to get the full bonus game points! 💪🔥
									</div>";
							}
						}
						?>
						<table id="studentReviewAssessment" class="table table-bordered table-striped responsive nowrap" style="width:100%">
							<thead>
								<tr>
									<th style="width:35%">Peer Review Assessment</th>
									<th style="width:30%">Course</th>
									<th style="width:15%">Review Due</th>
									<th style="width:7%">Score</th>
									<th style="width:18%">Status / Actions</th>
								</tr>
							</thead>
							
							<tbody>
							<?php
								// Query diperbarui untuk mengambil data skor dan deskripsi
								$sql_review = 
								"SELECT 
									a.name AS assessment_name,
									c.name AS course_name,
									pra.peer_review_close_time,
									prs.review_status,
									prs.pr_submission_id,
									pr.review_score,
									pr.review_description
								FROM 
									peer_review_submission prs
								JOIN 
									peer_review_assessment pra ON prs.pr_assessment_id = pra.pr_assessment_id
								JOIN 
									assessment a ON pra.assessment_id = a.assessment_id
								JOIN 
									course c ON a.course_id = c.course_id
								LEFT JOIN 
									peer_review pr ON prs.pr_submission_id = pr.pr_submission_id
								WHERE 
									prs.reviewer_id = ? 
								ORDER BY 
									pra.peer_review_close_time ASC";

								$stmt = $db->prepare($sql_review);
								$stmt->bind_param("i", $_SESSION['user_id']);
								$stmt->execute();
								$result = $stmt->get_result();

								if ($result && $result->num_rows > 0) {
									while ($row = $result->fetch_assoc()) {
								?>
										<tr>
											<td><?php echo htmlspecialchars($row['assessment_name']); ?></td>
											<td><?php echo htmlspecialchars($row['course_name']); ?></td>
											<td><?php echo htmlspecialchars($row['peer_review_close_time']);?></td>

											<td>
												<?php 
													// Tampilkan skor jika ada, jika tidak, tampilkan 'N/A'
													echo isset($row['review_score']) ? htmlspecialchars($row['review_score']) : 'N/A';
												?>
											</td>

											<td>
												<?php
													// Mengambil dan bersihkan deskripsi
													$description = trim($row['review_description']) ?? '';
													$description = preg_replace('/<p>\s*<br>\s*<\/p>/', '', $description);

													// Cek status submit
													if ($row['review_status'] == 1) {
														// Jika sudah submit, maka menampilkan tombol "View Review" dan Modal
														if (!empty($description)) {
															echo "
																<button type=\"button\" class=\"btn btn-primary btn-sm\" data-bs-toggle=\"modal\" data-bs-target=\"#descModal".$row['pr_submission_id']."\">
																	View Review
																</button>
																<div class=\"modal fade\" id=\"descModal".$row['pr_submission_id']."\" tabindex=\"-1\" aria-labelledby=\"modalLabel".$row['pr_submission_id']."\" aria-hidden=\"true\">
																	<div class=\"modal-dialog\">
																		<div class=\"modal-content\">
																			<div class=\"modal-header\">
																				<h5 class=\"modal-title\" id=\"modalLabel".$row['pr_submission_id']."\">Review Description</h5>
																				<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"Close\"></button>
																			</div>
																			<div class=\"modal-body\" style=\"white-space: pre-line;\">
																				".preg_replace("/(\r\n|\n){2,}/", "\n", $row['review_description'])."
																			</div>
																			<div class=\"modal-footer\">
																				<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Close</button>
																			</div>
																		</div>
																	</div>
																</div>";
														} else {
															// Fallback jika sudah submit tapi deskripsi kosong (?)
															echo 'Submitted';
														}

													} else {
														// Jika belum submit, mengecek deadline
														$now = new DateTime();
														$close_time = new DateTime($row['peer_review_close_time']);

														if ($now > $close_time) {
															// Jika sudah lewat deadline dan belum submit
															echo 'Submission Closed';
														} else {
															// Jika belum lewat deadline dan belum submit
															echo '<a href="student_review_submit.php?id=' . $row['pr_submission_id'] . '" class="btn btn-primary btn-sm">Submit Review</a>';
														}
													}
												?>
											</td>
										</tr>
								<?php
									} // Akhir dari while loop
								} else {
									
								}
								$stmt->close();
								?>
							</tbody>
						</table>
					</div>
				</div>

				<div class="tab-pane fade" id="assessmentReviewed" role="tabpanel" aria-labelledby="assessment-reviewed-tab">
					<div class="tablecontainer">
						<table id="studentReviewedSubmission" class="table table-bordered table-striped responsive nowrap" style="width:100%">
							<thead>
								<tr>
									<th style="width: 35%;">My Reviewed Submission</th>
									<th style="width: 30%;">Course</th>
									<th style="width: 10%;">Reviews Received</th>
									<th style="width: 10%;">Average Score</th>
									<th style="width: 15%;">Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php
								// Query mengambil hasil review dari mahasiswa lain
								$sql_feedback = 
								"SELECT a.name as assessment_name, 
										c.name as course_name, 
										s.submission_id,
										pra.peer_review_close_time,
										COUNT(pr.peer_review_id) as total_reviews,
										AVG(pr.review_score) as avg_score
									FROM 
										peer_review pr
									JOIN
										peer_review_submission prs ON pr.pr_submission_id = prs.pr_submission_id
									JOIN
										peer_review_assessment pra ON prs.pr_assessment_id = pra.pr_assessment_id
									JOIN 
										submission s ON prs.submission_to_review = s.submission_id
									JOIN
										assessment a ON s.assessment_id = a.assessment_id
									JOIN 
										course c ON a.course_id = c.course_id
									WHERE 
										s.submitter_id = ?
									GROUP BY s.submission_id, a.name, c.name, pra.peer_review_close_time
									ORDER BY c.name, a.name";

								$stmt_feedback = $db->prepare($sql_feedback);
								$stmt_feedback->bind_param("i", $_SESSION['user_id']);
								$stmt_feedback->execute();
								$result_feedback = $stmt_feedback->get_result();

								if ($result_feedback && $result_feedback->num_rows > 0) {
									while ($row = $result_feedback->fetch_assoc()) {
								?>
									<tr>
										<td><?php echo htmlspecialchars($row['assessment_name']); ?></td>
										<td><?php echo htmlspecialchars($row['course_name']); ?></td>
										<?php
											$close_time = new DateTime($row['peer_review_close_time']);
											$now = new DateTime();
											// Cek apakah waktu review (penutupan) sudah lewat
											$isReviewPeriodOver = ($now > $close_time);
										?>
										<td>
											<?php if ($isReviewPeriodOver): ?>
												<?php echo (int)$row['total_reviews']; ?>
											<?php else: ?>
												<span class="text-muted fst-italic">Not available yet</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ($isReviewPeriodOver): ?>
												<?php echo ($row['avg_score']) ? number_format($row['avg_score']) : 'N/A'; ?>
											<?php else: ?>
												<span class="text-muted fst-italic">Not available yet</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ($isReviewPeriodOver): ?>
												<a href="student_view_review_details.php?submission_id=<?php echo $row['submission_id']; ?>" class="btn btn-primary btn-sm">Review Details</a>
											<?php else: ?>
												<button class="btn btn-secondary btn-sm" disabled>Review Details</button>
											<?php endif; ?>
										</td>
									</tr>
								<?php
									}
								} else {
									echo"";
								}
								$stmt_feedback->close();
								?>
							</tbody>
						</table>
					</div>
				</div>

				<div class="tab-pane fade" id="leaderboard" role="tabpanel" aria-labelledby="leaderboard-tab">
					<!-- FILTER AREA -->
					<div class="row g-3 mb-3 align-items-end">
						<div class="col-md-4">
							<label class="form-label mt-2 fs-4">Select Course:</label>
							<select id="courseSelect" class="min-w-[200px] shrink-0 form-select" name="course_id" onchange="onCourseChange(this.value)">
								<?php 
								$student_id = $_SESSION['user_id'];
								$selected_course_id = $_GET['course_id'] ?? null;

								$sql = "SELECT c.course_id, c.name 
										FROM enrollment e
										JOIN course c ON c.course_id = e.course_id
										WHERE e.student_id = ?
										ORDER BY c.course_id ASC";

								$stmt = $db->prepare($sql);
								$stmt->bind_param("i", $student_id);
								$stmt->execute();
								$rs = $stmt->get_result();

								$first_course = true;

								while ($c = $rs->fetch_assoc()) {

									// Jika user belum memilih course -> pilih row pertama
									if ($selected_course_id === null && $first_course) {
										$selected_course_id = $c['course_id'];
									}

									echo '<option value="'. $c['course_id'] .'" '.
											($selected_course_id == $c['course_id'] ? 'selected' : '') .
										'>'. $c['name'] .'</option>';

									$first_course = false;
								}

								$stmt->close();
								?>
							</select>
						</div>
					</div>

					<!-- MAIN LEADERBOARD -->
					<div id="mainLeaderboardContainer">
						<h5 class="mt-3">Course Leaderboard</h5>

						<table class="table table-bordered table-striped" id="mainLeaderboardTable">
							<thead>
								<tr>
									<th style="width:10%">Rank</th>
									<th style="width:45%">Student</th>
									<th style="width:20%">Total Points</th>
								</tr>
							</thead>
							<tbody id="mainLeaderboardBody">
								<tr><td colspan="3" class="text-center">Loading...</td></tr>
							</tbody>
						</table>
					</div>

					<!-- ASSESSMENT TABS -->
					<h5 class="mt-4">Assessment Leaderboard</h5>
					<ul class="nav nav-tabs" id="assessmentTabs"></ul>

					<!-- ASSESSMENT TAB CONTENT -->
					<div class="tab-content" id="assessmentTabsContent"></div>
				</div>
			</div>

		</div>
	</div>

	<script>
	new DataTable('#studentReviewAssessment', {
		responsive: true,
		order: [[2, 'desc']],
	});

	new DataTable('#studentReviewedSubmission', {
		responsive: true,
		order: [[1, 'asc']],
	});

	new DataTable('#leaderboardTable', {
        responsive: true,
        order: [[0, 'asc']],
    });
	</script>
	
	<script>
		function confirmDelete(userId) {
			$('#deleteModal' + userId).modal('show');
		}
	</script>

	<script>
	document.addEventListener("DOMContentLoaded", function () {

		// Ambil selected course dari PHP
		let selectedCourse = <?= json_encode($selected_course_id); ?>;
		let defaultCourse  = <?= json_encode($default_course_id); ?>;

		// Jika user BELUM memilih course → redirect ke course ID paling kecil
		if (!selectedCourse || selectedCourse == 0) {
			window.location.href = "student_peer_review.php?course_id=" + defaultCourse;
			return;
		}

		// Event listener dropdown
		let cs = document.getElementById("courseSelect");
		if (cs) {
			cs.addEventListener("change", function () {
				let cid = this.value;
				window.location.href = "student_peer_review.php?course_id=" + cid + "&active_tab=leaderboard";
			});
		}

		// Render leaderboard
		let leaderboardData = <?php echo json_encode($leaderboard_data); ?>;
		let assessmentList  = <?php echo json_encode($assessments); ?>;

		renderMainLeaderboard(leaderboardData);
		renderAssessmentTabs(leaderboardData, assessmentList);
	});

	function renderMainLeaderboard(data) {
		let tbody = "";
		let rank = 1;

		data.forEach(row => {
			tbody += `
				<tr>
					<td>${rank++}</td>
					<td>${row.username} - ${row.name}</td>
					<td>${row.total_points}</td>
				</tr>
			`;
		});

		document.getElementById("mainLeaderboardBody").innerHTML = tbody;
	}

	function renderAssessmentTabs(data, assessments) {
		let tabHeader = "";
		let tabContent = "";
		let first = true;

		Object.keys(assessments).forEach(pr_id => {

			tabHeader += `
				<li class="nav-item">
					<button class="nav-link ${first ? 'active' : ''}"
						data-bs-toggle="tab"
						data-bs-target="#ass_${pr_id}">
						${assessments[pr_id]}
					</button>
				</li>
			`;

			let rows = "";
			let rank2 = 1;

			data.forEach(student => {
				let det = student.assessments[pr_id] ?? {
					avg_score: 0,
					extra_points: 0,
					final_score: 0
				};

				rows += `
					<tr>
						<td>${rank2++}</td>
						<td>${student.username} - ${student.name}</td>
						<td>${det.avg_score}</td>
						<td>${det.extra_points}</td>
						<td>${det.final_score}</td>
					</tr>
				`;
			});

			tabContent += `
				<div class="tab-pane fade ${first ? 'show active' : ''}" id="ass_${pr_id}">
					<table class="table table-bordered table-striped table-sm mt-3">
						<thead>
							<tr>
								<th>Rank</th>
								<th>Student</th>
								<th>Average Score</th>
								<th>Extra Point</th>
								<th>Total Point</th>
							</tr>
						</thead>
						<tbody>${rows}</tbody>
					</table>
				</div>
			`;

			first = false;
		});

		document.getElementById("assessmentTabs").innerHTML = tabHeader;
		document.getElementById("assessmentTabsContent").innerHTML = tabContent;
	}
	</script>
	<script>
	document.addEventListener("DOMContentLoaded", function() {
		const activeTab = "<?php echo $active_tab; ?>";

		const tabTrigger = document.querySelector(`[data-bs-target="#${activeTab}"]`);

		if (tabTrigger) {
			const tab = new bootstrap.Tab(tabTrigger);
			tab.show();
		}
	});
	</script>
	<script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
