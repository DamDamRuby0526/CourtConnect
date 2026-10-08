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
    <script src="includes/js/mybookings.js" defer></script>
    <title>CourtConnect Bataan</title>
</head>

<body>


    <?php include 'includes/navigation/header.php'; ?>

   <div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Facility</th>
                <th>Court</th>
                <th>Date</th>
                <th>Time</th>
                <th>Booking status</th>
                <th>Amount</th>
                <th>Payment status</th>
                <th>GCash receipt</th>
            </tr>
        </thead>
        <tbody id="bookingsTable">
        </tbody>
    </table>
</div>

</body>

<?php include 'includes/navigation/footer.php'; ?>


</html>