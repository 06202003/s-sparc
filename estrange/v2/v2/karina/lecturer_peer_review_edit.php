<?php
    include("_sessionchecker_peer.php"); 
    include("_config_peer_review.php");
    include("_header_peer_review.php");

$error_message = '';
$pr_assessment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$existing_data = null;

if (!$pr_assessment_id) {
    die("Peer Review Assessment ID not valid");
}

// Mengambil semua data yang ada di database untuk ditampilkan di form
$sql_fetch = "SELECT 
                pra.assessment_id, 
                pra.reviews_per_submission, 
                pra.peer_review_close_time,
                pra.peer_review_start_time,
                a.course_id,
                a.name AS assessment_name,
                a.submission_close_time,
                c.name AS course_name
              FROM peer_review_assessment pra
              JOIN assessment a ON pra.assessment_id = a.assessment_id
              JOIN course c ON a.course_id = c.course_id
              WHERE pra.pr_assessment_id = ?";
              
$stmt_fetch = $db->prepare($sql_fetch);
$stmt_fetch->bind_param("i", $pr_assessment_id);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch->num_rows !== 1) {
    die("Peer Review Assessment not found");
}

$existing_data = $result_fetch->fetch_assoc();
$stmt_fetch->close();

// Cek apakah review close time sudah lewat
$review_close_dt = new DateTime($existing_data['peer_review_close_time']);
if ($review_close_dt < new DateTime()) { // new DateTime() = NOW()
    die("This Peer Review Assessment is already closed and cannot be edited. <a href='lecturer_peer_review.php'>Back</a>");
}


// Logika Proses Form Update (POST Request)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data hanya dari field yang bisa diedit
    $reviews_per_submission = filter_input(INPUT_POST, 'reviews_per_submission', FILTER_VALIDATE_INT);
    $peer_review_start_time = $_POST['peer_review_start_time'] ?? '';
    $peer_review_close_time = $_POST['peer_review_close_time'] ?? '';
    $pr_assessment_id_post = filter_input(INPUT_POST, 'pr_assessment_id', FILTER_VALIDATE_INT); 

    // Ambil submission_close_time dari data yang sudah dipilih
    $submission_close_time_dt = new DateTime($existing_data['submission_close_time']);
    $new_start_time_dt = new DateTime($peer_review_start_time);
    $new_close_time_dt = new DateTime($peer_review_close_time);

    // start time peer review harus setelah submission close time
    if ($new_start_time_dt < $submission_close_time_dt) {
        $error_message = "Error: Peer review start time must be after the submission close time (" . $submission_close_time_dt->format('d M Y, H:i') . ").";
    } 
    //close time tidak boleh lebih kecil dari start time
    else if ($new_close_time_dt <= $new_start_time_dt) {
        $error_message = "Error: Peer review close time must be after the start time (" . $new_start_time_dt->format('d M Y, H:i') . ").";
    }
    // Validasi input dasar
    else if (!$reviews_per_submission || !$peer_review_close_time || !$peer_review_start_time || !$pr_assessment_id_post) {
        $error_message = "All fields need to be filled";
    } 
    // Jika semua validasi lolos
    else {
        $db->begin_transaction();
        try {
            $sql_update = "UPDATE peer_review_assessment SET 
                                peer_review_start_time = ?, 
                                reviews_per_submission = ?, 
                                peer_review_close_time = ? 
                           WHERE 
                                pr_assessment_id = ?";
            
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->bind_param("sisi", $peer_review_start_time, $reviews_per_submission, $peer_review_close_time, $pr_assessment_id_post);
            $stmt_update->execute();

            if ($db->error) {
                 throw new Exception("Failed to update: " . $db->error);
            }
            $stmt_update->close();

            $db->commit();
            header("Location: lecturer_peer_review.php?status=edit_success");
            exit();

        } catch (Exception $e) {
            $db->rollback();
            $error_message = "Error Found: " . $e->getMessage();
            // Muat ulang data yang ada jika terjadi error, agar form menampilkan data lama
            $existing_data = $result_fetch->fetch_assoc();
        }
    }
}
?>
<html>
	<head>
		<title>E-STRANGE: Edit Peer Review</title>
		<!-- ASLI <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet"> -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <style> 
		@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300&display=swap');
		body { 
			font-family: 'Montserrat', sans-serif; 
		}
		.btn-primary{ 
			background: #00A0A5 !important ; 
			color: white !important ; 
		}
		.form-control { 
			border: 2px solid #000; 
			border-radius: 8px; 
		}
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
	<body>
		<?php setHeaderLecturer("peer_review", "Edit Peer Review"); ?>
		
		<div class="container my-4">
			<div class="row justify-content-center">
				<div class="col-lg-8">
					<h2>Edit Peer Review Assessment</h2>
					<?php if ($error_message): ?>
						<div class="alert alert-danger"><?php echo $error_message; ?></div>
					<?php endif; ?>
					
                    <form action="lecturer_peer_review_edit.php?id=<?php echo $pr_assessment_id; ?>" method="POST" class="mt-4 p-4 border rounded bg-light">
						
                        <input type="hidden" name="pr_assessment_id" value="<?php echo $pr_assessment_id; ?>">

						<div class="mb-3">
							<label for="course_name" class="form-label">Course</label>
							<input type="text" class="form-control" id="course_name" 
                                   value="<?php echo htmlspecialchars($existing_data['course_name']); ?>" readonly disabled>
						</div>

						<div class="mb-3">
							<label for="assessment_name" class="form-label">Assessment</label>
                            <input type="text" class="form-control" id="assessment_name" 
                                   value="<?php echo htmlspecialchars($existing_data['assessment_name']); ?>" readonly disabled>
                            <div class="form-text">Submission Close Time for this assessment: 
                                <strong><?php echo (new DateTime($existing_data['submission_close_time']))->format('d M Y, H:i'); ?></strong>
                            </div>
						</div>

						<div class="mb-3">
							<label for="peer_review_start_time" class="form-label">Review Start Time</label>
                            <?php
                                $start_time_formatted = (new DateTime($existing_data['peer_review_start_time']))->format('Y-m-d\TH:i');
                            ?>
							<input type="datetime-local" class="form-control" id="peer_review_start_time" name="peer_review_start_time" required 
                                   value="<?php echo $start_time_formatted; ?>">
                            <div class="form-text text-danger">Start time must be after submission close time.</div>
						</div>

						<div class="mb-3">
							<label for="reviews_per_submission" class="form-label">Reviews per Submission</label>
							<input type="number" class="form-control" id="reviews_per_submission" name="reviews_per_submission" min="1" required 
                                   value="<?php echo htmlspecialchars($existing_data['reviews_per_submission']); ?>">
						</div>

						<div class="mb-3">
							<label for="peer_review_close_time" class="form-label">Review Close Time</label>
                            <?php
                                $close_time_formatted = (new DateTime($existing_data['peer_review_close_time']))->format('Y-m-d\TH:i');
                            ?>
							<input type="datetime-local" class="form-control" id="peer_review_close_time" name="peer_review_close_time" required 
                                   value="<?php echo $close_time_formatted; ?>">
						</div>

						<div class="mt-4">
							<button type="submit" class="btn btn-primary">Update Peer Review</button>
							<a href="lecturer_peer_review.php" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
				</div>
			</div>
		</div>

	<script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
	</body>
</html>