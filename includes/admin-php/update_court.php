<?php
header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";
require_once __DIR__ . "/upload_helper.php";

$facilityId = (int) $_SESSION["facility_id"];
$courtId = filter_var($_POST["court_id"] ?? null, FILTER_VALIDATE_INT);
$courtNo = filter_var($_POST["court_no"] ?? null, FILTER_VALIDATE_INT);
$courtRate = filter_var($_POST["court_rate"] ?? null, FILTER_VALIDATE_INT);
$description = trim($_POST["description"] ?? "");
$slotDuration = filter_var($_POST["slot_duration"] ?? null, FILTER_VALIDATE_INT);
$courtStatus = trim($_POST["court_status"] ?? "");

if (!$courtId || !$courtNo || !$courtRate || !$slotDuration || $description === "" || !in_array($courtStatus, ["Available", "Maintenance"], true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter valid court details before saving."]);
    exit();
}

$currentStmt = $conn->prepare("SELECT court_img FROM court_details WHERE court_id = ? AND facility_id = ?");
$currentStmt->bind_param("ii", $courtId, $facilityId);
$currentStmt->execute();
$currentCourt = $currentStmt->get_result()->fetch_assoc();
$currentStmt->close();

if (!$currentCourt) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Court not found in your facility."]);
    exit();
}

$uploadDir = __DIR__ . "/../../uploads/court";
try {
    $newCourtImage = handleImageUpload("court_img", $uploadDir);
} catch (RuntimeException $error) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
    exit();
}

$courtImage = $newCourtImage ?: $currentCourt["court_img"];
$sql = "UPDATE court_details
        SET court_no = ?, court_rate = ?, description = ?, court_img = ?, slot_duration = ?, court_status = ?
        WHERE court_id = ? AND facility_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iissisii", $courtNo, $courtRate, $description, $courtImage, $slotDuration, $courtStatus, $courtId, $facilityId);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Court updated successfully."]);
    if ($newCourtImage && $currentCourt["court_img"] && is_file($uploadDir . "/" . $currentCourt["court_img"])) {
        unlink($uploadDir . "/" . $currentCourt["court_img"]);
    }
} else {
    if ($newCourtImage && is_file($uploadDir . "/" . $newCourtImage)) {
        unlink($uploadDir . "/" . $newCourtImage);
    }
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Update failed."]);
}

$stmt->close();
$conn->close();
