<?php
	include("_nosessionchecker.php");
	include("_config.php");

	if($_SERVER["REQUEST_METHOD"] != "POST") {
		// first time landed to this page via link given from the change password email

		// if the key does not exist, redirect to login
		if(isset($_GET['key']) == false || $_GET['key'] == ''){
			header('Location: index.php');
			exit;
		}

		// get the access key
		$access_key = mysqli_real_escape_string($db,$_GET['key']);

		// get username and user_id
		$sql = "SELECT user.user_id, user.username FROM user
						INNER JOIN password_request ON user.user_id = password_request.user_id
						WHERE password_request.access_key = '$access_key'";
		$result = mysqli_query($db,$sql);
		$count = mysqli_num_rows($result);
		if($count == 0) {
			// if no such key exists, redirect to dashboard
			header('Location: index.php');
		}

		// set user id and username
		$row = mysqli_fetch_array($result,MYSQLI_ASSOC);
		$myusername = $row['username'];
		$myuserid = $row['user_id'];

	}else{
		// if landed from this page's form
		// copied and modified from https://www.tutorialspoint.com/php/php_mysql_login.htm

   // data sent from form
   $mypass = mysqli_real_escape_string($db,$_POST['pass']);
   $mypassr = mysqli_real_escape_string($db,$_POST['passr']);
	 $myusername = mysqli_real_escape_string($db,$_POST['username']);
	 $myuserid = mysqli_real_escape_string($db,$_POST['userid']);

   // to store the error message
   $errorMessage = "";

   // checking the validity of password
   if($mypass != $mypassr){
     // if the retyped pass is not the same as the pass, error.
     $errorMessage .= "The password is not retyped correctly. <br />";
   }

   // if no error message
   if($errorMessage == ""){
		 // encrypt the password
		 $mypass = password_hash($mypass, PASSWORD_DEFAULT);
		 // update the password
		 $sql = "UPDATE user SET password='$mypass' WHERE user_id='$myuserid'";
      if ($db->query($sql) === TRUE) {
				// if updated well, delete the password request
				$sql = "DELETE FROM password_request
	      WHERE user_id = '$myuserid'";
	      mysqli_query($db,$sql);

        // and redirect to login
        header('Location: index.php?update2=true');
				exit;
      } else {
        echo "Error adding record: " . $db->error;
      }
   }
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password - E-STRANGE</title>
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
  <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200/80 shadow-xl p-8 sm:p-10">
    <div class="flex items-center gap-3 mb-6">
      <div class="h-10 w-10 rounded-xl bg-[#00A0A5] text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
        ES
      </div>
      <div>
        <h1 class="text-base font-bold text-slate-900 tracking-tight">E-STRANGE</h1>
        <p class="text-[11px] text-slate-500 font-medium">Account Security</p>
      </div>
    </div>

    <div class="mb-6">
      <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Set New Password</h2>
      <p class="text-xs text-slate-500 mt-1">Creating a new password for account <strong class="text-slate-800"><?php echo htmlspecialchars($myusername); ?></strong>.</p>
    </div>

    <?php if (isset($errorMessage) && $errorMessage != "") : ?>
      <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50/70 p-3.5 text-xs text-rose-700 flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div><?php echo $errorMessage; ?></div>
      </div>
    <?php endif; ?>

    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" class="space-y-4">
      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1.5" for="pass">New Password</label>
        <input 
          id="pass" 
          name="pass" 
          type="password" 
          required 
          placeholder="Enter new password"
          class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition"
        />
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1.5" for="passr">Confirm New Password</label>
        <input 
          id="passr" 
          name="passr" 
          type="password" 
          required 
          placeholder="Retype new password"
          class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition"
        />
      </div>

      <input type="hidden" name="username" value="<?php echo htmlspecialchars($myusername); ?>"/>
      <input type="hidden" name="userid" value="<?php echo htmlspecialchars($myuserid); ?>"/>

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
          <span>Update Password</span>
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </button>
      </div>
    </form>
  </div>
</body>
</html>
