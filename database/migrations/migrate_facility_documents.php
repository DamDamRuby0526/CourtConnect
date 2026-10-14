<?php

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit("CLI only.\n");
}
if (($argv[1] ?? "") !== "--confirm-after-backup") {
    fwrite(STDERR, "Export a database and filesystem backup first, then rerun with --confirm-after-backup.\n");
    exit(1);
}

require_once __DIR__ . "/../../includes/backend-api/facility_document_storage.php";

$sourceDirectory = facilityLegacyDocumentDirectory();
$targetDirectory = facilityPrivateDocumentDirectory();
$projectRoot = realpath(dirname(__DIR__, 2));
$webRoot = $projectRoot ? realpath(dirname($projectRoot)) : false;
$targetCandidate = realpath($targetDirectory) ?: $targetDirectory;
if (
    $webRoot
    && str_starts_with(
        strtolower(rtrim($targetCandidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR),
        strtolower(rtrim($webRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
    )
) {
    fwrite(STDERR, "Private document storage must be outside the web root.\n");
    exit(1);
}
if (!is_dir($sourceDirectory)) {
    fwrite(STDERR, "Legacy verification folder not found: {$sourceDirectory}\n");
    exit(1);
}
if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0700, true) && !is_dir($targetDirectory)) {
    fwrite(STDERR, "Unable to create private verification folder: {$targetDirectory}\n");
    exit(1);
}

$moved = 0;
$skipped = 0;
foreach (new DirectoryIterator($sourceDirectory) as $entry) {
    if ($entry->isDot() || !$entry->isFile() || $entry->isLink() || $entry->getFilename() === ".htaccess") {
        continue;
    }
    $source = $entry->getPathname();
    $target = $targetDirectory . DIRECTORY_SEPARATOR . $entry->getFilename();
    if (is_file($target)) {
        if (hash_file("sha256", $source) !== hash_file("sha256", $target)) {
            fwrite(STDERR, "Name collision with different content: {$entry->getFilename()}\n");
            exit(1);
        }
        if (!chmod($target, 0600)) {
            fwrite(STDERR, "Unable to protect existing private file: {$entry->getFilename()}\n");
            exit(1);
        }
        if (!unlink($source)) {
            fwrite(STDERR, "Unable to remove duplicate legacy file: {$source}\n");
            exit(1);
        }
        $skipped++;
        continue;
    }
    if (!copy($source, $target) || hash_file("sha256", $source) !== hash_file("sha256", $target)) {
        if (is_file($target) && !unlink($target)) {
            fwrite(STDERR, "Unable to remove partial copy: {$target}\n");
        }
        fwrite(STDERR, "Copy verification failed: {$entry->getFilename()}\n");
        exit(1);
    }
    if (!chmod($target, 0600)) {
        if (!unlink($target)) {
            fwrite(STDERR, "Unable to remove unprotected copy: {$target}\n");
        }
        fwrite(STDERR, "Unable to protect migrated file: {$entry->getFilename()}\n");
        exit(1);
    }
    if (!unlink($source)) {
        fwrite(STDERR, "Copied file but could not remove legacy original: {$source}\n");
        exit(1);
    }
    $moved++;
}

echo "Moved {$moved} files; removed {$skipped} identical duplicates.\n";
