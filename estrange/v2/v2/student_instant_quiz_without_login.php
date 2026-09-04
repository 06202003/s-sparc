<?php
    date_default_timezone_set("Asia/Jakarta");
    session_start();
    // Feature quiz has been deprecated
    header('Location: index.php');
    exit;
	
	include("_config.php");
	
	if(isset($_SESSION["q_student_id"]) == false){
		header('Location: index.php');
		exit;
	}
	
	if($_SERVER["REQUEST_METHOD"] == "POST"){ 
		// for submitting response to a quiz
		$message = "";
		
		// get the data from form
		$response = mysqli_real_escape_string($db,$_POST['response']);
		
		// check whether the response is correct
		$isCorrect = 0;
		foreach ($_SESSION["q_answers"] as $answer){
			if($answer == $response){
				$isCorrect = 1;
			}
		}
		
		if($isCorrect){
			$message .="<div class='information'>";
			$message .=($human_language == 'en'? "Your response is correct!<br /><br/>": "Jawaban kamu benar!<br /><br/>");
		}
		else{
			$message .="<div class='warning'>";
			$message .=($human_language == 'en'? "Your response is incorrect!<br /><br/>": "Jawaban kamu salah!<br /><br/>");
		}
		$message .=($human_language == 'en'? "Question:<br/>".$_SESSION["q_question"]."<br /><br/>Expected response(s):<br/>": "Pertanyaan:<br/>".$_SESSION["q_question"]."<br /><br/>Jawaban:<br/>");
		
		// add the answers
		foreach($_SESSION["q_answers"] as $answer){
			$message .= ($answer . ",");
		}
		// remove the last comma
		$message = substr($message,0,strlen($message) - 1);
		
		$message .="</div>";
		
		// get last attempt
    	$sql = "SELECT response_time FROM instant_quiz_response_history
    		WHERE student_id = '".$_SESSION['user_id']."' ORDER BY response_time DESC LIMIT 1";
    	$result = mysqli_query($db,$sql);
    	
    	
    	$timeDiffLastSunday = 0; $timeDiffLastResponse = -1;
    	if ($result->num_rows > 0) {
    		$row = $result->fetch_assoc();
    
    		// check whether the user has responded to the quiz this week
    		$lastResponseTime = strtotime($row['response_time']);
    		$lastSundayTime = strtotime('last sunday');
    		$nowTime = strtotime('now');
    		
    		
    		$timeDiffLastSunday = ($nowTime - $lastSundayTime) / (60*60*24);
    		$timeDiffLastResponse = ($lastResponseTime - $lastSundayTime) / (60*60*24);
    	}
    	
    	if($timeDiffLastSunday < 7 && $timeDiffLastResponse < 0){
    		$sql = "INSERT INTO instant_quiz_response_history (student_id, question_id, is_correct)
    			 VALUES ('".$_SESSION['q_student_id']."', '".$_SESSION["q_id"]."', '".$isCorrect."')";
    		if ($db->query($sql) === TRUE) {
    			// if updated well, do nothing for now
    		} else {
    			echo "Error adding record: " . $db->error;
    		}
    	}
	}
	
	$studentId = $_SESSION['q_student_id'];

	// get number of correct attempts and incorrect ones
	$sql = "SELECT is_correct, COUNT(question_id)  AS tot FROM instant_quiz_response_history
		WHERE student_id = '".$studentId."' AND response_time > DATE_SUB(now(), INTERVAL 6 MONTH) GROUP BY is_correct";
	$result = mysqli_query($db,$sql);
	$correctAttempts = 0;
	$incorrectAttempts = 0;
	while($row = $result->fetch_assoc()) {
		$temp = $row['is_correct'];
		if($temp == 1){
			$correctAttempts = $row['tot'];
		}else{
			$incorrectAttempts = $row['tot'];
		}
	}
	
	// get last attempt
	$sql = "SELECT response_time FROM instant_quiz_response_history
		WHERE student_id = '".$studentId."' ORDER BY response_time DESC LIMIT 1";
	$result = mysqli_query($db,$sql);
	
	$timeDiffLastSunday = 0; $timeDiffLastResponse = -1;
	if ($result->num_rows > 0) {
		$row = $result->fetch_assoc();
		
		// check whether the user has responded to the quiz this week
		$lastResponseTime = strtotime($row['response_time']);
		$lastSundayTime = strtotime('last sunday');
		$nowTime = strtotime('now');
		
		
		$timeDiffLastSunday = ($nowTime - $lastSundayTime) / (60*60*24);
		$timeDiffLastResponse = ($lastResponseTime - $lastSundayTime) / (60*60*24);
	}
	
	$isValid = false;
	$hasQuestion = false;
	if($timeDiffLastSunday < 7 && $timeDiffLastResponse < 0){
		// if more than one week, set valid to take quiz
		$isValid = true;
		
		// get maximum ID
		$sql = "SELECT MAX(question_id) AS maks FROM instant_quiz_bank";
		$result = mysqli_query($db,$sql);	
		$row = $result->fetch_assoc();
		$maks = $row['maks'];
		
		$question = "";
		$answerOptions = array();
		$answers = array();
		
		if($maks != ""){
		    $hasQuestion = true;
    		while(true){
    			$questionID = rand(0,$maks)+1;
    			
    			$sqlt = "SELECT question, answer_options, answers FROM instant_quiz_bank WHERE question_id = " .$questionID;
    			$resultt = mysqli_query($db,$sqlt);	
    			if ($resultt->num_rows > 0) {
    				$rowt = $resultt->fetch_assoc();
    				$question = $rowt['question'];
    				$answerOptions = explode(",",$rowt['answer_options']);
    				$_SESSION["q_question"] = $question; // set in a session var
    				$_SESSION["q_answers"] = explode(",",$rowt['answers']); // set in a session var
    				$_SESSION["q_id"] = $questionID; // set in a session var
    				break;
    			}
    		}
		}
	}
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

		<title> E-STRANGE: Instant quiz</title>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
		<link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">

		<link rel="icon" href="strange_html_layout_additional_files/icon.png">
		<!-- The use of Notyf library https://github.com/caroso1222/notyf -->
		<link rel="stylesheet" href="strange_html_layout_additional_files/notyf.min.css">
		<script src="strange_html_layout_additional_files/notyf.min.js"></script>
		<script type="text/javascript">
			function loadGameNotif(){
				// Create an instance of Notyf
				var notyf = new Notyf({
				  duration: 5000,
				  position: {
					x: 'center',
					y: 'top',
				  },
				  dismissible: true
				});
				
	<?php
			
			 if(isset($_GET['submit'])){ // landed from submitting the code without login
				if($isValid == false){
					echo "notyf.success('Code submitted! Check your progress on instant quizzes!');";
				}else{
					echo "notyf.success('Code submitted! If interested, respond to the instant quiz below!');";
				}
			 }
		?>
			}
		</script>
		<style>
			@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300&display=swap');
			body {
			/* font-family: "Times New Roman", Times, serif; */
			font-family: 'Montserrat', sans-serif;
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

			button.actionbutton, a.actionbutton{
				float:right;
				margin-top:10px;
				margin-left:10px;
				padding: 6px 20px;
				height:30px;
			}
			a.actionbutton{
				height:18px;
				padding-top:8px;
				padding-bottom:4px;
				background-color: rgba(0,140,186,1);
				border: none;
				color: white;
				text-align: center;
				text-decoration: none;
				cursor: pointer;
			}

			<!-- copied and modified from https://www.w3schools.com/howto/howto_css_login_form.asp -->
			form{
				float:left;
				width:100%;
			}
			div.formbody{
				width:58%;
				height:50%;
				padding: 1%;
				margin-top:30px;
				margin-left:20%;
				margin-right:20%;
				border: 1px solid #dddddd;
				overflow-y: auto;
			}
			div.formrow {
				float:left;
				width:100%;
			}
			div.formrowaction{
				float:left;
				width:60%;
				margin-left:20%;
				margin-right:20%;
			}
			label, div.infolabel{
				float:left;
				width:20%;
				font-size: 16px;
				text-align: left;
				padding: 12px 20px;
				margin: 8px 0;
			}
			div.infovalue{
				float:right;
				width: 70%;
				border: 1px solid #ccc;
				box-sizing: border-box;
				padding: 12px 20px;
				margin: 8px 0;
				font-size: 16px;
			}
			div.infovalue{
				padding-top:14px;
				padding-bottom:10px;
				padding-left:0px;
				padding-right:0px;
				border: 0px;
				font-family: inherit;
				font-size: 16px;
			}
			textarea{
				margin-top:15px;
				font-family: inherit;
				font-size: inherit;
				resize: none;
			}

			div.information, div.warning{
				float:left;
				width:95%;
				font-size: 16px;
				font-weight:bold;
				text-align:left;
				color:green;
				margin-left:2%;
				margin-top:2%;
				margin-bottom:0%;
			}
			div.warning{
				color:red;
			}
			
			#asmt_desc{
				float:right;
				width: 70%;
				min-height: 50px;
				padding-left: 6px;
				padding-right: 6px;
				border: 1px solid #ccc;
				box-sizing: border-box;
				margin-top:10px;
				margin-bottom:8px;
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
  <body onload="loadGameNotif()">
		<div class="header">
			<img src="strange_html_layout_additional_files/logo.png" alt="logo" />
			<div class="headertitle">Instant quiz</div>
		</div>

		<div class="tab">
			<button class="tablinks" onclick="window.open('index.php', '_self');">Login</button>
		</div>

		<!-- copied and modified from https://www.w3schools.com/howto/howto_css_login_form.asp -->
		<form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
			<div class="formbody">
				<?php
					// if error message exist, show it
					if(isset($message) && $message != ""){
						// show the message
						echo $message;
						// unset afterward
						$message = "";
					}
				 ?>
				<div class="formrow">
					<div class="infolabel"><b><?php echo ($human_language == 'en'? "Correct attempts": "Jumlah benar") ?> :</b></div>
					<div class="infovalue"><?php echo $correctAttempts. " / ". ($incorrectAttempts+$correctAttempts); echo ($human_language == 'en'? " &nbsp;(past six months)": " &nbsp;(enam bulan terakhir)"); ?></div>
				</div>
				<?php 

				if($isValid == true && $hasQuestion == true){
					// show the question
					echo '	<div class="formrow">
								<label><b>'.($human_language == 'en'? "Question": "Pertanyaan").':</b></label>
								<div class="infovalue">'.$question.'</div>
							</div>
							<div class="formrow">
								<div class="infolabel"><b>'.($human_language == 'en'? "Answer options": "Pilihan jawaban").':</b></div>
								<div class="infovalue">';
					foreach ($answerOptions as $option){
						echo "<input type=\"radio\" name=\"response\" value=\"".$option."\" required />".$option."<br /><br />";
					}
						
					echo '		</div>
							</div>';
				}else if($isValid == true && $hasQuestion == false){
				    // no questions
				    if($human_language == 'en'){
						echo '<div class="formrow">
								<div class="infolabel"><b>Information:</b></div>
								<div class="infovalue">No questions are available for this course!</div>
							</div>';
					}else{
						echo '<div class="formrow">
								<div class="infolabel"><b>Informasi:</b></div>
								<div class="infovalue">Tidak ada pertanyaan yang tersedia untuk mata pelajaran ini!</div>
							</div>';
					}
				}else{
					// if not valid
					if($human_language == 'en'){
						echo '<div class="formrow">
								<div class="infolabel"><b>Information:</b></div>
								<div class="infovalue">You have participated in this week\'s quis. Kindly wait for next week\'s quiz!</div>
							</div>';
					}else{
						echo '<div class="formrow">
								<div class="infolabel"><b>Informasi:</b></div>
								<div class="infovalue">Kamu telah berpartisipasi dalam kuis minggu ini. Silakan menunggu kuis baru di minggu depan!</div>
							</div>';
					}
				}
				?>
			</div>
			<div class="formrowaction">
				<?php
					
					if($isValid == true){
						echo '<a href="index.php" class="actionbutton">Skip</a>';
						echo '<button class="actionbutton" type="submit">Submit</button>';
					}else{
						echo '<a href="index.php" class="actionbutton">Go to home</a>';
					}
				?>
				
				
			</div>
		</form>
		<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

  </body>
</html>
