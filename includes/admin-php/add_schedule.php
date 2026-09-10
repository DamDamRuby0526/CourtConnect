<?php

header("Content-Type: application/json");

// require_once __DIR__ . "/admin_auth_guard.php";
require_once __DIR__ . "/../backend-api/config.php";

$courtId        = $_POST["court_id"] ?? null;
$courtDate      = $_POST["court_date"] ?? "";
$courtTime      = $_POST["court_time"] ?? "";
$scheduleStatus = $_POST["schedule_status"] ?? "";

if (!$courtId || !is_numeric($courtId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing court_id."
    ]);
    exit();
}

if (empty($courtDate) || empty($courtTime) || empty($scheduleStatus)) {
    echo json_encode([
        "success" => false,
        "message" => "Please fill in the required fields."
    ]);
    return;
}

$sql = "INSERT INTO court_schedule (court_id, court_date, court_time, schedule_status) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $courtId, $courtDate, $courtTime, $scheduleStatus);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Schedule added successfully.",
        "schedule_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to add schedule."
    ]);
}

$stmt->close();
$conn->close();

?>