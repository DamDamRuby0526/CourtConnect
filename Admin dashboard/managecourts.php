<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/css/styles.css" />
    <script src="../includes/js/admin_managecourts.js" defer></script>
    <title>CourtConnect Bataan</title>
</head>

<body>

    <?php include '../includes/navigation/admin_header.php'; ?>



    <!-- fetch id -->
    <h1 id="facility-name">Facility Name</h1>
    <h2>Manage Courts</h2>

    <button type="button" id="add-btn">Add New Courts</button>
    <div class="grid-container" id="grid-container">



    </div>

    <!-- modal open user edit form -->
    <div class="modal-container" id="modal_container">
        <div class="modal">
            <form id="courtForm">

            <p>Court Number</p>
                <input type="text" id="court_no" name="court_no" required><br>

                <p>Court Rate</p>
                <input type="number" id="court_rate" name="court_rate"
                    step="0.01" min="0" required><br>

                <p>Description</p>
                <textarea id="description" name="description" required></textarea><br>

                <p>Slot Duration</p>
                <input type="number" id="slot_duration" name="slot_duration"
                    min="1" required><br>

                <p>Court Status</p>
                <select id="court_status" name="court_status" required>
                    <option value="">Select Status</option>
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                    <option value="Maintenance">Maintenance</option>
                </select><br>

                <button type="button" class="save-btn">Save</button>
                <button type="button" class="cancel-btn">Cancel</button>

            </form>

        </div>
    </div>


</body>

<?php include '../includes/navigation/admin_footer.php'; ?>



</html>