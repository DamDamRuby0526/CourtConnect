<?php
require_once __DIR__ . "/../backend-api/config.php";
header("Content-Type: application/json");

$ownerId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);

if (!$ownerId || !$facilityId || (int) ($_SESSION["role_id"] ?? 0) !== 3) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Facility owner sign-in is required."]);
    exit();
}

$ownerStmt = $conn->prepare(
    "SELECT a.admin_id
     FROM users u
     INNER JOIN admins a ON a.user_id = u.user_id
         WHERE u.user_id = ? AND u.role_id = 3 AND u.is_verified = 1
             AND a.facility_id = ? AND a.status = 'Active'
     LIMIT 1"
);
$ownerStmt->bind_param("ii", $ownerId, $facilityId);
$ownerStmt->execute();
$owner = $ownerStmt->get_result()->fetch_assoc();
$ownerStmt->close();

if (!$owner) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Active facility owner access is required."]);
    exit();
}