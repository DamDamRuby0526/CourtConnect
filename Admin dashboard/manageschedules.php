<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule</title>

    <link rel="stylesheet" href="../includes/css/styles.css">
    <script src="../includes/js/admin_manageschedules.js" defer></script>
</head>

<body>

    <?php include '../includes/navigation/admin_header.php'; ?>

    
    <h1>Manage Court Schedule</h1>
    <button type="button" id="add-btn"> Add New Schedule </button>

  
    <span id="court-id">Court ID:</span>

    <div class="grid-container" id="grid-container">   
    </div>

    <!-- MODAL -->
    <div class="modal-container" id="modal_container">
        <div class="modal">
            <form id="scheduleForm">

                <h2>Schedule Details</h2>

                <p>Date</p>
                <input type="date" id="court_date" name="court_date" required>

                <p>Time</p>
                <input type="time" id="court_time" name="court_time" required>

                <p>Status</p>
                <select id="schedule_status" name="schedule_status">
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                    <option value="Booked">Booked</option>
                </select><br>

                <button type="button" class="save-btn"> Save </button>

                <button type="button" class="cancel-btn"> Cancel </button>

            </form>

        </div>

    </div>

    <?php include '../includes/navigation/admin_footer.php'; ?>

</body>

</html>