<?php
// set header for similarity and quality reports
function setHeaderReport($selectedMenu, $submissionID, $db){
	
	// HEADER PEER_REVIEW UNTUK REPORT
	if ($selectedMenu == 'peer_review') {
        
        echo "<div class=\"tab\">"; 
        if(isset($_SESSION['role']) && $_SESSION['role'] == 'student'){
            echo '<button class="btn btn-outline-secondary me-1 mb-2 buttonmobile" onclick="window.open(\'student_peer_review.php\', \'_self\');">Back</button>';
        }else if (isset($_SESSION['role']) && $_SESSION['role'] == 'lecturer') {
			if (!empty($submissionID)) {
				echo '<button class="btn btn-outline-secondary me-1 mb-2 buttonmobile" onclick="window.open(\'lecturer_peer_review_list.php?id=' . $submissionID . '\', \'_self\');">Back</button>';
			} else {
				// Jika ID-nya null, kembali ke daftar utama
				echo '<button class="btn btn-outline-secondary me-1 mb-2 buttonmobile" onclick="window.open(\'lecturer_peer_review.php\', \'_self\');">Back</button>';
			}
		}

        if(isset($_SESSION['name']) && $_SESSION['role'] == 'student'){
            echo "<button class=\"btn btn-outline-primary buttonmobile mb-2\" onclick=\"window.open('student_peer_dashboard.php', '_self');\">Dashboard</button>";
        }else{
			echo "<button class=\"btn btn-outline-primary buttonmobile mb-2\" onclick=\"window.open('lecturer_peer_dashboard.php', '_self');\">Dashboard</button>";
		}

        echo "</div>";
        return; 
    }

	if(isset($_SESSION['name']) == false){
		// not logged in, use public similarity report and login button
		
		echo "<div class=\"row d-flex\">";
		
		// public similarity report
		$sqlt = "SELECT suspicion.originality_point, suspicion.public_suspicion_id, suspicion.suspicion_id, suspicion.suspicion_type, assessment.name AS assessment_name, course.name AS course_name      
		FROM submission
		INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id 
		INNER JOIN course ON assessment.course_id = course.course_id 
		INNER JOIN suspicion ON submission.submission_id = suspicion.submission_id 
		WHERE submission.submission_id = '".$submissionID."'";
		$resultt = mysqli_query($db,$sqlt);
		
		// do nothing if no result
		if($resultt->num_rows != 0){
		
			$rowt = $resultt->fetch_assoc();
			
			if($rowt["originality_point"] < 0){
			    $rowt["originality_point"] = 0;
			}
				
			echo "<button class=\"btn btn-outline-primary khususbtnout mb-2 ".(($selectedMenu == 'originality')?'active':'')."\" onclick=\"window.open('student_suspicion_sub_without_login.php?id=".$rowt["public_suspicion_id"]."', '_self');\">Originality: ".$rowt["originality_point"]."%</button>";
		}
		
		// quality report
		$sqlt = "SELECT code_clarity_suggestion.quality_point, code_clarity_suggestion.public_suggestion_id, assessment.name AS assessment_name, course.name AS course_name      
		FROM submission
		INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id 
		INNER JOIN course ON assessment.course_id = course.course_id 
		INNER JOIN code_clarity_suggestion ON submission.submission_id = code_clarity_suggestion.submission_id
		WHERE submission.submission_id = '".$submissionID."'";
		$resultt = mysqli_query($db,$sqlt);
		
		
		if($resultt->num_rows != 0){		
			$rowt = $resultt->fetch_assoc();
			
			echo "<button class=\"btn btn-outline-primary buttonmobile ".(($selectedMenu == 'quality')?'active':'')."\" onclick=\"window.open('student_code_clarity.php?id=".$rowt["public_suggestion_id"]."', '_self');\">Quality: ".$rowt["quality_point"]."%</button>";
		}else{
		    // if none, put dummy button
		    echo "<button class=\"btn btn-outline-primary buttonmobile disabled \">Quality: 100%</button>";
		}
		
		echo "<button class=\"btn btn-outline-primary buttonmobile".(($selectedMenu == 'login')?'active':'')."\" onclick=\"window.open('index.php', '_self');\">Login</button>
		</div>";
	}else{
		// logged in, similarity report needs to be accessed via form
		
		echo "<div class=\"tab\">";
		
		// originality points
		$sqlt = "SELECT suspicion.originality_point, suspicion.public_suspicion_id, suspicion.suspicion_id, suspicion.suspicion_type, assessment.name AS assessment_name, course.name AS course_name      
		FROM submission
		INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id 
		INNER JOIN course ON assessment.course_id = course.course_id 
		INNER JOIN suspicion ON submission.submission_id = suspicion.submission_id 
		WHERE submission.submission_id = '".$submissionID."'";
		$resultt = mysqli_query($db,$sqlt);
		
		// do nothing if no result
		if($resultt->num_rows != 0){
		
			$rowt = $resultt->fetch_assoc();
			
			if($rowt["originality_point"] < 0){
			    $rowt["originality_point"] = 0;
			}
			
			echo "<form class=\"d-inline\" action=\"user_suspicion_report.php\" method=\"post\">
					<input type=\"hidden\" name=\"id\" value=\"".$rowt['suspicion_id']."\">
					<input type=\"hidden\" name=\"course_name\" value=\"".$rowt['course_name']."\">
					<input type=\"hidden\" name=\"assessment_name\" value=\"".$rowt['assessment_name']."\">";
			
			if(isset($_POST['mode'])){
				echo "<input type=\"hidden\" name=\"mode\" value=\"".$_POST['mode']."\">";
			}
			
			echo "<button class=\"btn btn-outline-primary buttonmobile khususoriginal   ".(($selectedMenu == 'originality')?'active':'')." \" type=\"submit\">Originality: ".$rowt["originality_point"]."%</button>
				</form>";
		}
		
		
		// code quality
		$sqlt = "SELECT code_clarity_suggestion.quality_point, code_clarity_suggestion.public_suggestion_id, assessment.name AS assessment_name, course.name AS course_name      
		FROM submission
		INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id 
		INNER JOIN course ON assessment.course_id = course.course_id 
		INNER JOIN code_clarity_suggestion ON submission.submission_id = code_clarity_suggestion.submission_id
		WHERE submission.submission_id = '".$submissionID."'";
		$resultt = mysqli_query($db,$sqlt);
		
		// do nothing if no result
		if($resultt->num_rows != 0){		
			$rowt = $resultt->fetch_assoc();
			
			echo "<form class=\"d-inline \" action=\"student_code_clarity.php?id=".$rowt['public_suggestion_id']."\" method=\"post\">";
			
			if(isset($_POST['mode'])){
				echo "<input type=\"hidden\" name=\"mode\" value=\"".$_POST['mode']."\">";
			}
			
			echo "<button class=\"btn btn-outline-primary buttonmobile khususquality    ".(($selectedMenu == 'quality')?'active':'')."\" type=>Quality: ".$rowt["quality_point"]."%</button>
				</form>";
		}else{
		    // if none, put dummy button
		    echo "<button class=\"btn btn-outline-primary buttonmobile khususquality disabled\">Quality: 100%</button>";
		}
			
		echo "<div class=\"mt-2\"></div>";
		// back button
		if($_SESSION['role'] == 'lecturer'){
			echo '<button class="btn btn-outline-secondary me-1 mb-2 buttonmobile" onclick="window.open(\'lecturer_submission.php\', \'_self\');">Back</button>';
		}else if($_SESSION['role'] == 'student'){
			if($_POST['mode'] == '1'){
				echo '<button class="btn btn-outline-secondary me-1 mb-2 buttonmobile" onclick="window.open(\'student_dashboard.php\', \'_self\');">Back</button>';
			}else if($_POST['mode'] == '2'){
				echo '<button class="btn btn-outline-secondary me-1 mb-2 buttonmobile" onclick="window.open(\'student_submission.php\', \'_self\');">Back</button>';
			}
		}
			
		echo "<button class=\"btn btn-outline-primary buttonmobile mb-2".(($selectedMenu == 'login')?'active':'')."\" onclick=\"window.open('index.php', '_self');\">Dashboard</button>
		</div>";
	}


	
}


