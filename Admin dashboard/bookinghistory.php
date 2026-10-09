<?php
require_once __DIR__ . "/../includes/backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);
$roleId = (int) ($_SESSION["role_id"] ?? 0);
if (!$userId || !$facilityId || !in_array($roleId, [2, 3], true)) {
    header("Location: ../adminsignin.php");
    exit();
}

$accessStmt = $conn->prepare(
    "SELECT a.admin_id
     FROM admins a
     INNER JOIN users u ON u.user_id = a.user_id
     WHERE a.user_id = ? AND a.facility_id = ? AND a.status = 'Active'
         AND u.role_id = ? AND u.is_verified = 1
     LIMIT 1"
);
$accessStmt->bind_param("iii", $userId, $facilityId, $roleId);
$accessStmt->execute();
$hasAccess = $accessStmt->get_result()->fetch_assoc();
$accessStmt->close();
if (!$hasAccess) {
    $_SESSION = [];
    session_destroy();
    header("Location: ../adminsignin.php");
    exit();
}

$adminDashboardLayout = true;
$adminNavPage = "history";
$facilityName = "Your facility";
$bookings = [];

$facilityStmt = $conn->prepare("SELECT facility_name FROM facilities WHERE facility_id = ?");
$facilityStmt->bind_param("i", $facilityId);
$facilityStmt->execute();
$facility = $facilityStmt->get_result()->fetch_assoc();
if ($facility) {
    $facilityName = $facility["facility_name"];
}
$facilityStmt->close();

$bookingsStmt = $conn->prepare(
    "SELECT b.booking_id, b.total_amount, b.created_at,
            COALESCE(b.customer_name, CONCAT(u.first_name, ' ', u.last_name)) AS customer_name,
            COALESCE(b.customer_phone, u.phone_number) AS customer_phone,
            cs.court_date, cs.court_time,
            cd.court_no, cd.slot_duration,
            p.payment_status, b.arrived_at, b.did_not_arrive_at
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
     WHERE cd.facility_id = ?
         AND (b.arrived_at IS NOT NULL
             OR b.did_not_arrive_at IS NOT NULL
             OR cs.court_date < CURDATE()
             OR (cs.court_date = CURDATE() AND cs.court_time < CURTIME()))
     ORDER BY cs.court_date DESC, cs.court_time DESC"
);
$bookingsStmt->bind_param("i", $facilityId);
$bookingsStmt->execute();
$bookingsResult = $bookingsStmt->get_result();
while ($booking = $bookingsResult->fetch_assoc()) {
    $bookings[] = $booking;
}
$bookingsStmt->close();
$conn->close();

function bookingHistoryEscape($value)
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
    <title>Booking History | CourtConnect</title>
</head>
<body class="admin-ui">
    <div class="admin-layout">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="admin-content">
            <header class="admin-page-head">
                <div>
                    <p class="admin-breadcrumb"><?= $roleId === 3 ? "FACILITY OWNER" : "FACILITY ADMIN" ?> <span>/</span> HISTORY / ARCHIVE</p>
                    <h1>Booking history</h1>
                    <p class="admin-subtitle">Bookings appear here after their scheduled time passes or when attendance is recorded.</p>
                </div>
                <div class="admin-head-actions">
                    <span class="admin-today"><?= date("l, F j, Y") ?></span>
                    <button class="admin-refresh-button" type="button" id="refreshBookings" aria-label="Refresh booking history" title="Refresh booking history">&#8635;</button>
                </div>
            </header>
            <section class="booking-section" aria-label="Archived bookings">
                <div class="booking-section-head">
                    <div>
                        <h2>Archived reservations</h2>
                        <p><?= bookingHistoryEscape($facilityName) ?> <span class="section-divider">/</span> All courts</p>
                    </div>
                    <div class="booking-totals" aria-live="polite">
                        <span><strong id="visibleCount"><?= count($bookings) ?></strong> shown</span>
                    </div>
                </div>
                <div class="booking-tools" role="search" aria-label="Filter booking history">
                    <label class="booking-filter booking-date-filter">
                        <span>Date</span>
                        <input type="date" id="bookingDate" aria-label="Filter by date">
                    </label>
                    <label class="booking-filter booking-search-filter">
                        <span>Player</span>
                        <input type="search" id="playerSearch" placeholder="Search player" aria-label="Search by player">
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
                                <th scope="col">Customer</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Payment</th>
                                <th scope="col">Attendance</th>
                                <th scope="col">Booked on</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$bookings): ?>
                                <tr><td colspan="9" class="schedule-empty">No past bookings found for <?= bookingHistoryEscape($facilityName) ?>.</td></tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $booking): ?>
                                    <?php $paymentSlug = strtolower($booking["payment_status"] ?? "unpaid"); ?>
                                    <?php
                                        $attendanceStatus = $booking["did_not_arrive_at"]
                                            ? "Did not arrive"
                                            : ($booking["arrived_at"] ? "Arrived" : "Not recorded");
                                        $attendanceSlug = $booking["did_not_arrive_at"]
                                            ? "no-show"
                                            : ($booking["arrived_at"] ? "arrived" : "not-recorded");
                                    ?>
                                    <tr class="booking-row" data-date="<?= bookingHistoryEscape($booking["court_date"]) ?>" data-player="<?= bookingHistoryEscape($booking["customer_name"]) ?>">
                                        <td data-label="Date"><?= bookingHistoryEscape(date("D, M j, Y", strtotime($booking["court_date"]))) ?></td>
                                        <td data-label="Time"><?= bookingHistoryEscape(date("g:i A", strtotime($booking["court_time"]))) ?></td>
                                        <td data-label="Court"><?= bookingHistoryEscape($booking["court_no"]) ?></td>
                                        <td data-label="Duration"><?= (int) $booking["slot_duration"] ?> min</td>
                                        <td data-label="Customer" class="booking-player"><?= bookingHistoryEscape($booking["customer_name"]) ?><small><?= bookingHistoryEscape($booking["customer_phone"] ?? "") ?></small></td>
                                        <td data-label="Amount">&#8369;<?= number_format((float) $booking["total_amount"], 2) ?></td>
                                        <td data-label="Payment"><span class="payment-status payment-<?= bookingHistoryEscape($paymentSlug) ?>"><?= bookingHistoryEscape($booking["payment_status"] ?? "Unpaid") ?></span></td>
                                        <td data-label="Attendance"><span class="attendance-status attendance-status-<?= bookingHistoryEscape($attendanceSlug) ?>"><?= bookingHistoryEscape($attendanceStatus) ?></span></td>
                                        <td data-label="Booked on"><?= bookingHistoryEscape(date("M j, Y g:i A", strtotime($booking["created_at"]))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p class="schedule-no-results" id="noResults" hidden>No bookings match those filters.</p>
                </div>
            </section>
            <footer class="admin-footer"><span><?= bookingHistoryEscape($facilityName) ?></span></footer>
        </main>
    </div>
    <script>
        const bookingFilters = [
            document.getElementById("bookingDate"),
            document.getElementById("playerSearch")
        ];
        const bookingRows = [...document.querySelectorAll(".booking-row")];
        const visibleCount = document.getElementById("visibleCount");
        const noResults = document.getElementById("noResults");

        function filterBookings() {
            const [dateValue, playerValue] = bookingFilters.map((filter) => filter.value.trim().toLowerCase());
            let visible = 0;
            bookingRows.forEach((row) => {
                const matches = (!dateValue || row.dataset.date === dateValue)
                    && (!playerValue || row.dataset.player.toLowerCase().includes(playerValue));
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
    </script>
</body>
</html>
