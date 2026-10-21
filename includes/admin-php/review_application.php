<?php
header("Content-Type: application/json");
require_once __DIR__ . "/courtconnect_auth_guard.php";
require_once __DIR__ . "/../backend-api/facility_document_storage.php";
require_once __DIR__ . "/../backend-api/send_email.php";

function reviewApplicationRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    reviewApplicationRespond(405, ["success" => false, "message" => "Use POST to review an application."]);
}

$data = json_decode(file_get_contents("php://input"), true);
$data = is_array($data) ? $data : [];
$csrfToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
$sessionCsrfToken = $_SESSION["csrf_token"] ?? "";
if (
    !is_string($csrfToken) || $csrfToken === ""
    || !is_string($sessionCsrfToken) || $sessionCsrfToken === ""
    || !hash_equals($sessionCsrfToken, $csrfToken)
) {
    reviewApplicationRespond(403, ["success" => false, "message" => "Your session has expired. Refresh the page and try again."]);
}

$adminId = filter_var($data["application_id"] ?? null, FILTER_VALIDATE_INT);
$decision = $data["decision"] ?? "";
$reason = is_string($data["reason"] ?? null) ? $data["reason"] : "";
$flagged = $data["rejected_documents"] ?? [];
$documentColumns = [
    "gov_id" => "government_id_img",
    "business_registration" => "business_reg_img",
    "mayors_permit" => "mayors_permit_img",
    "proof_of_property" => "proof_of_property_img",
];

if (!$adminId || $adminId < 1 || !in_array($decision, ["Approved", "Rejected"], true)) {
    reviewApplicationRespond(400, ["success" => false, "message" => "Choose a valid application and decision."]);
}
if ($decision === "Rejected") {
    if (mb_strlen(trim($reason), "UTF-8") < 10 || mb_strlen($reason, "UTF-8") > 500) {
        reviewApplicationRespond(400, ["success" => false, "message" => "The rejection reason must be between 10 and 500 characters."]);
    }
    if (!is_array($flagged) || !$flagged) {
        reviewApplicationRespond(400, ["success" => false, "message" => "Select at least one document that needs to be replaced."]);
    }
    $flagged = array_values(array_unique(array_filter(
        $flagged,
        static fn($key) => is_string($key) && array_key_exists($key, $documentColumns)
    )));
    if (!$flagged) {
        reviewApplicationRespond(400, ["success" => false, "message" => "Choose at least one valid document to replace."]);
    }
} else {
    $flagged = [];
}

try {
    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start the review transaction.");
    }
    $stmt = $conn->prepare(
        "SELECT a.admin_id, a.user_id, a.status, a.government_id_img, a.business_reg_img,
                a.mayors_permit_img, a.proof_of_property_img, a.reviewed_at, a.reviewed_by,
                u.email, u.first_name, u.last_name,
                reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name
         FROM admins a
         INNER JOIN users u ON u.user_id = a.user_id
         LEFT JOIN users reviewer ON reviewer.user_id = a.reviewed_by
         WHERE a.admin_id = ? AND u.role_id = 3
         FOR UPDATE"
    );
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$application) {
        $conn->rollback();
        reviewApplicationRespond(404, ["success" => false, "message" => "Application not found."]);
    }
    if ($application["status"] !== "Pending") {
        $conn->rollback();
        $reviewerName = trim(($application["reviewer_first_name"] ?? "") . " " . ($application["reviewer_last_name"] ?? ""));
        reviewApplicationRespond(409, [
            "success" => false,
            "already_reviewed" => true,
            "message" => "This application was already reviewed by " . ($reviewerName ?: "another staff member")
                . " on " . ($application["reviewed_at"] ?: "an earlier date") . ".",
        ]);
    }
    if ($decision === "Approved") {
        foreach ($documentColumns as $column) {
            if (!facilityFindDocumentPath($application[$column])) {
                $conn->rollback();
                reviewApplicationRespond(400, ["success" => false, "message" => "All four documents must be present before approval."]);
            }
        }
    }

    $reviewedBy = (int) $_SESSION["user_id"];
    $storedReason = $decision === "Rejected" ? $reason : null;
    $rejectedDocuments = $decision === "Rejected" ? implode(",", $flagged) : null;
    $newStatus = $decision === "Approved" ? "Active" : "Rejected";
    $stmt = $conn->prepare(
        "UPDATE admins
         SET status = ?, reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ?, rejected_documents = ?
         WHERE admin_id = ? AND status = 'Pending'"
    );
    $stmt->bind_param("sissi", $newStatus, $reviewedBy, $storedReason, $rejectedDocuments, $adminId);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        $conn->rollback();
        reviewApplicationRespond(409, ["success" => false, "already_reviewed" => true, "message" => "This application was already reviewed."]);
    }
    $stmt->close();

    if (!$conn->commit()) {
        throw new RuntimeException("Unable to commit the review decision.");
    }
} catch (Throwable $error) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    error_log("Facility application review failed: " . $error->getMessage());
    reviewApplicationRespond(500, ["success" => false, "message" => "Unable to save the application decision."]);
}

$fullName = trim($application["first_name"] . " " . $application["last_name"]);
try {
    $emailResult = sendFacilityApplicationDecisionEmail(
        $application["email"],
        $fullName,
        $decision,
        $reason,
        $flagged
    );
} catch (Throwable $emailError) {
    $emailResult = ["success" => false, "message" => $emailError->getMessage()];
}
if (!$emailResult["success"]) {
    error_log("Facility application decision email failed for application {$adminId}: " . $emailResult["message"]);
}

echo json_encode([
    "success" => true,
    "status" => $newStatus,
    "email_sent" => $emailResult["success"],
    "message" => $decision === "Approved" ? "Application approved." : "Application rejected.",
]);
$conn->close();
