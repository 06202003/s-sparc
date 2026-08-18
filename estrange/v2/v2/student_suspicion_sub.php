<?php
	 // all below code copied from student_assessment_submit_suspicious.php
	session_start();

	// if the SUSPICION id does not exist
	if(isset($_GET['id']) == false || $_GET['id'] == ''){
		header('Location: student_dashboard.php');
		exit;
	}

	// part of sessionchecker pasted here due to unique behaviour of this page
	// redirect if it is not logged in
	if(isset($_SESSION['name']) == false){
		header('Location: student_suspicion_sub_without_login.php?id='.$_GET['id']);
		exit;
	}else{
		// check whether the role is similar to the opened pages

		// get the page role
		$pagerole = htmlentities($_SERVER['PHP_SELF']);
		$pagerole = substr($pagerole, strrpos($pagerole,'/')+1);
		$pagerole = substr($pagerole, 0, strpos($pagerole,'_'));

		// check whether the page is user specific
		if($pagerole != 'user'){
			// if it is in different role
			if($pagerole != $_SESSION['role']){
				// redirect to its dashboard
				if ($_SESSION['role'] == 'admin'){
					header('Location: admin_dashboard.php');
					exit;
				} else if ($_SESSION['role'] == 'lecturer'){
					header('Location: lecturer_dashboard.php');
					exit;
				} else if ($_SESSION['role'] == 'student'){
					header('Location: student_dashboard.php');
					exit;
				}
				
			}
		}
	}

	include("_config.php");

	// check whether the suspicion id is actually exist
	$sql = "SELECT suspicion.suspicion_id, suspicion.student_response, suspicion.marked_code,
		 suspicion.artificial_code, suspicion.table_info, suspicion.explanation_info,
		 suspicion.did_you_know FROM suspicion
		 INNER JOIN submission ON submission.submission_id = suspicion.submission_id
		 WHERE suspicion.public_suspicion_id = '".$_GET['id']."'
		 AND suspicion.suspicion_type = 'real'
		 AND submission.submitter_id = '".$_SESSION['user_id']."'";
	$result = mysqli_query($db,$sql);
	// if the result is zero, redirect to dashboard
	if($result->num_rows == 0){
		header('Location: student_dashboard.php');
		exit;
	}else{
		$row = $result->fetch_assoc();

		$_GET['id'] = $row['suspicion_id'];
		$markedCode = $row['marked_code'];
		$artificialCode = $row['artificial_code'];
		$tableInfo = $row['table_info'];
		$explanationInfo = $row['explanation_info'];
		$didyouknow = $row['did_you_know'];
	}

	// check whether the suspicion id is listed to a course which the submitter enrolled to,
	// and the submission is still open
	$sql = "SELECT assessment.name AS assessment_name, assessment.assessment_id, course.name AS course_name,
		 assessment.submission_close_time AS close_time, CURRENT_TIMESTAMP AS now_time, assessment.suspicion_response AS suspicion_response, assessment.allow_late_submission, course.course_id  
		 FROM assessment INNER JOIN course ON course.course_id = assessment.course_id
		 INNER JOIN submission ON submission.assessment_id = assessment.assessment_id
		 INNER JOIN suspicion ON suspicion.submission_id = submission.submission_id
		 WHERE suspicion.suspicion_id = '".$_GET['id']."'";
	$result = mysqli_query($db,$sql);
	$row = $result->fetch_assoc();

	// if the given assessment id is not listed, redirect to dashboard
	if(is_null($row)){
		header('Location: student_dashboard.php');
		exit;
	}else{
		// set all temporary variables
		$myassessmentid = $row['assessment_id'];
		$myassessmentname = $row['assessment_name'];
		$mycoursename = $row['course_name'];
		$closetime = new DateTime($row['close_time']);
		$nowtime = new DateTime($row['now_time']);
		$mysuspicionresponse = $row['suspicion_response'];
		$allowLateSubmission = $row['allow_late_submission'];
		$courseId = $row['course_id'];
	}

	recordAccess($db, $_GET['id'], $_SESSION['user_id']);

