<?php
header("Content-Type: application/json");
require_once __DIR__ . "/facility_owner_guard.php";

class FacilityBrandingInputException extends RuntimeException
{
}

$facilityId = (int) $_SESSION["facility_id"];
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$facilityName = trim((string) ($_POST["facility_name"] ?? ""));
$nameLength = function_exists("mb_strlen")
    ? mb_strlen($facilityName, "UTF-8")
    : preg_match_all("/./us", $facilityName);
if ($facilityName === "" || $nameLength === false || $nameLength > 250) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Enter a facility name between 1 and 250 characters."]);
    exit();
}

$file = $_FILES["facility_logo"] ?? null;
$hasUpload = is_array($file) && ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$qrFile = $_FILES["gcash_qr"] ?? null;
$hasQrUpload = is_array($qrFile) && ($qrFile["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$removeLogo = ($_POST["remove_logo"] ?? "0") === "1";
if (($hasUpload || $hasQrUpload) && $removeLogo) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Choose either a new logo or remove the current logo."]);
    exit();
}

$allowedMimeTypes = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp",
];
$maxUploadBytes = 2 * 1024 * 1024;
$uploadDirectory = __DIR__ . "/../../uploads/facility";
$qrUploadDirectory = __DIR__ . "/../../uploads/qr";
$newLogoPath = null;
$newLogoFilename = null;
$oldLogoFilename = null;
$newQrPath = null;
$newQrFilename = null;
$oldQrFilename = null;

try {
    $storeImage = static function (array $imageFile, string $targetDirectory, string $label) use ($allowedMimeTypes, $maxUploadBytes): array {
        if (($imageFile["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new FacilityBrandingInputException("The {$label} upload failed. Please choose the image again.");
        }
        if (($imageFile["size"] ?? 0) < 1 || $imageFile["size"] > $maxUploadBytes) {
            throw new FacilityBrandingInputException("{$label} images must be 2 MB or smaller.");
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($imageFile["tmp_name"]);
        $imageInfo = @getimagesize($imageFile["tmp_name"]);
        if (!isset($allowedMimeTypes[$mimeType]) || !$imageInfo || ($imageInfo["mime"] ?? "") !== $mimeType) {
            throw new FacilityBrandingInputException("Upload a valid JPG, PNG, or WebP {$label} image.");
        }
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException("Unable to prepare {$label} storage.");
        }

        $filename = bin2hex(random_bytes(16)) . "." . $allowedMimeTypes[$mimeType];
        $path = $targetDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($imageFile["tmp_name"], $path)) {
            throw new RuntimeException("Unable to save the uploaded {$label} image.");
        }
        return ["filename" => $filename, "path" => $path];
    };

    if ($hasUpload) {
        $newLogo = $storeImage($file, $uploadDirectory, "logo");
        $newLogoFilename = $newLogo["filename"];
        $newLogoPath = $newLogo["path"];
    }
    if ($hasQrUpload) {
        $newQr = $storeImage($qrFile, $qrUploadDirectory, "GCash QR");
        $newQrFilename = $newQr["filename"];
        $newQrPath = $newQr["path"];
    }

    if (!$conn->begin_transaction()) {
        throw new RuntimeException("Unable to start the facility branding update.");
    }
    $currentStmt = $conn->prepare(
        "SELECT facility_name, facility_img, qr_img
         FROM facilities
         WHERE facility_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $currentStmt->bind_param("i", $facilityId);
    $currentStmt->execute();
    $current = $currentStmt->get_result()->fetch_assoc();
    $currentStmt->close();
    if (!$current) {
        throw new RuntimeException("Your facility could not be found.");
    }

    $facilityLogo = (string) $current["facility_img"];
    $facilityQr = (string) $current["qr_img"];
    $oldLogoFilename = basename($facilityLogo);
    $oldQrFilename = basename($facilityQr);
    if ($hasUpload) {
        $facilityLogo = $newLogoFilename;
    } elseif ($removeLogo) {
        $facilityLogo = "";
    }
    if ($hasQrUpload) {
        $facilityQr = $newQrFilename;
    }

    if ($facilityName !== $current["facility_name"]
        || $facilityLogo !== $current["facility_img"]
        || $facilityQr !== $current["qr_img"]) {
        $updateStmt = $conn->prepare(
            "UPDATE facilities
             SET facility_name = ?, facility_img = ?, qr_img = ?
             WHERE facility_id = ?"
        );
        $updateStmt->bind_param("sssi", $facilityName, $facilityLogo, $facilityQr, $facilityId);
        $updateStmt->execute();
        $updateStmt->close();
    }

    if (!$conn->commit()) {
        throw new RuntimeException("Unable to save facility branding.");
    }

    if (($hasUpload || $removeLogo) && $oldLogoFilename !== "" && $oldLogoFilename !== $facilityLogo) {
        $oldLogoPath = $uploadDirectory . DIRECTORY_SEPARATOR . $oldLogoFilename;
        if (is_file($oldLogoPath)) {
            unlink($oldLogoPath);
        }
    }
    if ($hasQrUpload && $oldQrFilename !== "" && $oldQrFilename !== $facilityQr) {
        $oldQrPath = $qrUploadDirectory . DIRECTORY_SEPARATOR . $oldQrFilename;
        if (is_file($oldQrPath)) {
            unlink($oldQrPath);
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Facility branding saved successfully.",
        "facility_name" => $facilityName,
        "facility_img" => $facilityLogo,
        "qr_img" => $facilityQr,
    ]);
} catch (Throwable $error) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    if ($newLogoPath && is_file($newLogoPath)) {
        unlink($newLogoPath);
    }
    if ($newQrPath && is_file($newQrPath)) {
        unlink($newQrPath);
    }
    error_log("Facility branding update failed for facility {$facilityId}: " . $error->getMessage());
    http_response_code($error instanceof FacilityBrandingInputException ? 400 : 500);
    echo json_encode([
        "success" => false,
        "message" => $error instanceof FacilityBrandingInputException ? $error->getMessage() : "Unable to save facility branding. Please try again.",
    ]);
}