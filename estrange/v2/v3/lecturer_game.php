<?php
	include("_sessionchecker.php");
	include("_config.php");
	
	$courseID = null;
	$prizeText = null;
	
	// if it has a course id attached in the url, set the course id
	if(isset($_GET['id']) == true && $_GET['id'] != ''){
		$courseID = mysqli_real_escape_string($db,$_GET['id']);
		
		// check if the lecturer is the creator of the course and game feature is on for that course
		$sql = "SELECT course.creator_id FROM course
			INNER JOIN game_course ON game_course.course_id = course.course_id 
			WHERE game_course.is_active = 1 
			AND course.creator_id = '".$_SESSION['user_id']."' 
			AND course.course_id = '".$courseID."'";
		$result = mysqli_query($db,$sql);
		if ($result->num_rows == 0) {
			// if the lecturer is not in charge for given course or the gamification is off, redirect to dashboard
			header('Location: lecturer_dashboard.php');
			exit;
		}
	}
	
	// check if the lecturer is in charge in at least one course with game feature
	$sql = "SELECT course.course_id, course.name, 
			game_course.prize_text FROM course 
			INNER JOIN game_course ON game_course.course_id = course.course_id 
			WHERE game_course.is_active = 1 
			AND course.creator_id = '".$_SESSION['user_id']."' ";
	$result = mysqli_query($db,$sql);
	if ($result->num_rows == 0) {
		// if the lecturer is not in charge in at least one gamified course, redirect to lecturer_nogame
		header('Location: lecturer_no_game.php');
		exit;
	}
?>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
		<title> E-STRANGE: Course game</title>
    <link rel="icon" href="strange_html_layout_additional_files/icon.png">
	<link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>