?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

		<title> E-Strange: <?php echo ($human_language == 'en'? "Similarity alert": "Laporan kesamaan"); ?></title>
    <link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">


    <!-- Google Prettify to generate highlight https://github.com/google/code-prettify -->
	<script src="strange_html_layout_additional_files/run_prettify.js"></script>
	<!-- The use of Notyf library https://github.com/caroso1222/notyf -->
	<link rel="stylesheet" href="strange_html_layout_additional_files/notyf.min.css">
	<script src="strange_html_layout_additional_files/notyf.min.js"></script>
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
			 $sqlt = "SELECT game_unobserved_notif.notification_id, game_unobserved_notif.message 
					FROM game_unobserved_notif 
					INNER JOIN game_student_course ON game_student_course.gs_id = game_unobserved_notif.gs_id 
					INNER JOIN game_course ON game_course.course_id = game_student_course.course_id 
					WHERE game_student_course.student_id = '".$_SESSION['user_id']."' 
					AND game_student_course.course_id = '".$courseId."' 
					AND game_course.is_active = '1' 
					AND game_student_course.is_participating = '1' 
					ORDER BY game_unobserved_notif.time_created ASC
					LIMIT 3";
			 $rt = mysqli_query($db,$sqlt);
			 
			 // to make each notification has its own JavaScript variable
			 $i =0;
			 while($row = $rt->fetch_assoc()) {
				 // print the notification
				 echo "const notification".$i." = notyf.success(\"".$row['message']."<br />Click me for details!\");
					   notification".$i.".on('click', ({target, event}) => {window.location.href = 'student_game_statistics.php?id=".$courseId."';});";
					   
					   
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

			// function to toggle general info given at top left of the page.
			function toggleCollapsible(targetDiv){
				var content = document.getElementById(targetDiv);
				if (content.style.display == "block") {
					content.style.display = "none";
				} else {
					content.style.display = "block";
				}
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
				// set to the same selection as prior sorted
				if(selectedCodeFragmentId != null)
					markSelected(selectedCodeFragmentId,tableId);
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

			function markSelected(id, tableId){
				// for header table, redirect to the row's link
				window.location.hash = '#' + id + "hl";
				// mark all related components on both code views and the table.
				markSelectedWithoutChangingTableFocus(id, tableId);
			}

			// to highlight code
			var selectedCodeFragmentId = null;
			function markSelectedWithoutChangingTableFocus(id, tableId){
				if(selectedCodeFragmentId != null){
					resetCurrentFocus();
					// for header table, recolor the contents
					recolorTableContent(tableId);
				}
				// set the CSS of currently selected fragment
				var defaultColour = "";
				if(id.startsWith("c")){
					defaultColour = "rgba(244,161,164,1)";
				}else if(id.startsWith("s")){
					defaultColour = "rgba(101,244,104,1)";
				}
				// for left panel
				recolorCodeFragment(id + "a",defaultColour);
				window.location.hash = '#' + id + "a";
				// for right panel
				recolorCodeFragment(id + "b",defaultColour);
				window.location.hash = '#' + id + "b";
				// for header table, recolor the row
				recolorCodeFragment(id + "hr",defaultColour);
				// for natural language explanation
				document.getElementById(id +"he").style.display = "block";
				// hide the default one
				document.getElementById("dg").style.display = "none";
				// for code fragment counterpart
				document.getElementById(id +"g").style.display = "block";
				selectedCodeFragmentId = id;
			}

			function resetCurrentFocus(){
				// do nothing if selectedCodeFragmentId is null
				if(selectedCodeFragmentId == null)
					return;


				// reset the CSS of previously selected fragment
				var defaultColour = "";
				if(selectedCodeFragmentId.startsWith("c")){
					defaultColour = "rgba(244,211,214,1)";
				}else if(selectedCodeFragmentId.startsWith("s")){
					defaultColour = "rgba(171,244,174,1)";
				}

				// for natural language explanation
				document.getElementById(selectedCodeFragmentId +"he").style.display = "none";
				// for code fragment counterpart
				document.getElementById(selectedCodeFragmentId +"g").style.display = "none";
				// for left panel
				recolorCodeFragment(selectedCodeFragmentId +"a", defaultColour);
				// for right panel
				recolorCodeFragment(selectedCodeFragmentId +"b", defaultColour);

				selectedCodeFragmentId = null;
			}

			// recolor a code fragment with its following rows
			function recolorCodeFragment(id, defaultColour){
				document.getElementById(id).style.backgroundColor = defaultColour;
				// check the following rows which id is the same as the given parameter except concatenated with a positive number.
				for(var i=1;;i++){
					var childId = id + i;
					var child = document.getElementById(childId);
					if(child == null){
						break;
					}else{
						child.style.backgroundColor = defaultColour;
					}
				}
			}
    </script>
    <style>
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif;
			font-size: 12px;
			background-color: rgba(253,253,253,1);
		}
		a{
			text-decoration: none;
			color: black;
		}
		div{
			float:left;
		}

		/* left panel */
		div.leftpanel{
			width:48%;
			height:100%;
			margin-right:1.5%;
		}

		/* title panel */
		div.titlepanel{
			width:100%;
		}
		div.image{
			width:15%;
			height:8%;
			margin-right:1%;
			margin-bottom:1%;
		}
		img{
			width:100%;
			margin-bottom:1%;
		}
		div.titlewrapper{
			width:84%;
			font-weight: bold;
			font-size: 22px;
			padding-top:20px;
			color: rgba(0,65,111,1);
		}

		/* message panel */
		div.messagepanel{
			width:98%;
			height:25%;
			border: 1px solid #dddddd;
			padding:1%;
			overflow:auto;
		}
		ol{
			margin-top:-2px;
		}
		div.subtitlewrapper{
			width:30%;
			font-size:14px;
			padding-bottom:3px;
			font-weight:bold;
		}
		div.subcontentwrapper{
			width:69%;
			font-size:14px;
			padding-bottom:3px;
		}
		div.longsubtitlewrapper{
			width:100%;
			font-size:14px;
			padding-bottom:3px;
			font-weight:bold;
		}
		div.longsubcontentwrapper{
			width:100%;
			font-size:14px;
			padding-bottom:3px;
			display: none;
			overflow: hidden;
		}
		button.collapsible {
			background-color: rgba(0,140,186,1);
			border: none;
			color: white;
			padding: 2px 4px;
			text-align: center;
			text-decoration: none;
			display: inline-block;
			cursor: pointer;
		}

		/* for left code view */
		div.codetitle{
			width:100%;
			font-size:14px;
			font-weight:bold;
			margin-top:1%;
			margin-bottom:-1.5%;
		}
		div.codeview{
			width: 100%;
			height:58%;
		}
		pre{
			tab-size: 2;
			overflow: auto;
			width: 100%;
			height:100%;
			float:left;
			border-color: #dddddd;
		}
		.commentsim{
			background-color:rgba(244,211,214,1);
		}
		.syntaxsim{
			background-color:rgba(171,244,174,1);
		}
		.linenums li {
			list-style-type: decimal;
		}

		/* for table, copied and modified from https://www.w3schools.com/html/tryit.asp?filename=tryhtml_table_intro*/
		div.tablecontainer{
			width: 100%;
			height:20%;
			margin-bottom:1%;
		}
		div.tableheader{
			width: 100%;
			height:20%;
		}
		div.tablecontentcontainer{
			width:100%;
			height:80%;
			overflow-y:scroll;
			overflow-x: hidden;
			border: 1px solid #dddddd;
		}
		table {
			width:100%;
			font-family: inherit;
			font-size: inherit;
			border-collapse: collapse;
		}
		table.header {
			width:97.5%;
		}
		td, th {
			border: 1px solid #dddddd;
			text-align: center;
		}
		td{
			width:20%;
			padding: 2px;
		}
		th{
			border-top: none;
			background-color: rgba(0,140,186,1);
			color: white;
			padding: 4px 8px;
			text-align: center;
			text-decoration: none;
			font-weight: normal;
			width:25%;
			height:100%;
			cursor: pointer;
		}
		tr:nth-child(even) {
			background-color: #eeeeee;
		}
		tr{
			cursor: pointer;
		}
		img.sortpic{
			float:right;
			width:12px;
			margin-bottom:0px;
		}

		/* right panel */
		div.rightpanel{
			width:49%;
			height:100%;
		}
		div.explanationpanel{
			width:98%;
			height:20%;
			border: 1px solid #dddddd;
			padding:1%;
			overflow-y:auto;
			overflow-x:hidden;
		}
		div.explanationcontent{
			float:left;
			width:98%;
			padding:1%;
			display: none;
			text-align: justify;
		}

		/* only for table inside the explanation */
		td.inexplanation, th.inexplanation {
			border: 1px solid black;
			text-align: center;
			padding: 0;
			height:16px;
			font-size: 12px;
		}
		th.inexplanation{
			border: 1px solid black;
			background-color: white;
			color: black;
			width:20%;
			cursor: none;
			font-weight:bold;
		}
		tr.inexplanation:nth-child(even) {
			background-color: white;
		}
		tr.inexplanation{
			cursor: default;
			padding: 0;
			height:16px;
			font-size: 12px;
		}

		div.codefragmentview{
			width: 99.5%;
			height:20%;
			margin-bottom:3.5%;
		}
		div.didyouknowpanel{
			width:98%;
			height:10%;
			border: 1px solid #dddddd;
			padding:1%;
			overflow:auto;
		}
		div.othernav{
			width:98%;
			margin-top:2%;
			text-align:center;
		}

		form.invisform{
			float:left;
			width:23%;
			margin-left:20%;
		}

		button.tablink{
			float:left;
			width:100%;
			border:none;
			outline: none;
			cursor: pointer;
			padding: 6px 20px;
			transition: 0.3s;
			background-color: rgba(0,112,149,1);
			text-align: center;
			color: white;
		}

		div.generatedfragment{
			width:100%;
			height:100%;
			display: none;
		}
		div.didyouknowpanel, div.explanationpanel{
			font-size:14px;
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
    <div class="leftpanel">
      <div class="titlepanel">
        <div class="image"><img src="strange_html_layout_additional_files/logo.png" alt="logo"></div>
        <div class="titlewrapper"><?php echo ($human_language == 'en'? "Similarity report": "Laporan kesamaan"); ?></div>
      </div>
			<div class="messagepanel">
				<div class="subtitlewrapper"><?php echo ($human_language == 'en'? "Student ID": "ID mahasiswa"); ?></div>
				<?php
					// get username and name for given user_id
					$sqlt = "SELECT username, name FROM user
						WHERE user_id = '".$_SESSION['user_id']."'";
					$resultt = mysqli_query($db,$sqlt);
					$rowt = $resultt->fetch_assoc();
				?>
				<div class="subcontentwrapper"><b>:</b> <?php echo $rowt['username'].' / ' . $rowt['name']; ?></div>
				<div class="subtitlewrapper"><?php echo ($human_language == 'en'? "Course": "Mata kuliah"); ?></div>
				<div class="subcontentwrapper"><b>:</b> <?php echo $mycoursename; ?> </div>
				<div class="subtitlewrapper"><?php echo ($human_language == 'en'? "Assessment": "Tugas"); ?></div>
				<div class="subcontentwrapper"><b>:</b> <?php echo $myassessmentname; ?></div>
				<?php
					if($human_language == 'en'){
						echo '
							<div class="longsubtitlewrapper">Why the code is alerted? <button class="collapsible" onclick="toggleCollapsible(\'message1\')">details</button></div>
							<div class="longsubcontentwrapper" id="message1">
								The alert is raised since the code shares obvious similarity to other students\' code that has been previously submitted.
							</div>
							<div class="longsubtitlewrapper">What actions did the student possibly do that lead to this similarity? <button class="collapsible" onclick="toggleCollapsible(\'message2\')">details</button></div>
							<div class="longsubcontentwrapper" id="message2">
								<ol>
									<!-- sorted from positive to negative accusation -->
									<li>Discussing with another student how to approach a task and what resources to use, then developing the solution independently.</li>
									<li>Discussing the detail of your code with another student while working on it.</li>
									<li>Showing troublesome code to another student and asking them for advice on how to fix it.</li>
									<li>Asking another student to take troublesome code and get it working.</li>
									<li>Copying an early draft of another student\'s work and developing it into your own.</li>
									<li>Copying another student\'s code and changing it so that it looks quite different.</li>
									<li>After completing an assessment, adding features that you noticed when looking at another student\'s work. </li>
									<li>Incorporating the work of another student without their permission.</li>
									<li>Incorporating purchased code written by other students into your own work</li>
									<li>Submitting purchased code written by another student as your own work</li>
									<li>Writing the code by yourself but this unexpectedly happens.</li>
									<!-- Basing an assessment largely on work that you wrote and submitted for a previous course, without acknowledging this.-->
								</ol>
							</div>';
							if($mysuspicionresponse == 1){
								echo '<div class="longsubtitlewrapper">What actions should the student do next? <button class="collapsible" onclick="toggleCollapsible(\'message3\')">details</button></div>
								<div class="longsubcontentwrapper" id="message3">
									The student is expected to resubmit the code and provide the reasons why such similarity occurs.
								</div>';
							}
						
					}else{
						echo '
							<div class="longsubtitlewrapper">Mengapa kode ini ditandai? <button class="collapsible" onclick="toggleCollapsible(\'message1\')">detil</button></div>
							<div class="longsubcontentwrapper" id="message1">
								Alert didasarkan dari kesamaan kentara dengan sebagian kode program dari mahasiswa-mahasiswa lain yang telah dikumpulkan sebelumnya.
							</div>
							<div class="longsubtitlewrapper">Apa saja kemungkinan tindakan yang dapat menghasilkan kesamaan ini? <button class="collapsible" onclick="toggleCollapsible(\'message2\')">detil</button></div>
							<div class="longsubcontentwrapper" id="message2">
								<ol>
									<!-- sorted from positive to negative accusation -->
									<li>Berdiskusi dengan siswa lain tentang cara mengerjakan tugas dan sumber-sumber apa yang sebaiknya digunakan, kemudian mengembangkan solusinya secara mandiri.</li>
									<li>Mendiskusikan kode anda secara detil dengan siswa lain pada saat mengerjakannya.</li>
									<li>Memperlihatkan kode yang bermasalah kepada siswa lain dan meminta saran tentang cara memperbaikinya.</li>
									<li>Meminta siswa lain untuk memperbaiki kode yang bermasalah.</li>
									<li>Menyalin draf awal hasil karya siswa lain dan mengembangkannya menjadi milik anda.</li>
									<li>Menyalin kode hasil karya siswa lain dan mengubahnya sehingga terlihat agak berbeda.</li>
									<li>Setelah menyelesaikan suatu tugas, anda menambahkan fitur-fitur yang terinspirasi setelah anda  melihat hasil karya siswa lain. </li>
									<li>Memasukkan pekerjaan siswa lain tanpa meminta izin yang kepada bersangkutan.</li>
									<li>Membeli kode yang ditulis oleh siswa lain untuk dimasukkan ke dalam pekerjaan anda sendiri.</li>
									<li>Membayar siswa lain untuk menulis kode dan mengirimkan sebagai karya anda sendiri.</li>
									<li>Menulis kode secara individu namun kecurigaan ini secara tidak diduga muncul.</li>
									<!-- Basing an assessment largely on work that you wrote and submitted for a previous course, without acknowledging this.-->
								</ol>
							</div>';
							if($mysuspicionresponse == 1){
								echo '<div class="longsubtitlewrapper">Tindakan apa yang harus dilakukan oleh siswa terkait? <button class="collapsible" onclick="toggleCollapsible(\'message3\')">detil</button></div>
								<div class="longsubcontentwrapper" id="message3">
									Siswa terkait diharapkan untuk mengumpulkan ulang kode dan memberikan alasan mengapa kesamaan tak wajar tersebut muncul.
								</div>';
							}
						
					}
				?>
			</div>
			<div class="codetitle" style="width:60%;"><?php echo ($human_language == 'en'? "The code with similar contents highlighted: ": "Kode dengan konten sama ditandai: "); ?></div>
			<div class="codeview">
				<pre class="prettyprint linenums">
<?php echo $markedCode; ?>
				</pre>
			</div>
    </div>
    <div class="rightpanel">
			<div class="subtitlewrapper" style="width:60%;"><?php echo ($human_language == 'en'? "Similar contents: ": "Konten yang sama: "); ?> </div>
			<div class="subcontentwrapper"></div>
			<div class="tablecontainer">
				<div class="tableheader">
					<table class="header">
						<tr>
							<th onclick="sortTable(0,'origtablecontent',false, 'origcontainer')" >ID <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
							<th onclick="sortTable(1,'origtablecontent',false, 'origcontainer')"><?php echo ($human_language == 'en'? "Similarity type": "Tipe kesamaan"); ?> <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
							<th onclick="sortTable(2,'origtablecontent',true, 'origcontainer')"><?php echo ($human_language == 'en'? "Length": "Panjang"); ?> <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
							<th onclick="sortTable(3,'origtablecontent',true, 'origcontainer')"><?php echo ($human_language == 'en'? "Warning level": "Level peringatan"); ?> <img class="sortpic" src="strange_html_layout_additional_files/sort icon.png" alt="logo"></th>
						</tr>
					</table>
				</div>
				<div class="tablecontentcontainer" id="origcontainer">
					<table id="origtablecontent">
						<?php echo $tableInfo; ?>
					</table>
				</div>
			</div>
			<div class="longsubtitlewrapper"><?php echo ($human_language == 'en'? "Similarity explanation:": "Penjelasan kesamaan:"); ?></div>
      <div class="explanationpanel">
<?php echo $explanationInfo; ?>
      </div>
			<div class="codetitle"><?php echo ($human_language == 'en'? "Code counterpart example:": "Contoh kode pembanding:"); ?></div>
			<div class="codefragmentview">
				<div class="generatedfragment" id='dg' style="display:block"> <pre class="prettyprint linenums"></pre></div>
<?php echo $artificialCode; ?>
			</div>
			<div class="longsubtitlewrapper"><?php echo ($human_language == 'en'? "Did you know?": "Apakah kamu tahu?"); ?></div>
      <div class="didyouknowpanel">
<?php echo $didyouknow; ?>
			</div>
			<div class="othernav">
				<?php
						// resubmit button
						if($closetime > $nowtime || $allowLateSubmission == '1'){
							// opening form
							echo "
								<form class=\"invisform\" action=\"student_assessment_submit_suspicious.php\" method=\"post\">
									";
							// if a mode is given, pass it to the form
							if(isset($_POST['mode'])){
								echo"
									<input type=\"hidden\" name=\"mode\" value=\"".$_POST['mode']."\">
									";
							}
							// closing form
							echo "
								<input type=\"hidden\" name=\"id\" value=\"".$_GET['id']."\">
								<button class=\"tablink\" type=\"submit\">Resubmit</button>
							</form>
							";
						}else{
							echo "<form class=\"invisform\" >"; 
							echo ($human_language == 'en'? "Cannot resubmit code": "Tidak dapat mengirimkan ulang kode");
							echo "</form>";
						}

					// back or home button
					if(isset($_POST['mode'])){
						// if mode is set, change the direction of back buttons based on given mode
 						if($_POST['mode'] == '1'){
							echo '
								<form class="invisform"  action="student_dashboard.php" method="post">
									<button class="tablink" type="submit">Back</button>
								</form>
								';
						}else if($_POST['mode'] == '2'){
							echo '
								<form class="invisform"  action="student_submission.php" method="post">
									<button class="tablink" type="submit">Back</button>
								</form>
								';
						}

					}else{
						// otherwise, set to student dashboard
						echo '
							<form class="invisform"  action="student_dashboard.php" method="post">
								<button class="tablink" type="submit">Home</button>
							</form>
							';
					}
				?>
			</div>
    </div>
	
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
