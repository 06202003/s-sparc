<?php
    date_default_timezone_set("Asia/Jakarta");
    session_start();
    // Feature quiz has been deprecated
    header('Location: student_dashboard.php');
    exit;
	
	if($_SERVER["REQUEST_METHOD"] == "POST"){ 
		 if(isset($_POST['logout']) == true){
			 // for logout in any pages
			// remove all session variables
			session_unset();

			// destroy the session
			session_destroy();

			// redirect to home
			header('Location: index.php');
			exit;
		 }else{
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
    				 VALUES ('".$_SESSION['user_id']."', '".$_SESSION["q_id"]."', '".$isCorrect."')";
    			if ($db->query($sql) === TRUE) {
    				// if updated well, do nothing for now
    			} else {
    				echo "Error adding record: " . $db->error;
    			}
			}
		}
	
	}

	// part of sessionchecker pasted here due to unique behaviour of this page	
	if(isset($_SESSION['name']) == false){
		// redirect if it is not logged in
		header('Location: student_dashboard.php');
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
		  } else if ($_SESSION['role'] == 'lecturer'){
			header('Location: lecturer_dashboard.php');
		  } else if ($_SESSION['role'] == 'student'){
			header('Location: student_dashboard.php');
		  }
		  exit;
		}
	  }
	}

	
	
	$studentId = $_SESSION['user_id'];

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
		// if more than one week, and last response time is earlier than this week, set valid to take quiz
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
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Instant Quiz</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>

	<!-- Notyf library -->
	<link rel="stylesheet" href="strange_html_layout_additional_files/notyf.min.css">
	<script src="strange_html_layout_additional_files/notyf.min.js"></script>
	
	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
	</style>
	<script type="text/javascript">
		function loadGameNotif(){
			var notyf = new Notyf({
				duration: 5000,
				position: { x: 'center', y: 'top' },
				dismissible: true
			});
			
			<?php if(isset($_GET['submit'])): ?>
				<?php if($isValid == false): ?>
					notyf.success('Code submitted! Check your progress on instant quizzes!');
				<?php else: ?>
					notyf.success('Code submitted! Test your knowledge with the instant quiz below!');
				<?php endif; ?>
			<?php endif; ?>
		}
	</script>
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col" onload="loadGameNotif()">
	<?php setHeaderStudent("quiz", "Instant quiz"); ?>

	<main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
		<div class="w-full max-w-xl bg-white rounded-2xl border border-slate-200/80 shadow-xl p-8 sm:p-10 space-y-6">
			
			<div class="border-b border-slate-200 pb-4">
				<div class="flex items-center gap-2 mb-1">
					<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
						Instant Quiz
					</span>
					<span class="text-xs font-semibold text-slate-500">
						Weekly Knowledge Check
					</span>
				</div>
				<h1 class="text-xl font-bold text-slate-900 tracking-tight">Code Mastery Challenge</h1>
				<p class="text-xs text-slate-500 mt-1">Answer quick questions to reinforce software engineering, efficiency, and code clarity concepts.</p>
			</div>

			<!-- Score Banner -->
			<div class="bg-slate-50 rounded-xl p-4 border border-slate-200/80 flex items-center justify-between">
				<div>
					<span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Your 6-Month Record</span>
					<span class="text-sm font-bold text-slate-900"><?= $correctAttempts ?> Correct</span>
					<span class="text-xs text-slate-500 font-medium"> / <?= ($incorrectAttempts + $correctAttempts) ?> Attempts</span>
				</div>
				<div class="px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 shadow-2xs">
					Accuracy: <?= ($incorrectAttempts + $correctAttempts > 0) ? round(($correctAttempts / ($incorrectAttempts + $correctAttempts)) * 100) : 0 ?>%
				</div>
			</div>

			<?php if(isset($message) && $message != ""): ?>
				<div class="rounded-xl border p-4 text-xs leading-relaxed <?= $isCorrect ? 'border-emerald-200 bg-emerald-50/80 text-emerald-800' : 'border-rose-200 bg-rose-50/80 text-rose-800' ?>">
					<?= $message ?>
				</div>
				<?php $message = ""; ?>
			<?php endif; ?>

			<form action="<?= htmlentities($_SERVER['PHP_SELF']); ?>" method="post" class="space-y-5">
				<?php if($isValid == true && $hasQuestion == true): ?>
					<div class="space-y-4">
						<div class="p-4 bg-[#00A0A5] text-white rounded-xl space-y-2">
							<span class="text-[11px] font-bold uppercase tracking-wider text-emerald-400">Weekly Question</span>
							<p class="text-xs sm:text-sm font-medium leading-relaxed"><?= htmlspecialchars($question) ?></p>
						</div>

						<div>
							<label class="block text-xs font-semibold text-slate-700 mb-2">Select Your Answer:</label>
							<div class="space-y-2">
								<?php foreach ($answerOptions as $option): ?>
									<label class="flex items-center gap-3 p-3 bg-slate-50 hover:bg-slate-100/80 border border-slate-200 rounded-xl cursor-pointer transition text-xs font-medium text-slate-800">
										<input type="radio" name="response" value="<?= htmlspecialchars($option) ?>" required class="w-4 h-4 text-slate-900 focus:ring-[#00A0A5] border-slate-300">
										<span><?= htmlspecialchars($option) ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<div class="flex items-center gap-3 pt-2">
						<a 
							href="student_dashboard.php" 
							class="w-1/3 py-2.5 px-4 bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition text-center shadow-2xs"
						>
							Skip For Now
						</a>
						<button 
							type="submit" 
							class="w-2/3 py-2.5 px-4 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-semibold rounded-xl shadow-xs transition duration-150 flex items-center justify-center gap-2"
						>
							<span>Submit Answer</span>
							<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
						</button>
					</div>

				<?php elseif($isValid == true && $hasQuestion == false): ?>
					<div class="p-6 text-center rounded-xl bg-slate-50 border border-slate-200 space-y-2">
						<p class="text-xs font-semibold text-slate-700">No active quiz questions are available right now.</p>
						<p class="text-xs text-slate-500">Please check back next week for fresh challenges.</p>
					</div>
					<div class="pt-2">
						<a href="student_dashboard.php" class="block w-full py-2.5 px-4 bg-[#00A0A5] hover:bg-[#008488] text-white text-xs font-semibold rounded-xl text-center shadow-xs transition">
							Return to Dashboard
						</a>
					</div>

				<?php else: ?>
					<div class="p-6 text-center rounded-xl bg-slate-50 border border-slate-200 space-y-2">
						<p class="text-xs font-semibold text-slate-700">You have already completed this week's quiz challenge.</p>
						<p class="text-xs text-slate-500">A new quiz problem will become available next week.</p>
					</div>
					<div class="pt-2">
						<a href="student_dashboard.php" class="block w-full py-2.5 px-4 bg-[#00A0A5] hover:bg-[#008488] text-white text-xs font-semibold rounded-xl text-center shadow-xs transition">
							Return to Dashboard
						</a>
					</div>
				<?php endif; ?>
			</form>

		</div>
	</main>
</body>
</html>
