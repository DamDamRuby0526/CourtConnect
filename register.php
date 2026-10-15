<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$pendingScheduleIds = $_SESSION["pending_reservation"]["schedule_ids"] ?? [];
$hasPendingReservation = (is_array($pendingScheduleIds) && count($pendingScheduleIds) > 0)
  || filter_var($_SESSION["pending_reservation"]["schedule_id"] ?? null, FILTER_VALIDATE_INT) > 0;
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
  <script src="includes/js/register.js" defer></script>
  <title>CourtConnect Bataan</title>
</head>

<body class="account-auth-page" data-pending-reservation="<?= $hasPendingReservation ? "true" : "false" ?>">

  <?php include 'includes/navigation/header.php'; ?>

  <main class="account-auth-main">
    <form id="registerForm" class="account-auth-form">
      <h2>Sign Up</h2>
      <?php if ($hasPendingReservation): ?>
        <p class="reservation-auth-notice">Your selected court time is saved while you create and verify your customer account.</p>
      <?php endif; ?>
      <div class="account-auth-grid">
        <div class="account-auth-field">
          <label for="first_name">First Name</label>
          <input type="text" id="first_name" name="first_name" required>
        </div>
        <div class="account-auth-field">
          <label for="last_name">Last Name</label>
          <input type="text" id="last_name" name="last_name" required>
        </div>
        <div class="account-auth-field account-auth-field-full">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autocomplete="email">
        </div>
        <div class="account-auth-field account-auth-field-full">
          <label for="confirm_email">Confirm Email</label>
          <input type="email" id="confirm_email" name="confirm_email" required autocomplete="email">
        </div>
        <div class="account-auth-field account-auth-field-full">
          <label for="phone_number">Contact Number (09 format)</label>
          <input type="text" id="phone_number" name="phone_number" required>
        </div>
        <div class="account-auth-field">
          <label for="password">Password (8+ characters)</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="account-auth-field">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
      </div>
      <button type="submit" class="btn">Next</button>
      <p class="account-auth-helper">Already have an account? <a href="signin.php">Login</a></p>
    </form>
  </main>



  <div class="modal-container" id="modal_container">
    <div class="modal otp-modal">
      <form id="otpForm" class="otp-form">
        <h2>Verify your account</h2>
        <p class="otp-instructions">The 6-digit code has been sent to your email. It expires in 5 minutes.</p>
        <div id="inputs" class="inputs">
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 1" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 2" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 3" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 4" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 5" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 6" required>
        </div>

        <button type="submit" id="submitOtpButton" class="btn">Submit Code</button>
        <p class="otp-resend-line">Didn't receive the code? <a href="#" id="resendOtpLink">Resend <span id="resendOtpCountdown" aria-live="polite"></span></a></p>
      </form>
    </div>
  </div>

  <?php include 'includes/navigation/footer.php'; ?>
</body>

</html>