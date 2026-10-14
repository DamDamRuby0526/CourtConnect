<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Sign in with a customer account to submit payment."]);
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$bookingId = filter_var($_POST["booking_id"] ?? null, FILTER_VALIDATE_INT);
$paymentMethod = $_POST["payment_method"] ?? "";
$referenceNumber = is_string($_POST["reference_number"] ?? null) ? trim($_POST["reference_number"]) : "";
$receipt = $_FILES["receipt"] ?? null;
if (!$bookingId || $bookingId < 1 || $paymentMethod !== "Gcash"
    || $referenceNumber === "" || strlen($referenceNumber) > 50) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a valid reference number and payment method."]);
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
    $bookingStmt = $conn->prepare(
        "SELECT b.booking_id, b.status, b.schedule_id,
                (b.hold_expires_at IS NULL OR b.hold_expires_at <= NOW()) AS booking_hold_expired,
                cs.schedule_status,
                (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW()) AS schedule_hold_expired,
                (TIMESTAMP(cs.court_date, cs.court_time) <= NOW()) AS slot_started
         FROM bookings b
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE b.booking_id = ? AND b.user_id = ?
         FOR UPDATE"
    );
    $bookingStmt->bind_param("ii", $bookingId, $userId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();

    if (!$booking) {
        http_response_code(404);
        throw new RuntimeException("Booking not found.");
    }
    $bookingStatus = strtolower((string) ($booking["status"] ?? ""));
    if (in_array($bookingStatus, ["cancelled", "completed", "expired"], true)) {
        http_response_code(409);
        throw new RuntimeException("This booking is no longer active and cannot accept another payment.");
    }
    if (!in_array($bookingStatus, ["pending", "pending_payment"], true)
        || $booking["schedule_status"] !== "Held"
        || (int) $booking["booking_hold_expired"] === 1
        || (int) $booking["schedule_hold_expired"] === 1
        || (int) $booking["slot_started"] === 1) {
        http_response_code(409);
        throw new RuntimeException("This reservation is no longer available for payment resubmission.");
    }

    $latestPaymentStmt = $conn->prepare(
        "SELECT payments_id, payment_status, receipt_image
         FROM payments
         WHERE booking_id = ?
         ORDER BY payments_id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $latestPaymentStmt->bind_param("i", $bookingId);
    $latestPaymentStmt->execute();
    $latestPayment = $latestPaymentStmt->get_result()->fetch_assoc();
    $latestPaymentStmt->close();
    if (!$latestPayment || $latestPayment["payment_status"] !== "Rejected") {
        http_response_code(409);
        throw new RuntimeException("This booking does not have a rejected payment awaiting resubmission.");
    }

    $oldReceipt = basename((string) ($latestPayment["receipt_image"] ?? ""));
    if (!move_uploaded_file($receipt["tmp_name"], $filePath)) {
        http_response_code(500);
        throw new RuntimeException("Unable to save the receipt image.");
    }
    $fileSaved = true;

    $paymentStmt = $conn->prepare(
        "UPDATE payments
         SET payment_method = ?, payment_status = 'Pending', reference_number = ?,
             receipt_image = ?, rejection_reason = NULL, paid_at = NULL
         WHERE payments_id = ? AND booking_id = ? AND payment_status = 'Rejected'"
    );
    $paymentStmt->bind_param("sssii", $paymentMethod, $referenceNumber, $fileName, $latestPayment["payments_id"], $bookingId);
    $paymentStmt->execute();
    if ($paymentStmt->affected_rows !== 1) {
        $paymentStmt->close();
        http_response_code(409);
        throw new RuntimeException("This rejected payment has already changed.");
    }
    $paymentId = (int) $latestPayment["payments_id"];
    $paymentStmt->close();

    $bookingUpdateStmt = $conn->prepare(
        "UPDATE bookings
         SET status = 'pending', payment_status = 'unpaid',
             hold_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
         WHERE booking_id = ? AND user_id = ? AND status IN ('pending', 'pending_payment')"
    );
    $bookingUpdateStmt->bind_param("ii", $bookingId, $userId);
    $bookingUpdateStmt->execute();
    if ($bookingUpdateStmt->affected_rows !== 1) {
        $bookingUpdateStmt->close();
        http_response_code(409);
        throw new RuntimeException("This booking changed before the payment could be resubmitted.");
    }
    $bookingUpdateStmt->close();

    $scheduleStmt = $conn->prepare(
        "UPDATE court_schedule
         SET hold_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
         WHERE schedule_id = ? AND schedule_status = 'Held' AND hold_expires_at > NOW()"
    );
    $scheduleStmt->bind_param("i", $booking["schedule_id"]);
    $scheduleStmt->execute();
    if ($scheduleStmt->affected_rows !== 1) {
        $scheduleStmt->close();
        http_response_code(409);
        throw new RuntimeException("This reservation is no longer available for payment resubmission.");
    }
    $scheduleStmt->close();

    $conn->commit();
    if ($oldReceipt !== "" && $oldReceipt !== $fileName) {
        $oldReceiptPath = $uploadDirectory . DIRECTORY_SEPARATOR . $oldReceipt;
        if (is_file($oldReceiptPath)) {
            unlink($oldReceiptPath);
        }
    }
    echo json_encode([
        "success" => true,
        "payment_id" => $paymentId,
        "payment_status" => "Pending",
        "message" => "Your replacement payment was submitted for review."
    ]);
} catch (RuntimeException $error) {
    $conn->rollback();
    if ($fileSaved && is_file($filePath)) {
        unlink($filePath);
    }
    if (http_response_code() < 400) {
        http_response_code(409);
    }
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
} catch (Throwable $error) {
    $conn->rollback();
    if ($fileSaved && is_file($filePath)) {
        unlink($filePath);
    }
    http_response_code(500);
    error_log("Payment resubmission failed for booking " . $bookingId . ": " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to submit this payment. Please try again."]);
}
$conn->close();
