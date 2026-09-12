<?php
	include("_sessionchecker_peer.php"); //"../_sessionchecker.php"
	include("_config_peer_review.php");
	include("_header_peer_review.php");

	// Memanggil file fungsi untuk ngecek harus ada tugas yang digenerate atau tidak
	include("lecturer_generate_pr_functions.php");
	generate_peer_review_assessments($db);

	// Mengambil Daftar Course untuk Filter
    $lecturer_id = $_SESSION['user_id'];
    $courses_list = []; // Array untuk menyimpan course
    
    // Query ini mengambil course-course unik yang dimiliki dosen yang memiliki peer review assessment
    $sql_courses = "SELECT DISTINCT c.course_id, c.name 
                    FROM course c
                    JOIN assessment a ON c.course_id = a.course_id
                    JOIN peer_review_assessment pra ON a.assessment_id = pra.assessment_id
                    WHERE c.creator_id = ?
                    ORDER BY c.name";
                    
    $stmt_courses = $db->prepare($sql_courses);
    $stmt_courses->bind_param("i", $lecturer_id);
    $stmt_courses->execute();
    $result_courses = $stmt_courses->get_result();
    while ($course = $result_courses->fetch_assoc()) {
        $courses_list[] = $course; // Simpan di array
    }
    $stmt_courses->close();
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title> E-STRANGE: Lecturer Peer Review</title>
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
	.btn-outline-primary:hover{
	    background: #00A0A5 !important ;
		color: white !important ;
	}
	.buttontambah{
		text-align: right;
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
	@media only screen and (max-width: 425px) {
		.buttontambah{
			text-align: left;
			margin: 1rem 0 1rem 0;
		}
		tr td{
			font-size: 0.9rem;
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
		  setHeaderLecturer("peer_review", "Lecturer Peer Review");
		?>
	<div class="container my-3">
		<div class="bodycontent">
			<div class="row d-flex justify-content-center align-items-center">
				<div class="col-md-6">
					<div class="infotitle fs-1">Peer Review List:</div>
				</div>
				<div class="col-md-6 buttontambah">
					<button class="btn btn-primary"  onclick="window.open('lecturer_peer_review_add.php', '_self');">Add Peer Review</button>
				</div>
			</div>
			<!-- Filter dropdown course -->
			<div class="row my-3 align-items-end">
                <div class="col-md-6">
                    <label for="courseFilterDropdown" class="form-label fw-bold">Filter by Course:</label>
                    <select id="courseFilterDropdown" class="min-w-[200px] shrink-0 form-select">
                        <option value="">Show All Courses</option>
                        <?php foreach ($courses_list as $course): ?>
                            <option value="<?php echo htmlspecialchars($course['name']); ?>">
                                <?php echo htmlspecialchars($course['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"></div>
            </div>
			<div class="tablecontainer">
				<table id="lecturerPeerReviewTable" class="table table-bordered table-striped responsive nowrap" style="width:100%" >
					<thead>
                    <tr>
                        <th style="width: 30%;">Assessment Name</th>
                        <th style="width: 23%;">Course</th>
                        <th style="width: 15%;">Start Time</th>
                        <th style="width: 15%;">Close Time</th>
                        <th style="width: 17%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $lecturer_id = $_SESSION['user_id'];
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
                            WHERE 
                                c.creator_id = ?"; 

                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("i", $lecturer_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                    ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['assessment_name']); ?></td>
                                
                                <td><?php echo htmlspecialchars($row['course_name']); ?></td>

                                <td><?php echo htmlspecialchars($row['peer_review_start_time']); ?></td>

                                <td><?php echo htmlspecialchars($row['peer_review_close_time']); ?></td>

                                <td>
								<?php 
									$now = new DateTime();
									$start_time = new DateTime($row['peer_review_start_time']);
									$close_time = new DateTime($row['peer_review_close_time']);

									// sebelum waktu mulai, menampilkan edit
									if ($now < $start_time) {
								?>
										<a href="lecturer_peer_review_edit.php?id=<?php echo $row['pr_assessment_id']; ?>" class="btn btn-primary btn-sm">
											Edit Assessment
										</a>
								<?php 
									// setelah melewati waktu selesai, menampilkan view
									} elseif ($now > $close_time) {
								?>
										<a href="lecturer_peer_review_list.php?id=<?php echo $row['pr_assessment_id']; ?>" class="btn btn-primary btn-sm">
											View Assessment
										</a>
								<?php 
									// selama periode review (di antara start dan close), menampilkan kedua tombol
									} else {
								?>
										<a href="lecturer_peer_review_edit.php?id=<?php echo $row['pr_assessment_id']; ?>" class="btn btn-primary btn-sm me-1" title="Edit Settings">
											Edit
										</a>
										<a href="lecturer_peer_review_list.php?id=<?php echo $row['pr_assessment_id']; ?>" class="btn btn-primary btn-sm" title="View Submissions">
											View Assessment
										</a>
								<?php 
									} 
								?>
							</td>
                            </tr>
                    <?php
                        } 
                    } else {
                        // Tampilkan pesan jika tidak ada data sama sekali
                        echo"";
                    }
                    $stmt->close();
                    ?>
                </tbody>
				</table>
				</div>
			</div>
		</div>
	</div>

	<script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
	
	<script>
		$(document).ready(function() {
			var table = new DataTable('#lecturerPeerReviewTable', {
				responsive: true,
				order: [[3, 'desc']], // Urutkan berdasarkan Close Time
			});

            // Menambahkan event listener untuk dropdown filter
            $('#courseFilterDropdown').on('change', function() {
                // Ambil nilai yang dipilih (yaitu nama course, atau string kosong untuk "All Courses")
                var selectedCourseName = $(this).val();
                table.column(1).search(selectedCourseName).draw();
            });
		});
	</script>

	<script>
    function confirmDelete(course_id) {
        $('#deleteModal' + course_id).modal('show');
    }
	</script>
  </body>
</html>
