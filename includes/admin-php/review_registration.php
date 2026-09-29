<?php
require_once __DIR__ . "/courtconnect_auth_guard.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$adminId = filter_var($data["admin_id"] ?? null, FILTER_VALIDATE_INT);
$status = $data["status"] ?? "";
$reason = trim($data["rejection_reason"] ?? "");

if (!$adminId || $adminId <= 0 || !in_array($status, ["Active", "Rejected"], true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "A valid application and decision are required."]);
    exit();
}

if ($status === "Rejected" && $reason === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Provide a reason for rejecting this application."]);
    exit();
}

$reviewedBy = (int) $_SESSION["user_id"];
$stmt = $conn->prepare("UPDATE admins SET status = ?, reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ? WHERE admin_id = ? AND status = 'Pending'");
$stmt->bind_param("sisi", $status, $reviewedBy, $reason, $adminId);
$stmt->execute();
$updated = $stmt->affected_rows;
$stmt->close();

if ($updated !== 1) {
    http_response_code(409);
    echo json_encode(["success" => false, "message" => "This application is no longer pending review."]);
    exit();
}

echo json_encode(["success" => true, "message" => "Application marked as " . strtolower($status) . "."]);
$conn->close();
?>