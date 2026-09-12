<?php
	include("_nosessionchecker.php");
	include("_config.php");
	
	if($_SERVER["REQUEST_METHOD"] != "POST") {
		// first time landed to this page via link given from the invite student email

		// if the key does not exist, redirect to login
		if(isset($_GET['key']) == false || $_GET['key'] == ''){
			header('Location: index.php');
			exit;
		}

		// get the access key
		$access_key = mysqli_real_escape_string($db,$_GET['key']);

		// get username and user_id
		$sql = "SELECT email FROM invited_student
					WHERE access_key = '$access_key'";
		$result = mysqli_query($db,$sql);
		$count = mysqli_num_rows($result);
		if($count == 0) {
			// if no such key exists, redirect to dashboard
			header('Location: index.php');
		}

		// set the email
		$row = mysqli_fetch_array($result,MYSQLI_ASSOC);
		$myemail = $row['email'];
	
	}else{
		// copied and modified from https://www.tutorialspoint.com/php/php_mysql_login.htm
		// process the added data
		if(isset($_POST['uname']) == true){
		   // data sent from form
		   $myusername = mysqli_real_escape_string($db,$_POST['uname']);
		   $myname= mysqli_real_escape_string($db,$_POST['cname']);
		   $myemail = mysqli_real_escape_string($db,$_POST['email']);
		   $mypass = mysqli_real_escape_string($db,$_POST['pass']);
		   $mypassr = mysqli_real_escape_string($db,$_POST['passr']);
		   $access_key = mysqli_real_escape_string($db,$_POST['access_key']);
		   
		   

		   // to store the error message
		   $errorMessage = "";
		   
		   // checking the validity of email
		   $sql = "SELECT invitation_id FROM invited_student WHERE email = '".$myemail."' AND access_key = '".$access_key."'";
		   $result = mysqli_query($db,$sql);
		   $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
		   $count = mysqli_num_rows($result);
		   if($count == 0) {
		      // if no rows exist, it is not the invited one
		      $errorMessage .= "The email is not the invited one. Please contact your lecturer for help. <br />";
		   }else{
				$invitation_id = $row['invitation_id'];


			   // checking the validity of username
			   $sql = "SELECT user_id FROM user WHERE username = '$myusername'";
			   $result = mysqli_query($db,$sql);
			   $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
			   $count = mysqli_num_rows($result);
			   if($count > 0) {
				  // if at least one entry fetched, the username is not unique
				  $errorMessage .= "The username has been registered for another account. <br />";
				}

			   // checking the validity of password
			   if($mypass != $mypassr){
				 // if the retyped pass is not the same as the pass, error.
				 $errorMessage .= "The password is not retyped correctly. <br />";
			   }

			   // checking the validity of email
			   $sql = "SELECT user_id FROM user WHERE email = '$myemail'";
			   $result = mysqli_query($db,$sql);
			   $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
			   $count = mysqli_num_rows($result);
			   if($count > 0) {
				  // if at least one entry fetched, the email is not unique
				  $errorMessage .= "The email has been registered for another account. <br />";
			   }

			   // validate the length of the inputs
			   if(strlen($myusername) >= 30){
					$errorMessage .= "The username should be shorter or equal to 30 characters. <br />";
			   }
			   if(strlen($myname) >= 50){
					$errorMessage .= "The name should be shorter or equal to 50 characters. <br />";
			   }
			   if(strlen($myemail) >= 50){
					$errorMessage .= "The email should be shorter or equal to 50 characters. <br />";
			   }
				 
			   // validate the content of the username that should be alphanumeric without space
			   if(ctype_alnum($myusername) == false){
					$errorMessage .= "The username should contain only alphabets and/or numbers. <br />";
			   }
				 
			   // validate the content of the name that should be alphanumeric and space
			   if(preg_match('/^[a-z0-9 .\-]+$/i', $myname) == false){
					$errorMessage .= "The name should contain only alphabets, numbers, and/or space. <br />";
			   }
			   
			   // check max password length
			   if(strlen($mypass) >= 50){
				 $errorMessage .= "The password should be shorter or equal to 50 characters. <br />";
			   }
			   
			   // check min password length
			   if(strlen($mypass) < 8 && strlen($mypass) > 0){
				 $errorMessage .= "The password should be longer or equal to 8 characters. <br />";
			   }
			   
			   // if the course var is not set, ask the user to select at least one course
			   if(isset($_POST['courses']) == false){
					$errorMessage .= "Please select at least one course to be enrolled to. <br />";
			   }
			}

			 // if no error message
		     if($errorMessage == ""){
				// encrypt the password
				$mypass = password_hash($mypass, PASSWORD_DEFAULT);
				// remove the invited email from invited student list, given that an account has been created
				$sql = "DELETE FROM invited_student
						WHERE invitation_id = '".$invitation_id."'";
				mysqli_query($db,$sql);
				
				// add the entry
				$sql = "INSERT INTO user (username, password, name, email, role)
					 VALUES ('".$myusername."', '".$mypass."', '".$myname."', '".$myemail."', 'student')";
				if ($db->query($sql) === TRUE) {
					// get the user ID
					$sql = "SELECT user_id FROM user WHERE email = '".$myemail."'";
				    $result = mysqli_query($db,$sql);
				    $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
					
					// if updated well, enroll the courses
					foreach ($_POST['courses'] as $enrolledCourseId){
						$enrolledCourseId = mysqli_real_escape_string($db,$enrolledCourseId);
						$sql = "INSERT INTO enrollment (student_id, course_id) VALUES ('".$row['user_id']."','".$enrolledCourseId."')";
						mysqli_query($db,$sql);
					}
					
					// redirect to dashboard
					header('Location: index.php?update4=true');
					exit;
				} else {
					echo "Error adding record: " . $db->error;
				}
			 }
		 }
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invitee Registration - E-STRANGE</title>
  <link rel="icon" href="strange_html_layout_additional_files/icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root { color-scheme: light; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex items-center justify-center p-4">
  <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200/80 shadow-xl p-8 sm:p-10">
    <div class="flex items-center gap-3 mb-6">
      <div class="h-10 w-10 rounded-xl bg-[#00A0A5] text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
        ES
      </div>
      <div>
        <h1 class="text-base font-bold text-slate-900 tracking-tight">E-STRANGE</h1>
        <p class="text-[11px] text-slate-500 font-medium">Invited Student Account</p>
      </div>
    </div>

    <div class="mb-6">
      <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Complete Registration</h2>
      <p class="text-xs text-slate-500 mt-1">Set up your credentials and choose your invited courses.</p>
    </div>

    <?php if (isset($errorMessage) && $errorMessage != "") : ?>
      <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50/70 p-3.5 text-xs text-rose-700 flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div><?php echo $errorMessage; ?></div>
      </div>
    <?php endif; ?>

    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" class="space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5" for="email">Invited Email</label>
          <input 
            id="email" 
            name="email" 
            type="email" 
            value="<?php echo htmlspecialchars($myemail ?? ''); ?>" 
            required 
            readonly 
            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-600 cursor-not-allowed"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5" for="uname">Username</label>
          <input 
            id="uname" 
            name="uname" 
            type="text" 
            value="<?php echo htmlspecialchars($myusername ?? ''); ?>" 
            required 
            placeholder="e.g. user123"
            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition"
          />
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1.5" for="cname">Full Name</label>
        <input 
          id="cname" 
          name="cname" 
          type="text" 
          value="<?php echo htmlspecialchars($myname ?? ''); ?>" 
          required 
          placeholder="e.g. John Doe"
          class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5" for="pass">Password</label>
          <input 
            id="pass" 
            name="pass" 
            type="password" 
            required 
            placeholder="Min 8 characters"
            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1.5" for="passr">Confirm Password</label>
          <input 
            id="passr" 
            name="passr" 
            type="password" 
            required 
            placeholder="Retype password"
            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition"
          />
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1.5" for="courses">
          Invited Courses <span class="text-[11px] font-normal text-slate-400">(Hold Ctrl/Cmd to select multiple)</span>
        </label>
        <select 
          name="courses[]" 
          id="courses" 
          multiple 
          required 
          class="min-w-[200px] shrink-0 w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-sm font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition min-h-[100px]"
        >
          <?php
            $sql = "SELECT name, course_id FROM course WHERE enrollment_mode = 1";
            $result = mysqli_query($db, $sql);
            if ($result && $result->num_rows > 0) {
              while($row = $result->fetch_assoc()) {
                echo '<option class="py-1 px-2 rounded hover:bg-slate-100" value="'.$row['course_id'].'">'.htmlspecialchars($row['name']).'</option>';
              }
            } else {
              header('Location: index.php?nocoursesinvitee=true');
              exit;
            }
          ?>
        </select>
      </div>

      <input type="hidden" name="access_key" value="<?php echo htmlspecialchars($access_key); ?>">

      <div class="flex items-center gap-3 pt-2">
        <a 
          href="index.php" 
          class="w-1/3 py-2.5 px-4 bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 text-sm font-semibold rounded-xl transition text-center shadow-2xs"
        >
          Cancel
        </a>
        <button 
          type="submit" 
          class="w-2/3 py-2.5 px-4 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-semibold rounded-xl shadow-xs transition duration-150 flex items-center justify-center gap-2"
        >
          <span>Register Account</span>
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </button>
      </div>
    </form>
  </div>
</body>
</html>
