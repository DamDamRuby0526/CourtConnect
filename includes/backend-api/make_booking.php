<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Sign in with a player account to book a court."]);
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$scheduleId = filter_var(is_array($data) ? ($data["schedule_id"] ?? null) : ($_POST["schedule_id"] ?? null), FILTER_VALIDATE_INT);
if (!$scheduleId || $scheduleId < 1) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid time slot."]);
    exit();
}

try {
    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start your booking.");
    }

    $slotStmt = $conn->prepare(
        "SELECT cs.schedule_id, cs.court_id, cs.court_date, cs.court_time,
                cs.schedule_status, cs.hold_expires_at,
                (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW()) AS hold_expired,
                cd.court_rate, cd.court_status,
                (TIMESTAMP(cs.court_date, cs.court_time) > NOW()
                    AND TIMESTAMP(cs.court_date, cs.court_time) <= DATE_ADD(NOW(), INTERVAL 30 DAY)) AS within_booking_window
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         INNER JOIN facilities f ON f.facility_id = cd.facility_id
         WHERE cs.schedule_id = ?
             AND NOT EXISTS (
                 SELECT 1 FROM admins a
                 WHERE a.facility_id = f.facility_id AND a.status <> 'Active'
             )
         FOR UPDATE"
    );
    $slotStmt->bind_param("i", $scheduleId);
    $slotStmt->execute();
    $slot = $slotStmt->get_result()->fetch_assoc();
    $slotStmt->close();

    if ($slot) {
        $existingBookingStmt = $conn->prepare(
            "SELECT b.booking_id
             FROM bookings b
             WHERE b.user_id = ? AND b.schedule_id = ? AND b.booking_type = 'online'
                 AND b.status = 'pending_payment' AND b.payment_status = 'unpaid'
                 AND b.hold_expires_at > NOW()
                 AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.booking_id)
             ORDER BY b.booking_id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $existingBookingStmt->bind_param("ii", $userId, $scheduleId);
        $existingBookingStmt->execute();
        $existingBooking = $existingBookingStmt->get_result()->fetch_assoc();
        $existingBookingStmt->close();

        if ($existingBooking) {
            if (!$conn->commit()) {
                throw new RuntimeException("Unable to reopen your booking.");
            }
            echo json_encode([
                "success" => true,
                "booking_id" => (int) $existingBooking["booking_id"],
                "reused" => true,
            ]);
            $conn->close();
            exit();
        }
    }

    if (!$slot || $slot["court_status"] !== "Available" || !(int) $slot["within_booking_window"]) {
        throw new RuntimeException("That slot was just taken. Choose another time.");
    }

    if ($slot["schedule_status"] === "Held" && (int) $slot["hold_expired"] === 1) {
        $releaseExpiredHoldStmt = $conn->prepare(
            "UPDATE court_schedule SET schedule_status = 'Available', hold_expires_at = NULL
             WHERE schedule_id = ? AND schedule_status = 'Held'
                 AND (hold_expires_at IS NULL OR hold_expires_at <= NOW())"
        );
        $releaseExpiredHoldStmt->bind_param("i", $scheduleId);
        $releaseExpiredHoldStmt->execute();
        $releasedExpiredHold = $releaseExpiredHoldStmt->affected_rows === 1;
        $releaseExpiredHoldStmt->close();
        if (!$releasedExpiredHold) {
            throw new RuntimeException("That slot was just taken. Refresh and choose it again.");
        }
        $slot["schedule_status"] = "Available";
    }
    if ($slot["schedule_status"] !== "Available") {
        throw new RuntimeException("That slot was just taken. Choose another time.");
    }

    $holdStmt = $conn->prepare(
        "UPDATE court_schedule
         SET schedule_status = 'Held', hold_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
         WHERE schedule_id = ? AND schedule_status = 'Available'"
    );
    $holdStmt->bind_param("i", $scheduleId);
    $holdStmt->execute();
    if ($holdStmt->affected_rows !== 1) {
        $holdStmt->close();
        throw new RuntimeException("That slot was just taken. Choose another time.");
    }
    $holdStmt->close();

    $courtId = (int) $slot["court_id"];
    $totalAmount = (int) $slot["court_rate"];
    $bookingStmt = $conn->prepare(
        "INSERT INTO bookings
            (user_id, booking_type, customer_name, customer_phone, court_id, schedule_id, total_amount, status, payment_status, hold_expires_at)
         SELECT u.user_id, 'online',
                CONCAT_WS(' ', NULLIF(u.first_name, ''), NULLIF(u.last_name, '')),
                u.phone_number, ?, ?, ?, 'pending_payment', 'unpaid', DATE_ADD(NOW(), INTERVAL 10 MINUTE)
         FROM users u
         WHERE u.user_id = ?"
    );
    $bookingStmt->bind_param("iiii", $courtId, $scheduleId, $totalAmount, $userId);
    $bookingStmt->execute();
    if ($bookingStmt->affected_rows !== 1) {
        $bookingStmt->close();
        throw new RuntimeException("Your account details could not be loaded. Please sign in again.");
    }
    $bookingId = $bookingStmt->insert_id;
    $bookingStmt->close();

    if (!$conn->commit()) {
        throw new RuntimeException("Unable to save your booking.");
    }
    echo json_encode(["success" => true, "booking_id" => $bookingId]);
} catch (RuntimeException $error) {
    $conn->rollback();
    if (str_contains($error->getMessage(), "just taken")) {
        expireUnpaidBookings($conn);
    }
    http_response_code(str_contains($error->getMessage(), "just taken") ? 409 : 500);
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    error_log("Player booking hold failed: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to reserve this time. Please try again."]);
}
$conn->close();