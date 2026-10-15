<?php
require_once __DIR__ . "/includes/backend-api/config.php";

$rawPendingIds = $_SESSION["pending_reservation"]["schedule_ids"]
    ?? (isset($_SESSION["pending_reservation"]["schedule_id"])
        ? [$_SESSION["pending_reservation"]["schedule_id"]]
        : []);
$pendingScheduleIds = [];
if (is_array($rawPendingIds)) {
    foreach ($rawPendingIds as $rawScheduleId) {
        $validScheduleId = filter_var($rawScheduleId, FILTER_VALIDATE_INT);
        if (!$validScheduleId || $validScheduleId < 1) {
            $pendingScheduleIds = [];
            break;
        }
        $pendingScheduleIds[] = (int) $validScheduleId;
    }
}
$pendingScheduleIds = array_values(array_unique($pendingScheduleIds));
sort($pendingScheduleIds);
$hasPendingReservation = count($pendingScheduleIds) > 0 && count($pendingScheduleIds) <= 8;
$reservationSlots = [];
$isAvailable = false;

if ($hasPendingReservation) {
    $placeholders = implode(",", array_fill(0, count($pendingScheduleIds), "?"));
    $reservationStmt = $conn->prepare(
        "SELECT cs.schedule_id, cs.court_date, cs.court_time, cs.schedule_status,
                cs.hold_expires_at, cd.court_id, cd.court_no, cd.court_rate,
                cd.slot_duration, cd.court_status, f.facility_id, f.facility_name,
                f.municipality,
                (cs.schedule_status = 'Available'
                    OR (cs.schedule_status = 'Held'
                        AND (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW()))) AS slot_open,
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
         ORDER BY cs.court_time ASC"
    );
    $scheduleTypes = str_repeat("i", count($pendingScheduleIds));
    $reservationStmt->bind_param($scheduleTypes, ...$pendingScheduleIds);
    $reservationStmt->execute();
    $reservationResult = $reservationStmt->get_result();
    while ($row = $reservationResult->fetch_assoc()) {
        $reservationSlots[] = $row;
    }
    $reservationStmt->close();

    $firstSlot = $reservationSlots[0] ?? null;
    $isAvailable = $firstSlot && count($reservationSlots) === count($pendingScheduleIds);
    foreach ($reservationSlots as $index => $slot) {
        $isAvailable = $isAvailable
            && $slot["court_id"] === $firstSlot["court_id"]
            && $slot["court_date"] === $firstSlot["court_date"]
            && $slot["slot_duration"] === $firstSlot["slot_duration"]
            && $slot["court_status"] === "Available"
            && (int) $slot["facility_active"] === 1
            && (int) $slot["within_booking_window"] === 1
            && (int) $slot["slot_open"] === 1;
        if ($index > 0) {
            $previousStart = strtotime($reservationSlots[$index - 1]["court_date"] . " " . $reservationSlots[$index - 1]["court_time"]);
            $currentStart = strtotime($slot["court_date"] . " " . $slot["court_time"]);
            $isAvailable = $isAvailable
                && $currentStart === $previousStart + (int) $firstSlot["slot_duration"] * 60;
        }
    }
    if (!$isAvailable) {
        unset($_SESSION["pending_reservation"]);
        $_SESSION["reservation_notice"] = "The selected time is no longer available. Please choose another time.";
    }
} else {
    unset($_SESSION["pending_reservation"]);
}
$reservationNotice = is_string($_SESSION["reservation_notice"] ?? null)
    ? $_SESSION["reservation_notice"]
    : "";
unset($_SESSION["reservation_notice"]);
$conn->close();

function reservationResumeEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$isLoggedIn = !empty($_SESSION["logged_in"]);
$isPlayer = $isLoggedIn
    && !empty($_SESSION["user_id"])
    && (int) ($_SESSION["role_id"] ?? 0) === 1;
$reservation = $reservationSlots[0] ?? null;
$courtUrl = $reservation
    ? "courts.php?facility_id=" . (int) $reservation["facility_id"] . "#court-" . (int) $reservation["court_id"]
    : "facility.php";
$lastSlot = $reservationSlots ? $reservationSlots[count($reservationSlots) - 1] : null;
$courtDate = $reservation ? date("D, M j, Y", strtotime($reservation["court_date"])) : "";
$courtStartTime = $reservation ? date("g:i A", strtotime($reservation["court_time"])) : "";
$courtEndTime = $lastSlot
    ? date("g:i A", strtotime($lastSlot["court_time"]) + (int) $lastSlot["slot_duration"] * 60)
    : "";
$reservationDuration = $reservation
    ? count($reservationSlots) * (int) $reservation["slot_duration"]
    : 0;
$reservationDurationHours = $reservationDuration / 60;
$reservationDurationLabel = rtrim(rtrim(number_format($reservationDurationHours, 2, ".", ""), "0"), ".")
    . " " . ($reservationDurationHours === 1.0 ? "hour" : "hours");
