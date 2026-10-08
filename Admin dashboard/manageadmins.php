<?php
require_once __DIR__ . "/../includes/backend-api/config.php";

$ownerId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$facilityId = filter_var($_SESSION["facility_id"] ?? null, FILTER_VALIDATE_INT);
if (!$ownerId || !$facilityId || (int) ($_SESSION["role_id"] ?? 0) !== 3) {
    header("Location: ../adminsignin.php");
    exit();
}

$facilityStmt = $conn->prepare(
    "SELECT f.facility_name
     FROM facilities f
     INNER JOIN admins a ON a.facility_id = f.facility_id
     INNER JOIN users u ON u.user_id = a.user_id
     WHERE f.facility_id = ? AND u.user_id = ? AND u.role_id = 3
         AND u.is_verified = 1 AND a.status = 'Active'
     LIMIT 1"
);
$facilityStmt->bind_param("ii", $facilityId, $ownerId);
$facilityStmt->execute();
$facility = $facilityStmt->get_result()->fetch_assoc();
$facilityStmt->close();
if (!$facility) {
    header("Location: ../adminsignin.php");
    exit();
}

$ownerDashboardLayout = true;
$ownerNavPage = "accounts";
$facilityName = $facility["facility_name"];
$conn->close();

function ownerAccountsEscape($value)
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../includes/js/admin_manageaccounts.js" defer></script>
    <title>Admin accounts | CourtConnect</title>
</head>
<body class="owner-ui">
    <div class="owner-layout">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="owner-content">
            <header class="owner-page-head">
                <div>
                    <p class="owner-breadcrumb">FACILITY OWNER <span>/</span> COURT SETTINGS <span>/</span> ADMIN ACCOUNTS</p>
                    <h1>Admin accounts</h1>
                    <p>Manage staff access to <?= ownerAccountsEscape($facilityName) ?>.</p>
                </div>
                <button type="button" id="addAdminButton" class="owner-primary-button"><span aria-hidden="true">+</span> Add admin</button>
            </header>
            <section class="owner-accounts-section" aria-labelledby="accountsHeading">
                <div class="owner-section-heading">
                    <div>
                        <h2 id="accountsHeading">Facility admins</h2>
                        <p>Admins can manage bookings and payments for this facility.</p>
                    </div>
                    <span class="owner-court-count" id="accountCount" aria-live="polite">Loading accounts</span>
                </div>
                <p id="accountStatus" class="owner-form-status" role="status" aria-live="polite"></p>
                <div id="accountList" class="owner-account-list" aria-live="polite"></div>
            </section>
        </main>
    </div>

    <div class="modal-container owner-modal" id="accountModal" aria-hidden="true">
        <div class="modal owner-modal-panel" role="dialog" aria-modal="true" aria-labelledby="accountDialogTitle">
            <form id="adminAccountForm">
                <div class="owner-modal-heading">
                    <div>
                        <p class="owner-breadcrumb">FACILITY ACCESS</p>
                        <h2 id="accountDialogTitle">Add an admin</h2>
                    </div>
                </div>
                <div class="owner-form-grid">
                    <label for="adminFirstName">First name
                        <input type="text" id="adminFirstName" name="first_name" maxlength="100" autocomplete="given-name" required>
                    </label>
                    <label for="adminLastName">Last name
                        <input type="text" id="adminLastName" name="last_name" maxlength="100" autocomplete="family-name" required>
                    </label>
                    <label for="adminEmail">Email
                        <input type="email" id="adminEmail" name="email" maxlength="150" autocomplete="email" required>
                    </label>
                    <label for="adminPhone">Phone number
                        <input type="tel" id="adminPhone" name="phone_number" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" autocomplete="tel" required>
                    </label>
                    <label class="owner-wide-field" for="adminPassword">
                        <span id="adminPasswordLabel">Temporary password</span>
                        <input type="password" id="adminPassword" name="password" minlength="8" autocomplete="new-password" required>
                        <small>Use at least 8 characters.</small>
                    </label>
                </div>
                <p id="adminFormStatus" class="owner-form-status" role="status" aria-live="polite"></p>
                <div class="owner-modal-actions">
                    <button type="button" class="owner-secondary-button" id="cancelAccountModal">Cancel</button>
                    <button type="submit" class="owner-primary-button" id="saveAdminButton">Create account</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-container owner-modal" id="accountStatusModal" aria-hidden="true">
        <div class="modal owner-modal-panel owner-confirm-panel" role="alertdialog" aria-modal="true" aria-labelledby="accountStatusTitle" aria-describedby="accountStatusMessage">
            <div class="owner-modal-heading">
                <div>
                    <p class="owner-breadcrumb">FACILITY ACCESS</p>
                    <h2 id="accountStatusTitle">Confirm account status</h2>
                </div>
            </div>
            <p id="accountStatusMessage" class="owner-confirm-message"></p>
            <div class="owner-modal-actions">
                <button type="button" class="owner-secondary-button" id="cancelAccountStatus">Cancel</button>
                <button type="button" class="owner-danger-button" id="confirmAccountStatus">Confirm</button>
            </div>
        </div>
    </div>
</body>
</html>
