<?php
header("X-Robots-Tag: noindex, nofollow");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="no-referrer">
  <link rel="stylesheet" href="includes/css/styles.css?v=20261021-password-reset-3">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="includes/js/forgot_password.js?v=20261021-password-reset-3" defer></script>
  <title>Forgot Password | CourtConnect Bataan</title>
</head>
<body class="account-auth-page">
  <?php include 'includes/navigation/header.php'; ?>
  <main class="account-auth-main password-reset-main">
    <form id="forgotPasswordForm" class="account-auth-form password-reset-form" novalidate>
      <h2>Forgot Password?</h2>
      <p class="password-reset-intro">Enter the email address associated with your account. If it matches an account, we’ll email you a secure reset link.</p>
      <div class="account-auth-field">
        <label for="resetEmail">Email</label>
        <input id="resetEmail" name="email" type="email" required maxlength="150" autocomplete="email" aria-describedby="resetEmailError">
        <small class="password-reset-field-error" id="resetEmailError" hidden></small>
      </div>
      <p class="password-reset-feedback" id="forgotPasswordFeedback" role="status" aria-live="polite"></p>
      <button type="submit" class="btn" id="forgotPasswordSubmit">Send Reset Link</button>
      <p class="account-auth-helper"><a href="signin.php">Back to sign in</a></p>
    </form>
  </main>
  <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>
