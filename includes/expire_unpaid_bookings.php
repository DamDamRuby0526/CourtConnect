<?php
if (!defined("EXPIRE_MODE")) {
    define("EXPIRE_MODE", "delete");
}

function expireUnpaidBookings(mysqli $conn)
{
    if (!in_array(EXPIRE_MODE, ["delete", "mark_expired"], true)) {
        throw new LogicException("EXPIRE_MODE must be 'delete' or 'mark_expired'.");
    }

    $candidateStmt = $conn->prepare(
        "SELECT b.booking_id
         FROM bookings b
         WHERE b.status = 'pending_payment'
             AND b.hold_expires_at < NOW()
             AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.booking_id)
         ORDER BY b.hold_expires_at ASC
         LIMIT 1000"
    );
    $candidateStmt->execute();
    $candidateResult = $candidateStmt->get_result();
    $bookingIds = [];
    while ($candidate = $candidateResult->fetch_assoc()) {
        $bookingIds[] = (int) $candidate["booking_id"];
    }
    $candidateStmt->close();

    $expiredCount = 0;
    $releasedCount = 0;
    foreach ($bookingIds as $bookingId) {
        try {
            if (!$conn->begin_transaction()) {
                throw new RuntimeException("Unable to start booking-expiry transaction.");
            }

            $bookingStmt = $conn->prepare(
                "SELECT b.booking_id, b.user_id, b.schedule_id, b.status, b.hold_expires_at,
                        cd.court_no, cs.court_date, cs.court_time, cs.schedule_status,
                        (b.hold_expires_at < NOW()) AS hold_expired
                 FROM bookings b
                 INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
                 INNER JOIN court_details cd ON cd.court_id = b.court_id
                 WHERE b.booking_id = ?
                 FOR UPDATE"
            );
            $bookingStmt->bind_param("i", $bookingId);
            $bookingStmt->execute();
            $booking = $bookingStmt->get_result()->fetch_assoc();
            $bookingStmt->close();

            $hasPayment = false;
            if ($booking) {
                $paymentCheckStmt = $conn->prepare(
                    "SELECT payments_id FROM payments WHERE booking_id = ? LIMIT 1 FOR UPDATE"
                );
                $paymentCheckStmt->bind_param("i", $bookingId);
                $paymentCheckStmt->execute();
                $hasPayment = (bool) $paymentCheckStmt->get_result()->fetch_assoc();
                $paymentCheckStmt->close();
            }

            if (!$booking || $booking["status"] !== "pending_payment"
                || (int) $booking["hold_expired"] !== 1 || $hasPayment) {
                $conn->commit();
                continue;
            }

            $releasedThisBooking = false;
            if ($booking["schedule_status"] === "Held") {
                $scheduleId = (int) $booking["schedule_id"];
                $releaseStmt = $conn->prepare(
                    "UPDATE court_schedule cs
                     INNER JOIN bookings b ON b.schedule_id = cs.schedule_id
                     SET cs.schedule_status = 'Available', cs.hold_expires_at = NULL
                     WHERE b.booking_id = ? AND b.schedule_id = ?
                         AND cs.schedule_status = 'Held'
                         AND NOT EXISTS (
                             SELECT 1 FROM bookings other_booking
                             WHERE other_booking.schedule_id = cs.schedule_id
                                 AND other_booking.booking_id <> b.booking_id
                                 AND other_booking.status IN ('pending_payment', 'pending', 'confirmed')
                         )"
                );
                $releaseStmt->bind_param("ii", $bookingId, $scheduleId);
                $releaseStmt->execute();
                $releasedThisBooking = $releaseStmt->affected_rows === 1;
                $releaseStmt->close();
            }

            if (EXPIRE_MODE === "delete") {
                $removeStmt = $conn->prepare(
                    "DELETE FROM bookings
                     WHERE booking_id = ? AND status = 'pending_payment'
                         AND hold_expires_at < NOW()
                         AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = bookings.booking_id)"
                );
                $removeStmt->bind_param("i", $bookingId);
                $removeStmt->execute();
                $removed = $removeStmt->affected_rows === 1;
                $removeStmt->close();
            } else {
                $expireStmt = $conn->prepare(
                    "UPDATE bookings SET status = 'Expired'
                     WHERE booking_id = ? AND status = 'pending_payment'
                         AND hold_expires_at < NOW()
                         AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = bookings.booking_id)"
                );
                $expireStmt->bind_param("i", $bookingId);
                $expireStmt->execute();
                $removed = $expireStmt->affected_rows === 1;
                $expireStmt->close();
            }

            if ($removed) {
                $expiredCount++;
                $releasedCount += $releasedThisBooking ? 1 : 0;
                $conn->commit();
            } else {
                $conn->rollback();
            }
        } catch (Throwable $error) {
            $conn->rollback();
            error_log("Unpaid booking expiry failed for booking {$bookingId}: " . $error->getMessage());
        }
    }

    return ["expired" => $expiredCount, "released" => $releasedCount];
}