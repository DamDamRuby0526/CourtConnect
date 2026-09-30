<?php
require_once __DIR__ . "/../includes/backend-api/config.php";

$adminDashboardLayout = true;
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);
$facilityName = "Your facility";
$bookings = [];
$upcomingCount = 0;
$pendingPaymentCount = 0;
$paidPaymentTotal = 0;

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

    $bookingsStmt = $conn->prepare(
        "SELECT b.booking_id, b.total_amount, b.created_at,
                u.first_name, u.last_name,
                cs.court_date, cs.court_time, cs.schedule_status,
                cd.court_no, cd.slot_duration,
                p.payment_status
         FROM bookings b
         INNER JOIN users u ON u.user_id = b.user_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         LEFT JOIN (
             SELECT booking_id, MAX(payments_id) AS payments_id
             FROM payments
             GROUP BY booking_id
         ) latest_payment ON latest_payment.booking_id = b.booking_id
         LEFT JOIN payments p ON p.payments_id = latest_payment.payments_id
         WHERE cd.facility_id = ?
         ORDER BY (cs.court_date < CURDATE()) ASC, cs.court_date ASC, cs.court_time ASC
         LIMIT 100"
    );
    if ($bookingsStmt) {
        $bookingsStmt->bind_param("i", $facilityId);
        $bookingsStmt->execute();
        $bookingsResult = $bookingsStmt->get_result();
        while ($booking = $bookingsResult->fetch_assoc()) {
            $bookings[] = $booking;
            if ($booking["court_date"] >= date("Y-m-d")) {
                $upcomingCount++;
            }
            if ($booking["payment_status"] === "Pending") {
                $pendingPaymentCount++;
            } elseif ($booking["payment_status"] === "Paid") {
                $paidPaymentTotal += (int) $booking["total_amount"];
            }
        }
        $bookingsStmt->close();
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
    <title>Bookings | CourtConnect</title>
</head>
<body class="admin-ui">
    <div class="admin-layout">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="admin-content">
            <header class="admin-page-head">
                <div>
                    <p class="admin-breadcrumb">FACILITY ADMIN <span>/</span> BOOKINGS</p>
                    <h1>Bookings</h1>
                    <p class="admin-subtitle">Manage incoming reservations, payments, and upcoming court sessions.</p>
                </div>
                <div class="admin-head-actions">
                    <span class="admin-today"><?= date("l, F j") ?></span>
                    <button class="admin-refresh-button" type="button" id="refreshBookings" aria-label="Refresh bookings" title="Refresh bookings">&#8635;</button>
                </div>
            </header>

            <section class="booking-section" id="bookings" aria-label="Bookings and payments">
                <div class="booking-section-head">
                    <div>
                        <h2>Reservations</h2>
                        <p><?= dashboardEscape($facilityName) ?> <span class="section-divider">/</span> All courts</p>
                    </div>
                    <div class="booking-totals" aria-live="polite">
                        <span><strong id="visibleCount"><?= count($bookings) ?></strong> shown</span>
                        <i aria-hidden="true"></i>
                        <span><strong><?= $pendingPaymentCount ?></strong> pending payment</span>
                        <i aria-hidden="true"></i>
                        <span><strong><?= $upcomingCount ?></strong> upcoming</span>
                    </div>
                </div>

                <div class="booking-tools" role="search" aria-label="Filter bookings">
                    <label class="booking-filter booking-date-filter">
                        <span>Date</span>
                        <input type="date" id="bookingDate" aria-label="Filter by date">
                    </label>
                    <label class="booking-filter booking-search-filter">
                        <span>Player</span>
                        <input type="search" id="playerSearch" placeholder="Search player" aria-label="Search by player">
                    </label>
                    <label class="booking-filter booking-status-filter">
                        <span>Payment</span>
                        <select id="paymentFilter" aria-label="Filter by payment status">
                            <option value="">All payments</option>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </label>
                    <button class="admin-clear-button" type="button" id="clearFilters">Clear</button>
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
            </section>
            <footer class="admin-footer"><span><?= dashboardEscape($facilityName) ?></span><span>Paid total: ₱<?= number_format($paidPaymentTotal, 2) ?></span></footer>
        </main>
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