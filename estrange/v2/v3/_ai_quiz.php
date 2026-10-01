<?php

if (!function_exists('load_app_env')) {
    function load_app_env($path)
    {
        static $loaded = [];
        if (isset($loaded[$path])) {
            return;
        }
        $loaded[$path] = true;
        if (!is_readable($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
                $value = trim($value, "\"'");
            }
            if ($name !== '' && getenv($name) === false) {
                putenv($name . '=' . $value);
            }
        }
    }
}

load_app_env(__DIR__ . DIRECTORY_SEPARATOR . '.env');
load_app_env(__DIR__ . DIRECTORY_SEPARATOR . 'env');
load_app_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
load_app_env(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . '.env');
load_app_env(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . '.env');

function set_quiz_failed($db, $submissionId, $errorMsg)
{
    $stmt = $db->prepare("UPDATE generated_quizzes SET status = 'failed', error_message = ? WHERE submission_id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $errorMsg, $submissionId);
        $stmt->execute();
        $stmt->close();
    }
}

function get_quiz_env($name, $default = '')
{
    $value = getenv($name);
    return $value === false ? $default : trim($value);
}

function get_gemini_keys()
{
    $keys = [];
    for ($index = 1; $index <= 4; $index++) {
        $key = get_quiz_env('GEMINI_API_KEY_' . $index);
        if ($key !== '') {
            $keys[] = $key;
        }
    }
    return $keys;
}

function extract_code_from_zip_recursive(ZipArchive $zip, $prefix = '')
{
    $combined = '';
    $tempDir = sys_get_temp_dir();

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entryName = $zip->getNameIndex($index);
        if ($entryName === false || $entryName === '') {
            continue;
        }

        if (substr($entryName, -1) === '/') {
            continue;
        }

        $lowerName = strtolower($entryName);
        $realName = basename($entryName);

        if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) === 'zip') {
            $tmpFile = tempnam($tempDir, 'submission_zip_');
            if ($tmpFile === false) {
                $combined .= "--- Error: Failed to create temp file for nested zip: " . htmlspecialchars($realName) . " ---\n\n";
                continue;
            }

            $zipContent = $zip->getFromIndex($index);
            if ($zipContent !== false && file_put_contents($tmpFile, $zipContent) !== false) {
                $innerZip = new ZipArchive();
                if ($innerZip->open($tmpFile) === true) {
                    $combined .= "--- ZIP: " . htmlspecialchars($realName) . " ---\n\n";
                    $combined .= extract_code_from_zip_recursive($innerZip, $entryName . '/');
                    $innerZip->close();
                    $combined .= "--- END ZIP: " . htmlspecialchars($realName) . " ---\n\n";
                } else {
                    $combined .= "--- Error: Failed to open nested zip: " . htmlspecialchars($realName) . " ---\n\n";
                }
                @unlink($tmpFile);
            } else {
                $combined .= "--- Error: Failed to extract nested zip: " . htmlspecialchars($realName) . " ---\n\n";
                if (file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
            }

            continue;
        }

        if (!preg_match('/\.(py|java|c|cpp|cc|h|hpp|cs|js|ts|php|html|css|sql|json|xml|yaml|yml|md|txt)$/i', $entryName)) {
            continue;
        }

        $fileContent = $zip->getFromName($entryName);
        if ($fileContent === false) {
            continue;
        }

        $combined .= "--- File: " . htmlspecialchars($realName) . " ---\n\n";
        $combined .= $fileContent . "\n\n";
    }

    return $combined;
}

function read_submission_source_code($filePath, $originalFilename)
{
    if (!is_readable($filePath)) {
        return '';
    }

    $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

    if ($extension === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $content = extract_code_from_zip_recursive($zip);
            $zip->close();
            return $content;
        }
        return '';
    }

    $raw = file_get_contents($filePath);
    if ($raw === false) {
        return '';
    }

    return $raw;
}

