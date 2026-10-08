<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);
$roleId = (int) ($_SESSION["role_id"] ?? 0);
if (!$userId || !$facilityId || !in_array($roleId, [2, 3], true)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Facility admin access is required."]);
    exit();
}

$accessStmt = $conn->prepare(
    "SELECT a.admin_id
     FROM admins a
     INNER JOIN users u ON u.user_id = a.user_id
     WHERE a.user_id = ? AND a.facility_id = ? AND a.status = 'Active'
         AND u.role_id = ? AND u.is_verified = 1
     LIMIT 1"
);
$accessStmt->bind_param("iii", $userId, $facilityId, $roleId);
$accessStmt->execute();
$hasAccess = $accessStmt->get_result()->fetch_assoc();
$accessStmt->close();
if (!$hasAccess) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "You do not have access to this facility."]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $slotsStmt = $conn->prepare(
        "SELECT cs.schedule_id, cs.court_id, cs.court_date, cs.court_time,
                cd.court_no, cd.slot_duration, cd.court_rate
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         WHERE cd.facility_id = ? AND cd.court_status = 'Available'
             AND cs.schedule_status = 'Available'
             AND (cs.court_date > CURDATE() OR (cs.court_date = CURDATE() AND cs.court_time > CURTIME()))
         ORDER BY cs.court_date ASC, cs.court_time ASC, cd.court_no ASC
         LIMIT 300"
    );
    $slotsStmt->bind_param("i", $facilityId);
    $slotsStmt->execute();
    $slotsResult = $slotsStmt->get_result();
    $slots = [];
    while ($slot = $slotsResult->fetch_assoc()) {
        $slots[] = $slot;
    }
    $slotsStmt->close();

    $customersStmt = $conn->prepare(
        "SELECT user_id, first_name, last_name, email
         FROM users
         WHERE role_id = 1 AND is_verified = 1
         ORDER BY first_name, last_name
         LIMIT 500"
    );
    $customersStmt->execute();
    $customersResult = $customersStmt->get_result();
    $customers = [];
    while ($customer = $customersResult->fetch_assoc()) {
        $customers[] = $customer;
    }
    $customersStmt->close();
    echo json_encode(["success" => true, "slots" => $slots, "customers" => $customers]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$scheduleId = filter_var($data["schedule_id"] ?? null, FILTER_VALIDATE_INT);
$customerId = filter_var($data["customer_id"] ?? null, FILTER_VALIDATE_INT);
if (!$scheduleId || !$customerId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose an available time and a registered customer."]);
    exit();
}

try {
    $conn->begin_transaction();
    $slotStmt = $conn->prepare(
        "SELECT cs.court_id, cd.court_rate
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         WHERE cs.schedule_id = ? AND cd.facility_id = ?
             AND cs.schedule_status = 'Available'
             AND (cs.court_date > CURDATE() OR (cs.court_date = CURDATE() AND cs.court_time > CURTIME()))
             AND cd.court_status = 'Available'
         FOR UPDATE"
    );
    $slotStmt->bind_param("ii", $scheduleId, $facilityId);
    $slotStmt->execute();
    $slot = $slotStmt->get_result()->fetch_assoc();
    $slotStmt->close();
    if (!$slot) {
        throw new RuntimeException("That time is no longer available. Refresh and choose another slot.");
    }

    $customerStmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role_id = 1 AND is_verified = 1 LIMIT 1");
    $customerStmt->bind_param("i", $customerId);
    $customerStmt->execute();
    $customer = $customerStmt->get_result()->fetch_assoc();
    $customerStmt->close();
    if (!$customer) {
        throw new RuntimeException("Choose a verified customer account.");
    }

    $courtId = (int) $slot["court_id"];
    $totalAmount = (float) $slot["court_rate"];
    $bookingStmt = $conn->prepare(
        "INSERT INTO bookings (user_id, court_id, schedule_id, total_amount)
         VALUES (?, ?, ?, ?)"
    );
    $bookingStmt->bind_param("iiid", $customerId, $courtId, $scheduleId, $totalAmount);
    $bookingStmt->execute();
    $bookingId = $bookingStmt->insert_id;
    $bookingStmt->close();

    $paymentMethod = "Gcash";
    $paymentStatus = "Pending";
    $paymentStmt = $conn->prepare(
        "INSERT INTO payments (booking_id, payment_method, payment_status, reference_number, paid_at)
         VALUES (?, ?, ?, NULL, NULL)"
    );
    $paymentStmt->bind_param("iss", $bookingId, $paymentMethod, $paymentStatus);
    $paymentStmt->execute();
    $paymentStmt->close();

    $updateStmt = $conn->prepare(
        "UPDATE court_schedule SET schedule_status = 'Booked'
         WHERE schedule_id = ? AND schedule_status = 'Available'"
    );
    $updateStmt->bind_param("i", $scheduleId);
    $updateStmt->execute();
    if ($updateStmt->affected_rows !== 1) {
        $updateStmt->close();
        throw new RuntimeException("That time is no longer available. Refresh and choose another slot.");
    }
    $updateStmt->close();
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Booking confirmed.", "booking_id" => $bookingId]);
} catch (RuntimeException $error) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => $error->getMessage()]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    error_log("Dashboard booking error: " . $error->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to create this booking."]);
}