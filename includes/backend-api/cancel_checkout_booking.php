<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/../cancel_booking.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "code" => "unauthorized",
        "message" => "Sign in with a player account to cancel this booking.",
    ]);
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "code" => "invalid_method", "message" => "Invalid request method."]);
    exit();
}

$bookingId = filter_var($_POST["booking_id"] ?? null, FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId < 1) {
    http_response_code(400);
    echo json_encode(["success" => false, "code" => "invalid_booking", "message" => "Choose a valid booking."]);
    exit();
}

$result = cancelBookingForUser((int) $bookingId, (int) $userId);
$httpStatus = (int) ($result["http_status"] ?? 500);
unset($result["http_status"]);
http_response_code($httpStatus);
echo json_encode($result);