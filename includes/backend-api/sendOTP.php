<?php

header("Content-Type: application/json");

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/send_email.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON data."
    ]);
    exit();
}

$userId = filter_var(
    $data["user_id"] ?? null,
    FILTER_VALIDATE_INT
);

if ($userId === false || $userId <= 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid user ID is required."
    ]);
    exit();
}

// Get the user's email address 
$sql = "SELECT u.email, u.first_name, u.is_verified,
           (SELECT a.status FROM admins a WHERE a.user_id = u.user_id LIMIT 1) AS admin_status
    FROM users u
    WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("User query preparation failed: " . $conn->error);

    echo json_encode([
        "success" => false,
        "message" => "Unable to process the request."
    ]);
    exit();
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

if ((int) $user["is_verified"] === 1) {
    echo json_encode([
        "success" => false,
        "message" => "Account is already verified."
    ]);
    exit();
}

if ($user["admin_status"] !== null && $user["admin_status"] !== "Active") {
    echo json_encode([
        "success" => false,
        "message" => "Your facility application must be approved before email verification."
    ]);
    exit();
}

$email = trim($user["email"] ?? "");
$firstName = trim($user["first_name"] ?? "");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "The account does not have a valid email address."
    ]);
    exit();
}

// Generation of OTP CODE

//Allow only one OTP request every 60 seconds.
$rateStmt = $conn->prepare(
    "SELECT expires_at
     FROM otp_codes
     WHERE user_id = ?
     LIMIT 1"
);

if (!$rateStmt) {
    error_log("OTP cooldown query failed: " . $conn->error);

    echo json_encode([
        "success" => false,
        "message" => "Unable to process the OTP request."
    ]);
    exit();
}

$rateStmt->bind_param("i", $userId);
$rateStmt->execute();

$rateResult = $rateStmt->get_result();

if ($rateResult->num_rows === 1) {
    $existingOtp = $rateResult->fetch_assoc();
    $expiresTimestamp = strtotime($existingOtp["expires_at"]);
    $secondsRemaining = $expiresTimestamp - time();

    if ($secondsRemaining > 240) {
        $waitSeconds = $secondsRemaining - 240;
        $rateStmt->close();

        echo json_encode([
            "success" => false,
            "message" =>
                "Please wait {$waitSeconds} seconds before requesting another code.",
            "retry_after" => $waitSeconds
        ]);
        exit();
    }
}

$rateStmt->close();

//Generate a six-digit OTP that expires after five minutes.
$otp = (string) random_int(100000, 999999);
$expiresAt = date("Y-m-d H:i:s", time() + 300);


// Remove the user's previous OTP.
$sql = "DELETE FROM otp_codes WHERE user_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("OTP deletion preparation failed: " . $conn->error);

    echo json_encode([
        "success" => false,
        "message" => "Unable to generate the verification code."
    ]);
    exit();
}

$stmt->bind_param("i", $userId);

if (!$stmt->execute()) {
    error_log("OTP deletion failed: " . $stmt->error);
    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Unable to generate the verification code."
    ]);
    exit();
}

$stmt->close();

//Save the newly generated OTP.
$sql = "INSERT INTO otp_codes
        (user_id, otp_code, expires_at)
        VALUES (?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("OTP insertion preparation failed: " . $conn->error);

    echo json_encode([
        "success" => false,
        "message" => "Unable to generate the verification code."
    ]);
    exit();
}

$stmt->bind_param(
    "iss",
    $userId,
    $otp,
    $expiresAt
);

if (!$stmt->execute()) {
    error_log("OTP insertion failed: " . $stmt->error);
    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Failed to generate the verification code."
    ]);
    exit();
}

$stmt->close();

//Send the OTP through Brevo using send_email.php.
$emailResult = sendOtpEmail(
    $email,
    $firstName,
    $otp
);

if (empty($emailResult["success"])) {
    // Remove the unusable OTP when the email cannot be sent.
    

    $deleteStmt = $conn->prepare(
        "DELETE FROM otp_codes WHERE user_id = ?"
    );

    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $userId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
}

$conn->close();

echo json_encode($emailResult);
exit();



?>