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
$referenceNumber = is_string($_POST["reference_number"] ?? null) ? trim($_POST["reference_number"]) : "";
$receipt = $_FILES["receipt"] ?? null;
if (!$bookingId || $bookingId < 1 || $referenceNumber === "" || strlen($referenceNumber) > 50) {
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
$oldReceipt = "";

try {
    $conn->begin_transaction();
    $bookingStmt = $conn->prepare(
        "SELECT b.booking_id, b.schedule_id, b.status, b.hold_expires_at,
            cs.schedule_status, cs.hold_expires_at AS schedule_hold_expires_at,
            (b.hold_expires_at IS NULL OR b.hold_expires_at <= NOW()) AS hold_expired
         FROM bookings b
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE b.booking_id = ? AND b.user_id = ?
         FOR UPDATE"
    );
    $bookingStmt->bind_param("ii", $bookingId, $userId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();
    if (!$booking || $booking["status"] !== "pending_payment" || $booking["schedule_status"] !== "Held"
        || (int) $booking["hold_expired"] === 1) {
        $conn->rollback();
        if ($booking && $booking["status"] === "pending_payment") {
            expireUnpaidBookings($conn);
        }
        unlink($filePath);
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "This slot hold has expired. Pick another time."]);
        exit();
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
    if ($latestPayment && $latestPayment["payment_status"] !== "Rejected") {
        throw new RuntimeException("This booking already has a payment awaiting review.");
    }
    if (!move_uploaded_file($receipt["tmp_name"], $filePath)) {
        http_response_code(500);
        throw new RuntimeException("Unable to save the receipt image.");
    }
    $fileSaved = true;
    if ($latestPayment) {
        $oldReceipt = basename((string) ($latestPayment["receipt_image"] ?? ""));
        $paymentStmt = $conn->prepare(
            "UPDATE payments
             SET payment_method = 'Gcash', payment_status = 'Pending',
                 reference_number = ?, receipt_image = ?, rejection_reason = NULL, paid_at = NULL
             WHERE payments_id = ? AND booking_id = ? AND payment_status = 'Rejected'"
        );
        $paymentStmt->bind_param("ssii", $referenceNumber, $fileName, $latestPayment["payments_id"], $bookingId);
        $paymentStmt->execute();
        if ($paymentStmt->affected_rows !== 1) {
            $paymentStmt->close();
            throw new RuntimeException("This rejected payment has already changed.");
        }
        $paymentStmt->close();
    } else {
        $paymentStmt = $conn->prepare(
            "INSERT INTO payments (booking_id, payment_method, payment_status, reference_number, receipt_image)
             VALUES (?, 'Gcash', 'Pending', ?, ?)"
        );
        $paymentStmt->bind_param("iss", $bookingId, $referenceNumber, $fileName);
        $paymentStmt->execute();
        $paymentStmt->close();
    }

    $updateBookingStmt = $conn->prepare(
        "UPDATE bookings SET status = 'pending', payment_status = 'unpaid',
            hold_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
         WHERE booking_id = ? AND user_id = ? AND status = 'pending_payment'"
    );
    $updateBookingStmt->bind_param("ii", $bookingId, $userId);
    $updateBookingStmt->execute();
    if ($updateBookingStmt->affected_rows !== 1) {
        $updateBookingStmt->close();
        throw new RuntimeException("This booking is no longer waiting for payment.");
    }
    $updateBookingStmt->close();

    $extendHoldStmt = $conn->prepare(
        "UPDATE court_schedule SET hold_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
            WHERE schedule_id = ?
             AND schedule_status = 'Held' AND hold_expires_at > NOW()"
    );
        $extendHoldStmt->bind_param("i", $booking["schedule_id"]);
    $extendHoldStmt->execute();
    if ($extendHoldStmt->affected_rows !== 1) {
        $extendHoldStmt->close();
        throw new RuntimeException("This booking hold expired before payment could be submitted.");
    }
    $extendHoldStmt->close();

    $conn->commit();
    if ($oldReceipt !== "" && $oldReceipt !== $fileName) {
        $oldReceiptPath = $uploadDirectory . DIRECTORY_SEPARATOR . $oldReceipt;
        if (is_file($oldReceiptPath)) {
            unlink($oldReceiptPath);
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