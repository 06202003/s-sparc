<?php
	include("_sessionchecker.php");
	include("_config.php");
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

		<title> E-STRANGE: Student similarity responses</title>
    <link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">


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
			body { font-family: 'Inter', system-ui, -apple-system, sans-serif;
				font-size: 12px;
				background-color: rgba(250,250,250,1);
			}
			div{
				float:left;
			}

			/* copied and modified from https://www.w3schools.com/css/css3_buttons.asp */
			button {
				background-color: rgba(0,140,186,1);
				border: none;
				color: white;
				padding: 2px 4px;
				text-align: center;
				text-decoration: none;
				display: inline-block;
				cursor: pointer;
			}

			/* for tabbed view. copied and modified from https://www.w3schools.com/howto/howto_js_tabs.asp */
			.tab {
			  float:left;
				width:100%;
				background-color: rgba(0,140,186,1);
			}
			button.tablinks {
				border:none;
				outline: none;
			  float: left;
			  cursor: pointer;
			  padding: 6px 20px;
				height:30px;
			  transition: 0.3s;
			}
			.tab button:hover {
			  background-color: rgba(20,160,206,1);
			}
			.tab button.active {
			  background-color: rgba(40,180,226,1);
			}
			div.tabcontent {
				float:left;
				width:99%;
				height:80%;
				display: none;
				border-top: none;
			}

			/* for header */
			div.header{
				width:100%;
				height:8%;
				margin-bottom:10px;
			}
			img{
				float:left;
				height:100%;
				margin-right:10px;
			}
			div.headertitle{
				font-weight: bold;
				font-size: 22px;
				height:100%;
				padding-top:20px;
				color: rgba(0,65,111,1);
			}
			div.logintext{
				float:right;
			}

			div.bodycontent{
				width:98%;
				padding-top:30px;
				padding-bottom:30px;
				padding-left:1%;
				padding-right:1%;
			}

			div.infotitle{
				width:100%;
				font-size: 20px;
				font-weight: bold;
				margin-bottom:10px;
			}

			/* for table, copied and modified from https://www.w3schools.com/html/tryit.asp?filename=tryhtml_table_intro*/
			div.tablecontainer{
				float:left;
				width: 100%;
			}
			table {
				width:100%;
			  font-family: inherit;
				font-size: inherit;
			  border-collapse: collapse;
			}
			table.header{
				width:99%;
			}
			td, th {
			  border: 1px solid #dddddd;
				padding: 4px;
			}
			td{
				width:17%;
				text-align: justify;
				vertical-align: top;
			}
			th{
				border-top: none;
				background-color: rgba(0,140,186,1);
				color: white;
				padding: 4px 8px;
				text-align: justify;
				text-decoration: none;
				font-weight: normal;
				width:17%;
				height:100%;
				cursor: pointer;
			}
			tr{
				background-color: rgba(255,255,255,1);
				cursor: pointer;
			}
			tr:nth-child(even) {
			  background-color: #eeeeee;
			}

			/* additional CSS for tables */
			div.tablecontentpanel{
				width:100%;
				height:50%;
				overflow-y: scroll;
				border: 1px solid #dddddd;
			}

			/* for action buttons */
			th.thactions{
				cursor: default;
			}
			td.tdactions{
				text-align: left;
			}
			button.actions{
				margin-left:2px;
				margin-right:2px;
			}

			img.sortpic{
				float:right;
				height:14px;
				margin-right:-4px;
				margin-bottom:0px;
			}

			button.alert{
				background-color: rgba(255,0,0,1);
			}

			form.invisform{
				float:left;
				height:10px;
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
		<div class="header">
			<img src="strange_html_layout_additional_files/logo.png" alt="logo" />
			<div class="headertitle">Student similarity responses</div>
			<?php
			  echo '<div class="logintext">Hello '.$_SESSION['name'].' You logged in as '.$_SESSION['role'].'!</div>';
			?>
		</div>

		<div class="tab">
			<button class="tablinks" onclick="window.open('student_dashboard.php', '_self');">Upcoming assessment due</button>
			<button class="tablinks" onclick="window.open('student_enrollment.php', '_self');">Enrolment</button>
			<button class="tablinks" onclick="window.open('student_submission.php', '_self');">Submissions</button>
			<button class="tablinks active" onclick="window.open('student_suspicion_response.php', '_self');">Similarity responses</button>
			<button class="tablinks" onclick="window.open('student_game.php', '_self');">Game</button>
			<button class="tablinks" onclick="window.open('user_info_self_update.php', '_self');">Update personal information</button>
			<button class="tablinks" onclick="window.open('user_about.php', '_self');">About</button>
			<form action=" <?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
			  <input type="hidden" name="logout" value="logout">
			  <button class="tablinks" type="submit">Logout</button>
			</form>
		</div>

		<div class="bodycontent">
			<div class="infotitle"> Similarity responses: </div>
			<div class="tablecontainer">
				<table class="header">
					<tr>
						<th onclick="sortTable(0,'sumtablecontent',false, 'sumcontainer')">Assessment name <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						<th onclick="sortTable(1,'sumtablecontent',false, 'sumcontainer')">Course <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						<th onclick="sortTable(2,'sumtablecontent',true, 'sumcontainer')"> Alert time <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						<th onclick="sortTable(3,'sumtablecontent',false, 'sumcontainer')"> Response <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						<th class="thactions"> Actions </th>
					</tr>
				</table>
				<div class="tablecontentpanel">
					<table id="sumtablecontent">
						<?php
							// show all suspicions responded by the student
							$sql = "SELECT suspicion.suspicion_id, assessment.name AS assessment_name,
								course.name AS course_name, suspicion.time_created,
								suspicion.student_response FROM suspicion
								INNER JOIN submission ON suspicion.submission_id = submission.submission_id
								INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id
								INNER JOIN course ON assessment.course_id = course.course_id
								WHERE submission.submitter_id = '".$_SESSION['user_id']."'
								AND suspicion.student_response IS NOT NULL
								AND suspicion.suspicion_type = 'real'
								ORDER BY suspicion.time_created DESC";
							$result = mysqli_query($db,$sql);
							// adapted from https://www.w3schools.com/php/php_mysql_select.asp
							if ($result->num_rows > 0) {
									while($row = $result->fetch_assoc()) {
											echo "
												<tr id=\"".$row['suspicion_id']."\" onclick=\"selectRow('".$row['suspicion_id']."','sumtablecontent')\">
													<td><a>".$row['assessment_name']."</a></td>
													<td>".$row['course_name']."</td>
													<td>".$row['time_created']."</td>
													<td>".$row['student_response']."</td>
													<td class=\"tdactions\">
														<form class=\"invisform\" action=\"user_suspicion_report.php\" method=\"post\">
															<input type=\"hidden\" name=\"id\" value=\"".$row['suspicion_id']."\">
															<input type=\"hidden\" name=\"course_name\" value=\"".$row['course_name']."\">
															<input type=\"hidden\" name=\"assessment_name\" value=\"".$row['assessment_name']."\">
															<input type=\"hidden\" name=\"mode\" value=\"3\">
															<button class=\"actions\" type=\"submit\">similarity report</button>
														</form>
													</td>
												</tr>
											";
									}
							} else {
									// if no entries
									echo "
										<tr>
											<td colspan=6 style='text-align:center'>No entries</td>
										</tr>
									";
							}
						?>
					</table>
				</div>

			</div>
		</div>

		<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
