<?php
require_once __DIR__ . "/../backend-api/config.php";
header("Content-Type: application/json");

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Developer sign-in is required."]);
    exit();
}

$stmt = $conn->prepare("SELECT role_id, is_verified FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || (int) $user["role_id"] !== 4 || (int) $user["is_verified"] !== 1) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Developer access is required."]);
    exit();
}

$_SESSION["role_id"] = 4;
