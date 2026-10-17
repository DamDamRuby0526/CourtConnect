<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Sign in with a player account to submit payment."]);
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$bookingId = filter_var($_POST["booking_id"] ?? null, FILTER_VALIDATE_INT);
$bookingGroupId = filter_var($_POST["booking_group_id"] ?? null, FILTER_VALIDATE_INT);
$referenceNumber = is_string($_POST["reference_number"] ?? null) ? trim($_POST["reference_number"]) : "";
$receipt = $_FILES["receipt"] ?? null;
if ((!$bookingGroupId || $bookingGroupId < 1) && (!$bookingId || $bookingId < 1)
    || $referenceNumber === "" || strlen($referenceNumber) > 50) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a valid reference number."]);
    exit();
}
if (!$receipt || ($receipt["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a receipt image to upload."]);
    exit();
}
if (($receipt["size"] ?? 0) <= 0 || $receipt["size"] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Receipt images must be 5 MB or smaller."]);
    exit();
}

$mimeExtensions = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($receipt["tmp_name"]);
$imageInfo = @getimagesize($receipt["tmp_name"]);
if (!isset($mimeExtensions[$mimeType]) || !$imageInfo || ($imageInfo["mime"] ?? "") !== $mimeType) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Upload a valid JPG, PNG, or WEBP receipt image."]);
    exit();
}

$uploadDirectory = __DIR__ . "/../../uploads/payment-receipts";
if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to prepare receipt storage."]);
    exit();
}
$fileName = bin2hex(random_bytes(16)) . "." . $mimeExtensions[$mimeType];
$filePath = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
$fileSaved = false;