// set header lecturer
// function setHeaderLecturer($selectedMenu, $headerText){
	
// 	echo '<div class="header">
// 			<img src="strange_html_layout_additional_files/logo.png" alt="logo" />
// 			<div class="headertitle">'.$headerText.'</div>
// 			<div class="logintext">Hello '.$_SESSION['name'].' You logged in as '.$_SESSION['role'].'!</div>
// 		</div>

// 		<div class="tab">
// 			<button class="tablinks '.(($selectedMenu == 'courses')?'active':'').'" onclick="window.open(\'lecturer_dashboard.php\', \'_self\');">Courses</button>
// 			<button class="tablinks '.(($selectedMenu == 'colecturer courses')?'active':'').'" onclick="window.open(\'colecturer_courses.php\', \'_self\');">Co-lecturing</button>
// 			<button class="tablinks '.(($selectedMenu == 'update personal information')?'active':'').'" onclick="window.open(\'user_info_self_update.php\', \'_self\');">Account</button>
// 			<button class="tablinks '.(($selectedMenu == 'about')?'active':'').'" onclick="window.open(\'user_about.php\', \'_self\');">About</button>
// 			<form action=" '.htmlentities($_SERVER['PHP_SELF']).'" method="post">
// 			  <input type="hidden" name="logout" value="logout">
// 			  <button class="tablinks" type="submit">Logout</button>
// 			</form>
// 		</div>';
// }

