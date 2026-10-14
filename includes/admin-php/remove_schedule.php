<?php
header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";

$data = json_decode(file_get_contents("php://input"), true);
$data = is_array($data) ? $data : $_POST;
$action = $data["action"] ?? "";
$dryRun = filter_var($data["dry_run"] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$facilityId = (int) $_SESSION["facility_id"];
$courtId = filter_var($data["court_id"] ?? null, FILTER_VALIDATE_INT);
$scheduleId = filter_var($data["schedule_id"] ?? null, FILTER_VALIDATE_INT);

function removeScheduleDateIsValid($date): bool
{
    if (!is_string($date)) {
        return false;
    }
    $parsed = DateTimeImmutable::createFromFormat("!Y-m-d", $date);
    return $parsed !== false && $parsed->format("Y-m-d") === $date;
}

function removeScheduleBindValues(mysqli_stmt $stmt, string $types, array $values): void
{
    $params = [$types];
    foreach ($values as $index => $value) {
        $params[] = &$values[$index];
    }
    call_user_func_array([$stmt, "bind_param"], $params);
}

if (!in_array($action, ["slot", "day", "range"], true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid schedule removal action."]);
    exit();
}
if ($dryRun === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "The dry_run flag must be true or false."]);
    exit();
}

$courtIds = [];
$extraWhere = "";
$extraTypes = "";
$extraValues = [];
$startDate = "";
$endDate = "";

if ($action === "slot" || $action === "day" || ($action === "range" && ($data["scope"] ?? "") === "court")) {
    if (!$courtId || $courtId < 1) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Choose a valid court."]);
        exit();
    }
    $ownedCourtStmt = $conn->prepare(
        "SELECT court_id FROM court_details WHERE court_id = ? AND facility_id = ? LIMIT 1"
    );
    $ownedCourtStmt->bind_param("ii", $courtId, $facilityId);
    $ownedCourtStmt->execute();
    $ownedCourt = $ownedCourtStmt->get_result()->fetch_assoc();
    $ownedCourtStmt->close();
    if (!$ownedCourt) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "That court does not belong to your facility."]);
        exit();
    }
    $courtIds[] = (int) $ownedCourt["court_id"];
} elseif ($action === "range" && ($data["scope"] ?? "") === "facility") {
    $courtsStmt = $conn->prepare("SELECT court_id FROM court_details WHERE facility_id = ? ORDER BY court_id");
    $courtsStmt->bind_param("i", $facilityId);
    $courtsStmt->execute();
    $courtsResult = $courtsStmt->get_result();
    while ($court = $courtsResult->fetch_assoc()) {
        $courtIds[] = (int) $court["court_id"];
    }
    $courtsStmt->close();
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose a valid schedule removal scope."]);
    exit();
}

if ($action === "slot") {
    if (!$scheduleId || $scheduleId < 1) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Choose a valid schedule slot."]);
        exit();
    }
    $extraWhere = " AND cs.schedule_id = ?";
    $extraTypes = "i";
    $extraValues[] = (int) $scheduleId;
    $startDate = "1000-01-01";
    $endDate = "9999-12-31";
} else {
    $startDateValue = $data["start_date"] ?? $data["court_date"] ?? "";
    $endDateValue = $data["end_date"] ?? "";
    $startDate = is_string($startDateValue) ? trim($startDateValue) : "";
    $endDate = $action === "day"
        ? $startDate
        : (is_string($endDateValue) ? trim($endDateValue) : "");
    if (!removeScheduleDateIsValid($startDate) || !removeScheduleDateIsValid($endDate) || $endDate < $startDate) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Enter a valid schedule date range."]);
        exit();
    }
    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    if ($start->diff($end)->days > 89) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "The date range cannot exceed 90 days."]);
        exit();
    }
}

if (!$courtIds) {
    echo json_encode([
        "success" => true,
        "removed" => 0,
        "kept_booked" => 0,
        "kept_held" => 0,
        "kept_other" => 0,
    ]);
    exit();
}

$courtPlaceholders = implode(",", array_fill(0, count($courtIds), "?"));
$courtTypes = str_repeat("i", count($courtIds));
$queryValues = array_merge([$facilityId], $courtIds, [$startDate, $endDate], $extraValues);
$queryTypes = "i" . $courtTypes . "ss" . $extraTypes;

try {
    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to begin schedule removal transaction.");
    }

    $candidateStmt = $conn->prepare(
        "SELECT cs.schedule_id, cs.schedule_status,
                (cs.schedule_status = 'Held' AND cs.hold_expires_at > NOW()) AS live_hold,
                (cs.schedule_status = 'Held' AND cs.hold_expires_at < NOW()) AS expired_hold,
                EXISTS (SELECT 1 FROM bookings b WHERE b.schedule_id = cs.schedule_id) AS has_booking
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         WHERE cd.facility_id = ? AND cs.court_id IN ($courtPlaceholders)
             AND cs.court_date BETWEEN ? AND ? $extraWhere
         FOR UPDATE"
    );
    removeScheduleBindValues($candidateStmt, $queryTypes, $queryValues);
    $candidateStmt->execute();
    $candidateResult = $candidateStmt->get_result();
    $deletableIds = [];
    $keptBooked = 0;
    $keptHeld = 0;
    $keptOther = 0;
    while ($slot = $candidateResult->fetch_assoc()) {
        $expiredAvailable = $slot["schedule_status"] === "Available"
            || (int) $slot["expired_hold"] === 1;
        if ($expiredAvailable && (int) $slot["has_booking"] === 0) {
            $deletableIds[] = (int) $slot["schedule_id"];
        } elseif ($slot["schedule_status"] === "Booked") {
            $keptBooked++;
        } elseif ((int) $slot["live_hold"] === 1) {
            $keptHeld++;
        } else {
            $keptOther++;
        }
    }
    $candidateStmt->close();

    $removed = count($deletableIds);
    if (!$dryRun && $deletableIds) {
        $slotPlaceholders = implode(",", array_fill(0, count($deletableIds), "?"));
        $deleteStmt = $conn->prepare(
            "DELETE cs FROM court_schedule cs
             INNER JOIN court_details cd ON cd.court_id = cs.court_id
             WHERE cd.facility_id = ? AND cs.court_id IN ($courtPlaceholders)
                 AND cs.court_date BETWEEN ? AND ? $extraWhere
                 AND cs.schedule_id IN ($slotPlaceholders)
                 AND (cs.schedule_status = 'Available'
                     OR (cs.schedule_status = 'Held' AND cs.hold_expires_at < NOW()))
                 AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.schedule_id = cs.schedule_id)"
        );
        $deleteValues = array_merge(
            [$facilityId],
            $courtIds,
            [$startDate, $endDate],
            $extraValues,
            $deletableIds
        );
        removeScheduleBindValues(
            $deleteStmt,
            "i" . $courtTypes . "ss" . $extraTypes . str_repeat("i", count($deletableIds)),
            $deleteValues
        );
        $deleteStmt->execute();
        $removed = $deleteStmt->affected_rows;
        $deleteStmt->close();
    }

    if ($dryRun) {
        $conn->rollback();
    } else {
        $conn->commit();
    }
    $conn->close();

    echo json_encode([
        "success" => true,
        "removed" => $removed,
        "kept_booked" => $keptBooked,
        "kept_held" => $keptHeld,
        "kept_other" => $keptOther,
    ]);
} catch (Throwable $error) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log("Schedule removal failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Unable to remove schedule slots. Please try again."]);
}
