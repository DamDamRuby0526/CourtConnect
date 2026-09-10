<?php

header("Content-Type: application/json");
require 'config.php';

// This one will read JSON from Android
$data = json_decode(file_get_contents("php://input"), true);

//Check if data exists
if (!$data){
    echo json_encode ([
        "success" => false,
        "message" => "No data received"
    ]);
    exit();
}

//Get data
$firstName = trim($data["first_name"]);
$lastName = trim($data["last_name"]);
$email = trim($data["email"]);
$phoneNumber = trim($data["phone_number"] ?? "");
$password = trim($data["password"] ?? "");

//Validate if Fields are empty
if (
    empty($firstName) ||
    empty($lastName) ||
    empty($email) ||
    empty($phoneNumber) ||
    empty($password)
) {
    echo json_encode([
        "success" => false,
        "message" => "Please fill in all fields."
    ]);
    exit();
}

// Validate the Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid email address."
    ]);

    exit();
}

// Check if there is duplicate email
$sql = "SELECT user_id FROM users WHERE email = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $email);

$stmt->execute();

$stmt->store_result();

if ($stmt->num_rows > 0) {

    echo json_encode([
        "success" => false,
        "message" => "Email already exists."
    ]);

    exit();
}

$stmt->close();

// Check if phone number is already used
$sql = "SELECT user_id FROM users WHERE phone_number = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $phoneNumber);

$stmt->execute();

$stmt->store_result();

if ($stmt->num_rows > 0) {

    echo json_encode([
        "success" => false,
        "message" => "Phone number already exists."
    ]);

    exit();
}

$stmt->close();

// Reject Short passwords
if(strlen($password) < 8){

    echo json_encode([
        "success"=>false,
        "message"=>"Password must be at least 8 characters."
    ]);

    exit();

}

// Rejects Invalid Phone number
if(!preg_match('/^09\d{9}$/', $phoneNumber)){

    echo json_encode([
        "success"=>false,
        "message"=>"Invalid Philippine phone number."
    ]);

    exit();

}

//Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert into users
$sql = "INSERT INTO users
(first_name, last_name, email, phone_number, password)
VALUES (?,?,?,?,?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssss",
    $firstName,
    $lastName,
    $email,
    $phoneNumber,
    $hashedPassword
);


// Execute it 
if ($stmt->execute()) {

    $newUserId = $stmt->insert_id;

    echo json_encode([
        "success" => true,
        "message" => "Registration successful.",
        "user_id" => $newUserId,
        "requires_verification" => true
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Registration failed."
    ]);
}

$stmt->close();

$conn->close();

?>
