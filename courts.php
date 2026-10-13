<?php
require_once __DIR__ . "/includes/backend-api/config.php";

$facilityId = filter_var($_GET["facility_id"] ?? null, FILTER_VALIDATE_INT);
if (!$facilityId || $facilityId < 1) {
    header("Location: facility.php");
    exit();
}

$facilityStmt = $conn->prepare(
    "SELECT f.facility_id, f.facility_name, f.facility_img, f.address, f.municipality, f.opening_time, f.closing_time
     FROM facilities f
     WHERE f.facility_id = ?
         AND NOT EXISTS (SELECT 1 FROM admins a WHERE a.facility_id = f.facility_id AND a.status <> 'Active')"
);
$facilityStmt->bind_param("i", $facilityId);
$facilityStmt->execute();
$facility = $facilityStmt->get_result()->fetch_assoc();
$facilityStmt->close();
if (!$facility) {
    http_response_code(404);
    $conn->close();
    exit("Facility not found.");
}

$courtsStmt = $conn->prepare(
    "SELECT court_id, court_no, description, court_img, court_rate, slot_duration, court_status
     FROM court_details
     WHERE facility_id = ?
     ORDER BY court_no ASC"
);
$courtsStmt->bind_param("i", $facilityId);
$courtsStmt->execute();
$courtsResult = $courtsStmt->get_result();
$courts = [];
while ($court = $courtsResult->fetch_assoc()) {
    $courts[] = $court;
}
$courtsStmt->close();
$conn->close();

function courtsPageEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function courtsPageTime($value)
{
    return $value ? date("g:i A", strtotime($value)) : "Hours not set";
}

$today = new DateTimeImmutable("today");
$maximumDate = $today->modify("+30 days");
$isPlayer = !empty($_SESSION["user_id"]) && (int) ($_SESSION["role_id"] ?? 0) === 1;
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
    <script src="includes/js/courts.js" defer></script>
    <title>Courts at <?= courtsPageEscape($facility["facility_name"]) ?> | CourtConnect</title>
</head>
<body class="player-ui">
    <?php include 'includes/navigation/header.php'; ?>
    <main class="player-page courts-page" data-facility-id="<?= (int) $facilityId ?>" data-logged-in="<?= $isPlayer ? "true" : "false" ?>">
        <nav class="player-breadcrumb" aria-label="Breadcrumb"><a href="facility.php">Find a court</a><span aria-hidden="true">/</span><span><?= courtsPageEscape($facility["facility_name"]) ?></span></nav>
        <header class="player-page-heading courts-page-heading">
            <div>
                <p class="player-eyebrow">CHOOSE YOUR COURT</p>
                <?php if (!empty($facility["facility_img"])): ?>
                    <img class="facility-detail-logo" src="uploads/facility/<?= rawurlencode(basename($facility["facility_img"])) ?>" alt="<?= courtsPageEscape($facility["facility_name"]) ?> logo">
                <?php endif; ?>
                <h1>Courts at <?= courtsPageEscape($facility["facility_name"]) ?></h1>
                <p><?= courtsPageEscape($facility["address"]) ?>, <?= courtsPageEscape($facility["municipality"]) ?></p>
                <p><?= courtsPageTime($facility["opening_time"]) ?> - <?= courtsPageTime($facility["closing_time"]) ?></p>
            </div>
            <a class="player-secondary-link" href="facility.php">All facilities</a>
        </header>

        <?php if (!$courts): ?>
            <div class="player-empty-state">
                <h2>No courts available yet</h2>
                <p>This facility has not added any courts.</p>
                <a class="player-primary-link" href="facility.php">Find another facility</a>
            </div>
        <?php else: ?>
            <section class="court-grid" aria-label="Courts at this facility">
                <?php foreach ($courts as $court): ?>
                    <?php $isMaintenance = $court["court_status"] === "Maintenance"; ?>
                    <article class="court-card" id="court-<?= (int) $court["court_id"] ?>">
                        <div class="court-card-media">
                            <?php if (!empty($court["court_img"])): ?>
                                <img class="court-card-image is-loading" src="uploads/court/<?= rawurlencode(basename($court["court_img"])) ?>" alt="Court <?= courtsPageEscape($court["court_no"]) ?>" loading="eager">
                                <div class="court-image-fallback" aria-hidden="true">Court photo unavailable</div>
                            <?php else: ?>
                                <div class="court-image-fallback" aria-hidden="true">Court photo unavailable</div>
                            <?php endif; ?>
                        </div>
                        <div class="court-card-content">
                            <div class="court-card-title-row">
                                <h2>Court <?= courtsPageEscape($court["court_no"]) ?></h2>
                                <span class="court-status-badge court-status-<?= $isMaintenance ? "maintenance" : "available" ?>"><?= courtsPageEscape($court["court_status"]) ?></span>
                            </div>
                            <p class="court-description"><?= courtsPageEscape($court["description"]) ?></p>
                            <div class="court-card-details">
                                <span>P<?= number_format((float) $court["court_rate"], 0) ?> / hour</span>
                                <span><?= (int) $court["slot_duration"] ?> min slots</span>
                            </div>
                            <?php if ($isMaintenance): ?>
                                <button class="player-primary-button" type="button" disabled>Unavailable</button>
                            <?php else: ?>
                                <button class="player-primary-button court-book-button" type="button" data-court-id="<?= (int) $court["court_id"] ?>">Book now</button>
                                <section class="court-booking-panel" hidden aria-label="Choose a booking time for Court <?= courtsPageEscape($court["court_no"]) ?>">
                                    <label class="court-date-label">
                                        <span>Date</span>
                                        <input class="court-date-input" type="date" value="<?= $today->format("Y-m-d") ?>" min="<?= $today->format("Y-m-d") ?>" max="<?= $maximumDate->format("Y-m-d") ?>">
                                    </label>
                                    <div class="court-slot-heading"><h3>Available times</h3><span class="court-slot-loading" hidden>Loading...</span></div>
                                    <div class="court-slot-list" aria-live="polite"></div>
                                    <div class="court-slot-empty" hidden>
                                        <span>No available times on this date.</span>
                                        <button class="court-next-date" type="button" hidden></button>
                                    </div>
                                    <dl class="court-booking-summary">
                                        <div><dt>Court</dt><dd><?= courtsPageEscape($court["court_no"]) ?></dd></div>
                                        <div><dt>Date</dt><dd class="summary-date">Choose a date</dd></div>
                                        <div><dt>Time</dt><dd class="summary-time">Choose a time</dd></div>
                                        <div><dt>Duration</dt><dd class="summary-duration"><?= (int) $court["slot_duration"] ?> min</dd></div>
                                        <div><dt>Total</dt><dd class="summary-total">P0</dd></div>
                                    </dl>
                                    <button class="player-primary-button court-continue-button" type="button" disabled data-court-rate="<?= (int) $court["court_rate"] ?>" data-slot-duration="<?= (int) $court["slot_duration"] ?>">Continue to checkout</button>
                                </section>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
    <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>