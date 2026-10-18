<?php
require_once __DIR__ . "/includes/backend-api/config.php";

$municipalities = [
    "Abucay", "Bagac", "Balanga City", "Dinalupihan", "Hermosa", "Limay",
    "Mariveles", "Morong", "Orani", "Orion", "Pilar", "Samal",
];
$homeHeroSlides = [
    [
        "file" => "shaflers-court.png",
        "alt" => "Indoor Shaflers court with green pickleball playing surfaces",
    ],
    [
        "file" => "court31-pickleball.png",
        "alt" => "Blue pickleball courts at Court31",
    ],
    [
        "file" => "racquet-recreational-hub.png",
        "alt" => "Pickleball and badminton courts at Racquet Recreational Hub",
    ],
];
$activeFacilityCondition = "EXISTS (
    SELECT 1 FROM admins active_owner
    WHERE active_owner.facility_id = f.facility_id AND active_owner.status = 'Active'
) AND NOT EXISTS (
    SELECT 1 FROM admins inactive_owner
    WHERE inactive_owner.facility_id = f.facility_id AND inactive_owner.status <> 'Active'
)";

$featuredStmt = $conn->prepare(
    "SELECT f.facility_id, f.facility_name, f.municipality, f.sport_type,
            f.opening_time, f.closing_time, f.facility_img, rates.lowest_rate,
            (SELECT court.court_img
             FROM court_details court
             WHERE court.facility_id = f.facility_id
                 AND court.court_status = 'Available'
                 AND court.court_img IS NOT NULL
                 AND court.court_img <> ''
             ORDER BY court.court_id ASC
             LIMIT 1) AS court_image
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
$isLoggedIn = !empty($_SESSION["logged_in"]);

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
                <p class="home-hero-description">Discover badminton and pickleball facilities across Bataan. Compare courts, check availability, and reserve a time that works for you.</p>
                <div class="home-hero-actions">
                    <a class="home-primary-button" href="facility.php">Find a Court <span aria-hidden="true">→</span></a>
                    <a class="home-text-link" href="#how-it-works">How It Works <span aria-hidden="true">↓</span></a>
                </div>
                <form class="home-search" action="facility.php" method="get" aria-label="Find a court">
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
                    <button class="home-primary-button" type="submit">Find Courts <span aria-hidden="true">→</span></button>
                </form>
            </div>
            <div class="home-hero-art" data-court-slideshow aria-label="Court photos">
                <?php foreach ($homeHeroSlides as $slideIndex => $slide): ?>
                    <img
                        class="home-hero-slide<?= $slideIndex === 0 ? " is-active" : "" ?>"
                        src="uploads/court/<?= homePageEscape($slide["file"]) ?>"
                        alt="<?= homePageEscape($slide["alt"]) ?>"
                        aria-hidden="<?= $slideIndex === 0 ? "false" : "true" ?>"
                    >
                <?php endforeach; ?>
                <div class="home-art-caption">
                    <span>COURTCONNECT / BATAAN</span>
                    <strong>Made for your next match.</strong>
                </div>
                <div class="home-slideshow-controls" aria-label="Court photo controls">
                    <button type="button" data-slide-previous aria-label="Previous court photo">‹</button>
                    <button type="button" data-slide-next aria-label="Next court photo">›</button>
                </div>
            </div>
        </section>

        <section class="home-section home-how" id="how-it-works" aria-labelledby="howTitle">
            <div class="home-section-heading">
                <p class="home-eyebrow">THREE STEPS TO GAME TIME</p>
                <h2 id="howTitle">How it works</h2>
                <p>A clear path from finding a venue to submitting your reservation.</p>
            </div>
            <div class="home-steps">
                <article class="home-step">
                    <span class="home-step-number">01</span>
                    <span class="home-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.7"/><path d="m16 16 4.2 4.2M8 10.8h5.6M10.8 8v5.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                    <h3>Find a Court</h3>
                    <p>Browse badminton and pickleball facilities by sport and municipality.</p>
                </article>
                <article class="home-step">
                    <span class="home-step-number">02</span>
                    <span class="home-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5" width="17" height="16" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M7.5 3v4M16.5 3v4M3.5 9.5h17M8 13h3M8 16.5h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                    <h3>Choose Your Schedule</h3>
                    <p>Select an available court and reservation time that suits you.</p>
                </article>
                <article class="home-step">
                    <span class="home-step-number">03</span>
                    <span class="home-step-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 1 4 16.5v-11Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M4 6h13M8 12h8M8 15h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                    <h3>Submit Your Payment</h3>
                    <p>Follow the facility’s payment instructions and upload your receipt for verification.</p>
                </article>
            </div>
        </section>

        <section class="home-section home-featured" aria-labelledby="featuredTitle">
                <div class="home-section-heading home-section-heading-row">
                    <div>
                        <p class="home-eyebrow">PLAY CLOSE TO HOME</p>
                        <h2 id="featuredTitle">Find Your Next Court</h2>
                        <p>Explore badminton and pickleball facilities around Bataan.</p>
                    </div>
                    <a class="home-section-link" href="facility.php">View All Facilities <span aria-hidden="true">→</span></a>
                </div>
                <?php if ($featuredFacilities): ?>
                    <div class="home-featured-grid">
                    <?php foreach ($featuredFacilities as $facility): ?>
                        <article class="facility-card home-facility-card">
                            <div class="facility-card-media">
                                <?php if (!empty($facility["court_image"])): ?>
                                    <img class="facility-card-image is-loading" src="uploads/court/<?= rawurlencode(basename($facility["court_image"])) ?>" alt="Court at <?= homePageEscape($facility["facility_name"]) ?>" loading="lazy">
                                    <div class="facility-image-fallback" aria-hidden="true">
                                        <?php if (!empty($facility["facility_img"])): ?>
                                            <img src="uploads/facility/<?= rawurlencode(basename($facility["facility_img"])) ?>" alt="">
                                        <?php endif; ?>
                                        <span>Court photo unavailable</span>
                                    </div>
                                <?php elseif (!empty($facility["facility_img"])): ?>
                                    <div class="facility-image-fallback home-facility-placeholder">
                                        <img src="uploads/facility/<?= rawurlencode(basename($facility["facility_img"])) ?>" alt="">
                                        <span>Court photo not provided</span>
                                    </div>
                                <?php else: ?>
                                    <div class="facility-image-fallback home-facility-placeholder"><span>Court photo not provided</span></div>
                                <?php endif; ?>
                            </div>
                            <div class="facility-card-content">
                                <div class="facility-card-title-row">
                                    <h3><?= homePageEscape($facility["facility_name"]) ?></h3>
                                    <div class="facility-card-tags">
                                        <span class="sport-badge"><?= homePageEscape($facility["sport_type"] === "Both" ? "Pickleball & Badminton" : $facility["sport_type"]) ?></span>
                                        <span class="facility-open-badge <?= (int) $facility["is_open"] === 1 ? "is-open" : "is-closed" ?>"><?= (int) $facility["is_open"] === 1 ? "Open now" : "Closed" ?></span>
                                    </div>
                                </div>
                                <p class="facility-location"><?= homePageEscape($facility["municipality"] ?: "Bataan") ?>, Bataan</p>
                                <p class="facility-rate"><span>From</span> ₱<?= number_format((float) $facility["lowest_rate"], 0) ?> <span>/ hour</span></p>
                                <a class="home-outline-link" href="courts.php?facility_id=<?= (int) $facility["facility_id"] ?>">View Courts <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="home-facility-empty">
                        <strong>New courts are on their way.</strong>
                        <p>There are no participating facilities with available courts to feature right now. Browse the directory to see current listings.</p>
                        <a href="facility.php">Browse all facilities <span aria-hidden="true">→</span></a>
                    </div>
                <?php endif; ?>
        </section>

        <section class="home-benefits" aria-labelledby="benefitsTitle">
            <div class="home-benefits-heading">
                <p class="home-eyebrow">A BETTER WAY TO PLAN GAME TIME</p>
                <h2 id="benefitsTitle">Why Use CourtConnect?</h2>
                <p>Useful booking information, together in one place.</p>
            </div>
            <div class="home-benefit-list">
                <article><span>01</span><div><h3>Discover Local Courts</h3><p>Find participating badminton and pickleball venues across Bataan.</p></div></article>
                <article><span>02</span><div><h3>Check Court Availability</h3><p>Review listed schedules before making a reservation.</p></div></article>
                <article><span>03</span><div><h3>Manage Your Bookings</h3><p>Access reservation details and booking history online.</p></div></article>
                <article><span>04</span><div><h3>Clear Payment Status</h3><p>Check the status of your submitted payment receipt.</p></div></article>
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
                </div>
            </div>
        </section>

        <?php if (!$isLoggedIn): ?>
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
        <?php endif; ?>
    </main>

    <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>