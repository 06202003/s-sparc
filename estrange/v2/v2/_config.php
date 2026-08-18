<?php
// just a configuration for the database access
$servername = "127.0.0.1";
$username = "david";
$password = "david20juni2003#";
$dbname = "db_semantic_final";
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
        $sqlt = "SELECT suspicion.originality_point, suspicion.suspicion_id, suspicion.suspicion_type, assessment.name AS assessment_name, course.name AS course_name      
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
            $linkTarget = ($_SESSION['role'] == 'student') ? 'student_suspicion_sub.php?id=' : 'lecturer_suspicion_sub.php?id=';
            echo '<button class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm font-medium ' . $badgeClass . ' ' . $activeClass . ' transition shadow-xs" onclick="window.open(\'' . $linkTarget . $rowt["suspicion_id"] . '\', \'_self\');">Originality: '.$orig.'%</button>';
        }

        $sqlt = "SELECT code_clarity_suggestion.quality_point, code_clarity_suggestion.suggestion_id, assessment.name AS assessment_name, course.name AS course_name      
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
            echo '<button class="inline-flex items-center rounded-lg border px-3 py-1.5 text-sm font-medium ' . $badgeClass . ' ' . $activeClass . ' transition shadow-xs" onclick="window.open(\'' . $linkTarget . $submissionID . '\', \'_self\');">Quality: '.$qual.'%</button>';
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