try {
    $conn->begin_transaction();
    $bookingLookupId = $bookingGroupId > 0 ? $bookingGroupId : $bookingId;
    $bookingLookup = $bookingGroupId > 0 ? "b.booking_group_id = ?" : "b.booking_id = ?";
    $bookingStmt = $conn->prepare(
        "SELECT b.booking_id, b.schedule_id, b.status, b.hold_expires_at,
            cs.schedule_status, cs.hold_expires_at AS schedule_hold_expires_at,
            (cs.hold_expires_at IS NOT NULL AND cs.hold_expires_at > NOW()) AS schedule_hold_active,
            (b.hold_expires_at IS NULL OR b.hold_expires_at <= NOW()) AS hold_expired
         FROM bookings b
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE $bookingLookup AND b.user_id = ?
         ORDER BY cs.court_date, cs.court_time
         FOR UPDATE"
    );
    $bookingStmt->bind_param("ii", $bookingLookupId, $userId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    $bookings = [];
    while ($booking = $bookingResult->fetch_assoc()) {
        $bookings[] = $booking;
    }
    $bookingStmt->close();
    $validBookings = count($bookings) > 0;
    foreach ($bookings as $booking) {
        $scheduleIdsForBooking = bookingRangeScheduleIds($conn, (int) $booking["booking_id"]);
        if (!$scheduleIdsForBooking) $scheduleIdsForBooking[] = (int) $booking["schedule_id"];
        $placeholders = implode(",", array_fill(0, count($scheduleIdsForBooking), "?"));
        $types = str_repeat("i", count($scheduleIdsForBooking));
        $slotStmt = $conn->prepare(
            "SELECT cs.schedule_id, cs.schedule_status,
                    (cs.hold_expires_at IS NOT NULL AND cs.hold_expires_at > NOW()) AS schedule_hold_active
             FROM court_schedule cs
             WHERE cs.schedule_id IN ($placeholders)
             ORDER BY cs.court_date, cs.court_time
             FOR UPDATE"
        );
        $slotStmt->bind_param($types, ...$scheduleIdsForBooking);
        $slotStmt->execute();
        $slotResult = $slotStmt->get_result();
        $bookingSlots = [];
        while ($bookingSlot = $slotResult->fetch_assoc()) {
            $bookingSlots[] = $bookingSlot;
        }
        $slotStmt->close();
        $validBookings = $validBookings
            && $booking["status"] === "pending_payment"
            && (int) $booking["hold_expired"] === 0;
        foreach ($bookingSlots as $bookingSlot) {
            $validBookings = $validBookings
                && $bookingSlot["schedule_status"] === "Held"
                && (int) $bookingSlot["schedule_hold_active"] === 1;
        }
    }
    if (!$validBookings) {
        $conn->rollback();
        if ($bookings && $bookings[0]["status"] === "pending_payment") {
            expireUnpaidBookings($conn);
        }
        unlink($filePath);
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "This slot hold has expired. Pick another time."]);
        exit();
    }

    $latestPaymentStmt = $conn->prepare(
        "SELECT payments_id, payment_status, receipt_image,
                (resubmission_deadline IS NOT NULL AND resubmission_deadline > NOW()) AS resubmission_open
         FROM payments
         WHERE booking_id = ?
         ORDER BY payments_id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $paymentRecords = [];
    foreach ($bookings as $booking) {
        $currentBookingId = (int) $booking["booking_id"];
        $latestPaymentStmt->bind_param("i", $currentBookingId);
        $latestPaymentStmt->execute();
        $latestPayment = $latestPaymentStmt->get_result()->fetch_assoc();
        if ($latestPayment && $latestPayment["payment_status"] !== "Rejected") {
            $latestPaymentStmt->close();
            throw new RuntimeException("This booking already has a payment awaiting review.");
        }
        if ($latestPayment && (int) $latestPayment["resubmission_open"] !== 1) {
            $latestPaymentStmt->close();
            http_response_code(409);
            throw new RuntimeException("The 24-hour payment resubmission window has expired.");
        }
        $paymentRecords[$currentBookingId] = $latestPayment;
    }
    $latestPaymentStmt->close();
    if (!move_uploaded_file($receipt["tmp_name"], $filePath)) {
        http_response_code(500);
        throw new RuntimeException("Unable to save the receipt image.");
    }
    $fileSaved = true;
    $oldReceipts = [];
    foreach ($bookings as $booking) {
        $currentBookingId = (int) $booking["booking_id"];
        $latestPayment = $paymentRecords[$currentBookingId];
        if ($latestPayment) {
            $oldReceipt = basename((string) ($latestPayment["receipt_image"] ?? ""));
            if ($oldReceipt !== "") {
                $oldReceipts[] = $oldReceipt;
            }
            $paymentStmt = $conn->prepare(
                "UPDATE payments
                 SET payment_method = 'Gcash', payment_status = 'Pending',
                     reference_number = ?, receipt_image = ?, rejection_reason = NULL,
                     rejected_at = NULL, resubmission_deadline = NULL, paid_at = NULL
                 WHERE payments_id = ? AND booking_id = ? AND payment_status = 'Rejected'"
            );
            $paymentStmt->bind_param("ssii", $referenceNumber, $fileName, $latestPayment["payments_id"], $currentBookingId);
            $paymentStmt->execute();
            if ($paymentStmt->affected_rows !== 1) {
                $paymentStmt->close();
                throw new RuntimeException("A rejected payment has already changed.");
            }
        } else {
            $paymentStmt = $conn->prepare(
                "INSERT INTO payments (booking_id, payment_method, payment_status, reference_number, receipt_image)
                 VALUES (?, 'Gcash', 'Pending', ?, ?)"
            );
            $paymentStmt->bind_param("iss", $currentBookingId, $referenceNumber, $fileName);
            $paymentStmt->execute();
        }
        $paymentStmt->close();

        $updateBookingStmt = $conn->prepare(
            "UPDATE bookings SET status = 'pending', payment_status = 'unpaid',
                hold_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
             WHERE booking_id = ? AND user_id = ? AND status = 'pending_payment'"
        );
        $updateBookingStmt->bind_param("ii", $currentBookingId, $userId);
        $updateBookingStmt->execute();
        if ($updateBookingStmt->affected_rows !== 1) {
            $updateBookingStmt->close();
            throw new RuntimeException("A booking in this reservation is no longer waiting for payment.");
        }
        $updateBookingStmt->close();

        $extendHoldStmt = $conn->prepare(
            "UPDATE court_schedule SET hold_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                WHERE schedule_id = ?
                 AND schedule_status = 'Held' AND hold_expires_at > NOW()"
        );
        $slotIds = bookingRangeScheduleIds($conn, $currentBookingId);
        if (!$slotIds) $slotIds[] = (int) $booking["schedule_id"];
        foreach ($slotIds as $scheduleId) {
            $extendHoldStmt->bind_param("i", $scheduleId);
            $extendHoldStmt->execute();
            if ($extendHoldStmt->affected_rows !== 1) {
                $extendHoldStmt->close();
                throw new RuntimeException("A booking hold expired before payment could be submitted.");
            }
        }
        $extendHoldStmt->close();
    }

    $conn->commit();
    foreach (array_unique($oldReceipts) as $oldReceipt) {
        if ($oldReceipt === $fileName) {
            continue;
        }
        $receiptCheck = $conn->prepare("SELECT COUNT(*) AS receipt_references FROM payments WHERE receipt_image = ?");
        $receiptCheck->bind_param("s", $oldReceipt);
        $receiptCheck->execute();
        $receiptReferences = (int) $receiptCheck->get_result()->fetch_assoc()["receipt_references"];
        $receiptCheck->close();
        $oldReceiptPath = $uploadDirectory . DIRECTORY_SEPARATOR . $oldReceipt;
        if ($receiptReferences === 0 && is_file($oldReceiptPath) && !unlink($oldReceiptPath)) {
            error_log("Unable to remove replaced receipt image for booking " . $bookingId . ".");
        }
    }
    echo json_encode(["success" => true, "message" => "Payment submitted. The facility will confirm your payment."]);
} catch (RuntimeException $error) {
    $conn->rollback();
    if ($fileSaved && is_file($filePath)) unlink($filePath);
    if (http_response_code() < 400) {
        http_response_code(409);
    }
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
} catch (Throwable $error) {
    $conn->rollback();
    if ($fileSaved && is_file($filePath)) unlink($filePath);
    http_response_code(500);
    error_log("Checkout receipt submission failed: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to submit this payment."]);
}
$conn->close();