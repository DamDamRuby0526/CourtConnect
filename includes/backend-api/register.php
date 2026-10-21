<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/registration_consent_config.php";

function registrationRespond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    registrationRespond(405, ["success" => false, "message" => "Invalid request method."]);
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    registrationRespond(400, ["success" => false, "message" => "Invalid registration data."]);
}

if (($data["consent"] ?? null) !== "accepted"
    || ($data["privacy_notice_version"] ?? null) !== COURTCONNECT_PRIVACY_NOTICE_VERSION
    || ($data["terms_version"] ?? null) !== COURTCONNECT_TERMS_VERSION) {
    registrationRespond(400, [
        "success" => false,
        "message" => "Review and accept the current Data Privacy Notice and Terms and Conditions before registering."
    ]);
}

$firstName = is_string($data["first_name"] ?? null) ? trim($data["first_name"]) : "";
$lastName = is_string($data["last_name"] ?? null) ? trim($data["last_name"]) : "";
$email = is_string($data["email"] ?? null) ? trim($data["email"]) : "";
$phoneNumber = is_string($data["phone_number"] ?? null) ? trim($data["phone_number"]) : "";
$password = is_string($data["password"] ?? null) ? trim($data["password"]) : "";

if ($firstName === "" || $lastName === "" || $email === "" || $phoneNumber === "" || $password === "") {
    registrationRespond(400, ["success" => false, "message" => "Please fill in all fields."]);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    registrationRespond(400, ["success" => false, "message" => "Invalid email address."]);
}
if (strlen($password) < 8) {
    registrationRespond(400, ["success" => false, "message" => "Password must be at least 8 characters."]);
}
if (!preg_match('/^09\d{9}$/', $phoneNumber)) {
    registrationRespond(400, ["success" => false, "message" => "Invalid Philippine phone number."]);
}

try {
    $emailStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $emailStmt->bind_param("s", $email);
    $emailStmt->execute();
    $emailExists = (bool) $emailStmt->get_result()->fetch_assoc();
    $emailStmt->close();
    if ($emailExists) {
        registrationRespond(409, [
            "success" => false,
            "code" => "email_exists",
            "field" => "email",
            "message" => "Email already exists."
        ]);
    }

    $phoneStmt = $conn->prepare("SELECT user_id FROM users WHERE phone_number = ? LIMIT 1");
    $phoneStmt->bind_param("s", $phoneNumber);
    $phoneStmt->execute();
    $phoneExists = (bool) $phoneStmt->get_result()->fetch_assoc();
    $phoneStmt->close();
    if ($phoneExists) {
        registrationRespond(409, ["success" => false, "message" => "Phone number already exists."]);
    }

    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start the customer registration transaction.");
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hashedPassword)) {
        throw new RuntimeException("Password hashing failed.");
    }

    $roleId = 1;
    $userStmt = $conn->prepare(
        "INSERT INTO users
            (first_name, last_name, email, phone_number, password, role_id,
             privacy_notice_version, terms_version, consented_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
    );
    $privacyVersion = COURTCONNECT_PRIVACY_NOTICE_VERSION;
    $termsVersion = COURTCONNECT_TERMS_VERSION;
    $userStmt->bind_param(
        "sssssiss",
        $firstName,
        $lastName,
        $email,
        $phoneNumber,
        $hashedPassword,
        $roleId,
        $privacyVersion,
        $termsVersion
    );
    $userStmt->execute();
    $newUserId = (int) $userStmt->insert_id;
    $userStmt->close();
    if ($newUserId < 1) {
        throw new RuntimeException("Customer account was not created.");
    }

    if (!$conn->commit()) {
        throw new RuntimeException("Customer registration transaction could not be committed.");
    }

    registrationRespond(201, [
        "success" => true,
        "message" => "Registration successful.",
        "user_id" => $newUserId,
        "requires_verification" => true
    ]);
} catch (Throwable $error) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log("Customer registration failed: " . $error->getMessage());
    registrationRespond(500, [
        "success" => false,
        "message" => "Registration could not be completed. Please try again."
    ]);
}
