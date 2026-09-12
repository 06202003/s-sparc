<?php
	include("_sessionchecker.php");
	include("_config.php");
	
	// remove all old notifications (longer than three days)
	$sql = "DELETE FROM game_unobserved_notif WHERE  DATEDIFF(CURRENT_TIMESTAMP,time_created) > 3;";
	$db->query($sql);

	if($_SERVER["REQUEST_METHOD"] == "POST") {
		// for deleting a course
		if(isset($_POST['id']) == true){
			// course id
			$id = mysqli_real_escape_string($db,$_POST['id']);
			// delete that course data from game_course
			$sql = "DELETE FROM game_course WHERE course_id = '$id'";
			if ($db->query($sql) === TRUE) {
			  $sql = "DELETE FROM game_course WHERE course_id = '$id'";
			  if ($db->query($sql) === TRUE) {
				  // if removed well, redirect to dashboard
				  header('Location: lecturer_dashboard.php');
				  exit;
			  }else{
				echo "<script>alert('The course cannot be deleted since it either has been assigned with some assessments or has been enrolled by some students');</script>";
			  }
			} else {
			  echo "<script>alert('The course cannot be deleted since it either has been assigned with some assessments or has been enrolled by some students');</script>";
			}
		}
	}

	// Ambil ID Submission dari URL
	if (!isset($_GET['submission_id']) || !is_numeric($_GET['submission_id'])) {
		die("Error: ID Submission tidak valid.");
	}

	$submission_id = $_GET['submission_id'];
	$lecturer_id = $_SESSION['user_id'];

	function extractCodeRecursively(ZipArchive $zip, $prefix = '') {
		$combined_content = "";
		$temp_dir = sys_get_temp_dir(); // Direktori sementara sistem

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry_name = $zip->getNameIndex($i); 

			// Cek apakah ini file ZIP lain di dalamnya
			if (strtolower(pathinfo($entry_name, PATHINFO_EXTENSION)) === 'zip') {
				$temp_zip_path = tempnam($temp_dir, 'nested_zip_');
				if ($temp_zip_path === false) {
					$combined_content .= "--- Error: Failed to create temp file for nested zip: " . htmlspecialchars(basename($entry_name)) . " ---\n";
					continue; // Lewati jika gagal buat file temp
				}

				$zip_content = $zip->getFromIndex($i);
				if ($zip_content !== false && file_put_contents($temp_zip_path, $zip_content) !== false) {
					$inner_zip = new ZipArchive;
					if ($inner_zip->open($temp_zip_path) === TRUE) {
						// Panggil fungsi ini lagi secara rekursif
						$combined_content .= "--- ZIP: " . htmlspecialchars(basename($entry_name)) . " ---\n\n";
						$combined_content .= extractCodeRecursively($inner_zip, $entry_name . '/'); 
						$inner_zip->close();
						$combined_content .= "--- ZIP: " . htmlspecialchars(basename($entry_name)) . " ---\n\n";
					} else {
						$combined_content .= "--- Error: Failed to open nested zip: " . htmlspecialchars(basename($entry_name)) . " ---\n";
					}
					unlink($temp_zip_path); // Hapus file sementara
				} else {
					$combined_content .= "--- Error: Failed to extract nested zip: " . htmlspecialchars(basename($entry_name)) . " ---\n";
					if (file_exists($temp_zip_path)) unlink($temp_zip_path);
				}
			} 
			// Cek apakah ini file kode biasa (bukan folder)
			elseif (substr($entry_name, -1) !== '/' && preg_match('/\.(py|java|c|cpp|h|cs|js|html|css|php|sql)$/i', $entry_name)) {
				$file_content = $zip->getFromIndex($i);
				if ($file_content !== false) {
					
					// Ambil hanya nama filenya saja, buang path folder
					$filename_only = basename($entry_name); 
					
					$combined_content .= "--- File: " . htmlspecialchars($filename_only) . " ---\n\n";
					$combined_content .= $file_content . "\n\n";
				}
			}
		}
		return $combined_content;
	}

	// Query untuk mendapatkan info assessment, course, file path, dan data mahasiswa yang direview
	$sql_info =
	"SELECT
		a.name as assessment_name,
		c.name as course_name,
		s.file_path,
		s.filename,
		reviewed.username AS reviewed_username,
		reviewed.name AS reviewed_name,
		c.creator_id 
	FROM
		submission s
	JOIN
		assessment a ON s.assessment_id = a.assessment_id
	JOIN
		course c ON a.course_id = c.course_id
	JOIN
		user reviewed ON s.submitter_id = reviewed.user_id
	LEFT JOIN
    	colecturer co ON c.course_id = co.course_id
	WHERE
		s.submission_id = ?
		AND (c.creator_id = ? OR co.user_id = ?)
	";

	$stmt_info = $db->prepare($sql_info);
	$stmt_info->bind_param("iii", $submission_id, $lecturer_id, $lecturer_id);
	$stmt_info->execute();
	$result_info = $stmt_info->get_result();

	if ($result_info->num_rows == 0) {
		die("Submission not found or access denied");
	}
	$submission_info = $result_info->fetch_assoc();
	$stmt_info->close();

	// Query mengambil ID review, skor, deskripsi, dan info reviewer
	$sql_reviews =
	"SELECT
		pr.peer_review_id,
		pr.review_score,
		pr.review_description
	FROM
		peer_review pr
	JOIN
		peer_review_submission prs ON pr.pr_submission_id = prs.pr_submission_id
	WHERE
		prs.submission_to_review = ?";

	$stmt_reviews = $db->prepare($sql_reviews);
	$stmt_reviews->bind_param("i", $submission_id);
	$stmt_reviews->execute();
	$result_reviews = $stmt_reviews->get_result();
	$reviews_list = $result_reviews->fetch_all(MYSQLI_ASSOC);
	$stmt_reviews->close();

	// Logika Pembacaan & Pemrosesan File
	$code_content_raw = ""; 
	$code_content = "Error: File submission not found"; 
	$file_path_on_server = $submission_info['file_path']; // Path ke file .code
	$original_filename = $submission_info['filename'];    // Nama file asli

	// Periksa apakah file .code ada
	if (isset($file_path_on_server) && file_exists($file_path_on_server)) {
		
		// Cek ekstensi file asli
		$file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
	
		// JIKA FILE ASLI ADALAH ZIP 
		if (strpos($file_extension, 'zip') === 0) {
			$zip = new ZipArchive;
			if ($zip->open($file_path_on_server) === TRUE) {
				// Panggil fungsi rekursif
				$code_content_raw = extractCodeRecursively($zip); 
				$zip->close();
			} else {
				$code_content_raw = "Error: Failed to open ZIP file";
			}
		} 
		// JIKA BUKAN ZIP (dianggap file teks biasa)
		else {
			$code_content_raw = file_get_contents($file_path_on_server);
		}

		// Pemrosesan Konten (Trim Baris Kosong)
		if (!empty($code_content_raw)) {
			// Menghapus SEMUA baris kosong:
			$code_content = preg_replace('/^\s*\R/m', '', $code_content_raw);
		} else {
			$code_content = $code_content_raw; // Gunakan kode yang sudah diproses untuk ditampilkan
		}

	} else {
		$code_content = "Error: File submission '" . htmlspecialchars($file_path_on_server ?? 'N/A') . "' not found in the server";
	}

	// Ambil pr_assessment_id yang terkait dengan salah satu review (jika ada)
	$pr_assessment_id_for_back = null;
	if (!empty($reviews_list)) {
		$sql_get_assessment_id = "SELECT prs.pr_assessment_id
								FROM peer_review pr
								JOIN peer_review_submission prs ON pr.pr_submission_id = prs.pr_submission_id
								WHERE pr.peer_review_id = ? LIMIT 1";
		$stmt_back = $db->prepare($sql_get_assessment_id);
		$stmt_back->bind_param("i", $reviews_list[0]['peer_review_id']); // Ambil dari review pertama
		$stmt_back->execute();
		$res_back = $stmt_back->get_result();
		if($res_back->num_rows > 0) {
			$pr_assessment_id_for_back = $res_back->fetch_assoc()['pr_assessment_id'];
		}
		$stmt_back->close();
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Lecturer Peer Review Details</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>
	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

	<!-- Google Code Prettify -->
	<script src="strange_html_layout_additional_files/run_prettify.js"></script>

	<style>
		:root { color-scheme: light; }
		body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
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
	<?php setHeaderReport("peer_review", $pr_assessment_id_for_back, $db); ?>

	<main class="flex-1 py-8">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
			
			<!-- Header Card -->
			<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 flex flex-wrap items-center justify-between gap-4">
				<div>
					<div class="flex items-center gap-2 mb-1">
						<span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-[#00A0A5] text-white">
							Review Report
						</span>
						<span class="text-xs font-semibold text-slate-500">
							<?= htmlspecialchars($submission_info['course_name'] ?? 'Course') ?>
						</span>
					</div>
					<h1 class="text-xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($submission_info['assessment_name'] ?? 'Assessment') ?> &mdash; Peer Review Evaluation</h1>
					<p class="text-xs text-slate-500 mt-1">
						Student Submitter: <span class="font-bold text-slate-800"><?= htmlspecialchars($submission_info['reviewed_username'] ?? '') ?></span> 
						(<?= htmlspecialchars($submission_info['reviewed_name'] ?? 'N/A') ?>) &bull;
						Artifact: <span class="font-mono font-semibold text-slate-700"><?= htmlspecialchars($submission_info['filename'] ?? '') ?></span>
					</p>
				</div>
				<div class="flex items-center gap-2">
					<a href="lecturer_peer_review_list.php?id=<?= htmlspecialchars($pr_assessment_id_for_back ?? '') ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
						<span>Back to Tasks</span>
					</a>
				</div>
			</div>

			<!-- 2-Column Split -->
			<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
				
				<!-- Left Column: Source Code Viewer (7 cols) -->
				<div class="lg:col-span-7 bg-slate-900 rounded-2xl border border-slate-800 shadow-xl flex flex-col overflow-hidden">
					<div class="px-4 py-3 bg-slate-800/90 border-b border-slate-700/80 flex items-center justify-between">
						<div class="flex items-center gap-2">
							<span class="w-3 h-3 rounded-full bg-rose-500/90 inline-block"></span>
							<span class="w-3 h-3 rounded-full bg-amber-500/90 inline-block"></span>
							<span class="w-3 h-3 rounded-full bg-emerald-500/90 inline-block"></span>
							<span class="ml-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-mono bg-slate-900 text-slate-200 border border-slate-700/60 shadow-2xs">
								<svg class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
								<span>Submitted Source Code</span>
							</span>
						</div>
						<span class="text-[11px] font-mono text-slate-400"><?= htmlspecialchars($original_filename ?? '') ?></span>
					</div>
					<div class="flex-1 overflow-auto bg-slate-900 text-slate-100" style="max-height: 70vh;">
						<pre class="prettyprint linenums"><code><?= htmlspecialchars($code_content); ?></code></pre>
					</div>
				</div>

				<!-- Right Column: Received Reviews & Description (5 cols) -->
				<div class="lg:col-span-5 space-y-6">
					
					<!-- Received Reviews Table -->
					<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-3">
						<div class="flex items-center justify-between">
							<h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Peer Reviews Received (<?= count($reviews_list) ?>)</h2>
							<span class="text-[11px] text-slate-400">Click a row to inspect feedback</span>
						</div>
						<div class="overflow-x-auto">
							<table id="review-table" class="w-full text-left text-xs">
								<thead>
									<tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-bold text-[11px]">
										<th class="py-2.5 px-3" style="width: 40%;">Review ID</th>
										<th class="py-2.5 px-3 text-right" style="width: 60%;">Score (0-100)</th>
									</tr>
								</thead>
								<tbody class="divide-y divide-slate-100">
									<?php $review_counter = 1; ?>
									<?php foreach ($reviews_list as $review): ?>
										<tr class="hover:bg-slate-50 cursor-pointer transition-colors review-row"
											data-description="<?= htmlspecialchars($review['review_description'] ?? ''); ?>">
											<td class="py-3 px-3 font-mono font-bold text-slate-800">
												Review #<?= str_pad($review_counter++, 2, '0', STR_PAD_LEFT); ?>
											</td>
											<td class="py-3 px-3 text-right font-bold text-slate-900">
												<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-extrabold bg-slate-100 text-slate-800">
													<?= htmlspecialchars($review['review_score']); ?> / 100
												</span>
											</td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($reviews_list)): ?>
										<tr>
											<td colspan="2" class="py-4 px-3 text-center text-slate-400">No peer reviews recorded for this submission.</td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Detailed Feedback Box -->
					<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 space-y-2.5">
						<h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Reviewer Qualitative Feedback</h2>
						<div id="review-explanation-box" class="p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 min-h-[140px] leading-relaxed">
							<span class="text-slate-400 italic">Select a review from the table above to view the evaluator's critique, suggestions, and assessment remarks.</span>
						</div>
					</div>

				</div>

			</div>

		</div>
	</main>

	<script>
		$(document).ready(function() {
			const reviewTableRows = $('#review-table tbody tr.review-row');
			const explanationBox = $('#review-explanation-box');

			reviewTableRows.on('click', function() {
				reviewTableRows.removeClass('bg-indigo-50/80 font-bold');
				$(this).addClass('bg-indigo-50/80 font-bold');
				const description = $(this).data('description');
				if (description) {
					explanationBox.html(description.replace(/\n/g, '<br>'));
				} else {
					explanationBox.html('<span class="text-slate-400 italic">No textual feedback provided with this review score.</span>');
				}
			});

			// Auto select first row if exists
			if (reviewTableRows.length > 0) {
				reviewTableRows.first().trigger('click');
			}
		});
	</script>
</body>
</html>
