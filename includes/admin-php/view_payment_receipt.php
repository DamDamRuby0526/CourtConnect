<?php
require_once __DIR__ . "/../backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$paymentId = filter_var($_GET["payment_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || !$paymentId) {
    http_response_code(404);
    exit();
}

$roleId = (int) ($_SESSION["role_id"] ?? 0);
if (in_array($roleId, [2, 3], true)) {
    $facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);
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
    $authorized = (bool) $accessStmt->get_result()->fetch_assoc();
    $accessStmt->close();
    if (!$authorized) {
        http_response_code(403);
        exit();
    }
    $stmt = $conn->prepare(
        "SELECT p.receipt_image
         FROM payments p
         INNER JOIN bookings b ON b.booking_id = p.booking_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         WHERE p.payments_id = ? AND cd.facility_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $paymentId, $facilityId);
} elseif ($roleId === 1) {
    $stmt = $conn->prepare(
        "SELECT p.receipt_image
         FROM payments p
         INNER JOIN bookings b ON b.booking_id = p.booking_id
         WHERE p.payments_id = ? AND b.user_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $paymentId, $userId);
} else {
    http_response_code(403);
    exit();
}

$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$fileName = basename((string) ($payment["receipt_image"] ?? ""));
$filePath = __DIR__ . "/../../uploads/payment-receipts/" . $fileName;
$mimeByExtension = ["jpg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if (!$payment || $fileName === "" || !isset($mimeByExtension[$extension]) || !is_file($filePath)) {
    http_response_code(404);
    exit();
}

header("Content-Type: " . $mimeByExtension[$extension]);
header("Content-Length: " . filesize($filePath));
header("Content-Disposition: inline; filename=\"payment-receipt." . $extension . "\"");
header("Cache-Control: private, no-store");
header("X-Content-Type-Options: nosniff");
readfile($filePath);