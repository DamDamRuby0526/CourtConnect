<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/../cancel_booking.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "code" => "unauthorized",
        "message" => "Sign in with a player account to cancel this booking.",
    ]);
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "code" => "invalid_method", "message" => "Invalid request method."]);
    exit();
}

$bookingId = filter_var($_POST["booking_id"] ?? null, FILTER_VALIDATE_INT);
$bookingGroupId = filter_var($_POST["booking_group_id"] ?? null, FILTER_VALIDATE_INT);
if ($bookingGroupId && $bookingGroupId > 0) {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["success" => false, "code" => "invalid_method", "message" => "Invalid request method."]);
        exit();
    }
    try {
        $conn->begin_transaction();
        $groupStmt = $conn->prepare(
            "SELECT b.booking_id, b.schedule_id, b.status, b.payment_status, b.hold_expires_at,
                    cs.schedule_status, cs.hold_expires_at AS schedule_hold_expires_at,
                    cd.facility_id,
                    (b.hold_expires_at > NOW() AND cs.hold_expires_at > NOW()
                        AND cs.schedule_status = 'Held') AS active_hold,
                    EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.booking_id) AS has_payment
             FROM bookings b
             INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
             INNER JOIN court_details cd ON cd.court_id = b.court_id
             WHERE b.booking_group_id = ? AND b.user_id = ?
             ORDER BY b.booking_id
             FOR UPDATE"
        );
        $groupStmt->bind_param("ii", $bookingGroupId, $userId);
        $groupStmt->execute();
        $groupResult = $groupStmt->get_result();
        $groupBookings = [];
        while ($row = $groupResult->fetch_assoc()) $groupBookings[] = $row;
        $groupStmt->close();
        $cancellable = count($groupBookings) > 0;
        foreach ($groupBookings as $row) {
            $cancellable = $cancellable
                && $row["status"] === "pending_payment"
                && strtolower((string) $row["payment_status"]) === "unpaid"
                && (int) $row["has_payment"] === 0
                && (int) $row["active_hold"] === 1;
        }
        if (!$cancellable) {
            $conn->rollback();
            http_response_code(409);
            echo json_encode(["success" => false, "code" => "not_cancellable", "message" => "This reservation can no longer be cancelled."]);
            exit();
        }
        $scheduleIds = array_map(static function ($row) {
            return (int) $row["schedule_id"];
        }, $groupBookings);
        $releaseStmt = $conn->prepare(
            "UPDATE court_schedule cs
             SET cs.schedule_status = 'Available', cs.hold_expires_at = NULL
             WHERE cs.schedule_id = ? AND cs.schedule_status = 'Held'
                 AND NOT EXISTS (
                     SELECT 1 FROM bookings other_booking
                     WHERE other_booking.schedule_id = cs.schedule_id
                         AND (other_booking.booking_group_id IS NULL
                             OR other_booking.booking_group_id <> ?)
                         AND other_booking.status IN ('pending_payment', 'pending', 'confirmed')
                 )"
        );
        foreach ($scheduleIds as $scheduleId) {
            $releaseStmt->bind_param("ii", $scheduleId, $bookingGroupId);
            $releaseStmt->execute();
            if ($releaseStmt->affected_rows !== 1) {
                throw new RuntimeException("A court time in this reservation could not be released.");
            }
        }
        $releaseStmt->close();
        $deleteStmt = $conn->prepare(
            "DELETE FROM bookings WHERE booking_group_id = ? AND user_id = ? AND status = 'pending_payment'"
        );
        $deleteStmt->bind_param("ii", $bookingGroupId, $userId);
        $deleteStmt->execute();
        if ($deleteStmt->affected_rows !== count($groupBookings)) {
            $deleteStmt->close();
            throw new RuntimeException("This reservation could not be cancelled.");
        }
        $deleteStmt->close();
        $conn->commit();
        echo json_encode([
            "success" => true,
            "code" => "deleted",
            "message" => "Reservation cancelled. All selected time slots are available again.",
            "facility_id" => (int) $groupBookings[0]["facility_id"],
        ]);
    } catch (Throwable $error) {
        $conn->rollback();
        error_log("Grouped checkout cancellation failed: " . $error->getMessage());
        http_response_code(409);
        echo json_encode(["success" => false, "code" => "not_cancellable", "message" => "This reservation could not be cancelled. Please try again."]);
    }
    $conn->close();
    exit();
}
if (!$bookingId || $bookingId < 1) {
    http_response_code(400);
    echo json_encode(["success" => false, "code" => "invalid_booking", "message" => "Choose a valid booking."]);
    exit();
}

$result = cancelBookingForUser((int) $bookingId, (int) $userId);
$httpStatus = (int) ($result["http_status"] ?? 500);
unset($result["http_status"]);
http_response_code($httpStatus);
echo json_encode($result);