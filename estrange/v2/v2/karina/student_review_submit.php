<?php
    include("_sessionchecker_peer.php"); //"../_sessionchecker.php"
    include("_config_peer_review.php");
    include("_header_peer_review.php");

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
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

		<title> E-Strange: Peer Review Submission</title>
		<link rel="icon" href="../strange_html_layout_additional_files/icon.png">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
		
		<!-- ASLI <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet"> -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
		
		<!-- jQuery -->
		<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>

		<!-- DataTables JS -->
		<!-- ASLI <link rel="stylesheet" type="text/css" href="../datatables/jquery.dataTables.min.css">
		<script type="text/javascript" src="../datatables/jquery.dataTables.min.js"></script>
		<link rel="stylesheet" type="text/css" href="../datatables/responsive.bootstrap5.min.css">
		<script type="text/javascript" src="../datatables/dataTables.responsive.min.js"></script>
		<script type="text/javascript" src="../datatables/responsive.bootstrap5.min.js"></script> -->

		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/3.0.2/css/responsive.bootstrap5.min.css"/>
		<script type="text/javascript" src="https://cdn.datatables.net/v/bs5/dt-2.0.8/datatables.min.js"></script>
		<script type="text/javascript" src="https://cdn.datatables.net/responsive/3.0.2/js/dataTables.responsive.min.js"></script>
		<script type="text/javascript" src="https://cdn.datatables.net/responsive/3.0.2/js/responsive.bootstrap5.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

		<!-- Prettify -->
		<!-- ASLI <script src="../strange_html_layout_additional_files/run_prettify.js"></script> -->
		<script src="https://cdn.jsdelivr.net/gh/google/code-prettify@master/loader/run_prettify.js"></script>

		<!-- The use of Notyf library https://github.com/caroso1222/notyf -->
		<link rel="stylesheet" href="../strange_html_layout_additional_files/notyf.min.css">
		<script src="../strange_html_layout_additional_files/notyf.min.js"></script>
		<script type="text/javascript">
			function loadGameNotif(){
				// Create an instance of Notyf
				var notyf = new Notyf({
				  duration: 0,
				  position: {
					x: 'right',
					y: 'top',
				  },
				  dismissible: true
				});
			}
			function construct(){
				loadGameNotif();
			}

			// function to toggle general info given at top left of the page.
			function toggleCollapsible(targetDiv){
				var content = document.getElementById(targetDiv);
				if (content.style.display == "block") {
					content.style.display = "none";
				} else {
					content.style.display = "block";
				}
			}
            
            function updateHash(id) {
                history.replaceState(null, null, " "); // Kosongkan hash sementara
                setTimeout(() => {
                    window.location.hash = "#" + id;
                }, 50);
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
<style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300&display=swap');
    body {
      /* font-family: "Times New Roman", Times, serif; */
      font-family: 'Montserrat', sans-serif;
    }
	.btn-primary{
			background: #00A0A5 !important ;
			color: white !important ;
		}
	.btn-outline-primary:hover{
	    background: #00A0A5 !important ;
			color: white !important ;
	}
	.form-control{
			border: 2px solid #000;	
			border-radius: 8px;
		}
	.prettyprint ol.linenums {
        list-style-type: decimal; /* Pastikan pakai angka biasa */
    }
    
    .prettyprint ol.linenums li {
        counter-increment: list-number 1; /* Naik 1 per baris */
        list-style: none;
        position: relative;
    }
    
    .prettyprint ol.linenums li:before {
        content: counter(list-number);
        position: absolute;
        left: -2em; /* Geser agar sejajar */
    }
	@media only screen and (max-width: 425px) {
		.buttonmobile{
			width: 100%;
		}
	}
</style>
<body onload="construct()">
  	<div class="container-fluid">
		<div class="row d-flex justify-content-center align-items-center  mx-3">
			<div class="col-md-6 layoutmobilestart">
			<!-- src="../strange_html_layout_additional_files/logo.png" -->
			<img src="../strange_html_layout_additional_files/logo.png" alt="logo" class="mobile" />
			<style>
				.layoutmobilestart{
					text-align:left;
				}
				.layoutmobileend{
					text-align:right;
				}
				.logout{
					margin-right:1rem;
				}
				.mobile {
					margin: 0;
					width: 100%;
					height: auto;
					max-height: 200px;
					max-width: 200px;
				}
				.navbarAdmin{
					background-color: #51adba;height:auto;padding-bottom:1rem;
				}
				.colNav{
					margin-bottom:-1.25rem;
				}
				.logoutli{
					margin-left:auto;
				}
				@media only screen and (max-width: 425px) {
					.mobile {
						margin: 1rem;
						width: 100%;
						height: auto;
						max-height: 150px;
						max-width: 150px;
					}
					.layoutmobilestart{
						text-align:center;
					}
					.layoutmobileend{
						text-align:center;
					}
					.logout{
						margin:0;
					}
					.navbarAdmin{
						background-color: #51adba;height:auto;padding-bottom:0rem;
					}
					.colNav{
						margin-bottom:1rem;
						text-align:left;
					}
					a{
						text-align:left;			
					}
					.logoutli{
						margin-left:0;
					}
					.khususquality{
						margin-bottom: .5rem;
					}
				}
			</style>
			</div>

			<div class="col-md-6 layoutmobileend fs-3">
				<div class='fs-1'>Submit Peer Review</div>
			<?php
				setHeaderReport("peer_review", $pr_submission_id, $db);
			?>

			</div>
		</div>
        
		<div class="row d-flex mx-1 mt-3 ">
            <?php
				$student_username = $_SESSION['username'] ?? 'N/A';
				$student_name = $_SESSION['name'] ?? 'N/A';
			?>
            <div class="col-md-6" >
                <div class="subcontentwrapper my-1 fs-5">Student ID<b>:</b> <?php echo htmlspecialchars($student_username) . ' / ' . htmlspecialchars($student_name); ?></div>
                
                <div class="subcontentwrapper my-1 fs-5">Course<b>:</b> <?php echo htmlspecialchars($course_name); ?>  </div>
        		<div class="subcontentwrapper my-1 fs-5">Assessment<b>:</b> <?php echo htmlspecialchars($assessment_name); ?> </div>
            </div>
        </div>
    </div>

    <hr />
    <section>
        <div class="container-fluid">
            <div class="row d-flex justify-content-center mb-5">
                
                <div class="col-md-7"> <div class="codetitle fs-4">Submitted code:</div>
                    <div class="codeview border border-1" style="height: 75vh; overflow-y: auto;">
                        <pre class="prettyprint linenums"><?php echo htmlspecialchars($display_code); ?></code></pre>
                    </div>
                </div>

                <div class="col-md-5"> <div class="codetitle fs-4">Your Review:</div>
				<?php
					$score = isset($_GET['review_score']) ? intval($_GET['review_score']) : null;
					// Default: tidak butuh min char kecuali score < 60
					$required_min_chars = 0;
					if ($score !== null && $score < 60) {
						$required_min_chars = 100 - $score; // contoh: score 45 => min 55
					}
					// Cek error
					if (isset($_GET['error']) && $_GET['error'] === 'minchar') {
						// Pesan dinamis berdasarkan rule baru
						if ($required_min_chars > 0) {
							$error_message = ($human_language == 'en') 
								? "Submission failed. Review must contain at least {$required_min_chars} characters."
								: "Submit gagal. Review harus berisi minimal {$required_min_chars} karakter.";
						} else {
							// fallback kalau tidak ada rule (aman)
							$error_message = ($human_language == 'en') 
								? "Submission failed. Review does not meet the minimum character requirement."
								: "Submit gagal. Review tidak memenuhi syarat jumlah karakter.";
						}
						echo '<div class="alert alert-danger">' . $error_message . '</div>';
					}

					// Sukses
					if (isset($_GET['status']) && $_GET['status'] === 'review_success') {
						$success_message = ($human_language == 'en')
							? "Thank you! Your review has been submitted successfully."
							: "Terima kasih! Review Anda telah berhasil disubmit.";

						echo '<div class="alert alert-success mt-3">' . $success_message . '</div>';
					}
				?>
                    <form action="student_review_submit_process.php" method="POST">
                        
                        <input type="hidden" name="pr_submission_id" value="<?php echo $pr_submission_id; ?>">

                        <div class="mb-3">
                            <label for="review_score" class="form-label">Score (0-100)</label>
                            <input type="number" class="form-control" id="review_score" name="review_score" min="0" max="100" required placeholder="No Decimal Score">
                        </div>

                        <div class="mb-3">
                            <label for="review_description" class="form-label">Review Description</label>
                            <textarea class="form-control" id="review_description" name="review_description" rows="10" required style="height: calc(75vh - 300px);"></textarea>
							<small id="char-error" class="form-text text-danger"></small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Submit Review</button>
                    </form>
                </div>

            </div> </div>
    </section>
</body>
</html>
