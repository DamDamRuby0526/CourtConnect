<?php

header("Content-Type: application/json");
session_start();
require "config.php";

if (empty($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "You must be logged in to view bookings."
    ]);
    exit();
}

$userId = $_SESSION["user_id"];

$sql = "SELECT b.booking_id, b.total_amount, b.created_at, cs.schedule_id, cs.court_date, cs.court_time,
        cs.schedule_status, cd.court_id, cd.court_no, cd.description AS court_description, f.facility_id,
        f.facility_name, f.municipality FROM bookings b
        JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
        JOIN court_details cd ON cd.court_id = b.court_id
        JOIN facilities f ON f.facility_id = cd.facility_id
        WHERE b.user_id = ?
        ORDER BY cs.court_date DESC, cs.court_time DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Unable to retrieve bookings."
    ]);
    exit();
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = [
        "booking_id" => (int) $row["booking_id"],
        "total_amount" => (float) $row["total_amount"],
        "created_at" => $row["created_at"],
        "court_no" => $row["court_no"],
        "court_description" => $row["court_description"],
        "court_date" => $row["court_date"],
        "court_time" => $row["court_time"],
        "schedule_status" => $row["schedule_status"],
        "facility_name" => $row["facility_name"],
        "municipality" => $row["municipality"]
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "bookings" => $bookings
]);
