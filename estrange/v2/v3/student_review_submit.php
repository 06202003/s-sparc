<?php
	// default template
	include("_sessionchecker.php");
	include("_config.php");

// Mengambil id review submission
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: Review Submission ID is not valid");
}
$pr_submission_id = $_GET['id'];
$current_user_id = $_SESSION['user_id']; // Mengambil user ID dari session

//Fungsi untuk menangani jika file submitted adalah nested zip
function extractCodeRecursively(ZipArchive $zip, $prefix = '') {
    $combined_content = "";
    $temp_dir = sys_get_temp_dir(); 

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry_name = $zip->getNameIndex($i);

        // Cek apakah ini file ZIP lain di dalamnya
        if (strtolower(pathinfo($entry_name, PATHINFO_EXTENSION)) === 'zip') {
            $temp_zip_path = tempnam($temp_dir, 'nested_zip_');
            if ($temp_zip_path === false) continue; // Lewati jika gagal buat file temp

            $zip_content = $zip->getFromIndex($i);
            if ($zip_content !== false && file_put_contents($temp_zip_path, $zip_content) !== false) {
                $inner_zip = new ZipArchive;
                if ($inner_zip->open($temp_zip_path) === TRUE) {
                    // Panggil fungsi ini lagi secara rekursif
                    $combined_content .= "--- ZIP: " . htmlspecialchars(basename($entry_name)) . " ---\n\n";
                    $combined_content .= extractCodeRecursively($inner_zip, $entry_name . '/'); 
                    $inner_zip->close();
                    $combined_content .= "--- ZIP: " . htmlspecialchars(basename($entry_name)) . " ---\n\n";
                }
                unlink($temp_zip_path); // Hapus file sementara
            } else {
                 if (file_exists($temp_zip_path)) unlink($temp_zip_path);
            }
        } 
        // Cek apakah ini file kode biasa (bukan folder)
        elseif (substr($entry_name, -1) !== '/' && preg_match('/\.(py|java|c|cpp|h|cs|js|html|css|php|sql)$/i', $entry_name)) {
            $file_content = $zip->getFromIndex($i);
            if ($file_content !== false) {
                $filename_only = basename($entry_name); 
                // Ambil hanya nama filenya saja
                $combined_content .= "--- File: " . htmlspecialchars($filename_only) . " ---\n\n";
                $combined_content .= $file_content . "\n\n";
            }
        }
    }
    return $combined_content;
}

//Query untuk mengambil info submitter & nama file asli ---
$sql = "SELECT 
            s.file_path,        
            s.filename,         
            s.submitter_id,     
            a.name as assessment_name,
            c.name AS course_name,
            prs.reviewer_id
        FROM peer_review_submission prs
        JOIN submission s ON prs.submission_to_review = s.submission_id
        JOIN assessment a ON s.assessment_id = a.assessment_id
        JOIN course c ON c.course_id = a.course_id
        WHERE prs.pr_submission_id = ? AND prs.reviewer_id = ?";

$stmt = $db->prepare($sql);

$stmt->bind_param("ii", $pr_submission_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: student_peer_review.php?error=submission_not_found");
    exit();
}

$submission_data = $result->fetch_assoc();
$file_path_on_server = $submission_data['file_path']; 
$original_filename = $submission_data['filename'];    
$submitter_id = $submission_data['submitter_id'];       
$assessment_name = $submission_data['assessment_name'];
$course_name = $submission_data['course_name'];        

$stmt->close();

// Query tambahan untuk mendapatkan detail submitter
$sql_submitter = "SELECT username, name, email FROM user WHERE user_id = ?";
$stmt_submitter = $db->prepare($sql_submitter);
$stmt_submitter->bind_param("i", $submitter_id);
$stmt_submitter->execute();
$result_submitter = $stmt_submitter->get_result();
$submitter_details = $result_submitter->fetch_assoc();
$stmt_submitter->close();

// Logika Pembacaan & Pemrosesan File
$code_content_raw = ""; // Konten asli sebelum diproses
$display_code = "Error: Submission file not found";

