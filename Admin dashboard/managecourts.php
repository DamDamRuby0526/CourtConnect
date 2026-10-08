<?php
require_once __DIR__ . "/../includes/backend-api/config.php";

$ownerId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);

if (!$ownerId || !$facilityId || (int) ($_SESSION["role_id"] ?? 0) !== 3) {
    header("Location: ../adminsignin.php");
    exit();
}

$facilityStmt = $conn->prepare(
    "SELECT f.facility_id, f.facility_name, f.opening_time, f.closing_time
     FROM facilities f
     INNER JOIN admins a ON a.facility_id = f.facility_id
     INNER JOIN users u ON u.user_id = a.user_id
    WHERE f.facility_id = ? AND u.user_id = ? AND u.role_id = 3 AND u.is_verified = 1 AND a.status = 'Active'
     LIMIT 1"
);
$facilityStmt->bind_param("ii", $facilityId, $ownerId);
$facilityStmt->execute();
$facility = $facilityStmt->get_result()->fetch_assoc();
$facilityStmt->close();

if (!$facility) {
    header("Location: ../adminsignin.php");
    exit();
}

$ownerDashboardLayout = true;
$ownerNavPage = "courts";
$facilityName = $facility["facility_name"];

function ownerEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../includes/js/admin_managecourts.js" defer></script>
    <title>Court settings | CourtConnect</title>
</head>
<body class="owner-ui">
    <div class="owner-layout" id="ownerFacility" data-facility-id="<?= (int) $facilityId ?>">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="owner-content">
            <header class="owner-page-head">
                <div>
                    <p class="owner-breadcrumb">FACILITY OWNER <span>/</span> COURTS</p>
                    <h1>Facility settings</h1>
                    <p>Update operating hours, court pricing, and the details players see when reserving.</p>
                </div>
                <button type="button" id="add-btn" class="owner-primary-button"><span aria-hidden="true">+</span> Add court</button>
            </header>

            <section class="owner-hours-section" aria-labelledby="hoursHeading">
                <div class="owner-section-heading">
                    <div>
                        <h2 id="hoursHeading">Facility hours</h2>
                        <p>These hours set when your venue is open for reservations.</p>
                    </div>
                    <span class="owner-section-icon" aria-hidden="true">&#9719;</span>
                </div>
                <form id="facilityHoursForm" class="owner-hours-form">
                    <label for="opening_time">Opens</label>
                    <input type="time" id="opening_time" name="opening_time" value="<?= ownerEscape(substr((string) $facility["opening_time"], 0, 5)) ?>" required>
                    <label for="closing_time">Closes</label>
                    <input type="time" id="closing_time" name="closing_time" value="<?= ownerEscape(substr((string) $facility["closing_time"], 0, 5)) ?>" required>
                    <button type="submit" class="owner-secondary-button">Save hours</button>
                    <p id="hoursStatus" class="owner-form-status" role="status" aria-live="polite"></p>
                </form>
            </section>

            <section class="owner-courts-section" aria-labelledby="courtsHeading">
                <div class="owner-section-heading">
                    <div>
                        <h2 id="courtsHeading">Your courts</h2>
                        <p>Manage rates, play details, availability, and court photos.</p>
                    </div>
                    <span class="owner-court-count" id="courtCount">Loading courts</span>
                </div>
                <div class="owner-courts-grid" id="grid-container" aria-live="polite"></div>
            </section>
        </main>
    </div>

    <div class="modal-container owner-modal" id="modal_container" aria-hidden="true">
        <div class="modal owner-modal-panel" role="dialog" aria-modal="true" aria-labelledby="courtDialogTitle">
            <form id="courtForm" enctype="multipart/form-data">
                <div class="owner-modal-heading">
                    <div>
                        <p class="owner-breadcrumb">COURT DETAILS</p>
                        <h2 id="courtDialogTitle">Add a court</h2>
                    </div>
                </div>
                <div class="owner-form-grid">
                    <label for="court_no">Court number
                        <input type="number" id="court_no" name="court_no" min="1" step="1" required>
                    </label>
                    <label for="court_rate">Rate per hour (PHP)
                        <input type="number" id="court_rate" name="court_rate" min="1" step="1" required>
                    </label>
                    <label for="slot_duration">Reservation duration (minutes)
                        <input type="number" id="slot_duration" name="slot_duration" min="1" step="1" required>
                    </label>
                    <label for="court_status">Court availability
                        <select id="court_status" name="court_status" required>
                            <option value="Available">Available</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </label>
                    <label class="owner-wide-field" for="description">Court description
                        <textarea id="description" name="description" rows="3" maxlength="255" required></textarea>
                    </label>
                    <label class="owner-wide-field" for="court_img">Court photo
                        <input type="file" id="court_img" name="court_img" accept="image/jpeg,image/png,image/webp">
                        <small>JPG, PNG, or WEBP. Maximum 5 MB.</small>
                    </label>
                </div>
                <img id="court_img_preview" class="owner-image-preview" alt="Current court photo" hidden>
                <p id="courtFormStatus" class="owner-form-status" role="status" aria-live="polite"></p>
                <div class="owner-modal-actions">
                    <button type="button" class="owner-secondary-button cancel-btn">Cancel</button>
                    <button type="button" class="owner-primary-button save-btn">Save court</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>