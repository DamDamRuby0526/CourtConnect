<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";

$roleId = (int) ($_SESSION["role_id"] ?? 0);
if ($roleId === 5) {
    require_once __DIR__ . "/superadmin_auth_guard.php";
    if (array_key_exists("facility_id", $_GET) && filter_var($_GET["facility_id"], FILTER_VALIDATE_INT) === false) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid facility ID."]);
        exit();
    }
    $facilityId = filter_var($_GET["facility_id"] ?? null, FILTER_VALIDATE_INT);
    if ($facilityId) {
        $stmt = $conn->prepare(
            "SELECT facility_id, facility_name, sport_type, address, municipality,
                    opening_time, closing_time, facility_img, qr_img
             FROM facilities WHERE facility_id = ?"
        );
        $stmt->bind_param("i", $facilityId);
        $stmt->execute();
        $result = $stmt->get_result();
        $facilities = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query(
            "SELECT facility_id, facility_name, sport_type, address, municipality,
                    opening_time, closing_time, facility_img, qr_img
             FROM facilities ORDER BY facility_id DESC"
        );
        $facilities = $result->fetch_all(MYSQLI_ASSOC);
    }
} else {
    require_once __DIR__ . "/facility_owner_guard.php";

    $ownerFacilityId = (int) ($_SESSION["facility_id"] ?? 0);
    $requestedFacilityId = filter_var($_GET["facility_id"] ?? $ownerFacilityId, FILTER_VALIDATE_INT);
    if (!$requestedFacilityId || $requestedFacilityId !== $ownerFacilityId) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "You can only view your own facility."]);
        exit();
    }

    $stmt = $conn->prepare(
        "SELECT facility_id, facility_name, sport_type, address, municipality,
                opening_time, closing_time, facility_img, qr_img
         FROM facilities WHERE facility_id = ?"
    );
    $stmt->bind_param("i", $ownerFacilityId);
    $stmt->execute();
    $facilities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if (!$facilities) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Facility not found."]);
    exit();
}

echo json_encode(["success" => true, "facilities" => $facilities]);
$conn->close();