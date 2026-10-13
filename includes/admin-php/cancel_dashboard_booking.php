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

try {
    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start the cancellation transaction.");
    }

    $bookingStmt = $conn->prepare(
        "SELECT b.schedule_id
         FROM bookings b
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE b.booking_id = ? AND cd.facility_id = ? AND cs.court_date = CURDATE()
             AND cs.schedule_status = 'Booked' AND b.status IN ('confirmed', 'pending')
             AND b.arrived_at IS NULL AND b.did_not_arrive_at IS NULL
         FOR UPDATE"
    );
    $bookingStmt->bind_param("ii", $bookingId, $facilityId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();
    if (!$booking) {
        throw new RuntimeException("This reservation can no longer be cancelled.");
    }

    $cancelStmt = $conn->prepare(
        "UPDATE bookings SET status = 'cancelled'
         WHERE booking_id = ? AND status IN ('confirmed', 'pending')
             AND arrived_at IS NULL AND did_not_arrive_at IS NULL"
    );
    $cancelStmt->bind_param("i", $bookingId);
    $cancelStmt->execute();
    if ($cancelStmt->affected_rows !== 1) {
        $cancelStmt->close();
        throw new RuntimeException("This reservation can no longer be cancelled.");
    }
    $cancelStmt->close();

    $scheduleId = (int) $booking["schedule_id"];
    $releaseStmt = $conn->prepare(
        "UPDATE court_schedule SET schedule_status = 'Available'
         WHERE schedule_id = ? AND schedule_status = 'Booked'"
    );
    $releaseStmt->bind_param("i", $scheduleId);
    $releaseStmt->execute();
    if ($releaseStmt->affected_rows !== 1) {
        $releaseStmt->close();
        throw new RuntimeException("The court time could not be released.");
    }
    $releaseStmt->close();

    if (!$conn->commit()) {
        throw new RuntimeException("Unable to save the cancellation.");
    }
    echo json_encode(["success" => true, "message" => "Reservation cancelled and court time released."]);
} catch (RuntimeException $error) {
    $conn->rollback();
    http_response_code(409);
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    error_log("Dashboard reservation cancellation failed: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to cancel this reservation."]);
}