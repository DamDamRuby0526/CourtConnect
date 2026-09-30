<?php
header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";

$facilityId = (int) $_SESSION["facility_id"];
$requestedFacilityId = filter_var($_GET["facility_id"] ?? $facilityId, FILTER_VALIDATE_INT);

if (!$requestedFacilityId || $requestedFacilityId !== $facilityId) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "You can only manage courts at your own facility."
    ]);
    exit();
}

// Get the facility name
$facilitySql = "SELECT facility_name FROM facilities WHERE facility_id = ?";
$facilityStmt = $conn->prepare($facilitySql);
$facilityStmt->bind_param("i", $facilityId);
$facilityStmt->execute();
$facilityResult = $facilityStmt->get_result();

if ($facilityResult->num_rows === 0) {
    $facilityStmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Facility not found."
    ]);
    exit();
}

$facility = $facilityResult->fetch_assoc();
$facilityStmt->close();

// Get the courts belonging to that facility
$sql = "SELECT court_id, court_no, court_rate, description, court_img, slot_duration, court_status
        FROM court_details
        WHERE facility_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $facilityId);
$stmt->execute();
$result = $stmt->get_result();

$courts = [];

while ($row = $result->fetch_assoc()) {
    $courts[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "facility_name" => $facility["facility_name"],
    "courts" => $courts
]);
