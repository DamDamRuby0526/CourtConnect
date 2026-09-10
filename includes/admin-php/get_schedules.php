<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";

// itlog? same as null pala
$court_id = $_GET['court_id'] ?? 0;

if (!$court_id || !is_numeric($court_id)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing court_id."
    ]);

    exit();
}

$sql = "SELECT schedule_id, court_id, court_date, court_time, schedule_status FROM court_schedule WHERE court_id = ?";
       
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $court_id);
$stmt->execute();

$result = mysqli_stmt_get_result($stmt);

$schedules = [];

while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

echo json_encode([
    "success" => true,
    "court_id" => "court_id",
    "court_no" => "court_no",
    "schedules" => $schedules
]);

$stmt->close();
$conn->close();
