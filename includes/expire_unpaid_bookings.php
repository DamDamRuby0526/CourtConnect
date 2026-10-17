<?php
if (!defined("EXPIRE_MODE")) {
    define("EXPIRE_MODE", "delete");
}

function bookingRangeScheduleIds(mysqli $conn, int $bookingId): array
{
    $stmt = $conn->prepare(
        "SELECT cs.schedule_id
         FROM bookings b
         INNER JOIN court_schedule start_cs ON start_cs.schedule_id = b.schedule_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs
             ON cs.court_id = b.court_id
             AND cs.court_date = start_cs.court_date
             AND cs.court_time >= COALESCE(b.start_time, start_cs.court_time)
             AND cs.court_time < COALESCE(
                 b.end_time,
                 ADDTIME(start_cs.court_time, SEC_TO_TIME(cd.slot_duration * 60))
             )
         WHERE b.booking_id = ?
         ORDER BY cs.court_time"
    );
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $scheduleIds = [];
    while ($row = $result->fetch_assoc()) $scheduleIds[] = (int) $row["schedule_id"];
    $stmt->close();
    return $scheduleIds ?: [];
}

function expireRejectedPaymentBookings(mysqli $conn): array
{
    $schemaStmt = $conn->prepare(
        "SELECT COUNT(*) AS required_columns
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
             AND (
                 (TABLE_NAME = 'payments' AND COLUMN_NAME IN ('rejected_at', 'resubmission_deadline'))
                 OR (TABLE_NAME = 'bookings' AND COLUMN_NAME = 'cancellation_reason')
             )"
    );
    $schemaStmt->execute();
    $schemaReady = (int) ($schemaStmt->get_result()->fetch_assoc()["required_columns"] ?? 0) === 3;
    $schemaStmt->close();
    if (!$schemaReady) {
        error_log("Rejected-payment expiry is disabled until the payment resubmission migration is applied.");
        return ["expired" => 0, "released" => 0];
    }

    $candidateStmt = $conn->prepare(
        "SELECT MAX(b.booking_group_id) AS booking_group_id,
                MIN(b.booking_id) AS booking_id
         FROM bookings b
         INNER JOIN payments p ON p.booking_id = b.booking_id
             AND p.payments_id = (
                 SELECT MAX(latest.payments_id)
                 FROM payments latest
                 WHERE latest.booking_id = b.booking_id
             )
         WHERE b.status IN ('pending', 'pending_payment')
             AND b.payment_status = 'rejected'
             AND p.payment_status = 'Rejected'
             AND p.resubmission_deadline <= NOW()
         GROUP BY COALESCE(b.booking_group_id, b.booking_id)
         ORDER BY MIN(p.resubmission_deadline)
         LIMIT 1000"
    );
    $candidateStmt->execute();
    $result = $candidateStmt->get_result();
    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = [
            "group_id" => (int) ($row["booking_group_id"] ?? 0),
            "booking_id" => (int) $row["booking_id"],
        ];
    }
    $candidateStmt->close();

    $expiredCount = 0;
    $releasedCount = 0;
    foreach ($candidates as $candidate) {
        $groupId = $candidate["group_id"];
        $bookingId = $candidate["booking_id"];
        $lookupId = $groupId > 0 ? $groupId : $bookingId;
        $where = $groupId > 0 ? "b.booking_group_id = ?" : "b.booking_id = ?";
        try {
            if (!$conn->begin_transaction()) {
                throw new RuntimeException("Unable to start rejected-payment expiry transaction.");
            }
            $bookingStmt = $conn->prepare(
                "SELECT b.booking_id, b.booking_group_id, b.schedule_id, b.status, b.payment_status,
                        p.payment_status AS latest_payment_status, p.resubmission_deadline,
                        (p.resubmission_deadline <= NOW()) AS deadline_passed
                 FROM bookings b
                 INNER JOIN payments p ON p.booking_id = b.booking_id
                     AND p.payments_id = (
                         SELECT MAX(latest.payments_id)
                         FROM payments latest
                         WHERE latest.booking_id = b.booking_id
                     )
                 WHERE $where
                 ORDER BY b.booking_id
                 FOR UPDATE"
            );
            $bookingStmt->bind_param("i", $lookupId);
            $bookingStmt->execute();
            $bookingResult = $bookingStmt->get_result();
            $bookings = [];
            while ($row = $bookingResult->fetch_assoc()) {
                $bookings[] = $row;
            }
            $bookingStmt->close();

            $canExpire = count($bookings) > 0;
            foreach ($bookings as $booking) {
                $canExpire = $canExpire
                    && in_array($booking["status"], ["pending", "pending_payment"], true)
                    && strtolower((string) $booking["payment_status"]) === "rejected"
                    && $booking["latest_payment_status"] === "Rejected"
                    && (int) $booking["deadline_passed"] === 1;
            }
            if (!$canExpire) {
                $conn->commit();
                continue;
            }

            $cancelStmt = $conn->prepare(
                "UPDATE bookings
                 SET status = 'cancelled',
                     cancellation_reason = 'Payment was not resubmitted within 24 hours after rejection.'
                 WHERE " . ($groupId > 0 ? "booking_group_id = ?" : "booking_id = ?") . "
                     AND status IN ('pending', 'pending_payment')
                     AND payment_status = 'rejected'"
            );
            $cancelStmt->bind_param("i", $lookupId);
            $cancelStmt->execute();
            $cancelledCount = $cancelStmt->affected_rows;
            $cancelStmt->close();
            if ($cancelledCount !== count($bookings)) {
                $conn->rollback();
                continue;
            }

            $releasedThisGroup = 0;
            foreach ($bookings as $booking) {
                $currentBookingId = (int) $booking["booking_id"];
                $scheduleIds = bookingRangeScheduleIds($conn, $currentBookingId);
                if (!$scheduleIds) {
                    $scheduleIds[] = (int) $booking["schedule_id"];
                }
                foreach ($scheduleIds as $scheduleId) {
                    $releaseStmt = $conn->prepare(
                        "UPDATE court_schedule cs
                         INNER JOIN court_details cd ON cd.court_id = cs.court_id
                         SET cs.schedule_status = 'Available', cs.hold_expires_at = NULL
                         WHERE cs.schedule_id = ? AND cs.schedule_status = 'Held'
                             AND NOT EXISTS (
                                 SELECT 1
                                 FROM bookings other_booking
                                 INNER JOIN court_schedule other_start ON other_start.schedule_id = other_booking.schedule_id
                                 INNER JOIN court_details other_cd ON other_cd.court_id = other_booking.court_id
                                 WHERE other_booking.booking_id <> ?
                                     AND other_booking.court_id = cs.court_id
                                     AND other_start.court_date = cs.court_date
                                     AND other_booking.status IN ('pending_payment', 'pending', 'confirmed')
                                     AND (other_booking.status = 'confirmed' OR other_booking.hold_expires_at > NOW())
                                     AND COALESCE(other_booking.start_time, other_start.court_time)
                                         < ADDTIME(cs.court_time, SEC_TO_TIME(cd.slot_duration * 60))
                                     AND COALESCE(other_booking.end_time,
                                         ADDTIME(other_start.court_time, SEC_TO_TIME(other_cd.slot_duration * 60)))
                                         > cs.court_time
                             )"
                    );
                    $releaseStmt->bind_param("ii", $scheduleId, $currentBookingId);
                    $releaseStmt->execute();
                    $releasedThisGroup += $releaseStmt->affected_rows;
                    $releaseStmt->close();
                }
            }
            $expiredCount += $cancelledCount;
            $releasedCount += $releasedThisGroup;
            $conn->commit();
        } catch (Throwable $error) {
            $conn->rollback();
            error_log("Rejected-payment expiry failed for booking {$bookingId}: " . $error->getMessage());
        }
    }

    return ["expired" => $expiredCount, "released" => $releasedCount];
}

