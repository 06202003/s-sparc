<?php
include("_nosessionchecker_peer.php"); //"../_nosessionchecker.php"

// copied and modified from https://www.tutorialspoint.com/php/php_mysql_login.htm
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  include("_config_peer_review.php");

  $myusername = mysqli_real_escape_string($db, $_POST['uname']);
  $mypassword = mysqli_real_escape_string($db, $_POST['pwd']);

  $sql = "SELECT user_id, username, name, email, password, role FROM user WHERE username = '$myusername'";
  $result = mysqli_query($db, $sql);
  $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
  $count = mysqli_num_rows($result);

  if ($count == 1 && password_verify($mypassword, $row['password'])) {
      session_start();
      session_unset();

      $_SESSION['user_id'] = $row['user_id'];
      $_SESSION['username'] = $myusername;
      $_SESSION['name'] = $row['name'];
      $_SESSION['role'] = $row['role'];
      $_SESSION['sub_domain'] = "mcu";
      
      // Redirect sesuai peran pengguna
      if ($row['role'] == 'admin') {
          header('Location: admin_peer_dashboard.php'); //admin_dashboard.php
      } elseif ($row['role'] == 'lecturer') {
          header('Location: lecturer_peer_dashboard.php'); //lecturer_dashboard.php
      } elseif ($row['role'] == 'student') {
          header('Location: student_peer_dashboard.php'); //student_dashboard.php
      }
      exit;
  } else {
      $error = true;
  }
}
?>
<html>
  <head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
	<title> E-STRANGE </title>
	<link rel="icon" href="../strange_html_layout_additional_files/icon.png">
  <!-- Untuk Icon -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<!-- The use of Notyf library https://github.com/caroso1222/notyf -->
	<link rel="stylesheet" href="../strange_html_layout_additional_files/notyf.min.css">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
  <!-- <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet"> -->
  <script src="../strange_html_layout_additional_files/notyf.min.js"></script>
	<script type="text/javascript">
		function loadGameNotif(){
			// Create an instance of Notyf
			var notyf = new Notyf({
			  duration: 5000,
			  position: {
				x: 'center',
				y: 'top',
			  },
			  dismissible: true
			});
			
<?php
		 if(isset($_GET['err'])){ // if wrong username or password
			 echo "notyf.error('Incorrect username and/or password!');";
		 }else if(isset($_GET['errregis'])){ // if the registration link is invalid
			echo "notyf.error('The registration link is invalid! <br /> Please re-register your email');";
		 }else if(isset($_GET['update'])){ // landed from forgot password request page
			 echo "notyf.success('A password change link has been sent to your email!');";
		 }else if(isset($_GET['update2'])){ // landed after changing the password
			 echo "notyf.success('Your password has been changed!');";
		 }else if(isset($_GET['update3'])){ // landed after creating an account
			 echo "notyf.success('An account registration link has been sent to your email!');";
		 }else if(isset($_GET['update4'])){ // landed after changing the password
			 echo "notyf.success('Your account has been created!');";
		 }else if(isset($_GET['submit'])){ // landed from submitting the code without login
			 echo "notyf.success('Your code has been uploaded!');";
		 }else if(isset($_GET['nocoursesinvitee'])){
			 echo "notyf.error('There are no courses with public registration!');";
		 }else if(isset($_GET['invalidreport'])){
			 echo "notyf.error('Invalid link! Please log in and see the report in the submission tab!');";
		 }
	?>
		}
	</script>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300&display=swap');
    body {
      /* font-family: "Times New Roman", Times, serif; */
      font-family: 'Montserrat', sans-serif;
    }
    .singup {
      color: #000;
      text-transform: uppercase;
      text-decoration: none;
      font-size: 2rem;
    }

    .card {
      display: flex;
      justify-content: center;
      align-items: center;
	  height: auto;
      width: 80%;
      flex-direction: column;
      border: none;
	  color:#396ab3;
    }

    .inputBox,
    .inputBox1 {
      position: relative;
      width: 100%;
    }

    .inputBox input,
    .inputBox1 input {
      width: 100%;
      padding: 10px;
      outline: none;
      border: none;
      color: #396ab3;
      font-size: 1em;
      background: transparent;
      border: 2px solid #000;
      transition: 0.1s;
      border-radius: 8px;
    }

    .inputBox span,
    .inputBox1 span {
      margin-top: 5px;
      position: absolute;
      left: 0;
      transform: translateY(-4px);
      margin-left: 10px;
      padding: 10px;
      pointer-events: none;
      font-size: 12px;
      color: #396ab3;
      text-transform: uppercase;
      transition: 0.5s;
      letter-spacing: 3px;
      border-radius: 8px;
    }



    .inputBox input:valid,
    .inputBox input:focus,
    .inputBox1 input:valid,
    .inputBox1 input:focus {
      border: 2px solid #000;
      border-radius: 8px;
    }

    .enter {
      height: 45px;
      width: 100%;
      border-radius: 5px;
      border: 2px solid #000;
      cursor: pointer;
      background-color: transparent;
      transition: 0.5s;
      text-transform: uppercase;
      font-size: 16px;
      letter-spacing: 2px;
      margin-bottom: 3em;
	  color:#396ab3;
    }

    .enter:hover {
      background-color: #396ab3;
      color: white;
    }

	.youtube-video {
	 aspect-ratio: 16 / 9;
	 width: 80%;
	}
	.btn-primary{
			background: #00A0A5 !important ;
			color: white !important ;
		}
  @media (max-width: 425px) {
    
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
  <body onload="loadGameNotif()">
    <div class="container mt-5">
      <div class="row d-flex justify-content-center align-items-center" style="min-height: 80vh;"> 
        <div class="col-md-5">
          <!-- src="../strange_html_layout_additional_files/logo.png" -->
          <img src="../strange_html_layout_additional_files/logo.png" style="width: 100%; height: auto; min-height: 150px; min-width: 250px" alt="" />
          <h3><b>Educational Mode</b></h3>
          <h5>Maranatha Christian University</h5>
        </div>
        <div class="col-md-6 ">
          <form class="w-100" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="card mx-auto">
                  <a class="singup"><b>Sign In</b></a>

                  <div class="inputBox mt-3">
                    <label><h6>Username</h6></label>
                    <input class="w-100" type="text" required="required" name="uname" />
                  </div>

                  <div class="inputBox my-3">
                    <label><h6>Password</h6></label>
                    <input class="w-100" type="password" required="required"  name="pwd"/>
                  </div>

                  <button class="btn w-100 mt-2 mb-4 btn-primary" type="submit">Login</button>
                  <a class="btn btn-light border border-1 w-100 mb-2" href="student_registration.php">Student registration</a>
                  <a class="btn btn-light border border-1 w-100" href="forgot_password.php">Forgot password?</a>
                </div>

          </form>
        </div>
        <div class="col-md-12">
          <div class="information">
            <!--<b>Pemberitahuan:</b><br />-->
            <!--<ol>-->
            <!--  <li>Isu pada poin game telah diperbaiki. Poin seharusnya menjadi lebih fair.</li>-->
            <!--  <li>Sistem ini digunakan untuk pengumpulan tugas; poin game akan semakin tinggi jika kode unik, mudah dibaca, dan dikumpulkan cepat.</li>-->
            <!--  <li>Terdapat insentif bagi mahasiswa yang memperoleh poin game tertinggi (lihat detil di bagian game).</li>-->
            <!--  <li>Ada 1 pertanyaan kuis setiap minggu dengan bonus 100 poin game jika jawaban benar.</li>-->
            <!--  <li>Lolos dari deteksi kesamaan sistem <b>bukan berarti</b> bebas dari plagiarisme. Kode akan dicek kembali dengan sistem yang lebih efektif.</li>-->
            <!--  <li>-->
            <!--    Tutorial penggunaan e-strange dapat dilihat di   <a class="text-center" data-bs-toggle="offcanvas" href="#tutorial" role="button" aria-controls="tutorial">sini</a> sedangkan tutorial forwarding email dapat dilihat di-->
            <!--    <a class="text-center" data-bs-toggle="offcanvas" href="#tutorialemail" role="button" aria-controls="tutorial">sini</a>-->
            <!--  </li>-->
            <!--</ol>-->
          </div>
        </div>
      </div>
    </div>
	
	<!-- Offcanvas -->
	<div class="offcanvas offcanvas-bottom h-100" tabindex="-1" id="tutorial" aria-labelledby="offcanvasExampleLabel">
		<div class="offcanvas-header">
			<h5 class="offcanvas-title" id="offcanvasExampleLabel">Tutorial Penggunaan Estrange</h5>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
		</div>
		<div class="offcanvas-body text-center">
	
			<iframe class="youtube-video" src="https://www.youtube.com/embed/iC3VT7QG2Dc?si=9mNI943TCunPrzms" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>				
		</div>
	</div>

	<div class="offcanvas offcanvas-bottom h-100" tabindex="-1" id="tutorialemail" aria-labelledby="offcanvasExampleLabel">
		<div class="offcanvas-header">
			<h5 class="offcanvas-title" id="offcanvasExampleLabel">Tutorial Forwarding Email</h5>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
		</div>
		<div class="offcanvas-body text-center">
	
			<iframe class="youtube-video" src="https://www.youtube.com/embed/5tb8rAo5s_s?si=kESZ5KO7898rJViw" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>				
		</div>
	</div>

  <script>
      <?php if (isset($error) && $error == true) : ?>
          Swal.fire({
              icon: 'error',
              title: 'Oops...',
              text: 'Incorrect username and/or password!',
          });
      <?php endif; ?>
  </script>
		 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
     <!-- <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script> -->
    </body>

</html>
