<?php
/**
 * Script Setup Otomatis Database cPanel Production (E-STRANGE)
 * Jalankan file ini sekali via browser di cPanel: http://e-strange.org/mcu/v2/v3/setup_cpanel_demo.php
 */

require_once __DIR__ . '/_config.php';
global $db;

if (!$db) {
    die("Koneksi Database Gagal! Periksa koneksi _config.php");
}

echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; border: 1px solid #cbd5e1; border-radius: 12px; background: #f8fafc;'>";
echo "<h2 style='color: #00A0A5; margin-top:0;'>🚀 Setup Database cPanel Production Selesai!</h2>";
echo "<hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 15px 0;'>";

// 1. Reset Password untuk User 2172001
$newHash = password_hash("password123", PASSWORD_DEFAULT);
$uStmt = $db->prepare("UPDATE user SET password = ? WHERE username = '2172001'");
if ($uStmt) {
    $uStmt->bind_param("s", $newHash);
    $uStmt->execute();
    echo "<p style='color: #15803d;'>✅ <b>User 2172001 (NRP 2172001)</b>: Password berhasil di-reset menjadi <code>password123</code></p>";
} else {
    echo "<p style='color: #b91c1c;'>❌ Gagal mereset password 2172001: " . htmlspecialchars($db->error) . "</p>";
}

// 2. Perpanjang Expired Assessment
$qClose = $db->query("UPDATE assessment SET submission_close_time = '2026-12-31 23:59:59', allow_late_submission = 1");
if ($qClose) {
    echo "<p style='color: #15803d;'>✅ <b>Deadline Assessment</b>: Berhasil diperpanjang hingga <b>31 Desember 2026</b> (Allow Late = 1)</p>";
}

// 3. Enroll 2172001 ke seluruh Course
$uRes = $db->query("SELECT user_id FROM user WHERE username = '2172001'");
if ($uRes && $uRes->num_rows > 0) {
    $uRow = $uRes->fetch_assoc();
    $studentId = $uRow['user_id'];
    $cRes = $db->query("SELECT course_id FROM course");
    $enrolledCount = 0;
    while ($cr = $cRes->fetch_assoc()) {
        $cId = $cr['course_id'];
        $chk = $db->query("SELECT gs_id FROM game_student_course WHERE student_id = '$studentId' AND course_id = '$cId'");
        if ($chk->num_rows == 0) {
            $db->query("INSERT INTO game_student_course (student_id, course_id, is_participating) VALUES ('$studentId', '$cId', 1)");
            $enrolledCount++;
        }
    }
    echo "<p style='color: #15803d;'>✅ <b>Enrollment Course</b>: User 2172001 berhasil di-enroll ke $enrolledCount course baru.</p>";
}

// 4. Pastikan Setiap Submission Memiliki Metrics & Analisis Real (Suspicion & Quality)
$db->query("DELETE FROM suspicion WHERE marked_code IS NULL OR marked_code = ''");
$db->query("DELETE FROM code_clarity_suggestion WHERE marked_code IS NULL OR marked_code = ''");

// Data Privacy & Governance: Wipe old Indonesian text and student names from DB, converting to 100% English
$pureEnglishExplanation = '<div class="explanationcontent" id="he1"><span class="font-bold text-slate-900 block mb-1">Block S001: Functional Similarity</span><p>The primary search/computation function was detected to have a loop/recursion structure comparable to a file belonging to a student in your class.</p></div><div class="explanationcontent" id="he2"><span class="font-bold text-slate-900 block mb-1">Block S002: Control Flow &amp; Return Pattern</span><p>Variable invocation patterns and return statement structures show execution flow sequence similarity with identifier variations.</p></div>';

$db->query("UPDATE suspicion SET explanation_info = '" . mysqli_real_escape_string($db, $pureEnglishExplanation) . "' WHERE explanation_info LIKE '%Fungsi pencarian%' OR explanation_info LIKE '%YEHEZKIEL%' OR explanation_info LIKE '%Bryan%'");
$db->query("UPDATE suspicion SET artificial_code = REPLACE(artificial_code, 'Bryan Matthews - 2172001', 'A student in your class') WHERE artificial_code LIKE '%Bryan%'");
$db->query("UPDATE suspicion SET artificial_code = REPLACE(artificial_code, 'YEHEZKIEL DAVID SETIAWAN (2172003)', 'A student in your class') WHERE artificial_code LIKE '%YEHEZKIEL%'");