function expireUnpaidBookings(mysqli $conn)
{
    if (!in_array(EXPIRE_MODE, ["delete", "mark_expired"], true)) {
        throw new LogicException("EXPIRE_MODE must be 'delete' or 'mark_expired'.");
    }

    $candidateStmt = $conn->prepare(
        "SELECT COALESCE(b.booking_group_id, b.booking_id) AS expiry_key,
                MAX(b.booking_group_id) AS booking_group_id,
                MIN(b.booking_id) AS booking_id
         FROM bookings b
         WHERE b.status = 'pending_payment'
             AND b.hold_expires_at < NOW()
             AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.booking_id)
         GROUP BY COALESCE(b.booking_group_id, b.booking_id)
         ORDER BY MIN(b.hold_expires_at) ASC
         LIMIT 1000"
    );
    $candidateStmt->execute();
    $candidateResult = $candidateStmt->get_result();
    $expiryCandidates = [];
    while ($candidate = $candidateResult->fetch_assoc()) {
        $expiryCandidates[] = [
            "group_id" => (int) ($candidate["booking_group_id"] ?? 0),
            "booking_id" => (int) $candidate["booking_id"],
        ];
    }
    $candidateStmt->close();

    $expiredCount = 0;
    $releasedCount = 0;
    foreach ($expiryCandidates as $candidate) {
        $bookingId = $candidate["booking_id"];
        $groupId = $candidate["group_id"];
        try {
            if (!$conn->begin_transaction()) {
                throw new RuntimeException("Unable to start booking-expiry transaction.");
            }

            $bookingStmt = $conn->prepare(
                "SELECT b.booking_id, b.user_id, b.schedule_id, b.status, b.hold_expires_at,
                        b.booking_group_id, cs.schedule_status,
                        (b.hold_expires_at < NOW()) AS hold_expired,
                        EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.booking_id) AS has_payment
                 FROM bookings b
                 INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
                 WHERE " . ($groupId > 0 ? "b.booking_group_id = ?" : "b.booking_id = ?") . "
                 FOR UPDATE"
            );
            $lookupId = $groupId > 0 ? $groupId : $bookingId;
            $bookingStmt->bind_param("i", $lookupId);
            $bookingStmt->execute();
            $bookingResult = $bookingStmt->get_result();
            $bookings = [];
            while ($booking = $bookingResult->fetch_assoc()) {
                $bookings[] = $booking;
            }
            $bookingStmt->close();

            $canExpire = count($bookings) > 0;
            foreach ($bookings as $booking) {
                $canExpire = $canExpire
                    && $booking["status"] === "pending_payment"
                    && (int) $booking["hold_expired"] === 1
                    && (int) $booking["has_payment"] === 0;
            }
            if (!$canExpire) {
                $conn->commit();
                continue;
            }

            $releasedThisGroup = 0;
            foreach ($bookings as $booking) {
                $currentBookingId = (int) $booking["booking_id"];
                foreach (bookingRangeScheduleIds($conn, $currentBookingId) as $scheduleId) {
                    $releaseStmt = $conn->prepare(
                        "UPDATE court_schedule cs
                         INNER JOIN court_details cd ON cd.court_id = cs.court_id
                         SET cs.schedule_status = 'Available', cs.hold_expires_at = NULL
                         WHERE cs.schedule_id = ? AND cs.schedule_status = 'Held'
                             AND NOT EXISTS (
                                 SELECT 1
                                 FROM bookings other_booking
                                 INNER JOIN court_schedule other_start ON other_start.schedule_id = other_booking.schedule_id
                                 INNER JOIN court_details other_cd ON other_cd.court_id = other_booking.court_id
                                 WHERE other_booking.booking_id <> ?
                                     AND other_booking.court_id = cs.court_id
                                     AND other_start.court_date = cs.court_date
                                     AND other_booking.status IN ('pending_payment', 'pending', 'confirmed')
                                     AND (other_booking.status = 'confirmed' OR other_booking.hold_expires_at > NOW())
                                     AND COALESCE(other_booking.start_time, other_start.court_time)
                                         < ADDTIME(cs.court_time, SEC_TO_TIME(cd.slot_duration * 60))
                                     AND COALESCE(other_booking.end_time,
                                         ADDTIME(other_start.court_time, SEC_TO_TIME(other_cd.slot_duration * 60)))
                                         > cs.court_time
                             )"
                    );
                    $releaseStmt->bind_param("ii", $scheduleId, $currentBookingId);
                    $releaseStmt->execute();
                    $releasedThisGroup += $releaseStmt->affected_rows;
                    $releaseStmt->close();
                }
            }

            if (EXPIRE_MODE === "delete") {
                if ($groupId > 0) {
                    $removeStmt = $conn->prepare(
                        "DELETE FROM bookings
                         WHERE booking_group_id = ? AND status = 'pending_payment'
                             AND hold_expires_at < NOW()
                             AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = bookings.booking_id)"
                    );
                    $removeStmt->bind_param("i", $groupId);
                    $removeStmt->execute();
                    $removedCount = $removeStmt->affected_rows;
                    $removeStmt->close();
                } else {
                    $removeStmt = $conn->prepare(
                        "DELETE FROM bookings
                         WHERE booking_id = ? AND status = 'pending_payment'
                             AND hold_expires_at < NOW()
                             AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = bookings.booking_id)"
                    );
                    $removeStmt->bind_param("i", $bookingId);
                    $removeStmt->execute();
                    $removedCount = $removeStmt->affected_rows;
                    $removeStmt->close();
                }
            } else {
                $expireWhere = $groupId > 0 ? "booking_group_id = ?" : "booking_id = ?";
                $expireStmt = $conn->prepare(
                    "UPDATE bookings SET status = 'Expired'
                     WHERE $expireWhere AND status = 'pending_payment'
                         AND hold_expires_at < NOW()
                         AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = bookings.booking_id)"
                );
                $expireStmt->bind_param("i", $lookupId);
                $expireStmt->execute();
                $removedCount = $expireStmt->affected_rows;
                $expireStmt->close();
            }

            if ($removedCount === count($bookings)) {
                $expiredCount += $removedCount;
                $releasedCount += $releasedThisGroup;
                $conn->commit();
            } else {
                $conn->rollback();
            }
        } catch (Throwable $error) {
            $conn->rollback();
            error_log("Unpaid booking expiry failed for booking {$bookingId}: " . $error->getMessage());
        }
    }

    $rejectedPaymentExpiry = expireRejectedPaymentBookings($conn);
    return [
        "expired" => $expiredCount,
        "rejected_expired" => $rejectedPaymentExpiry["expired"],
        "released" => $releasedCount + $rejectedPaymentExpiry["released"],
    ];
}