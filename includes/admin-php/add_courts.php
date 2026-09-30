<?php
header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";
require_once __DIR__ . "/upload_helper.php";

$facilityId = (int) $_SESSION["facility_id"];
$courtNo = filter_var($_POST["court_no"] ?? null, FILTER_VALIDATE_INT);
$courtRate = filter_var($_POST["court_rate"] ?? null, FILTER_VALIDATE_INT);
$description = trim($_POST["description"] ?? "");
$slotDuration = filter_var($_POST["slot_duration"] ?? null, FILTER_VALIDATE_INT);
$courtStatus = trim($_POST["court_status"] ?? "");

if (!$courtNo || !$courtRate || !$slotDuration || $description === "" || !in_array($courtStatus, ["Available", "Maintenance"], true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a court number, positive rate and duration, description, and valid status."]);
    exit();
}

$uploadDir = __DIR__ . "/../../uploads/court";
try {
    $courtImage = handleImageUpload("court_img", $uploadDir);
} catch (RuntimeException $error) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
    exit();
}

$sql = "INSERT INTO court_details (facility_id, court_no, court_rate, description, court_img, slot_duration, court_status)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiissis", $facilityId, $courtNo, $courtRate, $description, $courtImage, $slotDuration, $courtStatus);

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Court added successfully.",
        "court_id" => $stmt->insert_id
    ]);
} else {
    if ($courtImage && is_file($uploadDir . "/" . $courtImage)) {
        unlink($uploadDir . "/" . $courtImage);
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to add court."
    ]);
}

$stmt->close();
$conn->close();
