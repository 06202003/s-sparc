<?php
	// default template
	include("_sessionchecker.php");
	include("_config.php");

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: student_peer_review.php");
    exit();
}

$pr_submission_id = filter_input(INPUT_POST, 'pr_submission_id', FILTER_VALIDATE_INT);
$review_score = filter_input(INPUT_POST, 'review_score', FILTER_VALIDATE_INT);
$review_description = $_POST['review_description'] ?? '';

// Jika ada data yang tidak valid (misal: ID bukan angka), hentikan proses
if ($pr_submission_id === false || $review_score === false) {
    die("Data not valid");
}

// Validasi jumlah karakter minimal (tidak termasuk spasi)
$minChars = ($review_score < 60) ? (100 - $review_score) : 0;
$descNoSpace = preg_replace('/\s+/', '', $review_description);

if (strlen($descNoSpace) < $minChars) {
    header("Location: student_review_submit.php?id=" . $pr_submission_id . "&error=minchar&review_score=" . $review_score);
    exit();
}

// Mulai transaksi. Ini memastikan semua query berhasil atau semua gagal.
$db->begin_transaction();

try {
    //Query untuk INSERT data review baru ke dalam tabel `peer_review`
    $sql_insert = "INSERT INTO peer_review (pr_submission_id, review_score, review_description , review_time) VALUES (?, ?, ?, NOW())";
    
    $stmt_insert = $db->prepare($sql_insert);
    // "iis" -> i: integer, i: integer, s: string
    $stmt_insert->bind_param("iis", $pr_submission_id, $review_score, $review_description);
    
    // Eksekusi query insert
    $stmt_insert->execute();

    // Periksa apakah insert gagal
    if ($stmt_insert->affected_rows !== 1) {
        throw new Exception("Failed to save review");
    }
    $stmt_insert->close();

    // Query untuk UPDATE status di tabel `peer_review_submission`
    $sql_update = "UPDATE peer_review_submission SET review_status = 1 WHERE pr_submission_id = ?";

    $stmt_update = $db->prepare($sql_update);
    $stmt_update->bind_param("i", $pr_submission_id);

    // Eksekusi query update
    $stmt_update->execute();
    
    // Periksa apakah update gagal
    if ($stmt_update->errno) { 
        throw new Exception("Failed to update submission status"); 
    }
    $stmt_update->close();
    $db->commit();

    // Mengarah kembali ke halaman dasbor dengan pesan sukses
    header("Location: student_peer_review.php?status=review_success");
    exit();

} catch (Exception $e) {
    // Jika ada error di salah satu query, batalkan semua perubahan (rollback)
    $db->rollback();
    
    //  Mengarah kembali ke halaman form dengan pesan error umum
    header("Location: student_review_submit.php?id=$pr_submission_id&error=minchar&score=$review_score");
    exit();
}
?>
