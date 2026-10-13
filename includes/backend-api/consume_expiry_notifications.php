<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Sign in with a player account to check booking notices."]);
    exit();
}

try {
    $conn->begin_transaction();
    $noticeStmt = $conn->prepare(
        "SELECT notification_id, court_no, court_time
         FROM booking_expiry_notifications
         WHERE user_id = ? AND consumed_at IS NULL
         ORDER BY notification_id ASC
         FOR UPDATE"
    );
    $noticeStmt->bind_param("i", $userId);
    $noticeStmt->execute();
    $result = $noticeStmt->get_result();
    $messages = [];
    $notificationIds = [];
    while ($notice = $result->fetch_assoc()) {
        $notificationIds[] = (int) $notice["notification_id"];
        $messages[] = "Your booking for Court " . $notice["court_no"] . " at "
            . date("g:i A", strtotime($notice["court_time"]))
            . " expired because payment wasn't completed.";
    }
    $noticeStmt->close();

    if ($notificationIds) {
        $markStmt = $conn->prepare(
            "UPDATE booking_expiry_notifications
             SET consumed_at = NOW()
             WHERE notification_id = ? AND user_id = ? AND consumed_at IS NULL"
+        );
+        foreach ($notificationIds as $notificationId) {
+            $markStmt->bind_param("ii", $notificationId, $userId);
+            $markStmt->execute();
+        }
+        $markStmt->close();
+    }
+    $conn->commit();
+    echo json_encode(["success" => true, "messages" => $messages]);
+} catch (Throwable $error) {
+    $conn->rollback();
+    http_response_code(500);
+    error_log("Expiry notification retrieval failed: " . $error->getMessage());
+    echo json_encode(["success" => false, "message" => "Unable to load booking notices."]);
+}
+$conn->close();
