<?php
require_once __DIR__ . "/../includes/backend-api/config.php";

$adminDashboardLayout = true;
$adminNavPage = "upcoming";
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);
$facilityName = "Your facility";
$todayBookings = [];
$availableCourtCount = 0;

if ($facilityId && $facilityId > 0) {
    $facilityStmt = $conn->prepare("SELECT facility_name FROM facilities WHERE facility_id = ?");
    if ($facilityStmt) {
        $facilityStmt->bind_param("i", $facilityId);
        $facilityStmt->execute();
        $facility = $facilityStmt->get_result()->fetch_assoc();
        if ($facility) {
            $facilityName = $facility["facility_name"];
        }
        $facilityStmt->close();
    }

    $todayStmt = $conn->prepare(
        "SELECT b.booking_id, u.first_name, u.last_name, cs.court_date, cs.court_time,
                cd.court_no, cd.slot_duration
         FROM bookings b
         INNER JOIN users u ON u.user_id = b.user_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE cd.facility_id = ? AND cs.court_date = CURDATE()
             AND cs.schedule_status = 'Booked'
         ORDER BY cs.court_time ASC, cd.court_no ASC"
    );
    if ($todayStmt) {
        $todayStmt->bind_param("i", $facilityId);
        $todayStmt->execute();
        $todayResult = $todayStmt->get_result();
        while ($booking = $todayResult->fetch_assoc()) {
            $todayBookings[] = $booking;
        }
        $todayStmt->close();
    }

    $availableCourtsStmt = $conn->prepare(
        "SELECT COUNT(DISTINCT cd.court_id) AS court_count
         FROM court_schedule cs
         INNER JOIN court_details cd ON cd.court_id = cs.court_id
         WHERE cd.facility_id = ? AND cd.court_status = 'Available'
             AND cs.schedule_status = 'Available'
             AND (cs.court_date > CURDATE() OR (cs.court_date = CURDATE() AND cs.court_time > CURTIME()))"
    );
    if ($availableCourtsStmt) {
        $availableCourtsStmt->bind_param("i", $facilityId);
        $availableCourtsStmt->execute();
        $availableCourtCount = (int) ($availableCourtsStmt->get_result()->fetch_assoc()["court_count"] ?? 0);
        $availableCourtsStmt->close();
    }

}

$conn->close();

function dashboardEscape($value)
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="../includes/js/admin_dashboard.js" defer></script>
    <title>Upcoming reservations | CourtConnect</title>
</head>
<body class="admin-ui">
    <div class="admin-layout">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="admin-content">
            <header class="admin-page-head">
                <div>
                    <p class="admin-breadcrumb"><?= (int) ($_SESSION["role_id"] ?? 0) === 3 ? "FACILITY OWNER" : "FACILITY ADMIN" ?> <span>/</span> UPCOMING RESERVATIONS</p>
                    <h1>Upcoming reservations</h1>
                    <p class="admin-subtitle">Today's confirmed court bookings and open court times.</p>
                </div>
                <div class="admin-head-actions">
                    <span class="admin-today"><?= date("l, F j") ?></span>
                    <button class="admin-refresh-button" type="button" id="refreshBookings" aria-label="Refresh bookings" title="Refresh bookings">&#8635;</button>
                </div>
            </header>

            <section class="dashboard-summary-cards" aria-label="Reservation overview">
                <button type="button" class="dashboard-summary-card" data-dashboard-panel="todayBookingsPanel" aria-expanded="false" aria-controls="todayBookingsPanel">
                    <span class="dashboard-card-label">Court reservations today</span>
                    <strong><?= count($todayBookings) ?></strong>
                    <span class="dashboard-card-hint">Confirmed bookings · View today's schedule</span>
                </button>
                <button type="button" class="dashboard-summary-card dashboard-summary-card-available" data-dashboard-panel="availableCourtsPanel" aria-expanded="false" aria-controls="availableCourtsPanel">
                    <span class="dashboard-card-label">Available courts</span>
                    <strong><?= $availableCourtCount ?></strong>
                    <span class="dashboard-card-hint">Courts with open times · Add a booking</span>
                </button>
            </section>

            <section class="dashboard-detail-panel" id="todayBookingsPanel" aria-labelledby="todayBookingsHeading" hidden>
                <div class="dashboard-detail-heading">
                    <div>
                        <h2 id="todayBookingsHeading">Confirmed bookings today</h2>
                        <p>Sorted by start time</p>
                    </div>
                    <span><?= count($todayBookings) ?> <?= count($todayBookings) === 1 ? "booking" : "bookings" ?></span>
                </div>
                <?php if (!$todayBookings): ?>
                    <p class="dashboard-empty">No confirmed bookings for today.</p>
                <?php else: ?>
                    <div class="dashboard-today-list">
                        <?php foreach ($todayBookings as $booking): ?>
                            <article class="dashboard-today-row">
                                <time datetime="<?= dashboardEscape($booking["court_date"] . "T" . $booking["court_time"]) ?>"><?= dashboardEscape(date("g:i A", strtotime($booking["court_time"]))) ?></time>
                                <strong>Court <?= dashboardEscape($booking["court_no"]) ?></strong>
                                <span><?= dashboardEscape($booking["first_name"] . " " . $booking["last_name"]) ?></span>
                                <small><?= (int) $booking["slot_duration"] ?> min</small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-detail-panel" id="availableCourtsPanel" aria-labelledby="availableCourtsHeading" hidden>
                <div class="dashboard-detail-heading">
                    <div>
                        <h2 id="availableCourtsHeading">Available court times</h2>
                        <p>Upcoming open slots for <?= dashboardEscape($facilityName) ?></p>
                    </div>
                    <span id="availableSlotCount" aria-live="polite">Loading</span>
                </div>
                <p class="dashboard-empty" id="availableSlotsStatus" role="status">Loading available court times...</p>
                <div class="dashboard-available-list" id="availableSlotsList"></div>
            </section>

            <footer class="admin-footer"><span><?= dashboardEscape($facilityName) ?></span></footer>
        </main>
    </div>
    <div class="modal-container owner-modal" id="manualBookingModal" aria-hidden="true">
        <div class="modal owner-modal-panel manual-booking-panel" role="dialog" aria-modal="true" aria-labelledby="manualBookingTitle">
            <form id="manualBookingForm">
                <div class="owner-modal-heading">
                    <div>
                        <p class="owner-breadcrumb">FACILITY RESERVATION</p>
                        <h2 id="manualBookingTitle">Add a booking</h2>
                    </div>
                </div>
                <p class="manual-booking-slot" id="manualBookingSlot"></p>
                <input type="hidden" name="schedule_id" id="manualScheduleId">
                <label for="manualCustomerId">Customer
                    <select id="manualCustomerId" name="customer_id" required>
                        <option value="">Choose a registered customer</option>
                    </select>
                </label>
                <p class="owner-form-status" id="manualBookingStatus" role="status" aria-live="polite"></p>
                <div class="owner-modal-actions">
                    <button type="button" class="owner-secondary-button" id="cancelManualBooking">Cancel</button>
                    <button type="submit" class="owner-primary-button" id="submitManualBooking">Confirm booking</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>