// <img src="../strange_html_layout_additional_files/logo.png" alt="logo" class="mobile" />
function setHeaderLecturer($selectedMenu, $headerText){
	echo '
	<div class="container-fluid">
	<div class="row d-flex justify-content-center align-items-center  mx-3">
	<div class="col-md-6 layoutmobilestart">
	
	<img src="../strange_html_layout_additional_files/logo.png" alt="logo" class="mobile" />
	<style>
		.layoutmobilestart{
			text-align:left;
		}
		.layoutmobileend{
			text-align:right;
		}
		.logout{
			margin-right:1rem;
		}
		.mobile {
			margin: 0;
			width: 100%;
			height: auto;
			max-height: 200px;
			max-width: 200px;
		}
		.navbarAdmin{
			background-color: #51adba;height:auto;padding-bottom:1rem;
		}
		.colNav{
			margin-bottom:-1.25rem;
		}
		.logoutli{
			margin-left:auto;
		}
		@media only screen and (max-width: 425px) {
			.mobile {
				margin: 1rem;
				width: 100%;
				height: auto;
				max-height: 150px;
				max-width: 150px;
			}
			.layoutmobilestart{
				text-align:center;
			}
			.layoutmobileend{
				text-align:center;
			}
			.logout{
				margin:0;
			}
			.navbarAdmin{
				background-color: #51adba;height:auto;padding-bottom:0rem;
			}
			.colNav{
				margin-bottom:1rem;
				text-align:left;
			}
			a{
				text-align:left;			
			}
			.logoutli{
				margin-left:0;
			}
		}
	</style>
	 
	  </div>
	  <div class="col-md-6 layoutmobileend">
		<div class="headertitle fs-2">' . $headerText . '</div>
		<div class="logintext">Hello ' . $_SESSION['name'] . ' You logged in as ' . $_SESSION['role'] . '!</div>
	  </div>
	</div>
  </div>

    <nav class="navbar navbar-expand-lg fw-bold navbarAdmin" >
        <!-- <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
             <span class="navbar-toggler-icon"></span>
         </button> -->
		<button class="navbar-toggler text-start w-100 h-100 border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
			<div class=" me-2 pb-2"><i class="fa-solid fa-bars" style="padding-right: 5px; padding-bottom:5px;"></i><h3 class="fw-bolder">Menu</h3></div>
	  	</button>
        <div class="collapse navbar-collapse mx-3 colNav" id="navbarNav">
            <ul class="navbar-nav " style="display: flex;  ;width: 100%;">
                <li class="nav-item ' . ($selectedMenu == 'courses' ? 'active' : '') . '">
                    <a class="nav-link text-white  me-3 fw-medium" href="lecturer_peer_dashboard.php">Courses</a>
                </li>
				<li class="nav-item ' . ($selectedMenu == 'peer_review' ? 'active' : '') . '">
                    <a class="nav-link text-white  me-3 fw-medium" href="lecturer_peer_review.php">Peer Review</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'colecturer courses' ? 'active' : '') . '">
                    <a class="nav-link text-white  me-3 fw-medium " href="colecturer_courses.php">Co-lecturing</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'update personal information' ? 'active' : '') . '">
                    <a class="nav-link text-white  me-3 fw-medium " href="user_info_self_update.php">Account</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'about' ? 'active' : '') . '">
                    <a class="nav-link text-white  me-3 fw-medium " href="user_about.php">About</a>
                <li class="nav-item  " >
                    <form class="logout" action="' . htmlentities($_SERVER['PHP_SELF']) . '" method="post">
                        <input type="hidden" name="logout" value="logout">
                        <button class="btn  w-100  text-center fw-medium bg-transparent text-white" type="submit" style="padding-top:7px">Logout</button>
                    </form>
                </li>
				
            </ul>
        </div>
    </nav>

	';
}



