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
    <title>CourtConnect Bataan</title>
</head>

<body>


    <?php include 'includes/navigation/header.php'; ?>

    <div>
        <h2>About Us</h2>
        <p>The project aims to develop a web and mobile application reservation and management system for badminton and pickleball facilities in Bataan. 
            The system will allow the users to browse courts, check real-time availability, reserve courts, and manage reservations while providing an admin dashboard 
            for court management, reservation verification, and reporting for the court owners.
        </p>

        <h3>Mission</h3>
        <p>To provide a reliable, secure, and user-friendly digital court reservation system that enhances the badminton & pickleball booking experience in Bataan</p>
        
        <h3>Vision</h3>
        <p>To be a trusted and convenient reservation platform constantly improving from user’s  feedback </p>
    </div>


</body>

<?php include 'includes/navigation/footer.php'; ?>



</html>