<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Sign in with a player account to check this booking."]);
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$bookingId = filter_var($_POST["booking_id"] ?? null, FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId < 1) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid booking."]);
    exit();
}

$bookingStmt = $conn->prepare(
    "SELECT status, hold_expires_at,
            (hold_expires_at IS NULL OR hold_expires_at <= NOW()) AS hold_expired,
            EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = bookings.booking_id) AS has_payment,
            UNIX_TIMESTAMP(hold_expires_at) AS hold_expires_epoch,
            UNIX_TIMESTAMP(NOW()) AS server_now_epoch
     FROM bookings
     WHERE booking_id = ? AND user_id = ?
     LIMIT 1"
);
$bookingStmt->bind_param("ii", $bookingId, $userId);
$bookingStmt->execute();
$booking = $bookingStmt->get_result()->fetch_assoc();
$bookingStmt->close();

if ($booking && $booking["status"] === "pending_payment"
    && empty($booking["has_payment"]) && (int) $booking["hold_expired"] === 1) {
    expireUnpaidBookings($conn);
    $bookingStmt = $conn->prepare(
        "SELECT status, hold_expires_at,
                (hold_expires_at IS NULL OR hold_expires_at <= NOW()) AS hold_expired,
            EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = bookings.booking_id) AS has_payment,
                UNIX_TIMESTAMP(hold_expires_at) AS hold_expires_epoch,
                UNIX_TIMESTAMP(NOW()) AS server_now_epoch
         FROM bookings
         WHERE booking_id = ? AND user_id = ?
         LIMIT 1"
    );
    $bookingStmt->bind_param("ii", $bookingId, $userId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();
}

$noticeStmt = $conn->prepare(
    "SELECT notification_id, court_no, court_time
     FROM booking_expiry_notifications
     WHERE booking_id = ? AND user_id = ?
     LIMIT 1"
);
$noticeStmt->bind_param("ii", $bookingId, $userId);
$noticeStmt->execute();
$notice = $noticeStmt->get_result()->fetch_assoc();
$noticeStmt->close();

if (!$booking && !$notice) {
    $conn->close();
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Booking not found."]);
    exit();
}

$isExpired = !$booking || $booking["status"] === "Expired"
    || $booking["status"] === "cancelled"
    || ($booking["status"] === "pending_payment"
        && empty($booking["has_payment"]) && (int) $booking["hold_expired"] === 1);
$message = $notice
    ? "Your booking for Court " . $notice["court_no"] . " at " . date("g:i A", strtotime($notice["court_time"])) . " expired because payment wasn't completed. The slot was released."
    : "Your booking expired and the slot was released.";

if ($isExpired && $notice) {
    $notificationId = (int) $notice["notification_id"];
    $consumeStmt = $conn->prepare(
        "UPDATE booking_expiry_notifications SET consumed_at = COALESCE(consumed_at, NOW())
         WHERE notification_id = ? AND user_id = ?"
    );
    $consumeStmt->bind_param("ii", $notificationId, $userId);
    $consumeStmt->execute();
    $consumeStmt->close();
}
$conn->close();

echo json_encode([
    "success" => true,
    "expired" => $isExpired,
    "message" => $isExpired ? $message : "",
    "hold_expires_epoch" => $booking ? (int) $booking["hold_expires_epoch"] : 0,
    "server_now_epoch" => $booking ? (int) $booking["server_now_epoch"] : 0,
]);
