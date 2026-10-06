<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$userId = filter_var($_SESSION["pending_facility_user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    echo json_encode(["success" => false, "message" => "No pending facility application found."]);
    exit();
}

$stmt = $conn->prepare("SELECT status, rejection_reason FROM admins WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$application) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Facility application not found."]);
    exit();
}

echo json_encode([
    "success" => true,
    "user_id" => (int) $userId,
    "status" => $application["status"],
    "rejection_reason" => $application["rejection_reason"]
]);
$conn->close();
