<?php
header("Content-Type: application/json");
// require_once __DIR__ . "/admin_auth_guard.php";
require_once __DIR__ . "/../backend-api/config.php";

if (empty($_POST)) {
    echo json_encode(["success" => false, "message" => "No data received."]);
    exit();
}

$facilityName = trim($_POST["facility_name"] ?? "");
$sportType    = trim($_POST["sport_type"] ?? "");
$address      = trim($_POST["address"] ?? "");
$municipality = trim($_POST["municipality"] ?? "");
$openingTime  = trim($_POST["opening_time"] ?? "");
$closingTime  = trim($_POST["closing_time"] ?? "");

// Function to process uploaded files
function handleFileUpload($fileKey, $targetFolder) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]["error"] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES[$fileKey]["tmp_name"];
        $fileName = time() . "_" . basename($_FILES[$fileKey]["name"]);
        $uploadDir = __DIR__ . "/../uploads/" . $targetFolder . "/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            return $fileName;
        }
    }
    return "";
}

$facilityImg = handleFileUpload("facility_img", "facility");
$qrImg       = handleFileUpload("qr_img", "qr");

$sql = "INSERT INTO facilities (facility_name, sport_type, address, municipality, opening_time, closing_time, facility_img, qr_img) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssss", $facilityName, $sportType, $address, $municipality, $openingTime, $closingTime,
    $facilityImg, $qrImg);


if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Facility added successfully.",
        $stmt->insert_id
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Failed to add facility."
    ]);
}
$stmt->close();
$conn->close();
?>