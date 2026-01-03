<?php
require __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\BotMan\Cache\ArrayCache;
use BotMan\Drivers\Web\WebDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

$backendBase = backend_base();
$httpClient = new Client([
    'base_uri' => $backendBase . '/',
    // timeout 0 berarti tidak ada batas waktu (biarkan backend yang mengontrol)
    'timeout' => 0,
]);

DriverManager::loadDriver(WebDriver::class);
$config = [];
$botman = BotManFactory::create($config, new ArrayCache());

function flask_request($method, $path, $payload = null)
{
    global $httpClient;
    $options = ['headers' => ['Content-Type' => 'application/json']];
    if (!empty($_SESSION['flask_cookie'])) {
        $options['headers']['Cookie'] = $_SESSION['flask_cookie'];
    }
    if ($payload !== null) {
        $options['json'] = $payload;
    }

    try {
        $resp = $httpClient->request($method, ltrim($path, '/'), $options);
        $status = $resp->getStatusCode();
        $body = (string) $resp->getBody();
        $data = json_decode($body, true);
        $cookieHeaders = $resp->getHeader('Set-Cookie');
        if ($cookieHeaders) {
            $_SESSION['flask_cookie'] = $cookieHeaders[0];
        }
        return ['ok' => true, 'status' => $status, 'data' => $data, 'raw' => $body];
    } catch (\Throwable $e) {
        if ($e instanceof RequestException) {
            $resp = $e->getResponse();
            $status = $resp ? $resp->getStatusCode() : null;
            $body = $resp ? (string) $resp->getBody() : null;
            $data = $body ? json_decode($body, true) : null;
            $message = $data['error'] ?? $e->getMessage();
            $cookieHeaders = $resp ? $resp->getHeader('Set-Cookie') : [];
            if ($cookieHeaders) {
                $_SESSION['flask_cookie'] = $cookieHeaders[0];
            }
            return ['ok' => false, 'status' => $status, 'error' => $message, 'data' => $data, 'raw' => $body];
        }
        return ['ok' => false, 'status' => null, 'error' => $e->getMessage(), 'data' => null, 'raw' => null];
    }
}

