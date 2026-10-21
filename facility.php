<?php
require_once __DIR__ . "/includes/backend-api/config.php";

$municipalities = [
    "Abucay", "Bagac", "Balanga City", "Dinalupihan", "Hermosa", "Limay",
    "Mariveles", "Morong", "Orani", "Orion", "Pilar", "Samal",
];
$municipality = is_string($_GET["municipality"] ?? null) ? $_GET["municipality"] : "";
$sport = is_string($_GET["sport"] ?? null) ? $_GET["sport"] : "";
$search = is_string($_GET["search"] ?? null) ? trim($_GET["search"]) : "";
if (!in_array($municipality, $municipalities, true)) {
    $municipality = "";
}
if (!in_array($sport, ["Badminton", "Pickleball"], true)) {
    $sport = "";
}

$conditions = ["NOT EXISTS (SELECT 1 FROM admins a WHERE a.facility_id = f.facility_id AND a.status <> 'Active')"];
$values = [];
$types = "";
if ($municipality !== "") {
    $conditions[] = "f.municipality = ?";
    $values[] = $municipality;
    $types .= "s";
}
if ($sport !== "") {
    $conditions[] = "(f.sport_type = ? OR f.sport_type = 'Both')";
    $values[] = $sport;
    $types .= "s";
}
if ($search !== "") {
    $conditions[] = "f.facility_name LIKE ?";
    $values[] = "%" . $search . "%";
    $types .= "s";
}

$stmt = $conn->prepare(
    "SELECT f.facility_id, f.facility_name, f.address, f.municipality, f.sport_type,
            f.opening_time, f.closing_time, f.facility_img, rates.lowest_rate
     FROM facilities f
     LEFT JOIN (
         SELECT facility_id, MIN(court_rate) AS lowest_rate
         FROM court_details
         WHERE court_status = 'Available'
         GROUP BY facility_id
     ) rates ON rates.facility_id = f.facility_id
     WHERE " . implode(" AND ", $conditions) . "
     ORDER BY f.facility_name ASC"
);
$bindParams = [$types];
foreach ($values as $index => $value) {
    $bindParams[] = &$values[$index];
}
if ($values) {
    call_user_func_array([$stmt, "bind_param"], $bindParams);
}
$stmt->execute();
$result = $stmt->get_result();
$facilities = [];
while ($row = $result->fetch_assoc()) {
    $facilities[] = $row;
}
$stmt->close();
$conn->close();

function facilityPageEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function facilityPageTime($value)
{
    return $value ? date("g:i A", strtotime($value)) : "Hours not set";
}

function facilityPageIsOpen($openingTime, $closingTime)
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
    <title>Find a court | CourtConnect</title>
</head>
<body class="player-ui">
    <?php include 'includes/navigation/header.php'; ?>
    <main class="player-page">
        <header class="player-page-heading">
            <div>
                <p class="player-eyebrow">COURTCONNECT BATAAN</p>
                <h1>Find a court to play</h1>
                <p>Find a facility and choose a court that fits your game.</p>
            </div>
            <strong class="player-result-count"><?= count($facilities) ?> facilities</strong>
        </header>

        <form class="player-filter-bar facility-filters" method="get" action="facility.php" id="facilityFilters">
            <label>
                <span>Sport</span>
                <select name="sport" onchange="this.form.submit()">
                    <option value="">All sports</option>
                    <option value="Badminton" <?= $sport === "Badminton" ? "selected" : "" ?>>Badminton</option>
                    <option value="Pickleball" <?= $sport === "Pickleball" ? "selected" : "" ?>>Pickleball</option>
                </select>
            </label>
            <label>
                <span>Municipality</span>
                <select name="municipality" onchange="this.form.submit()">
                    <option value="">All municipalities</option>
                    <?php foreach ($municipalities as $option): ?>
                        <option value="<?= facilityPageEscape($option) ?>" <?= $municipality === $option ? "selected" : "" ?>><?= facilityPageEscape($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="player-filter-search">
                <span>Facility</span>
                <input type="search" name="search" value="<?= facilityPageEscape($search) ?>" placeholder="Search facility name">
            </label>
            <a class="player-clear-link" href="facility.php">Clear</a>
        </form>

        <?php if (!$facilities): ?>
            <div class="player-empty-state">
                <h2>No facilities match these filters</h2>
                <p>Try another municipality or sport.</p>
                <a class="player-primary-link" href="facility.php">Clear filters</a>
            </div>
        <?php else: ?>
            <section class="facility-grid" aria-label="Available facilities">
                <?php foreach ($facilities as $facility): ?>
                    <article class="facility-card">
                        <div class="facility-card-media">
                            <?php if (!empty($facility["facility_img"])): ?>
                                <img class="facility-card-image is-loading" src="uploads/facility/<?= rawurlencode(basename($facility["facility_img"])) ?>" alt="<?= facilityPageEscape($facility["facility_name"]) ?>" loading="lazy">
                                <div class="facility-image-fallback" aria-hidden="true">Facility photo unavailable</div>
                            <?php else: ?>
                                <div class="facility-image-fallback" aria-hidden="true">Facility photo unavailable</div>
                            <?php endif; ?>
                        </div>
                        <div class="facility-card-content">
                            <div class="facility-card-title-row">
                                <h2><?= facilityPageEscape($facility["facility_name"]) ?></h2>
                                <div class="facility-card-tags">
                                    <span class="sport-badge"><?= facilityPageEscape($facility["sport_type"] === "Both" ? "Pickleball & Badminton" : $facility["sport_type"]) ?></span>
                                    <span class="facility-open-badge <?= facilityPageIsOpen($facility["opening_time"], $facility["closing_time"]) ? "is-open" : "is-closed" ?>"><?= facilityPageIsOpen($facility["opening_time"], $facility["closing_time"]) ? "Open now" : "Closed" ?></span>
                                </div>
                            </div>
                            <p class="facility-location"><?= facilityPageEscape($facility["municipality"] ?: "Bataan") ?>, Bataan</p>
                            <p class="facility-hours"><?= facilityPageTime($facility["opening_time"]) ?> - <?= facilityPageTime($facility["closing_time"]) ?></p>
                            <p class="facility-rate">
                                <?php if ($facility["lowest_rate"] !== null): ?>
                                    <span>From</span> ₱<?= number_format((float) $facility["lowest_rate"], 0) ?> <span>/ hour</span>
                                <?php else: ?>
                                    <span>Rates unavailable</span>
                                <?php endif; ?>
                            </p>
                            <a class="player-primary-link" href="courts.php?facility_id=<?= (int) $facility["facility_id"] ?>">View courts</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <section class="facility-owner-banner">
            <div>
                <p class="player-eyebrow">FOR FACILITY OWNERS</p>
                <h2>Own a court?</h2>
                <p>List your facility so players around Bataan can find and book your courts.</p>
            </div>
            <div class="facility-owner-actions">
                <a class="player-primary-link" href="adminregister.php">List your facility</a>
                <a class="player-text-link" href="about.php">Learn more</a>
            </div>
        </section>
    </main>
    <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>