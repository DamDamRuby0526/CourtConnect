<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";
$isSuperadmin = (int) ($_SESSION["role_id"] ?? 0) === 5;
if ($isSuperadmin) {
    require_once __DIR__ . "/superadmin_auth_guard.php";
} else {
    require_once __DIR__ . "/facility_owner_guard.php";
}
$method = $_SERVER['REQUEST_METHOD'];

// get
if ($method === 'GET') {

    $facilityId = $_GET['facility_id'] ?? null;

    if (!$facilityId || !is_numeric($facilityId)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid or missing facility_id."
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
    exit();
}

//post update
if ($method === 'POST') {

    $facilityId   = $_POST['facility_id'] ?? null;
    $facilityName = trim($_POST['facility_name'] ?? "");
    $sportType    = trim($_POST['sport_type'] ?? "");
    $address      = trim($_POST['address'] ?? "");
    $municipality = trim($_POST['municipality'] ?? "");

    $ownerFacilityId = (int) ($_SESSION["facility_id"] ?? 0);
    if (!$facilityId || !is_numeric($facilityId) || (!$isSuperadmin && (int) $facilityId !== $ownerFacilityId)) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "You can only update your own facility."]);
        exit();
    }

    if (empty($facilityName) || empty($sportType) || empty($address) || empty($municipality)) {
        echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
        exit();
    }

    if (!in_array($sportType, ["Badminton", "Pickleball", "Both"])) {
        echo json_encode(["success" => false, "message" => "Invalid sport type."]);
        exit();
    }

    $sql = "UPDATE facilities
            SET facility_name = ?, sport_type = ?, address = ?, municipality = ?
            WHERE facility_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssi",
        $facilityName, $sportType, $address, $municipality, $facilityId
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Facility updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Update failed."]);
    }

    $stmt->close();
    $conn->close();
    exit();
}

// ============================================================
// Anything else
// ============================================================
http_response_code(405);
echo json_encode(["success" => false, "message" => "Method not allowed."]);