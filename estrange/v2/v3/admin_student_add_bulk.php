<?php
	include("_sessionchecker.php");
	include("_config.php");

	// file handling copied and modified from https://stackoverflow.com/questions/5593473/how-to-upload-and-parse-a-csv-file-in-php
	if($_SERVER["REQUEST_METHOD"] == "POST") {
		if ( isset($_FILES["ufile"])) {
			 // if there was an error uploading the file
      if ($_FILES["ufile"]["error"] > 0) {
        echo "Return Code: " . $_FILES["ufile"]["error"] . "<br />";
      }
      else {
				// set error message
				$errorMessage = "";

				// open the file
				$file = fopen($_FILES["ufile"]["tmp_name"],"r");

				// exclude the first line as it contains only column headers
				if(!feof($file)){
					fgetcsv($file);
				}

				// to store the user data from uploaded csv
				$dataList = [];
				$lineCounter = 0;

				// check each remaining line
				while(!feof($file)){
  				$line = fgetcsv($file);

					// the last line read is always null and should be excluded
					if($line == null){
						break;
					}

					// store the values to temporary variables
					$username = trim($line[0]);
					$name = trim($line[1]);
					$password = trim($line[2]);
					$email = trim($line[3]);

					// checking username
					// empty check
					if($username == ""){
						$errorMessage .= ("Entry ". ($lineCounter+2) . ": the username should not be empty.<br />");
					}
					// uniqueness check
					$sql = "SELECT user_id FROM user WHERE username = '".$username."'";
					$result = mysqli_query($db,$sql);
	 		   	$row = mysqli_fetch_array($result,MYSQLI_ASSOC);
	 		   	$count = mysqli_num_rows($result);
	 		   	if($count > 0) {
	 		      // if at least one entry fetched, the username is not unique
	 		     	$errorMessage .= ("Entry ". ($lineCounter+2) . ": the username ". $username ." has been used by another user.<br />");
	 		    }else{
 					 // check from the added entries
 					 $isExist = false;
 					 for($i=0;$i<$lineCounter;$i++){
 						 if($username == $dataList[$i][0]){
 							 $isExist = true;
 							 break;
 						 }
 					 }
					 // if unique
 					 if($isExist){
 						 $errorMessage .= ("Entry ". ($lineCounter+2) . ": the username ".$username. " has been used in an earlier entry.<br />");
 					 }
 				 }

				 // checking name
				 // empty check
				 if($name == ""){
					 $errorMessage .= ("Entry ". ($lineCounter+2) . ": the name should not be empty.<br />");
				 }

				 // checking password
				 // empty check
				 if($password == ""){
					 $errorMessage .= ("Entry ". ($lineCounter+2) . ": the password should not be empty.<br />");
				 }


					// checking email
					// empty check
					if($email == ""){
						$errorMessage .= ("Entry ". ($lineCounter+2) . ": the email should not be empty.<br />");
					}
					// uniqueness check
					$sql = "SELECT user_id FROM user WHERE email = '".$email."'";
					$result = mysqli_query($db,$sql);
	 		   	$row = mysqli_fetch_array($result,MYSQLI_ASSOC);
	 		   	$count = mysqli_num_rows($result);
	 		   	if($count > 0) {
	 		      // if at least one entry fetched, the email is not unique
	 		     $errorMessage .= ("Entry ". ($lineCounter+2) . ": the email ".$email." has been used by another user.<br />");
				 }else{
					 // check from the added entries
					 $isExist = false;
					 for($i=0;$i<$lineCounter;$i++){
						 if($email == $dataList[$i][3]){
							 $isExist = true;
							 break;
						 }
					 }
					 // if unique
					 if($isExist){
						 $errorMessage .= ("Entry ". ($lineCounter+2) . ": the email ".$email." has been used in an earlier entry.<br />");
					 }
				 }
					// validate format
					if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
					  $errorMessage .= ("Entry ". ($lineCounter+2). ": the email ".$email." is not correctly written.<br />");
					}

					// check whether the length violates the max length
					if(strlen($username) >= 30){
	 					$errorMessage .= "Entry ". ($lineCounter+2). ": the username ".$username." should be shorter or equal to 30 characters. <br />";
		 			 }
					 if(strlen($name) >= 50){
						 $errorMessage .= "Entry ". ($lineCounter+2). ": the name ".$name." should be shorter or equal to 50 characters. <br />";
					 }
					 if(strlen($email) >= 50){
						 $errorMessage .= "Entry ". ($lineCounter+2). ": the email ".$email." should be shorter or equal to 50 characters. <br />";
					 }
					 
					 // validate the content of the username that should be alphanumeric without space
					 if(ctype_alnum($username) == false){
							$errorMessage .= "Entry ". ($lineCounter+2). ": the username ".$username." should contain only alphabets and/or numbers. <br />";
					 }
					 
					 // validate the content of the name that should be alphanumeric and space
					 if(preg_match('/^[a-z0-9 .\-]+$/i', $name) == false){
							$errorMessage .= "Entry ". ($lineCounter+2). ": the name ".$name." should contain only alphabets, numbers, and/or space. <br />";
					 }

					// create an array entry for this
					$dataList[$lineCounter] = $line;

					$lineCounter++;
  			}

				// close the file
				fclose($file);

				if($errorMessage == ""){
					// no error, proceed to storing the data
					for($i=0;$i<$lineCounter;$i++){
						// encrypt the password
		        $mypass = password_hash($dataList[$i][2], PASSWORD_DEFAULT);
		        // add the entry
		        $sql = "INSERT INTO user (username, password, name, email, role)
						 VALUES ('".$dataList[$i][0]."', '".$mypass."', '".$dataList[$i][1]."', '".$dataList[$i][3]."', 'student')";
			      if ($db->query($sql) === FALSE) {
			        echo "Error adding record: " . $db->error;
			      }
					}

					// redirect to dashboard
					header('Location: admin_students.php');
					exit;
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
	<title>E-STRANGE: Bulk Student Import</title>
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
	<?php setHeaderAdmin("students", "Add student"); ?>

	<main class="flex-1 py-10 flex items-center justify-center">
		<div class="max-w-xl w-full mx-auto px-4 sm:px-6">
			
			<div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
				
				<!-- Header -->
				<div class="px-8 pt-8 pb-6 border-b border-slate-100 bg-slate-50/50">
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Admin Governance
						</span>
						<span class="text-xs font-semibold text-slate-500">
							Batch Import
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight">Bulk Import Students</h1>
					<p class="text-xs text-slate-500 mt-1">Upload a standardized CSV spreadsheet to provision multiple student accounts at once.</p>
				</div>

				<!-- Form -->
				<form action="<?= htmlentities($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data" class="p-8 space-y-6">
					
					<?php if (isset($errorMessage) && $errorMessage != ""): ?>
						<div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 space-y-1">
							<span class="font-bold block">Validation Errors:</span>
							<div><?= $errorMessage ?></div>
						</div>
					<?php endif; ?>

					<!-- Template Download Card -->
					<div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex items-center justify-between gap-4">
						<div class="space-y-0.5">
							<div class="text-xs font-bold text-slate-900">Standard CSV Template</div>
							<p class="text-[11px] text-slate-500">Required format: Username, Full Name, Password, Email Address.</p>
						</div>
						<a href="bulk_template.csv" download class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition shadow-2xs shrink-0">
							<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
							<span>Download</span>
						</a>
					</div>

					<!-- File Upload Input -->
					<div class="space-y-2">
						<label for="ufile" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Select CSV File <span class="text-rose-500">*</span></label>
						<input type="file" id="ufile" name="ufile" accept=".csv" required
							class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 file:mr-4 file:py-1.5 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800 transition">
					</div>

					<!-- Actions -->
					<div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
						<a href="admin_students.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
							Cancel
						</a>
						<button type="submit" class="px-6 py-2.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl shadow-xs transition duration-150">
							Upload &amp; Process
						</button>
					</div>

				</form>
			</div>

		</div>
	</main>
</body>
</html>
