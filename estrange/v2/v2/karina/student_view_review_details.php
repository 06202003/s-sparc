<?php
	include("_sessionchecker_peer.php"); //"../_sessionchecker.php"
	include("_config_peer_review.php");
	include("_header_peer_review.php");

if (!isset($_GET['submission_id']) || !is_numeric($_GET['submission_id'])) {
    die("Error: ID Submission not valid.");
}
$submission_id = $_GET['submission_id'];
$current_user_id = $_SESSION['user_id'];  // Mengambil user ID dari session

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

//  Mengambil Info Umum & Path File (Termasuk nama file asli)
$sql_info = 
"SELECT 
    a.name as assessment_name, 
    c.name as course_name, 
    s.file_path,        
    s.filename,         
    AVG(pr.review_score) as avg_score
FROM 
    submission s
JOIN 
    assessment a ON s.assessment_id = a.assessment_id
JOIN 
    course c ON a.course_id = c.course_id
LEFT JOIN 
    peer_review_submission prs ON s.submission_id = prs.submission_to_review
LEFT JOIN 
    peer_review pr ON prs.pr_submission_id = pr.pr_submission_id
WHERE 
    s.submission_id = ? AND s.submitter_id = ?
GROUP BY 
    s.submission_id, a.name, c.name, s.file_path, s.filename";

$stmt_info = $db->prepare($sql_info);
$stmt_info->bind_param("ii", $submission_id, $current_user_id);
$stmt_info->execute();
$result_info = $stmt_info->get_result();

if ($result_info->num_rows !== 1) {
    die("Submission not found");
}
$submission_info = $result_info->fetch_assoc();
$stmt_info->close();


// Mengambil Daftar Semua Review untuk Submission Ini 
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
$code_content_raw = ""; // Konten asli sebelum diproses
$display_code = "Error: File submission not found";
$file_path_on_server = $submission_info['file_path']; // Path ke file .code
$original_filename = $submission_info['filename'];    // Nama file asli

