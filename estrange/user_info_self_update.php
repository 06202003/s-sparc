<?php
	include("_sessionchecker.php");
	include("_config.php");

	// copied and modified from https://www.tutorialspoint.com/php/php_mysql_login.htm
	if($_SERVER["REQUEST_METHOD"] == "POST") {
		// update the profile
		if(isset($_POST['uname']) == true){
			// data sent from form
			$myusername = mysqli_real_escape_string($db,$_POST['uname']);
			$myname= mysqli_real_escape_string($db,$_POST['cname']);
			$myemail = mysqli_real_escape_string($db,$_POST['email']);
			$mypass = mysqli_real_escape_string($db,$_POST['pass']);
			$mypassr = mysqli_real_escape_string($db,$_POST['passr']);
			$id = $_SESSION['user_id'];

			// to store the error message
			$errorMessage = "";

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


		   // checking the validity of username
		   $sql = "SELECT user_id FROM user WHERE username = '$myusername'";
		   $result = mysqli_query($db,$sql);
		   $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
		   $count = mysqli_num_rows($result);
		   if($count == 1 && $row['user_id'] != $id){
		       // if not the updated one, the username is not unique
		       $errorMessage .= "The username has been registered for another account. <br />";
		   }else if($count > 1) {
		      // if more than one entry fetched, the username is not unique
		      $errorMessage .= "The username has been registered for another account. <br />";
		    }

		   // checking the validity of password
		   if($mypass != $mypassr){
		     // if the retyped pass is not the same as the pass, error.
		     $errorMessage .= "The password is not retyped correctly. <br />";
		   }
		   
		   // check max password length
		   if(strlen($mypass) >= 50){
			 $errorMessage .= "The password should be shorter or equal to 50 characters. <br />";
		   }
		   
		   // check min password length
		   if(strlen($mypass) < 8 && strlen($mypass) > 0){
			 $errorMessage .= "The password should be longer or equal to 8 characters. <br />";
		   }

		   // checking the validity of email
		   $sql = "SELECT user_id FROM user WHERE email = '$myemail'";
		   $result = mysqli_query($db,$sql);
		   $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
		   $count = mysqli_num_rows($result);
		   if($count == 1 && $row['user_id'] != $id){
		       // if not the updated one, the email is not unique
		       $errorMessage .= "The email has been registered for another account. <br />";
		   }else if($count > 1) {
		      // if more than one entry fetched, the email is not unique
		      $errorMessage .= "The email has been registered for another account. <br />";
		    }

		   // if no error message
		   if($errorMessage == ""){
		      $sql = "";
		       if($mypass != ''){
		         // encrypt the password
		         $mypass = password_hash($mypass, PASSWORD_DEFAULT);
		         // if new pass is set, change the pass also
		         $sql = "UPDATE user SET username = '$myusername', name = '$myname', email = '$myemail', password='$mypass' WHERE user_id='$id'";
		       }else{
		         // otherwise, exclude it
		         $sql = "UPDATE user SET username = '$myusername', name = '$myname', email = '$myemail' WHERE user_id='$id'";
		       }
		      if ($db->query($sql) === TRUE) {
		        // if updated well, set the session and redirect to dashboard
						$_SESSION['username'] = $myusername;
						$_SESSION['name'] = $myname;
						if($_SESSION['role'] == 'admin'){
			        header('Location: admin_dashboard.php');
						} else if($_SESSION['role'] == 'lecturer'){
			        header('Location: lecturer_dashboard.php');
						} else if($_SESSION['role'] == 'student'){
			        header('Location: student_dashboard.php');
						}
						exit;
		      } else {
		        echo "Error updating record: " . $db->error;
		      }
		   }else{
		     // set the error message
		     $_SESSION['error_message'] = $errorMessage;
		     $_SESSION['temp_username'] = $myusername;
		     $_SESSION['temp_name'] = $myname;
		     $_SESSION['temp_email'] = $myemail;
		   }
		 }
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Update Personal Information</title>
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
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
	<?php
		if ($_SESSION['role'] == 'admin') {
			setHeaderAdmin("update personal information", "Account info");
		} else if ($_SESSION['role'] == 'lecturer') {
			setHeaderLecturer("update personal information", "Account info");
		} else if ($_SESSION['role'] == 'student') {
			setHeaderStudent("update personal information", "Account info");
		}

		$sql = "SELECT username, name, email FROM user WHERE user_id = '".$_SESSION['user_id']."'";
		$result = mysqli_query($db, $sql);
		$row = mysqli_fetch_array($result, MYSQLI_ASSOC);

		$cancel_url = "student_dashboard.php";
		if ($_SESSION['role'] == 'admin') {
			$cancel_url = "admin_dashboard.php";
		} else if ($_SESSION['role'] == 'lecturer') {
			$cancel_url = "lecturer_dashboard.php";
		}
	?>

	<main class="flex-1 py-10 flex items-center justify-center">
		<div class="max-w-2xl w-full mx-auto px-4 sm:px-6">
			
			<div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
				
				<!-- Header -->
				<div class="px-8 pt-8 pb-6 border-b border-slate-100 bg-slate-50/50">
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Account Management
						</span>
						<span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
							<?= htmlspecialchars($_SESSION['role']); ?>
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Personal Information</h1>
					<p class="text-xs text-slate-500 mt-1">Update your display name, contact email address, or secure authentication credentials.</p>
				</div>

				<!-- Form -->
				<form action="<?= htmlentities($_SERVER['PHP_SELF']); ?>" method="POST" class="p-8 space-y-6">
					
					<?php if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])): ?>
						<div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 space-y-1">
							<span class="font-bold block">Validation Errors:</span>
							<div><?= $_SESSION['error_message']; ?></div>
						</div>
						<?php
							$row['username'] = $_SESSION['temp_username'] ?? $row['username'];
							$row['name'] = $_SESSION['temp_name'] ?? $row['name'];
							$row['email'] = $_SESSION['temp_email'] ?? $row['email'];
							unset($_SESSION['error_message'], $_SESSION['temp_username'], $_SESSION['temp_name'], $_SESSION['temp_email']);
						?>
					<?php endif; ?>

					<!-- Username -->
					<div class="space-y-1.5">
						<label for="uname" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Username <span class="text-rose-500">*</span></label>
						<?php if ($_SESSION['role'] == 'admin'): ?>
							<input type="text" id="uname" name="uname" value="<?= htmlspecialchars($row['username']); ?>" required placeholder="Enter username"
								class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
						<?php else: ?>
							<input type="text" id="uname" name="uname" value="<?= htmlspecialchars($row['username']); ?>" required readonly
								class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 cursor-not-allowed">
							<p class="text-[11px] text-slate-400">Institutional usernames are managed by system administrators.</p>
						<?php endif; ?>
					</div>

					<!-- Full Name -->
					<div class="space-y-1.5">
						<label for="cname" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Full Name <span class="text-rose-500">*</span></label>
						<input type="text" id="cname" name="cname" value="<?= htmlspecialchars($row['name']); ?>" required placeholder="Enter full name"
							class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
					</div>

					<!-- Passwords -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div class="space-y-1.5">
							<label for="pass" class="block text-xs font-bold uppercase tracking-wider text-slate-700">New Password</label>
							<input type="password" id="pass" name="pass" placeholder="Leave empty if unchanged"
								class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono">
						</div>
						<div class="space-y-1.5">
							<label for="passr" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Confirm Password</label>
							<input type="password" id="passr" name="passr" placeholder="Retype new password"
								class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition font-mono">
						</div>
					</div>

					<!-- Email Address -->
					<div class="space-y-1.5">
						<label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Email Address <span class="text-rose-500">*</span></label>
						<?php if ($_SESSION['role'] == 'student'): ?>
							<input type="email" id="email" name="email" value="<?= htmlspecialchars($row['email']); ?>" required readonly
								class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 cursor-not-allowed">
							<p class="text-[11px] text-slate-400">Student primary email addresses are locked to academic domain rosters.</p>
						<?php else: ?>
							<input type="email" id="email" name="email" value="<?= htmlspecialchars($row['email']); ?>" required placeholder="Enter institutional email"
								class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:bg-white focus:border-slate-900 focus:ring-1 focus:ring-[#00A0A5] transition">
						<?php endif; ?>
					</div>

					<!-- Actions -->
					<div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
						<a href="<?= $cancel_url; ?>" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
							Cancel
						</a>
						<button type="submit" class="px-6 py-2.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl shadow-xs transition duration-150">
							Update Account
						</button>
					</div>

				</form>
			</div>

		</div>
	</main>
</body>
</html>
