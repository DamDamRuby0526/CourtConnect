<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/css/styles.css" />
    <script src="includes/js/courts.js"></script>
    <title>CourtConnect Bataan</title>
</head>

<body>


    <?php include 'includes/navigation/header.php'; ?>

     <!-- fetch id -->
    <h1 id="facility-name">Facility Name</h1>
    <h2>Manage Courts</h2>
    <div class="grid-container" id="grid-container">
    </div>

    
    <!-- modal open user edit form -->
    <div class="modal-container" id="modal_container">
        <div class="modal">
            <form id="bookForm">
                <button type="button" class="save-btn">Save</button>
                <button type="button" class="cancel-btn">Cancel</button>
            </form>
        </div>
    </div>


</body>

<?php include 'includes/navigation/footer.php'; ?>

</html>