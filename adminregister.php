<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$ownerOnboardingHeader = true;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/css/styles.css">
    <script src="includes/js/file_upload_zone.js?v=20261016-1" defer></script>
    <script src="includes/js/admin_register.js?v=20261016-1" defer></script>
    <title>Facility owner registration | CourtConnect</title>
</head>
<body class="owner-onboarding-page">
    <?php include 'includes/navigation/header.php'; ?>
    <main class="auth-shell owner-onboarding-layout">
        <section class="auth-panel owner-onboarding-form-panel">
            <form id="registerForm" class="auth-card owner-onboarding-card" enctype="multipart/form-data">
                <h1 id="registrationTitle">Create your facility account</h1>
                <p class="auth-subtitle" id="registrationSubtitle">Start with your account details. You'll add your facility and documents next.</p>
                <ol class="registration-progress owner-registration-progress" aria-label="Registration progress">
                    <li class="progress-step active" id="progressStep1" aria-current="step"><span class="progress-circle">1</span><span>Account</span></li>
                    <span class="progress-line" id="progressLine"></span>
                    <li class="progress-step" id="progressStep2"><span class="progress-circle">2</span><span>Facility &amp; documents</span></li>
                    <span class="progress-line" id="progressLine2"></span>
                    <li class="progress-step" id="progressStep3"><span class="progress-circle">3</span><span>Review</span></li>
                </ol>

                <div id="accountStep" class="owner-registration-step">
                    <div class="field-row">
                        <div class="field-group">
                            <label for="first_name">First name</label>
                            <input type="text" id="first_name" name="first_name" autocomplete="given-name" required aria-describedby="first_name_error">
                            <small class="owner-field-error" id="first_name_error" hidden></small>
                        </div>
                        <div class="field-group">
                            <label for="last_name">Last name</label>
                            <input type="text" id="last_name" name="last_name" autocomplete="family-name" required aria-describedby="last_name_error">
                            <small class="owner-field-error" id="last_name_error" hidden></small>
                        </div>
                    </div>
                    <div class="field-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" autocomplete="email" required aria-describedby="email_error">
                        <small class="owner-field-error" id="email_error" hidden></small>
                    </div>
                    <div class="field-group">
                        <label for="confirm_email">Confirm email</label>
                        <input type="email" id="confirm_email" name="confirm_email" autocomplete="email" required aria-describedby="confirm_email_error">
                        <small class="owner-field-error" id="confirm_email_error" hidden></small>
                    </div>
                    <div class="field-group">
                        <label for="phone_number">Contact number</label>
                        <input type="tel" id="phone_number" name="phone_number" autocomplete="tel" maxlength="11" placeholder="09XXXXXXXXX" required aria-describedby="phone_number_error">
                        <small class="owner-field-error" id="phone_number_error" hidden></small>
                    </div>
                    <div class="field-group owner-password-group">
                        <label for="password">Password</label>
                        <div class="owner-password-input">
                            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required aria-describedby="password_hint password_error">
                            <button type="button" class="owner-password-toggle" aria-label="Show password" data-password-target="password"><span aria-hidden="true">◉</span></button>
                        </div>
                        <div class="owner-password-strength" aria-hidden="true"><span id="passwordStrengthBar"></span></div>
                        <small id="password_hint" class="owner-field-hint">At least 8 characters</small>
                        <small class="owner-field-error" id="password_error" hidden></small>
                    </div>
                    <div class="field-group owner-password-group">
                        <label for="confirm_password">Confirm password</label>
                        <div class="owner-password-input">
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required aria-describedby="confirm_password_error">
                            <button type="button" class="owner-password-toggle" aria-label="Show password" data-password-target="confirm_password"><span aria-hidden="true">◉</span></button>
                        </div>
                        <small class="owner-field-error" id="confirm_password_error" hidden></small>
                    </div>
                    <button type="button" id="continueToVerification" class="auth-submit">Continue</button>
                </div>

                <div id="verificationStep" class="owner-registration-step" hidden>
                    <aside class="owner-document-prep" role="note">
                        Please prepare your PDF documents (Government ID, DTI/SEC, Business Permit, Proof of Ownership) before continuing.
                    </aside>
                    <section class="owner-form-section">
                        <h2>Facility information</h2>
                        <p>Tell us where players can find and book your facility.</p>
                        <div class="field-group">
                            <label for="facility_name">Facility name</label>
                            <input type="text" id="facility_name" name="facility_name" placeholder="e.g. Bravo Pickleball" required>
                        </div>
                        <div class="field-group">
                            <label for="facility_address">Facility address</label>
                            <input type="text" id="facility_address" name="facility_address" placeholder="Complete facility address" required>
                        </div>
                        <div class="field-row">
                            <div class="field-group">
                                <label for="municipality">Municipality / City</label>
                                <select id="municipality" name="municipality" required>
                                    <option value="">Select municipality</option>
                                    <?php foreach (["Abucay", "Bagac", "Balanga City", "Dinalupihan", "Hermosa", "Limay", "Mariveles", "Morong", "Orani", "Orion", "Pilar", "Samal"] as $municipality): ?>
                                        <option value="<?= htmlspecialchars($municipality, ENT_QUOTES, "UTF-8") ?>"><?= htmlspecialchars($municipality, ENT_QUOTES, "UTF-8") ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field-group">
                                <label for="facility_type">Facility type</label>
                                <select id="facility_type" name="facility_type" required>
                                    <option value="">Select type</option>
                                    <option value="Badminton">Badminton</option>
                                    <option value="Pickleball">Pickleball</option>
                                    <option value="Both">Both</option>
                                </select>
                            </div>
                        </div>
                    </section>
                    <section class="owner-form-section owner-documents-section">
                        <h2>Verification documents</h2>
                        <p>Upload clear files that verify your identity and authority to operate.</p>
                        <p class="owner-upload-counter" id="requiredUploadCount" aria-live="polite">0 of 4 required documents added</p>
                        <?php
                        $registrationDocuments = [
                            "valid_id" => "Valid government ID",
                            "business_registration" => "DTI / SEC registration",
                            "business_permit" => "Business / Mayor's permit",
                            "proof_of_occupancy" => "Proof of ownership / lease",
                        ];
                        foreach ($registrationDocuments as $inputId => $label):
                        ?>
                            <div class="owner-document-upload-card" data-required-document="<?= htmlspecialchars($inputId, ENT_QUOTES, "UTF-8") ?>">
                                <div class="owner-upload-heading"><strong><?= htmlspecialchars($label, ENT_QUOTES, "UTF-8") ?></strong><span>Required</span></div>
                                <div class="resubmit-file-zone owner-upload-zone">
                                    <input class="resubmit-file-input owner-upload-input" type="file" id="<?= htmlspecialchars($inputId, ENT_QUOTES, "UTF-8") ?>" name="<?= htmlspecialchars($inputId, ENT_QUOTES, "UTF-8") ?>" accept=".pdf,application/pdf" required aria-describedby="<?= htmlspecialchars($inputId, ENT_QUOTES, "UTF-8") ?>_error">
                                    <label class="resubmit-drop-area" for="<?= htmlspecialchars($inputId, ENT_QUOTES, "UTF-8") ?>" tabindex="0"><span class="resubmit-upload-icon" aria-hidden="true">↑</span><span><strong>Choose file</strong><small>or drop it here</small></span><small>PDF ONLY · up to 5 MB</small></label>
                                    <div class="resubmit-file-preview owner-upload-preview" id="<?= htmlspecialchars($inputId, ENT_QUOTES, "UTF-8") ?>_preview" hidden aria-live="polite"></div>
                                    <p class="resubmit-file-error" id="<?= htmlspecialchars($inputId, ENT_QUOTES, "UTF-8") ?>_error" role="alert" hidden></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="owner-document-upload-card owner-photos-card">
                            <div class="owner-upload-heading"><strong>Facility / court photos</strong><span>Required · at least 1</span></div>
                            <div class="resubmit-file-zone owner-upload-zone">
                                <input class="resubmit-file-input owner-upload-input" type="file" id="facility_photos" name="facility_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple required aria-describedby="facility_photos_error facilityPhotosCount">
                                <label class="resubmit-drop-area" for="facility_photos" tabindex="0"><span class="resubmit-upload-icon" aria-hidden="true">↑</span><span><strong>Choose photos</strong><small>or drop them here</small></span><small>JPG, PNG or WEBP · up to 10 MB each · max 5 photos</small></label>
                                <p class="resubmit-file-error" id="facility_photos_error" role="alert" hidden></p>
                            </div>
                            <p class="owner-photo-count" id="facilityPhotosCount" aria-live="polite">0 photos added</p>
                            <div class="owner-photo-grid" id="facilityPhotosPreview" aria-live="polite"></div>
                        </div>
                        <aside class="verification-notice owner-verification-notice">
                            <span aria-hidden="true">i</span><div><strong>Verification notice</strong><p>Your facility will go live after our team reviews your submitted information and documents.</p></div>
                        </aside>
                    </section>
                    <p class="owner-submit-helper" id="registrationSubmitHelp" aria-live="polite">Complete the facility information and add all four documents and at least one photo.</p>
                    <div class="verification-buttons owner-registration-actions">
                        <button type="button" id="backToAccount" class="auth-back">Back</button>
                        <button type="submit" id="registrationSubmit" class="auth-submit" disabled><span class="owner-button-spinner" id="registrationSpinner" aria-hidden="true" hidden></span><span id="registrationSubmitLabel">Submit for review</span></button>
                    </div>
                </div>
            </form>
        </section>
    </main>

    <div class="modal-container owner-otp-overlay" id="modal_container" aria-hidden="true">
        <div class="modal owner-otp-modal">
            <form id="otpForm" class="otp-form" aria-labelledby="ownerOtpTitle">
                <h2 id="ownerOtpTitle">Verify your account</h2>
                <p class="otp-instructions">The 6-digit code has been sent to your email.</p>
                <p class="otp-instructions">This code will expire in 5 minutes.</p>
                <div id="inputs" class="inputs" aria-label="Six-digit verification code">
                    <?php for ($digit = 1; $digit <= 6; $digit++): ?>
                        <input class="input" type="text" name="otp" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="OTP digit <?= $digit ?>" required>
                    <?php endfor; ?>
                </div>
                <p class="owner-otp-error" id="ownerOtpError" role="alert" aria-live="polite"></p>
                <button type="submit" id="submitOtpButton" class="btn">Submit Code</button>
                <p>Didn't receive the code? <a href="#" id="resendOtpLink">Resend code</a> <span id="resendOtpCountdown" aria-live="polite"></span></p>
            </form>
        </div>
    </div>
    <div class="owner-upload-viewer" id="ownerUploadViewer" aria-hidden="true">
        <section class="owner-upload-viewer-dialog" role="dialog" aria-modal="true" aria-labelledby="ownerUploadViewerTitle" tabindex="-1">
            <header><h2 id="ownerUploadViewerTitle">Selected file preview</h2><button type="button" id="ownerUploadViewerClose" aria-label="Close preview">&times;</button></header>
            <div id="ownerUploadViewerContent"></div>
        </section>
    </div>
</body>
</html>
