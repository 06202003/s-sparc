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

?>
