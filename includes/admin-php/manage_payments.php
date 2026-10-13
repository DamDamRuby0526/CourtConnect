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

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $stmt = $conn->prepare(
        "SELECT p.payments_id, p.payment_method, p.payment_status, p.reference_number,
            p.rejection_reason, p.receipt_image,
            p.paid_at, p.created_at, b.booking_id, b.booking_type, b.total_amount, b.court_id, b.schedule_id,
                CASE WHEN b.booking_type = 'walk_in' OR b.user_id IS NULL
                    THEN COALESCE(NULLIF(b.customer_name, ''), 'Walk-in customer')
                    ELSE COALESCE(NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ''), NULLIF(b.customer_name, ''), 'Customer')
                END AS customer_name,
                CASE WHEN b.booking_type = 'walk_in' OR b.user_id IS NULL
                    THEN b.customer_phone ELSE COALESCE(u.phone_number, b.customer_phone)
                END AS customer_phone,
            cs.court_date, cs.court_time, cd.court_no, cd.slot_duration
         FROM payments p
         INNER JOIN bookings b ON b.booking_id = p.booking_id
         LEFT JOIN users u ON u.user_id = b.user_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         WHERE cd.facility_id = ?
         ORDER BY (p.payment_status = 'Pending') DESC, cs.court_date ASC, cs.court_time ASC"
    );
    $stmt->bind_param("i", $facilityId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    $pendingCount = 0;
    $rejectedCount = 0;
    $paidTotal = 0.0;
    while ($payment = $result->fetch_assoc()) {
        $payment["total_amount"] = (float) $payment["total_amount"];
        $payment["has_receipt"] = !empty($payment["receipt_image"]);
        unset($payment["receipt_image"]);
        if ($payment["payment_status"] === "Pending") {
            $pendingCount++;
        } elseif ($payment["payment_status"] === "Rejected") {
            $rejectedCount++;
        } elseif ($payment["payment_status"] === "Paid") {
            $paidTotal += $payment["total_amount"];
        }
        $payments[] = $payment;
    }
    $stmt->close();
    echo json_encode([
        "success" => true,
        "payments" => $payments,
        "pending_count" => $pendingCount,
        "rejected_count" => $rejectedCount,
        "paid_total" => $paidTotal
    ]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$action = $data["action"] ?? "mark_paid";
$paymentId = filter_var($data["payment_id"] ?? null, FILTER_VALIDATE_INT);
if (!$paymentId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid payment."]);
    exit();
}

if ($action === "reject") {
    $rejectionReason = is_string($data["rejection_reason"] ?? null)
        ? trim($data["rejection_reason"])
        : "";
    if ($rejectionReason === "" || mb_strlen($rejectionReason, "UTF-8") > 500) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => $rejectionReason === ""
                ? "Enter a reason for rejecting this payment."
                : "The rejection reason must be 500 characters or fewer."
        ]);
        exit();
    }

    try {
        $conn->begin_transaction();
        $currentStmt = $conn->prepare(
            "SELECT p.payment_status, b.booking_id, b.status AS booking_status,
                    b.schedule_id, cs.schedule_status,
                    TIMESTAMPDIFF(SECOND, NOW(), cs.hold_expires_at) AS hold_seconds
             FROM payments p
             INNER JOIN bookings b ON b.booking_id = p.booking_id
             INNER JOIN court_details cd ON cd.court_id = b.court_id
             INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
             WHERE p.payments_id = ? AND cd.facility_id = ?
             FOR UPDATE"
        );
        $currentStmt->bind_param("ii", $paymentId, $facilityId);
        $currentStmt->execute();
        $current = $currentStmt->get_result()->fetch_assoc();
        $currentStmt->close();

        if (!$current) {
            http_response_code(404);
            throw new RuntimeException("Payment was not found for this facility.");
        }
        if ($current["payment_status"] !== "Pending") {
            http_response_code(409);
            throw new RuntimeException("Only pending payments can be rejected.");
        }
        if (in_array($current["booking_status"], ["cancelled", "completed"], true)) {
            http_response_code(409);
            throw new RuntimeException("This booking is no longer active.");
        }

        $paymentStmt = $conn->prepare(
            "UPDATE payments
             SET payment_status = 'Rejected', rejection_reason = ?, paid_at = NULL
             WHERE payments_id = ? AND payment_status = 'Pending'"
        );
        $paymentStmt->bind_param("si", $rejectionReason, $paymentId);
        $paymentStmt->execute();
        if ($paymentStmt->affected_rows !== 1) {
            $paymentStmt->close();
            http_response_code(409);
            throw new RuntimeException("This payment is no longer pending.");
        }
        $paymentStmt->close();

        $bookingStatus = $current["booking_status"] === "pending"
            ? "pending_payment"
            : $current["booking_status"];
        $bookingStmt = $conn->prepare(
            "UPDATE bookings
             SET status = ?, payment_status = 'rejected',
                 hold_expires_at = CASE
                     WHEN ? = 'pending_payment' AND ? = 'pending'
                         THEN DATE_ADD(NOW(), INTERVAL 24 HOUR)
                     ELSE hold_expires_at
                 END
             WHERE booking_id = ?"
        );
        $bookingStmt->bind_param(
            "sssi",
            $bookingStatus,
            $bookingStatus,
            $current["booking_status"],
            $current["booking_id"]
        );
        $bookingStmt->execute();
        $bookingStmt->close();

        if ($current["booking_status"] === "pending"
            && $current["schedule_status"] === "Held"
            && (int) $current["hold_seconds"] > 0) {
            $scheduleStmt = $conn->prepare(
                "UPDATE court_schedule
                 SET hold_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                 WHERE schedule_id = ? AND schedule_status = 'Held' AND hold_expires_at > NOW()"
            );
            $scheduleStmt->bind_param("i", $current["schedule_id"]);
            $scheduleStmt->execute();
            $scheduleStmt->close();
        }

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Payment rejected."]);
    } catch (RuntimeException $error) {
        $conn->rollback();
        if (http_response_code() < 400) {
            http_response_code(409);
        }
        echo json_encode(["success" => false, "message" => $error->getMessage()]);
    } catch (Throwable $error) {
        $conn->rollback();
        http_response_code(500);
        error_log("Payment rejection failed for payment " . $paymentId . ": " . $error->getMessage());
        echo json_encode(["success" => false, "message" => "Unable to reject this payment."]);
    }
    exit();
}

