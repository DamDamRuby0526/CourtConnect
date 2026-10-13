<?php
require_once __DIR__ . "/../includes/backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$roleId = (int) ($_SESSION["role_id"] ?? 0);
if (!$userId || !in_array($roleId, [2, 3], true)) {
    header("Location: ../adminsignin.php");
    exit();
}

$accessStmt = $conn->prepare(
    "SELECT a.admin_id, a.facility_id
     FROM admins a
     INNER JOIN users u ON u.user_id = a.user_id
    WHERE a.user_id = ? AND a.status = 'Active'
         AND u.role_id = ? AND u.is_verified = 1
     LIMIT 1"
);
$accessStmt->bind_param("ii", $userId, $roleId);
$accessStmt->execute();
$adminAccess = $accessStmt->get_result()->fetch_assoc();
$accessStmt->close();
if (!$adminAccess) {
    $_SESSION = [];
    session_destroy();
    header("Location: ../adminsignin.php");
    exit();
}
$facilityId = (int) $adminAccess["facility_id"];

$adminDashboardLayout = true;
$adminNavPage = "bookings";
$facilityName = "Your facility";
$facilityLogo = "";
$bookings = [];
$courts = [];
$paidPaymentTotal = 0;
$attendanceFilter = is_string($_GET["attendance"] ?? null) ? $_GET["attendance"] : "";
$dateFilter = is_string($_GET["date"] ?? null) ? $_GET["date"] : "";
$courtFilter = filter_var($_GET["court"] ?? null, FILTER_VALIDATE_INT);
$playerFilter = is_string($_GET["player"] ?? null) ? trim($_GET["player"]) : "";
$typeFilter = is_string($_GET["type"] ?? null) ? $_GET["type"] : "";
$paymentFilter = is_string($_GET["payment"] ?? null) ? $_GET["payment"] : "";

if (!in_array($attendanceFilter, ["upcoming", "awaiting", "arrived", "no-show", "cancelled"], true)) {
    $attendanceFilter = "";
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)
    || !checkdate((int) substr($dateFilter, 5, 2), (int) substr($dateFilter, 8, 2), (int) substr($dateFilter, 0, 4))) {
    $dateFilter = "";
}
if (!$courtFilter || $courtFilter < 1) {
    $courtFilter = null;
}
if (!in_array($typeFilter, ["walk_in", "online"], true)) {
    $typeFilter = "";
}
if (!in_array($paymentFilter, ["Paid", "Pending"], true)) {
    $paymentFilter = "";
}

$facilityStmt = $conn->prepare("SELECT facility_name, facility_img FROM facilities WHERE facility_id = ?");
$facilityStmt->bind_param("i", $facilityId);
$facilityStmt->execute();
$facility = $facilityStmt->get_result()->fetch_assoc();
if ($facility) {
    $facilityName = $facility["facility_name"];
    $facilityLogo = $facility["facility_img"];
}
$facilityStmt->close();

$courtsStmt = $conn->prepare("SELECT court_id, court_no FROM court_details WHERE facility_id = ? ORDER BY court_no ASC");
$courtsStmt->bind_param("i", $facilityId);
$courtsStmt->execute();
$courtsResult = $courtsStmt->get_result();
while ($court = $courtsResult->fetch_assoc()) {
    $courts[] = $court;
}
$courtsStmt->close();

$where = [
    "cd.facility_id = ?",
    "b.status NOT IN ('pending_payment', 'Expired')",
    "NOT (LOWER(b.status) = 'cancelled' AND LOWER(b.payment_status) = 'unpaid'
        AND NOT EXISTS (SELECT 1 FROM payments unpaid_cancel_payment WHERE unpaid_cancel_payment.booking_id = b.booking_id))",
];
$params = [$facilityId];
$paramTypes = "i";
$startedSlot = "TIMESTAMP(cs.court_date, cs.court_time) <= NOW()";
$paymentStatus = "COALESCE(p.payment_status, IF(LOWER(b.payment_status) = 'paid', 'Paid', 'Pending'))";