// Periksa apakah file .code ada
if (isset($file_path_on_server) && file_exists($file_path_on_server)) {
    
    // Cek ekstensi file ASLI
    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

    // JIKA FILE ASLI ADALAH ZIP
    if (strpos($file_extension, 'zip') === 0) {
        $zip = new ZipArchive;
        if ($zip->open($file_path_on_server) === TRUE) {
            // Memanggil fungsi rekursif
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

    // Pemrosesan Konten (Setelah dibaca/digabung)
    // Menghapus SEMUA baris kosong:
    $processed_code = preg_replace('/^\s*\R/m', '', $code_content_raw);

    // Anonimisasi Komentar (jika data submitter ditemukan)
    if ($submitter_details) {
        // Mengumpulkan semua kemungkinan identifier
        $identifiers = [];
        if (!empty($submitter_details['username'])) {
            $identifiers[] = $submitter_details['username'];
        }
        if (!empty($submitter_details['email'])) {
            $identifiers[] = $submitter_details['email'];
        }
        if (!empty($submitter_details['name'])) {
            // nama lengkap
            $identifiers[] = $submitter_details['name'];
            // semua bagian dari nama
            $name_parts = explode(' ', $submitter_details['name']);
            if (count($name_parts) > 1) {
                // Filter bagian nama yang terlalu pendek
                $filtered_name_parts = array_filter($name_parts, function($part) {
                    return strlen($part) > 2; // Hanya ambil bagian nama > 2 karakter
                });
                $identifiers = array_merge($identifiers, $filtered_name_parts);
            }
        }

        // Membersihkan daftar identifier
        $identifiers = array_unique(array_filter($identifiers));

        if (!empty($identifiers)) {
            
            // Membuat satu pola regex dari semua identifier
            $escaped_identifiers = array_map(function($id) {
                return preg_quote($id, '/'); // Escape karakter spesial regex
            }, $identifiers);
            
            // Gabungkan semua identifier
            // /i membuat menjadi case-insensitive (tidak peduli huruf besar/kecil)
            $regex_pattern = '/' . implode('|', $escaped_identifiers) . '/i';

            // Menjalankan preg_replace pada SELURUH teks kode
            $processed_code = preg_replace($regex_pattern, 'anonymous', $processed_code);
        }
    }
    
    $display_code = $processed_code; // Menggunakan kode yang sudah diproses untuk ditampilkan

} else {
    $display_code = "Error: File submission '" . htmlspecialchars($file_path_on_server ?? 'N/A') . "' not found on server.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>E-STRANGE: Submit Peer Review</title>
	<link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>

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
	</style>
	
	<script type="text/javascript">
		function loadGameNotif(){
			var notyf = new Notyf({
				duration: 0,
				position: { x: 'right', y: 'top' },
				dismissible: true
			});
			
			<?php
				$sqlt = "SELECT game_unobserved_notif.notification_id, game_unobserved_notif.message, game_student_course.course_id, course.name AS course_name  
						FROM game_unobserved_notif 
						INNER JOIN game_student_course ON game_student_course.gs_id = game_unobserved_notif.gs_id 
						INNER JOIN course ON course.course_id = game_student_course.course_id 
						INNER JOIN game_course ON game_course.course_id = game_student_course.course_id 
						WHERE game_student_course.student_id = '".$_SESSION['user_id']."' 
						AND game_course.is_active = '1' 
						AND game_student_course.is_participating = '1' 
						ORDER BY game_unobserved_notif.time_created ASC
						LIMIT 3";
				$rt = mysqli_query($db,$sqlt);
				$i = 0;
				if ($rt) {
					while($row = $rt->fetch_assoc()) {
						echo "const notification".$i." = notyf.success(\"[".addslashes($row['course_name'])."] ".addslashes($row['message'])."<br />Click here for details!\");
							  notification".$i.".on('click', ({target, event}) => {window.location.href = 'student_game.php?id=".$row['course_id']."';});";
						$sql = "DELETE FROM game_unobserved_notif WHERE notification_id = '".$row['notification_id']."'";
						$db->query($sql);
						$i++;
					}
				}
			?>
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
				<h1 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">Submit Peer Review</h1>
			</div>
			<div>
				<?php setHeaderReport("peer_review", $pr_submission_id, $db); ?>
			</div>
		</div>
	</header>

	<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
		
		<!-- Context Info Strip -->
		<div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 flex flex-wrap items-center justify-between gap-4 text-xs">
			<div class="flex flex-wrap items-center gap-6">
				<div>
					<span class="text-slate-400 uppercase tracking-wider font-bold text-[11px] block">Reviewer</span>
					<span class="font-bold text-slate-900"><?= htmlspecialchars($_SESSION['username'] ?? 'N/A') ?> / <?= htmlspecialchars($_SESSION['name'] ?? 'N/A') ?></span>
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
			<div>
				<a href="student_peer_review.php" class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
					<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
					<span>Back to Tasks</span>
				</a>
			</div>
		</div>

		<!-- Main 2-Column Grid -->
		<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
			
			<!-- Left Column: Code Preview (7 cols) -->
			<div class="lg:col-span-7 bg-slate-900 rounded-2xl border border-slate-800 shadow-xl overflow-hidden flex flex-col" style="height: calc(85vh - 120px);">
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
					<span class="text-[11px] font-mono text-slate-400">Anonymized Submission</span>
				</div>
				<div class="flex-1 overflow-auto bg-slate-900 text-slate-100 font-code text-xs">
					<pre class="prettyprint linenums"><?= htmlspecialchars($display_code); ?></pre>
				</div>
			</div>

			<!-- Right Column: Review Form (5 cols) -->
			<div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-5">
				<div>
					<h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider text-[11px] text-slate-500">Evaluation Form</h2>
					<p class="text-xs text-slate-500 mt-0.5">Provide constructive feedback and assign an objective score.</p>
				</div>

				<?php
					$score = isset($_GET['review_score']) ? intval($_GET['review_score']) : null;
					$required_min_chars = 0;
					if ($score !== null && $score < 60) {
						$required_min_chars = 100 - $score;
					}
					if (isset($_GET['error']) && $_GET['error'] === 'minchar') {
						if ($required_min_chars > 0) {
							$error_message = "Submission failed. Review explanation must contain at least {$required_min_chars} characters.";
						} else {
							$error_message = "Submission failed. Review does not meet the minimum character requirement.";
						}
						echo '<div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 font-medium">'.$error_message.'</div>';
					}
					if (isset($_GET['status']) && $_GET['status'] === 'review_success') {
						echo '<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 font-medium">Thank you! Your peer review has been recorded successfully.</div>';
					}
				?>

				<form action="student_review_submit_process.php" method="POST" class="space-y-4">
					<input type="hidden" name="pr_submission_id" value="<?= htmlspecialchars($pr_submission_id); ?>">

					<div>
						<label for="review_score" class="block text-xs font-semibold text-slate-700 mb-1.5">Score (0 - 100 Integer)</label>
						<input 
							type="number" 
							class="w-full px-3.5 py-2.5 text-sm font-semibold text-slate-900 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition font-mono font-bold" 
							id="review_score" 
							name="review_score" 
							min="0" 
							max="100" 
							required 
							placeholder="Enter numerical score"
						>
					</div>

					<div>
						<label for="review_description" class="block text-xs font-semibold text-slate-700 mb-1.5">Detailed Review Feedback</label>
						<div id="min-char-info" class="hidden mb-2 text-[11px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2.5 leading-relaxed"></div>
						<textarea 
							class="w-full px-3.5 py-2.5 text-xs text-slate-900 bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#00A0A5] focus:border-slate-900 transition resize-none leading-relaxed" 
							id="review_description" 
							name="review_description" 
							rows="9" 
							required 
							placeholder="Explain the strengths, flaws, code clarity, and rationale for your given score..."
						></textarea>
						<div id="char-counter" class="hidden mt-1.5 text-[11px] font-mono font-medium"></div>
					</div>

					<div class="pt-2">
						<button 
							type="submit" 
							class="w-full py-2.5 px-4 bg-[#00A0A5] hover:bg-[#008488] text-white text-sm font-semibold rounded-xl shadow-xs transition duration-150 flex items-center justify-center gap-2"
						>
							<span>Submit Peer Review</span>
							<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
						</button>
					</div>
				</form>

			</div>

		</div>

	</main>

	<script>
		const scoreInput = document.getElementById('review_score');
		const reviewTextarea = document.getElementById('review_description');
		const minCharInfo = document.getElementById('min-char-info');
		const charCounter = document.getElementById('char-counter');

		function countCharsExcludeSpace(text) {
			return text.replace(/\s+/g, '').length;
		}

		function updateRequirement() {
			const score = parseInt(scoreInput.value);
			const charCount = countCharsExcludeSpace(reviewTextarea.value);

			if (!isNaN(score) && score < 60) {
				const requiredChars = 100 - score;

				minCharInfo.classList.remove('hidden');
				charCounter.classList.remove('hidden');

				minCharInfo.innerText =
					`For scores below 60, please provide a detailed explanation of at least ${requiredChars} characters (excluding spaces).`;

				charCounter.innerText =
					`Characters typed: ${charCount} / ${requiredChars}`;

				if (charCount < requiredChars) {
					charCounter.className = 'mt-1.5 text-[11px] font-mono font-medium text-rose-600';
				} else {
					charCounter.className = 'mt-1.5 text-[11px] font-mono font-medium text-emerald-600';
				}

			} else {
				minCharInfo.classList.add('hidden');
				charCounter.classList.add('hidden');
			}
		}

		scoreInput.addEventListener('input', updateRequirement);
		reviewTextarea.addEventListener('input', updateRequirement);
	</script>
</body>
</html>
