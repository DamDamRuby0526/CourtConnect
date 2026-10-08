<?php

header("Content-Type: application/json");
require 'config.php';

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

$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");

if (empty($email) || empty($password)) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);
    exit();
}

$sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone_number,
           u.password, u.is_verified, u.role_id, r.role_name,
           (SELECT a.admin_id FROM admins a WHERE a.user_id = u.user_id LIMIT 1) AS admin_id,
           (SELECT a.facility_id FROM admins a WHERE a.user_id = u.user_id LIMIT 1) AS facility_id,
           (SELECT a.status FROM admins a WHERE a.user_id = u.user_id LIMIT 1) AS admin_status,
           (SELECT a.rejection_reason FROM admins a WHERE a.user_id = u.user_id LIMIT 1) AS rejection_reason
    FROM users u
    LEFT JOIN roles r ON r.role_id = u.role_id
    WHERE u.email = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password."
    ]);
    exit();
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user["password"])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password."
    ]);
    exit();
}

if ($user["admin_status"] === "Pending") {
    $_SESSION["pending_facility_user_id"] = (int) $user["user_id"];
    echo json_encode([
        "success" => false,
        "message" => "Your facility application is waiting for developer review.",
        "requires_review" => true
    ]);
    exit();
}

if ($user["admin_status"] === "Inactive") {
    echo json_encode([
        "success" => false,
        "message" => "This admin account has been disabled by the facility owner."
    ]);
    exit();
}

if ($user["admin_status"] !== null && $user["admin_status"] !== "Active") {
    $_SESSION["pending_facility_user_id"] = (int) $user["user_id"];
    echo json_encode([
        "success" => false,
        "message" => $user["rejection_reason"] ?: "Your facility application was not approved.",
        "requires_review" => true
    ]);
    exit();
}

if ((int)$user["is_verified"] === 0) {
    $isFacilityOwner = $user["admin_status"] !== null;
    if ($isFacilityOwner) {
        $_SESSION["pending_facility_user_id"] = (int) $user["user_id"];
    }
    echo json_encode([
        "success" => false,
        "message" => "Account is not yet verified.",
        "requires_verification" => true,
        "is_facility_owner" => $isFacilityOwner,
        "user_id" => $user["user_id"]
    ]);
    exit();
}

$_SESSION["user_id"] = $user["user_id"];
$_SESSION["first_name"] = $user["first_name"];
$_SESSION["last_name"] = $user["last_name"];
$_SESSION["email"] = $user["email"];
$_SESSION["logged_in"] = true;
$_SESSION["role_id"] = (int) $user["role_id"];
$_SESSION["role_name"] = $user["role_name"];

if ($user["admin_id"] !== null && $user["facility_id"] !== null) {
    $_SESSION["admin_id"] = (int) $user["admin_id"];
    $_SESSION["facility_id"] = (int) $user["facility_id"];
    $_SESSION["role"] = "admin";
}

echo json_encode([
    "success" => true,
    "message" => "Login successful.",
    "user" => [
        "user_id" => $user["user_id"],
        "first_name" => $user["first_name"],
        "last_name" => $user["last_name"],
        "email" => $user["email"],
        "phone_number" => $user["phone_number"],
        "role_name" => $user["role_name"],
        "admin_id" => $user["admin_id"] ? (int) $user["admin_id"] : null,
        "facility_id" => $user["facility_id"] ? (int) $user["facility_id"] : null
    ]
]);

$stmt->close();
$conn->close();