<!-- DataTables JS -->
<link rel="stylesheet" type="text/css" href="datatables/jquery.dataTables.min.css">
<script type="text/javascript" src="datatables/jquery.dataTables.min.js"></script>
<link rel="stylesheet" type="text/css" href="datatables/responsive.bootstrap5.min.css">
<script type="text/javascript" src="datatables/dataTables.responsive.min.js"></script>
<script type="text/javascript" src="datatables/responsive.bootstrap5.min.js"></script>

  
    <script>
			function recolorTableContent(tableId){
				table = document.getElementById(tableId);
				rows = table.rows;
				/* Loop through all table rows */
				for (i = 0; i < rows.length; i++) {
					if(i%2 == 0){
						rows[i].style.backgroundColor = "rgba(255,255,255,1)";
					}else {
						rows[i].style.backgroundColor = "#eeeeee";
					}
				}
			}

			var previousRowId = null;
			function selectRow(id, tableId){
				if(previousRowId != null){
					// for header table, recolor the contents
					recolorTableContent(tableId);
				}
				// for header table, recolor the row
				recolorCodeFragment(id,"rgba(60,200,246,1)");
				previousRowId= id;
			}

			// recolor a code fragment with its following rows
			function recolorCodeFragment(id, defaultColour){
				document.getElementById(id).style.backgroundColor = defaultColour;
			}
			
			// to update the whole page when the course is changed
			function updateDisplayedGameDataBasedOnCourse(){
				var selectedValue = document.getElementById("course").value;
				window.location.href = window.location.href.substring(0, window.location.href.indexOf("?")) + "?id=" + selectedValue;
			}
			
			// for sorting table 
			// sort table content. Copied and modified from https://www.w3schools.com/howto/howto_js_sort_table.asp
			function sortTable(n, tableId, isNumber) {
				var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
				table = document.getElementById(tableId);
				
				switching = true;
				// Set the sorting direction to ascending:
				dir = "desc";
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
				
				// set the ranks for the first top N
				rows = table.rows;
				threshold = <?php echo $num_students_shown_leaderboard;?>;
				if(threshold > rows.length)
					threshold = rows.length;
				for (i = 0; i < threshold; i++) {
					if(rows[i].getElementsByTagName("TD").length == 1)
						// if it is only one <td>, it means the table has no entries, skip the process
						break;
					else
						// set the first <td>
						rows[i].getElementsByTagName("TD")[0].innerHTML = (i+1);
				}
				
				// remove remaining elements
				while(rows.length > <?php echo $num_students_shown_leaderboard;?>){
					rows[rows.length-1].remove();
				}
				
			}
		
			
			window.addEventListener('load', function () {
			  sortTable(2,'sumtablecontent',true); // perubahan
			});
			
			
    </script>
       <style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300&display=swap');
    body {
      /* font-family: "Times New Roman", Times, serif; */
      font-family: 'Montserrat', sans-serif;
    }
	.btn-primary{
			background: #a8c6e7 !important ;
			color: black  !important ;
		}
	.buttontambah{
		text-align: right;
	}
	@media (max-width: 425px) {
		.buttontambah{
			text-align: left;
			margin: 1rem 0 1rem 0;
		}
	}
	</style>
  </head>
  <body>
		<?php
		  setHeaderLecturer("courses", "Course game");
		?>

		<div class="container bodycontent mt-4">
			<div class="coursetitle">
				<?php 
					// this section uses result from the top of this code
					// it lists all gamified courses where the lecturer is in charge
					echo 'Course: <select name="course" id="course" class="form-control courseselect" onchange="updateDisplayedGameDataBasedOnCourse()">';
					while($row = $result->fetch_assoc()) {
						// set the courseID if it has not been set
						if($courseID == null)
							$courseID = $row['course_id'];
						
						// echo all of the options
						echo "<option value=\"".$row['course_id']."\" ";
						// for selected entry, get also the prize text data
						if($courseID == $row['course_id']){
							echo "selected ";
							$prizeText = $row['prize_text'];
						}
						echo ">";
						echo $row['name']." </option>";
						
						
						
					}
					echo "</select>";
				?>
			</div>
			
			<div class="row d-flex justify-content-center mt-4" style="min-height:10vh">
				<div class="col-md-12 mb-3">
					<div class="row d-flex justify-content-center">
						<div class="col-md-12 fs-2 fw-bold mb-2 text-center"> 
							Leaderboard 
						</div>
						<div class="col-md-12">
							<div class="tablecontainer">
								<table id="leaderboard" class="table table-bordered table-striped responsive nowrap"  style="width:100%">
									<thead>
										<tr>
											<th style='width:5%'>Rank</th>
											<th>Student</th>
											<th>General</th>
											<th>Timeliness</th>
											<th>Decisiveness</th>
											<th>Quality</th>
											<th>Efficiency</th>
										</tr>
									</thead>
									<tbody>
                                    <?php
                                    $students = array();
                                    
                                    // Ambil semua siswa yang ikut serta dalam game
                                    $sql = "SELECT user.username, user.name, game_student_course.gs_id, game_student_course.student_id 
                                            FROM game_student_course 
                                            INNER JOIN user ON user.user_id = game_student_course.student_id 
                                            WHERE game_student_course.course_id = '".$courseID."' 
                                            AND game_student_course.is_participating = 1";
                                    
                                    $result = mysqli_query($db, $sql);
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            // Hindari duplikasi username
                                            if (in_array($row['username'], array_column($students, 'username'))) {
                                                continue;
                                            }
                                    
                                            $myTimelinessPoints = 0;
                                            $myEfficiencyPoints = 0;
                                            $myQualityPoints = 0;
                                            $myDecisivePoints = 0;
                                    
                                            // Ambil nilai submission
                                            $sqlt = "SELECT MAX(submission.attempt) as maxattempt,
                                                            ROUND(MAX((assessment.submission_close_time - submission.submission_time)/(assessment.submission_close_time - assessment.submission_open_time)*100),0) as mintime,
                                                            ROUND(AVG(suspicion.efficiency_point),0) as eff, 
                                                            ROUND(AVG(code_clarity_suggestion.quality_point),0) as qual
                                                     FROM suspicion  
                                                     INNER JOIN submission ON submission.submission_id = suspicion.submission_id 
                                                     INNER JOIN user ON user.user_id = submission.submitter_id 
                                                     INNER JOIN assessment ON assessment.assessment_id = submission.assessment_id 
                                                     INNER JOIN course ON course.course_id = assessment.course_id 
                                                     LEFT JOIN code_clarity_suggestion ON code_clarity_suggestion.submission_id = submission.submission_id 
                                                     WHERE submission.submitter_id = '".$row['student_id']."' 
                                                     AND course.course_id = '".$courseID."'
                                                     GROUP BY assessment.assessment_id";
                                    
                                            $resultt = mysqli_query($db, $sqlt);
                                            if ($resultt->num_rows > 0) {
                                                while ($rowt = $resultt->fetch_assoc()) {
                                                    if($rowt['qual'] == NULL)
                                                        $rowt['qual'] = 100;
                                                    if($rowt['mintime'] < 0){
                                                        $rowt['mintime'] = 0;
                                                    }
                                                    $myDecisivePoints += round(100/$rowt['maxattempt']);
                                                    $myTimelinessPoints += $rowt['mintime'];
                                                    $myEfficiencyPoints += $rowt['eff'];
                                                    $myQualityPoints += $rowt['qual'];
                                                }
                                            }
                                    
                                            // // Ambil jumlah jawaban benar dalam 6 bulan terakhir
                                            // $sqlt = "SELECT COUNT(question_id) AS tot 
                                            //          FROM instant_quiz_response_history
                                            //          WHERE student_id = '".$row['student_id']."' 
                                            //          AND is_correct = 1 
                                            //          AND response_time > DATE_SUB(now(), INTERVAL 6 MONTH)";
                                    
                                            // $resultt = mysqli_query($db, $sqlt);
                                            // $rowt = $resultt->fetch_assoc();
                                            // $myPeerReviewPoints = $rowt['tot'] * 100;
                                            
                                    
                                            // Hitung total poin
                                            $totalPoints = $myTimelinessPoints + $myEfficiencyPoints + $myQualityPoints + $myDecisivePoints;
                                    
                                            // Masukkan data ke array jika total poin tidak nol
                                            if ($totalPoints != 0) {
                                                $students[] = [
                                                    'student_id' => $row['student_id'],
                                                    'username' => $row['username'],
                                                    'name' => $row['name'],
                                                    'totalPoints' => $totalPoints,
                                                    'submissionPoints' => $myTimelinessPoints,
                                                    'qualityPoints' => $myQualityPoints,
                                                    'efficiencyPoints' => $myEfficiencyPoints,
                                                    'decisivePoints' => $myDecisivePoints
                                                ];
                                            }
                                        }
                                    }
                                    
                                    // Urutkan array berdasarkan totalPoints secara descending
                                    usort($students, function ($a, $b) {
                                        return $b['totalPoints'] <=> $a['totalPoints'];
                                    });
                                    
                                    // Tampilkan hasil yang telah diurutkan
                                    $counter = 1;
                                    foreach ($students as $student) {
                                        echo "<tr class=\"content\" id=\"".$student['student_id']."\" onclick=\"selectRow('".$student['student_id']."','sumtablecontent')\">
                                                <td style='width:5%'>".$counter."</td>
                                                <td>".$student['username']." / ".$student['name']."</td>
                                                <td>".$student['totalPoints']."</td>
                                                <td>".$student['submissionPoints']."</td>
                                                <td>".$student['decisivePoints']."</td>
                                                <td>".$student['qualityPoints']."</td>
                                                <td>".$student['efficiencyPoints']."</td>
                                              </tr>";
                                        $counter++;
                                    }
                                    ?>

									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="row d-flex justify-content-center mt-4">
				<div class="col-md-12 fs-2 mb-2 fw-bold text-center"> 
					Game Description 
				</div>
				<div class="col-md-12">
					<div class="gameexplanation" style="font-size:0.9em"> 
						<?php 
							
							echo "<p><b>Prize</b>: ".$prizeText."</p>";
							
							// showing general rules how to obtain points in preferred language
							if($human_language == 'en'){
								echo "<p>Students will obtain more game points by submitting high-quality and efficient programs as early as possible (timeliness) with fewer submission attempts (decisiveness). Submitting programs early means the students have good time management. 
								Fewer submission attempts means students only submit their work when it is ready.
								Having high-quality programs means the students know how to write maintainable programs. Having efficient programs means students know how to write environment-friendly programs. 
								The points will be averaged if students do multiple submissions for a particular assessment. </p>
								<p>Students can also get more points by giving peer reviews or receiving good reviews about their programs if asked by the instructors.</p>";
								echo '<p>Students can turn off the game feature. Their points will be hidden from anyone (but still recorded so the students can rejoin at any time without losing any points). </p>';
							}else{
								echo "<p>Siswa akan mendapatkan poin permainan lebih dengan mengumpulkan program yang berkualitas tinggi dan efisien sedini mungkin (timeliness) dengan pengumpulan sesedikit mungkin (decisiveness). Mengumpulkan program sedini mungkin berarti siswa terkait memiliki manajemen waktu yang baik. 
								Jumlah pengumpulan tugas yang sedikit berarti siswa hanya mengumpulkan tugas jika memang sudah siap.
								Memiliki program berkualitas tinggi berarti siswa terkait mengerti cara menulis program yang dapat dipelihara. Memiliki program efisien berarti siswa terkait mengerti cara menulis program yang ramah lingkungan. 
								Poin-poin tersebut akan direrata jika siswanya memiliki beberapa program untuk sebuah tugas. </p>
								<p>Siswa juga dapat memperoleh poin lebih dengan memberikan review pada program rekan atau menerima review baik terkait programnya jika diminta oleh pengajar.</p>";
								echo '<p>Siswa dapat mematikan fitur permainan. Poin nya akan disembunyikan dari siswa lain (namun tetap disimpan sehingga siswa dapat ikut kembali tanpa kehilangan poin). </p>';
							}
						?>
					</div>
				</div>
			</div>

	<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
	<script>
		new DataTable('#leaderboard', {
			responsive: true,
			pageLength: 5,
   			lengthMenu: [5, 10, 15, 25, 50],
		});
	</script>
  </body>
</html>