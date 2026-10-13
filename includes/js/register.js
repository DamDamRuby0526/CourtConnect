const resumeVerification = new URLSearchParams(window.location.search).get("resumeVerification") === "1";
const resumeUserId = new URLSearchParams(window.location.search).get("user_id");

async function resumeOtpVerification(userId) {
  const otpForm = document.getElementById("otpForm");
  otpForm.dataset.userId = userId;
  document.getElementById("modal_container").classList.add("show");

  const sendResponse = await fetch("includes/backend-api/sendOTP.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ user_id: userId }),
  });

  const sendResult = await sendResponse.json();
  if (!sendResult.success) {
    alert(sendResult.message);
    return;
  }

  if (sendResult.otp_code) {
    alert(`Development OTP: ${sendResult.otp_code}`);
  }
}

document.addEventListener("DOMContentLoaded", async () => {
  if (resumeVerification && resumeUserId) {
    const registerForm = document.getElementById("registerForm");
    if (registerForm) {
      registerForm.style.display = "none";
    }
    await resumeOtpVerification(resumeUserId);
  }
});

document
  .getElementById("registerForm")
  .addEventListener("submit", async (e) => {
    e.preventDefault();

    const form = new FormData(e.target);

    if (form.get("email") !== form.get("confirm_email")) {
      alert("Emails do not match.");
      return;
    }

    if (form.get("password") !== form.get("confirm_password")) {
      alert("Passwords do not match.");
      return;
    }

    //get data from form
    const data = {
      first_name: form.get("first_name"),
      last_name: form.get("last_name"),
      email: form.get("email"),
      phone_number: form.get("phone_number"),
      password: form.get("password"),
    };

    const response = await fetch("includes/backend-api/register.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    });

    const result = await response.json();

    if (!result.success) {
      alert(result.message);
      return;
    }

    // open OTP modal
    const otpForm = document.getElementById("otpForm");
    otpForm.dataset.userId = result.user_id;

    document.getElementById("modal_container").classList.add("show");

    // Automatically send the OTP as soon as the modal opens
    const sendResponse = await fetch("includes/backend-api/sendOTP.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        user_id: result.user_id,
      }),
    });

    const sendResult = await sendResponse.json();

    if (!sendResult.success) {
      alert(sendResult.message);
    }
  });

// Move through the six OTP fields as the user types.

const inputs = document.getElementById("inputs");
const otpInputs = Array.from(inputs.querySelectorAll('input[name="otp"]'));

inputs.addEventListener("input", function (e) {
  const target = e.target;
  const val = target.value.replace(/\D/g, "").slice(-1);

  target.value = val;

  if (val != "") {
    const next = target.nextElementSibling;
    if (next) {
      next.focus();
    }
  }
});

inputs.addEventListener("keyup", function (e) {
  const target = e.target;
  const key = e.key.toLowerCase();

  if (key == "backspace" || key == "delete") {
    target.value = "";
    const prev = target.previousElementSibling;
    if (prev) {
      prev.focus();
    }
    return;
  }
});

// form actions

document.getElementById("otpForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const otpForm = e.target;
  const userId = otpForm.dataset.userId;
  const otp = otpInputs.map((input) => input.value).join("");
  const btn = document.getElementById("submitOtpButton");

  if (otp.length !== 6) {
    alert("Please enter all 6 digits of the code sent to your email.");
    return;
  }

  // disable button after submitting once
  btn.disabled = true;
  btn.innerText = "Submitting...";

  try {
    const response = await fetch("includes/backend-api/verifyOTP.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        user_id: userId,
        otp: otp,
      }),
    });

    const result = await response.json();
    alert(result.message);
    // redirect to home page after successful verification without forcing login again
    if (result.success) {
      const notification = document.createElement("div");
      const loginRedirectDelay = 2000;
      notification.className = "login-success-overlay";
      notification.style.setProperty("--login-redirect-delay", `${loginRedirectDelay}ms`);
      notification.setAttribute("role", "status");
      notification.innerHTML = `
        <div class="login-success-card">
          <span class="login-success-icon" aria-hidden="true">&#10003;</span>
          <strong>Welcome, ${result.user.first_name}!</strong>
          <small>Taking you to your home page.</small>
          <span class="login-success-loader" aria-hidden="true"></span>
        </div>
      `;
      document.body.appendChild(notification);

      window.setTimeout(() => {
        window.location.href = "index.php";
      }, loginRedirectDelay);
    }
  } catch (error) {
    alert("Network error. Please try again.");
    btn.disabled = false;
    btn.innerText = "Submit Code";
  }
});
