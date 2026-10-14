<?php

header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";

$data = json_decode(file_get_contents("php://input"), true);
$data = is_array($data) ? $data : [];
$courtId = filter_var($data["court_id"] ?? null, FILTER_VALIDATE_INT);
$scope = $data["scope"] ?? "";
$startDateValue = trim((string) ($data["start_date"] ?? ""));
$endDateValue = trim((string) ($data["end_date"] ?? ""));
$facilityId = (int) $_SESSION["facility_id"];

$startDate = DateTimeImmutable::createFromFormat("!Y-m-d", $startDateValue);
$endDate = DateTimeImmutable::createFromFormat("!Y-m-d", $endDateValue);

if (
    !$courtId ||
    !in_array($scope, ["court", "facility"], true) ||
    !$startDate ||
    $startDate->format("Y-m-d") !== $startDateValue ||
    !$endDate ||
    $endDate->format("Y-m-d") !== $endDateValue
) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a valid court, date range, and generation scope."]);
    exit();
}

$today = new DateTimeImmutable("today");
if ($startDate < $today) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "The start date cannot be in the past."]);
    exit();
}

if ($endDate < $startDate) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "The end date must be on or after the start date."]);
    exit();
}

if ($startDate->diff($endDate)->days > 89) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "The date range cannot exceed 90 days."]);
    exit();
}

if ($scope === "court") {
    $courtStmt = $conn->prepare(
        "SELECT cd.court_id, cd.slot_duration, f.opening_time, f.closing_time
         FROM court_details cd
         INNER JOIN facilities f ON f.facility_id = cd.facility_id
         WHERE cd.court_id = ? AND cd.facility_id = ? AND cd.court_status = 'Available'"
    );
    $courtStmt->bind_param("ii", $courtId, $facilityId);
} else {
    $courtStmt = $conn->prepare(
        "SELECT cd.court_id, cd.slot_duration, f.opening_time, f.closing_time
         FROM court_details cd
         INNER JOIN facilities f ON f.facility_id = cd.facility_id
         WHERE cd.facility_id = ? AND cd.court_status = 'Available'
         ORDER BY cd.court_id"
    );
    $courtStmt->bind_param("i", $facilityId);
}

$courtStmt->execute();
$courtResult = $courtStmt->get_result();
$courts = [];
while ($row = $courtResult->fetch_assoc()) {
    $courts[] = $row;
}
$courtStmt->close();

if (!$courts) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "No available courts were found in your facility for this request."]);
    exit();
}

foreach ($courts as $court) {
    if (empty($court["opening_time"]) || empty($court["closing_time"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Set the facility opening and closing hours before generating schedules."
        ]);
        exit();
    }
}

$timePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/';
foreach ($courts as $index => $court) {
    if (!preg_match($timePattern, (string) $court["opening_time"]) || !preg_match($timePattern, (string) $court["closing_time"])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Facility opening hours are invalid."]);
        exit();
    }

    $openingTime = DateTimeImmutable::createFromFormat("!H:i:s", (string) $court["opening_time"]);
    $closingTime = DateTimeImmutable::createFromFormat("!H:i:s", (string) $court["closing_time"]);
    if (!$openingTime || !$closingTime || $closingTime <= $openingTime) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Facility opening hours must have a closing time after the opening time."]);
        exit();
    }

    $slotDuration = (int) $court["slot_duration"];
    $courts[$index]["opening_datetime"] = $openingTime;
    $courts[$index]["closing_datetime"] = $closingTime;
    $courts[$index]["slot_duration"] = $slotDuration > 0 ? $slotDuration : 60;
}

$createdSlots = 0;
$skippedSlots = 0;
$transactionOpen = false;

try {
    $insertStmt = $conn->prepare(
        "INSERT IGNORE INTO court_schedule (court_id, court_date, court_time, schedule_status)
         VALUES (?, ?, ?, 'Available')"
    );

    foreach ($courts as $court) {
        if (!$conn->begin_transaction()) {
            throw new RuntimeException("Unable to begin court schedule transaction.");
        }
        $transactionOpen = true;
        $courtCreatedSlots = 0;
        $courtTotalSlots = 0;
        $date = $startDate;

        while ($date <= $endDate) {
            $slotStart = $court["opening_datetime"];
            while ($slotStart <= $court["closing_datetime"]) {
                $courtDate = $date->format("Y-m-d");
                $courtTime = $slotStart->format("H:i:s");
                $courtIdForInsert = (int) $court["court_id"];

                $insertStmt->bind_param("iss", $courtIdForInsert, $courtDate, $courtTime);
                if (!$insertStmt->execute()) {
                    throw new RuntimeException("Unable to insert court schedule slot.");
                }
                $courtCreatedSlots += $insertStmt->affected_rows;
                $courtTotalSlots++;
                $slotStart = $slotStart->modify("+{$court["slot_duration"]} minutes");
            }
            $date = $date->modify("+1 day");
        }

        if (!$conn->commit()) {
            throw new RuntimeException("Unable to commit court schedule transaction.");
        }
        $transactionOpen = false;
        $createdSlots += $courtCreatedSlots;
        $skippedSlots += $courtTotalSlots - $courtCreatedSlots;
    }
} catch (Throwable $error) {
    if ($transactionOpen) {
        $conn->rollback();
    }
    error_log("Schedule generation failed: " . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Schedule generation failed. Completed courts may already have schedules; retrying is safe and will skip existing slots."
    ]);
    exit();
}

$insertStmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "created_slots" => $createdSlots,
    "skipped_slots" => $skippedSlots,
    "message" => "Generated {$createdSlots} new slots, {$skippedSlots} already existed and were skipped."
]);
