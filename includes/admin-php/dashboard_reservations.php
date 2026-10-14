<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";
require_once __DIR__ . "/upload_helper.php";

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
    $slotsStmt = $conn->prepare(
        "SELECT cs.schedule_id, cs.court_id, cs.court_date, cs.court_time,
                cd.court_no, cd.slot_duration, cd.court_rate
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         WHERE cd.facility_id = ? AND cd.court_status = 'Available'
             AND (cs.schedule_status = 'Available'
                 OR (cs.schedule_status = 'Held'
                     AND (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW())))
             AND (cs.court_date > CURDATE() OR (cs.court_date = CURDATE() AND cs.court_time > CURTIME()))
         ORDER BY cs.court_date ASC, cs.court_time ASC, cd.court_no ASC
         LIMIT 300"
    );
    $slotsStmt->bind_param("i", $facilityId);
    $slotsStmt->execute();
    $slotsResult = $slotsStmt->get_result();
    $slots = [];
    while ($slot = $slotsResult->fetch_assoc()) {
        $slots[] = $slot;
    }
    $slotsStmt->close();

    $todayStmt = $conn->prepare(
        "SELECT b.booking_id, b.booking_type, b.customer_phone, b.total_amount,
                b.arrived_at, b.did_not_arrive_at, b.payment_status AS booking_payment_status,
                COALESCE(b.customer_name, CONCAT(u.first_name, ' ', u.last_name)) AS customer_name,
                COALESCE(b.customer_phone, u.phone_number) AS display_phone,
                cs.court_date, cs.court_time, cd.court_no,
                COALESCE(p.payment_status, IF(b.payment_status = 'paid', 'Paid', 'Pending')) AS payment_status
         FROM bookings b
         LEFT JOIN users u ON u.user_id = b.user_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         LEFT JOIN (
             SELECT booking_id, MAX(payments_id) AS payments_id
             FROM payments
             GROUP BY booking_id
         ) latest_payment ON latest_payment.booking_id = b.booking_id
         LEFT JOIN payments p ON p.payments_id = latest_payment.payments_id
         WHERE cd.facility_id = ? AND cs.court_date = CURDATE()
             AND cs.schedule_status = 'Booked' AND b.status IN ('confirmed', 'pending')
         ORDER BY cd.court_no, cs.court_time"
    );
    $todayStmt->bind_param("i", $facilityId);
    $todayStmt->execute();
    $todayResult = $todayStmt->get_result();
    $todayBookings = [];
    while ($booking = $todayResult->fetch_assoc()) {
        $todayBookings[] = $booking;
    }
    $todayStmt->close();

    $availableCourtStmt = $conn->prepare(
        "SELECT COUNT(DISTINCT cd.court_id) AS court_count
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         WHERE cd.facility_id = ? AND cd.court_status = 'Available'
             AND (cs.schedule_status = 'Available'
                 OR (cs.schedule_status = 'Held'
                     AND (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW())))
             AND (cs.court_date > CURDATE() OR (cs.court_date = CURDATE() AND cs.court_time > CURTIME()))"
    );
    $availableCourtStmt->bind_param("i", $facilityId);
    $availableCourtStmt->execute();
    $availableCourtCount = (int) ($availableCourtStmt->get_result()->fetch_assoc()["court_count"] ?? 0);
    $availableCourtStmt->close();

    echo json_encode([
        "success" => true,
        "slots" => $slots,
        "today_bookings" => $todayBookings,
        "available_court_count" => $availableCourtCount
    ]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$data = $_POST;
$scheduleId = filter_var($data["schedule_id"] ?? null, FILTER_VALIDATE_INT);
$customerName = trim((string) ($data["customer_name"] ?? ""));
$customerPhone = trim((string) ($data["customer_phone"] ?? ""));
$referenceNumber = trim((string) ($data["reference_number"] ?? ""));
$paymentMethod = $data["payment_method"] ?? "";
if (!$scheduleId || $scheduleId < 1 || $customerName === "" || $customerPhone === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter the customer's name and phone number."]);
    exit();
}
if (strlen($customerName) > 200 || !preg_match('/^09[0-9]{9}$/', $customerPhone)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a valid customer name and a phone number in 09XXXXXXXXX format."]);
    exit();
}
if (!in_array($paymentMethod, ["Gcash", "Cash"], true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose GCash or Cash as the payment method."]);
    exit();
}
if ($paymentMethod === "Gcash" && ($referenceNumber === "" || strlen($referenceNumber) > 50)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a valid GCash reference number (up to 50 characters)."]);
    exit();
}
$qrImage = null;
if ($paymentMethod === "Cash") {
    $referenceNumber = "N/A";
    $qrImage = "N/A";
}

$transactionStarted = false;
try {
    if ($paymentMethod === "Gcash") {
        $qrImage = handleImageUpload("gcash_qr", __DIR__ . "/../../uploads/payment-receipts");
        if ($qrImage === null) {
            throw new RuntimeException("Upload the GCash QR code or payment image.");
        }
    }

    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start the booking transaction.");
    }
    $transactionStarted = true;
    $slotStmt = $conn->prepare(
        "SELECT cs.court_id, cs.court_date, cs.court_time, cs.schedule_status,
                cs.hold_expires_at, cd.court_rate
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         WHERE cs.schedule_id = ? AND cd.facility_id = ?
             AND (cs.schedule_status = 'Available'
                 OR (cs.schedule_status = 'Held'
                     AND (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW())))
             AND (cs.court_date > CURDATE() OR (cs.court_date = CURDATE() AND cs.court_time > CURTIME()))
             AND cd.court_status = 'Available'
         FOR UPDATE"
    );
    $slotStmt->bind_param("ii", $scheduleId, $facilityId);
    $slotStmt->execute();
    $slot = $slotStmt->get_result()->fetch_assoc();
    $slotStmt->close();
    if (!$slot) {
        throw new RuntimeException("That time is no longer available. Refresh and choose another slot.");
    }

    if ($slot["schedule_status"] === "Held") {
        $releaseExpiredStmt = $conn->prepare(
            "UPDATE court_schedule SET schedule_status = 'Available', hold_expires_at = NULL
             WHERE schedule_id = ? AND schedule_status = 'Held'
                 AND (hold_expires_at IS NULL OR hold_expires_at <= NOW())"
        );
        $releaseExpiredStmt->bind_param("i", $scheduleId);
        $releaseExpiredStmt->execute();
        $releaseExpiredStmt->close();
    }

    $updateStmt = $conn->prepare(
        "UPDATE court_schedule SET schedule_status = 'Booked', hold_expires_at = NULL
         WHERE schedule_id = ? AND schedule_status = 'Available'"
    );
    $updateStmt->bind_param("i", $scheduleId);
    $updateStmt->execute();
    if ($updateStmt->affected_rows !== 1) {
        $updateStmt->close();
        throw new RuntimeException("That time is no longer available. Refresh and choose another slot.");
    }
    $updateStmt->close();

    $courtId = (int) $slot["court_id"];
    $totalAmount = (int) $slot["court_rate"];
    $bookingStmt = $conn->prepare(
        "INSERT INTO bookings
            (user_id, booking_type, customer_name, customer_phone, court_id, schedule_id, total_amount, status, payment_status)
         VALUES (NULL, 'walk_in', ?, ?, ?, ?, ?, 'confirmed', 'paid')"
    );
    $bookingStmt->bind_param("ssiid", $customerName, $customerPhone, $courtId, $scheduleId, $totalAmount);
    $bookingStmt->execute();
    $bookingId = $bookingStmt->insert_id;
    $bookingStmt->close();

    $paymentStmt = $conn->prepare(
        "INSERT INTO payments (booking_id, payment_method, payment_status, reference_number, receipt_image, paid_at)
         VALUES (?, ?, 'Paid', ?, ?, NOW())"
    );
    $paymentStmt->bind_param("isss", $bookingId, $paymentMethod, $referenceNumber, $qrImage);
    $paymentStmt->execute();
    $paymentStmt->close();

    if (!$conn->commit()) {
        throw new RuntimeException("Unable to save the booking.");
    }
    $transactionStarted = false;
    echo json_encode([
        "success" => true,
        "message" => "Booking confirmed and payment recorded as paid.",
        "booking_id" => $bookingId,
        "court_date" => $slot["court_date"],
        "court_time" => $slot["court_time"]
    ]);
} catch (RuntimeException $error) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    if ($qrImage !== null) {
        $imagePath = __DIR__ . "/../../uploads/payment-receipts/" . basename($qrImage);
        if (is_file($imagePath)) {
            unlink($imagePath);
        }
    }
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
} catch (Throwable $error) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    if ($qrImage !== null) {
        $imagePath = __DIR__ . "/../../uploads/payment-receipts/" . basename($qrImage);
        if (is_file($imagePath)) {
            unlink($imagePath);
        }
    }
    http_response_code(500);
    error_log("Dashboard booking error: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to create this booking."]);
}