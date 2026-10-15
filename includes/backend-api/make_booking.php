<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$submittedIds = is_array($data) ? ($data["schedule_ids"] ?? null) : null;
if (!is_array($submittedIds) && is_array($data) && isset($data["schedule_id"])) {
    $submittedIds = [$data["schedule_id"]];
}
$scheduleIds = [];
if (is_array($submittedIds)) {
    foreach ($submittedIds as $submittedId) {
        $scheduleId = filter_var($submittedId, FILTER_VALIDATE_INT);
        if (!$scheduleId || $scheduleId < 1) {
            $scheduleIds = [];
            break;
        }
        $scheduleIds[] = (int) $scheduleId;
    }
}
$scheduleIds = array_values(array_unique($scheduleIds));
sort($scheduleIds);
if (!$scheduleIds || count($scheduleIds) > 8) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose between one and eight consecutive time slots."]);
    exit();
}

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$isLoggedIn = !empty($_SESSION["logged_in"]);
$isPlayer = $isLoggedIn && $userId && (int) ($_SESSION["role_id"] ?? 0) === 1;
$schedulePlaceholders = implode(",", array_fill(0, count($scheduleIds), "?"));
$scheduleTypes = str_repeat("i", count($scheduleIds));

function bookingFetchSlots(mysqli $conn, array $scheduleIds, bool $lockRows = false): array
{
    $placeholders = implode(",", array_fill(0, count($scheduleIds), "?"));
    $types = str_repeat("i", count($scheduleIds));
    $sql = "SELECT cs.schedule_id, cs.court_id, cs.court_date, cs.court_time,
                   cs.schedule_status, cs.hold_expires_at,
                   (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW()) AS hold_expired,
                   cd.court_no, cd.court_rate, cd.slot_duration, cd.court_status,
                   f.facility_id, f.facility_name,
                   (TIMESTAMP(cs.court_date, cs.court_time) > NOW()
                       AND TIMESTAMP(cs.court_date, cs.court_time) <= DATE_ADD(NOW(), INTERVAL 30 DAY)) AS within_booking_window,
                   NOT EXISTS (
                       SELECT 1 FROM admins a
                       WHERE a.facility_id = f.facility_id AND a.status <> 'Active'
                   ) AS facility_active
            FROM court_schedule cs
            INNER JOIN court_details cd ON cd.court_id = cs.court_id
            INNER JOIN facilities f ON f.facility_id = cd.facility_id
            WHERE cs.schedule_id IN ($placeholders)
            ORDER BY cs.court_time ASC" . ($lockRows ? " FOR UPDATE" : "");
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$scheduleIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $slots[] = $row;
    }
    $stmt->close();
    return $slots;
}

function bookingSlotsAreConsecutive(array $slots, array $scheduleIds): bool
{
    if (count($slots) !== count($scheduleIds) || !$slots) {
        return false;
    }
    $first = $slots[0];
    if ($first["court_status"] !== "Available" || (int) $first["facility_active"] !== 1) {
        return false;
    }
    foreach ($slots as $index => $slot) {
        if ($slot["court_id"] !== $first["court_id"]
            || $slot["court_date"] !== $first["court_date"]
            || $slot["slot_duration"] !== $first["slot_duration"]
            || $slot["court_status"] !== "Available"
            || (int) $slot["facility_active"] !== 1
            || !(int) $slot["within_booking_window"]) {
            return false;
        }
        if ($index > 0) {
            $previousStart = strtotime($slots[$index - 1]["court_date"] . " " . $slots[$index - 1]["court_time"]);
            $currentStart = strtotime($slot["court_date"] . " " . $slot["court_time"]);
            if ($currentStart !== $previousStart + (int) $first["slot_duration"] * 60) {
                return false;
            }
        }
    }
    return true;
}

function bookingRangeHasConflict(mysqli $conn, array $firstSlot, array $lastSlot): bool
{
    $courtId = (int) $firstSlot["court_id"];
    $courtDate = $firstSlot["court_date"];
    $startTime = $firstSlot["court_time"];
    $endTime = (new DateTimeImmutable($lastSlot["court_date"] . " " . $lastSlot["court_time"]))
        ->modify("+" . (int) $firstSlot["slot_duration"] . " minutes")
        ->format("H:i:s");
    $stmt = $conn->prepare(
        "SELECT b.booking_id
         FROM bookings b
         INNER JOIN court_schedule start_schedule ON start_schedule.schedule_id = b.schedule_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         WHERE b.court_id = ? AND start_schedule.court_date = ?
             AND b.status IN ('pending_payment', 'pending', 'confirmed')
             AND (b.status = 'confirmed' OR b.hold_expires_at > NOW())
             AND COALESCE(b.start_time, start_schedule.court_time) < ?
             AND COALESCE(b.end_time, ADDTIME(start_schedule.court_time, SEC_TO_TIME(cd.slot_duration * 60))) > ?
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bind_param("isss", $courtId, $courtDate, $endTime, $startTime);
    $stmt->execute();
    $conflict = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool) $conflict;
}

