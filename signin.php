<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$returningForReservation = ($_GET["return_to"] ?? "") === "reservation_resume.php";
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="includes/css/styles.css" />
  <title>CourtConnect Bataan</title>
  <script src="includes/js/login.js?v=20261021-1" defer></script>
</head>

<body>

  <?php include 'includes/navigation/header.php'; ?>

  <div>
    <div>

      <form id="loginForm">
        <h2>Sign In</h2>
        <?php if ($returningForReservation): ?>
          <p class="reservation-auth-notice">You’re one step away from your next match! Log in to continue with your selected court reservation.</p>
        <?php endif; ?>

        <label for="email">Email</label><br>
        <input type="email" name="email" required autocomplete="email"><br>

        <label for="password">Password</label><br>
        <input type="password" name="password" required autocomplete="password"><br>

        <button type="submit" class="btn">Login</button>

        <p>Don't have an account? <a href="register.php">Create one</a></p>
      </form>
    </div>


  </div>

  <?php include 'includes/navigation/facility_otp_modal.php'; ?>



</body>

<?php include 'includes/navigation/footer.php'; ?>


</html>