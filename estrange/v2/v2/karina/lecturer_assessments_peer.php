<?php
	include("_sessionchecker_peer.php");
	include("_config_peer_review.php");
	include("_header_peer_review.php");

	if($_SERVER["REQUEST_METHOD"] == "POST") {
		if(isset($_POST['id']) == true){ // this is course id
			// if landed from courses page
			// set the course data in the session
			$_SESSION['course_id'] = mysqli_real_escape_string($db,$_POST['id']);
			$_SESSION['course_name'] = mysqli_real_escape_string($db,$_POST['name']);
		}else if(isset($_POST['did']) == true){
			// delete a particular assessment

			// assessment id
			$id = mysqli_real_escape_string($db,$_POST['did']);

			// delete an assessment
			$sql = "DELETE FROM assessment WHERE assessment_id = '$id'";
			if ($db->query($sql) === TRUE) {
			  // if removed well, redirect to dashboard
			  header('Location: lecturer_peer_assessments.php');
			  exit;
			} else {
			  echo "<script>alert('The assessment cannot be deleted since at least one student has submitted a program to it');</script>";
			}
		}
	}

	// if the session values do not exist
	if(isset($_SESSION['course_id']) == false){
		header('Location: lecturer_peer_dashboard.php');
		exit;
	}
	$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title> E-STRANGE: Course assessments</title>
    <link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>

