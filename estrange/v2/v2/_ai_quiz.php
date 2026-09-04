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
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);$text = preg_replace('/\s*```$/i', '', $text);
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

function generate_submission_quiz($db, $submissionId, $studentId)
{
    $stmt = $db->prepare('SELECT s.file_path, s.filename, a.name AS assessment_name, c.name AS course_name, u.username FROM submission s INNER JOIN assessment a ON a.assessment_id = s.assessment_id INNER JOIN course c ON c.course_id = a.course_id INNER JOIN user u ON u.user_id = s.submitter_id WHERE s.submission_id = ? AND s.submitter_id = ?');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database query failed.'];
    }
    $stmt->bind_param('ii', $submissionId, $studentId);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $codePath = $submission ? $submission['file_path'] : '';
    if ($codePath !== '' && !preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $codePath)) {
        $codePath = __DIR__ . DIRECTORY_SEPARATOR . $codePath;
    }
    if (!$submission || !is_readable($codePath)) {
        return ['ok' => false, 'error' => 'Submission code cannot be read.'];
    }

    $keys = get_gemini_keys();
    if (!$keys) {
        return ['ok' => false, 'error' => 'Gemini API key is not configured in .env.'];
    }

    $rawCode = read_submission_source_code($codePath, $submission['filename']);
    if ($rawCode === '') {
        return ['ok' => false, 'error' => 'File submission tidak berisi source code yang dapat dibaca.'];
    }
    // Konversi string ke UTF-8 valid agar json_encode tidak gagal
    $code = mb_convert_encoding($rawCode, 'UTF-8', 'UTF-8');
    $code = substr($code, 0, 50000);

    $prompt = "Buatkan tepat 3 pertanyaan pilihan ganda dalam bahasa Indonesia yang sangat spesifik dan hanya dapat dijawab dengan membaca kode yang dibuat mahasiswa berikut.\n"
        . "Jangan membuat pertanyaan umum tentang materi atau konsep teori; semua pertanyaan harus berakar pada kode yang diberikan.\n"
        . "Mata kuliah: " . $submission['course_name'] . "\n"
        . "Tugas: " . $submission['assessment_name'] . "\n"
        . "Pengirim: " . $submission['username'] . "\n\n"
        . "Aturan penting:\n"
        . "1. Pertanyaan harus merujuk pada variabel, fungsi, kondisi, perulangan, input/output, state, atau alur logika yang benar-benar ada di kode mahasiswa.\n"
        . "2. Pilihan jawaban harus dibuat berdasarkan detail kode yang terlihat, bukan definisi umum.\n"
        . "3. Hindari pertanyaan tentang pengertian umum bahasa pemrograman, konsep teori, atau materi kuliah secara luas.\n"
        . "4. Fokus pada apa yang dilakukan kode, mengapa kondisi tertentu ada, hasil dari sebuah fungsi, atau dampak dari pernyataan tertentu.\n"
        . "5. Setiap soal harus memiliki 4 opsi (A, B, C, D) dan hanya 1 opsi benar.\n"
        . "6. Opsi yang salah harus masuk akal jika seseorang tidak membaca kode dengan teliti, tetapi jelas keliru saat dibandingkan dengan kode asli.\n"
        . "7. Gunakan nama variabel, fungsi, dan struktur yang ada di kode agar soal terasa spesifik terhadap karya mahasiswa tersebut.\n"
        . "8. Jangan gunakan teks tambahan, jangan beri penjelasan, dan jangan sertakan markdown.\n"
        . "Kembalikan format JSON persis seperti contoh berikut:\n"
        . "{\n"
        . '  "questions": [' . "\n"
        . '    {"question": "...", "options": {"A": "...", "B": "...", "C": "...", "D": "..."}, "correct_option": "A"}' . "\n"
        . "  ]\n"
        . "}\n\n"
        . "Kode Tugas Mahasiswa:\n" . $code;

    // Tambahkan opsi penanganan UTF-8 invalid saat encode JSON
    $bodyData = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'responseMimeType' => 'application/json'
        ]
    ];
    
    $body = json_encode($bodyData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if (!$body) {
        return ['ok' => false, 'error' => 'Gagal menyusun payload JSON untuk AI API.'];
    }

    $model = get_quiz_env('GEMINI_MODEL', 'gemini-3.1-flash-lite');
    $lastError = 'Gemini request failed.';

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
            $lastError = 'cURL Error: ' . ($curlError ?: 'Gagal terhubung ke Gemini API.');
            continue;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorResponse = json_decode((string)$response, true);
            $apiMessage = $errorResponse['error']['message'] ?? '';
            $lastError = 'Gemini API Error (' . $httpCode . '): ' . ($apiMessage ?: 'Permintaan ditolak.');
            continue;
        }

        $extractedText = extract_gemini_text($response);
        $payload = json_decode($extractedText, true);

        if (!validate_quiz_payload($payload)) {
            $lastError = 'Format JSON dari Gemini tidak sesuai spesifikasi.';
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

            $update = $db->prepare("UPDATE generated_quizzes SET status = 'ready', error_message = NULL WHERE submission_id = ?");
            $update->bind_param('i', $submissionId);
            $update->execute();
            $update->close();

            $db->commit();
            return ['ok' => true];
        } catch (Throwable $exception) {
            $db->rollback();
            return ['ok' => false, 'error' => 'Gagal menyimpan quiz ke database: ' . $exception->getMessage()];
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
    $stmt = $db->prepare('INSERT INTO generated_quizzes (submission_id, student_id, penalty_points) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE student_id = VALUES(student_id), status = \'pending\', penalty_points = VALUES(penalty_points), error_message = NULL');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iid', $submissionId, $studentId, $penalty);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
} 