// // set header student
// function setHeaderStudent($selectedMenu, $headerText){
	
// 	echo '<div class="header">
// 			<img src="strange_html_layout_additional_files/logo.png" alt="logo" />
// 			<div class="headertitle">'.$headerText.'</div>
// 			<div class="logintext">Hello '.$_SESSION['name'].' You logged in as '.$_SESSION['role'].'!</div>
// 		</div>
		
// 		<div class="tab">
// 		<button class="tablinks '.(($selectedMenu == 'dashboard')?'active':'').'" onclick="window.open(\'student_dashboard.php\', \'_self\');">Asmt due</button>
// 			<button class="tablinks '.(($selectedMenu == 'enrollment')?'active':'').'" onclick="window.open(\'student_enrollment.php\', \'_self\');">Enrolment</button>
// 			<button class="tablinks '.(($selectedMenu == 'submissions')?'active':'').'" onclick="window.open(\'student_submission.php\', \'_self\');">Submissions</button>
// 			<button class="tablinks '.(($selectedMenu == 'quiz')?'active':'').'" onclick="window.open(\'student_instant_quiz.php\', \'_self\');">Quiz</button>
// 			<button class="tablinks '.(($selectedMenu == 'game')?'active':'').'" onclick="window.open(\'student_game.php\', \'_self\');">Game</button>
// 			<button class="tablinks '.(($selectedMenu == 'colecturer courses')?'active':'').'" onclick="window.open(\'colecturer_courses.php\', \'_self\');">Co-lecturing</button>
// 			<button class="tablinks '.(($selectedMenu == 'update personal information')?'active':'').'" onclick="window.open(\'user_info_self_update.php\', \'_self\');">Account</button>
// 			<button class="tablinks '.(($selectedMenu == 'about')?'active':'').'" onclick="window.open(\'user_about.php\', \'_self\');">About</button>
// 			<form action=" '.htmlentities($_SERVER['PHP_SELF']).'" method="post">
// 			  <input type="hidden" name="logout" value="logout">
// 			  <button class="tablinks" type="submit">Logout</button>
// 			</form>
// 		</div>';
// }