if ($attendanceFilter === "upcoming") {
    $where[] = "LOWER(b.status) <> 'cancelled' AND b.arrived_at IS NULL AND b.did_not_arrive_at IS NULL AND NOT ($startedSlot)";
} elseif ($attendanceFilter === "awaiting") {
    $where[] = "LOWER(b.status) <> 'cancelled' AND b.arrived_at IS NULL AND b.did_not_arrive_at IS NULL AND $startedSlot";
} elseif ($attendanceFilter === "arrived") {
    $where[] = "LOWER(b.status) <> 'cancelled' AND b.did_not_arrive_at IS NULL AND b.arrived_at IS NOT NULL";
} elseif ($attendanceFilter === "no-show") {
    $where[] = "LOWER(b.status) <> 'cancelled' AND b.did_not_arrive_at IS NOT NULL";
} elseif ($attendanceFilter === "cancelled") {
    $where[] = "LOWER(b.status) = 'cancelled'";
}
if ($dateFilter !== "") {
    $where[] = "cs.court_date = ?";
    $params[] = $dateFilter;
    $paramTypes .= "s";
}
if ($courtFilter !== null) {
    $where[] = "cd.court_id = ?";
    $params[] = $courtFilter;
    $paramTypes .= "i";
}
if ($playerFilter !== "") {
    $where[] = "(COALESCE(b.customer_name, CONCAT(u.first_name, ' ', u.last_name)) LIKE ? OR COALESCE(b.customer_phone, u.phone_number) LIKE ?)";
    $playerSearch = "%" . $playerFilter . "%";
    $params[] = $playerSearch;
    $params[] = $playerSearch;
    $paramTypes .= "ss";
}
if ($typeFilter !== "") {
    $where[] = "b.booking_type = ?";
    $params[] = $typeFilter;
    $paramTypes .= "s";
}
if ($paymentFilter !== "") {
    $where[] = "$paymentStatus = ?";
    $params[] = $paymentFilter;
    $paramTypes .= "s";
}

$bookingsStmt = $conn->prepare(
    "SELECT b.booking_id, b.total_amount, b.created_at, b.status, b.booking_type,
            b.arrived_at, b.did_not_arrive_at,
            COALESCE(b.customer_name, CONCAT(u.first_name, ' ', u.last_name)) AS customer_name,
            COALESCE(b.customer_phone, u.phone_number) AS customer_phone,
            cs.court_date, cs.court_time,
            cd.court_no, cd.slot_duration,
            $paymentStatus AS payment_status,
            CASE
                WHEN LOWER(b.status) = 'cancelled' THEN 'cancelled'
                WHEN b.did_not_arrive_at IS NOT NULL THEN 'no-show'
                WHEN b.arrived_at IS NOT NULL THEN 'arrived'
                WHEN $startedSlot THEN 'awaiting'
                ELSE 'upcoming'
            END AS attendance_status,
            (LOWER(b.status) <> 'cancelled' AND b.arrived_at IS NULL AND b.did_not_arrive_at IS NULL) AS can_mark_attendance,
            $startedSlot AS slot_has_started
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
     WHERE " . implode(" AND ", $where) . "
     ORDER BY cs.court_date DESC, cs.court_time DESC"
);
$bindParams = [$paramTypes];
foreach ($params as $index => $value) {
    $bindParams[] = &$params[$index];
}
call_user_func_array([$bookingsStmt, "bind_param"], $bindParams);
$bookingsStmt->execute();
$bookingsResult = $bookingsStmt->get_result();
while ($booking = $bookingsResult->fetch_assoc()) {
    $bookings[] = $booking;
    if ($booking["payment_status"] === "Paid") {
        $paidPaymentTotal += (float) $booking["total_amount"];
    }
}
$bookingsStmt->close();
$conn->close();

