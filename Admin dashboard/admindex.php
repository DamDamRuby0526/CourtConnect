<?php
require_once __DIR__ . "/../includes/backend-api/config.php";
require_once __DIR__ . "/../includes/admin-php/facility_owner_page_guard.php";

$adminDashboardLayout = true;
$adminNavPage = "upcoming";
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);
$facilityName = "Your facility";
$facilityLogo = "";
$todayBookings = [];
$availableCourtCount = 0;

if ($facilityId && $facilityId > 0) {
    $facilityStmt = $conn->prepare("SELECT facility_name, facility_img FROM facilities WHERE facility_id = ?");
    if ($facilityStmt) {
        $facilityStmt->bind_param("i", $facilityId);
        $facilityStmt->execute();
        $facility = $facilityStmt->get_result()->fetch_assoc();
        if ($facility) {
            $facilityName = $facility["facility_name"];
            $facilityLogo = $facility["facility_img"];
        }
        $facilityStmt->close();
    }

    $todayStmt = $conn->prepare(
        "SELECT b.booking_id, b.booking_type, b.customer_name AS walk_in_name, b.customer_phone,
                b.total_amount, b.payment_status AS booking_payment_status, b.arrived_at, b.did_not_arrive_at,
                COALESCE(b.customer_name, CONCAT(u.first_name, ' ', u.last_name)) AS customer_name,
                COALESCE(b.customer_phone, u.phone_number) AS display_phone,
                cs.court_date, cs.court_time, cd.court_no, cd.slot_duration,
                COALESCE(p.payment_status, IF(b.payment_status = 'paid', 'Paid', 'Pending')) AS payment_status
         FROM bookings b
         LEFT JOIN users u ON u.user_id = b.user_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         LEFT JOIN (
             SELECT booking_id, MAX(payments_id) AS payments_id
             FROM payments
             GROUP BY booking_id
         ) latest_payment ON latest_payment.booking_id = b.booking_id
         LEFT JOIN payments p ON p.payments_id = latest_payment.payments_id
         WHERE cd.facility_id = ? AND cs.court_date = CURDATE()
             AND cs.schedule_status = 'Booked'
             AND b.status IN ('confirmed', 'pending')
         ORDER BY cd.court_no ASC, cs.court_time ASC"
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
             AND (cs.schedule_status = 'Available'
                 OR (cs.schedule_status = 'Held'
                     AND (cs.hold_expires_at IS NULL OR cs.hold_expires_at <= NOW())))
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
    <script src="../includes/js/toast.js" defer></script>
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
                <button type="button" class="dashboard-summary-card is-active" data-dashboard-panel="todayBookingsPanel" aria-expanded="true" aria-controls="todayBookingsPanel">
                    <span class="dashboard-card-label">Court reservations today</span>
                    <strong id="todayBookingCount"><?= count($todayBookings) ?></strong>
                    <span class="dashboard-card-hint">Confirmed bookings · View today's schedule</span>
                </button>
                <button type="button" class="dashboard-summary-card dashboard-summary-card-available" data-dashboard-panel="availableCourtsPanel" aria-expanded="false" aria-controls="availableCourtsPanel">
                    <span class="dashboard-card-label">Available courts</span>
                    <strong id="availableCourtCount"><?= $availableCourtCount ?></strong>
                    <span class="dashboard-card-hint">Courts with open times · Add a booking</span>
                </button>
            </section>

            <section class="dashboard-detail-panel" id="todayBookingsPanel" aria-labelledby="todayBookingsHeading">
                <div class="dashboard-detail-heading">
                    <div>
                        <h2 id="todayBookingsHeading">Court reservations today</h2>
                        <p>Grouped by court and sorted by start time</p>
                    </div>
                    <span id="todayBookingSummary"><?= count($todayBookings) ?> <?= count($todayBookings) === 1 ? "booking" : "bookings" ?></span>
                </div>
                <div class="dashboard-today-list" id="todayBookingsList">
                    <p class="dashboard-empty">Loading today's reservations...</p>
                </div>
            </section>

            <section class="dashboard-detail-panel" id="availableCourtsPanel" aria-labelledby="availableCourtsHeading" hidden>
                <div class="dashboard-detail-heading">
                    <div>
                        <h2 id="availableCourtsHeading">Available court times</h2>
                        <p>Upcoming open slots for <?= dashboardEscape($facilityName) ?></p>
                    </div>
                    <span id="availableSlotCount" aria-live="polite">Loading</span>
                </div>
                <p class="dashboard-empty" id="availableSlotsStatus" role="status" hidden></p>
                <div class="dashboard-available-list" id="availableSlotsList"></div>
            </section>

            <footer class="admin-footer"><span><?= dashboardEscape($facilityName) ?></span></footer>
        </main>
    </div>
    <script>
        window.initialTodayBookings = <?= json_encode($todayBookings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
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
                <div class="owner-form-grid manual-booking-fields">
                    <label for="manualCustomerName">Customer name
                        <input type="text" id="manualCustomerName" name="customer_name" maxlength="200" autocomplete="name" required>
                    </label>
                    <label for="manualCustomerPhone">Phone number
                        <input type="tel" id="manualCustomerPhone" name="customer_phone" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" autocomplete="tel" required>
                    </label>
                    <label class="owner-wide-field" for="manualPaymentMethod">Payment method
                        <select id="manualPaymentMethod" name="payment_method" required>
                            <option value="Gcash">GCash</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </label>
                    <label class="owner-wide-field manual-gcash-upload" id="manualGcashQrField" for="manualGcashQr" hidden>
                        Upload GCash QR code / payment image
                        <input type="file" id="manualGcashQr" name="gcash_qr" accept="image/jpeg,image/png,image/webp">
                        <small>JPG, PNG, or WEBP. Maximum 5 MB.</small>
                    </label>
                    <label class="owner-wide-field manual-gcash-upload" id="manualGcashReferenceField" for="manualGcashReference" hidden>
                        GCash reference number
                        <input type="text" id="manualGcashReference" name="reference_number" maxlength="50" autocomplete="off">
                    </label>
                </div>
                <div class="owner-modal-actions">
                    <button type="button" class="owner-secondary-button" id="cancelManualBooking">Cancel</button>
                    <button type="submit" class="owner-primary-button" id="submitManualBooking">Confirm booking</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal-container owner-modal" id="cancelBookingModal" aria-hidden="true">
        <div class="modal owner-modal-panel owner-confirm-panel" role="alertdialog" aria-modal="true" aria-labelledby="cancelBookingTitle">
            <div class="owner-modal-heading"><h2 id="cancelBookingTitle">Cancel reservation?</h2></div>
            <p class="owner-confirm-message">This will release the court time for other customers.</p>
            <div class="owner-modal-actions">
                <button type="button" class="owner-secondary-button" id="dismissCancelBooking">Keep booking</button>
                <button type="button" class="owner-danger-button" id="confirmCancelBooking">Cancel reservation</button>
            </div>
        </div>
    </div>
    <div class="modal-container owner-modal" id="arrivedConfirmationModal" aria-hidden="true">
        <div class="modal owner-modal-panel owner-confirm-panel" role="alertdialog" aria-modal="true" aria-labelledby="arrivedConfirmationTitle" aria-describedby="arrivedConfirmationMessage">
            <div class="owner-modal-heading">
                <div>
                    <h2 id="arrivedConfirmationTitle">Customer Arrived?</h2>
                </div>
            </div>
            <p class="owner-confirm-message" id="arrivedConfirmationMessage">Confirm that this customer has arrived for their booking.</p>
            <div class="owner-modal-actions">
                <button type="button" class="owner-secondary-button" id="cancelArrivedConfirmation">Cancel</button>
                <button type="button" class="owner-danger-button" id="markNoShowConfirmation">Mark as no-show</button>
                <button type="button" class="owner-primary-button" id="confirmArrivedConfirmation">Confirm</button>
            </div>
        </div>
    </div>
</body>
</html>