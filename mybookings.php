<?php
require_once __DIR__ . "/includes/backend-api/config.php";
$isPlayer = !empty($_SESSION["user_id"]) && (int) ($_SESSION["role_id"] ?? 0) === 1;
$conn->close();
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
    <?php if ($isPlayer): ?>
        <script src="includes/js/mybookings.js" defer></script>
    <?php endif; ?>
    <title>My bookings | CourtConnect</title>
</head>
<body class="player-ui">
    <?php include 'includes/navigation/header.php'; ?>
    <main class="player-page mybookings-page">
        <header class="player-page-heading">
            <div>
                <p class="player-eyebrow">YOUR COURTCONNECT</p>
                <h1>My bookings</h1>
                <p>Keep track of your reservations and payment status.</p>
            </div>
            <a class="player-primary-link" href="facility.php">Find a court</a>
        </header>

        <?php if (!$isPlayer): ?>
                <p>Your reservations will appear here after you sign in.</p>
                <a class="player-primary-link" href="signin.php?return_to=mybookings.php">Sign in</a>
            </div>
        <?php else: ?>
            <div class="booking-tabs" role="tablist" aria-label="Booking periods">
                <button class="booking-tab is-active" type="button" role="tab" aria-selected="true" data-booking-tab="upcoming">Upcoming</button>
                <button class="booking-tab" type="button" role="tab" aria-selected="false" data-booking-tab="past">Past</button>
            </div>
            <section class="player-booking-list" id="playerBookingList" aria-live="polite" data-active-tab="upcoming">
                <p class="booking-loading">Loading bookings...</p>
            </section>
        <?php endif; ?>
    </main>
    <div class="modal-container owner-modal player-payment-modal" id="rejectionReasonModal" aria-hidden="true">
        <section class="modal owner-modal-panel player-payment-dialog" role="dialog" aria-modal="true" aria-labelledby="rejectionReasonTitle">
            <div class="owner-modal-heading">
                <div>
                    <p class="owner-breadcrumb">PAYMENT REVIEW</p>
                    <h2 id="rejectionReasonTitle">Payment Rejected</h2>
                </div>
            </div>
            <p class="player-rejection-intro">Your payment was rejected by the facility administrator.</p>
            <div class="player-rejection-reason">
                <strong>Reason for rejection:</strong>
                <p id="rejectionReasonText"></p>
            </div>
            <p id="rejectionResubmissionMessage" class="player-resubmission-message" hidden></p>
            <div class="owner-modal-actions">
                <button type="button" class="owner-secondary-button" id="closeRejectionReason">Close</button>
                <button type="button" class="owner-primary-button" id="openPaymentResubmission">Submit New Payment</button>
            </div>
        </section>
    </div>
    <div class="modal-container owner-modal player-payment-modal" id="paymentResubmissionModal" aria-hidden="true">
        <div class="modal owner-modal-panel player-payment-dialog">
            <form id="paymentResubmissionForm" enctype="multipart/form-data">
                <div class="owner-modal-heading">
                    <div>
                        <p class="owner-breadcrumb">PAYMENT RESUBMISSION</p>
                        <h2>Submit New Payment</h2>
                    </div>
                </div>
                <p class="player-resubmission-booking" id="resubmissionBookingDetails"></p>
                <input type="hidden" id="resubmissionBookingId" name="booking_id">
                <label class="player-payment-field" for="resubmissionPaymentMethod">Payment method
                    <select id="resubmissionPaymentMethod" name="payment_method" required>
                        <option value="Gcash">GCash</option>
                    </select>
                </label>
                <label class="player-payment-field" for="resubmissionReference">Reference number
                    <input id="resubmissionReference" name="reference_number" type="text" maxlength="50" required>
                </label>
                <label class="player-payment-field" for="resubmissionReceipt">Replacement receipt (JPG, PNG, or WebP; max 5 MB)
                    <input id="resubmissionReceipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required>
                </label>
                <p id="paymentResubmissionFeedback" class="owner-form-status" role="status" aria-live="polite"></p>
                <div class="owner-modal-actions">
                    <button type="button" class="owner-secondary-button" id="cancelPaymentResubmission">Cancel</button>
                    <button type="submit" class="owner-primary-button" id="submitPaymentResubmission">Submit for review</button>
                </div>
            </form>
        </div>
    </div>
    <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>