<?php
$db = mysqli_connect("127.0.0.1", "david", "david20juni2003#", "db_semantic_final");
if (!$db) {
    die("DB connection failed\n");
}

// Check users in `user` missing in `users`
$q = mysqli_query($db, "
    SELECT u.user_id, u.username, u.email, u.name, u.role
    FROM user u
    LEFT JOIN users s ON u.username = s.username
    WHERE s.username IS NULL
");

$count = 0;
while ($row = mysqli_fetch_assoc($q)) {
    // Generate UUID v4
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    $username = mysqli_real_escape_string($db, $row['username']);
    $email = mysqli_real_escape_string($db, !empty($row['email']) ? $row['email'] : $row['username'] . "@maranatha.ac.id");
    $isAdmin = ($row['role'] === 'admin') ? 1 : 0;
    $dummyHash = hash('sha256', 'student123');

    $ins = mysqli_query($db, "
        INSERT INTO users (user_id, username, email, password_hash, is_admin)
        VALUES ('$uuid', '$username', '$email', '$dummyHash', $isAdmin)
    ");
    if ($ins) {
        echo "[SYNCED] Username: {$row['username']} -> UUID: $uuid\n";
        $count++;
    } else {
        echo "[ERROR] Failed for {$row['username']}: " . mysqli_error($db) . "\n";
    }
}

echo "Sync completed. Total synced: $count users.\n";
?>
