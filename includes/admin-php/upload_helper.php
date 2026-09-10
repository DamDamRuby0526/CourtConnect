<?php

/**
 * Handles a single optional image upload from $_FILES.
 *
 * - Returns null if no file was submitted for this field (caller should
 *   keep the existing image in that case).
 * - Returns the generated filename (not the full path) on success.
 * - Throws RuntimeException on any validation or filesystem failure.
 *
 * @param string $fieldName  Name attribute of the <input type="file"> field.
 * @param string $uploadDir  Absolute directory to save the file into.
 * @return string|null
 */
function handleImageUpload(string $fieldName, string $uploadDir): ?string
{
    // No file submitted for this field — nothing to do.
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload failed for {$fieldName}.");
    }

    // Validate actual file content, not just the client-supplied name/type.
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException("Invalid file type for {$fieldName}. Only JPG, PNG, or WEBP images are allowed.");
    }

    $maxBytes = 5 * 1024 * 1024; // 5 MB
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException("File for {$fieldName} exceeds the 5MB size limit.");
    }

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException("Unable to prepare the upload directory.");
        }
    }

    // Generate a unique filename to avoid collisions and path traversal
    // (never trust the original client-supplied filename).
    // Checks if an image file was selected and uploaded without errors (UPLOAD_ERR_OK).
    // Generates a unique filename using a timestamp (time() . "_" . basename(...)) to prevent overwriting existing files.
    // Creates the target directory automatically if it doesn't already exist (mkdir($uploadDir, 0777, true)).
    // Moves the file from PHP's temporary folder to ../uploads/{folder}/ using move_uploaded_file().
    // Returns the new filename string (or an empty string "" if no file was uploaded).
    $extension = $allowedMimeTypes[$mimeType];
    $generatedName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = rtrim($uploadDir, '/') . '/' . $generatedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException("Failed to save uploaded file for {$fieldName}.");
    }

    return $generatedName;
}