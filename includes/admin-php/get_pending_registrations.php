<?php
header("Content-Type: application/json");
header("Cache-Control: private, no-store");
require_once __DIR__ . "/courtconnect_auth_guard.php";
require_once __DIR__ . "/../backend-api/facility_document_storage.php";

$_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));
$sql = "SELECT a.admin_id, a.user_id, a.government_id_img, a.business_reg_img,
               a.mayors_permit_img, a.proof_of_property_img, a.status, a.created_at,
               a.reviewed_at, a.rejection_reason, a.rejected_documents,
               reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name,
               u.first_name, u.last_name, u.email, u.phone_number,
               f.facility_name, f.sport_type, f.address, f.municipality
        FROM admins a
        JOIN users u ON u.user_id = a.user_id
        LEFT JOIN facilities f ON f.facility_id = a.facility_id
        LEFT JOIN users reviewer ON reviewer.user_id = a.reviewed_by
        ORDER BY CASE WHEN a.status = 'Pending' THEN 0 ELSE 1 END,
                 CASE WHEN a.status = 'Pending' THEN a.created_at END ASC,
                 a.created_at DESC";
$result = $conn->query($sql);
$registrations = [];
$documentColumns = [
    "gov_id" => "government_id_img",
    "business_registration" => "business_reg_img",
    "mayors_permit" => "mayors_permit_img",
    "proof_of_property" => "proof_of_property_img",
];

while ($row = $result->fetch_assoc()) {
    $documents = [];
    foreach ($documentColumns as $key => $column) {
        $path = facilityFindDocumentPath($row[$column]);
        $mime = $path ? (new finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $documents[$key] = [
            "available" => $path !== null,
            "mime" => is_string($mime) ? $mime : null,
        ];
        unset($row[$column]);
    }
    $row["documents"] = $documents;
    $row["rejected_documents"] = $row["rejected_documents"]
        ? explode(",", $row["rejected_documents"])
        : [];
    $registrations[] = $row;
}

echo json_encode([
    "success" => true,
    "csrf_token" => $_SESSION["csrf_token"],
    "registrations" => $registrations,
    "pending_count" => count(array_filter($registrations, static fn($row) => $row["status"] === "Pending")),
]);
$conn->close();
