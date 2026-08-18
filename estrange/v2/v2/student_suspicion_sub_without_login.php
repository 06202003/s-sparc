<?php

	session_start();

	// if the SUSPICION id does not exist
	if(isset($_GET['id']) == false || $_GET['id'] == ''){
 		header('Location: index.php?invalidreport=true');
		exit;
	}

	include("_config.php");

	// escape sql injection
	$_GET['id'] = mysqli_real_escape_string($db,$_GET['id']);

	// check whether the suspicion id is actually exist
	$sql = "SELECT suspicion_id, marked_code, artificial_code, table_info, explanation_info FROM suspicion
		 WHERE public_suspicion_id = '".$_GET['id']."'";
	$result = mysqli_query($db,$sql);
	// if the result is zero, redirect to login
	if($result->num_rows == 0){
		header('Location: index.php?invalidreport=true');
		exit;
	}else{
		$row = $result->fetch_assoc();

		$_GET['id'] = $row['suspicion_id'];
		$markedCode = $row['marked_code'];
		$artificialCode = $row['artificial_code'];
		$tableInfo = $row['table_info'];
		$explanationInfo = $row['explanation_info'];
	}

	// check whether the suspicion id is listed to a course which the submitter enrolled to
	$sql = "SELECT assessment.name AS assessment_name, assessment.assessment_id, suspicion.is_overly_unique, 
		 course.name AS course_name, submission.submitter_id, submission.submission_id, course.course_id, suspicion.suspicion_type,
		 suspicion.efficiency_point  
		 FROM assessment INNER JOIN course ON course.course_id = assessment.course_id
		 INNER JOIN submission ON submission.assessment_id = assessment.assessment_id
		 INNER JOIN suspicion ON suspicion.submission_id = submission.submission_id
		 WHERE suspicion.suspicion_id = '".$_GET['id']."'";
	$result = mysqli_query($db,$sql);
	$row = $result->fetch_assoc();

	// if the given assessment id is not listed, redirect to login
	if(is_null($row)){
		header('Location: index.php?invalidreport=true');
		exit;
	}else{
		// set all temporary variables
		$myassessmentid = $row['assessment_id'];
		$myassessmentname = $row['assessment_name'];
		$mycoursename = $row['course_name'];
		$mysubmitterid = $row['submitter_id'];
		$submission_id = $row['submission_id'];
		$courseId = $row['course_id'];
		$suspicion_type = $row['suspicion_type'];
		$isOverlyUnique = $row['is_overly_unique'];
		$efficiencyPoint = $row['efficiency_point'];
	}
	recordAccess($db, $_GET['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Similarity &amp; Originality Report</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>

	<!-- jQuery -->
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

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
		
		
		
		.commentsim { background-color: rgba(254, 205, 211, 0.4); border-radius: 4px; padding: 1px 4px; }
		.syntaxsim { background-color: rgba(187, 247, 208, 0.4); border-radius: 4px; padding: 1px 4px; }
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
	</style>
	
	<script type="text/javascript">
		function loadGameNotif(){
			var notyf = new Notyf({
				duration: 0,
				position: { x: 'right', y: 'top' },
				dismissible: true
			});
			
			<?php
				$sqlt = "SELECT game_unobserved_notif.notification_id, game_unobserved_notif.message 
						FROM game_unobserved_notif 
						INNER JOIN game_student_course ON game_student_course.gs_id = game_unobserved_notif.gs_id 
						INNER JOIN game_course ON game_course.course_id = game_student_course.course_id 
						WHERE game_student_course.student_id = '".$mysubmitterid."' 
						AND game_student_course.course_id = '".$courseId."' 
						AND game_course.is_active = '1' 
						AND game_student_course.is_participating = '1' 
						ORDER BY game_unobserved_notif.time_created ASC
						LIMIT 3";
				$rt = mysqli_query($db,$sqlt);
				$i = 0;
				if ($rt) {
					while($row = $rt->fetch_assoc()) {
						echo "const notification".$i." = notyf.success(\"".addslashes($row['message'])."<br />Log in for details!\");
							  notification".$i.".on('click', ({target, event}) => {window.location.href = 'index.php';});";
						$sql = "DELETE FROM game_unobserved_notif WHERE notification_id = '".$row['notification_id']."'";
						$db->query($sql);
						$i++;
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

		function showOriginalityModal() {
			Swal.fire({
				title: 'How Originality Degree is Calculated',
				html: `<div class="text-left text-xs text-slate-700 leading-relaxed space-y-3 p-2">
					<p>The Originality Degree is the proportion of code identified as structurally distinct from submissions previously uploaded by peers.</p>
					<p>A high originality degree indicates uniqueness relative to current course corpora, but does not definitively rule out all forms of misconduct.</p>
				</div>`,
				confirmButtonText: 'Understood',
				confirmButtonColor: '#0f172a',
				width: '550px'
			});
		}

		function showMisconductModal() {
			Swal.fire({
				title: 'Potential Similarity Factors',
				html: `<div class="text-left text-xs text-slate-700 leading-relaxed space-y-2 p-2 max-h-96 overflow-y-auto">
					<ol class="list-decimal list-inside space-y-1.5">
						<li>Discussing problem-solving approaches before implementing code independently.</li>
						<li>Reviewing code structure details collaboratively during development.</li>
						<li>Showing debugging snippets to peers for diagnostic guidance.</li>
						<li><span class="text-rose-700 font-semibold">[Inappropriate]</span> Requesting peers to rewrite problematic code segments.</li>
						<li><span class="text-rose-700 font-semibold">[Inappropriate]</span> Adapting early draft solutions created by other students.</li>
						<li><span class="text-rose-700 font-semibold">[Inappropriate]</span> Modifying peer code cosmetically to disguise structural similarity.</li>
						<li><span class="text-rose-700 font-semibold">[Inappropriate]</span> Incorporating external commercial or unauthorized code.</li>
						<li>Independent convergence on common idiomatic solutions.</li>
					</ol>
				</div>`,
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
				<h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">
					<?= ($suspicion_type == 'real') ? 'Similarity &amp; Originality Report' : ($isOverlyUnique ? 'Originality Simulation: Highly Unique' : 'Originality Simulation') ?>
				</h1>
			</div>
			<div>
				<?php setHeaderReport("originality", $submission_id, $db); ?>
			</div>
		</div>
	</header>

	<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
		
		<!-- Context Info Strip -->
		<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 flex flex-wrap items-center justify-between gap-4 text-xs">
			<?php
				$sqlt = "SELECT username, name FROM user WHERE user_id = '".$mysubmitterid."'";
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
					<span class="font-semibold text-slate-800"><?= htmlspecialchars($mycoursename) ?></span>
				</div>
				<div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
				<div>
					<span class="text-slate-400 uppercase tracking-wider font-bold text-[11px] block">Assessment</span>
					<span class="font-semibold text-slate-800"><?= htmlspecialchars($myassessmentname) ?></span>
				</div>
			</div>
			<div class="flex items-center gap-2">
				<button type="button" onclick="showOriginalityModal()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl transition shadow-2xs">
					Originality Metrics
				</button>
				<button type="button" onclick="showMisconductModal()" class="px-3 py-1.5 bg-[#00A0A5] hover:bg-[#008488] text-white font-semibold rounded-xl transition shadow-xs">
					Similarity Factors
				</button>
			</div>
		</div>

		<!-- Main 2-Column Grid -->
		<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
			
			<!-- Left Column: Source Code View (6 cols) -->
			<div class="lg:col-span-6 bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col" style="height: calc(85vh - 120px);">
				<div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
					<div class="flex items-center gap-2">
						<span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span>
						<span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span>
						<span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span>
						<span class="text-xs font-bold text-slate-700 ml-2">Highlighted Submission</span>
					</div>
					<span class="text-[11px] font-mono text-slate-400">Match Highlighting</span>
				</div>
				<div class="flex-1 overflow-auto p-4 bg-slate-900 text-slate-100 font-code text-xs leading-relaxed">
					<pre class="prettyprint linenums"><?= $markedCode; ?></pre>
				</div>
			</div>

			<!-- Right Column: Similar Contents & Comparison (6 cols) -->
			<div class="lg:col-span-6 space-y-6">
				
				<!-- Similar Contents Table -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
					<div>
						<h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-[11px] text-slate-500">Detected Similar Segments</h2>
						<p class="text-xs text-slate-500 mt-0.5">Click rows to cross-reference marked passages.</p>
					</div>

					<div class="overflow-x-auto border border-slate-200/80 rounded-xl">
						<table class="w-full text-left text-xs divide-y divide-slate-100">
							<thead>
								<tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
									<th class="py-2.5 px-3">ID</th>
									<th class="py-2.5 px-3">Similarity Type</th>
									<th class="py-2.5 px-3 text-center">Length</th>
									<th class="py-2.5 px-3 text-right">Warning Level</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-100">
								<?= $tableInfo; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Explanation Panel -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-3">
					<h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider text-slate-500">Similarity Diagnostic Explanation</h3>
					<div class="border border-slate-200/80 rounded-xl bg-slate-50 p-4 text-xs text-slate-600 leading-relaxed max-h-48 overflow-y-auto">
						<?= $explanationInfo; ?>
					</div>
				</div>

				<!-- Counterpart Example Code -->
				<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col">
					<div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
						<span class="text-xs font-bold text-slate-700">Code Counterpart Reference</span>
						<span class="text-[11px] font-mono text-slate-400">Comparison Fragment</span>
					</div>
					<div class="p-4 bg-slate-900 text-slate-100 font-code text-xs leading-relaxed max-h-48 overflow-y-auto">
						<div id="dg" class="block"><pre class="prettyprint linenums"></pre></div>
						<?= $artificialCode; ?>
					</div>
				</div>

			</div>

		</div>

	</main>
</body>
</html>
