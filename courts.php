<?php
require_once __DIR__ . "/includes/backend-api/config.php";

$facilityId = filter_var($_GET["facility_id"] ?? null, FILTER_VALIDATE_INT);
if (!$facilityId || $facilityId < 1) {
    header("Location: facility.php");
    exit();
}

$facilityStmt = $conn->prepare(
    "SELECT f.facility_id, f.facility_name, f.facility_img, f.address, f.municipality,
            f.opening_time, f.closing_time, f.sport_type
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

function courtsPageHours($minutes)
{
    return rtrim(rtrim(number_format((float) $minutes / 60, 2, ".", ""), "0"), ".");
}

$today = new DateTimeImmutable("today");
$maximumDate = $today->modify("+30 days");
$weekDates = [];
for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
    $weekDates[] = $today->modify("+$dayOffset days");
}
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
    <title><?= courtsPageEscape($facility["facility_name"]) ?> | CourtConnect</title>
</head>
<body class="player-ui">
    <?php include 'includes/navigation/header.php'; ?>
    <main class="player-page courts-page" data-facility-id="<?= (int) $facilityId ?>">
        <header class="player-page-heading courts-page-heading">
            <div>
                <div class="courts-facility-heading">
                    <span class="courts-facility-logo" aria-hidden="true">
                        <span class="courts-facility-logo-fallback"><?= courtsPageEscape(mb_strtoupper(mb_substr($facility["facility_name"], 0, 1, "UTF-8"), "UTF-8")) ?></span>
                        <?php if (!empty($facility["facility_img"])): ?>
                            <img class="facility-detail-logo" src="uploads/facility/<?= rawurlencode(basename($facility["facility_img"])) ?>" alt="<?= courtsPageEscape($facility["facility_name"]) ?> logo">
                        <?php endif; ?>
                    </span>
                    <div>
                        <p class="player-eyebrow">BOOK A COURT</p>
                        <h1><?= courtsPageEscape($facility["facility_name"]) ?></h1>
                        <p><?= courtsPageEscape($facility["address"]) ?>, <?= courtsPageEscape($facility["municipality"]) ?> <span class="courts-sport-tag"><?= courtsPageEscape($facility["sport_type"] === "Both" ? "Pickleball & Badminton" : $facility["sport_type"]) ?></span></p>
                        <p class="courts-hours"><span aria-hidden="true">◷</span> <?= courtsPageTime($facility["opening_time"]) ?> – <?= courtsPageTime($facility["closing_time"]) ?></p>
                    </div>
                </div>
            </div>
        </header>

        <?php if (!$courts): ?>
            <div class="player-empty-state">
                <h2>No courts available yet</h2>
                <p>This facility has not added any courts.</p>
                <a class="player-primary-link" href="facility.php">Find another facility</a>
            </div>
        <?php else: ?>
            <section class="court-control-bar" aria-label="Reservation date and filters">
                <div class="court-date-controls">
                    <div class="court-control-heading">
                        <label for="courtDateInput">Choose a date</label>
                        <input id="courtDateInput" type="date" value="<?= $today->format("Y-m-d") ?>" min="<?= $today->format("Y-m-d") ?>" max="<?= $maximumDate->format("Y-m-d") ?>">
                    </div>
                    <div class="court-date-strip" role="group" aria-label="Upcoming dates">
                        <?php foreach ($weekDates as $dateIndex => $day): ?>
                            <button class="court-date-pill<?= $dateIndex === 0 ? " is-active" : "" ?>" type="button" data-date="<?= $day->format("Y-m-d") ?>" aria-pressed="<?= $dateIndex === 0 ? "true" : "false" ?>">
                                <span><?= $dateIndex === 0 ? "Today" : courtsPageEscape($day->format("D")) ?></span>
                                <strong><?= $day->format("j") ?></strong>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="court-filter-controls">
                    <label class="court-price-control" for="courtPriceFilter">
                        <span>Maximum rate</span>
                        <select id="courtPriceFilter">
                            <option value="">Any price</option>
                            <option value="100">Up to ₱100 / hour</option>
                            <option value="250">Up to ₱250 / hour</option>
                            <option value="500">Up to ₱500 / hour</option>
                            <option value="1000">Up to ₱1,000 / hour</option>
                        </select>
                    </label>
                    <label class="court-availability-control" for="courtAvailabilityFilter">
                        <span>Availability</span>
                        <select id="courtAvailabilityFilter">
                            <option value="all">All courts</option>
                            <option value="available">Available only</option>
                        </select>
                    </label>
                </div>
                <p class="court-filter-note">Sport: <?= courtsPageEscape($facility["sport_type"]) ?> · Rates shown per hour · No added booking fees</p>
            </section>

            <p class="court-hover-preview" id="courtHoverPreview" role="status" aria-live="polite" hidden></p>
            <section class="court-schedule-view" aria-label="Court availability schedule">
                <div class="court-schedule-heading">
                    <div><h2>Choose your time</h2><p>Select an available time, then click adjacent slots to add hours. Click an end slot to remove it.</p></div>
                    <span class="court-schedule-date" aria-live="polite"><?= $today->format("D, M j") ?></span>
                </div>
                <div class="court-schedule-loading" role="status" aria-live="polite">Checking court availability…</div>
                <div class="court-schedule-empty" hidden>No schedules are listed for this date. Try another day.</div>
                <div class="court-schedule-scroll" hidden>
                    <div class="court-schedule-grid" role="group" aria-label="Court schedule lanes"></div>
                </div>
            </section>

            <div class="court-data-list" hidden>
                <?php foreach ($courts as $court): ?>
                    <?php $isMaintenance = $court["court_status"] === "Maintenance"; ?>
                    <article
                        class="court-card court-modern-card"
                        id="court-<?= (int) $court["court_id"] ?>"
                        data-court-id="<?= (int) $court["court_id"] ?>"
                        data-court-no="<?= courtsPageEscape($court["court_no"]) ?>"
                        data-facility-name="<?= courtsPageEscape($facility["facility_name"]) ?>"
                        data-court-rate="<?= (int) $court["court_rate"] ?>"
                        data-slot-duration="<?= (int) $court["slot_duration"] ?>"
                        data-court-status="<?= courtsPageEscape($court["court_status"]) ?>"
                        data-court-description="<?= courtsPageEscape($court["description"] ?: "") ?>"
                    >
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
                            <p class="court-description"><?= courtsPageEscape($court["description"] ?: "Court at " . $facility["facility_name"]) ?></p>
                            <div class="court-card-details">
                                <strong>₱<?= number_format((float) $court["court_rate"], 0) ?><small> / hour</small></strong>
                                <span><?= courtsPageEscape(courtsPageHours($court["slot_duration"])) ?>-hour slots</span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <aside class="court-checkout-bar" id="courtCheckoutBar" aria-live="polite" hidden>
                <div class="court-checkout-selection">
                    <span class="court-checkout-eyebrow">YOUR SELECTION</span>
                    <strong id="courtCheckoutTitle">Choose a court time</strong>
                    <span id="courtCheckoutDetails"></span>
                </div>
                <div class="court-checkout-total">
                    <span id="courtCheckoutBreakdown">Select a time to see the price</span>
                    <small class="court-checkout-fees">No additional fees</small>
                    <strong id="courtCheckoutTotal">₱0</strong>
                </div>
                <button class="player-primary-button" id="courtProceedButton" type="button" disabled>Book Now</button>
            </aside>
        <?php endif; ?>
    </main>
    <div class="court-image-lightbox" id="courtImageLightbox" aria-hidden="true" hidden>
        <div class="court-image-lightbox-panel" role="dialog" aria-modal="true" aria-label="Court image preview">
            <button class="court-image-lightbox-close" id="courtImageLightboxClose" type="button" aria-label="Close image preview">&times;</button>
            <img id="courtImageLightboxImage" src="" alt="">
        </div>
    </div>
    <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>