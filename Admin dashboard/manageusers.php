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
    <div class="modal-container" id="modal_container">
        <div class="modal">
            <form id="registerForm">
                <p>First Name</p>
                <input type="text" id="first_name" name="first_name" required><br>
                <p>Last Name</p>
                <input type="text" id="last_name" name="last_name" required><br>
                <p>Email</p>
                <input type="email" id="email" name="email" required><br>
                <p>Contact Number (09 format)</p>
                <input type="text" id="phone_number" name="phone_number" required><br>
                <button type="button" class="save-btn">Save</button>
                <button type="button" class="cancel-btn">Cancel</button>
            </form>

        </div>
    </div>


</body>

<?php include '../includes/navigation/admin_footer.php'; ?>



</html>