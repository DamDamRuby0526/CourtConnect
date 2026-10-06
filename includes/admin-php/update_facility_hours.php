<?php
header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";

$openingTime = trim($_POST["opening_time"] ?? "");
$closingTime = trim($_POST["closing_time"] ?? "");
$timePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

if (!preg_match($timePattern, $openingTime) || !preg_match($timePattern, $closingTime) || $openingTime >= $closingTime) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid opening time earlier than the closing time."]);
    exit();
}

$facilityId = (int) $_SESSION["facility_id"];
$stmt = $conn->prepare("UPDATE facilities SET opening_time = ?, closing_time = ? WHERE facility_id = ?");
$stmt->bind_param("ssi", $openingTime, $closingTime, $facilityId);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Facility hours updated."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Could not update facility hours."]);
}

$stmt->close();
$conn->close();
