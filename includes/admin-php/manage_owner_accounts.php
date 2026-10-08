<?php
require_once __DIR__ . "/facility_owner_guard.php";

$facilityId = (int) $_SESSION["facility_id"];
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
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

if ($method !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$action = $data["action"] ?? "";

if ($action === "create") {
    $firstName = trim($data["first_name"] ?? "");
    $lastName = trim($data["last_name"] ?? "");
    $email = trim($data["email"] ?? "");
    $phoneNumber = trim($data["phone_number"] ?? "");
    $password = $data["password"] ?? "";

    if ($firstName === "" || $lastName === "" || $email === "" || $phoneNumber === "" || $password === "") {
        echo json_encode(["success" => false, "message" => "Complete all fields to add an admin account."]);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Enter a valid email address."]);
        exit();
    }
    if (!preg_match('/^09\d{9}$/', $phoneNumber)) {
        echo json_encode(["success" => false, "message" => "Enter a valid 11-digit phone number starting with 09."]);
        exit();
    }
    if (strlen($password) < 8) {
        echo json_encode(["success" => false, "message" => "Password must be at least 8 characters."]);
        exit();
    }

    $duplicateStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR phone_number = ? LIMIT 1");
    $duplicateStmt->bind_param("ss", $email, $phoneNumber);
    $duplicateStmt->execute();
    $duplicate = $duplicateStmt->get_result()->fetch_assoc();
    $duplicateStmt->close();
    if ($duplicate) {
        echo json_encode(["success" => false, "message" => "That email or phone number is already registered."]);
        exit();
    }

    try {
        $conn->begin_transaction();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $roleId = 2;
        $stmt = $conn->prepare(
            "INSERT INTO users (first_name, last_name, email, phone_number, password, role_id, is_verified)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->bind_param("sssssi", $firstName, $lastName, $email, $phoneNumber, $hashedPassword, $roleId);
        $stmt->execute();
        $userId = $stmt->insert_id;
        $stmt->close();

        $status = "Active";
        $stmt = $conn->prepare(
            "INSERT INTO admins (user_id, facility_id, first_name, last_name, status)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iisss", $userId, $facilityId, $firstName, $lastName, $status);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Admin account added."]);
    } catch (Throwable $error) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Unable to add the admin account."]);
    }
    exit();
}

if ($action === "update") {
    $adminId = filter_var($data["admin_id"] ?? null, FILTER_VALIDATE_INT);
    $firstName = trim($data["first_name"] ?? "");
    $lastName = trim($data["last_name"] ?? "");
    $email = trim($data["email"] ?? "");
    $phoneNumber = trim($data["phone_number"] ?? "");
    $password = $data["password"] ?? "";

    if (!$adminId || $firstName === "" || $lastName === "" || $email === "" || $phoneNumber === "") {
        echo json_encode(["success" => false, "message" => "Complete all required fields to update the admin account."]);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Enter a valid email address."]);
        exit();
    }
    if (!preg_match('/^09\d{9}$/', $phoneNumber)) {
        echo json_encode(["success" => false, "message" => "Enter a valid 11-digit phone number starting with 09."]);
        exit();
    }
    if ($password !== "" && strlen($password) < 8) {
        echo json_encode(["success" => false, "message" => "Password must be at least 8 characters."]);
        exit();
    }

    $accountStmt = $conn->prepare(
        "SELECT u.user_id
         FROM admins a
         INNER JOIN users u ON u.user_id = a.user_id
         WHERE a.admin_id = ? AND a.facility_id = ? AND u.role_id = 2
         LIMIT 1"
    );
    $accountStmt->bind_param("ii", $adminId, $facilityId);
    $accountStmt->execute();
    $account = $accountStmt->get_result()->fetch_assoc();
    $accountStmt->close();
    if (!$account) {
        echo json_encode(["success" => false, "message" => "Admin account was not found for this facility."]);
        exit();
    }

    $userId = (int) $account["user_id"];
    $duplicateStmt = $conn->prepare(
        "SELECT user_id FROM users WHERE (email = ? OR phone_number = ?) AND user_id != ? LIMIT 1"
    );
    $duplicateStmt->bind_param("ssi", $email, $phoneNumber, $userId);
    $duplicateStmt->execute();
    $duplicate = $duplicateStmt->get_result()->fetch_assoc();
    $duplicateStmt->close();
    if ($duplicate) {
        echo json_encode(["success" => false, "message" => "That email or phone number is already registered."]);
        exit();
    }

    try {
        $conn->begin_transaction();
        if ($password !== "") {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userStmt = $conn->prepare(
                "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, password = ? WHERE user_id = ?"
            );
            $userStmt->bind_param("sssssi", $firstName, $lastName, $email, $phoneNumber, $hashedPassword, $userId);
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
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Admin account updated."]);
    } catch (Throwable $error) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Unable to update the admin account."]);
    }
    exit();
}

if ($action === "set_status") {
    $adminId = filter_var($data["admin_id"] ?? null, FILTER_VALIDATE_INT);
    $status = $data["status"] ?? "";
    if (!$adminId || !in_array($status, ["Active", "Inactive"], true)) {
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
    $updated = $stmt->affected_rows > 0;
    $stmt->close();
    echo json_encode([
        "success" => $updated,
        "message" => $updated ? "Account status updated." : "Admin account was not found or already has that status."
    ]);
    exit();
}

echo json_encode(["success" => false, "message" => "Unknown account action."]);