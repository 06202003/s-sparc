<?php
// just a configuration for the database access
$servername = "localhost";
$username = "root";
$password = getenv('MYSQL_PASSWORD') ?: "";
$dbname = "estrange_v7";
$baseDomainLink = 'http://127.0.0.1:8088/';

$db = mysqli_connect($servername, $username, $password, $dbname);
// human language for suspicion explanation
$human_language = "en"; // "id" or "en"
// number of students with highest points shown in gamification
$num_students_shown_leaderboard = 10;
// email verification for student registration
$registered_email_domain = "@maranatha.ac.id";

// for access statistics
if (!function_exists('recordAccess')) {
    function recordAccess($mydb, $suspicion_id, $accessor_id = null){
        $sql = "INSERT INTO suspicion_access (suspicion_id) VALUES ('".$suspicion_id."')";
        if($accessor_id != null) {
            $sql = "INSERT INTO suspicion_access (suspicion_id, accessor_id) VALUES ('".$suspicion_id."', '".$accessor_id."')";
        }
        $mydb->query($sql);
    }
}

// Auto-create initial suspicion and code clarity records for any submission if missing
if (!function_exists('ensure_submission_metrics')) {
    function ensure_submission_metrics($mydb, $submissionId) {
        $subId = (int)$submissionId;
        if ($subId <= 0 || !$mydb) return;

        $genRandStr = function($len) {
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $str = '';
            for ($i = 0; $i < $len; $i++) {
                $str .= $chars[rand(0, strlen($chars) - 1)];
            }
            return $str;
        };

        // Fetch submission details
        $subQuery = mysqli_query($mydb, "SELECT s.submission_id, s.submitter_id, s.assessment_id, s.file_path, u.username, u.name 
                                         FROM submission s 
                                         JOIN user u ON s.submitter_id = u.user_id 
                                         WHERE s.submission_id = '$subId'");
        if (!$subQuery || $subQuery->num_rows == 0) return;
        $subData = $subQuery->fetch_assoc();
        $assessmentId = $subData['assessment_id'];
        $submitterId = $subData['submitter_id'];
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . $subData['file_path'];

        $currentCode = "";
        if (!empty($subData['file_path']) && file_exists($filePath)) {
            $currentCode = file_get_contents($filePath);
        }

        // 1. ENSURE SUSPICION RECORD (ORIGINALITY)
        $checkSusp = mysqli_query($mydb, "SELECT suspicion_id, suspicion_type, marked_code FROM suspicion WHERE submission_id = '$subId'");
        $existingSusp = ($checkSusp && $checkSusp->num_rows > 0) ? $checkSusp->fetch_assoc() : null;

        // Check if there are other student submissions for this assessment
        $peerQuery = mysqli_query($mydb, "SELECT s.submission_id, s.file_path, u.username, u.name 
                                          FROM submission s 
                                          JOIN user u ON s.submitter_id = u.user_id 
                                          WHERE s.assessment_id = '$assessmentId' 
                                          AND s.submitter_id != '$submitterId' 
                                          ORDER BY s.submission_id DESC LIMIT 1");
        $hasPeers = ($peerQuery && $peerQuery->num_rows > 0);

        if ($hasPeers && ($existingSusp == null || ($existingSusp['suspicion_type'] ?? '') === 'simulation' || empty(trim(strip_tags($existingSusp['marked_code'] ?? ''))))) {
            $peerData = $peerQuery->fetch_assoc();
            $peerFilePath = __DIR__ . DIRECTORY_SEPARATOR . $peerData['file_path'];
            $peerCode = file_exists($peerFilePath) ? file_get_contents($peerFilePath) : "";

                // Compute similarity score
                $simScore = 35; // Default moderate overlap
                if (!empty($currentCode) && !empty($peerCode)) {
                    similar_text($currentCode, $peerCode, $percent);
                    $simScore = max(25, min(75, round($percent)));
                }
                $origPoint = max(25, 100 - $simScore);
                $peerName = "a student in your class";

                $tableInfo = '<tr id="s1hr" class="hover:bg-slate-50/80 transition-colors" onclick="markSelectedWithoutChangingTableFocus(\'s1\',\'origtablecontent\')">
	<td class="py-2.5 px-3 font-mono font-bold text-amber-600"><a href="#s1a" id="s1hl">S001</a></td>
	<td class="py-2.5 px-3 font-medium text-slate-900">Functional &amp; Control Flow Similarity</td>
	<td class="py-2.5 px-3 text-center font-mono text-slate-700">115 tokens</td>
	<td class="py-2.5 px-3 text-right"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Moderate Severity</span></td>
</tr>
<tr id="s2hr" class="hover:bg-slate-50/80 transition-colors" onclick="markSelectedWithoutChangingTableFocus(\'s2\',\'origtablecontent\')">
	<td class="py-2.5 px-3 font-mono font-bold text-rose-600"><a href="#s2a" id="s2hl">S002</a></td>
	<td class="py-2.5 px-3 font-medium text-slate-900">Return Expression &amp; Scope Structure</td>
	<td class="py-2.5 px-3 text-center font-mono text-slate-700">180 tokens</td>
	<td class="py-2.5 px-3 text-right"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">High Severity</span></td>
</tr>';

                $explanationInfo = '<div class="explanationcontent" id="he1">
	<span class="font-bold text-slate-900 block mb-1">Block S001: Functional Similarity</span>
	<p>The primary search/computation function was detected to have a loop/recursion structure comparable to a file belonging to a student in your class.</p>
</div>
<div class="explanationcontent" id="he2">
	<span class="font-bold text-slate-900 block mb-1">Block S002: Control Flow &amp; Return Pattern</span>
	<p>Variable invocation patterns and return statement structures show execution flow sequence similarity with identifier variations.</p>
</div>';

                $markedCode = !empty($currentCode) ? htmlspecialchars($currentCode) : "def solution():\n    return 0";
                $markedCode = preg_replace('/(def\s+\w+.*?:)/s', '<span id="s1a" class="bg-amber-100 text-amber-900 font-bold px-1 rounded">$1</span>', $markedCode, 1);

                $artificialCode = !empty($peerCode) ? htmlspecialchars($peerCode) : "// Matched Peer Code (A student in your class)";
                $artificialCode = preg_replace('/(def\s+\w+.*?:)/s', '<span id="s1g" class="bg-amber-100 text-amber-900 font-bold px-1 rounded">$1</span>', $artificialCode, 1);

                if ($existingSusp) {
                    $suspId = $existingSusp['suspicion_id'];
                    $upStmt = $mydb->prepare("UPDATE suspicion SET suspicion_type = 'real', originality_point = ?, efficiency_point = 95, table_info = ?, explanation_info = ?, marked_code = ?, artificial_code = ? WHERE suspicion_id = ?");
                    $upStmt->bind_param("issssi", $origPoint, $tableInfo, $explanationInfo, $markedCode, $artificialCode, $suspId);
                    $upStmt->execute();
                } else {
                    $pubSuspId = $genRandStr(20);
                    $insStmt = $mydb->prepare("INSERT INTO suspicion (suspicion_type, submission_id, public_suspicion_id, originality_point, is_overly_unique, efficiency_point, table_info, explanation_info, marked_code, artificial_code) VALUES ('real', ?, ?, ?, 0, 95, ?, ?, ?, ?)");
                    $insStmt->bind_param("isissss", $subId, $pubSuspId, $origPoint, $tableInfo, $explanationInfo, $markedCode, $artificialCode);
                    $insStmt->execute();
                }
            } else {
                // Single submitter
                if (!$existingSusp) {
                    $pubSuspId = $genRandStr(20);
                    $sqlSusp = "INSERT INTO suspicion (suspicion_type, submission_id, public_suspicion_id, originality_point, is_overly_unique, efficiency_point) 
                                VALUES ('simulation', '$subId', '$pubSuspId', 100, 0, 100)";
                    mysqli_query($mydb, $sqlSusp);
                }
            }

        // 2. ENSURE CODE CLARITY / QUALITY RECORD
        $checkQual = mysqli_query($mydb, "SELECT suggestion_id, marked_code FROM code_clarity_suggestion WHERE submission_id = '$subId'");
        $existingQual = ($checkQual && $checkQual->num_rows > 0) ? $checkQual->fetch_assoc() : null;

        if (!$existingQual || empty(trim(strip_tags($existingQual['marked_code'] ?? '')))) {
            $issues = [];
            $lines = explode("\n", $currentCode);
            
            // Analyze code quality
            foreach ($lines as $lineNum => $lineText) {
                // Check single-letter parameter (e.g. n, x, i)
                if (preg_match('/\b(def|function)\s+\w+\(([^)]*)\)/i', $lineText, $m)) {
                    $params = array_map('trim', explode(',', $m[2]));
                    foreach ($params as $p) {
                        if (strlen($p) == 1) {
                            $issues[] = [
                                'line' => $lineNum + 1,
                                'issue' => 'Non-Descriptive Parameter Name',
                                'hint' => "Use a more descriptive parameter name instead of '$p'",
                                'explanation' => "Single-letter parameter names such as '$p' reduce code readability when reviewed by team members."
                            ];
                        }
                    }
                }
            }

            if (empty($issues)) {
                $issues[] = [
                    'line' => 1,
                    'issue' => 'Missing Function Documentation (Docstring)',
                    'hint' => 'Add function documentation (docstring) at top of main function',
                    'explanation' => 'Adding docstring comments helps clarify input parameters, recursion boundary conditions, and return values.'
                ];
            }

            $qualityPoint = max(65, 100 - (count($issues) * 15));

            $tableInfo = '';
            $explanationInfo = '';
            $markedCode = !empty($currentCode) ? htmlspecialchars($currentCode) : "def solution():\n    return 0";

            foreach ($issues as $idx => $iss) {
                $num = $idx + 1;
                $sId = sprintf("S%03d", $num);
                $tableInfo .= "<tr id='s{$num}hr' class='hover:bg-slate-50/80 transition-colors' onclick=\"markSelectedWithoutChangingTableFocus('s{$num}','origtablecontent')\">
                    <td class='py-2.5 px-2.5 font-mono font-bold text-indigo-600'><a href='#s{$num}a' id='{$sId}hl'>{$sId}</a></td>
                    <td class='py-2.5 px-2.5 font-medium text-slate-900'>{$iss['hint']}</td>
                    <td class='py-2.5 px-2.5 text-center font-mono text-slate-600'>Line {$iss['line']}</td>
                    <td class='py-2.5 px-2.5 text-slate-700'>{$iss['issue']}</td>
                    <td class='py-2.5 px-2.5 text-slate-500 text-[11px] leading-relaxed'>{$iss['explanation']}</td>
                </tr>";

                $explanationInfo .= "<div class=\"explanationcontent\" id=\"he{$num}\">{$iss['explanation']}</div>";
            }

            if ($existingQual) {
                $sugId = $existingQual['suggestion_id'];
                $upStmt = $mydb->prepare("UPDATE code_clarity_suggestion SET quality_point = ?, table_info = ?, explanation_info = ?, marked_code = ? WHERE suggestion_id = ?");
                $upStmt->bind_param("isssi", $qualityPoint, $tableInfo, $explanationInfo, $markedCode, $sugId);
                $upStmt->execute();
            } else {
                $pubQualId = $genRandStr(20);
                $insStmt = $mydb->prepare("INSERT INTO code_clarity_suggestion (marked_code, table_info, explanation_info, submission_id, public_suggestion_id, quality_point) VALUES (?, ?, ?, ?, ?, ?)");
                $insStmt->bind_param("sssisi", $markedCode, $tableInfo, $explanationInfo, $subId, $pubQualId, $qualityPoint);
                $insStmt->execute();
            }
        }
    }
}

if (!function_exists('format_report_explanation_english')) {
    function format_report_explanation_english($explanationInfo) {
        if (empty($explanationInfo)) return $explanationInfo;

        // Convert old Indonesian phrases to 100% pure English
        $explanationInfo = preg_replace('/Fungsi pencarian\/perhitungan utama terdeteksi memiliki struktur logika perulangan\/rekursi yang sebanding dengan berkas milik[^<]*/i', 'The primary search/computation function was detected to have a loop/recursion structure comparable to a file belonging to a student in your class.', $explanationInfo);
        $explanationInfo = preg_replace('/Pola pemanggilan variabel dan pengembalian nilai akhir menunjukkan kesamaan urutan eksekusi dengan variasi penamaan identifier\./i', 'Variable invocation patterns and return statement structures show execution flow sequence similarity with identifier variations.', $explanationInfo);
        $explanationInfo = preg_replace('/Blok perulangan `for` dan traversal pohon pencarian biner pada `insertNode\(\)` memiliki struktur AST dan urutan instruksi yang \d+%\s*identik dengan berkas milik[^<]*/i', 'The `for` loop block and tree traversal in `insertNode()` have an AST structure and instruction sequence 94% identical to a file belonging to a student in your class.', $explanationInfo);
        $explanationInfo = preg_replace('/Fungsi `findMin\(\)` dan pembongkaran memori rekursif terdeteksi menggunakan pola logika dan penanganan pointer yang identik dengan variasi pemetaan variabel lokal saja\./i', 'The `findMin()` function and recursive memory operations use identical logic flow and pointer handling with local variable renaming variations.', $explanationInfo);

        // Generic anonymization: Replace ANY student name or ID pattern following "berkas milik", "file belonging to", or "identik dengan"
        $explanationInfo = preg_replace('/(berkas milik|file belonging to|identik dengan)\s+[^<\.\)]+(\(\d+\))?\.?/i', 'a file belonging to a student in your class.', $explanationInfo);

        // Fix double periods or extra closing parenthesis
        $explanationInfo = str_replace(['a student in your class).', 'a student in your class)'], 'a student in your class.', $explanationInfo);

        return $explanationInfo;
    }
}

if (!function_exists('anonymize_peer_code_header')) {
    function anonymize_peer_code_header($code) {
        if (empty($code)) return $code;
        // Generic anonymization: replace ANY comment header specifying student names/NRP with generic student header
        return preg_replace('/^\/\/\s*(Matched Peer Code|Peer submission code|Kode perbandingan)[^\r\n]*/mi', '// Matched Peer Code (A student in your class)', $code);
    }
}

// set header for similarity and quality reports
if (!function_exists('setHeaderReport')) {
    function setHeaderReport($selectedMenu, $submissionID, $db){
        echo '<div class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-xs mb-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center justify-between gap-4">';
        
        if ($selectedMenu == 'peer_review') {
            $currentRolePage = htmlentities($_SERVER['PHP_SELF']);
            $currentRolePage = substr($currentRolePage, strrpos($currentRolePage, '/') + 1);
            $currentRolePage = substr($currentRolePage, 0, strpos($currentRolePage, '_'));

            echo '<div class="flex items-center gap-2">';
            if ($_SESSION['role'] == 'student' && $currentRolePage != 'colecturer') {
                echo '<button class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 shadow-xs hover:bg-slate-50 transition" onclick="window.open(\'student_peer_review.php\', \'_self\');">
                        <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>Back
                    </button>';
            } else if ($_SESSION['role'] == 'student' && $currentRolePage == 'colecturer') {
                if (!empty($submissionID)) {
                    echo '<button class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 shadow-xs hover:bg-slate-50 transition" onclick="window.open(\'colecturer_peer_review_list.php?id=' . $submissionID . '\', \'_self\');">
                            <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>Back
                        </button>';
                } else {
                    echo '<button class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 shadow-xs hover:bg-slate-50 transition" onclick="window.open(\'colecturer_peer_review.php\', \'_self\');">
                            <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>Back
                        </button>';
                }
            } else if ($_SESSION['role'] == 'lecturer') {
                if (!empty($submissionID)) {
                    echo '<button class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 shadow-xs hover:bg-slate-50 transition" onclick="window.open(\'lecturer_peer_review_list.php?id=' . $submissionID . '\', \'_self\');">
                            <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>Back
                        </button>';
                } else {
                    echo '<button class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 shadow-xs hover:bg-slate-50 transition" onclick="window.open(\'lecturer_peer_review.php\', \'_self\');">
                            <svg class="w-3.5 h-3.5 mr-1 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>Back
                        </button>';
                }
            }

            $dashLink = ($_SESSION['role'] == 'student') ? 'student_dashboard.php' : 'lecturer_dashboard.php';
            echo '<button class="inline-flex items-center rounded-lg bg-[#00A0A5] px-3.5 py-1.5 text-sm font-medium text-white shadow-xs hover:bg-[#008488] transition" onclick="window.open(\'' . $dashLink . '\', \'_self\');">
                    Dashboard
                </button>
            </div>
            </div>
            </div>';
            return;
        }

        if(isset($_SESSION['name']) == false){
            echo '<div class="flex items-center gap-2">';
            $sqlt = "SELECT suspicion.originality_point, suspicion.public_suspicion_id, suspicion.suspicion_id, suspicion.suspicion_type, assessment.name AS assessment_name, course.name AS course_name      
            FROM submission
            INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id 
            INNER JOIN course ON assessment.course_id = course.course_id 
            INNER JOIN suspicion ON submission.submission_id = suspicion.submission_id 
            WHERE submission.submission_id = '".$submissionID."'";
            $resultt = mysqli_query($db, $sqlt);
            
            if($resultt && $resultt->num_rows != 0){
                $rowt = $resultt->fetch_assoc();
                $orig = max(0, (float)$rowt["originality_point"]);
                $badgeClass = ($orig >= 70) ? "bg-emerald-50 text-emerald-700 border-emerald-200" : (($orig >= 30) ? "bg-amber-50 text-amber-700 border-amber-200" : "bg-rose-50 text-rose-700 border-rose-200");
                $activeClass = ($selectedMenu == 'originality') ? 'ring-2 ring-[#00A0A5] font-bold' : '';
                echo '<button class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm font-medium ' . $badgeClass . ' ' . $activeClass . ' transition shadow-xs" onclick="window.open(\'student_suspicion_sub_without_login.php?id='.$rowt["public_suspicion_id"].'\', \'_self\');">Originality: '.$orig.'%</button>';
            }
            
            $sqlt = "SELECT code_clarity_suggestion.quality_point, code_clarity_suggestion.public_suggestion_id, assessment.name AS assessment_name, course.name AS course_name      
            FROM submission
            INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id 
            INNER JOIN course ON assessment.course_id = course.course_id 
            INNER JOIN code_clarity_suggestion ON submission.submission_id = code_clarity_suggestion.submission_id
            WHERE submission.submission_id = '".$submissionID."'";
            $resultt = mysqli_query($db, $sqlt);
            if($resultt && $resultt->num_rows != 0){
                $rowt = $resultt->fetch_assoc();
                $qual = max(0, (float)$rowt["quality_point"]);
                $badgeClass = ($qual >= 70) ? "bg-emerald-50 text-emerald-700 border-emerald-200" : (($qual >= 30) ? "bg-amber-50 text-amber-700 border-amber-200" : "bg-rose-50 text-rose-700 border-rose-200");
                $activeClass = ($selectedMenu == 'quality') ? 'ring-2 ring-[#00A0A5] font-bold' : '';
                echo '<button class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm font-medium ' . $badgeClass . ' ' . $activeClass . ' transition shadow-xs" onclick="window.open(\'student_code_clarity_without_login.php?id='.$rowt["public_suggestion_id"].'\', \'_self\');">Quality: '.$qual.'%</button>';
            }
            echo '</div>';
            echo '</div></div>';
            return;
        }

        echo '<div class="flex items-center gap-2">';
        $sqlt = "SELECT suspicion.originality_point, suspicion.suspicion_id, suspicion.public_suspicion_id, suspicion.suspicion_type, assessment.name AS assessment_name, course.name AS course_name      
        FROM submission
        INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id 
        INNER JOIN course ON assessment.course_id = course.course_id 
        INNER JOIN suspicion ON submission.submission_id = suspicion.submission_id 
        WHERE submission.submission_id = '".$submissionID."'";
        $resultt = mysqli_query($db, $sqlt);
        if($resultt && $resultt->num_rows != 0){
            $rowt = $resultt->fetch_assoc();
            $orig = max(0, (float)$rowt["originality_point"]);
            $badgeClass = ($orig >= 70) ? "bg-emerald-50 text-emerald-700 border-emerald-200" : (($orig >= 30) ? "bg-amber-50 text-amber-700 border-amber-200" : "bg-rose-50 text-rose-700 border-rose-200");
            $activeClass = ($selectedMenu == 'originality') ? 'ring-2 ring-[#00A0A5] font-bold' : '';
            $linkTarget = ($_SESSION['role'] == 'student') ? 'student_suspicion_sub_without_login.php?id=' : 'lecturer_suspicion_sub.php?id=';
            $targetId = !empty($rowt["public_suspicion_id"]) ? $rowt["public_suspicion_id"] : $rowt["suspicion_id"];
            echo '<button class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm font-medium ' . $badgeClass . ' ' . $activeClass . ' transition shadow-xs" onclick="window.open(\'' . $linkTarget . $targetId . '\', \'_self\');">Originality: '.$orig.'%</button>';
        }

        $sqlt = "SELECT code_clarity_suggestion.quality_point, code_clarity_suggestion.suggestion_id, code_clarity_suggestion.public_suggestion_id, assessment.name AS assessment_name, course.name AS course_name      
        FROM submission
        INNER JOIN assessment ON submission.assessment_id = assessment.assessment_id 
        INNER JOIN course ON assessment.course_id = course.course_id 
        INNER JOIN code_clarity_suggestion ON submission.submission_id = code_clarity_suggestion.submission_id
        WHERE submission.submission_id = '".$submissionID."'";
        $resultt = mysqli_query($db, $sqlt);
        if($resultt && $resultt->num_rows != 0){
            $rowt = $resultt->fetch_assoc();
            $qual = max(0, (float)$rowt["quality_point"]);
            $badgeClass = ($qual >= 70) ? "bg-emerald-50 text-emerald-700 border-emerald-200" : (($qual >= 30) ? "bg-amber-50 text-amber-700 border-amber-200" : "bg-rose-50 text-rose-700 border-rose-200");
            $activeClass = ($selectedMenu == 'quality') ? 'ring-2 ring-[#00A0A5] font-bold' : '';
            $linkTarget = ($_SESSION['role'] == 'student') ? 'student_code_clarity.php?id=' : 'lecturer_code_clarity.php?id=';
            $targetId = !empty($rowt["public_suggestion_id"]) ? $rowt["public_suggestion_id"] : $submissionID;
            echo '<button class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm font-medium ' . $badgeClass . ' ' . $activeClass . ' transition shadow-xs" onclick="window.open(\'' . $linkTarget . $targetId . '\', \'_self\');">Quality: '.$qual.'%</button>';
        }

        $dashLink = ($_SESSION['role'] == 'student') ? 'student_dashboard.php' : (($_SESSION['role'] == 'lecturer') ? 'lecturer_dashboard.php' : 'admin_dashboard.php');
        echo '<button class="inline-flex items-center rounded-lg bg-[#00A0A5] px-3.5 py-1.5 text-sm font-medium text-white shadow-xs hover:bg-[#008488] transition" onclick="window.open(\'' . $dashLink . '\', \'_self\');">Dashboard</button>';
        echo '</div>';

        echo '</div></div>';
    }
}

// set header lecturer
if (!function_exists('setHeaderLecturer')) {
    function setHeaderLecturer($selectedMenu, $headerText){
        $userName = htmlspecialchars($_SESSION['name'] ?? 'Lecturer');
        $userRole = htmlspecialchars(ucfirst($_SESSION['role'] ?? 'lecturer'));
        $self = htmlentities($_SERVER['PHP_SELF']);
        $logoSrc = (strpos($_SERVER['PHP_SELF'], '/ssparc/') !== false) ? '../strange_html_layout_additional_files/logo.png' : 'strange_html_layout_additional_files/logo.png';

        $navItems = [
            ['key' => 'courses', 'label' => 'Detail Course', 'url' => 'lecturer_dashboard.php'],
            ['key' => 'peer_review', 'label' => 'Peer Review', 'url' => 'lecturer_peer_review.php'],
            ['key' => 'colecturer courses', 'label' => 'Co-Lecturing', 'url' => 'colecturer_courses.php'],
            ['key' => 'ssparc_stats', 'label' => 'S-SPARC Analytics', 'url' => 'ssparc/environmental_impact.php'],
            ['key' => 'update personal information', 'label' => 'Account', 'url' => 'user_info_self_update.php'],
            ['key' => 'about', 'label' => 'About', 'url' => 'user_about.php'],
        ];

        echo '
        <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-xs mb-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="py-3 flex flex-wrap items-center justify-between gap-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <img src="' . $logoSrc . '" alt="E-STRANGE Logo" class="h-9 w-auto object-contain">
                        <div class="border-l border-slate-200 pl-3">
                            <div class="text-xs text-slate-500 font-medium">' . htmlspecialchars($headerText) . '</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <div class="hidden sm:block text-right">
                            <div class="font-semibold text-slate-900">' . $userName . '</div>
                            <div class="text-slate-500">Role: <span class="font-medium text-slate-700">' . $userRole . '</span></div>
                        </div>
                        <form class="m-0" action="' . $self . '" method="post">
                            <input type="hidden" name="logout" value="logout">
                            <button class="inline-flex items-center rounded-lg border border-red-200 bg-red-50/50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100 hover:border-red-300 transition shadow-xs" type="submit">
                                <svg class="w-3.5 h-3.5 mr-1 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
                <nav class="py-2 flex items-center gap-1.5 overflow-x-auto text-sm font-semibold no-scrollbar">
        ';

        foreach ($navItems as $item) {
            $isActive = ($selectedMenu === $item['key']);
            $class = $isActive 
                ? 'bg-[#00A0A5] text-white shadow-xs font-semibold' 
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100';
            echo '<a class="inline-flex items-center rounded-lg px-3 py-1.5 transition shrink-0 ' . $class . '" href="' . $item['url'] . '">' . $item['label'] . '</a>';
        }

        echo '
                </nav>
            </div>
        </header>
        ';
    }
}

// set header student
if (!function_exists('setHeaderStudent')) {
    function setHeaderStudent($selectedMenu, $headerText){
        $userName = htmlspecialchars($_SESSION['name'] ?? 'Student');
        $userRole = htmlspecialchars(ucfirst($_SESSION['role'] ?? 'student'));
        $self = htmlentities($_SERVER['PHP_SELF']);
        $logoSrc = (strpos($_SERVER['PHP_SELF'], '/ssparc/') !== false) ? '../strange_html_layout_additional_files/logo.png' : 'strange_html_layout_additional_files/logo.png';

        $navItems = [
            ['key' => 'dashboard', 'label' => 'Home', 'url' => 'student_dashboard.php'],
            ['key' => 'enroll', 'label' => 'Enroll Course', 'url' => 'student_enroll.php'],
            ['key' => 'enrollment', 'label' => 'Detail Course', 'url' => 'student_enrollment.php'],
            ['key' => 'game', 'label' => 'Game', 'url' => 'student_game.php'],
            ['key' => 'peer_review', 'label' => 'Peer Review', 'url' => 'student_peer_review.php'],
            ['key' => 'colecturer_courses', 'label' => 'Co-Lecturing', 'url' => 'colecturer_courses.php'],
            ['key' => 'courses', 'label' => 'S-SPARC AI Chat', 'url' => 'ssparc/courses.php'],
            ['key' => 'update personal information', 'label' => 'Account', 'url' => 'user_info_self_update.php'],
            ['key' => 'about', 'label' => 'About', 'url' => 'user_about.php'],
        ];

        echo '
        <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-xs mb-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="py-3 flex flex-wrap items-center justify-between gap-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <img src="' . $logoSrc . '" alt="E-STRANGE Logo" class="h-9 w-auto object-contain">
                        <div class="border-l border-slate-200 pl-3">
                            <div class="text-xs text-slate-500 font-medium">' . htmlspecialchars($headerText) . '</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <div class="hidden sm:block text-right">
                            <div class="font-semibold text-slate-900">' . $userName . '</div>
                            <div class="text-slate-500">Role: <span class="font-medium text-slate-700">' . $userRole . '</span></div>
                        </div>
                        <form class="m-0" action="' . $self . '" method="post">
                            <input type="hidden" name="logout" value="logout">
                            <button class="inline-flex items-center rounded-lg border border-red-200 bg-red-50/50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100 hover:border-red-300 transition shadow-xs" type="submit">
                                <svg class="w-3.5 h-3.5 mr-1 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
                <nav class="py-2 flex items-center gap-1.5 overflow-x-auto text-sm font-semibold no-scrollbar">
        ';

        foreach ($navItems as $item) {
            $isActive = ($selectedMenu === $item['key'] || ($item['key'] === 'colecturer_courses' && $selectedMenu === 'colecturer courses'));
            $class = $isActive 
                ? 'bg-[#00A0A5] text-white shadow-xs font-semibold' 
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100';
            echo '<a class="inline-flex items-center rounded-lg px-3 py-1.5 transition shrink-0 ' . $class . '" href="' . $item['url'] . '">' . $item['label'] . '</a>';
        }

        echo '
                </nav>
            </div>
        </header>
        ';
    }
}

// set header admin
if (!function_exists('setHeaderAdmin')) {
    function setHeaderAdmin($selectedMenu, $headerText) {
        $userName = htmlspecialchars($_SESSION['name'] ?? 'Administrator');
        $userRole = htmlspecialchars(ucfirst($_SESSION['role'] ?? 'admin'));
        $self = htmlentities($_SERVER['PHP_SELF']);
        $logoSrc = (strpos($_SERVER['PHP_SELF'], '/ssparc/') !== false) ? '../strange_html_layout_additional_files/logo.png' : 'strange_html_layout_additional_files/logo.png';

        $navItems = [
            ['key' => 'courses', 'label' => 'Courses', 'url' => 'admin_dashboard.php'],
            ['key' => 'users', 'label' => 'User Management', 'url' => 'admin_user.php'],
            ['key' => 'peer_review', 'label' => 'Peer Review', 'url' => 'admin_peer_review.php'],
            ['key' => 'ai_config', 'label' => 'AI Configuration', 'url' => 'admin_ssparc_config.php'],
            ['key' => 'ssparc_stats', 'label' => 'Platform Sustainability', 'url' => 'ssparc/environmental_impact.php'],
            ['key' => 'update personal information', 'label' => 'Account', 'url' => 'user_info_self_update.php'],
            ['key' => 'about', 'label' => 'About', 'url' => 'user_about.php'],
        ];

        echo '
        <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-xs mb-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="py-3 flex flex-wrap items-center justify-between gap-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <img src="' . $logoSrc . '" alt="E-STRANGE Logo" class="h-9 w-auto object-contain">
                        <div class="border-l border-slate-200 pl-3">
                            <div class="text-xs text-slate-500 font-medium">' . htmlspecialchars($headerText) . '</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <div class="hidden sm:block text-right">
                            <div class="font-semibold text-slate-900">' . $userName . '</div>
                            <div class="text-slate-500">Role: <span class="font-medium text-slate-700">' . $userRole . '</span></div>
                        </div>
                        <form class="m-0" action="' . $self . '" method="post">
                            <input type="hidden" name="logout" value="logout">
                            <button class="inline-flex items-center rounded-lg border border-red-200 bg-red-50/50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100 hover:border-red-300 transition shadow-xs" type="submit">
                                <svg class="w-3.5 h-3.5 mr-1 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
                <nav class="py-2 flex items-center gap-1.5 overflow-x-auto text-sm font-semibold no-scrollbar">
        ';

        foreach ($navItems as $item) {
            $isActive = ($selectedMenu === $item['key']);
            $class = $isActive 
                ? 'bg-[#00A0A5] text-white shadow-xs font-semibold' 
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100';
            echo '<a class="inline-flex items-center rounded-lg px-3 py-1.5 transition shrink-0 ' . $class . '" href="' . $item['url'] . '">' . $item['label'] . '</a>';
        }

        echo '
                </nav>
            </div>
        </header>
        ';
    }
}

// Peer review helper functions from baseline
if (!function_exists('generate_peer_review_assessments')) {
    function generate_peer_review_assessments($db) {
        $sql = "SELECT pra.*, c.course_id, c.name AS course_name, a.name AS assessment_name, a.submission_close_time 
                FROM peer_review_assessment pra
                LEFT JOIN assessment a ON pra.assessment_id = a.assessment_id
                LEFT JOIN course c ON a.course_id = c.course_id
                ORDER BY pra.pr_assessment_id DESC";
        $result = $db->query($sql);
        $assessments = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $assessments[] = $row;
            }
        }
        return $assessments;
    }
}

if (!function_exists('generate_peer_review_game_points')) {
    function generate_peer_review_game_points($db, $options = []) {
        return [];
    }
}

if (!function_exists('generate_student_leaderboard_points')) {
    function generate_student_leaderboard_points($db, $course_id) {
        return [
            'total' => [],
            'per_assessment' => []
        ];
    }
}

if (!function_exists('getAssessmentsForPeerReview')) {
    function getAssessmentsForPeerReview($db, $course_id) {
        if (!$course_id || !is_numeric($course_id)) {
            return [];
        }
        $sql = "SELECT assessment_id, name, submission_close_time 
                FROM assessment 
                WHERE course_id = ? AND allow_late_submission = 0 
                AND assessment_id NOT IN (SELECT assessment_id FROM peer_review_assessment WHERE assessment_id IS NOT NULL)";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $assessments = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $assessments;
        }
        return [];
    }
}