if ($action === "update") {
    $paymentMethod = $data["payment_method"] ?? "";
    $referenceNumber = trim($data["reference_number"] ?? "");
    $paymentStatus = $data["payment_status"] ?? "";
    if (!in_array($paymentMethod, ["Gcash", "Cash"], true) ||
        !in_array($paymentStatus, ["Pending", "Paid"], true) || strlen($referenceNumber) > 50) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Choose a payment method and a valid status."]);
        exit();
    }
    if ($paymentMethod === "Cash") {
        $referenceNumber = null;
    }

    try {
        $conn->begin_transaction();
        $currentStmt = $conn->prepare(
                    "SELECT b.booking_id, b.status AS booking_status, b.schedule_id,
                        cs.schedule_status, cs.hold_expires_at,
                        TIMESTAMPDIFF(SECOND, NOW(), cs.hold_expires_at) AS hold_seconds,
                        p.receipt_image, p.payment_status
             FROM payments p
             INNER JOIN bookings b ON b.booking_id = p.booking_id
             INNER JOIN court_details cd ON cd.court_id = b.court_id
                 INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
             WHERE p.payments_id = ? AND cd.facility_id = ?
             FOR UPDATE"
        );
        $currentStmt->bind_param("ii", $paymentId, $facilityId);
        $currentStmt->execute();
        $current = $currentStmt->get_result()->fetch_assoc();
        $currentStmt->close();
        if (!$current) {
            throw new RuntimeException("Payment was not found for this facility.");
        }
        if ($current["payment_status"] === "Rejected") {
            throw new RuntimeException("Rejected payment attempts cannot be edited.");
        }
        if (in_array($current["booking_status"], ["cancelled", "completed"], true)) {
            throw new RuntimeException("A cancelled or completed booking cannot be approved.");
        }
        $awaitingPlayerBooking = $current["booking_status"] === "pending_payment"
            || ($current["booking_status"] === "pending" && $current["schedule_status"] === "Held");
        if ($paymentStatus === "Paid" && $awaitingPlayerBooking
            && ($current["schedule_status"] !== "Held" || (int) $current["hold_seconds"] <= 0)) {
            throw new RuntimeException("This booking hold has expired and cannot be approved.");
        }
        if ($paymentStatus === "Paid" && $paymentMethod === "Gcash" && empty($current["receipt_image"])) {
            throw new RuntimeException("Upload a GCash receipt before marking this payment paid.");
        }

        $paidAtSql = $paymentStatus === "Paid" ? "NOW()" : "NULL";
        $paymentStmt = $conn->prepare(
            "UPDATE payments
             SET payment_method = ?, reference_number = ?, payment_status = ?,
                 rejection_reason = NULL, paid_at = " . $paidAtSql . "
             WHERE payments_id = ?"
        );
        $paymentStmt->bind_param("sssi", $paymentMethod, $referenceNumber, $paymentStatus, $paymentId);
        $paymentStmt->execute();
        $paymentStmt->close();
        $bookingPaymentStatus = $paymentStatus === "Paid" ? "paid" : "unpaid";
        $nextBookingStatus = $current["booking_status"];
        if ($paymentStatus === "Paid" && $awaitingPlayerBooking) {
            $nextBookingStatus = "confirmed";
        }
        $bookingStmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = ? WHERE booking_id = ?");
        $bookingStmt->bind_param("ssi", $nextBookingStatus, $bookingPaymentStatus, $current["booking_id"]);
        $bookingStmt->execute();
        $bookingStmt->close();
        if ($paymentStatus === "Paid" && $awaitingPlayerBooking) {
            $scheduleStmt = $conn->prepare(
                "UPDATE court_schedule SET schedule_status = 'Booked', hold_expires_at = NULL
                 WHERE schedule_id = ? AND schedule_status = 'Held' AND hold_expires_at > NOW()"
            );
            $scheduleStmt->bind_param("i", $current["schedule_id"]);
            $scheduleStmt->execute();
            if ($scheduleStmt->affected_rows !== 1) {
                $scheduleStmt->close();
                throw new RuntimeException("This booking hold expired and cannot be approved.");
            }
            $scheduleStmt->close();
        }
        $conn->commit();
        echo json_encode([
            "success" => true,
            "message" => $paymentStatus === "Paid" ? "Payment marked as paid." : "Payment details updated."
        ]);
    } catch (RuntimeException $error) {
        $conn->rollback();
        echo json_encode(["success" => false, "message" => $error->getMessage()]);
    } catch (Throwable $error) {
        $conn->rollback();
        http_response_code(500);
        error_log("Payment record update error: " . $error->getMessage());
        echo json_encode(["success" => false, "message" => "Unable to update the booking and payment."]);
    }
    exit();
}

