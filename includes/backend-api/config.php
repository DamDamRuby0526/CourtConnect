<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$username = "root";
$password = "";
$database = "courtconnect";

$conn = mysqli_connect($host, $username, $password, $database);

date_default_timezone_set("Asia/Manila");

if (!$conn) {
    header("Content-Type: application/json");
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed."
    ]);

    exit();
}

$conn->set_charset("utf8mb4");

?>