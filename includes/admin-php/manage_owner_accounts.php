<?php
header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";

$facilityId = (int) $_SESSION["facility_id"];
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $stmt = $conn->prepare(
        "SELECT a.admin_id, u.first_name, u.last_name, u.email, u.phone_number, a.status
         FROM admins a
         INNER JOIN users u ON u.user_id = a.user_id
         WHERE a.facility_id = ? AND u.role_id = 2
         ORDER BY u.first_name, u.last_name"
    );
    $stmt->bind_param("i", $facilityId);
    $stmt->execute();
    $result = $stmt->get_result();
    $accounts = [];
    while ($account = $result->fetch_assoc()) {
        $accounts[] = $account;
    }
    $stmt->close();
    echo json_encode(["success" => true, "accounts" => $accounts]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$action = $data["action"] ?? "";

if ($action === "set_status") {
    $adminId = filter_var($data["admin_id"] ?? null, FILTER_VALIDATE_INT);
    $status = $data["status"] ?? "";
    if (!$adminId || !in_array($status, ["Active", "Inactive"], true)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Choose a valid account status."]);
        exit();
    }
    $stmt = $conn->prepare(
        "UPDATE admins a
         INNER JOIN users u ON u.user_id = a.user_id
         SET a.status = ?
         WHERE a.admin_id = ? AND a.facility_id = ? AND u.role_id = 2"
    );
    $stmt->bind_param("sii", $status, $adminId, $facilityId);
    $stmt->execute();
    $updated = $stmt->affected_rows === 1;
    $stmt->close();
    echo json_encode([
        "success" => $updated,
        "message" => $updated ? "Account status updated." : "Admin account was not found or already has that status."
    ]);
    exit();
}

if (!in_array($action, ["create", "update"], true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Unknown account action."]);
    exit();
}

$adminId = filter_var($data["admin_id"] ?? null, FILTER_VALIDATE_INT);
$firstName = trim($data["first_name"] ?? "");
$lastName = trim($data["last_name"] ?? "");
$email = trim($data["email"] ?? "");
$phoneNumber = trim($data["phone_number"] ?? "");
$password = (string) ($data["password"] ?? "");
if ($firstName === "" || $lastName === "" || $email === "" || $phoneNumber === "") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Complete all required fields."]);
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^09\d{9}$/', $phoneNumber)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a valid email and 11-digit phone number starting with 09."]);
    exit();
}
if (($action === "create" && strlen($password) < 8) || ($action === "update" && $password !== "" && strlen($password) < 8)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Password must be at least 8 characters."]);
    exit();
}
if ($action === "update" && !$adminId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid admin account."]);
    exit();
}

$userId = null;
if ($action === "update") {
    $lookupStmt = $conn->prepare(
        "SELECT u.user_id
         FROM admins a
         INNER JOIN users u ON u.user_id = a.user_id
         WHERE a.admin_id = ? AND a.facility_id = ? AND u.role_id = 2
         LIMIT 1"
    );
    $lookupStmt->bind_param("ii", $adminId, $facilityId);
    $lookupStmt->execute();
    $userId = $lookupStmt->get_result()->fetch_assoc()["user_id"] ?? null;
    $lookupStmt->close();
    if (!$userId) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Admin account was not found for this facility."]);
        exit();
    }
}

if ($action === "create") {
    $duplicateStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR phone_number = ? LIMIT 1");
    $duplicateStmt->bind_param("ss", $email, $phoneNumber);
} else {
    $duplicateStmt = $conn->prepare("SELECT user_id FROM users WHERE (email = ? OR phone_number = ?) AND user_id != ? LIMIT 1");
    $duplicateStmt->bind_param("ssi", $email, $phoneNumber, $userId);
}
$duplicateStmt->execute();
$duplicate = $duplicateStmt->get_result()->fetch_assoc();
$duplicateStmt->close();
if ($duplicate) {
    http_response_code(409);
    echo json_encode(["success" => false, "message" => "That email or phone number is already registered."]);
    exit();
}

try {
    $conn->begin_transaction();
    if ($action === "create") {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $roleId = 2;
        $verified = 1;
        $userStmt = $conn->prepare(
            "INSERT INTO users (first_name, last_name, email, phone_number, password, role_id, is_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $userStmt->bind_param("sssssii", $firstName, $lastName, $email, $phoneNumber, $passwordHash, $roleId, $verified);
        $userStmt->execute();
        $userId = $userStmt->insert_id;
        $userStmt->close();

        $status = "Active";
        $adminStmt = $conn->prepare(
            "INSERT INTO admins (user_id, facility_id, first_name, last_name, status)
             VALUES (?, ?, ?, ?, ?)"
        );
        $adminStmt->bind_param("iisss", $userId, $facilityId, $firstName, $lastName, $status);
        $adminStmt->execute();
        $adminStmt->close();
        $message = "Admin account created.";
    } else {
        if ($password !== "") {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $userStmt = $conn->prepare(
                "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, password = ? WHERE user_id = ?"
            );
            $userStmt->bind_param("sssssi", $firstName, $lastName, $email, $phoneNumber, $passwordHash, $userId);
        } else {
            $userStmt = $conn->prepare(
                "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ? WHERE user_id = ?"
            );
            $userStmt->bind_param("ssssi", $firstName, $lastName, $email, $phoneNumber, $userId);
        }
        $userStmt->execute();
        $userStmt->close();
        $adminStmt = $conn->prepare("UPDATE admins SET first_name = ?, last_name = ? WHERE admin_id = ? AND facility_id = ?");
        $adminStmt->bind_param("ssii", $firstName, $lastName, $adminId, $facilityId);
        $adminStmt->execute();
        $adminStmt->close();
        $message = "Admin account updated.";
    }
    $conn->commit();
    echo json_encode(["success" => true, "message" => $message]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    error_log("Owner admin account error: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to save the admin account."]);
}