<?php
    include("_sessionchecker_peer.php"); 
    include("_config_peer_review.php");
    include("_header_peer_review.php");

$error_message = '';
$success_message = ''; 

// Logika Proses Form Submission -> POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil dan validasi data
    $assessment_id = filter_input(INPUT_POST, 'assessment_id', FILTER_VALIDATE_INT);
    $reviews_per_submission = filter_input(INPUT_POST, 'reviews_per_submission', FILTER_VALIDATE_INT);
    $peer_review_start_time = $_POST['peer_review_start_time'] ?? '';
    $peer_review_close_time = $_POST['peer_review_close_time'] ?? '';

    // Pengecekan / validasi
    if (!$assessment_id || !$reviews_per_submission || !$peer_review_close_time || !$peer_review_start_time) {
        $error_message = "All fields need to be filled";
    } else {
        $db->begin_transaction();
        try {
            // Mengambil submission_close_time dari database
            $sql_get_close_time = "SELECT submission_close_time FROM assessment WHERE assessment_id = ?";
            $stmt_time = $db->prepare($sql_get_close_time);
            $stmt_time->bind_param("i", $assessment_id);
            $stmt_time->execute();
            $result_time = $stmt_time->get_result();
            if ($result_time->num_rows !== 1) {
                throw new Exception("Assessment not found");
            }
            $submission_close_time = $result_time->fetch_assoc()['submission_close_time'];
            $stmt_time->close();

            // Melakukan Validasi Waktu
            $submission_close_time_dt = new DateTime($submission_close_time);
            $new_start_time_dt = new DateTime($peer_review_start_time);
			$new_close_time_dt = new DateTime($peer_review_close_time);
			//start time tidak boleh lebih kecil dari submission close time
            if ($new_start_time_dt < $submission_close_time_dt) {
                throw new Exception("Error: Peer review start time must be after the submission close time (" . $submission_close_time_dt->format('d M Y, H:i') . ").");
            }
			//close time tidak boleh lebih kecil dari start time
			if ($new_close_time_dt <= $new_start_time_dt) {
                throw new Exception("Error: Peer review close time must be after the start time (" . $new_start_time_dt->format('d M Y, H:i') . ").");
            }

            // INSERT ke peer_review_assessment
            $sql_insert_assessment = "INSERT INTO peer_review_assessment(assessment_id, peer_review_start_time, reviews_per_submission, peer_review_close_time, is_pr_assessment_generated) VALUES (?, ?, ?, ?, 0)";
            $stmt_insert = $db->prepare($sql_insert_assessment);
            $stmt_insert->bind_param("isis", $assessment_id, $peer_review_start_time, $reviews_per_submission, $peer_review_close_time);
            $stmt_insert->execute();

            if ($stmt_insert->affected_rows !== 1) {
                throw new Exception("Failed to add new Peer Review Assessment");
            }
            $stmt_insert->close();

            // Commit transaksi
            $db->commit();
            header("Location: lecturer_peer_review.php?status=add_success"); 
            exit();

        } catch (Exception $e) {
            $db->rollback();
            $error_message = "Error Found: " . $e->getMessage();
        }
    }
}
?>
<html>
	<head>
		<title>E-STRANGE: Add Peer Review</title>
		<!-- ASLI <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet"> -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
		<?php setHeaderLecturer("peer_review", "Add Peer Review"); ?>
		
		<div class="container my-4">
			<div class="row justify-content-center">
				<div class="col-lg-8">
					<h2>Add New Peer Review Assessment</h2>
					<?php if ($error_message): ?>
						<div class="alert alert-danger"><?php echo $error_message; ?></div>
					<?php endif; ?>
					
                    <form action="lecturer_peer_review_add.php" method="POST" class="mt-4 p-4 border rounded bg-light">
						
						<div class="mb-3">
							<label for="course_id" class="form-label">Course</label>
							<select class="min-w-[200px] shrink-0 form-select" id="course_id" name="course_id" required> 
                                <option value="" disabled selected>Choose Course...</option>
								<?php
								$lecturer_id = $_SESSION['user_id'];
								$sql_courses = "SELECT DISTINCT c.course_id, c.name FROM course c JOIN assessment a ON c.course_id = a.course_id WHERE c.creator_id = ? AND a.allow_late_submission = 0"; 
								$stmt_courses = $db->prepare($sql_courses);
								$stmt_courses->bind_param("i", $lecturer_id);
								$stmt_courses->execute();
								$result_courses = $stmt_courses->get_result();
								while ($course = $result_courses->fetch_assoc()) {
									echo '<option value="' . $course['course_id'] . '">' . htmlspecialchars($course['name']) . '</option>';
								}
								$stmt_courses->close();
								?>
							</select>
						</div>

						<div class="mb-3">
							<label for="assessment_id" class="form-label">Assessment</label>
							<select class="min-w-[200px] shrink-0 form-select" id="assessment_id" name="assessment_id" required disabled>
								<option value="" selected>Choose the course first</option>
							</select>
                             <div class="form-text">Only show assessment that doesn't have peer review yet and not allowed late submission</div>
						</div>

                        <div class="mb-3">
							<label for="peer_review_start_time" class="form-label">Review Start Time</label>
							<input type="datetime-local" class="form-control" id="peer_review_start_time" name="peer_review_start_time" required>
                            <div class="form-text text-danger">Start time must be after the submission close time.</div>
						</div>

						<div class="mb-3">
							<label for="reviews_per_submission" class="form-label">Reviews per Submission</label>
							<input type="number" class="form-control" id="reviews_per_submission" name="reviews_per_submission" min="1" required placeholder="Example: 3">
						</div>

						<div class="mb-3">
							<label for="peer_review_close_time" class="form-label">Review Close Time</label>
							<input type="datetime-local" class="form-control" id="peer_review_close_time" name="peer_review_close_time" required>
						</div>

						<div class="mt-4">
							<button type="submit" class="btn btn-primary">Add Peer Review</button>
							<a href="lecturer_peer_review.php" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
				</div>
			</div>
		</div>

	<script>
		$(document).ready(function() {
            // Listener untuk dropdown Course
			$('#course_id').on('change', function() {
				var courseId = $(this).val();
				var assessmentDropdown = $('#assessment_id');
				
				assessmentDropdown.html('<option>Loading...</option>').prop('disabled', true);
                $('#peer_review_start_time').val(''); // Mengkosongkan start time jika course diubah

				if (courseId) {
					$.ajax({
						url: 'lecturer_get_assessments_for_pr.php',
						type: 'GET',
						data: { course_id: courseId },
						dataType: 'json',
						success: function(assessments) {
							assessmentDropdown.empty().prop('disabled', false);
							assessmentDropdown.append('<option value="" disabled selected>Choose Assessment...</option>');
							if(assessments.length > 0) {
								$.each(assessments, function(key, assessment) {
                                    // Menyimpan close_time sebagai data attribute
									assessmentDropdown.append($('<option>', {
										value: assessment.assessment_id,
										text: assessment.name,
                                        'data-closetime': assessment.submission_close_time
									}));
								});
							} else {
								assessmentDropdown.append('<option disabled>No valid assessments found</option>').prop('disabled', true);
							}
						},
						error: function() {
							assessmentDropdown.html('<option>Failed loading data</option>').prop('disabled', true);
						}
					});
				} else {
					assessmentDropdown.html('<option>Choose course first</option>').prop('disabled', true);
				}
			});

            // Listener untuk Assessment
            $('#assessment_id').on('change', function() {
                // Mengambil nilai <option> yang sedang dipilih
                const selectedOption = $(this).find('option:selected');
                // Menambil nilai dari 'data-closetime' yang kita simpan
                const closeTime = selectedOption.data('closetime');

                if (closeTime) {
                    // Format string YYYY-MM-DD HH:MM:SS menjadi YYYY-MM-DDTHH:MM
                    const formattedCloseTime = closeTime.slice(0, 16).replace(' ', 'T');
                    
                    // Set nilai (value) dari input start_time
                    $('#peer_review_start_time').val(formattedCloseTime);
                } else {
                    $('#peer_review_start_time').val('');
                }
            });
		});
	</script>

    <script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
	</body>
</html>