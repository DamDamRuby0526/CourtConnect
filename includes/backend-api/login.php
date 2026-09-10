<?php

header("Content-Type: application/json");
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "No data received."
    ]);
    exit();
}

$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");

if (empty($email) || empty($password)) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);
    exit();
}

$sql = "SELECT user_id, first_name, last_name, email, phone_number, password, is_verified
        FROM users
        WHERE email = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password."
    ]);
    exit();
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user["password"])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password."
    ]);
    exit();
}

if ((int)$user["is_verified"] === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Account is not yet verified.",
        "requires_verification" => true,
        "user_id" => $user["user_id"]
    ]);
    exit();
}

$_SESSION["user_id"] = $user["user_id"];
$_SESSION["first_name"] = $user["first_name"];
$_SESSION["last_name"] = $user["last_name"];
$_SESSION["email"] = $user["email"];
$_SESSION["logged_in"] = true;

echo json_encode([
    "success" => true,
    "message" => "Login successful.",
    "user" => [
        "user_id" => $user["user_id"],
        "first_name" => $user["first_name"],
        "last_name" => $user["last_name"],
        "email" => $user["email"],
        "phone_number" => $user["phone_number"]
    ] 
]);

$stmt->close();
$conn->close();