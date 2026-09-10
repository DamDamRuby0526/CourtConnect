<?php
// ayusin nalang format to match others
header("Content-Type: application/json");

// require_once __DIR__ . "/admin_auth_guard.php";
require_once __DIR__ . "/../backend-api/config.php";

// json
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data)) {

    echo json_encode([
        "success" => false,
        "message" => "No data received."
    ]);

    exit();
}

$facilityId   = trim($data["facility_id"] ?? "");
$courtNo      = trim($data["court_no"] ?? "");
$courtRate    = trim($data["court_rate"] ?? "");
$description  = trim($data["description"] ?? "");
$slotDuration = trim($data["slot_duration"] ?? "");
$courtStatus  = trim($data["court_status"] ?? "");

if ($facilityId === "" || $courtNo === "" ||
    $courtRate === "" || $description === "" ||
    $slotDuration === "" || $courtStatus === "") 
{
    echo json_encode([
        "success" => false,
        "message" => "Please fill in the required fields."
    ]);
    return;
}

if (!is_numeric($facilityId) || $facilityId <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid facility ID."
    ]);
    return;
}

if (!is_numeric($courtRate) || $courtRate <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Rate must be a positive number."
    ]);
    return;
}

if (!is_numeric($slotDuration) || $slotDuration <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Slot duration must be a positive number."
    ]);
    return;
}

// insert facility id foregin key
$sql = "INSERT INTO court_details (facility_id, court_no, court_rate, description, slot_duration, court_status)
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isdsis", $facilityId, $courtNo, $courtRate, $description, $slotDuration, $courtStatus);

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Court added successfully.",
        "court_id" => $stmt->insert_id
    ]);
} else {

    echo json_encode([
        "success" => false,
        "message" => "Failed to add court."
    ]);
}

$stmt->close();
$conn->close();
