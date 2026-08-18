<?php
header("Content-Type: application/json");

// ===== API KEY =====
$API_KEY = "TESIS_DAVID_2479011_SECRET_2026";

if (!isset($_POST['key']) || $_POST['key'] !== $API_KEY) {
    http_response_code(403);
    exit(json_encode(["status" => "error", "message" => "Unauthorized"]));
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $db->prepare("SELECT username, password_hash FROM user WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "fail", "message" => "User not found"]);
    exit;
}

$row = $result->fetch_assoc();

if (password_verify($password, $row['password_hash'])) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "fail", "message" => "Wrong password"]);
}