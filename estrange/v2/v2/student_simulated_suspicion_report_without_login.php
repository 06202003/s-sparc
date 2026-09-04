<?php
	// if the assessment id does not exist, redirect to login
	if(isset($_GET['id']) == false || $_GET['id'] == ''){
		header('Location: index.php?invalidreport=true');
		exit;
	}

	include("_config.php");

	// get all data required for this page
	$sqlt = "SELECT suspicion.suspicion_id, suspicion.marked_code,
		suspicion.artificial_code, suspicion.table_info, suspicion.explanation_info,
		suspicion.student_response, submission.submitter_id, assessment.name AS assessment_name,
		course.name AS course_name, course.course_id FROM suspicion
		INNER JOIN submission ON submission.submission_id = suspicion.submission_id
		INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id
		INNER JOIN course ON course.course_id = assessment.course_id
		WHERE suspicion.public_suspicion_id = '".$_GET['id']."'
		AND suspicion.suspicion_type = 'simulation'";
	$resultt = mysqli_query($db,$sqlt);
	$rowt = $resultt->fetch_assoc();

	// if the public suspicion id is invalid OR it is not simulation, redirect to login
	if(is_null($rowt)){
		header('Location: index.php?invalidreport=true');
		exit;
	}

	$markedCode = $rowt['marked_code'];
	$artificialCode = $rowt['artificial_code'];
	$tableInfo = $rowt['table_info'];
	$explanationInfo = $rowt['explanation_info'];
	$studentresponse = $rowt['student_response'];
	$submitter_id = $rowt['submitter_id'];
	$assessment_name =  $rowt['assessment_name'];
	$course_name =  $rowt['course_name'];
	$courseId = $rowt['course_id'];

	recordAccess($db,  $rowt['suspicion_id']);
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

		<title> E-Strange: <?php echo ($human_language == 'en'? "Similarity simulation": "Simulasi kesamaan"); ?> </title>
    <link rel="icon" href="strange_html_layout_additional_files/icon.png">
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
					WHERE game_student_course.student_id = '".$submitter_id."' 
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
				 echo "const notification".$i." = notyf.success(\"".$row['message']."<br />Log in for details!\");
					   notification".$i.".on('click', ({target, event}) => {window.location.href = 'index.php';});";
					   
					   
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
					defaultColour = "rgba(244,224,104,1)";
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
					defaultColour = "rgba(244,224,174,1)";
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
			border: 1px solid #b1b1b1;
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
			border-color: #b1b1b1;
		}
		.commentsim{
			background-color:rgba(244,224,174,1);
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
			border: 1px solid #b1b1b1;
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
			border: 1px solid #b1b1b1;
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
			border: 1px solid #b1b1b1;
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
		div.responsepanel{
			width:98%;
			height:10%;
			border: 1px solid #b1b1b1;
			padding:1%;
			overflow:auto;
		}
		button.action{
			width:100%;
			border:none;
			outline: none;
			cursor: pointer;
			padding: 6px 20px;
			transition: 0.3s;
			background-color: rgba(0,112,149,1);
			text-align: center;
			color: white;
			text-decoration: none;
			margin-top:2%;
			margin-left:1px;
		}
		/* to display div in simulation mode */
		div.longsubcontentwrapperdisplay{
			display: block;
		}
		div.generatedfragment{
			width:100%;
			height:100%;
			display: none;
		}
		div.responsepanel, div.explanationpanel{
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
				<div class="titlewrapper"><?php echo ($human_language == 'en'? "Similarity simulation": "Simulasi kesamaan"); ?></div>

      </div>
			<div class="messagepanel">
				<div class="subtitlewrapper"><?php echo ($human_language == 'en'? "Student ID": "ID mahasiswa"); ?></div>
				<?php
					// get the username and name of the victim
					$sqlt = "SELECT username, name FROM user
						WHERE user_id = '".$submitter_id."'";
					$resultt = mysqli_query($db,$sqlt);
					$rowt = $resultt->fetch_assoc();
				?>
				<div class="subcontentwrapper"><b>:</b> <?php echo $rowt['username'].' / ' . $rowt['name']; ?></div>
				<div class="subtitlewrapper"><?php echo ($human_language == 'en'? "Course": "Mata kuliah"); ?></div>
				<div class="subcontentwrapper"><b>:</b> <?php echo $course_name; ?> </div>
				<div class="subtitlewrapper"><?php echo ($human_language == 'en'? "Assessment": "Tugas"); ?></div>
				<div class="subcontentwrapper"><b>:</b> <?php echo $assessment_name; ?></div>
				<?php
					if($human_language == 'en'){
							echo '
								<div class="longsubtitlewrapper">Actions that may lead alerted similarity:</div>
								<div class="longsubcontentwrapper longsubcontentwrapperdisplay">
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
								</div>
							';
					}else{
							echo '
								<div class="longsubtitlewrapper">Tindakan-tindakan yang dapat menghasilkan kesamaan:</div>
								<div class="longsubcontentwrapper longsubcontentwrapperdisplay">
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
								</div>
							';
					}
				 ?>
			</div>
			<div class="codetitle"><?php echo ($human_language == 'en'? "Submitted code: ": "Kode yang dikumpulkan: "); ?></div>
			<div class="codeview">
				<pre class="prettyprint linenums">
<?php echo $markedCode; ?>
				</pre>
			</div>
    </div>
    <div class="rightpanel">
			<div class="subtitlewrapper"><?php echo ($human_language == 'en'? "Similar content: ": "Konten yang sama: "); ?> </div>';
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
			<?php
				if($human_language == 'en'){
						echo '
	<div class="longsubtitlewrapper">Notice:</div>
	<div class="responsepanel">
The submission is NOT similar to those of other colleagues and this is entirely artificial.
	</div>';
				}else{
						echo '
	<div class="longsubtitlewrapper">Pernyataan:</div>
	<div class="responsepanel">
Kode yang dikumpulkan TIDAK sama dengan siswa lainnya dan segala hal pada laman ini hanya simulasi.
	</div>';
				}
				?>
    </div>
	
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>
