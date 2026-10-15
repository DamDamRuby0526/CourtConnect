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
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
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

$bookingId = filter_var($_POST["booking_id"] ?? null, FILTER_VALIDATE_INT);
$requestedDuration = filter_var($_POST["total_duration"] ?? null, FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId < 1 || !$requestedDuration || $requestedDuration < 1) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid booking and duration."]);
    exit();
}

try {
    $conn->begin_transaction();
    $bookingStmt = $conn->prepare(
        "SELECT b.booking_id, b.status, b.payment_status, b.schedule_id, b.start_time, b.end_time,
                b.total_duration, b.hold_expires_at, cd.court_rate, cd.slot_duration, cs.court_date,
                cs.schedule_status, cs.hold_expires_at AS schedule_hold_expires_at
         FROM bookings b
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE b.booking_id = ? AND cd.facility_id = ?
         FOR UPDATE"
    );
    $bookingStmt->bind_param("ii", $bookingId, $facilityId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();
    if (!$booking || $booking["status"] !== "pending_payment"
        || strtolower((string) $booking["payment_status"]) !== "unpaid"
        || !$booking["start_time"] || !$booking["end_time"] || !(int) $booking["total_duration"]
        || strtotime((string) $booking["hold_expires_at"]) <= time()
        || $booking["schedule_status"] !== "Held"
        || strtotime((string) $booking["schedule_hold_expires_at"]) <= time()) {
        throw new RuntimeException("Only active unpaid reservations can have their duration changed.");
    }
    $paymentStmt = $conn->prepare("SELECT payments_id FROM payments WHERE booking_id = ? LIMIT 1 FOR UPDATE");
    $paymentStmt->bind_param("i", $bookingId);
    $paymentStmt->execute();
    $hasPayment = (bool) $paymentStmt->get_result()->fetch_assoc();
    $paymentStmt->close();
    if ($hasPayment) {
        throw new RuntimeException("Duration cannot be changed after a payment has been submitted.");
    }

    $slotDuration = (int) $booking["slot_duration"];
    $currentDuration = (int) $booking["total_duration"];
    if ($slotDuration < 1 || $requestedDuration >= $currentDuration
        || $requestedDuration % $slotDuration !== 0
        || $currentDuration % $slotDuration !== 0) {
        throw new RuntimeException("Choose a shorter duration in full court-slot increments.");
    }

    $slotsStmt = $conn->prepare(
        "SELECT cs.schedule_id, cs.court_time, cs.schedule_status, cs.hold_expires_at
         FROM court_schedule start_cs
         INNER JOIN court_schedule cs
             ON cs.court_id = start_cs.court_id
             AND cs.court_date = start_cs.court_date
             AND cs.court_time >= ?
             AND cs.court_time < ?
         WHERE start_cs.schedule_id = ?
         ORDER BY cs.court_time
         FOR UPDATE"
    );
    $slotsStmt->bind_param("ssi", $booking["start_time"], $booking["end_time"], $booking["schedule_id"]);
    $slotsStmt->execute();
    $slotsResult = $slotsStmt->get_result();
    $slots = [];
    while ($slot = $slotsResult->fetch_assoc()) $slots[] = $slot;
    $slotsStmt->close();
    $currentSlotCount = (int) ($currentDuration / $slotDuration);
    $newSlotCount = (int) ($requestedDuration / $slotDuration);
    if (count($slots) !== $currentSlotCount || $newSlotCount < 1) {
        throw new RuntimeException("The reservation schedule has changed. Refresh and try again.");
    }
    foreach ($slots as $slot) {
        if ($slot["schedule_status"] !== "Held" || strtotime((string) $slot["hold_expires_at"]) <= time()) {
            throw new RuntimeException("A slot in this reservation is no longer being held.");
        }
    }

    $newEndTime = (new DateTimeImmutable($booking["court_date"] . " " . $slots[$newSlotCount - 1]["court_time"]))
        ->modify("+{$slotDuration} minutes")
        ->format("H:i:s");
    $newAmount = (int) $booking["court_rate"] * $newSlotCount;
    $updateStmt = $conn->prepare(
        "UPDATE bookings
         SET end_time = ?, total_duration = ?, total_amount = ?
        WHERE booking_id = ? AND status = 'pending_payment' AND LOWER(payment_status) = 'unpaid'"
    );
    $updateStmt->bind_param("siii", $newEndTime, $requestedDuration, $newAmount, $bookingId);
    $updateStmt->execute();
    if ($updateStmt->affected_rows !== 1) {
        $updateStmt->close();
        throw new RuntimeException("The reservation changed before its duration could be updated.");
    }
    $updateStmt->close();

    $courtIdStmt = $conn->prepare("SELECT court_id FROM bookings WHERE booking_id = ?");
    $courtIdStmt->bind_param("i", $bookingId);
    $courtIdStmt->execute();
    $courtId = (int) $courtIdStmt->get_result()->fetch_assoc()["court_id"];
    $courtIdStmt->close();
    $conflictStmt = $conn->prepare(
        "SELECT other_booking.booking_id
         FROM bookings other_booking
         INNER JOIN court_schedule other_start ON other_start.schedule_id = other_booking.schedule_id
         INNER JOIN court_details other_cd ON other_cd.court_id = other_booking.court_id
         WHERE other_booking.booking_id <> ?
             AND other_booking.court_id = ?
             AND other_start.court_date = ?
             AND other_booking.status IN ('pending_payment', 'pending', 'confirmed')
             AND (other_booking.status = 'confirmed' OR other_booking.hold_expires_at > NOW())
             AND COALESCE(other_booking.start_time, other_start.court_time)
                 < ADDTIME(?, SEC_TO_TIME(? * 60))
             AND COALESCE(other_booking.end_time,
                 ADDTIME(other_start.court_time, SEC_TO_TIME(other_cd.slot_duration * 60)))
                 > ?
         LIMIT 1
         FOR UPDATE"
    );
    $releaseStmt = $conn->prepare(
        "UPDATE court_schedule
         SET schedule_status = 'Available', hold_expires_at = NULL
         WHERE schedule_id = ? AND schedule_status = 'Held' AND hold_expires_at > NOW()"
    );
    for ($index = $newSlotCount; $index < $currentSlotCount; $index++) {
        $scheduleId = (int) $slots[$index]["schedule_id"];
        $slotEndTime = (new DateTimeImmutable($booking["court_date"] . " " . $slots[$index]["court_time"]))
            ->modify("+{$slotDuration} minutes")
            ->format("H:i:s");
        $conflictStmt->bind_param(
            "iissis",
            $bookingId,
            $courtId,
            $booking["court_date"],
            $slotEndTime,
            $slotDuration,
            $slots[$index]["court_time"]
        );
        $conflictStmt->execute();
        $hasConflict = (bool) $conflictStmt->get_result()->fetch_assoc();
        if ($hasConflict) {
            $conflictStmt->close();
            $releaseStmt->close();
            throw new RuntimeException("A removed slot is now needed by another reservation.");
        }
        $releaseStmt->bind_param("i", $scheduleId);
        $releaseStmt->execute();
        if ($releaseStmt->affected_rows !== 1) {
            $conflictStmt->close();
            $releaseStmt->close();
            throw new RuntimeException("A removed slot could not be released safely.");
        }
    }
    $conflictStmt->close();
    $releaseStmt->close();
    $conn->commit();
    echo json_encode([
        "success" => true,
        "message" => "Reservation duration updated. The total price was recalculated.",
        "total_duration" => $requestedDuration,
        "total_amount" => $newAmount,
    ]);
} catch (RuntimeException $error) {
    $conn->rollback();
    http_response_code(409);
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    error_log("Admin booking duration update failed: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to update the booking duration."]);
}
$conn->close();
