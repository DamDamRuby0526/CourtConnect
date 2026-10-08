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
$adminNavPage = "payments";
$facilityStmt = $conn->prepare("SELECT facility_name FROM facilities WHERE facility_id = ?");
$facilityStmt->bind_param("i", $facilityId);
$facilityStmt->execute();
$facilityName = $facilityStmt->get_result()->fetch_assoc()["facility_name"] ?? "Your facility";
$facilityStmt->close();
$conn->close();
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
    <script src="../includes/js/admin_payments.js" defer></script>
    <title>Payments | CourtConnect</title>
</head>
<body class="admin-ui">
    <div class="admin-layout">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="admin-content">
            <header class="admin-page-head">
                <div>
                    <p class="admin-breadcrumb"><?= $roleId === 3 ? "FACILITY OWNER" : "FACILITY ADMIN" ?> <span>/</span> PAYMENTS</p>
                    <h1>Payments</h1>
                    <p class="admin-subtitle">Review GCash receipts and record confirmed payments.</p>
                </div>
                <div class="admin-head-actions">
                    <span class="admin-today"><?= date("l, F j, Y") ?></span>
                    <button class="admin-refresh-button" type="button" id="refreshPayments" aria-label="Refresh payments" title="Refresh payments">&#8635;</button>
                </div>
            </header>
            <section class="booking-section" aria-label="Facility payments">
                <div class="booking-section-head">
                    <div>
                        <h2>Payment records</h2>
                        <p><?= htmlspecialchars($facilityName, ENT_QUOTES, "UTF-8") ?></p>
                    </div>
                    <div class="payment-summary" aria-live="polite">
                        <span><strong id="pendingPaymentCount">--</strong> pending</span>
                        <span><strong id="rejectedPaymentCount">--</strong> rejected</span>
                        <span><strong id="paidPaymentTotal">--</strong> paid total</span>
                    </div>
                </div>
                <label class="payment-filter">
                    <span>Payment status</span>
                    <select id="paymentStatusFilter">
                        <option value="">All status</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                        <option value="paid">Paid</option>
                    </select>
                </label>
                <p id="paymentMessage" class="owner-form-status" role="status" aria-live="polite"></p>
                <div class="schedule-table-wrap payment-table-wrap">
                    <table class="schedule-table payment-table">
                        <thead>
                            <tr>
                                <th scope="col">Booking date</th>
                                <th scope="col">Court</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Method</th>
                                <th scope="col">Reference Number</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Receipt</th>
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody id="paymentRows">
                            <tr><td colspan="9" class="schedule-empty">Loading payment records...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <footer class="admin-footer"><span><?= htmlspecialchars($facilityName, ENT_QUOTES, "UTF-8") ?></span></footer>
        </main>
    </div>
    <div class="modal-container owner-modal" id="markPaidModal" aria-hidden="true">
        <div class="modal owner-modal-panel payment-confirm-panel" role="alertdialog" aria-modal="true" aria-labelledby="markPaidTitle" aria-describedby="markPaidMessage">
            <div class="owner-modal-heading">
                <div>
                    <p class="owner-breadcrumb">PAYMENT REVIEW</p>
                    <h2 id="markPaidTitle">Confirm payment</h2>
                </div>
            </div>
            <p class="owner-confirm-message" id="markPaidMessage">Mark as paid?</p>
            <div class="owner-modal-actions">
                <button type="button" class="owner-secondary-button" id="cancelMarkPaid">Cancel</button>
                <button type="button" class="owner-primary-button" id="confirmMarkPaid">Mark as paid</button>
            </div>
        </div>
    </div>
    <div class="modal-container owner-modal" id="editPaymentModal" aria-hidden="true">
        <div class="modal owner-modal-panel payment-edit-panel" role="dialog" aria-modal="true" aria-labelledby="editPaymentTitle">
            <form id="editPaymentForm">
                <div class="owner-modal-heading">
                    <div>
                        <p class="owner-breadcrumb">PAYMENT DETAILS</p>
                        <h2 id="editPaymentTitle">Edit payment</h2>
                    </div>
                </div>
                <input type="hidden" id="editPaymentId" name="payment_id">
                <div class="owner-form-grid payment-edit-grid">
                    <label for="editPaymentMethod">Payment method
                        <select id="editPaymentMethod" name="payment_method" required>
                            <option value="Gcash">GCash</option>
                            <option value="Cash">Cash</option>
                        </select>
                    </label>
                    <label for="editPaymentReference" id="editPaymentReferenceField">Reference number
                        <input type="text" id="editPaymentReference" name="reference_number" maxlength="50">
                    </label>
                    <label for="editPaymentStatusSelect">Payment status
                        <select id="editPaymentStatusSelect" name="payment_status" required>
                            <option value="Pending">Pending</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Paid">Paid</option>
                        </select>
                    </label>
                </div>
                <p id="editPaymentFeedback" class="owner-form-status" role="status" aria-live="polite"></p>
                <div class="owner-modal-actions">
                    <button type="button" class="owner-secondary-button" id="cancelEditPayment">Cancel</button>
                    <button type="submit" class="owner-primary-button" id="savePaymentChanges">Save changes</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal-container owner-modal" id="paymentReceiptModal" aria-hidden="true">
        <div class="modal owner-modal-panel payment-receipt-panel" role="dialog" aria-modal="true" aria-labelledby="paymentReceiptTitle">
            <div class="owner-modal-heading">
                <div>
                    <p class="owner-breadcrumb">PAYMENT REVIEW</p>
                    <h2 id="paymentReceiptTitle">GCash receipt</h2>
                </div>
            </div>
            <img id="paymentReceiptImage" class="payment-receipt-preview" alt="Uploaded GCash receipt">
            <div class="owner-modal-actions">
                <button type="button" class="owner-secondary-button" id="closePaymentReceipt">Close</button>
            </div>
        </div>
    </div>
</body>
</html>