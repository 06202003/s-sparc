<?php
	// if the suggestion id does not exist, redirect to login
	if(isset($_GET['id']) == false || $_GET['id'] == ''){
		header('Location: index.php');
		exit;
	}

	include("_config.php");
	session_start();

	// get all data required for this page
	$sqlt = "SELECT code_clarity_suggestion.suggestion_id,
		code_clarity_suggestion.marked_code, code_clarity_suggestion.table_info,
		code_clarity_suggestion.explanation_info, submission.submitter_id, 
		assessment.name AS assessment_name, assessment.submission_file_extension, course.name AS course_name, course.course_id AS course_id, submission.submission_id   
		FROM code_clarity_suggestion
		INNER JOIN submission ON submission.submission_id = code_clarity_suggestion.submission_id
		INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id
		INNER JOIN course ON course.course_id = assessment.course_id
		WHERE code_clarity_suggestion.public_suggestion_id = '".$_GET['id']."'";
	$resultt = mysqli_query($db,$sqlt);
	$rowt = $resultt->fetch_assoc();

	// if the public suggestion id is invalid, redirect to login
	if(is_null($rowt)){
		header('Location: index.php');
		exit;
	}

	$markedCode = $rowt['marked_code'];
	$tableInfo = $rowt['table_info'];
	$explanationInfo = $rowt['explanation_info'];
	$submitter_id = $rowt['submitter_id'];
	$course_id = $rowt['course_id'];
	$assessment_name =  $rowt['assessment_name'];
	$course_name =  $rowt['course_name'];
	$submission_id = $rowt['submission_id'];
	$file_extension = $rowt['submission_file_extension'];
	$file_extension = str_replace('zip_', '', $file_extension); // remove prefix 'zip_' if any
	
	// for access statistics of suggestion page
	$sql = "INSERT INTO suggestion_access (suggestion_id) VALUES ('".$rowt['suggestion_id']."')";
	$db->query($sql);
	
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Code Quality Suggestion</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>

	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

	<!-- DataTables -->
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
	<script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

	<!-- SweetAlert2 -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

	<!-- Google Prettify -->
	<script src="strange_html_layout_additional_files/run_prettify.js"></script>

	<!-- Notyf library -->
	<link rel="stylesheet" href="strange_html_layout_additional_files/notyf.min.css">
	<script src="strange_html_layout_additional_files/notyf.min.js"></script>
	
	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
		.font-code { font-family: 'JetBrains Mono', monospace; }
		/* VS Code Dark+ Code Editor Theme */
		pre.prettyprint, pre.code-editor {
			border: none !important;
			background: #0f172a !important;
			padding: 0.75rem 0 !important;
			margin: 0 !important;
			font-family: 'JetBrains Mono', 'Fira Code', ui-monospace, monospace !important;
			font-size: 13px !important;
			line-height: 1.6 !important;
		}
		.prettyprint ol.linenums, ol.linenums {
			padding-left: 0 !important;
			margin: 0 !important;
			list-style: none !important;
			list-style-type: none !important;
			counter-reset: linenumber;
		}
		.prettyprint ol.linenums li, ol.linenums li {
			background: transparent !important;
			background-color: transparent !important;
			color: #f8fafc !important;
			padding: 0 1rem 0 3.75rem !important;
			position: relative;
			white-space: pre-wrap;
			word-break: break-all;
			list-style: none !important;
			transition: background-color 0.15s ease;
		}
		.prettyprint ol.linenums li:hover, ol.linenums li:hover {
			background-color: rgba(51, 65, 85, 0.4) !important;
		}
		.prettyprint ol.linenums li::before, ol.linenums li::before {
			counter-increment: linenumber;
			content: counter(linenumber);
			position: absolute;
			left: 0;
			top: 0;
			bottom: 0;
			width: 2.75rem;
			padding-right: 0.75rem;
			text-align: right;
			color: #64748b !important;
			font-family: 'JetBrains Mono', ui-monospace, monospace !important;
			font-size: 11px;
			font-weight: 500;
			user-select: none;
			border-right: 1px solid #1e293b;
			background-color: #090d16 !important;
		}
		.prettyprint *, ol.linenums *, ol.linenums li * {
			background: transparent !important;
			background-color: transparent !important;
		}
		/* VS Code Dark+ Syntax Colors */
		.prettyprint .kwd { color: #38bdf8 !important; font-weight: 600; }
		.prettyprint .str { color: #34d399 !important; }
		.prettyprint .com { color: #64748b !important; font-style: italic; }
		.prettyprint .lit { color: #fbbf24 !important; }
		.prettyprint .pun { color: #cbd5e1 !important; }
		.prettyprint .typ { color: #a78bfa !important; font-weight: 600; }
		.prettyprint .pln { color: #f8fafc !important; }
		.prettyprint .atn { color: #f472b6 !important; }
		.prettyprint .atv { color: #38bdf8 !important; }
		.commentsim { background-color: rgba(254, 205, 211, 0.4); border-radius: 4px; padding: 1px 4px; }
		.syntaxsim { background-color: rgba(187, 247, 208, 0.4); border-radius: 4px; padding: 1px 4px; }
		.dataTables_wrapper .dataTables_length select,
		.dataTables_wrapper .dataTables_filter input {
			border: 1px solid #cbd5e1;
			border-radius: 0.5rem;
			padding: 0.35rem 0.6rem;
			font-size: 0.8rem;
			outline: none;
		}
		
		@media (max-width: 640px) {
			.dataTables_wrapper .dataTables_length,
			.dataTables_wrapper .dataTables_filter {
				float: none !important;
				text-align: left !important;
				margin-bottom: 0.75rem;
				width: 100%;
			}
			.dataTables_wrapper .dataTables_filter input {
				width: 100% !important;
				margin-left: 0 !important;
				margin-top: 0.25rem;
			}
			.dataTables_wrapper .dataTables_info,
			.dataTables_wrapper .dataTables_paginate {
				float: none !important;
				text-align: center !important;
				margin-top: 0.5rem;
				width: 100%;
			}
			.dataTables_wrapper .dataTables_paginate .paginate_button {
				padding: 0.25rem 0.5rem !important;
				font-size: 0.75rem !important;
			}
		}
		.dataTables_wrapper .dataTables_paginate .paginate_button.current {
			background: #0f172a !important;
			color: #ffffff !important;
			border-radius: 0.5rem;
			border: 1px solid #0f172a !important;
		}
	</style>
	
	<script type="text/javascript">
		function loadGameNotif(){
			var notyf = new Notyf({
				duration: 0,
				position: { x: 'right', y: 'top' },
				dismissible: true
			});
			
			<?php
				if(isset($_SESSION['role']) && $_SESSION['role'] == 'student'){
					$sqlt = "SELECT game_unobserved_notif.notification_id, game_unobserved_notif.message 
							FROM game_unobserved_notif 
							INNER JOIN game_student_course ON game_student_course.gs_id = game_unobserved_notif.gs_id 
							INNER JOIN game_course ON game_course.course_id = game_student_course.course_id 
							WHERE game_student_course.student_id = '".$submitter_id."' 
							AND game_student_course.course_id = '".$course_id."' 
							AND game_course.is_active = '1' 
							AND game_student_course.is_participating = '1' 
							ORDER BY game_unobserved_notif.time_created ASC
							LIMIT 3";
					$rt = mysqli_query($db,$sqlt);
					$i = 0;
					if ($rt) {
						while($row = $rt->fetch_assoc()) {
							echo "const notification".$i." = notyf.success(\"".addslashes($row['message'])."<br />Click here for details!\");
								  notification".$i.".on('click', ({target, event}) => {window.location.href = 'student_game.php?id=".$course_id."';});";
							$sql = "DELETE FROM game_unobserved_notif WHERE notification_id = '".$row['notification_id']."'";
							$db->query($sql);
							$i++;
						}
					}
				}
			?>
		}

		var selectedCodeFragmentId = null;
		var selectedTwice = false;
		
		function markSelectedWithoutChangingTableFocus(id, tableId) {
			if (selectedCodeFragmentId === id) {
				selectedTwice = !selectedTwice;
			} else {
				if (selectedCodeFragmentId !== null) {
					resetCurrentFocus();
				}
				selectedTwice = false;
			}
		
			var defaultColour = id.startsWith("c") ? "rgba(254,205,211,0.5)" : "rgba(187,247,208,0.5)";
			var highlightColour = id.startsWith("c") ? "rgba(251,146,60,0.8)" : "rgba(52,211,153,0.8)";
		
			var appliedColour = selectedTwice ? defaultColour : highlightColour;
			highlightElement(id + "a", appliedColour);
			highlightElement(id + "hr", appliedColour);
		
			var explanationElement = document.getElementById(id + "he");
			if (explanationElement) {
				explanationElement.style.display = "block";
			}
		
			selectedCodeFragmentId = selectedTwice ? null : id;
		}
		
		function resetCurrentFocus() {
			if (!selectedCodeFragmentId) return;
			var defaultColour = selectedCodeFragmentId.startsWith("c") ? "rgba(254,205,211,0.5)" : "rgba(187,247,208,0.5)";
			recolorCodeFragment(selectedCodeFragmentId + "a", defaultColour);
			recolorCodeFragment(selectedCodeFragmentId + "hr", defaultColour);
			var explanationElement = document.getElementById(selectedCodeFragmentId + "he");
			if (explanationElement) {
				explanationElement.style.display = "none";
			}
			selectedCodeFragmentId = null;
		}
		
		function highlightElement(id, color) {
			var element = document.getElementById(id);
			if (element) {
				element.style.backgroundColor = color;
				element.style.transition = "background-color 0.3s ease";
			}
		}
		
		function recolorCodeFragment(id, color) {
			var element = document.getElementById(id);
			if (element) {
				element.style.backgroundColor = color;
				element.style.transition = "background-color 0.3s ease";
			}
		}

		function showCalculationModal() {
			Swal.fire({
				title: 'How Quality Degree is Calculated',
				html: `<div class="text-left text-xs text-slate-700 leading-relaxed space-y-3 p-2">
					<p>The Quality Degree represents the proportion of analyzed code blocks that exhibit zero code quality violations.</p>
					<p>Each code block spans approximately eight program statements. A high quality degree indicates structured syntax conformity, but does not evaluate variable or function semantic meaning.</p>
				</div>`,
				confirmButtonText: 'Understood',
				confirmButtonColor: '#0f172a',
				width: '550px'
			});
		}

		function showGuidelinesModal() {
			<?php
				$guideHtml = "<ol class='list-decimal list-inside space-y-1.5 text-xs text-slate-700 leading-relaxed text-left'>";
				$guideHtml .= "<li>Identifier names and comments should be at least three characters long.</li>";
				$guideHtml .= "<li>Identifier names should contain at least one meaningful word.</li>";
				$guideHtml .= "<li>Spellings in identifier names and comments should be correct.</li>";
				$guideHtml .= "<li>Use consistent casing (camelCase or snake_case).</li>";
				$guideHtml .= "<li>Provide clear comments preceding complex syntax blocks.</li>";
				$guideHtml .= "<li>Remove stale or obsolete commented-out code blocks.</li>";

				if ($file_extension == 'java') {
					$guideHtml .= "<li>Declare static variables first, followed by attributes, constructors, and methods.</li>";
					$guideHtml .= "<li>Keep each program line reasonably short.</li>";
					$guideHtml .= "<li>Place one statement per line.</li>";
					$guideHtml .= "<li>Compare Strings using .equals() or standard equality methods.</li>";
					$guideHtml .= "<li>Access non-static attributes with 'this'.</li>";
				} else if ($file_extension == 'py') {
					$guideHtml .= "<li>Keep lines under 80 characters for optimal readability.</li>";
					$guideHtml .= "<li>One statement per line; adhere to standard PEP8 indentations.</li>";
					$guideHtml .= "<li>Ensure space follows '#' in comments.</li>";
					$guideHtml .= "<li>Import only modules that are actively used.</li>";
				}
				$guideHtml .= "</ol>";
			?>
			Swal.fire({
				title: 'Code Quality Best Practices',
				html: `<div class="p-2 max-h-96 overflow-y-auto"><?= addslashes($guideHtml) ?></div>`,
				confirmButtonText: 'Understood',
				confirmButtonColor: '#0f172a',
				width: '600px'
			});
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

	<!-- Top Navigation Header -->
	<header class="bg-white/80 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-40">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between">
			<div>
				<h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">Code Quality Suggestions</h1>
			</div>
			<div>
				<?php setHeaderReport("quality", $submission_id, $db); ?>
			</div>
		</div>
	</header>

	<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
		
		<!-- Context Info Strip -->
		<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 flex flex-wrap items-center justify-between gap-4 text-xs">
			<?php
				$sqlt = "SELECT username, name FROM user WHERE user_id = '".$submitter_id."'";
				$resultt = mysqli_query($db,$sqlt);
				$rowt = $resultt ? $resultt->fetch_assoc() : ['username' => 'N/A', 'name' => 'N/A'];
			?>
			<div class="flex flex-wrap items-center gap-6">
				<div>
					<span class="text-slate-400 uppercase tracking-wider font-bold text-[11px] block">Student</span>
					<span class="font-bold text-slate-900"><?= htmlspecialchars($rowt['username'] ?? 'N/A') ?> / <?= htmlspecialchars($rowt['name'] ?? 'N/A') ?></span>
				</div>
				<div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
				<div>
					<span class="text-slate-400 uppercase tracking-wider font-bold text-[11px] block">Course</span>
					<span class="font-semibold text-slate-800"><?= htmlspecialchars($course_name) ?></span>
				</div>
				<div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
				<div>
					<span class="text-slate-400 uppercase tracking-wider font-bold text-[11px] block">Assessment</span>
					<span class="font-semibold text-slate-800"><?= htmlspecialchars($assessment_name) ?></span>
				</div>
			</div>
			<div class="flex items-center gap-2">
				<button type="button" onclick="showCalculationModal()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition shadow-2xs">
					Calculation Rules
				</button>
				<button type="button" onclick="showGuidelinesModal()" class="px-3 py-1.5 bg-[#00A0A5] hover:bg-[#008488] text-white font-semibold rounded-xl transition shadow-xs">
					Quality Guidelines
				</button>
			</div>
		</div>

		<!-- Main 2-Column Grid -->
		<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
			
			<!-- Left Column: Source Code View (6 cols) -->
			<div class="lg:col-span-6 bg-slate-900 rounded-2xl border border-slate-800 shadow-xl overflow-hidden flex flex-col" style="height: calc(85vh - 120px);">
				<div class="px-4 py-3 bg-slate-800/90 border-b border-slate-700/80 flex items-center justify-between">
					<div class="flex items-center gap-2">
						<span class="w-3 h-3 rounded-full bg-rose-500/90 inline-block"></span>
						<span class="w-3 h-3 rounded-full bg-amber-500/90 inline-block"></span>
						<span class="w-3 h-3 rounded-full bg-emerald-500/90 inline-block"></span>
						<span class="ml-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-mono bg-slate-900 text-slate-200 border border-slate-700/60 shadow-2xs">
							<svg class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
							<span>Analyzed Code Source</span>
						</span>
					</div>
					<span class="text-[11px] font-mono text-slate-400">Syntax &amp; Style Markers</span>
				</div>
				<div class="flex-1 overflow-auto bg-slate-900 text-slate-100 font-code text-xs">
					<pre class="prettyprint linenums"><?= $markedCode; ?></pre>
				</div>
			</div>

			<!-- Right Column: Suggestions Table (6 cols) -->
			<div class="lg:col-span-6 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4" style="min-height: calc(85vh - 120px);">
				<div>
					<h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-[11px] text-slate-500">Representative Suggestions Per Issue</h2>
					<p class="text-xs text-slate-500 mt-0.5">Click suggestion IDs to highlight corresponding lines in the code viewer.</p>
				</div>

				<?php
					$explanations = [];
					preg_match_all('/<div class="explanationcontent" id="([^"]+)">(.+?)<\/div>/s', $explanationInfo, $matches, PREG_SET_ORDER);
					foreach ($matches as $match) {
						$id = strtoupper(str_replace("he", "", trim($match[1])));
						$id = sprintf("S%03d", intval(substr($id, 1)));
						$text = trim(strip_tags($match[2]));
						$explanations[$id] = $text;
					}
					
					$tableInfo = preg_replace_callback(
						'/<tr id="(.*?)".*?<td.*?>(S\d+)<\/a><\/td>.*?<td.*?>(.*?)<\/td>.*?<td.*?>(.*?)<\/td>.*?<td.*?>(.*?)<\/td>/s',
						function ($matches) use ($explanations) {
							$id = sprintf("S%03d", intval(substr($matches[2], 1)));
							$explanation = isset($explanations[$id]) ? $explanations[$id] : "Not specified";
							$formattedId = strtolower("S" . intval(substr($id, 1))) . "a";
							$formattedExplanation = nl2br(htmlspecialchars($explanation));
							$idNumber = "s" . intval(substr($matches[2], 1));
							return "<tr id='{$matches[1]}' class='hover:bg-slate-50/80 transition-colors' onclick=\"markSelectedWithoutChangingTableFocus('{$idNumber}','origtablecontent')\">
										<td class='py-2.5 px-2.5 font-mono font-bold text-indigo-600'><a href='#" . $formattedId . "' id='{$id}hl'>{$id}</a></td>
										<td class='py-2.5 px-2.5 font-medium text-slate-900'>{$matches[3]}</td>
										<td class='py-2.5 px-2.5 text-center font-mono text-slate-600'>{$matches[4]}</td>
										<td class='py-2.5 px-2.5 text-slate-700'>{$matches[5]}</td>
										<td class='py-2.5 px-2.5 text-slate-500 text-[11px] leading-relaxed'>{$formattedExplanation}</td>
									</tr>";
						},
						$tableInfo
					);
				?>

				<div class="overflow-x-auto">
					<table id="lecturerDashboardTable" class="w-full text-left text-xs" style="width:100%">
						<thead>
							<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
								<th class="py-2.5 px-2.5" style="width: 50px;">ID</th>
								<th class="py-2.5 px-2.5">Hint Text</th>
								<th class="py-2.5 px-2.5 text-center" style="width: 50px;">Line</th>
								<th class="py-2.5 px-2.5">Issue</th>
								<th class="py-2.5 px-2.5">Explanation</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							<?= $tableInfo; ?>
						</tbody>
					</table>
				</div>

			</div>

		</div>

	</main>

	<script>
		$(document).ready(function() {
			var table = $('#lecturerDashboardTable').DataTable({
				responsive: true,
				pageLength: 5,
				lengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
				language: { search: "_INPUT_", searchPlaceholder: "Search suggestions..." }
			});
			
			$('#lecturerDashboardTable tbody').on('click', 'a', function (e) {
				e.preventDefault();
				var row = $(this).closest('tr');
				if (row.hasClass('selected-row')) {
					row.removeClass('selected-row bg-indigo-50/80');
				} else {
					table.$('tr.selected-row').removeClass('selected-row bg-indigo-50/80');
					row.addClass('selected-row bg-indigo-50/80');
				}
				e.stopPropagation();
			});
		});
	</script>
</body>
</html>l>
