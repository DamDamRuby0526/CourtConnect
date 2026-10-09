<?php
// load facilities on USER
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$sql = "SELECT facility_id, facility_name, address, municipality, sport_type,
               opening_time, closing_time, facility_img
                FROM facilities f
                WHERE NOT EXISTS (
                        SELECT 1
                        FROM admins a
                        WHERE a.facility_id = f.facility_id
                            AND a.status <> 'Active'
                )";

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
