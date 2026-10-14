<?php

function facilityPrivateDocumentDirectory(): string
{
    $configuredDirectory = getenv("COURTCONNECT_PRIVATE_UPLOADS_DIR");
    $directory = is_string($configuredDirectory) && $configuredDirectory !== ""
        ? rtrim($configuredDirectory, DIRECTORY_SEPARATOR)
        : dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . "private_uploads"
            . DIRECTORY_SEPARATOR . "facility_verification";
    $isAbsolute = DIRECTORY_SEPARATOR === "\\"
        ? (bool) preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\\\\\\\\)/', $directory)
        : str_starts_with($directory, DIRECTORY_SEPARATOR);
    if (!$isAbsolute) {
        throw new RuntimeException("COURTCONNECT_PRIVATE_UPLOADS_DIR must be an absolute path.");
    }
    $documentRoot = realpath($_SERVER["DOCUMENT_ROOT"] ?? "");
    if ($documentRoot) {
        $candidate = realpath($directory) ?: $directory;
        $publicPrefix = strtolower(rtrim($documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
        if (str_starts_with(strtolower(rtrim($candidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR), $publicPrefix)) {
            throw new RuntimeException("Facility verification documents must be stored outside the web document root.");
        }
    }
    return $directory;
}

function facilityLegacyDocumentDirectory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . "uploads"
        . DIRECTORY_SEPARATOR . "verification";
}

function facilityFindDocumentPath(?string $storedName): ?string
{
    if (!$storedName || basename($storedName) !== $storedName) {
        return null;
    }

    $privateDirectory = facilityPrivateDocumentDirectory();
    $legacyDirectory = facilityLegacyDocumentDirectory();
    foreach ([$privateDirectory, $legacyDirectory] as $directory) {
        $path = realpath($directory . DIRECTORY_SEPARATOR . $storedName);
        $root = realpath($directory);
        if ($path && $root && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) {
            return $path;
        }
    }

    return null;
}

function saveFacilityDocumentUpload(array $file): string
{
    $allowedTypes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "application/pdf" => "pdf",
    ];
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload all required documents.");
    }
    $temporaryPath = $file["tmp_name"] ?? "";
    $actualSize = is_string($temporaryPath) && is_file($temporaryPath)
        ? filesize($temporaryPath)
        : false;
    if (
        ($file["size"] ?? 0) > 5 * 1024 * 1024
        || $actualSize === false
        || $actualSize > 5 * 1024 * 1024
        || !is_uploaded_file($temporaryPath)
    ) {
        throw new RuntimeException("Each document must be no larger than 5 MB.");
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file["tmp_name"]);
    if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
        throw new RuntimeException("Documents must be JPG, PNG, WEBP, or PDF files.");
    }

    $directory = facilityPrivateDocumentDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to prepare the private document folder.");
    }

    $fileName = bin2hex(random_bytes(16)) . "." . $allowedTypes[$mimeType];
    $targetPath = $directory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
        throw new RuntimeException("Unable to save the uploaded document.");
    }
    if (!chmod($targetPath, 0600)) {
        unlink($targetPath);
        throw new RuntimeException("Unable to protect the uploaded document.");
    }
    return $fileName;
}
