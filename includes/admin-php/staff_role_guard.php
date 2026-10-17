<?php

function requireStaffRole(array $allowedRoleIds, bool $apiRequest): void
{
    global $conn;
    require_once __DIR__ . "/../backend-api/config.php";

    $deny = static function (int $status, string $message) use ($apiRequest): void {
        http_response_code($status);
        if ($apiRequest) {
            header("Content-Type: application/json");
            echo json_encode(["success" => false, "message" => $message]);
        } else {
            echo htmlspecialchars($message, ENT_QUOTES, "UTF-8");
        }
        exit();
    };

    $userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
    if (!$userId || empty($_SESSION["logged_in"])) {
        if (!$apiRequest) {
            header("Location: ../signin.php");
            exit();
        }
        $deny(401, "Staff sign-in is required.");
    }

    $stmt = $conn->prepare(
        "SELECT u.role_id, u.is_verified, r.role_name
         FROM users u
         INNER JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        error_log("Staff authorization query prepare failed: " . $conn->error);
        $deny(500, "Unable to verify staff access.");
    }
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        error_log("Staff authorization query failed: " . $stmt->error);
        $stmt->close();
        $deny(500, "Unable to verify staff access.");
    }
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $roleId = (int) ($user["role_id"] ?? 0);
    if (!$user || (int) $user["is_verified"] !== 1 || !in_array($roleId, $allowedRoleIds, true)) {
        $deny(403, "You do not have permission to access this resource.");
    }

    $_SESSION["role_id"] = $roleId;
    $_SESSION["role_name"] = $user["role_name"];
}
