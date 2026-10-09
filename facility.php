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
    <script src="includes/js/facilities.js"></script>
    <title>CourtConnect Bataan</title>
</head>

<body>
<?php include 'includes/navigation/header.php'; ?>

<h2 class="page-title">Available Facilities</h2>
    <div class="grid-container" id="facilities-container">
        <!-- facility cards injected by facilities.js -->
    </div>
    

</body>
<?php include 'includes/navigation/footer.php'; ?>

</html>