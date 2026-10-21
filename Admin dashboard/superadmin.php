<?php
require_once __DIR__ . "/../includes/backend-api/config.php";
$staffRoleGuardApi = false;
require_once __DIR__ . "/../includes/admin-php/superadmin_auth_guard.php";

$courtconnectDashboardLayout = true;
$courtconnectNavPage = "dashboard";
$pendingResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM admins a
     INNER JOIN users u ON u.user_id = a.user_id
     WHERE a.status = 'Pending' AND u.role_id = 3"
);
$courtconnectPendingCount = (int) ($pendingResult ? $pendingResult->fetch_assoc()["total"] : 0);
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <title>Superadmin dashboard | CourtConnect</title>
</head>
<body class="owner-ui">
    <div class="owner-layout">
        <?php include '../includes/navigation/admin_header.php'; ?>
        <main class="owner-content superadmin-dashboard">
            <header class="owner-page-head">
                <div>
                    <p class="owner-breadcrumb">SYSTEM ADMINISTRATION</p>
                    <h1>Superadmin dashboard</h1>
                    <p>Manage CourtConnect’s system-wide users and facilities.</p>
                </div>
            </header>
            <section class="superadmin-dashboard-grid" aria-label="System administration">
                <a class="superadmin-dashboard-card" href="manageusers.php">
                    <span aria-hidden="true">&#9786;</span>
                    <strong>Manage users</strong>
                    <small>View and manage CourtConnect user accounts.</small>
                </a>
                <a class="superadmin-dashboard-card" href="managefacilities.php">
                    <span aria-hidden="true">&#9881;</span>
                    <strong>Manage facilities</strong>
                    <small>View and manage facility listings.</small>
                </a>
                <a class="superadmin-dashboard-card" href="reviewregistrations.php">
                    <span aria-hidden="true">&#10003;</span>
                    <strong>Facility applications</strong>
                    <small>Review applications, verify documents, and approve or reject facility owners.</small>
                </a>
            </section>
        </main>
    </div>
</body>
</html>
