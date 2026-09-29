<?php
session_start();
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";

// Check if admin is logged in
if (!isset($_SESSION["facility_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);
    exit();
}

$facilityId = $_SESSION["facility_id"];

// Get only the assigned facility
$sql = "SELECT facility_id, facility_name, sport_type, address, municipality,
               opening_time, closing_time, facility_img, qr_img
        FROM facilities
        WHERE facility_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $facilityId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Facility not found."
    ]);
    exit();
}

echo json_encode([
    "success" => true,
    "facilities" => [$result->fetch_assoc()]
]);

$stmt->close();
$conn->close();
?>