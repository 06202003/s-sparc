<?php
	 include("_config.php");
	 date_default_timezone_set('Asia/Jakarta');
	 echo "executed at : " . date("Y-m-d h:i:sa") . "\n";

   // get the oldest suspicion email request
   $sql = "SELECT user.name AS user_name, user.username AS user_username, assessment.name AS assessment_name, assessment.assessment_id, user.user_id, submission.submission_id,
          suspicion.public_suspicion_id, suspicion.suspicion_type, user.email,
          suspicion_email_request.request_id FROM suspicion_email_request
          INNER JOIN suspicion ON suspicion.suspicion_id = suspicion_email_request.suspicion_id
          INNER JOIN submission ON submission.submission_id = suspicion.submission_id
          INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id
          INNER JOIN user ON user.user_id = submission.submitter_id
          WHERE suspicion_email_request.time_created IN (SELECT MIN(time_created) FROM suspicion_email_request)";
   $result = mysqli_query($db,$sql);
   $count = mysqli_num_rows($result);
   // get the data
   if($count >= 1){

    $row = $result->fetch_assoc();
	$requestID = $row['request_id'];
    
	// create and send the link email
    $to = $row['email'];
	
	// check whether code clarity suggestion page exists
	$sqls = "SELECT suggestion_id FROM code_clarity_suggestion WHERE 
			submission_id = '".$row['submission_id']."'";
	$results = mysqli_query($db,$sqls);
	$counts = mysqli_num_rows($results);
	
	// set suggestion link if any
	// the public id is similar to that of suspicion
	$suggestionlink = null;
	if($counts == 1){
		$suggestionlink = $baseDomainLink.'student_code_clarity.php?id='.$row['public_suspicion_id'];
	}
	
	// check whether gamification is applied to the course
	$sqls = "SELECT game_course.course_id, game_course.prize_text, 
			game_student_course.is_participating, game_student_course.gs_id FROM game_course
			INNER JOIN assessment ON assessment.course_id = game_course.course_id
			INNER JOIN game_student_course ON game_student_course.course_id = game_course.course_id AND game_student_course.student_id = '".$row['user_id']."'
			WHERE assessment.assessment_id = '".$row['assessment_id']."' AND game_course.is_active = 1";
	$results = mysqli_query($db,$sqls);
	$counts = mysqli_num_rows($results);
	
	// set gamification message
	$gamificationMessage = null;
	if($counts == 1){
		// get collaboration score and student participation
		$rowt = $results->fetch_assoc();
		$isParticipating = $rowt['is_participating'];
		$gsId = $rowt['gs_id'];
		$courseId = $rowt['course_id'];
		$prizeText = $rowt['prize_text'];
		if(strlen($prizeText) == 0)
			$prizeText = "-";
				
		if($human_language == 'en'){
			// english text for gamification
			
			// check if the student is participating in the game for this course
			if($isParticipating == false){
				$gamificationMessage = "The course enables game feature but you have not enrolled to it yet. Please login to the website and visit game menu to activate the feature! You can participate or withdraw from the game feature at any time without losing any points.";
			}else{
				$gamificationMessage = "You are participating in the course's game feature. Please login to the website to see your progress!";
				
			}
		}else{
			// Indonesian text for gamification
			
			// check if the student is participating in the game for this course
			if($isParticipating == false){
				$gamificationMessage = "Mata kuliah terkait menghadirkan fitur gamifikasi tapi kamu belum terdaftar dalam fitur tersebut. Silakan login ke website dan kunjungi menu game untuk mengaktifkan fitur! Kamu dapat menyalakan atau mematikan fitur game kapan saja tanpa kehilangan poin.";
			}else{
				$gamificationMessage = "Kamu sedang berpartisipasi di fitur game mata kuliah terkait. Silakan login ke website untuk melihat progress!";
			}
		}
		
		// prize tect
		if($prizeText != '-')
			$gamificationMessage .= "<br />Game prize: ".$prizeText;
	}
	
    if($row['suspicion_type'] == 'real'){
      $link = $baseDomainLink.'student_suspicion_sub_without_login.php?id='.$row['public_suspicion_id'];
	  
	  if($human_language == 'en'){
		  $subject = "[E-STRANGE] Similarity report";
		  $txt = "Hi ".$row['user_name']." with username ".$row['user_username']."!<br /><br />Based on the latest submission data, your submission for an assessment entitled '".$row['assessment_name']."' seems to be similar to those of other colleagues! <br />Visit <a href='".$link."'>the similarity report page</a> to see the details and resubmit if necessary.<br /> <br />";

		  // add code clarity message
		  if($suggestionlink != null){
			$txt = $txt."According to our code quality guidelines, your code can be improved. Visit <a href='".$suggestionlink."'>the suggestion page</a> to see the details.<br /> <br />";
		  }else{
			$txt = $txt."According to our code quality guidelines, your code has a good quality.<br /> <br />";
		  }
		  
		  // add gamification message
		  if($gamificationMessage != null){
			 $txt = $txt.$gamificationMessage."<br /> <br />";
		  }
		  
		  $txt = $txt."Thank you <br /><br /> E-STRANGE Team";
	  }else{
		  $subject = "[E-STRANGE] Laporan kesamaan";
		  $txt = "Halo ".$row['user_name']." dengan akun ".$row['user_username']."!<br /><br />Berdasarkan jawaban siswa yang terkumpul pada server, jawaban kamu untuk tugas berjudul '".$row['assessment_name']."' tampak sama dengan milik beberapa siswa lain! <br />Kunjungi <a href='".$link."'>laman laporan kesamaan</a> untuk melihat detailnya dan mengirimkan jawaban yang baru jika perlu.<br /> <br />";

		  // add code clarity message
		  if($suggestionlink != null){
			$txt = $txt."Berdasarkan guideline kualitas kode kami, kode anda dapat ditingkatkan kualitasnya. Silakan kunjungi <a href='".$suggestionlink."'>laman rekomendasi</a> untuk melihat detailnya.<br /> <br />";
		  }else{
			$txt = $txt."Berdasarkan guideline kualitas kode kami, kode anda berkualitas..<br /> <br />";
		  }
		  
		  // add gamification message
		  if($gamificationMessage != null){
			 $txt = $txt.$gamificationMessage."<br /> <br />";
		  }
		  
		  $txt = $txt."Terima kasih <br /><br /> E-STRANGE Team";
	  }
    }else{
      $link = $baseDomainLink.'student_suspicion_sub_without_login.php?id='.$row['public_suspicion_id'];
	  
	  if($human_language == 'en'){
		  $subject = "[E-STRANGE] Similarity simulation";
		  $txt = "Hi ".$row['user_name']." with username ".$row['user_username']."!<br /><br />We are happy to inform you that your most recent submission for an assessment entitled '".$row['assessment_name']."' seems to be NOT similar to those of other colleagues! <br />Visit <a href='".$link."'>the simulation page</a> to see what kinds of code similarities can entail suspicion for plagiarism or collusion.<br /> <br />";

		  // add code clarity message
		  if($suggestionlink != null){
			$txt = $txt."According to our code quality guidelines, your code can be improved. Visit <a href='".$suggestionlink."'>the suggestion page</a> to see the details.<br /> <br />";
		  }else{
			$txt = $txt."According to our code quality guidelines, your code has a good quality.<br /> <br />";
		  }
		  
		  // add gamification message
		  if($gamificationMessage != null){
			 $txt = $txt.$gamificationMessage."<br /> <br />";
		  }
		  
		  $txt = $txt."Thank you <br /><br /> E-STRANGE Team";
	  }else{
		  $subject = "[E-STRANGE] Simulasi kesamaan";
		  $txt = "Halo ".$row['user_name']." dengan akun ".$row['user_username']."!<br /><br />Jawaban terakhir yang kamu kumpulkan untuk tugas berjudul '".$row['assessment_name']."' tampak tidak sama dengan milik siswa lainnya! <br />Kunjungi <a href='".$link."'>laman simulasi</a> untuk melihat kesamaan kode apa saja yang dapat menimbulkan kecurigaan plagiarisme atau kolusi.<br /> <br />";

		  // add code clarity message
		  if($suggestionlink != null){
			$txt = $txt."Berdasarkan guideline kualitas kode kami, kode anda dapat ditingkatkan kualitasnya. Silakan kunjungi <a href='".$suggestionlink."'>laman rekomendasi</a> untuk melihat detailnya.<br /> <br />";
		  }else{
			$txt = $txt."Berdasarkan guideline kualitas kode kami, kode anda berkualitas..<br /> <br />";
		  }
		  
		  // add gamification message
		  if($gamificationMessage != null){
			 $txt = $txt.$gamificationMessage."<br /> <br />";
		  }
		  
		  $txt = $txt."Terima kasih <br /><br /> E-STRANGE Team";

	  }
    }

	// send the email
	include("_phpmailerlib.php");
	sendEmail($to,$subject,$txt);

    // remove the selected suspicion email request
    $sqlt = "DELETE FROM suspicion_email_request
    WHERE request_id = '".$requestID."'";
    mysqli_query($db,$sqlt);
	
	echo "An email has been sent to ". $to . "\n";
	
   }else{
	   echo "No email requests to send" . "\n";
   }
?>
