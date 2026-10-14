<?php
require_once __DIR__ . "/../includes/backend-api/config.php";

$ownerId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);
$courtId = filter_var($_GET["court_id"] ?? null, FILTER_VALIDATE_INT);

if (!$ownerId || !$facilityId || !$courtId || (int) ($_SESSION["role_id"] ?? 0) !== 3) {
    header("Location: ../adminsignin.php");
    exit();
}

$courtStmt = $conn->prepare(
    "SELECT cd.court_no, cd.slot_duration, f.facility_id, f.facility_name, f.facility_img,
            f.opening_time, f.closing_time
     FROM court_details cd
     INNER JOIN facilities f ON f.facility_id = cd.facility_id
     INNER JOIN admins a ON a.facility_id = f.facility_id
         INNER JOIN users u ON u.user_id = a.user_id
         WHERE cd.court_id = ? AND cd.facility_id = ? AND a.user_id = ? AND a.status = 'Active'
             AND u.role_id = 3 AND u.is_verified = 1
     LIMIT 1"
);
$courtStmt->bind_param("iii", $courtId, $facilityId, $ownerId);
$courtStmt->execute();
$court = $courtStmt->get_result()->fetch_assoc();
$courtStmt->close();

if (!$court) {
    header("Location: managecourts.php");
    exit();
}

$ownerDashboardLayout = true;
$ownerNavPage = "facility";
$facilityName = $court["facility_name"];
$facilityLogo = $court["facility_img"];
$previewCourtsStmt = $conn->prepare(
    "SELECT court_id, slot_duration
     FROM court_details
     WHERE facility_id = ? AND court_status = 'Available'
     ORDER BY court_id"
);
$previewCourtsStmt->bind_param("i", $court["facility_id"]);
$previewCourtsStmt->execute();
$previewCourts = $previewCourtsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$previewCourtsStmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Court Schedule | CourtConnect</title>

    <link rel="stylesheet" href="../includes/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../includes/js/admin_manageschedules.js" defer></script>
</head>