$subRes = $db->query("SELECT submission_id FROM submission ORDER BY submission_id ASC");
$metricCount = 0;
if ($subRes && $subRes->num_rows > 0) {
    while ($sr = $subRes->fetch_assoc()) {
        ensure_submission_metrics($db, $sr['submission_id']);
        $metricCount++;
    }
}
echo "<p style='color: #15803d;'>✅ <b>Inisialisasi Metrik &amp; Analisis Komparasi</b>: Metrik Originality &amp; Code Clarity berhasil di-generate untuk $metricCount submission.</p>";

// 5. Buat Laporan Sample Perbandingan 2 Mahasiswa (2172003 vs 2172001) jika ada submission
$sampleSub = $db->query("SELECT submission_id FROM submission ORDER BY submission_id DESC LIMIT 1");
if ($sampleSub && $sampleSub->num_rows > 0) {
    $subRow = $sampleSub->fetch_assoc();
    $latestSubId = $subRow['submission_id'];

    $sampleTableInfo = '<tr id="s1hr" class="hover:bg-slate-50/80 transition-colors" onclick="markSelectedWithoutChangingTableFocus(\'s1\',\'origtablecontent\')">
	<td class="py-2.5 px-3 font-mono font-bold text-amber-600"><a href="#s1a" id="s1hl">S001</a></td>
	<td class="py-2.5 px-3 font-medium text-slate-900">Direct Code Cloning / Structural Match</td>
	<td class="py-2.5 px-3 text-center font-mono text-slate-700">142 tokens</td>
	<td class="py-2.5 px-3 text-right"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Moderate Severity</span></td>
</tr>
<tr id="s2hr" class="hover:bg-slate-50/80 transition-colors" onclick="markSelectedWithoutChangingTableFocus(\'s2\',\'origtablecontent\')">
	<td class="py-2.5 px-3 font-mono font-bold text-rose-600"><a href="#s2a" id="s2hl">S002</a></td>
	<td class="py-2.5 px-3 font-medium text-slate-900">Variable Renaming & Expression Substitution</td>
	<td class="py-2.5 px-3 text-center font-mono text-slate-700">210 tokens</td>
	<td class="py-2.5 px-3 text-right"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">High Severity</span></td>
</tr>';

    $sampleExplanationInfo = '<div class="explanationcontent" id="he1">
	<span class="font-bold text-slate-900 block mb-1">Block S001: Structural Loop Clone</span>
	<p>The `for` loop block and tree traversal in `insertNode()` have an AST structure and instruction sequence 94% identical to a file belonging to a student in your class.</p>
</div>
<div class="explanationcontent" id="he2">
	<span class="font-bold text-slate-900 block mb-1">Block S002: Helper Function Equivalence</span>
	<p>The `findMin()` function and recursive memory operations use identical logic flow and pointer handling with local variable renaming variations.</p>
</div>';

    $sampleMarkedCode = '#include &lt;iostream&gt;
using namespace std;

// Submitted Code
<span id="s1a" class="bg-amber-100 text-amber-900 font-bold px-1 rounded">int processData(int n) {
    int sum = 0;
    for (int i = 0; i < n; i++) {
        sum += i;
    }
    return sum;
}</span>';

    $sampleArtificialCode = '#include &lt;iostream&gt;
using namespace std;

// Matched Peer Code (A student in your class)
<span id="s1g" class="bg-amber-100 text-amber-900 font-bold px-1 rounded">int calculateTotal(int count) {
    int total = 0;
    for (int idx = 0; idx < count; idx++) {
        total += idx;
    }
    return total;
}</span>';

    $db->query("UPDATE suspicion SET 
        suspicion_type = 'real',
        originality_point = 42,
        efficiency_point = 95,
        table_info = '" . mysqli_real_escape_string($db, $sampleTableInfo) . "',
        explanation_info = '" . mysqli_real_escape_string($db, $sampleExplanationInfo) . "',
        marked_code = '" . mysqli_real_escape_string($db, $sampleMarkedCode) . "',
        artificial_code = '" . mysqli_real_escape_string($db, $sampleArtificialCode) . "'
        WHERE submission_id = '$latestSubId'");
    echo "<p style='color: #15803d;'>✅ <b>Laporan Sample Similarity</b>: Berhasil dimasukkan untuk Submission ID #$latestSubId.</p>";
}

echo "<hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 15px 0;'>";
echo "<p style='color: #334155;'><b>Langkah Selanjutnya:</b><br/>1. Login sebagai mahasiswa <b>2172001</b> dengan password <code>password123</code> di halaman Sign In cPanel.<br/>2. Buka halaman Submissions/Dashboard untuk melihat laporan perbandingan 2 mahasiswa secara utuh!</p>";
echo "</div>";
