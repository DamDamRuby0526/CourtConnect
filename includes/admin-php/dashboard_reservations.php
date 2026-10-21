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
                cs.court_date, COALESCE(b.start_time, cs.court_time) AS court_time,
                COALESCE(b.end_time, ADDTIME(cs.court_time, SEC_TO_TIME(cd.slot_duration * 60))) AS end_time,
                COALESCE(b.total_duration, cd.slot_duration) AS total_duration,
                cd.court_no,
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
$submittedScheduleIds = $data["schedule_ids"] ?? [];
if (!is_array($submittedScheduleIds)) {
    $submittedScheduleIds = isset($data["schedule_id"]) ? [$data["schedule_id"]] : [];
}
$scheduleIds = [];
foreach ($submittedScheduleIds as $submittedScheduleId) {
    $scheduleId = filter_var($submittedScheduleId, FILTER_VALIDATE_INT);
    if (!$scheduleId || $scheduleId < 1) {
        $scheduleIds = [];
        break;
    }
    $scheduleIds[] = (int) $scheduleId;
}
$scheduleIds = array_values(array_unique($scheduleIds));
sort($scheduleIds);
$customerName = trim((string) ($data["customer_name"] ?? ""));
$customerPhone = trim((string) ($data["customer_phone"] ?? ""));
$referenceNumber = trim((string) ($data["reference_number"] ?? ""));
$paymentMethod = $data["payment_method"] ?? "";
if (!$scheduleIds || count($scheduleIds) > 8 || $customerName === "" || $customerPhone === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose one to eight consecutive slots and enter the customer's name and phone number."]);
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
    $schedulePlaceholders = implode(",", array_fill(0, count($scheduleIds), "?"));
    $scheduleTypes = str_repeat("i", count($scheduleIds));
    $slotsStmt = $conn->prepare(
        "SELECT cs.schedule_id, cs.court_id, cs.court_date, cs.court_time,
                cs.schedule_status, cs.hold_expires_at,
                (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW()) AS hold_expired,
                cd.court_no, cd.court_rate, cd.slot_duration, cd.court_status,
                (TIMESTAMP(cs.court_date, cs.court_time) > NOW()) AS is_future_slot
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         WHERE cs.schedule_id IN ($schedulePlaceholders) AND cd.facility_id = ?
         ORDER BY cs.schedule_id ASC
         FOR UPDATE"
    );
    $slotParameters = array_merge($scheduleIds, [$facilityId]);
    $slotsStmt->bind_param($scheduleTypes . "i", ...$slotParameters);
    $slotsStmt->execute();
    $slotsResult = $slotsStmt->get_result();
    $slots = [];
    while ($row = $slotsResult->fetch_assoc()) {
        $slots[] = $row;
    }
    $slotsStmt->close();
    usort($slots, static function ($left, $right) {
        return strcmp($left["court_date"] . " " . $left["court_time"], $right["court_date"] . " " . $right["court_time"]);
    });

    if (count($slots) !== count($scheduleIds)) {
        throw new RuntimeException("One or more selected times do not belong to your facility.");
    }

    $firstSlot = $slots[0];
    $courtId = (int) $firstSlot["court_id"];
    $slotDuration = (int) $firstSlot["slot_duration"];
    $totalAmount = 0.0;
    foreach ($slots as $index => $slot) {
        $isAvailable = $slot["schedule_status"] === "Available"
            || ($slot["schedule_status"] === "Held" && (int) $slot["hold_expired"] === 1);
        if (!$isAvailable || $slot["court_status"] !== "Available" || !(int) $slot["is_future_slot"]) {
            throw new RuntimeException("One or more selected times are no longer available. Refresh and choose another time.");
        }
        if ((int) $slot["court_id"] !== $courtId
            || $slot["court_date"] !== $firstSlot["court_date"]
            || (int) $slot["slot_duration"] !== $slotDuration) {
            throw new RuntimeException("Choose consecutive times for one court and date.");
        }
        if ($index > 0) {
            $previousStart = strtotime($slots[$index - 1]["court_date"] . " " . $slots[$index - 1]["court_time"]);
            $currentStart = strtotime($slot["court_date"] . " " . $slot["court_time"]);
            if ($currentStart !== $previousStart + $slotDuration * 60) {
                throw new RuntimeException("Choose consecutive available times without gaps.");
            }
        }
        $totalAmount += (float) $slot["court_rate"];
    }

    $lastSlot = $slots[count($slots) - 1];
    $startTime = $firstSlot["court_time"];
    $endTime = (new DateTimeImmutable($lastSlot["court_date"] . " " . $lastSlot["court_time"]))
        ->modify("+{$slotDuration} minutes")
        ->format("H:i:s");
    $totalDuration = $slotDuration * count($slots);
    if ($totalDuration > 480) {
        throw new RuntimeException("Walk-in reservations are limited to eight hours.");
    }

    $conflictStmt = $conn->prepare(
        "SELECT b.booking_id
         FROM bookings b
         INNER JOIN court_schedule start_schedule ON start_schedule.schedule_id = b.schedule_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         WHERE b.court_id = ? AND start_schedule.court_date = ?
             AND b.status IN ('pending_payment', 'pending', 'confirmed')
             AND (b.status = 'confirmed' OR b.hold_expires_at > NOW())
             AND COALESCE(b.start_time, start_schedule.court_time) < ?
             AND COALESCE(b.end_time, ADDTIME(start_schedule.court_time, SEC_TO_TIME(cd.slot_duration * 60))) > ?
         LIMIT 1
         FOR UPDATE"
    );
    $conflictStmt->bind_param("isss", $courtId, $firstSlot["court_date"], $endTime, $startTime);
    $conflictStmt->execute();
    $hasConflict = (bool) $conflictStmt->get_result()->fetch_assoc();
    $conflictStmt->close();
    if ($hasConflict) {
        throw new RuntimeException("One or more selected times are no longer available. Refresh and choose another time.");
    }

    foreach ($slots as $slot) {
        if ($slot["schedule_status"] !== "Held") {
            continue;
        }
        $releaseExpiredStmt = $conn->prepare(
            "UPDATE court_schedule SET schedule_status = 'Available', hold_expires_at = NULL
             WHERE schedule_id = ? AND schedule_status = 'Held'
                 AND (hold_expires_at IS NULL OR hold_expires_at <= NOW())"
        );
        $releaseExpiredStmt->bind_param("i", $slot["schedule_id"]);
        $releaseExpiredStmt->execute();
        $released = $releaseExpiredStmt->affected_rows === 1;
        $releaseExpiredStmt->close();
        if (!$released) {
            throw new RuntimeException("One or more selected times are no longer available. Refresh and choose another time.");
        }
    }

    $updateStmt = $conn->prepare(
        "UPDATE court_schedule SET schedule_status = 'Booked', hold_expires_at = NULL
         WHERE schedule_id IN ($schedulePlaceholders) AND schedule_status = 'Available'"
    );
    $updateStmt->bind_param($scheduleTypes, ...$scheduleIds);
    $updateStmt->execute();
    if ($updateStmt->affected_rows !== count($scheduleIds)) {
        $updateStmt->close();
        throw new RuntimeException("One or more selected times are no longer available. Refresh and choose another time.");
    }
    $updateStmt->close();

    $bookingStmt = $conn->prepare(
        "INSERT INTO bookings
            (user_id, booking_type, customer_name, customer_phone, court_id, schedule_id,
             start_time, end_time, total_duration, total_amount, status, payment_status)
         VALUES (NULL, 'walk_in', ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'paid')"
    );
    $firstScheduleId = (int) $firstSlot["schedule_id"];
    $bookingStmt->bind_param("ssiissid", $customerName, $customerPhone, $courtId, $firstScheduleId, $startTime, $endTime, $totalDuration, $totalAmount);
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
        "court_no" => $firstSlot["court_no"],
        "court_date" => $firstSlot["court_date"],
        "court_time" => $startTime,
        "end_time" => $endTime,
        "total_duration" => $totalDuration,
        "total_amount" => $totalAmount
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