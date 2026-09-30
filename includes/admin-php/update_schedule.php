<?php

header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "No data received."
    ]);
    exit();
}

$scheduleId     = filter_var($data["schedule_id"] ?? null, FILTER_VALIDATE_INT);
$courtId        = filter_var($data["court_id"] ?? null, FILTER_VALIDATE_INT);
$courtDate      = $data["court_date"] ?? "";
$courtTime      = $data["court_time"] ?? "";
$scheduleStatus = $data["schedule_status"] ?? "";
$facilityId     = (int) $_SESSION["facility_id"];

if (!$scheduleId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing schedule_id."
    ]);
    exit();
}

if (!$courtId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing court_id."
    ]);
    exit();
}

    $parsedDate = DateTime::createFromFormat("!Y-m-d", $courtDate);
if (
        !$parsedDate || $parsedDate->format("Y-m-d") !== $courtDate ||
    !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $courtTime) ||
    !in_array($scheduleStatus, ["Available", "Booked"], true)
) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a valid date, time, and schedule status."]);
    exit();
}

$ownershipStmt = $conn->prepare(
    "SELECT cs.schedule_id, cd.slot_duration, f.opening_time, f.closing_time
     FROM court_schedule cs
     INNER JOIN court_details cd ON cd.court_id = cs.court_id
     INNER JOIN facilities f ON f.facility_id = cd.facility_id
     WHERE cs.schedule_id = ? AND cs.court_id = ? AND cd.facility_id = ?"
);
$ownershipStmt->bind_param("iii", $scheduleId, $courtId, $facilityId);
$ownershipStmt->execute();
$ownedSchedule = $ownershipStmt->get_result()->fetch_assoc();
$ownershipStmt->close();

if (!$ownedSchedule) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Schedule not found in your facility."]);
    exit();
}

$slotEnd = strtotime($courtTime) + ((int) $ownedSchedule["slot_duration"] * 60);
if ($courtTime < substr($ownedSchedule["opening_time"], 0, 5) || $slotEnd > strtotime($ownedSchedule["closing_time"])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "This time slot must fit within your facility's opening hours."]);
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
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Update failed."
    ]);
}

$stmt->close();
$conn->close();