if ($action !== "mark_paid") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid payment action."]);
    exit();
}

$conn->begin_transaction();
try {
    $currentStmt = $conn->prepare(
        "SELECT b.booking_id, b.status AS booking_status, b.schedule_id,
                cs.schedule_status, p.receipt_image,
                TIMESTAMPDIFF(SECOND, NOW(), cs.hold_expires_at) AS hold_seconds,
                p.payment_method
         FROM payments p
         INNER JOIN bookings b ON b.booking_id = p.booking_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE p.payments_id = ? AND cd.facility_id = ? AND p.payment_status = 'Pending'
         FOR UPDATE"
    );
    $currentStmt->bind_param("ii", $paymentId, $facilityId);
    $currentStmt->execute();
    $current = $currentStmt->get_result()->fetch_assoc();
    $currentStmt->close();
    if (!$current || in_array($current["booking_status"], ["cancelled", "completed"], true)) {
        throw new RuntimeException("Pending payment was not found or its booking is no longer active.");
    }
    if ($current["payment_method"] === "Gcash" && empty($current["receipt_image"])) {
        throw new RuntimeException("Upload a GCash receipt before marking this payment paid.");
    }

    $awaitingPlayerBooking = $current["booking_status"] === "pending_payment"
        || ($current["booking_status"] === "pending" && $current["schedule_status"] === "Held");
    if ($awaitingPlayerBooking
        && ($current["schedule_status"] !== "Held" || (int) $current["hold_seconds"] <= 0)) {
        throw new RuntimeException("This booking hold expired and cannot be approved.");
    }

    $paymentStmt = $conn->prepare(
        "UPDATE payments SET payment_status = 'Paid', paid_at = NOW()
         WHERE payments_id = ? AND payment_status = 'Pending'"
    );
    $paymentStmt->bind_param("i", $paymentId);
    $paymentStmt->execute();
    if ($paymentStmt->affected_rows !== 1) {
        $paymentStmt->close();
        throw new RuntimeException("This payment is no longer pending.");
    }
    $paymentStmt->close();

    $bookingStatus = $awaitingPlayerBooking ? "confirmed" : $current["booking_status"];
    $bookingStmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = 'paid' WHERE booking_id = ?");
    $bookingStmt->bind_param("si", $bookingStatus, $current["booking_id"]);
    $bookingStmt->execute();
    $bookingStmt->close();

    if ($awaitingPlayerBooking) {
        $scheduleStmt = $conn->prepare(
            "UPDATE court_schedule SET schedule_status = 'Booked', hold_expires_at = NULL
             WHERE schedule_id = ? AND schedule_status = 'Held' AND hold_expires_at > NOW()"
        );
        $scheduleStmt->bind_param("i", $current["schedule_id"]);
        $scheduleStmt->execute();
        if ($scheduleStmt->affected_rows !== 1) {
            $scheduleStmt->close();
            throw new RuntimeException("This booking hold expired and cannot be approved.");
        }
        $scheduleStmt->close();
    }

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Payment marked as paid."]);
} catch (RuntimeException $error) {
    $conn->rollback();
    http_response_code(409);
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    error_log("Payment approval failed: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to mark this payment paid."]);
}
$conn->close();