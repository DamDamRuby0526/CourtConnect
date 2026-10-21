<?php
require_once __DIR__ . "/includes/backend-api/registration_consent_config.php";
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
  <link rel="stylesheet" href="includes/css/styles.css?v=20261021-consent-validation-3">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="includes/js/register.js?v=20261021-consent-validation-3" defer></script>
  <title>CourtConnect Bataan</title>
</head>

<body class="account-auth-page" data-pending-reservation="<?= $hasPendingReservation ? "true" : "false" ?>">

  <?php include 'includes/navigation/header.php'; ?>

  <main class="account-auth-main">
    <form id="registerForm" class="account-auth-form" novalidate>
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
          <input type="text" id="phone_number" name="phone_number" required inputmode="numeric" pattern="09[0-9]{9}" title="Enter an 11-digit Philippine mobile number starting with 09.">
        </div>
        <div class="account-auth-field">
          <label for="password">Password (8+ characters)</label>
          <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="account-auth-field">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </div>
      </div>
      <input type="hidden" id="registrationConsent" value="">
      <input type="hidden" id="privacyNoticeVersion" value="<?= htmlspecialchars(COURTCONNECT_PRIVACY_NOTICE_VERSION, ENT_QUOTES, "UTF-8") ?>">
      <input type="hidden" id="termsVersion" value="<?= htmlspecialchars(COURTCONNECT_TERMS_VERSION, ENT_QUOTES, "UTF-8") ?>">
      <div class="registration-consent-summary">
        <h3>Before you continue</h3>
        <p class="registration-consent-description">Please review how CourtConnect handles your information and the rules for using the service.</p>
        <div class="registration-consent-links">
          <input type="checkbox" id="registrationConsentIndicator" aria-label="Open and agree to the Data Privacy Notice and Terms and Conditions">
          <label for="registrationConsentIndicator">Read the Data Privacy Notice and Terms and Conditions</label>
        </div>
      </div>
      <button type="submit" class="btn" id="registerSubmit" disabled>Next</button>
      <p id="registrationSubmitHint" class="registration-submit-hint" role="status" aria-live="polite"></p>
      <p class="account-auth-helper">Already have an account? <a href="signin.php">Login</a></p>
    </form>
  </main>

  <div class="registration-consent-overlay" id="consentModal" aria-hidden="true">
    <section class="registration-consent-dialog" role="dialog" aria-modal="true" aria-labelledby="consentDialogTitle" aria-describedby="consentScrollHint">
      <header class="registration-consent-header">
        <div>
          <h2 id="consentDialogTitle">Privacy and Terms</h2>
          <p class="registration-consent-subtitle">Data Privacy Notice and Terms and Conditions</p>
          <p class="registration-consent-versions">Privacy <?= htmlspecialchars(COURTCONNECT_PRIVACY_NOTICE_VERSION, ENT_QUOTES, "UTF-8") ?> · Terms <?= htmlspecialchars(COURTCONNECT_TERMS_VERSION, ENT_QUOTES, "UTF-8") ?></p>
        </div>
        <button type="button" id="closeConsentNotice" aria-label="Close notice">×</button>
      </header>
      <div class="registration-consent-content" id="consentNoticeContent" tabindex="0" role="region" aria-label="Data Privacy Notice and Terms and Conditions" aria-describedby="consentScrollHint">
        <section id="privacyNoticePanel" aria-labelledby="privacyNoticeHeading">
          <h3 id="privacyNoticeHeading">Data Privacy Notice</h3>
          <p>CourtConnect Bataan processes personal information to provide customer accounts and court reservation services. We handle information according to the principles of transparency, legitimate purpose, and proportionality under the Philippine Data Privacy Act of 2012 (Republic Act No. 10173). We do not use this consent for unrelated marketing.</p>
          <h4>1. Personal information collected</h4>
          <p>When you register, we collect your first and last name, email address, phone number, and password. Passwords are stored as password hashes. To verify your email, the system creates a six-digit code that is temporarily stored and expires after five minutes; it is removed after successful verification or replaced when another code is issued.</p>
          <p>If you make a reservation, the system processes details about the facility, court, selected schedule, booking status, and booking history. For GCash payments, customers may submit a reference number and an image of payment proof for facility staff to review. The platform also stores session information needed to keep you signed in and process requests.</p>
          <h4>2. Purposes</h4>
          <p>We use this information to create and manage accounts, verify email addresses, protect account access, display facilities, courts, schedules and rates, submit and manage reservations, review payment submissions where supported, send verification and relevant booking or payment emails, maintain booking records, resolve reservation issues, and protect the service from unauthorized access or abuse.</p>
          <h4>3. Sharing and service providers</h4>
          <p>Authorized facility owners and staff can access customer and reservation details for bookings at their facility, including submitted payment proof when relevant. CourtConnect uses Brevo to deliver email. Your email address, name, and the content needed for verification or notification messages are sent to Brevo for delivery. Information is not shared with these recipients for unrelated purposes through this feature.</p>
          <h4>4. Storage and safeguards</h4>
          <p>Account and reservation records are stored in the application database; uploaded payment proof is stored with application files and made available to authorized reviewers. The application uses password hashing, server-side validation, prepared database queries, session-based access controls, and restricted access to certain uploaded files. These are reasonable safeguards, not a guarantee that any electronic system is completely secure.</p>
          <h4>5. Retention and deletion</h4>
          <p>Information should be kept only as long as needed for reservation operations, record keeping, and applicable legal obligations. The current application has no self-service account deletion and does not publish a specific retention schedule. Please contact us to request assistance with your information. The operator must establish and publish detailed retention and deletion procedures before deployment.</p>
          <h4>6. Your privacy rights</h4>
          <p>Subject to applicable law, you may ask to be informed about processing, access or correct your information, object to certain processing, request erasure or blocking where applicable, and seek remedies for violations. We may need to verify your identity and may be unable to fulfill a request where a legal exception applies.</p>
          <p>For privacy questions or requests, contact CourtConnect Bataan at <a href="mailto:courtconnectbataan@gmail.com">courtconnectbataan@gmail.com</a> or +639399159178. The operator should designate a privacy contact and confirm the request-handling process before deployment.</p>
          <h4>7. Reservations and payments</h4>
          <p>Please provide accurate account and reservation information. Payment proof and reference numbers are used for manual review by the relevant facility. CourtConnect does not process these submissions through an integrated payment gateway in this flow; payment is handled according to the facility's instructions.</p>
        </section>
        <div class="registration-consent-section-end" id="privacyNoticeEnd" aria-hidden="true"></div>
        <section class="registration-consent-terms" id="termsPanel" aria-labelledby="termsHeading">
          <h3 id="termsHeading">Terms and Conditions</h3>
          <h4>1. Eligibility and account registration</h4>
          <p>You must be legally able to enter into these terms and provide a working email address and phone number. The application does not currently verify a minimum age; the operator must decide and publish any age-related eligibility rules before deployment. Email verification is required to use customer booking features.</p>
          <h4>2. Accurate information and account security</h4>
          <p>Keep your registration and reservation details accurate and current. You are responsible for protecting your password and for activity performed through your account. Notify CourtConnect if you believe your account has been accessed without authorization.</p>
          <h4>3. Permitted use</h4>
          <p>Use CourtConnect to browse facilities and make legitimate court reservations. Do not submit fraudulent bookings or payment proof, misuse another person's account, disrupt the service, or attempt to access accounts, records, or systems without authorization.</p>
          <h4>4. Facility details and availability</h4>
          <p>Facilities provide their court information, schedules, and rates. Listings and availability may change. A selected time is not a confirmed booking until the booking flow shows that it has been completed; unpaid checkout holds are temporary and may expire.</p>
          <h4>5. Reservations and payments</h4>
          <p>Review the facility, court, time, duration, price, and payment instructions before submitting. Where GCash is offered, upload a genuine receipt and accurate reference number; facility staff manually review submissions. Payment status remains pending until the facility records its decision.</p>
          <h4>6. Cancellation and refunds</h4>
          <p>Cancellation availability is subject to the facility's configured cancellation window, which is shown by the booking flow when available. The application currently falls back to a two-hour cutoff when no facility cancellation setting exists. Refund handling is not automated or guaranteed by CourtConnect; contact the relevant facility about payment or refund arrangements. The operator should publish a complete refund policy before deployment.</p>
          <h4>7. Service availability</h4>
          <p>CourtConnect may be temporarily unavailable for maintenance, updates, or circumstances outside the operator's control. We will work to restore service, but continuous availability is not guaranteed.</p>
          <h4>8. Account restrictions</h4>
          <p>The operator may restrict or suspend access where reasonably necessary to investigate suspected fraud, unauthorized access, or material misuse of the service. Where practicable, the operator should explain the restriction and provide a way to raise a concern. A specific customer appeal process is not currently built into the application.</p>
          <h4>9. Changes and concerns</h4>
          <p>The current versions are shown above. Material updates should be posted with their effective date and users should be notified through a suitable channel. The application does not currently provide automatic notice to existing users about term changes; the operator must establish that process before deployment. For questions, contact <a href="mailto:courtconnectbataan@gmail.com">courtconnectbataan@gmail.com</a> or +639399159178.</p>
        </section>
        <div class="registration-consent-section-end" id="termsEnd" aria-hidden="true"></div>
      </div>
      <div class="registration-consent-confirmation">
        <p class="registration-consent-scroll-hint" id="consentScrollHint" role="status" aria-live="polite">Scroll through both sections to continue.</p>
        <label class="registration-consent-checkbox" for="consentAgreement">
          <input type="checkbox" id="consentAgreement" disabled>
          <span>I have read and understood the Data Privacy Notice and Terms and Conditions, and I agree to the processing of my personal information as described in the Privacy Notice.</span>
        </label>
      </div>
      <div class="registration-consent-actions">
        <button type="button" class="registration-consent-decline" id="declineConsent">Decline and leave</button>
        <button type="button" class="registration-consent-accept" id="acceptConsent" disabled>I agree and continue</button>
      </div>
    </section>
  </div>

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