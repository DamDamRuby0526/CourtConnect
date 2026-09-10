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

    <section class="hero">
    <h1>Book Your Court in Bataan</h1>
    <p>Find and reserve badminton and pickleball courts near you in just a few taps.</p>
    <a href="facility.php">Browse Courts</a>
    </section>

        <section class="login-options">
        <h2>Get Started</h2>
        <p>Choose how you'd like to sign in.</p>
        <div class="login-options-buttons">
            <button type="button" class="btn login-option-btn login-option-user">
                  <a href="signin.php">Login as User</a></button>
            <button type="button" class="btn login-option-btn login-option-admin">Login as Facility Admin</button>
        </div>

        <h2>No account?</h2> <p>Register Now!</p>
         <div class="login-options-buttons">
            <button type="button" class="btn login-option-btn login-option-user"> 
                <a href="register.php">Register as User</a></button>

            <button type="button" class="btn login-option-btn login-option-admin">Register as Facility Admin</button>
        </div>
        </section>
    
    <section class="facilities-preview">
        <h2>Available Facilities</h2>
        <p>Browse badminton and pickleball facilities across Bataan.</p>
        <div class="facilities-grid">
            <!-- placeholder facility cards -->
            <div class="facility-card facility-card-placeholder">
                <p>Facility name</p>
            </div>
            <div class="facility-card facility-card-placeholder">
                <p>Facility name</p>
            </div>
            <div class="facility-card facility-card-placeholder">
                <p>Facility name</p>
            </div>
        </div>
    </section>

</body>

<?php include 'includes/navigation/footer.php'; ?>


</html>