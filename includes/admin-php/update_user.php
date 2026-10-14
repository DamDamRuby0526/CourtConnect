<?php
// update single user in a form
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";
require_once __DIR__ . "/courtconnect_auth_guard.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    echo json_encode(["success" => false, "message" => "Use POST to update a user."]);
    exit();
}

$csrfToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
$sessionCsrfToken = $_SESSION["csrf_token"] ?? "";
if (
    !is_string($csrfToken) || $csrfToken === ""
    || !is_string($sessionCsrfToken) || $sessionCsrfToken === ""
    || !hash_equals($sessionCsrfToken, $csrfToken)
) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Your session has expired. Refresh the page and try again."]);
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

$userId      = $data["user_id"] ?? null;
$firstName   = trim($data["first_name"] ?? "");
$lastName    = trim($data["last_name"] ?? "");
$email       = trim($data["email"] ?? "");
$phoneNumber = trim($data["phone_number"] ?? "");

if (!$userId || !is_numeric($userId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing user_id."
    ]);
    exit();
}


if (empty($firstName) || empty($lastName) || empty($email) || empty($phoneNumber)) {
    echo json_encode([
        "success" => false,
        "message" => "Please fill in all fields."
    ]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email address."
    ]);
    exit();
}

if (!preg_match('/^09\d{9}$/', $phoneNumber)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid Philippine phone number."
    ]);
    exit();
}


$sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $email, $userId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "Email already in use by another account."
    ]);
    exit();
}
$stmt->close();


$sql = "SELECT user_id FROM users WHERE phone_number = ? AND user_id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $phoneNumber, $userId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "Phone number already in use by another account."
    ]);
    exit();
}
$stmt->close();

$sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ? WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $firstName, $lastName, $email, $phoneNumber, $userId);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "User updated successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Update failed."
    ]);
}

$stmt->close();
$conn->close();
?>