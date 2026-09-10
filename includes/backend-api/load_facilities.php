<?php
// load facilities on USER
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$sql = "SELECT facility_id, facility_name, address, municipality, sport_type,
               opening_time, closing_time, facility_img
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
?>