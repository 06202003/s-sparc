<?php
ob_start();
// should not be logged in
include("_nosessionchecker.php");
include("_config.php");

// function to generate a random pass
function random_str(
    $length,
    // the possible values
    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    $str = '';
    $max = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i) {
        $str .= $keyspace[rand(0, $max)];
    }
    return $str;
}

// copied and modified from https://www.tutorialspoint.com/php/php_mysql_login.htm
if($_SERVER["REQUEST_METHOD"] != "POST"){
	// first time landing
	$sql = "SELECT name, course_id FROM course
			WHERE enrollment_mode = 1";
	$result = mysqli_query($db,$sql);
	if ($result->num_rows == 0) {
		header('Location: index.php?nocoursesinvitee=true');
		exit;
	}
}else{
	$myemail = mysqli_real_escape_string($db,$_POST['email']);

	// check whether the combination is valid
	$sql = "SELECT invitation_id FROM invited_student WHERE email = '$myemail'";
	$result = mysqli_query($db,$sql);
	$count = mysqli_num_rows($result);
	if($count == 1) {

     // get invitation id
     $row = mysqli_fetch_array($result,MYSQLI_ASSOC);
     $invitationid = $row['invitation_id'];

     // set access key
     $access_key = '';
     while(true){
       // generate the key
       $access_key = random_str(5).microtime(true).random_str(5);

       // if such key is nonexistent, escape the loop
       $sql = "SELECT invitation_id FROM invited_student WHERE access_key = '$access_key'";
       $result = mysqli_query($db,$sql);
       $count = mysqli_num_rows($result);
       if($count == 0){
         break;
       }
     }

     // update invited student table with the key
     $sql = "UPDATE invited_student SET access_key = '".$access_key."'
				WHERE invitation_id = '".$invitationid."'";
     if ($db->query($sql) === TRUE) {
        // create and send the link email

        $registerlink = $baseDomainLink.'invite_student_acc.php?key='.$access_key;
        $to = $myemail;
		if($human_language == 'en'){
			$subject = "[E-STRANGE] Account registration request";
			$txt = "Hi!<br /><br />An account registration request for this email has been made. <br />Click <a href='".$registerlink."'>here</a> to register an account. <br /> <br />Thank you <br /><br /> E-STRANGE Team";
		}else{
			$subject = "[E-STRANGE] Permintaan registrasi akun";
			$txt = "Halo!<br /><br />Permintaan registrasi akun untuk email ini sudah berhasil diajukan. Akses <a href='".$registerlink."'>tautan ini</a> untuk meregister akun.<br /> <br />Terima kasih <br /><br /> E-STRANGE Team";
		}

        // send the email
        include("_phpmailerlib.php");
        sendEmail($to,$subject,$txt);

        // redirect page
        header('Location: index.php?update3=true');
        //exit;
    } else {
        echo "Error updating record: " . $db->error;
    }
   }else{
	   $_GET['err'] = true;
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
  <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200/80 shadow-xl p-8 sm:p-10">
    <div class="flex items-center gap-3 mb-6">
      <div class="h-10 w-10 rounded-xl bg-[#00A0A5] text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
        ES
      </div>
      <div>
        <h1 class="text-base font-bold text-slate-900 tracking-tight">E-STRANGE</h1>
        <p class="text-[11px] text-slate-500 font-medium">Invited Student Onboarding</p>
      </div>
    </div>

    <div class="mb-6">
      <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Invitee Registration</h2>
      <p class="text-xs text-slate-500 mt-1">Enter the email address invited by your lecturer to receive your registration link.</p>
    </div>

    <?php if (isset($_GET['err'])) : ?>
      <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50/70 p-3.5 text-xs text-rose-700 flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>This email is not on the invited list. Please contact your lecturer for assistance.</span>
      </div>
    <?php endif; ?>

    <form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" class="space-y-4">
      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1.5" for="email">Invited Email Address</label>
        <input 
          id="email" 
          name="email" 
          type="email" 
          required 
          placeholder="your.email@example.com"
          class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition"
        />
      </div>

      <button 
        type="submit" 
        class="w-full py-2.5 px-4 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-semibold rounded-xl shadow-xs transition duration-150 flex items-center justify-center gap-2"
      >
        <span>Send Registration Link</span>
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
      </button>
    </form>

    <div class="mt-6 pt-6 border-t border-slate-100 text-center text-xs text-slate-500">
      Already have an account? 
      <a href="index.php" class="font-semibold text-slate-900 hover:underline ml-1">Sign In &rarr;</a>
    </div>
  </div>
</body>
</html>
