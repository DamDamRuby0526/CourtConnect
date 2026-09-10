<?php

header("Content-Type: application/json");
require "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "No data received."
    ]);
    exit();
}

$userId = $data["user_id"] ?? null;
$otp = trim($data["otp"] ?? "");

if (!$userId || empty($otp)) {
    echo json_encode([
        "success" => false,
        "message" => "User ID and OTP are required."
    ]);
    exit();
}

if (!preg_match('/^\d{6}$/', $otp)) {
    echo json_encode([
        "success" => false,
        "message" => "OTP must contain 6 digits."
    ]);
    exit();
}

$sql = "SELECT otp_id, otp_code, expires_at
        FROM otp_codes
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare OTP verification."
    ]);
    exit();
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "No OTP found. Please request a new code."
    ]);
    exit();
}

$otpRecord = $result->fetch_assoc();
$stmt->close();

// //Checks expiration
// $currentTime = time();
// $expiryTime = strtotime($otpRecord["expires_at"]);

// if ($expiryTime === false || $currentTime > $expiryTime) {
//     echo json_encode([
//         "success" => false,
//         "message" => "OTP has expired. Please request a new code."
//     ]);
//     exit();
// }

//Verify the OTP
if (!hash_equals((string) $otpRecord["otp_code"], $otp)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid OTP."
    ]);
    exit();
}

// If OTP is correct, then verify the user
$sql = "UPDATE users
        SET is_verified = 1
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Unable to prepare account verification."
    ]);
    exit();
}

$stmt->bind_param("i", $userId);

if (!$stmt->execute()) {
    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Failed to verify account."
    ]);
    exit();
}

$stmt->close();

// Get the user information needed by the Android session
$sql = "SELECT user_id, first_name, last_name, email, phone_number
        FROM users
        WHERE user_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Account was verified, but user information could not be retrieved."
    ]);
    exit();
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Account was verified, but the user was not found."
    ]);
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// If verified, OTP is deleted
$sql = "DELETE FROM otp_codes
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    "success" => true,
    "message" => "Account verified successfully.",
    "user" => [
        "user_id" => (int) $user["user_id"],
        "first_name" => $user["first_name"],
        "last_name" => $user["last_name"],
        "email" => $user["email"],
        "phone_number" => $user["phone_number"]
    ]
]);

$conn->close();

?>