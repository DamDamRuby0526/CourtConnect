document.addEventListener("DOMContentLoaded", () => {

  // =========================================================
  // REGISTRATION STEPS
  // =========================================================

  const registerForm = document.getElementById("registerForm");

  const accountStep = document.getElementById("accountStep");
  const verificationStep = document.getElementById("verificationStep");

  const continueButton =
    document.getElementById("continueToVerification");

  const backButton =
    document.getElementById("backToAccount");

  const progressStep1 =
    document.getElementById("progressStep1");

  const progressStep2 =
    document.getElementById("progressStep2");

  const progressLine =
    document.getElementById("progressLine");

  const pendingReview = document.getElementById("pendingReview");
  const pendingReviewTitle = document.getElementById("pendingReviewTitle");
  const pendingReviewMessage = document.getElementById("pendingReviewMessage");
  const backToHomeButton = document.getElementById("backToHome");
  const resumeVerification = new URLSearchParams(window.location.search).get("resumeVerification") === "1";
  let reviewPollingInterval = null;
  let otpRequestInProgress = false;

  function beginReviewWait(userId) {
    registerForm.hidden = true;
    pendingReview.hidden = false;
    document.getElementById("otpForm").dataset.userId = userId;
    checkReviewStatus();
    reviewPollingInterval = window.setInterval(checkReviewStatus, 15000);
  }

  async function checkReviewStatus() {
    try {
      const response = await fetch("includes/backend-api/registration_status.php");
      const result = await response.json();

      if (!result.success) return;

      document.getElementById("otpForm").dataset.userId = result.user_id;

      if (result.status === "Active") {
        window.clearInterval(reviewPollingInterval);

        if (!resumeVerification) {
          pendingReviewTitle.textContent = "Account is Verified";
          pendingReviewMessage.textContent = "Your facility application is approved. Log in to verify your email and access your account.";
          return;
        }

        if (otpRequestInProgress) return;
        otpRequestInProgress = true;
        pendingReviewMessage.textContent = "Your account is approved. Sending your email verification code...";

        const sendResponse = await fetch("includes/backend-api/sendOTP.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ user_id: result.user_id })
        });
        const sendResult = await sendResponse.json();

        if (!sendResult.success) {
          otpRequestInProgress = false;
          pendingReviewMessage.textContent = sendResult.message;
          return;
        }

        pendingReview.hidden = true;
        document.getElementById("modal_container").classList.add("show");
        return;
      }

      if (result.status === "Rejected") {
        window.clearInterval(reviewPollingInterval);
        pendingReviewMessage.textContent = result.rejection_reason
          ? `Your application was not approved: ${result.rejection_reason}`
          : "Your application was not approved.";
        return;
      }

      pendingReviewMessage.textContent = "Your documents are waiting for developer review.";
    } catch (error) {
      pendingReviewMessage.textContent = "Unable to check the review status. Please try again.";
    }
  }

  backToHomeButton.addEventListener("click", () => {
    window.location.href = "index.php";
  });

  fetch("includes/backend-api/registration_status.php")
    .then(response => response.json())
    .then(result => {
      if (result.success) beginReviewWait(result.user_id);
    })
    .catch(() => {});


  // =========================================================
  // STEP 1 → STEP 2
  // =========================================================

  continueButton.addEventListener("click", () => {

    // Check required fields in Step 1
    const accountInputs =
      accountStep.querySelectorAll("input[required]");

    for (const input of accountInputs) {

      if (!input.checkValidity()) {
        input.reportValidity();
        return;
      }

    }


    // Check email
    const email =
      document.getElementById("email").value.trim();

    const confirmEmail =
      document.getElementById("confirm_email").value.trim();

    if (email !== confirmEmail) {
      alert("Emails do not match.");
      return;
    }


    // Check password
    const password =
      document.getElementById("password").value;

    const confirmPassword =
      document.getElementById("confirm_password").value;

    if (password !== confirmPassword) {
      alert("Passwords do not match.");
      return;
    }


    // Move to Step 2
    accountStep.style.display = "none";
    verificationStep.style.display = "block";


    // Update progress bar
    progressStep1.classList.remove("active");
    progressStep1.classList.add("completed");

    progressStep2.classList.add("active");

    progressLine.classList.add("active");

  });


  // =========================================================
  // STEP 2 → STEP 1
  // =========================================================

  backButton.addEventListener("click", () => {

    verificationStep.style.display = "none";
    accountStep.style.display = "block";


    // Reset progress bar
    progressStep1.classList.remove("completed");
    progressStep1.classList.add("active");

    progressStep2.classList.remove("active");

    progressLine.classList.remove("active");

  });


  // =========================================================
  // FINAL REGISTRATION SUBMISSION
  // =========================================================

  registerForm.addEventListener("submit", async (e) => {

    e.preventDefault();


    // Make sure Step 2 fields are valid
    const verificationInputs =
      verificationStep.querySelectorAll("input[required], select[required]");

    for (const input of verificationInputs) {

      if (!input.checkValidity()) {
        input.reportValidity();
        return;
      }

    }


    const form = new FormData(registerForm);

    try {

      const response = await fetch(
        "includes/backend-api/adminregister.php",
        {
          method: "POST",
          body: form
        }
      );


      const result = await response.json();


      if (!result.success) {

        alert(result.message);

        return;

      }


      beginReviewWait(result.user_id);

    } catch (error) {

      console.error("Registration error:", error);

      alert(
        "Network error. Please check your connection and try again."
      );

    }

  });


  // =========================================================
  // OTP INPUTS
  // =========================================================

  const inputs =
    document.getElementById("inputs");

  const otpInputs =
    Array.from(
      inputs.querySelectorAll('input[name="otp"]')
    );


  // Move to next OTP box
  inputs.addEventListener("input", function (e) {

    const target = e.target;

    const val =
      target.value
        .replace(/\D/g, "")
        .slice(-1);


    target.value = val;


    if (val !== "") {

      const next =
        target.nextElementSibling;

      if (next) {
        next.focus();
      }

    }

  });


  // Move back when pressing Backspace/Delete
  inputs.addEventListener("keyup", function (e) {

    const target = e.target;

    const key =
      e.key.toLowerCase();


    if (key === "backspace" || key === "delete") {

      target.value = "";


      const prev =
        target.previousElementSibling;

      if (prev) {
        prev.focus();
      }

    }

  });


  // =========================================================
  // OTP VERIFICATION
  // =========================================================

  document
    .getElementById("otpForm")
    .addEventListener("submit", async (e) => {

      e.preventDefault();


      const otpForm = e.target;

      const userId =
        otpForm.dataset.userId;


      const otp =
        otpInputs
          .map(input => input.value)
          .join("");


      const btn =
        document.getElementById("submitOtpButton");


      if (otp.length !== 6) {

        alert(
          "Please enter all 6 digits of the code sent to your email."
        );

        return;

      }


      // Disable button
      btn.disabled = true;

      btn.innerText = "Submitting...";


      try {

        const response = await fetch(
          "includes/backend-api/verifyOTP.php",
          {
            method: "POST",

            headers: {
              "Content-Type": "application/json"
            },

            body: JSON.stringify({
              user_id: userId,
              otp: otp
            })
          }
        );


        const result =
          await response.json();


        alert(result.message);


        // Redirect if successful
        if (result.success) {

          window.location.href =
            "adminsignin.php";

        } else {

          btn.disabled = false;

          btn.innerText =
            "Submit Code";

        }


      } catch (error) {

        console.error("OTP verification error:", error);

        alert(
          "Network error. Please try again."
        );


        btn.disabled = false;

        btn.innerText =
          "Submit Code";

      }

    });

  document.getElementById("resendOtpLink").addEventListener("click", async (event) => {
    event.preventDefault();
    const userId = document.getElementById("otpForm").dataset.userId;

    try {
      const response = await fetch("includes/backend-api/sendOTP.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: userId })
      });
      const result = await response.json();
      alert(result.message);
    } catch (error) {
      alert("Unable to resend the verification code. Please try again.");
    }
  });

});