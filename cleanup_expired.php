<?php
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}

require_once __DIR__ . "/includes/backend-api/config.php";
$result = expireUnpaidBookings($conn);
$conn->close();

printf(
    "Expired %d unpaid booking(s); released %d Held slot(s).%s",
    $result["expired"],
    $result["released"],
    PHP_EOL
);
