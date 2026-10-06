<?php
header("Content-Type: application/json");
// require_once __DIR__ . "/admin_auth_guard.php";
require_once __DIR__ . "/../backend-api/config.php";

$userId = $_GET['user_id'] ?? null;

if (!$userId || !is_numeric($userId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing user_id."
    ]);
    exit();
}

$sql = "SELECT user_id, first_name, last_name, email, phone_number FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
    exit();
}

$user = $result->fetch_assoc();

echo json_encode([
    "success" => true,
    "user" => $user
]);

$stmt->close();
$conn->close();
