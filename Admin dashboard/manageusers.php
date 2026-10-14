<?php
require_once __DIR__ . "/../includes/backend-api/config.php";
require_once __DIR__ . "/../includes/admin-php/courtconnect_auth_guard.php";

$courtconnectDashboardLayout = true;
$courtconnectNavPage = "users";
$_SESSION["csrf_token"] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION["csrf_token"];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../includes/js/admin_manageusers.js" defer></script>
    <title>Users | CourtConnect</title>
</head>
<body class="owner-ui">
    <div class="owner-layout">
        <?php include "../includes/navigation/admin_header.php"; ?>
        <main class="owner-content staff-users-content" data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>">
            <header class="owner-page-head">
                <div>
                    <p class="owner-breadcrumb">COURTCONNECT STAFF <span>/</span> USERS</p>
                    <h1>Users</h1>
                    <p>Search and update account contact details.</p>
                </div>
            </header>

            <section class="staff-users-section" aria-labelledby="usersHeading">
                <div class="owner-section-heading staff-users-heading">
                    <div>
                        <h2 id="usersHeading">User directory</h2>
                        <p>Review account details and keep contact information up to date.</p>
                    </div>
                    <span class="owner-court-count" id="userCount" aria-live="polite">Loading users...</span>
                </div>
                <div class="staff-users-tools">
                    <label for="userSearch">Search users</label>
                    <input type="search" id="userSearch" placeholder="Search by name, email, or phone" autocomplete="off">
                </div>
                <p id="usersStatus" class="staff-users-status" role="status" aria-live="polite">Loading users...</p>
                <div class="staff-users-table-wrap">
                    <table class="staff-users-table">
                        <thead>
                            <tr>
                                <th scope="col">User</th>
                                <th scope="col">Email</th>
                                <th scope="col">Contact number</th>
                                <th scope="col">User ID</th>
                                <th scope="col"><span class="compact-visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody id="usersTable"></tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-container compact-modal-overlay" id="modal_container" aria-hidden="true">
        <div class="modal modal--compact" role="dialog" aria-modal="true" aria-labelledby="userDialogTitle" tabindex="-1">
            <form id="registerForm">
                <div class="compact-modal-header">
                    <h2 id="userDialogTitle">Edit user details</h2>
                    <button type="button" class="owner-close-button cancel-btn" aria-label="Close">&times;</button>
                </div>
                <label for="first_name">First name
                    <input type="text" id="first_name" name="first_name" maxlength="100" autocomplete="given-name" required>
                </label>
                <label for="last_name">Last name
                    <input type="text" id="last_name" name="last_name" maxlength="100" autocomplete="family-name" required>
                </label>
                <label for="email">Email
                    <input type="email" id="email" name="email" maxlength="150" autocomplete="email" required>
                </label>
                <label for="phone_number">Contact number
                    <input type="tel" id="phone_number" name="phone_number" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" autocomplete="tel" required>
                </label>
                <p id="userFormStatus" class="staff-users-status" role="status" aria-live="polite"></p>
                <div class="owner-modal-actions compact-modal-actions">
                    <button type="button" class="owner-secondary-button cancel-btn">Cancel</button>
                    <button type="submit" class="owner-primary-button save-btn">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