// <img src="../strange_html_layout_additional_files/logo.png" alt="logo" class="mobile" />
function setHeaderStudent($selectedMenu, $headerText){ 
	// penambahan menu Peer_Review
	echo '
	<div class="container-fluid">
	<div class="row d-flex justify-content-center align-items-center  mx-3">
	<div class="col-md-6 layoutmobilestart">
	
	<img src="../strange_html_layout_additional_files/logo.png" alt="logo" class="mobile" />
	<style>
		.layoutmobilestart{
			text-align:left;
		}
		.layoutmobileend{
			text-align:right;
		}
		.logout{
			margin-right:1rem;
		}
		.mobile {
			margin: 0;
			width: 100%;
			height: auto;
			max-height: 200px;
			max-width: 200px;
		}
		.navbarStudent{
			background-color: #51adba;height:auto;padding-bottom:1rem;
		}
		.colNav{
			margin-bottom:-1.25rem;
		}
		.logoutli{
			margin-left:auto;
		}
		@media only screen and (max-width: 425px) {
			.mobile {
				margin: 1rem;
				width: 100%;
				height: auto;
				max-height: 150px;
				max-width: 150px;
			}
			.layoutmobilestart{
				text-align:center;
			}
			.layoutmobileend{
				text-align:center;
			}
			.logout{
				margin:0;
			}
			.navbarStudent{
				background-color: #51adba;height:auto;padding-bottom:0rem;
			}
			.colNav{
				margin-bottom:1rem;
				text-align:left;
			}
			a{
				text-align:left;			
			}
			.logoutli{
				margin-left:0;
			}
		}
	</style>
	</div>

	<div class="col-md-6 layoutmobileend">
	<div class="headertitle fs-1">' . $headerText . '</div>
	<div class="logintext">Hello ' . $_SESSION['name'] . ' You logged in as ' . $_SESSION['role'] . '!</div>
	</div>
	</div>
	</div>
		
	<nav class="navbar navbar-expand-lg navbarStudent">
	
	<a class="navbar-toggler text-start text-decoration-none w-100 h-100 border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavStudent" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
		<div class="me-2 pb-2 ps-1"><i class="fa-solid fa-bars" style="padding-right: 5px; padding-bottom:5px;"></i><h3 class="fw-bolder">Menu</h3></div>
	</a>
	<div class="collapse navbar-collapse mx-3 colNav" id="navbarNavStudent">
		<ul class="navbar-nav " style="display: flex;  ;width: 100%;">
			<li class="nav-item'. ($selectedMenu == 'dashboard'?'active':'') .' mx-3">
				<a class="nav-link text-white" href="student_peer_dashboard.php">Asmt due</a>
			</li>
			<li class="nav-item '. ($selectedMenu == 'enrollment'?'active':'') .' mx-3">
				<a class="nav-link text-white" href="student_enrollment.php">Enrollment</a>
			</li>
			<li class="nav-item '. ($selectedMenu == 'submissions'?'active':'') .' mx-3">
				<a class="nav-link text-white" href="student_submission.php">Submission</a>
			</li>
			<li class="nav-item '. ($selectedMenu == 'game'?'active':'') .' mx-3">
				<a class="nav-link text-white" href="student_game.php">Game</a>
			</li>
			<li class="nav-item '. ($selectedMenu == 'peer_review'?'active':'') .' mx-3">
				<a class="nav-link text-white" href="student_peer_review.php">Peer Review</a>
			</li>
			<li class="nav-item '. ($selectedMenu == 'colecturer courses'?'active':'') .' mx-3">
				<a class="nav-link text-white" href="colecturer_courses.php">Co-Lecturing</a>
			</li>
			<li class="nav-item '. ($selectedMenu == 'update personal information'?'active':'') .' mx-3">
				<a class="nav-link text-white" href="user_info_self_update.php">Account</a>
			</li>
			<li class="nav-item '. (($selectedMenu == 'about')?'active':'').' mx-3">
				<a class="nav-link text-white" href="user_about.php">About</a>
			</li>
			<li class="nav-item  mx-3" >
				<form class="logout" action="' . htmlentities($_SERVER['PHP_SELF']) . '" method="post">
					<input type="hidden" name="logout" value="logout">
					<button class="btn w-100  text-center fw-medium bg-transparent text-white" type="submit" style="padding-top:7px">Logout</button>
				</form>
			</li>
		</ul>
	</div>

	</nav>';
}


// set header admin
// function setHeaderAdmin($selectedMenu, $headerText){
	
// 	echo '<div class="header">
// 			<img src="strange_html_layout_additional_files/logo.png" alt="logo" style="width: 100%; height: auto; min-height: 175px; min-width: 300px" />
// 			<div class="headertitle">'.$headerText.'</div>
// 			<div class="logintext">Hello '.$_SESSION['name'].' You logged in as '.$_SESSION['role'].'!</div>
// 		</div>
		
// 		<div class="tab">
// 			<button class="tablinks '.(($selectedMenu == 'dashboard')?'active':'').'" onclick="window.open(\'admin_dashboard.php\', \'_self\');">Lecturers</button>
// 			<button class="tablinks '.(($selectedMenu == 'students')?'active':'').'" onclick="window.open(\'admin_students.php\', \'_self\');">Students</button>
// 			<button class="tablinks '.(($selectedMenu == 'enrollment')?'active':'').'" onclick="window.open(\'admin_enrollment_student.php\', \'_self\');">Student enrollment</button>
// 			<button class="tablinks '.(($selectedMenu == 'colecturer enrollment')?'active':'').'" onclick="window.open(\'admin_enrollment_colecturer.php\', \'_self\');">Co-lecturer enrollment</button>
// 			<button class="tablinks '.(($selectedMenu == 'submissions')?'active':'').'" onclick="window.open(\'admin_student_submissions.php\', \'_self\');">Submissions</button>
// 			<button class="tablinks '.(($selectedMenu == 'game')?'active':'').'" onclick="window.open(\'admin_game.php\', \'_self\');">Game</button>
// 			<button class="tablinks '.(($selectedMenu == 'update personal information')?'active':'').'" onclick="window.open(\'user_info_self_update.php\', \'_self\');">Account</button>
// 			<button class="tablinks '.(($selectedMenu == 'about')?'active':'').'" onclick="window.open(\'user_about.php\', \'_self\');">About</button>
// 			<form action=" '.htmlentities($_SERVER['PHP_SELF']).'" method="post">
// 			  <input type="hidden" name="logout" value="logout">
// 			  <button class="tablinks" type="submit">Logout</button>
// 			</form>
// 		</div>';


