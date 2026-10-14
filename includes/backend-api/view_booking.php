<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Sign in with a player account to view bookings."]);
    exit();
}

$stmt = $conn->prepare(
    "SELECT b.booking_id, b.status AS booking_status, b.payment_status AS booking_payment_status,
            b.total_amount, b.created_at, b.arrived_at, b.did_not_arrive_at,
            cs.court_date, cs.court_time, cs.schedule_status, cs.hold_expires_at,
            cd.court_no, cd.slot_duration, cd.facility_id, f.facility_name, f.municipality,
            p.payments_id, p.payment_status AS payment_record_status,
            p.rejection_reason, p.receipt_image,
            CASE
                WHEN p.payment_status = 'Paid' OR LOWER(b.payment_status) = 'paid' THEN 'Paid'
                WHEN p.payment_status = 'Rejected' OR LOWER(b.payment_status) = 'rejected' THEN 'Rejected'
                WHEN LOWER(b.status) = 'cancelled' AND p.payment_status = 'Pending' THEN 'Submitted'
                ELSE 'Pending'
            END AS display_payment_status,
            CASE
                WHEN LOWER(b.status) = 'cancelled' THEN NULL
                WHEN b.did_not_arrive_at IS NOT NULL THEN 'Did not arrive'
                WHEN b.arrived_at IS NOT NULL THEN 'Arrived'
                WHEN TIMESTAMP(cs.court_date, cs.court_time) <= NOW() THEN 'Awaiting attendance'
                ELSE 'Upcoming'
            END AS attendance_status,
            (TIMESTAMP(cs.court_date, cs.court_time) <= NOW()) AS is_past,
            (b.status = 'pending_payment' AND p.payments_id IS NULL
                AND cs.schedule_status = 'Held' AND b.hold_expires_at > NOW()) AS can_checkout,
            (b.status = 'pending_payment'
                AND cs.schedule_status = 'Held'
                AND b.hold_expires_at > NOW()
                AND cs.hold_expires_at > NOW()) AS active_hold,
            (p.payment_status = 'Rejected'
                AND b.status IN ('pending', 'pending_payment')
                AND cs.schedule_status = 'Held'
                AND b.hold_expires_at > NOW()
                AND cs.hold_expires_at > NOW()
                AND TIMESTAMP(cs.court_date, cs.court_time) > NOW()) AS can_resubmit_payment,
            CASE
                WHEN p.payment_status = 'Rejected'
                    AND LOWER(b.status) IN ('cancelled', 'completed', 'expired')
                    THEN 'This booking is no longer active and cannot accept another payment.'
                WHEN p.payment_status = 'Rejected'
                    AND (b.status NOT IN ('pending', 'pending_payment')
                        OR cs.schedule_status <> 'Held'
                        OR b.hold_expires_at IS NULL
                        OR b.hold_expires_at <= NOW()
                        OR cs.hold_expires_at IS NULL
                        OR cs.hold_expires_at <= NOW()
                        OR TIMESTAMP(cs.court_date, cs.court_time) <= NOW())
                    THEN 'This reservation is no longer available for payment resubmission. Please contact the facility.'
                ELSE NULL
            END AS resubmission_message,
            GREATEST(TIMESTAMPDIFF(SECOND, NOW(), b.hold_expires_at), 0) AS hold_seconds,
            UNIX_TIMESTAMP(b.hold_expires_at) AS hold_expires_epoch,
            UNIX_TIMESTAMP(NOW()) AS server_now_epoch
     FROM bookings b
     INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
     INNER JOIN court_details cd ON cd.court_id = b.court_id
     INNER JOIN facilities f ON f.facility_id = cd.facility_id
     LEFT JOIN (
         SELECT booking_id, MAX(payments_id) AS payments_id
         FROM payments
         GROUP BY booking_id
     ) latest_payment ON latest_payment.booking_id = b.booking_id
     LEFT JOIN payments p ON p.payments_id = latest_payment.payments_id
    WHERE b.user_id = ? AND b.status <> 'Expired'
        AND NOT (LOWER(b.status) = 'cancelled' AND LOWER(b.payment_status) = 'unpaid'
            AND NOT EXISTS (SELECT 1 FROM payments unpaid_cancel_payment WHERE unpaid_cancel_payment.booking_id = b.booking_id))
     ORDER BY cs.court_date DESC, cs.court_time DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = [
        "booking_id" => (int) $row["booking_id"],
        "booking_status" => $row["booking_status"],
        "booking_payment_status" => $row["booking_payment_status"],
        "total_amount" => (float) $row["total_amount"],
        "court_date" => $row["court_date"],
        "court_time" => $row["court_time"],
        "court_no" => $row["court_no"],
        "facility_id" => (int) $row["facility_id"],
        "slot_duration" => (int) $row["slot_duration"],
        "facility_name" => $row["facility_name"],
        "municipality" => $row["municipality"],
        "payment_id" => $row["payments_id"] ? (int) $row["payments_id"] : null,
        "payment_status" => $row["display_payment_status"],
        "rejection_reason" => $row["rejection_reason"],
        "has_receipt" => !empty($row["receipt_image"]),
        "is_past" => (bool) $row["is_past"],
        "attendance_status" => $row["attendance_status"],
        "can_checkout" => (bool) $row["can_checkout"],
        "can_resubmit_payment" => (bool) $row["can_resubmit_payment"],
        "resubmission_message" => $row["resubmission_message"],
        "can_cancel" => !in_array(strtolower((string) $row["booking_status"]), ["cancelled", "completed", "expired"], true)
            && ($row["display_payment_status"] === "Rejected"
                || (bool) $row["active_hold"]),
        "can_delete_on_cancel" => $row["booking_status"] === "pending_payment"
            && strtolower((string) $row["booking_payment_status"]) === "unpaid"
            && empty($row["payment_id"]),
        "hold_seconds" => (int) $row["hold_seconds"],
        "hold_expires_epoch" => (int) $row["hold_expires_epoch"],
        "server_now_epoch" => (int) $row["server_now_epoch"],
    ];
}
$stmt->close();
$conn->close();

echo json_encode(["success" => true, "bookings" => $bookings]);