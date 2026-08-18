<?php
	include("_sessionchecker.php");
	include("_config.php");

	if(isset($_POST['id']) == false){
		if(isset($_SESSION['user_suspicion_report_id']) == false){
			// does not have id? move to login (that will redirect to their respective dashboard)
			header('Location: index.php');
			exit;
		}else{
			// if there is a session var for this, set the id with that value
			$_POST['id'] = $_SESSION['user_suspicion_report_id'];
			$_POST['course_name'] = $_SESSION['user_suspicion_report_course_name'];
			$_POST['assessment_name'] = $_SESSION['user_suspicion_report_assessment_name'];
			if(isset($_SESSION['user_suspicion_report_assessment_mode']))
				$_POST['mode'] = $_SESSION['user_suspicion_report_assessment_mode'];
		}
	}else{
		// set the session value for id
		$_SESSION['user_suspicion_report_id'] = $_POST['id'];
		$_SESSION['user_suspicion_report_course_name'] = $_POST['course_name'];
		$_SESSION['user_suspicion_report_assessment_name'] = $_POST['assessment_name'];
		if(isset($_POST['mode']))
			$_SESSION['user_suspicion_report_assessment_mode'] = $_POST['mode'];
	}

	// redirect if not eligible
	if($_SESSION['role'] == 'student'){
		if(isset($_POST['mode']) == false){
			// student without mode? move to dashboard
			header('Location: student_dashboard.php');
			exit;
		}
	}else if($_SESSION['role'] == 'admin'){
		// or admin
		header('Location: admin_dashboard.php');
		exit;
	}

	// get all data required for this page
	$sqlt = "SELECT suspicion.suspicion_type, suspicion.marked_code, suspicion.artificial_code, suspicion.table_info, suspicion.explanation_info, suspicion.is_overly_unique, 
	    assessment.course_id, submission.submission_id,  submission.submitter_id, suspicion.efficiency_point      
		FROM suspicion
		INNER JOIN submission ON submission.submission_id = suspicion.submission_id
		INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id
		WHERE suspicion_id = '".$_POST['id']."'";
	$resultt = mysqli_query($db,$sqlt);
	$rowt = $resultt->fetch_assoc();

	$markedCode = $rowt['marked_code'];
	$artificialCode = $rowt['artificial_code'];
	$tableInfo = $rowt['table_info'];
	$explanationInfo = $rowt['explanation_info'];
	$suspicion_type = $rowt['suspicion_type'];
	$courseId = $rowt['course_id'];
	$submission_id = $rowt['submission_id'];
	$submitter_id = $rowt['submitter_id'];
	$isOverlyUnique = $rowt['is_overly_unique'];
	$efficiencyPoint = $rowt['efficiency_point'];
	
	// record access only if done by student
	if($_SESSION['role'] == 'student')
		recordAccess($db, $_POST['id'], $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: <?= ($suspicion_type == 'real' ? 'Similarity Report' : ($isOverlyUnique ? 'Similarity Simulation: Overly Unique' : 'Similarity Simulation')); ?></title>
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

	<!-- Google Prettify -->
	<script src="strange_html_layout_additional_files/run_prettify.js"></script>
	
	<!-- Notyf -->
	<link rel="stylesheet" href="strange_html_layout_additional_files/notyf.min.css">
	<script src="strange_html_layout_additional_files/notyf.min.js"></script>

	<script type="text/javascript">
		function loadGameNotif(){
			var notyf = new Notyf({
				duration: 0,
				position: { x: 'right', y: 'top' },
				dismissible: true
			});
			
			<?php
				if($_SESSION['role'] == 'student'){
					$sqlt = "SELECT game_unobserved_notif.notification_id, game_unobserved_notif.message 
							FROM game_unobserved_notif 
							INNER JOIN game_student_course ON game_student_course.gs_id = game_unobserved_notif.gs_id 
							INNER JOIN game_course ON game_course.course_id = game_student_course.course_id 
							WHERE game_student_course.student_id = '".$_SESSION['user_id']."' 
							AND game_student_course.course_id = '".$courseId."' 
							AND game_course.is_active = '1' 
							AND game_student_course.is_participating = '1' 
							ORDER BY game_unobserved_notif.time_created ASC
							LIMIT 3";
					$rt = mysqli_query($db,$sqlt);
					
					$i = 0;
					while($row = $rt->fetch_assoc()) {
						echo "const notification".$i." = notyf.success(\"".addslashes($row['message'])."<br />Click for details!\");
							  notification".$i.".on('click', ({target, event}) => {window.location.href = 'student_game.php?id=".$courseId."';});";
						
						$sql = "DELETE FROM game_unobserved_notif WHERE notification_id = '".$row['notification_id']."'";
						$db->query($sql);
						$i++;
					}
				}
			?>
		}

		function construct(){
			loadGameNotif();
		}

		var selectedCodeFragmentId = null;
		function markSelectedWithoutChangingTableFocus(id, tableId) {
			if (selectedCodeFragmentId === id) return;
			if (selectedCodeFragmentId !== null) resetCurrentFocus();

			let defaultColour = id.startsWith("c") ? "rgba(244,161,164,1)" : "rgba(101,244,104,1)";
			recolorCodeFragment(id + "a", defaultColour);
			recolorCodeFragment(id + "b", defaultColour);

			var heEl = document.getElementById(id + "he");
			if (heEl) heEl.style.display = "block";
			var gEl = document.getElementById(id + "g");
			if (gEl) gEl.style.display = "block";

			selectedCodeFragmentId = id;
		}

		function resetCurrentFocus(){
			if (selectedCodeFragmentId == null) return;

			var defaultColour = "";
			if (selectedCodeFragmentId.startsWith("c")){
				defaultColour = "rgba(244,211,214,1)";
			} else if (selectedCodeFragmentId.startsWith("s")){
				defaultColour = "rgba(171,244,174,1)";
			}

			var heEl = document.getElementById(selectedCodeFragmentId + "he");
			if (heEl) heEl.style.display = "none";
			var gEl = document.getElementById(selectedCodeFragmentId + "g");
			if (gEl) gEl.style.display = "none";

			recolorCodeFragment(selectedCodeFragmentId + "a", defaultColour);
			recolorCodeFragment(selectedCodeFragmentId + "b", defaultColour);

			selectedCodeFragmentId = null;
		}

		function recolorCodeFragment(id, defaultColour){
			var el = document.getElementById(id);
			if (el) el.style.backgroundColor = defaultColour;
			for (var i = 1; ; i++){
				var childId = id + i;
				var child = document.getElementById(childId);
				if (child == null) break;
				child.style.backgroundColor = defaultColour;
			}
		}
	</script>

	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
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
		.selected-row td {
			background-color: #f1f5f9 !important;
			font-weight: 600;
		}
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
		.commentsim { background-color: rgba(244,63,94,0.3) !important; color: #fecdd3 !important; }
		.syntaxsim { background-color: rgba(16,185,129,0.3) !important; color: #a7f3d0 !important; }
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
<body onload="construct()" class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200 text-slate-900 flex flex-col">
	<?php
		setHeaderReport("originality", $submission_id, $db);

		$sqlt = "SELECT username, name FROM user WHERE user_id = '".$submitter_id."'";
		$resultt = mysqli_query($db, $sqlt);
		$rowt = $resultt->fetch_assoc();
	?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							<?= ($suspicion_type == 'real' ? 'Originality Alert' : ($isOverlyUnique ? 'Simulation: Overly Unique' : 'Similarity Simulation')); ?>
						</span>
						<span class="text-xs font-semibold text-slate-500">
							<?= htmlspecialchars($_POST['course_name']); ?>
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($_POST['assessment_name']); ?> &mdash; Code Similarity Inspection</h1>
					<p class="text-xs text-slate-500 mt-1">
						Student Submitter: <span class="font-bold text-slate-800"><?= htmlspecialchars($rowt['username']); ?></span> 
						(<?= htmlspecialchars($rowt['name']); ?>) &bull;
						Efficiency Score: <span class="font-mono font-bold text-indigo-700"><?= htmlspecialchars($efficiencyPoint); ?> / 100</span>
					</p>
				</div>
				<div class="flex items-center gap-2 flex-wrap">
					<button type="button" onclick="document.getElementById('modal-efficiency').classList.remove('hidden')"
						class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold rounded-xl transition">
						Efficiency Info
					</button>
					<button type="button" onclick="document.getElementById('modal-why-alert').classList.remove('hidden')"
						class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold rounded-xl transition">
						Alert Reasons
					</button>
					<button type="button" onclick="document.getElementById('modal-misconduct-actions').classList.remove('hidden')"
						class="px-3 py-1.5 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl transition shadow-2xs">
						Action Explanations
					</button>
				</div>
			</div>

			<!-- Top Section: Table & Explanation Panel (2 columns) -->
			<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
				
				<!-- Similar Contents Table (7 cols) -->
				<div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-3">
					<div class="flex items-center justify-between">
						<h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Identified Similarity Blocks</h2>
						<span class="text-[11px] text-slate-400">Click a row to highlight counterpart in source code</span>
					</div>
					<div class="overflow-x-auto">
						<table id="lecturerDashboardTable" class="w-full text-left text-xs" style="width:100%">
							<thead>
								<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
									<th class="py-2.5 px-3">Block ID</th>
									<th class="py-2.5 px-3">Similarity Category</th>
									<th class="py-2.5 px-3 text-center">Token Length</th>
									<th class="py-2.5 px-3 text-right">Warning Severity</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-slate-100">
								<?= $tableInfo; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Similarity Explanation Panel (5 cols) -->
				<div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-col space-y-3">
					<h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Detailed Similarity Description</h2>
					<div class="flex-1 p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 leading-relaxed overflow-y-auto min-h-[160px]" style="max-height: 280px;">
						<?= $explanationInfo; ?>
					</div>
				</div>

			</div>

			<!-- Bottom Section: Side-by-Side Code Viewer -->
			<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
				
				<!-- Left Code Panel: Your Code (6 cols) -->
				<div class="lg:col-span-6 bg-slate-900 rounded-2xl border border-slate-800 shadow-xl flex flex-col overflow-hidden">
					<div class="px-4 py-3 bg-slate-800/90 border-b border-slate-700/80 flex items-center justify-between">
						<div class="flex items-center gap-2">
							<span class="w-3 h-3 rounded-full bg-rose-500/90 inline-block"></span>
							<span class="w-3 h-3 rounded-full bg-amber-500/90 inline-block"></span>
							<span class="w-3 h-3 rounded-full bg-emerald-500/90 inline-block"></span>
							<span class="ml-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-mono bg-slate-900 text-slate-200 border border-slate-700/60 shadow-2xs">
								<svg class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
								<span>Submitted Code</span>
							</span>
						</div>
						<span class="text-[11px] font-mono text-slate-400">Student Submission</span>
					</div>
					<div class="flex-1 overflow-auto bg-slate-900 text-slate-100" style="max-height: 60vh;">
						<pre class="prettyprint linenums"><?= $markedCode; ?></pre>
					</div>
				</div>

				<!-- Right Code Panel: Matched Peer Code (6 cols) -->
				<div class="lg:col-span-6 bg-slate-900 rounded-2xl border border-slate-800 shadow-xl flex flex-col overflow-hidden">
					<div class="px-4 py-3 bg-slate-800/90 border-b border-slate-700/80 flex items-center justify-between">
						<div class="flex items-center gap-2">
							<span class="w-3 h-3 rounded-full bg-rose-500/90 inline-block"></span>
							<span class="w-3 h-3 rounded-full bg-amber-500/90 inline-block"></span>
							<span class="w-3 h-3 rounded-full bg-emerald-500/90 inline-block"></span>
							<span class="ml-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-mono bg-slate-900 text-slate-200 border border-slate-700/60 shadow-2xs">
								<svg class="w-3.5 h-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
								<span>Matched Counterpart Fragment</span>
							</span>
						</div>
						<span class="text-[11px] font-mono text-slate-400">Peer Comparison Database</span>
					</div>
					<div class="flex-1 overflow-auto bg-slate-900 text-slate-100" style="max-height: 60vh;">
						<div id="dg" class="hidden"></div>
						<pre class="prettyprint linenums"><?= $artificialCode; ?></pre>
					</div>
				</div>

			</div>

		</div>
	</main>

	<!-- Modal: Efficiency Info -->
	<div id="modal-efficiency" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
		<div class="bg-white rounded-3xl border border-slate-200 shadow-xl max-w-lg w-full p-6 space-y-4">
			<div class="flex items-center justify-between border-b border-slate-100 pb-3">
				<h3 class="text-sm font-bold text-slate-900">Efficiency Metric Explanation</h3>
				<button type="button" onclick="document.getElementById('modal-efficiency').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
			</div>
			<p class="text-xs text-slate-600 leading-relaxed">
				Efficiency is calculated based on the submission token count and size compared to the cohort distribution for this assessment. Submissions exceeding typical structural bounds receive score adjustments to encourage concise, idiomatic code.
			</p>
			<div class="pt-2 text-right">
				<button type="button" onclick="document.getElementById('modal-efficiency').classList.add('hidden')"
					class="px-4 py-2 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl transition">
					Understood
				</button>
			</div>
		</div>
	</div>

	<!-- Modal: Why Alerted -->
	<div id="modal-why-alert" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
		<div class="bg-white rounded-3xl border border-slate-200 shadow-xl max-w-lg w-full p-6 space-y-4">
			<div class="flex items-center justify-between border-b border-slate-100 pb-3">
				<h3 class="text-sm font-bold text-slate-900">Why was this submission alerted?</h3>
				<button type="button" onclick="document.getElementById('modal-why-alert').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
			</div>
			<div class="text-xs text-slate-600 space-y-2 leading-relaxed">
				<p>The system identified non-trivial AST (Abstract Syntax Tree) and syntactic structural overlap with code previously submitted by other students or index repositories.</p>
				<p class="text-slate-400 text-[11px]">Note: High originality degree does not guarantee absolute absence of misconduct, nor does a flagged fragment imply intentional collusion without instructor verification.</p>
			</div>
			<div class="pt-2 text-right">
				<button type="button" onclick="document.getElementById('modal-why-alert').classList.add('hidden')"
					class="px-4 py-2 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl transition">
					Understood
				</button>
			</div>
		</div>
	</div>

	<!-- Modal: Misconduct Actions -->
	<div id="modal-misconduct-actions" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
		<div class="bg-white rounded-3xl border border-slate-200 shadow-xl max-w-xl w-full p-6 space-y-4">
			<div class="flex items-center justify-between border-b border-slate-100 pb-3">
				<h3 class="text-sm font-bold text-slate-900">Potential Academic Causes of Structural Overlap</h3>
				<button type="button" onclick="document.getElementById('modal-misconduct-actions').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
			</div>
			<ol class="space-y-1.5 text-xs text-slate-600 list-decimal list-inside max-h-72 overflow-y-auto pr-2 leading-relaxed">
				<li>Discussing problem approaches conceptually, then independently coding distinct solutions.</li>
				<li>Pair debugging troublesome sections and suggesting structural fixes.</li>
				<li><span class="text-rose-600 font-semibold">[Inappropriate]</span> Direct copy-pasting from unapproved generative AI tools.</li>
				<li><span class="text-rose-600 font-semibold">[Inappropriate]</span> Requesting peers to debug and refactor personal code solutions.</li>
				<li><span class="text-rose-600 font-semibold">[Inappropriate]</span> Adapting an early draft from a peer into a final deliverable.</li>
				<li><span class="text-rose-600 font-semibold">[Inappropriate]</span> Renaming variables or altering superficial layout of peer code.</li>
				<li><span class="text-rose-600 font-semibold">[Inappropriate]</span> Incorporating work without explicit permission or attribution.</li>
				<li><span class="text-rose-600 font-semibold">[Inappropriate]</span> Commercial acquisition of academic programming deliverables.</li>
				<li>Coincidental canonical implementation of textbook algorithms.</li>
			</ol>
			<div class="pt-2 text-right">
				<button type="button" onclick="document.getElementById('modal-misconduct-actions').classList.add('hidden')"
					class="px-4 py-2 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-bold rounded-xl transition">
					Understood
				</button>
			</div>
		</div>
	</div>

	<script>
		$(document).ready(function () {
			var table = new DataTable('#lecturerDashboardTable', {
				responsive: true,
				pageLength: 5,
				lengthMenu: [5, 10, 25],
				language: { search: "_INPUT_", searchPlaceholder: "Search blocks..." }
			});

			$('#lecturerDashboardTable tbody').on('click', 'tr', function (e) {
				var row = $(this);
				table.$('tr.selected-row').removeClass('selected-row');
				row.addClass('selected-row');
			});

			document.querySelectorAll("[id$='he'], [id$='g']").forEach(el => {
				el.style.display = "none";
			});

			document.querySelector("table tbody").addEventListener("click", function (event) {
				let clickedLink = event.target.closest("a");
				if (!clickedLink) return;

				setTimeout(function () {
					let href = clickedLink.getAttribute("href");
					if (href && href.startsWith("#")) {
						let nextLink = document.querySelector(href);
						if (nextLink) nextLink.click();
					}
				}, 50);
			});

			let firstRow = document.querySelector("table tbody tr:first-child a");
			if (firstRow) firstRow.click();
		});
	</script>
</body>
</html>