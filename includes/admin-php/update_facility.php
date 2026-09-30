<?php
header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";
require 'upload_helper.php';

$uploadDir = __DIR__ . '/../../uploads/facility';

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
    $openingTime  = trim($_POST['opening_time'] ?? "");
    $closingTime  = trim($_POST['closing_time'] ?? "");

    $ownerFacilityId = (int) $_SESSION["facility_id"];
    if (!$facilityId || !is_numeric($facilityId) || (int) $facilityId !== $ownerFacilityId) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "You can only update your own facility."]);
        exit();
    }

    if (empty($facilityName) || empty($sportType) || empty($address) ||
        empty($municipality) || empty($openingTime) || empty($closingTime)) {
        echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
        exit();
    }

    if (!in_array($sportType, ["Badminton", "Pickleball", "Both"])) {
        echo json_encode(["success" => false, "message" => "Invalid sport type."]);
        exit();
    }

    // get current image filenames so we know what to keep/replace/delete
    $sql = "SELECT facility_img, qr_img FROM facilities WHERE facility_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $facilityId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$current) {
        echo json_encode(["success" => false, "message" => "Facility not found."]);
        exit();
    }

    try {
        $newFacilityImg = handleImageUpload('facility_img', $uploadDir);
        $newQrImg       = handleImageUpload('qr_img', $uploadDir);
    } catch (RuntimeException $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
        exit();
    }

    // if a new file was uploaded, use it and delete the old one; otherwise keep existing
    $facilityImg = $current['facility_img'];
    if ($newFacilityImg) {
        if ($current['facility_img'] && file_exists("$uploadDir/{$current['facility_img']}")) {
            unlink("$uploadDir/{$current['facility_img']}");
        }
        $facilityImg = $newFacilityImg;
    }

    $qrImg = $current['qr_img'];
    if ($newQrImg) {
        if ($current['qr_img'] && file_exists("$uploadDir/{$current['qr_img']}")) {
            unlink("$uploadDir/{$current['qr_img']}");
        }
        $qrImg = $newQrImg;
    }

    $sql = "UPDATE facilities
            SET facility_name = ?, sport_type = ?, address = ?, municipality = ?,
                opening_time = ?, closing_time = ?, facility_img = ?, qr_img = ?
            WHERE facility_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssi",
        $facilityName, $sportType, $address, $municipality,
        $openingTime, $closingTime, $facilityImg, $qrImg, $facilityId
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