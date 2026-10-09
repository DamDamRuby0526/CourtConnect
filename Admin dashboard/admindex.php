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
        "SELECT b.booking_id, COALESCE(b.customer_name, CONCAT(u.first_name, ' ', u.last_name)) AS customer_name,
            cs.court_date, cs.court_time,
                cd.court_no, cd.slot_duration
         FROM bookings b
         LEFT JOIN users u ON u.user_id = b.user_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         WHERE cd.facility_id = ? AND cs.court_date = CURDATE()
             AND cs.schedule_status = 'Booked'
             AND b.arrived_at IS NULL
             AND b.did_not_arrive_at IS NULL
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
                                <span><?= dashboardEscape($booking["customer_name"]) ?></span>
                                <small><?= (int) $booking["slot_duration"] ?> min</small>
                                <button type="button" class="dashboard-arrived-button" data-booking-id="<?= (int) $booking["booking_id"] ?>">Arrived</button>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="dashboard-empty" id="todayBookingsStatus" role="status" aria-live="polite" hidden></p>
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
                <div class="owner-form-grid manual-booking-fields">
                    <label for="manualCustomerName">Customer name
                        <input type="text" id="manualCustomerName" name="customer_name" maxlength="200" autocomplete="name" required>
                    </label>
                    <label for="manualCustomerPhone">Phone number
                        <input type="tel" id="manualCustomerPhone" name="customer_phone" maxlength="30" autocomplete="tel" required>
                    </label>
                    <label class="owner-wide-field" for="manualPaymentMethod">Payment method
                        <select id="manualPaymentMethod" name="payment_method" required>
                            <option value="Gcash">GCash</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </label>
                    <label class="owner-wide-field" id="manualGcashReceiptField" for="manualGcashReceipt" hidden>
                        GCash receipt image
                        <input type="file" id="manualGcashReceipt" name="gcash_receipt" accept="image/jpeg,image/png,image/webp">
                        <small>JPG, PNG, or WEBP. Maximum 5 MB.</small>
                    </label>
                </div>
                <p class="owner-form-status" id="manualBookingStatus" role="status" aria-live="polite"></p>
                <div class="owner-modal-actions">
                    <button type="button" class="owner-secondary-button" id="cancelManualBooking">Cancel</button>
                    <button type="submit" class="owner-primary-button" id="submitManualBooking">Confirm booking</button>
                </div>

                <div class="schedule-table-wrap">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Time</th>
                                <th scope="col">Court</th>
                                <th scope="col">Duration</th>
                                <th scope="col">Player</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Payment</th>
                                <th scope="col">Booked on</th>
                            </tr>
                        </thead>
                        <tbody id="bookingRows">
                            <?php if (!$facilityId): ?>
                                <tr><td colspan="8" class="schedule-empty">Sign in with a facility admin account to view bookings.</td></tr>
                            <?php elseif (!$bookings): ?>
                                <tr><td colspan="8" class="schedule-empty">No reservations found for <?= dashboardEscape($facilityName) ?>.</td></tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $booking): ?>
                                    <?php $paymentSlug = strtolower($booking["payment_status"] ?? "unpaid"); ?>
                                    <tr class="booking-row" data-date="<?= dashboardEscape($booking["court_date"]) ?>" data-player="<?= dashboardEscape($booking["first_name"] . " " . $booking["last_name"]) ?>" data-payment="<?= dashboardEscape($paymentSlug) ?>" data-has-payment="<?= $booking["payment_status"] !== null ? "true" : "false" ?>" data-upcoming="<?= $booking["court_date"] >= date("Y-m-d") ? "true" : "false" ?>">
                                        <td data-label="Date"><?= dashboardEscape(date("D, M j, Y", strtotime($booking["court_date"]))) ?></td>
                                        <td data-label="Time"><?= dashboardEscape(date("g:i A", strtotime($booking["court_time"]))) ?></td>
                                        <td data-label="Court"><?= dashboardEscape($booking["court_no"]) ?></td>
                                        <td data-label="Duration"><?= (int) $booking["slot_duration"] ?> min</td>
                                        <td data-label="Player" class="booking-player"><?= dashboardEscape($booking["first_name"] . " " . $booking["last_name"]) ?></td>
                                        <td data-label="Amount">₱<?= number_format((float) $booking["total_amount"], 2) ?></td>
                                        <td data-label="Payment"><span class="payment-status payment-<?= dashboardEscape($paymentSlug) ?>"><?= dashboardEscape($booking["payment_status"] ?? "Unpaid") ?></span></td>
                                        <td data-label="Booked on"><?= dashboardEscape(date("M j, Y g:i A", strtotime($booking["created_at"]))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p class="schedule-no-results" id="noResults" hidden>No bookings match those filters.</p>
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
    <script>
        const bookingFilters = [
            document.getElementById("bookingDate"),
            document.getElementById("playerSearch"),
            document.getElementById("paymentFilter")
        ];
        const bookingRows = [...document.querySelectorAll(".booking-row")];
        const adminViews = [...document.querySelectorAll("[data-admin-view]")];
        const visibleCount = document.getElementById("visibleCount");
        const noResults = document.getElementById("noResults");
        let activeView = "bookings";

        function filterBookings() {
            const [dateValue, playerValue, paymentValue] = bookingFilters.map((filter) => filter.value.trim().toLowerCase());
            let visible = 0;

            bookingRows.forEach((row) => {
                const matchesView = activeView === "bookings"
                    || (activeView === "payments" && row.dataset.hasPayment === "true")
                    || (activeView === "upcoming" && row.dataset.upcoming === "true");
                const matches = matchesView
                    && (!dateValue || row.dataset.date === dateValue)
                    && (!playerValue || row.dataset.player.toLowerCase().includes(playerValue))
                    && (!paymentValue || row.dataset.payment === paymentValue);
                row.hidden = !matches;
                if (matches) visible++;
            });

            visibleCount.textContent = visible;
            noResults.hidden = visible > 0 || bookingRows.length === 0;
        }

        bookingFilters.forEach((filter) => filter.addEventListener("input", filterBookings));
        document.getElementById("clearFilters").addEventListener("click", () => {
            bookingFilters.forEach((filter) => { filter.value = ""; });
            filterBookings();
        });
        document.getElementById("refreshBookings").addEventListener("click", () => location.reload());
        adminViews.forEach((link) => link.addEventListener("click", (event) => {
            event.preventDefault();
            activeView = link.dataset.adminView;
            adminViews.forEach((item) => {
                const isActive = item === link;
                item.classList.toggle("active", isActive);
                if (isActive) item.setAttribute("aria-current", "page");
                else item.removeAttribute("aria-current");
            });
            filterBookings();
        }));
    </script>
</body>

</html>