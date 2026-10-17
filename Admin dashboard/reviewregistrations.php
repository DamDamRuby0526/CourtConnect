<?php
require_once __DIR__ . "/../includes/backend-api/config.php";
$staffRoleGuardApi = false;
require_once __DIR__ . "/../includes/admin-php/courtconnect_auth_guard.php";

$pendingResult = $conn->query("SELECT COUNT(*) AS total FROM admins WHERE status = 'Pending'");
$courtconnectPendingCount = (int) ($pendingResult ? $pendingResult->fetch_assoc()["total"] : 0);
$isSuperadmin = (int) ($_SESSION["role_id"] ?? 0) === 5;
$courtconnectDashboardLayout = true;
$courtconnectNavPage = "applications";
$_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION["csrf_token"];
$staffName = trim(($_SESSION["first_name"] ?? "") . " " . ($_SESSION["last_name"] ?? ""));
$staffEmail = $_SESSION["email"] ?? "";
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
    <script src="../includes/js/registration_reviews.js?v=20261014-1" defer></script>
    <title>Facility Applications | CourtConnect</title>
</head>
<body class="owner-ui">
    <div class="owner-layout">
        <?php include "../includes/navigation/admin_header.php"; ?>
        <main class="owner-content staff-application-content" data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>">
            <header class="owner-page-head">
                <div>
                    <p class="owner-breadcrumb"><?= $isSuperadmin ? "SUPERADMIN" : "COURTCONNECT STAFF" ?> <span>/</span> FACILITY APPLICATIONS</p>
                    <h1>Facility applications</h1>
                    <p>Review submitted documents and decisions for facility owners.</p>
                </div>
            </header>

            <section class="application-toolbar" aria-label="Application filters">
                <div class="application-tabs" role="tablist" aria-label="Application status">
                    <button type="button" class="is-active" data-status-filter="Pending" role="tab" aria-selected="true">Pending <span data-tab-count="Pending">0</span></button>
                    <button type="button" data-status-filter="Active" role="tab" aria-selected="false">Approved <span data-tab-count="Active">0</span></button>
                    <button type="button" data-status-filter="Rejected" role="tab" aria-selected="false">Rejected <span data-tab-count="Rejected">0</span></button>
                    <button type="button" data-status-filter="All" role="tab" aria-selected="false">All <span data-tab-count="All">0</span></button>
                </div>
                <div class="application-search-sort">
                    <label class="application-search-label" for="application-search">Search applications</label>
                    <input id="application-search" type="search" placeholder="Applicant, email, or facility">
                    <label class="application-sort-label" for="application-sort">Sort</label>
                    <select id="application-sort">
                        <option value="oldest">Oldest first</option>
                        <option value="newest">Newest first</option>
                    </select>
                </div>
            </section>
            <p id="reviewStatus" class="application-load-status" role="status" aria-live="polite">Loading applications...</p>
            <section id="pendingRegistrations" class="application-list" aria-live="polite"></section>
        </main>
    </div>

    <div class="application-review-overlay" id="review-modal-overlay" aria-hidden="true">
        <section class="application-review-modal" role="dialog" aria-modal="true" aria-labelledby="review-modal-title" tabindex="-1">
            <header class="review-modal-header">
                <h2 id="review-modal-title">Review application</h2>
                <button type="button" data-close-review class="review-modal-close" aria-label="Close review">&times;</button>
            </header>
            <div class="review-modal-columns">
                <section class="review-document-column" aria-label="Application documents">
                    <div class="review-document-toolbar">
                        <button type="button" id="document-previous" aria-label="Previous document">&larr;</button>
                        <h3 id="document-heading">Document 1 of 4</h3>
                        <button type="button" id="document-next" aria-label="Next document">&rarr;</button>
                    </div>
                    <div class="review-document-viewer" id="document-viewer">
                        <p class="review-document-placeholder">Choose a document to view.</p>
                    </div>
                    <div class="review-document-controls" id="image-controls" hidden>
                        <button type="button" data-image-action="zoom-out" aria-label="Zoom out">−</button>
                        <button type="button" data-image-action="zoom-in" aria-label="Zoom in">+</button>
                        <button type="button" data-image-action="reset">Reset</button>
                        <button type="button" data-image-action="rotate">Rotate</button>
                        <a id="document-open-new" href="#" target="_blank" rel="noopener" hidden>Open in new tab</a>
                    </div>
                    <nav class="review-document-strip" id="document-strip" aria-label="Documents"></nav>
                </section>
                <aside class="review-details-column">
                    <h3>Applicant details</h3>
                    <dl id="review-applicant-details"></dl>
                    <div id="review-readonly-state" class="review-readonly-state" hidden></div>
                    <div class="review-decision-area">
                        <p id="approve-help" class="review-decision-help">Open all documents to enable approval.</p>
                        <button type="button" class="review-approve-button" id="approve-application" disabled>Approve application</button>
                        <button type="button" class="review-reject-toggle" id="show-rejection">Reject</button>
                        <form id="rejection-form" hidden>
                            <label for="rejection-reason">Reason for rejection</label>
                            <div class="rejection-reason-chips">
                                <button type="button" data-reason="The document is blurry or unreadable.">Blurry or unreadable</button>
                                <button type="button" data-reason="The document has expired.">Expired document</button>
                                <button type="button" data-reason="The name does not match the government ID.">Name doesn't match the ID</button>
                                <button type="button" data-reason="This is the wrong document type.">Wrong document type</button>
                                <button type="button" data-reason="The required document is missing.">Missing document</button>
                                <button type="button" data-reason="Other: ">Other</button>
                            </div>
                            <textarea id="rejection-reason" minlength="10" maxlength="500" required></textarea>
                            <small class="rejection-character-count" id="rejection-character-count">0 / 500</small>
                            <fieldset>
                                <legend>Which documents need to be replaced?</legend>
                                <label><input type="checkbox" name="rejected_documents" value="gov_id"> Government ID</label>
                                <label><input type="checkbox" name="rejected_documents" value="business_registration"> Business registration</label>
                                <label><input type="checkbox" name="rejected_documents" value="mayors_permit"> Mayor's permit</label>
                                <label><input type="checkbox" name="rejected_documents" value="proof_of_property"> Proof of property</label>
                            </fieldset>
                            <p id="rejection-validation-message" class="review-validation-message" role="alert"></p>
                            <button type="submit" class="review-send-rejection" id="send-rejection">Send rejection</button>
                        </form>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</body>
</html>
