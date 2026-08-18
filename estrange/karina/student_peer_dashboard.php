<?php
	include("_sessionchecker_peer.php"); //"../_sessionchecker.php"
	include("_config_peer_review.php");
	include("_header_peer_review.php");
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title> E-STRANGE: Student home</title>
	<link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet"> 
	<!--TAMBAHAN <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">-->
	<link rel="icon" href="../strange_html_layout_additional_files/icon.png">
	
	<!-- DataTables CSS -->
	<link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css"> 
	<!--TAMBAHAN<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.css"/>-->
	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>

	<!-- DataTables JS -->
	<link rel="stylesheet" type="text/css" href="../datatables/jquery.dataTables.min.css">
	<script type="text/javascript" src="../datatables/jquery.dataTables.min.js"></script>
	<link rel="stylesheet" type="text/css" href="../datatables/responsive.bootstrap5.min.css">
	<script type="text/javascript" src="../datatables/dataTables.responsive.min.js"></script>
	<script type="text/javascript" src="../datatables/responsive.bootstrap5.min.js"></script>
    <!--TAMBAHAN-->
	<!--<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/3.0.2/css/responsive.bootstrap5.min.css"/>-->
	<!--<script type="text/javascript" src="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.js"></script>-->
	<!--<script type="text/javascript" src="https://cdn.datatables.net/responsive/3.0.2/js/dataTables.responsive.min.js"></script>-->
	<!--<script type="text/javascript" src="https://cdn.datatables.net/responsive/3.0.2/js/responsive.bootstrap5.min.js"></script>-->
	<!--<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>-->

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
				
		<?php
			 // get three earliest notification for courses in which game feature is active 
			 // and student participation in the game is also active
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
			 
			 // to make each notification has its own JavaScript variable
			 $i =0;
			 while($row = $rt->fetch_assoc()) {
				 // print the notification
				 echo "const notification".$i." = notyf.success(\"[".$row['course_name']."] ".$row['message']."<br />Click me for details!\");
					   notification".$i.".on('click', ({target, event}) => {window.location.href = 'student_game.php?id=".$row['course_id']."';});";
				 // remove the notification
				 $sql = "DELETE FROM game_unobserved_notif WHERE notification_id = '".$row['notification_id']."'";
				 $db->query($sql);
				 // increment the i
				 $i = $i+1;
			 }
		?>
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
		.btn-warning{
		    background: #fef2b3  !important ;
	    }
		.buttontambah{
			text-align: right;
		}
			td {
            white-space: normal !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
        }

		@media (max-width: 425px) {
			.buttontambah{
				text-align: left;
				margin: 1rem 0 1rem 0;
			}
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
		  setHeaderStudent("dashboard", "Student home");
		?>
<div class="container my-3">
			
		<div class="bodycontent">
			<div class="row d-flex justify-content-center align-items-center">
				<div class="col-md-12">
					<div class="infotitle fs-1"> Student Assesment: </div>
				</div>
			</div>
			<div class="tablecontainer">
				<table id="studentDashboard" class="table table-bordered table-striped responsive nowrap" style="width:100%">
					<!-- <tr>
						<th onclick="sortTable(0,'sumtablecontent',false, 'sumcontainer')">Assessment name <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						<th onclick="sortTable(1,'sumtablecontent',false, 'sumcontainer')">Description <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						<th onclick="sortTable(2,'sumtablecontent',false, 'sumcontainer')">Course <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						<th onclick="sortTable(3,'sumtablecontent',false, 'sumcontainer')">Assessment due <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						<th onclick="sortTable(4,'sumtablecontent',false, 'sumcontainer')">Status <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						<th class="thactions"> Actions </th>
					</tr> -->
					<thead>
							<tr>
								<th>Assessment name</th>
								<th class="description-column">Description</th>
								<style>
									.description-column {
										max-width: 150px; /* Atur sesuai kebutuhan */
										overflow: hidden;
										white-space: nowrap;
										text-overflow: ellipsis; /* Menampilkan elipsis (...) jika terlalu panjang */
									}
								</style>
								<th style="width:25%">Course</th>
								<th>Assesment due</th>
								<th>Status</th>
								<th>Actions</th>
							</tr>
						</thead>
						
						<tbody>
				 <?php
				 		// get the upcoming assessments
						$sql = "SELECT assessment.assessment_id, assessment.public_assessment_id, assessment.name AS assessment_name, assessment.description, assessment.submission_close_time,
							 assessment.course_id, course.name AS course_name
							 FROM assessment	INNER JOIN enrollment ON enrollment.course_id = assessment.course_id
							 INNER JOIN course ON course.course_id = enrollment.course_id
							 WHERE enrollment.student_id = '".$_SESSION['user_id']."'
							 AND (assessment.submission_close_time > CURRENT_TIMESTAMP OR assessment.allow_late_submission = '1')
							 AND assessment.submission_open_time < CURRENT_TIMESTAMP
							 AND course.is_active = 1 
							 ORDER BY assessment.submission_close_time ASC";
						$result = mysqli_query($db,$sql);
						// adapted from https://www.w3schools.com/php/php_mysql_select.asp
						if ($result->num_rows > 0) {
								while($row = $result->fetch_assoc()) {

										// getting an entry with the largest submission id (i.e., the latest)
										$sqlt = "SELECT MAX(submission_id) AS sub_id FROM submission
											WHERE submitter_id = '".$_SESSION['user_id']."' AND assessment_id = '".$row['assessment_id']."'";
										$resultt = mysqli_query($db,$sqlt);
										$rowt = $resultt->fetch_assoc();

									echo "
                                        <tr id=\"".$row['assessment_id']."\" onclick=\"selectRow('".$row['assessment_id']."','sumtablecontent')\">
                                            <td><a>".$row['assessment_name']."</a></td>
                                            <td>";
                                            
                                        $description = trim($row['description']);
                                        $description = preg_replace('/<p>\s*<br>\s*<\/p>/', '', $description);
                                        
                                        if (!empty($description)) {
                                            echo "
                                                <button type=\"button\" class=\"btn btn-primary w-100\" data-bs-toggle=\"modal\" data-bs-target=\"#descModal".$row['assessment_id']."\">
                                                    description
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
                                        
                                        echo "
                                            </td>
                                            <td style=\"white-space: normal !important; word-break: break-word !important; overflow-wrap: break-word !important;\">".$row['course_name']."</td>
                                            <td>".$row['submission_close_time']."</td>";


										if($rowt['sub_id'] != ''){
											// if has been submitted
											echo "
												<td>Submitted</td>
												<td class=\"tdactions\">
													<form class=\"invisform\" action=\"user_download_code.php\" method=\"post\">
														<input type=\"hidden\" name=\"id\" value=\"".$rowt['sub_id']."\">
														<button class=\"btn btn-primary  w-100\" type=\"submit\">download submission</button>
													</form>
													";
													
											// show resubmit
											echo "
													<form class=\"invisform\" action=\"student_assessment_submit.php?id=".$row['public_assessment_id']."\" method=\"post\">
														<button class=\"btn btn-primary w-100\" type=\"submit\">resubmit</button>
													</form>
												";
											// for dealing with suspicion
											 $sqlt = "SELECT suspicion_id, suspicion_type FROM suspicion
												WHERE submission_id = '".$rowt['sub_id']."'";
											$resultt = mysqli_query($db,$sqlt);

											if ($resultt->num_rows > 0) {
												$rowtt = $resultt->fetch_assoc();
												echo "
													<form class=\"invisform\" action=\"user_suspicion_report.php\" method=\"post\">
														<input type=\"hidden\" name=\"id\" value=\"".$rowtt['suspicion_id']."\">
														<input type=\"hidden\" name=\"course_name\" value=\"".$row['course_name']."\">
														<input type=\"hidden\" name=\"assessment_name\" value=\"".$row['assessment_name']."\">
														<input type=\"hidden\" name=\"mode\" value=\"1\">
														";
												if($rowtt['suspicion_type'] == "real"){
													echo "
																<button class=\"btn btn-warning alert  w-100\" type=\"submit\">similarity report</button>
															</form>
															";
												}else{
													echo "
																<button class=\"btn btn-primary  w-100\" type=\"submit\">similarity simulation</button>
															</form>
															";
												}
											}

											
											// for code quality suggestion
											$sqlt = "SELECT public_suggestion_id FROM code_clarity_suggestion
											WHERE submission_id = '".$rowt['sub_id']."'";
											$resultt = mysqli_query($db,$sqlt);
											if ($resultt->num_rows > 0) {
												$rowt = $resultt->fetch_assoc();
												echo "
														<form class=\"invisform\" action=\"student_code_clarity.php?id=".$rowt['public_suggestion_id']."\" method=\"post\">
															<input type=\"hidden\" name=\"mode\" value=\"1\">
															<button class=\"btn btn-primary  w-100\" type=\"submit\">quality suggestion</button>
														</form>
													";
											}
											
											

										}else{
											// have not submitted
											echo "
												<td>Not submitted</td>
												<td class=\"tdactions\">
													<button class=\"btn btn-primary  w-100\" onclick=\"window.open('student_assessment_submit.php?id=".$row['public_assessment_id']."', '_self');\">submit</button>
												";
										}

										// just for HTML closing tags
										echo"
												</td>
											</tr>
										";
								}

						} else {
							// if no upcoming assessments
								echo "
								";
						}
					?>
					</tbody>
					</table>
				</div>
			</div>
		</div>

		<script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
		<script>
		new DataTable('#studentDashboard', {
			responsive: true,
			order: [[4, 'asc']],
		});
		</script>
		
		<script>
			function confirmDelete(userId) {
				$('#deleteModal' + userId).modal('show');
			}
		</script>
  </body>
</html>
