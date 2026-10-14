<?php
require_once __DIR__ . "/includes/backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || $userId < 1 || (int) ($_SESSION["role_id"] ?? 0) !== 3) {
    header("Location: signin.php");
    exit();
}

$stmt = $conn->prepare(
    "SELECT a.admin_id, a.status, a.rejection_reason, a.rejected_documents, a.created_at, a.reviewed_at,
            f.facility_name
     FROM admins a
     LEFT JOIN facilities f ON f.facility_id = a.facility_id
     WHERE a.user_id = ?
     LIMIT 1"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$application) {
    http_response_code(404);
    exit("Facility application not found.");
}
if ($application["status"] === "Active") {
    header("Location: Admin dashboard/admindex.php");
    exit();
}
if (!in_array($application["status"], ["Pending", "Rejected"], true)) {
    header("Location: adminsignin.php");
    exit();
}
$_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));

$documentLabels = [
    "gov_id" => "Government ID",
    "business_registration" => "Business registration",
    "mayors_permit" => "Mayor's permit",
    "proof_of_property" => "Proof of property",
];
$reviewedAt = $application["reviewed_at"]
    ? (new DateTimeImmutable($application["reviewed_at"]))->format("M j, Y, g:i A")
    : "";
$rejectedDocuments = $application["rejected_documents"]
    ? array_values(array_intersect(explode(",", $application["rejected_documents"]), array_keys($documentLabels)))
    : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="includes/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="includes/js/admin_dialog.js" defer></script>
    <title>Application status | CourtConnect</title>
</head>
<body class="owner-application-body">
    <main class="owner-application-card owner-status-card owner-status-page">
        <p class="owner-breadcrumb">COURTCONNECT <span>/</span> FACILITY APPLICATION</p>
        <?php if ($application["status"] === "Rejected"): ?>
            <header class="status-page-heading">
                <span class="owner-application-status status-rejected-badge"><span aria-hidden="true">!</span> Rejected</span>
                <h1>Your application needs changes</h1>
                <p>We couldn't approve some of your documents. Replace the ones listed below and submit them again.</p>
                <p class="status-facility-name">Facility: <?= htmlspecialchars((string) ($application["facility_name"] ?: "Facility"), ENT_QUOTES, "UTF-8") ?></p>
            </header>
            <ol class="owner-application-stepper status-rejected-stepper" aria-label="Application progress">
                <li class="is-complete"><span class="status-step-icon" aria-hidden="true">✓</span>Account created</li>
                <li class="is-complete"><span class="status-step-icon" aria-hidden="true">✓</span>Documents submitted</li>
                <li class="is-complete"><span class="status-step-icon" aria-hidden="true">✓</span>Under review</li>
                <li class="is-current is-rejected-step"><span class="status-step-icon" aria-hidden="true">!</span>Needs changes</li>
            </ol>
            <section class="status-reason-callout" aria-labelledby="rejection-reason-heading">
                <h2 id="rejection-reason-heading">REASON FROM THE REVIEWER</h2>
                <p><?= nl2br(htmlspecialchars((string) ($application["rejection_reason"] ?? ""), ENT_QUOTES, "UTF-8")) ?></p>
                <?php if ($reviewedAt): ?><small>Reviewed <?= htmlspecialchars($reviewedAt, ENT_QUOTES, "UTF-8") ?></small><?php endif; ?>
            </section>
            <section class="owner-application-section status-documents-section">
                <h2>Documents to replace</h2>
                <?php if ($rejectedDocuments): ?>
                    <ul class="status-document-list">
                        <?php foreach ($rejectedDocuments as $key): ?><li><span aria-hidden="true">!</span><?= htmlspecialchars($documentLabels[$key], ENT_QUOTES, "UTF-8") ?></li><?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Please contact CourtConnect support for the documents that need changes.</p>
                <?php endif; ?>
            </section>
            <div class="status-page-actions">
                <a class="owner-primary-button" href="adminregisterdocuments.php">Resubmit documents</a>
                <a class="owner-secondary-button status-logout-button" href="logout.php" data-confirm-logout>Log out</a>
            </div>
        <?php else: ?>
            <div class="owner-application-banner">
                <span class="owner-application-status">Application under review</span>
                <h1>Thanks, <?= htmlspecialchars($_SESSION["first_name"] ?? "there", ENT_QUOTES, "UTF-8") ?>.</h1>
                <p>Your documents are being reviewed. We’ll let you know when there is an update.</p>
                <small>Submitted <?= htmlspecialchars((new DateTimeImmutable($application["created_at"]))->format("M j, Y, g:i A"), ENT_QUOTES, "UTF-8") ?></small>
            </div>
            <ol class="owner-application-stepper">
                <li class="is-complete">Account created</li>
                <li class="is-complete">Documents submitted</li>
                <li class="is-current">Under review</li>
                <li>Approved</li>
            </ol>
        <?php endif; ?>
        <?php if ($application["status"] !== "Rejected"): ?><a class="owner-application-logout" href="logout.php" data-confirm-logout>Log out</a><?php endif; ?>
    </main>
</body>
</html>