$reservationTotal = array_sum(array_map(static function ($slot) {
    return (int) $slot["court_rate"];
}, $reservationSlots));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if ($isAvailable && $isPlayer): ?>
        <script src="includes/js/reservation_resume.js" defer></script>
    <?php endif; ?>
    <title>Continue your reservation | CourtConnect</title>
</head>
<body class="player-ui">
    <?php include 'includes/navigation/header.php'; ?>
    <main class="player-page reservation-resume-page">
        <nav class="player-breadcrumb" aria-label="Breadcrumb">
            <a href="facility.php">Find a court</a><span aria-hidden="true">/</span><span>Reservation</span>
        </nav>
        <section class="reservation-resume-card" aria-labelledby="reservationResumeTitle">
            <?php if (!$hasPendingReservation && $reservationNotice !== ""): ?>
                <p class="player-eyebrow">TIME SLOT UPDATE</p>
                <h1 id="reservationResumeTitle">This time is no longer available</h1>
                <p class="reservation-resume-copy"><?= reservationResumeEscape($reservationNotice) ?></p>
                <a class="player-primary-link" href="<?= reservationResumeEscape($courtUrl) ?>">Choose another time</a>
            <?php elseif (!$hasPendingReservation): ?>
                <p class="player-eyebrow">NO RESERVATION SELECTED</p>
                <h1 id="reservationResumeTitle">Choose a court first</h1>
                <p class="reservation-resume-copy">Select an available court time to start a reservation. You can browse facilities and schedules without signing in.</p>
                <a class="player-primary-link" href="facility.php">Find a court</a>
            <?php elseif (!$reservation || !$isAvailable): ?>
                <p class="player-eyebrow">TIME SLOT UPDATE</p>
                <h1 id="reservationResumeTitle">This time is no longer available</h1>
                <p class="reservation-resume-copy">The selected court or time slot is no longer available. Choose another time to continue.</p>
                <a class="player-primary-link" href="<?= reservationResumeEscape($courtUrl) ?>">Choose another time</a>
            <?php else: ?>
                <p class="player-eyebrow"><?= $isPlayer ? "REVIEW YOUR RESERVATION" : "ACCOUNT REQUIRED TO RESERVE" ?></p>
                <h1 id="reservationResumeTitle"><?= $isPlayer ? "Confirm your court time" : "You're one step away from your next match!" ?></h1>
                <p class="reservation-resume-copy">
                    <?php if ($isPlayer): ?>
                        Review your reservation details. Your time will only be held after you confirm.
                    <?php elseif ($isLoggedIn): ?>
                        A customer account is required to reserve a court. Sign out of this account and log in with a customer account to continue.
                    <?php else: ?>
                        Create an account or log in to continue with your court reservation. Your selected time will be checked again before it is reserved.
                    <?php endif; ?>
                </p>

                <dl class="reservation-resume-summary">
                    <div><dt>Facility</dt><dd><?= reservationResumeEscape($reservation["facility_name"]) ?></dd></div>
                    <div><dt>Court</dt><dd><?= reservationResumeEscape($reservation["court_no"]) ?></dd></div>
                    <div><dt>Date</dt><dd><?= reservationResumeEscape($courtDate) ?></dd></div>
                    <div><dt>Time</dt><dd><?= reservationResumeEscape($courtStartTime) ?>–<?= reservationResumeEscape($courtEndTime) ?></dd></div>
                    <div><dt>Duration</dt><dd><?= reservationResumeEscape($reservationDurationLabel) ?></dd></div>
                    <div><dt>Slots</dt><dd><?= count($reservationSlots) ?></dd></div>
                    <div><dt>Subtotal</dt><dd>₱<?= number_format((float) $reservationTotal, 2) ?></dd></div>
                </dl>

                <p class="reservation-resume-message" role="status" aria-live="polite" hidden></p>

                <?php if ($isPlayer): ?>
                    <div class="reservation-resume-actions">
                        <button
                            class="player-primary-button"
                            id="confirmReservationButton"
                            type="button"
                            data-schedule-ids="<?= reservationResumeEscape(json_encode($pendingScheduleIds)) ?>"
                        >Confirm and reserve time</button>
                        <a class="player-secondary-link" href="<?= reservationResumeEscape($courtUrl) ?>">Choose another time</a>
                    </div>
                <?php elseif ($isLoggedIn): ?>
                    <div class="reservation-resume-actions">
                        <a class="player-primary-link" href="logout.php">Log out and continue</a>
                        <a class="player-secondary-link" href="<?= reservationResumeEscape($courtUrl) ?>">Choose another time</a>
                    </div>
                <?php else: ?>
                    <div class="reservation-resume-actions">
                        <a class="player-primary-link" href="register.php">Create Account</a>
                        <a class="player-secondary-link" href="signin.php?return_to=reservation_resume.php">Log In</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
    <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>
