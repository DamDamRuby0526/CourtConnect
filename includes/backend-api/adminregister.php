<?php

header("Content-Type: application/json");
require_once __DIR__ . "/config.php";

function saveRegistrationUpload(array $file, string $directory, array $allowedMimeTypes, array &$uploadedPaths): string
{
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Please upload all required documents.");
    }

    if (($file["size"] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException("Each uploaded file must be smaller than 10 MB.");
    }

    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file["tmp_name"]);
    finfo_close($fileInfo);

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException("One of the uploaded files has an unsupported file type.");
    }

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to prepare the document upload folder.");
    }

    $fileName = bin2hex(random_bytes(16)) . "." . $allowedMimeTypes[$mimeType];
    $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file["tmp_name"], $filePath)) {
        throw new RuntimeException("Unable to save one of the uploaded files.");
    }

    $uploadedPaths[] = $filePath;
    return $fileName;
}

$firstName = trim($_POST["first_name"] ?? "");
$lastName = trim($_POST["last_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phoneNumber = trim($_POST["phone_number"] ?? "");
$password = $_POST["password"] ?? "";
$facilityName = trim($_POST["facility_name"] ?? "");
$facilityAddress = trim($_POST["facility_address"] ?? "");
$municipality = trim($_POST["municipality"] ?? "");
$facilityTypes = [
    "badminton" => "Badminton",
    "pickleball" => "Pickleball",
    "both" => "Both"
];
$facilityType = $facilityTypes[strtolower(trim($_POST["facility_type"] ?? ""))] ?? "";

if (
    $firstName === "" || $lastName === "" || $email === "" || $phoneNumber === "" ||
    $password === "" || $facilityName === "" || $facilityAddress === "" ||
    $municipality === "" || $facilityType === ""
) {
    echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email address."]);
    exit();
}

if (!preg_match('/^09\d{9}$/', $phoneNumber)) {
    echo json_encode(["success" => false, "message" => "Enter a valid 11-digit Philippine phone number starting with 09."]);
    exit();
}

if (strlen($password) < 8) {
    echo json_encode(["success" => false, "message" => "Password must be at least 8 characters."]);
    exit();
}

$allowedDocuments = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp",
    "image/gif" => "gif",
    "application/pdf" => "pdf"
];
$allowedImages = array_diff_key($allowedDocuments, ["application/pdf" => true]);
$uploadedPaths = [];
$transactionStarted = false;

try {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR phone_number = ?");
    $stmt->bind_param("ss", $email, $phoneNumber);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Email or phone number is already registered."]);
        $stmt->close();
        exit();
    }
    $stmt->close();

    $uploadRoot = __DIR__ . "/../uploads";
    $validId = saveRegistrationUpload($_FILES["valid_id"] ?? [], $uploadRoot . "/verification", $allowedDocuments, $uploadedPaths);
    $businessRegistration = saveRegistrationUpload($_FILES["business_registration"] ?? [], $uploadRoot . "/verification", $allowedDocuments, $uploadedPaths);
    $businessPermit = saveRegistrationUpload($_FILES["business_permit"] ?? [], $uploadRoot . "/verification", $allowedDocuments, $uploadedPaths);
    $proofOfOccupancy = saveRegistrationUpload($_FILES["proof_of_occupancy"] ?? [], $uploadRoot . "/verification", $allowedDocuments, $uploadedPaths);

    $photoUpload = $_FILES["facility_photos"] ?? [];
    if (isset($photoUpload["name"]) && is_array($photoUpload["name"])) {
        $photoUpload = [
            "name" => $photoUpload["name"][0] ?? "",
            "type" => $photoUpload["type"][0] ?? "",
            "tmp_name" => $photoUpload["tmp_name"][0] ?? "",
            "error" => $photoUpload["error"][0] ?? UPLOAD_ERR_NO_FILE,
            "size" => $photoUpload["size"][0] ?? 0
        ];
    }
    $facilityImage = saveRegistrationUpload($photoUpload, $uploadRoot . "/facility", $allowedImages, $uploadedPaths);

    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare("INSERT INTO facilities (facility_name, sport_type, address, municipality, opening_time, closing_time, facility_img, qr_img) VALUES (?, ?, ?, ?, NULL, NULL, ?, '')");
    $stmt->bind_param("sssss", $facilityName, $facilityType, $facilityAddress, $municipality, $facilityImage);
    $stmt->execute();
    $facilityId = $stmt->insert_id;
    $stmt->close();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $roleId = 3;
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone_number, password, role_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $firstName, $lastName, $email, $phoneNumber, $hashedPassword, $roleId);
    $stmt->execute();
    $userId = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO admins (user_id, facility_id, government_id_img, business_reg_img, mayors_permit_img, proof_of_property_img, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("iissssss", $userId, $facilityId, $validId, $businessRegistration, $businessPermit, $proofOfOccupancy, $firstName, $lastName);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $transactionStarted = false;
    $_SESSION["pending_facility_user_id"] = $userId;

    echo json_encode([
        "success" => true,
        "message" => "Your facility application was submitted and is waiting for developer review.",
        "user_id" => $userId,
        "requires_review" => true
    ]);
} catch (Throwable $error) {
    if ($transactionStarted) {
        $conn->rollback();
    }

    foreach ($uploadedPaths as $uploadedPath) {
        if (is_file($uploadedPath)) {
            unlink($uploadedPath);
        }
    }

    echo json_encode([
        "success" => false,
        "message" => $error instanceof RuntimeException ? $error->getMessage() : "Registration failed. Please try again."
    ]);
}

$conn->close();
