<?php
header("X-Robots-Tag: noindex, nofollow");
header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store");
require_once __DIR__ . "/includes/backend-api/config.php";

$resetToken = is_string($_GET["token"] ?? null) ? $_GET["token"] : "";
$tokenIsValid = false;
if (preg_match('/^[a-f0-9]{64}$/', $resetToken)) {
    $tokenHash = hash("sha256", $resetToken);
    $tokenStmt = $conn->prepare(
        "SELECT reset_id FROM password_reset_tokens
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    $tokenStmt->bind_param("s", $tokenHash);
    $tokenStmt->execute();
    $tokenIsValid = (bool) $tokenStmt->get_result()->fetch_assoc();
    $tokenStmt->close();
}
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
  <?php if ($tokenIsValid): ?>
    <script src="includes/js/reset_password.js?v=20261021-password-reset-3" defer></script>
  <?php endif; ?>
  <title>Reset Password | CourtConnect Bataan</title>
</head>
<body class="account-auth-page">
  <main class="account-auth-main password-reset-main">
    <?php if ($tokenIsValid): ?>
      <form id="resetPasswordForm" class="account-auth-form password-reset-form" novalidate>
        <h2>Set a New Password</h2>
        <p class="password-reset-intro">Choose a new password for your CourtConnect account. Use at least 8 characters.</p>
        <input id="resetToken" type="hidden" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, "UTF-8") ?>">
        <div class="account-auth-field">
          <label for="newPassword">New Password</label>
          <div class="password-reset-input-wrap">
            <input id="newPassword" type="password" required minlength="8" maxlength="1024" autocomplete="new-password" aria-describedby="newPasswordError">
            <button class="password-reset-visibility" type="button" data-password-target="newPassword" aria-label="Show new password" aria-pressed="false">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
            </button>
          </div>
          <small class="password-reset-field-error" id="newPasswordError" hidden></small>
        </div>
        <div class="account-auth-field">
          <label for="confirmNewPassword">Confirm New Password</label>
          <div class="password-reset-input-wrap">
            <input id="confirmNewPassword" type="password" required minlength="8" maxlength="1024" autocomplete="new-password" aria-describedby="confirmNewPasswordError">
            <button class="password-reset-visibility" type="button" data-password-target="confirmNewPassword" aria-label="Show confirmation password" aria-pressed="false">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
            </button>
          </div>
          <small class="password-reset-field-error" id="confirmNewPasswordError" hidden></small>
        </div>
        <p class="password-reset-feedback" id="resetPasswordFeedback" role="status" aria-live="polite"></p>
        <button type="submit" class="btn" id="resetPasswordSubmit">Update Password</button>
        <p class="account-auth-helper"><a href="signin.php">Back to sign in</a></p>
      </form>
      <section id="resetPasswordSuccess" class="account-auth-form password-reset-success" role="status" hidden>
        <h2>Password Updated</h2>
        <p class="password-reset-intro">Your password has been changed. Sign in with your new password to continue.</p>
        <a class="password-reset-login-button" href="signin.php">Return to Sign In</a>
      </section>
    <?php else: ?>
      <section class="account-auth-form password-reset-form">
        <h2>Reset Link Unavailable</h2>
        <p class="password-reset-intro">This password reset link is invalid, expired, or has already been used. Request a new link to continue.</p>
        <a class="password-reset-login-button" href="forgot_password.php">Request a New Reset Link</a>
        <p class="account-auth-helper"><a href="signin.php">Back to sign in</a></p>
      </section>
    <?php endif; ?>
  </main>
  <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>
