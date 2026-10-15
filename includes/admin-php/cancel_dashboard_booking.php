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
        "SELECT b.booking_id, b.schedule_id, b.court_id, b.start_time, b.end_time,
                cs.court_date, cd.slot_duration
         FROM bookings b
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE b.booking_id = ? AND cd.facility_id = ? AND cs.court_date = CURDATE()
             AND b.status IN ('confirmed', 'pending')
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

    $scheduleIds = bookingRangeScheduleIds($conn, $bookingId);
    if (!$scheduleIds) {
        throw new RuntimeException("The reservation schedule could not be found.");
    }
    $placeholders = implode(",", array_fill(0, count($scheduleIds), "?"));
    $types = str_repeat("i", count($scheduleIds));
    $slotsStmt = $conn->prepare(
        "SELECT schedule_id, schedule_status
         FROM court_schedule
         WHERE schedule_id IN ($placeholders)
         ORDER BY court_time
         FOR UPDATE"
    );
    $slotsStmt->bind_param($types, ...$scheduleIds);
    $slotsStmt->execute();
    $slotsResult = $slotsStmt->get_result();
    $slots = [];
    while ($slot = $slotsResult->fetch_assoc()) $slots[] = $slot;
    $slotsStmt->close();
    if (count($slots) !== count($scheduleIds)) {
        throw new RuntimeException("The reservation schedule could not be found.");
    }
    foreach ($slots as $slot) {
        if ($slot["schedule_status"] !== "Booked") {
            throw new RuntimeException("A time in this reservation is no longer booked.");
        }
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

    $conflictStmt = $conn->prepare(
        "SELECT other_booking.booking_id
         FROM bookings other_booking
         INNER JOIN court_schedule other_start ON other_start.schedule_id = other_booking.schedule_id
         INNER JOIN court_details other_cd ON other_cd.court_id = other_booking.court_id
         INNER JOIN court_schedule slot ON slot.schedule_id = ?
         WHERE other_booking.booking_id <> ?
             AND other_booking.court_id = ?
             AND other_start.court_date = slot.court_date
             AND other_booking.status IN ('pending_payment', 'pending', 'confirmed')
             AND (other_booking.status = 'confirmed' OR other_booking.hold_expires_at > NOW())
             AND COALESCE(other_booking.start_time, other_start.court_time)
                 < ADDTIME(slot.court_time, SEC_TO_TIME(other_cd.slot_duration * 60))
             AND COALESCE(other_booking.end_time,
                 ADDTIME(other_start.court_time, SEC_TO_TIME(other_cd.slot_duration * 60)))
                 > slot.court_time
         LIMIT 1
         FOR UPDATE"
    );
    $releaseStmt = $conn->prepare(
        "UPDATE court_schedule SET schedule_status = 'Available', hold_expires_at = NULL
         WHERE schedule_id = ? AND schedule_status = 'Booked'"
    );
    foreach ($scheduleIds as $scheduleId) {
        $conflictStmt->bind_param("iii", $scheduleId, $bookingId, $booking["court_id"]);
        $conflictStmt->execute();
        $hasConflict = (bool) $conflictStmt->get_result()->fetch_assoc();
        if ($hasConflict) continue;
        $releaseStmt->bind_param("i", $scheduleId);
        $releaseStmt->execute();
        if ($releaseStmt->affected_rows !== 1) {
            $conflictStmt->close();
            $releaseStmt->close();
            throw new RuntimeException("A court time in this reservation could not be released.");
        }
    }
    $conflictStmt->close();
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