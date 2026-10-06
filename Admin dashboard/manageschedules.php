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
    "SELECT cd.court_no, f.facility_name
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
$facilityName = $court["facility_name"];
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
                <button type="button" id="add-btn" class="owner-primary-button">Add time slot</button>
            </header>
            <span id="court-id" class="owner-court-count">Court <?= (int) $court["court_no"] ?></span>
            <div class="owner-schedule-grid" id="grid-container" aria-live="polite"></div>

            <!-- MODAL -->
            <div class="modal-container owner-modal" id="modal_container" aria-hidden="true">
                <div class="modal owner-modal-panel" role="dialog" aria-modal="true" aria-labelledby="scheduleDialogTitle">
                    <form id="scheduleForm">

                        <h2 id="scheduleDialogTitle">Schedule details</h2>

                        <label for="court_date">Date</label>
                        <input type="date" id="court_date" name="court_date" required>

                        <label for="court_time">Start time</label>
                        <input type="time" id="court_time" name="court_time" required>

                        <label for="schedule_status">Status</label>
                        <select id="schedule_status" name="schedule_status">
                            <option value="Available">Available</option>
                            <option value="Booked">Booked</option>
                        </select><br>

                        <button type="button" class="owner-primary-button save-btn">Save slot</button>
                        <button type="button" class="owner-secondary-button cancel-btn">Cancel</button>

                    </form>

                </div>
            </div>

        </main>
    </div>
</body>

</html>