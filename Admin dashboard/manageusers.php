<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/css/styles.css" />
    <script src="../includes/js/admin_manageusers.js"></script>
    <title>CourtConnect Bataan</title>
</head>

<body>

    <?php include '../includes/navigation/admin_header.php'; ?>

    <!-- table display -->
     
    <div class="table-container">
        <h2>Users</h2>
        <p>(CourtConnect view)</p>
        <table>
            <thead>
                <tr>
                    <th>id</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                    <th>Contact No.</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="usersTable">
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- modal open user edit form -->
    <div class="modal-container compact-modal-overlay" id="modal_container" aria-hidden="true">
        <div class="modal modal--compact" role="dialog" aria-modal="true" aria-labelledby="userDialogTitle">
            <form id="registerForm">
                <div class="compact-modal-header">
                    <h2 id="userDialogTitle">User details</h2>
                    <button type="button" class="owner-close-button cancel-btn" aria-label="Close">&times;</button>
                </div>
                <label for="first_name">First name
                    <input type="text" id="first_name" name="first_name" required>
                </label>
                <label for="last_name">Last name
                    <input type="text" id="last_name" name="last_name" required>
                </label>
                <label for="email">Email
                    <input type="email" id="email" name="email" required>
                </label>
                <label for="phone_number">Contact number (09 format)
                    <input type="text" id="phone_number" name="phone_number" required>
                </label>
                <div class="owner-modal-actions compact-modal-actions">
                    <button type="button" class="owner-secondary-button cancel-btn">Cancel</button>
                    <button type="button" class="owner-primary-button save-btn">Save</button>
                </div>
            </form>

        </div>
    </div>


</body>

<?php include '../includes/navigation/admin_footer.php'; ?>



</html>