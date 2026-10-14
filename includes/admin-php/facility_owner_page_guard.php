<?php
if ((int) ($_SESSION["role_id"] ?? 0) === 3) {
    $ownerUserId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
    if (!$ownerUserId || $ownerUserId < 1) {
        header("Location: ../signin.php");
        exit();
    }

    $ownerStatusStmt = $conn->prepare("SELECT status FROM admins WHERE user_id = ? LIMIT 1");
    $ownerStatusStmt->bind_param("i", $ownerUserId);
    $ownerStatusStmt->execute();
    $ownerStatus = $ownerStatusStmt->get_result()->fetch_assoc();
    $ownerStatusStmt->close();

    if ($ownerStatus && in_array($ownerStatus["status"], ["Pending", "Rejected"], true)) {
        header("Location: ../owner_application.php");
        exit();
    }
    if (!$ownerStatus || $ownerStatus["status"] !== "Active") {
        header("Location: ../signin.php");
        exit();
    }
}
