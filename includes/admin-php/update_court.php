<?php
header("Content-Type: application/json");
// require_once __DIR__ . "/admin_auth_guard.php";
require_once __DIR__ . "/../backend-api/config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "No data received."]);
    exit();
}

$courtId     = $data["court_id"] ?? null;
$courtNo   = trim($data["court_no"] ?? "");
$courtRate   = $data["court_rate"] ?? "";
$description = trim($data["description"] ?? "");

if (!$courtId || !is_numeric($courtId)) {
    echo json_encode(["success" => false, "message" => "Invalid or missing court_id."]);
    exit();
}

if (empty($courtNo) || $courtRate === "") {
    echo json_encode(["success" => false, "message" => "Please fill in the required fields."]);
    exit();
}

if (!is_numeric($courtRate) || $courtRate <= 0) {
    echo json_encode(["success" => false, "message" => "Rate must be a positive number."]);
    exit();
}

$sql = "UPDATE courts SET court_no = ?, court_rate = ?, description = ? WHERE court_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sdsi", $courtNo, $courtRate, $description, $courtId);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Court updated successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Update failed."]);
}

$stmt->close();
$conn->close();
?>