<?php
require_once __DIR__ . "/../backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Developer sign-in is required."]);
    exit();
}

$stmt = $conn->prepare(
    "SELECT u.role_id, u.is_verified
     FROM users u
     INNER JOIN roles r ON r.role_id = u.role_id
     WHERE u.user_id = ? AND r.role_name = 'courtconnect'"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || (int) $user["is_verified"] !== 1) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Developer access is required."]);
    exit();
}

$_SESSION["role_id"] = 4;
?>