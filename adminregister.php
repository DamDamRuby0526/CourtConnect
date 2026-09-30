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

  <script src="includes/js/admin_register.js?v=20260929-5" defer></script>

  <title>CourtConnect Bataan</title>
</head>

<body>

  <?php include 'includes/navigation/header.php'; ?>

  <div class="auth-shell">

    <div class="auth-visual">
      <p class="auth-visual-eyebrow">FOR FACILITY OWNERS</p>

      <h2>List your courts.<br>Start earning bookings.</h2>

      <p>
        Join facility owners across Bataan managing reservations,
        rates, and bookings in one place.
      </p>
    </div>


    <div class="auth-panel">

      <form id="registerForm" class="auth-card">

        <h2>Create Facility Account</h2>

        <p class="auth-subtitle">
          Register your account to list and manage your facility on CourtConnect.
        </p>


        <!-- PROGRESS BAR -->
        <div class="registration-progress">

          <div class="progress-step active" id="progressStep1">
            <div class="progress-circle">1</div>
            <span>Account</span>
          </div>

          <div class="progress-line" id="progressLine"></div>

          <div class="progress-step" id="progressStep2">
            <div class="progress-circle">2</div>
            <span>Verification</span>
          </div>

        </div>


        <!-- ========================= -->
        <!-- STEP 1: ACCOUNT INFORMATION -->
        <!-- ========================= -->

        <div id="accountStep">

          <div class="field-row">

            <div class="field-group">
              <label for="first_name">First Name</label>

              <input
                type="text"
                id="first_name"
                name="first_name"
                required>
            </div>


            <div class="field-group">
              <label for="last_name">Last Name</label>

              <input
                type="text"
                id="last_name"
                name="last_name"
                required>
            </div>

          </div>


          <div class="field-group">

            <label for="email">Email</label>

            <input
              type="email"
              id="email"
              name="email"
              required
              autocomplete="email">

          </div>


          <div class="field-group">

            <label for="confirm_email">Confirm Email</label>

            <input
              type="email"
              id="confirm_email"
              name="confirm_email"
              required
              autocomplete="email">

          </div>


          <div class="field-group">

            <label for="phone_number">
              Contact Number (09 format)
            </label>

            <input
              type="text"
              id="phone_number"
              name="phone_number"
              required
              maxlength="11"
              placeholder="09XXXXXXXXX">

          </div>


          <div class="field-group">

            <label for="password">
              Password (8 characters minimum)
            </label>

            <input
              type="password"
              id="password"
              name="password"
              minlength="8"
              required>

          </div>


          <div class="field-group">

            <label for="confirm_password">
              Confirm Password
            </label>

            <input
              type="password"
              id="confirm_password"
              name="confirm_password"
              minlength="8"
              required>

          </div>


          <button
            type="button"
            id="continueToVerification"
            class="auth-submit">

            Continue to Verification

          </button>

        </div>



        <!-- STEP 2: VERIFICATION -->


        <div id="verificationStep" style="display: none;">

          <div class="auth-section-label">
            Facility Information
          </div>

          <p class="auth-section-hint">
            Provide information about the facility you want to register
            on CourtConnect.
          </p>


          <div class="field-group">

            <label for="facility_name">
              Facility Name
            </label>

            <input
              type="text"
              id="facility_name"
              name="facility_name"
              placeholder="e.g. Bravo Pickleball"
              required>

          </div>


          <div class="field-group">

            <label for="facility_address">
              Facility Address
            </label>

            <input
              type="text"
              id="facility_address"
              name="facility_address"
              placeholder="Complete facility address"
              required>

          </div>


          <div class="field-group">

            <label for="municipality">
              Municipality / City
            </label>

            <input
              type="text"
              id="municipality"
              name="municipality"
              placeholder="e.g. Balanga City"
              required>

          </div>


          <div class="field-group">

            <label for="facility_type">
              Facility Type
            </label>

            <select
              id="facility_type"
              name="facility_type"
              required>

              <option value="">
                Select facility type
              </option>

              <option value="badminton">
                Badminton
              </option>

              <option value="pickleball">
                Pickleball
              </option>

              <option value="both">
                Badminton and Pickleball
              </option>

            </select>

          </div>


          <div class="auth-section-label">
            Verification Documents
          </div>

          <p class="auth-section-hint">
            Upload documents that can verify your identity and
            your authority to operate the facility.
          </p>


          <div class="field-group">

            <label for="valid_id">
              Valid Government ID
            </label>

            <input
              type="file"
              id="valid_id"
              name="valid_id"
              accept="image/*,.pdf"
              required>

          </div>


          <div class="field-group">

            <label for="business_registration">
              DTI / SEC Registration
            </label>

            <input
              type="file"
              id="business_registration"
              name="business_registration"
              accept="image/*,.pdf"
              required>

          </div>


          <div class="field-group">

            <label for="business_permit">
              Business / Mayor's Permit
            </label>

            <input
              type="file"
              id="business_permit"
              name="business_permit"
              accept="image/*,.pdf"
              required>

          </div>


          <div class="field-group">

            <label for="proof_of_occupancy">
              Proof of Ownership / Lease
            </label>

            <input
              type="file"
              id="proof_of_occupancy"
              name="proof_of_occupancy"
              accept="image/*,.pdf"
              required>

          </div>


          <div class="field-group">

            <label for="facility_photos">
              Facility / Court Photos
            </label>

            <input
              type="file"
              id="facility_photos"
              name="facility_photos[]"
              accept="image/*"
              multiple
              required>

          </div>


          <div class="verification-notice">

            <strong>Verification Notice</strong>

            <p>
              Your facility will not be immediately listed on CourtConnect.
              Our administrators will review your submitted information
              and documents before approving your facility.
            </p>

          </div>


          <div class="verification-buttons">

            <button
              type="button"
              id="backToAccount"
              class="auth-back">

              Back

            </button>


            <button
              type="submit"
              class="auth-submit">

              Submit for Verification

            </button>

          </div>

        </div>


        <p class="auth-footer-note">
          Already have an account?
          <a href="adminsignin.php">
            Login as Facility
          </a>
        </p>

      </form>

      <section id="pendingReview" class="auth-card" hidden>
        <h2 id="pendingReviewTitle">Application Under Review</h2>
        <p id="pendingReviewMessage" class="auth-subtitle" role="status" aria-live="polite">
          Your documents are waiting for review.
        </p>
        <button type="button" id="backToHome" class="auth-submit">
          Back to home page
        </button>
      </section>

    </div>

  </div>


  <!-- OTP MODAL -->

  <div class="modal-container" id="modal_container">

    <div class="modal">

      <form id="otpForm" class="otp-form">

        <h2>Verify your account</h2>

        <p class="otp-instructions">
          The 6-digit code has been sent to your email.
        </p>

        <p class="otp-instructions">
          This code will expire in 5 minutes.
        </p>


        <div id="inputs" class="inputs">

          <input class="input"
            type="text"
            name="otp"
            inputmode="numeric"
            maxlength="1"
            aria-label="OTP digit 1"
            required>

          <input class="input"
            type="text"
            name="otp"
            inputmode="numeric"
            maxlength="1"
            aria-label="OTP digit 2"
            required>

          <input class="input"
            type="text"
            name="otp"
            inputmode="numeric"
            maxlength="1"
            aria-label="OTP digit 3"
            required>

          <input class="input"
            type="text"
            name="otp"
            inputmode="numeric"
            maxlength="1"
            aria-label="OTP digit 4"
            required>

          <input class="input"
            type="text"
            name="otp"
            inputmode="numeric"
            maxlength="1"
            aria-label="OTP digit 5"
            required>

          <input class="input"
            type="text"
            name="otp"
            inputmode="numeric"
            maxlength="1"
            aria-label="OTP digit 6"
            required>

        </div>


        <button
          type="submit"
          id="submitOtpButton"
          class="btn">

          Submit Code

        </button>


        <p>
          Didn't receive the code?
          <a href="#" id="resendOtpLink">
            Request to resend OTP code
          </a>
        </p>

      </form>

    </div>

  </div>


  <?php include 'includes/navigation/footer.php'; ?>

</body>

</html>