<?php
session_start();
$pendingReservation = $_SESSION["pending_reservation"] ?? null;
$_SESSION = [];
if (
    is_array($pendingReservation)
    && (
        (is_array($pendingReservation["schedule_ids"] ?? null) && $pendingReservation["schedule_ids"])
        || filter_var($pendingReservation["schedule_id"] ?? null, FILTER_VALIDATE_INT) > 0
    )
) {
    $_SESSION["pending_reservation"] = $pendingReservation;
}
$hasPendingReservation = isset($_SESSION["pending_reservation"]);
session_regenerate_id(true);
header("Location: " . ($hasPendingReservation ? "reservation_resume.php" : "index.php"));
exit();