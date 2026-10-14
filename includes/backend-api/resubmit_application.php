<?php
header("Content-Type: application/json");
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/facility_document_storage.php";

function resubmitRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || $userId < 1 || (int) ($_SESSION["role_id"] ?? 0) !== 3) {
    resubmitRespond(403, ["success" => false, "message" => "Sign in as the facility owner to resubmit documents."]);
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    resubmitRespond(405, ["success" => false, "message" => "Use POST to resubmit documents."]);
}
$csrfToken = $_POST["csrf_token"] ?? "";
$sessionCsrfToken = $_SESSION["csrf_token"] ?? "";
if (
    !is_string($csrfToken) || $csrfToken === ""
    || !is_string($sessionCsrfToken) || $sessionCsrfToken === ""
    || !hash_equals($sessionCsrfToken, $csrfToken)
) {
    resubmitRespond(403, ["success" => false, "message" => "Your session has expired. Refresh the page and try again."]);
}

$documentFields = [
    "gov_id" => "government_id_img",
    "business_registration" => "business_reg_img",
    "mayors_permit" => "mayors_permit_img",
    "proof_of_property" => "proof_of_property_img",
];
$uploaded = [];
try {
    $stmt = $conn->prepare(
        "SELECT admin_id, rejected_documents
         FROM admins
         WHERE user_id = ? AND status = 'Rejected'
         LIMIT 1"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$application) {
        resubmitRespond(409, ["success" => false, "message" => "There is no rejected application to resubmit."]);
    }

    $required = array_values(array_intersect(
        array_filter(explode(",", (string) $application["rejected_documents"])),
        array_keys($documentFields)
    ));
    if (!$required) {
        resubmitRespond(409, ["success" => false, "message" => "No documents are marked for replacement. Contact CourtConnect support."]);
    }
    foreach ($required as $key) {
        if (($_FILES["documents"]["error"][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            resubmitRespond(400, ["success" => false, "message" => "Upload every document marked Needs replacing."]);
        }
    }
    foreach ($documentFields as $key => $column) {
        $file = $_FILES["documents"]["tmp_name"][$key] ?? null;
        $error = $_FILES["documents"]["error"][$key] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $document = [
            "name" => $_FILES["documents"]["name"][$key] ?? "",
            "type" => $_FILES["documents"]["type"][$key] ?? "",
            "tmp_name" => $file,
            "error" => $error,
            "size" => $_FILES["documents"]["size"][$key] ?? 0,
        ];
        $uploaded[$key] = saveFacilityDocumentUpload($document);
    }
} catch (Throwable $error) {
    foreach ($uploaded as $fileName) {
        $path = facilityPrivateDocumentDirectory() . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path) && !unlink($path)) {
            error_log("Unable to remove failed resubmission upload: {$path}");
        }
    }
    resubmitRespond(400, ["success" => false, "message" => $error->getMessage()]);
}

if (!$conn->begin_transaction()) {
    foreach ($uploaded as $fileName) {
        $path = facilityPrivateDocumentDirectory() . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path) && !unlink($path)) {
            error_log("Unable to clean up resubmission upload after transaction start failure: {$path}");
        }
    }
    resubmitRespond(500, ["success" => false, "message" => "Unable to start the resubmission. Please try again."]);
}
try {
    $lockStmt = $conn->prepare(
        "SELECT admin_id, government_id_img, business_reg_img, mayors_permit_img, proof_of_property_img
         FROM admins
         WHERE user_id = ? AND status = 'Rejected'
         FOR UPDATE"
    );
    $lockStmt->bind_param("i", $userId);
    $lockStmt->execute();
    $lockedApplication = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();
    if (!$lockedApplication || (int) $lockedApplication["admin_id"] !== (int) $application["admin_id"]) {
        throw new RuntimeException("The application changed before the resubmission could be saved.");
    }

    $setParts = [];
    $types = "";
    $values = [];
    $oldFiles = [];
    foreach ($uploaded as $key => $fileName) {
        $column = $documentFields[$key];
        $setParts[] = "`{$column}` = ?";
        $types .= "s";
        $values[] = $fileName;
        $oldFiles[] = $lockedApplication[$column];
    }
    $setParts[] = "status = 'Pending'";
    $setParts[] = "rejection_reason = NULL";
    $setParts[] = "rejected_documents = NULL";
    $setParts[] = "created_at = NOW()";
    $types .= "i";
    $values[] = (int) $application["admin_id"];
    $stmt = $conn->prepare(
        "UPDATE admins SET " . implode(", ", $setParts) . " WHERE admin_id = ? AND status = 'Rejected'"
    );
    $bind = [$types];
    foreach ($values as $index => $value) {
        $bind[] = &$values[$index];
    }
    call_user_func_array([$stmt, "bind_param"], $bind);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        throw new RuntimeException("The application changed before the resubmission could be saved.");
    }
    $stmt->close();
    if (!$conn->commit()) {
        throw new RuntimeException("Unable to commit the document resubmission.");
    }
} catch (Throwable $error) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    foreach ($uploaded as $fileName) {
        $path = facilityPrivateDocumentDirectory() . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path) && !unlink($path)) {
            error_log("Unable to remove failed resubmission upload: {$path}");
        }
    }
    error_log("Facility application resubmission failed: " . $error->getMessage());
    resubmitRespond(500, ["success" => false, "message" => "Unable to save your resubmitted documents."]);
}

foreach ($oldFiles as $oldFile) {
    if (!$oldFile) continue;
    $oldPath = facilityFindDocumentPath($oldFile);
    if ($oldPath && !unlink($oldPath)) {
        error_log("Unable to remove replaced facility document: {$oldPath}");
    }
}

echo json_encode([
    "success" => true,
    "message" => "Your updated documents have been submitted successfully. Your application is now pending review by the CourtConnect administrator.",
]);
$conn->close();
