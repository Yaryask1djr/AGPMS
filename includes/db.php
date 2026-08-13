<?php
$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
}

$host = $env['DB_HOST'] ?? 'localhost';
$db = $env['DB_NAME'] ?? 'agpms'; 
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>

