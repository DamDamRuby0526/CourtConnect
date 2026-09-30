<?php
// load courts INSIDE facilities on USER
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$facilityId = $_GET['facility_id'] ?? null;

if (!$facilityId || !is_numeric($facilityId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing facility_id."
    ]);
    exit();
}

// Get the facility name
$facilitySql = "SELECT f.facility_name
FROM facilities f
WHERE f.facility_id = ?
AND NOT EXISTS (
SELECT 1
FROM admins a
WHERE a.facility_id = f.facility_id
AND a.status <> 'Active'
    )";
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
$sql = "SELECT court_id, court_no, court_rate, description, court_img
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

echo json_encode([
    "success" => true,
    "facility_name" => $facility["facility_name"],
    "courts" => $courts
]);

$stmt->close();
$conn->close();
?>