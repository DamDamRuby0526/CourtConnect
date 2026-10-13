<?php
const ALLOW_EARLY_ARRIVAL = false;

header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function respondJsonError(int $statusCode, string $code, string $message, ?array $extras = null): void
{
    http_response_code($statusCode);
    $payload = ["success" => false, "code" => $code, "message" => $message];
    if (is_array($extras)) {
        $payload = array_merge($payload, $extras);
    }
    echo json_encode($payload);
    exit();
}

function buildNotStartedMessage(string $courtDate, string $courtTime): string
{
    $slotTime = strtotime($courtDate . " " . $courtTime);
    if ($slotTime === false) {
        return "This booking hasn't started yet.";
    }

    return sprintf(
        "This booking hasn't started yet. You can mark attendance from %s on %s.",
        date("g:i A", $slotTime),
        date("D, M j", $slotTime)
    );
}

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$roleId = (int) ($_SESSION["role_id"] ?? 0);
if (!$userId || !in_array($roleId, [2, 3], true)) {
    respondJsonError(401, "unauthorized", "Facility admin access is required.");
}

$accessStmt = $conn->prepare(
    "SELECT a.admin_id, a.facility_id
     FROM admins a
     INNER JOIN users u ON u.user_id = a.user_id
    WHERE a.user_id = ? AND a.status = 'Active'
         AND u.role_id = ? AND u.is_verified = 1
     LIMIT 1"
);
$accessStmt->bind_param("ii", $userId, $roleId);
$accessStmt->execute();
$adminAccess = $accessStmt->get_result()->fetch_assoc();
$accessStmt->close();
if (!$adminAccess) {
    respondJsonError(403, "forbidden", "You do not have access to this facility.");
}
$facilityId = (int) $adminAccess["facility_id"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respondJsonError(405, "invalid_method", "Invalid request method.");
}

$bookingId = filter_var($_POST["booking_id"] ?? null, FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId < 1) {
    respondJsonError(400, "invalid_booking", "A valid booking is required.");
}

$attendance = $_POST["attendance"] ?? "arrived";
if (!in_array($attendance, ["arrived", "no_show"], true)) {
    respondJsonError(400, "invalid_attendance", "A valid attendance outcome is required.");
}

try {
    $conn->begin_transaction();

    $bookingStmt = $conn->prepare(
        "SELECT b.booking_id, b.status, b.arrived_at, b.did_not_arrive_at,
                cs.court_date, cs.court_time,
                TIMESTAMP(cs.court_date, cs.court_time) AS slot_start_ts,
                NOW() AS mysql_now
         FROM bookings b
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE b.booking_id = ? AND cd.facility_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $bookingStmt->bind_param("ii", $bookingId, $facilityId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();

    if (!$booking) {
        $conn->rollback();
        respondJsonError(404, "not_found", "Booking not found.");
    }

    if (strtolower((string) $booking["status"]) === "cancelled") {
        $conn->rollback();
        respondJsonError(409, "cancelled", "This booking was cancelled.");
    }

    $attendanceState = null;
    if (!empty($booking["arrived_at"])) {
        $attendanceState = "arrived";
    } elseif (!empty($booking["did_not_arrive_at"])) {
        $attendanceState = "no_show";
    }

    if ($attendanceState !== null) {
        $conn->rollback();
        respondJsonError(409, "already_recorded", "Attendance was already recorded for this booking.", [
            "attendance_state" => $attendanceState,
        ]);
    }

    $slotStartTs = $booking["slot_start_ts"] ?? null;
    $mysqlNow = $booking["mysql_now"] ?? null;
    $slotHasStarted = $slotStartTs !== null && $mysqlNow !== null && $slotStartTs <= $mysqlNow;

    if (!$slotHasStarted && (!ALLOW_EARLY_ARRIVAL || $attendance === "no_show")) {
        $conn->rollback();
        respondJsonError(409, "not_started", buildNotStartedMessage($booking["court_date"], $booking["court_time"]));
    }

    $updateStmt = $conn->prepare(
        "UPDATE bookings b
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         SET b.arrived_at = CASE WHEN ? = 'arrived' THEN NOW() ELSE NULL END,
             b.did_not_arrive_at = CASE WHEN ? = 'no_show' THEN NOW() ELSE NULL END
         WHERE b.booking_id = ? AND cd.facility_id = ?
           AND LOWER(b.status) <> 'cancelled'
           AND b.arrived_at IS NULL
           AND b.did_not_arrive_at IS NULL"
    );
    $updateStmt->bind_param("ssii", $attendance, $attendance, $bookingId, $facilityId);
    $updateStmt->execute();
    $updated = $updateStmt->affected_rows === 1;
    $updateStmt->close();

    if (!$updated) {
        throw new RuntimeException("Booking update did not affect expected row.");
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => $attendance === "arrived"
            ? "Booking marked as arrived."
            : "Booking marked as no-show.",
    ]);
} catch (Throwable $exception) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log("mark_booking_arrived booking_id={$bookingId}: " . $exception->getMessage());
    respondJsonError(500, "server_error", "Something went wrong. Please try again.");
}