// Periksa apakah file .code ada
if (isset($file_path_on_server) && file_exists($file_path_on_server)) {
    
    // Cek ekstensi file ASLI
    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

    // JIKA FILE ASLI ADALAH ZIP
    if ($file_extension === 'zip') {
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
	// Menghapus SEMUA baris kosong:
    $processed_code = preg_replace('/^\s*\R/m', '', $code_content_raw);
    
    $display_code = $processed_code; // Gunakan kode yang sudah diproses untuk ditampilkan

} else {
    $display_code = "Error: File submission '" . htmlspecialchars($file_path_on_server ?? 'N/A') . "' not found in the server";
}

?>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">

		<title> E-Strange: Peer Review Details</title>
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

			// sort table content. Copied and modified from https://www.w3schools.com/howto/howto_js_sort_table.asp
			function sortTable(n, tableId, isNumber, tableContainerId) {
			  var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
			  table = document.getElementById(tableId);
			  switching = true;
			  // Set the sorting direction to ascending:
			  dir = "asc";
			  /* Make a loop that will continue until
			  no switching has been done: */
			  while (switching) {
			    // Start by saying: no switching is done:
			    switching = false;
			    rows = table.rows;
			    /* Loop through all table rows */
			    for (i = 0; i < (rows.length - 1); i++) {
			      // Start by saying there should be no switching:
			      shouldSwitch = false;
			      /* Get the two elements you want to compare,
			      one from current row and one from the next: */
			      x = rows[i].getElementsByTagName("TD")[n];
			      y = rows[i + 1].getElementsByTagName("TD")[n];
				  	if(n==0){
							/*
							* the column content is encapsulated with a link and can provide confusing result
							* as the <A> tag is considered in comparison
							*/
							x = x.getElementsByTagName("A")[0];
							y = y.getElementsByTagName("A")[0];
				  	}
			      /* Check if the two rows should switch place,
			      based on the direction, asc or desc: */
			      if (dir == "asc") {
							if(isNumber == true){
								numx = Number(x.innerHTML.split(" ")[0]);
								numy = Number(y.innerHTML.split(" ")[0]);
								if (numx > numy ){
				          // If so, mark as a switch and break the loop:
				          shouldSwitch = true;
				          break;
				        }
							}else{
				        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
				          // If so, mark as a switch and break the loop:
				          shouldSwitch = true;
				          break;
				        }
							}
			      } else if (dir == "desc") {
							if(isNumber == true){
								numx = Number(x.innerHTML.split(" ")[0]);
								numy = Number(y.innerHTML.split(" ")[0]);
								if (numx < numy ){
				          // If so, mark as a switch and break the loop:
				          shouldSwitch = true;
				          break;
				        }
							}else{
				        if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
				          // If so, mark as a switch and break the loop:
				          shouldSwitch = true;
				          break;
				        }
							}
			      }
			    }
			    if (shouldSwitch) {
			      /* If a switch has been marked, make the switch
			      and mark that a switch has been done: */
			      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
			      switching = true;
			      // Each time a switch is done, increase this count by 1:
			      switchcount ++;
			    } else {
			      /* If no switching has been done AND the direction is "asc",
			      set the direction to "desc" and run the while loop again. */
			      if (switchcount == 0 && dir == "asc") {
			        dir = "desc";
			        switching = true;
			      }
			    }
			  }

			recolorTableContent(tableId);
				// set to the same selection as prior sorted
				if(selectedCodeFragmentId != null)
					markSelected(selectedCodeFragmentId,tableId);
			}

			function recolorTableContent(tableId){
				table = document.getElementById(tableId);
				rows = table.rows;
				/* Loop through all table rows */
				for (i = 0; i < rows.length; i++) {
					if(i%2 == 0){
						rows[i].style.backgroundColor = "rgba(171,244,174,1)";
					}else {
						rows[i].style.backgroundColor = "rgba(244,224,104,1)";
					}
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
		tr td{
			font-size: 0.9rem;
		}
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
				<div class='fs-1'>Peer Review Details</div>
				<?php
					setHeaderReport("peer_review", $submission_id, $db);
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
                <div class="subcontentwrapper my-1 fs-5">Course<b>:</b> <?php echo htmlspecialchars($submission_info['course_name']); ?>  </div>
        		<div class="subcontentwrapper my-1 fs-5">Assessment<b>:</b> <?php echo htmlspecialchars($submission_info['assessment_name']); ?> </div>
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

                <div class="col-md-5">
					<div class="codetitle fs-4">Review Details:</div>
					<table id="review-table" class="table table-hover">
						<thead>
							<tr>
								<th>ID</th>
								<th>Score</th>
							</tr>
						</thead>
						<tbody>
							<?php $review_counter = 1; ?>
							<?php foreach ($reviews_list as $review): ?>
								<tr style="cursor: pointer;" 
									data-description="<?php echo htmlspecialchars($review['review_description']); ?>">
									<td>R<?php echo str_pad($review_counter++, 3, '0', STR_PAD_LEFT); ?></td>
									<td><?php echo htmlspecialchars($review['review_score']); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				
					<h5 class="mt-4">Review Description:</h5>
					<div id="review-explanation-box" class="border rounded bg-light p-3" style="flex-grow: 1; overflow-y: auto;">
						<span class="text-muted">Click review ID to view review description</span>
					</div>
                    
                </div>

            </div> </div>
    </section>

<script>
//script untuk menampilkan deskripsi review
$(document).ready(function() {
    
    const reviewTableRows = $('#review-table tbody tr');
    const explanationBox = $('#review-explanation-box');

    // Tambahkan 'event listener' untuk mendeteksi klik pada salah satu baris tabel
    reviewTableRows.on('click', function() {
        reviewTableRows.removeClass('table-primary');
        $(this).addClass('table-primary');
        const description = $(this).data('description');
        explanationBox.text(description);
    });
});
</script>

</body>
</html>
