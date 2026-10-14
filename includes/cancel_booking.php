<?php
function cancelBookingForUser(int $bookingId, int $userId): array
{
    global $conn;

    if ($bookingId < 1 || $userId < 1) {
        return ["success" => false, "code" => "not_found", "message" => "Booking not found.", "http_status" => 404];
    }

    try {
        $policyStmt = $conn->prepare(
            "SELECT COUNT(*) AS column_count
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'facilities'
                 AND column_name = 'cancellation_hours'"
        );
        $policyStmt->execute();
        $hasCancellationHours = (int) $policyStmt->get_result()->fetch_assoc()["column_count"] > 0;
        $policyStmt->close();
        $cancellationHoursExpression = $hasCancellationHours ? "f.cancellation_hours" : "2";

        $conn->begin_transaction();
        $bookingStmt = $conn->prepare(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM payments p WHERE p.booking_id = b.booking_id) AS payment_rows,
                    (SELECT p.payment_status FROM payments p WHERE p.booking_id = b.booking_id
                        ORDER BY p.payments_id DESC LIMIT 1) AS latest_payment_status,
                    cs.schedule_status, cs.court_date, cs.court_time,
                    (b.status IN ('pending_payment', 'pending')
                        AND cs.schedule_status = 'Held'
                        AND b.hold_expires_at > NOW()
                        AND cs.hold_expires_at > NOW()) AS active_hold,
                    cd.facility_id,
                    $cancellationHoursExpression AS cancellation_hours,
                    UNIX_TIMESTAMP(TIMESTAMP(cs.court_date, cs.court_time)) AS slot_start_epoch,
                    UNIX_TIMESTAMP(NOW()) AS mysql_now_epoch
             FROM bookings b
             INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
             INNER JOIN court_details cd ON cd.court_id = b.court_id
             INNER JOIN facilities f ON f.facility_id = cd.facility_id
             WHERE b.booking_id = ? AND b.user_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $bookingStmt->bind_param("ii", $bookingId, $userId);
        $bookingStmt->execute();
        $booking = $bookingStmt->get_result()->fetch_assoc();
        $bookingStmt->close();

        if (!$booking) {
            $conn->rollback();
            $recentCancellations = $_SESSION["cancelled_booking_ids"] ?? [];
            if (isset($recentCancellations[(string) $bookingId])) {
                $cancellation = $recentCancellations[(string) $bookingId];
                return [
                    "success" => true,
                    "code" => "already_gone",
                    "message" => "This booking was already cancelled.",
                    "facility_id" => (int) ($cancellation["facility_id"] ?? 0),
                    "schedule_id" => (int) ($cancellation["schedule_id"] ?? 0),
                    "http_status" => 200,
                ];
            }
            return ["success" => false, "code" => "not_found", "message" => "Booking not found.", "http_status" => 404];
        }

        $paymentRows = (int) $booking["payment_rows"];
        $bookingStatus = strtolower((string) $booking["status"]);
        $paymentStatus = strtolower((string) $booking["payment_status"]);
        $isUnpaidWaiting = $paymentRows === 0
            && $bookingStatus === "pending_payment"
            && $paymentStatus === "unpaid";
        $isLegacyUnpaidCancellation = $paymentRows === 0
            && $bookingStatus === "cancelled"
            && $paymentStatus === "unpaid";
        $isPaidOrSubmitted = $paymentRows > 0 || $bookingStatus === "confirmed";
        $isPaymentRejected = $paymentRows > 0
            ? strtolower((string) $booking["latest_payment_status"]) === "rejected"
            : $paymentStatus === "rejected";
        $hasActiveHold = (int) $booking["active_hold"] === 1;

        if ($isUnpaidWaiting || $isLegacyUnpaidCancellation) {
            if (!$hasActiveHold) {
                $conn->rollback();
                return ["success" => false, "code" => "not_cancellable", "message" => "This booking can no longer be cancelled.", "http_status" => 409];
            }
            if ($booking["schedule_status"] === "Held") {
                $scheduleId = (int) $booking["schedule_id"];
                $releaseStmt = $conn->prepare(
                    "UPDATE court_schedule cs
                     SET cs.schedule_status = 'Available', cs.hold_expires_at = NULL
                     WHERE cs.schedule_id = ? AND cs.schedule_status = 'Held'
                         AND NOT EXISTS (
                             SELECT 1 FROM bookings other_booking
                             WHERE other_booking.schedule_id = cs.schedule_id
                                 AND other_booking.booking_id <> ?
                                 AND other_booking.status IN ('pending_payment', 'pending', 'confirmed')
                         )"
                );
                $releaseStmt->bind_param("ii", $scheduleId, $bookingId);
                $releaseStmt->execute();
                $releaseStmt->close();
            }

            $deleteStmt = $conn->prepare(
                "DELETE FROM bookings WHERE booking_id = ? AND user_id = ?"
            );
            $deleteStmt->bind_param("ii", $bookingId, $userId);
            $deleteStmt->execute();
            $deleted = $deleteStmt->affected_rows === 1;
            $deleteStmt->close();
            if (!$deleted) {
                throw new RuntimeException("Unpaid booking could not be deleted.");
            }

            $conn->commit();
            $_SESSION["cancelled_booking_ids"][(string) $bookingId] = [
                "cancelled_at" => time(),
                "facility_id" => (int) $booking["facility_id"],
                "schedule_id" => (int) $booking["schedule_id"],
            ];
            if (count($_SESSION["cancelled_booking_ids"]) > 50) {
                $_SESSION["cancelled_booking_ids"] = array_slice($_SESSION["cancelled_booking_ids"], -50, null, true);
            }

            return [
                "success" => true,
                "code" => "deleted",
                "message" => "Booking cancelled. The time slot is available again.",
                "booking_id" => $bookingId,
                "schedule_id" => (int) $booking["schedule_id"],
                "facility_id" => (int) $booking["facility_id"],
                "http_status" => 200,
            ];
        }

        if ($bookingStatus === "cancelled") {
            $conn->commit();
            return [
                "success" => true,
                "code" => "cancelled",
                "message" => "Booking cancelled. Contact the facility about your payment.",
                "booking_id" => $bookingId,
                "schedule_id" => (int) $booking["schedule_id"],
                "facility_id" => (int) $booking["facility_id"],
                "http_status" => 200,
            ];
        }

        if (!$isPaymentRejected && !$hasActiveHold) {
            $conn->rollback();
            return ["success" => false, "code" => "not_cancellable", "message" => "This booking can no longer be cancelled.", "http_status" => 409];
        }

        if (!$isPaidOrSubmitted) {
            $conn->rollback();
            return ["success" => false, "code" => "not_cancellable", "message" => "This booking can no longer be cancelled.", "http_status" => 409];
        }

        $cancellationHours = is_numeric($booking["cancellation_hours"])
            ? max(0, (float) $booking["cancellation_hours"])
            : 2.0;
        $cutoffEpoch = (int) $booking["slot_start_epoch"] - (int) round($cancellationHours * 3600);
        if ((int) $booking["mysql_now_epoch"] > $cutoffEpoch) {
            returnAfterRollback($conn);
            return [
                "success" => false,
                "code" => "too_late",
                "message" => "This booking can no longer be cancelled. The cutoff was " . date("g:i A on D, M j", $cutoffEpoch) . ".",
                "http_status" => 409,
            ];
        }

        $cancelStmt = $conn->prepare(
            "UPDATE bookings SET status = 'cancelled', hold_expires_at = NULL
             WHERE booking_id = ? AND user_id = ? AND status <> 'cancelled'"
        );
        $cancelStmt->bind_param("ii", $bookingId, $userId);
        $cancelStmt->execute();
        $cancelled = $cancelStmt->affected_rows === 1;
        $cancelStmt->close();
        if (!$cancelled) {
            throw new RuntimeException("Booking was not cancelled.");
        }

        if (in_array($booking["schedule_status"], ["Held", "Booked"], true)) {
            $scheduleId = (int) $booking["schedule_id"];
            $releaseStmt = $conn->prepare(
                "UPDATE court_schedule cs
                 SET cs.schedule_status = 'Available', cs.hold_expires_at = NULL
                 WHERE cs.schedule_id = ? AND cs.schedule_status IN ('Held', 'Booked')
                     AND NOT EXISTS (
                         SELECT 1 FROM bookings other_booking
                         WHERE other_booking.schedule_id = cs.schedule_id
                             AND other_booking.booking_id <> ?
                             AND other_booking.status IN ('pending_payment', 'pending', 'confirmed')
                     )"
            );
            $releaseStmt->bind_param("ii", $scheduleId, $bookingId);
            $releaseStmt->execute();
            $released = $releaseStmt->affected_rows === 1;
            $releaseStmt->close();
            if (!$released) {
                throw new RuntimeException("Court time could not be released.");
            }
        }

        $conn->commit();
        return [
            "success" => true,
            "code" => "cancelled",
            "message" => "Booking cancelled. Contact the facility about your payment.",
            "booking_id" => $bookingId,
            "schedule_id" => (int) $booking["schedule_id"],
            "facility_id" => (int) $booking["facility_id"],
            "http_status" => 200,
        ];
    } catch (Throwable $error) {
        if ($conn->in_transaction) {
            $conn->rollback();
        }
        error_log("Player booking cancellation failed for booking {$bookingId}: " . $error->getMessage());
        return ["success" => false, "code" => "server_error", "message" => "Something went wrong. Please try again.", "http_status" => 500];
    }
}

function returnAfterRollback(mysqli $conn): void
{
    $conn->rollback();
}