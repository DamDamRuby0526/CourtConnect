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
  <link rel="stylesheet" href="includes/css/styles.css?v=20261016-auth-3">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <title>CourtConnect Bataan</title>
  <script src="includes/js/login.js?v=20261016-2" defer></script>
</head>

<body class="account-auth-page">

  <?php include 'includes/navigation/header.php'; ?>

  <main class="account-auth-main">
    <form id="loginForm" class="account-auth-form account-login-form">
      <h2>Sign In</h2>
      <?php if ($returningForReservation): ?>
        <p class="reservation-auth-notice">You’re one step away from your next match! Log in to continue with your selected court reservation.</p>
      <?php endif; ?>

      <div class="account-auth-field">
        <label for="email">Email</label>
        <input type="email" name="email" required autocomplete="email">
      </div>

      <div class="account-auth-field">
        <label for="password">Password</label>
        <input type="password" name="password" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn">Login</button>
      <p class="account-auth-helper">Don't have an account? <a href="register.php">Create one</a></p>
    </form>
  </main>

  <?php include 'includes/navigation/facility_otp_modal.php'; ?>

  <?php include 'includes/navigation/footer.php'; ?>
</body>

</html>