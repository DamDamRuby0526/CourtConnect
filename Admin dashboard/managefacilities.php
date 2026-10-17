<?php
require_once __DIR__ . "/../includes/backend-api/config.php";
$staffRoleGuardApi = false;
require_once __DIR__ . "/../includes/admin-php/superadmin_auth_guard.php";
$courtconnectDashboardLayout = true;
$courtconnectNavPage = "facilities";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/css/styles.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="../includes/js/admin_managefacilities.js?v=20261017-1" defer></script>
    <title>Manage facilities | CourtConnect</title>
</head>
<body class="owner-ui" data-role-id="<?= (int) $_SESSION["role_id"] ?>">
    <div class="owner-layout">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="owner-content owner-workspace-page superadmin-facilities-page">
            <header class="owner-page-head">
                <div>
                    <p class="owner-breadcrumb">SYSTEM ADMINISTRATION</p>
                    <h1>Manage facilities</h1>
                    <p>Review and manage facility listings across CourtConnect.</p>
                </div>
                <button type="button" id="add-btn" class="owner-primary-button">Add new facility</button>
            </header>
            <div id="grid-container" class="superadmin-facility-grid" aria-live="polite"></div>
        </main>
    </div>

    <div class="modal-container compact-modal-overlay" id="modal_container" aria-hidden="true">
        <div class="modal modal--compact" role="dialog" aria-modal="true" aria-labelledby="facilityDialogTitle">
            <form id="facilityForm" enctype="multipart/form-data">
                <div class="compact-modal-header">
                    <h2 id="facilityDialogTitle">Facility details</h2>
                    <button type="button" class="owner-close-button cancel-btn" aria-label="Close">&times;</button>
                </div>
                <label for="facility_name">Facility name
                    <input type="text" id="facility_name" name="facility_name" required>
                </label>
                <label for="address">Address
                    <textarea id="address" name="address" required></textarea>
                </label>
                <label for="municipality">Municipality
                    <select id="municipality" name="municipality" required>
                    <option value="Abucay">Abucay</option>
                    <option value="Bagac">Bagac</option>
                    <option value="Balanga City">Balanga City</option>
                    <option value="Dinalupihan">Dinalupihan</option>
                    <option value="Hermosa">Hermosa</option>
                    <option value="Limay">Limay</option>
                    <option value="Mariveles">Mariveles</option>
                    <option value="Morong">Morong</option>
                    <option value="Orani">Orani</option>
                    <option value="Orion">Orion</option>
                    <option value="Pilar">Pilar</option>
                    <option value="Samal">Samal</option>
                    </select>
                </label>
                <label for="sport_type">Sport type
                    <select id="sport_type" name="sport_type" required>
                    <option value="Badminton">Badminton</option>
                    <option value="Pickleball">Pickleball</option>
                    <option value="Both">Both</option>
                    </select>
                </label>
                <div class="compact-field-row">
                    <label for="opening_time">Opening time
                        <input type="time" id="opening_time" name="opening_time" required>
                    </label>
                    <label for="closing_time">Closing time
                        <input type="time" id="closing_time" name="closing_time" required>
                    </label>
                </div>
                <label for="facility_img">Facility image
                    <img id="facility_img_preview" src="" alt="" style="max-width:150px; display:none;">
                    <input type="file" id="facility_img" name="facility_img" accept="image/png, image/jpeg, image/webp">
                    <small id="facility_img_hint"></small>
                </label>
                <label for="qr_img">QR code image
                    <img id="qr_img_preview" src="" alt="" style="max-width:150px; display:none;">
                    <input type="file" id="qr_img" name="qr_img" accept="image/png, image/jpeg, image/webp">
                    <small id="qr_img_hint"></small>
                </label>
                <div class="owner-modal-actions compact-modal-actions">
                    <button type="button" class="owner-secondary-button cancel-btn">Cancel</button>
                    <button type="button" class="owner-primary-button save-btn">Save</button>
                </div>
            </form>
        </div>
    </div>
    <?php include '../includes/navigation/admin_footer.php'; ?>
</body>
</html>