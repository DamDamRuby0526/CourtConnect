<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

function passwordResetRespond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    passwordResetRespond(405, ["success" => false, "message" => "Invalid request method."]);
}

$data = json_decode(file_get_contents("php://input"), true);
$token = is_array($data) && is_string($data["token"] ?? null) ? $data["token"] : "";
$password = is_array($data) && is_string($data["password"] ?? null) ? trim($data["password"]) : "";
$confirmation = is_array($data) && is_string($data["confirm_password"] ?? null)
    ? trim($data["confirm_password"])
    : "";

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    passwordResetRespond(400, ["success" => false, "code" => "invalid_token", "message" => "This reset link is invalid or has expired. Request a new link to continue."]);
}
if (strlen($password) < 8 || strlen($password) > 1024) {
    passwordResetRespond(400, ["success" => false, "code" => "invalid_password", "field" => "password", "message" => "Use a password between 8 and 1024 characters."]);
}
if ($password !== $confirmation) {
    passwordResetRespond(400, ["success" => false, "code" => "password_mismatch", "field" => "confirm_password", "message" => "Passwords do not match."]);
}

$tokenHash = hash("sha256", $token);
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if (!is_string($passwordHash)) {
    passwordResetRespond(500, ["success" => false, "message" => "Unable to update your password. Please try again."]);
}

try {
    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start password update transaction.");
    }

    $lookupStmt = $conn->prepare("SELECT user_id FROM password_reset_tokens WHERE token_hash = ? LIMIT 1");
    $lookupStmt->bind_param("s", $tokenHash);
    $lookupStmt->execute();
    $tokenRecord = $lookupStmt->get_result()->fetch_assoc();
    $lookupStmt->close();
    if (!$tokenRecord) {
        $conn->rollback();
        passwordResetRespond(400, ["success" => false, "code" => "invalid_token", "message" => "This reset link is invalid or has expired. Request a new link to continue."]);
    }

    $userId = (int) $tokenRecord["user_id"];
    $userLockStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? FOR UPDATE");
    $userLockStmt->bind_param("i", $userId);
    $userLockStmt->execute();
    $userExists = (bool) $userLockStmt->get_result()->fetch_assoc();
    $userLockStmt->close();
    if (!$userExists) {
        $conn->rollback();
        passwordResetRespond(400, ["success" => false, "code" => "invalid_token", "message" => "This reset link is invalid or has expired. Request a new link to continue."]);
    }

    $validTokenStmt = $conn->prepare(
        "SELECT reset_id FROM password_reset_tokens
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1 FOR UPDATE"
    );
    $validTokenStmt->bind_param("s", $tokenHash);
    $validTokenStmt->execute();
    $validToken = (bool) $validTokenStmt->get_result()->fetch_assoc();
    $validTokenStmt->close();
    if (!$validToken) {
        $conn->rollback();
        passwordResetRespond(400, ["success" => false, "code" => "invalid_token", "message" => "This reset link is invalid or has expired. Request a new link to continue."]);
    }

    $updatePasswordStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $updatePasswordStmt->bind_param("si", $passwordHash, $userId);
    $updatePasswordStmt->execute();
    $updatePasswordStmt->close();

    $invalidateTokensStmt = $conn->prepare(
        "UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL"
    );
    $invalidateTokensStmt->bind_param("i", $userId);
    $invalidateTokensStmt->execute();
    $invalidateTokensStmt->close();

    if (!$conn->commit()) {
        throw new RuntimeException("Unable to commit password update.");
    }

    passwordResetRespond(200, ["success" => true, "message" => "Your password has been updated. You can now sign in with your new password."]);
} catch (Throwable $error) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log("Password reset failed: " . $error->getMessage());
    passwordResetRespond(500, ["success" => false, "message" => "We couldn’t update your password right now. Please try again."]);
}
