<div class="facility-otp-overlay" id="facilityOtpOverlay" aria-hidden="true">
    <section class="facility-otp-modal" role="dialog" aria-modal="true" aria-labelledby="facilityOtpTitle">
        <button class="facility-otp-close" id="facilityOtpClose" type="button" aria-label="Close verification">×</button>
        <p class="facility-otp-eyebrow">EMAIL VERIFICATION</p>
        <h2 id="facilityOtpTitle">Verify your email</h2>
        <p class="facility-otp-copy">Enter the six-digit code sent to your email to continue to your facility dashboard.</p>
        <p class="facility-otp-status" id="facilityOtpStatus" role="status" aria-live="polite"></p>
        <form id="facilityOtpForm">
            <label for="facilityOtpCode">Verification code</label>
            <input id="facilityOtpCode" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
            <div class="facility-otp-actions">
                <button class="facility-otp-resend" id="facilityOtpResend" type="button">Resend code</button>
                <button class="facility-otp-submit" id="facilityOtpSubmit" type="submit">Verify and continue</button>
            </div>
        </form>
    </section>
</div>
<script src="includes/js/facility_otp.js?v=20261021-1" defer></script>
