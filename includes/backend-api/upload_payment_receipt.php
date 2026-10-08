<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Sign in with a customer account to upload a receipt."]);
    exit();
}

$paymentId = filter_var($_POST["payment_id"] ?? null, FILTER_VALIDATE_INT);
$receipt = $_FILES["receipt"] ?? null;
if (!$paymentId || !$receipt || ($receipt["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a receipt image to upload."]);
    exit();
}
if (($receipt["size"] ?? 0) <= 0 || $receipt["size"] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Receipt images must be smaller than 5 MB."]);
    exit();
}

$mimeTypes = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp"
];
$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $fileInfo->file($receipt["tmp_name"]);
if (!isset($mimeTypes[$mimeType])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Upload a JPG, PNG, or WEBP receipt image."]);
    exit();
}

$uploadDirectory = __DIR__ . "/../../uploads/payment-receipts";
if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to prepare the receipt upload folder."]);
    exit();
}
$fileName = bin2hex(random_bytes(16)) . "." . $mimeTypes[$mimeType];
$filePath = $uploadDirectory . "/" . $fileName;

try {
    $conn->begin_transaction();
    $paymentStmt = $conn->prepare(
        "SELECT p.payment_status, p.receipt_image
         FROM payments p
         INNER JOIN bookings b ON b.booking_id = p.booking_id
         WHERE p.payments_id = ? AND b.user_id = ?
         FOR UPDATE"
    );
    $paymentStmt->bind_param("ii", $paymentId, $userId);
    $paymentStmt->execute();
    $payment = $paymentStmt->get_result()->fetch_assoc();
    $paymentStmt->close();
    if (!$payment || $payment["payment_status"] !== "Pending") {
        throw new RuntimeException("A receipt can only be uploaded for your pending payment.");
    }
    if (!move_uploaded_file($receipt["tmp_name"], $filePath)) {
        throw new RuntimeException("Unable to save the receipt image.");
    }

    $updateStmt = $conn->prepare(
        "UPDATE payments p
         INNER JOIN bookings b ON b.booking_id = p.booking_id
         SET p.receipt_image = ?
         WHERE p.payments_id = ? AND b.user_id = ? AND p.payment_status = 'Pending'"
    );
    $updateStmt->bind_param("sii", $fileName, $paymentId, $userId);
    $updateStmt->execute();
    $updated = $updateStmt->affected_rows === 1;
    $updateStmt->close();
    if (!$updated) {
        throw new RuntimeException("The payment changed before the receipt could be saved.");
    }

    $conn->commit();
    $oldFileName = basename((string) ($payment["receipt_image"] ?? ""));
    if ($oldFileName !== "" && is_file($uploadDirectory . "/" . $oldFileName)) {
        unlink($uploadDirectory . "/" . $oldFileName);
    }
    echo json_encode(["success" => true, "message" => "Receipt uploaded for review."]);
} catch (RuntimeException $error) {
    $conn->rollback();
    if (is_file($filePath)) {
        unlink($filePath);
    }
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
} catch (Throwable $error) {
    $conn->rollback();
    if (is_file($filePath)) {
        unlink($filePath);
    }
    http_response_code(500);
    error_log("Payment receipt upload error: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to upload the receipt image."]);
}