<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";


$facilityId = $_GET['facility_id'] ?? null;

// loads facilities id if no facility provided
if (!$facilityId) {

    $sql = "SELECT facility_id, facility_name, sport_type, address, municipality,
                   opening_time, closing_time, facility_img, qr_img
            FROM facilities";
    $result = $conn->query($sql);

    $facilities = [];
    while ($row = $result->fetch_assoc()) {
        $facilities[] = $row;
    }

    echo json_encode([
        "success" => true,
        "facilities" => $facilities
    ]);

    $conn->close();
    exit();
}

// return a facility for reading 
if (!is_numeric($facilityId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid facility_id."
    ]);
    exit();
}

$sql = "SELECT facility_id, facility_name, sport_type, address, municipality,
               opening_time, closing_time, facility_img, qr_img
        FROM facilities WHERE facility_id = ?";
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
    "facility" => $result->fetch_assoc()
]);

$stmt->close();
$conn->close();
?>