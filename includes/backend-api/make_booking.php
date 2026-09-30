<?php
header("Content-Type: application/json");
session_start();
require_once __DIR__ . "/config.php";

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "You must be logged in to book a court."
    ]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "No data received."
    ]);
    exit();
}

$userId     = $_SESSION["user_id"];
$courtId    = $data["court_id"] ?? null;
$scheduleId = $data["schedule_id"] ?? null;

if (!$courtId || !is_numeric($courtId) || !$scheduleId || !is_numeric($scheduleId)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing court_id or schedule_id."
    ]);
    exit();
}

// Lock the schedule row and confirm it's still available before booking,
// to avoid double-booking if two users submit at the same time.
$conn->begin_transaction();

try {
    $sql = "SELECT schedule_status FROM court_schedule WHERE schedule_id = ? AND court_id = ? FOR UPDATE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $scheduleId, $courtId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new RuntimeException("Schedule not found for this court.");
    }

    $schedule = $result->fetch_assoc();
    $stmt->close();

    if ($schedule["schedule_status"] !== "Available") {
        throw new RuntimeException("This time slot is no longer available.");
    }

    // Get the court rate to compute total_amount
    $sql = "SELECT court_rate FROM court_details WHERE court_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $courtId);
    $stmt->execute();
    $courtResult = $stmt->get_result();

    if ($courtResult->num_rows === 0) {
        throw new RuntimeException("Court not found.");
    }

    $court = $courtResult->fetch_assoc();
    $stmt->close();
    $totalAmount = $court["court_rate"];

    // Insert the booking
    $sql = "INSERT INTO bookings (user_id, court_id, schedule_id, total_amount)
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiid", $userId, $courtId, $scheduleId, $totalAmount);
    $stmt->execute();
    $bookingId = $stmt->insert_id;
    $stmt->close();

    // Mark the schedule as no longer available
    $sql = "UPDATE court_schedule SET schedule_status = 'Booked' WHERE schedule_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $scheduleId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Booking confirmed.",
        "booking_id" => $bookingId
    ]);

} catch (RuntimeException $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log("Booking DB error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Unable to complete the booking."
    ]);
}

$conn->close();
?>