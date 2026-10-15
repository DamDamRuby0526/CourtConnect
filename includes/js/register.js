const resumeVerification = new URLSearchParams(window.location.search).get("resumeVerification") === "1";
const resumeUserId = new URLSearchParams(window.location.search).get("user_id");
const resendOtpLink = document.getElementById("resendOtpLink");
const resendOtpCountdown = document.getElementById("resendOtpCountdown");
let resendOtpTimer = null;

function startOtpResendCountdown(seconds) {
  window.clearInterval(resendOtpTimer);
  let remaining = seconds;
  resendOtpLink.setAttribute("aria-disabled", "true");
  const updateCountdown = () => {
    const minutes = Math.floor(remaining / 60).toString().padStart(2, "0");
    const secondsRemaining = (remaining % 60).toString().padStart(2, "0");
    resendOtpCountdown.textContent = `(${minutes}:${secondsRemaining})`;
  };
  updateCountdown();
  resendOtpTimer = window.setInterval(() => {
    remaining -= 1;
    if (remaining <= 0) {
      window.clearInterval(resendOtpTimer);
      resendOtpLink.removeAttribute("aria-disabled");
      resendOtpCountdown.textContent = "";
      return;
    }
    updateCountdown();
  }, 1000);
}

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
    resendOtpLink.removeAttribute("aria-disabled");
    resendOtpCountdown.textContent = "";
    alert(sendResult.message);
    return;
  }

  if (sendResult.otp_code) {
    alert(`Development OTP: ${sendResult.otp_code}`);
  }
  startOtpResendCountdown(60);
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
    try {
      const sendResponse = await fetch("includes/backend-api/sendOTP.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ user_id: result.user_id }),
      });
      let sendResult;
      try {
        sendResult = await sendResponse.json();
      } catch {
        throw new Error("The email service did not return a valid response. Please contact support.");
      }
      if (!sendResponse.ok || !sendResult.success) {
        throw new Error(sendResult.message || "Unable to send the verification code.");
      }
      startOtpResendCountdown(60);
    } catch (error) {
      resendOtpLink.removeAttribute("aria-disabled");
      resendOtpCountdown.textContent = "";
      alert(error.message || "Unable to send the verification code.");
    }
  });

resendOtpLink.addEventListener("click", async (event) => {
  event.preventDefault();
  if (resendOtpLink.getAttribute("aria-disabled") === "true") return;
  try {
    const response = await fetch("includes/backend-api/sendOTP.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user_id: document.getElementById("otpForm").dataset.userId }),
    });
    let result;
    try {
      result = await response.json();
    } catch {
      throw new Error("The email service did not return a valid response. Please contact support.");
    }
    if (!response.ok || !result.success) throw new Error(result.message || "Unable to resend the code.");
    startOtpResendCountdown(60);
  } catch (error) {
    alert(error.message || "Unable to resend the code.");
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

    let result;
    try {
      result = await response.json();
    } catch {
      throw new Error("The email service did not return a valid response. Please contact support.");
    }
    if (result.success) {
      document.getElementById("modal_container").classList.remove("show");
      const notification = document.createElement("div");
      const loginRedirectDelay = 2000;
      const hasPendingReservation = document.body.dataset.pendingReservation === "true";
      notification.className = "login-success-overlay";
      notification.style.setProperty("--login-redirect-delay", `${loginRedirectDelay}ms`);
      notification.setAttribute("role", "status");
      const card = document.createElement("div");
      card.className = "login-success-card";
      const icon = document.createElement("span");
      icon.className = "login-success-icon";
      icon.setAttribute("aria-hidden", "true");
      icon.textContent = "\u2713";
      const welcome = document.createElement("strong");
      welcome.textContent = `Welcome, ${result.user.first_name}!`;
      const destinationMessage = document.createElement("small");
      destinationMessage.textContent = `Taking you to ${hasPendingReservation ? "your selected reservation" : "your home page"}.`;
      const loader = document.createElement("span");
      loader.className = "login-success-loader";
      loader.setAttribute("aria-hidden", "true");
      card.append(icon, welcome, destinationMessage, loader);
      notification.appendChild(card);
      document.body.appendChild(notification);

      window.setTimeout(() => {
        window.location.href = hasPendingReservation ? "reservation_resume.php" : "index.php";
      }, loginRedirectDelay);
    } else {
      alert(result.message || "Unable to verify this code.");
    }
  } catch (error) {
    alert("Network error. Please try again.");
    btn.disabled = false;
    btn.innerText = "Submit Code";
  }
});
