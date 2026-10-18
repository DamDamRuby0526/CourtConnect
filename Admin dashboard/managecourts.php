<?php
require_once __DIR__ . "/../includes/backend-api/config.php";
require_once __DIR__ . "/../includes/admin-php/facility_owner_page_guard.php";

$ownerId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);

if (!$ownerId || !$facilityId || (int) ($_SESSION["role_id"] ?? 0) !== 3) {
    header("Location: ../signin.php");
    exit();
}

$facilityStmt = $conn->prepare(
    "SELECT f.facility_id, f.facility_name, f.facility_img, f.qr_img, f.gcash_number, f.opening_time, f.closing_time
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
    header("Location: ../signin.php");
    exit();
}

$ownerDashboardLayout = true;
$ownerNavPage = "facility";
$facilityName = $facility["facility_name"];
$facilityLogo = $facility["facility_img"];

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
    <link rel="stylesheet" href="../includes/css/styles.css?v=20261018-schedule-layout-1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../includes/js/admin_managecourts.js?v=20261018-schedule-layout-1" defer></script>
    <title>Court settings | CourtConnect</title>
</head>
<body class="owner-ui owner-facility-settings">
    <div class="owner-layout" id="ownerFacility" data-facility-id="<?= (int) $facilityId ?>">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="owner-content owner-workspace-page">
            <header class="owner-page-head">
                <div>
                    <h1>Facility Settings</h1>
                    <p>Manage your facility profile, payment details, operating hours, and courts.</p>
                </div>
            </header>

            <form id="facilityBrandingForm" class="owner-settings-grid" enctype="multipart/form-data">
                <section class="owner-settings-card" id="facilityProfile" aria-labelledby="facilityBrandingHeading">
                    <div class="owner-settings-card-heading">
                        <div>
                            <h2 id="facilityBrandingHeading">Facility Profile</h2>
                            <p>Update the facility identity shown to customers.</p>
                        </div>
                    </div>
                    <div class="owner-profile-layout">
                        <div class="owner-profile-logo">
                            <img
                                class="owner-branding-preview"
                                id="facilityLogoPreview"
                                src="<?= !empty($facility["facility_img"]) ? "../uploads/facility/" . rawurlencode(basename($facility["facility_img"])) : "../uploads/badminton-pickleball-court.svg" ?>"
                                alt="Facility logo preview"
                                data-current-logo="<?= !empty($facility["facility_img"]) ? "1" : "0" ?>">
                            <div class="owner-branding-controls">
                                <input id="facilityLogoInput" name="facility_logo" type="file" accept="image/jpeg,image/png,image/webp" hidden>
                                <input id="removeFacilityLogo" name="remove_logo" type="hidden" value="0">
                                <div class="owner-branding-buttons">
                                    <button type="button" class="owner-secondary-button" id="uploadFacilityLogo">Change Logo</button>
                                    <button type="button" class="owner-secondary-button" id="clearFacilityLogo" <?= empty($facility["facility_img"]) ? "disabled" : "" ?>>Remove Logo</button>
                                </div>
                                <small>JPG, PNG, or WebP. Maximum 2 MB.</small>
                            </div>
                        </div>
                        <div class="owner-profile-fields">
                            <label class="owner-branding-name-label" for="facilityNameInput">Facility Name</label>
                            <input class="owner-branding-name-input" id="facilityNameInput" name="facility_name" type="text" maxlength="250" value="<?= ownerEscape($facility["facility_name"]) ?>" required>
                            <p id="facilityBrandingStatus" class="owner-form-status" role="status" aria-live="polite"></p>
                            <button class="owner-primary-button" id="saveFacilityBranding" type="submit">Save Profile</button>
                            <small class="owner-settings-save-note">Saves profile and payment detail changes.</small>
                        </div>
                    </div>
                </section>

                <section class="owner-settings-card owner-payment-card" id="paymentDetails" aria-labelledby="paymentDetailsHeading">
                    <div class="owner-settings-card-heading">
                        <div>
                            <h2 id="paymentDetailsHeading">Payment Details</h2>
                            <p>Customers use this QR code to pay for reservations.</p>
                        </div>
                    </div>
                    <div class="owner-payment-content">
                        <div class="owner-qr-preview-wrap">
                            <img
                                class="owner-branding-preview owner-qr-preview"
                                id="gcashQrPreview"
                                src="<?= !empty($facility["qr_img"]) ? "../uploads/qr/" . rawurlencode(basename($facility["qr_img"])) : "" ?>"
                                alt="GCash QR code preview"
                                <?= empty($facility["qr_img"]) ? "hidden" : "" ?>>
                            <span id="gcashQrPlaceholder" class="owner-qr-placeholder" <?= !empty($facility["qr_img"]) ? "hidden" : "" ?>>No GCash QR uploaded</span>
                        </div>
                        <div class="owner-branding-controls">
                            <input id="gcashQrInput" name="gcash_qr" type="file" accept="image/jpeg,image/png,image/webp" hidden>
                            <button type="button" class="owner-secondary-button" id="uploadGcashQr">Upload / Change QR Code</button>
                            <small>JPG, PNG, or WebP. Maximum 2 MB.</small>
                            <label class="owner-branding-name-label" for="gcashNumberInput">GCash Number</label>
                            <input class="owner-branding-name-input" id="gcashNumberInput" name="gcash_number" type="tel" inputmode="numeric" autocomplete="tel" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" value="<?= ownerEscape($facility["gcash_number"] ?? "") ?>" aria-describedby="gcashNumberHint">
                            <small id="gcashNumberHint">Enter an 11-digit GCash number starting with 09.</small>
                        </div>
                    </div>
                </section>
            </form>

            <section class="owner-settings-card owner-hours-section" id="operatingHours" aria-labelledby="hoursHeading">
                <div class="owner-settings-card-heading">
                    <div>
                        <h2 id="hoursHeading">Operating Hours</h2>
                        <p>Set the hours when customers can reserve your facility.</p>
                    </div>
                </div>
                <form id="facilityHoursForm" class="owner-hours-form">
                    <label for="opening_time">Opens at
                        <input type="time" id="opening_time" name="opening_time" value="<?= ownerEscape(substr((string) $facility["opening_time"], 0, 5)) ?>" required>
                    </label>
                    <label for="closing_time">Closes at
                        <input type="time" id="closing_time" name="closing_time" value="<?= ownerEscape(substr((string) $facility["closing_time"], 0, 5)) ?>" required>
                    </label>
                    <button type="submit" class="owner-primary-button">Save Hours</button>
                    <p id="hoursStatus" class="owner-form-status" role="status" aria-live="polite"></p>
                </form>
            </section>

            <section class="owner-settings-card owner-courts-section" id="courtsSection" aria-labelledby="courtsHeading">
                <div class="owner-settings-card-heading owner-courts-section-heading">
                    <div>
                        <h2 id="courtsHeading">Courts</h2>
                        <p>Manage court details, pricing, availability, and schedules.</p>
                    </div>
                    <div class="owner-courts-heading-actions">
                        <span class="owner-court-count" id="courtCount">Loading courts</span>
                        <button type="button" id="add-btn" class="owner-primary-button"><span aria-hidden="true">+</span> Add court</button>
                    </div>
                </div>
                <div class="owner-courts-grid" id="grid-container" aria-live="polite"></div>
            </section>
        </main>
    </div>

    <div class="modal-container owner-modal compact-modal-overlay" id="modal_container" aria-hidden="true">
        <div class="modal owner-modal-panel modal--compact" role="dialog" aria-modal="true" aria-labelledby="courtDialogTitle">
            <form id="courtForm" enctype="multipart/form-data">
                <div class="owner-modal-heading">
                    <div>
                        <p class="owner-breadcrumb">COURT DETAILS</p>
                        <h2 id="courtDialogTitle">Add a court</h2>
                    </div>
                    <button type="button" class="owner-close-button cancel-btn" aria-label="Close">&times;</button>
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
                    <label class="owner-wide-field" for="description">Court description (optional)
                        <textarea id="description" name="description" rows="3" maxlength="255"></textarea>
                    </label>
                    <div class="owner-wide-field court-photo-field">
                        <span class="court-photo-label">Court photo</span>
                        <div class="checkout-receipt-upload court-photo-upload">
                            <input class="checkout-receipt-input" type="file" id="court_img" name="court_img" accept="image/jpeg,image/png,image/webp" aria-label="Court photo" aria-describedby="courtImageHelp courtImageFileStatus">
                            <label class="checkout-receipt-dropzone" id="courtImageDropzone" for="court_img" tabindex="0">
                                <span class="checkout-receipt-upload-icon" aria-hidden="true">↑</span>
                                <span class="checkout-receipt-upload-copy"><strong>Choose file</strong><small>or drop it here</small></span>
                                <small class="checkout-receipt-upload-help" id="courtImageHelp">JPG, PNG, or WEBP. Maximum size 5 MB.</small>
                            </label>
                            <small class="court-photo-file-status" id="courtImageFileStatus" role="status" aria-live="polite"></small>
                        </div>
                    </div>
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