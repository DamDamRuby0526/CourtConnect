<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/send_email.php";

function passwordResetRequestRespond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

function passwordResetGenericResponse(): void
{
    passwordResetRequestRespond(200, [
        "success" => true,
        "message" => "If an account exists for that email, we’ll send a password reset link shortly. Check your inbox and spam folder."
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    passwordResetRequestRespond(405, ["success" => false, "message" => "Invalid request method."]);
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data) || !is_string($data["email"] ?? null)) {
    passwordResetRequestRespond(400, ["success" => false, "message" => "Enter an email address to continue."]);
}

$email = trim($data["email"]);
$normalizedEmail = strtolower($email);
$remoteAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";
$requesterHash = hash("sha256", is_string($remoteAddress) ? $remoteAddress : "unknown");
$recipientHash = $email !== "" ? hash("sha256", $normalizedEmail) : null;

try {
    $conn->query(
        "DELETE FROM password_reset_tokens
         WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
            OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY))"
    );
    $conn->query("DELETE FROM password_reset_requests WHERE requested_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        passwordResetGenericResponse();
    }

    $ipLimitStmt = $conn->prepare(
        "SELECT COUNT(*) AS request_count
         FROM password_reset_requests
         WHERE requester_hash = ? AND requested_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $ipLimitStmt->bind_param("s", $requesterHash);
    $ipLimitStmt->execute();
    $ipLimit = (int) $ipLimitStmt->get_result()->fetch_assoc()["request_count"];
    $ipLimitStmt->close();

    $emailLimit = 0;
    if ($recipientHash !== null) {
        $emailLimitStmt = $conn->prepare(
            "SELECT COUNT(*) AS request_count
             FROM password_reset_requests
             WHERE recipient_hash = ? AND requested_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $emailLimitStmt->bind_param("s", $recipientHash);
        $emailLimitStmt->execute();
        $emailLimit = (int) $emailLimitStmt->get_result()->fetch_assoc()["request_count"];
        $emailLimitStmt->close();
    }

    if ($ipLimit >= 5 || $emailLimit >= 3) {
        passwordResetGenericResponse();
    }

    $rateStmt = $conn->prepare(
        "INSERT INTO password_reset_requests (requester_hash, recipient_hash) VALUES (?, ?)"
    );
    $rateStmt->bind_param("ss", $requesterHash, $recipientHash);
    $rateStmt->execute();
    $rateStmt->close();

    $userStmt = $conn->prepare(
        "SELECT user_id, first_name, last_name, email FROM users WHERE email = ? LIMIT 1"
    );
    $userStmt->bind_param("s", $email);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        passwordResetGenericResponse();
    }

    try {
        $baseUrl = courtConnectPublicBaseUrl();
    } catch (Throwable $error) {
        error_log("Password reset URL configuration error: " . $error->getMessage());
        passwordResetGenericResponse();
    }
    if (!courtConnectEmailConfigured()) {
        error_log("Password reset email delivery is not configured.");
        passwordResetGenericResponse();
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash("sha256", $token);
    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start password reset transaction.");
    }

    $invalidateStmt = $conn->prepare(
        "UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL"
    );
    $userId = (int) $user["user_id"];
    $invalidateStmt->bind_param("i", $userId);
    $invalidateStmt->execute();
    $invalidateStmt->close();

    $insertStmt = $conn->prepare(
        "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
    );
    $insertStmt->bind_param("is", $userId, $tokenHash);
    $insertStmt->execute();
    $insertStmt->close();
    if (!$conn->commit()) {
        throw new RuntimeException("Unable to commit password reset token.");
    }

    $emailResult = sendPasswordResetEmail(
        $user["email"],
        trim($user["first_name"] . " " . $user["last_name"]),
        $baseUrl . "/reset_password.php?token=" . rawurlencode($token)
    );

    if (!$emailResult["success"]) {
        $disableTokenStmt = $conn->prepare(
            "UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE token_hash = ? AND used_at IS NULL"
        );
        $disableTokenStmt->bind_param("s", $tokenHash);
        $disableTokenStmt->execute();
        $disableTokenStmt->close();
        error_log("Password reset email could not be delivered: " . $emailResult["message"]);
    }

    passwordResetGenericResponse();
} catch (Throwable $error) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log("Password reset request failed: " . $error->getMessage());
    passwordResetRequestRespond(500, [
        "success" => false,
        "message" => "We couldn’t process your request right now. Please try again later."
    ]);
}
