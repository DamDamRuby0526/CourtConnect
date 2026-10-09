<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);
$roleId = (int) ($_SESSION["role_id"] ?? 0);
if (!$userId || !$facilityId || !in_array($roleId, [2, 3], true)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Facility admin access is required."]);
    exit();
}

$accessStmt = $conn->prepare(
    "SELECT a.admin_id
     FROM admins a
     INNER JOIN users u ON u.user_id = a.user_id
     WHERE a.user_id = ? AND a.facility_id = ? AND a.status = 'Active'
         AND u.role_id = ? AND u.is_verified = 1
     LIMIT 1"
);
$accessStmt->bind_param("iii", $userId, $facilityId, $roleId);
$accessStmt->execute();
$hasAccess = $accessStmt->get_result()->fetch_assoc();
$accessStmt->close();
if (!$hasAccess) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "You do not have access to this facility."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$bookingId = filter_var($_POST["booking_id"] ?? null, FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId < 1) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "A valid booking is required."]);
    exit();
}

$attendance = $_POST["attendance"] ?? "arrived";
if (!in_array($attendance, ["arrived", "no_show"], true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "A valid attendance outcome is required."]);
    exit();
}

$timestampColumn = $attendance === "arrived" ? "arrived_at" : "did_not_arrive_at";
$attendanceStmt = $conn->prepare(
    "UPDATE bookings b
     INNER JOIN court_details cd ON cd.court_id = b.court_id
     INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
     SET b.$timestampColumn = NOW()
     WHERE b.booking_id = ? AND cd.facility_id = ?
         AND cs.court_date = CURDATE() AND cs.schedule_status = 'Booked'
         AND b.arrived_at IS NULL AND b.did_not_arrive_at IS NULL"
);
$attendanceStmt->bind_param("ii", $bookingId, $facilityId);
$attendanceStmt->execute();
$updated = $attendanceStmt->affected_rows === 1;
$attendanceStmt->close();

if (!$updated) {
    http_response_code(409);
    echo json_encode(["success" => false, "message" => "This booking is no longer available to update."]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => $attendance === "arrived"
        ? "Booking marked as arrived."
        : "Booking marked as no-show.",
]);