if (!$isPlayer) {
    $pendingSlots = bookingFetchSlots($conn, $scheduleIds);
    if (!bookingSlotsAreConsecutive($pendingSlots, $scheduleIds)) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Those consecutive times are no longer available. Choose another time.",
            "slot_unavailable" => true
        ]);
        exit();
    }
    if (bookingRangeHasConflict($conn, $pendingSlots[0], $pendingSlots[count($pendingSlots) - 1])) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Those consecutive times are no longer available. Choose another time.",
            "slot_unavailable" => true
        ]);
        exit();
    }
    foreach ($pendingSlots as $slot) {
        if (!in_array($slot["schedule_status"], ["Available", "Held"], true)
            || ($slot["schedule_status"] === "Held" && !(int) $slot["hold_expired"])) {
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "message" => "Those consecutive times are no longer available. Choose another time.",
                "slot_unavailable" => true
            ]);
            exit();
        }
    }
    $_SESSION["pending_reservation"] = ["schedule_ids" => $scheduleIds];
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "requires_auth" => true,
        "message" => "Create an account or log in to continue with your court reservation."
    ]);
    exit();
}

try {
    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start your booking.");
    }

    $accountStmt = $conn->prepare("SELECT role_id, is_verified FROM users WHERE user_id = ? FOR UPDATE");
    $accountStmt->bind_param("i", $userId);
    $accountStmt->execute();
    $account = $accountStmt->get_result()->fetch_assoc();
    $accountStmt->close();
    if (!$account || (int) $account["role_id"] !== 1 || (int) $account["is_verified"] !== 1) {
        throw new RuntimeException("A verified customer account is required to reserve a court.");
    }

    $slots = bookingFetchSlots($conn, $scheduleIds, true);
    if (!bookingSlotsAreConsecutive($slots, $scheduleIds)) {
        throw new RuntimeException("Those consecutive times are no longer available. Choose another time.");
    }

    $slotDuration = (int) $slots[0]["slot_duration"];
    $startTime = $slots[0]["court_time"];
    $lastSlot = $slots[count($slots) - 1];
    $endTime = (new DateTimeImmutable($lastSlot["court_date"] . " " . $lastSlot["court_time"]))
        ->modify("+{$slotDuration} minutes")
        ->format("H:i:s");
    $totalDuration = $slotDuration * count($slots);

    $existingSingleStmt = $conn->prepare(
        "SELECT b.booking_id
         FROM bookings b
         WHERE b.user_id = ? AND b.schedule_id = ? AND b.start_time = ? AND b.end_time = ?
             AND b.status = 'pending_payment' AND b.payment_status = 'unpaid'
             AND b.hold_expires_at > NOW()
             AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.booking_id)
         LIMIT 1 FOR UPDATE"
    );
    $firstScheduleId = (int) $slots[0]["schedule_id"];
    $existingSingleStmt->bind_param("iiss", $userId, $firstScheduleId, $startTime, $endTime);
    $existingSingleStmt->execute();
    $existingSingle = $existingSingleStmt->get_result()->fetch_assoc();
    $existingSingleStmt->close();
    if ($existingSingle) {
        if (!$conn->commit()) {
            throw new RuntimeException("Unable to reopen your reservation.");
        }
        unset($_SESSION["pending_reservation"], $_SESSION["reservation_notice"]);
        echo json_encode(["success" => true, "booking_id" => (int) $existingSingle["booking_id"], "reused" => true]);
        $conn->close();
        exit();
    }

    $existingStmt = $conn->prepare(
        "SELECT b.booking_group_id, b.booking_id, b.schedule_id
         FROM bookings b
         WHERE b.user_id = ? AND b.schedule_id IN ($schedulePlaceholders)
             AND b.booking_type = 'online' AND b.status = 'pending_payment'
             AND b.payment_status = 'unpaid' AND b.hold_expires_at > NOW()
             AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.booking_id = b.booking_id)
         ORDER BY b.schedule_id ASC
         FOR UPDATE"
    );
    $existingParams = array_merge([$userId], $scheduleIds);
    $existingTypes = "i" . $scheduleTypes;
    $existingStmt->bind_param($existingTypes, ...$existingParams);
    $existingStmt->execute();
    $existingResult = $existingStmt->get_result();
    $existingBookings = [];
    while ($existing = $existingResult->fetch_assoc()) {
        $existingBookings[] = $existing;
    }
    $existingStmt->close();

    if (count($existingBookings) === count($scheduleIds)) {
        $existingGroupId = (int) ($existingBookings[0]["booking_group_id"] ?? 0);
        $existingBookingIds = array_values(array_unique(array_map(static function ($booking) {
            return (int) $booking["booking_id"];
        }, $existingBookings)));
        $existingScheduleIds = array_map(static function ($booking) {
            return (int) $booking["schedule_id"];
        }, $existingBookings);
        sort($existingScheduleIds);
        if ($existingGroupId > 0 && count($existingBookingIds) === count($scheduleIds)
            && $existingScheduleIds === $scheduleIds) {
            if (!$conn->commit()) {
                throw new RuntimeException("Unable to reopen your reservation.");
            }
            unset($_SESSION["pending_reservation"], $_SESSION["reservation_notice"]);
            echo json_encode(["success" => true, "booking_group_id" => $existingGroupId, "reused" => true]);
            $conn->close();
            exit();
        }
        if (count($scheduleIds) === 1 && count($existingBookingIds) === 1
            && $existingScheduleIds === $scheduleIds) {
            if (!$conn->commit()) {
                throw new RuntimeException("Unable to reopen your booking.");
            }
            unset($_SESSION["pending_reservation"], $_SESSION["reservation_notice"]);
            echo json_encode(["success" => true, "booking_id" => (int) $existingBookings[0]["booking_id"], "reused" => true]);
            $conn->close();
            exit();
        }
    }

    if (bookingRangeHasConflict($conn, $slots[0], $slots[count($slots) - 1])) {
        throw new RuntimeException("Those consecutive times are no longer available. Choose another time.");
    }

    foreach ($slots as $slot) {
        if ($slot["schedule_status"] === "Held" && (int) $slot["hold_expired"] === 1) {
            $releaseStmt = $conn->prepare(
                "UPDATE court_schedule SET schedule_status = 'Available', hold_expires_at = NULL
                 WHERE schedule_id = ? AND schedule_status = 'Held'
                     AND (hold_expires_at IS NULL OR hold_expires_at <= NOW())"
            );
            $releaseStmt->bind_param("i", $slot["schedule_id"]);
            $releaseStmt->execute();
            $released = $releaseStmt->affected_rows === 1;
            $releaseStmt->close();
            if (!$released) {
                throw new RuntimeException("Those consecutive times are no longer available. Choose another time.");
            }
            $slot["schedule_status"] = "Available";
        }
        if ($slot["schedule_status"] !== "Available") {
            throw new RuntimeException("Those consecutive times are no longer available. Choose another time.");
        }
    }

    $totalAmount = 0;
    foreach ($slots as $slot) {
        $totalAmount += (int) $slot["court_rate"];
    }
    $holdStmt = $conn->prepare(
        "UPDATE court_schedule
         SET schedule_status = 'Held', hold_expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
         WHERE schedule_id = ? AND schedule_status = 'Available'"
    );
    $bookingStmt = $conn->prepare(
        "INSERT INTO bookings
            (user_id, booking_type, customer_name, customer_phone, court_id, schedule_id,
             start_time, end_time, total_duration, total_amount, status, payment_status, hold_expires_at)
         SELECT u.user_id, 'online',
                CONCAT_WS(' ', NULLIF(u.first_name, ''), NULLIF(u.last_name, '')),
                u.phone_number, ?, ?, ?, ?, ?, ?, 'pending_payment', 'unpaid', DATE_ADD(NOW(), INTERVAL 5 MINUTE)
         FROM users u WHERE u.user_id = ?"
    );
    $firstScheduleId = (int) $slots[0]["schedule_id"];
    $courtId = (int) $slots[0]["court_id"];
    $bookingStmt->bind_param(
        "iissiii",
        $courtId,
        $firstScheduleId,
        $startTime,
        $endTime,
        $totalDuration,
        $totalAmount,
        $userId
    );
    $bookingStmt->execute();
    if ($bookingStmt->affected_rows !== 1) {
        throw new RuntimeException("Your account details could not be loaded. Please sign in again.");
    }
    $bookingId = (int) $bookingStmt->insert_id;
    $bookingStmt->close();
    foreach ($slots as $slot) {
        $scheduleId = (int) $slot["schedule_id"];
        $holdStmt->bind_param("i", $scheduleId);
        $holdStmt->execute();
        if ($holdStmt->affected_rows !== 1) {
            throw new RuntimeException("Those consecutive times are no longer available. Choose another time.");
        }
    }
    $holdStmt->close();

    if (!$conn->commit()) {
        throw new RuntimeException("Unable to save your reservation.");
    }
    unset($_SESSION["pending_reservation"], $_SESSION["reservation_notice"]);
    echo json_encode(["success" => true, "booking_id" => $bookingId]);
} catch (RuntimeException $error) {
    $conn->rollback();
    $isUnavailable = str_contains($error->getMessage(), "no longer available");
    $isAuthFailure = str_contains($error->getMessage(), "verified customer account");
    if ($isUnavailable) {
        unset($_SESSION["pending_reservation"]);
        $_SESSION["reservation_notice"] = "The selected time is no longer available. Please choose another time.";
        expireUnpaidBookings($conn);
    }
    if ($isAuthFailure) {
        $_SESSION["pending_reservation"] = ["schedule_ids" => $scheduleIds];
        unset(
            $_SESSION["user_id"],
            $_SESSION["first_name"],
            $_SESSION["last_name"],
            $_SESSION["email"],
            $_SESSION["logged_in"],
            $_SESSION["role_id"],
            $_SESSION["role_name"]
        );
        session_regenerate_id(true);
    }
    http_response_code($isUnavailable ? 409 : ($isAuthFailure ? 403 : 500));
    echo json_encode([
        "success" => false,
        "message" => $error->getMessage(),
        "slot_unavailable" => $isUnavailable
    ]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    error_log("Player booking hold failed: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to reserve this time. Please try again."]);
}
$conn->close();
