<?php
header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";

$courtId = filter_var($_GET["court_id"] ?? null, FILTER_VALIDATE_INT);
$facilityId = (int) $_SESSION["facility_id"];

if (!$courtId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing court_id."
    ]);

    exit();
}

$courtStmt = $conn->prepare("SELECT court_no FROM court_details WHERE court_id = ? AND facility_id = ?");
$courtStmt->bind_param("ii", $courtId, $facilityId);
$courtStmt->execute();
$court = $courtStmt->get_result()->fetch_assoc();
$courtStmt->close();

if (!$court) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Court not found in your facility."]);
    exit();
}

$sql = "SELECT schedule_id, court_id, court_date, court_time, schedule_status
        FROM court_schedule
        WHERE court_id = ?
        ORDER BY court_date ASC, court_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $courtId);
$stmt->execute();

$result = mysqli_stmt_get_result($stmt);

$schedules = [];

while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

echo json_encode([
    "success" => true,
    "court_id" => $courtId,
    "schedules" => $schedules
]);

$stmt->close();
$conn->close();
