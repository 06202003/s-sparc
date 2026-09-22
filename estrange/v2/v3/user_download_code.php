<?php
// this page downloads a submission.

include("_sessionchecker.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // proceed only if it is landed from post form

  include("_config.php");

  if (!isset($_POST['id']) || empty($_POST['id'])) {
      echo "<script>alert('Submission ID is missing.'); window.history.back();</script>";
      exit;
  }

  // get submission id
  $id = mysqli_real_escape_string($db, $_POST['id']);

  // get filename and filepath
  $sqlt = "SELECT filename, file_path FROM submission WHERE submission_id = '".$id."'";
  $resultt = mysqli_query($db, $sqlt);
  
  if (!$resultt || mysqli_num_rows($resultt) == 0) {
      echo "<script>alert('Submission not found in database.'); window.history.back();</script>";
      exit;
  }

  $rowt = $resultt->fetch_assoc();
  $rawFilePath = $rowt['file_path'];
  $filename = !empty($rowt['filename']) ? $rowt['filename'] : basename($rawFilePath);

  // Search candidate paths to resolve relative vs absolute directory differences
  $foundPath = null;
  if (!empty($rawFilePath)) {
      $candidates = [
          $rawFilePath,
          __DIR__ . DIRECTORY_SEPARATOR . $rawFilePath,
          __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($rawFilePath),
          __DIR__ . DIRECTORY_SEPARATOR . 'submitted_files' . DIRECTORY_SEPARATOR . basename($rawFilePath),
          dirname(__DIR__) . DIRECTORY_SEPARATOR . $rawFilePath,
          dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($rawFilePath),
          dirname(__DIR__) . DIRECTORY_SEPARATOR . 'v3' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($rawFilePath),
          dirname(__DIR__) . DIRECTORY_SEPARATOR . 'v2' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($rawFilePath),
          dirname(__DIR__) . DIRECTORY_SEPARATOR . 'v1' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($rawFilePath),
          dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . $rawFilePath,
          getcwd() . DIRECTORY_SEPARATOR . $rawFilePath,
          getcwd() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($rawFilePath)
      ];

      foreach ($candidates as $cand) {
          if (!empty($cand) && file_exists($cand) && is_file($cand)) {
              $foundPath = $cand;
              break;
          }
      }
  }

  if ($foundPath !== null && is_readable($foundPath)) {
      // Clean any existing output buffer to avoid corrupted file download
      if (ob_get_level()) {
          ob_end_clean();
      }

      // set the metadata
      header('Content-Description: File Transfer');
      header('Content-Type: application/octet-stream');
      header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
      header('Expires: 0');
      header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
      header('Pragma: public');
      header('Content-Length: ' . filesize($foundPath));
      readfile($foundPath);
      exit;
  } else {
      echo "<script>alert('File tidak ditemukan di server untuk: " . addslashes(htmlspecialchars($filename)) . "'); window.history.back();</script>";
      exit;
  }

} else {
  // redirect if accessed directly
  if (isset($_SESSION['role'])) {
      if ($_SESSION['role'] == 'admin') {
          header('Location: admin_dashboard.php');
      } else if ($_SESSION['role'] == 'lecturer') {
          header('Location: lecturer_dashboard.php');
      } else if ($_SESSION['role'] == 'student') {
          header('Location: student_dashboard.php');
      }
  } else {
      header('Location: index.php');
  }
  exit;
}
?>
