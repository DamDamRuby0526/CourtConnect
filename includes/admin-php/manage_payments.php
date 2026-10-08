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
        "SELECT p.payments_id, p.payment_method, p.payment_status, p.reference_number, p.receipt_image,
            p.paid_at, p.created_at, b.booking_id, b.total_amount, b.court_id, b.schedule_id,
                COALESCE(b.customer_name, CONCAT(u.first_name, ' ', u.last_name), 'Walk-in customer') AS customer_name,
                COALESCE(b.customer_phone, u.phone_number) AS customer_phone,
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
    $schedulesStmt = $conn->prepare(
        "SELECT cs.schedule_id, cs.court_id, cs.court_date, cs.court_time,
                cd.court_no, cd.slot_duration
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         WHERE cd.facility_id = ? AND cd.court_status = 'Available'
             AND cs.schedule_status = 'Available'
             AND (cs.court_date > CURDATE() OR (cs.court_date = CURDATE() AND cs.court_time > CURTIME()))
         ORDER BY cs.court_date, cs.court_time, cd.court_no
         LIMIT 300"
    );
    $schedulesStmt->bind_param("i", $facilityId);
    $schedulesStmt->execute();
    $schedulesResult = $schedulesStmt->get_result();
    $availableSchedules = [];
    while ($schedule = $schedulesResult->fetch_assoc()) {
        $availableSchedules[] = $schedule;
    }
    $schedulesStmt->close();
    echo json_encode([
        "success" => true,
        "payments" => $payments,
        "pending_count" => $pendingCount,
        "rejected_count" => $rejectedCount,
        "paid_total" => $paidTotal,
        "available_schedules" => $availableSchedules
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

if ($action === "update") {
    $customerName = trim($data["customer_name"] ?? "");
    $customerPhone = trim($data["customer_phone"] ?? "");
    $scheduleId = filter_var($data["schedule_id"] ?? null, FILTER_VALIDATE_INT);
    $paymentMethod = $data["payment_method"] ?? "";
    $referenceNumber = trim($data["reference_number"] ?? "");
    $paymentStatus = $data["payment_status"] ?? "";
    if ($customerName === "" || strlen($customerName) > 200 ||
        !preg_match('/^\+?[0-9().\s-]{7,30}$/', $customerPhone) ||
        !$scheduleId || !in_array($paymentMethod, ["Gcash", "Cash"], true) ||
        !in_array($paymentStatus, ["Pending", "Rejected", "Paid"], true) || strlen($referenceNumber) > 50) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Check the customer, schedule, payment method, reference, and status fields."]);
        exit();
    }
    if ($paymentMethod === "Cash") {
        $referenceNumber = null;
    }

    try {
        $conn->begin_transaction();
        $currentStmt = $conn->prepare(
            "SELECT b.booking_id, b.schedule_id, b.court_id, p.receipt_image, p.payment_status
             FROM payments p
             INNER JOIN bookings b ON b.booking_id = p.booking_id
             INNER JOIN court_details cd ON cd.court_id = b.court_id
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
        if ($paymentStatus === "Paid" && $paymentMethod === "Gcash" && empty($current["receipt_image"])) {
            throw new RuntimeException("Upload a GCash receipt before marking this payment paid.");
        }

        $currentScheduleId = (int) $current["schedule_id"];
        $targetScheduleStmt = $conn->prepare(
            "SELECT cs.schedule_id, cs.court_id, cs.schedule_status, cd.court_no
             FROM court_schedule cs
             INNER JOIN court_details cd ON cd.court_id = cs.court_id
             WHERE cs.schedule_id = ? AND cd.facility_id = ? AND cd.court_status = 'Available'
             FOR UPDATE"
        );
        $targetScheduleStmt->bind_param("ii", $scheduleId, $facilityId);
        $targetScheduleStmt->execute();
        $targetSchedule = $targetScheduleStmt->get_result()->fetch_assoc();
        $targetScheduleStmt->close();
        if (!$targetSchedule) {
            throw new RuntimeException("Choose a court schedule in this facility.");
        }
        if ($current["payment_status"] === "Rejected" && $paymentStatus !== "Rejected" &&
            $scheduleId === $currentScheduleId && $targetSchedule["schedule_status"] !== "Available") {
            throw new RuntimeException("The original slot has been booked. Choose another available time.");
        }

        if ($paymentStatus === "Rejected") {
            if ($scheduleId !== $currentScheduleId) {
                throw new RuntimeException("Keep the current schedule when rejecting a booking.");
            }
            if ($current["payment_status"] !== "Rejected") {
                $releaseStmt = $conn->prepare(
                    "UPDATE court_schedule SET schedule_status = 'Available'
                     WHERE schedule_id = ? AND schedule_status = 'Booked'"
                );
                $releaseStmt->bind_param("i", $currentScheduleId);
                $releaseStmt->execute();
                $releaseStmt->close();
            }
        } elseif ($scheduleId !== $currentScheduleId) {
            if ($targetSchedule["schedule_status"] !== "Available") {
                throw new RuntimeException("That schedule is no longer available.");
            }
            $releaseStmt = $conn->prepare(
                "UPDATE court_schedule SET schedule_status = 'Available'
                 WHERE schedule_id = ? AND schedule_status = 'Booked'"
            );
            $releaseStmt->bind_param("i", $currentScheduleId);
            $releaseStmt->execute();
            $releaseStmt->close();
            $claimStmt = $conn->prepare(
                "UPDATE court_schedule SET schedule_status = 'Booked'
                 WHERE schedule_id = ? AND schedule_status = 'Available'"
            );
            $claimStmt->bind_param("i", $scheduleId);
            $claimStmt->execute();
            if ($claimStmt->affected_rows !== 1) {
                $claimStmt->close();
                throw new RuntimeException("That schedule is no longer available.");
            }
            $claimStmt->close();
        } elseif ($paymentStatus !== "Rejected" && $targetSchedule["schedule_status"] === "Available") {
            $claimStmt = $conn->prepare(
                "UPDATE court_schedule SET schedule_status = 'Booked'
                 WHERE schedule_id = ? AND schedule_status = 'Available'"
            );
            $claimStmt->bind_param("i", $scheduleId);
            $claimStmt->execute();
            if ($claimStmt->affected_rows !== 1) {
                $claimStmt->close();
                throw new RuntimeException("That schedule is no longer available.");
            }
            $claimStmt->close();
        }

        $bookingId = (int) $current["booking_id"];
        $courtId = (int) $targetSchedule["court_id"];
        $bookingStmt = $conn->prepare(
            "UPDATE bookings
               SET customer_name = ?, customer_phone = ?, court_id = ?, schedule_id = ?
             WHERE booking_id = ?"
        );
           $bookingStmt->bind_param("ssiii", $customerName, $customerPhone, $courtId, $scheduleId, $bookingId);
        $bookingStmt->execute();
        $bookingStmt->close();

        $paidAtSql = $paymentStatus === "Paid" ? "NOW()" : "NULL";
        $paymentStmt = $conn->prepare(
            "UPDATE payments
             SET payment_method = ?, reference_number = ?, payment_status = ?, paid_at = " . $paidAtSql . "
             WHERE payments_id = ?"
        );
        $paymentStmt->bind_param("sssi", $paymentMethod, $referenceNumber, $paymentStatus, $paymentId);
        $paymentStmt->execute();
        $paymentStmt->close();
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Booking and payment details updated."]);
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

$stmt = $conn->prepare(
    "UPDATE payments p
     INNER JOIN bookings b ON b.booking_id = p.booking_id
     INNER JOIN court_details cd ON cd.court_id = b.court_id
     SET p.payment_status = 'Paid', p.paid_at = NOW()
     WHERE p.payments_id = ? AND cd.facility_id = ?
         AND p.payment_status = 'Pending'
         AND (p.payment_method = 'Cash' OR p.receipt_image IS NOT NULL)"
);
$stmt->bind_param("ii", $paymentId, $facilityId);
$stmt->execute();
$updated = $stmt->affected_rows === 1;
$stmt->close();

if (!$updated) {
    echo json_encode(["success" => false, "message" => "Pending payment was not found or requires a GCash receipt."]);
    exit();
}

echo json_encode(["success" => true, "message" => "Payment marked as paid."]);
$conn->close();