<?php
    include("_sessionchecker_peer.php"); // ../_sessionchecker.php
    include("_config_peer_review.php");
    include("_header_peer_review.php");

// Mengambil ID Peer Review Assessment
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: Peer Review Assessment ID not valid.");
}

$pr_assessment_id = $_GET['id'];
$lecturer_id = $_SESSION['user_id'];

// Query untuk mendapatkan nama assessment dan course
$sql_info = "SELECT a.name AS assessment_name, 
					c.name AS course_name
             FROM peer_review_assessment pra
             JOIN assessment a ON pra.assessment_id = a.assessment_id
             JOIN course c ON a.course_id = c.course_id
             WHERE pra.pr_assessment_id = ? AND c.creator_id = ?";

$stmt_info = $db->prepare($sql_info);
$stmt_info->bind_param("ii", $pr_assessment_id, $lecturer_id);
$stmt_info->execute();
$result_info = $stmt_info->get_result();

if ($result_info->num_rows !== 1) {
    die("No data available in table");
}
$assessment_info = $result_info->fetch_assoc();
$stmt_info->close();

include("game_peer_review_points.php");

// Ambil hasil game point dari backend
$game_points_all = generate_peer_review_game_points($db, [
    'pr_assessment_id' => $pr_assessment_id,
    'ignore_deadline' => true,
    'use_cache' => false
]);

$game_points = $game_points_all[$pr_assessment_id] ?? [];

// Query hanya ambil data student & submission, tanpa hitung nilai lagi
$sql_reviewed_students = "
    SELECT 
        s.submission_id,
        u.user_id AS student_id,
        u.username AS reviewed_username,
        u.name AS reviewed_name
    FROM peer_review_submission prs
    JOIN submission s ON prs.submission_to_review = s.submission_id
    JOIN user u ON s.submitter_id = u.user_id
    WHERE prs.pr_assessment_id = ?
    GROUP BY u.user_id
    ORDER BY u.username
";

$stmt_reviewed = $db->prepare($sql_reviewed_students);
$stmt_reviewed->bind_param("i", $pr_assessment_id);
$stmt_reviewed->execute();
$result_reviewed = $stmt_reviewed->get_result();
?>

<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title> E-STRANGE: Lecturer Peer Review List</title>
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

    <script>
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
	@media only screen and (max-width: 425px) {
		tr td{
			font-size: 0.9rem;
		}
	}
	</style>
<body>
    <?php setHeaderLecturer("peer_review", "Peer Review List"); ?>

    <div class="container my-4">
        <h2><?php echo htmlspecialchars($assessment_info['assessment_name']); ?></h2>
        <h3 class="lead"><?php echo htmlspecialchars($assessment_info['course_name']); ?></h3>
        <hr/>
        <div class="bodycontent">
            <h4>Peer Review List</h4>
            <div class="table-responsive mt-3">
                <table id="reviewedSubmissionsTable" class="table table-bordered table-striped" style="width:100%">
					<thead>
						<tr>
							<th style="width: 30%;">Reviewed Student</th>
							<th style="width: 15%;">Average Score</th>
							<th style="width: 15%;">Completed/Assigned</th>
							<th style="width: 13%;">Extra Point</th>
							<th style="width: 14%;">Total Point</th>
							<th style="width: 13%;">Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if ($result_reviewed && $result_reviewed->num_rows > 0): ?>
							<?php while ($row = $result_reviewed->fetch_assoc()): ?>

								<?php
								// Ambil hasil dari backend game point
								$gp = $game_points[$row['student_id']] ?? null;

								$avg = $gp['avg_score'] ?? null;
								$assign = $gp['pr_assign'] ?? 0;
								$comp = $gp['pr_completed'] ?? 0;
								$extra = $gp['extra_points'] ?? null;
								$total = $gp['final_score'] ?? null;

								// Default score detection (NULL atau 0)
								$is_default_score = ($avg == 100);
								?>

								<tr>

									<!-- Reviewed Student -->
									<td>
										<?= htmlspecialchars($row['reviewed_username'] . ' - ' . $row['reviewed_name']); ?>
									</td>

									<!-- Average Score -->
									<td>
										<?php if ($is_default_score): ?>
											100* <br>
											<small class="text-muted">*default score because there was no review score received</small>
										<?php else: ?>
											<?= number_format($avg); ?>
										<?php endif; ?>
									</td>

									<!-- Assigned / Completed -->
									<td>
										<?= $comp . '/' . $assign; ?>
									</td>

									<!-- Extra Point -->
									<td>
										<?= ($extra !== null ? number_format($extra) : '<span class="text-muted">N/A</span>'); ?>
									</td>

									<!-- Total Point -->
									<td>
										<?php if ($comp == 0 && $avg !== null): ?>
											0* <br>
											<small class="text-muted">*no points because peer review task was not completed</small>
										<?php else: ?>
											<?= ($total !== null ? number_format($total) : '<span class="text-muted">N/A</span>'); ?>
										<?php endif; ?>
									</td>

									<!-- Actions -->
									<td class="text-center">
										<a href="lecturer_view_review_details.php?submission_id=<?= $row['submission_id']; ?>"
										class="btn btn-primary btn-sm">
											View All Reviews
										</a>
									</td>

								</tr>

							<?php endwhile; ?>
						<?php endif; ?>
					</tbody>
				</table>

            </div>
             <div class="mt-3">
                 <a href="lecturer_peer_review.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Back to Peer Review
                </a>
            </div>
        </div>
    </div>
	
	<script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    
	<script>
        $(document).ready(function() {
            new DataTable('#reviewedSubmissionsTable', {
                responsive: true,
            });
        });
    </script>
</body>
</html>

<?php
if (isset($stmt_reviewed)) $stmt_reviewed->close();
?>