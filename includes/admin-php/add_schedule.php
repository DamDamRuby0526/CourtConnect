<?php

header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";

$data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

$courtId = filter_var($data["court_id"] ?? null, FILTER_VALIDATE_INT);
$courtDate = trim($data["court_date"] ?? "");
$courtTime = trim($data["court_time"] ?? "");
$scheduleStatus = trim($data["schedule_status"] ?? "");
$facilityId = (int) $_SESSION["facility_id"];

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

$courtStmt = $conn->prepare(
    "SELECT cd.court_id, cd.slot_duration, f.opening_time, f.closing_time
     FROM court_details cd
     INNER JOIN facilities f ON f.facility_id = cd.facility_id
     WHERE cd.court_id = ? AND cd.facility_id = ? AND cd.court_status = 'Available'"
);
$courtStmt->bind_param("ii", $courtId, $facilityId);
$courtStmt->execute();
$ownedCourt = $courtStmt->get_result()->fetch_assoc();
$courtStmt->close();

if (!$ownedCourt) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Available court not found in your facility."]);
    exit();
}

$slotEnd = strtotime($courtTime) + ((int) $ownedCourt["slot_duration"] * 60);
if ($courtTime < substr($ownedCourt["opening_time"], 0, 5) || $slotEnd > strtotime($ownedCourt["closing_time"])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "This time slot must fit within your facility's opening hours."]);
    exit();
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
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to add schedule."
    ]);
}

$stmt->close();
$conn->close();

?>