<body class="owner-ui">
    <div class="owner-layout">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="owner-content">
            <header class="owner-page-head">
                <div>
                    <p class="owner-breadcrumb"><a href="managecourts.php">COURTS</a> <span>/</span> COURT <?= (int) $court["court_no"] ?></p>
                    <h1>Court schedule</h1>
                    <p>Manage reservation times for <?= htmlspecialchars($facilityName, ENT_QUOTES, "UTF-8") ?>.</p>
                </div>
                <div class="owner-page-actions">
                    <button type="button" id="add-btn" class="owner-primary-button">Add time slot</button>
                    <button type="button" id="generate-btn" class="owner-secondary-button" data-today="<?= date('Y-m-d') ?>">Generate Schedule</button>
                    <button type="button" id="remove-schedule-btn" class="owner-secondary-button">Remove schedule</button>
                </div>
            </header>
            <span id="court-id" class="owner-court-count">Court <?= (int) $court["court_no"] ?></span>
            <div class="schedule-legend" aria-label="Schedule status legend">
                <span><i class="schedule-legend-swatch is-available" aria-hidden="true"></i>Available</span>
                <span><i class="schedule-legend-swatch is-booked" aria-hidden="true"></i>Booked</span>
                <span><i class="schedule-legend-swatch is-held" aria-hidden="true"></i>Held</span>
            </div>
            <div class="owner-schedule-grid" id="grid-container" aria-live="polite"></div>

    <!-- MODAL -->
    <div class="modal-container owner-modal compact-modal-overlay" id="modal_container" aria-hidden="true">
        <div class="modal owner-modal-panel modal--compact" role="dialog" aria-modal="true" aria-labelledby="scheduleDialogTitle">
            <form id="scheduleForm">
                <div class="compact-modal-header">
                    <h2 id="scheduleDialogTitle">Schedule details</h2>
                    <button type="button" class="owner-close-button cancel-btn" aria-label="Close">&times;</button>
                </div>
                <div class="compact-field-row">
                    <label for="court_date">Date
                        <input type="date" id="court_date" name="court_date" required>
                    </label>
                    <label for="court_time">Start time
                        <input type="time" id="court_time" name="court_time" required>
                    </label>
                </div>
                <label for="schedule_status">Status</label>
                <select id="schedule_status" name="schedule_status">
                    <option value="Available">Available</option>
                    <option value="Booked">Booked</option>
                </select>
                <p id="remove-slot-help" class="compact-helper" hidden></p>
                <div class="owner-modal-actions compact-modal-actions">
                    <button type="button" class="owner-danger-button" id="remove-slot-btn">Remove slot</button>
                    <span class="compact-modal-action-spacer"></span>
                    <button type="button" class="owner-secondary-button cancel-btn">Cancel</button>
                    <button type="submit" class="owner-primary-button save-btn">Save slot</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-container owner-modal compact-modal-overlay" id="generate_modal" aria-hidden="true">
        <div class="modal owner-modal-panel modal--compact" role="dialog" aria-modal="true" aria-labelledby="generateDialogTitle"
             data-opening-time="<?= htmlspecialchars(substr((string) $court["opening_time"], 0, 8), ENT_QUOTES, "UTF-8") ?>"
             data-closing-time="<?= htmlspecialchars(substr((string) $court["closing_time"], 0, 8), ENT_QUOTES, "UTF-8") ?>"
             data-court-id="<?= (int) $courtId ?>"
             data-preview-courts="<?= htmlspecialchars(json_encode($previewCourts), ENT_QUOTES, "UTF-8") ?>">
            <form id="generateForm">
                <div class="compact-modal-header">
                    <h2 id="generateDialogTitle">Generate schedule</h2>
                    <button type="button" class="owner-close-button generate-cancel-btn" aria-label="Close">&times;</button>
                </div>
                <div class="compact-presets" aria-label="Date range presets">
                    <button type="button" data-generation-days="1">Today</button>
                    <button type="button" data-generation-days="7">Next 7 days</button>
                    <button type="button" data-generation-days="14">Next 14 days</button>
                    <button type="button" data-generation-days="30">Next 30 days</button>
                </div>
                <div class="compact-field-row">
                    <label for="generate_start_date">Start date
                        <input type="date" id="generate_start_date" name="start_date" required>
                    </label>
                    <label for="generate_end_date">End date
                        <input type="date" id="generate_end_date" name="end_date" required>
                    </label>
                </div>
                <fieldset class="compact-segmented" aria-label="Generation scope">
                    <legend class="compact-visually-hidden">Generation scope</legend>
                    <label><input type="radio" name="scope" value="court" checked><span>This court</span></label>
                    <label><input type="radio" name="scope" value="facility"><span>All courts</span></label>
                </fieldset>
                <p id="generate-preview" class="compact-live-preview" aria-live="polite"></p>
                <div class="owner-modal-actions">
                    <button type="button" class="owner-secondary-button generate-cancel-btn">Cancel</button>
                    <button type="submit" class="owner-primary-button" id="generate-submit-btn">Generate</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-container owner-modal compact-modal-overlay" id="remove-schedule-modal" aria-hidden="true">
        <div class="modal owner-modal-panel modal--compact" role="dialog" aria-modal="true" aria-labelledby="removeScheduleTitle">
            <form id="remove-schedule-form">
                <div class="compact-modal-header">
                    <h2 id="removeScheduleTitle">Remove schedule</h2>
                    <button type="button" class="owner-close-button remove-schedule-cancel-btn" aria-label="Close">&times;</button>
                </div>
                <div class="compact-field-row">
                    <label for="remove_start_date">Start date
                        <input type="date" id="remove_start_date" name="start_date" required>
                    </label>
                    <label for="remove_end_date">End date
                        <input type="date" id="remove_end_date" name="end_date" required>
                    </label>
                </div>
                <fieldset class="compact-segmented" aria-label="Removal scope">
                    <legend class="compact-visually-hidden">Removal scope</legend>
                    <label><input type="radio" name="remove_scope" value="court" checked><span>This court</span></label>
                    <label><input type="radio" name="remove_scope" value="facility"><span>All courts</span></label>
                </fieldset>
                <p id="remove-range-summary" class="compact-live-preview" aria-live="polite">Choose a date range to preview removable slots.</p>
                <div class="owner-modal-actions">
                    <button type="button" class="owner-secondary-button remove-schedule-cancel-btn">Cancel</button>
                    <button type="submit" class="owner-danger-button" id="remove-range-submit-btn" disabled>Remove</button>
                </div>
            </form>
        </div>
    </div>

        </main>
    </div>
</body>

</html>