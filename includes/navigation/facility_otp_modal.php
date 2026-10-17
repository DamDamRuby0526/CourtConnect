<div class="modal-container facility-login-otp-overlay" id="facilityOtpOverlay" aria-hidden="true">
    <div class="modal otp-modal facility-login-otp-modal" role="dialog" aria-modal="true" aria-labelledby="facilityOtpTitle">
        <form id="facilityOtpForm" class="otp-form">
            <h2 id="facilityOtpTitle">Verify your account</h2>
            <p class="otp-instructions">The 6-digit code has been sent to your email. It expires in 5 minutes.</p>
            <p class="facility-login-otp-status" id="facilityOtpStatus" role="status" aria-live="polite"></p>
            <div id="facilityOtpInputs" class="inputs" aria-label="Six-digit verification code">
                <?php for ($digit = 1; $digit <= 6; $digit++): ?>
                    <input class="input" type="text" inputmode="numeric" autocomplete="<?= $digit === 1 ? "one-time-code" : "off" ?>" maxlength="1" pattern="[0-9]" aria-label="OTP digit <?= $digit ?>" aria-required="true">
                <?php endfor; ?>
            </div>
            <button type="submit" id="facilityOtpSubmit" class="btn">Submit Code</button>
            <p class="otp-resend-line">Didn't receive the code? <a href="#" id="facilityOtpResend">Resend <span id="facilityOtpResendCountdown" aria-live="polite"></span></a></p>
        </form>
    </div>
</div>
<script src="includes/js/facility_otp.js?v=20261018-2" defer></script>