function extract_gemini_text($response)
{
    $decoded = json_decode($response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/i', '', $text);
    return trim($text);
}

function validate_quiz_payload($payload)
{
    if (!is_array($payload) || !isset($payload['questions']) || !is_array($payload['questions']) || count($payload['questions']) !== 3) {
        return false;
    }
    foreach ($payload['questions'] as $question) {
        if (!is_array($question) || trim((string)($question['question'] ?? '')) === '') {
            return false;
        }
        if (!isset($question['options']) || !is_array($question['options']) || count($question['options']) !== 4) {
            return false;
        }
        if (!in_array(strtoupper((string)($question['correct_option'] ?? '')), ['A', 'B', 'C', 'D'], true)) {
            return false;
        }
    }
    return true;
}

function detect_quiz_language($submission = [], $rawCode = '')
{
    // 1. Deteksi Negara dari Cloudflare / GeoIP cPanel
    $countryCode = strtoupper(trim(
        $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 
        $_SERVER['GEOIP_COUNTRY_CODE'] ?? 
        $_SERVER['HTTP_X_COUNTRY_CODE'] ?? 
        $_SERVER['HTTP_X_GEOIP_COUNTRY'] ?? 
        ''
    ));

    if (!empty($countryCode) && $countryCode !== 'XX') {
        // Jika IP berasal dari Indonesia, gunakan Bahasa Indonesia
        if ($countryCode === 'ID') {
            return 'id';
        }
        // Jika IP berasal dari luar negeri (US, SG, AU, dll), gunakan Bahasa Inggris
        return 'en';
    }

    // 2. Deteksi dari Locale / Bahasa Browser Mahasiswa (HTTP_ACCEPT_LANGUAGE)
    $acceptLang = strtolower(trim($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    if (!empty($acceptLang)) {
        if (preg_match('/(^|,|\s)(id|id-id|in-id|in)(;|,|$)/i', $acceptLang)) {
            return 'id';
        }
    }

    // 3. Deteksi dari Konteks Tugas/Kode jika ada penanda kuat
    $contextText = ($submission['course_name'] ?? '') . ' ' . ($submission['assessment_name'] ?? '');
    if (!empty($rawCode)) {
        $contextText .= ' ' . substr($rawCode, 0, 500);
    }
    $lowerText = strtolower($contextText);
    if (preg_match('/\b(tugas|praktikum|pertemuan|fungsi|variabel|struktur data|algoritma)\b/i', $lowerText)) {
        return 'id';
    }

    // 4. Fallback: Baca settingan $human_language dari _config.php tanpa memodifikasi global
    global $human_language;
    if (!empty($human_language)) {
        return strtolower(trim($human_language));
    }
    if (!empty($GLOBALS['human_language'])) {
        return strtolower(trim($GLOBALS['human_language']));
    }

    $configPath = __DIR__ . DIRECTORY_SEPARATOR . '_config.php';
    if (file_exists($configPath) && is_readable($configPath)) {
        $content = file_get_contents($configPath);
        if ($content !== false && preg_match('/\$human_language\s*=\s*["\']([^"\']+)["\']/i', $content, $matches)) {
            return strtolower(trim($matches[1]));
        }
    }

    $envLang = getenv('HUMAN_LANGUAGE');
    if (!empty($envLang)) {
        return strtolower(trim($envLang));
    }

    return 'en';
}

function generate_submission_quiz($db, $submissionId, $studentId)
{
    $stmt = $db->prepare('SELECT s.file_path, s.filename, a.name AS assessment_name, a.description AS assessment_description, c.name AS course_name, u.username FROM submission s INNER JOIN assessment a ON a.assessment_id = s.assessment_id INNER JOIN course c ON c.course_id = a.course_id INNER JOIN user u ON u.user_id = s.submitter_id WHERE s.submission_id = ? AND s.submitter_id = ?');
    if (!$stmt) {
        $err = 'Database query failed.';
        set_quiz_failed($db, $submissionId, $err);
        return ['ok' => false, 'error' => $err];
    }
    $stmt->bind_param('ii', $submissionId, $studentId);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $codePath = $submission ? $submission['file_path'] : '';
    $foundPath = null;

    if ($codePath !== '') {
        $candidates = [
            $codePath,
            __DIR__ . DIRECTORY_SEPARATOR . $codePath,
            __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($codePath),
            __DIR__ . DIRECTORY_SEPARATOR . 'submitted_files' . DIRECTORY_SEPARATOR . basename($codePath),
            dirname(__DIR__) . DIRECTORY_SEPARATOR . $codePath,
            dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . $codePath,
            dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . $codePath,
            getcwd() . DIRECTORY_SEPARATOR . $codePath,
            getcwd() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($codePath),
            getcwd() . DIRECTORY_SEPARATOR . 'submitted_files' . DIRECTORY_SEPARATOR . basename($codePath)
        ];
        foreach ($candidates as $cand) {
            if (!empty($cand) && is_readable($cand) && is_file($cand)) {
                $foundPath = $cand;
                break;
            }
        }
    }

    if (!$submission || !$foundPath) {
        $err = 'Submission code cannot be read or found on server.';
        set_quiz_failed($db, $submissionId, $err);
        return ['ok' => false, 'error' => $err];
    }

    $keys = get_gemini_keys();
    if (!$keys) {
        $err = 'Gemini API key is not configured in .env or env file.';
        set_quiz_failed($db, $submissionId, $err);
        return ['ok' => false, 'error' => $err];
    }

    $rawCode = read_submission_source_code($foundPath, $submission['filename']);

    // Deteksi bahasa khusus AI Quiz: negara -> browser locale -> konteks tugas -> config fallback
    $quizLanguage = detect_quiz_language($submission, $rawCode);
    $isEnglish = ($quizLanguage === 'en');
    if ($rawCode === '') {
        $err = $isEnglish ? 'Submission file does not contain readable source code.' : 'File submission tidak berisi source code yang dapat dibaca.';
        set_quiz_failed($db, $submissionId, $err);
        return ['ok' => false, 'error' => $err];
    }
    // Konversi string ke UTF-8 valid agar json_encode tidak gagal
    $code = mb_convert_encoding($rawCode, 'UTF-8', 'UTF-8');
    $code = substr($code, 0, 50000);

    // Pre-prompt / System Instruction untuk AI
    if ($isEnglish) {
        $systemPreprompt = "You are an expert Computer Science educator and automated assessment verification assistant.\n"
            . "Your goal is to verify that the student actually wrote and understands the code they submitted.\n\n"
            . "STRICT INSTRUCTIONS:\n"
            . "1. LANGUAGE: You MUST write all questions, options, and text strictly in ENGLISH.\n"
            . "2. CODE GROUNDING: Generate exactly 3 multiple-choice questions that can ONLY be answered by carefully inspecting the provided student code. Every question must refer to actual variables, functions, loop bounds, conditions, recursion logic, or state flows present in the code.\n"
            . "3. NO GENERIC THEORY: Never ask generic programming questions, syntax definitions, or theoretical textbook questions.\n"
            . "4. OPTIONS: Each question must have exactly 4 options (A, B, C, D) with 1 correct option and 3 plausible but incorrect distractors.\n"
            . "5. FORMAT: Return strictly valid JSON adhering to the specified schema with no markdown or additional conversational text.";

        $userPrompt = "Course: " . $submission['course_name'] . "\n"
            . "Assignment: " . $submission['assessment_name'] . "\n"
            . "Submitter: " . $submission['username'] . "\n\n"
            . "Generate 3 verification multiple-choice questions in English based exclusively on this student code:\n\n"
            . "```\n" . $code . "\n```\n\n"
            . "Return strictly the following JSON structure:\n"
            . "{\n"
            . '  "questions": [' . "\n"
            . '    {"question": "...", "options": {"A": "...", "B": "...", "C": "...", "D": "..."}, "correct_option": "A"}' . "\n"
            . "  ]\n"
            . "}";
    } else {
        $systemPreprompt = "Anda adalah asisten dosen Ilmu Komputer dan validator otomatis kode tugas mahasiswa.\n"
            . "Tujuan Anda adalah memverifikasi bahwa mahasiswa benar-benar menulis dan memahami kode yang mereka kumpulkan.\n\n"
            . "PETUNJUK KRUSIAL:\n"
            . "1. BAHASA: Anda WAJIB menyusun seluruh pertanyaan, pilihan jawaban, dan teks dalam BAHASA INDONESIA.\n"
            . "2. BERAKAR PADA KODE: Buatkan tepat 3 pertanyaan pilihan ganda yang HANYA dapat dijawab dengan membaca kode mahasiswa yang dilampirkan. Setiap soal harus merujuk langsung pada nama variabel, fungsi, kondisi logika, batas perulangan, rekursi, atau alur instruksi asli di kode.\n"
            . "3. HINDARI TEORI UMUM: Jangan menanyakan pengertian umum bahasa pemrograman, teori buku, atau konsep materi di luar konteks kode.\n"
            . "4. OPSI: Setiap soal harus memiliki 4 opsi (A, B, C, D) dengan 1 jawaban benar dan 3 opsi pengecoh yang masuk akal namun salah.\n"
            . "5. FORMAT: Kembalikan murni format JSON sesuai skema yang diminta tanpa pembungkus markdown atau teks tambahan.";

        $userPrompt = "Mata kuliah: " . $submission['course_name'] . "\n"
            . "Tugas: " . $submission['assessment_name'] . "\n"
            . "Pengirim: " . $submission['username'] . "\n\n"
            . "Buatkan 3 pertanyaan verifikasi pilihan ganda dalam Bahasa Indonesia yang berakar langsung pada kode mahasiswa berikut:\n\n"
            . "```\n" . $code . "\n```\n\n"
            . "Kembalikan format JSON persis seperti berikut:\n"
            . "{\n"
            . '  "questions": [' . "\n"
            . '    {"question": "...", "options": {"A": "...", "B": "...", "C": "...", "D": "..."}, "correct_option": "A"}' . "\n"
            . "  ]\n"
            . "}";
    }

    // Susun payload dengan system_instruction (preprompt) dan contents
    $bodyData = [
        'system_instruction' => [
            'parts' => [
                ['text' => $systemPreprompt]
            ]
        ],
        'contents' => [
            [
                'parts' => [
                    ['text' => $userPrompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'responseMimeType' => 'application/json'
        ]
    ];
    
    $body = json_encode($bodyData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if (!$body) {
        $err = $isEnglish ? 'Failed to construct JSON payload for AI API.' : 'Gagal menyusun payload JSON untuk AI API.';
        set_quiz_failed($db, $submissionId, $err);
        return ['ok' => false, 'error' => $err];
    }

    $model = get_quiz_env('GEMINI_MODEL', 'gemini-3.1-flash-lite');
    $lastError = $isEnglish ? 'Gemini request failed.' : 'Permintaan ke Gemini gagal.';

    foreach ($keys as $key) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            $lastError = 'cURL Error: ' . ($curlError ?: ($isEnglish ? 'Failed to connect to Gemini API.' : 'Gagal terhubung ke Gemini API.'));
            continue;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorResponse = json_decode((string)$response, true);
            $apiMessage = $errorResponse['error']['message'] ?? '';
            $lastError = 'Gemini API Error (' . $httpCode . '): ' . ($apiMessage ?: ($isEnglish ? 'Request rejected.' : 'Permintaan ditolak.'));
            continue;
        }

        $extractedText = extract_gemini_text($response);
        $payload = json_decode($extractedText, true);

        if (!validate_quiz_payload($payload)) {
            $lastError = $isEnglish ? 'JSON format from Gemini does not match specification.' : 'Format JSON dari Gemini tidak sesuai spesifikasi.';
            continue;
        }

        $db->begin_transaction();
        try {
            $delete = $db->prepare('DELETE FROM generated_quiz_questions WHERE quiz_id = (SELECT quiz_id FROM generated_quizzes WHERE submission_id = ?)');
            $delete->bind_param('i', $submissionId);
            $delete->execute();
            $delete->close();

            $insert = $db->prepare('INSERT INTO generated_quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) SELECT quiz_id, ?, ?, ?, ?, ?, ? FROM generated_quizzes WHERE submission_id = ?');
            foreach ($payload['questions'] as $question) {
                $options = $question['options'];
                $text = trim($question['question']);
                $correct = strtoupper(trim($question['correct_option']));
                $insert->bind_param('ssssssi', $text, $options['A'], $options['B'], $options['C'], $options['D'], $correct, $submissionId);
                $insert->execute();
            }
            $insert->close();

            $update = $db->prepare("UPDATE generated_quizzes SET status = 'ready', error_message = NULL, quiz_started_at = NULL, quiz_expires_at = NULL, answered_at = NULL, score_points = NULL WHERE submission_id = ?");
            $update->bind_param('i', $submissionId);
            $update->execute();
            $update->close();

            $db->commit();
            return ['ok' => true];
        } catch (Throwable $exception) {
            $db->rollback();
            return ['ok' => false, 'error' => ($isEnglish ? 'Failed to save quiz to database: ' : 'Gagal menyimpan quiz ke database: ') . $exception->getMessage()];
        }
    }

    $stmt = $db->prepare("UPDATE generated_quizzes SET status = 'failed', error_message = ? WHERE submission_id = ?");
    $stmt->bind_param('si', $lastError, $submissionId);
    $stmt->execute();
    $stmt->close();
    return ['ok' => false, 'error' => $lastError];
}

function create_submission_quiz($db, $submissionId, $studentId)
{
    $penalty = (float)get_quiz_env('QUIZ_PENALTY_POINTS', '0');
    $stmt = $db->prepare('INSERT INTO generated_quizzes (submission_id, student_id, penalty_points) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE student_id = VALUES(student_id), status = \'pending\', penalty_points = VALUES(penalty_points), error_message = NULL, quiz_started_at = NULL, quiz_expires_at = NULL, answered_at = NULL, score_points = NULL');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iid', $submissionId, $studentId, $penalty);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function abort_submission_quiz($db, $quizId)
{
    $stmt = $db->prepare("UPDATE generated_quizzes SET score_points = 0, answered_at = NOW() WHERE quiz_id = ? AND answered_at IS NULL");
    if ($stmt) {
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $stmt->close();
    }
}
