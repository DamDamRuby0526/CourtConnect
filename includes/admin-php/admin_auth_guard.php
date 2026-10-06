<?php
require_once __DIR__ . "/../backend-api/config.php";

header("Content-Type: application/json");

// ATTACH TO admin-php php file HEADERS TO LOCK ACCESS TO ADMINS ONLY
// not yet implemented lulE

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Unauthorized. Please log in as an admin."
    ]);

    exit();
}
