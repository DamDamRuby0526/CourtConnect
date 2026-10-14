<?php
require_once __DIR__ . "/includes/backend-api/config.php";
$ownerOnboardingHeader = true;

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || $userId < 1 || (int) ($_SESSION["role_id"] ?? 0) !== 3) {
    header("Location: signin.php");
    exit();
}

$stmt = $conn->prepare(
    "SELECT a.admin_id, a.status, a.rejection_reason, a.rejected_documents, a.created_at, a.reviewed_at,
            a.government_id_img, a.business_reg_img, a.mayors_permit_img, a.proof_of_property_img,
            u.email, f.facility_name, f.municipality, f.sport_type, f.facility_img
     FROM admins a
     INNER JOIN users u ON u.user_id = a.user_id
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
    header("Location: signin.php");
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
    <title>Application status | CourtConnect</title>
</head>
<body class="owner-application-body owner-onboarding-page">
    <?php include 'includes/navigation/header.php'; ?>
    <main class="owner-application-card owner-status-card owner-status-page">
        <p class="owner-breadcrumb">COURTCONNECT <span>/</span> FACILITY APPLICATION</p>
        <?php if ($application["status"] === "Rejected"): ?>
            <header class="status-page-heading">
                <span class="owner-application-status status-rejected-badge"><span aria-hidden="true">!</span> Rejected</span>
                <h1>Your application needs changes</h1>
                <p>We couldn't approve some of your documents. Replace the ones listed below and submit them again.</p>
                <p class="status-facility-name">Facility: <?= htmlspecialchars((string) ($application["facility_name"] ?: "Facility"), ENT_QUOTES, "UTF-8") ?></p>
            </header>
            <ol class="owner-application-stepper status-rejected-stepper owner-three-stepper" aria-label="Application progress">
                <li class="is-complete"><span class="status-step-icon" aria-hidden="true">✓</span><span>Account</span></li>
                <li class="is-complete"><span class="status-step-icon" aria-hidden="true">✓</span><span>Facility &amp; documents</span></li>
                <li class="is-current is-rejected-step"><span class="status-step-icon" aria-hidden="true">3</span><span>Review: needs changes</span></li>
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
            <header class="status-page-heading">
                <span class="owner-application-status status-pending-badge"><span aria-hidden="true">◷</span> Under review</span>
                <h1>We're reviewing your application</h1>
                <p>Thanks for submitting your documents. Our team will review them and email you at <strong><?= htmlspecialchars((string) $application["email"], ENT_QUOTES, "UTF-8") ?></strong> once there's a decision. You can't access your dashboard until your application is approved.</p>
            </header>
            <ol class="owner-application-stepper status-rejected-stepper owner-three-stepper" aria-label="Application progress">
                <li class="is-complete"><span class="status-step-icon" aria-hidden="true">✓</span><span>Account</span></li>
                <li class="is-complete"><span class="status-step-icon" aria-hidden="true">✓</span><span>Facility &amp; documents</span></li>
                <li class="is-current"><span class="status-step-icon" aria-hidden="true">3</span><span>Review</span></li>
            </ol>
            <section class="owner-pending-summary" aria-label="Application summary">
                <div class="owner-pending-details">
                    <div><span>Facility name</span><strong><?= htmlspecialchars((string) ($application["facility_name"] ?: "Facility"), ENT_QUOTES, "UTF-8") ?></strong></div>
                    <div><span>Municipality</span><strong><?= htmlspecialchars((string) ($application["municipality"] ?: "—"), ENT_QUOTES, "UTF-8") ?></strong></div>
                    <div><span>Facility type</span><strong><?= htmlspecialchars((string) ($application["sport_type"] ?: "—"), ENT_QUOTES, "UTF-8") ?></strong></div>
                    <div><span>Submitted</span><strong><?= htmlspecialchars((new DateTimeImmutable($application["created_at"]))->format("M j, Y, g:i A"), ENT_QUOTES, "UTF-8") ?></strong></div>
                </div>
                <div class="owner-submitted-documents">
                    <h2>Documents submitted</h2>
                    <ul>
                        <?php foreach ([
                            "government_id_img" => "Valid government ID",
                            "business_reg_img" => "DTI / SEC registration",
                            "mayors_permit_img" => "Business / Mayor's permit",
                            "proof_of_property_img" => "Proof of ownership / lease",
                        ] as $documentField => $documentLabel): ?>
                            <li><span aria-hidden="true"><?= !empty($application[$documentField]) ? "✓" : "–" ?></span><?= htmlspecialchars($documentLabel, ENT_QUOTES, "UTF-8") ?></li>
                        <?php endforeach; ?>
                        <li><span aria-hidden="true"><?= !empty($application["facility_img"]) ? "✓" : "–" ?></span><?= !empty($application["facility_img"]) ? "1 facility photo submitted" : "No facility photo recorded" ?></li>
                    </ul>
                </div>
            </section>
            <section class="owner-next-steps">
                <h2>What happens next</h2>
                <ol>
                    <li>Our team checks your documents.</li>
                    <li>You'll get an email with the decision.</li>
                    <li>If approved, sign in to manage your courts.</li>
                    <li>If something needs fixing, you can update and resubmit it.</li>
                </ol>
            </section>
            <div class="status-page-actions owner-pending-actions">
                <a class="owner-primary-button" href="index.php">Back to home page</a>
                <a class="owner-secondary-button status-logout-button" href="logout.php" data-confirm-logout>Log out</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