// }

function setHeaderAdmin($selectedMenu, $headerText) {
    echo '
	<div class="container-fluid">
	<div class="row d-flex justify-content-center align-items-center  mx-3">
	<div class="col-md-6 layoutmobilestart">
	<img src="../strange_html_layout_additional_files/logo.png" alt="logo" class="mobile" />
	<style>


		.layoutmobilestart{
			text-align:left;
		}
		.layoutmobileend{
			text-align:right;
		}
		.logout{
			margin-right:1rem;
		}
		.mobile {
			margin: 0;
			width: 100%;
			height: auto;
			max-height: 200px;
			max-width: 200px;
		}
		.navbarAdmin{
			background-color: #51adba;height:auto;padding-bottom:1rem;
		}
		.colNav{
			margin-bottom:-1.25rem;
		}
		.logoutli{
			margin-left:auto;
		}
		@media only screen and (max-width: 425px) {
			.mobile {
				margin: 1rem;
				width: 100%;
				height: auto;
				max-height: 150px;
				max-width: 150px;
			}
			.layoutmobilestart{
				text-align:center;
			}
			.layoutmobileend{
				text-align:center;
			}
			.logout{
				margin:0;
			}
			.navbarAdmin{
				background-color: #51adba;height:auto;padding-bottom:0rem;
			}
			.colNav{
				margin-bottom:1rem;
				text-align:left;
			}
			a{
				text-align:left;			
			}
			.logoutli{
				margin-left:0;
			}
		}
	</style>
	 
	  </div>
	  <div class="col-md-6 layoutmobileend">
		<div class="headertitle fs-2">' . $headerText . '</div>
		<div class="logintext">Hello ' . $_SESSION['name'] . ' You logged in as ' . $_SESSION['role'] . '!</div>
	  </div>
	</div>
  </div>

    <nav class="navbar navbar-expand-lg navbarAdmin">
        <!-- <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
             <span class="navbar-toggler-icon"></span>
         </button> -->
		<a class="navbar-toggler text-start text-decoration-none w-100 h-100 border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
			<div class="me-2 pb-2 ps-1 d-flex align-items-center justify-content-center"><i class="fa-solid fa-bars" style="padding-right: 5px; padding-bottom:5px;"></i><h3 class="fw-bolder">Menu</h3></div>
	  	</a>
        <div class="collapse navbar-collapse mx-3 colNav" id="navbarNav" >
            <ul class="navbar-nav " style="display: flex;  ;width: 100%;">
                <li class="nav-item ' . ($selectedMenu == 'dashboard' ? 'active' : '') . '">
                    <a class="nav-link text-white me-3 fw-medium" href="admin_dashboard.php">Lecturers</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'students' ? 'active' : '') . '">
                    <a class="nav-link text-white me-3 fw-medium " href="admin_students.php">Students</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'enrollment' ? 'active' : '') . '">
                    <a class="nav-link text-white me-3 fw-medium " href="admin_enrollment_student.php">Student enrollment</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'colecturer enrollment' ? 'active' : '') . '">
                    <a class="nav-link text-white me-3 fw-medium " href="admin_enrollment_colecturer.php">Co-lecturer enrollment</a>
                </li>
				<li class="nav-item ' . ($selectedMenu == 'courses' ? 'active' : '') . '">
                    <a class="nav-link text-white  me-3 fw-medium" href="admin_course.php">Courses</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'submissions' ? 'active' : '') . '">
                    <a class="nav-link text-white me-3 fw-medium " href="admin_student_submissions.php">Submissions</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'game' ? 'active' : '') . '">
                    <a class="nav-link text-white me-3 fw-medium " href="admin_game.php">Game</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'update personal information' ? 'active' : '') . '">
                    <a class="nav-link text-white me-3 fw-medium " href="user_info_self_update.php">Account</a>
                </li>
                <li class="nav-item ' . ($selectedMenu == 'about' ? 'active' : '') . '">
                    <a class="nav-link text-white me-3 fw-medium " href="user_about.php">About</a>
                </li>
                <li class="nav-item  " >
                    <form class="logout" action="' . htmlentities($_SERVER['PHP_SELF']) . '" method="post">
                        <input type="hidden" name="logout" value="logout">
                        <button class="btn  w-100  text-center fw-medium bg-transparent text-white" type="submit" style="padding-top:7px">Logout</button>
                    </form>
                </li>
				
            </ul>
        </div>
    </nav>
	

	';
}

?>
