<?php
require_once __DIR__ . "/includes/backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$bookingId = filter_var($_GET["booking_id"] ?? null, FILTER_VALIDATE_INT);
$bookingGroupId = filter_var($_GET["booking_group_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || (int) ($_SESSION["role_id"] ?? 0) !== 1) {
    $returnTo = $bookingGroupId
        ? "checkout.php?booking_group_id=" . (int) $bookingGroupId
        : "checkout.php?booking_id=" . (int) ($bookingId ?: 0);
    header("Location: signin.php?return_to=" . rawurlencode($returnTo));
    exit();
}
if ((!$bookingGroupId || $bookingGroupId < 1) && (!$bookingId || $bookingId < 1)) {
    header("Location: mybookings.php");
    exit();
}

function checkoutFindBookings(mysqli $conn, int $bookingId, int $bookingGroupId, int $userId)
{
    $where = $bookingGroupId > 0 ? "b.booking_group_id = ?" : "b.booking_id = ?";
    $lookupId = $bookingGroupId > 0 ? $bookingGroupId : $bookingId;
    $bookingStmt = $conn->prepare(
        "SELECT b.booking_id, b.status AS booking_status, b.total_amount, b.hold_expires_at,
                b.booking_group_id,
                b.start_time, b.end_time, b.total_duration,
                cs.court_date, cs.court_time, cs.schedule_status,
                cd.court_no, cd.slot_duration, cd.court_rate, f.facility_name, f.municipality, f.qr_img,
                p.payments_id, p.payment_status, p.reference_number, p.receipt_image,
                UNIX_TIMESTAMP(b.hold_expires_at) AS hold_expires_epoch,
                (b.hold_expires_at IS NULL OR b.hold_expires_at <= NOW()) AS hold_expired,
                UNIX_TIMESTAMP(NOW()) AS server_now_epoch
         FROM bookings b
         INNER JOIN court_schedule cs ON cs.schedule_id = b.schedule_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN facilities f ON f.facility_id = cd.facility_id
         LEFT JOIN (
             SELECT booking_id, MAX(payments_id) AS payments_id
             FROM payments
             GROUP BY booking_id
         ) latest_payment ON latest_payment.booking_id = b.booking_id
         LEFT JOIN payments p ON p.payments_id = latest_payment.payments_id
         WHERE $where AND b.user_id = ?
         ORDER BY cs.court_date ASC, cs.court_time ASC"
    );
    $bookingStmt->bind_param("ii", $lookupId, $userId);
    $bookingStmt->execute();
    $result = $bookingStmt->get_result();
    $bookings = [];
    while ($booking = $result->fetch_assoc()) {
        $bookings[] = $booking;
    }
    $bookingStmt->close();
    return $bookings;
}

$groupBookings = checkoutFindBookings($conn, (int) $bookingId, (int) $bookingGroupId, (int) $userId);
if ($groupBookings && array_filter($groupBookings, static function ($booking) {
    return $booking["booking_status"] === "pending_payment"
        && ($booking["schedule_status"] !== "Held" || (int) $booking["hold_expired"] === 1);
})) {
    expireUnpaidBookings($conn);
    $groupBookings = checkoutFindBookings($conn, (int) $bookingId, (int) $bookingGroupId, (int) $userId);
}

if (!$groupBookings) {
    $booking = ["booking_id" => (int) $bookingId, "booking_status" => "Expired"];
    $groupBookings = [];
} else {
    $booking = $groupBookings[0];
    if ($bookingGroupId > 0) {
        $booking["total_amount"] = array_sum(array_map(static function ($row) {
            return (int) $row["total_amount"];
        }, $groupBookings));
    }
}
$checkoutSlots = [];
if ($groupBookings) {
    $slotLookup = $bookingGroupId > 0 ? "b.booking_group_id = ?" : "b.booking_id = ?";
    $slotLookupId = $bookingGroupId > 0 ? (int) $bookingGroupId : (int) $bookingId;
    $slotStmt = $conn->prepare(
        "SELECT cs.court_time, cd.slot_duration, cd.court_rate, b.total_amount
         FROM bookings b
         INNER JOIN court_schedule start_cs ON start_cs.schedule_id = b.schedule_id
         INNER JOIN court_details cd ON cd.court_id = b.court_id
         INNER JOIN court_schedule cs
             ON cs.court_id = b.court_id
             AND cs.court_date = start_cs.court_date
             AND cs.court_time >= COALESCE(b.start_time, start_cs.court_time)
             AND cs.court_time < COALESCE(
                 b.end_time,
                 ADDTIME(start_cs.court_time, SEC_TO_TIME(cd.slot_duration * 60))
             )
         WHERE $slotLookup AND b.user_id = ?
         ORDER BY cs.court_date, cs.court_time"
    );
    $slotStmt->bind_param("ii", $slotLookupId, $userId);
    $slotStmt->execute();
    $slotResult = $slotStmt->get_result();
    while ($slot = $slotResult->fetch_assoc()) $checkoutSlots[] = $slot;
    $slotStmt->close();
}
$conn->close();

function checkoutEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$bookingStatus = strtolower((string) ($booking["booking_status"] ?? ""));
$isExpired = in_array($bookingStatus, ["expired", "cancelled"], true)
    || ($bookingStatus === "pending_payment"
        && empty($booking["payments_id"])
        && ($booking["schedule_status"] !== "Held" || (int) $booking["hold_expired"] === 1))
    || ($bookingGroupId > 0 && $groupBookings && (bool) array_filter($groupBookings, static function ($row) {
        return $row["booking_status"] === "pending_payment"
            && (empty($row["payments_id"])
                ? ($row["schedule_status"] !== "Held" || (int) $row["hold_expired"] === 1)
                : strtolower((string) $row["payment_status"]) === "rejected"
                    && (int) $row["hold_expired"] === 1);
    }));
$isFacilityCancelled = $bookingStatus === "cancelled";
$isAwaitingConfirmation = in_array($bookingStatus, ["pending", "pending_payment"], true)
    && strtolower((string) ($booking["payment_status"] ?? "")) === "pending"
    && !empty($booking["receipt_image"])
    && !array_filter($groupBookings, static function ($row) {
        return !in_array($row["booking_status"], ["pending", "pending_payment"], true)
            || strtolower((string) ($row["payment_status"] ?? "")) !== "pending"
            || empty($row["receipt_image"]);
    });
$canSubmitPayment = $bookingStatus === "pending_payment" && !$isExpired
    && (int) $booking["hold_expired"] === 0
    && (empty($booking["payments_id"]) || strtolower((string) ($booking["payment_status"] ?? "")) === "rejected")
    && !array_filter($groupBookings, static function ($row) {
        return $row["booking_status"] !== "pending_payment"
            || $row["schedule_status"] !== "Held"
            || (int) $row["hold_expired"] === 1
            || (!empty($row["payments_id"]) && strtolower((string) $row["payment_status"]) !== "rejected");
    });
$canCancelCheckout = $canSubmitPayment && empty($booking["payments_id"]);
$isRejectedExpired = $bookingStatus === "pending_payment"
    && strtolower((string) ($booking["payment_status"] ?? "")) === "rejected"
    && !empty($booking["payments_id"])
    && (int) $booking["hold_expired"] === 1;
$courtDate = isset($booking["court_date"]) ? date("D, M j, Y", strtotime($booking["court_date"])) : "";
$startTime = $booking["start_time"] ?: ($booking["court_time"] ?? "");
$courtTime = $startTime !== "" ? date("g:i A", strtotime($startTime)) : "";
$lastCheckoutSlot = $groupBookings ? $groupBookings[count($groupBookings) - 1] : $booking;
$endTime = $booking["end_time"] ?: null;
$courtEndTime = $endTime
    ? date("g:i A", strtotime($endTime))
    : (isset($lastCheckoutSlot["court_time"])
        ? date("g:i A", strtotime($lastCheckoutSlot["court_time"]) + (int) $lastCheckoutSlot["slot_duration"] * 60)
        : "");
$groupDuration = (int) ($booking["total_duration"] ?? 0);
if ($groupDuration < 1) {
    $groupDuration = array_sum(array_map(static function ($row) {
        return (int) $row["slot_duration"];
    }, $groupBookings));
}
$groupDurationHours = $groupDuration / 60;
$groupDurationLabel = rtrim(rtrim(number_format($groupDurationHours, 2, ".", ""), "0"), ".")
    . " " . ($groupDurationHours === 1.0 ? "hour" : "hours");
$expiredMessage = "Your booking expired and the slot was released.";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="includes/js/checkout.js" defer></script>
    <title>Checkout | CourtConnect</title>
</head>
<body class="player-ui">
    <?php include 'includes/navigation/header.php'; ?>
    <main class="player-page checkout-page"
        data-booking-id="<?= (int) $booking["booking_id"] ?>"
        data-booking-group-id="<?= (int) ($booking["booking_group_id"] ?? 0) ?>">
        <nav class="player-breadcrumb" aria-label="Breadcrumb"><a href="facility.php"><a href="mybookings.php">My bookings</a></nav>
        <header class="player-page-heading">
            <div>
                <p class="player-eyebrow">SECURE YOUR COURT TIME</p>
                <h1>Checkout</h1>
                <p>Complete your GCash payment to send this booking for confirmation.</p>
            </div>
        </header>

        <?php if ($isExpired): ?>
            <section class="checkout-state checkout-expired" aria-live="polite">
                <p class="player-eyebrow">HOLD EXPIRED</p>
                <h2>Your booking expired and the slot was released</h2>
                <p><?= checkoutEscape($expiredMessage) ?></p>
                <a class="player-primary-link" href="facility.php">Pick another time</a>
            </section>
        <?php elseif ($isFacilityCancelled): ?>
            <section class="checkout-state" aria-live="polite">
                <p class="player-eyebrow">BOOKING UPDATE</p>
                <h2>This booking is no longer active</h2>
                <p>The facility cancelled this booking. View your bookings or find another court.</p>
                <div class="checkout-confirmation-links">
                    <a class="player-primary-link" href="mybookings.php">My bookings</a>
                    <a class="player-secondary-link" href="facility.php">Find a court</a>
                </div>
            </section>
        <?php elseif ($isAwaitingConfirmation): ?>
            <section class="checkout-state checkout-confirmation" aria-live="polite">
                <p class="player-eyebrow">PAYMENT SUBMITTED</p>
                <h2>Booking reference #<?= (int) $booking["booking_id"] ?></h2>
                <p class="checkout-next-step"><strong>What happens next</strong><span>The facility will confirm your payment.</span></p>
                <div class="checkout-confirmation-links">
                    <a class="player-primary-link" href="mybookings.php">My bookings</a>
                    <a class="player-secondary-link" href="facility.php">Find a court</a>
                </div>
            </section>
        <?php elseif ($isRejectedExpired): ?>
            <section class="checkout-state checkout-expired" aria-live="polite">
                <p class="player-eyebrow">PAYMENT REJECTED</p>
                <h2>The facility rejected this payment</h2>
                <p>This booking has a payment record and was kept for facility review. Contact the facility for next steps.</p>
                <div class="checkout-confirmation-links">
                    <a class="player-primary-link" href="mybookings.php">My bookings</a>
                    <a class="player-secondary-link" href="facility.php">Find a court</a>
                </div>
            </section>
        <?php else: ?>
            <div class="checkout-layout">
                <section class="checkout-payment-column" aria-label="Payment instructions">
                    <div class="checkout-panel checkout-instructions">
                        <div class="checkout-qr-wrap">
                            <?php if (!empty($booking["qr_img"])): ?>
                                <button class="checkout-qr-open" id="checkoutQrOpen" type="button" aria-label="Enlarge the GCash QR code">
                                    <img class="checkout-qr-image" src="uploads/qr/<?= rawurlencode(basename($booking["qr_img"])) ?>" alt="GCash QR code for <?= checkoutEscape($booking["facility_name"]) ?>">
                                    <span>Click to enlarge</span>
                                </button>
                            <?php else: ?>
                                <div class="checkout-qr-placeholder">GCash QR unavailable</div>
                            <?php endif; ?>
                        </div>
                        <div class="checkout-amount-block">
                            <span>Exact amount to pay</span>
                            <strong>P<?= number_format((float) $booking["total_amount"], 2) ?></strong>
                            <span>Payment method: GCash</span>
                        </div>
                        <ol class="checkout-steps">
                            <li>Scan the QR</li>
                            <li>Pay the exact amount</li>
                            <li>Enter the reference number and upload your receipt</li>
                        </ol>
                    </div>

                    <?php if ($canSubmitPayment): ?>
                        <form class="checkout-form" id="checkoutPaymentForm" enctype="multipart/form-data">
                            <h2>Submit payment</h2>
                            <label for="paymentReference">Reference number</label>
                            <input id="paymentReference" name="reference_number" type="text" maxlength="50" required autocomplete="off">
                            <label for="paymentReceipt">Receipt screenshot</label>
                            <input id="paymentReceipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required>
                            <small>JPG, PNG, or WEBP. Maximum size 5 MB.</small>
                            <p class="checkout-form-message" id="checkoutFormMessage" role="status" aria-live="polite"></p>
                            <button class="player-primary-button" type="submit">Submit payment</button>
                            <?php if ($canCancelCheckout): ?>
                                <button class="player-secondary-link checkout-cancel-button" id="cancelCheckoutBooking" type="button">Cancel booking</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </section>

                <aside class="checkout-summary-column" aria-label="Booking summary">
                    <section class="checkout-panel checkout-summary-card">
                        <p class="player-eyebrow">BOOKING SUMMARY</p>
                        <h2><?= checkoutEscape($booking["facility_name"]) ?></h2>
                        <p class="checkout-summary-location"><?= checkoutEscape($booking["municipality"]) ?></p>
                        <dl class="checkout-summary-details">
                            <div><dt>Court</dt><dd>Court <?= checkoutEscape($booking["court_no"]) ?></dd></div>
                            <div><dt>Date</dt><dd><?= checkoutEscape($courtDate) ?></dd></div>
                            <div><dt>Time</dt><dd><?= checkoutEscape($courtTime) ?>–<?= checkoutEscape($courtEndTime) ?></dd></div>
                            <div><dt>Duration</dt><dd><?= checkoutEscape($groupDurationLabel) ?></dd></div>
                            <?php if (count($checkoutSlots) > 1): ?>
                                <div><dt>Consecutive slots</dt><dd><?= count($checkoutSlots) ?></dd></div>
                            <?php endif; ?>
                            <div><dt>Subtotal</dt><dd>P<?= number_format((float) $booking["total_amount"], 2) ?></dd></div>
                            <div><dt>Fees</dt><dd>P0.00</dd></div>
                            <div><dt>Total</dt><dd>P<?= number_format((float) $booking["total_amount"], 2) ?></dd></div>
                        </dl>
                        <?php if (count($checkoutSlots) > 1): ?>
                            <ol class="checkout-slot-list" aria-label="Selected court slots">
                                <?php foreach ($checkoutSlots as $slot): ?>
                                    <li>
                                        <span><?= checkoutEscape(date("g:i A", strtotime($slot["court_time"]))) ?>–<?= checkoutEscape(date("g:i A", strtotime($slot["court_time"]) + (int) $slot["slot_duration"] * 60)) ?></span>
                                        <strong>P<?= number_format((float) $slot["court_rate"], 2) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                        <?php if ($canSubmitPayment): ?>
                            <p class="checkout-hold-timer" id="holdTimer" data-expires="<?= (int) $booking["hold_expires_epoch"] ?>" data-server-now="<?= (int) $booking["server_now_epoch"] ?>" aria-live="polite">Slot held for --:--</p>
                        <?php endif; ?>
                    </section>
                    <?php if ($canSubmitPayment): ?>
                    <?php endif; ?>
                </aside>
            </div>
            <?php if (!empty($booking["qr_img"])): ?>
                <div class="checkout-qr-lightbox" id="checkoutQrLightbox" aria-hidden="true" hidden>
                    <div class="checkout-qr-lightbox-panel" role="dialog" aria-modal="true" aria-labelledby="checkoutQrTitle">
                        <button class="checkout-qr-close" id="checkoutQrClose" type="button" aria-label="Close enlarged QR code">&times;</button>
                        <h2 id="checkoutQrTitle">GCash QR Code</h2>
                        <img src="uploads/qr/<?= rawurlencode(basename($booking["qr_img"])) ?>" alt="Enlarged GCash QR code for <?= checkoutEscape($booking["facility_name"]) ?>">
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>