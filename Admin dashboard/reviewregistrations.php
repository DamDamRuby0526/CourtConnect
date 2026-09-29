<?php
require_once __DIR__ . "/../includes/backend-api/config.php";

$userId = filter_var($_SESSION["user_id"] ?? null, FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    header("Location: ../signin.php");
    exit();
}

$stmt = $conn->prepare("SELECT role_id, is_verified FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user || (int) $user["role_id"] !== 4 || (int) $user["is_verified"] !== 1) {
    header("Location: ../index.php");
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/css/styles.css">
    <script src="../includes/js/registration_reviews.js?v=20260929-1" defer></script>
    <title>Facility Applications | CourtConnect</title>
</head>
<body>
    <?php include '../includes/navigation/admin_header.php'; ?>
    <main class="registration-review-page">
        <h1>Facility Applications</h1>
        <p>Review submitted documents before approving a facility account.</p>
        <p id="reviewStatus" role="status" aria-live="polite"></p>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Facility</th>
                        <th>Documents</th>
                        <th>Decision</th>
                    </tr>
                </thead>
                <tbody id="pendingRegistrations"></tbody>
            </table>
        </div>
    </main>
</body>
</html>