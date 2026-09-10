<?php

header("Content-Type: application/json");

// require_once __DIR__ . "/admin_auth_guard.php";
require_once __DIR__ . "/../backend-api/config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "No data received."
    ]);
    exit();
}

$scheduleId     = $data["schedule_id"] ?? null;
$courtId        = $data["court_id"] ?? null;
$courtDate      = $data["court_date"] ?? "";
$courtTime      = $data["court_time"] ?? "";
$scheduleStatus = $data["schedule_status"] ?? "";

if (!$scheduleId || !is_numeric($scheduleId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing schedule_id."
    ]);
    exit();
}

if (!$courtId || !is_numeric($courtId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing court_id."
    ]);
    exit();
}

if (
    empty($courtDate) ||
    empty($courtTime) ||
    empty($scheduleStatus)
) {
    echo json_encode([
        "success" => false,
        "message" => "Please fill in the required fields."
    ]);
    exit();
}

$sql = "UPDATE court_schedule
        SET court_date = ?, court_time = ?, schedule_status = ?
        WHERE schedule_id = ? AND court_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssii",
    $courtDate,
    $courtTime,
    $scheduleStatus,
    $scheduleId,
    $courtId
);

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Schedule updated successfully."
    ]);
} else {

    echo json_encode([
        "success" => false,
        "message" => "Update failed."
    ]);
}

$stmt->close();
$conn->close();
