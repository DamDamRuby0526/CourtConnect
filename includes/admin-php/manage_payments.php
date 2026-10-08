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
    $stmt = $conn->prepare(
        "SELECT p.payments_id, p.payment_method, p.payment_status, p.reference_number, p.receipt_image,
                p.paid_at, p.created_at, b.booking_id, b.total_amount,
                u.first_name, u.last_name,
                cs.court_date, cs.court_time, cd.court_no
         FROM payments p
         INNER JOIN bookings b ON b.booking_id = p.booking_id
         INNER JOIN users u ON u.user_id = b.user_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         WHERE cd.facility_id = ?
         ORDER BY (p.payment_status = 'Pending') DESC, cs.court_date ASC, cs.court_time ASC"
    );
    $stmt->bind_param("i", $facilityId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    $pendingCount = 0;
    $paidTotal = 0.0;
    while ($payment = $result->fetch_assoc()) {
        $payment["total_amount"] = (float) $payment["total_amount"];
        $payment["has_receipt"] = !empty($payment["receipt_image"]);
        unset($payment["receipt_image"]);
        if ($payment["payment_status"] === "Pending") {
            $pendingCount++;
        } else {
            $paidTotal += $payment["total_amount"];
        }
        $payments[] = $payment;
    }
    $stmt->close();
    echo json_encode([
        "success" => true,
        "payments" => $payments,
        "pending_count" => $pendingCount,
        "paid_total" => $paidTotal
    ]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$paymentId = filter_var($data["payment_id"] ?? null, FILTER_VALIDATE_INT);
if (!$paymentId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid payment."]);
    exit();
}

$stmt = $conn->prepare(
    "UPDATE payments p
     INNER JOIN bookings b ON b.booking_id = p.booking_id
     INNER JOIN court_details cd ON cd.court_id = b.court_id
     SET p.payment_status = 'Paid', p.paid_at = NOW()
     WHERE p.payments_id = ? AND cd.facility_id = ?
         AND p.payment_status = 'Pending' AND p.receipt_image IS NOT NULL"
);
$stmt->bind_param("ii", $paymentId, $facilityId);
$stmt->execute();
$updated = $stmt->affected_rows === 1;
$stmt->close();

if (!$updated) {
    echo json_encode(["success" => false, "message" => "A pending payment with an uploaded receipt was not found for this facility."]);
    exit();
}

echo json_encode(["success" => true, "message" => "Payment marked as paid."]);
$conn->close();