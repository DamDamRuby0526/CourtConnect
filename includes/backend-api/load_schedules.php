<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";

$court_id = $_GET['court_id'] ?? null;

if (!$court_id || !is_numeric($court_id)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing court_id."
    ]);
    exit();
}

$sql = "SELECT schedule_id, court_id, court_date, court_time, schedule_status FROM court_schedule WHERE court_id = ?";
       
$stmt = $conn->prepare($sql);
$stmt->bind_param("i, $court_id");
$stmt->execute();

$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

echo json_encode([
    "success" => true,
    "court_id" => (int) $court_id,
    "schedules" => $schedules
]);

$stmt->close();
$conn->close();