function bookingsPageEscape($value)
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
                    <p class="admin-breadcrumb"><?= $roleId === 3 ? "FACILITY OWNER" : "FACILITY ADMIN" ?> <span>/</span> BOOKINGS</p>
                    <h1>Bookings</h1>
                    <p class="admin-subtitle">Reservations across all dates, with attendance and payment status.</p>
                </div>
                <div class="admin-head-actions">
                    <span class="admin-today"><?= date("l, F j") ?></span>
                    <button class="admin-refresh-button" type="button" id="refreshBookings" aria-label="Refresh bookings" title="Refresh bookings">&#8635;</button>
                </div>
            </header>
            <section class="booking-section" aria-label="Bookings">
                <div class="booking-section-head">
                    <div>
                        <h2>Reservations</h2>
                        <p><?= bookingsPageEscape($facilityName) ?> <span class="section-divider">/</span> All courts</p>
                    </div>
                    <div class="booking-totals" aria-live="polite">
                        <span><strong><?= count($bookings) ?></strong> shown</span>
                    </div>
                </div>
                <form class="booking-tools" method="get" action="managebookings.php" role="search" aria-label="Filter bookings" id="bookingFilters">
                    <label class="booking-filter">
                        <span>Attendance</span>
                        <select name="attendance" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="upcoming" <?= $attendanceFilter === "upcoming" ? "selected" : "" ?>>Upcoming</option>
                            <option value="awaiting" <?= $attendanceFilter === "awaiting" ? "selected" : "" ?>>Awaiting attendance</option>
                            <option value="arrived" <?= $attendanceFilter === "arrived" ? "selected" : "" ?>>Arrived</option>
                            <option value="no-show" <?= $attendanceFilter === "no-show" ? "selected" : "" ?>>Did not arrive</option>
                            <option value="cancelled" <?= $attendanceFilter === "cancelled" ? "selected" : "" ?>>Cancelled</option>
                        </select>
                    </label>
                    <label class="booking-filter booking-date-filter">
                        <span>Date</span>
                        <input type="date" name="date" value="<?= bookingsPageEscape($dateFilter) ?>" onchange="this.form.submit()" aria-label="Filter by date">
                    </label>
                    <label class="booking-filter">
                        <span>Court</span>
                        <select name="court" onchange="this.form.submit()">
                            <option value="">All</option>
                            <?php foreach ($courts as $court): ?>
                                <option value="<?= (int) $court["court_id"] ?>" <?= $courtFilter === (int) $court["court_id"] ? "selected" : "" ?>><?= bookingsPageEscape($court["court_no"]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="booking-filter booking-search-filter">
                        <span>Player</span>
                        <input type="search" name="player" value="<?= bookingsPageEscape($playerFilter) ?>" placeholder="Name or phone" aria-label="Search by player">
                    </label>
                    <label class="booking-filter">
                        <span>Booking type</span>
                        <select name="type" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="walk_in" <?= $typeFilter === "walk_in" ? "selected" : "" ?>>Walk-in</option>
                            <option value="online" <?= $typeFilter === "online" ? "selected" : "" ?>>Online</option>
                        </select>
                    </label>
                    <label class="booking-filter">
                        <span>Payment</span>
                        <select name="payment" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="Paid" <?= $paymentFilter === "Paid" ? "selected" : "" ?>>Paid</option>
                            <option value="Pending" <?= $paymentFilter === "Pending" ? "selected" : "" ?>>Pending</option>
                        </select>
                    </label>
                    <a class="admin-clear-button" href="managebookings.php">Clear</a>
                </form>
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
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bookingRows">
                            <?php if (!$bookings): ?>
                                <tr><td colspan="10" class="schedule-empty">No bookings match these filters. <a class="admin-clear-button" href="managebookings.php">Clear</a></td></tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $booking): ?>
                                    <?php
                                        $displayPaymentStatus = strtolower((string) $booking["status"]) === "cancelled"
                                            && strtolower((string) ($booking["payment_status"] ?? "")) === "pending"
                                            ? "Submitted"
                                            : ($booking["payment_status"] ?? "Pending");
                                        $paymentSlug = strtolower($displayPaymentStatus);
                                        $attendanceLabels = [
                                            "cancelled" => "Cancelled",
                                            "no-show" => "Did not arrive",
                                            "arrived" => "Arrived",
                                            "awaiting" => "Awaiting attendance",
                                            "upcoming" => "Upcoming",
                                        ];
                                        $attendanceStatus = $booking["attendance_status"];
                                        $slotHasStarted = (bool) $booking["slot_has_started"];
                                        $availableFromTitle = $slotHasStarted ? "" : "Available from " . date("g:i A", strtotime($booking["court_date"] . " " . $booking["court_time"]));
                                    ?>
                                    <tr class="booking-row">
                                        <td data-label="Date"><?= bookingsPageEscape(date("D, M j, Y", strtotime($booking["court_date"]))) ?></td>
                                        <td data-label="Time"><?= bookingsPageEscape(date("g:i A", strtotime($booking["court_time"]))) ?></td>
                                        <td data-label="Court"><?= bookingsPageEscape($booking["court_no"]) ?></td>
                                        <td data-label="Duration"><?= (int) $booking["slot_duration"] ?> min</td>
                                        <td data-label="Customer" class="booking-player"><?= bookingsPageEscape($booking["customer_name"]) ?><small><?= bookingsPageEscape($booking["customer_phone"] ?? "") ?></small></td>
                                        <td data-label="Amount">&#8369;<?= number_format((float) $booking["total_amount"], 2) ?></td>
                                        <td data-label="Payment"><span class="payment-status payment-<?= bookingsPageEscape($paymentSlug) ?>"><?= bookingsPageEscape($displayPaymentStatus) ?></span></td>
                                        <td data-label="Attendance"><span class="attendance-status attendance-status-<?= bookingsPageEscape($attendanceStatus) ?>"><?= bookingsPageEscape($attendanceLabels[$attendanceStatus] ?? "Upcoming") ?></span></td>
                                        <td data-label="Booked on"><?= bookingsPageEscape(date("M j, Y g:i A", strtotime($booking["created_at"]))) ?></td>
                                        <td data-label="Actions">
                                            <?php if ($booking["can_mark_attendance"]): ?>
                                                <div class="booking-attendance-actions">
                                                    <button type="button" class="dashboard-arrived-button" data-booking-id="<?= (int) $booking["booking_id"] ?>" data-attendance="arrived" title="<?= $availableFromTitle !== "" ? bookingsPageEscape($availableFromTitle) : "" ?>">Mark arrived</button>
                                                    <button type="button" class="dashboard-cancel-button" data-booking-id="<?= (int) $booking["booking_id"] ?>" data-attendance="no_show" title="<?= $availableFromTitle !== "" ? bookingsPageEscape($availableFromTitle) : "" ?>">Did not arrive</button>
                                                </div>
                                            <?php else: ?>
                                                <span class="booking-no-action">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <footer class="admin-footer"><span><?= bookingsPageEscape($facilityName) ?></span><span>Paid total: &#8369;<?= number_format($paidPaymentTotal, 2) ?></span></footer>
        </main>
    </div>
    <script>
        const filtersForm = document.getElementById("bookingFilters");
        const playerFilterInput = filtersForm.elements.player;
        let playerFilterTimer;
        playerFilterInput.addEventListener("input", () => {
            window.clearTimeout(playerFilterTimer);
            playerFilterTimer = window.setTimeout(() => filtersForm.requestSubmit(), 300);
        });
        document.getElementById("refreshBookings").addEventListener("click", () => location.reload());
        document.querySelectorAll("[data-booking-id][data-attendance]").forEach((button) => {
            button.addEventListener("click", async () => {
                const rowButtons = button.closest("tr").querySelectorAll("button[data-booking-id]");
                rowButtons.forEach((rowButton) => { rowButton.disabled = true; });
                try {
                    const response = await fetch("../includes/admin-php/mark_booking_arrived.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: new URLSearchParams({ booking_id: button.dataset.bookingId, attendance: button.dataset.attendance }),
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        if (result.code === "already_recorded") {
                            const row = button.closest("tr");
                            const attendanceCell = row?.querySelector("[data-label='Attendance']");
                            const attendanceValue = result.attendance_state === "arrived" ? "Arrived" : "Did not arrive";
                            const attendanceClass = result.attendance_state === "arrived" ? "attendance-status-arrived" : "attendance-status-no-show";
                            if (attendanceCell) {
                                attendanceCell.innerHTML = `<span class="attendance-status ${attendanceClass}">${attendanceValue}</span>`;
                            }
                            window.showDialog({ type: "success", title: "Attendance recorded", message: result.message || "Attendance was already recorded for this booking." });
                            rowButtons.forEach((rowButton) => { rowButton.disabled = false; });
                            return;
                        }

                        throw new Error(result.message || "Unable to update this booking.");
                    }

                    location.reload();
                } catch (error) {
                    window.showDialog({ type: "error", title: "Unable to update booking", message: error.message || "Unable to update this booking." });
                    rowButtons.forEach((rowButton) => { rowButton.disabled = false; });
                }
            });
        });
    </script>
</body>
</html>