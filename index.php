<?php
require_once __DIR__ . "/includes/backend-api/config.php";

$municipalities = [
    "Abucay", "Bagac", "Balanga City", "Dinalupihan", "Hermosa", "Limay",
    "Mariveles", "Morong", "Orani", "Orion", "Pilar", "Samal",
];
$activeFacilityCondition = "EXISTS (
    SELECT 1 FROM admins active_owner
    WHERE active_owner.facility_id = f.facility_id AND active_owner.status = 'Active'
) AND NOT EXISTS (
    SELECT 1 FROM admins inactive_owner
    WHERE inactive_owner.facility_id = f.facility_id AND inactive_owner.status <> 'Active'
)";

$facilityCountStmt = $conn->prepare(
    "SELECT COUNT(*) AS facility_count FROM facilities f WHERE $activeFacilityCondition"
);
$facilityCountStmt->execute();
$activeFacilityCount = (int) ($facilityCountStmt->get_result()->fetch_assoc()["facility_count"] ?? 0);
$facilityCountStmt->close();

$availableCourtStmt = $conn->prepare(
    "SELECT COUNT(DISTINCT cd.court_id) AS court_count
     FROM court_details cd
     INNER JOIN facilities f ON f.facility_id = cd.facility_id
     WHERE cd.court_status = 'Available' AND $activeFacilityCondition"
);
$availableCourtStmt->execute();
$availableCourtCount = (int) ($availableCourtStmt->get_result()->fetch_assoc()["court_count"] ?? 0);
$availableCourtStmt->close();

$municipalityCountStmt = $conn->prepare(
    "SELECT COUNT(DISTINCT NULLIF(TRIM(f.municipality), '')) AS municipality_count
     FROM facilities f
     WHERE $activeFacilityCondition"
);
$municipalityCountStmt->execute();
$municipalityCount = (int) ($municipalityCountStmt->get_result()->fetch_assoc()["municipality_count"] ?? 0);
$municipalityCountStmt->close();

$featuredStmt = $conn->prepare(
    "SELECT f.facility_id, f.facility_name, f.municipality, f.sport_type,
            f.opening_time, f.closing_time, f.facility_img, rates.lowest_rate
     FROM facilities f
     INNER JOIN (
         SELECT facility_id, MIN(court_rate) AS lowest_rate
         FROM court_details
         WHERE court_status = 'Available'
         GROUP BY facility_id
     ) rates ON rates.facility_id = f.facility_id
     WHERE $activeFacilityCondition
     ORDER BY f.facility_id DESC
     LIMIT 4"
);
$featuredStmt->execute();
$featuredResult = $featuredStmt->get_result();
$featuredFacilities = [];
while ($facility = $featuredResult->fetch_assoc()) {
    $facility["is_open"] = homePageIsOpen($facility["opening_time"], $facility["closing_time"]);
    $featuredFacilities[] = $facility;
}
$featuredStmt->close();
$conn->close();

function homePageEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function homePageIsOpen($openingTime, $closingTime)
{
    if (!$openingTime || !$closingTime) {
        return false;
    }
    $currentTime = date("H:i:s");
    if ($openingTime <= $closingTime) {
        return $currentTime >= $openingTime && $currentTime < $closingTime;
    }
    return $currentTime >= $openingTime || $currentTime < $closingTime;
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
    <script src="includes/js/facilities.js" defer></script>
    <title>CourtConnect Bataan | Find a court to play</title>
</head>
<body class="home-page">
    <?php include 'includes/navigation/header.php'; ?>

    <main>
        <section class="home-hero" aria-labelledby="homeTitle">
            <div class="home-hero-copy">
                <p class="home-eyebrow">YOUR GAME. YOUR TIME. YOUR COURT.</p>
                <h1 id="homeTitle">Find a court.<br><span>Make your next match.</span></h1>
                <p class="home-hero-description">Discover badminton and pickleball courts around Bataan, then reserve a time that works for you.</p>
                <form class="home-search" action="facility.php" method="get" aria-label="Search courts">
                    <label>
                        <span>Sport</span>
                        <select name="sport">
                            <option value="">All sports</option>
                            <option value="Badminton">Badminton</option>
                            <option value="Pickleball">Pickleball</option>
                        </select>
                    </label>
                    <label>
                        <span>Municipality</span>
                        <select name="municipality">
                            <option value="">All municipalities</option>
                            <?php foreach ($municipalities as $municipalityOption): ?>
                                <option value="<?= homePageEscape($municipalityOption) ?>"><?= homePageEscape($municipalityOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="home-primary-button" type="submit">Search courts</button>
                </form>
                <a class="home-text-link" href="#how-it-works">How it works <span aria-hidden="true">&#8595;</span></a>
            </div>
            <div class="home-hero-art">
                <img src="uploads/badminton-pickleball-court.svg" alt="Badminton and pickleball court illustration">
                <span class="home-art-caption" aria-hidden="true">BATAAN / FIND YOUR COURT</span>
            </div>
        </section>

        <section class="home-section home-how" id="how-it-works" aria-labelledby="howTitle">
            <div class="home-section-heading">
                <p class="home-eyebrow">THREE STEPS TO GAME TIME</p>
                <h2 id="howTitle">How it works</h2>
                <p>Find a local court, choose your time, and send your payment for confirmation.</p>
            </div>
            <div class="home-steps">
                <article class="home-step">
                    <span>01</span>
                    <h3>Find a court</h3>
                    <p>Browse active facilities by sport and municipality.</p>
                </article>
                <article class="home-step">
                    <span>02</span>
                    <h3>Pick a time</h3>
                    <p>Choose an available slot and hold it while you check out.</p>
                </article>
                <article class="home-step">
                    <span>03</span>
                    <h3>Pay and get confirmed</h3>
                    <p>Pay with the facility's GCash QR and upload your receipt for confirmation.</p>
                </article>
            </div>
        </section>

        <?php if ($featuredFacilities): ?>
            <section class="home-section home-featured" aria-labelledby="featuredTitle">
                <div class="home-section-heading home-section-heading-row">
                    <div>
                        <p class="home-eyebrow">PLAY CLOSE TO HOME</p>
                        <h2 id="featuredTitle">Featured facilities</h2>
                        <p>Explore courts listed by facility owners around Bataan.</p>
                    </div>
                    <a class="home-primary-button" href="facility.php">See all facilities</a>
                </div>
                <div class="home-featured-grid">
                    <?php foreach ($featuredFacilities as $facility): ?>
                        <article class="facility-card home-facility-card">
                            <div class="facility-card-media">
                                <?php if (!empty($facility["facility_img"])): ?>
                                    <img class="facility-card-image is-loading" src="uploads/facility/<?= rawurlencode(basename($facility["facility_img"])) ?>" alt="<?= homePageEscape($facility["facility_name"]) ?>" loading="lazy">
                                    <div class="facility-image-fallback" aria-hidden="true">Facility photo unavailable</div>
                                <?php else: ?>
                                    <div class="facility-image-fallback" aria-hidden="true">Facility photo unavailable</div>
                                <?php endif; ?>
                            </div>
                            <div class="facility-card-content">
                                <div class="facility-card-title-row">
                                    <h3><?= homePageEscape($facility["facility_name"]) ?></h3>
                                    <div class="facility-card-tags">
                                        <span class="sport-badge"><?= homePageEscape($facility["sport_type"]) ?></span>
                                        <span class="facility-open-badge <?= (int) $facility["is_open"] === 1 ? "is-open" : "is-closed" ?>"><?= (int) $facility["is_open"] === 1 ? "Open now" : "Closed" ?></span>
                                    </div>
                                </div>
                                <p class="facility-location"><?= homePageEscape($facility["municipality"] ?: "Bataan") ?>, Bataan</p>
                                <p class="facility-rate"><span>From</span> P<?= number_format((float) $facility["lowest_rate"], 0) ?> <span>/ hour</span></p>
                                <a class="home-outline-link" href="courts.php?facility_id=<?= (int) $facility["facility_id"] ?>">View courts</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="home-section home-sports" aria-labelledby="sportsTitle">
            <div class="home-section-heading">
                <p class="home-eyebrow">CHOOSE YOUR GAME</p>
                <h2 id="sportsTitle">Browse by sport</h2>
                <p>Start with the game you want to play.</p>
            </div>
            <div class="home-sport-grid">
                <a class="home-sport-tile home-sport-badminton" href="facility.php?sport=Badminton">
                    <span class="home-sport-art home-badminton-art" aria-hidden="true"></span>
                    <span class="home-sport-tile-copy"><strong>Badminton</strong><span>Find badminton courts</span></span>
                    <span class="home-sport-arrow" aria-hidden="true">&#8594;</span>
                </a>
                <a class="home-sport-tile home-sport-pickleball" href="facility.php?sport=Pickleball">
                    <span class="home-sport-art home-pickleball-art" aria-hidden="true"></span>
                    <span class="home-sport-tile-copy"><strong>Pickleball</strong><span>Find pickleball courts</span></span>
                    <span class="home-sport-arrow" aria-hidden="true">&#8594;</span>
                </a>
            </div>
        </section>

        <section class="home-owner-band" aria-labelledby="ownerTitle">
            <div>
                <p class="home-eyebrow">FOR FACILITY OWNERS</p>
                <h2 id="ownerTitle">Run your courts in one place</h2>
                <p>Manage bookings, walk-ins, payments, and schedules from your facility workspace.</p>
            </div>
            <div class="home-owner-details">
                <ul>
                    <li>Online bookings</li>
                    <li>Walk-in booking in seconds</li>
                    <li>GCash QR payments</li>
                    <li>Schedules and reports</li>
                </ul>
                <div class="home-owner-actions">
                    <a class="home-primary-button" href="adminregister.php">Register your facility</a>
                    <a class="home-outline-link home-outline-light" href="adminsignin.php">Sign in as facility</a>
                </div>
            </div>
        </section>

        <section class="home-final-cta" aria-labelledby="finalCtaTitle">
            <div>
                <p class="home-eyebrow">MAKE ROOM FOR YOUR NEXT MATCH</p>
                <h2 id="finalCtaTitle">Ready to play?</h2>
            </div>
            <div class="home-final-actions">
                <a class="home-primary-button" href="register.php">Create an account</a>
                <a class="home-outline-link" href="facility.php">Find a court</a>
            </div>
        </section>
    </main>

    <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>