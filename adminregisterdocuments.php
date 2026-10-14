<?php
require_once __DIR__ . "/includes/backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || $userId < 1 || (int) ($_SESSION["role_id"] ?? 0) !== 3) {
    header("Location: signin.php");
    exit();
}
$stmt = $conn->prepare(
    "SELECT a.admin_id, a.status, a.rejection_reason, a.rejected_documents, a.reviewed_at,
            a.government_id_img, a.business_reg_img, a.mayors_permit_img, a.proof_of_property_img,
            u.first_name, u.last_name, u.email, u.phone_number,
            f.facility_name, f.address, f.municipality
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
if (!$application || $application["status"] !== "Rejected") {
    header("Location: owner_application.php");
    exit();
}

$_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));
$rejectedDocuments = array_values(array_filter(explode(",", (string) $application["rejected_documents"])));
$reviewedAt = $application["reviewed_at"]
    ? (new DateTimeImmutable($application["reviewed_at"]))->format("M j, Y, g:i A")
    : "";
$documents = [
    "gov_id" => ["label" => "Government ID", "field" => "government_id_img"],
    "business_registration" => ["label" => "Business registration", "field" => "business_reg_img"],
    "mayors_permit" => ["label" => "Mayor's permit", "field" => "mayors_permit_img"],
    "proof_of_property" => ["label" => "Proof of property", "field" => "proof_of_property_img"],
];
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
    <script src="includes/js/resubmit_application.js?v=20261014-2" defer></script>
    <title>Resubmit documents | CourtConnect</title>
