<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../backend-api/config.php";

$courtId = filter_var($_GET["court_id"] ?? null, FILTER_VALIDATE_INT);
$courtDate = is_string($_GET["date"] ?? null) ? $_GET["date"] : date("Y-m-d");

if (!$courtId || $courtId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $courtDate)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Choose a valid court and date."
    ]);
    exit();
}

$date = DateTimeImmutable::createFromFormat("!Y-m-d", $courtDate);
if (!$date || $date->format("Y-m-d") !== $courtDate || $courtDate < date("Y-m-d") || $courtDate > date("Y-m-d", strtotime("+30 days"))) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a date within the next 30 days."]);
    exit();
}

$sql = "SELECT cs.schedule_id, cs.court_id, cs.court_date, cs.court_time,
               CASE WHEN cs.schedule_status = 'Held'
                         AND (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW())
                    THEN 'Available' ELSE cs.schedule_status END AS schedule_status
        FROM court_schedule cs
        INNER JOIN court_details cd ON cd.court_id = cs.court_id
        WHERE cs.court_id = ? AND cd.court_status = 'Available' AND cs.court_date = ?
            AND (cs.court_date > CURDATE() OR cs.court_time > CURTIME())
        ORDER BY cs.court_time ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $courtId, $courtDate);
$stmt->execute();

$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

$nextDateStmt = $conn->prepare(
    "SELECT MIN(cs.court_date) AS next_available_date
     FROM court_schedule cs
     INNER JOIN court_details cd ON cd.court_id = cs.court_id
     WHERE cs.court_id = ? AND cd.court_status = 'Available'
         AND cs.court_date > ? AND cs.court_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
         AND (cs.schedule_status = 'Available'
             OR (cs.schedule_status = 'Held'
                 AND (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW())))
         AND (cs.court_date > CURDATE() OR cs.court_time > CURTIME())"
);
$nextDateStmt->bind_param("is", $courtId, $courtDate);
$nextDateStmt->execute();
$nextAvailableDate = $nextDateStmt->get_result()->fetch_assoc()["next_available_date"] ?? null;
$nextDateStmt->close();

echo json_encode([
    "success" => true,
    "court_id" => $courtId,
    "schedules" => $schedules,
    "next_available_date" => $nextAvailableDate
]);

$stmt->close();
$conn->close();
