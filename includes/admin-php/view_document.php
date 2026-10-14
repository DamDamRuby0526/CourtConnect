<?php
require_once __DIR__ . "/../backend-api/config.php";
require_once __DIR__ . "/../backend-api/facility_document_storage.php";

$documentColumns = [
    "gov_id" => "government_id_img",
    "business_registration" => "business_reg_img",
    "mayors_permit" => "mayors_permit_img",
    "proof_of_property" => "proof_of_property_img",
];
$applicationId = filter_var($_GET["application_id"] ?? null, FILTER_VALIDATE_INT);
$documentKey = $_GET["doc"] ?? "";
$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
$roleId = (int) ($_SESSION["role_id"] ?? 0);

if (!$userId || !in_array($roleId, [3, 4], true) || !isset($documentColumns[$documentKey])) {
    http_response_code(403);
    exit("Forbidden");
}
if (!in_array($_SERVER["REQUEST_METHOD"], ["GET", "HEAD"], true)) {
    header("Allow: GET, HEAD");
    http_response_code(405);
    exit("Method not allowed");
}
if (!$applicationId || $applicationId < 1) {
    http_response_code(404);
    exit("Document not found");
}

$column = $documentColumns[$documentKey];
$stmt = $conn->prepare(
    "SELECT a.user_id, a.`$column` AS stored_name
     FROM admins a
     INNER JOIN users owner ON owner.user_id = a.user_id
     WHERE a.admin_id = ? AND owner.role_id = 3
     LIMIT 1"
);
$stmt->bind_param("i", $applicationId);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$application) {
    http_response_code(404);
    exit("Document not found");
}
if ($roleId === 4) {
    $staffStmt = $conn->prepare(
        "SELECT 1
         FROM users u
         INNER JOIN roles r ON r.role_id = u.role_id
         WHERE u.user_id = ? AND u.is_verified = 1 AND r.role_name = 'courtconnect'
         LIMIT 1"
    );
    $staffStmt->bind_param("i", $userId);
    $staffStmt->execute();
    $isStaff = $staffStmt->get_result()->fetch_row();
    $staffStmt->close();
    if (!$isStaff) {
        http_response_code(403);
        exit("Forbidden");
    }
}
if ($roleId !== 4 && (int) $application["user_id"] !== $userId) {
    http_response_code(403);
    exit("Forbidden");
}

$path = facilityFindDocumentPath($application["stored_name"]);
if (!$path) {
    http_response_code(404);
    exit("Document file missing");
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
$allowedTypes = ["image/jpeg", "image/png", "image/webp", "application/pdf"];
if (!is_string($mime) || !in_array($mime, $allowedTypes, true)) {
    http_response_code(404);
    exit("Document file missing");
}

header("Content-Type: " . $mime);
header("Content-Disposition: inline; filename=\"application-document\"");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store, max-age=0");
header("Content-Length: " . filesize($path));
if ($_SERVER["REQUEST_METHOD"] !== "HEAD") {
    readfile($path);
}
$conn->close();
