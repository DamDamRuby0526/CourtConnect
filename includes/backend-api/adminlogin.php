<?php

header("Content-Type: application/json");
session_start();
require "../backend-api/config.php";

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
        "message" => "Email, password are required."
    ]);
    exit();
}

// Join admins & users, require the facility_id to match
// so an admin can only log in against a facility they're assigned to
$sql = "SELECT a.admin_id, a.user_id, a.facility_id, a.status, a.rejection_reason,
           u.first_name, u.last_name, u.email, u.password, u.is_verified, u.role_id
        FROM admins a
        JOIN users u ON u.user_id = a.user_id
    WHERE u.email = ? AND u.role_id IN (2, 3)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Unable to process the request."
    ]);
    exit();
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email, or password."
    ]);
    exit();
}

$admin = $result->fetch_assoc();
$stmt->close();

if (!password_verify($password, $admin["password"])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password."
    ]);
    exit();
}

if ($admin["status"] === "Pending") {
    $_SESSION["pending_facility_user_id"] = (int) $admin["user_id"];
    echo json_encode([
        "success" => false,
        "message" => "Your facility application is waiting for developer review.",
        "requires_review" => true
    ]);
    exit();
}

if ($admin["status"] !== "Active") {
    $_SESSION["pending_facility_user_id"] = (int) $admin["user_id"];
    echo json_encode([
        "success" => false,
        "message" => $admin["rejection_reason"] ?: "Your facility application was not approved.",
        "requires_review" => true
    ]);
    exit();
}

if ((int) $admin["is_verified"] === 0) {
    $_SESSION["pending_facility_user_id"] = (int) $admin["user_id"];
    echo json_encode([
        "success" => false,
        "message" => "Your application is approved. Verify your email to continue.",
        "requires_verification" => true,
        "user_id" => (int) $admin["user_id"]
    ]);
    exit();
}

session_regenerate_id(true);

$_SESSION["admin_id"] = $admin["admin_id"];
$_SESSION["user_id"] = $admin["user_id"];
$_SESSION["facility_id"] = $admin["facility_id"];
$_SESSION["logged_in"] = true;
$_SESSION["role_id"] = (int) $admin["role_id"];
$_SESSION["role"] = "admin";
$_SESSION["first_name"] = $admin["first_name"];

echo json_encode([
    "success" => true,
    "message" => "Login successful.",
    "user" => [
        "admin_id" => $admin["admin_id"],
        "facility_id" => $admin["facility_id"],
        "first_name" => $admin["first_name"],
        "last_name" => $admin["last_name"],
        "email" => $admin["email"]
    ]
]);

$conn->close();