<!-- DataTables JS -->
<link rel="stylesheet" type="text/css" href="../datatables/jquery.dataTables.min.css">
<script type="text/javascript" src="../datatables/jquery.dataTables.min.js"></script>
<link rel="stylesheet" type="text/css" href="../datatables/responsive.bootstrap5.min.css">
<script type="text/javascript" src="../datatables/dataTables.responsive.min.js"></script>
<script type="text/javascript" src="../datatables/responsive.bootstrap5.min.js"></script>


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

			// adapted and modified from https://www.w3schools.com/howto/howto_js_copy_clipboard.asp
			function copy_assessment_link(input_id) {
				/* Get the text field */
			  var copyText = document.getElementById(input_id);
			  /* Select the text field */
			  copyText.select();
			  copyText.setSelectionRange(0, 99999); /*For mobile devices*/
			  /* Copy the text inside the text field */
			  document.execCommand("copy");
			  /* Alert the copied submission link */
			//   alert("Copied the submission link: " + copyText.value);
			  // Display modal
			  var modalMessage = "Copied the submission link: " + copyText.value;
			  displayModal(modalMessage);
			  setTimeout(function() {
				closeModal();
			  }, 2000);
			}
		
			function displayModal(message) {
				var modal = document.getElementById("copyModal");
				var modalMessage = document.getElementById("modalMessage");
				
				modalMessage.textContent = message;
				modal.style.display = "block";
			}

			function closeModal() {
				var modal = document.getElementById("copyModal");
				modal.style.display = "none";
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
	.btn-warning{
		background: #fef2b3  !important ;
	}
	.btn-danger{
			background: #f56976 !important ;
		}
	.buttontambah{
		text-align: right;
	}
	.form-control {
			border: 2px solid #000;	
			border-radius: 8px;
		}
	@media only screen and (max-width: 425px) {
		*{
			font-size: 0.95em;
		}
		.buttontambah{
			text-align: left;
			margin: 1rem 0 1rem 0;
		}
		tr td{
			font-size: 0.9rem;
		}
		.addcourse{
			width: 100%;
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
  <body>
  		<?php
		  setHeaderLecturer("courses", "Course assessments");
		?>
		<div class="container-fluid px-3 my-3">
			<div class="bodycontent">
				
				<div class="row d-flex justify-content-center align-items-center mb-3">
				<div class="col-md-6">
					<div class="infotitle fs-3"> <?php echo $_SESSION['course_name']; ?>'s assessment list: </div>
				</div>
				<div class="col-md-6 buttontambah">
					<!-- <button class="btn btn-primary addcourse mb-2" onclick="window.open('lecturer_dashboard.php', '_self');">Return to courses</button> -->
					<?php
						echo "<button id='return-course'  class='btn btn-primary mb-2 addcourse' onclick=\"window.open('lecturer_peer_dashboard.php', '_self');\">Return to courses</button>";
						echo "<script>
							document.getElementById('return-course').addEventListener('click', function() {
								localStorage.setItem('returning_from_assessment', 'true'); // Pastikan flag benar-benar tersimpan
							});
						</script>";
					?>
					<button class="btn btn-primary addcourse mb-2" onclick="window.open('lecturer_assessment_add_peer.php', '_self');">Add assessment</button>
				</div>
				</div>
				<div class="tablecontainer">
				<table id="lecturerAssessmentTable" class="table table-bordered table-striped responsive nowrap" style="width:100%" >
					<thead>		
						<tr>
							<th>Name</th>
							<th>Subs. opening time</th>
							<th>Subs. closing time</th>
							<th>Additional information</th>
							<th>Description</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
							<?php
								// get the assessments for a particular course
								$sql = "SELECT assessment_id, name, description, submission_open_time, submission_close_time, submission_file_extension, allow_late_submission, public_assessment_id FROM assessment WHERE course_id = '".$_SESSION['course_id']."' ORDER BY submission_close_time DESC";
								$result = mysqli_query($db,$sql);
								// adapted from https://www.w3schools.com/php/php_mysql_select.asp
								if ($result->num_rows > 0) {
										while($row = $result->fetch_assoc()) {
												echo "
													<tr id=\"".$row['assessment_id']."\" onclick=\"selectRow('".$row['assessment_id']."','sumtablecontent')\">
														<td><a>".$row['name']."</a></td>
														<td>".$row['submission_open_time']."</td>
														<td>".$row['submission_close_time']."</td>
														
														";
												if($row['submission_file_extension'] == 'java'){
													echo "<td>File type: Java file<br />";
												}else if($row['submission_file_extension'] == 'py'){
													echo "<td>File type: Python file<br />";
												}else if($row['submission_file_extension'] == 'zip_java'){
													echo "<td>File type: Zip file (Java)<br />";
												}else if($row['submission_file_extension'] == 'zip_py'){
													echo "<td>File type: Zip file (Python)<br />";
												}
												if($row['allow_late_submission'] == 1){
													echo "Late submission : allow <br />";
												}else{
													echo "Late submission : disallow <br />";
												}
												echo"<td>";
													$description = trim($row['description']);
                                                    $description = preg_replace('/<p>\s*<br>\s*<\/p>/', '', $description);
                                                    
                                                    if (!empty($description)) {
                                                        echo "
                                                            <button type=\"button\" class=\"btn btn-primary w-100\" data-bs-toggle=\"modal\" data-bs-target=\"#descModal".$row['assessment_id']."\">
                                                                Description
                                                            </button>
                                            
                                                            <div class=\"modal fade\" id=\"descModal".$row['assessment_id']."\" tabindex=\"-1\" aria-labelledby=\"modalLabel".$row['assessment_id']."\" aria-hidden=\"true\">
                                                                <div class=\"modal-dialog\">
                                                                    <div class=\"modal-content\">
                                                                        <div class=\"modal-header\">
                                                                            <h5 class=\"modal-title\" id=\"modalLabel".$row['assessment_id']."\">Description</h5>
                                                                            <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"Close\"></button>
                                                                        </div>
                                                                        <div class=\"modal-body\" style=\"white-space: pre-line;\">
                                                                            ".preg_replace("/(\r\n|\n){2,}/", "\n", $row['description'])."
                                                                        </div>
                                                                        <div class=\"modal-footer\">
                                                                            <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Close</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>";
                                                    }
                                                echo"
                                                        </td>
												        ";
												echo "
														<td class=\"tdactions\">
															<form class=\"invisform\" action=\"lecturer_submission.php\" method=\"post\">
																<input type=\"hidden\" name=\"id\" value=\"".$row['assessment_id']."\">
																<input type=\"hidden\" name=\"name\" value=\"".$row['name']."\">
																<button class=\"btn btn-primary w-100\" type=\"submit\">Student Programs</button>
															</form>
															<form class=\"invisform\" action=\"lecturer_assessment_update.php\" method=\"post\">
																<input type=\"hidden\" name=\"id\" value=\"".$row['assessment_id']."\">
																<button class=\"btn btn-warning w-100\" type=\"submit\">Update</button>
															</form>
															<div class=\"invisform\" action=\"". htmlentities($_SERVER['PHP_SELF']). "\" method=\"post\">
																<button class=\"btn btn-danger w-100\"  onclick=\"confirmDelete('".$row['assessment_id']."');\">Delete</button>
															</div>
															<div class=\"my-2 w-100\">
																<span class=\"link\">Link:</span>
																<input type=\"text\" class=\"link\" id=\"assessment_link".$row['assessment_id']."\"
																	value=\"".$baseDomainLink."student_assessment_submit.php?id=".$row['public_assessment_id']."\"
																	readonly >
																<button class=\"btn btn-primary\" onclick=\"copy_assessment_link('assessment_link".$row['assessment_id']."');\">copy</button>
															</div>
														</td>
													</tr>
													<div class=\"modal fade\" id=\"deleteModal".$row['assessment_id']."\" data-bs-backdrop=\"static\" data-bs-keyboard=\"false\">
													<div class=\"modal-dialog modal-dialog-centered\">
														<div class=\"modal-content\">
															<div class=\"modal-header\">
																<h4 class=\"modal-title\">Delete Confirmation</h4>
																<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"Close\"></button>
															</div>
															<div class=\"modal-body\">
																<p>Are you sure you want to delete this entry?</p>
															</div>
															<div class=\"modal-footer\">
																<form action=\"".htmlentities($_SERVER['PHP_SELF'])."\" method=\"post\">
																	<input type=\"hidden\" name=\"did\" value=\"".$row['assessment_id']."\">
																	<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Close</button>
																	<button type=\"submit\" class=\"btn btn-danger\">Delete</button>
																</form>
															</div>
														</div>
													</div>
												</div>
												";
										}
								} else {
										echo "
											
										";
								}
							?>
							</tbody>
						</table>
						
					</div>
				</div>
			</div>
		</div>
		<div id="copyModal" class="modal">
			<div class="modal-content container-fluid">
				<div class="row d-flex justify-content-center align-items-center my-3">
					<div class="col-md-10">
						<p id="modalMessage"></p>
					</div>
					<div class="col-md-2 buttontambah">
						<button type="button" class="btn-close"onclick="closeModal()"></button>
					</div>
				</div>
			</div>
		</div>


		<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
        var table = new DataTable('#lecturerAssessmentTable', {
            responsive: true,
            order: [[2, 'desc']],
            pageLength: 3,
            lengthMenu: [3, 5, 10, 25] // Menyediakan opsi pilihan entries
        });

        var savedPage = localStorage.getItem('datatable_page');
        var returning = localStorage.getItem('returning_from_submission');

        if (returning === 'true' && savedPage !== null) {
            table.page(parseInt(savedPage)).draw(false);
            localStorage.removeItem('returning_from_submission'); // Hapus flag setelah dipakai
        }

        // Simpan halaman yang sedang dibuka ke localStorage
        table.on('page.dt', function() {
            var currentPage = table.page();
            localStorage.setItem('datatable_page', currentPage); // Simpan dalam format indeks 0
        });
    });
</script>
	
</script>
<script>
    function confirmDelete(course_id) {
        $('#deleteModal' + course_id).modal('show');
    }
</script>
  </body>
</html>