$botman->hears('.*', function ($bot) {
    // Ambil teks pesan dari BotMan (WebDriver)
    $incoming = $bot->getMessage();
    $prompt = '';
    if (method_exists($incoming, 'getText')) {
        $prompt = trim((string) $incoming->getText());
    } else {
        $prompt = trim((string) $incoming);
    }
    if (stripos($prompt, 'status ') === 0) {
        $jobId = trim(substr($prompt, 7));
        if ($jobId === '') {
            $bot->reply('Incorrect status format. Example: status abc-123');
            return;
        }
        // Kirim user_id dan assessment_id jika ada
        $params = [];
        // Prefer canonical backend user_id if present, otherwise fallback to chat_user_id
        if (!empty($_SESSION['user_id'])) {
            $params['user_id'] = $_SESSION['user_id'];
        } elseif (!empty($_SESSION['chat_user_id'])) {
            $params['user_id'] = $_SESSION['chat_user_id'];
        }
        if (!empty($_SESSION['assessment_id'])) {
            $params['assessment_id'] = $_SESSION['assessment_id'];
        }
        $query = $params ? ('?' . http_build_query($params)) : '';
        $poll = flask_request('GET', '/check-status/' . urlencode($jobId) . $query);
        if (!$poll['ok']) {
            $bot->reply('Failed to check status (job_id: ' . $jobId . '). ' . ($poll['error'] ?? ''));
            return;
        }
        $pollData = $poll['data'] ?? [];
        $status = $pollData['status'] ?? 'unknown';
        if ($status === 'pending') {
            $bot->reply('Job is still being processed. job_id: ' . $jobId);
            return;
        }
        if ($status === 'done') {
            $reply = 'Job done.';
            if (!empty($pollData['code'])) {
                $reply .= "\n" . $pollData['code'];
            }
            $bot->reply($reply);
            return;
        }
        if ($status === 'error') {
            $bot->reply('Job failed: ' . ($pollData['message'] ?? $pollData['error'] ?? 'unknown reason'));
            return;
        }
        $bot->reply('Job status unknown. job_id: ' . $jobId);
        return;
    }

    if (empty($_SESSION['flask_cookie'])) {
        $bot->reply('You are not logged in. Please log in first to connect to the server.');
        return;
    }

    if ($prompt === '') {
        $bot->reply('Please enter a question first.');
        return;
    }

    $payload = ['prompt' => $prompt];
    if (!empty($_SESSION['assessment_id'])) {
        $payload['assessment_id'] = $_SESSION['assessment_id'];
    }
    // If the web client posted language or response_mode, forward them to the backend
    // (chat.php sends these as form fields when present)
    if (!empty($_POST['language'])) {
        $payload['language'] = trim((string) $_POST['language']);
    }
    if (!empty($_POST['response_mode'])) {
        $payload['response_mode'] = trim((string) $_POST['response_mode']);
    }
    // If this is a forced generation request (from the FE 'Generate with ChatGPT' button),
    // call the enqueue endpoint which is not subject to the per-route rate limit.
    $is_force = false;
    $FORCE_PREFIX = '__force_gpt__ ';
    if (stripos($prompt, $FORCE_PREFIX) === 0) {
        $is_force = true;
        $payload['prompt'] = substr($prompt, strlen($FORCE_PREFIX));
    }

    if ($is_force) {
        $resp = flask_request('POST', '/enqueue-gpt', $payload);
    } else {
        $resp = flask_request('POST', '/generate-code', $payload);
    }
    if (!$resp['ok']) {
        $status = $resp['status'];
        if ($status === 401 || $status === 403) {
            unset($_SESSION['flask_cookie']);
            $bot->reply('Login session expired/invalid. Please log in again.');
            return;
        }
        if ($status === 429) {
            $bot->reply('Backend rate limit reached. Please try again after a while.');
            return;
        }
        if ($status && $status >= 500) {
            $bot->reply('Backend server error (status ' . $status . '). Please try again.');
            return;
        }
        $bot->reply('Sorry, the system cannot reach the server. Details: ' . ($resp['error'] ?? 'unknown'));
        return;
    }

    $data = $resp['data'] ?? null;
    if (!$data) {
        $bot->reply('Server response cannot be processed. Please try again.');
        return;
    }

    if (!empty($data['error'])) {
        $bot->reply('Sorry, an error occurred: ' . $data['error']);
        return;
    }

    $mode = $data['mode'] ?? null;
    $similarity = isset($data['similarity']) ? round(floatval($data['similarity']) * 100, 1) : null;

    if (!empty($data['code']) && empty($data['job_id'])) {
        // Jawaban langsung tanpa antrian: sumber dari database (retrieval/suggestion)
        if ($mode === 'retrieval') {
            $prefix = 'Answers are retrieved from the database (free of charge, does not reduce your quota).';
        } elseif ($mode === 'suggestion') {
            $prefix = 'Found similar code in the database (free of charge). If it is not suitable, you can request a new version from ChatGPT.';
        } else {
            $prefix = 'Answer from the system.';
        }
        if ($similarity !== null) {
            $prefix .= ' Similarity ' . $similarity . '%.';
        }
        $bot->reply($prefix . "\n" . $data['code']);
        return;
    }

    $respMode = $data['mode'] ?? '';
    if (strpos($respMode, 'gpt-queued') !== false && !empty($data['job_id'])) {
        $jobId = $data['job_id'];
        $bot->reply('Your request is being processed (queued). job_id: ' . $jobId . '. I will check for up to 24 seconds, or type "status ' . $jobId . '" to check manually.');

        $maxPoll = 30; // ~60 detik (2s * 30)
        for ($i = 0; $i < $maxPoll; $i++) {
            sleep(2);
            // Kirim user_id dan assessment_id jika ada
            $params = [];
            if (!empty($_SESSION['user_id'])) {
                $params['user_id'] = $_SESSION['user_id'];
            } elseif (!empty($_SESSION['chat_user_id'])) {
                $params['user_id'] = $_SESSION['chat_user_id'];
            }
            if (!empty($_SESSION['assessment_id'])) {
                $params['assessment_id'] = $_SESSION['assessment_id'];
            }
            $query = $params ? ('?' . http_build_query($params)) : '';
            $poll = flask_request('GET', '/check-status/' . urlencode($jobId) . $query);
            if (!$poll['ok']) {
                continue;
            }
            $pollData = $poll['data'] ?? null;
            if (!$pollData) {
                continue;
            }
            if (($pollData['status'] ?? '') === 'pending') {
                continue;
            }
            if (($pollData['status'] ?? '') === 'done') {
                if (!empty($pollData['code'])) {
                    $bot->reply("Here is the code result:\n" . $pollData['code']);
                } else {
                    $debug = json_encode($pollData, JSON_PRETTY_PRINT);
                    $bot->reply('Job done, but no code was provided. Backend /check-status returned: ' . $debug);
                }
                return;
            }
            if (($pollData['status'] ?? '') === 'error') {
                $bot->reply('Sorry, the job failed: ' . ($pollData['error'] ?? 'unknown reason'));
                return;
            }
        }
        $bot->reply('Still processing. To check again anytime, type: status ' . $jobId);
        return;
    }

    if (!empty($data['code'])) {
        $bot->reply($data['code']);
    } else {
        $debugMain = json_encode($data, JSON_PRETTY_PRINT);
        $bot->reply('No code is available to send at this time. Backend returned: ' . $debugMain);
    }
});

$botman->listen();
