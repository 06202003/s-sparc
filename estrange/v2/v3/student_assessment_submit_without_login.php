<?php
	session_start();

	// if the assessment id does not exist, redirect to login
	$rawId = $_GET['id'] ?? $_POST['id'] ?? '';
	if (empty($rawId)) {
		header('Location: index.php');
		exit;
	}

	// redirect to main submit page if properly logged in as student
	$isLoggedInStudent = (!empty($_SESSION['name']) && !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student');
	if ($isLoggedInStudent) {
		header('Location: student_assessment_submit.php?id=' . urlencode($rawId));
		exit;
	}

	include("_config.php");
	include_once("_ai_quiz.php");

	// escape sql injection
	$safeId = mysqli_real_escape_string($db, $rawId);

	// check whether the assessment id is listed to a course and the submission is open / late allowed
	$sql = "SELECT assessment.name AS assessment_name, assessment.assessment_id, assessment.public_assessment_id, 
	               course.name AS course_name, assessment.description AS assessment_description, 
	               assessment.submission_file_extension AS ext 
	        FROM assessment 
	        INNER JOIN course ON course.course_id = assessment.course_id
	        WHERE (assessment.public_assessment_id = '$safeId' OR assessment.assessment_id = '$safeId')
	        AND (assessment.submission_close_time > CURRENT_TIMESTAMP OR assessment.allow_late_submission = '1' OR assessment.allow_late_submission = 1)
	        AND assessment.submission_open_time <= CURRENT_TIMESTAMP LIMIT 1";
	$result = mysqli_query($db, $sql);
	$row = ($result) ? $result->fetch_assoc() : null;

	// if the given assessment id is not submittable, redirect to index
	if (is_null($row)) {
		header('Location: index.php?status=closed');
		exit;
	}

	// set the temporary variables
	$publicAssessmentId = !empty($row['public_assessment_id']) ? $row['public_assessment_id'] : $row['assessment_id'];
	$numericAssessmentId = $row['assessment_id'];
	$_GET['id'] = $numericAssessmentId;
	$course_name = $row['course_name'];
	$assessment_name = $row['assessment_name'];
	$assessment_description = $row['assessment_description'];

	// Determine accepted file formats dynamically from lecturer setting
	$rawExt = strtolower(trim($row['ext'] ?? ''));
	$acceptAttr = '';
	$formatLabel = 'Any Code File';
	$allowedExts = [];

	if ($rawExt == 'java') {
		$formatLabel = 'Java Source File (.java)';
		$acceptAttr = '.java';
		$allowedExts = ['java'];
	} elseif ($rawExt == 'py') {
		$formatLabel = 'Python Source File (.py)';
		$acceptAttr = '.py';
		$allowedExts = ['py'];
	} elseif ($rawExt == 'zip_java') {
		$formatLabel = 'Java Project Archive (.zip)';
		$acceptAttr = '.zip';
		$allowedExts = ['zip'];
	} elseif ($rawExt == 'zip_py') {
		$formatLabel = 'Python Project Archive (.zip)';
		$acceptAttr = '.zip';
		$allowedExts = ['zip'];
	}

	// for generating random string
	function random_str(
	    $length,
	    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
	    $str = '';
	    $max = mb_strlen($keyspace, '8bit') - 1;
	    for ($i = 0; $i < $length; ++$i) {
	        $str .= $keyspace[rand(0, $max)];
	    }
	    return $str;
	}

	$errorMessage = "";

	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		if (isset($_FILES["code"])) {
			if ($_FILES["code"]["error"] > 0) {
				$errorMessage .= "Return Code: " . $_FILES["code"]["error"] . "<br />";
			} else {
				// get the data from form
				$myusername = mysqli_real_escape_string($db, $_POST['uname'] ?? '');
				$mypassword = mysqli_real_escape_string($db, $_POST['upass'] ?? '');
				$mydesc = mysqli_real_escape_string($db, $_POST['desc'] ?? '');

				// for checking username and password
				$sql = "SELECT user_id, password, role FROM user WHERE username = '$myusername' LIMIT 1";
				$result = mysqli_query($db, $sql);
				$uRow = ($result) ? mysqli_fetch_array($result, MYSQLI_ASSOC) : null;
				$count = ($result) ? mysqli_num_rows($result) : 0;

				$user_id = "";

				if ($count != 1 || !password_verify($mypassword, $uRow['password'])) {
					$errorMessage .= "The username and/or password are incorrect! <br />";
				} else {
					$user_id = $uRow['user_id'];
					if ($uRow['role'] != 'student') {
						$errorMessage .= "The username is not registered as a student! <br />";
					} else {
						// check enrollment
						$sql = "SELECT enrollment.course_id, enrollment.student_id
								FROM enrollment
								INNER JOIN course ON course.course_id = enrollment.course_id
								INNER JOIN assessment ON course.course_id = assessment.course_id
								WHERE enrollment.student_id = '$user_id'
								AND assessment.assessment_id = '$numericAssessmentId' LIMIT 1";
						$eResult = mysqli_query($db, $sql);
						$eCount = ($eResult) ? mysqli_num_rows($eResult) : 0;
						if ($eCount == 0) {
							$errorMessage .= "You are not enrolled in the course for this assessment! <br />";
						}
					}
				}

				if ($errorMessage == "") {
					// get the highest attempt
					$sqlt = "SELECT MAX(attempt) as max_att FROM submission
							 WHERE submitter_id = '$user_id' AND assessment_id = '$numericAssessmentId'";
					$resultt = mysqli_query($db, $sqlt);
					$rowt = ($resultt) ? $resultt->fetch_assoc() : null;
					$attempt = ((int)($rowt['max_att'] ?? 0) + 1);

					// metadata of uploaded file
					$raw_file_name = basename($_FILES['code']['name']);
					// Normalize spaces and special characters in filename to prevent submission failures
					$clean_file_name = preg_replace('/[^\w\.\-]/', '_', $raw_file_name);
					if (empty($clean_file_name) || $clean_file_name === '.') {
						$clean_file_name = 'submission_' . time() . '.code';
					}
					$file_name = mysqli_real_escape_string($db, $clean_file_name);
					$file_size = $_FILES['code']['size'];
					$file_tmp = $_FILES['code']['tmp_name'];
					$file_ext = strtolower(trim(pathinfo($raw_file_name, PATHINFO_EXTENSION)));

					if (strlen($file_name) >= 100) {
						$errorMessage .= "The file name should be shorter than or equal to 100 characters. <br />";
					}

					// file extension check
					$expectedExt = explode('_', $row['ext'])[0];
					if (!empty($allowedExts) && !in_array($file_ext, $allowedExts) && $file_ext != $expectedExt) {
						$errorMessage .= "The uploaded file's extension must be '." . $expectedExt . "' as required for this assessment! <br />";
					}

					if ($file_size > 5242880) {
						$errorMessage .= 'The file size must be lower than or equal to 5 MB.<br />';
					}

					if ($errorMessage == "") {
						$uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
						if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
							$errorMessage .= "The upload directory could not be created.";
						} else {
							$new_file_name = "uploads/" . microtime(true) . ".code";
							$new_file_path = __DIR__ . DIRECTORY_SEPARATOR . $new_file_name;
							while (file_exists($new_file_path)) {
								$counter = random_str(3);
								$new_file_name = "uploads/" . microtime(true) . $counter . ".code";
								$new_file_path = __DIR__ . DIRECTORY_SEPARATOR . $new_file_name;
							}

							$insertSql = "INSERT INTO submission (description, filename, file_path, attempt, submitter_id, assessment_id)
										  VALUES ('$mydesc', '$file_name', '$new_file_name', '$attempt', '$user_id', '$numericAssessmentId')";
							if ($db->query($insertSql) === TRUE) {
								$submissionId = $db->insert_id;
								move_uploaded_file($file_tmp, $new_file_path);
								ensure_submission_metrics($db, $submissionId);
								
								// Set session for immediate quiz if student wants to continue
								$_SESSION['user_id'] = $user_id;
								$_SESSION['name'] = $myusername;
								$_SESSION['role'] = 'student';
								create_submission_quiz($db, $submissionId, (int)$user_id);

								header('Location: student_instant_quiz.php?submission_id=' . $submissionId);
								exit;
							} else {
								$errorMessage .= "Database error: " . $db->error;
							}
						}
					}
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
	<title>E-STRANGE: Submit Assessment</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
	<!-- Tailwind CSS -->
	<script src="https://cdn.tailwindcss.com"></script>
	<!-- FontAwesome -->
	<link rel="stylesheet" href="assets/vendor/fontawesome/all.min.css" />
	<!-- SweetAlert2 -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
	</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col justify-between">
	
	<!-- Top Navigation Bar -->
	<header class="w-full bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-30 px-4 py-3 sm:px-6">
		<div class="max-w-4xl mx-auto flex items-center justify-between">
			<div class="flex items-center gap-3">
				<img src="strange_html_layout_additional_files/icon.png" alt="E-STRANGE Logo" class="w-8 h-8 rounded-lg shadow-xs" onerror="this.style.display='none'" />
				<div>
					<span class="text-sm font-bold text-slate-900 tracking-tight block">E-STRANGE</span>
					<span class="text-[10px] font-semibold text-[#00A0A5] tracking-wider uppercase block">Assessment Portal</span>
				</div>
			</div>
			<a href="index.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
				<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
				<span>Login to LMS</span>
			</a>
		</div>
	</header>

	<!-- Main Form Content -->
	<main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
		<div class="w-full max-w-xl bg-white rounded-2xl border border-slate-200/90 shadow-xl p-6 sm:p-8 space-y-6">
			
			<!-- Assessment Header Banner -->
			<div class="border-b border-slate-100 pb-4">
				<div class="flex items-center gap-2 mb-2">
					<span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
						Direct Submission
					</span>
					<span class="text-xs font-semibold text-teal-700 bg-teal-50 px-2 py-0.5 rounded-md border border-teal-100">
						<?= htmlspecialchars($formatLabel) ?>
					</span>
				</div>
				<h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug">
					<?= htmlspecialchars($assessment_name) ?>
				</h1>
				<p class="text-xs font-semibold text-slate-500 mt-1 flex items-center gap-1.5">
					<svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
					<span><?= htmlspecialchars($course_name) ?></span>
				</p>
			</div>

			<!-- Error Notification Banner -->
			<?php if (!empty($errorMessage)): ?>
				<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700 flex items-start gap-2.5 shadow-2xs">
					<svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
					<div class="leading-relaxed font-medium"><?= $errorMessage ?></div>
				</div>
			<?php endif; ?>

			<!-- Assessment Instructions -->
			<?php if (!empty($assessment_description)): ?>
				<div class="bg-slate-50 rounded-xl p-4 border border-slate-200/80">
					<span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">
						Instructions &amp; Problem Constraints
					</span>
					<div class="text-xs text-slate-700 leading-relaxed max-h-48 overflow-y-auto bg-white p-3 rounded-lg border border-slate-200/60 prose prose-slate max-w-none">
						<?= $assessment_description ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Submission Form -->
			<form action="<?= htmlentities($_SERVER['PHP_SELF']) . '?id=' . urlencode($publicAssessmentId); ?>" method="post" enctype="multipart/form-data" class="space-y-4">
				
				<!-- Student Credentials -->
				<div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
					<div>
						<label for="uname" class="block text-xs font-bold text-slate-700 mb-1">
							Student NRP / Username <span class="text-rose-500">*</span>
						</label>
						<input 
							type="text" 
							id="uname" 
							name="uname" 
							required 
							value="<?= htmlspecialchars($_POST['uname'] ?? '') ?>"
							placeholder="e.g. 2172001"
							class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-transparent transition"
						/>
					</div>
					<div>
						<label for="upass" class="block text-xs font-bold text-slate-700 mb-1">
							Password <span class="text-rose-500">*</span>
						</label>
						<input 
							type="password" 
							id="upass" 
							name="upass" 
							required 
							placeholder="Enter LMS password"
							class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-transparent transition"
						/>
					</div>
				</div>

				<!-- File Upload Field -->
				<div>
					<label for="code" class="block text-xs font-bold text-slate-700 mb-1">
						Upload Source Code File <span class="text-rose-500">*</span>
					</label>
					<div class="relative">
						<input 
							type="file" 
							id="code" 
							name="code" 
							accept="<?= htmlspecialchars($acceptAttr) ?>"
							required
							class="w-full text-xs font-semibold text-slate-600 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800 file:cursor-pointer border border-slate-200 rounded-xl bg-slate-50 p-2 cursor-pointer transition"
						/>
					</div>
					<div class="flex items-center justify-between text-[11px] text-slate-500 mt-1.5 px-0.5">
						<span>Format: <strong class="text-teal-700"><?= htmlspecialchars($formatLabel) ?></strong></span>
						<span>Max size: <strong>5 MB</strong></span>
					</div>
				</div>

				<!-- Notes / Description -->
				<div>
					<label for="desc" class="block text-xs font-bold text-slate-700 mb-1">
						Submission Notes <span class="text-slate-400 font-normal">(Optional)</span>
					</label>
					<textarea 
						id="desc" 
						name="desc" 
						rows="2" 
						placeholder="Add any execution notes or details..."
						class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-transparent transition resize-none"
					><?= htmlspecialchars($_POST['desc'] ?? '') ?></textarea>
				</div>

				<!-- Action Buttons -->
				<div class="pt-2">
					<button 
						type="submit" 
						class="w-full py-3 px-4 bg-[#00A0A5] hover:bg-[#008488] active:scale-[0.99] text-white text-xs sm:text-sm font-bold rounded-xl shadow-md transition duration-150 flex items-center justify-center gap-2"
					>
						<span>Submit Code &amp; Proceed to Verification</span>
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
					</button>
				</div>
			</form>

			<!-- Quality Assurance Tip -->
			<div class="border-t border-slate-100 pt-3 text-[11px] text-slate-500 flex items-center justify-between">
				<span>Powered by <strong>E-STRANGE</strong></span>
				<a href="https://youtu.be/iC3VT7QG2Dc" target="_blank" class="text-[#00A0A5] hover:underline font-semibold flex items-center gap-1">
					<span>User Tutorial Video</span>
					<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
				</a>
			</div>

		</div>
	</main>

	<!-- Footer -->
	<footer class="w-full text-center py-3 text-[11px] text-slate-400 border-t border-slate-200/60">
		&copy; 2026 E-STRANGE &bull; Smart Technology &amp; Engineering Faculty - Maranatha Christian University
	</footer>

</body>
</html>