</head>
<body class="owner-application-body">
    <main class="owner-application-card owner-resubmit-card owner-status-page">
        <p class="owner-breadcrumb">COURTCONNECT <span>/</span> RESUBMIT DOCUMENTS</p>
        <header class="status-page-heading resubmit-page-heading">
            <span class="owner-application-status status-rejected-badge"><span aria-hidden="true">!</span> Rejected</span>
            <h1>Resubmit your documents</h1>
            <p>Replace the documents marked below and send your application back for review.</p>
        </header>
        <section class="resubmit-applicant-summary" aria-label="Applicant and facility details">
            <div><span>Facility</span><strong><?= htmlspecialchars((string) ($application["facility_name"] ?: "Facility"), ENT_QUOTES, "UTF-8") ?></strong></div>
            <div><span>Owner</span><strong><?= htmlspecialchars(trim($application["first_name"] . " " . $application["last_name"]), ENT_QUOTES, "UTF-8") ?></strong></div>
            <div><span>Contact</span><strong><?= htmlspecialchars((string) $application["email"], ENT_QUOTES, "UTF-8") ?> · <?= htmlspecialchars((string) $application["phone_number"], ENT_QUOTES, "UTF-8") ?></strong></div>
            <div><span>Location</span><strong><?= htmlspecialchars((string) $application["address"], ENT_QUOTES, "UTF-8") ?><?= $application["municipality"] ? ", " . htmlspecialchars((string) $application["municipality"], ENT_QUOTES, "UTF-8") : "" ?></strong></div>
        </section>
        <section class="status-reason-callout" aria-labelledby="resubmit-reason-heading">
            <h2 id="resubmit-reason-heading">REASON FROM THE REVIEWER</h2>
            <p><?= nl2br(htmlspecialchars((string) $application["rejection_reason"], ENT_QUOTES, "UTF-8")) ?></p>
            <?php if ($reviewedAt): ?><small>Reviewed <?= htmlspecialchars($reviewedAt, ENT_QUOTES, "UTF-8") ?></small><?php endif; ?>
        </section>
        <p class="owner-resubmit-intro">Replace each document marked Needs replacing. You may also replace another document if needed.</p>
        <form id="resubmit-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
            <p class="resubmit-required-count" id="required-document-count" aria-live="polite"></p>
            <?php foreach ($documents as $key => $document):
                $isRequired = in_array($key, $rejectedDocuments, true);
                $storedFile = $application[$document["field"]];
                $viewerUrl = "includes/admin-php/view_document.php?application_id=" . (int) $application["admin_id"] . "&doc=" . rawurlencode($key);
                $inputId = "document-upload-" . $key;
                $storedExtension = strtolower(pathinfo((string) $storedFile, PATHINFO_EXTENSION));
                $storedMime = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp", "pdf" => "application/pdf"][$storedExtension] ?? "";
            ?>
                <section class="owner-resubmit-document<?= $isRequired ? " is-required" : "" ?>" data-document="<?= htmlspecialchars($key, ENT_QUOTES, "UTF-8") ?>" data-required="<?= $isRequired ? "true" : "false" ?>">
                    <div class="resubmit-document-heading">
                        <h2><?= htmlspecialchars($document["label"], ENT_QUOTES, "UTF-8") ?></h2>
                        <?php if ($isRequired): ?>
                            <span class="owner-document-tag is-rejected"><span aria-hidden="true">!</span> Needs replacing</span>
                        <?php else: ?>
                            <span class="owner-document-tag is-accepted"><span aria-hidden="true">✓</span> Accepted</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($storedFile): ?>
                        <button class="owner-secondary-button view-current-button" type="button" data-viewer-url="<?= htmlspecialchars($viewerUrl, ENT_QUOTES, "UTF-8") ?>" data-document-title="<?= htmlspecialchars($document["label"], ENT_QUOTES, "UTF-8") ?>" data-document-mime="<?= htmlspecialchars($storedMime, ENT_QUOTES, "UTF-8") ?>">View current</button>
                    <?php endif; ?>
                    <?php if (!$isRequired): ?>
                        <button class="resubmit-text-action replace-document-button" type="button" aria-expanded="false">Replace</button>
                    <?php endif; ?>
                    <div class="resubmit-file-zone"<?= $isRequired ? "" : " hidden" ?>>
                        <input id="<?= htmlspecialchars($inputId, ENT_QUOTES, "UTF-8") ?>" class="resubmit-file-input" type="file" name="documents[<?= htmlspecialchars($key, ENT_QUOTES, "UTF-8") ?>]" accept=".jpg,.jpeg,.png,.webp,.pdf" aria-label="Upload <?= htmlspecialchars($document["label"], ENT_QUOTES, "UTF-8") ?>"<?= $isRequired ? " required" : "" ?>>
                        <label class="resubmit-drop-area" for="<?= htmlspecialchars($inputId, ENT_QUOTES, "UTF-8") ?>" tabindex="0">
                            <span class="resubmit-upload-icon" aria-hidden="true">↑</span>
                            <span><strong>Choose file</strong><small>or drop it here</small></span>
                            <small>JPG, PNG, WEBP or PDF · up to 5 MB</small>
                        </label>
                        <div class="resubmit-file-preview" hidden aria-live="polite"></div>
                        <p class="resubmit-file-error" role="alert" hidden></p>
                    </div>
                </section>
            <?php endforeach; ?>
            <div class="owner-resubmit-actions">
                <button class="owner-secondary-button" type="button" id="resubmit-cancel">Cancel</button>
                <button class="owner-primary-button" type="submit" id="resubmit-submit" disabled><span class="resubmit-submit-spinner" aria-hidden="true" hidden></span><span id="resubmit-submit-label">Submit for review</span></button>
            </div>
        </form>
    </main>
    <div class="resubmit-viewer-overlay" id="resubmit-viewer" aria-hidden="true">
        <section class="resubmit-viewer-dialog" role="dialog" aria-modal="true" aria-labelledby="resubmit-viewer-title" tabindex="-1">
            <header><h2 id="resubmit-viewer-title">Current document</h2><button type="button" class="resubmit-viewer-close" aria-label="Close document viewer">&times;</button></header>
            <div class="resubmit-viewer-content" id="resubmit-viewer-content"></div>
            <footer class="resubmit-viewer-controls" id="resubmit-viewer-controls" hidden>
                <button type="button" data-viewer-action="zoom-out" aria-label="Zoom out">−</button>
                <button type="button" data-viewer-action="zoom-in" aria-label="Zoom in">+</button>
                <button type="button" data-viewer-action="rotate">Rotate</button>
                <button type="button" data-viewer-action="reset">Reset</button>
            </footer>
        </section>
    </div>
</body>
</html>
