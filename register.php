<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="includes/css/styles.css" />
  <script src="includes/js/register.js" defer></script>
  <title>CourtConnect Bataan</title>
</head>

<body>

  <?php include 'includes/navigation/header.php'; ?>

  <form id="registerForm">
    <h2>Sign Up</h2>
    <label for="first_name">First Name</label><br>
    <input type="text" id="first_name" name="first_name" required><br>

    <label for="last_name">Last Name</label><br>
    <input type="text" id="last_name" name="last_name" required><br>

    <label for="email">Email</label><br>
    <input type="email" id="email" name="email" required autocomplete="email"><br>

    <label for="confirm_email">Confirm Email</label><br>
    <input type="email" id="confirm_email" name="confirm_email" required autocomplete="email"><br>

    <label for="phone_number">Contact Number (09 format)</label><br>
    <input type="text" id="phone_number" name="phone_number" required><br>

    <label for="password">Password (8 characters minimum)</label><br>
    <input type="password" id="password" name="password" required><br>

    <label for="confirm_password">Confirm Password</label><br>
    <input type="password" id="confirm_password" name="confirm_password" required><br>

    <button type="submit" class="btn">Next</button>
    <p>Already have an account? <a href="signin.php"> Login</a></p>
  </form>



  <div class="modal-container" id="modal_container">
    <div class="modal">
      <form id="otpForm" class="otp-form">
        <h2>Verify your account</h2>
        <p class="otp-instructions">The 6-digit code has been sent to your email.</p>
        <p class="otp-instructions">This code will expire in 5 minutes.</p>
        <div id="inputs" class="inputs">
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 1" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 2" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 3" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 4" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 5" required>
          <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" aria-label="OTP digit 6" required>
        </div>

        <button type="submit" id="submitOtpButton" class="btn">Submit Code</button>
        <p>Didn't receive the code? <a href="#" id="resendOtpLink">Request to resend OTP code</a></p>
      </form>
    </div>
  </div>



</body>

<?php include 'includes/navigation/footer.php'; ?>


</html>